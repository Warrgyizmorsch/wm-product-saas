<?php

namespace Tests\Feature\QA\Platform;

use App\Models\Access\Role;
use App\Services\Access\AccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\QA\Concerns\BuildsQaTenants;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * AccessService::decide() treats any assigned role whose slug is 'admin' or
 * 'super_admin' as a platform administrator. Roles are tenant-creatable, and
 * the slug is only unique *within* a tenant, so these tests make sure a tenant
 * owner can't mint a platform-admin role for themselves by choosing that name.
 */
class RoleSlugEscalationTest extends TestCase
{
    use BuildsQaTenants;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootTwoTenants();
    }

    public static function reservedSlugs(): array
    {
        return [
            'slug admin' => ['Admin', 'admin'],
            'slug super_admin' => ['Super Admin', 'super_admin'],
            'name Admin, slug derived' => ['Admin', null],
        ];
    }

    #[DataProvider('reservedSlugs')]
    public function test_a_tenant_role_with_a_reserved_slug_never_grants_platform_rights(string $name, ?string $slug): void
    {
        $owner = $this->userWithRole($this->tenantA, 'tenant_owner');

        $this->as($owner, $this->tenantA)->post(route('access.roles.store'), array_filter([
            'name' => $name,
            'slug' => $slug,
        ]));

        $role = Role::query()->where('tenant_id', $this->tenantA->id)->latest('id')->first();

        if ($role !== null) {
            // Assign the tenant's own role to themselves through the normal screen.
            $ownRoleId = $owner->role_id;
            $this->as($owner, $this->tenantA)->put(route('access.users.update', $owner), [
                'name' => $owner->name,
                'email' => $owner->email,
                'role_ids' => [$ownRoleId, $role->id],
            ]);
        }

        $owner->refresh();
        $access = app(AccessService::class);

        $this->assertFalse(
            $access->allows($owner, 'platform.tenants.manage'),
            "A tenant-created role '{$name}' made its holder a platform admin (platform.tenants.manage)."
        );
        $this->assertFalse(
            $access->allows($owner, 'platform.payment_gateway.manage'),
            "A tenant-created role '{$name}' exposed the platform payment-gateway keys."
        );

        $this->assertDenied($this->as($owner, $this->tenantA)->get(route('platform.tenants.index')), 'tenant list');
        $this->assertDenied($this->as($owner, $this->tenantA)->get(route('platform.payment-gateway.index')), 'gateway settings');
        $this->assertDenied($this->as($owner, $this->tenantA)->get(route('platform.usage.index')), 'usage console');
    }

    public function test_a_tenant_role_named_admin_does_not_open_other_tenants_workspaces(): void
    {
        $owner = $this->userWithRole($this->tenantA, 'tenant_owner', 'climber@qa.test');
        $this->userWithRole($this->tenantB, 'accountant', 'victim.cfo@tenant-b.test');

        $this->as($owner, $this->tenantA)->post(route('access.roles.store'), ['name' => 'Admin', 'slug' => 'admin']);
        $role = Role::query()->where('tenant_id', $this->tenantA->id)->where('slug', 'admin')->first();
        if ($role !== null) {
            $this->as($owner, $this->tenantA)->put(route('access.users.update', $owner), [
                'name' => $owner->name, 'email' => $owner->email, 'role_ids' => [$owner->role_id, $role->id],
            ]);
        }

        // Fresh session, real sign-in, then aim the same session at tenant B.
        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $this->withHeader('X-Tenant', $this->tenantA->slug)
            ->post(route('login.store'), ['email' => 'climber@qa.test', 'password' => 'password']);
        $this->app['auth']->forgetGuards();

        $response = $this->withHeader('X-Tenant', $this->tenantB->slug)->get(route('dashboard'));
        $this->assertNotSame(200, $response->getStatusCode(), 'Tenant A owner with a self-made "admin" role was let into tenant B\'s workspace.');

        $this->app['auth']->forgetGuards();
        $users = $this->withHeader('X-Tenant', $this->tenantB->slug)->get(route('access.users.index'));
        $this->assertStringNotContainsString('victim.cfo@tenant-b.test', (string) $users->getContent(), 'Tenant B\'s user list leaked to tenant A\'s owner.');
    }

    public function test_a_tenant_owner_cannot_edit_another_tenant_through_the_platform_screens(): void
    {
        $owner = $this->userWithRole($this->tenantA, 'tenant_owner');

        $this->assertDenied($this->as($owner, $this->tenantA)->get(route('platform.tenants.edit', $this->tenantB)));
        $this->assertDenied($this->as($owner, $this->tenantA)->patch(route('platform.tenants.status', $this->tenantB), ['status' => 'suspended']));

        $this->assertSame('active', $this->tenantB->fresh()->status);
    }

    public function test_a_tenant_owner_cannot_assign_a_role_that_belongs_to_another_tenant(): void
    {
        $ownerA = $this->userWithRole($this->tenantA, 'tenant_owner');
        $foreignRole = Role::create([
            'tenant_id' => $this->tenantB->id,
            'name' => 'B Finance Lead',
            'slug' => 'b-finance-lead',
            'level' => 50,
            'is_system' => false,
        ]);

        $response = $this->as($ownerA, $this->tenantA)->post(route('access.users.store'), [
            'name' => 'Smuggled',
            'email' => 'smuggled@qa.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role_ids' => [$foreignRole->id],
        ]);

        $this->assertNotContains($response->getStatusCode(), [200, 201], 'Foreign role was accepted');
        $this->assertDatabaseMissing('user_roles', ['role_id' => $foreignRole->id]);
    }

    public function test_a_tenant_owner_cannot_grant_themselves_the_system_super_admin_role_on_edit(): void
    {
        $owner = $this->userWithRole($this->tenantA, 'tenant_owner');
        $superAdmin = Role::query()->whereNull('tenant_id')->where('slug', 'super_admin')->firstOrFail();

        $this->as($owner, $this->tenantA)->put(route('access.users.update', $owner), [
            'name' => $owner->name,
            'email' => $owner->email,
            'role_ids' => [$owner->role_id, $superAdmin->id],
        ]);

        $this->assertDatabaseMissing('user_roles', ['user_id' => $owner->id, 'role_id' => $superAdmin->id]);
        $this->assertNotSame($superAdmin->id, $owner->fresh()->role_id);
    }
}
