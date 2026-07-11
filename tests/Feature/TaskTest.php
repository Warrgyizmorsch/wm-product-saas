<?php

namespace Tests\Feature;

use App\Domains\Projects\Models\Milestone;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\ProjectMember;
use App\Domains\Projects\Models\Task;
use App\Domains\Projects\Models\TaskList;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskTest extends TestCase
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

    private function createProject(string $code = 'PRJ-0001', string $name = 'ERP Development'): Project
    {
        return Project::create([
            'tenant_id' => $this->tenant->id,
            'project_code' => $code,
            'name' => $name,
            'owner_id' => $this->tenantOwner->id,
            'start_date' => now(),
            'priority' => 'High',
            'status' => 'Active',
        ]);
    }

    private function createTaskList(Project $project, ?Milestone $milestone = null): TaskList
    {
        return TaskList::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
            'milestone_id' => $milestone?->id,
            'name' => 'Backlog',
            'position' => 1,
        ]);
    }

    private function addMember(Project $project, User $user): ProjectMember
    {
        return ProjectMember::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
            'user_id' => $user->id,
            'is_active' => true,
        ]);
    }

    /** @test */
    public function creating_a_task_persists_it_with_project_scoped_code_and_derived_milestone(): void
    {
        $project = $this->createProject();
        $milestone = Milestone::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
            'name' => 'Phase 1',
            'status' => 'Active',
        ]);
        $taskList = $this->createTaskList($project, $milestone);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('projects.tasks.store', $project), [
                'task_list_id' => $taskList->id,
                'title' => 'Build login screen',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('project_tasks', [
            'project_id' => $project->id,
            'task_list_id' => $taskList->id,
            'title' => 'Build login screen',
            'task_code' => 'PRJ-0001-T-001',
            'milestone_id' => $milestone->id,
            'position' => 1,
            'status' => 'Open',
        ]);
    }

    /** @test */
    public function milestone_id_is_not_user_editable_and_is_always_derived_from_the_task_list(): void
    {
        $project = $this->createProject();
        $milestoneA = Milestone::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
            'name' => 'Phase A',
            'status' => 'Active',
        ]);
        $milestoneB = Milestone::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
            'name' => 'Phase B',
            'status' => 'Active',
        ]);
        $taskList = $this->createTaskList($project, $milestoneA);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('projects.tasks.store', $project), [
                'task_list_id' => $taskList->id,
                'title' => 'Spoofed milestone attempt',
                'milestone_id' => $milestoneB->id,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('project_tasks', [
            'project_id' => $project->id,
            'title' => 'Spoofed milestone attempt',
            'milestone_id' => $milestoneA->id,
        ]);
        $this->assertDatabaseMissing('project_tasks', [
            'title' => 'Spoofed milestone attempt',
            'milestone_id' => $milestoneB->id,
        ]);
    }

    /** @test */
    public function assigning_a_non_member_is_rejected(): void
    {
        $project = $this->createProject();
        $taskList = $this->createTaskList($project);
        $outsider = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Outsider',
            'email' => 'outsider@example.com',
            'password' => bcrypt('password'),
        ]);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('projects.tasks.store', $project), [
                'task_list_id' => $taskList->id,
                'title' => 'Needs an assignee',
                'assignee_id' => $outsider->id,
            ]);

        $response->assertSessionHasErrors('assignee_id');
        $this->assertDatabaseMissing('project_tasks', [
            'project_id' => $project->id,
            'title' => 'Needs an assignee',
        ]);
    }

    /** @test */
    public function assigning_an_active_project_member_succeeds(): void
    {
        $project = $this->createProject();
        $taskList = $this->createTaskList($project);
        $member = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Member',
            'email' => 'member@example.com',
            'password' => bcrypt('password'),
        ]);
        $this->addMember($project, $member);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('projects.tasks.store', $project), [
                'task_list_id' => $taskList->id,
                'title' => 'Assigned to a member',
                'assignee_id' => $member->id,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('project_tasks', [
            'project_id' => $project->id,
            'title' => 'Assigned to a member',
            'assignee_id' => $member->id,
        ]);
    }

    /** @test */
    public function status_cannot_skip_directly_from_open_to_completed(): void
    {
        $project = $this->createProject();
        $taskList = $this->createTaskList($project);
        $task = Task::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
            'task_list_id' => $taskList->id,
            'task_code' => 'PRJ-0001-T-001',
            'title' => 'Skip attempt',
            'status' => Task::STATUS_OPEN,
        ]);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->patch(route('projects.tasks.update-status', [$project, $task]), [
                'status' => Task::STATUS_COMPLETED,
            ]);

        $response->assertSessionHasErrors('status');
        $this->assertSame(Task::STATUS_OPEN, $task->fresh()->status);
    }

    /** @test */
    public function status_moves_forward_through_the_allowed_workflow(): void
    {
        $project = $this->createProject();
        $taskList = $this->createTaskList($project);
        $task = Task::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
            'task_list_id' => $taskList->id,
            'task_code' => 'PRJ-0001-T-001',
            'title' => 'Happy path',
            'status' => Task::STATUS_OPEN,
        ]);

        $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->patch(route('projects.tasks.update-status', [$project, $task]), ['status' => Task::STATUS_IN_PROGRESS])
            ->assertRedirect();

        $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->patch(route('projects.tasks.update-status', [$project, $task]), ['status' => Task::STATUS_REVIEW])
            ->assertRedirect();

        $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->patch(route('projects.tasks.update-status', [$project, $task]), ['status' => Task::STATUS_COMPLETED])
            ->assertRedirect();

        $task->refresh();
        $this->assertSame(Task::STATUS_COMPLETED, $task->status);
        $this->assertNotNull($task->completed_at);
    }

    /** @test */
    public function deleting_a_task_list_cascades_to_its_tasks(): void
    {
        $project = $this->createProject();
        $taskList = $this->createTaskList($project);
        $task = Task::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
            'task_list_id' => $taskList->id,
            'task_code' => 'PRJ-0001-T-001',
            'title' => 'Orphan candidate',
            'status' => Task::STATUS_OPEN,
        ]);

        $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->delete(route('projects.tasklists.destroy', [$project, $taskList]))
            ->assertRedirect();

        $this->assertSoftDeleted('project_task_lists', ['id' => $taskList->id]);
        $this->assertSoftDeleted('project_tasks', ['id' => $task->id]);
    }

    /** @test */
    public function creating_a_second_task_in_a_task_list_gets_the_next_position(): void
    {
        $project = $this->createProject();
        $taskList = $this->createTaskList($project);

        Task::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
            'task_list_id' => $taskList->id,
            'task_code' => 'PRJ-0001-T-001',
            'title' => 'First',
            'position' => 1,
        ]);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('projects.tasks.store', $project), [
                'task_list_id' => $taskList->id,
                'title' => 'Second',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('project_tasks', [
            'project_id' => $project->id,
            'title' => 'Second',
            'position' => 2,
        ]);
    }
}
