<?php

namespace Tests\Feature;

use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiLoginTenantTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $demo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->demo = Tenant::create(['name' => 'Demo Tenant', 'slug' => 'demo', 'status' => 'active', 'plan' => 'enterprise']);
        $this->seed(RbacSeeder::class);

        $superAdmin = Role::query()->whereNull('tenant_id')->where('slug', 'super_admin')->firstOrFail();

        $user = User::create([
            'tenant_id' => $this->demo->id,
            'name' => 'Platform Admin',
            'email' => 'platform@example.com',
            'password' => bcrypt('secret-pass'),
            'role_id' => $superAdmin->id,
        ]);
        UserRole::create(['user_id' => $user->id, 'role_id' => $superAdmin->id, 'tenant_id' => $this->demo->id]);
    }

    private function login(?string $tenant, string $password = 'secret-pass')
    {
        return $this->withHeaders($tenant === null ? [] : ['X-Tenant' => $tenant])
            ->postJson('/api/auth/login', ['email' => 'platform@example.com', 'password' => $password]);
    }

    /** @test */
    public function an_unknown_tenant_header_is_refused_even_for_a_platform_admin(): void
    {
        $this->login('no-such-tenant')->assertNotFound()->assertJson(['message' => 'Tenant not found.']);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    /** @test */
    public function an_unknown_tenant_header_is_refused_before_the_password_is_checked(): void
    {
        $this->login('no-such-tenant', 'wrong-pass')->assertNotFound();
    }

    /** @test */
    public function a_suspended_tenant_header_is_refused(): void
    {
        Tenant::create(['name' => 'Gone', 'slug' => 'gone', 'status' => 'suspended', 'plan' => 'enterprise']);

        $this->login('gone')->assertNotFound();
    }

    /** @test */
    public function a_known_tenant_header_still_signs_in(): void
    {
        $this->login('demo')->assertOk()->assertJsonStructure(['token']);
    }
}
