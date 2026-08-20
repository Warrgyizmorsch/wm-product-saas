<?php

namespace Database\Seeders;

use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\FiscalYear;
use App\Domains\Accounting\Models\TaxRate;
use App\Domains\Accounting\Services\FiscalPeriodService;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class AccountingChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::where('slug', 'demo')->first() ?? Tenant::first();

        if (!$tenant) {
            return;
        }

        $this->seedChartOfAccounts($tenant->id);
        $this->seedTaxRates($tenant->id);
        $this->seedCurrentFiscalYear($tenant->id);
    }

    /**
     * @return void
     */
    private function seedChartOfAccounts(int $tenantId): void
    {
        // Top-level headers first so child rows can resolve parent_id.
        $headers = [
            ['code' => '1000', 'name' => 'Assets', 'type' => ChartOfAccount::TYPE_ASSET, 'normal_balance' => ChartOfAccount::BALANCE_DEBIT],
            ['code' => '2000', 'name' => 'Liabilities', 'type' => ChartOfAccount::TYPE_LIABILITY, 'normal_balance' => ChartOfAccount::BALANCE_CREDIT],
            ['code' => '3000', 'name' => 'Equity', 'type' => ChartOfAccount::TYPE_EQUITY, 'normal_balance' => ChartOfAccount::BALANCE_CREDIT],
            ['code' => '4000', 'name' => 'Income', 'type' => ChartOfAccount::TYPE_INCOME, 'normal_balance' => ChartOfAccount::BALANCE_CREDIT],
            ['code' => '5000', 'name' => 'Expenses', 'type' => ChartOfAccount::TYPE_EXPENSE, 'normal_balance' => ChartOfAccount::BALANCE_DEBIT],
        ];

        $headerIds = [];

        foreach ($headers as $header) {
            $account = ChartOfAccount::query()->updateOrCreate(
                ['tenant_id' => $tenantId, 'code' => $header['code']],
                [
                    'name' => $header['name'],
                    'type' => $header['type'],
                    'normal_balance' => $header['normal_balance'],
                    'is_system' => true,
                    'is_active' => true,
                ]
            );

            $headerIds[$header['code']] = $account->id;
        }

        $children = [
            // ---------------------------------------------------------------
            // Assets
            // ---------------------------------------------------------------
            ['code' => '1010', 'name' => 'Cash on Hand', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'current_asset', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '1020', 'name' => 'Bank Account', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'current_asset', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '1100', 'name' => 'Accounts Receivable', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'current_asset', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            // NOTE: labeled "Accounts Receivable" (US/IFRS terminology). Tally/Busy call this
            // "Sundry Debtors" — same account, different label. If tenants or their CAs will
            // ever see this ledger name directly (reports, exports), consider making the
            // display label configurable/aliasable rather than renaming the code, so US-GAAP-style
            // reports and Tally-style reports can both read from the same underlying account.
            ['code' => '1200', 'name' => 'Inventory', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'current_asset', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '1300', 'name' => 'Security Deposits', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'security_deposit', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '1400', 'name' => 'Loans & Advances', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'loans_advances', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '1410', 'name' => 'Advance to Suppliers', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'loans_advances', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '1500', 'name' => 'Fixed Assets', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'fixed_asset', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '1510', 'name' => 'Accumulated Depreciation', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'fixed_asset', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '1000'],
            // NOTE: this is a single pooled contra-account for ALL fixed assets. Tally/Busy
            // users typically expect per-asset-class depreciation (Schedule II, Companies Act)
            // — e.g. a separate "Accumulated Depreciation - Furniture" per asset category.
            // Fine as a v1 default; flag this as a known simplification if a Fixed Asset
            // Register module is planned later, since retrofitting per-asset tracking onto
            // a pooled balance is messy.

            // --- Duties & Taxes (Input / recoverable side lives under Assets) ---
            ['code' => '1600', 'name' => 'Duties & Taxes (Input Credit)', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'duties_taxes', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '1610', 'name' => 'Input CGST', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'duties_taxes', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '1620', 'name' => 'Input SGST', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'duties_taxes', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '1630', 'name' => 'Input IGST', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'duties_taxes', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '1640', 'name' => 'TDS Receivable', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'duties_taxes', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            // TDS Receivable = tax OTHERS deducted from payments made TO this tenant
            // (e.g. a customer deducts TDS before paying an invoice). Recoverable via ITR /
            // adjustable against tenant's own tax liability — it's an asset, not an expense.

            // ---------------------------------------------------------------
            // Liabilities
            // ---------------------------------------------------------------
            ['code' => '2010', 'name' => 'Accounts Payable', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'current_liability', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            // Tally/Busy: "Sundry Creditors" — same note as Accounts Receivable above.
            ['code' => '2020', 'name' => 'Taxes Payable (Other)', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'current_liability', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            // Renamed from "Taxes Payable" to "Taxes Payable (Other)" now that GST/TDS/TCS
            // have their own explicit ledgers below — this becomes the catch-all for
            // anything not GST/TDS/TCS (e.g. professional tax, local body tax).
            ['code' => '2030', 'name' => 'Salaries Payable', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'current_liability', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            ['code' => '2200', 'name' => 'Customer Advances', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'current_liability', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],

            // --- Duties & Taxes (Output / payable side lives under Liabilities) ---
            ['code' => '2100', 'name' => 'Duties & Taxes (Output)', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'duties_taxes', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            ['code' => '2110', 'name' => 'Output CGST', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'duties_taxes', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            ['code' => '2120', 'name' => 'Output SGST', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'duties_taxes', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            ['code' => '2130', 'name' => 'Output IGST', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'duties_taxes', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            ['code' => '2140', 'name' => 'TDS Payable', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'duties_taxes', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            // TDS the tenant deducted from payments made TO OTHERS (e.g. vendor payments,
            // salaries) and owes to the government. Mirror of TDS Receivable (1640).
            ['code' => '2150', 'name' => 'TCS Payable', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'duties_taxes', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],

            // --- Provisions (its own Tally sub-group, distinct from Taxes Payable) ---
            ['code' => '2300', 'name' => 'Provision for Taxation', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'provisions', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            ['code' => '2310', 'name' => 'Provision for Bad Debts', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'provisions', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],

            // --- Loans (split Secured/Unsecured per Schedule III, Companies Act) ---
            ['code' => '2400', 'name' => 'Secured Loans', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'long_term_liability', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            ['code' => '2410', 'name' => 'Unsecured Loans', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'long_term_liability', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            // Replaces the single "Loans Payable" (old code 2100, now reused above for
            // Duties & Taxes Output — update any references/migrations accordingly).

            // --- Suspense (temporary parking account for unreconciled entries) ---
            ['code' => '2900', 'name' => 'Suspense Account', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'suspense', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],

            // ---------------------------------------------------------------
            // Equity
            // ---------------------------------------------------------------
            ['code' => '3010', 'name' => "Owner's Capital", 'type' => ChartOfAccount::TYPE_EQUITY, 'subtype' => 'capital', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '3000'],
            ['code' => '3020', 'name' => 'Retained Earnings', 'type' => ChartOfAccount::TYPE_EQUITY, 'subtype' => 'reserves_surplus', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '3000'],

            // ---------------------------------------------------------------
            // Income
            // ---------------------------------------------------------------
            ['code' => '4010', 'name' => 'Sales Revenue', 'type' => ChartOfAccount::TYPE_INCOME, 'subtype' => 'direct_income', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '4000'],
            ['code' => '4020', 'name' => 'Service Revenue', 'type' => ChartOfAccount::TYPE_INCOME, 'subtype' => 'direct_income', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '4000'],
            ['code' => '4900', 'name' => 'Other Income', 'type' => ChartOfAccount::TYPE_INCOME, 'subtype' => 'indirect_income', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '4000'],

            // ---------------------------------------------------------------
            // Expenses
            // ---------------------------------------------------------------
            ['code' => '5010', 'name' => 'Cost of Goods Sold', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'cogs', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
            // Deliberate deviation from Tally's "Purchase Accounts" primary group — COGS as
            // an expense matches perpetual-inventory costing (QuickBooks/Xero-style) and
            // works better with the Inventory module. Flag this to Tally-native accountants
            // reviewing the system as an intentional, modern choice, not a gap.
            ['code' => '5100', 'name' => 'Salaries & Wages', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'indirect_expense', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
            ['code' => '5200', 'name' => 'Rent Expense', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'indirect_expense', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
            ['code' => '5300', 'name' => 'Utilities Expense', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'indirect_expense', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
            ['code' => '5400', 'name' => 'Depreciation Expense', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'indirect_expense', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
            ['code' => '5900', 'name' => 'Other Expense', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'indirect_expense', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
            // Retagged subtype from generic 'operating_expense' to 'indirect_expense' so a
            // future Trading Account / Gross Profit report can filter Direct vs Indirect
            // cleanly. Add a 'direct_expense' subtype (freight inward, factory wages, etc.)
            // if/when a tenant needs manufacturing-style costing.
        ];

        foreach ($children as $child) {
            ChartOfAccount::query()->updateOrCreate(
                ['tenant_id' => $tenantId, 'code' => $child['code']],
                [
                    'name' => $child['name'],
                    'type' => $child['type'],
                    'subtype' => $child['subtype'] ?? null,
                    'normal_balance' => $child['normal_balance'],
                    'parent_id' => $headerIds[$child['parent']],
                    'is_system' => true,
                    'is_active' => true,
                ]
            );
        }
    }

    /**
     * NOTE: GST is not a single flat rate/account — it is a *tax group* made of
     * components (CGST + SGST for intra-state, or IGST for inter-state), each
     * posting to its own ledger. The current TaxRate shape (one `rate` +
     * one `tax_payable_account_id`) cannot represent this correctly — it will
     * either double-post or post the wrong split. This seeder wires up the
     * underlying ledger accounts (done above) but the TaxRate model itself
     * needs a `tax_components` (or similar) relation before GST can be
     * calculated correctly end to end. Flagging this as a model-level
     * change, not something a seeder can paper over.
     */
    private function seedTaxRates(int $tenantId): void
    {
        $accounts = ChartOfAccount::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('code', ['2110', '2120', '2130', '1610', '1620', '1630'])
            ->pluck('id', 'code');

        // Seeded as reference/output-side rates for now. Once TaxRate supports
        // components, each of these should decompose into two 9% legs (CGST+SGST)
        // for intra-state and one 18% IGST leg for inter-state, rather than a
        // single flat rate/account pair.
        foreach ([0, 5, 12, 18, 28] as $rate) {
            TaxRate::query()->updateOrCreate(
                ['tenant_id' => $tenantId, 'name' => "GST {$rate}%"],
                [
                    'type' => 'gst',
                    'rate' => (float) $rate,
                    'is_compound' => false,
                    'is_active' => true,
                    // Placeholder: points at Output IGST until component support exists.
                    'tax_payable_account_id' => $accounts['2130'] ?? null,
                ]
            );
        }
    }

    private function seedCurrentFiscalYear(int $tenantId): void
    {
        // India: statutory fiscal year is 1 April – 31 March, NOT calendar year.
        // now()->startOfYear()/endOfYear() default to Jan–Dec and will silently
        // produce the wrong FY for every Indian tenant.
        $today = now();
        $fyStartYear = $today->month >= 4 ? $today->year : $today->year - 1;

        $startDate = now()->setDate($fyStartYear, 4, 1)->startOfDay();
        $endDate = (clone $startDate)->addYear()->subDay()->endOfDay();

        $existing = FiscalYear::query()
            ->where('tenant_id', $tenantId)
            ->whereDate('start_date', $startDate->toDateString())
            ->exists();

        if ($existing) {
            return;
        }

        app(FiscalPeriodService::class)->createFiscalYearWithMonthlyPeriods([
            'tenant_id' => $tenantId,
            'name' => 'FY ' . $fyStartYear . '-' . ($fyStartYear + 1),
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
        ]);
    }
}
