<?php

namespace Tests\Feature;

use App\Domains\Accounting\Models\AccountingPeriod;
use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Services\FiscalPeriodService;
use App\Domains\Accounting\Services\JournalService;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashFlowTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private ChartOfAccount $cash;
    private ChartOfAccount $capital;
    private ChartOfAccount $bank;
    private ChartOfAccount $revenue;
    private ChartOfAccount $expense;
    private ChartOfAccount $payable;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->seed(RbacSeeder::class);

        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Accountant User',
            'email' => 'accountant@example.com',
            'password' => bcrypt('password'),
        ]);

        $role = Role::query()->whereNull('tenant_id')->where('slug', 'accountant')->firstOrFail();
        UserRole::create(['user_id' => $this->user->id, 'role_id' => $role->id, 'tenant_id' => $this->tenant->id]);

        $this->cash = ChartOfAccount::create([
            'tenant_id' => $this->tenant->id,
            'code' => '1010',
            'name' => 'Cash on Hand',
            'type' => ChartOfAccount::TYPE_ASSET,
            'subtype' => ChartOfAccount::SUBTYPE_CURRENT_ASSET,
            'normal_balance' => ChartOfAccount::BALANCE_DEBIT,
            'is_cash_or_bank' => true,
        ]);

        $this->bank = ChartOfAccount::create([
            'tenant_id' => $this->tenant->id,
            'code' => '1020',
            'name' => 'Bank Account',
            'type' => ChartOfAccount::TYPE_ASSET,
            'subtype' => ChartOfAccount::SUBTYPE_CURRENT_ASSET,
            'normal_balance' => ChartOfAccount::BALANCE_DEBIT,
            'is_cash_or_bank' => true,
        ]);

        $this->payable = ChartOfAccount::create([
            'tenant_id' => $this->tenant->id,
            'code' => '2010',
            'name' => 'Accounts Payable',
            'type' => ChartOfAccount::TYPE_LIABILITY,
            'subtype' => ChartOfAccount::SUBTYPE_CURRENT_LIABILITY,
            'normal_balance' => ChartOfAccount::BALANCE_CREDIT,
        ]);

        $this->capital = ChartOfAccount::create([
            'tenant_id' => $this->tenant->id,
            'code' => '3010',
            'name' => "Owner's Capital",
            'type' => ChartOfAccount::TYPE_EQUITY,
            'subtype' => ChartOfAccount::SUBTYPE_CAPITAL,
            'normal_balance' => ChartOfAccount::BALANCE_CREDIT,
        ]);

        $this->revenue = ChartOfAccount::create([
            'tenant_id' => $this->tenant->id,
            'code' => '4010',
            'name' => 'Sales Revenue',
            'type' => ChartOfAccount::TYPE_INCOME,
            'subtype' => ChartOfAccount::SUBTYPE_DIRECT_INCOME,
            'normal_balance' => ChartOfAccount::BALANCE_CREDIT,
        ]);

        $this->expense = ChartOfAccount::create([
            'tenant_id' => $this->tenant->id,
            'code' => '5200',
            'name' => 'Rent Expense',
            'type' => ChartOfAccount::TYPE_EXPENSE,
            'subtype' => ChartOfAccount::SUBTYPE_OPERATING_EXPENSE,
            'normal_balance' => ChartOfAccount::BALANCE_DEBIT,
        ]);

        app(FiscalPeriodService::class)->createFiscalYearWithMonthlyPeriods([
            'tenant_id' => $this->tenant->id,
            'name' => 'FY ' . now()->year,
            'start_date' => now()->startOfYear()->toDateString(),
            'end_date' => now()->endOfYear()->toDateString(),
        ]);
    }

    /** @test */
    public function cash_flow_reconciles_to_cash_and_bank_movement(): void
    {
        $journals = app(JournalService::class);

        // Owner injects capital: Dr Bank 10,000 / Cr Capital 10,000 (financing)
        $journals->post([
            ['chart_of_account_id' => $this->bank->id, 'debit' => 10000, 'credit' => 0],
            ['chart_of_account_id' => $this->capital->id, 'debit' => 0, 'credit' => 10000],
        ], ['tenant_id' => $this->tenant->id, 'journal_date' => now()]);

        // Post revenue: Dr Cash 2000 / Cr Revenue 2000 (operating, via net profit)
        $journals->post([
            ['chart_of_account_id' => $this->cash->id, 'debit' => 2000, 'credit' => 0],
            ['chart_of_account_id' => $this->revenue->id, 'debit' => 0, 'credit' => 2000],
        ], ['tenant_id' => $this->tenant->id, 'journal_date' => now()]);

        // Pay rent: Dr Expense 500 / Cr Bank 500 (operating, via net profit)
        $journals->post([
            ['chart_of_account_id' => $this->expense->id, 'debit' => 500, 'credit' => 0],
            ['chart_of_account_id' => $this->bank->id, 'debit' => 0, 'credit' => 500],
        ], ['tenant_id' => $this->tenant->id, 'journal_date' => now()]);

        // Take on a payable: Dr Cash 300 / Cr Accounts Payable 300 (operating working capital)
        $journals->post([
            ['chart_of_account_id' => $this->cash->id, 'debit' => 300, 'credit' => 0],
            ['chart_of_account_id' => $this->payable->id, 'debit' => 0, 'credit' => 300],
        ], ['tenant_id' => $this->tenant->id, 'journal_date' => now()]);

        $period = AccountingPeriod::where('tenant_id', $this->tenant->id)
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now())
            ->firstOrFail();

        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('accounting.reports.cash-flow', ['period_id' => $period->id]));

        $response->assertOk();

        // Net profit = 2000 - 500 = 1500; + Accounts Payable +300 = Operating 1,800.00
        // Investing = 0; Financing = Capital +10,000.
        // Net change in cash = 1,800 + 0 + 10,000 = 11,800 = actual Cash+Bank movement (2,300 + 9,500).
        $response->assertSee('Reconciled to Cash');
        $response->assertSee('1,800.00');
        $response->assertSee('10,000.00');
        $response->assertSee('11,800.00');
    }

    /** @test */
    public function unrelated_role_is_forbidden_from_viewing_cash_flow(): void
    {
        $reader = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Read Only',
            'email' => 'reader@example.com',
            'password' => bcrypt('password'),
        ]);
        $role = Role::query()->whereNull('tenant_id')->where('slug', 'read_only')->firstOrFail();
        UserRole::create(['user_id' => $reader->id, 'role_id' => $role->id, 'tenant_id' => $this->tenant->id]);

        $this->actingAs($reader)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('accounting.reports.cash-flow'))
            ->assertForbidden();
    }
}
