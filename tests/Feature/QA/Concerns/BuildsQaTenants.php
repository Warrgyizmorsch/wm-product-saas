<?php

namespace Tests\Feature\QA\Concerns;

use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Services\ChartOfAccountsService;
use App\Domains\Accounting\Services\FiscalPeriodService;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Testing\TestResponse;

/**
 * Shared fixtures for the QA suite: two independent tenants, users holding a
 * seeded system role, and an optional standard ledger + open fiscal year.
 *
 * Every request helper sends the X-Tenant header so tenant resolution goes
 * through ResolveTenant exactly as it does in production.
 */
trait BuildsQaTenants
{
    protected Tenant $tenantA;

    protected Tenant $tenantB;

    protected function bootTwoTenants(bool $withBooks = false): void
    {
        $this->tenantA = $this->makeTenant('qa-tenant-a', 'QA Tenant A');
        $this->tenantB = $this->makeTenant('qa-tenant-b', 'QA Tenant B');

        $this->seed(RbacSeeder::class);

        if ($withBooks) {
            $this->provisionBooks($this->tenantA);
            $this->provisionBooks($this->tenantB);
        }
    }

    protected function makeTenant(string $slug, string $name, string $status = 'active'): Tenant
    {
        return Tenant::create([
            'name' => $name,
            'slug' => $slug,
            'status' => $status,
            'plan' => 'enterprise',
        ]);
    }

    protected function provisionBooks(Tenant $tenant): void
    {
        app(ChartOfAccountsService::class)->provisionDefaults($tenant->id);

        app(FiscalPeriodService::class)->createFiscalYearWithMonthlyPeriods([
            'tenant_id' => $tenant->id,
            'name' => 'FY ' . now()->year,
            'start_date' => now()->startOfYear()->toDateString(),
            'end_date' => now()->endOfYear()->toDateString(),
        ]);
    }

    protected function userWithRole(Tenant $tenant, string $roleSlug, ?string $email = null): User
    {
        $email ??= $roleSlug . '.' . $tenant->slug . '@qa.test';

        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => ucfirst(str_replace('_', ' ', $roleSlug)) . ' ' . $tenant->slug,
            'email' => $email,
            'password' => bcrypt('password'),
        ]);

        $role = Role::query()->whereNull('tenant_id')->where('slug', $roleSlug)->firstOrFail();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'tenant_id' => $tenant->id]);
        $user->forceFill(['role_id' => $role->id])->save();

        return $user->fresh();
    }

    /**
     * A platform operator: no home tenant, system super_admin role assigned
     * with a null tenant — the same shape SubscriptionPricingTest uses. A
     * tenant-bound super_admin is (by design) only an admin of its own tenant.
     */
    protected function platformAdmin(): User
    {
        $user = User::create([
            'tenant_id' => null,
            'name' => 'Platform Admin',
            'email' => 'platform.admin@qa.test',
            'password' => bcrypt('password'),
        ]);

        $role = Role::query()->whereNull('tenant_id')->where('slug', 'super_admin')->firstOrFail();
        UserRole::create(['user_id' => $user->id, 'role_id' => $role->id, 'tenant_id' => null]);

        return $user->fresh();
    }

    protected function accountFor(Tenant $tenant, string $code): ChartOfAccount
    {
        return ChartOfAccount::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('code', $code)
            ->firstOrFail();
    }

    protected function as(User $user, Tenant $tenant): static
    {
        return $this->actingAs($user)->withHeader('X-Tenant', $tenant->slug);
    }

    /**
     * Status a denied request is allowed to come back with: 403 (policy) or
     * 404 (scoped binding hid the record). Anything 2xx/3xx means it went through.
     */
    protected function assertDenied(TestResponse $response, string $context = ''): void
    {
        $this->assertContains(
            $response->getStatusCode(),
            [403, 404],
            trim("Expected 403/404 but got {$response->getStatusCode()}. {$context}")
        );
    }
}
