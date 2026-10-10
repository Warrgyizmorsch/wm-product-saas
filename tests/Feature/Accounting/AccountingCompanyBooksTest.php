<?php

namespace Tests\Feature\Accounting;

use App\Core\Branch\BranchContext;
use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Services\AccountingDashboardService;
use App\Domains\Accounting\Services\FiscalPeriodService;
use App\Domains\Accounting\Services\JournalService;
use App\Domains\Accounting\Support\DashboardPeriod;
use App\Domains\HRMS\Models\Branch;
use App\Domains\Platform\Services\DashboardService;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The books are the company's: picking a branch in the header must not hide
 * postings made at company level (no branch), which is how auto-postings from
 * queues and most manual journals are saved.
 */
class AccountingCompanyBooksTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private ChartOfAccount $bank;
    private ChartOfAccount $sales;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
        $this->tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme', 'status' => 'active', 'plan' => 'enterprise']);
        app(\App\Core\Tenant\TenantContext::class)->set($this->tenant);

        app(FiscalPeriodService::class)->createFiscalYearWithMonthlyPeriods([
            'tenant_id' => $this->tenant->id,
            'name' => 'FY '.now()->year,
            'start_date' => now()->startOfYear()->toDateString(),
            'end_date' => now()->endOfYear()->toDateString(),
        ]);

        $this->bank = ChartOfAccount::create([
            'tenant_id' => $this->tenant->id, 'code' => '1020', 'name' => 'Bank', 'type' => ChartOfAccount::TYPE_ASSET,
            'subtype' => ChartOfAccount::SUBTYPE_CURRENT_ASSET, 'normal_balance' => ChartOfAccount::BALANCE_DEBIT, 'is_cash_or_bank' => true,
        ]);
        $this->sales = ChartOfAccount::create([
            'tenant_id' => $this->tenant->id, 'code' => '4010', 'name' => 'Sales', 'type' => ChartOfAccount::TYPE_INCOME,
            'subtype' => ChartOfAccount::SUBTYPE_DIRECT_INCOME, 'normal_balance' => ChartOfAccount::BALANCE_CREDIT,
        ]);

        // A company-level posting: no branch selected when it was made.
        app(JournalService::class)->post([
            ['chart_of_account_id' => $this->bank->id, 'debit' => 5000],
            ['chart_of_account_id' => $this->sales->id, 'credit' => 5000],
        ], ['tenant_id' => $this->tenant->id, 'journal_date' => now()->startOfMonth()->toDateString()]);
    }

    private function selectBranch(int $id): void
    {
        // Only the id matters to the scopes; no branch row is needed.
        $branch = new Branch();
        $branch->id = $id;
        app(BranchContext::class)->set($branch);
    }

    public function test_company_level_postings_stay_visible_when_a_branch_is_selected(): void
    {
        $this->selectBranch(99);

        $this->assertSame(1, Journal::query()->count());
        $this->assertSame(2, JournalEntry::query()->count());
        $this->assertNotNull(ChartOfAccount::query()->find($this->bank->id));

        $today = Carbon::today();
        $summary = app(AccountingDashboardService::class)->summary(
            $this->tenant->id,
            DashboardPeriod::resolve(['preset' => 'this_month'], $today),
            false,
            null,
            $today,
        );

        $this->assertSame(5000.0, (float) $summary['kpis']['income']['current']);
        $this->assertSame(5000.0, (float) $summary['cash']['total']);
        $this->assertCount(1, $summary['recentJournals']);
    }

    public function test_new_postings_still_record_the_selected_branch(): void
    {
        $this->selectBranch(99);

        // Saving the row would trip the branches foreign key; checking the
        // creating hook on an unsaved model is enough.
        $journal = new Journal(['tenant_id' => $this->tenant->id]);
        $journal->getConnection()->transaction(function () use ($journal) {
            Journal::getEventDispatcher()->until('eloquent.creating: '.Journal::class, $journal);
        });

        $this->assertSame(99, (int) $journal->branch_id);
    }

    public function test_identical_duplicate_widgets_are_dropped_from_a_layout(): void
    {
        $owner = User::create(['tenant_id' => $this->tenant->id, 'name' => 'Owner', 'email' => 'owner@acme.test', 'password' => bcrypt('password')]);
        UserRole::create([
            'user_id' => $owner->id,
            'role_id' => Role::query()->whereNull('tenant_id')->where('slug', 'tenant_owner')->firstOrFail()->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $clean = app(DashboardService::class)->sanitize([
            ['id' => 'a', 'key' => 'accounting.report_links', 'w' => 6, 'h' => 2],
            ['id' => 'b', 'key' => 'accounting.financial_health', 'w' => 6, 'h' => 4],
            ['id' => 'c', 'key' => 'accounting.report_links', 'w' => 6, 'h' => 2],
            ['id' => 'd', 'key' => 'accounting.financial_health', 'w' => 6, 'h' => 4, 'config' => ['title' => 'Health (renamed)']],
        ], $owner, $this->tenant->id, 'accounting');

        $this->assertSame(['a', 'b', 'd'], array_column($clean, 'id'));
    }
}
