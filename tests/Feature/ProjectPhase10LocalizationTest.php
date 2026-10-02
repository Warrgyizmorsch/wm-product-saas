<?php

namespace Tests\Feature;

use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

class ProjectPhase10LocalizationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Localization Tenant',
            'slug' => 'loc-tenant',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->seed(RbacSeeder::class);

        $ownerRole = Role::query()->whereNull('tenant_id')->where('slug', 'tenant_owner')->firstOrFail();

        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Loc User',
            'email' => 'loc-user@example.com',
            'password' => bcrypt('password'),
        ]);
        UserRole::create(['user_id' => $this->user->id, 'role_id' => $ownerRole->id, 'tenant_id' => $this->tenant->id]);
    }

    public function test_phase10_keys_have_100_percent_parity_across_all_supported_locales(): void
    {
        $en = include base_path('lang/en/projects.php');
        $hi = include base_path('lang/hi/projects.php');
        $bg = include base_path('lang/bg/projects.php');

        $phase10Keys = [
            'executive_dashboard',
            'reports',
            'reports_directory',
            'reports_directory_desc',
            'portfolio_health',
            'active_projects_health',
            'health_on_track',
            'health_at_risk',
            'health_critical',
            'on_track',
            'high',
            'medium',
            'low',
            'critical',
            'overdue',
            'resolved',
            'overdue_and_defects',
            'team_workload_distribution',
            'defects_distribution',
            'view_report',
            'quick_export',
            'export_csv',
            'export_excel',
            'back_to_reports',
            'report_summary_name',
            'report_summary_desc',
            'report_task_status_name',
            'report_task_status_desc',
            'report_utilization_name',
            'report_utilization_desc',
            'report_timesheet_name',
            'report_timesheet_desc',
            'report_issues_name',
            'report_issues_desc',
            'report_variance_name',
            'report_variance_desc',
            'report_budget_name',
            'report_budget_desc',
            'status_draft',
            'time_period',
            'all_time',
            'this_week',
            'this_month',
            'last_month',
            'this_quarter',
            'this_year',
            'custom_range',
            'all',
            'all_clients',
            'base_budget',
            'cr_budget',
            'revised_budget',
            'actual_cost',
            'cost_variance',
            'revised_hours',
            'logged_hours',
            'completion_percent',
            'budget_consumed',
            'budget_burn',
            'open_tasks',
            'completed_tasks',
            'overdue_tasks',
            'task_number',
            'issue_number',
            'open_issues',
            'days_to_resolve',
            'resolution_date',
            'resolution_ratio',
            'planned_cost',
            'actual_completion',
            'schedule_slippage',
            'billability',
            'billable_only',
            'non_billable_only',
            'billable_hours',
            'billable_ratio',
            'billable_amount',
            'hourly_rate',
            'approval',
            'approval_status',
            'invoice_status',
            'invoiced',
            'invoiced_state',
            'unbilled',
            'unbilled_approved',
            'logged_date',
            'projects_count',
            'tracked_vs_budget',
            'total',
            'total_hours',
            'variance',
            'yes',
            'no',
            'search',
            'projects_directory',
            'status_completed',
            'no_active_projects',
            'no_members_found',
            'no_projects_found',
            'no_timelogs_found',
        ];

        foreach ($phase10Keys as $key) {
            $this->assertArrayHasKey($key, $en, "Missing English key: {$key}");
            $this->assertArrayHasKey($key, $hi, "Missing Hindi key: {$key}");
            $this->assertArrayHasKey($key, $bg, "Missing Bulgarian key: {$key}");

            $this->assertNotEmpty($en[$key], "Empty English value for key: {$key}");
            $this->assertNotEmpty($hi[$key], "Empty Hindi value for key: {$key}");
            $this->assertNotEmpty($bg[$key], "Empty Bulgarian value for key: {$key}");
        }
    }

    public function test_dashboard_and_reports_render_under_hindi_locale(): void
    {
        App::setLocale('hi');

        $responseDashboard = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->withSession(['locale' => 'hi'])
            ->get(route('projects.dashboard'));

        $responseDashboard->assertStatus(200);
        $responseDashboard->assertSee('कार्यकारी डैशबोर्ड');

        $responseReports = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->withSession(['locale' => 'hi'])
            ->get(route('projects.reports.index'));

        $responseReports->assertStatus(200);
        $responseReports->assertSee('रिपोर्ट निर्देशिका');
    }

    public function test_dashboard_and_reports_render_under_bulgarian_locale(): void
    {
        App::setLocale('bg');

        $responseDashboard = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->withSession(['locale' => 'bg'])
            ->get(route('projects.dashboard'));

        $responseDashboard->assertStatus(200);
        $responseDashboard->assertSee('Оперативно табло');

        $responseReports = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->withSession(['locale' => 'bg'])
            ->get(route('projects.reports.index'));

        $responseReports->assertStatus(200);
        $responseReports->assertSee('Каталог на отчетите');
    }
}
