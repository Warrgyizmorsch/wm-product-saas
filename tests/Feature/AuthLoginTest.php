<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthLoginTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
        ]);
    }

    /** @test */
    public function guest_is_redirected_to_login_from_a_protected_route()
    {
        $response = $this->withHeader('X-Tenant', 'test-tenant')
            ->get(route('dashboard'));

        $response->assertRedirect(route('login'));
    }

    /** @test */
    public function valid_credentials_log_the_user_in_and_redirect_to_dashboard()
    {
        $response = $this->withHeader('X-Tenant', 'test-tenant')
            ->post(route('login.store'), [
                'email' => 'test@example.com',
                'password' => 'password',
            ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($this->user);
    }

    /** @test */
    public function invalid_credentials_are_rejected()
    {
        $response = $this->withHeader('X-Tenant', 'test-tenant')
            ->post(route('login.store'), [
                'email' => 'test@example.com',
                'password' => 'wrong-password',
            ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    /** @test */
    public function a_regular_user_stays_authenticated_on_a_second_request_after_login()
    {
        // Regression test: TenantAwareUserProvider must resolve the current
        // tenant independently of TenantContext, since the app's `tenant`
        // middleware is not guaranteed to run before Laravel's own auth
        // middleware resolves the session user. A plain user (no
        // platform-scope permission to fall back on) previously got
        // silently logged out on their very next request after login.
        $this->withHeader('X-Tenant', 'test-tenant')
            ->post(route('login.store'), [
                'email' => 'test@example.com',
                'password' => 'password',
            ])->assertRedirect(route('dashboard'));

        $response = $this->withHeader('X-Tenant', 'test-tenant')
            ->get(route('dashboard'));

        $response->assertOk();
        $this->assertAuthenticatedAs($this->user);
    }

    /** @test */
    public function logout_clears_the_session_and_reblocks_protected_routes()
    {
        $this->actingAs($this->user)
            ->withHeader('X-Tenant', 'test-tenant')
            ->post(route('logout'));

        $this->assertGuest();

        $response = $this->withHeader('X-Tenant', 'test-tenant')
            ->get(route('dashboard'));

        $response->assertRedirect(route('login'));
    }
}
