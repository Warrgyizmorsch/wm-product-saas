<?php

namespace Tests\Feature;

use App\Core\Navigation\MenuBuilder;
use App\Core\Tenant\TenantContext;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The app-style sidebar: inside CRM you see only CRM's menu, opened on the page you are on. */
class AppMenuTest extends TestCase
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

    private function nav(?User $user, ?string $route): array
    {
        return app(MenuBuilder::class)->navigation($user, $route);
    }

    /** @return list<string> every route the app's menu links to */
    private function routes(array $nav): array
    {
        $routes = [];
        foreach ($nav['items'] as $item) {
            $routes[] = $item['route'];
            foreach ($item['children'] as $child) {
                $routes[] = $child['route'];
            }
        }

        return array_values(array_filter($routes));
    }

    public function test_inside_crm_only_crm_menus_are_listed_and_the_current_group_is_open(): void
    {
        $nav = $this->nav($this->owner, 'crm.leads.index');

        $this->assertSame('crm', $nav['app']);
        $this->assertNotEmpty($nav['items']);

        foreach ($nav['items'] as $item) {
            $this->assertSame('crm', $item['app'], $item['label']);
        }

        $routes = $this->routes($nav);
        $this->assertContains('crm.leads.index', $routes);
        $this->assertContains('crm.dashboard', $routes);
        $this->assertNotContains('inventory.products.index', $routes);
        $this->assertNotContains('production.orders.index', $routes);
        $this->assertNotContains('accounting.dashboard', $routes);

        // The current page is highlighted, and its screens are listed directly (no "CRM" group inside the CRM app).
        $active = collect($nav['items'])->filter(fn (array $item) => $item['active']);
        $this->assertCount(1, $active);
        $this->assertSame('crm.leads.index', $active->first()['route']);
    }

    public function test_a_group_named_like_its_app_is_replaced_by_its_screens(): void
    {
        foreach (['crm.leads.index' => 'CRM', 'purchase.orders.index' => 'Purchase', 'inventory.products.index' => 'Inventory', 'sales.orders.index' => 'Sales'] as $route => $app) {
            $nav = $this->nav($this->owner, $route);

            foreach ($nav['items'] as $item) {
                $this->assertFalse($item['children'] !== [] && strcasecmp($item['label'], $app) === 0, "$route still lists a '$app' group inside the $app app");
            }
        }

        // Other groups stay grouped: an active page inside one keeps that group highlighted.
        $active = collect($this->nav($this->owner, 'purchase.pr-approvals.index')['items'])->filter(fn (array $item) => $item['active']);
        $this->assertCount(1, $active);
        $this->assertNotEmpty($active->first()['children']);
    }

    public function test_each_module_page_selects_its_own_app(): void
    {
        $expected = [
            'crm.dashboard' => 'crm',
            'crm.quotations.index' => 'sales',         // Quotations are listed under Sales
            'sales.orders.index' => 'sales',
            'supply-chain.dashboard' => 'inventory',
            'inventory.products.index' => 'inventory',
            'purchase.orders.index' => 'purchase',
            'grns.index' => 'purchase',
            'production.orders.index' => 'production',
            'hrms.dashboard' => 'hrms',
            'accounting.dashboard' => 'accounting',
            'projects.index' => 'projects',
            'access.users.index' => 'admin',
            'platform.plans.index' => 'admin',
        ];

        foreach ($expected as $route => $app) {
            $this->assertSame($app, $this->nav($this->owner, $route)['app'], $route);
        }
    }

    public function test_a_page_outside_the_menu_still_belongs_to_its_apps_prefix(): void
    {
        $this->assertSame('hrms', $this->nav($this->owner, 'hrms.somewhere.unlisted')['app']);
        $this->assertSame('crm', $this->nav($this->owner, 'crm.leads.create')['app']);
    }

    public function test_workspace_pages_show_the_whole_menu(): void
    {
        foreach (['dashboard', 'home', 'notifications.index', null] as $route) {
            $nav = $this->nav($this->owner, $route);

            $this->assertNull($nav['app'], (string) $route);
            $this->assertSame([], $nav['items']);
            $this->assertNotEmpty($nav['sections'], (string) $route);
        }
    }

    public function test_users_only_get_the_apps_their_role_reaches(): void
    {
        $all = array_keys($this->nav($this->owner, 'dashboard')['apps']);
        foreach (['crm', 'sales', 'inventory', 'purchase', 'production', 'hrms', 'accounting'] as $app) {
            $this->assertContains($app, $all, $app);
        }

        // No role: no business app. (Administration remains only because three Tenant Console
        // links — Payment Terms, Email & SMTP, WhatsApp — carry no permission in menu.php today.)
        $none = $this->makeUser('plain@acme.test', null);
        $this->assertSame(['admin'], array_keys($this->nav($none, 'dashboard')['apps']));
        $this->assertNull($this->nav($none, 'crm.dashboard')['app']);

        $sales = array_keys($this->nav($this->makeUser('sales@acme.test', 'sales_manager'), 'dashboard')['apps']);
        $this->assertContains('crm', $sales);
        $this->assertNotContains('production', $sales);
        $this->assertNotContains('accounting', $sales);
    }

    public function test_each_app_opens_on_its_first_screen(): void
    {
        $apps = $this->nav($this->owner, 'dashboard')['apps'];

        $this->assertSame(route('crm.dashboard'), $apps['crm']['url']);
        $this->assertSame(route('supply-chain.dashboard'), $apps['inventory']['url']);

        foreach ($apps as $key => $app) {
            $this->assertNotSame('#', $app['url'], $key);
        }
    }

    public function test_the_current_app_is_marked_in_the_app_list(): void
    {
        $apps = $this->nav($this->owner, 'crm.leads.index')['apps'];

        $this->assertTrue($apps['crm']['active']);
        $this->assertFalse($apps['sales']['active']);
    }

    public function test_the_sidebar_and_launcher_render_for_both_modes(): void
    {
        // The sidebar's app-context button opens its own flat app-switcher list
        // (id="app-switcher-list"), separate from the header's own "Modules"
        // dropdown (id="mega-menu-dropdown") — kept apart so the two never
        // collide as duplicate ids on the same page.
        $crm = $this->actingAs($this->owner)->get(route('crm.dashboard'))->assertOk();
        $crm->assertSee('data-nav-app="crm"', false);
        $crm->assertSee('app-switcher-toggle', false);
        $crm->assertSee('app-switcher-list', false);

        // Not assertDontSee('app-switcher-list', ...): the id also appears inside the
        // page's own <script> (a getElementById call), which renders unconditionally.
        $home = $this->actingAs($this->owner)->get(route('dashboard'))->assertOk();
        $home->assertDontSee('data-nav-app=', false);
        $home->assertSee(route('apps'), false);
    }
}
