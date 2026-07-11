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

class SubTaskTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $tenantOwner;

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
            'name' => 'ERP Development',
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
            'name' => 'Backlog',
            'position' => 1,
        ]);

        return Task::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
            'task_list_id' => $taskList->id,
            'task_code' => 'PRJ-0001-T-001',
            'title' => 'Build login screen',
            'status' => Task::STATUS_OPEN,
        ]);
    }

    /** @test */
    public function creating_a_subtask_persists_it_with_the_next_position(): void
    {
        $project = $this->createProject();
        $task = $this->createTask($project);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('projects.tasks.subtasks.store', [$project, $task]), [
                'title' => 'Write unit tests',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('project_sub_tasks', [
            'task_id' => $task->id,
            'title' => 'Write unit tests',
            'is_completed' => false,
            'position' => 1,
        ]);
    }

    /** @test */
    public function assigning_a_non_member_to_a_subtask_is_rejected(): void
    {
        $project = $this->createProject();
        $task = $this->createTask($project);
        $outsider = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Outsider',
            'email' => 'outsider@example.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('projects.tasks.subtasks.store', [$project, $task]), [
                'title' => 'Needs an assignee',
                'assignee_id' => $outsider->id,
            ]);

        $response->assertSessionHasErrors('assignee_id');
        $this->assertDatabaseMissing('project_sub_tasks', ['title' => 'Needs an assignee']);
    }

    /** @test */
    public function toggling_completion_sets_completed_at(): void
    {
        $project = $this->createProject();
        $task = $this->createTask($project);
        $subTask = SubTask::create([
            'tenant_id' => $this->tenant->id,
            'task_id' => $task->id,
            'title' => 'Write unit tests',
            'is_completed' => false,
            'position' => 1,
        ]);

        $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->patch(route('projects.tasks.subtasks.toggle-complete', [$project, $task, $subTask]))
            ->assertRedirect();

        $subTask->refresh();
        $this->assertTrue($subTask->is_completed);
        $this->assertNotNull($subTask->completed_at);

        $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->patch(route('projects.tasks.subtasks.toggle-complete', [$project, $task, $subTask]))
            ->assertRedirect();

        $subTask->refresh();
        $this->assertFalse($subTask->is_completed);
        $this->assertNull($subTask->completed_at);
    }

    /** @test */
    public function renaming_a_subtask_updates_its_title(): void
    {
        $project = $this->createProject();
        $task = $this->createTask($project);
        $subTask = SubTask::create([
            'tenant_id' => $this->tenant->id,
            'task_id' => $task->id,
            'title' => 'Old title',
            'position' => 1,
        ]);

        $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->put(route('projects.tasks.subtasks.update', [$project, $task, $subTask]), [
                'title' => 'New title',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('project_sub_tasks', [
            'id' => $subTask->id,
            'title' => 'New title',
        ]);
    }

    /** @test */
    public function deleting_a_subtask_soft_deletes_it(): void
    {
        $project = $this->createProject();
        $task = $this->createTask($project);
        $subTask = SubTask::create([
            'tenant_id' => $this->tenant->id,
            'task_id' => $task->id,
            'title' => 'Removable',
            'position' => 1,
        ]);

        $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->delete(route('projects.tasks.subtasks.destroy', [$project, $task, $subTask]))
            ->assertRedirect();

        $this->assertSoftDeleted('project_sub_tasks', ['id' => $subTask->id]);
    }

    /** @test */
    public function completion_percentage_is_computed_not_stored(): void
    {
        $project = $this->createProject();
        $task = $this->createTask($project);
        SubTask::create(['tenant_id' => $this->tenant->id, 'task_id' => $task->id, 'title' => 'One', 'is_completed' => true, 'position' => 1]);
        SubTask::create(['tenant_id' => $this->tenant->id, 'task_id' => $task->id, 'title' => 'Two', 'is_completed' => false, 'position' => 2]);

        $percentage = app(SubTaskService::class)->completionPercentage($task);

        $this->assertSame(50, $percentage);
        $this->assertArrayNotHasKey('completion_percentage', $task->getAttributes());
    }

    /** @test */
    public function completion_percentage_is_zero_when_there_are_no_subtasks(): void
    {
        $project = $this->createProject();
        $task = $this->createTask($project);

        $percentage = app(SubTaskService::class)->completionPercentage($task);

        $this->assertSame(0, $percentage);
    }
}
