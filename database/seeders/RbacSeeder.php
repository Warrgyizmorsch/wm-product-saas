<?php

namespace Database\Seeders;

use App\Models\Access\Permission;
use App\Models\Access\Role;
use App\Models\Access\RolePermission;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

class RbacSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = $this->seedPermissions();
        $roles = $this->seedRoles();

        $this->grant($roles['super_admin'], $permissions, RolePermission::SCOPE_PLATFORM);

        $this->grant($roles['tenant_owner'], $permissions, RolePermission::SCOPE_TENANT);
        $this->grant($roles['company_admin'], $permissions, RolePermission::SCOPE_TENANT);

        $this->grant($roles['production_manager'], [
            'production.work_center.manage' => $permissions['production.work_center.manage'],
            'production.machine.manage' => $permissions['production.machine.manage'],
            'production.routing.create' => $permissions['production.routing.create'],
            'production.routing.update' => $permissions['production.routing.update'],
            'production.routing.approve' => $permissions['production.routing.approve'],
            'production.routing.cancel' => $permissions['production.routing.cancel'],
            'production.bom.create' => $permissions['production.bom.create'],
            'production.bom.update' => $permissions['production.bom.update'],
            'production.bom.approve' => $permissions['production.bom.approve'],
        ], RolePermission::SCOPE_TENANT);

        $this->grant($roles['production_engineer'], [
            'production.work_center.manage' => $permissions['production.work_center.manage'],
            'production.machine.manage' => $permissions['production.machine.manage'],
            'production.routing.create' => $permissions['production.routing.create'],
            'production.routing.update' => $permissions['production.routing.update'],
            'production.bom.create' => $permissions['production.bom.create'],
            'production.bom.update' => $permissions['production.bom.update'],
        ], RolePermission::SCOPE_TENANT);

        foreach (['production_manager', 'production_engineer'] as $roleSlug) {
            $this->grant($roles[$roleSlug], [
                'production.mes.execute' => $permissions['production.mes.execute'],
                'production.schedule.manage' => $permissions['production.schedule.manage'],
            ], RolePermission::SCOPE_TENANT);
        }

        $this->grant($roles['sales_manager'], [
            'crm.leads.view' => $permissions['crm.leads.view'],
            'crm.leads.create' => $permissions['crm.leads.create'],
            'crm.leads.update' => $permissions['crm.leads.update'],
            'crm.leads.delete' => $permissions['crm.leads.delete'],
            'crm.customers.view' => $permissions['crm.customers.view'],
            'crm.customers.create' => $permissions['crm.customers.create'],
            'crm.customers.update' => $permissions['crm.customers.update'],
            'crm.customers.delete' => $permissions['crm.customers.delete'],
            'crm.quotations.view' => $permissions['crm.quotations.view'],
            'crm.quotations.create' => $permissions['crm.quotations.create'],
            'crm.quotations.update' => $permissions['crm.quotations.update'],
            'crm.quotations.approve' => $permissions['crm.quotations.approve'],
            'crm.quotations.delete' => $permissions['crm.quotations.delete'],
        ], RolePermission::SCOPE_TENANT);

        // "create" abilities have no existing record to own yet, so they're granted at
        // tenant scope — the creator naturally becomes the owner of what they create.
        $this->grant($roles['sales_executive'], [
            'crm.leads.create' => $permissions['crm.leads.create'],
            'crm.quotations.create' => $permissions['crm.quotations.create'],
            'crm.customers.view' => $permissions['crm.customers.view'],
            'crm.customers.create' => $permissions['crm.customers.create'],
        ], RolePermission::SCOPE_TENANT);

        // "view"/"update" are restricted to records this user owns.
        $this->grant($roles['sales_executive'], [
            'crm.leads.view' => $permissions['crm.leads.view'],
            'crm.leads.update' => $permissions['crm.leads.update'],
            'crm.quotations.view' => $permissions['crm.quotations.view'],
        ], RolePermission::SCOPE_OWN);

        $this->grant($roles['inventory_manager'], [
            'inventory.products.view' => $permissions['inventory.products.view'],
            'inventory.products.create' => $permissions['inventory.products.create'],
            'inventory.products.update' => $permissions['inventory.products.update'],
            'inventory.products.delete' => $permissions['inventory.products.delete'],
            'inventory.warehouses.manage' => $permissions['inventory.warehouses.manage'],
            'inventory.uoms.manage' => $permissions['inventory.uoms.manage'],
        ], RolePermission::SCOPE_TENANT);

        $this->grant($roles['sales_manager'], [
            'sales.orders.view' => $permissions['sales.orders.view'],
            'sales.orders.create' => $permissions['sales.orders.create'],
            'sales.orders.update' => $permissions['sales.orders.update'],
            'sales.orders.delete' => $permissions['sales.orders.delete'],
            'sales.orders.confirm' => $permissions['sales.orders.confirm'],
            'sales.orders.cancel' => $permissions['sales.orders.cancel'],
            'sales.deliveries.view' => $permissions['sales.deliveries.view'],
            'sales.deliveries.create' => $permissions['sales.deliveries.create'],
            'sales.deliveries.ship' => $permissions['sales.deliveries.ship'],
            'sales.deliveries.cancel' => $permissions['sales.deliveries.cancel'],
            'sales.dispatches.view' => $permissions['sales.dispatches.view'],
            'sales.dispatches.create' => $permissions['sales.dispatches.create'],
            'sales.invoices.view' => $permissions['sales.invoices.view'],
            'sales.invoices.create' => $permissions['sales.invoices.create'],
            'sales.invoices.send' => $permissions['sales.invoices.send'],
            'sales.payments.view' => $permissions['sales.payments.view'],
            'sales.payments.create' => $permissions['sales.payments.create'],
            'sales.returns.view' => $permissions['sales.returns.view'],
            'sales.returns.create' => $permissions['sales.returns.create'],
            'sales.returns.complete' => $permissions['sales.returns.complete'],
        ], RolePermission::SCOPE_TENANT);

        // Creating an order has no existing record to own yet, so it's granted at
        // tenant scope; deliveries/dispatches/invoices/payments/returns are shared
        // order-to-cash fulfillment work, not personally owned.
        $this->grant($roles['sales_executive'], [
            'sales.orders.create' => $permissions['sales.orders.create'],
            'sales.deliveries.view' => $permissions['sales.deliveries.view'],
            'sales.deliveries.create' => $permissions['sales.deliveries.create'],
            'sales.dispatches.view' => $permissions['sales.dispatches.view'],
            'sales.dispatches.create' => $permissions['sales.dispatches.create'],
            'sales.invoices.view' => $permissions['sales.invoices.view'],
            'sales.invoices.create' => $permissions['sales.invoices.create'],
            'sales.invoices.send' => $permissions['sales.invoices.send'],
            'sales.payments.view' => $permissions['sales.payments.view'],
            'sales.payments.create' => $permissions['sales.payments.create'],
            'sales.returns.view' => $permissions['sales.returns.view'],
            'sales.returns.create' => $permissions['sales.returns.create'],
        ], RolePermission::SCOPE_TENANT);

        // Viewing, updating, confirming, and cancelling orders is restricted to
        // orders this sales rep is assigned as sales_person_id on.
        $this->grant($roles['sales_executive'], [
            'sales.orders.view' => $permissions['sales.orders.view'],
            'sales.orders.update' => $permissions['sales.orders.update'],
            'sales.orders.confirm' => $permissions['sales.orders.confirm'],
            'sales.orders.cancel' => $permissions['sales.orders.cancel'],
        ], RolePermission::SCOPE_OWN);

        // Quick-create is an inline "add a missing product/uom while filling out
        // another form" helper (used from Sales Order and BOM screens) — it's a
        // low-risk additive action, not full catalog management, so it's granted
        // to any role that references products in its own workflow.
        foreach (['production_manager', 'production_engineer', 'sales_manager', 'sales_executive'] as $roleSlug) {
            $this->grant($roles[$roleSlug], [
                'inventory.products.create' => $permissions['inventory.products.create'],
                'inventory.uoms.manage' => $permissions['inventory.uoms.manage'],
            ], RolePermission::SCOPE_TENANT);
        }

        $this->grant($roles['hr_manager'], [
            'hr.settings.manage' => $permissions['hr.settings.manage'],
        ], RolePermission::SCOPE_TENANT);

        // Day-to-day bookkeeping only — deleting accounts/tax rates, closing fiscal
        // years/periods, and reversing posted journals stay reserved for
        // tenant_owner/company_admin (segregation of duties over the ledger).
        $this->grant($roles['accountant'], [
            'accounting.chart_of_accounts.view' => $permissions['accounting.chart_of_accounts.view'],
            'accounting.chart_of_accounts.create' => $permissions['accounting.chart_of_accounts.create'],
            'accounting.chart_of_accounts.update' => $permissions['accounting.chart_of_accounts.update'],
            'accounting.fiscal_years.view' => $permissions['accounting.fiscal_years.view'],
            'accounting.periods.view' => $permissions['accounting.periods.view'],
            'accounting.journals.view' => $permissions['accounting.journals.view'],
            'accounting.journals.post' => $permissions['accounting.journals.post'],
            'accounting.tax_rates.view' => $permissions['accounting.tax_rates.view'],
            'accounting.tax_rates.create' => $permissions['accounting.tax_rates.create'],
            'accounting.tax_rates.update' => $permissions['accounting.tax_rates.update'],
            'accounting.reports.view' => $permissions['accounting.reports.view'],
        ], RolePermission::SCOPE_TENANT);

        // Auditor is a read-only oversight role (existing but previously had zero
        // grants anywhere in this seeder) — financial reports are exactly what it's for.
        $this->grant($roles['auditor'], [
            'accounting.reports.view' => $permissions['accounting.reports.view'],
        ], RolePermission::SCOPE_TENANT);

        $this->assignDemoAdmin($roles['tenant_owner']);
    }

    /**
     * @return array<string, Permission>
     */
    private function seedPermissions(): array
    {
        $definitions = [
            ['name' => 'platform.tenants.manage', 'module' => 'platform', 'entity' => 'tenants', 'action' => 'manage'],
            ['name' => 'access.roles.manage', 'module' => 'access', 'entity' => 'roles', 'action' => 'manage'],
            ['name' => 'access.permissions.manage', 'module' => 'access', 'entity' => 'permissions', 'action' => 'manage'],
            ['name' => 'access.users.manage', 'module' => 'access', 'entity' => 'users', 'action' => 'manage'],
            ['name' => 'production.work_center.manage', 'module' => 'production', 'entity' => 'work_center', 'action' => 'manage'],
            ['name' => 'production.machine.manage', 'module' => 'production', 'entity' => 'machine', 'action' => 'manage'],
            ['name' => 'production.routing.create', 'module' => 'production', 'entity' => 'routing', 'action' => 'create'],
            ['name' => 'production.routing.update', 'module' => 'production', 'entity' => 'routing', 'action' => 'update'],
            ['name' => 'production.routing.approve', 'module' => 'production', 'entity' => 'routing', 'action' => 'approve'],
            ['name' => 'production.routing.cancel', 'module' => 'production', 'entity' => 'routing', 'action' => 'cancel'],
            ['name' => 'production.bom.create', 'module' => 'production', 'entity' => 'bom', 'action' => 'create'],
            ['name' => 'production.bom.update', 'module' => 'production', 'entity' => 'bom', 'action' => 'update'],
            ['name' => 'production.bom.approve', 'module' => 'production', 'entity' => 'bom', 'action' => 'approve'],
            ['name' => 'production.mes.execute', 'module' => 'production', 'entity' => 'mes', 'action' => 'execute'],
            ['name' => 'production.schedule.manage', 'module' => 'production', 'entity' => 'schedule', 'action' => 'manage'],
            ['name' => 'hr.settings.manage', 'module' => 'hr', 'entity' => 'settings', 'action' => 'manage'],
            ['name' => 'audit.logs.view', 'module' => 'audit', 'entity' => 'logs', 'action' => 'view'],
            ['name' => 'crm.leads.view', 'module' => 'crm', 'entity' => 'leads', 'action' => 'view'],
            ['name' => 'crm.leads.create', 'module' => 'crm', 'entity' => 'leads', 'action' => 'create'],
            ['name' => 'crm.leads.update', 'module' => 'crm', 'entity' => 'leads', 'action' => 'update'],
            ['name' => 'crm.leads.delete', 'module' => 'crm', 'entity' => 'leads', 'action' => 'delete'],
            ['name' => 'crm.customers.view', 'module' => 'crm', 'entity' => 'customers', 'action' => 'view'],
            ['name' => 'crm.customers.create', 'module' => 'crm', 'entity' => 'customers', 'action' => 'create'],
            ['name' => 'crm.customers.update', 'module' => 'crm', 'entity' => 'customers', 'action' => 'update'],
            ['name' => 'crm.customers.delete', 'module' => 'crm', 'entity' => 'customers', 'action' => 'delete'],
            ['name' => 'crm.quotations.view', 'module' => 'crm', 'entity' => 'quotations', 'action' => 'view'],
            ['name' => 'crm.quotations.create', 'module' => 'crm', 'entity' => 'quotations', 'action' => 'create'],
            ['name' => 'crm.quotations.update', 'module' => 'crm', 'entity' => 'quotations', 'action' => 'update'],
            ['name' => 'crm.quotations.approve', 'module' => 'crm', 'entity' => 'quotations', 'action' => 'approve'],
            ['name' => 'crm.quotations.delete', 'module' => 'crm', 'entity' => 'quotations', 'action' => 'delete'],
            ['name' => 'inventory.products.view', 'module' => 'inventory', 'entity' => 'products', 'action' => 'view'],
            ['name' => 'inventory.products.create', 'module' => 'inventory', 'entity' => 'products', 'action' => 'create'],
            ['name' => 'inventory.products.update', 'module' => 'inventory', 'entity' => 'products', 'action' => 'update'],
            ['name' => 'inventory.products.delete', 'module' => 'inventory', 'entity' => 'products', 'action' => 'delete'],
            ['name' => 'inventory.warehouses.manage', 'module' => 'inventory', 'entity' => 'warehouses', 'action' => 'manage'],
            ['name' => 'inventory.uoms.manage', 'module' => 'inventory', 'entity' => 'uoms', 'action' => 'manage'],
            ['name' => 'sales.orders.view', 'module' => 'sales', 'entity' => 'orders', 'action' => 'view'],
            ['name' => 'sales.orders.create', 'module' => 'sales', 'entity' => 'orders', 'action' => 'create'],
            ['name' => 'sales.orders.update', 'module' => 'sales', 'entity' => 'orders', 'action' => 'update'],
            ['name' => 'sales.orders.delete', 'module' => 'sales', 'entity' => 'orders', 'action' => 'delete'],
            ['name' => 'sales.orders.confirm', 'module' => 'sales', 'entity' => 'orders', 'action' => 'confirm'],
            ['name' => 'sales.orders.cancel', 'module' => 'sales', 'entity' => 'orders', 'action' => 'cancel'],
            ['name' => 'sales.deliveries.view', 'module' => 'sales', 'entity' => 'deliveries', 'action' => 'view'],
            ['name' => 'sales.deliveries.create', 'module' => 'sales', 'entity' => 'deliveries', 'action' => 'create'],
            ['name' => 'sales.deliveries.ship', 'module' => 'sales', 'entity' => 'deliveries', 'action' => 'ship'],
            ['name' => 'sales.deliveries.cancel', 'module' => 'sales', 'entity' => 'deliveries', 'action' => 'cancel'],
            ['name' => 'sales.dispatches.view', 'module' => 'sales', 'entity' => 'dispatches', 'action' => 'view'],
            ['name' => 'sales.dispatches.create', 'module' => 'sales', 'entity' => 'dispatches', 'action' => 'create'],
            ['name' => 'sales.invoices.view', 'module' => 'sales', 'entity' => 'invoices', 'action' => 'view'],
            ['name' => 'sales.invoices.create', 'module' => 'sales', 'entity' => 'invoices', 'action' => 'create'],
            ['name' => 'sales.invoices.send', 'module' => 'sales', 'entity' => 'invoices', 'action' => 'send'],
            ['name' => 'sales.payments.view', 'module' => 'sales', 'entity' => 'payments', 'action' => 'view'],
            ['name' => 'sales.payments.create', 'module' => 'sales', 'entity' => 'payments', 'action' => 'create'],
            ['name' => 'sales.returns.view', 'module' => 'sales', 'entity' => 'returns', 'action' => 'view'],
            ['name' => 'sales.returns.create', 'module' => 'sales', 'entity' => 'returns', 'action' => 'create'],
            ['name' => 'sales.returns.complete', 'module' => 'sales', 'entity' => 'returns', 'action' => 'complete'],
            ['name' => 'projects.projects.view', 'module' => 'projects', 'entity' => 'projects', 'action' => 'view'],
            ['name' => 'projects.projects.create', 'module' => 'projects', 'entity' => 'projects', 'action' => 'create'],
            ['name' => 'projects.projects.update', 'module' => 'projects', 'entity' => 'projects', 'action' => 'update'],
            ['name' => 'projects.projects.delete', 'module' => 'projects', 'entity' => 'projects', 'action' => 'delete'],
            ['name' => 'projects.members.manage', 'module' => 'projects', 'entity' => 'members', 'action' => 'manage'],
            ['name' => 'projects.milestones.manage', 'module' => 'projects', 'entity' => 'milestones', 'action' => 'manage'],
            ['name' => 'projects.tasklists.manage', 'module' => 'projects', 'entity' => 'tasklists', 'action' => 'manage'],
            ['name' => 'projects.tasks.view', 'module' => 'projects', 'entity' => 'tasks', 'action' => 'view'],
            ['name' => 'projects.tasks.create', 'module' => 'projects', 'entity' => 'tasks', 'action' => 'create'],
            ['name' => 'projects.tasks.update', 'module' => 'projects', 'entity' => 'tasks', 'action' => 'update'],
            ['name' => 'projects.tasks.delete', 'module' => 'projects', 'entity' => 'tasks', 'action' => 'delete'],
            ['name' => 'accounting.chart_of_accounts.view', 'module' => 'accounting', 'entity' => 'chart_of_accounts', 'action' => 'view'],
            ['name' => 'accounting.chart_of_accounts.create', 'module' => 'accounting', 'entity' => 'chart_of_accounts', 'action' => 'create'],
            ['name' => 'accounting.chart_of_accounts.update', 'module' => 'accounting', 'entity' => 'chart_of_accounts', 'action' => 'update'],
            ['name' => 'accounting.chart_of_accounts.delete', 'module' => 'accounting', 'entity' => 'chart_of_accounts', 'action' => 'delete'],
            ['name' => 'accounting.fiscal_years.view', 'module' => 'accounting', 'entity' => 'fiscal_years', 'action' => 'view'],
            ['name' => 'accounting.fiscal_years.create', 'module' => 'accounting', 'entity' => 'fiscal_years', 'action' => 'create'],
            ['name' => 'accounting.fiscal_years.close', 'module' => 'accounting', 'entity' => 'fiscal_years', 'action' => 'close'],
            ['name' => 'accounting.periods.view', 'module' => 'accounting', 'entity' => 'periods', 'action' => 'view'],
            ['name' => 'accounting.periods.manage', 'module' => 'accounting', 'entity' => 'periods', 'action' => 'manage'],
            ['name' => 'accounting.journals.view', 'module' => 'accounting', 'entity' => 'journals', 'action' => 'view'],
            ['name' => 'accounting.journals.post', 'module' => 'accounting', 'entity' => 'journals', 'action' => 'post'],
            ['name' => 'accounting.journals.reverse', 'module' => 'accounting', 'entity' => 'journals', 'action' => 'reverse'],
            ['name' => 'accounting.tax_rates.view', 'module' => 'accounting', 'entity' => 'tax_rates', 'action' => 'view'],
            ['name' => 'accounting.tax_rates.create', 'module' => 'accounting', 'entity' => 'tax_rates', 'action' => 'create'],
            ['name' => 'accounting.tax_rates.update', 'module' => 'accounting', 'entity' => 'tax_rates', 'action' => 'update'],
            ['name' => 'accounting.tax_rates.delete', 'module' => 'accounting', 'entity' => 'tax_rates', 'action' => 'delete'],
            ['name' => 'accounting.reports.view', 'module' => 'accounting', 'entity' => 'reports', 'action' => 'view'],
        ];

        $permissions = [];

        foreach ($definitions as $definition) {
            $permissions[$definition['name']] = Permission::query()->updateOrCreate(
                ['name' => $definition['name']],
                $definition + ['is_system' => true],
            );
        }

        return $permissions;
    }

    /**
     * @return array<string, Role>
     */
    private function seedRoles(): array
    {
        $definitions = [
            ['slug' => 'super_admin', 'name' => 'Super Admin', 'tenant_id' => null, 'level' => 1],
            ['slug' => 'tenant_owner', 'name' => 'Tenant Owner', 'tenant_id' => null, 'level' => 10],
            ['slug' => 'company_admin', 'name' => 'Company Admin', 'tenant_id' => null, 'level' => 20],
            ['slug' => 'production_manager', 'name' => 'Production Manager', 'tenant_id' => null, 'level' => 40],
            ['slug' => 'production_engineer', 'name' => 'Production Engineer', 'tenant_id' => null, 'level' => 50],
            ['slug' => 'sales_manager', 'name' => 'Sales Manager', 'tenant_id' => null, 'level' => 40],
            ['slug' => 'sales_executive', 'name' => 'Sales Executive', 'tenant_id' => null, 'level' => 50],
            ['slug' => 'inventory_manager', 'name' => 'Inventory Manager', 'tenant_id' => null, 'level' => 40],
            ['slug' => 'hr_manager', 'name' => 'HR Manager', 'tenant_id' => null, 'level' => 40],
            ['slug' => 'accountant', 'name' => 'Accountant', 'tenant_id' => null, 'level' => 40],
            ['slug' => 'auditor', 'name' => 'Auditor', 'tenant_id' => null, 'level' => 80],
            ['slug' => 'read_only', 'name' => 'Read Only User', 'tenant_id' => null, 'level' => 90],
        ];

        $roles = [];

        foreach ($definitions as $definition) {
            $roles[$definition['slug']] = Role::query()->updateOrCreate(
                [
                    'tenant_id' => $definition['tenant_id'],
                    'slug' => $definition['slug'],
                ],
                $definition + ['is_system' => true],
            );
        }

        return $roles;
    }

    /**
     * @param array<string, Permission> $permissions
     */
    private function grant(Role $role, array $permissions, string $scope): void
    {
        foreach ($permissions as $permission) {
            RolePermission::query()->updateOrCreate(
                [
                    'role_id' => $role->id,
                    'permission_id' => $permission->id,
                    'scope' => $scope,
                ],
            );
        }
    }

    private function assignDemoAdmin(Role $role): void
    {
        $tenant = Tenant::query()->where('slug', 'demo')->first();
        $user = User::query()
            ->where('email', 'admin@example.com')
            ->when($tenant !== null, fn ($query) => $query->where('tenant_id', $tenant->id))
            ->first();

        if ($tenant === null || $user === null) {
            return;
        }

        UserRole::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'role_id' => $role->id,
                'tenant_id' => $tenant->id,
            ],
        );

        $user->forceFill(['role_id' => $role->id])->save();
    }
}
