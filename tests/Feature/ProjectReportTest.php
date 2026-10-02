<?php

namespace Tests\Feature;

use App\Domains\CRM\Models\Customer;
use App\Domains\Projects\Models\Issue;
use App\Domains\Projects\Models\Milestone;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\ProjectMember;
use App\Domains\Projects\Models\Task;
use App\Domains\Projects\Models\TaskList;
use App\Domains\Projects\Models\TimeLog;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectReportTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $userA;
    private User $userB;
    private User $unauthorizedUser;
    private Project $projectA;
    private Project $projectB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::create([
            'name' => 'Report Tenant Alpha',
            'slug' => 'report-tenant-alpha',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->tenantB = Tenant::create([
            'name' => 'Report Tenant Beta',
            'slug' => 'report-tenant-beta',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->seed(RbacSeeder::class);

        $ownerRole = Role::query()->whereNull('tenant_id')->where('slug', 'tenant_owner')->firstOrFail();

        $this->userA = User::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Report Alpha Owner',
            'email' => 'report-alpha@example.com',
            'password' => bcrypt('password'),
        ]);
        UserRole::create(['user_id' => $this->userA->id, 'role_id' => $ownerRole->id, 'tenant_id' => $this->tenantA->id]);

        $this->userB = User::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Report Beta Owner',
            'email' => 'report-beta@example.com',
            'password' => bcrypt('password'),
        ]);
        UserRole::create(['user_id' => $this->userB->id, 'role_id' => $ownerRole->id, 'tenant_id' => $this->tenantB->id]);

        // Unauthorized user without projects.reports.view
        $limitedRole = Role::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Report Limited',
            'slug' => 'report_limited_' . uniqid(),
            'is_system' => false,
        ]);
        $this->unauthorizedUser = User::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Report Unauthorized',
            'email' => 'report-unauth@example.com',
            'password' => bcrypt('password'),
        ]);
        UserRole::create(['user_id' => $this->unauthorizedUser->id, 'role_id' => $limitedRole->id, 'tenant_id' => $this->tenantA->id]);

        // Create Customer
        $customerA = Customer::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Acme Corporation',
            'email' => 'contact@acme.test',
        ]);

        // Seed Project for Tenant A
        $this->projectA = Project::create([
            'tenant_id' => $this->tenantA->id,
            'customer_id' => $customerA->id,
            'project_code' => 'PRJ-REP-01',
            'name' => 'Alpha Operations Project',
            'owner_id' => $this->userA->id,
            'status' => 'Active',
            'priority' => 'High',
            'budget_amount' => 75000.00,
            'budget_hours' => 600.00,
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonths(2),
        ]);

        ProjectMember::create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'user_id' => $this->userA->id,
            'project_role' => 'Lead Engineer',
            'hourly_rate' => 120.00,
            'cost_rate' => 60.00,
            'budget_hours' => 300.00,
            'is_active' => true,
        ]);

        $list = TaskList::create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'name' => 'Core Sprint',
            'position' => 1,
        ]);

        $task = Task::create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'task_list_id' => $list->id,
            'task_code' => 'TSK-REP-01',
            'title' => 'Report Fixture Task',
            'assigned_to' => $this->userA->id,
            'status' => 'In Progress',
            'priority' => 'High',
            'start_date' => now()->subDays(10),
            'due_date' => now()->addDays(5),
            'estimated_hours' => 40.00,
        ]);

        TimeLog::create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'task_id' => $task->id,
            'user_id' => $this->userA->id,
            'log_date' => now()->subDay(),
            'hours' => 15.00,
            'is_billable' => true,
            'approval_status' => 'Approved',
            'hourly_rate' => 120.00,
        ]);

        Milestone::create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'name' => 'M1 - Architecture Baseline',
            'status' => 'Active',
            'start_date' => now()->subMonth(),
            'due_date' => now()->subDays(2),
            'billing_amount' => 15000.00,
        ]);

        Issue::create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectA->id,
            'issue_number' => 'ISS-REP-01',
            'title' => 'Sample Defect for Report',
            'severity' => 'Major',
            'status' => 'Open',
            'reporter_id' => $this->userA->id,
        ]);

        // Seed Tenant B Project (to test multi-tenant isolation)
        $this->projectB = Project::create([
            'tenant_id' => $this->tenantB->id,
            'project_code' => 'PRJ-REP-BETA',
            'name' => 'Beta Secret Operations',
            'owner_id' => $this->userB->id,
            'status' => 'Active',
            'priority' => 'Critical',
            'budget_amount' => 500000.00,
            'budget_hours' => 5000.00,
            'start_date' => now(),
            'end_date' => now()->addMonths(6),
        ]);
    }

    public function test_unauthorized_user_cannot_access_reports(): void
    {
        $response = $this->actingAs($this->unauthorizedUser)
            ->withHeader('X-Tenant', $this->tenantA->slug)
            ->get(route('projects.reports.index'));

        $response->assertStatus(403);

        $responseSummary = $this->actingAs($this->unauthorizedUser)
            ->withHeader('X-Tenant', $this->tenantA->slug)
            ->get(route('projects.reports.summary'));

        $responseSummary->assertStatus(403);
    }

    public function test_authorized_user_can_view_reports_directory_index(): void
    {
        $response = $this->actingAs($this->userA)
            ->withHeader('X-Tenant', $this->tenantA->slug)
            ->get(route('projects.reports.index'));

        $response->assertStatus(200);
        $response->assertViewIs('modules.projects.reports.index');
        $response->assertSee(route('projects.reports.summary'));
        $response->assertSee(route('projects.reports.task-status'));
        $response->assertSee(route('projects.reports.resource-utilization'));
        $response->assertSee(route('projects.reports.timesheet-billability'));
        $response->assertSee(route('projects.reports.issue-defect-density'));
        $response->assertSee(route('projects.reports.milestone-variance'));
        $response->assertSee(route('projects.reports.budget-cost'));
    }

    public function test_all_seven_operational_reports_render_successfully(): void
    {
        $reports = [
            'summary' => ['view' => 'modules.projects.reports.summary', 'needle' => 'PRJ-REP-01'],
            'task-status' => ['view' => 'modules.projects.reports.task-status', 'needle' => 'TSK-REP-01'],
            'resource-utilization' => ['view' => 'modules.projects.reports.resource-utilization', 'needle' => 'Report Alpha Owner'],
            'timesheet-billability' => ['view' => 'modules.projects.reports.timesheet-billability', 'needle' => '15.00'],
            'issue-defect-density' => ['view' => 'modules.projects.reports.issue-defect-density', 'needle' => 'ISS-REP-01'],
            'milestone-variance' => ['view' => 'modules.projects.reports.milestone-variance', 'needle' => 'M1 - Architecture Baseline'],
            'budget-cost' => ['view' => 'modules.projects.reports.budget-cost', 'needle' => 'PRJ-REP-01'],
        ];

        foreach ($reports as $slug => $meta) {
            $response = $this->actingAs($this->userA)
                ->withHeader('X-Tenant', $this->tenantA->slug)
                ->get(route("projects.reports.{$slug}", ['preset' => 'all_time']));

            $response->assertStatus(200);
            $response->assertViewIs($meta['view']);
            $response->assertSee($meta['needle']);
            // Tenant B data must never bleed into Tenant A's reports
            $response->assertDontSee('PRJ-REP-BETA');
        }
    }

    public function test_reports_exports_generate_valid_files(): void
    {
        // 1. CSV export on summary report
        $csvResponse = $this->actingAs($this->userA)
            ->withHeader('X-Tenant', $this->tenantA->slug)
            ->get(route('projects.reports.export', ['report' => 'summary', 'format' => 'csv', 'preset' => 'all_time']));

        $csvResponse->assertStatus(200);
        $this->assertTrue(
            str_contains($csvResponse->headers->get('content-type'), 'text/csv') ||
            str_contains($csvResponse->headers->get('content-type'), 'text/plain')
        );
        $this->assertStringContainsString('PRJ-REP-01', $csvResponse->streamedContent());

        // 2. Excel export on budget-cost report
        $xlsxResponse = $this->actingAs($this->userA)
            ->withHeader('X-Tenant', $this->tenantA->slug)
            ->get(route('projects.reports.export', ['report' => 'budget-cost', 'format' => 'xlsx', 'preset' => 'all_time']));

        $xlsxResponse->assertStatus(200);
        $this->assertTrue(
            str_contains($xlsxResponse->headers->get('content-type'), 'spreadsheetml') ||
            str_contains($xlsxResponse->headers->get('content-type'), 'octet-stream')
        );
    }

    public function test_unsupported_or_unwhitelisted_report_returns_404(): void
    {
        $response = $this->actingAs($this->userA)
            ->withHeader('X-Tenant', $this->tenantA->slug)
            ->get('/projects/reports/malicious-unknown-report');

        $response->assertStatus(404);

        $responseInvalidFormat = $this->actingAs($this->userA)
            ->withHeader('X-Tenant', $this->tenantA->slug)
            ->get('/projects/reports/summary/export/pdf');

        $responseInvalidFormat->assertStatus(404);
    }
}
