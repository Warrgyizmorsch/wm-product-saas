<?php

namespace Tests\Feature;

use App\Domains\Projects\Models\Issue;
use App\Domains\Projects\Models\Milestone;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\ProjectMember;
use App\Domains\Projects\Models\Task;
use App\Domains\Projects\Models\TaskList;
use App\Domains\Projects\Models\TimeLog;
use App\Models\Access\Permission;
use App\Models\Access\Role;
use App\Models\Access\RolePermission;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectDashboardTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private User $userA;
    private User $userB;
    private User $unauthorizedUser;
    private Project $projectOnTrack;
    private Project $projectCritical;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::create([
            'name' => 'Tenant Alpha',
            'slug' => 'tenant-alpha',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->tenantB = Tenant::create([
            'name' => 'Tenant Beta',
            'slug' => 'tenant-beta',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->seed(RbacSeeder::class);

        $ownerRole = Role::query()->whereNull('tenant_id')->where('slug', 'tenant_owner')->firstOrFail();

        $this->userA = User::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Alpha Owner',
            'email' => 'alpha@example.com',
            'password' => bcrypt('password'),
        ]);
        UserRole::create(['user_id' => $this->userA->id, 'role_id' => $ownerRole->id, 'tenant_id' => $this->tenantA->id]);

        $this->userB = User::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'Beta Owner',
            'email' => 'beta@example.com',
            'password' => bcrypt('password'),
        ]);
        UserRole::create(['user_id' => $this->userB->id, 'role_id' => $ownerRole->id, 'tenant_id' => $this->tenantB->id]);

        // Create an unauthorized user in Tenant A (no projects.dashboard.view permission)
        $limitedRole = Role::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Limited Staff',
            'slug' => 'limited_staff_' . uniqid(),
            'is_system' => false,
        ]);
        $this->unauthorizedUser = User::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Limited User',
            'email' => 'limited@example.com',
            'password' => bcrypt('password'),
        ]);
        UserRole::create(['user_id' => $this->unauthorizedUser->id, 'role_id' => $limitedRole->id, 'tenant_id' => $this->tenantA->id]);

        // Seed Project 1 (On Track)
        $this->projectOnTrack = Project::create([
            'tenant_id' => $this->tenantA->id,
            'project_code' => 'PRJ-TRK-01',
            'name' => 'Alpha On Track Project',
            'owner_id' => $this->userA->id,
            'status' => 'Active',
            'priority' => 'Medium',
            'budget_amount' => 50000.00,
            'budget_hours' => 500.00,
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonths(3),
        ]);

        ProjectMember::create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectOnTrack->id,
            'user_id' => $this->userA->id,
            'project_role' => 'Manager',
            'hourly_rate' => 100.00,
            'cost_rate' => 50.00,
            'budget_hours' => 200.00,
            'is_active' => true,
        ]);

        $listA = TaskList::create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectOnTrack->id,
            'name' => 'Default List',
            'position' => 1,
        ]);

        $taskA = Task::create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectOnTrack->id,
            'task_list_id' => $listA->id,
            'task_code' => 'TSK-001',
            'title' => 'Normal Task',
            'assigned_to' => $this->userA->id,
            'status' => 'In Progress',
            'priority' => 'Medium',
            'start_date' => now()->subDays(5),
            'due_date' => now()->addDays(10),
            'estimated_hours' => 20.00,
        ]);

        TimeLog::create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectOnTrack->id,
            'task_id' => $taskA->id,
            'user_id' => $this->userA->id,
            'log_date' => now()->subDay(),
            'hours' => 10.00,
            'is_billable' => true,
            'approval_status' => 'Approved',
            'hourly_rate' => 50.00,
        ]);

        // Seed Project 2 (Critical: overdue milestone and open critical defect)
        $this->projectCritical = Project::create([
            'tenant_id' => $this->tenantA->id,
            'project_code' => 'PRJ-CRT-02',
            'name' => 'Alpha Critical Project',
            'owner_id' => $this->userA->id,
            'status' => 'Active',
            'priority' => 'High',
            'budget_amount' => 10000.00,
            'budget_hours' => 100.00,
            'start_date' => now()->subMonths(2),
            'end_date' => now()->addMonth(),
        ]);

        Milestone::create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectCritical->id,
            'name' => 'Overdue Milestone Alpha',
            'status' => 'Active',
            'due_date' => now()->subDays(5), // overdue
            'billing_amount' => 5000.00,
        ]);

        Issue::create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectCritical->id,
            'issue_number' => 'ISS-001',
            'title' => 'System Blocker Issue',
            'severity' => 'Critical',
            'status' => 'Open',
            'reporter_id' => $this->userA->id,
        ]);

        ProjectMember::create([
            'tenant_id' => $this->tenantA->id,
            'project_id' => $this->projectOnTrack->id,
            'user_id' => $this->userA->id,
            'budget_hours' => 50,
            'is_active' => true,
        ]);

        // Seed Tenant B Project (to test multi-tenant isolation)
        Project::create([
            'tenant_id' => $this->tenantB->id,
            'project_code' => 'PRJ-BETA-01',
            'name' => 'Beta Secret Project',
            'owner_id' => $this->userB->id,
            'status' => 'Active',
            'priority' => 'Critical',
            'budget_amount' => 999999.00,
            'budget_hours' => 9999.00,
            'start_date' => now(),
            'end_date' => now()->addMonths(6),
        ]);
    }

    public function test_unauthorized_user_cannot_access_executive_dashboard(): void
    {
        $response = $this->actingAs($this->unauthorizedUser)
            ->withHeader('X-Tenant', $this->tenantA->slug)
            ->get(route('projects.dashboard'));

        $response->assertStatus(403);
    }

    public function test_authorized_user_can_view_dashboard_with_kpis_and_health_breakdown(): void
    {
        $response = $this->actingAs($this->userA)
            ->withHeader('X-Tenant', $this->tenantA->slug)
            ->get(route('projects.dashboard'));

        $response->assertStatus(200);
        $response->assertViewIs('modules.projects.dashboard');

        // Check KPI metrics present in view
        $response->assertSee('PRJ-TRK-01');
        $response->assertSee('PRJ-CRT-02');
        // Tenant B data must not be visible
        $response->assertDontSee('PRJ-BETA-01');

        $kpis = $response->viewData('kpis');
        $healthList = $response->viewData('healthList');
        $this->assertNotNull($kpis);
        $this->assertEquals(2, $kpis->active_projects);
        $this->assertEquals(60000.00, $kpis->total_budget_amount);
        $this->assertEquals(500.00, $kpis->total_incurred_cost); // 10 hrs * $50 hourly_rate
        $this->assertEquals(1, $kpis->overdue_milestones_count);
        $this->assertEquals(1, $kpis->critical_issues_count);

        // Portfolio health calculation: 1 On Track out of 2 Active = 50%
        $this->assertEquals(50.0, $kpis->portfolio_health_score);
        $this->assertCount(2, $healthList);
    }

    public function test_dashboard_respects_date_presets(): void
    {
        $response = $this->actingAs($this->userA)
            ->withHeader('X-Tenant', $this->tenantA->slug)
            ->get(route('projects.dashboard', ['preset' => 'today']));

        $response->assertStatus(200);
        $kpis = $response->viewData('kpis');
        $this->assertNotNull($kpis);
    }

    public function test_dashboard_csv_export_returns_file_download(): void
    {
        $response = $this->actingAs($this->userA)
            ->withHeader('X-Tenant', $this->tenantA->slug)
            ->get(route('projects.dashboard.export', ['format' => 'csv']));

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('attachment; filename=', $response->headers->get('content-disposition'));
        $content = $response->streamedContent();
        $this->assertStringContainsString('KPI Metric', $content);
        $this->assertStringContainsString('Active Projects', $content);
    }

    public function test_sidebar_includes_dashboard_and_reports_links(): void
    {
        $response = $this->actingAs($this->userA)
            ->withHeader('X-Tenant', $this->tenantA->slug)
            ->get(route('projects.index'));

        $response->assertStatus(200);
        $response->assertSee(route('projects.dashboard'));
        $response->assertSee(route('projects.reports.index'));
        $response->assertSee(__('projects.executive_dashboard'));
        $response->assertSee(__('projects.reports'));
    }
}
