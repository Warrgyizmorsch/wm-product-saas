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

class TimeLogTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $tenantOwner;
    private User $developer;
    private User $externalUser;
    private Project $project;
    private TaskList $taskList;
    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name'   => 'Time Tracking Tenant',
            'slug'   => 'timetrack-tenant',
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

        $this->externalUser = User::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'External Staff',
            'email'     => 'external@example.com',
            'password'  => bcrypt('password'),
        ]);
        UserRole::create([
            'user_id'   => $this->externalUser->id,
            'role_id'   => $ownerRole->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $this->project = Project::create([
            'tenant_id'    => $this->tenant->id,
            'project_code' => 'PRJ-0001',
            'name'         => 'SaaS Implementation',
            'owner_id'     => $this->tenantOwner->id,
            'start_date'   => now(),
            'priority'     => 'High',
            'status'       => 'Active',
        ]);

        ProjectMember::create([
            'tenant_id'     => $this->tenant->id,
            'project_id'    => $this->project->id,
            'user_id'       => $this->developer->id,
            'project_role'  => 'Senior Developer',
            'rate_per_hour' => 75.00,
            'is_active'     => true,
        ]);

        $this->taskList = TaskList::create([
            'tenant_id'  => $this->tenant->id,
            'project_id' => $this->project->id,
            'name'       => 'Backend Sprint',
            'position'   => 1,
        ]);

        $this->task = Task::create([
            'tenant_id'       => $this->tenant->id,
            'project_id'      => $this->project->id,
            'task_list_id'    => $this->taskList->id,
            'task_code'       => 'PRJ-0001-T-001',
            'title'           => 'Build Time Tracking Engine',
            'assignee_id'     => $this->developer->id,
            'status'          => Task::STATUS_IN_PROGRESS,
            'estimated_hours' => 10.00,
            'actual_hours'    => 0.00,
        ]);
    }

    public function test_active_collaborator_can_log_time_and_inherits_member_rate(): void
    {
        $response = $this->actingAs($this->developer)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->post(
                route('projects.tasks.timelogs.store', [$this->project, $this->task]),
                [
                    'log_date'    => '2026-09-29',
                    'hours'       => 4.5,
                    'is_billable' => 1,
                    'description' => 'Implemented TimeLog model and migrations',
                ]
            );

        $response->assertRedirect();
        $this->assertDatabaseHas('project_time_logs', [
            'tenant_id'       => $this->tenant->id,
            'project_id'      => $this->project->id,
            'task_id'         => $this->task->id,
            'user_id'         => $this->developer->id,
            'hours'           => 4.50,
            'hourly_rate'     => 75.00,
            'approval_status' => TimeLog::STATUS_PENDING,
        ]);

        $this->assertDatabaseHas('project_activity_logs', [
            'project_id' => $this->project->id,
            'event_type' => 'timelog.created',
        ]);
    }

    public function test_non_collaborator_cannot_log_time(): void
    {
        $this->actingAs($this->externalUser);

        $this->expectException(ValidationException::class);

        $service = app(TimeLogService::class);
        $service->logTime($this->project, $this->task, $this->externalUser, [
            'log_date' => '2026-09-29',
            'hours'    => 2.0,
        ]);
    }

    public function test_time_log_validates_hours_boundary(): void
    {
        $responseZero = $this->actingAs($this->developer)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->post(
                route('projects.tasks.timelogs.store', [$this->project, $this->task]),
                [
                    'log_date' => '2026-09-29',
                    'hours'    => 0,
                ]
            );
        $responseZero->assertSessionHasErrors('hours');

        $responseExcessive = $this->actingAs($this->developer)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->post(
                route('projects.tasks.timelogs.store', [$this->project, $this->task]),
                [
                    'log_date' => '2026-09-29',
                    'hours'    => 25,
                ]
            );
        $responseExcessive->assertSessionHasErrors('hours');
    }

    public function test_pending_time_log_can_be_updated_and_deleted(): void
    {
        $log = TimeLog::create([
            'tenant_id'       => $this->tenant->id,
            'project_id'      => $this->project->id,
            'task_id'         => $this->task->id,
            'user_id'         => $this->developer->id,
            'log_date'        => '2026-09-29',
            'hours'           => 3.00,
            'approval_status' => TimeLog::STATUS_PENDING,
        ]);

        $responseUpdate = $this->actingAs($this->developer)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->put(
                route('projects.tasks.timelogs.update', [$this->project, $this->task, $log]),
                [
                    'hours'       => 5.00,
                    'description' => 'Updated task description',
                ]
            );
        $responseUpdate->assertRedirect();
        $this->assertDatabaseHas('project_time_logs', [
            'id'    => $log->id,
            'hours' => 5.00,
        ]);

        $responseDelete = $this->actingAs($this->developer)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->delete(
                route('projects.tasks.timelogs.destroy', [$this->project, $this->task, $log])
            );
        $responseDelete->assertRedirect();
        $this->assertSoftDeleted('project_time_logs', ['id' => $log->id]);
    }

    public function test_approved_time_log_is_locked_against_update_and_deletion(): void
    {
        $this->actingAs($this->developer);

        $log = TimeLog::create([
            'tenant_id'       => $this->tenant->id,
            'project_id'      => $this->project->id,
            'task_id'         => $this->task->id,
            'user_id'         => $this->developer->id,
            'log_date'        => '2026-09-29',
            'hours'           => 4.00,
            'approval_status' => TimeLog::STATUS_APPROVED,
            'approved_by'     => $this->tenantOwner->id,
            'approved_at'     => now(),
        ]);

        $service = app(TimeLogService::class);

        $this->expectException(ValidationException::class);
        $service->updateTimeLog($log, ['hours' => 6.00], $this->developer);
    }

    public function test_tenant_isolation_prevents_cross_tenant_access(): void
    {
        $otherTenant = Tenant::create([
            'name'   => 'Other Tenant',
            'slug'   => 'other-tenant',
            'status' => 'active',
            'plan'   => 'enterprise',
        ]);

        $otherUser = User::create([
            'tenant_id' => $otherTenant->id,
            'name'      => 'Other User',
            'email'     => 'other@example.com',
            'password'  => bcrypt('password'),
        ]);

        $ownerRole = Role::query()->whereNull('tenant_id')->where('slug', 'tenant_owner')->firstOrFail();
        UserRole::create([
            'user_id'   => $otherUser->id,
            'role_id'   => $ownerRole->id,
            'tenant_id' => $otherTenant->id,
        ]);

        // Attempting to log time against Tenant A's task using Tenant B's credentials should fail with 404
        $response = $this->actingAs($otherUser)
            ->withHeader('X-Tenant', $otherTenant->slug)
            ->post(
                route('projects.tasks.timelogs.store', [$this->project, $this->task]),
                [
                    'log_date' => '2026-09-29',
                    'hours'    => 2.0,
                ]
            );

        $response->assertStatus(404);
    }
}
