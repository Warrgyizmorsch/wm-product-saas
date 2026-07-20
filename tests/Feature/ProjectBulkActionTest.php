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

class ProjectBulkActionTest extends TestCase
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
    public function selected_projects_are_deleted_in_bulk(): void
    {
        $projectA = $this->createProject(['project_code' => 'PRJ-0001']);
        $projectB = $this->createProject(['project_code' => 'PRJ-0002']);
        $projectC = $this->createProject(['project_code' => 'PRJ-0003']);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('projects.bulk-action'), [
                'action' => 'delete',
                'ids'    => [$projectA->id, $projectB->id],
            ]);

        $response->assertRedirect(route('projects.index'));
        $response->assertSessionHas('success');

        $this->assertSoftDeleted('projects', ['id' => $projectA->id]);
        $this->assertSoftDeleted('projects', ['id' => $projectB->id]);
        $this->assertDatabaseHas('projects', ['id' => $projectC->id, 'deleted_at' => null]);
    }

    /** @test */
    public function bulk_delete_with_no_ids_is_rejected(): void
    {
        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('projects.bulk-action'), ['action' => 'delete']);

        $response->assertRedirect(route('projects.index'));
        $response->assertSessionHas('error');
    }

    /** @test */
    public function an_unsupported_action_is_rejected(): void
    {
        $project = $this->createProject();

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('projects.bulk-action'), [
                'action' => 'archive',
                'ids'    => [$project->id],
            ]);

        $response->assertRedirect(route('projects.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('projects', ['id' => $project->id]);
    }

    /** @test */
    public function a_user_can_only_bulk_delete_projects_they_are_authorized_to_delete(): void
    {
        $viewPermission = Permission::query()->where('name', 'projects.projects.view')->firstOrFail();
        $updatePermission = Permission::query()->where('name', 'projects.projects.update')->firstOrFail();
        $deletePermission = Permission::query()->where('name', 'projects.projects.delete')->firstOrFail();

        $role = Role::create([
            'tenant_id' => $this->tenant->id,
            'name'      => 'Project Editor (Own)',
            'slug'      => 'project_editor_own_scope_bulk',
            'level'     => 60,
        ]);
        RolePermission::create(['role_id' => $role->id, 'permission_id' => $viewPermission->id, 'scope' => RolePermission::SCOPE_TENANT]);
        RolePermission::create(['role_id' => $role->id, 'permission_id' => $updatePermission->id, 'scope' => RolePermission::SCOPE_OWN]);
        RolePermission::create(['role_id' => $role->id, 'permission_id' => $deletePermission->id, 'scope' => RolePermission::SCOPE_OWN]);

        $editor = User::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Editor', 'email' => 'editor@example.com', 'password' => bcrypt('password'),
        ]);
        UserRole::create(['user_id' => $editor->id, 'role_id' => $role->id, 'tenant_id' => $this->tenant->id]);

        $ownProject = $this->createProject(['project_code' => 'PRJ-0001', 'owner_id' => $editor->id]);
        $otherProject = $this->createProject(['project_code' => 'PRJ-0002', 'owner_id' => $this->tenantOwner->id]);

        $response = $this->actingAs($editor)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('projects.bulk-action'), [
                'action' => 'delete',
                'ids'    => [$ownProject->id, $otherProject->id],
            ]);

        $response->assertRedirect(route('projects.index'));
        $response->assertSessionHas('success');

        $this->assertSoftDeleted('projects', ['id' => $ownProject->id]);
        $this->assertDatabaseHas('projects', ['id' => $otherProject->id, 'deleted_at' => null]);
    }
}
