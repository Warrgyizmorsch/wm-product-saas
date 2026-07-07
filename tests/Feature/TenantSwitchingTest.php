<?php

namespace Tests\Feature;

use App\Models\Access\Permission;
use App\Models\Access\Role;
use App\Models\Access\RolePermission;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantSwitchingTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;
    private Role $platformRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::create([
            'name' => 'Tenant A', 'slug' => 'tenant-a', 'status' => 'active', 'plan' => 'enterprise',
        ]);
        $this->tenantB = Tenant::create([
            'name' => 'Tenant B', 'slug' => 'tenant-b', 'status' => 'active', 'plan' => 'enterprise',
        ]);

        $permission = Permission::create([
            'name' => 'platform.tenants.manage', 'module' => 'platform', 'entity' => 'tenants', 'action' => 'manage',
        ]);

        $this->platformRole = Role::create(['name' => 'Super Admin', 'slug' => 'super_admin', 'level' => 1]);

        RolePermission::create([
            'role_id' => $this->platformRole->id,
            'permission_id' => $permission->id,
            'scope' => RolePermission::SCOPE_PLATFORM,
        ]);
    }

    /** @test */
    public function a_platform_admin_can_switch_into_a_tenant_they_do_not_belong_to()
    {
        $admin = User::create([
            'tenant_id' => $this->tenantA->id,
            'role_id' => $this->platformRole->id,
            'name' => 'Platform Admin',
            'email' => 'admin@platform.test',
            'password' => bcrypt('password'),
        ]);

        $response = $this->actingAs($admin)
            ->withHeader('X-Tenant', 'tenant-a')
            ->get(route('tenant.switch', $this->tenantB->slug));

        $response->assertRedirect();
        $response->assertSessionHas('tenant_slug', 'tenant-b');
    }

    /** @test */
    public function a_tenant_bound_user_cannot_switch_into_a_different_tenant()
    {
        $user = User::create([
            'tenant_id' => $this->tenantA->id,
            'name' => 'Regular User',
            'email' => 'user@tenant-a.test',
            'password' => bcrypt('password'),
        ]);

        $response = $this->actingAs($user)
            ->withHeader('X-Tenant', 'tenant-a')
            ->get(route('tenant.switch', $this->tenantB->slug));

        $response->assertForbidden();
    }

    /** @test */
    public function a_platform_admin_stays_authenticated_and_scoped_after_switching_tenant()
    {
        $admin = User::create([
            'tenant_id' => $this->tenantA->id,
            'role_id' => $this->platformRole->id,
            'name' => 'Platform Admin',
            'email' => 'admin@platform.test',
            'password' => bcrypt('password'),
        ]);

        // Real session-based login (not actingAs) so the next request must
        // re-resolve the user from the session id via our custom provider,
        // exactly like a fresh page load in a browser would.
        $this->withHeader('X-Tenant', 'tenant-a')
            ->post(route('login.store'), [
                'email' => 'admin@platform.test',
                'password' => 'password',
            ])->assertRedirect(route('dashboard'));

        $this->withHeader('X-Tenant', 'tenant-a')
            ->get(route('tenant.switch', $this->tenantB->slug))
            ->assertRedirect();

        // No X-Tenant header this time: resolution falls back to the session's
        // 'tenant_slug' (tenant-b) set by the switch above. Under the plain
        // Eloquent provider this user would be scoped out and appear logged out.
        $response = $this->get(route('dashboard'));

        $response->assertOk();
        $this->assertAuthenticatedAs($admin);
    }
}
