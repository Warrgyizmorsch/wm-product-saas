<?php

namespace Tests\Feature;

use App\Domains\Projects\Models\Milestone;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\TaskList;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskListTest extends TestCase
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

    /** @test */
    public function creating_a_task_list_persists_it_with_the_next_position(): void
    {
        $project = $this->createProject();

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('projects.tasklists.store', $project), [
                'name' => 'Backlog',
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('project_task_lists', [
            'project_id' => $project->id,
            'name' => 'Backlog',
            'position' => 1,
        ]);
    }

    /** @test */
    public function assigning_a_milestone_from_a_different_project_is_rejected(): void
    {
        $projectA = $this->createProject('PRJ-0001', 'Project A');
        $projectB = $this->createProject('PRJ-0002', 'Project B');

        $milestoneOnB = Milestone::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $projectB->id,
            'name' => 'Milestone on Project B',
            'status' => 'Draft',
        ]);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('projects.tasklists.store', $projectA), [
                'name' => 'Backlog',
                'milestone_id' => $milestoneOnB->id,
            ]);

        $response->assertSessionHasErrors('milestone_id');
        $this->assertDatabaseMissing('project_task_lists', [
            'project_id' => $projectA->id,
            'name' => 'Backlog',
        ]);
    }

    /** @test */
    public function reordering_persists_the_swapped_positions(): void
    {
        $project = $this->createProject();

        $first = TaskList::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
            'name' => 'First',
            'position' => 1,
        ]);

        $second = TaskList::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $project->id,
            'name' => 'Second',
            'position' => 2,
        ]);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->patch(route('projects.tasklists.move-down', [$project, $first]));

        $response->assertRedirect();

        $this->assertSame(2, $first->fresh()->position);
        $this->assertSame(1, $second->fresh()->position);
    }
}
