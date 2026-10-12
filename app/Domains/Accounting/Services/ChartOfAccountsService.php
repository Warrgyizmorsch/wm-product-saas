<?php

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Repositories\ChartOfAccountRepositoryInterface;
use App\Domains\Accounting\Support\SystemAccount;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class ChartOfAccountsService
{
    public function __construct(
        private readonly ChartOfAccountRepositoryInterface $accounts,
        private readonly JournalService $journals,
        private readonly AccountResolverService $accountResolver,
    ) {
    }

    public function list(array $filters = []): Collection
    {
        return $this->accounts->getAll($filters);
    }

    public function active(): Collection
    {
        return $this->accounts->getActive();
    }

    public function ofType(string $type): Collection
    {
        return $this->accounts->getByType($type);
    }

    public function find(int $id): ?ChartOfAccount
    {
        return $this->accounts->find($id);
    }

    public function create(array $data): ChartOfAccount
    {
        $tenantId = $data['tenant_id'] ?? tenant_id();

        if ($this->accounts->findByCode($data['code'], $tenantId) !== null) {
            throw new InvalidArgumentException("Account code '{$data['code']}' already exists.");
        }

        return $this->accounts->create($data);
    }

    public function update(int $id, array $data): ChartOfAccount
    {
        $account = $this->accounts->find($id);

        if ($account === null) {
            throw new InvalidArgumentException('Chart of account not found.');
        }

        // Posting logic across Sales, Purchase, HRMS, Inventory and Accounting
        // still finds system accounts by code, so renumbering one would make
        // auto-posting silently miss it. Lift this once every lookup goes
        // through SystemAccount (system_key) instead of the code.
        if ($account->is_system && isset($data['code']) && (string) $data['code'] !== (string) $account->code) {
            throw new InvalidArgumentException(
                "The code of system account '{$account->code}' can't be changed, because automatic postings depend on it."
            );
        }

        if (isset($data['code']) && $this->accounts->findByCode($data['code'], $account->tenant_id, $id) !== null) {
            throw new InvalidArgumentException("Account code '{$data['code']}' already exists.");
        }

        return $this->accounts->update($id, $data);
    }

    public function delete(int $id): bool
    {
        $account = $this->accounts->find($id);

        if ($account === null) {
            throw new InvalidArgumentException('Chart of account not found.');
        }

        if ($account->is_system) {
            throw new InvalidArgumentException('System-seeded accounts cannot be deleted.');
        }

        if ($account->journalEntries()->exists()) {
            throw new InvalidArgumentException('Account has posted journal entries and cannot be deleted.');
        }

        return $this->accounts->delete($id);
    }

    /**
     * Keeps the GL in sync with a ledger's opening_balance field: reverses any
     * previously-posted opening-balance journal for this account (if the
     * balance changed) and posts a fresh one against a suspense/equity offset
     * account, so opening balances flow through the same double-entry ledger
     * that Trial Balance/Balance Sheet read from, rather than being a field
     * that reports would have to special-case.
     *
     * Posted with today's date (not the fiscal year start) since a tenant's
     * accounting periods may not yet cover that back-date — this keeps ledger
     * creation from failing when period setup is incomplete. Failures here are
     * logged and swallowed rather than propagated, since a missing opening
     * journal shouldn't block basic Chart of Accounts CRUD.
     */
    public function syncOpeningBalance(ChartOfAccount $account): void
    {
        try {
            $existing = $this->journals->activePosting((int) $account->tenant_id, 'chart_of_account_opening_balance', $account->id);

            $balance = round((float) $account->opening_balance, 2);

            if ($existing && round((float) $existing->total_debit, 2) !== $balance) {
                $this->journals->reverse($existing->id, 'Opening balance changed');
                $existing = null;
            }

            if ($balance <= 0 || $existing) {
                return;
            }

            $suspense = $this->accountResolver->resolveAccount(
                identifier: null,
                tenantId: $account->tenant_id,
                fallbackCode: '3020',
                fallbackType: ChartOfAccount::TYPE_EQUITY,
            );

            if (!$suspense) {
                return;
            }

            $isDebitOpening = $account->opening_balance_type === ChartOfAccount::BALANCE_DEBIT;

            $this->journals->postOnce([
                [
                    'chart_of_account_id' => $account->id,
                    'debit' => $isDebitOpening ? $balance : 0,
                    'credit' => $isDebitOpening ? 0 : $balance,
                    'description' => "Opening balance for {$account->code} {$account->name}",
                ],
                [
                    'chart_of_account_id' => $suspense->id,
                    'debit' => $isDebitOpening ? 0 : $balance,
                    'credit' => $isDebitOpening ? $balance : 0,
                    'description' => "Opening balance offset for {$account->code} {$account->name}",
                ],
            ], [
                'tenant_id' => $account->tenant_id,
                'journal_date' => now(),
                'source' => \App\Domains\Accounting\Models\Journal::SOURCE_MANUAL,
                'reference_type' => 'chart_of_account_opening_balance',
                'reference_id' => $account->id,
                'memo' => "Opening balance — {$account->code} {$account->name}",
            ]);
        } catch (\Throwable $e) {
            Log::warning('ChartOfAccountsService::syncOpeningBalance failed', [
                'chart_of_account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * The standard default Chart of Accounts every tenant starts with —
     * called both at tenant creation (TenantService::create()) and by
     * AccountingChartOfAccountsSeeder for local/demo data. Idempotent
     * (updateOrCreate keyed on tenant_id + code), safe to re-run.
     *
     * Aligned to the Chart_of_Accounts_Master_Oltao.xlsx reference sheet
     * (Indian Tally/Busy-style group structure). Codes already relied on
     * elsewhere in the codebase by hardcoded lookup — 1010, 1020, 1100,
     * 1200, 1400, 1410, 1600, 2010, 2020, 2100, 2110-2130, 2140, 2150,
     * 2200, 3010, 3020, 4010, 4020, 4900, 5010, 5900 — keep their original
     * code, type and normal_balance so every existing auto-posting listener
     * (SalesAccountingService, PostPurchaseBillJournal, PostSalesReturnJournal,
     * PostCustomerPaymentJournal, PostVendorPaymentJournal, StockService, the
     * HRMS Travel & Expense controller, etc.) keeps resolving the same
     * accounts; only their display name/subtype was adjusted where the sheet
     * uses different wording, and never on the handful of accounts matched by
     * name rather than code ('Accounts Receivable', 'Sales Revenue', 'Cost of
     * Goods Sold', 'Inventory', 'Output CGST/SGST/IGST' — see
     * SalesAccountingService::postInvoiceJournal). Every other row below is a
     * new ledger added to reach the sheet's full list.
     */
    /**
     * provisionDefaults() re-applies the template's names and types to existing
     * codes, which would undo a tenant's renames — so tenant provisioning only
     * runs it for a tenant that has no accounts yet.
     */
    public function provisionDefaultsIfMissing(int $tenantId, ?int $companyId = null, ?int $branchId = null): bool
    {
        // withoutGlobalScopes(): existence must be checked tenant-wide, not
        // filtered by the caller's company/branch scope — a tenant that
        // already has a Chart of Accounts (even one seeded with no company
        // assigned yet, see provisionDefaults()) must not get a second one.
        if (ChartOfAccount::query()->withoutGlobalScopes()->where('tenant_id', $tenantId)->exists()) {
            return false;
        }

        $this->provisionDefaults($tenantId, $companyId, $branchId);

        return true;
    }

    /**
     * Every row carries:
     *  - 'code': the default number — a label the tenant may renumber later;
     *  - 'key':  the account's permanent system_key, used by posting logic to
     *            find it whatever its code becomes (see SystemAccount).
     */
    public function provisionDefaults(int $tenantId, ?int $companyId = null, ?int $branchId = null): void
    {
        $headers = [
            ['code' => '1000', 'key' => 'assets', 'name' => 'Assets', 'type' => ChartOfAccount::TYPE_ASSET, 'normal_balance' => ChartOfAccount::BALANCE_DEBIT],
            ['code' => '2000', 'key' => 'liabilities', 'name' => 'Liabilities', 'type' => ChartOfAccount::TYPE_LIABILITY, 'normal_balance' => ChartOfAccount::BALANCE_CREDIT],
            ['code' => '3000', 'key' => 'equity', 'name' => 'Equity', 'type' => ChartOfAccount::TYPE_EQUITY, 'normal_balance' => ChartOfAccount::BALANCE_CREDIT],
            ['code' => '4000', 'key' => 'income', 'name' => 'Income', 'type' => ChartOfAccount::TYPE_INCOME, 'normal_balance' => ChartOfAccount::BALANCE_CREDIT],
            ['code' => '5000', 'key' => 'expenses', 'name' => 'Expenses', 'type' => ChartOfAccount::TYPE_EXPENSE, 'normal_balance' => ChartOfAccount::BALANCE_DEBIT],
        ];

        $headerIds = [];

        foreach ($headers as $header) {
            $account = $this->upsertTemplateAccount(
                $tenantId,
                $header['code'],
                $header['key'],
                [
                    'name' => $header['name'],
                    'type' => $header['type'],
                    'normal_balance' => $header['normal_balance'],
                    'company_id' => $companyId,
                    'branch_id' => $branchId,
                    'is_system' => true,
                    'is_active' => true,
                ]
            );

            $headerIds[$header['code']] = $account->id;
        }

        $children = [
            // --- Capital Account (sheet rows 1-4) ---
            ['code' => '3010', 'key' => 'share_capital', 'name' => 'Share Capital', 'type' => ChartOfAccount::TYPE_EQUITY, 'subtype' => 'capital', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '3000'],
            ['code' => '3020', 'key' => 'reserves_and_surplus', 'name' => 'Reserves & Surplus', 'type' => ChartOfAccount::TYPE_EQUITY, 'subtype' => 'reserves_surplus', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '3000'],
            ['code' => '3030', 'key' => 'partners_proprietors_capital', 'name' => "Partner's/Proprietor's Capital", 'type' => ChartOfAccount::TYPE_EQUITY, 'subtype' => 'capital', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '3000'],
            ['code' => '3040', 'key' => 'drawings', 'name' => 'Drawings', 'type' => ChartOfAccount::TYPE_EQUITY, 'subtype' => 'capital', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '3000'],
            ['code' => '3200', 'key' => 'revaluation_reserve', 'name' => 'Revaluation Reserve', 'type' => ChartOfAccount::TYPE_EQUITY, 'subtype' => 'reserves_surplus', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '3000'],

            // --- Loans (Liability) (sheet rows 5-8) ---
            ['code' => '2400', 'key' => 'secured_loans', 'name' => 'Secured Loans', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'long_term_liability', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            ['code' => '2401', 'key' => 'term_loan_bank', 'name' => 'Term Loan - Bank', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'long_term_liability', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            ['code' => '2402', 'key' => 'vehicle_loan', 'name' => 'Vehicle Loan', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'long_term_liability', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            ['code' => '2410', 'key' => 'unsecured_loans', 'name' => 'Unsecured Loans', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'long_term_liability', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            ['code' => '2411', 'key' => 'unsecured_loan_director', 'name' => 'Unsecured Loan - Director', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'long_term_liability', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            ['code' => '2412', 'key' => 'unsecured_loan_others', 'name' => 'Unsecured Loan - Others', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'long_term_liability', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],

            // --- Current Liabilities (sheet rows 9-28) ---
            ['code' => '2010', 'key' => 'accounts_payable', 'name' => 'Accounts Payable', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'current_liability', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            ['code' => '2015', 'key' => 'creditors_for_expenses', 'name' => 'Creditors for Expenses', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'current_liability', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            ['code' => '2100', 'key' => 'duties_and_taxes_output', 'name' => 'Duties & Taxes (Output)', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'duties_taxes', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            ['code' => '2110', 'key' => 'output_cgst', 'name' => 'Output CGST', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'duties_taxes', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            ['code' => '2120', 'key' => 'output_sgst', 'name' => 'Output SGST', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'duties_taxes', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            ['code' => '2130', 'key' => 'output_igst', 'name' => 'Output IGST', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'duties_taxes', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            ['code' => '2140', 'key' => 'tds_payable', 'name' => 'TDS Payable', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'duties_taxes', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            ['code' => '2150', 'key' => 'tcs_payable', 'name' => 'TCS Payable', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'duties_taxes', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            ['code' => '2160', 'key' => 'pf_payable', 'name' => 'PF Payable', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'duties_taxes', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            ['code' => '2170', 'key' => 'esi_payable', 'name' => 'ESI Payable', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'duties_taxes', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            ['code' => '2180', 'key' => 'professional_tax_payable', 'name' => 'Professional Tax Payable', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'duties_taxes', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            ['code' => '2020', 'key' => 'taxes_payable_other', 'name' => 'Taxes Payable (Other)', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'current_liability', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            ['code' => '2030', 'key' => 'salary_payable', 'name' => 'Salary Payable', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'provisions', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            ['code' => '2320', 'key' => 'provision_for_expenses', 'name' => 'Provision for Expenses', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'provisions', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            ['code' => '2330', 'key' => 'provision_for_warranty', 'name' => 'Provision for Warranty', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'provisions', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            ['code' => '2340', 'key' => 'audit_fee_payable', 'name' => 'Audit Fee Payable', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'provisions', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            ['code' => '2300', 'key' => 'provision_for_taxation', 'name' => 'Provision for Taxation', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'provisions', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            ['code' => '2310', 'key' => 'provision_for_bad_debts', 'name' => 'Provision for Bad Debts', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'provisions', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            ['code' => '2200', 'key' => 'advance_from_customers', 'name' => 'Advance from Customers', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'current_liability', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            ['code' => '2070', 'key' => 'outstanding_expenses', 'name' => 'Outstanding Expenses', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'current_liability', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            ['code' => '2080', 'key' => 'statutory_dues_payable', 'name' => 'Statutory Dues Payable', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'current_liability', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],

            // --- Suspense / Misc. (sheet rows 100-101) ---
            ['code' => '2900', 'key' => 'suspense_account', 'name' => 'Suspense Account', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'suspense', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            ['code' => '1720', 'key' => 'preliminary_expenses_to_be_written_off', 'name' => 'Preliminary Expenses (to be written off)', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'current_asset', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],

            // --- Fixed Assets (sheet rows 29-35) ---
            ['code' => '1500', 'key' => 'fixed_assets', 'name' => 'Fixed Assets', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'fixed_asset', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '1501', 'key' => 'land_and_building', 'name' => 'Land & Building', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'fixed_asset', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '1502', 'key' => 'plant_and_machinery', 'name' => 'Plant & Machinery', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'fixed_asset', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '1503', 'key' => 'office_equipment', 'name' => 'Office Equipment', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'fixed_asset', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '1504', 'key' => 'computers_and_it_equipment', 'name' => 'Computers & IT Equipment', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'fixed_asset', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '1505', 'key' => 'furniture_and_fixtures', 'name' => 'Furniture & Fixtures', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'fixed_asset', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '1506', 'key' => 'vehicles', 'name' => 'Vehicles', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'fixed_asset', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '1510', 'key' => 'accumulated_depreciation', 'name' => 'Accumulated Depreciation', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'fixed_asset', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '1000'],

            // --- Investments (sheet rows 36-37) ---
            ['code' => '1700', 'key' => 'fixed_deposits', 'name' => 'Fixed Deposits', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'current_asset', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '1710', 'key' => 'mutual_funds', 'name' => 'Mutual Funds', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'current_asset', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],

            // --- Current Assets (sheet rows 38-52) ---
            ['code' => '1010', 'key' => 'cash_in_hand', 'name' => 'Cash-in-Hand', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'current_asset', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000', 'is_cash_or_bank' => true],
            ['code' => '1020', 'key' => 'bank_account', 'name' => 'Bank Account', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'current_asset', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000', 'is_cash_or_bank' => true],
            ['code' => '1021', 'key' => 'hdfc_bank_current_ac', 'name' => 'HDFC Bank - Current A/c', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'current_asset', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000', 'is_cash_or_bank' => true],
            ['code' => '1022', 'key' => 'icici_bank_current_ac', 'name' => 'ICICI Bank - Current A/c', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'current_asset', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000', 'is_cash_or_bank' => true],
            ['code' => '1023', 'key' => 'razorpay_settlement_account', 'name' => 'Razorpay Settlement Account', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'current_asset', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000', 'is_cash_or_bank' => true],
            ['code' => '1100', 'key' => 'accounts_receivable', 'name' => 'Accounts Receivable', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'current_asset', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '1110', 'key' => 'debtors_cod_pending', 'name' => 'Debtors - COD Pending', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'current_asset', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '1200', 'key' => 'inventory', 'name' => 'Inventory', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'current_asset', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '1201', 'key' => 'stock_in_hand_finished_goods', 'name' => 'Stock-in-Hand - Finished Goods', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'current_asset', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '1202', 'key' => 'stock_in_hand_raw_material', 'name' => 'Stock-in-Hand - Raw Material', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'current_asset', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '1203', 'key' => 'stock_in_transit', 'name' => 'Stock-in-Transit', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'current_asset', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '1204', 'key' => 'work_in_progress', 'name' => 'Work-in-Progress', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'current_asset', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '1400', 'key' => 'loans_and_advances', 'name' => 'Loans & Advances', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'loans_advances', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '1410', 'key' => 'advance_to_suppliers', 'name' => 'Advance to Suppliers', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'loans_advances', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '1420', 'key' => 'advance_to_employees', 'name' => 'Advance to Employees', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'loans_advances', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '1430', 'key' => 'prepaid_expenses', 'name' => 'Prepaid Expenses', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'loans_advances', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '1300', 'key' => 'security_deposits', 'name' => 'Security Deposits', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'security_deposit', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '1640', 'key' => 'tds_receivable', 'name' => 'TDS Receivable', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'duties_taxes', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '1650', 'key' => 'gst_refund_receivable', 'name' => 'GST Refund Receivable', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'duties_taxes', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '1600', 'key' => 'duties_and_taxes_input_credit', 'name' => 'Duties & Taxes (Input Credit)', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'duties_taxes', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '1610', 'key' => 'input_cgst', 'name' => 'Input CGST', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'duties_taxes', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '1620', 'key' => 'input_sgst', 'name' => 'Input SGST', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'duties_taxes', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '1630', 'key' => 'input_igst', 'name' => 'Input IGST', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'duties_taxes', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],

            // --- Sales Accounts (sheet rows 53-58) ---
            ['code' => '4010', 'key' => 'sales_revenue', 'name' => 'Sales Revenue', 'type' => ChartOfAccount::TYPE_INCOME, 'subtype' => 'direct_income', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '4000'],
            ['code' => '4011', 'key' => 'sales_website_d2c', 'name' => 'Sales - Website (D2C)', 'type' => ChartOfAccount::TYPE_INCOME, 'subtype' => 'direct_income', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '4000'],
            ['code' => '4012', 'key' => 'sales_offline_retail', 'name' => 'Sales - Offline / Retail', 'type' => ChartOfAccount::TYPE_INCOME, 'subtype' => 'direct_income', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '4000'],
            ['code' => '4013', 'key' => 'sales_b2b_corporate', 'name' => 'Sales - B2B / Corporate', 'type' => ChartOfAccount::TYPE_INCOME, 'subtype' => 'direct_income', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '4000'],
            ['code' => '4014', 'key' => 'installation_charges_income', 'name' => 'Installation Charges Income', 'type' => ChartOfAccount::TYPE_INCOME, 'subtype' => 'direct_income', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '4000'],
            ['code' => '4030', 'key' => 'sales_returns_and_allowances', 'name' => 'Sales Returns & Allowances', 'type' => ChartOfAccount::TYPE_INCOME, 'subtype' => 'direct_income', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '4000'],
            ['code' => '4031', 'key' => 'discount_allowed', 'name' => 'Discount Allowed', 'type' => ChartOfAccount::TYPE_INCOME, 'subtype' => 'direct_income', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '4000'],
            ['code' => '4020', 'key' => 'service_revenue', 'name' => 'Service Revenue', 'type' => ChartOfAccount::TYPE_INCOME, 'subtype' => 'direct_income', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '4000'],

            // --- Purchase Accounts (sheet rows 59-62) ---
            ['code' => '5020', 'key' => 'purchase_raw_material', 'name' => 'Purchase - Raw Material', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'cogs', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
            ['code' => '5021', 'key' => 'purchase_finished_goods', 'name' => 'Purchase - Finished Goods', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'cogs', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
            ['code' => '5022', 'key' => 'purchase_returns', 'name' => 'Purchase Returns', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'cogs', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '5000'],
            ['code' => '5023', 'key' => 'discount_received', 'name' => 'Discount Received', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'cogs', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '5000'],

            // --- Direct Income (sheet row 63) ---
            ['code' => '4040', 'key' => 'job_work_income', 'name' => 'Job Work Income', 'type' => ChartOfAccount::TYPE_INCOME, 'subtype' => 'direct_income', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '4000'],

            // --- Indirect Income (sheet rows 64-66) ---
            ['code' => '4910', 'key' => 'interest_received', 'name' => 'Interest Received', 'type' => ChartOfAccount::TYPE_INCOME, 'subtype' => 'indirect_income', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '4000'],
            ['code' => '4920', 'key' => 'scrap_sale_income', 'name' => 'Scrap Sale Income', 'type' => ChartOfAccount::TYPE_INCOME, 'subtype' => 'indirect_income', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '4000'],
            ['code' => '4900', 'key' => 'miscellaneous_income', 'name' => 'Miscellaneous Income', 'type' => ChartOfAccount::TYPE_INCOME, 'subtype' => 'indirect_income', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '4000'],
            ['code' => '4930', 'key' => 'foreign_exchange_gain', 'name' => 'Foreign Exchange Gain', 'type' => ChartOfAccount::TYPE_INCOME, 'subtype' => 'indirect_income', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '4000'],
            ['code' => '4940', 'key' => 'gain_on_sale_of_fixed_assets', 'name' => 'Gain on Sale of Fixed Assets', 'type' => ChartOfAccount::TYPE_INCOME, 'subtype' => 'indirect_income', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '4000'],

            // --- Direct Expenses (sheet rows 67-70) ---
            ['code' => '5010', 'key' => 'cost_of_goods_sold', 'name' => 'Cost of Goods Sold', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'cogs', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
            ['code' => '5030', 'key' => 'freight_and_forwarding_inward', 'name' => 'Freight & Forwarding (Inward)', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'cogs', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
            ['code' => '5031', 'key' => 'wages_factory_warehouse', 'name' => 'Wages - Factory/Warehouse', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'cogs', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
            ['code' => '5032', 'key' => 'packing_material_expense', 'name' => 'Packing Material Expense', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'cogs', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
            ['code' => '5033', 'key' => 'power_and_fuel_production', 'name' => 'Power & Fuel - Production', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'cogs', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],

            // --- Indirect Expenses (sheet rows 71-99) ---
            ['code' => '5100', 'key' => 'salary_and_wages_staff', 'name' => 'Salary & Wages - Staff', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'indirect_expense', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
            ['code' => '5500', 'key' => 'staff_welfare_expenses', 'name' => 'Staff Welfare Expenses', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'indirect_expense', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
            ['code' => '5200', 'key' => 'rent_expense', 'name' => 'Rent Expense', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'indirect_expense', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
            ['code' => '5300', 'key' => 'electricity_expense', 'name' => 'Electricity Expense', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'indirect_expense', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
            ['code' => '5510', 'key' => 'telephone_and_internet_expense', 'name' => 'Telephone & Internet Expense', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'indirect_expense', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
            ['code' => '5520', 'key' => 'office_supplies_stationery', 'name' => 'Office Supplies / Stationery', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'indirect_expense', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
            ['code' => '5530', 'key' => 'repairs_and_maintenance', 'name' => 'Repairs & Maintenance', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'indirect_expense', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
            ['code' => '5540', 'key' => 'insurance_expense', 'name' => 'Insurance Expense', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'indirect_expense', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
            ['code' => '5550', 'key' => 'legal_and_professional_charges', 'name' => 'Legal & Professional Charges', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'indirect_expense', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
            ['code' => '5560', 'key' => 'audit_fees', 'name' => 'Audit Fees', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'indirect_expense', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
            ['code' => '5570', 'key' => 'bank_charges', 'name' => 'Bank Charges', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'indirect_expense', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
            ['code' => '5580', 'key' => 'payment_gateway_charges_razorpay', 'name' => 'Payment Gateway Charges - Razorpay', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'indirect_expense', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
            ['code' => '5590', 'key' => 'shopify_subscription_and_app_fees', 'name' => 'Shopify Subscription & App Fees', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'indirect_expense', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
            ['code' => '5600', 'key' => 'odoo_subscription_license_fees', 'name' => 'Odoo Subscription/License Fees', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'indirect_expense', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
            ['code' => '5610', 'key' => 'freight_and_forwarding_outward', 'name' => 'Freight & Forwarding (Outward)', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'indirect_expense', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
            ['code' => '5620', 'key' => 'cod_handling_charges', 'name' => 'COD Handling Charges', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'indirect_expense', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
            ['code' => '5630', 'key' => 'courier_and_postage', 'name' => 'Courier & Postage', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'indirect_expense', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
            ['code' => '5640', 'key' => 'installation_and_technician_charges_vms', 'name' => 'Installation & Technician Charges (VMS)', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'indirect_expense', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
            ['code' => '5650', 'key' => 'warranty_and_after_sales_expense', 'name' => 'Warranty & After-Sales Expense', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'indirect_expense', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
            ['code' => '5660', 'key' => 'advertising_and_marketing_expense', 'name' => 'Advertising & Marketing Expense', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'indirect_expense', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
            ['code' => '5670', 'key' => 'whatsapp_email_sms_api_charges', 'name' => 'WhatsApp/Email/SMS API Charges', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'indirect_expense', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
            ['code' => '5680', 'key' => 'myoperator_ivr_charges', 'name' => 'MyOperator / IVR Charges', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'indirect_expense', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
            ['code' => '5690', 'key' => 'software_and_subscription_charges', 'name' => 'Software & Subscription Charges', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'indirect_expense', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
            ['code' => '5700', 'key' => 'travel_and_conveyance', 'name' => 'Travel & Conveyance', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'indirect_expense', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
            ['code' => '5710', 'key' => 'printing_and_stationery', 'name' => 'Printing & Stationery', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'indirect_expense', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
            ['code' => '5400', 'key' => 'depreciation_expense', 'name' => 'Depreciation Expense', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'indirect_expense', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
            ['code' => '5720', 'key' => 'interest_on_loan', 'name' => 'Interest on Loan', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'indirect_expense', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
            ['code' => '5730', 'key' => 'round_off', 'name' => 'Round Off', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'indirect_expense', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
            ['code' => '5740', 'key' => 'foreign_exchange_loss', 'name' => 'Foreign Exchange Loss', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'indirect_expense', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
            ['code' => '5900', 'key' => 'other_expense', 'name' => 'Other Expense', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'indirect_expense', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
            ['code' => '5910', 'key' => 'loss_on_sale_of_fixed_assets', 'name' => 'Loss on Sale of Fixed Assets', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'indirect_expense', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
            ['code' => '5920', 'key' => 'impairment_loss', 'name' => 'Impairment Loss', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'indirect_expense', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
        ];

        foreach ($children as $child) {
            $this->upsertTemplateAccount(
                $tenantId,
                $child['code'],
                $child['key'],
                [
                    'name' => $child['name'],
                    'type' => $child['type'],
                    'subtype' => $child['subtype'] ?? null,
                    'normal_balance' => $child['normal_balance'],
                    'parent_id' => $headerIds[$child['parent']],
                    'is_cash_or_bank' => $child['is_cash_or_bank'] ?? false,
                    'company_id' => $companyId,
                    'branch_id' => $branchId,
                    'is_system' => true,
                    'is_active' => true,
                ]
            );
        }
    }

    /**
     * Create or update one default account. An existing row is matched by its
     * system_key first, so an account the tenant has renumbered is updated in
     * place rather than duplicated under its original code; rows provisioned
     * before system keys existed are matched by code and get their key here.
     *
     * Each row's 'key' must equal SystemAccount::TEMPLATE for its code — the
     * migration backfills existing tenants from that map — which
     * SystemAccountKeysTest enforces.
     */
    private function upsertTemplateAccount(int $tenantId, string $templateCode, string $systemKey, array $attributes): ChartOfAccount
    {
        $account = $this->accounts->findBySystemKey($systemKey, $tenantId)
            ?? ChartOfAccount::query()->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('code', $templateCode)
                ->first()
            ?? new ChartOfAccount(['tenant_id' => $tenantId, 'code' => $templateCode]);

        $account->fill($attributes);

        if ($account->system_key === null) {
            $account->system_key = $systemKey;
        }

        $account->save();

        return $account;
    }

    /**
     * Flat list annotated with a `depth` key, ordered so children follow their
     * parent — the shape Blade views need to render an indented COA tree
     * without recursive partials.
     *
     * @return array<int, array{account: ChartOfAccount, depth: int}>
     */
    public function tree(): array
    {
        $accounts = $this->accounts->getAll();
        $byParent = $accounts->groupBy('parent_id');

        $flatten = function ($parentId, int $depth) use (&$flatten, $byParent): array {
            $rows = [];

            foreach ($byParent->get($parentId, collect()) as $account) {
                $rows[] = ['account' => $account, 'depth' => $depth];
                $rows = array_merge($rows, $flatten($account->id, $depth + 1));
            }

            return $rows;
        };

        return $flatten(null, 0);
    }
}
