<?php

namespace Tests\Feature\Access;

use App\Models\Tenant;
use App\Models\User;
use App\Services\Access\AccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The legacy users.role text ('admin' / 'super_admin') used to make any
 * account a platform admin. It now only does so for accounts with no tenant;
 * a tenant-bound account keeps full access inside its own tenant only.
 */
class LegacyAdminRoleTextTest extends TestCase
{
    use RefreshDatabase;

    private AccessService $access;
    private Tenant $tenantA;
    private Tenant $tenantB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->access = app(AccessService::class);
        $this->tenantA = Tenant::create(['name' => 'Tenant A', 'slug' => 'tenant-a', 'status' => 'active', 'plan' => 'enterprise']);
        $this->tenantB = Tenant::create(['name' => 'Tenant B', 'slug' => 'tenant-b', 'status' => 'active', 'plan' => 'enterprise']);
    }

    private function userWithRoleText(?Tenant $tenant, string $role): User
    {
        return User::create([
            'tenant_id' => $tenant?->id,
            'name' => $role,
            'email' => $role.'-'.($tenant?->slug ?? 'platform').'@example.com',
            'password' => bcrypt('password'),
            'role' => $role,
        ]);
    }

    public function test_tenant_bound_admin_text_is_not_a_platform_admin(): void
    {
        foreach (['admin', 'super_admin'] as $role) {
            $user = $this->userWithRoleText($this->tenantA, $role);

            $this->assertFalse($this->access->allows($user, 'platform.tenants.manage'), $role);
        }
    }

    public function test_tenant_bound_admin_text_keeps_full_access_in_its_own_tenant(): void
    {
        $admin = $this->userWithRoleText($this->tenantA, 'admin');

        $this->assertTrue($this->access->allows($admin, 'crm.leads.view'));
        $this->assertTrue($this->access->allows($admin, 'crm.leads.view', ['tenant_id' => $this->tenantA->id]));
    }

    public function test_tenant_bound_admin_text_gets_nothing_in_another_tenant(): void
    {
        $admin = $this->userWithRoleText($this->tenantA, 'admin');

        $this->assertFalse($this->access->allows($admin, 'crm.leads.view', ['tenant_id' => $this->tenantB->id]));
    }

    public function test_tenantless_super_admin_text_is_still_a_platform_admin(): void
    {
        $platformAdmin = $this->userWithRoleText(null, 'super_admin');

        $this->assertTrue($this->access->allows($platformAdmin, 'platform.tenants.manage'));
    }
}
