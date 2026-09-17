<?php

namespace Tests\Feature;

use App\Domains\Projects\Models\Milestone;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\Task;
use App\Domains\Projects\Models\TaskList;
use App\Domains\Projects\Services\MilestoneService;
use App\Domains\Projects\Services\ProjectService;
use App\Domains\Projects\Services\TaskService;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MilestoneProgressRollupTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $tenantOwner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Milestone Test Tenant',
            'slug' => 'milestone-test-tenant',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->seed(RbacSeeder::class);

        $this->tenantOwner = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Owner',
            'email' => 'owner@example.com',
            'password' => bcrypt('password'),
        ]);

        $role = Role::query()->whereNull('tenant_id')->where('slug', 'tenant_owner')->firstOrFail();
        UserRole::create([
            'user_id' => $this->tenantOwner->id,
            'role_id' => $role->id,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    private function createProject(): Project
    {
        return Project::create([
            'tenant_id' => $this->tenant->id,
            'project_code' => 'PRJ-0001',
            'name' => 'Rollup Test Project',
            'owner_id' => $this->tenantOwner->id,
            'start_date' => now(),
            'priority' => 'High',
            'status' => 'Active',
        ]);
    }

    private function createMilestone(Project $project, string $name): Milestone
    {
        return Milestone::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
            'milestone_code' => 'MS-001',
            'name' => $name,
            'status' => Milestone::STATUS_ACTIVE,
            'progress' => 0,
        ]);
    }

    private function createTaskList(Project $project, Milestone $milestone, string $name): TaskList
    {
        return TaskList::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
            'milestone_id' => $milestone->id,
            'name' => $name,
            'position' => 1,
        ]);
    }

    /** @test */
    public function milestone_with_zero_tasks_has_zero_progress(): void
    {
        $project = $this->createProject();
        $milestone = $this->createMilestone($project, 'Phase 1');

        $progress = app(MilestoneService::class)->recalculateProgress($milestone);

        $this->assertSame(0, $progress);
        $this->assertSame(0, $milestone->fresh()->progress);
    }

    /** @test */
    public function cancelled_tasks_are_excluded_from_denominator(): void
    {
        $project = $this->createProject();
        $milestone = $this->createMilestone($project, 'Phase 1');
        $taskList = $this->createTaskList($project, $milestone, 'List 1');
        $taskService = app(TaskService::class);

        // 1 Completed task, 1 Cancelled task -> 1 eligible, 1 completed = 100%
        $t1 = $taskService->create($project, [
            'task_list_id' => $taskList->id,
            'title' => 'Task 1',
        ]);
        $taskService->updateStatus($t1, Task::STATUS_IN_PROGRESS);
        $taskService->updateStatus($t1, Task::STATUS_REVIEW);
        $taskService->updateStatus($t1, Task::STATUS_COMPLETED);

        $t2 = $taskService->create($project, [
            'task_list_id' => $taskList->id,
            'title' => 'Task 2',
        ]);
        $taskService->updateStatus($t2, Task::STATUS_CANCELLED);

        $milestone->refresh();
        $this->assertSame(100, $milestone->progress);

        // If only cancelled tasks exist -> 0 eligible -> 0%
        $taskService->delete($t1);
        $milestone->refresh();
        $this->assertSame(0, $milestone->progress);
    }

    /** @test */
    public function moving_task_between_milestones_recalculates_both(): void
    {
        $project = $this->createProject();
        $msA = $this->createMilestone($project, 'Milestone A');
        $msB = Milestone::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
            'milestone_code' => 'MS-002',
            'name' => 'Milestone B',
            'status' => Milestone::STATUS_ACTIVE,
            'progress' => 0,
        ]);

        $listA = $this->createTaskList($project, $msA, 'List A');
        $listB = TaskList::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
            'milestone_id' => $msB->id,
            'name' => 'List B',
            'position' => 1,
        ]);

        $taskService = app(TaskService::class);

        // Task 1 in list A: completed
        $task1 = $taskService->create($project, [
            'task_list_id' => $listA->id,
            'title' => 'Completed Task in A',
        ]);
        $taskService->updateStatus($task1, Task::STATUS_IN_PROGRESS);
        $taskService->updateStatus($task1, Task::STATUS_REVIEW);
        $taskService->updateStatus($task1, Task::STATUS_COMPLETED);

        // Task 2 in list A: open
        $task2 = $taskService->create($project, [
            'task_list_id' => $listA->id,
            'title' => 'Open Task in A',
        ]);

        // MS A has 1 completed, 1 open -> 50%
        $this->assertSame(50, $msA->fresh()->progress);
        $this->assertSame(0, $msB->fresh()->progress);

        // Move task 2 (open) to list B
        $taskService->update($task2, ['task_list_id' => $listB->id]);

        // Now MS A has 1 completed, 0 open -> 100%
        $this->assertSame(100, $msA->fresh()->progress);
        // MS B has 0 completed, 1 open -> 0%
        $this->assertSame(0, $msB->fresh()->progress);

        // Now move task 1 (completed) to list B as well
        $taskService->update($task1, ['task_list_id' => $listB->id]);

        // MS A now has 0 tasks -> 0%
        $this->assertSame(0, $msA->fresh()->progress);
        // MS B now has 1 completed, 1 open -> 50%
        $this->assertSame(50, $msB->fresh()->progress);
    }

    /** @test */
    public function deleting_task_recalculates_milestone_progress(): void
    {
        $project = $this->createProject();
        $milestone = $this->createMilestone($project, 'Phase 1');
        $taskList = $this->createTaskList($project, $milestone, 'List 1');
        $taskService = app(TaskService::class);

        $t1 = $taskService->create($project, ['task_list_id' => $taskList->id, 'title' => 'T1']);
        $taskService->updateStatus($t1, Task::STATUS_IN_PROGRESS);
        $taskService->updateStatus($t1, Task::STATUS_REVIEW);
        $taskService->updateStatus($t1, Task::STATUS_COMPLETED);

        $t2 = $taskService->create($project, ['task_list_id' => $taskList->id, 'title' => 'T2']);

        $this->assertSame(50, $milestone->fresh()->progress);

        // Delete open task -> remaining task is 1 completed -> 100%
        $taskService->delete($t2);
        $this->assertSame(100, $milestone->fresh()->progress);
    }

    /** @test */
    public function project_dashboard_stats_consistently_exclude_cancelled_tasks_from_progress(): void
    {
        $project = $this->createProject();
        $milestone = $this->createMilestone($project, 'Phase 1');
        $taskList = $this->createTaskList($project, $milestone, 'List 1');
        $taskService = app(TaskService::class);

        $t1 = $taskService->create($project, ['task_list_id' => $taskList->id, 'title' => 'T1']);
        $taskService->updateStatus($t1, Task::STATUS_IN_PROGRESS);
        $taskService->updateStatus($t1, Task::STATUS_REVIEW);
        $taskService->updateStatus($t1, Task::STATUS_COMPLETED);

        $t2 = $taskService->create($project, ['task_list_id' => $taskList->id, 'title' => 'T2']);
        $taskService->updateStatus($t2, Task::STATUS_CANCELLED);

        $projectService = app(ProjectService::class);
        $allTasks = Task::query()->where('project_id', $project->id)->get();
        $tasksByList = $allTasks->groupBy('task_list_id');
        $milestones = Milestone::query()->where('project_id', $project->id)->get();
        $taskLists = TaskList::query()->where('project_id', $project->id)->get();
        $members = new \Illuminate\Database\Eloquent\Collection();

        $stats = $projectService->dashboardStats(
            $project,
            $taskLists,
            $tasksByList,
            $allTasks,
            $milestones,
            $members,
        );

        $this->assertSame(2, $stats['tasks']['total']);
        $this->assertSame(1, $stats['tasks']['eligible']);
        $this->assertSame(1, $stats['tasks']['done']);
        $this->assertSame(100, $stats['tasks']['percent']);
    }
}
