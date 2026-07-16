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
 * Covers the modal-based create/edit refactor in ProjectController. Description is
 * the only field still edited via the full-form "editProjectModal-{id}" (see
 * resources/views/modules/projects/_edit-description-modal.blade.php); every other
 * field uses inline editing instead. The modal scopes old()/$errors to itself via a
 * hidden "_modal" input, since old() and $errors are global to the request but the
 * index page renders one edit modal per visible project.
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
    public function failed_edit_reopens_only_the_submitted_projects_modal(): void
    {
        $projectA = $this->createProject(['project_code' => 'PRJ-0001', 'name' => 'Alpha']);
        $projectB = $this->createProject(['project_code' => 'PRJ-0002', 'name' => 'Beta']);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->from(route('projects.index'))
            ->put(route('projects.update', $projectA), [
                '_modal'     => 'editProjectModal-' . $projectA->id,
                'name'       => 'Alpha Rework Attempt',
                'owner_id'   => $this->tenantOwner->id,
                'start_date' => now()->toDateString(),
                'end_date'   => now()->subDay()->toDateString(), // before start_date -> invalid
                'priority'   => Project::PRIORITY_MEDIUM,
                'status'     => Project::STATUS_ACTIVE,
            ]);

        $response->assertRedirect(route('projects.index'));
        $response->assertSessionHasErrors('end_date');

        // Underlying record is untouched by the failed submission.
        $this->assertSame('Alpha', $projectA->fresh()->name);

        $reopen = $this->get(route('projects.index'));
        $reopen->assertOk();

        // Every rendered modal carries its own boilerplate relocation script (see
        // x-ui.modal), which also calls getElementById with that modal's id — so we
        // isolate the *reopen* script specifically (identified by its unique
        // ".show()" call) rather than asserting on getElementById() in general.
        $content = $reopen->getContent();
        $marker = 'if (modalEl && window.bootstrap)';
        $markerPos = strpos($content, $marker);
        $this->assertNotFalse($markerPos, 'Expected the modal reopen script to be present.');

        $reopenSnippet = substr($content, max(0, $markerPos - 200), 250);
        $this->assertStringContainsString('editProjectModal-' . $projectA->id, $reopenSnippet);
        $this->assertStringNotContainsString('editProjectModal-' . $projectB->id, $reopenSnippet);
    }

    /** @test */
    public function edit_validation_errors_and_old_input_do_not_leak_into_other_project_modals(): void
    {
        $projectA = $this->createProject(['project_code' => 'PRJ-0001', 'name' => 'Alpha']);
        $projectB = $this->createProject(['project_code' => 'PRJ-0002', 'name' => 'Beta']);

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->from(route('projects.index'))
            ->put(route('projects.update', $projectA), [
                '_modal'     => 'editProjectModal-' . $projectA->id,
                'name'       => 'Alpha Rework Attempt',
                'owner_id'   => $this->tenantOwner->id,
                'start_date' => now()->toDateString(),
                'end_date'   => now()->subDay()->toDateString(), // before start_date -> invalid
                'priority'   => Project::PRIORITY_MEDIUM,
                'status'     => Project::STATUS_ACTIVE,
            ]);

        $response->assertSessionHasErrors('end_date');
        $errorMessage = session('errors')->first('end_date');
        $this->assertNotEmpty($errorMessage);

        $reopen = $this->get(route('projects.index'));
        $reopen->assertOk();

        $content = $reopen->getContent();

        $startA = strpos($content, 'id="editProjectModal-' . $projectA->id . '"');
        $startB = strpos($content, 'id="editProjectModal-' . $projectB->id . '"');

        $this->assertNotFalse($startA, 'Expected project A\'s edit modal to be rendered.');
        $this->assertNotFalse($startB, 'Expected project B\'s edit modal to be rendered.');
        $this->assertLessThan($startB, $startA, 'Expected project A to render before project B (sorted by project_code).');

        // Bound blockB before the trailing reopen-script/toast scripts at the end of the
        // page (which legitimately echo the raw error message globally) so we're only
        // inspecting project B's own modal markup, not unrelated page-level scripts.
        $trailingScriptsPos = strpos($content, 'if (modalEl && window.bootstrap)', $startB);
        $this->assertNotFalse($trailingScriptsPos, 'Expected the modal reopen script to be present.');

        $blockA = substr($content, $startA, $startB - $startA);
        $blockB = substr($content, $startB, $trailingScriptsPos - $startB);

        // The submitted modal shows the stale (invalid) old() input and the field error.
        $this->assertStringContainsString('Alpha Rework Attempt', $blockA);
        $this->assertStringContainsString($errorMessage, $blockA);

        // The other project's modal must show its own unrelated stored data, not the
        // failed submission's stale input or error.
        $this->assertStringNotContainsString('Alpha Rework Attempt', $blockB);
        $this->assertStringNotContainsString($errorMessage, $blockB);
        $this->assertStringContainsString('value="Beta"', $blockB);
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
        $index->assertSee('data-bs-target="#editProjectModal-' . $ownProject->id . '"', false);
        $index->assertDontSee('id="editProjectModal-' . $otherProject->id . '"', false);

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
