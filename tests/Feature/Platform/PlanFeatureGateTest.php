<?php

namespace Tests\Feature\Platform;

use App\Domains\Platform\Models\Plan;
use App\Domains\Platform\Services\PlanFeatureGate;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Access\AccessService;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanFeatureGateTest extends TestCase
{
    use RefreshDatabase;

    private Plan $plan;
    private Tenant $tenant;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);

        $this->plan = Plan::create([
            'name' => 'Standard', 'slug' => 'standard-gate', 'price' => 0, 'currency' => 'INR', 'billing_cycle' => 'monthly',
            'features' => ['crm', 'sales', 'inventory', 'purchase'], 'excluded_features' => ['purchase.returns'], 'is_active' => true,
        ]);
        $this->tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme', 'status' => 'active', 'plan' => 'standard-gate', 'plan_id' => $this->plan->id]);

        $this->owner = User::create(['tenant_id' => $this->tenant->id, 'name' => 'Owner', 'email' => 'owner@acme.test', 'password' => bcrypt('password')]);
        $role = Role::query()->whereNull('tenant_id')->where('slug', 'tenant_owner')->firstOrFail();
        UserRole::create(['user_id' => $this->owner->id, 'role_id' => $role->id, 'tenant_id' => $this->tenant->id]);
        $this->owner->forceFill(['role_id' => $role->id])->save();
    }

    private function allows(User $user, string $permission): bool
    {
        app()->forgetScopedInstances();

        return app(AccessService::class)->allows($user, $permission, ['tenant_id' => $this->tenant->id]);
    }

    public function test_an_excluded_feature_is_view_only_even_for_the_tenant_owner(): void
    {
        $this->assertTrue($this->allows($this->owner, 'purchase.returns.view'));
        $this->assertFalse($this->allows($this->owner, 'purchase.returns.create'));
        $this->assertFalse($this->allows($this->owner, 'purchase.returns.approve'));
        $this->assertTrue($this->allows($this->owner, 'purchase.orders.create'));
    }

    public function test_nothing_is_gated_when_the_plan_excludes_nothing(): void
    {
        $this->plan->update(['excluded_features' => null]);

        $this->assertTrue($this->allows($this->owner, 'purchase.returns.create'));
    }

    public function test_platform_staff_without_a_tenant_are_never_gated(): void
    {
        $platform = User::create(['tenant_id' => null, 'name' => 'Ops', 'email' => 'ops@platform.test', 'password' => bcrypt('password'), 'role' => 'super_admin']);

        $this->assertTrue($this->allows($platform, 'purchase.returns.create'));
    }

    public function test_a_permission_maps_to_the_longest_matching_feature(): void
    {
        $gate = app(PlanFeatureGate::class);

        $this->assertSame('accounting.vouchers', $gate->featureFor('accounting.vouchers.contra.post'));
        $this->assertSame('hrms.payroll', $gate->featureFor('hr.payroll.view'));
        $this->assertSame('hrms.payroll', $gate->featureFor('hrms.payroll_runs.manage'));
        $this->assertSame('grns', $gate->featureFor('grns.approve'));
        $this->assertSame('fixed_assets', $gate->featureFor('fixed_assets.depreciation.post'));
        $this->assertNull($gate->featureFor('access.users.create'));
    }

    public function test_every_catalog_feature_matches_a_real_permission(): void
    {
        $gate = app(PlanFeatureGate::class);
        $used = \App\Models\Access\Permission::pluck('name')->map(fn (string $name) => $gate->featureFor($name))->filter()->unique();

        $this->assertSame([], array_values(array_diff($gate->keys(), $used->all())), 'Catalog keys with no permission behind them');
    }

    public function test_the_list_screen_opens_but_creating_says_upgrade(): void
    {
        $this->actingAs($this->owner)->withHeader('X-Tenant', 'acme')
            ->get(route('purchase.returns.index'))
            ->assertOk();

        $this->actingAs($this->owner)->withHeader('X-Tenant', 'acme')
            ->get(route('purchase.returns.create'))
            ->assertForbidden()
            ->assertSee('Purchase returns needs an upgrade');

        $this->actingAs($this->owner)->withHeader('X-Tenant', 'acme')
            ->postJson(route('purchase.returns.store'), [])
            ->assertForbidden()
            ->assertJsonPath('plan_feature', 'purchase.returns');
    }

    public function test_a_role_denial_is_not_dressed_up_as_a_plan_upgrade(): void
    {
        $this->plan->update(['excluded_features' => null]);
        $viewer = User::create(['tenant_id' => $this->tenant->id, 'name' => 'Viewer', 'email' => 'viewer@acme.test', 'password' => bcrypt('password')]);
        $role = Role::query()->whereNull('tenant_id')->where('slug', 'read_only')->firstOrFail();
        UserRole::create(['user_id' => $viewer->id, 'role_id' => $role->id, 'tenant_id' => $this->tenant->id]);
        $viewer->forceFill(['role_id' => $role->id])->save();

        $this->actingAs($viewer)->withHeader('X-Tenant', 'acme')
            ->postJson(route('purchase.returns.store'), [])
            ->assertForbidden()
            ->assertJsonMissingPath('plan_feature');
    }
}
