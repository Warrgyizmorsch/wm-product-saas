<?php

namespace Tests\Feature;

use App\Core\Tenant\TenantResolver;
use App\Domains\Platform\Services\PlanFeatureGate;
use App\Domains\Platform\Services\UsageLimitService;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SingleClientModeTest extends TestCase
{
    use RefreshDatabase;

    private const ENV = [
        'SINGLE_CLIENT_SLUG' => 'bharat-ceramics',
        'SINGLE_CLIENT_HIDDEN_MODULES' => 'production,projects',
    ];

    protected function setUp(): void
    {
        // Routes are registered at boot, so the env must be in place before the app is created.
        foreach (self::ENV as $key => $value) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $_SERVER[$key] = $value;
        }

        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        foreach (array_keys(self::ENV) as $key) {
            putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);
        }
    }

    private function clientTenant(array $attributes = []): Tenant
    {
        return Tenant::create([
            'name' => 'Bharat Ceramics', 'slug' => 'bharat-ceramics', 'status' => 'active', 'plan' => 'single-client',
            ...$attributes,
        ]);
    }

    public function test_every_request_resolves_to_the_pinned_tenant(): void
    {
        $client = $this->clientTenant();
        Tenant::create(['name' => 'Other', 'slug' => 'other', 'status' => 'active', 'plan' => 'enterprise']);

        $request = Request::create('https://other.example.com/');
        $request->headers->set(config('tenancy.header'), 'other');

        $this->assertTrue($client->is(app(TenantResolver::class)->resolve($request)));
    }

    public function test_all_modules_except_hidden_ones_are_allowed(): void
    {
        $modules = $this->clientTenant()->planModules();

        $this->assertContains('accounting', $modules);
        $this->assertContains('crm', $modules);
        $this->assertNotContains('production', $modules);
        $this->assertNotContains('projects', $modules);
    }

    public function test_billing_plan_and_usage_gates_are_off(): void
    {
        $tenant = $this->clientTenant([
            'subscription_status' => Tenant::SUBSCRIPTION_SUSPENDED,
            'max_users' => 1,
        ]);

        $this->assertFalse($tenant->isBillingLocked());
        $this->assertNull(app(UsageLimitService::class)->maxUsers($tenant));
        $this->assertTrue(app(PlanFeatureGate::class)->allows('accounting.journals.create.tenant', $tenant->id));
    }

    public function test_saas_only_routes_are_not_registered(): void
    {
        foreach (['tenant.switch', 'webhooks.razorpay', 'platform.tenants.index', 'platform.plans.index',
            'platform.subscription.index', 'platform.billing.checkout', 'platform.modules.checkout'] as $name) {
            $this->assertFalse(Route::has($name), "{$name} should not be registered");
        }

        $this->assertTrue(Route::has('platform.currencies.index'));
        $this->assertTrue(Route::has('dashboard'));
    }

    public function test_hidden_module_routes_are_blocked(): void
    {
        $tenant = $this->clientTenant();
        $user = User::create([
            'tenant_id' => $tenant->id, 'name' => 'Owner', 'email' => 'owner@bharat.test', 'password' => bcrypt('password'),
        ]);

        $this->actingAs($user)->get(route('production.dashboard'))->assertForbidden();
    }

    public function test_install_command_creates_the_tenant_once_and_is_safe_to_rerun(): void
    {
        $this->artisan('client:install', [
            '--name' => 'Bharat Ceramics',
            '--owner-name' => 'Owner',
            '--owner-email' => 'owner@bharat.test',
            '--owner-password' => 'secret-password',
        ])->assertSuccessful();

        $tenant = Tenant::query()->where('slug', 'bharat-ceramics')->sole();
        $this->assertSame(Tenant::STATUS_ACTIVE, $tenant->status);
        $this->assertDatabaseHas('users', ['email' => 'owner@bharat.test', 'tenant_id' => $tenant->id]);

        $this->artisan('client:install')->assertSuccessful();
        $this->assertSame(1, Tenant::query()->where('slug', 'bharat-ceramics')->count());
    }
}
