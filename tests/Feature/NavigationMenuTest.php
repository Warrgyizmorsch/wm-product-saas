<?php

namespace Tests\Feature;

use App\Core\Navigation\MenuBuilder;
use App\Core\Navigation\MenuRegistry;
use App\Core\Tenant\TenantContext;
use App\Domains\Platform\Models\Plan;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationMenuTest extends TestCase
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

    private function menu(?User $user, ?string $currentRoute = null): array
    {
        return app(MenuBuilder::class)->build($user, $currentRoute);
    }

    /** @return list<string> every section, item and child label */
    private function labels(array $sections): array
    {
        $labels = [];
        foreach ($sections as $section) {
            $labels[] = $section['label'];
            foreach ($section['items'] as $item) {
                $labels[] = $item['label'];
                foreach ($item['children'] as $child) {
                    $labels[] = $child['label'];
                }
            }
        }

        return $labels;
    }

    private function find(array $sections, string $label): ?array
    {
        foreach ($sections as $section) {
            foreach ($section['items'] as $item) {
                if ($item['label'] === $label) {
                    return $item;
                }
                foreach ($item['children'] as $child) {
                    if ($child['label'] === $label) {
                        return $child;
                    }
                }
            }
        }

        return null;
    }

    public function test_tenant_owner_sees_every_module_but_not_the_platform_console(): void
    {
        $labels = $this->labels($this->menu($this->owner));

        $this->assertContains('Chart of Accounts', $labels);
        $this->assertContains('Production Orders', $labels);
        $this->assertContains('HRMS Masters', $labels);
        $this->assertContains('Users', $labels);
        $this->assertNotContains('Tenants', $labels);
        $this->assertNotContains('Plans', $labels);
    }

    public function test_screens_that_had_no_menu_link_are_listed_for_users_allowed_to_open_them(): void
    {
        $ownerLabels = $this->labels($this->menu($this->owner));

        foreach (['Audit Logs', 'Posting Failures', 'Activities', 'Employee Exits', 'Advance Payments', 'Knowledge Base', 'Lot Traceability', 'Quality Plans', 'KPI Targets'] as $label) {
            $this->assertContains($label, $ownerLabels);
        }

        $staffLabels = $this->labels($this->menu($this->makeUser('staff2@acme.test', null)));
        $this->assertContains('Tickets', $staffLabels);
        $this->assertNotContains('Knowledge Base', $staffLabels);
        $this->assertNotContains('Employee Exits', $staffLabels);
    }

    public function test_modules_outside_the_tenants_plan_are_hidden(): void
    {
        $plan = Plan::create(['name' => 'CRM Only', 'slug' => 'crm-only', 'features' => ['crm'], 'is_active' => true]);
        $this->tenant->forceFill(['plan_id' => $plan->id])->save();
        app(TenantContext::class)->set($this->tenant->fresh());

        $labels = $this->labels($this->menu($this->owner));

        $this->assertContains('Track Status', $labels);
        $this->assertNotContains('Chart of Accounts', $labels);
        $this->assertNotContains('Production Orders', $labels);
        // A group pinned to a module disappears whole, including its non-gated child.
        $this->assertNotContains('Transporters Master', $labels);
    }

    public function test_hr_admin_entries_need_an_hr_admin_permission(): void
    {
        $employee = $this->makeUser('staff@acme.test', null);

        $labels = $this->labels($this->menu($employee));

        $this->assertContains('My Attendance', $labels);
        $this->assertNotContains('HRMS Masters', $labels);
        $this->assertNotContains('Employees Attendance', $labels);
        $this->assertNotContains('Users', $labels);
    }

    public function test_placeholders_are_hidden_unless_switched_on(): void
    {
        $this->assertNotContains('Subscriptions', $this->labels($this->menu($this->owner)));
        $this->assertNotContains('Reports & BI', $this->labels($this->menu($this->owner)));

        config(['navigation.show_placeholders' => true]);

        $this->assertContains('Subscriptions', $this->labels($this->menu($this->owner)));
    }

    public function test_entries_for_missing_routes_are_dropped_and_labels_fall_back_to_their_default(): void
    {
        $registry = app(MenuRegistry::class);
        $registry->add(['section' => 'workspace', 'order' => 99, 'label' => 'nav.no_such_key', 'default' => 'Fallback Label', 'route' => 'dashboard']);
        $registry->add(['section' => 'workspace', 'order' => 99, 'label' => 'Ghost Screen', 'route' => 'no.such.route']);

        $labels = $this->labels($this->menu($this->owner));

        $this->assertContains('Fallback Label', $labels);
        $this->assertNotContains('Ghost Screen', $labels);
    }

    public function test_detail_pages_highlight_their_menu_entry(): void
    {
        $onLead = $this->menu($this->owner, 'crm.leads.show');
        $this->assertTrue($this->find($onLead, 'Leads')['active']);
        $this->assertTrue($this->find($onLead, 'CRM')['active']);
        $this->assertFalse($this->find($onLead, 'Track Status')['active']);

        $onTrackStatus = $this->menu($this->owner, 'crm.leads.trackStatus');
        $this->assertTrue($this->find($onTrackStatus, 'Track Status')['active']);
        $this->assertFalse($this->find($onTrackStatus, 'Leads')['active']);

        $this->assertTrue($this->find($this->menu($this->owner, 'home'), 'Executive Dashboard')['active']);
    }

    public function test_sidebar_renders_the_built_menu(): void
    {
        $this->actingAs($this->owner)
            ->withHeader('X-Tenant', 'acme')
            ->get(route('accounting.chart-of-accounts.index'))
            ->assertOk()
            ->assertSee('Chart of Accounts')
            ->assertSee(route('accounting.reports.trial-balance'), false)
            ->assertDontSee('Subscriptions');
    }
}
