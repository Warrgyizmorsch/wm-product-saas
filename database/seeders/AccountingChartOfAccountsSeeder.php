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
            // Assets
            ['code' => '1010', 'name' => 'Cash on Hand', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'current_asset', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '1020', 'name' => 'Bank Account', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'current_asset', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '1100', 'name' => 'Accounts Receivable', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'current_asset', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '1200', 'name' => 'Inventory', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'current_asset', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '1500', 'name' => 'Fixed Assets', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'fixed_asset', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '1000'],
            ['code' => '1510', 'name' => 'Accumulated Depreciation', 'type' => ChartOfAccount::TYPE_ASSET, 'subtype' => 'fixed_asset', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '1000'],

            // Liabilities
            ['code' => '2010', 'name' => 'Accounts Payable', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'current_liability', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            ['code' => '2020', 'name' => 'Taxes Payable', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'current_liability', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            ['code' => '2030', 'name' => 'Salaries Payable', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'current_liability', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            ['code' => '2100', 'name' => 'Loans Payable', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'long_term_liability', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],
            ['code' => '2200', 'name' => 'Customer Advances', 'type' => ChartOfAccount::TYPE_LIABILITY, 'subtype' => 'current_liability', 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '2000'],

            // Equity
            ['code' => '3010', 'name' => "Owner's Capital", 'type' => ChartOfAccount::TYPE_EQUITY, 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '3000'],
            ['code' => '3020', 'name' => 'Retained Earnings', 'type' => ChartOfAccount::TYPE_EQUITY, 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '3000'],

            // Income
            ['code' => '4010', 'name' => 'Sales Revenue', 'type' => ChartOfAccount::TYPE_INCOME, 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '4000'],
            ['code' => '4020', 'name' => 'Service Revenue', 'type' => ChartOfAccount::TYPE_INCOME, 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '4000'],
            ['code' => '4900', 'name' => 'Other Income', 'type' => ChartOfAccount::TYPE_INCOME, 'normal_balance' => ChartOfAccount::BALANCE_CREDIT, 'parent' => '4000'],

            // Expenses
            ['code' => '5010', 'name' => 'Cost of Goods Sold', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'cogs', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
            ['code' => '5100', 'name' => 'Salaries & Wages', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'operating_expense', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
            ['code' => '5200', 'name' => 'Rent Expense', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'operating_expense', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
            ['code' => '5300', 'name' => 'Utilities Expense', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'operating_expense', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
            ['code' => '5400', 'name' => 'Depreciation Expense', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'operating_expense', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
            ['code' => '5900', 'name' => 'Other Expense', 'type' => ChartOfAccount::TYPE_EXPENSE, 'subtype' => 'operating_expense', 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'parent' => '5000'],
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

    private function seedTaxRates(int $tenantId): void
    {
        $taxPayableAccountId = ChartOfAccount::query()
            ->where('tenant_id', $tenantId)
            ->where('code', '2020')
            ->value('id');

        TaxRate::query()->updateOrCreate(
            ['tenant_id' => $tenantId, 'name' => 'GST 18%'],
            [
                'type' => 'gst',
                'rate' => 18.0,
                'is_compound' => false,
                'is_active' => true,
                'tax_payable_account_id' => $taxPayableAccountId,
            ]
        );
    }

    private function seedCurrentFiscalYear(int $tenantId): void
    {
        $existing = FiscalYear::query()
            ->where('tenant_id', $tenantId)
            ->whereYear('start_date', now()->year)
            ->exists();

        if ($existing) {
            return;
        }

        app(FiscalPeriodService::class)->createFiscalYearWithMonthlyPeriods([
            'tenant_id' => $tenantId,
            'name' => 'FY ' . now()->year,
            'start_date' => now()->startOfYear()->toDateString(),
            'end_date' => now()->endOfYear()->toDateString(),
        ]);
    }
}
