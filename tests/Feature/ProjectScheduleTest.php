<?php

namespace Tests\Feature;

use App\Domains\Projects\Models\Milestone;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\Task;
use App\Domains\Projects\Models\TaskDependency;
use App\Domains\Projects\Models\TaskList;
use App\Domains\Projects\Services\ProjectScheduleService;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProjectScheduleTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $tenantOwner;
    private Project $project;
    private TaskList $taskList;
    private ProjectScheduleService $scheduleService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Test Scheduling Tenant',
            'slug' => 'test-sched-tenant',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->seed(RbacSeeder::class);

        $this->tenantOwner = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Schedule Owner',
            'email' => 'sched-owner@example.com',
            'password' => bcrypt('password'),
        ]);

        $role = Role::query()->whereNull('tenant_id')->where('slug', 'tenant_owner')->firstOrFail();
        UserRole::create([
            'user_id' => $this->tenantOwner->id,
            'role_id' => $role->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $this->project = Project::create([
            'tenant_id' => $this->tenant->id,
            'project_code' => 'PRJ-SCHED-01',
            'name' => 'CPM Scheduling Project',
            'owner_id' => $this->tenantOwner->id,
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
            'priority' => 'High',
            'status' => 'Active',
        ]);

        $this->taskList = TaskList::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Main Engineering',
            'sort_order' => 1,
        ]);

        $this->scheduleService = app(ProjectScheduleService::class);
    }

    private function createTask(string $title, string $startDate, string $dueDate, ?int $milestoneId = null, string $status = 'Todo'): Task
    {
        static $codeCounter = 1;

        return Task::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'task_list_id' => $this->taskList->id,
            'milestone_id' => $milestoneId,
            'task_code' => 'TSK-' . str_pad((string) $codeCounter++, 4, '0', STR_PAD_LEFT),
            'title' => $title,
            'start_date' => $startDate,
            'due_date' => $dueDate,
            'status' => $status,
            'priority' => 'Medium',
            'created_by' => $this->tenantOwner->id,
        ]);
    }

    private function createDependency(Task $task, Task $dependsOn, string $type = TaskDependency::TYPE_FINISH_TO_START, int $lagDays = 0): TaskDependency
    {
        return TaskDependency::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'task_id' => $task->id,
            'depends_on_task_id' => $dependsOn->id,
            'dependency_type' => $type,
            'lag_days' => $lagDays,
        ]);
    }

    public function test_cpm_forward_and_backward_pass_with_critical_path(): void
    {
        // Path 1 (Critical): Task A (3 days) -> Task B (4 days) = 7 days
        // Path 2 (Non-critical): Task C (2 days) -> Task B = 6 days (Float = 1)
        $taskA = $this->createTask('Task A', '2026-10-01', '2026-10-03'); // 3 days
        $taskC = $this->createTask('Task C', '2026-10-01', '2026-10-02'); // 2 days
        $taskB = $this->createTask('Task B', '2026-10-04', '2026-10-07'); // 4 days

        $this->createDependency($taskB, $taskA, TaskDependency::TYPE_FINISH_TO_START);
        $this->createDependency($taskB, $taskC, TaskDependency::TYPE_FINISH_TO_START);

        $schedule = $this->scheduleService->calculateSchedule($this->project);

        $this->assertEquals(7, $schedule['project_duration']);
        $this->assertContains($taskA->id, $schedule['critical_path']);
        $this->assertContains($taskB->id, $schedule['critical_path']);
        $this->assertNotContains($taskC->id, $schedule['critical_path']);

        $tasksById = collect($schedule['tasks'])->keyBy('id');
        $this->assertEquals(0, $tasksById[$taskA->id]['total_float']);
        $this->assertEquals(0, $tasksById[$taskB->id]['total_float']);
        $this->assertEquals(1, $tasksById[$taskC->id]['total_float']);
    }

    public function test_fs_dependency_with_lag_days(): void
    {
        // Task A (2 days: 10-01 to 10-02) -> FS + 2 lag -> Task B (2 days)
        // Earliest Start for B = 10-02 + 1 + 2 = 10-05
        $taskA = $this->createTask('Task A', '2026-10-01', '2026-10-02');
        $taskB = $this->createTask('Task B', '2026-10-05', '2026-10-06');

        $this->createDependency($taskB, $taskA, TaskDependency::TYPE_FINISH_TO_START, 2);

        $schedule = $this->scheduleService->calculateSchedule($this->project);
        $tasksById = collect($schedule['tasks'])->keyBy('id');

        $this->assertEquals('2026-10-05', $tasksById[$taskB->id]['early_start']);
        $this->assertEquals('2026-10-06', $tasksById[$taskB->id]['early_finish']);
    }

    public function test_ss_dependency_with_lag_days(): void
    {
        // Task A (start 10-01, due 10-05) -> SS + 2 lag -> Task B (duration 3)
        // Earliest Start for B = 10-01 + 2 = 10-03
        $taskA = $this->createTask('Task A', '2026-10-01', '2026-10-05');
        $taskB = $this->createTask('Task B', '2026-10-03', '2026-10-05');

        $this->createDependency($taskB, $taskA, TaskDependency::TYPE_START_TO_START, 2);

        $schedule = $this->scheduleService->calculateSchedule($this->project);
        $tasksById = collect($schedule['tasks'])->keyBy('id');

        $this->assertEquals('2026-10-03', $tasksById[$taskB->id]['early_start']);
    }

    public function test_ff_dependency_with_lag_days(): void
    {
        // Task A (start 10-01, due 10-04) -> FF + 1 lag -> Task B (duration 2)
        // Earliest Finish for B = 10-04 + 1 = 10-05.
        // ES for B = 10-05 - 2 + 1 = 10-04.
        $taskA = $this->createTask('Task A', '2026-10-01', '2026-10-04');
        $taskB = $this->createTask('Task B', '2026-10-04', '2026-10-05');

        $this->createDependency($taskB, $taskA, TaskDependency::TYPE_FINISH_TO_FINISH, 1);

        $schedule = $this->scheduleService->calculateSchedule($this->project);
        $tasksById = collect($schedule['tasks'])->keyBy('id');

        $this->assertEquals('2026-10-05', $tasksById[$taskB->id]['early_finish']);
    }

    public function test_sf_dependency_with_lag_days(): void
    {
        // Task A (start 10-03, due 10-06) -> SF + 1 lag -> Task B (duration 2)
        // Earliest Finish for B = 10-03 + 1 = 10-04.
        // ES for B = 10-04 - 2 + 1 = 10-03.
        $taskA = $this->createTask('Task A', '2026-10-03', '2026-10-06');
        $taskB = $this->createTask('Task B', '2026-10-03', '2026-10-04');

        $this->createDependency($taskB, $taskA, TaskDependency::TYPE_START_TO_FINISH, 1);

        $schedule = $this->scheduleService->calculateSchedule($this->project);
        $tasksById = collect($schedule['tasks'])->keyBy('id');

        $this->assertEquals('2026-10-04', $tasksById[$taskB->id]['early_finish']);
    }

    public function test_isolated_rescheduling_rejects_boundary_violation(): void
    {
        $taskA = $this->createTask('Task A', '2026-10-01', '2026-10-05');
        $taskB = $this->createTask('Task B', '2026-10-06', '2026-10-08');

        $this->createDependency($taskB, $taskA, TaskDependency::TYPE_FINISH_TO_START);

        // Attempt to move Task B earlier than Task A's finish date (e.g. 2026-10-03)
        $this->expectException(ValidationException::class);

        $this->scheduleService->rescheduleTask($taskB, '2026-10-03', '2026-10-05', 'isolated');
    }

    public function test_isolated_rescheduling_succeeds_when_within_boundaries(): void
    {
        $taskA = $this->createTask('Task A', '2026-10-01', '2026-10-05');
        $taskB = $this->createTask('Task B', '2026-10-06', '2026-10-08');

        $this->createDependency($taskB, $taskA, TaskDependency::TYPE_FINISH_TO_START);

        // Move Task B further out (e.g. 2026-10-10 to 2026-10-12)
        $result = $this->scheduleService->rescheduleTask($taskB, '2026-10-10', '2026-10-12', 'isolated');

        $this->assertEquals(1, $result['affected_count']);
        $taskB->refresh();
        $this->assertEquals('2026-10-10', $taskB->start_date->format('Y-m-d'));
        $this->assertEquals('2026-10-12', $taskB->due_date->format('Y-m-d'));
    }

    public function test_ripple_rescheduling_shifts_downstream_successors(): void
    {
        $taskA = $this->createTask('Task A', '2026-10-01', '2026-10-03');
        $taskB = $this->createTask('Task B', '2026-10-04', '2026-10-06');
        $taskC = $this->createTask('Task C', '2026-10-07', '2026-10-09');

        $this->createDependency($taskB, $taskA, TaskDependency::TYPE_FINISH_TO_START);
        $this->createDependency($taskC, $taskB, TaskDependency::TYPE_FINISH_TO_START);

        // Delay Task A by 3 days: 2026-10-01..03 -> 2026-10-04..06
        $result = $this->scheduleService->rescheduleTask($taskA, '2026-10-04', '2026-10-06', 'ripple');

        $this->assertEquals(3, $result['affected_count']);

        $taskA->refresh();
        $taskB->refresh();
        $taskC->refresh();

        $this->assertEquals('2026-10-04', $taskA->start_date->format('Y-m-d'));
        $this->assertEquals('2026-10-06', $taskA->due_date->format('Y-m-d'));

        // Task B shifted to start on or after 2026-10-07
        $this->assertEquals('2026-10-07', $taskB->start_date->format('Y-m-d'));
        $this->assertEquals('2026-10-09', $taskB->due_date->format('Y-m-d'));

        // Task C shifted to start on or after 2026-10-10
        $this->assertEquals('2026-10-10', $taskC->start_date->format('Y-m-d'));
        $this->assertEquals('2026-10-12', $taskC->due_date->format('Y-m-d'));
    }

    public function test_ripple_rescheduling_safeguards_completed_tasks(): void
    {
        $taskA = $this->createTask('Task A', '2026-10-01', '2026-10-03');
        $taskB = $this->createTask('Task B', '2026-10-04', '2026-10-06', null, 'Completed');

        $this->createDependency($taskB, $taskA, TaskDependency::TYPE_FINISH_TO_START);

        // Delay Task A: Task B is completed so ripple cannot automatically reschedule it and must throw ValidationException
        $this->expectException(ValidationException::class);
        $this->scheduleService->rescheduleTask($taskA, '2026-10-05', '2026-10-07', 'ripple');

        $taskB->refresh();
        $this->assertEquals('2026-10-04', $taskB->start_date->format('Y-m-d'));
        $this->assertEquals('2026-10-06', $taskB->due_date->format('Y-m-d'));
    }

    public function test_milestone_scoping_schedule(): void
    {
        $milestone = Milestone::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Sprint 1',
            'start_date' => '2026-10-01',
            'due_date' => '2026-10-15',
            'status' => 'In Progress',
            'milestone_order' => 1,
            'created_by' => $this->tenantOwner->id,
        ]);

        $task1 = $this->createTask('M1 Task', '2026-10-01', '2026-10-03', $milestone->id);
        $task2 = $this->createTask('General Task', '2026-10-05', '2026-10-07');

        $scopedSchedule = $this->scheduleService->calculateSchedule($this->project, $milestone->id);

        $this->assertCount(1, $scopedSchedule['tasks']);
        $this->assertEquals($task1->id, $scopedSchedule['tasks'][0]['id']);
    }

    public function test_http_timeline_index_and_data_endpoints(): void
    {
        $task = $this->createTask('Task 1', '2026-10-01', '2026-10-03');

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-sched-tenant')
            ->get(route('projects.timeline.index', $this->project));

        $response->assertOk();
        $response->assertViewIs('modules.projects.timeline');
        $response->assertSee('Gantt Chart');

        $jsonResponse = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-sched-tenant')
            ->getJson(route('projects.timeline.data', $this->project));

        $jsonResponse->assertOk();
        $jsonResponse->assertJsonStructure([
            'tasks',
            'links',
            'critical_path',
            'project_duration',
            'min_start',
            'max_due',
        ]);
    }

    public function test_http_reschedule_endpoint(): void
    {
        $task = $this->createTask('Task 1', '2026-10-01', '2026-10-03');

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-sched-tenant')
            ->postJson(route('projects.timeline.reschedule', [$this->project, $task]), [
                'start_date' => '2026-10-05',
                'due_date' => '2026-10-07',
                'shift_mode' => 'isolated',
            ]);

        $response->assertOk();
        $response->assertJson([
            'message' => 'Task rescheduled successfully.',
        ]);

        $task->refresh();
        $this->assertEquals('2026-10-05', $task->start_date->format('Y-m-d'));
        $this->assertEquals('2026-10-07', $task->due_date->format('Y-m-d'));
    }

    public function test_multi_tenant_isolation_on_schedule(): void
    {
        $otherTenant = Tenant::create([
            'name' => 'Other Tenant',
            'slug' => 'other-tenant',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $otherUser = User::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Other User',
            'email' => 'other-user@example.com',
            'password' => bcrypt('password'),
        ]);

        $role = Role::query()->whereNull('tenant_id')->where('slug', 'tenant_owner')->firstOrFail();
        UserRole::create([
            'user_id' => $otherUser->id,
            'role_id' => $role->id,
            'tenant_id' => $otherTenant->id,
        ]);

        // Attempt to access timeline of another tenant's project
        $response = $this->actingAs($otherUser)
            ->withHeader('X-Tenant', 'other-tenant')
            ->getJson(route('projects.timeline.data', $this->project));

        $this->assertTrue(in_array($response->status(), [403, 404], true));
    }
}
