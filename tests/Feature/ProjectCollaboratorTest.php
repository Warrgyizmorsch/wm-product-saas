<?php

namespace Tests\Feature;

use App\Domains\Projects\Models\Project;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectCollaboratorTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Tenant $otherTenant;
    private User $tenantOwner;
    private User $readOnlyUser;
    private User $otherTenantUser;
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

        $this->otherTenant = Tenant::create([
            'name' => 'Other Tenant',
            'slug' => 'other-tenant',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->seed(RbacSeeder::class);

        $this->tenantOwner = $this->createUserWithRole('owner@example.com', 'tenant_owner', $this->tenant);
        $this->readOnlyUser = $this->createUserWithRole('readonly@example.com', 'read_only', $this->tenant);
        $this->otherTenantUser = $this->createUserWithRole('owner2@example.com', 'tenant_owner', $this->otherTenant);

        $this->project = Project::create([
            'tenant_id' => $this->tenant->id,
            'project_code' => 'PRJ-0001',
            'name' => 'ERP Development',
            'owner_id' => $this->tenantOwner->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonths(6)->toDateString(),
            'priority' => 'High',
            'status' => 'Draft',
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

    /** @test */
    public function search_returns_tenant_users_excluding_existing_collaborators(): void
    {
        $candidate = $this->createUserWithRole('candidate@example.com', 'tenant_owner', $this->tenant);

        $this->project->members()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->tenantOwner->id,
        ]);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->getJson(route('projects.collaborators.search', $this->project) . '?q=cand');

        $response->assertOk();
        $response->assertJsonFragment(['id' => $candidate->id, 'text' => $candidate->name]);
        $response->assertJsonMissing(['id' => $this->tenantOwner->id]);
    }

    /** @test */
    public function search_does_not_return_users_from_another_tenant(): void
    {
        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->getJson(route('projects.collaborators.search', $this->project) . '?q=owner2');

        $response->assertOk();
        $response->assertJsonMissing(['id' => $this->otherTenantUser->id]);
    }

    /** @test */
    public function read_only_user_cannot_search_collaborators(): void
    {
        $response = $this->actingAs($this->readOnlyUser)
            ->withHeader('X-Tenant', 'test-tenant')
            ->getJson(route('projects.collaborators.search', $this->project) . '?q=owner');

        $response->assertForbidden();
    }

    /** @test */
    public function manager_can_add_a_collaborator_as_a_project_member(): void
    {
        $candidate = $this->createUserWithRole('candidate@example.com', 'tenant_owner', $this->tenant);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->postJson(route('projects.collaborators.store', $this->project), ['user_id' => $candidate->id]);

        $response->assertOk();
        $response->assertJsonPath('member.user_id', $candidate->id);
        $response->assertJsonPath('active_count', 1);

        $this->assertDatabaseHas('project_members', [
            'project_id' => $this->project->id,
            'user_id' => $candidate->id,
            'is_active' => 1,
            'project_role' => null,
            'rate_per_hour' => null,
        ]);
    }

    /** @test */
    public function adding_the_same_collaborator_twice_is_rejected(): void
    {
        $candidate = $this->createUserWithRole('candidate@example.com', 'tenant_owner', $this->tenant);

        $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->postJson(route('projects.collaborators.store', $this->project), ['user_id' => $candidate->id]);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->postJson(route('projects.collaborators.store', $this->project), ['user_id' => $candidate->id]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('user_id');
        $this->assertDatabaseCount('project_members', 1);
    }

    /** @test */
    public function read_only_user_cannot_add_a_collaborator(): void
    {
        $candidate = $this->createUserWithRole('candidate@example.com', 'tenant_owner', $this->tenant);

        $response = $this->actingAs($this->readOnlyUser)
            ->withHeader('X-Tenant', 'test-tenant')
            ->postJson(route('projects.collaborators.store', $this->project), ['user_id' => $candidate->id]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('project_members', ['project_id' => $this->project->id, 'user_id' => $candidate->id]);
    }

    /** @test */
    public function cannot_add_a_user_from_another_tenant_as_a_collaborator(): void
    {
        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->postJson(route('projects.collaborators.store', $this->project), ['user_id' => $this->otherTenantUser->id]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('user_id');
    }

    /** @test */
    public function manager_can_remove_a_collaborator(): void
    {
        $candidate = $this->createUserWithRole('candidate@example.com', 'tenant_owner', $this->tenant);

        $member = $this->project->members()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $candidate->id,
        ]);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->deleteJson(route('projects.collaborators.destroy', [$this->project, $member]));

        $response->assertOk();
        $response->assertJsonPath('member_id', $member->id);
        $response->assertJsonPath('active_count', 0);

        $this->assertSoftDeleted('project_members', ['id' => $member->id]);
    }

    /** @test */
    public function cannot_remove_the_project_owner_as_a_collaborator(): void
    {
        $member = $this->project->members()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->tenantOwner->id,
        ]);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->deleteJson(route('projects.collaborators.destroy', [$this->project, $member]));

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('member');
        $this->assertDatabaseHas('project_members', ['id' => $member->id, 'deleted_at' => null]);
    }

    /** @test */
    public function cannot_remove_the_project_manager_as_a_collaborator(): void
    {
        $manager = $this->createUserWithRole('manager@example.com', 'tenant_owner', $this->tenant);
        $this->project->update(['manager_id' => $manager->id]);

        $member = $this->project->members()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $manager->id,
        ]);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->deleteJson(route('projects.collaborators.destroy', [$this->project, $member]));

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('member');
        $this->assertDatabaseHas('project_members', ['id' => $member->id, 'deleted_at' => null]);
    }

    /** @test */
    public function read_only_user_cannot_remove_a_collaborator(): void
    {
        $candidate = $this->createUserWithRole('candidate@example.com', 'tenant_owner', $this->tenant);

        $member = $this->project->members()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $candidate->id,
        ]);

        $response = $this->actingAs($this->readOnlyUser)
            ->withHeader('X-Tenant', 'test-tenant')
            ->deleteJson(route('projects.collaborators.destroy', [$this->project, $member]));

        $response->assertForbidden();
        $this->assertDatabaseHas('project_members', ['id' => $member->id, 'deleted_at' => null]);
    }

    /** @test */
    public function removing_a_nonexistent_collaborator_returns_not_found(): void
    {
        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->deleteJson(route('projects.collaborators.destroy', [$this->project, 999999]));

        $response->assertNotFound();
    }
}
