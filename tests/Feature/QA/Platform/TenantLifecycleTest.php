<?php

namespace Tests\Feature\QA\Platform;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\QA\Concerns\BuildsQaTenants;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Platform operator flows for tenants: create with an owner, validation
 * edges, status changes and what each status does to that tenant's users.
 */
class TenantLifecycleTest extends TestCase
{
    use BuildsQaTenants;
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootTwoTenants();
        $this->admin = $this->platformAdmin();
    }

    private function validTenant(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Mewar Textiles',
            'slug' => 'mewar-textiles',
            // The HTML form always posts these keys (as empty strings); omit
            // one and TenantService::payload() dies on an undefined index.
            'domain' => '',
            'plan_id' => '',
            'max_users' => '',
            'max_storage_mb' => '',
            'trial_ends_at' => '',
            'plan_started_at' => '',
            'plan_expires_at' => '',
            'display_name' => '',
            'branch' => '',
            'currency' => '',
            'financial_year' => '',
            'billing_email' => 'accounts@mewar.test',
            'status' => 'active',
            'plan' => 'enterprise',
            'subscription_status' => 'active',
            'timezone' => 'Asia/Kolkata',
            'locale' => 'en',
            'owner_name' => 'Mewar Owner',
            'owner_email' => 'owner@mewar.test',
            'owner_password' => 'password123',
            'owner_password_confirmation' => 'password123',
        ], $overrides);
    }

    private function asAdmin(): static
    {
        return $this->as($this->admin, $this->tenantA);
    }

    public function test_platform_admin_creates_a_tenant_with_its_owner_and_default_books(): void
    {
        $this->asAdmin()->post(route('platform.tenants.store'), $this->validTenant())
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $tenant = Tenant::query()->where('slug', 'mewar-textiles')->firstOrFail();
        $owner = User::withoutGlobalScopes()->where('email', 'owner@mewar.test')->firstOrFail();

        $this->assertSame($tenant->id, (int) $owner->tenant_id);
        $this->assertSame($owner->id, (int) $tenant->owner_user_id);
        $this->assertNotNull($tenant->onboarded_at);
        $this->assertNotSame('password123', $owner->password, 'Owner password stored in plain text');

        // Provisioning must write only into the new tenant.
        $this->assertGreaterThan(0, \App\Domains\Accounting\Models\ChartOfAccount::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
    }

    public static function invalidTenants(): array
    {
        return [
            'missing name' => [['name' => ''], 'name'],
            'slug with spaces' => [['slug' => 'bad slug'], 'slug'],
            'unknown status' => [['status' => 'zombie'], 'status'],
            'unknown plan' => [['plan' => 'galactic'], 'plan'],
            'unknown subscription status' => [['subscription_status' => 'maybe'], 'subscription_status'],
            'bad billing email' => [['billing_email' => 'not-an-email'], 'billing_email'],
            'zero max users' => [['max_users' => 0], 'max_users'],
            'expiry before start' => [['plan_started_at' => '2026-10-01', 'plan_expires_at' => '2026-09-01'], 'plan_expires_at'],
            'owner password too short' => [['owner_password' => 'short', 'owner_password_confirmation' => 'short'], 'owner_password'],
            'owner password mismatch' => [['owner_password_confirmation' => 'different123'], 'owner_password'],
            'owner email without password' => [['owner_password' => null, 'owner_password_confirmation' => null], 'owner_password'],
            'logo that is not an image' => [['logo_full' => 'IGNORED'], null],
        ];
    }

    #[DataProvider('invalidTenants')]
    public function test_invalid_tenant_input_is_rejected_and_nothing_is_created(array $overrides, ?string $field): void
    {
        if (array_key_exists('logo_full', $overrides)) {
            $overrides['logo_full'] = \Illuminate\Http\UploadedFile::fake()->create('evil.php', 10, 'application/x-php');
            $field = 'logo_full';
        }

        $this->asAdmin()->post(route('platform.tenants.store'), $this->validTenant($overrides))
            ->assertSessionHasErrors($field);

        $this->assertDatabaseMissing('tenants', ['slug' => $overrides['slug'] ?? 'mewar-textiles']);
    }

    public function test_slug_and_domain_must_be_unique_across_tenants(): void
    {
        $this->asAdmin()->post(route('platform.tenants.store'), $this->validTenant(['slug' => $this->tenantB->slug]))
            ->assertSessionHasErrors('slug');

        $this->tenantB->forceFill(['domain' => 'b.erp.test'])->save();
        $this->asAdmin()->post(route('platform.tenants.store'), $this->validTenant(['domain' => 'b.erp.test']))
            ->assertSessionHasErrors('domain');
    }

    public function test_the_owner_email_cannot_take_over_an_existing_users_account(): void
    {
        $existing = $this->userWithRole($this->tenantB, 'accountant', 'taken@qa.test');
        $originalHash = $existing->password;

        $this->asAdmin()->post(route('platform.tenants.store'), $this->validTenant(['owner_email' => 'taken@qa.test']));

        $existing->refresh();
        $this->assertSame($this->tenantB->id, (int) $existing->tenant_id, 'Existing user was moved into the new tenant');
        $this->assertSame($originalHash, $existing->password, 'Existing user\'s password was overwritten');
    }

    public function test_platform_admin_suspends_a_tenant_and_its_users_are_shut_out(): void
    {
        $userB = $this->userWithRole($this->tenantB, 'accountant');
        $userA = $this->userWithRole($this->tenantA, 'accountant');

        $this->asAdmin()->patch(route('platform.tenants.status', $this->tenantB), ['status' => 'suspended'])
            ->assertSessionHasNoErrors();
        $this->assertSame('suspended', $this->tenantB->fresh()->status);

        $this->assertDenied($this->as($userB, $this->tenantB)->get(route('dashboard')), 'suspended tenant dashboard');
        $this->as($userA, $this->tenantA)->get(route('dashboard'))->assertOk();
    }

    public static function lockedStatuses(): array
    {
        // Reached through billing/lifecycle jobs rather than the status button.
        return [
            'cancelled' => ['cancelled'],
            'archived' => ['archived'],
            'past_due' => ['past_due'],
        ];
    }

    #[DataProvider('lockedStatuses')]
    public function test_every_non_accessible_status_shuts_out_the_tenants_users(string $status): void
    {
        $userB = $this->userWithRole($this->tenantB, 'accountant');
        $this->tenantB->forceFill(['status' => $status])->save();

        $this->assertDenied($this->as($userB, $this->tenantB)->get(route('dashboard')), "{$status} tenant dashboard");
    }

    public function test_reactivating_a_tenant_restores_access(): void
    {
        $userB = $this->userWithRole($this->tenantB, 'accountant');

        $this->asAdmin()->patch(route('platform.tenants.status', $this->tenantB), ['status' => 'suspended']);
        $this->asAdmin()->patch(route('platform.tenants.status', $this->tenantB), ['status' => 'active']);

        $this->as($userB, $this->tenantB)->get(route('dashboard'))->assertOk();
    }

    public function test_status_change_rejects_an_unknown_status(): void
    {
        $this->asAdmin()->patch(route('platform.tenants.status', $this->tenantB), ['status' => 'deleted'])
            ->assertSessionHasErrors('status');

        $this->assertSame('active', $this->tenantB->fresh()->status);
    }

    public function test_an_unknown_tenant_header_is_a_404_not_a_fallback_to_another_tenant(): void
    {
        $userA = $this->userWithRole($this->tenantA, 'accountant');

        $this->actingAs($userA)->withHeader('X-Tenant', 'does-not-exist')
            ->get(route('dashboard'))
            ->assertNotFound();
    }

    public function test_a_signed_in_user_cannot_carry_their_session_into_another_tenant(): void
    {
        $this->userWithRole($this->tenantA, 'accountant', 'roamer@qa.test');

        // Real sign-in (not actingAs) so the session goes through the
        // tenant-aware user provider exactly as a browser would.
        $this->withHeader('X-Tenant', $this->tenantA->slug)
            ->post(route('login.store'), ['email' => 'roamer@qa.test', 'password' => 'password'])
            ->assertRedirect();
        $this->withHeader('X-Tenant', $this->tenantA->slug)->get(route('dashboard'))->assertOk();

        // The test client keeps the guard's resolved user between requests;
        // a real browser request re-resolves it from the session every time.
        $this->app['auth']->forgetGuards();

        $response = $this->withHeader('X-Tenant', $this->tenantB->slug)->get(route('dashboard'));

        $this->assertNotSame(200, $response->getStatusCode(), 'Tenant A session was served tenant B\'s dashboard');
    }

    public function test_a_tenant_owner_cannot_open_switch_into_another_tenant(): void
    {
        $owner = $this->userWithRole($this->tenantA, 'tenant_owner');

        $response = $this->as($owner, $this->tenantA)->get(route('tenant.switch', $this->tenantB->slug));

        $this->assertNotSame($this->tenantB->slug, session('tenant_slug'));
        $this->assertNotContains($response->getStatusCode(), [200]);
    }

    public function test_login_is_throttled_after_five_failed_attempts(): void
    {
        $this->userWithRole($this->tenantA, 'accountant', 'brute@qa.test');

        foreach (range(1, 5) as $_) {
            $this->withHeader('X-Tenant', $this->tenantA->slug)
                ->post(route('login.store'), ['email' => 'brute@qa.test', 'password' => 'wrong']);
        }

        $this->withHeader('X-Tenant', $this->tenantA->slug)
            ->post(route('login.store'), ['email' => 'brute@qa.test', 'password' => 'password'])
            ->assertStatus(429);
    }

    public function test_a_deactivated_account_cannot_sign_in(): void
    {
        $user = $this->userWithRole($this->tenantA, 'accountant', 'gone@qa.test');
        $user->forceFill(['settings' => ['is_deactivated' => true]])->save();

        $this->withHeader('X-Tenant', $this->tenantA->slug)
            ->post(route('login.store'), ['email' => 'gone@qa.test', 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }
}
