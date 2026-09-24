<?php

namespace Tests\Feature;

use App\Core\Dashboard\WidgetRegistry;
use App\Core\Tenant\Events\TenantProvisioning;
use App\Core\Tenant\TenantContext;
use App\Domains\Platform\Listeners\ProvisionDashboardLayout;
use App\Domains\Platform\Models\DashboardLayout;
use App\Domains\Platform\Services\DashboardService;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommonDashboardTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
        $this->tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme', 'status' => 'active', 'plan' => 'enterprise']);
        app(TenantContext::class)->set($this->tenant);
        $this->owner = $this->makeUser('owner@acme.test', 'tenant_owner');
        $this->withHeader('X-Tenant', 'acme');
    }

    private function makeUser(string $email, ?string $roleSlug, ?Tenant $tenant = null): User
    {
        $tenant ??= $this->tenant;
        $user = User::create(['tenant_id' => $tenant->id, 'name' => $email, 'email' => $email, 'password' => bcrypt('password')]);

        if ($roleSlug !== null) {
            UserRole::create([
                'user_id' => $user->id,
                'role_id' => Role::query()->whereNull('tenant_id')->where('slug', $roleSlug)->firstOrFail()->id,
                'tenant_id' => $tenant->id,
            ]);
        }

        return $user;
    }

    private function item(string $key, int $x = 0, int $y = 0): array
    {
        return ['id' => $key, 'key' => $key, 'x' => $x, 'y' => $y, 'w' => 3, 'h' => 2, 'config' => []];
    }

    public function test_every_widget_declares_a_valid_definition(): void
    {
        foreach (app(WidgetRegistry::class)->all() as $key => $widget) {
            $this->assertArrayHasKey('permission', $widget, "$key must declare a permission (null only for user-scoped data)");
            $this->assertContains($widget['type'], WidgetRegistry::TYPES, $key);
            $this->assertIsCallable($widget['data'], $key);
            $this->assertNotEmpty($widget['module'], $key);
        }
    }

    public function test_every_widget_returns_data_for_a_tenant_owner(): void
    {
        foreach (app(WidgetRegistry::class)->all() as $key => $widget) {
            $response = $this->actingAs($this->owner)->getJson(route('dashboard.widget', $key).'?dashboard='.$widget['dashboards'][0]);

            $response->assertOk();
            $this->assertSame($widget['type'], $response->json('type'), $key);
        }
    }

    public function test_every_widget_survives_filters_and_the_all_companies_scope(): void
    {
        foreach (app(WidgetRegistry::class)->all() as $key => $widget) {
            foreach (['preset=last_month&scope=all&limit=5', 'preset=today', 'preset=custom&from=2026-01-01&to=2026-03-31', 'preset=fiscal_year&limit=20'] as $query) {
                $this->actingAs($this->owner)->getJson(route('dashboard.widget', $key).'?dashboard='.$widget['dashboards'][0].'&'.$query)->assertOk();
            }
        }
    }

    public function test_widget_filters_are_validated(): void
    {
        $url = route('dashboard.widget', 'crm.open_leads');

        $this->actingAs($this->owner)->getJson($url.'?preset=next_decade')->assertStatus(422);
        $this->actingAs($this->owner)->getJson($url.'?scope=everywhere')->assertStatus(422);
        $this->actingAs($this->owner)->getJson($url.'?limit=1')->assertStatus(422);
        $this->actingAs($this->owner)->getJson($url.'?limit=500')->assertStatus(422);
    }

    public function test_widget_settings_are_kept_only_when_the_widget_supports_them(): void
    {
        $this->actingAs($this->owner)->putJson(route('dashboard.layout.save'), [
            'scope' => 'personal',
            'widgets' => [
                // supports period + limit
                ['key' => 'crm.sales_leaderboard', 'config' => ['title' => 'Top sellers', 'period' => 'last_month', 'limit' => 999]],
                // supports neither: period and limit are dropped, the title stays
                ['key' => 'accounting.cash', 'config' => ['title' => 'Bank', 'period' => 'last_month', 'limit' => 5]],
                // unknown period is dropped
                ['key' => 'crm.open_leads', 'config' => ['period' => 'whenever']],
            ],
        ])->assertOk();

        $widgets = DashboardLayout::query()->where('user_id', $this->owner->id)->firstOrFail()->widgets;
        $byKey = array_column($widgets, 'config', 'key');

        $this->assertSame(['title' => 'Top sellers', 'period' => 'last_month', 'limit' => 20], $byKey['crm.sales_leaderboard']);
        $this->assertSame(['title' => 'Bank'], $byKey['accounting.cash']);
        $this->assertSame([], $byKey['crm.open_leads']);
    }

    public function test_provisioning_seeds_a_default_layout_from_the_plan_and_never_overwrites_it(): void
    {
        $listener = app(ProvisionDashboardLayout::class);
        $tenantDefault = fn () => DashboardLayout::query()->whereNull('user_id')->whereNull('role_id')->get();

        $listener->handle(new TenantProvisioning($this->tenant->id, 1, 1, ['crm']));

        $this->assertCount(1, $tenantDefault());
        $keys = array_column($tenantDefault()->first()->widgets, 'key');
        $this->assertContains('crm.open_leads', $keys);
        $this->assertNotContains('accounting.cash', $keys);

        // A later run (plan change, `tenant:provision`) leaves the owner's layout alone.
        $listener->handle(new TenantProvisioning($this->tenant->id, 1, 1, null));

        $this->assertCount(1, $tenantDefault());
        $this->assertSame($keys, array_column($tenantDefault()->first()->widgets, 'key'));
    }

    public function test_each_dashboard_only_offers_its_own_widgets(): void
    {
        $registry = app(WidgetRegistry::class);

        $common = array_keys($registry->availableFor($this->owner, $this->tenant->id, 'common'));
        $accounting = array_keys($registry->availableFor($this->owner, $this->tenant->id, 'accounting'));

        $this->assertContains('crm.open_leads', $common);
        $this->assertNotContains('accounting.month_end_close', $common);
        $this->assertContains('accounting.month_end_close', $accounting);
        $this->assertNotContains('crm.open_leads', $accounting);

        // A page block cannot be fetched through the wrong dashboard.
        $this->actingAs($this->owner)->getJson(route('dashboard.widget', 'accounting.month_end_close'))->assertNotFound();
        $this->actingAs($this->owner)->getJson(route('dashboard.widget', 'accounting.month_end_close').'?dashboard=accounting')->assertOk();
    }

    public function test_html_widgets_return_markup_and_chart_specs(): void
    {
        $response = $this->actingAs($this->owner)
            ->getJson(route('dashboard.widget', 'accounting.trend_detail').'?dashboard=accounting')
            ->assertOk();

        $this->assertSame('html', $response->json('type'));
        $this->assertStringContainsString('Income vs Expense', $response->json('html'));
        $this->assertIsArray($response->json('charts'));
    }

    public function test_layouts_are_kept_separately_per_dashboard(): void
    {
        $service = app(DashboardService::class);

        $service->save($this->owner, $this->tenant->id, [$this->item('accounting.gst_tds'), $this->item('crm.open_leads')], 'personal', null, 'accounting');

        // The accounting layout keeps only what belongs on the accounting dashboard.
        $accounting = $service->resolve($this->owner, $this->tenant->id, 'accounting');
        $this->assertSame('personal', $accounting['source']);
        $this->assertSame(['accounting.gst_tds'], array_column($accounting['widgets'], 'key'));

        // The common dashboard is untouched.
        $this->assertSame('starter', $service->resolve($this->owner, $this->tenant->id, 'common')['source']);

        // Resetting one dashboard leaves the other alone.
        $service->save($this->owner, $this->tenant->id, [$this->item('crm.open_leads')], 'personal', null, 'common');
        $service->reset($this->owner, 'accounting');
        $this->assertSame('starter', $service->resolve($this->owner, $this->tenant->id, 'accounting')['source']);
        $this->assertSame('personal', $service->resolve($this->owner, $this->tenant->id, 'common')['source']);
    }

    public function test_accounting_starter_layout_follows_the_users_role(): void
    {
        $service = app(DashboardService::class);
        $keys = fn (string $starter) => array_column($service->resolve($this->owner, $this->tenant->id, 'accounting', $starter)['widgets'], 'key');

        $this->assertContains('accounting.financial_health', $keys('overview'));
        $this->assertNotContains('accounting.month_end_close', $keys('overview'));
        $this->assertContains('accounting.month_end_close', $keys('operations'));
        $this->assertNotContains('accounting.financial_health', $keys('operations'));
    }

    public function test_only_managers_can_save_the_accounting_default_and_it_reaches_other_users(): void
    {
        $plain = $this->makeUser('plain@acme.test', 'accountant');
        $payload = ['dashboard' => 'accounting', 'scope' => 'tenant', 'widgets' => [$this->item('accounting.budget_alerts')]];

        $this->actingAs($plain)->putJson(route('dashboard.layout.save'), $payload)->assertForbidden();
        $this->actingAs($this->owner)->putJson(route('dashboard.layout.save'), $payload)->assertOk();

        $resolved = app(DashboardService::class)->resolve($plain, $this->tenant->id, 'accounting');
        $this->assertSame('tenant', $resolved['source']);
        $this->assertSame(['accounting.budget_alerts'], array_column($resolved['widgets'], 'key'));
    }

    public function test_the_accounting_page_renders_every_block_of_both_layouts(): void
    {
        foreach (['overview', 'operations'] as $view) {
            $response = $this->actingAs($this->owner)->get(route('accounting.dashboard', ['view' => $view]))->assertOk();

            $layout = $response->viewData('layout');
            $initial = $response->viewData('initial');

            $this->assertNotEmpty($layout, $view);
            foreach ($layout as $item) {
                $this->assertNotNull($initial[$item['id']], "$view: {$item['key']} failed to render");
                $this->assertSame('html', $initial[$item['id']]['type']);
                $this->assertNotSame('', trim($initial[$item['id']]['html']), $item['key']);
            }
        }

        // Both ready-made layouts are offered for loading into the editor.
        $this->assertSame(['overview', 'operations'], array_keys($response->viewData('layoutPresets')));
    }

    public function test_cost_center_filter_must_belong_to_the_tenant(): void
    {
        $this->actingAs($this->owner)
            ->getJson(route('dashboard.widget', 'accounting.kpi_revenue').'?dashboard=accounting&cost_center_id=999999')
            ->assertStatus(422);
    }

    public function test_a_user_switched_into_another_tenant_keeps_their_widgets(): void
    {
        $other = Tenant::create(['name' => 'Other', 'slug' => 'other', 'status' => 'active', 'plan' => 'enterprise']);
        app(TenantContext::class)->set($other);

        $keys = array_keys(app(WidgetRegistry::class)->availableFor($this->owner, $other->id));

        $this->assertContains('crm.open_leads', $keys);
        $this->assertContains('accounting.cash', $keys);
    }

    public function test_dashboard_page_renders(): void
    {
        $this->actingAs($this->owner)->get(route('dashboard'))->assertOk()->assertSee('dash-grid', false);
    }

    public function test_a_user_without_permission_cannot_fetch_or_save_a_widget(): void
    {
        $plain = $this->makeUser('plain@acme.test', null);

        $this->actingAs($plain)->getJson(route('dashboard.widget', 'accounting.cash'))->assertNotFound();
        $this->actingAs($plain)->getJson(route('dashboard.widget', 'nope.nothing'))->assertNotFound();

        $this->actingAs($plain)->putJson(route('dashboard.layout.save'), [
            'scope' => 'personal',
            'widgets' => [$this->item('accounting.cash'), $this->item('platform.pending_approvals')],
        ])->assertOk();

        $keys = array_column(DashboardLayout::query()->where('user_id', $plain->id)->firstOrFail()->widgets, 'key');
        $this->assertSame(['platform.pending_approvals'], $keys);
    }

    public function test_layout_resolution_prefers_personal_then_role_then_tenant_then_starter(): void
    {
        $service = app(DashboardService::class);
        $keys = fn () => array_column($service->resolve($this->owner, $this->tenant->id)['widgets'], 'key');
        $source = fn () => $service->resolve($this->owner, $this->tenant->id)['source'];

        $this->assertSame('starter', $source());
        $this->assertNotEmpty($keys());

        $service->save($this->owner, $this->tenant->id, [$this->item('crm.open_leads')], 'tenant');
        $this->assertSame(['crm.open_leads'], $keys());
        $this->assertSame('tenant', $source());

        $ownerRole = Role::query()->whereNull('tenant_id')->where('slug', 'tenant_owner')->firstOrFail();
        $service->save($this->owner, $this->tenant->id, [$this->item('hrms.headcount')], 'role', $ownerRole->id);
        $this->assertSame(['hrms.headcount'], $keys());
        $this->assertSame('role', $source());

        $service->save($this->owner, $this->tenant->id, [$this->item('accounting.cash')], 'personal');
        $this->assertSame(['accounting.cash'], $keys());
        $this->assertSame('personal', $source());

        $service->reset($this->owner);
        $this->assertSame('role', $source());
    }

    public function test_only_managers_can_save_shared_defaults(): void
    {
        $plain = $this->makeUser('plain@acme.test', null);
        $payload = ['scope' => 'tenant', 'widgets' => [$this->item('platform.pending_approvals')]];

        $this->actingAs($plain)->putJson(route('dashboard.layout.save'), $payload)->assertForbidden();
        $this->actingAs($this->owner)->putJson(route('dashboard.layout.save'), $payload)->assertOk();
    }

    public function test_layouts_never_cross_tenants(): void
    {
        $other = Tenant::create(['name' => 'Other', 'slug' => 'other', 'status' => 'active', 'plan' => 'enterprise']);
        DashboardLayout::withoutGlobalScopes()->create([
            'tenant_id' => $other->id,
            'widgets' => [$this->item('crm.open_leads')],
        ]);

        $resolved = app(DashboardService::class)->resolve($this->owner, $this->tenant->id);

        $this->assertSame('starter', $resolved['source']);
    }

    public function test_save_clamps_geometry_and_drops_unknown_widgets(): void
    {
        $this->actingAs($this->owner)->putJson(route('dashboard.layout.save'), [
            'scope' => 'personal',
            'widgets' => [
                ['key' => 'crm.open_leads', 'x' => 99, 'y' => -4, 'w' => 500, 'h' => 0],
                ['key' => 'made.up', 'x' => 0, 'y' => 0, 'w' => 3, 'h' => 2],
            ],
        ])->assertOk();

        $widgets = DashboardLayout::query()->where('user_id', $this->owner->id)->firstOrFail()->widgets;

        $this->assertCount(1, $widgets);
        $this->assertSame(12, $widgets[0]['w']);
        $this->assertSame(0, $widgets[0]['x']);
        $this->assertSame(0, $widgets[0]['y']);
        $this->assertSame(1, $widgets[0]['h']);
    }
}
