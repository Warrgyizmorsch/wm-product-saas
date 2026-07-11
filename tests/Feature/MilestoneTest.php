<?php

namespace Tests\Feature;

use App\Domains\Projects\Models\Milestone;
use App\Domains\Projects\Models\Project;
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
}
