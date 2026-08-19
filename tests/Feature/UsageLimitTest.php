<?php

namespace Tests\Feature;

use App\Domains\Platform\Models\Plan;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UsageLimitTest extends TestCase
{
    use RefreshDatabase;

    private function tenantOwner(Tenant $tenant, string $email): User
    {
        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => $email,
            'email' => $email,
            'password' => bcrypt('password'),
        ]);

        $role = Role::query()->whereNull('tenant_id')->where('slug', 'tenant_owner')->firstOrFail();

        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'tenant_id' => $tenant->id]);
        $user->forceFill(['role_id' => $role->id])->save();

        return $user;
    }

    private function salesExecutiveRoleId(): int
    {
        return Role::query()->whereNull('tenant_id')->where('slug', 'sales_executive')->firstOrFail()->id;
    }

    /** @test */
    public function adding_a_user_beyond_the_tenants_own_max_users_is_blocked(): void
    {
        $tenant = Tenant::create([
            'name' => 'Capped Tenant', 'slug' => 'capped-tenant', 'status' => 'active',
            'plan' => 'starter', 'max_users' => 1,
        ]);
        $this->seed(RbacSeeder::class);
        $owner = $this->tenantOwner($tenant, 'owner@capped.test');

        $response = $this->actingAs($owner)->withHeader('X-Tenant', 'capped-tenant')
            ->post(route('access.users.store'), [
                'name' => 'Second User',
                'email' => 'second@capped.test',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role_id' => $this->salesExecutiveRoleId(),
            ]);

        $response->assertRedirect(route('access.users.create'));
        $response->assertSessionHas('error');
        $this->assertNull(User::where('email', 'second@capped.test')->first());
    }

    /** @test */
    public function a_tenant_without_its_own_max_users_falls_back_to_the_linked_plans_limit(): void
    {
        $plan = Plan::query()->where('slug', 'starter')->firstOrFail();
        $plan->update(['max_users' => 1]);

        $tenant = Tenant::create([
            'name' => 'Plan Capped Tenant', 'slug' => 'plan-capped-tenant', 'status' => 'active',
            'plan' => 'starter', 'plan_id' => $plan->id, 'max_users' => null,
        ]);
        $this->seed(RbacSeeder::class);
        $owner = $this->tenantOwner($tenant, 'owner@plancapped.test');

        $response = $this->actingAs($owner)->withHeader('X-Tenant', 'plan-capped-tenant')
            ->post(route('access.users.store'), [
                'name' => 'Second User',
                'email' => 'second@plancapped.test',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role_id' => $this->salesExecutiveRoleId(),
            ]);

        $response->assertRedirect(route('access.users.create'));
        $this->assertNull(User::where('email', 'second@plancapped.test')->first());
    }

    /** @test */
    public function a_tenant_with_no_limit_anywhere_can_add_users_freely(): void
    {
        $tenant = Tenant::create([
            'name' => 'Unlimited Tenant', 'slug' => 'unlimited-tenant', 'status' => 'active',
            'plan' => 'enterprise', 'max_users' => null,
        ]);
        $this->seed(RbacSeeder::class);
        $owner = $this->tenantOwner($tenant, 'owner@unlimited.test');

        $response = $this->actingAs($owner)->withHeader('X-Tenant', 'unlimited-tenant')
            ->post(route('access.users.store'), [
                'name' => 'Second User',
                'email' => 'second@unlimited.test',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'role_id' => $this->salesExecutiveRoleId(),
            ]);

        $response->assertRedirect(route('access.users.index'));
        $this->assertNotNull(User::where('email', 'second@unlimited.test')->first());
    }

    /** @test */
    public function the_platform_usage_console_shows_every_tenants_seat_consumption(): void
    {
        $tenantA = Tenant::create([
            'name' => 'Tenant A', 'slug' => 'usage-tenant-a', 'status' => 'active',
            'plan' => 'starter', 'max_users' => 5,
        ]);
        $tenantB = Tenant::create([
            'name' => 'Tenant B', 'slug' => 'usage-tenant-b', 'status' => 'active',
            'plan' => 'enterprise', 'max_users' => null,
        ]);
        $this->seed(RbacSeeder::class);

        $superAdmin = User::create([
            'tenant_id' => $tenantA->id, 'name' => 'super@usage.test', 'email' => 'super@usage.test',
            'password' => bcrypt('password'),
        ]);
        $role = Role::query()->whereNull('tenant_id')->where('slug', 'super_admin')->firstOrFail();
        UserRole::create(['user_id' => $superAdmin->id, 'role_id' => $role->id, 'tenant_id' => $tenantA->id]);
        $superAdmin->forceFill(['role_id' => $role->id])->save();

        $response = $this->actingAs($superAdmin)->withHeader('X-Tenant', 'usage-tenant-a')
            ->get(route('platform.usage.index'));

        $response->assertOk();
        $response->assertSee('Tenant A');
        $response->assertSee('Tenant B');
        $response->assertSee('1 / 5');
    }

    /** @test */
    public function a_non_admin_role_without_the_usage_permission_cannot_see_the_usage_console(): void
    {
        $tenant = Tenant::create([
            'name' => 'Scoped Tenant', 'slug' => 'usage-scoped-tenant', 'status' => 'active', 'plan' => 'starter',
        ]);
        $this->seed(RbacSeeder::class);

        $salesExec = User::create([
            'tenant_id' => $tenant->id, 'name' => 'sales@usagescoped.test', 'email' => 'sales@usagescoped.test',
            'password' => bcrypt('password'),
        ]);
        UserRole::create(['user_id' => $salesExec->id, 'role_id' => $this->salesExecutiveRoleId(), 'tenant_id' => $tenant->id]);
        $salesExec->forceFill(['role_id' => $this->salesExecutiveRoleId()])->save();

        $this->actingAs($salesExec)->withHeader('X-Tenant', 'usage-scoped-tenant')
            ->get(route('platform.usage.index'))
            ->assertForbidden();
    }
}
