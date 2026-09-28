<?php

namespace Tests\Feature\Accounting;

use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Models\LedgerGroup;
use App\Domains\Accounting\Services\FiscalPeriodService;
use App\Domains\Accounting\Services\JournalService;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Inventory\Services\StockService;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountingFeatureGapsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $accountant;
    private ChartOfAccount $bank;
    private ChartOfAccount $expense;
    private ChartOfAccount $income;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
        $this->tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme', 'status' => 'active', 'plan' => 'enterprise']);

        app(FiscalPeriodService::class)->createFiscalYearWithMonthlyPeriods([
            'tenant_id' => $this->tenant->id,
            'name' => 'FY '.now()->year,
            'start_date' => now()->startOfYear()->toDateString(),
            'end_date' => now()->endOfYear()->toDateString(),
        ]);

        $this->accountant = $this->makeUser('Asha Accountant', 'asha@acme.test', 'accountant');

        $this->bank = ChartOfAccount::create([
            'tenant_id' => $this->tenant->id, 'code' => '1020', 'name' => 'Bank', 'type' => ChartOfAccount::TYPE_ASSET,
            'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'is_cash_or_bank' => true,
        ]);
        $this->expense = ChartOfAccount::create([
            'tenant_id' => $this->tenant->id, 'code' => '5900', 'name' => 'Other Expense', 'type' => ChartOfAccount::TYPE_EXPENSE,
            'normal_balance' => ChartOfAccount::BALANCE_DEBIT,
        ]);
        $this->income = ChartOfAccount::create([
            'tenant_id' => $this->tenant->id, 'code' => '4010', 'name' => 'Sales Revenue', 'type' => ChartOfAccount::TYPE_INCOME,
            'normal_balance' => ChartOfAccount::BALANCE_CREDIT,
        ]);
    }

    private function makeUser(string $name, string $email, ?string $role): User
    {
        $user = User::create(['tenant_id' => $this->tenant->id, 'name' => $name, 'email' => $email, 'password' => bcrypt('password')]);

        if ($role !== null) {
            UserRole::create([
                'user_id' => $user->id,
                'role_id' => Role::query()->whereNull('tenant_id')->where('slug', $role)->firstOrFail()->id,
                'tenant_id' => $this->tenant->id,
            ]);
        }

        return $user;
    }

    private function as(User $user)
    {
        return $this->actingAs($user)->withHeader('X-Tenant', 'acme');
    }

    public function test_cash_book_and_bank_book_show_movements_for_the_selected_account(): void
    {
        app(JournalService::class)->post([
            ['chart_of_account_id' => $this->expense->id, 'debit' => 150],
            ['chart_of_account_id' => $this->bank->id, 'credit' => 150],
        ], ['tenant_id' => $this->tenant->id, 'journal_date' => now()]);

        $this->as($this->accountant)
            ->get(route('accounting.reports.bank-book', ['account_id' => $this->bank->id]))
            ->assertOk()
            ->assertSee('150.00');

        $this->as($this->accountant)
            ->get(route('accounting.reports.cash-book'))
            ->assertOk();
    }

    public function test_purchase_and_sales_vouchers_post_balanced_journals(): void
    {
        $vendor = ChartOfAccount::create([
            'tenant_id' => $this->tenant->id, 'code' => '2010', 'name' => 'Accounts Payable', 'type' => ChartOfAccount::TYPE_LIABILITY,
            'normal_balance' => ChartOfAccount::BALANCE_CREDIT,
        ]);

        $response = $this->as($this->accountant)->post(route('accounting.vouchers.purchase.store'), [
            'voucher_date' => now()->toDateString(),
            'party_name' => 'Acme Supplies',
            'items' => [
                ['chart_of_account_id' => $this->expense->id, 'debit' => 500, 'credit' => 0],
                ['chart_of_account_id' => $vendor->id, 'debit' => 0, 'credit' => 500],
            ],
        ]);

        $response->assertRedirect();
        $this->assertSame(1, Journal::where('voucher_type', 'purchase')->count());

        $customer = ChartOfAccount::create([
            'tenant_id' => $this->tenant->id, 'code' => '1100', 'name' => 'Accounts Receivable', 'type' => ChartOfAccount::TYPE_ASSET,
            'normal_balance' => ChartOfAccount::BALANCE_DEBIT,
        ]);

        $this->as($this->accountant)->post(route('accounting.vouchers.sales.store'), [
            'voucher_date' => now()->toDateString(),
            'party_name' => 'Acme Customer',
            'items' => [
                ['chart_of_account_id' => $customer->id, 'debit' => 700, 'credit' => 0],
                ['chart_of_account_id' => $this->income->id, 'debit' => 0, 'credit' => 700],
            ],
        ])->assertRedirect();

        $this->assertSame(1, Journal::where('voucher_type', 'sales')->count());
    }

    public function test_ledger_group_can_be_created_and_assigned_to_an_account(): void
    {
        $this->as($this->accountant)->post(route('accounting.ledger-groups.store'), [
            'code' => 'LG-CURASS', 'name' => 'Current Assets', 'nature' => ChartOfAccount::TYPE_ASSET,
        ])->assertRedirect();

        $group = LedgerGroup::where('code', 'LG-CURASS')->firstOrFail();

        $this->as($this->accountant)->put(route('accounting.chart-of-accounts.update', $this->bank), [
            'code' => $this->bank->code, 'name' => $this->bank->name, 'type' => $this->bank->type,
            'normal_balance' => $this->bank->normal_balance, 'ledger_group_id' => $group->id,
            'is_cash_or_bank' => true, 'is_active' => true,
        ])->assertRedirect();

        $this->assertSame($group->id, $this->bank->fresh()->ledger_group_id);
    }

    public function test_opening_balance_posts_a_journal_against_the_ledger(): void
    {
        ChartOfAccount::create([
            'tenant_id' => $this->tenant->id, 'code' => '3020', 'name' => 'Reserves & Surplus', 'type' => ChartOfAccount::TYPE_EQUITY,
            'normal_balance' => ChartOfAccount::BALANCE_CREDIT,
        ]);

        $this->as($this->accountant)->post(route('accounting.chart-of-accounts.store'), [
            'code' => '1099', 'name' => 'Petty Cash', 'type' => ChartOfAccount::TYPE_ASSET,
            'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'is_cash_or_bank' => true,
            'is_active' => true, 'opening_balance' => 1000, 'opening_balance_type' => 'debit',
        ])->assertRedirect();

        $account = ChartOfAccount::where('code', '1099')->firstOrFail();
        $journal = Journal::where('reference_type', 'chart_of_account_opening_balance')
            ->where('reference_id', $account->id)->first();

        $this->assertNotNull($journal);
        $this->assertEquals(1000, $journal->total_debit);
    }

    public function test_production_material_issue_posts_a_wip_journal(): void
    {
        ChartOfAccount::create([
            'tenant_id' => $this->tenant->id, 'code' => '1204', 'name' => 'Work-in-Progress', 'type' => ChartOfAccount::TYPE_ASSET,
            'normal_balance' => ChartOfAccount::BALANCE_DEBIT,
        ]);
        ChartOfAccount::create([
            'tenant_id' => $this->tenant->id, 'code' => '1200', 'name' => 'Inventory', 'type' => ChartOfAccount::TYPE_ASSET,
            'normal_balance' => ChartOfAccount::BALANCE_DEBIT,
        ]);

        $warehouse = Warehouse::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Main Warehouse', 'code' => 'WH-1', 'status' => 'active',
        ]);
        $product = Product::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Raw Material', 'sku' => 'RM-1',
            'type' => 'raw_material', 'status' => 'active', 'unit_cost' => 25.00,
        ]);

        StockService::recordInflow($this->tenant->id, $product->id, $warehouse->id, 50, 25.0, 'Opening Stock');

        $transaction = StockService::recordOutflow(
            $this->tenant->id, $product->id, $warehouse->id, 10, 'Production Material Issue', 999
        );

        $journal = Journal::where('source', Journal::SOURCE_PRODUCTION)
            ->where('reference_type', 'stock_transaction')
            ->where('reference_id', $transaction->id)
            ->first();

        $this->assertNotNull($journal);
        $this->assertEqualsWithDelta((float) $transaction->total_value, (float) $journal->total_debit, 0.01);
    }
}
