<?php

namespace Tests\Feature;

use App\Domains\Projects\Models\Milestone;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\ProjectMember;
use App\Domains\Projects\Models\TaskList;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectCollaboratorRestrictionTest extends TestCase
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

    private function addCollaborator(User $user, bool $active = true): ProjectMember
    {
        return ProjectMember::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'user_id' => $user->id,
            'is_active' => $active,
        ]);
    }

    private function validProjectPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => $this->project->name,
            'owner_id' => $this->project->owner_id,
            'start_date' => $this->project->start_date->toDateString(),
            'end_date' => $this->project->end_date->toDateString(),
            'priority' => 'High',
            'status' => 'Draft',
        ], $overrides);
    }

    /** @test */
    public function creating_a_project_auto_adds_the_owner_as_an_active_collaborator(): void
    {
        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('projects.store'), [
                'name' => 'New Project',
                'owner_id' => $this->tenantOwner->id,
                'start_date' => now()->toDateString(),
                'end_date' => now()->addMonths(3)->toDateString(),
                'priority' => 'Medium',
                'status' => 'Draft',
            ]);

        $project = Project::where('name', 'New Project')->firstOrFail();
        $response->assertRedirect(route('projects.show', $project));

        $this->assertDatabaseHas('project_members', [
            'project_id' => $project->id,
            'user_id' => $this->tenantOwner->id,
            'is_active' => 1,
        ]);
    }

    /** @test */
    public function owner_id_cannot_be_updated_to_a_non_collaborator(): void
    {
        $outsider = $this->createUserWithRole('outsider@example.com', 'tenant_owner', $this->tenant);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->put(route('projects.update', $this->project), $this->validProjectPayload(['owner_id' => $outsider->id]));

        $response->assertSessionHasErrors('owner_id');
        $this->assertNotEquals($outsider->id, $this->project->fresh()->owner_id);
    }

    /** @test */
    public function owner_id_can_be_updated_to_an_active_collaborator(): void
    {
        $collaborator = $this->createUserWithRole('collab@example.com', 'tenant_owner', $this->tenant);
        $this->addCollaborator($collaborator);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->put(route('projects.update', $this->project), $this->validProjectPayload(['owner_id' => $collaborator->id]));

        $response->assertRedirect(route('projects.show', $this->project));
        $this->assertEquals($collaborator->id, $this->project->fresh()->owner_id);
    }

    /** @test */
    public function owner_id_cannot_be_inline_edited_to_an_inactive_collaborator(): void
    {
        $inactive = $this->createUserWithRole('inactive@example.com', 'tenant_owner', $this->tenant);
        $this->addCollaborator($inactive, active: false);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->patchJson(route('projects.field', $this->project), ['field' => 'owner_id', 'value' => $inactive->id]);

        $response->assertUnprocessable();
        $this->assertNotEquals($inactive->id, $this->project->fresh()->owner_id);
    }

    /** @test */
    public function manager_id_inline_edit_to_an_active_collaborator_ensures_membership_stays_active(): void
    {
        $manager = $this->createUserWithRole('manager@example.com', 'tenant_owner', $this->tenant);
        $this->addCollaborator($manager);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->patchJson(route('projects.field', $this->project), ['field' => 'manager_id', 'value' => $manager->id]);

        $response->assertOk();
        $this->assertEquals($manager->id, $this->project->fresh()->manager_id);
    }

    /** @test */
    public function milestone_owner_must_be_an_active_collaborator(): void
    {
        $outsider = $this->createUserWithRole('outsider2@example.com', 'tenant_owner', $this->tenant);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('projects.milestones.store', $this->project), [
                'name' => 'Phase 1',
                'owner_id' => $outsider->id,
            ]);

        $response->assertSessionHasErrors('owner_id');
        $this->assertDatabaseMissing('project_milestones', ['name' => 'Phase 1']);
    }

    /** @test */
    public function milestone_owner_cannot_be_an_inactive_collaborator(): void
    {
        $collaborator = $this->createUserWithRole('milestoneowner@example.com', 'tenant_owner', $this->tenant);
        $this->addCollaborator($collaborator, active: false);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('projects.milestones.store', $this->project), [
                'name' => 'Phase 1',
                'owner_id' => $collaborator->id,
            ]);

        $response->assertSessionHasErrors('owner_id');
    }

    /** @test */
    public function milestone_created_with_an_active_collaborator_owner_succeeds(): void
    {
        $collaborator = $this->createUserWithRole('milestoneowner3@example.com', 'tenant_owner', $this->tenant);
        $this->addCollaborator($collaborator);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('projects.milestones.store', $this->project), [
                'name' => 'Phase 1',
                'owner_id' => $collaborator->id,
            ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('project_milestones', ['name' => 'Phase 1', 'owner_id' => $collaborator->id]);
    }

    /** @test */
    public function tasklist_owner_defaults_via_json_inline_create_are_auto_ensured_as_collaborators(): void
    {
        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->postJson(route('projects.tasklists.store', $this->project), [
                'name' => 'Backlog',
            ]);

        $response->assertOk();

        $taskList = TaskList::where('project_id', $this->project->id)->where('name', 'Backlog')->firstOrFail();
        $this->assertEquals($this->tenantOwner->id, $taskList->owner_id);

        $this->assertDatabaseHas('project_members', [
            'project_id' => $this->project->id,
            'user_id' => $this->tenantOwner->id,
            'is_active' => 1,
        ]);
    }

    /** @test */
    public function removing_the_current_project_owner_as_a_collaborator_is_blocked(): void
    {
        $ownerMember = $this->addCollaborator($this->tenantOwner);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->delete(route('projects.members.destroy', [$this->project, $ownerMember]));

        $response->assertSessionHasErrors('member');
        $this->assertDatabaseHas('project_members', ['id' => $ownerMember->id]);
    }

    /** @test */
    public function deactivating_the_current_milestone_owner_as_a_collaborator_is_blocked(): void
    {
        $collaborator = $this->createUserWithRole('milestoneowner2@example.com', 'tenant_owner', $this->tenant);
        $member = $this->addCollaborator($collaborator);

        Milestone::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Phase 1',
            'owner_id' => $collaborator->id,
        ]);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->patch(route('projects.members.toggle-active', [$this->project, $member]));

        $response->assertSessionHasErrors('member');
        $this->assertTrue($member->fresh()->is_active);
    }

    /** @test */
    public function removing_a_collaborator_with_no_assigned_role_still_works(): void
    {
        $plainCollaborator = $this->createUserWithRole('plain@example.com', 'tenant_owner', $this->tenant);
        $member = $this->addCollaborator($plainCollaborator);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->delete(route('projects.members.destroy', [$this->project, $member]));

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $this->assertSoftDeleted('project_members', ['id' => $member->id]);
    }

    /** @test */
    public function updating_a_member_cannot_deactivate_the_current_project_owner(): void
    {
        $ownerMember = $this->addCollaborator($this->tenantOwner);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->put(route('projects.members.update', [$this->project, $ownerMember]), ['is_active' => false]);

        $response->assertSessionHasErrors('member');
        $this->assertTrue($ownerMember->fresh()->is_active);
    }

    /** @test */
    public function updating_a_member_cannot_reassign_the_user_id_of_the_current_milestone_owner(): void
    {
        $collaborator = $this->createUserWithRole('milestoneowner3@example.com', 'tenant_owner', $this->tenant);
        $member = $this->addCollaborator($collaborator);
        $replacement = $this->createUserWithRole('replacement@example.com', 'tenant_owner', $this->tenant);

        Milestone::create([
            'tenant_id' => $this->tenant->id,
            'project_id' => $this->project->id,
            'name' => 'Phase 1',
            'owner_id' => $collaborator->id,
        ]);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->put(route('projects.members.update', [$this->project, $member]), ['user_id' => $replacement->id]);

        $response->assertSessionHasErrors('member');
        $this->assertEquals($collaborator->id, $member->fresh()->user_id);
    }
}
