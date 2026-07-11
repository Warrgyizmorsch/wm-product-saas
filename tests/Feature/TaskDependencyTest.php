<?php

namespace Tests\Feature;

use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\Task;
use App\Domains\Projects\Models\TaskDependency;
use App\Domains\Projects\Models\TaskList;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskDependencyTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $tenantOwner;
    private Project $project;
    private TaskList $taskList;

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

        $this->project = Project::create([
            'tenant_id' => $this->tenant->id,
            'project_code' => 'PRJ-0001',
            'name' => 'ERP Development',
            'owner_id' => $this->tenantOwner->id,
            'start_date' => now(),
            'priority' => 'High',
            'status' => 'Active',
        ]);

        $this->taskList = TaskList::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Backlog',
            'position' => 1,
        ]);
    }

    private function createTask(string $code, string $title): Task
    {
        return Task::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'task_list_id' => $this->taskList->id,
            'task_code' => $code,
            'title' => $title,
            'status' => Task::STATUS_OPEN,
        ]);
    }

    /** @test */
    public function a_dependency_edge_can_be_created_between_two_tasks_in_the_same_project(): void
    {
        $taskA = $this->createTask('PRJ-0001-T-001', 'Task A');
        $taskB = $this->createTask('PRJ-0001-T-002', 'Task B');

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('projects.tasks.dependencies.store', [$this->project, $taskA]), [
                'depends_on_task_id' => $taskB->id,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('project_task_dependencies', [
            'task_id' => $taskA->id,
            'depends_on_task_id' => $taskB->id,
            'project_id' => $this->project->id,
        ]);
    }

    /** @test */
    public function a_task_cannot_depend_on_itself(): void
    {
        $taskA = $this->createTask('PRJ-0001-T-001', 'Task A');

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('projects.tasks.dependencies.store', [$this->project, $taskA]), [
                'depends_on_task_id' => $taskA->id,
            ]);

        $response->assertSessionHasErrors('depends_on_task_id');
        $this->assertDatabaseMissing('project_task_dependencies', ['task_id' => $taskA->id]);
    }

    /** @test */
    public function a_duplicate_dependency_edge_is_rejected(): void
    {
        $taskA = $this->createTask('PRJ-0001-T-001', 'Task A');
        $taskB = $this->createTask('PRJ-0001-T-002', 'Task B');

        TaskDependency::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'task_id' => $taskA->id,
            'depends_on_task_id' => $taskB->id,
        ]);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('projects.tasks.dependencies.store', [$this->project, $taskA]), [
                'depends_on_task_id' => $taskB->id,
            ]);

        $response->assertSessionHasErrors('depends_on_task_id');
        $this->assertSame(1, TaskDependency::where('task_id', $taskA->id)->count());
    }

    /** @test */
    public function a_dependency_on_a_task_from_another_project_is_rejected(): void
    {
        $taskA = $this->createTask('PRJ-0001-T-001', 'Task A');

        $otherProject = Project::create([
            'tenant_id' => $this->tenant->id,
            'project_code' => 'PRJ-0002',
            'name' => 'Other Project',
            'owner_id' => $this->tenantOwner->id,
            'start_date' => now(),
            'priority' => 'High',
            'status' => 'Active',
        ]);
        $otherTaskList = TaskList::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $otherProject->id,
            'name' => 'Backlog',
            'position' => 1,
        ]);
        $foreignTask = Task::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $otherProject->id,
            'task_list_id' => $otherTaskList->id,
            'task_code' => 'PRJ-0002-T-001',
            'title' => 'Foreign task',
            'status' => Task::STATUS_OPEN,
        ]);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('projects.tasks.dependencies.store', [$this->project, $taskA]), [
                'depends_on_task_id' => $foreignTask->id,
            ]);

        $response->assertSessionHasErrors('depends_on_task_id');
        $this->assertDatabaseMissing('project_task_dependencies', ['task_id' => $taskA->id]);
    }

    /** @test */
    public function a_three_way_cycle_is_rejected_before_touching_the_database(): void
    {
        $taskA = $this->createTask('PRJ-0001-T-001', 'Task A');
        $taskB = $this->createTask('PRJ-0001-T-002', 'Task B');
        $taskC = $this->createTask('PRJ-0001-T-003', 'Task C');

        // A -> B -> C already exist; C -> A would close the loop.
        TaskDependency::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'task_id' => $taskA->id,
            'depends_on_task_id' => $taskB->id,
        ]);
        TaskDependency::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'task_id' => $taskB->id,
            'depends_on_task_id' => $taskC->id,
        ]);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('projects.tasks.dependencies.store', [$this->project, $taskC]), [
                'depends_on_task_id' => $taskA->id,
            ]);

        $response->assertSessionHasErrors('depends_on_task_id');
        $this->assertDatabaseMissing('project_task_dependencies', [
            'task_id' => $taskC->id,
            'depends_on_task_id' => $taskA->id,
        ]);
    }

    /** @test */
    public function deleting_a_dependency_removes_the_edge(): void
    {
        $taskA = $this->createTask('PRJ-0001-T-001', 'Task A');
        $taskB = $this->createTask('PRJ-0001-T-002', 'Task B');

        $dependency = TaskDependency::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'task_id' => $taskA->id,
            'depends_on_task_id' => $taskB->id,
        ]);

        $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->delete(route('projects.tasks.dependencies.destroy', [$this->project, $taskA, $dependency]))
            ->assertRedirect();

        $this->assertDatabaseMissing('project_task_dependencies', ['id' => $dependency->id]);
    }
}
