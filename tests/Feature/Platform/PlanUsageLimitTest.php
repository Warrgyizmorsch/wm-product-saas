<?php

namespace Tests\Feature\Platform;

use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Platform\Exceptions\UsageLimitExceededException;
use App\Domains\Platform\Models\Plan;
use App\Domains\Platform\Services\UsageLimitService;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanUsageLimitTest extends TestCase
{
    use RefreshDatabase;

    private Plan $plan;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->plan = Plan::create([
            'name' => 'Capped', 'slug' => 'capped', 'price' => 0, 'currency' => 'INR', 'billing_cycle' => 'monthly',
            'features' => ['inventory'], 'limits' => ['warehouses' => 2], 'is_active' => true,
        ]);
        $this->tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme', 'status' => 'active', 'plan' => 'capped', 'plan_id' => $this->plan->id]);
    }

    private function warehouse(int $n, ?Tenant $tenant = null): Warehouse
    {
        app()->forgetScopedInstances();

        return Warehouse::create(['tenant_id' => ($tenant ?? $this->tenant)->id, 'name' => "WH {$n}", 'code' => "WH{$n}"]);
    }

    public function test_creating_past_the_cap_is_refused(): void
    {
        $this->warehouse(1);
        $this->warehouse(2);

        $this->expectException(UsageLimitExceededException::class);
        $this->expectExceptionMessage('Your plan allows up to 2 warehouses.');

        $this->warehouse(3);
    }

    public function test_a_blank_cap_is_unlimited(): void
    {
        $this->plan->update(['limits' => ['warehouses' => null]]);

        foreach (range(1, 4) as $n) {
            $this->warehouse($n);
        }

        $this->assertSame(4, Warehouse::query()->withoutGlobalScope('tenant')->where('tenant_id', $this->tenant->id)->count());
    }

    public function test_another_tenants_rows_do_not_count(): void
    {
        $other = Tenant::create(['name' => 'Other', 'slug' => 'other', 'status' => 'active', 'plan' => 'capped', 'plan_id' => $this->plan->id]);
        $this->warehouse(1, $other);
        $this->warehouse(2, $other);

        $this->warehouse(3);
        $this->warehouse(4);

        $this->assertSame(2, app(UsageLimitService::class)->used($this->tenant, 'warehouses'));
    }

    public function test_provisioning_and_platform_tools_can_bypass_the_cap(): void
    {
        $this->warehouse(1);
        $this->warehouse(2);

        UsageLimitService::withoutLimits(fn () => $this->warehouse(3));

        $this->assertSame(3, app(UsageLimitService::class)->used($this->tenant, 'warehouses'));
    }

    public function test_a_monthly_cap_only_counts_this_month(): void
    {
        config(['plan_limits.resources.warehouses.period' => 'month']);

        $this->warehouse(1)->forceFill(['created_at' => now()->subMonth()])->save();
        $this->warehouse(2)->forceFill(['created_at' => now()->subMonth()])->save();
        $this->warehouse(3);
        $this->warehouse(4);

        $this->expectExceptionMessage('Your plan allows up to 2 warehouses this month.');
        $this->warehouse(5);
    }

    public function test_usage_rows_report_used_and_cap(): void
    {
        $this->warehouse(1);

        $row = collect(app(UsageLimitService::class)->resourceUsage($this->tenant))->firstWhere('key', 'warehouses');

        $this->assertSame(['used' => 1, 'limit' => 2, 'percent' => 50.0], array_intersect_key($row, array_flip(['used', 'limit', 'percent'])));
    }
}
