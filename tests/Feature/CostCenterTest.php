<?php

namespace Tests\Feature;

use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\CostCenter;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Services\FiscalPeriodService;
use App\Domains\Accounting\Services\JournalService;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CostCenterTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private ChartOfAccount $bank;
    private ChartOfAccount $expense;

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
            'normal_balance' => ChartOfAccount::BALANCE_DEBIT,
        ]);

        $this->expense = ChartOfAccount::create([
            'tenant_id' => $this->tenant->id,
            'code' => '5200',
            'name' => 'Rent Expense',
            'type' => ChartOfAccount::TYPE_EXPENSE,
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
    public function accountant_can_create_and_list_cost_centers(): void
    {
        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('accounting.cost-centers.store'), [
                'code' => 'CC-SALES',
                'name' => 'Sales Department',
                'description' => 'Sales team costs',
            ]);

        $response->assertRedirect();

        $costCenter = CostCenter::where('code', 'CC-SALES')->firstOrFail();
        $this->assertSame('Sales Department', $costCenter->name);
        $this->assertTrue($costCenter->is_active);

        $indexResponse = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('accounting.cost-centers.index'));

        $indexResponse->assertOk();
        $indexResponse->assertSee('CC-SALES');
        $indexResponse->assertSee('Sales Department');
    }

    /** @test */
    public function journal_lines_can_be_tagged_with_a_cost_center_and_trial_balance_filters_by_it(): void
    {
        $sales = CostCenter::create(['tenant_id' => $this->tenant->id, 'code' => 'CC-SALES', 'name' => 'Sales', 'is_active' => true]);
        $admin = CostCenter::create(['tenant_id' => $this->tenant->id, 'code' => 'CC-ADMIN', 'name' => 'Admin', 'is_active' => true]);

        $journals = app(JournalService::class);

        // Rent for the sales office
        $journals->post([
            ['chart_of_account_id' => $this->expense->id, 'debit' => 3000, 'cost_center_id' => $sales->id],
            ['chart_of_account_id' => $this->bank->id, 'credit' => 3000],
        ], ['tenant_id' => $this->tenant->id, 'journal_date' => now()]);

        // Rent for the admin office
        $journals->post([
            ['chart_of_account_id' => $this->expense->id, 'debit' => 1000, 'cost_center_id' => $admin->id],
            ['chart_of_account_id' => $this->bank->id, 'credit' => 1000],
        ], ['tenant_id' => $this->tenant->id, 'journal_date' => now()]);

        $this->assertSame(2, JournalEntry::whereNotNull('cost_center_id')->count());
        $this->assertSame(3000.0, (float) JournalEntry::where('cost_center_id', $sales->id)->where('chart_of_account_id', $this->expense->id)->value('debit'));

        $period = \App\Domains\Accounting\Models\AccountingPeriod::where('tenant_id', $this->tenant->id)
            ->where('start_date', '<=', now())
            ->where('end_date', '>=', now())
            ->firstOrFail();

        // Unfiltered trial balance sees both cost centers' rent combined (4000).
        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('accounting.reports.trial-balance', ['period_id' => $period->id]));
        $response->assertOk();
        $response->assertSee('4,000.00');

        // Filtered to Sales only, rent shows just the 3,000 sales-office line.
        $filtered = $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('accounting.reports.trial-balance', ['period_id' => $period->id, 'cost_center_id' => $sales->id]));
        $filtered->assertOk();
        $filtered->assertSee('3,000.00');
        $filtered->assertDontSee('4,000.00');
    }

    /** @test */
    public function cost_center_with_journal_entries_cannot_be_deleted(): void
    {
        // Deleting requires accounting.cost_centers.delete, deliberately withheld
        // from the accountant role (mirrors chart_of_accounts.delete) — use
        // tenant_owner here so this test exercises the in-use guard itself,
        // not the permission check.
        $owner = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Owner User',
            'email' => 'owner@example.com',
            'password' => bcrypt('password'),
        ]);
        $ownerRole = Role::query()->whereNull('tenant_id')->where('slug', 'tenant_owner')->firstOrFail();
        UserRole::create(['user_id' => $owner->id, 'role_id' => $ownerRole->id, 'tenant_id' => $this->tenant->id]);

        $sales = CostCenter::create(['tenant_id' => $this->tenant->id, 'code' => 'CC-SALES', 'name' => 'Sales', 'is_active' => true]);

        app(JournalService::class)->post([
            ['chart_of_account_id' => $this->expense->id, 'debit' => 100, 'cost_center_id' => $sales->id],
            ['chart_of_account_id' => $this->bank->id, 'credit' => 100],
        ], ['tenant_id' => $this->tenant->id, 'journal_date' => now()]);

        $response = $this->actingAs($owner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->delete(route('accounting.cost-centers.destroy', $sales->id));

        $response->assertRedirect();
        $this->assertDatabaseHas('cost_centers', ['id' => $sales->id]);
    }
}
