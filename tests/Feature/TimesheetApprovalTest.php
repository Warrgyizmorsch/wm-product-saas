<?php

namespace Tests\Feature;

use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\ProjectMember;
use App\Domains\Projects\Models\Task;
use App\Domains\Projects\Models\TaskList;
use App\Domains\Projects\Models\TimeLog;
use App\Domains\Projects\Services\TimeLogService;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TimesheetApprovalTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $tenantOwner;
    private User $projectManager;
    private User $developer;
    private Project $project;
    private TaskList $taskList;
    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name'   => 'Approval Tenant',
            'slug'   => 'approval-tenant',
            'status' => 'active',
            'plan'   => 'enterprise',
        ]);

        $this->seed(RbacSeeder::class);

        $this->tenantOwner = User::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Owner User',
            'email'     => 'owner@example.com',
            'password'  => bcrypt('password'),
        ]);

        $ownerRole = Role::query()->whereNull('tenant_id')->where('slug', 'tenant_owner')->firstOrFail();
        UserRole::create([
            'user_id'   => $this->tenantOwner->id,
            'role_id'   => $ownerRole->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $this->projectManager = User::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Project Manager',
            'email'     => 'pm@example.com',
            'password'  => bcrypt('password'),
        ]);
        UserRole::create([
            'user_id'   => $this->projectManager->id,
            'role_id'   => $ownerRole->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $this->developer = User::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Developer Resource',
            'email'     => 'dev@example.com',
            'password'  => bcrypt('password'),
        ]);
        UserRole::create([
            'user_id'   => $this->developer->id,
            'role_id'   => $ownerRole->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $this->project = Project::create([
            'tenant_id'    => $this->tenant->id,
            'project_code' => 'PRJ-0001',
            'name'         => 'Core Platform',
            'owner_id'     => $this->tenantOwner->id,
            'manager_id'   => $this->projectManager->id,
            'start_date'   => now(),
            'priority'     => 'High',
            'status'       => 'Active',
        ]);

        ProjectMember::create([
            'tenant_id'     => $this->tenant->id,
            'project_id'    => $this->project->id,
            'user_id'       => $this->developer->id,
            'project_role'  => 'Senior Developer',
            'rate_per_hour' => 80.00,
            'is_active'     => true,
        ]);

        $this->taskList = TaskList::create([
            'tenant_id'  => $this->tenant->id,
            'project_id' => $this->project->id,
            'name'       => 'Feature Sprint',
            'position'   => 1,
        ]);

        $this->task = Task::create([
            'tenant_id'       => $this->tenant->id,
            'project_id'      => $this->project->id,
            'task_list_id'    => $this->taskList->id,
            'task_code'       => 'PRJ-0001-T-001',
            'title'           => 'Architecture Spike',
            'assignee_id'     => $this->developer->id,
            'status'          => Task::STATUS_IN_PROGRESS,
            'estimated_hours' => 20.00,
            'actual_hours'    => 0.00,
        ]);
    }

    public function test_manager_can_view_timesheet_approval_queue(): void
    {
        TimeLog::create([
            'tenant_id'       => $this->tenant->id,
            'project_id'      => $this->project->id,
            'task_id'         => $this->task->id,
            'user_id'         => $this->developer->id,
            'log_date'        => '2026-09-29',
            'hours'           => 3.50,
            'approval_status' => TimeLog::STATUS_PENDING,
        ]);

        $response = $this->actingAs($this->projectManager)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->get(route('projects.timesheets.approval'));

        $response->assertOk();
        $response->assertSee('3.50');
        $response->assertSee('Architecture Spike');
    }

    public function test_approving_time_log_updates_status_and_rolls_up_to_task_actual_hours(): void
    {
        $log = TimeLog::create([
            'tenant_id'       => $this->tenant->id,
            'project_id'      => $this->project->id,
            'task_id'         => $this->task->id,
            'user_id'         => $this->developer->id,
            'log_date'        => '2026-09-29',
            'hours'           => 5.25,
            'approval_status' => TimeLog::STATUS_PENDING,
        ]);

        $response = $this->actingAs($this->projectManager)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->patch(route('projects.timesheets.approve', $log));

        $response->assertRedirect();

        $log->refresh();
        $this->assertEquals(TimeLog::STATUS_APPROVED, $log->approval_status);
        $this->assertEquals($this->projectManager->id, $log->approved_by);
        $this->assertNotNull($log->approved_at);

        // Verify task actual_hours rolled up
        $this->task->refresh();
        $this->assertEquals(5.25, (float) $this->task->actual_hours);
    }

    public function test_separation_of_duty_prevents_user_from_approving_own_time_log(): void
    {
        $this->actingAs($this->developer);

        $log = TimeLog::create([
            'tenant_id'       => $this->tenant->id,
            'project_id'      => $this->project->id,
            'task_id'         => $this->task->id,
            'user_id'         => $this->developer->id,
            'log_date'        => '2026-09-29',
            'hours'           => 4.00,
            'approval_status' => TimeLog::STATUS_PENDING,
        ]);

        $service = app(TimeLogService::class);

        $this->expectException(ValidationException::class);
        $service->approve($log, $this->developer);
    }

    public function test_separation_of_duty_prevents_user_from_rejecting_own_time_log(): void
    {
        $this->actingAs($this->developer);

        $log = TimeLog::create([
            'tenant_id'       => $this->tenant->id,
            'project_id'      => $this->project->id,
            'task_id'         => $this->task->id,
            'user_id'         => $this->developer->id,
            'log_date'        => '2026-09-29',
            'hours'           => 4.00,
            'approval_status' => TimeLog::STATUS_PENDING,
        ]);

        $service = app(TimeLogService::class);

        $this->expectException(ValidationException::class);
        $service->reject($log, $this->developer, 'Self rejection attempt');
    }

    public function test_rejecting_time_log_records_remarks_and_excludes_from_actual_hours(): void
    {
        $log = TimeLog::create([
            'tenant_id'       => $this->tenant->id,
            'project_id'      => $this->project->id,
            'task_id'         => $this->task->id,
            'user_id'         => $this->developer->id,
            'log_date'        => '2026-09-29',
            'hours'           => 4.00,
            'approval_status' => TimeLog::STATUS_PENDING,
        ]);

        $response = $this->actingAs($this->projectManager)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->patch(route('projects.timesheets.reject', $log), [
                'rejection_remarks' => 'Overlapping hours with another client engagement',
            ]);

        $response->assertRedirect();

        $log->refresh();
        $this->assertEquals(TimeLog::STATUS_REJECTED, $log->approval_status);
        $this->assertEquals('Overlapping hours with another client engagement', $log->rejection_remarks);

        // Task actual_hours remains 0
        $this->task->refresh();
        $this->assertEquals(0.00, (float) $this->task->actual_hours);
    }

    public function test_multiple_approved_time_logs_accumulate_accurately_in_task_actual_hours(): void
    {
        $service = app(TimeLogService::class);

        $log1 = TimeLog::create([
            'tenant_id'       => $this->tenant->id,
            'project_id'      => $this->project->id,
            'task_id'         => $this->task->id,
            'user_id'         => $this->developer->id,
            'log_date'        => '2026-09-27',
            'hours'           => 3.25,
            'approval_status' => TimeLog::STATUS_PENDING,
        ]);

        $log2 = TimeLog::create([
            'tenant_id'       => $this->tenant->id,
            'project_id'      => $this->project->id,
            'task_id'         => $this->task->id,
            'user_id'         => $this->developer->id,
            'log_date'        => '2026-09-28',
            'hours'           => 4.75,
            'approval_status' => TimeLog::STATUS_PENDING,
        ]);

        $service->approve($log1, $this->projectManager);
        $this->task->refresh();
        $this->assertEquals(3.25, (float) $this->task->actual_hours);

        $service->approve($log2, $this->projectManager);
        $this->task->refresh();
        $this->assertEquals(8.00, (float) $this->task->actual_hours);
    }
}
