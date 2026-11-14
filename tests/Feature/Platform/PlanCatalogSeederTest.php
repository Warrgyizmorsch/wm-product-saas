<?php

namespace Tests\Feature\Platform;

use App\Domains\Platform\Models\Plan;
use App\Domains\Platform\Services\PlanFeatureGate;
use App\Domains\Platform\Services\SubscriptionPricing;
use Database\Seeders\PlanCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanCatalogSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PlanCatalogSeeder::class);
    }

    public function test_every_excluded_feature_is_a_catalog_key(): void
    {
        $keys = app(PlanFeatureGate::class)->keys();

        foreach (Plan::query()->whereIn('slug', ['starter', 'pro', 'enterprise'])->get() as $plan) {
            $this->assertSame([], array_values(array_diff($plan->excluded_features, $keys)), "{$plan->slug} excludes unknown features");
        }
    }

    public function test_tiers_are_priced_per_user_with_a_yearly_discount(): void
    {
        $pricing = app(SubscriptionPricing::class);

        foreach (['starter' => [499, 399], 'pro' => [999, 799], 'enterprise' => [1799, 1499]] as $slug => [$monthly, $yearly]) {
            $plan = Plan::query()->where('slug', $slug)->firstOrFail();
            $this->assertSame($monthly, $pricing->planPricePerUser($plan, 'monthly'));
            $this->assertSame($yearly, $pricing->planPricePerUser($plan, 'yearly'));
        }
    }

    public function test_starter_buys_the_other_modules_as_per_user_add_ons(): void
    {
        $starter = Plan::query()->where('slug', 'starter')->firstOrFail();

        $quote = app(SubscriptionPricing::class)->quote($starter, 'monthly', 3, ['hrms', 'production', 'crm']);

        // CRM is already in Starter, so only the plan + two add-ons are billed.
        $this->assertCount(3, $quote->lines);
        $this->assertSame((499 + 199 + 299) * 3 * 100, $quote->subtotal); // paise
    }

    public function test_re_running_does_not_duplicate_plans(): void
    {
        $before = Plan::query()->count();

        $this->seed(PlanCatalogSeeder::class);

        $this->assertSame($before, Plan::query()->count());
    }
}
