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

/**
 * A contra account carries a normal_balance opposite its type's canonical
 * direction (e.g. Sales Returns is income-type but debit-normal). Summing
 * ChartOfAccount::signedMovement() across a type/subtype bucket adds a contra
 * account's balance instead of subtracting it — this locks in the fix
 * (canonicalMovement()) across the reports that pool multiple accounts of the
 * same type together: Balance Sheet, P&L, Cash Flow.
 */
class AccountingContraAccountTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private ChartOfAccount $bank;
    private ChartOfAccount $capital;
    private ChartOfAccount $revenue;
    private ChartOfAccount $salesReturns;

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

        $this->bank = ChartOfAccount::create([
            'tenant_id' => $this->tenant->id,
            'code' => '1020',
            'name' => 'Bank Account',
            'type' => ChartOfAccount::TYPE_ASSET,
            'subtype' => ChartOfAccount::SUBTYPE_CURRENT_ASSET,
            'normal_balance' => ChartOfAccount::BALANCE_DEBIT,
            'is_cash_or_bank' => true,
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

        // Contra-income: income type, but debit-normal — a refund should
        // *reduce* net income/equity, not add to it.
        $this->salesReturns = ChartOfAccount::create([
            'tenant_id' => $this->tenant->id,
            'code' => '4030',
            'name' => 'Sales Returns & Allowances',
            'type' => ChartOfAccount::TYPE_INCOME,
            'subtype' => ChartOfAccount::SUBTYPE_DIRECT_INCOME,
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
    public function contra_income_account_reduces_profit_and_balance_sheet_stays_balanced(): void
    {
        $journals = app(JournalService::class);

        // Capital: Dr Bank 100,000 / Cr Capital 100,000
        $journals->post([
            ['chart_of_account_id' => $this->bank->id, 'debit' => 100000, 'credit' => 0],
            ['chart_of_account_id' => $this->capital->id, 'debit' => 0, 'credit' => 100000],
        ], ['tenant_id' => $this->tenant->id, 'journal_date' => now()]);

        // Sale: Dr Bank 20,000 / Cr Revenue 20,000
        $journals->post([
            ['chart_of_account_id' => $this->bank->id, 'debit' => 20000, 'credit' => 0],
            ['chart_of_account_id' => $this->revenue->id, 'debit' => 0, 'credit' => 20000],
        ], ['tenant_id' => $this->tenant->id, 'journal_date' => now()]);

        // Refund a return: Dr Sales Returns 5,000 / Cr Bank 5,000
        $journals->post([
            ['chart_of_account_id' => $this->salesReturns->id, 'debit' => 5000, 'credit' => 0],
            ['chart_of_account_id' => $this->bank->id, 'debit' => 0, 'credit' => 5000],
        ], ['tenant_id' => $this->tenant->id, 'journal_date' => now()]);

        $period = AccountingPeriod::where('tenant_id', $this->tenant->id)
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now())
            ->firstOrFail();

        // Bank: 100,000 + 20,000 - 5,000 = 115,000. Net profit = 20,000 - 5,000 = 15,000.
        // Equity = Capital 100,000 + Net Profit 15,000 = 115,000 = Assets — balanced,
        // only if the contra return is subtracted rather than added.
        $balanceSheet = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('accounting.reports.balance-sheet', ['period_id' => $period->id]));

        $balanceSheet->assertOk();
        $balanceSheet->assertSee('Balanced');
        $balanceSheet->assertDontSee('Out of Balance');
        $balanceSheet->assertSee('115,000.00');

        $profitLoss = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('accounting.reports.profit-loss', ['period_id' => $period->id]));

        $profitLoss->assertOk();
        $profitLoss->assertSee('15,000.00');
    }
}
