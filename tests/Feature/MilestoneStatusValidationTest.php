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

class MilestoneStatusValidationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $tenantOwner;
    private Project $project;

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

        $this->tenantOwner = $this->createUserWithRole('owner@example.com', 'tenant_owner', $this->tenant);

        $this->project = Project::create([
            'tenant_id' => $this->tenant->id,
            'project_code' => 'PRJ-0001',
            'name' => 'ERP Development',
            'owner_id' => $this->tenantOwner->id,
            'start_date' => now(),
            'priority' => 'High',
            'status' => 'Active',
        ]);
    }

    private function createUserWithRole(string $email, string $roleSlug, Tenant $tenant): User
    {
        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => $email,
            'email' => $email,
            'password' => bcrypt('password'),
        ]);

        $role = Role::query()->whereNull('tenant_id')->where('slug', $roleSlug)->firstOrFail();

        UserRole::create([
            'user_id' => $user->id,
            'role_id' => $role->id,
            'tenant_id' => $tenant->id,
        ]);

        return $user;
    }

    private function validMilestonePayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Kickoff Milestone',
            'status' => 'Active',
            'completion_percentage' => 10,
        ], $overrides);
    }

    /** @test */
    public function milestone_can_be_created_with_a_valid_status(): void
    {
        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('projects.milestones.store', $this->project), $this->validMilestonePayload(['status' => 'Completed']));

        $response->assertSessionDoesntHaveErrors('status');

        $this->assertDatabaseHas('project_milestones', [
            'project_id' => $this->project->id,
            'name' => 'Kickoff Milestone',
            'status' => 'Completed',
        ]);
    }

    /** @test */
    public function milestone_cannot_be_created_with_an_invalid_status(): void
    {
        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('projects.milestones.store', $this->project), $this->validMilestonePayload(['status' => 'Bogus Status']));

        $response->assertSessionHasErrors('status');

        $this->assertDatabaseMissing('project_milestones', [
            'project_id' => $this->project->id,
            'name' => 'Kickoff Milestone',
        ]);
    }

    /** @test */
    public function milestone_can_be_updated_with_a_valid_status(): void
    {
        $milestone = Milestone::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Kickoff Milestone',
            'status' => 'Draft',
            'completion_percentage' => 0,
        ]);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->put(route('projects.milestones.update', [$this->project, $milestone]), $this->validMilestonePayload(['status' => 'On Hold']));

        $response->assertSessionDoesntHaveErrors('status');
        $this->assertSame('On Hold', $milestone->fresh()->status);
    }

    /** @test */
    public function milestone_cannot_be_updated_with_an_invalid_status(): void
    {
        $milestone = Milestone::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Kickoff Milestone',
            'status' => 'Draft',
            'completion_percentage' => 0,
        ]);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->put(route('projects.milestones.update', [$this->project, $milestone]), $this->validMilestonePayload(['status' => 'Not A Real Status']));

        $response->assertSessionHasErrors('status');
        $this->assertSame('Draft', $milestone->fresh()->status);
    }
}
