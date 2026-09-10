<?php

namespace Tests\Feature;

use App\Domains\Accounting\Models\Budget;
use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\CostCenter;
use App\Domains\Accounting\Models\FiscalYear;
use App\Domains\Accounting\Services\BudgetService;
use App\Domains\Accounting\Services\FiscalPeriodService;
use App\Domains\Accounting\Services\JournalService;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountingBudgetTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $accountant;
    private User $owner;
    private ChartOfAccount $bank;
    private ChartOfAccount $expense;
    private FiscalYear $fiscalYear;

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

        $this->accountant = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Accountant User',
            'email' => 'accountant@example.com',
            'password' => bcrypt('password'),
        ]);
        UserRole::create([
            'user_id' => $this->accountant->id,
            'role_id' => Role::query()->whereNull('tenant_id')->where('slug', 'accountant')->firstOrFail()->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $this->owner = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Owner User',
            'email' => 'owner@example.com',
            'password' => bcrypt('password'),
        ]);
        UserRole::create([
            'user_id' => $this->owner->id,
            'role_id' => Role::query()->whereNull('tenant_id')->where('slug', 'tenant_owner')->firstOrFail()->id,
            'tenant_id' => $this->tenant->id,
        ]);

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

        $this->fiscalYear = app(FiscalPeriodService::class)->createFiscalYearWithMonthlyPeriods([
            'tenant_id' => $this->tenant->id,
            'name' => 'FY ' . now()->year,
            'start_date' => now()->startOfYear()->toDateString(),
            'end_date' => now()->endOfYear()->toDateString(),
        ]);
    }

    /** @test */
    public function accountant_can_create_edit_and_delete_a_draft_budget(): void
    {
        $store = $this->actingAs($this->accountant)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('accounting.budgets.store'), [
                'fiscal_year_id' => $this->fiscalYear->id,
                'name' => 'FY Operating Budget',
                'lines' => [
                    ['chart_of_account_id' => $this->expense->id, 'amount' => 12000],
                ],
            ]);
        $store->assertRedirect();

        $budget = Budget::where('name', 'FY Operating Budget')->firstOrFail();
        $this->assertSame(Budget::STATUS_DRAFT, $budget->status);
        $this->assertSame(1, $budget->lines()->count());

        $update = $this->actingAs($this->accountant)
            ->withHeader('X-Tenant', 'test-tenant')
            ->put(route('accounting.budgets.update', $budget), [
                'fiscal_year_id' => $this->fiscalYear->id,
                'name' => 'FY Operating Budget',
                'lines' => [
                    ['chart_of_account_id' => $this->expense->id, 'amount' => 15000],
                ],
            ]);
        $update->assertRedirect();
        $this->assertSame(15000.0, (float) $budget->lines()->first()->amount);

        $destroy = $this->actingAs($this->accountant)
            ->withHeader('X-Tenant', 'test-tenant')
            ->delete(route('accounting.budgets.destroy', $budget));
        $destroy->assertRedirect();
        $this->assertDatabaseMissing('budgets', ['id' => $budget->id]);
    }

    /** @test */
    public function approving_a_budget_is_reserved_for_tenant_owner_not_accountant(): void
    {
        $budget = app(BudgetService::class)->create([
            'tenant_id' => $this->tenant->id,
            'fiscal_year_id' => $this->fiscalYear->id,
            'name' => 'FY Budget',
        ], [
            ['chart_of_account_id' => $this->expense->id, 'amount' => 5000],
        ]);

        $deniedResponse = $this->actingAs($this->accountant)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('accounting.budgets.approve', $budget));
        $deniedResponse->assertForbidden();
        $this->assertSame(Budget::STATUS_DRAFT, $budget->fresh()->status);

        $allowedResponse = $this->actingAs($this->owner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('accounting.budgets.approve', $budget));
        $allowedResponse->assertRedirect();
        $this->assertSame(Budget::STATUS_APPROVED, $budget->fresh()->status);

        // Once approved, it's no longer editable or deletable.
        $editAttempt = $this->actingAs($this->accountant)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('accounting.budgets.edit', $budget));
        $editAttempt->assertForbidden();

        $deleteAttempt = $this->actingAs($this->accountant)
            ->withHeader('X-Tenant', 'test-tenant')
            ->delete(route('accounting.budgets.destroy', $budget));
        $deleteAttempt->assertRedirect();
        $this->assertDatabaseHas('budgets', ['id' => $budget->id]);
    }

    /** @test */
    public function tenant_cannot_see_or_approve_another_tenants_budget(): void
    {
        $otherTenant = Tenant::create([
            'name' => 'Other Tenant',
            'slug' => 'other-tenant',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $otherFiscalYear = app(FiscalPeriodService::class)->createFiscalYearWithMonthlyPeriods([
            'tenant_id' => $otherTenant->id,
            'name' => 'FY ' . now()->year,
            'start_date' => now()->startOfYear()->toDateString(),
            'end_date' => now()->endOfYear()->toDateString(),
        ]);

        $otherExpense = ChartOfAccount::create([
            'tenant_id' => $otherTenant->id,
            'code' => '5200',
            'name' => 'Other Rent Expense',
            'type' => ChartOfAccount::TYPE_EXPENSE,
            'normal_balance' => ChartOfAccount::BALANCE_DEBIT,
        ]);

        $otherBudget = app(BudgetService::class)->create([
            'tenant_id' => $otherTenant->id,
            'fiscal_year_id' => $otherFiscalYear->id,
            'name' => 'Other Tenant Budget',
        ], [
            ['chart_of_account_id' => $otherExpense->id, 'amount' => 1000],
        ]);

        // Budget uses tenant-scoped route-model binding (BelongsToTenant global
        // scope), so a cross-tenant id resolves to nothing — a 404, not a 403.
        // This is the same behavior as every other tenant-owned model in this
        // app and is arguably safer than a 403 (it doesn't leak existence).
        $response = $this->actingAs($this->owner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('accounting.budgets.edit', $otherBudget));
        $response->assertNotFound();

        $approve = $this->actingAs($this->owner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('accounting.budgets.approve', $otherBudget));
        $approve->assertNotFound();
    }

    /** @test */
    public function budget_vs_actual_computes_actual_and_variance_and_nets_reversed_journals_to_zero(): void
    {
        $costCenter = CostCenter::create(['tenant_id' => $this->tenant->id, 'code' => 'CC-OPS', 'name' => 'Ops', 'is_active' => true]);

        $budget = app(BudgetService::class)->create([
            'tenant_id' => $this->tenant->id,
            'fiscal_year_id' => $this->fiscalYear->id,
            'name' => 'FY Ops Budget',
        ], [
            ['chart_of_account_id' => $this->expense->id, 'cost_center_id' => $costCenter->id, 'amount' => 1000],
        ]);
        app(BudgetService::class)->approve($budget, $this->owner->id);

        $journals = app(JournalService::class);
        $journal = $journals->post([
            ['chart_of_account_id' => $this->expense->id, 'debit' => 900, 'cost_center_id' => $costCenter->id],
            ['chart_of_account_id' => $this->bank->id, 'credit' => 900],
        ], ['tenant_id' => $this->tenant->id, 'journal_date' => now()]);

        $rows = app(BudgetService::class)->actualVsBudget($budget->fresh(['lines.account', 'lines.costCenter']));
        $this->assertCount(1, $rows);
        $this->assertSame(1000.0, $rows[0]['budgeted']);
        $this->assertSame(900.0, $rows[0]['actual']);
        $this->assertSame(100.0, $rows[0]['variance']);
        $this->assertSame(BudgetService::STATUS_WARNING, $rows[0]['status']);

        // Reversing the journal should bring the actual back to zero, not double-count.
        $journals->reverse($journal->id);
        $rowsAfterReversal = app(BudgetService::class)->actualVsBudget($budget->fresh(['lines.account', 'lines.costCenter']));
        $this->assertSame(0.0, $rowsAfterReversal[0]['actual']);
        $this->assertSame(BudgetService::STATUS_OK, $rowsAfterReversal[0]['status']);
    }

    /** @test */
    public function department_and_project_scoped_lines_report_no_dimension_data_instead_of_a_false_zero(): void
    {
        $budget = app(BudgetService::class)->create([
            'tenant_id' => $this->tenant->id,
            'fiscal_year_id' => $this->fiscalYear->id,
            'name' => 'FY Department Budget',
        ], [
            ['chart_of_account_id' => $this->expense->id, 'department_id' => 1, 'amount' => 5000],
        ]);

        $rows = app(BudgetService::class)->actualVsBudget($budget->fresh(['lines.account', 'lines.costCenter']));

        $this->assertFalse($rows[0]['has_actuals']);
        $this->assertNull($rows[0]['actual']);
    }
}
