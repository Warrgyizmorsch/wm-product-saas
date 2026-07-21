<?php

namespace Tests\Feature;

use App\Domains\Projects\Models\ActivityLog;
use App\Domains\Projects\Models\Project;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectsAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Tenant $otherTenant;
    private User $tenantOwner;
    private User $readOnlyUser;
    private User $otherTenantOwner;

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
        $this->otherTenantOwner = $this->createUserWithRole('owner2@example.com', 'tenant_owner', $this->otherTenant);
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

    private function validProjectPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'ERP Development',
            'owner_id' => $this->tenantOwner->id,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonths(6)->toDateString(),
            'priority' => 'High',
            'status' => 'Draft',
            'description' => 'Test project',
        ], $overrides);
    }

    /** @test */
    public function guest_is_redirected_to_login_instead_of_reaching_projects(): void
    {
        $response = $this->withHeader('X-Tenant', 'test-tenant')
            ->get(route('projects.index'));

        $response->assertRedirect(route('login'));
    }

    /** @test */
    public function tenant_owner_can_create_a_project_with_auto_generated_code_and_activity_log(): void
    {
        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('projects.store'), $this->validProjectPayload());

        $project = Project::withoutGlobalScopes()->where('name', 'ERP Development')->firstOrFail();

        $response->assertRedirect(route('projects.show', $project));

        $this->assertSame('PRJ-0001', $project->project_code);
        $this->assertSame($this->tenant->id, $project->tenant_id);

        $this->assertDatabaseHas('project_activity_logs', [
            'project_id' => $project->id,
            'event_type' => 'project.created',
        ]);
    }

    /**
     * @test
     * @dataProvider terminalCreationStatusProvider
     */
    public function project_cannot_be_created_directly_in_a_terminal_status(string $terminalStatus): void
    {
        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('projects.store'), $this->validProjectPayload(['status' => $terminalStatus]));

        $response->assertSessionHasErrors('status');
        $this->assertDatabaseMissing('projects', ['name' => 'ERP Development']);
    }

    public static function terminalCreationStatusProvider(): array
    {
        return [
            'closed' => ['Closed'],
            'cancelled' => ['Cancelled'],
        ];
    }

    /**
     * @test
     * @dataProvider creatableStatusProvider
     */
    public function project_can_be_created_as_draft_or_active(string $creatableStatus): void
    {
        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('projects.store'), $this->validProjectPayload(['status' => $creatableStatus]));

        $project = Project::withoutGlobalScopes()->where('name', 'ERP Development')->firstOrFail();

        $response->assertRedirect(route('projects.show', $project));
        $this->assertSame($creatableStatus, $project->status);
    }

    public static function creatableStatusProvider(): array
    {
        return [
            'draft' => ['Draft'],
            'active' => ['Active'],
        ];
    }

    /** @test */
    public function status_change_is_guarded_and_writes_an_activity_log(): void
    {
        $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('projects.store'), $this->validProjectPayload());

        $project = Project::withoutGlobalScopes()->where('name', 'ERP Development')->firstOrFail();

        // Draft -> Completed is not an allowed transition
        $invalid = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->put(route('projects.update', $project), $this->validProjectPayload(['status' => 'Completed']));

        $invalid->assertSessionHasErrors('status');

        // Draft -> Active is allowed
        $valid = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->put(route('projects.update', $project), $this->validProjectPayload(['status' => 'Active']));

        $valid->assertRedirect(route('projects.show', $project));

        $this->assertSame('Active', $project->fresh()->status);
        $this->assertDatabaseHas('project_activity_logs', [
            'project_id' => $project->id,
            'event_type' => 'project.status_changed',
        ]);
    }

    /** @test */
    public function cancelled_and_closed_transitions_are_enforced_identically_on_the_full_update_form(): void
    {
        $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('projects.store'), $this->validProjectPayload());

        $project = Project::withoutGlobalScopes()->where('name', 'ERP Development')->firstOrFail();

        // Draft -> Cancelled is allowed
        $cancelled = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->put(route('projects.update', $project), $this->validProjectPayload(['status' => 'Cancelled']));

        $cancelled->assertRedirect(route('projects.show', $project));
        $this->assertSame('Cancelled', $project->fresh()->status);
        $this->assertDatabaseHas('project_activity_logs', [
            'project_id' => $project->id,
            'event_type' => 'project.status_changed',
            'description' => "Status changed from 'Draft' to 'Cancelled'",
        ]);

        $log = ActivityLog::withoutGlobalScopes()
            ->where('project_id', $project->id)
            ->where('event_type', 'project.status_changed')
            ->firstOrFail();

        // Same metadata shape the inline-edit path writes for the identical action.
        $this->assertSame(['old' => 'Draft', 'new' => 'Cancelled'], $log->metadata);

        // Cancelled is terminal: no further transition is allowed
        $blocked = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->put(route('projects.update', $project), $this->validProjectPayload(['status' => 'Active']));

        $blocked->assertSessionHasErrors('status');
        $this->assertSame('Cancelled', $project->fresh()->status);
    }

    /** @test */
    public function completed_project_can_be_closed_on_the_full_update_form(): void
    {
        $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('projects.store'), $this->validProjectPayload(['status' => 'Active']));

        $project = Project::withoutGlobalScopes()->where('name', 'ERP Development')->firstOrFail();

        $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->put(route('projects.update', $project), $this->validProjectPayload(['status' => 'Completed']));

        $closed = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->put(route('projects.update', $project), $this->validProjectPayload(['status' => 'Closed']));

        $closed->assertRedirect(route('projects.show', $project));
        $this->assertSame('Closed', $project->fresh()->status);
        $this->assertDatabaseHas('project_activity_logs', [
            'project_id' => $project->id,
            'event_type' => 'project.status_changed',
            'description' => "Status changed from 'Completed' to 'Closed'",
        ]);
    }

    /** @test */
    public function read_only_user_is_forbidden_from_projects_index(): void
    {
        $response = $this->actingAs($this->readOnlyUser)
            ->withHeader('X-Tenant', 'test-tenant')
            ->get(route('projects.index'));

        $response->assertForbidden();
    }

    /** @test */
    public function read_only_user_cannot_update_a_project_field_inline(): void
    {
        $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('projects.store'), $this->validProjectPayload());

        $project = Project::withoutGlobalScopes()->where('name', 'ERP Development')->firstOrFail();

        $response = $this->actingAs($this->readOnlyUser)
            ->withHeader('X-Tenant', 'test-tenant')
            ->patchJson(route('projects.field', $project), ['field' => 'name', 'value' => 'Hijacked Name']);

        $response->assertForbidden();
        $this->assertSame('ERP Development', $project->fresh()->name);
    }

    /** @test */
    public function a_project_is_invisible_to_another_tenant_even_by_direct_url(): void
    {
        $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('projects.store'), $this->validProjectPayload());

        $project = Project::withoutGlobalScopes()->where('name', 'ERP Development')->firstOrFail();

        $response = $this->actingAs($this->otherTenantOwner)
            ->withHeader('X-Tenant', 'other-tenant')
            ->get(route('projects.show', $project));

        $response->assertNotFound();
    }

    /** @test */
    public function tenant_owner_can_soft_delete_a_project(): void
    {
        $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('projects.store'), $this->validProjectPayload());

        $project = Project::withoutGlobalScopes()->where('name', 'ERP Development')->firstOrFail();

        $response = $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->delete(route('projects.destroy', $project));

        $response->assertRedirect(route('projects.index'));
        $this->assertSoftDeleted($project);

        $this->assertDatabaseHas('project_activity_logs', [
            'project_id' => $project->id,
            'event_type' => 'project.deleted',
        ]);
    }

    /** @test */
    public function project_codes_increment_per_tenant(): void
    {
        $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('projects.store'), $this->validProjectPayload());

        $this->actingAs($this->tenantOwner)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('projects.store'), $this->validProjectPayload(['name' => 'Second Project']));

        // A different tenant starts back at PRJ-0001
        $this->actingAs($this->otherTenantOwner)
            ->withHeader('X-Tenant', 'other-tenant')
            ->post(route('projects.store'), $this->validProjectPayload([
                'name' => 'Other Tenant Project',
                'owner_id' => $this->otherTenantOwner->id,
            ]));

        $second = Project::withoutGlobalScopes()->where('name', 'Second Project')->firstOrFail();
        $other = Project::withoutGlobalScopes()->where('name', 'Other Tenant Project')->firstOrFail();

        $this->assertSame('PRJ-0002', $second->project_code);
        $this->assertSame('PRJ-0001', $other->project_code);
    }
}
