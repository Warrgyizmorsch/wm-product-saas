<?php

namespace Database\Seeders;

use App\Core\Branch\BranchContext;
use App\Core\Company\CompanyContext;
use App\Core\Tenant\TenantContext;
use App\Domains\Accounting\FixedAssets\Models\AssetDepreciationSchedule;
use App\Domains\Accounting\FixedAssets\Services\AssetCapitalizationService;
use App\Domains\Accounting\FixedAssets\Services\AssetDepreciationService;
use App\Domains\Accounting\FixedAssets\Services\AssetDisposalService;
use App\Domains\Accounting\FixedAssets\Services\AssetRevaluationService;
use App\Domains\Accounting\FixedAssets\Services\AssetWriteOffService;
use App\Domains\Accounting\Models\BankReconciliation;
use App\Domains\Accounting\Models\BankStatementLine;
use App\Domains\Accounting\Models\Budget;
use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\CostCenter;
use App\Domains\Accounting\Models\ExchangeRate;
use App\Domains\Accounting\Models\FiscalYear;
use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Services\BankReconciliationService;
use App\Domains\Accounting\Services\BudgetService;
use App\Domains\Accounting\Services\ExchangeRateService;
use App\Domains\Accounting\Services\JournalService;
use App\Domains\Accounting\Services\VoucherService;
use App\Domains\Accounting\Support\VoucherType;
use App\Domains\CRM\Models\Customer;
use App\Domains\HRMS\Models\Asset;
use App\Domains\HRMS\Models\AssetCategory;
use App\Domains\HRMS\Models\Branch;
use App\Domains\HRMS\Models\Company;
use App\Domains\Inventory\Models\Vendor;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Populates the existing Demo Tenant's Accounting module (FY2026-27) with
 * realistic Voucher/Journal/Fixed Asset/Bank Reconciliation/Budget data for a
 * client walkthrough. Scoped deliberately to pure-Accounting features —
 * never touches Sales/Purchase invoices.
 *
 * Idempotent: guarded by a `[DEMO]` memo/description prefix and an
 * up-front marker check, so `php artisan db:seed --class=AccountingVoucherDemoSeeder`
 * is safe to re-run.
 */
class AccountingVoucherDemoSeeder extends Seeder
{
    private const MARK = '[DEMO]';

    private int $tenantId;
    private int $companyId;
    private int $branchId;
    private int $userId;

    private JournalService $journalService;
    private VoucherService $voucherService;
    private BudgetService $budgetService;
    private BankReconciliationService $bankRecService;
    private ExchangeRateService $exchangeRateService;
    private AssetCapitalizationService $capitalizationService;
    private AssetDepreciationService $depreciationService;
    private AssetDisposalService $disposalService;
    private AssetWriteOffService $writeOffService;
    private AssetRevaluationService $revaluationService;

    public function run(): void
    {
        $tenant = Tenant::where('slug', config('tenancy.local_fallback_slug', 'demo'))->first()
            ?? Tenant::where('slug', 'demo')->first()
            ?? Tenant::first();

        if (!$tenant) {
            $this->command?->error('No tenant found — aborting.');

            return;
        }

        $this->tenantId = $tenant->id;

        $company = Company::where('tenant_id', $this->tenantId)->first();
        $branch = Branch::where('tenant_id', $this->tenantId)->first();
        $user = User::where('tenant_id', $this->tenantId)->orderBy('id')->first();

        if (!$company || !$branch || !$user) {
            $this->command?->error('Demo tenant is missing a company/branch/user — aborting.');

            return;
        }

        $this->companyId = $company->id;
        $this->branchId = $branch->id;
        $this->userId = $user->id;

        // Console context: tenant/company/branch scoping normally comes from
        // the HTTP request, so it must be seeded explicitly here (see
        // "Child models must inherit tenant from parent" memory note).
        app(TenantContext::class)->set($tenant);
        app(CompanyContext::class)->set($company);
        app(BranchContext::class)->set($branch);

        $this->journalService = app(JournalService::class);
        $this->voucherService = app(VoucherService::class);
        $this->budgetService = app(BudgetService::class);
        $this->bankRecService = app(BankReconciliationService::class);
        $this->exchangeRateService = app(ExchangeRateService::class);
        $this->capitalizationService = app(AssetCapitalizationService::class);
        $this->depreciationService = app(AssetDepreciationService::class);
        $this->disposalService = app(AssetDisposalService::class);
        $this->writeOffService = app(AssetWriteOffService::class);
        $this->revaluationService = app(AssetRevaluationService::class);

        $fiscalYear = FiscalYear::where('tenant_id', $this->tenantId)
            ->where('company_id', $this->companyId)
            ->orderByDesc('id')
            ->first()
            ?? FiscalYear::where('tenant_id', $this->tenantId)->orderByDesc('id')->first();

        if (!$fiscalYear) {
            $this->command?->error('No fiscal year found for demo tenant — aborting.');

            return;
        }

        if (Journal::where('tenant_id', $this->tenantId)->where('memo', 'like', self::MARK . '%')->exists()) {
            $this->command?->warn('Demo vouchers/journals already seeded — skipping voucher/journal block.');
        } else {
            $costCenters = $this->costCenters();
            [$customers, $vendors] = $this->parties();
            $accounts = $this->accountsByCode();

            $this->seedVouchersAndJournals($accounts, $costCenters, $customers, $vendors);
        }

        $this->seedExchangeRates();
        $categories = $this->assetCategories();
        $assets = $this->seedAssets($categories);
        $this->seedDepreciation($assets);
        $this->seedDisposalWriteOffRevaluation($assets);
        $this->seedBankReconciliation($this->accountsByCode());
        $this->seedBudget($fiscalYear, $this->accountsByCode(), $this->costCenters());

        $this->command?->info('AccountingVoucherDemoSeeder complete.');
    }

    // ------------------------------------------------------------------
    // Master data
    // ------------------------------------------------------------------

    /** @return \Illuminate\Support\Collection<int, CostCenter> */
    private function costCenters(): \Illuminate\Support\Collection
    {
        $defs = [
            ['code' => 'SALES', 'name' => 'Sales Department'],
            ['code' => 'PRODUCTION', 'name' => 'Production'],
            ['code' => 'ADMIN', 'name' => 'Admin Block'],
        ];

        foreach ($defs as $def) {
            CostCenter::withoutGlobalScopes()->firstOrCreate(
                ['tenant_id' => $this->tenantId, 'code' => $def['code']],
                [
                    'company_id' => $this->companyId,
                    'branch_id' => $this->branchId,
                    'name' => $def['name'],
                    'is_active' => true,
                ]
            );
        }

        return CostCenter::where('tenant_id', $this->tenantId)->get();
    }

    /**
     * @return array{0: \Illuminate\Support\Collection<int, Customer>, 1: \Illuminate\Support\Collection<int, Vendor>}
     */
    private function parties(): array
    {
        $customers = Customer::where('tenant_id', $this->tenantId)->get();

        if ($customers->isEmpty()) {
            $defs = [
                ['name' => 'Bluewave Retail Pvt Ltd', 'email' => 'accounts@bluewaveretail.example', 'gstin' => '27AAAAA0000A1Z5'],
                ['name' => 'Sunrise Traders', 'email' => 'finance@sunrisetraders.example', 'gstin' => '27BBBBB0000B1Z4'],
                ['name' => 'Greenfield Distributors', 'email' => 'ap@greenfielddist.example', 'gstin' => null],
            ];

            // Skip Customer's auto CRM-account creation: that path currently
            // fails on a pre-existing schema mismatch (crm_accounts.billing_address)
            // unrelated to this Accounting-only seeding task.
            Customer::$skipAccountAutoCreate = true;

            foreach ($defs as $def) {
                Customer::create([
                    'tenant_id' => $this->tenantId,
                    'company_id' => $this->companyId,
                    'branch_id' => $this->branchId,
                    'name' => $def['name'],
                    'company_name' => $def['name'],
                    'email' => $def['email'],
                    'gstin' => $def['gstin'],
                    'status' => 'active',
                    'opening_balance' => 0,
                ]);
            }

            Customer::$skipAccountAutoCreate = false;

            $customers = Customer::where('tenant_id', $this->tenantId)->get();
        }

        $vendors = Vendor::where('tenant_id', $this->tenantId)->get();

        return [$customers, $vendors];
    }

    /** @return array<string, ChartOfAccount> keyed by code */
    private function accountsByCode(): array
    {
        return ChartOfAccount::where('tenant_id', $this->tenantId)->get()->keyBy('code')->all();
    }

    private function seedExchangeRates(): void
    {
        if (ExchangeRate::where('tenant_id', $this->tenantId)->exists()) {
            return;
        }

        $today = Carbon::now();
        $pairs = [
            ['from' => 'USD', 'to' => 'INR', 'base' => 83.20, 'step' => 0.05],
            ['from' => 'EUR', 'to' => 'INR', 'base' => 90.10, 'step' => 0.07],
        ];

        foreach ($pairs as $pair) {
            for ($i = 6; $i >= 0; $i--) {
                $date = $today->copy()->subDays($i);
                $rate = round($pair['base'] + ($pair['step'] * (6 - $i)), 4);
                $isAutoSynced = $i === 0; // most recent one flagged as auto-synced

                $attrs = [
                    'tenant_id' => $this->tenantId,
                    'from_currency' => $pair['from'],
                    'to_currency' => $pair['to'],
                    'rate' => $rate,
                    'effective_date' => $date->toDateString(),
                    'source' => $isAutoSynced ? ExchangeRate::SOURCE_API : ExchangeRate::SOURCE_MANUAL,
                    'created_by' => $isAutoSynced ? null : $this->userId,
                ];

                ExchangeRate::withoutGlobalScopes()->firstOrCreate(
                    [
                        'tenant_id' => $this->tenantId,
                        'from_currency' => $pair['from'],
                        'to_currency' => $pair['to'],
                        'effective_date' => $date->toDateString(),
                    ],
                    $attrs
                );
            }
        }
    }

    /** @return array<string, AssetCategory> keyed by name */
    private function assetCategories(): array
    {
        $existing = AssetCategory::where('company_id', $this->companyId)->get()->keyBy('name');

        $defs = [
            'Computer Equipment' => ['default_useful_life_months' => 36, 'default_depreciation_method' => Asset::DEPRECIATION_METHOD_STRAIGHT_LINE, 'default_residual_value_percent' => 5],
            'Furniture & Fixtures' => ['default_useful_life_months' => 96, 'default_depreciation_method' => Asset::DEPRECIATION_METHOD_STRAIGHT_LINE, 'default_residual_value_percent' => 5],
            'Vehicles' => ['default_useful_life_months' => 60, 'default_depreciation_method' => Asset::DEPRECIATION_METHOD_WDV, 'default_residual_value_percent' => 10],
            'Office Equipment' => ['default_useful_life_months' => 60, 'default_depreciation_method' => Asset::DEPRECIATION_METHOD_STRAIGHT_LINE, 'default_residual_value_percent' => 5],
        ];

        foreach ($defs as $name => $attrs) {
            if ($existing->has($name)) {
                continue;
            }

            AssetCategory::create($attrs + [
                'company_id' => $this->companyId,
                'name' => $name,
                'status' => AssetCategory::STATUS_ACTIVE,
                'capitalization_threshold' => 5000,
            ]);
        }

        // Reuse pre-existing "IT Hardware"/"Office Furniture" categories too if present,
        // so we don't fragment the register with near-duplicates.
        return AssetCategory::where('company_id', $this->companyId)->get()->keyBy('name')->all();
    }

    // ------------------------------------------------------------------
    // Vouchers & Journals
    // ------------------------------------------------------------------

    private function seedVouchersAndJournals(array $accounts, \Illuminate\Support\Collection $costCenters, \Illuminate\Support\Collection $customers, \Illuminate\Support\Collection $vendors): void
    {
        $cash = $accounts['1010'];
        $bank = $accounts['1020'];
        $hdfc = $accounts['1021'];
        $ar = $accounts['1100'];
        $ap = $accounts['2010'];
        $salesRevenue = $accounts['4010'];
        $rentExpense = $accounts['5200'];
        $officeSupplies = $accounts['5520'];
        $travel = $accounts['5700'];
        $miscIncome = $accounts['4900'];
        $salesReturns = $accounts['4030'];
        $purchaseReturns = $accounts['5022'];

        $sales = $costCenters->firstWhere('code', 'SALES');
        $admin = $costCenters->firstWhere('code', 'ADMIN');

        $vendorNames = $vendors->pluck('name', 'id');
        $customerNames = $customers->pluck('name', 'id');

        $months = [Carbon::create(2026, 7, 1), Carbon::create(2026, 8, 1), Carbon::create(2026, 9, 1)];

        // --- Receipt Vouchers (customer collections) ---
        $receiptDefs = [
            ['day' => 5, 'amount' => 125000, 'customer' => $customers->get(0)],
            ['day' => 12, 'amount' => 87500, 'customer' => $customers->get(1)],
            ['day' => 20, 'amount' => 64000, 'customer' => $customers->get(2)],
            ['day' => 8, 'amount' => 152000, 'customer' => $customers->get(0)],
            ['day' => 15, 'amount' => 45000, 'customer' => $customers->get(1)],
        ];

        $reversalTarget = null;

        foreach ($receiptDefs as $i => $def) {
            $month = $months[$i % count($months)];
            $date = $month->copy()->day(min($def['day'], $month->daysInMonth));
            $customer = $def['customer'];

            $journal = $this->voucherService->post(VoucherType::RECEIPT, [
                ['chart_of_account_id' => $bank->id, 'debit' => $def['amount'], 'description' => self::MARK . ' Receipt from ' . ($customer?->name ?? 'Customer')],
                ['chart_of_account_id' => $ar->id, 'credit' => $def['amount'], 'party_type' => JournalEntry::PARTY_CUSTOMER, 'party_id' => $customer?->id, 'description' => self::MARK . ' Receipt from ' . ($customer?->name ?? 'Customer')],
            ], [
                'journal_date' => $date,
                'memo' => self::MARK . ' Receipt from ' . ($customer?->name ?? 'Customer'),
                'party_type' => JournalEntry::PARTY_CUSTOMER,
                'party_id' => $customer?->id,
                'party_name' => $customer?->name,
                'payment_method' => 'bank_transfer',
                'reference_no' => 'RCPT-REF-' . ($i + 1),
                'posted_by' => $this->userId,
            ]);

            if ($i === 0) {
                $reversalTarget = $journal;
            }
        }

        // --- Payment Vouchers (vendor payments + expenses) ---
        $paymentDefs = [
            ['day' => 4, 'amount' => 98000, 'vendor' => $vendors->get(0), 'account' => $ap],
            ['day' => 10, 'amount' => 32000, 'vendor' => null, 'account' => $rentExpense, 'label' => 'Office rent', 'cost_center' => $admin],
            ['day' => 18, 'amount' => 56000, 'vendor' => $vendors->get(1), 'account' => $ap],
            ['day' => 22, 'amount' => 12500, 'vendor' => null, 'account' => $officeSupplies, 'label' => 'Office supplies'],
            ['day' => 27, 'amount' => 74000, 'vendor' => $vendors->get(2), 'account' => $ap],
        ];

        foreach ($paymentDefs as $i => $def) {
            $month = $months[$i % count($months)];
            $date = $month->copy()->day(min($def['day'], $month->daysInMonth));
            $vendor = $def['vendor'];
            $label = $def['label'] ?? ('Payment to ' . ($vendor?->name ?? 'Vendor'));

            $line2 = ['chart_of_account_id' => $def['account']->id, 'debit' => $def['amount'], 'description' => self::MARK . ' ' . $label];
            if ($vendor) {
                $line2['party_type'] = JournalEntry::PARTY_VENDOR;
                $line2['party_id'] = $vendor->id;
            }
            if (!empty($def['cost_center'])) {
                $line2['cost_center_id'] = $def['cost_center']->id;
            }

            $this->voucherService->post(VoucherType::PAYMENT, [
                $line2,
                ['chart_of_account_id' => $bank->id, 'credit' => $def['amount'], 'description' => self::MARK . ' ' . $label],
            ], [
                'journal_date' => $date,
                'memo' => self::MARK . ' ' . $label,
                'party_type' => $vendor ? JournalEntry::PARTY_VENDOR : null,
                'party_id' => $vendor?->id,
                'party_name' => $vendor?->name,
                'payment_method' => 'bank_transfer',
                'reference_no' => 'PAY-REF-' . ($i + 1),
                'posted_by' => $this->userId,
            ]);
        }

        // --- Contra Vouchers (cash <-> bank transfers) ---
        $contraDefs = [
            ['day' => 2, 'amount' => 20000, 'direction' => 'bank_to_cash'],
            ['day' => 16, 'amount' => 15000, 'direction' => 'cash_to_bank'],
        ];

        foreach ($contraDefs as $i => $def) {
            $month = $months[$i % count($months)];
            $date = $month->copy()->day(min($def['day'], $month->daysInMonth));

            [$debitAccount, $creditAccount] = $def['direction'] === 'bank_to_cash'
                ? [$cash, $bank]
                : [$bank, $cash];

            $this->voucherService->post(VoucherType::CONTRA, [
                ['chart_of_account_id' => $debitAccount->id, 'debit' => $def['amount'], 'description' => self::MARK . ' Contra transfer'],
                ['chart_of_account_id' => $creditAccount->id, 'credit' => $def['amount'], 'description' => self::MARK . ' Contra transfer'],
            ], [
                'journal_date' => $date,
                'memo' => self::MARK . ' Contra transfer (' . $def['direction'] . ')',
                'reference_no' => 'CTR-REF-' . ($i + 1),
                'posted_by' => $this->userId,
            ]);
        }

        // --- Credit Notes (sales returns / allowances to customers) ---
        $creditNoteDefs = [
            ['day' => 6, 'amount' => 18500, 'customer' => $customers->get(0)],
            ['day' => 21, 'amount' => 9200, 'customer' => $customers->get(2)],
        ];

        foreach ($creditNoteDefs as $i => $def) {
            $month = $months[$i % count($months)];
            $date = $month->copy()->day(min($def['day'], $month->daysInMonth));
            $customer = $def['customer'];

            $this->voucherService->post(VoucherType::CREDIT_NOTE, [
                ['chart_of_account_id' => $salesReturns->id, 'debit' => $def['amount'], 'description' => self::MARK . ' Credit note to ' . ($customer?->name ?? 'Customer')],
                ['chart_of_account_id' => $ar->id, 'credit' => $def['amount'], 'party_type' => JournalEntry::PARTY_CUSTOMER, 'party_id' => $customer?->id, 'description' => self::MARK . ' Credit note to ' . ($customer?->name ?? 'Customer')],
            ], [
                'journal_date' => $date,
                'memo' => self::MARK . ' Credit note to ' . ($customer?->name ?? 'Customer'),
                'party_type' => JournalEntry::PARTY_CUSTOMER,
                'party_id' => $customer?->id,
                'party_name' => $customer?->name,
                'reference_no' => 'CN-REF-' . ($i + 1),
                'posted_by' => $this->userId,
            ]);
        }

        // --- Debit Notes (purchase returns / allowances from vendors) ---
        $debitNoteDefs = [
            ['day' => 9, 'amount' => 14000, 'vendor' => $vendors->get(0)],
            ['day' => 24, 'amount' => 7600, 'vendor' => $vendors->get(1)],
        ];

        foreach ($debitNoteDefs as $i => $def) {
            $month = $months[$i % count($months)];
            $date = $month->copy()->day(min($def['day'], $month->daysInMonth));
            $vendor = $def['vendor'];

            $this->voucherService->post(VoucherType::DEBIT_NOTE, [
                ['chart_of_account_id' => $ap->id, 'debit' => $def['amount'], 'party_type' => JournalEntry::PARTY_VENDOR, 'party_id' => $vendor?->id, 'description' => self::MARK . ' Debit note to ' . ($vendor?->name ?? 'Vendor')],
                ['chart_of_account_id' => $purchaseReturns->id, 'credit' => $def['amount'], 'description' => self::MARK . ' Debit note to ' . ($vendor?->name ?? 'Vendor')],
            ], [
                'journal_date' => $date,
                'memo' => self::MARK . ' Debit note to ' . ($vendor?->name ?? 'Vendor'),
                'party_type' => JournalEntry::PARTY_VENDOR,
                'party_id' => $vendor?->id,
                'party_name' => $vendor?->name,
                'reference_no' => 'DN-REF-' . ($i + 1),
                'posted_by' => $this->userId,
            ]);
        }

        // --- Manual Journals via JournalService ---
        $accrualDate = $months[1]->copy()->day(30);
        $this->journalService->post([
            ['chart_of_account_id' => $rentExpense->id, 'debit' => 32000, 'cost_center_id' => $admin?->id, 'description' => self::MARK . ' Accrual - August rent'],
            ['chart_of_account_id' => $accounts['2070']->id, 'credit' => 32000, 'description' => self::MARK . ' Accrual - August rent'],
        ], [
            'journal_date' => $accrualDate,
            'memo' => self::MARK . ' Accrual - office rent not yet invoiced',
            'posted_by' => $this->userId,
        ]);

        $openingBalanceDate = $months[0]->copy()->day(1);
        $this->journalService->post([
            ['chart_of_account_id' => $accounts['3020']->id, 'debit' => 50000, 'description' => self::MARK . ' Opening balance adjustment'],
            ['chart_of_account_id' => $bank->id, 'credit' => 50000, 'description' => self::MARK . ' Opening balance adjustment'],
        ], [
            'journal_date' => $openingBalanceDate,
            'memo' => self::MARK . ' Opening balance adjustment - reserves correction',
            'posted_by' => $this->userId,
        ]);

        $writeOffDate = $months[2]->copy()->day(28);
        $this->journalService->post([
            ['chart_of_account_id' => $accounts['5900']->id, 'debit' => 8400, 'cost_center_id' => $sales?->id, 'description' => self::MARK . ' Write-off of stale advance'],
            ['chart_of_account_id' => $accounts['1420']->id, 'credit' => 8400, 'description' => self::MARK . ' Write-off of stale advance'],
        ], [
            'journal_date' => $writeOffDate,
            'memo' => self::MARK . ' Write-off - stale employee advance',
            'posted_by' => $this->userId,
        ]);

        // --- Reversal (real reversal action, not a raw DB flip) ---
        if ($reversalTarget) {
            $this->journalService->reverse($reversalTarget->id, self::MARK . ' Reversed - receipt posted in error', $this->userId);
        }
    }

    // ------------------------------------------------------------------
    // Fixed Assets
    // ------------------------------------------------------------------

    /** @return array<int, Asset> */
    private function seedAssets(array $categories): array
    {
        $marker = self::MARK . ' seed asset';

        $existing = Asset::where('company_id', $this->companyId)
            ->where('notes', 'like', self::MARK . '%')
            ->get();

        if ($existing->isNotEmpty()) {
            return $existing->all();
        }

        $computerCat = $categories['Computer Equipment'] ?? $categories['IT Hardware'] ?? null;
        $furnitureCat = $categories['Furniture & Fixtures'] ?? $categories['Office Furniture'] ?? null;
        $vehicleCat = $categories['Vehicles'] ?? null;
        $officeCat = $categories['Office Equipment'] ?? null;

        $defs = [
            ['code' => 'DEMO-AST-001', 'name' => 'Dell Precision Workstation', 'category' => $computerCat, 'cost' => 145000, 'purchase_date' => '2026-04-15', 'life' => 36],
            ['code' => 'DEMO-AST-002', 'name' => 'Executive Office Desk Set', 'category' => $furnitureCat, 'cost' => 68000, 'purchase_date' => '2026-04-20', 'life' => 96],
            ['code' => 'DEMO-AST-003', 'name' => 'Mahindra Bolero (Delivery Van)', 'category' => $vehicleCat, 'cost' => 950000, 'purchase_date' => '2026-05-01', 'life' => 60],
            ['code' => 'DEMO-AST-004', 'name' => 'Canon imageRUNNER Printer', 'category' => $officeCat, 'cost' => 185000, 'purchase_date' => '2026-05-10', 'life' => 60],
            ['code' => 'DEMO-AST-005', 'name' => 'Conference Room AV System', 'category' => $officeCat, 'cost' => 220000, 'purchase_date' => '2026-05-15', 'life' => 60],
        ];

        $assets = [];

        foreach ($defs as $def) {
            if ($def['category'] === null) {
                continue;
            }

            $asset = Asset::create([
                'company_id' => $this->companyId,
                'branch_id' => $this->branchId,
                'asset_category_id' => $def['category']->id,
                'asset_code' => $def['code'],
                'name' => $def['name'],
                'purchase_date' => $def['purchase_date'],
                'purchase_cost' => $def['cost'],
                'status' => Asset::STATUS_DRAFT,
                'notes' => $marker,
                'condition' => 'new',
                'created_by' => $this->userId,
            ]);

            $asset = $this->capitalizationService->capitalize($asset, [
                'acquisition_cost' => $def['cost'],
                'useful_life_months' => $def['life'],
                'capitalization_date' => $def['purchase_date'],
                'depreciation_start_date' => $def['purchase_date'],
            ], $this->userId);

            $assets[] = $asset;
        }

        return $assets;
    }

    /** @param array<int, Asset> $assets */
    private function seedDepreciation(array $assets): void
    {
        if (empty($assets)) {
            return;
        }

        // Deliberately mixed workflow states across three consecutive months so
        // the full draft -> review -> approve -> post lifecycle can be demoed:
        //   July   -> posted   (real numbers already on the ledger)
        //   August -> approved (ready for the "Post" action live)
        //   Sept   -> reviewed for most assets, one left in draft for the
        //             "Review" step, one left ungenerated for "Generate".
        $periods = [
            ['year' => 2026, 'month' => 7, 'target' => 'posted'],
            ['year' => 2026, 'month' => 8, 'target' => 'approved'],
            ['year' => 2026, 'month' => 9, 'target' => 'reviewed'],
        ];

        foreach ($assets as $index => $asset) {
            $asset = $asset->fresh();

            if ($asset->status !== Asset::STATUS_ACTIVE) {
                continue;
            }

            foreach ($periods as $pIndex => $period) {
                // Leave the last asset's September schedule ungenerated (draft
                // workflow entry point) to demo "Generate" live.
                if ($pIndex === 2 && $index === count($assets) - 1) {
                    continue;
                }

                $existing = AssetDepreciationSchedule::where('asset_id', $asset->id)
                    ->where('period_year', $period['year'])
                    ->where('period_month', $period['month'])
                    ->first();

                if ($existing) {
                    $schedule = $existing;
                } else {
                    try {
                        $schedule = $this->depreciationService->generateSchedule($asset, $period['year'], $period['month'], $this->userId);
                    } catch (\InvalidArgumentException $e) {
                        continue;
                    }
                }

                $target = $index === count($assets) - 1 && $pIndex === 1 ? 'draft' : $period['target'];

                if ($target === 'draft') {
                    continue;
                }

                if (in_array($schedule->status, [AssetDepreciationSchedule::STATUS_REVIEWED, AssetDepreciationSchedule::STATUS_APPROVED, AssetDepreciationSchedule::STATUS_POSTED], true) === false) {
                    $schedule = $this->depreciationService->review($schedule, $this->userId);
                }

                if ($target === 'reviewed') {
                    continue;
                }

                if (in_array($schedule->status, [AssetDepreciationSchedule::STATUS_APPROVED, AssetDepreciationSchedule::STATUS_POSTED], true) === false) {
                    $schedule = $this->depreciationService->approve($schedule, $this->userId);
                }

                if ($target === 'approved') {
                    continue;
                }

                if ($schedule->status !== AssetDepreciationSchedule::STATUS_POSTED) {
                    $this->depreciationService->post($schedule, $this->userId);
                }
            }
        }
    }

    /** @param array<int, Asset> $assets */
    private function seedDisposalWriteOffRevaluation(array $assets): void
    {
        if (count($assets) < 3) {
            return;
        }

        // Disposal: sold, fully posted -> shows a real outcome.
        $disposalAsset = $assets[3]->fresh();
        if ($disposalAsset && !$disposalAsset->disposals()->exists()) {
            $disposal = $this->disposalService->dispose($disposalAsset, [
                'disposal_type' => 'sale',
                'disposal_date' => '2026-09-10',
                'sale_proceeds' => 150000,
                'tax_amount' => 0,
                'remarks' => self::MARK . ' Sold - upgraded to newer model',
            ], $this->userId);
            $disposal = $this->disposalService->approve($disposal, $this->userId);
            $this->disposalService->post($disposal, $this->userId);
        }

        // Write-off: pending approval -> demo the approval step live.
        $writeOffAsset = $assets[1]->fresh();
        if ($writeOffAsset && !$writeOffAsset->writeOffs()->exists()) {
            $this->writeOffService->writeOff($writeOffAsset, [
                'write_off_date' => '2026-09-14',
                'reason' => self::MARK . ' Damaged beyond repair',
            ], $this->userId);
        }

        // Revaluation: approved and posted -> shows updated book value.
        $revalAsset = $assets[2]->fresh();
        if ($revalAsset && !$revalAsset->revaluations()->exists()) {
            $revaluation = $this->revaluationService->revalue($revalAsset, [
                'revaluation_date' => '2026-09-05',
                'revalued_amount' => (float) $revalAsset->book_value + 80000,
                'reason' => self::MARK . ' Market appreciation on revaluation survey',
            ], $this->userId);
            $revaluation = $this->revaluationService->approve($revaluation, $this->userId);
            $this->revaluationService->post($revaluation, $this->userId);
        }
    }

    // ------------------------------------------------------------------
    // Bank Reconciliation
    // ------------------------------------------------------------------

    private function seedBankReconciliation(array $accounts): void
    {
        $hdfc = $accounts['1021'] ?? null;

        if ($hdfc === null) {
            return;
        }

        $existing = BankReconciliation::where('tenant_id', $this->tenantId)
            ->where('chart_of_account_id', $hdfc->id)
            ->first();

        if ($existing) {
            return;
        }

        $reconciliation = $this->bankRecService->start([
            'tenant_id' => $this->tenantId,
            'company_id' => $this->companyId,
            'branch_id' => $this->branchId,
            'chart_of_account_id' => $hdfc->id,
            'statement_date' => '2026-09-15',
            'opening_balance' => 0,
            'closing_balance' => 0,
        ]);

        $lines = [
            ['date' => '2026-09-02', 'description' => self::MARK . ' NEFT Credit - Customer', 'amount' => 45000, 'matched' => false],
            ['date' => '2026-09-06', 'description' => self::MARK . ' Cheque deposit', 'amount' => 30000, 'matched' => false],
            ['date' => '2026-09-09', 'description' => self::MARK . ' Vendor payment debit', 'amount' => -12500, 'matched' => false],
            ['date' => '2026-09-13', 'description' => self::MARK . ' Bank charges', 'amount' => -450, 'matched' => false],
        ];

        foreach ($lines as $line) {
            BankStatementLine::create([
                'tenant_id' => $this->tenantId,
                'bank_reconciliation_id' => $reconciliation->id,
                'transaction_date' => $line['date'],
                'description' => $line['description'],
                'amount' => $line['amount'],
                'is_matched' => false,
            ]);
        }

        // Auto-match whatever lines up against existing ledger entries on this
        // account (likely none yet, since demo vouchers used Bank/Cash — codes
        // 1010/1020 — rather than HDFC); leaves the rest as exceptions to demo
        // the unmatched-line UI, and the reconciliation itself stays in_progress
        // so the "complete" action can be demoed live.
        $this->bankRecService->autoMatch($reconciliation);
    }

    // ------------------------------------------------------------------
    // Budget
    // ------------------------------------------------------------------

    private function seedBudget(FiscalYear $fiscalYear, array $accounts, \Illuminate\Support\Collection $costCenters): void
    {
        if (Budget::where('tenant_id', $this->tenantId)->where('name', 'like', self::MARK . '%')->exists()) {
            return;
        }

        $sales = $costCenters->firstWhere('code', 'SALES');
        $admin = $costCenters->firstWhere('code', 'ADMIN');

        $lines = [
            // Income target - actual receipts (~473,500) will land under budget.
            ['chart_of_account_id' => $accounts['4010']->id, 'amount' => 600000],
            // Rent - actual 64,000 (2 x 32,000) vs budget 60,000 -> over budget.
            ['chart_of_account_id' => $accounts['5200']->id, 'amount' => 60000, 'cost_center_id' => $admin?->id],
            // Office supplies - actual 12,500 vs budget 15,000 -> under/on-target.
            ['chart_of_account_id' => $accounts['5520']->id, 'amount' => 15000, 'cost_center_id' => $admin?->id],
            // Travel - no actuals posted -> clean under-budget line.
            ['chart_of_account_id' => $accounts['5700']->id, 'amount' => 40000, 'cost_center_id' => $sales?->id],
            // Depreciation expense - close to actual -> on-target band.
            ['chart_of_account_id' => $accounts['5400']->id, 'amount' => 65000],
        ];

        $budget = $this->budgetService->create([
            'tenant_id' => $this->tenantId,
            'company_id' => $this->companyId,
            'branch_id' => $this->branchId,
            'fiscal_year_id' => $fiscalYear->id,
            'name' => self::MARK . ' FY2026-27 Operating Budget',
            'created_by' => $this->userId,
        ], $lines);

        $this->budgetService->approve($budget, $this->userId);
    }
}
