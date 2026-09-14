<?php

namespace Tests\Feature\Accounting;

use App\Core\Tenant\TenantContext;
use App\Domains\HRMS\Models\Company;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportingCurrencyDisplayTest extends TestCase
{
    use RefreshDatabase;

    private User $accountant;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = Tenant::create([
            'name' => 'Test Tenant', 'slug' => 'test-tenant', 'status' => 'active', 'plan' => 'enterprise',
            'currency' => 'GBP',
        ]);
        $this->seed(RbacSeeder::class);

        app(TenantContext::class)->set($tenant);
        // Stale per-company values must be ignored: the tenant's currency wins.
        // A second company makes the header render its company switcher chip.
        Company::create(['company_name' => 'UK Ltd', 'currency' => 'INR']);
        Company::create(['company_name' => 'India Pvt Ltd', 'currency' => 'USD']);

        $this->accountant = User::create(['tenant_id' => $tenant->id, 'name' => 'Accountant', 'email' => 'accountant@example.com', 'password' => bcrypt('password')]);
        $role = Role::query()->whereNull('tenant_id')->where('slug', 'accountant')->firstOrFail();
        UserRole::create(['user_id' => $this->accountant->id, 'role_id' => $role->id, 'tenant_id' => $tenant->id]);
    }

    /** @test */
    public function accounting_pages_label_amounts_with_the_tenant_currency(): void
    {
        $response = $this->actingAs($this->accountant)->withHeader('X-Tenant', 'test-tenant')
            ->get(route('accounting.journals.index'));

        $response->assertOk();
        $response->assertSee('Amounts in GBP (£)');
        $response->assertSee('GBP · ', false);
        $response->assertDontSee('Amounts in INR');
    }

    /** @test */
    public function the_exchange_rates_page_has_no_amounts_badge(): void
    {
        $this->actingAs($this->accountant)->withHeader('X-Tenant', 'test-tenant')
            ->get(route('accounting.exchange-rates.index'))
            ->assertOk()
            ->assertDontSee('Amounts in GBP');
    }
}
