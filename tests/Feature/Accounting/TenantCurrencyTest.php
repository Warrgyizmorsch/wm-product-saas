<?php

namespace Tests\Feature\Accounting;

use App\Core\Tenant\TenantContext;
use App\Domains\Accounting\Models\Journal;
use App\Domains\HRMS\Models\Company;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantCurrencyTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $superAdmin;
    private User $owner;
    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create(['name' => 'Test Tenant', 'slug' => 'test-tenant', 'status' => 'active', 'plan' => 'enterprise', 'currency' => 'INR']);
        $this->seed(RbacSeeder::class);
        app(TenantContext::class)->set($this->tenant);

        $this->company = Company::create(['company_name' => 'Acme', 'legal_name' => 'Acme Pvt Ltd', 'currency' => 'INR']);

        $this->superAdmin = $this->userWithRole('super@example.com', 'super_admin');
        $this->owner = $this->userWithRole('owner@example.com', 'tenant_owner');
    }

    private function userWithRole(string $email, string $roleSlug): User
    {
        $user = User::create(['tenant_id' => $this->tenant->id, 'name' => ucfirst($roleSlug), 'email' => $email, 'password' => bcrypt('password')]);
        $role = Role::query()->whereNull('tenant_id')->where('slug', $roleSlug)->firstOrFail();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'tenant_id' => $this->tenant->id]);

        return $user;
    }

    private function updateTenant(string $currency)
    {
        return $this->actingAs($this->superAdmin)->withHeader('X-Tenant', 'test-tenant')
            // Mirrors the tenant form, which always submits every field (blank = null).
            ->put(route('platform.tenants.update', $this->tenant), [
                'name' => 'Test Tenant',
                'display_name' => '',
                'slug' => 'test-tenant',
                'domain' => '',
                'billing_email' => '',
                'status' => 'active',
                'plan' => 'enterprise',
                'plan_id' => '',
                'subscription_status' => 'active',
                'max_users' => '',
                'max_storage_mb' => '',
                'trial_ends_at' => '',
                'plan_started_at' => '',
                'plan_expires_at' => '',
                'timezone' => 'UTC',
                'locale' => 'en',
                'branch' => '',
                'financial_year' => '',
                'currency' => $currency,
            ]);
    }

    /** @test */
    public function a_tenant_without_journals_can_change_currency_and_its_companies_follow(): void
    {
        $this->updateTenant('GBP')->assertSessionHasNoErrors();

        $this->assertSame('GBP', $this->tenant->fresh()->currency);
        $this->assertSame('GBP', $this->company->fresh()->currency);
    }

    /** @test */
    public function currency_is_locked_once_the_tenant_has_journals(): void
    {
        Journal::withoutGlobalScopes()->create([
            'tenant_id' => $this->tenant->id,
            'journal_number' => 'JNL-TEST-1',
            'journal_date' => '2026-09-01',
            'status' => Journal::STATUS_POSTED,
        ]);

        $this->updateTenant('GBP')->assertSessionHasErrors('currency');

        $this->assertSame('INR', $this->tenant->fresh()->currency);
        $this->assertSame('INR', $this->company->fresh()->currency);
    }

    /** @test */
    public function an_unknown_currency_is_rejected(): void
    {
        $this->updateTenant('XYZ')->assertSessionHasErrors('currency');
    }

    /** @test */
    public function the_company_master_ignores_a_submitted_currency_and_uses_the_tenants(): void
    {
        $this->actingAs($this->owner)->withHeader('X-Tenant', 'test-tenant')
            ->post(route('hrms.company.update', $this->company), [
                'company_name' => 'Acme',
                'legal_name' => 'Acme Pvt Ltd',
                'email' => 'info@example.com',
                'currency' => 'USD',
                'time_zone' => 'Asia/Kolkata',
                'status' => '1',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('INR', $this->company->fresh()->currency);
    }
}
