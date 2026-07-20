<?php

namespace Tests\Feature;

use App\Domains\Projects\Models\Project;
use App\Models\Access\Permission;
use App\Models\Access\Role;
use App\Models\Access\RolePermission;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Covers the modal-based quick-create flow in ProjectController. Description edits
 * (and all other field edits) go through inline editing on the show page instead
 * (see ProjectInlineFieldDescriptionTest) — the directory listing no longer offers
 * a full-form edit modal. The create modal scopes old()/$errors to itself via a
 * hidden "_modal" input, since old() and $errors are global to the request.
 */
class ProjectEditModalTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $tenantOwner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Test Tenant', 'slug' => 'test-tenant', 'status' => 'active', 'plan' => 'enterprise',
        ]);
        $this->seed(RbacSeeder::class);

        $this->tenantOwner = User::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Owner', 'email' => 'owner@example.com', 'password' => bcrypt('password'),
        ]);
        $role = Role::query()->whereNull('tenant_id')->where('slug', 'tenant_owner')->firstOrFail();
        UserRole::create(['user_id' => $this->tenantOwner->id, 'role_id' => $role->id, 'tenant_id' => $this->tenant->id]);
    }

    private function createProject(array $overrides = []): Project
    {
        return Project::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'project_code' => 'PRJ-0001',
            'name' => 'Original Name',
            'owner_id' => $this->tenantOwner->id,
            'start_date' => now(),
            'priority' => Project::PRIORITY_MEDIUM,
            'status' => Project::STATUS_DRAFT,
        ], $overrides));
    }

    /** @test */
    public function failed_quick_create_reopens_create_modal_with_entered_name_preserved(): void
    {
        $longName = str_repeat('A', 300);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->from(route('projects.index'))
            ->post(route('projects.store'), [
                '_modal'     => 'createProjectModal',
                'name'       => $longName, // exceeds max:255 -> invalid
                'owner_id'   => $this->tenantOwner->id,
                'start_date' => now()->toDateString(),
                'priority'   => Project::PRIORITY_MEDIUM,
                'status'     => Project::STATUS_DRAFT,
            ]);

        $response->assertRedirect(route('projects.index'));
        $response->assertSessionHasErrors('name');

        $reopen = $this->get(route('projects.index'));
        $reopen->assertOk();

        $reopen->assertSee("document.getElementById('createProjectModal')", false);
        $reopen->assertSee($longName, false);
    }

    /** @test */
    public function user_can_update_only_projects_they_own_under_own_scoped_permission(): void
    {
        $viewPermission = Permission::query()->where('name', 'projects.projects.view')->firstOrFail();
        $updatePermission = Permission::query()->where('name', 'projects.projects.update')->firstOrFail();

        $role = Role::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Project Editor (Own)',
            'slug'      => 'project_editor_own_scope',
            'level'     => 60,
        ]);
        RolePermission::create(['role_id' => $role->id, 'permission_id' => $viewPermission->id, 'scope' => RolePermission::SCOPE_TENANT]);
        RolePermission::create(['role_id' => $role->id, 'permission_id' => $updatePermission->id, 'scope' => RolePermission::SCOPE_OWN]);

        $editor = User::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Editor', 'email' => 'editor@example.com', 'password' => bcrypt('password'),
        ]);
        UserRole::create(['user_id' => $editor->id, 'role_id' => $role->id, 'tenant_id' => $this->tenant->id]);

        $ownProject = $this->createProject(['project_code' => 'PRJ-0001', 'name' => 'Owned by Editor', 'owner_id' => $editor->id]);
        $otherProject = $this->createProject(['project_code' => 'PRJ-0002', 'name' => 'Owned by Owner', 'owner_id' => $this->tenantOwner->id]);

        // Editor can view both projects (tenant-scoped view) but only edit their own.
        $index = $this->actingAs($editor)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('projects.index'));

        $index->assertOk();

        // Update succeeds for the project they own.
        $allowed = $this->actingAs($editor)
            ->withHeader('X-Tenant', 'test-tenant')
            ->put(route('projects.update', $ownProject), [
                'name'       => 'Updated By Editor',
                'owner_id'   => $editor->id,
                'start_date' => now()->toDateString(),
                'priority'   => Project::PRIORITY_MEDIUM,
                'status'     => Project::STATUS_ACTIVE,
            ]);
        $allowed->assertRedirect(route('projects.show', $ownProject->fresh()));
        $this->assertSame('Updated By Editor', $ownProject->fresh()->name);

        // Update is forbidden for a project owned by someone else.
        $forbidden = $this->actingAs($editor)
            ->withHeader('X-Tenant', 'test-tenant')
            ->put(route('projects.update', $otherProject), [
                'name'       => 'Should Not Apply',
                'owner_id'   => $this->tenantOwner->id,
                'start_date' => now()->toDateString(),
                'priority'   => Project::PRIORITY_MEDIUM,
                'status'     => Project::STATUS_ACTIVE,
            ]);
        $forbidden->assertForbidden();
        $this->assertSame('Owned by Owner', $otherProject->fresh()->name);
    }

    /** @test */
    public function user_without_create_permission_cannot_create_a_project(): void
    {
        $viewPermission = Permission::query()->where('name', 'projects.projects.view')->firstOrFail();

        $role = Role::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Project Viewer',
            'slug'      => 'project_viewer_only',
            'level'     => 70,
        ]);
        RolePermission::create(['role_id' => $role->id, 'permission_id' => $viewPermission->id, 'scope' => RolePermission::SCOPE_TENANT]);

        $viewer = User::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Viewer', 'email' => 'viewer@example.com', 'password' => bcrypt('password'),
        ]);
        UserRole::create(['user_id' => $viewer->id, 'role_id' => $role->id, 'tenant_id' => $this->tenant->id]);

        $index = $this->actingAs($viewer)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('projects.index'));
        $index->assertOk();
        $index->assertDontSee('id="createProjectModal"', false);

        $response = $this->actingAs($viewer)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('projects.store'), [
                'name'       => 'Should Not Be Created',
                'owner_id'   => $viewer->id,
                'start_date' => now()->toDateString(),
                'priority'   => Project::PRIORITY_MEDIUM,
                'status'     => Project::STATUS_DRAFT,
            ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('projects', ['name' => 'Should Not Be Created']);
    }
}
