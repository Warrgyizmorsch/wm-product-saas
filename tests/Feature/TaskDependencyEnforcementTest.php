<?php

namespace Tests\Feature;

use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\Task;
use App\Domains\Projects\Models\TaskDependency;
use App\Domains\Projects\Models\TaskList;
use App\Domains\Projects\Services\TaskDependencyService;
use App\Domains\Projects\Services\TaskService;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TaskDependencyEnforcementTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $tenantOwner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Dependency Test Tenant',
            'slug' => 'dep-test-tenant',
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

    private function createProject(string $code = 'PRJ-0001'): Project
    {
        return Project::create([
            'tenant_id' => $this->tenant->id,
            'project_code' => $code,
            'name' => 'Dependency Test Project',
            'owner_id' => $this->tenantOwner->id,
            'start_date' => now(),
            'priority' => 'High',
            'status' => 'Active',
        ]);
    }

    private function createTask(Project $project, string $title, string $code): Task
    {
        $taskList = TaskList::firstOrCreate(
            ['tenant_id' => $this->tenant->id, 'project_id' => $project->id, 'name' => 'General'],
            ['position' => 1]
        );

        return Task::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
            'task_list_id' => $taskList->id,
            'task_code' => $code,
            'title' => $title,
            'status' => Task::STATUS_OPEN,
        ]);
    }

    /** @test */
    public function self_dependency_is_rejected(): void
    {
        $project = $this->createProject();
        $task = $this->createTask($project, 'Task A', 'PRJ-0001-T-001');

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'dep-test-tenant')
            ->postJson(route('projects.tasks.dependencies.store', [$project, $task]), [
                'depends_on_task_id' => $task->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('depends_on_task_id');
    }

    /** @test */
    public function cross_project_dependency_is_rejected(): void
    {
        $projectA = $this->createProject('PRJ-0001');
        $projectB = $this->createProject('PRJ-0002');

        $taskA = $this->createTask($projectA, 'Task A', 'PRJ-0001-T-001');
        $taskB = $this->createTask($projectB, 'Task B', 'PRJ-0002-T-001');

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'dep-test-tenant')
            ->postJson(route('projects.tasks.dependencies.store', [$projectA, $taskA]), [
                'depends_on_task_id' => $taskB->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('depends_on_task_id');
    }

    /** @test */
    public function duplicate_dependency_is_rejected(): void
    {
        $project = $this->createProject();
        $taskA = $this->createTask($project, 'Task A', 'PRJ-0001-T-001');
        $taskB = $this->createTask($project, 'Task B', 'PRJ-0001-T-002');

        app(TaskDependencyService::class)->create($taskB, $taskA->id);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'dep-test-tenant')
            ->postJson(route('projects.tasks.dependencies.store', [$project, $taskB]), [
                'depends_on_task_id' => $taskA->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('depends_on_task_id');
    }

    /** @test */
    public function direct_circular_dependency_is_rejected(): void
    {
        $project = $this->createProject();
        $taskA = $this->createTask($project, 'Task A', 'PRJ-0001-T-001');
        $taskB = $this->createTask($project, 'Task B', 'PRJ-0001-T-002');

        // B depends on A
        app(TaskDependencyService::class)->create($taskB, $taskA->id);

        // Attempting A depends on B closes cycle A -> B -> A
        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'dep-test-tenant')
            ->postJson(route('projects.tasks.dependencies.store', [$project, $taskA]), [
                'depends_on_task_id' => $taskB->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('depends_on_task_id');
    }

    /** @test */
    public function indirect_circular_dependency_is_rejected(): void
    {
        $project = $this->createProject();
        $taskA = $this->createTask($project, 'Task A', 'PRJ-0001-T-001');
        $taskB = $this->createTask($project, 'Task B', 'PRJ-0001-T-002');
        $taskC = $this->createTask($project, 'Task C', 'PRJ-0001-T-003');

        $depService = app(TaskDependencyService::class);
        // B depends on A
        $depService->create($taskB, $taskA->id);
        // C depends on B
        $depService->create($taskC, $taskB->id);

        // Attempting A depends on C closes cycle A -> C -> B -> A
        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'dep-test-tenant')
            ->postJson(route('projects.tasks.dependencies.store', [$project, $taskA]), [
                'depends_on_task_id' => $taskC->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('depends_on_task_id');
    }

    /** @test */
    public function invalid_dependency_type_is_rejected(): void
    {
        $project = $this->createProject();
        $taskA = $this->createTask($project, 'Task A', 'PRJ-0001-T-001');
        $taskB = $this->createTask($project, 'Task B', 'PRJ-0001-T-002');

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'dep-test-tenant')
            ->postJson(route('projects.tasks.dependencies.store', [$project, $taskB]), [
                'depends_on_task_id' => $taskA->id,
                'dependency_type' => 'Arbitrary-Type',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('dependency_type');
    }

    /** @test */
    public function finish_to_start_blocks_start_until_predecessor_completed(): void
    {
        $project = $this->createProject();
        $taskA = $this->createTask($project, 'Task A', 'PRJ-0001-T-001');
        $taskB = $this->createTask($project, 'Task B', 'PRJ-0001-T-002');

        app(TaskDependencyService::class)->create($taskB, $taskA->id, TaskDependency::TYPE_FINISH_TO_START);
        $taskService = app(TaskService::class);

        // Task A is Open. Task B cannot move to In Progress.
        $this->expectException(ValidationException::class);
        $taskService->updateStatus($taskB, Task::STATUS_IN_PROGRESS);
    }

    /** @test */
    public function finish_to_start_allows_start_after_predecessor_completed(): void
    {
        $project = $this->createProject();
        $taskA = $this->createTask($project, 'Task A', 'PRJ-0001-T-001');
        $taskB = $this->createTask($project, 'Task B', 'PRJ-0001-T-002');

        app(TaskDependencyService::class)->create($taskB, $taskA->id, TaskDependency::TYPE_FINISH_TO_START);
        $taskService = app(TaskService::class);

        // Transition A to In Progress, Review, then Completed
        $taskService->updateStatus($taskA, Task::STATUS_IN_PROGRESS);
        $taskService->updateStatus($taskA, Task::STATUS_REVIEW);
        $taskService->updateStatus($taskA, Task::STATUS_COMPLETED);

        // Task B can now move to In Progress
        $updatedB = $taskService->updateStatus($taskB, Task::STATUS_IN_PROGRESS);
        $this->assertSame(Task::STATUS_IN_PROGRESS, $updatedB->status);
    }

    /** @test */
    public function start_to_start_blocks_until_predecessor_started(): void
    {
        $project = $this->createProject();
        $taskA = $this->createTask($project, 'Task A', 'PRJ-0001-T-001');
        $taskB = $this->createTask($project, 'Task B', 'PRJ-0001-T-002');

        app(TaskDependencyService::class)->create($taskB, $taskA->id, TaskDependency::TYPE_START_TO_START);
        $taskService = app(TaskService::class);

        // Task A is Open -> Task B cannot start
        try {
            $taskService->updateStatus($taskB, Task::STATUS_IN_PROGRESS);
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors());
        }

        // Task A starts -> Task B can start even though A is not finished
        $taskService->updateStatus($taskA, Task::STATUS_IN_PROGRESS);
        $updatedB = $taskService->updateStatus($taskB, Task::STATUS_IN_PROGRESS);
        $this->assertSame(Task::STATUS_IN_PROGRESS, $updatedB->status);
    }

    /** @test */
    public function finish_to_finish_allows_start_but_blocks_completion(): void
    {
        $project = $this->createProject();
        $taskA = $this->createTask($project, 'Task A', 'PRJ-0001-T-001');
        $taskB = $this->createTask($project, 'Task B', 'PRJ-0001-T-002');

        app(TaskDependencyService::class)->create($taskB, $taskA->id, TaskDependency::TYPE_FINISH_TO_FINISH);
        $taskService = app(TaskService::class);

        // Task B can start and reach Review while A is Open
        $taskB = $taskService->updateStatus($taskB, Task::STATUS_IN_PROGRESS);
        $taskB = $taskService->updateStatus($taskB, Task::STATUS_REVIEW);
        $this->assertSame(Task::STATUS_REVIEW, $taskB->fresh()->status);

        // Task B cannot complete while A is not completed
        try {
            $taskService->updateStatus($taskB, Task::STATUS_COMPLETED);
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors());
        }

        // Complete A, then B can complete
        $taskService->updateStatus($taskA, Task::STATUS_IN_PROGRESS);
        $taskService->updateStatus($taskA, Task::STATUS_REVIEW);
        $taskService->updateStatus($taskA, Task::STATUS_COMPLETED);

        $updatedB = $taskService->updateStatus($taskB, Task::STATUS_COMPLETED);
        $this->assertSame(Task::STATUS_COMPLETED, $updatedB->status);
    }

    /** @test */
    public function start_to_finish_blocks_completion_until_predecessor_starts(): void
    {
        $project = $this->createProject();
        $taskA = $this->createTask($project, 'Task A', 'PRJ-0001-T-001');
        $taskB = $this->createTask($project, 'Task B', 'PRJ-0001-T-002');

        app(TaskDependencyService::class)->create($taskB, $taskA->id, TaskDependency::TYPE_START_TO_FINISH);
        $taskService = app(TaskService::class);

        // Task B starts and reaches Review
        $taskB = $taskService->updateStatus($taskB, Task::STATUS_IN_PROGRESS);
        $taskB = $taskService->updateStatus($taskB, Task::STATUS_REVIEW);

        // Task B cannot complete while A has not started (A is Open)
        try {
            $taskService->updateStatus($taskB, Task::STATUS_COMPLETED);
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors());
        }

        // Task A starts (In Progress) -> Task B can now complete
        $taskService->updateStatus($taskA, Task::STATUS_IN_PROGRESS);
        $updatedB = $taskService->updateStatus($taskB, Task::STATUS_COMPLETED);
        $this->assertSame(Task::STATUS_COMPLETED, $updatedB->status);
    }

    /** @test */
    public function administrative_status_transitions_are_always_permitted_when_blocked(): void
    {
        $project = $this->createProject();
        $taskA = $this->createTask($project, 'Task A', 'PRJ-0001-T-001');
        $taskB = $this->createTask($project, 'Task B', 'PRJ-0001-T-002');
        $taskC = $this->createTask($project, 'Task C', 'PRJ-0001-T-003');

        $depService = app(TaskDependencyService::class);
        $depService->create($taskB, $taskA->id, TaskDependency::TYPE_FINISH_TO_START);
        $depService->create($taskC, $taskA->id, TaskDependency::TYPE_FINISH_TO_START);
        $taskService = app(TaskService::class);

        // Task B can transition from Open to On Hold even when predecessor is not completed
        $onHold = $taskService->updateStatus($taskB, Task::STATUS_ON_HOLD);
        $this->assertSame(Task::STATUS_ON_HOLD, $onHold->status);

        // Task C can transition from Open to Cancelled even when predecessor is not completed
        $cancelled = $taskService->updateStatus($taskC, Task::STATUS_CANCELLED);
        $this->assertSame(Task::STATUS_CANCELLED, $cancelled->status);
    }

    /** @test */
    public function direct_http_status_patch_enforces_dependency_blocking(): void
    {
        $project = $this->createProject();
        $taskA = $this->createTask($project, 'Task A', 'PRJ-0001-T-001');
        $taskB = $this->createTask($project, 'Task B', 'PRJ-0001-T-002');

        app(TaskDependencyService::class)->create($taskB, $taskA->id, TaskDependency::TYPE_FINISH_TO_START);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'dep-test-tenant')
            ->patchJson(route('projects.tasks.update-status', [$project, $taskB]), [
                'status' => Task::STATUS_IN_PROGRESS,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }
}
