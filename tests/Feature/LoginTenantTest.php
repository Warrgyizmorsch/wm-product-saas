<?php

namespace Tests\Feature;

use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTenantTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $demo;
    private Tenant $production;
    private User $productionOwner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->demo = Tenant::create(['name' => 'Demo Tenant', 'slug' => 'demo', 'status' => 'active', 'plan' => 'enterprise']);
        $this->production = Tenant::create(['name' => 'Production Tenant', 'slug' => 'production-tenant', 'status' => 'active', 'plan' => 'enterprise']);
        $this->seed(RbacSeeder::class);

        $ownerRole = Role::query()->whereNull('tenant_id')->where('slug', 'tenant_owner')->firstOrFail();

        $this->productionOwner = User::create([
            'tenant_id' => $this->production->id,
            'name' => 'Production Owner',
            'email' => 'production@example.com',
            'password' => bcrypt('secret-pass'),
            'role_id' => $ownerRole->id,
        ]);
        UserRole::create(['user_id' => $this->productionOwner->id, 'role_id' => $ownerRole->id, 'tenant_id' => $this->production->id]);
    }

    /**
     * Simulates the local shared host, where every visitor starts on the
     * fallback tenant (here: demo) before signing in.
     */
    private function onFallbackTenant(): self
    {
        return $this->withSession(['tenant_slug' => 'demo']);
    }

    /** @test */
    public function signing_in_moves_the_session_to_the_users_own_tenant(): void
    {
        $this->onFallbackTenant()
            ->post(route('login.store'), ['email' => 'production@example.com', 'password' => 'secret-pass'])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('tenant_slug', 'production-tenant');

        $this->assertAuthenticatedAs($this->productionOwner);
    }

    /** @test */
    public function a_tenant_owner_is_not_a_platform_admin(): void
    {
        $this->assertFalse(app(\App\Services\Access\AccessService::class)
            ->allows($this->productionOwner, 'platform.tenants.manage'));
    }

    /** @test */
    public function a_wrong_password_does_not_switch_tenant_or_sign_in(): void
    {
        $this->onFallbackTenant()
            ->post(route('login.store'), ['email' => 'production@example.com', 'password' => 'wrong-pass'])
            ->assertSessionHasErrors('email')
            ->assertSessionHas('tenant_slug', 'demo');

        $this->assertGuest();
    }

    /** @test */
    public function the_same_email_in_a_second_tenant_signs_into_that_tenants_account(): void
    {
        // Created after the production owner, so it has the higher id — the
        // lookup used to return the owner and reject this valid sign-in.
        $demoAccount = User::create([
            'tenant_id' => $this->demo->id,
            'name' => 'Demo Account',
            'email' => 'production@example.com',
            'password' => bcrypt('demo-pass'),
        ]);

        $this->onFallbackTenant()
            ->post(route('login.store'), ['email' => 'production@example.com', 'password' => 'demo-pass'])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('tenant_slug', 'demo');

        $this->assertAuthenticatedAs($demoAccount);
    }
}
