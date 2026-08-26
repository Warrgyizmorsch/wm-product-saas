<?php

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Repositories\ChartOfAccountRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

class ChartOfAccountsService
{
    public function __construct(
        private readonly ChartOfAccountRepositoryInterface $accounts,
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
    public function provisionDefaults(int $tenantId): void
    {
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
            // --- Capital Account (sheet rows 1-4) ---
            ['code' => '3010', 'name' => 'Share Capital', 'type' => ChartOfAccount::TYPE_EQUITY, 'subtype' => 'capital', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '3000'],
            ['code' => '3020', 'name' => 'Reserves & Surplus', 'type' => ChartOfAccount::TYPE_EQUITY, 'subtype' => 'reserves_surplus', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '3000'],
            ['code' => '3030', 'name' => "Partner's/Proprietor's Capital", 'type' => ChartOfAccount::TYPE_EQUITY, 'subtype' => 'capital', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '3000'],
            ['code' => '3040', 'name' => 'Drawings', 'type' => ChartOfAccount::TYPE_EQUITY, 'subtype' => 'capital', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '3000'],

            // --- Loans (Liability) (sheet rows 5-8) ---
            ['code' => '2400', 'name' => 'Secured Loans', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'long_term_liability', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            ['code' => '2401', 'name' => 'Term Loan - Bank', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'long_term_liability', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            ['code' => '2402', 'name' => 'Vehicle Loan', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'long_term_liability', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            ['code' => '2410', 'name' => 'Unsecured Loans', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'long_term_liability', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            ['code' => '2411', 'name' => 'Unsecured Loan - Director', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'long_term_liability', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            ['code' => '2412', 'name' => 'Unsecured Loan - Others', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'long_term_liability', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],

            // --- Current Liabilities (sheet rows 9-28) ---
            ['code' => '2010', 'name' => 'Accounts Payable', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'current_liability', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            ['code' => '2015', 'name' => 'Creditors for Expenses', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'current_liability', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            ['code' => '2100', 'name' => 'Duties & Taxes (Output)', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'duties_taxes', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            ['code' => '2110', 'name' => 'Output CGST', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'duties_taxes', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            ['code' => '2120', 'name' => 'Output SGST', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'duties_taxes', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            ['code' => '2130', 'name' => 'Output IGST', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'duties_taxes', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            ['code' => '2140', 'name' => 'TDS Payable', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'duties_taxes', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            ['code' => '2150', 'name' => 'TCS Payable', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'duties_taxes', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            ['code' => '2160', 'name' => 'PF Payable', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'duties_taxes', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            ['code' => '2170', 'name' => 'ESI Payable', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'duties_taxes', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            ['code' => '2180', 'name' => 'Professional Tax Payable', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'duties_taxes', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            ['code' => '2020', 'name' => 'Taxes Payable (Other)', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'current_liability', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            ['code' => '2030', 'name' => 'Salary Payable', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'provisions', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            ['code' => '2320', 'name' => 'Provision for Expenses', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'provisions', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            ['code' => '2330', 'name' => 'Provision for Warranty', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'provisions', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            ['code' => '2340', 'name' => 'Audit Fee Payable', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'provisions', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            ['code' => '2300', 'name' => 'Provision for Taxation', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'provisions', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            ['code' => '2310', 'name' => 'Provision for Bad Debts', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'provisions', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            ['code' => '2200', 'name' => 'Advance from Customers', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'current_liability', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            ['code' => '2070', 'name' => 'Outstanding Expenses', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'current_liability', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            ['code' => '2080', 'name' => 'Statutory Dues Payable', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'current_liability', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],

            // --- Suspense / Misc. (sheet rows 100-101) ---
            ['code' => '2900', 'name' => 'Suspense Account', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'suspense', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            ['code' => '1720', 'name' => 'Preliminary Expenses (to be written off)', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'current_asset', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],

            // --- Fixed Assets (sheet rows 29-35) ---
            ['code' => '1500', 'name' => 'Fixed Assets', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'fixed_asset', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '1501', 'name' => 'Land & Building', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'fixed_asset', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '1502', 'name' => 'Plant & Machinery', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'fixed_asset', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '1503', 'name' => 'Office Equipment', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'fixed_asset', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '1504', 'name' => 'Computers & IT Equipment', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'fixed_asset', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '1505', 'name' => 'Furniture & Fixtures', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'fixed_asset', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '1506', 'name' => 'Vehicles', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'fixed_asset', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '1510', 'name' => 'Accumulated Depreciation', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'fixed_asset', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '1000'],

            // --- Investments (sheet rows 36-37) ---
            ['code' => '1700', 'name' => 'Fixed Deposits', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'current_asset', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '1710', 'name' => 'Mutual Funds', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'current_asset', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],

            // --- Current Assets (sheet rows 38-52) ---
            ['code' => '1010', 'name' => 'Cash-in-Hand', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'current_asset', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000', 'is_cash_or_bank' => true],
            ['code' => '1020', 'name' => 'Bank Account', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'current_asset', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000', 'is_cash_or_bank' => true],
            ['code' => '1021', 'name' => 'HDFC Bank - Current A/c', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'current_asset', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000', 'is_cash_or_bank' => true],
            ['code' => '1022', 'name' => 'ICICI Bank - Current A/c', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'current_asset', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000', 'is_cash_or_bank' => true],
            ['code' => '1023', 'name' => 'Razorpay Settlement Account', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'current_asset', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000', 'is_cash_or_bank' => true],
            ['code' => '1100', 'name' => 'Accounts Receivable', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'current_asset', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '1110', 'name' => 'Debtors - COD Pending', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'current_asset', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '1200', 'name' => 'Inventory', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'current_asset', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '1201', 'name' => 'Stock-in-Hand - Finished Goods', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'current_asset', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '1202', 'name' => 'Stock-in-Hand - Raw Material', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'current_asset', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '1203', 'name' => 'Stock-in-Transit', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'current_asset', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '1400', 'name' => 'Loans & Advances', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'loans_advances', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '1410', 'name' => 'Advance to Suppliers', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'loans_advances', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '1420', 'name' => 'Advance to Employees', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'loans_advances', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '1430', 'name' => 'Prepaid Expenses', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'loans_advances', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '1300', 'name' => 'Security Deposits', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'security_deposit', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '1640', 'name' => 'TDS Receivable', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'duties_taxes', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '1650', 'name' => 'GST Refund Receivable', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'duties_taxes', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '1600', 'name' => 'Duties & Taxes (Input Credit)', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'duties_taxes', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '1610', 'name' => 'Input CGST', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'duties_taxes', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '1620', 'name' => 'Input SGST', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'duties_taxes', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '1630', 'name' => 'Input IGST', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'duties_taxes', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],

            // --- Sales Accounts (sheet rows 53-58) ---
            ['code' => '4010', 'name' => 'Sales Revenue', 'type' => ChartOfAccount::TYPE_INCOME, 'subtype' => 'direct_income', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '4000'],
            ['code' => '4011', 'name' => 'Sales - Website (D2C)', 'type' => ChartOfAccount::TYPE_INCOME, 'subtype' => 'direct_income', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '4000'],
            ['code' => '4012', 'name' => 'Sales - Offline / Retail', 'type' => ChartOfAccount::TYPE_INCOME, 'subtype' => 'direct_income', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '4000'],
            ['code' => '4013', 'name' => 'Sales - B2B / Corporate', 'type' => ChartOfAccount::TYPE_INCOME, 'subtype' => 'direct_income', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '4000'],
            ['code' => '4014', 'name' => 'Installation Charges Income', 'type' => ChartOfAccount::TYPE_INCOME, 'subtype' => 'direct_income', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '4000'],
            ['code' => '4030', 'name' => 'Sales Returns & Allowances', 'type' => ChartOfAccount::TYPE_INCOME, 'subtype' => 'direct_income', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '4000'],
            ['code' => '4031', 'name' => 'Discount Allowed', 'type' => ChartOfAccount::TYPE_INCOME, 'subtype' => 'direct_income', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '4000'],
            ['code' => '4020', 'name' => 'Service Revenue', 'type' => ChartOfAccount::TYPE_INCOME, 'subtype' => 'direct_income', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '4000'],

            // --- Purchase Accounts (sheet rows 59-62) ---
            ['code' => '5020', 'name' => 'Purchase - Raw Material', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'cogs', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
            ['code' => '5021', 'name' => 'Purchase - Finished Goods', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'cogs', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
            ['code' => '5022', 'name' => 'Purchase Returns', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'cogs', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '5000'],
            ['code' => '5023', 'name' => 'Discount Received', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'cogs', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '5000'],

            // --- Direct Income (sheet row 63) ---
            ['code' => '4040', 'name' => 'Job Work Income', 'type' => ChartOfAccount::TYPE_INCOME, 'subtype' => 'direct_income', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '4000'],

            // --- Indirect Income (sheet rows 64-66) ---
            ['code' => '4910', 'name' => 'Interest Received', 'type' => ChartOfAccount::TYPE_INCOME, 'subtype' => 'indirect_income', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '4000'],
            ['code' => '4920', 'name' => 'Scrap Sale Income', 'type' => ChartOfAccount::TYPE_INCOME, 'subtype' => 'indirect_income', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '4000'],
            ['code' => '4900', 'name' => 'Miscellaneous Income', 'type' => ChartOfAccount::TYPE_INCOME, 'subtype' => 'indirect_income', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '4000'],

            // --- Direct Expenses (sheet rows 67-70) ---
            ['code' => '5010', 'name' => 'Cost of Goods Sold', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'cogs', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
            ['code' => '5030', 'name' => 'Freight & Forwarding (Inward)', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'cogs', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
            ['code' => '5031', 'name' => 'Wages - Factory/Warehouse', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'cogs', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
            ['code' => '5032', 'name' => 'Packing Material Expense', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'cogs', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
            ['code' => '5033', 'name' => 'Power & Fuel - Production', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'cogs', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],

            // --- Indirect Expenses (sheet rows 71-99) ---
            ['code' => '5100', 'name' => 'Salary & Wages - Staff', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'indirect_expense', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
            ['code' => '5500', 'name' => 'Staff Welfare Expenses', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'indirect_expense', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
            ['code' => '5200', 'name' => 'Rent Expense', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'indirect_expense', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
            ['code' => '5300', 'name' => 'Electricity Expense', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'indirect_expense', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
            ['code' => '5510', 'name' => 'Telephone & Internet Expense', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'indirect_expense', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
            ['code' => '5520', 'name' => 'Office Supplies / Stationery', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'indirect_expense', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
            ['code' => '5530', 'name' => 'Repairs & Maintenance', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'indirect_expense', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
            ['code' => '5540', 'name' => 'Insurance Expense', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'indirect_expense', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
            ['code' => '5550', 'name' => 'Legal & Professional Charges', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'indirect_expense', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
            ['code' => '5560', 'name' => 'Audit Fees', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'indirect_expense', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
            ['code' => '5570', 'name' => 'Bank Charges', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'indirect_expense', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
            ['code' => '5580', 'name' => 'Payment Gateway Charges - Razorpay', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'indirect_expense', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
            ['code' => '5590', 'name' => 'Shopify Subscription & App Fees', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'indirect_expense', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
            ['code' => '5600', 'name' => 'Odoo Subscription/License Fees', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'indirect_expense', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
            ['code' => '5610', 'name' => 'Freight & Forwarding (Outward)', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'indirect_expense', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
            ['code' => '5620', 'name' => 'COD Handling Charges', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'indirect_expense', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
            ['code' => '5630', 'name' => 'Courier & Postage', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'indirect_expense', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
            ['code' => '5640', 'name' => 'Installation & Technician Charges (VMS)', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'indirect_expense', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
            ['code' => '5650', 'name' => 'Warranty & After-Sales Expense', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'indirect_expense', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
            ['code' => '5660', 'name' => 'Advertising & Marketing Expense', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'indirect_expense', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
            ['code' => '5670', 'name' => 'WhatsApp/Email/SMS API Charges', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'indirect_expense', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
            ['code' => '5680', 'name' => 'MyOperator / IVR Charges', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'indirect_expense', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
            ['code' => '5690', 'name' => 'Software & Subscription Charges', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'indirect_expense', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
            ['code' => '5700', 'name' => 'Travel & Conveyance', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'indirect_expense', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
            ['code' => '5710', 'name' => 'Printing & Stationery', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'indirect_expense', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
            ['code' => '5400', 'name' => 'Depreciation Expense', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'indirect_expense', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
            ['code' => '5720', 'name' => 'Interest on Loan', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'indirect_expense', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
            ['code' => '5730', 'name' => 'Round Off', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'indirect_expense', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
            ['code' => '5900', 'name' => 'Other Expense', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'indirect_expense', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
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
                    'is_cash_or_bank' => $child['is_cash_or_bank'] ?? false,
                    'is_system' => true,
                    'is_active' => true,
                ]
            );
        }
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
