<?php

namespace Tests\Feature;

use App\Models\Access\AccessAuditLog;
use App\Models\Access\Permission;
use App\Models\Access\Role;
use App\Models\Access\RolePermission;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Tenant $otherTenant;
    private User $tenantOwner;
    private User $readOnly;
    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Test Tenant', 'slug' => 'test-tenant', 'status' => 'active', 'plan' => 'enterprise',
        ]);
        $this->otherTenant = Tenant::create([
            'name' => 'Other Tenant', 'slug' => 'other-tenant', 'status' => 'active', 'plan' => 'enterprise',
        ]);

        $this->seed(RbacSeeder::class);

        $this->tenantOwner = $this->createUserWithRole('owner@example.com', 'tenant_owner', $this->tenant);
        $this->readOnly = $this->createUserWithRole('viewer@example.com', 'read_only', $this->tenant);
        $this->superAdmin = $this->createUserWithRole('super@example.com', 'super_admin', $this->tenant);
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

        $user->forceFill(['role_id' => $role->id])->save();

        return $user;
    }

    /** @test */
    public function guest_is_redirected_to_login_for_users_and_roles_screens(): void
    {
        $this->withHeader('X-Tenant', 'test-tenant')->get(route('access.users.index'))
            ->assertRedirect(route('login'));

        $this->withHeader('X-Tenant', 'test-tenant')->get(route('access.roles.index'))
            ->assertRedirect(route('login'));
    }

    /** @test */
    public function read_only_role_is_denied_on_every_new_route(): void
    {
        $anotherUser = $this->createUserWithRole('another@example.com', 'read_only', $this->tenant);

        $role = Role::query()->whereNull('tenant_id')->where('slug', 'sales_executive')->firstOrFail();

        $this->actingAs($this->readOnly)->withHeader('X-Tenant', 'test-tenant')
            ->get(route('access.users.index'))->assertForbidden();

        $this->actingAs($this->readOnly)->withHeader('X-Tenant', 'test-tenant')
            ->get(route('access.users.create'))->assertForbidden();

        $this->actingAs($this->readOnly)->withHeader('X-Tenant', 'test-tenant')
            ->post(route('access.users.store'), [])->assertForbidden();

        $this->actingAs($this->readOnly)->withHeader('X-Tenant', 'test-tenant')
            ->get(route('access.users.edit', $anotherUser))->assertForbidden();

        $this->actingAs($this->readOnly)->withHeader('X-Tenant', 'test-tenant')
            ->put(route('access.users.update', $anotherUser), [])->assertForbidden();

        $this->actingAs($this->readOnly)->withHeader('X-Tenant', 'test-tenant')
            ->get(route('access.roles.index'))->assertForbidden();

        $this->actingAs($this->readOnly)->withHeader('X-Tenant', 'test-tenant')
            ->get(route('access.roles.create'))->assertForbidden();

        $this->actingAs($this->readOnly)->withHeader('X-Tenant', 'test-tenant')
            ->post(route('access.roles.store'), [])->assertForbidden();

        $this->actingAs($this->readOnly)->withHeader('X-Tenant', 'test-tenant')
            ->get(route('access.roles.show', $role))->assertForbidden();

        $this->actingAs($this->readOnly)->withHeader('X-Tenant', 'test-tenant')
            ->put(route('access.roles.permissions.update', $role), [])->assertForbidden();
    }

    /** @test */
    public function tenant_owner_can_create_and_edit_a_user_in_their_own_tenant(): void
    {
        $salesExecutive = Role::query()->whereNull('tenant_id')->where('slug', 'sales_executive')->firstOrFail();

        $store = $this->actingAs($this->tenantOwner)->withHeader('X-Tenant', 'test-tenant')
            ->post(route('access.users.store'), [
                'name' => 'Jane Doe',
                'email' => 'jane@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role_id' => $salesExecutive->id,
            ]);

        $store->assertRedirect(route('access.users.index'));

        $newUser = User::where('email', 'jane@example.com')->firstOrFail();
        $this->assertEquals($salesExecutive->id, $newUser->role_id);

        $update = $this->actingAs($this->tenantOwner)->withHeader('X-Tenant', 'test-tenant')
            ->put(route('access.users.update', $newUser), [
                'name' => 'Jane Doe Updated',
                'email' => 'jane@example.com',
                'role_id' => $salesExecutive->id,
            ]);

        $update->assertRedirect(route('access.users.index'));
        $this->assertEquals('Jane Doe Updated', $newUser->fresh()->name);
    }

    /** @test */
    public function tenant_owner_cannot_edit_a_user_belonging_to_a_different_tenant(): void
    {
        // Route-model-binding for {user} is not tenant-scoped at the query
        // level (SubstituteBindings runs before the app's `tenant` middleware
        // sets TenantContext), so the explicit tenant_id check inside
        // UserPolicy::update() is the actual security boundary here — it
        // denies with 403 rather than a binding-level 404.
        $otherUser = $this->createUserWithRole('cross@example.com', 'sales_executive', $this->otherTenant);

        $response = $this->actingAs($this->tenantOwner)->withHeader('X-Tenant', 'test-tenant')
            ->get(route('access.users.edit', $otherUser));

        $response->assertForbidden();
    }

    /** @test */
    public function tenant_owner_can_assign_multiple_roles_to_a_user_and_change_them_later(): void
    {
        $salesExecutive = Role::query()->whereNull('tenant_id')->where('slug', 'sales_executive')->firstOrFail();
        $inventoryManager = Role::query()->whereNull('tenant_id')->where('slug', 'inventory_manager')->firstOrFail();
        $accountant = Role::query()->whereNull('tenant_id')->where('slug', 'accountant')->firstOrFail();

        $store = $this->actingAs($this->tenantOwner)->withHeader('X-Tenant', 'test-tenant')
            ->post(route('access.users.store'), [
                'name' => 'Multi Role',
                'email' => 'multirole@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role_ids' => [$salesExecutive->id, $inventoryManager->id],
            ]);

        $store->assertRedirect(route('access.users.index'));

        $user = User::where('email', 'multirole@example.com')->firstOrFail();

        $this->assertEqualsCanonicalizing(
            [$salesExecutive->id, $inventoryManager->id],
            $user->roles()->pluck('roles.id')->all()
        );
        // sales_executive (level 50) and inventory_manager (level 40) — the
        // more senior (lower level) role becomes the primary role_id.
        $this->assertEquals($inventoryManager->id, $user->role_id);

        $update = $this->actingAs($this->tenantOwner)->withHeader('X-Tenant', 'test-tenant')
            ->put(route('access.users.update', $user), [
                'name' => 'Multi Role',
                'email' => 'multirole@example.com',
                'role_ids' => [$accountant->id],
            ]);

        $update->assertRedirect(route('access.users.index'));

        $this->assertEqualsCanonicalizing(
            [$accountant->id],
            $user->fresh()->roles()->pluck('roles.id')->all()
        );
        $this->assertEquals($accountant->id, $user->fresh()->role_id);
    }

    /** @test */
    public function tenant_owner_can_deny_and_then_allow_a_specific_permission_override_for_a_user(): void
    {
        $target = $this->createUserWithRole('overridden@example.com', 'sales_executive', $this->tenant);
        $permission = Permission::where('name', 'crm.leads.create')->firstOrFail();

        // sales_executive is granted crm.leads.create at tenant scope by RbacSeeder,
        // so an explicit deny override should block it despite the role grant.
        $this->assertTrue(app(\App\Services\Access\AccessService::class)->allows($target, 'crm.leads.create', [
            'tenant_id' => $this->tenant->id,
        ]));

        $deny = $this->actingAs($this->tenantOwner)->withHeader('X-Tenant', 'test-tenant')
            ->put(route('access.users.overrides.update', $target), [
                'overrides' => [
                    $permission->id => ['state' => 'deny', 'reason' => 'Under investigation'],
                ],
            ]);

        $deny->assertRedirect(route('access.users.overrides.edit', $target));
        $this->assertDatabaseHas('user_permission_overrides', [
            'user_id' => $target->id,
            'permission_id' => $permission->id,
            'allowed' => false,
            'reason' => 'Under investigation',
        ]);

        $this->assertFalse(app(\App\Services\Access\AccessService::class)->allows($target->fresh(), 'crm.leads.create', [
            'tenant_id' => $this->tenant->id,
        ]));

        // Setting it back to "inherit" removes the override row and restores the role grant.
        $this->actingAs($this->tenantOwner)->withHeader('X-Tenant', 'test-tenant')
            ->put(route('access.users.overrides.update', $target), [
                'overrides' => [
                    $permission->id => ['state' => 'inherit'],
                ],
            ]);

        $this->assertDatabaseMissing('user_permission_overrides', [
            'user_id' => $target->id,
            'permission_id' => $permission->id,
        ]);

        $this->assertTrue(app(\App\Services\Access\AccessService::class)->allows($target->fresh(), 'crm.leads.create', [
            'tenant_id' => $this->tenant->id,
        ]));
    }

    /** @test */
    public function a_platform_permission_cannot_be_granted_through_a_user_override(): void
    {
        // matchingOverride() returns `allowed` without running scopeMatches(),
        // so an override row satisfies a platform-wide check that no
        // tenant-scoped role grant can. A tenant owner must therefore never be
        // able to write one, or they could browse every other tenant.
        $target = $this->createUserWithRole('escalate@example.com', 'sales_executive', $this->tenant);
        $platformPermission = Permission::where('name', 'platform.tenants.manage')->firstOrFail();

        $this->actingAs($this->tenantOwner)->withHeader('X-Tenant', 'test-tenant')
            ->put(route('access.users.overrides.update', $target), [
                'overrides' => [
                    $platformPermission->id => ['state' => 'allow', 'reason' => 'nice try'],
                ],
            ]);

        $this->assertDatabaseMissing('user_permission_overrides', [
            'user_id' => $target->id,
            'permission_id' => $platformPermission->id,
        ]);

        $this->assertFalse(app(\App\Services\Access\AccessService::class)->allows(
            $target->fresh(),
            'platform.tenants.manage'
        ));
    }

    /** @test */
    public function role_creation_permission_changes_and_role_assignment_are_all_audit_logged(): void
    {
        $store = $this->actingAs($this->tenantOwner)->withHeader('X-Tenant', 'test-tenant')
            ->post(route('access.roles.store'), [
                'name' => 'Audited Role',
                'level' => 60,
            ]);

        $role = Role::where('name', 'Audited Role')->firstOrFail();
        $store->assertRedirect(route('access.roles.show', $role));

        $this->assertDatabaseHas('access_audit_logs', [
            'actor_id' => $this->tenantOwner->id,
            'action' => 'role.created',
            'subject_type' => 'role',
            'subject_id' => $role->id,
        ]);

        $permission = Permission::first();

        $this->actingAs($this->tenantOwner)->withHeader('X-Tenant', 'test-tenant')
            ->put(route('access.roles.permissions.update', $role), [
                'grants' => [
                    $permission->id => ['tenant' => '1'],
                ],
            ]);

        $this->assertDatabaseHas('access_audit_logs', [
            'actor_id' => $this->tenantOwner->id,
            'action' => 'role.permissions.updated',
            'subject_type' => 'role',
            'subject_id' => $role->id,
        ]);

        $salesExecutive = Role::query()->whereNull('tenant_id')->where('slug', 'sales_executive')->firstOrFail();

        $store = $this->actingAs($this->tenantOwner)->withHeader('X-Tenant', 'test-tenant')
            ->post(route('access.users.store'), [
                'name' => 'Logged User',
                'email' => 'logged@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role_ids' => [$salesExecutive->id],
            ]);

        $newUser = User::where('email', 'logged@example.com')->firstOrFail();
        $store->assertRedirect(route('access.users.index'));

        $this->assertDatabaseHas('access_audit_logs', [
            'actor_id' => $this->tenantOwner->id,
            'target_user_id' => $newUser->id,
            'action' => 'user.roles.updated',
        ]);

        $this->assertEquals(3, AccessAuditLog::where('tenant_id', $this->tenant->id)->count());
    }

    /** @test */
    public function tenant_owner_cannot_assign_the_super_admin_role_to_a_new_user(): void
    {
        $superAdminRole = Role::query()->whereNull('tenant_id')->where('slug', 'super_admin')->firstOrFail();

        $response = $this->actingAs($this->tenantOwner)->withHeader('X-Tenant', 'test-tenant')
            ->post(route('access.users.store'), [
                'name' => 'Sneaky',
                'email' => 'sneaky@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role_id' => $superAdminRole->id,
            ]);

        $response->assertForbidden();
        $this->assertNull(User::where('email', 'sneaky@example.com')->first());
    }

    /** @test */
    public function tenant_owner_can_create_a_custom_tenant_role_and_edit_its_permissions_freely(): void
    {
        $store = $this->actingAs($this->tenantOwner)->withHeader('X-Tenant', 'test-tenant')
            ->post(route('access.roles.store'), [
                'name' => 'Warehouse Supervisor',
                'level' => 45,
            ]);

        $role = Role::where('name', 'Warehouse Supervisor')->firstOrFail();
        $store->assertRedirect(route('access.roles.show', $role));
        $this->assertEquals($this->tenant->id, $role->tenant_id);
        $this->assertFalse($role->is_system);

        $permission = Permission::first();

        $update = $this->actingAs($this->tenantOwner)->withHeader('X-Tenant', 'test-tenant')
            ->put(route('access.roles.permissions.update', $role), [
                'grants' => [
                    $permission->id => ['tenant' => '1'],
                ],
            ]);

        $update->assertRedirect(route('access.roles.show', $role));
        $this->assertDatabaseHas('role_permissions', [
            'role_id' => $role->id,
            'permission_id' => $permission->id,
            'scope' => RolePermission::SCOPE_TENANT,
        ]);
    }

    /** @test */
    public function tenant_owner_is_forbidden_editing_a_system_roles_permissions(): void
    {
        $systemRole = Role::query()->whereNull('tenant_id')->where('slug', 'sales_executive')->firstOrFail();

        $response = $this->actingAs($this->tenantOwner)->withHeader('X-Tenant', 'test-tenant')
            ->put(route('access.roles.permissions.update', $systemRole), [
                'grants' => [],
            ]);

        $response->assertForbidden();
    }

    /** @test */
    public function super_admin_can_edit_a_system_roles_permissions(): void
    {
        $systemRole = Role::query()->whereNull('tenant_id')->where('slug', 'sales_executive')->firstOrFail();
        $permission = Permission::where('name', 'crm.leads.view')->firstOrFail();

        $response = $this->actingAs($this->superAdmin)->withHeader('X-Tenant', 'test-tenant')
            ->put(route('access.roles.permissions.update', $systemRole), [
                'grants' => [
                    $permission->id => ['tenant' => '1'],
                ],
            ]);

        $response->assertRedirect(route('access.roles.show', $systemRole));
        $this->assertDatabaseHas('role_permissions', [
            'role_id' => $systemRole->id,
            'permission_id' => $permission->id,
            'scope' => RolePermission::SCOPE_TENANT,
        ]);
    }

    /** @test */
    public function granting_platform_scope_on_a_custom_tenant_role_is_rejected(): void
    {
        $role = Role::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Custom Role',
            'slug' => 'custom-role',
            'level' => 100,
            'is_system' => false,
        ]);

        $permission = Permission::first();

        $this->actingAs($this->superAdmin)->withHeader('X-Tenant', 'test-tenant')
            ->put(route('access.roles.permissions.update', $role), [
                'grants' => [
                    $permission->id => ['platform' => '1'],
                ],
            ]);

        $this->assertDatabaseMissing('role_permissions', [
            'role_id' => $role->id,
            'permission_id' => $permission->id,
            'scope' => RolePermission::SCOPE_PLATFORM,
        ]);
    }

    /** @test */
    public function unchecking_a_previously_granted_cell_removes_the_role_permission_row(): void
    {
        $role = Role::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Custom Role',
            'slug' => 'custom-role-2',
            'level' => 100,
            'is_system' => false,
        ]);

        $permission = Permission::first();

        RolePermission::create([
            'role_id' => $role->id,
            'permission_id' => $permission->id,
            'scope' => RolePermission::SCOPE_TENANT,
        ]);

        $this->actingAs($this->tenantOwner)->withHeader('X-Tenant', 'test-tenant')
            ->put(route('access.roles.permissions.update', $role), [
                'grants' => [],
            ]);

        $this->assertDatabaseMissing('role_permissions', [
            'role_id' => $role->id,
            'permission_id' => $permission->id,
        ]);
    }
}
