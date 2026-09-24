<?php

namespace Tests\Feature\Platform;

use App\Core\Tenant\TenantContext;
use App\Domains\Platform\Models\ModulePrice;
use App\Domains\Platform\Models\Plan;
use App\Domains\Platform\Models\TenantModule;
use App\Domains\Platform\Services\SubscriptionPricing;
use App\Domains\Platform\Services\TenantModuleService;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/** Zoho-style per-user pricing: quote maths, proration, and the admin price list. */
class SubscriptionPricingTest extends TestCase
{
    use RefreshDatabase;

    private Plan $plan;
    private SubscriptionPricing $pricing;

    protected function setUp(): void
    {
        parent::setUp();

        config(['billing.gst_rate' => 18]);

        $this->plan = Plan::create([
            'name' => 'Standard', 'slug' => 'standard-test', 'price' => 0, 'currency' => 'INR',
            'billing_cycle' => 'monthly', 'features' => ['crm', 'sales'], 'is_active' => true,
            'monthly_price_per_user' => 2500, 'yearly_price_per_user' => 1250,
        ]);

        ModulePrice::query()->where('module', 'inventory')->update(['monthly_price_per_user' => 400, 'yearly_price_per_user' => 300]);
        ModulePrice::query()->where('module', 'production')->update(['monthly_price_per_user' => 600, 'yearly_price_per_user' => null]);

        $this->pricing = app(SubscriptionPricing::class);
    }

    /** Platform admin (null tenant_id) browsing through a resolved tenant, as every route needs one. */
    private function platformAdmin(): User
    {
        $this->seed(RbacSeeder::class);
        Tenant::create(['name' => 'Acme', 'slug' => 'acme', 'status' => 'active', 'plan' => 'standard-test']);
        $this->withHeader('X-Tenant', 'acme');

        $admin = User::create(['tenant_id' => null, 'name' => 'Root', 'email' => 'root@platform.test', 'password' => bcrypt('password')]);
        UserRole::create(['user_id' => $admin->id, 'role_id' => Role::query()->whereNull('tenant_id')->where('slug', 'super_admin')->firstOrFail()->id, 'tenant_id' => null]);

        return $admin;
    }

    public function test_every_gated_module_has_a_price_row_after_migrating(): void
    {
        $this->assertSame(8, ModulePrice::query()->count());
    }

    public function test_monthly_quote_is_per_user_with_gst_on_top(): void
    {
        $quote = $this->pricing->quote($this->plan, 'monthly', 5);

        $this->assertSame(5 * 2500 * 100, $quote->subtotal);
        $this->assertSame(225000, $quote->gst);           // 18% of ₹12,500
        $this->assertSame(1475000, $quote->total);        // ₹14,750
        $this->assertSame(250000, $quote->perSeatAmount());
        $this->assertSame('INR', $quote->currency);
    }

    public function test_yearly_quote_uses_the_discounted_per_month_price_for_twelve_months(): void
    {
        $quote = $this->pricing->quote($this->plan, 'yearly', 2, ['inventory']);

        $this->assertSame([2 * 1250 * 100 * 12, 2 * 300 * 100 * 12], array_column($quote->lines, 'amount'));
        $this->assertSame(3720000, $quote->subtotal);     // ₹37,200
        $this->assertSame(669600, $quote->gst);
        $this->assertSame(['inventory'], $quote->addonModules());
    }

    public function test_modules_the_plan_includes_or_the_tenant_owns_for_life_are_not_charged(): void
    {
        $quote = $this->pricing->quote($this->plan, 'monthly', 3, ['crm', 'inventory', 'production'], ['production']);

        $this->assertSame(['plan', 'addon'], array_column($quote->lines, 'type'));
        $this->assertSame(['inventory'], $quote->addonModules());
        $this->assertSame(3 * (2500 + 400) * 100, $quote->subtotal);
    }

    public function test_an_add_on_not_sold_on_that_cycle_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Production isn't sold as an add-on on yearly billing.");

        $this->pricing->quote($this->plan, 'yearly', 1, ['production']);
    }

    public function test_an_inactive_add_on_is_rejected(): void
    {
        ModulePrice::query()->where('module', 'inventory')->update(['is_active' => false]);

        $this->expectException(InvalidArgumentException::class);

        app(SubscriptionPricing::class)->quote($this->plan, 'monthly', 1, ['inventory']);
    }

    public function test_a_plan_not_sold_on_that_cycle_or_zero_seats_is_rejected(): void
    {
        $this->plan->update(['yearly_price_per_user' => null]);

        try {
            $this->pricing->quote($this->plan->fresh(), 'yearly', 1);
            $this->fail('Expected yearly to be rejected.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame("Standard isn't available on yearly billing.", $e->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $this->pricing->quote($this->plan, 'monthly', 0);
    }

    public function test_an_upgrade_mid_cycle_is_charged_for_the_remaining_share_of_the_period(): void
    {
        $start = CarbonImmutable::parse('2026-09-01 00:00:00');
        $end = CarbonImmutable::parse('2026-10-01 00:00:00');   // 30 days
        $now = CarbonImmutable::parse('2026-09-21 00:00:00');   // 10 days left

        $current = $this->pricing->quote($this->plan, 'monthly', 5);
        $more = $this->pricing->quote($this->plan, 'monthly', 8, ['inventory']);

        // Δ = (8×2900 − 5×2500) = ₹10,700; ⅓ of the period left → ₹3,566.67 + 18% GST
        $net = (int) round((8 * 2900 - 5 * 2500) * 100 / 3);
        $this->assertSame($net + (int) round($net * 0.18), $this->pricing->prorate($current, $more, $start, $end, $now));
    }

    public function test_a_downgrade_or_a_finished_period_costs_nothing_now(): void
    {
        $start = CarbonImmutable::parse('2026-09-01');
        $end = CarbonImmutable::parse('2026-10-01');

        $big = $this->pricing->quote($this->plan, 'monthly', 8);
        $small = $this->pricing->quote($this->plan, 'monthly', 5);

        $this->assertSame(0, $this->pricing->prorate($big, $small, $start, $end, $start->addDays(3)));
        $this->assertSame(0, $this->pricing->prorate($small, $big, $start, $end, $end->addDay()));
    }

    public function test_one_time_purchases_are_recorded_as_lifetime_and_stay_lifetime_on_reinstall(): void
    {
        $tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme', 'status' => 'active', 'plan' => 'standard-test', 'plan_id' => $this->plan->id]);
        app(TenantContext::class)->set($tenant);
        $modules = app(TenantModuleService::class);

        $modules->install($tenant, ['projects'], null, null, TenantModule::BILLING_LIFETIME);
        $modules->uninstall($tenant, 'projects');
        $modules->reinstall($tenant, 'projects');
        $modules->install($tenant, ['hrms']);

        $this->assertSame(
            ['hrms' => TenantModule::BILLING_RECURRING, 'projects' => TenantModule::BILLING_LIFETIME],
            TenantModule::query()->orderBy('module')->pluck('billing', 'module')->all(),
        );
    }

    public function test_platform_admin_can_edit_add_on_prices(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)->get(route('platform.module-prices.index'))->assertOk()->assertSee('Inventory');

        $this->actingAs($admin)->put(route('platform.module-prices.update'), [
            'prices' => [
                'hrms' => ['monthly_price_per_user' => '350', 'yearly_price_per_user' => '', 'is_active' => '1'],
                'inventory' => ['monthly_price_per_user' => '400', 'yearly_price_per_user' => '300', 'is_active' => '0'],
            ],
        ])->assertRedirect(route('platform.module-prices.index'));

        $hrms = ModulePrice::query()->where('module', 'hrms')->sole();
        $this->assertSame(350, $hrms->monthly_price_per_user);
        $this->assertNull($hrms->yearly_price_per_user);
        $this->assertFalse(ModulePrice::query()->where('module', 'inventory')->sole()->is_active);
    }

    public function test_a_tenant_owner_cannot_edit_add_on_prices(): void
    {
        $this->seed(RbacSeeder::class);
        $tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme', 'status' => 'active', 'plan' => 'standard-test']);
        app(TenantContext::class)->set($tenant);
        $owner = User::create(['tenant_id' => $tenant->id, 'name' => 'Owner', 'email' => 'owner@acme.test', 'password' => bcrypt('password')]);
        UserRole::create(['user_id' => $owner->id, 'role_id' => Role::query()->whereNull('tenant_id')->where('slug', 'tenant_owner')->firstOrFail()->id, 'tenant_id' => $tenant->id]);

        $this->withHeader('X-Tenant', 'acme')->actingAs($owner)
            ->put(route('platform.module-prices.update'), ['prices' => ['hrms' => ['monthly_price_per_user' => '1']]])
            ->assertForbidden();

        $this->assertNull(ModulePrice::query()->where('module', 'hrms')->sole()->monthly_price_per_user);
    }

    public function test_plan_form_saves_per_user_prices(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)->put(route('platform.plans.update', $this->plan), [
            'name' => 'Standard', 'slug' => 'standard-test', 'billing_cycle' => 'monthly', 'price' => 0,
            'monthly_price_per_user' => '3000', 'yearly_price_per_user' => '',
            'features' => ['crm'], 'is_active' => '1',
        ])->assertRedirect(route('platform.plans.index'));

        $plan = $this->plan->fresh();
        $this->assertSame(3000, $plan->monthly_price_per_user);
        $this->assertNull($plan->yearly_price_per_user);
    }

    public function test_a_plan_without_its_own_price_costs_the_sum_of_its_modules(): void
    {
        ModulePrice::query()->where('module', 'crm')->update(['monthly_price_per_user' => 30, 'yearly_price_per_user' => 25]);
        ModulePrice::query()->where('module', 'sales')->update(['monthly_price_per_user' => 40, 'yearly_price_per_user' => 35]);
        $this->plan->update(['monthly_price_per_user' => null, 'yearly_price_per_user' => null]);
        $plan = $this->plan->fresh();

        $this->assertSame(70, $this->pricing->planPricePerUser($plan, 'monthly'));
        $this->assertSame(60, $this->pricing->planPricePerUser($plan, 'yearly'));
        $this->assertSame(3 * 60 * 100 * 12, $this->pricing->quote($plan, 'yearly', 3)->subtotal);

        // An override wins over the bundle (e.g. a bundle discount).
        $plan->update(['monthly_price_per_user' => 55]);
        $this->assertSame(55, app(SubscriptionPricing::class)->planPricePerUser($plan->fresh(), 'monthly'));
    }

    public function test_a_bundle_with_an_unpriced_module_is_not_sold(): void
    {
        ModulePrice::query()->where('module', 'crm')->update(['monthly_price_per_user' => 30]);
        $this->plan->update(['monthly_price_per_user' => null, 'yearly_price_per_user' => null]);
        $plan = $this->plan->fresh();

        $this->assertNull($this->pricing->planPricePerUser($plan, 'monthly'));   // sales has no price
        $this->assertFalse($this->pricing->sellsPerUser($plan));
    }

    public function test_a_yearly_total_typed_into_the_per_month_field_is_rejected(): void
    {
        $admin = $this->platformAdmin();

        $this->actingAs($admin)->put(route('platform.module-prices.update'), [
            'prices' => ['crm' => ['monthly_price_per_user' => '30', 'yearly_price_per_user' => '360', 'is_active' => '1']],
        ])->assertSessionHasErrors([
            'prices.crm.yearly_price_per_user' => 'CRM: the billed-yearly price is per user per month and cannot be more than the monthly price (₹30). For ₹360 per user per year, enter ₹30.',
        ]);
        $this->assertNull(ModulePrice::query()->where('module', 'crm')->sole()->yearly_price_per_user);

        $this->actingAs($admin)->put(route('platform.plans.update', $this->plan), [
            'name' => 'Standard', 'slug' => 'standard-test', 'billing_cycle' => 'monthly', 'price' => 0,
            'monthly_price_per_user' => '2500', 'yearly_price_per_user' => '30000', 'features' => ['crm'], 'is_active' => '1',
        ])->assertSessionHasErrors('yearly_price_per_user');
        $this->assertSame(1250, $this->plan->fresh()->yearly_price_per_user);
    }

    public function test_the_old_free_switch_and_one_time_checkouts_refuse_per_user_priced_items(): void
    {
        $this->seed(RbacSeeder::class);
        $tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme', 'status' => 'active', 'plan' => 'standard-test', 'plan_id' => $this->plan->id]);
        app(TenantContext::class)->set($tenant);
        $owner = User::create(['tenant_id' => $tenant->id, 'name' => 'Owner', 'email' => 'owner@acme.test', 'password' => bcrypt('password')]);
        UserRole::create(['user_id' => $owner->id, 'role_id' => Role::query()->whereNull('tenant_id')->where('slug', 'tenant_owner')->firstOrFail()->id, 'tenant_id' => $tenant->id]);
        $this->withHeader('X-Tenant', 'acme')->actingAs($owner);

        $pro = Plan::create([
            'name' => 'Pro', 'slug' => 'pro-test', 'price' => 0, 'currency' => 'INR', 'billing_cycle' => 'monthly',
            'features' => ['crm', 'sales', 'inventory'], 'is_active' => true, 'monthly_price_per_user' => 4000,
        ]);

        // Legacy price is 0, but the plan is sold per user → no free switch.
        $this->put(route('platform.subscription.update'), ['plan_id' => $pro->id])->assertSessionHas('error');
        $this->assertSame($this->plan->id, $tenant->fresh()->plan_id);

        $this->postJson(route('platform.subscription.checkout'), ['plan_id' => $pro->id])
            ->assertStatus(422)->assertJson(['message' => 'Pro is billed per user — choose it in the plan checkout.']);

        $this->postJson(route('platform.modules.checkout'), ['modules' => ['inventory']])
            ->assertStatus(422)->assertJson(['message' => 'Inventory is billed per user — add it in the plan checkout.']);
    }

    public function test_subscription_page_sends_per_user_plans_and_add_ons_to_the_checkout(): void
    {
        $this->seed(RbacSeeder::class);
        $tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme', 'status' => 'active', 'plan' => 'standard-test', 'plan_id' => $this->plan->id]);
        app(TenantContext::class)->set($tenant);
        $owner = User::create(['tenant_id' => $tenant->id, 'name' => 'Owner', 'email' => 'owner@acme.test', 'password' => bcrypt('password')]);
        UserRole::create(['user_id' => $owner->id, 'role_id' => Role::query()->whereNull('tenant_id')->where('slug', 'tenant_owner')->firstOrFail()->id, 'tenant_id' => $tenant->id]);

        $this->withHeader('X-Tenant', 'acme')->actingAs($owner)->get(route('platform.subscription.index'))
            ->assertOk()
            ->assertSee('₹1,250 /user/month billed yearly', false)
            ->assertSee('no paid subscription yet')
            ->assertSee(route('platform.billing.checkout', ['plan' => $this->plan->id]), false)
            ->assertSee(e(route('platform.billing.checkout', ['modules' => ['inventory']])), false)
            ->assertSee('₹300 /user/month billed yearly', false)
            // Per-user priced modules are no longer sold with the one-time checkbox.
            ->assertDontSee('value="inventory" class="form-check-input erp-app-tile-check"', false)
            ->assertSee('value="purchase" class="form-check-input erp-app-tile-check"', false)
            ->assertDontSee('Switch to Standard');
    }
}
