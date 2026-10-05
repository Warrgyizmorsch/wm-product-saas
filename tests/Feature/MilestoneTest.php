<?php

namespace Tests\Feature;

use App\Domains\Projects\Models\Milestone;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\Task;
use App\Domains\Projects\Models\TaskList;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MilestoneTest extends TestCase
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

    /** @test */
    public function creating_milestone_validates_status_correctly(): void
    {
        $project = Project::create([
            'tenant_id' => $this->tenant->id,
            'project_code' => 'PRJ-0001',
            'name' => 'ERP Development',
            'owner_id' => $this->tenantOwner->id,
            'start_date' => now(),
            'priority' => 'High',
            'status' => 'Active',
        ]);

        // 1. Invalid status should fail validation
        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('projects.milestones.store', $project), [
                'name' => 'Design Phase',
                'status' => 'InvalidStatus',
                'completion_percentage' => 0,
            ]);

        $response->assertSessionHasErrors('status');

        // 2. Valid status should succeed
        $response2 = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('projects.milestones.store', $project), [
                'name' => 'Design Phase',
                'status' => 'Active',
                'completion_percentage' => 10,
            ]);

        $response2->assertRedirect();
        $this->assertDatabaseHas('project_milestones', [
            'project_id' => $project->id,
            'name' => 'Design Phase',
            'status' => 'Active',
        ]);
    }

    /** @test */
    public function milestone_index_can_be_filtered_by_project(): void
    {
        $projectA = Project::create([
            'tenant_id' => $this->tenant->id,
            'project_code' => 'PRJ-0001',
            'name' => 'Project A',
            'owner_id' => $this->tenantOwner->id,
            'start_date' => now(),
            'priority' => 'High',
            'status' => 'Active',
        ]);

        $projectB = Project::create([
            'tenant_id' => $this->tenant->id,
            'project_code' => 'PRJ-0002',
            'name' => 'Project B',
            'owner_id' => $this->tenantOwner->id,
            'start_date' => now(),
            'priority' => 'High',
            'status' => 'Active',
        ]);

        $milestoneA = Milestone::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $projectA->id,
            'name' => 'Milestone on Project A',
            'status' => 'Draft',
        ]);

        $milestoneB = Milestone::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $projectB->id,
            'name' => 'Milestone on Project B',
            'status' => 'Draft',
        ]);

        // 1. Without filters, both should be visible
        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('projects.milestones.index'));

        $response->assertOk();
        $response->assertSee('Milestone on Project A');
        $response->assertSee('Milestone on Project B');

        // 2. Filtered by Project A
        $responseA = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('projects.milestones.index', ['project_id' => $projectA->id]));

        $responseA->assertOk();
        $responseA->assertSee('Milestone on Project A');
        $responseA->assertDontSee('Milestone on Project B');

        // 3. Filtered by Project B
        $responseB = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('projects.milestones.index', ['project_id' => $projectB->id]));

        $responseB->assertOk();
        $responseB->assertDontSee('Milestone on Project A');
        $responseB->assertSee('Milestone on Project B');
    }

    /** @test */
    public function creating_milestone_via_ajax_returns_rendered_row_html_without_loop_variable_error(): void
    {
        $project = Project::create([
            'tenant_id' => $this->tenant->id,
            'project_code' => 'PRJ-0002',
            'name' => 'Project AJAX',
            'owner_id' => $this->tenantOwner->id,
            'start_date' => now(),
            'priority' => 'Medium',
            'status' => 'Active',
        ]);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->postJson(route('projects.milestones.store', $project), [
                'name' => 'Sprint 1 Delivery',
                'status' => 'Active',
                'completion_percentage' => 0,
            ]);

        $response->assertOk();
        $response->assertJsonStructure(['html', 'id']);
        $this->assertStringContainsString('Sprint 1 Delivery', $response->json('html'));
        $this->assertStringNotContainsString('Undefined variable', $response->json('html'));
    }

    /** @test */
    public function deleting_milestone_with_unlink_action_unlinks_tasks_and_preserves_them(): void
    {
        $project = Project::create([
            'tenant_id' => $this->tenant->id,
            'project_code' => 'PRJ-0003',
            'name' => 'Project with Milestone Tasks',
            'owner_id' => $this->tenantOwner->id,
            'start_date' => now(),
            'priority' => 'High',
            'status' => 'Active',
        ]);

        $taskList = TaskList::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
            'name' => 'Phase 1',
        ]);

        $milestone = Milestone::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
            'name' => 'Second Milestone',
            'status' => 'Active',
        ]);

        $task1 = Task::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
            'task_list_id' => $taskList->id,
            'milestone_id' => $milestone->id,
            'task_code' => 'TSK-M01',
            'title' => 'First Task Under Milestone',
            'status' => 'Open',
        ]);

        $task2 = Task::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
            'task_list_id' => $taskList->id,
            'milestone_id' => $milestone->id,
            'task_code' => 'TSK-M02',
            'title' => 'Second Task Under Milestone',
            'status' => 'In Progress',
        ]);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->delete(route('projects.milestones.destroy', [$project, $milestone]), [
                'task_action' => 'unlink',
            ]);

        $response->assertRedirect();
        $this->assertSoftDeleted('project_milestones', ['id' => $milestone->id]);

        // Tasks must still exist and be unlinked (milestone_id = null)
        $this->assertDatabaseHas('project_tasks', [
            'id' => $task1->id,
            'milestone_id' => null,
            'deleted_at' => null,
        ]);
        $this->assertDatabaseHas('project_tasks', [
            'id' => $task2->id,
            'milestone_id' => null,
            'deleted_at' => null,
        ]);
    }

    /** @test */
    public function deleting_milestone_with_delete_action_deletes_both_milestone_and_its_tasks(): void
    {
        $project = Project::create([
            'tenant_id' => $this->tenant->id,
            'project_code' => 'PRJ-0004',
            'name' => 'Project with Cascaded Milestone',
            'owner_id' => $this->tenantOwner->id,
            'start_date' => now(),
            'priority' => 'High',
            'status' => 'Active',
        ]);

        $taskList = TaskList::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
            'name' => 'Phase 1',
        ]);

        $milestone = Milestone::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
            'name' => 'Milestone to Cascade Delete',
            'status' => 'Active',
        ]);

        $task1 = Task::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
            'task_list_id' => $taskList->id,
            'milestone_id' => $milestone->id,
            'task_code' => 'TSK-M03',
            'title' => 'Task to be deleted',
            'status' => 'Open',
        ]);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->delete(route('projects.milestones.destroy', [$project, $milestone]), [
                'task_action' => 'delete',
            ]);

        $response->assertRedirect();
        $this->assertSoftDeleted('project_milestones', ['id' => $milestone->id]);
        $this->assertSoftDeleted('project_tasks', ['id' => $task1->id]);
    }

    /** @test */
    public function global_tasks_index_page_is_accessible_and_lists_tasks(): void
    {
        $project = Project::create([
            'tenant_id' => $this->tenant->id,
            'project_code' => 'PRJ-0005',
            'name' => 'Project for Global Tasks List',
            'owner_id' => $this->tenantOwner->id,
            'start_date' => now(),
            'priority' => 'High',
            'status' => 'Active',
        ]);

        $taskList = TaskList::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
            'name' => 'Phase 1',
        ]);

        $task = Task::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
            'task_list_id' => $taskList->id,
            'task_code' => 'TSK-GLOBAL-01',
            'title' => 'Global Listing Visible Task',
            'status' => 'In Progress',
        ]);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('projects.tasks.index'));

        $response->assertOk();
        $response->assertSee('TSK-GLOBAL-01');
        $response->assertSee('Global Listing Visible Task');
    }

    /** @test */
    public function milestone_index_links_to_milestone_detail_workspace_page(): void
    {
        $project = Project::create([
            'tenant_id' => $this->tenant->id,
            'project_code' => 'PRJ-0006',
            'name' => 'Project with Clickable Milestone',
            'owner_id' => $this->tenantOwner->id,
            'start_date' => now(),
            'priority' => 'High',
            'status' => 'Active',
        ]);

        $milestone = Milestone::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
            'name' => 'Architecture Phase Delivery',
            'status' => 'Active',
        ]);

        $indexResponse = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('projects.milestones.index'));

        $indexResponse->assertOk();
        $indexResponse->assertSee('Architecture Phase Delivery');
        $detailUrl = route('projects.milestones.show', [$project, $milestone]);
        $indexResponse->assertSee($detailUrl);

        $detailResponse = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get($detailUrl);

        $detailResponse->assertOk();
        $detailResponse->assertSee('Architecture Phase Delivery');
    }
}
