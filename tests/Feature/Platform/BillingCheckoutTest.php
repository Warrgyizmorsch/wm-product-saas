<?php

namespace Tests\Feature\Platform;

use App\Core\Tenant\TenantContext;
use App\Domains\Platform\Models\ModulePrice;
use App\Domains\Platform\Models\Plan;
use App\Domains\Platform\Models\TenantModule;
use App\Domains\Platform\Services\TenantModuleService;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Zoho-style checkout page: live quote, validation, billing details. */
class BillingCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private Plan $standard;
    private Tenant $tenant;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        config(['billing.gst_rate' => 18]);
        $this->seed(RbacSeeder::class);

        $this->standard = Plan::create([
            'name' => 'Standard', 'slug' => 'standard-co', 'price' => 0, 'currency' => 'INR', 'billing_cycle' => 'monthly',
            'features' => ['crm', 'sales'], 'is_active' => true, 'sort_order' => 1,
            'monthly_price_per_user' => 2500, 'yearly_price_per_user' => 1250,
        ]);
        Plan::create([
            'name' => 'Legacy Flat', 'slug' => 'legacy-flat', 'price' => 999, 'currency' => 'INR', 'billing_cycle' => 'monthly',
            'features' => ['crm'], 'is_active' => true,
        ]);

        ModulePrice::query()->where('module', 'inventory')->update(['monthly_price_per_user' => 400, 'yearly_price_per_user' => 300]);
        ModulePrice::query()->where('module', 'production')->update(['monthly_price_per_user' => 600, 'yearly_price_per_user' => 500]);

        $this->tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme', 'status' => 'active', 'plan' => 'standard-co', 'plan_id' => $this->standard->id]);
        app(TenantContext::class)->set($this->tenant);
        $this->owner = $this->makeUser('owner@acme.test', 'tenant_owner');
        $this->makeUser('two@acme.test', null);
        $this->makeUser('three@acme.test', null);
        $this->withHeader('X-Tenant', 'acme');
    }

    private function makeUser(string $email, ?string $roleSlug): User
    {
        $user = User::create(['tenant_id' => $this->tenant->id, 'name' => $email, 'email' => $email, 'password' => bcrypt('password')]);

        if ($roleSlug !== null) {
            UserRole::create([
                'user_id' => $user->id,
                'role_id' => Role::query()->whereNull('tenant_id')->where('slug', $roleSlug)->firstOrFail()->id,
                'tenant_id' => $this->tenant->id,
            ]);
        }

        return $user;
    }

    private function quote(array $body, ?User $user = null)
    {
        return $this->actingAs($user ?? $this->owner)->postJson(route('platform.billing.quote'), $body + [
            'plan_id' => $this->standard->id, 'cycle' => 'yearly', 'seats' => 3, 'modules' => [],
        ]);
    }

    public function test_checkout_page_lists_per_user_plans_and_add_ons(): void
    {
        $this->actingAs($this->owner)->get(route('platform.billing.checkout'))
            ->assertOk()
            ->assertSee('Standard')
            ->assertDontSee('Legacy Flat')          // no per-user price → not in this checkout
            ->assertSee('Inventory')
            ->assertSee('Billing details')
            ->assertSee('subscription\/quote', false);  // @json-escaped route in the page script
    }

    public function test_subscription_page_links_to_the_checkout(): void
    {
        $this->actingAs($this->owner)->get(route('platform.subscription.index'))
            ->assertOk()
            ->assertSee(route('platform.billing.checkout'));
    }

    public function test_quote_prices_plan_and_add_ons_per_user_with_gst(): void
    {
        $this->quote(['cycle' => 'monthly', 'seats' => 4, 'modules' => ['inventory']])
            ->assertOk()
            ->assertJson([
                'cycle' => 'monthly',
                'cycle_label' => 'Monthly',
                'seats' => 4,
                'subtotal' => 4 * (2500 + 400) * 100,
                'gst' => (int) round(4 * 2900 * 100 * 0.18),
                'total' => 4 * 2900 * 100 + (int) round(4 * 2900 * 100 * 0.18),
            ])
            ->assertJsonPath('lines.1.label', 'Inventory');
    }

    public function test_quote_ignores_client_supplied_amounts(): void
    {
        $this->quote(['seats' => 3, 'total' => 1, 'subtotal' => 1, 'price_per_user' => 1])
            ->assertOk()
            ->assertJsonPath('subtotal', 3 * 1250 * 100 * 12);
    }

    public function test_users_cannot_go_below_the_workspace_head_count(): void
    {
        $this->quote(['seats' => 2])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['seats' => 'Your workspace already has 3 users — buy at least 3.']);
    }

    public function test_a_plan_without_per_user_pricing_or_an_unknown_cycle_is_rejected(): void
    {
        $legacy = Plan::query()->where('slug', 'legacy-flat')->sole();

        $this->quote(['plan_id' => $legacy->id])->assertStatus(422)->assertJsonValidationErrors('plan_id');
        $this->quote(['cycle' => 'weekly'])->assertStatus(422)->assertJsonValidationErrors('cycle');
    }

    public function test_an_add_on_needs_its_dependencies_in_the_same_order(): void
    {
        $this->quote(['modules' => ['production']])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['modules' => 'Production needs Inventory — add it too.']);

        $this->quote(['modules' => ['production', 'inventory']])->assertOk()->assertJsonCount(3, 'lines');
    }

    public function test_lifetime_add_ons_are_free_and_satisfy_dependencies(): void
    {
        app(TenantModuleService::class)->install($this->tenant, ['inventory'], null, null, TenantModule::BILLING_LIFETIME);

        $this->quote(['modules' => ['inventory', 'production']])
            ->assertOk()
            ->assertJsonCount(2, 'lines')
            ->assertJsonPath('lines.1.key', 'production');
    }

    public function test_an_add_on_not_for_sale_is_rejected(): void
    {
        $this->quote(['modules' => ['hrms']])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['modules' => "HR & Payroll isn't sold as an add-on on yearly billing."]);
    }

    public function test_quote_and_checkout_need_the_subscription_permission(): void
    {
        $plain = User::query()->where('email', 'two@acme.test')->sole();

        $this->quote([], $plain)->assertForbidden();
        $this->actingAs($plain)->get(route('platform.billing.checkout'))->assertForbidden();
        $this->actingAs($plain)->putJson(route('platform.billing.details'), ['billing_name' => 'X', 'billing_email' => 'x@y.z'])->assertForbidden();
    }

    public function test_billing_details_are_saved_with_an_upper_cased_gstin(): void
    {
        $this->actingAs($this->owner)->putJson(route('platform.billing.details'), [
            'billing_name' => 'Acme Industries Pvt Ltd',
            'billing_email' => 'accounts@acme.test',
            'billing_gstin' => '27abcde1234f1z5',
            'billing_state' => 'Maharashtra',
            'billing_address' => 'Plot 4, MIDC, Pune',
        ])->assertOk();

        $tenant = $this->tenant->fresh();
        $this->assertSame('Acme Industries Pvt Ltd', $tenant->billing_name);
        $this->assertSame('27ABCDE1234F1Z5', $tenant->billing_gstin);
        $this->assertSame('Maharashtra', $tenant->billing_state);
    }

    public function test_an_invalid_gstin_or_a_gstin_without_state_is_rejected(): void
    {
        $base = ['billing_name' => 'Acme', 'billing_email' => 'accounts@acme.test'];

        $this->actingAs($this->owner)->putJson(route('platform.billing.details'), $base + ['billing_gstin' => '27ABCDE1234F1X5', 'billing_state' => 'Maharashtra'])
            ->assertStatus(422)->assertJsonValidationErrors(['billing_gstin' => 'That is not a valid GSTIN (e.g. 27ABCDE1234F1Z5).']);

        $this->actingAs($this->owner)->putJson(route('platform.billing.details'), $base + ['billing_gstin' => '27ABCDE1234F1Z5'])
            ->assertStatus(422)->assertJsonValidationErrors('billing_state');

        // GSTIN is optional.
        $this->actingAs($this->owner)->putJson(route('platform.billing.details'), $base)->assertOk();
        $this->assertNull($this->tenant->fresh()->billing_gstin);
    }
}
