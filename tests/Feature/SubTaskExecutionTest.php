<?php

namespace Tests\Feature;

use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\ProjectMember;
use App\Domains\Projects\Models\SubTask;
use App\Domains\Projects\Models\Task;
use App\Domains\Projects\Models\TaskList;
use App\Domains\Projects\Services\SubTaskService;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubTaskExecutionTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $tenantOwner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'SubTask Test Tenant',
            'slug' => 'subtask-test-tenant',
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
            'name' => 'SubTask Execution Project',
            'owner_id' => $this->tenantOwner->id,
            'start_date' => now(),
            'priority' => 'High',
            'status' => 'Active',
        ]);
    }

    private function createTask(Project $project): Task
    {
        $taskList = TaskList::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
            'name' => 'Sprint Backlog',
            'position' => 1,
        ]);

        return Task::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
            'task_list_id' => $taskList->id,
            'task_code' => 'PRJ-0001-T-001',
            'title' => 'Build authentication API',
            'status' => Task::STATUS_OPEN,
        ]);
    }

    /** @test */
    public function creating_subtask_persists_execution_metadata(): void
    {
        $project = $this->createProject();
        $task = $this->createTask($project);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'subtask-test-tenant')
            ->postJson(route('projects.tasks.subtasks.store', [$project, $task]), [
                'title' => 'Write JWT middleware',
                'start_date' => '2026-09-15',
                'due_date' => '2026-09-20',
                'estimated_hours' => 6.5,
                'status' => SubTask::STATUS_IN_PROGRESS,
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('project_sub_tasks', [
            'task_id' => $task->id,
            'title' => 'Write JWT middleware',
            'status' => SubTask::STATUS_IN_PROGRESS,
            'is_completed' => false,
            'estimated_hours' => 6.5,
        ]);
    }

    /** @test */
    public function updating_status_to_completed_synchronizes_is_completed_and_completed_at(): void
    {
        $project = $this->createProject();
        $task = $this->createTask($project);
        $subTaskService = app(SubTaskService::class);

        $subTask = $subTaskService->create($task, [
            'title' => 'Initial subtask',
            'status' => SubTask::STATUS_OPEN,
        ]);

        $this->assertFalse($subTask->is_completed);
        $this->assertNull($subTask->completed_at);

        // Update status to Completed
        $updated = $subTaskService->update($subTask, ['status' => SubTask::STATUS_COMPLETED]);
        $this->assertTrue($updated->is_completed);
        $this->assertNotNull($updated->completed_at);
        $this->assertSame(SubTask::STATUS_COMPLETED, $updated->status);

        // Update status back to In Progress -> clears is_completed and completed_at
        $inProgress = $subTaskService->update($subTask, ['status' => SubTask::STATUS_IN_PROGRESS]);
        $this->assertFalse($inProgress->is_completed);
        $this->assertNull($inProgress->completed_at);
        $this->assertSame(SubTask::STATUS_IN_PROGRESS, $inProgress->status);
    }

    /** @test */
    public function toggle_complete_with_explicit_boolean_sets_exact_canonical_state(): void
    {
        $project = $this->createProject();
        $task = $this->createTask($project);
        $subTaskService = app(SubTaskService::class);

        $subTask = $subTaskService->create($task, [
            'title' => 'Toggle test subtask',
            'status' => SubTask::STATUS_OPEN,
        ]);

        // Explicit toggle to true
        $toggledTrue = $subTaskService->toggleComplete($subTask, true);
        $this->assertTrue($toggledTrue->is_completed);
        $this->assertNotNull($toggledTrue->completed_at);
        $this->assertSame(SubTask::STATUS_COMPLETED, $toggledTrue->status);

        // Explicit toggle to false
        $toggledFalse = $subTaskService->toggleComplete($subTask, false);
        $this->assertFalse($toggledFalse->is_completed);
        $this->assertNull($toggledFalse->completed_at);
        $this->assertSame(SubTask::STATUS_OPEN, $toggledFalse->status);
    }

    /** @test */
    public function completing_all_subtasks_never_automatically_changes_parent_task_status(): void
    {
        $project = $this->createProject();
        $task = $this->createTask($project);
        $this->assertSame(Task::STATUS_OPEN, $task->status);

        $subTaskService = app(SubTaskService::class);

        $sub1 = $subTaskService->create($task, ['title' => 'Sub 1']);
        $sub2 = $subTaskService->create($task, ['title' => 'Sub 2']);

        $subTaskService->toggleComplete($sub1, true);
        $subTaskService->toggleComplete($sub2, true);

        // Verify subtasks are completed
        $this->assertTrue($sub1->fresh()->is_completed);
        $this->assertTrue($sub2->fresh()->is_completed);

        // Parent task status MUST remain Open
        $this->assertSame(Task::STATUS_OPEN, $task->fresh()->status);
        $this->assertNull($task->fresh()->completed_at);
    }

    /** @test */
    public function subtask_assignee_must_be_active_project_member(): void
    {
        $project = $this->createProject();
        $task = $this->createTask($project);

        $memberUser = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Project Member',
            'email' => 'member@example.com',
            'password' => bcrypt('password'),
        ]);

        ProjectMember::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
            'user_id' => $memberUser->id,
            'project_role' => 'contributor',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'subtask-test-tenant')
            ->postJson(route('projects.tasks.subtasks.store', [$project, $task]), [
                'title' => 'Subtask with member',
                'assignee_id' => $memberUser->id,
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('project_sub_tasks', [
            'task_id' => $task->id,
            'assignee_id' => $memberUser->id,
        ]);
    }
}
