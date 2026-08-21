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
            ['code' => '1010', 'name' => 'Cash on Hand', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'current_asset', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000', 'is_cash_or_bank' => true],
            ['code' => '1020', 'name' => 'Bank Account', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'current_asset', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000', 'is_cash_or_bank' => true],
            ['code' => '1100', 'name' => 'Accounts Receivable', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'current_asset', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '1200', 'name' => 'Inventory', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'current_asset', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '1300', 'name' => 'Security Deposits', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'security_deposit', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '1400', 'name' => 'Loans & Advances', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'loans_advances', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '1410', 'name' => 'Advance to Suppliers', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'loans_advances', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '1500', 'name' => 'Fixed Assets', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'fixed_asset', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '1510', 'name' => 'Accumulated Depreciation', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'fixed_asset', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '1000'],
            ['code' => '1600', 'name' => 'Duties & Taxes (Input Credit)', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'duties_taxes', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '1610', 'name' => 'Input CGST', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'duties_taxes', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '1620', 'name' => 'Input SGST', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'duties_taxes', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '1630', 'name' => 'Input IGST', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'duties_taxes', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '1640', 'name' => 'TDS Receivable', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'duties_taxes', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '2010', 'name' => 'Accounts Payable', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'current_liability', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            ['code' => '2020', 'name' => 'Taxes Payable (Other)', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'current_liability', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            ['code' => '2030', 'name' => 'Salaries Payable', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'current_liability', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            ['code' => '2200', 'name' => 'Customer Advances', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'current_liability', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            ['code' => '2100', 'name' => 'Duties & Taxes (Output)', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'duties_taxes', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            ['code' => '2110', 'name' => 'Output CGST', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'duties_taxes', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            ['code' => '2120', 'name' => 'Output SGST', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'duties_taxes', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            ['code' => '2130', 'name' => 'Output IGST', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'duties_taxes', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            ['code' => '2140', 'name' => 'TDS Payable', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'duties_taxes', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            ['code' => '2150', 'name' => 'TCS Payable', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'duties_taxes', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            ['code' => '2300', 'name' => 'Provision for Taxation', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'provisions', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            ['code' => '2310', 'name' => 'Provision for Bad Debts', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'provisions', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            ['code' => '2400', 'name' => 'Secured Loans', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'long_term_liability', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            ['code' => '2410', 'name' => 'Unsecured Loans', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'long_term_liability', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            ['code' => '2900', 'name' => 'Suspense Account', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'suspense', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            ['code' => '3010', 'name' => "Owner's Capital", 'type' => ChartOfAccount::TYPE_EQUITY, 'subtype' => 'capital', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '3000'],
            ['code' => '3020', 'name' => 'Retained Earnings', 'type' => ChartOfAccount::TYPE_EQUITY, 'subtype' => 'reserves_surplus', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '3000'],
            ['code' => '4010', 'name' => 'Sales Revenue', 'type' => ChartOfAccount::TYPE_INCOME, 'subtype' => 'direct_income', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '4000'],
            ['code' => '4020', 'name' => 'Service Revenue', 'type' => ChartOfAccount::TYPE_INCOME, 'subtype' => 'direct_income', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '4000'],
            ['code' => '4900', 'name' => 'Other Income', 'type' => ChartOfAccount::TYPE_INCOME, 'subtype' => 'indirect_income', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '4000'],
            ['code' => '5010', 'name' => 'Cost of Goods Sold', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'cogs', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
            ['code' => '5100', 'name' => 'Salaries & Wages', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'indirect_expense', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
            ['code' => '5200', 'name' => 'Rent Expense', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'indirect_expense', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
            ['code' => '5300', 'name' => 'Utilities Expense', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'indirect_expense', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
            ['code' => '5400', 'name' => 'Depreciation Expense', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'indirect_expense', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
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
