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

        $staffLabels = $this->labels($this->menu($this->makeUser('staff2@acme.test', 'sales_executive')));
        $this->assertContains('Tickets', $staffLabels);
        $this->assertNotContains('Knowledge Base', $staffLabels);
        $this->assertNotContains('Employee Exits', $staffLabels);

        // A user with no role at all (so no hrms.self_service.use grant) gets none of the self-service HRMS screens.
        $noRoleLabels = $this->labels($this->menu($this->makeUser('staff3@acme.test', null)));
        $this->assertNotContains('Tickets', $noRoleLabels);
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
        $staff = $this->makeUser('staff@acme.test', 'sales_executive');

        $labels = $this->labels($this->menu($staff));

        $this->assertContains('My Attendance', $labels);
        $this->assertNotContains('HRMS Masters', $labels);
        $this->assertNotContains('Employees Attendance', $labels);
        $this->assertNotContains('Users', $labels);
    }

    public function test_hrms_self_service_screens_need_an_explicit_permission(): void
    {
        $noRoleUser = $this->makeUser('bare@acme.test', null);
        $noRoleLabels = $this->labels($this->menu($noRoleUser));

        foreach (['HRMS Dashboard', 'My Attendance', 'My Assets', 'Leave', 'WFH', 'Shift & Overtime', 'Travel & Expenses', 'Broadcasts', 'Tickets', 'My Payslips'] as $label) {
            $this->assertNotContains($label, $noRoleLabels);
        }

        // Any working-staff role (seeded with hrms.self_service.use) sees the full self-service set.
        $staffUser = $this->makeUser('staffer@acme.test', 'sales_executive');
        $staffLabels = $this->labels($this->menu($staffUser));

        foreach (['HRMS Dashboard', 'My Attendance', 'My Assets', 'Leave', 'WFH', 'Shift & Overtime', 'Travel & Expenses', 'Broadcasts', 'Tickets', 'My Payslips'] as $label) {
            $this->assertContains($label, $staffLabels);
        }

        // An HR admin sees the self-service screens too via hr.settings.manage, without needing the self-service grant itself.
        $hrManager = $this->makeUser('hr-manager@acme.test', 'hr_manager');
        $this->assertContains('HRMS Dashboard', $this->labels($this->menu($hrManager)));
    }

    public function test_accountant_does_not_see_purchase_or_hrms_admin_screens(): void
    {
        $accountant = $this->makeUser('accountant@acme.test', 'accountant');

        $labels = $this->labels($this->menu($accountant));

        $this->assertContains('Chart of Accounts', $labels);

        foreach ([
            'Vendors / Suppliers', 'purchase.rfqs', 'ui.purchase_requests', 'purchase.purchase_orders',
            'purchase.vendor_bills', 'purchase.vendor_payments', 'Purchase Returns', 'Landed Cost Vouchers',
            'GRN (Goods Receipts)', 'Purchase Approvals',
            'HRMS Masters', 'Employees', 'Documents', 'Employees Attendance', 'Employees Assets', 'Payroll Processing',
        ] as $label) {
            $this->assertNotContains($label, $labels);
        }

        // An Accountant is still a working-staff member (hrms.self_service.use is
        // seeded to every functional role), so their own Leave/Attendance/Payslip
        // self-service screens stay visible — just none of the HRMS/Purchase admin screens.
        $this->assertContains('My Attendance', $labels);
        $this->assertContains('My Payslips', $labels);
        $this->assertContains('Leave', $labels);
    }

    public function test_purchase_manager_sees_purchase_and_grn_screens_gated_by_permission(): void
    {
        $manager = $this->makeUser('purchasing@acme.test', 'purchase_manager');

        $labels = $this->labels($this->menu($manager));

        $this->assertContains('Vendors / Suppliers', $labels);
        $this->assertContains('GRN (Goods Receipts)', $labels);
    }

    public function test_crm_and_sales_entries_are_gated_by_permission(): void
    {
        // sales_executive holds crm.leads.view and sales.orders.view only at
        // SCOPE_OWN (RbacSeeder) — this also exercises MenuBuilder::permitted()
        // supplying the user's own id/branch/department/company as context, since
        // a sidebar link has no specific record to check an OWN-scoped grant against.
        $salesExecutive = $this->makeUser('sales-exec@acme.test', 'sales_executive');
        $salesLabels = $this->labels($this->menu($salesExecutive));

        foreach (['Leads', 'Customers', 'Quotations', 'Sales Orders', 'Invoices', 'Receipts (Payments)', 'Sales Returns'] as $label) {
            $this->assertContains($label, $salesLabels);
        }

        $accountant = $this->makeUser('accountant-crm@acme.test', 'accountant');
        $accountantLabels = $this->labels($this->menu($accountant));

        foreach (['Leads', 'Customers', 'Quotations', 'Sales Orders', 'Invoices', 'Receipts (Payments)', 'Sales Returns'] as $label) {
            $this->assertNotContains($label, $accountantLabels);
        }
    }

    public function test_inventory_entries_are_gated_by_permission(): void
    {
        $inventoryManager = $this->makeUser('inventory-mgr@acme.test', 'inventory_manager');
        $inventoryLabels = $this->labels($this->menu($inventoryManager));

        $this->assertContains('Products', $inventoryLabels);
        $this->assertContains('Warehouses', $inventoryLabels);

        $accountant = $this->makeUser('accountant-inv@acme.test', 'accountant');
        $accountantLabels = $this->labels($this->menu($accountant));

        $this->assertNotContains('Products', $accountantLabels);
        $this->assertNotContains('Warehouses', $accountantLabels);
    }

    public function test_accounting_entries_need_accounting_permission(): void
    {
        $accountant = $this->makeUser('accountant-acc@acme.test', 'accountant');
        $accountantLabels = $this->labels($this->menu($accountant));

        foreach (['Chart of Accounts', 'Journals', 'Payment Vouchers', 'Trial Balance'] as $label) {
            $this->assertContains($label, $accountantLabels);
        }

        $productionManager = $this->makeUser('production-acc@acme.test', 'production_manager');
        $productionLabels = $this->labels($this->menu($productionManager));

        foreach (['Chart of Accounts', 'Journals', 'Payment Vouchers', 'Trial Balance'] as $label) {
            $this->assertNotContains($label, $productionLabels);
        }
    }

    public function test_production_entries_are_gated_by_permission(): void
    {
        $productionManager = $this->makeUser('production-mgr@acme.test', 'production_manager');
        $productionLabels = $this->labels($this->menu($productionManager));

        foreach (['Production Dashboard', 'Quality Dashboard', 'NCR', 'Shop Floor (MES)', 'Live Andon Board'] as $label) {
            $this->assertContains($label, $productionLabels);
        }

        $accountant = $this->makeUser('accountant-prod@acme.test', 'accountant');
        $accountantLabels = $this->labels($this->menu($accountant));

        foreach (['Production Dashboard', 'Quality Dashboard', 'NCR', 'Shop Floor (MES)'] as $label) {
            $this->assertNotContains($label, $accountantLabels);
        }
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
