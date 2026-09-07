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
            'production.planning.create' => $permissions['production.planning.create'],
            'production.planning.update' => $permissions['production.planning.update'],
            'production.planning.approve' => $permissions['production.planning.approve'],
            'production.planning.cancel' => $permissions['production.planning.cancel'],
            'production.order.create' => $permissions['production.order.create'],
            'production.order.update' => $permissions['production.order.update'],
            'production.order.cancel' => $permissions['production.order.cancel'],
            'production.intelligence.view' => $permissions['production.intelligence.view'],
            'production.quality.manage' => $permissions['production.quality.manage'],
            'production.quality.approve' => $permissions['production.quality.approve'],
            'production.cost_adjustment.create' => $permissions['production.cost_adjustment.create'],
            'production.cost_adjustment.update' => $permissions['production.cost_adjustment.update'],
        ], RolePermission::SCOPE_TENANT);

        $this->grant($roles['production_engineer'], [
            'production.work_center.manage' => $permissions['production.work_center.manage'],
            'production.machine.manage' => $permissions['production.machine.manage'],
            'production.routing.create' => $permissions['production.routing.create'],
            'production.routing.update' => $permissions['production.routing.update'],
            'production.bom.create' => $permissions['production.bom.create'],
            'production.bom.update' => $permissions['production.bom.update'],
            'production.planning.create' => $permissions['production.planning.create'],
            'production.planning.update' => $permissions['production.planning.update'],
            'production.order.create' => $permissions['production.order.create'],
            'production.order.update' => $permissions['production.order.update'],
            'production.intelligence.view' => $permissions['production.intelligence.view'],
            'production.quality.manage' => $permissions['production.quality.manage'],
            'production.cost_adjustment.create' => $permissions['production.cost_adjustment.create'],
            'production.cost_adjustment.update' => $permissions['production.cost_adjustment.update'],
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
            'inventory.material_requirements.view' => $permissions['inventory.material_requirements.view'],
            'inventory.material_requirements.ship' => $permissions['inventory.material_requirements.ship'],
            'inventory.dispatches.view' => $permissions['inventory.dispatches.view'],
            'inventory.dispatches.create' => $permissions['inventory.dispatches.create'],
            'sales.material_requirements.view' => $permissions['sales.material_requirements.view'],
            'sales.material_requirements.ship' => $permissions['sales.material_requirements.ship'],
        ], RolePermission::SCOPE_TENANT);

        $this->grant($roles['sales_manager'], [
            'sales.orders.view' => $permissions['sales.orders.view'],
            'sales.orders.create' => $permissions['sales.orders.create'],
            'sales.orders.update' => $permissions['sales.orders.update'],
            'sales.orders.delete' => $permissions['sales.orders.delete'],
            'sales.orders.confirm' => $permissions['sales.orders.confirm'],
            'sales.orders.cancel' => $permissions['sales.orders.cancel'],
            'sales.material_requirements.view' => $permissions['sales.material_requirements.view'],
            'sales.material_requirements.create' => $permissions['sales.material_requirements.create'],
            'sales.material_requirements.ship' => $permissions['sales.material_requirements.ship'],
            'sales.material_requirements.cancel' => $permissions['sales.material_requirements.cancel'],
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
            'sales.material_requirements.view' => $permissions['sales.material_requirements.view'],
            'sales.material_requirements.create' => $permissions['sales.material_requirements.create'],
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

        $this->grant($roles['purchase_manager'], [
            'purchase.orders.view' => $permissions['purchase.orders.view'],
            'purchase.orders.create' => $permissions['purchase.orders.create'],
            'purchase.orders.edit' => $permissions['purchase.orders.edit'],
            'purchase.orders.delete' => $permissions['purchase.orders.delete'],
            'purchase.orders.approve' => $permissions['purchase.orders.approve'],
            'purchase.requisitions.view' => $permissions['purchase.requisitions.view'],
            'purchase.requisitions.create' => $permissions['purchase.requisitions.create'],
            'purchase.requisitions.edit' => $permissions['purchase.requisitions.edit'],
            'purchase.requisitions.delete' => $permissions['purchase.requisitions.delete'],
            'purchase.requisitions.approve' => $permissions['purchase.requisitions.approve'],
            'purchase.rfqs.view' => $permissions['purchase.rfqs.view'],
            'purchase.rfqs.create' => $permissions['purchase.rfqs.create'],
            'purchase.rfqs.edit' => $permissions['purchase.rfqs.edit'],
            'purchase.rfqs.delete' => $permissions['purchase.rfqs.delete'],
            'purchase.bills.view' => $permissions['purchase.bills.view'],
            'purchase.bills.create' => $permissions['purchase.bills.create'],
            'purchase.bills.edit' => $permissions['purchase.bills.edit'],
            'purchase.approvals.manage' => $permissions['purchase.approvals.manage'],
            'grns.view' => $permissions['grns.view'],
            'grns.create' => $permissions['grns.create'],
            'grns.update' => $permissions['grns.update'],
            'grns.delete' => $permissions['grns.delete'],
            'grns.approve' => $permissions['grns.approve'],
            'purchase.vendors.view' => $permissions['purchase.vendors.view'],
            'purchase.vendors.create' => $permissions['purchase.vendors.create'],
            'purchase.vendors.edit' => $permissions['purchase.vendors.edit'],
            'purchase.payments.view' => $permissions['purchase.payments.view'],
            'purchase.payments.create' => $permissions['purchase.payments.create'],
            'purchase.payments.edit' => $permissions['purchase.payments.edit'],
            'purchase.payments.delete' => $permissions['purchase.payments.delete'],
            'purchase.advances.view' => $permissions['purchase.advances.view'],
            'purchase.advances.create' => $permissions['purchase.advances.create'],
            'purchase.returns.view' => $permissions['purchase.returns.view'],
            'purchase.returns.create' => $permissions['purchase.returns.create'],
            'purchase.returns.approve' => $permissions['purchase.returns.approve'],
            'purchase.landed_costs.view' => $permissions['purchase.landed_costs.view'],
            'purchase.landed_costs.create' => $permissions['purchase.landed_costs.create'],
            'purchase.landed_costs.post' => $permissions['purchase.landed_costs.post'],
            'purchase.landed_costs.delete' => $permissions['purchase.landed_costs.delete'],
        ], RolePermission::SCOPE_TENANT);

        $this->grant($roles['hr_manager'], [
            'hr.settings.manage' => $permissions['hr.settings.manage'],
            'hrms.employees.view' => $permissions['hrms.employees.view'],
            'hrms.employees.create' => $permissions['hrms.employees.create'],
            'hrms.employees.update' => $permissions['hrms.employees.update'],
            'hrms.employees.delete' => $permissions['hrms.employees.delete'],
            'hrms.assets.view' => $permissions['hrms.assets.view'],
            'hrms.assets.create' => $permissions['hrms.assets.create'],
            'hrms.assets.update' => $permissions['hrms.assets.update'],
            'hrms.assets.delete' => $permissions['hrms.assets.delete'],
            'hrms.assets.approve' => $permissions['hrms.assets.approve'],
            'hrms.payroll_runs.view' => $permissions['hrms.payroll_runs.view'],
            'hrms.payroll_runs.create' => $permissions['hrms.payroll_runs.create'],
            'hrms.payroll_runs.approve' => $permissions['hrms.payroll_runs.approve'],
            'hrms.payroll_runs.process' => $permissions['hrms.payroll_runs.process'],
            'hrms.rosters.view' => $permissions['hrms.rosters.view'],
            'hrms.rosters.create' => $permissions['hrms.rosters.create'],
            'hrms.rosters.update' => $permissions['hrms.rosters.update'],
            'hrms.salary_structures.view' => $permissions['hrms.salary_structures.view'],
            'hrms.salary_structures.create' => $permissions['hrms.salary_structures.create'],
            'hrms.salary_structures.update' => $permissions['hrms.salary_structures.update'],
            'hrms.salary_structures.delete' => $permissions['hrms.salary_structures.delete'],
            'hrms.travel_expenses.view' => $permissions['hrms.travel_expenses.view'],
            'hrms.travel_expenses.create' => $permissions['hrms.travel_expenses.create'],
            'hrms.travel_expenses.approve' => $permissions['hrms.travel_expenses.approve'],
            'hrms.employee_exits.view' => $permissions['hrms.employee_exits.view'],
            'hrms.employee_exits.create' => $permissions['hrms.employee_exits.create'],
            'hrms.employee_exits.approve' => $permissions['hrms.employee_exits.approve'],
            'hrms.leave_requests.view' => $permissions['hrms.leave_requests.view'],
            'hrms.leave_requests.approve' => $permissions['hrms.leave_requests.approve'],
            'hrms.biometric_devices.view' => $permissions['hrms.biometric_devices.view'],
            'hrms.biometric_devices.manage' => $permissions['hrms.biometric_devices.manage'],
            'hrms.org.manage' => $permissions['hrms.org.manage'],
            'hrms.expense_policies.manage' => $permissions['hrms.expense_policies.manage'],
            'hrms.holiday_calendar.manage' => $permissions['hrms.holiday_calendar.manage'],
            'hrms.penalization_policies.manage' => $permissions['hrms.penalization_policies.manage'],
            'hrms.shift_changes.view' => $permissions['hrms.shift_changes.view'],
            'hrms.shift_changes.approve' => $permissions['hrms.shift_changes.approve'],
            'hrms.overtime.view' => $permissions['hrms.overtime.view'],
            'hrms.overtime.approve' => $permissions['hrms.overtime.approve'],
            'hrms.attendance.view' => $permissions['hrms.attendance.view'],
            'hrms.attendance.manage' => $permissions['hrms.attendance.manage'],
            'hrms.attendance.approve' => $permissions['hrms.attendance.approve'],
            'hrms.probation.manage' => $permissions['hrms.probation.manage'],
            'hrms.documents.manage' => $permissions['hrms.documents.manage'],
            'hrms.exit_policies.manage' => $permissions['hrms.exit_policies.manage'],
            'hrms.leave_structures.manage' => $permissions['hrms.leave_structures.manage'],
            'hrms.leave_encashments.view' => $permissions['hrms.leave_encashments.view'],
            'hrms.leave_encashments.approve' => $permissions['hrms.leave_encashments.approve'],
        ], RolePermission::SCOPE_TENANT);

        // Day-to-day bookkeeping only — deleting accounts/tax rates, closing fiscal
        // years/periods, and reversing posted journals or vouchers stay reserved for
        // tenant_owner/company_admin (segregation of duties over the ledger).
        $this->grant($roles['accountant'], [
            'accounting.chart_of_accounts.view' => $permissions['accounting.chart_of_accounts.view'],
            'accounting.chart_of_accounts.create' => $permissions['accounting.chart_of_accounts.create'],
            'accounting.chart_of_accounts.update' => $permissions['accounting.chart_of_accounts.update'],
            'accounting.cost_centers.view' => $permissions['accounting.cost_centers.view'],
            'accounting.cost_centers.create' => $permissions['accounting.cost_centers.create'],
            'accounting.cost_centers.update' => $permissions['accounting.cost_centers.update'],
            'accounting.fiscal_years.view' => $permissions['accounting.fiscal_years.view'],
            'accounting.periods.view' => $permissions['accounting.periods.view'],
            'accounting.journals.view' => $permissions['accounting.journals.view'],
            'accounting.journals.post' => $permissions['accounting.journals.post'],
            'accounting.tax_rates.view' => $permissions['accounting.tax_rates.view'],
            'accounting.tax_rates.create' => $permissions['accounting.tax_rates.create'],
            'accounting.tax_rates.update' => $permissions['accounting.tax_rates.update'],
            'accounting.reports.view' => $permissions['accounting.reports.view'],
            'accounting.vouchers.payment.view' => $permissions['accounting.vouchers.payment.view'],
            'accounting.vouchers.payment.post' => $permissions['accounting.vouchers.payment.post'],
            'accounting.vouchers.receipt.view' => $permissions['accounting.vouchers.receipt.view'],
            'accounting.vouchers.receipt.post' => $permissions['accounting.vouchers.receipt.post'],
            'accounting.vouchers.contra.view' => $permissions['accounting.vouchers.contra.view'],
            'accounting.vouchers.contra.post' => $permissions['accounting.vouchers.contra.post'],
            'accounting.vouchers.credit_note.view' => $permissions['accounting.vouchers.credit_note.view'],
            'accounting.vouchers.credit_note.post' => $permissions['accounting.vouchers.credit_note.post'],
            'accounting.vouchers.debit_note.view' => $permissions['accounting.vouchers.debit_note.view'],
            'accounting.vouchers.debit_note.post' => $permissions['accounting.vouchers.debit_note.post'],
            'fixed_assets.categories.view' => $permissions['fixed_assets.categories.view'],
            'fixed_assets.categories.create' => $permissions['fixed_assets.categories.create'],
            'fixed_assets.categories.edit' => $permissions['fixed_assets.categories.edit'],
            'fixed_assets.categories.delete' => $permissions['fixed_assets.categories.delete'],
            'fixed_assets.assets.view' => $permissions['fixed_assets.assets.view'],
            'fixed_assets.assets.create' => $permissions['fixed_assets.assets.create'],
            'fixed_assets.assets.edit' => $permissions['fixed_assets.assets.edit'],
            'fixed_assets.assets.delete' => $permissions['fixed_assets.assets.delete'],
            'fixed_assets.assets.capitalize' => $permissions['fixed_assets.assets.capitalize'],
            'fixed_assets.assets.assign' => $permissions['fixed_assets.assets.assign'],
            'fixed_assets.assets.transfer' => $permissions['fixed_assets.assets.transfer'],
            'fixed_assets.depreciation.view' => $permissions['fixed_assets.depreciation.view'],
            'fixed_assets.depreciation.generate' => $permissions['fixed_assets.depreciation.generate'],
            'fixed_assets.depreciation.post' => $permissions['fixed_assets.depreciation.post'],
            'fixed_assets.disposal.view' => $permissions['fixed_assets.disposal.view'],
            'fixed_assets.disposal.create' => $permissions['fixed_assets.disposal.create'],
            'fixed_assets.disposal.approve' => $permissions['fixed_assets.disposal.approve'],
            'fixed_assets.writeoff.create' => $permissions['fixed_assets.writeoff.create'],
            'fixed_assets.writeoff.approve' => $permissions['fixed_assets.writeoff.approve'],
            'fixed_assets.revaluation.create' => $permissions['fixed_assets.revaluation.create'],
            'fixed_assets.revaluation.approve' => $permissions['fixed_assets.revaluation.approve'],
        ], RolePermission::SCOPE_TENANT);

        // Auditor is a read-only oversight role (existing but previously had zero
        // grants anywhere in this seeder) — financial reports are exactly what it's for.
        $this->grant($roles['auditor'], [
            'accounting.reports.view' => $permissions['accounting.reports.view'],
            'accounting.vouchers.payment.view' => $permissions['accounting.vouchers.payment.view'],
            'accounting.vouchers.receipt.view' => $permissions['accounting.vouchers.receipt.view'],
            'accounting.vouchers.contra.view' => $permissions['accounting.vouchers.contra.view'],
            'accounting.vouchers.credit_note.view' => $permissions['accounting.vouchers.credit_note.view'],
            'accounting.vouchers.debit_note.view' => $permissions['accounting.vouchers.debit_note.view'],
            'fixed_assets.categories.view' => $permissions['fixed_assets.categories.view'],
            'fixed_assets.assets.view' => $permissions['fixed_assets.assets.view'],
            'fixed_assets.depreciation.view' => $permissions['fixed_assets.depreciation.view'],
            'fixed_assets.disposal.view' => $permissions['fixed_assets.disposal.view'],
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
            ['name' => 'platform.plans.manage', 'module' => 'platform', 'entity' => 'plans', 'action' => 'manage'],
            ['name' => 'platform.usage.view', 'module' => 'platform', 'entity' => 'usage', 'action' => 'view'],
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
            ['name' => 'production.planning.create', 'module' => 'production', 'entity' => 'planning', 'action' => 'create'],
            ['name' => 'production.planning.update', 'module' => 'production', 'entity' => 'planning', 'action' => 'update'],
            ['name' => 'production.planning.approve', 'module' => 'production', 'entity' => 'planning', 'action' => 'approve'],
            ['name' => 'production.planning.cancel', 'module' => 'production', 'entity' => 'planning', 'action' => 'cancel'],
            ['name' => 'production.order.create', 'module' => 'production', 'entity' => 'order', 'action' => 'create'],
            ['name' => 'production.order.update', 'module' => 'production', 'entity' => 'order', 'action' => 'update'],
            ['name' => 'production.order.cancel', 'module' => 'production', 'entity' => 'order', 'action' => 'cancel'],
            ['name' => 'production.mes.execute', 'module' => 'production', 'entity' => 'mes', 'action' => 'execute'],
            ['name' => 'production.schedule.manage', 'module' => 'production', 'entity' => 'schedule', 'action' => 'manage'],
            ['name' => 'production.intelligence.view', 'module' => 'production', 'entity' => 'intelligence', 'action' => 'view'],
            ['name' => 'production.quality.manage', 'module' => 'production', 'entity' => 'quality', 'action' => 'manage'],
            ['name' => 'production.quality.approve', 'module' => 'production', 'entity' => 'quality', 'action' => 'approve'],
            ['name' => 'production.cost_adjustment.create', 'module' => 'production', 'entity' => 'cost_adjustment', 'action' => 'create'],
            ['name' => 'production.cost_adjustment.update', 'module' => 'production', 'entity' => 'cost_adjustment', 'action' => 'update'],
            ['name' => 'hr.settings.manage', 'module' => 'hr', 'entity' => 'settings', 'action' => 'manage'],
            ['name' => 'hrms.employees.view', 'module' => 'hrms', 'entity' => 'employees', 'action' => 'view'],
            ['name' => 'hrms.employees.create', 'module' => 'hrms', 'entity' => 'employees', 'action' => 'create'],
            ['name' => 'hrms.employees.update', 'module' => 'hrms', 'entity' => 'employees', 'action' => 'update'],
            ['name' => 'hrms.employees.delete', 'module' => 'hrms', 'entity' => 'employees', 'action' => 'delete'],
            ['name' => 'hrms.assets.view', 'module' => 'hrms', 'entity' => 'assets', 'action' => 'view'],
            ['name' => 'hrms.assets.create', 'module' => 'hrms', 'entity' => 'assets', 'action' => 'create'],
            ['name' => 'hrms.assets.update', 'module' => 'hrms', 'entity' => 'assets', 'action' => 'update'],
            ['name' => 'hrms.assets.delete', 'module' => 'hrms', 'entity' => 'assets', 'action' => 'delete'],
            ['name' => 'hrms.assets.approve', 'module' => 'hrms', 'entity' => 'assets', 'action' => 'approve'],
            ['name' => 'hrms.payroll_runs.view', 'module' => 'hrms', 'entity' => 'payroll_runs', 'action' => 'view'],
            ['name' => 'hrms.payroll_runs.create', 'module' => 'hrms', 'entity' => 'payroll_runs', 'action' => 'create'],
            ['name' => 'hrms.payroll_runs.approve', 'module' => 'hrms', 'entity' => 'payroll_runs', 'action' => 'approve'],
            ['name' => 'hrms.payroll_runs.process', 'module' => 'hrms', 'entity' => 'payroll_runs', 'action' => 'process'],
            ['name' => 'hrms.rosters.view', 'module' => 'hrms', 'entity' => 'rosters', 'action' => 'view'],
            ['name' => 'hrms.rosters.create', 'module' => 'hrms', 'entity' => 'rosters', 'action' => 'create'],
            ['name' => 'hrms.rosters.update', 'module' => 'hrms', 'entity' => 'rosters', 'action' => 'update'],
            ['name' => 'hrms.salary_structures.view', 'module' => 'hrms', 'entity' => 'salary_structures', 'action' => 'view'],
            ['name' => 'hrms.salary_structures.create', 'module' => 'hrms', 'entity' => 'salary_structures', 'action' => 'create'],
            ['name' => 'hrms.salary_structures.update', 'module' => 'hrms', 'entity' => 'salary_structures', 'action' => 'update'],
            ['name' => 'hrms.salary_structures.delete', 'module' => 'hrms', 'entity' => 'salary_structures', 'action' => 'delete'],
            ['name' => 'hrms.travel_expenses.view', 'module' => 'hrms', 'entity' => 'travel_expenses', 'action' => 'view'],
            ['name' => 'hrms.travel_expenses.create', 'module' => 'hrms', 'entity' => 'travel_expenses', 'action' => 'create'],
            ['name' => 'hrms.travel_expenses.approve', 'module' => 'hrms', 'entity' => 'travel_expenses', 'action' => 'approve'],
            ['name' => 'hrms.employee_exits.view', 'module' => 'hrms', 'entity' => 'employee_exits', 'action' => 'view'],
            ['name' => 'hrms.employee_exits.create', 'module' => 'hrms', 'entity' => 'employee_exits', 'action' => 'create'],
            ['name' => 'hrms.employee_exits.approve', 'module' => 'hrms', 'entity' => 'employee_exits', 'action' => 'approve'],
            ['name' => 'hrms.leave_requests.view', 'module' => 'hrms', 'entity' => 'leave_requests', 'action' => 'view'],
            ['name' => 'hrms.leave_requests.approve', 'module' => 'hrms', 'entity' => 'leave_requests', 'action' => 'approve'],
            ['name' => 'hrms.biometric_devices.view', 'module' => 'hrms', 'entity' => 'biometric_devices', 'action' => 'view'],
            ['name' => 'hrms.biometric_devices.manage', 'module' => 'hrms', 'entity' => 'biometric_devices', 'action' => 'manage'],
            ['name' => 'hrms.org.manage', 'module' => 'hrms', 'entity' => 'org', 'action' => 'manage'],
            ['name' => 'hrms.expense_policies.manage', 'module' => 'hrms', 'entity' => 'expense_policies', 'action' => 'manage'],
            ['name' => 'hrms.holiday_calendar.manage', 'module' => 'hrms', 'entity' => 'holiday_calendar', 'action' => 'manage'],
            ['name' => 'hrms.penalization_policies.manage', 'module' => 'hrms', 'entity' => 'penalization_policies', 'action' => 'manage'],
            ['name' => 'hrms.shift_changes.view', 'module' => 'hrms', 'entity' => 'shift_changes', 'action' => 'view'],
            ['name' => 'hrms.shift_changes.approve', 'module' => 'hrms', 'entity' => 'shift_changes', 'action' => 'approve'],
            ['name' => 'hrms.overtime.view', 'module' => 'hrms', 'entity' => 'overtime', 'action' => 'view'],
            ['name' => 'hrms.overtime.approve', 'module' => 'hrms', 'entity' => 'overtime', 'action' => 'approve'],
            ['name' => 'hrms.attendance.view', 'module' => 'hrms', 'entity' => 'attendance', 'action' => 'view'],
            ['name' => 'hrms.attendance.manage', 'module' => 'hrms', 'entity' => 'attendance', 'action' => 'manage'],
            ['name' => 'hrms.attendance.approve', 'module' => 'hrms', 'entity' => 'attendance', 'action' => 'approve'],
            ['name' => 'hrms.probation.manage', 'module' => 'hrms', 'entity' => 'probation', 'action' => 'manage'],
            ['name' => 'hrms.documents.manage', 'module' => 'hrms', 'entity' => 'documents', 'action' => 'manage'],
            ['name' => 'hrms.exit_policies.manage', 'module' => 'hrms', 'entity' => 'exit_policies', 'action' => 'manage'],
            ['name' => 'hrms.leave_structures.manage', 'module' => 'hrms', 'entity' => 'leave_structures', 'action' => 'manage'],
            ['name' => 'hrms.leave_encashments.view', 'module' => 'hrms', 'entity' => 'leave_encashments', 'action' => 'view'],
            ['name' => 'hrms.leave_encashments.approve', 'module' => 'hrms', 'entity' => 'leave_encashments', 'action' => 'approve'],
            ['name' => 'fixed_assets.categories.view', 'module' => 'fixed_assets', 'entity' => 'categories', 'action' => 'view'],
            ['name' => 'fixed_assets.categories.create', 'module' => 'fixed_assets', 'entity' => 'categories', 'action' => 'create'],
            ['name' => 'fixed_assets.categories.edit', 'module' => 'fixed_assets', 'entity' => 'categories', 'action' => 'edit'],
            ['name' => 'fixed_assets.categories.delete', 'module' => 'fixed_assets', 'entity' => 'categories', 'action' => 'delete'],
            ['name' => 'fixed_assets.assets.view', 'module' => 'fixed_assets', 'entity' => 'assets', 'action' => 'view'],
            ['name' => 'fixed_assets.assets.create', 'module' => 'fixed_assets', 'entity' => 'assets', 'action' => 'create'],
            ['name' => 'fixed_assets.assets.edit', 'module' => 'fixed_assets', 'entity' => 'assets', 'action' => 'edit'],
            ['name' => 'fixed_assets.assets.delete', 'module' => 'fixed_assets', 'entity' => 'assets', 'action' => 'delete'],
            ['name' => 'fixed_assets.assets.capitalize', 'module' => 'fixed_assets', 'entity' => 'assets', 'action' => 'capitalize'],
            ['name' => 'fixed_assets.assets.assign', 'module' => 'fixed_assets', 'entity' => 'assets', 'action' => 'assign'],
            ['name' => 'fixed_assets.assets.transfer', 'module' => 'fixed_assets', 'entity' => 'assets', 'action' => 'transfer'],
            ['name' => 'fixed_assets.depreciation.view', 'module' => 'fixed_assets', 'entity' => 'depreciation', 'action' => 'view'],
            ['name' => 'fixed_assets.depreciation.generate', 'module' => 'fixed_assets', 'entity' => 'depreciation', 'action' => 'generate'],
            ['name' => 'fixed_assets.depreciation.post', 'module' => 'fixed_assets', 'entity' => 'depreciation', 'action' => 'post'],
            ['name' => 'fixed_assets.disposal.view', 'module' => 'fixed_assets', 'entity' => 'disposal', 'action' => 'view'],
            ['name' => 'fixed_assets.disposal.create', 'module' => 'fixed_assets', 'entity' => 'disposal', 'action' => 'create'],
            ['name' => 'fixed_assets.disposal.approve', 'module' => 'fixed_assets', 'entity' => 'disposal', 'action' => 'approve'],
            ['name' => 'fixed_assets.writeoff.create', 'module' => 'fixed_assets', 'entity' => 'writeoff', 'action' => 'create'],
            ['name' => 'fixed_assets.writeoff.approve', 'module' => 'fixed_assets', 'entity' => 'writeoff', 'action' => 'approve'],
            ['name' => 'fixed_assets.revaluation.create', 'module' => 'fixed_assets', 'entity' => 'revaluation', 'action' => 'create'],
            ['name' => 'fixed_assets.revaluation.approve', 'module' => 'fixed_assets', 'entity' => 'revaluation', 'action' => 'approve'],
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
            ['name' => 'grns.view', 'module' => 'grns', 'entity' => 'grns', 'action' => 'view'],
            ['name' => 'grns.create', 'module' => 'grns', 'entity' => 'grns', 'action' => 'create'],
            ['name' => 'grns.update', 'module' => 'grns', 'entity' => 'grns', 'action' => 'update'],
            ['name' => 'grns.delete', 'module' => 'grns', 'entity' => 'grns', 'action' => 'delete'],
            ['name' => 'grns.approve', 'module' => 'grns', 'entity' => 'grns', 'action' => 'approve'],
            ['name' => 'purchase.orders.view', 'module' => 'purchase', 'entity' => 'orders', 'action' => 'view'],
            ['name' => 'purchase.orders.create', 'module' => 'purchase', 'entity' => 'orders', 'action' => 'create'],
            ['name' => 'purchase.orders.edit', 'module' => 'purchase', 'entity' => 'orders', 'action' => 'edit'],
            ['name' => 'purchase.orders.delete', 'module' => 'purchase', 'entity' => 'orders', 'action' => 'delete'],
            ['name' => 'purchase.orders.approve', 'module' => 'purchase', 'entity' => 'orders', 'action' => 'approve'],
            ['name' => 'purchase.requisitions.view', 'module' => 'purchase', 'entity' => 'requisitions', 'action' => 'view'],
            ['name' => 'purchase.requisitions.create', 'module' => 'purchase', 'entity' => 'requisitions', 'action' => 'create'],
            ['name' => 'purchase.requisitions.edit', 'module' => 'purchase', 'entity' => 'requisitions', 'action' => 'edit'],
            ['name' => 'purchase.requisitions.delete', 'module' => 'purchase', 'entity' => 'requisitions', 'action' => 'delete'],
            ['name' => 'purchase.requisitions.approve', 'module' => 'purchase', 'entity' => 'requisitions', 'action' => 'approve'],
            ['name' => 'purchase.rfqs.view', 'module' => 'purchase', 'entity' => 'rfqs', 'action' => 'view'],
            ['name' => 'purchase.rfqs.create', 'module' => 'purchase', 'entity' => 'rfqs', 'action' => 'create'],
            ['name' => 'purchase.rfqs.edit', 'module' => 'purchase', 'entity' => 'rfqs', 'action' => 'edit'],
            ['name' => 'purchase.rfqs.delete', 'module' => 'purchase', 'entity' => 'rfqs', 'action' => 'delete'],
            ['name' => 'purchase.bills.view', 'module' => 'purchase', 'entity' => 'bills', 'action' => 'view'],
            ['name' => 'purchase.bills.create', 'module' => 'purchase', 'entity' => 'bills', 'action' => 'create'],
            ['name' => 'purchase.bills.edit', 'module' => 'purchase', 'entity' => 'bills', 'action' => 'edit'],
            ['name' => 'purchase.approvals.manage', 'module' => 'purchase', 'entity' => 'approvals', 'action' => 'manage'],
            ['name' => 'purchase.vendors.view', 'module' => 'purchase', 'entity' => 'vendors', 'action' => 'view'],
            ['name' => 'purchase.vendors.create', 'module' => 'purchase', 'entity' => 'vendors', 'action' => 'create'],
            ['name' => 'purchase.vendors.edit', 'module' => 'purchase', 'entity' => 'vendors', 'action' => 'edit'],
            ['name' => 'purchase.payments.view', 'module' => 'purchase', 'entity' => 'payments', 'action' => 'view'],
            ['name' => 'purchase.payments.create', 'module' => 'purchase', 'entity' => 'payments', 'action' => 'create'],
            ['name' => 'purchase.payments.edit', 'module' => 'purchase', 'entity' => 'payments', 'action' => 'edit'],
            ['name' => 'purchase.payments.delete', 'module' => 'purchase', 'entity' => 'payments', 'action' => 'delete'],
            ['name' => 'purchase.advances.view', 'module' => 'purchase', 'entity' => 'advances', 'action' => 'view'],
            ['name' => 'purchase.advances.create', 'module' => 'purchase', 'entity' => 'advances', 'action' => 'create'],
            ['name' => 'purchase.returns.view', 'module' => 'purchase', 'entity' => 'returns', 'action' => 'view'],
            ['name' => 'purchase.returns.create', 'module' => 'purchase', 'entity' => 'returns', 'action' => 'create'],
            ['name' => 'purchase.returns.approve', 'module' => 'purchase', 'entity' => 'returns', 'action' => 'approve'],
            ['name' => 'purchase.landed_costs.view', 'module' => 'purchase', 'entity' => 'landed_costs', 'action' => 'view'],
            ['name' => 'purchase.landed_costs.create', 'module' => 'purchase', 'entity' => 'landed_costs', 'action' => 'create'],
            ['name' => 'purchase.landed_costs.post', 'module' => 'purchase', 'entity' => 'landed_costs', 'action' => 'post'],
            ['name' => 'purchase.landed_costs.delete', 'module' => 'purchase', 'entity' => 'landed_costs', 'action' => 'delete'],
            ['name' => 'inventory.material_requirements.view', 'module' => 'inventory', 'entity' => 'material_requirements', 'action' => 'view'],
            ['name' => 'inventory.material_requirements.create', 'module' => 'inventory', 'entity' => 'material_requirements', 'action' => 'create'],
            ['name' => 'inventory.material_requirements.ship', 'module' => 'inventory', 'entity' => 'material_requirements', 'action' => 'ship'],
            ['name' => 'inventory.material_requirements.cancel', 'module' => 'inventory', 'entity' => 'material_requirements', 'action' => 'cancel'],
            ['name' => 'inventory.dispatches.view', 'module' => 'inventory', 'entity' => 'dispatches', 'action' => 'view'],
            ['name' => 'inventory.dispatches.create', 'module' => 'inventory', 'entity' => 'dispatches', 'action' => 'create'],
            ['name' => 'sales.orders.view', 'module' => 'sales', 'entity' => 'orders', 'action' => 'view'],
            ['name' => 'sales.orders.create', 'module' => 'sales', 'entity' => 'orders', 'action' => 'create'],
            ['name' => 'sales.orders.update', 'module' => 'sales', 'entity' => 'orders', 'action' => 'update'],
            ['name' => 'sales.orders.delete', 'module' => 'sales', 'entity' => 'orders', 'action' => 'delete'],
            ['name' => 'sales.orders.confirm', 'module' => 'sales', 'entity' => 'orders', 'action' => 'confirm'],
            ['name' => 'sales.orders.cancel', 'module' => 'sales', 'entity' => 'orders', 'action' => 'cancel'],
            ['name' => 'sales.material_requirements.view', 'module' => 'sales', 'entity' => 'material_requirements', 'action' => 'view'],
            ['name' => 'sales.material_requirements.create', 'module' => 'sales', 'entity' => 'material_requirements', 'action' => 'create'],
            ['name' => 'sales.material_requirements.ship', 'module' => 'sales', 'entity' => 'material_requirements', 'action' => 'ship'],
            ['name' => 'sales.material_requirements.cancel', 'module' => 'sales', 'entity' => 'material_requirements', 'action' => 'cancel'],
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
            ['name' => 'accounting.cost_centers.view', 'module' => 'accounting', 'entity' => 'cost_centers', 'action' => 'view'],
            ['name' => 'accounting.cost_centers.create', 'module' => 'accounting', 'entity' => 'cost_centers', 'action' => 'create'],
            ['name' => 'accounting.cost_centers.update', 'module' => 'accounting', 'entity' => 'cost_centers', 'action' => 'update'],
            ['name' => 'accounting.cost_centers.delete', 'module' => 'accounting', 'entity' => 'cost_centers', 'action' => 'delete'],
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
            ['name' => 'accounting.vouchers.payment.view', 'module' => 'accounting', 'entity' => 'vouchers_payment', 'action' => 'view'],
            ['name' => 'accounting.vouchers.payment.post', 'module' => 'accounting', 'entity' => 'vouchers_payment', 'action' => 'post'],
            ['name' => 'accounting.vouchers.payment.reverse', 'module' => 'accounting', 'entity' => 'vouchers_payment', 'action' => 'reverse'],
            ['name' => 'accounting.vouchers.receipt.view', 'module' => 'accounting', 'entity' => 'vouchers_receipt', 'action' => 'view'],
            ['name' => 'accounting.vouchers.receipt.post', 'module' => 'accounting', 'entity' => 'vouchers_receipt', 'action' => 'post'],
            ['name' => 'accounting.vouchers.receipt.reverse', 'module' => 'accounting', 'entity' => 'vouchers_receipt', 'action' => 'reverse'],
            ['name' => 'accounting.vouchers.contra.view', 'module' => 'accounting', 'entity' => 'vouchers_contra', 'action' => 'view'],
            ['name' => 'accounting.vouchers.contra.post', 'module' => 'accounting', 'entity' => 'vouchers_contra', 'action' => 'post'],
            ['name' => 'accounting.vouchers.contra.reverse', 'module' => 'accounting', 'entity' => 'vouchers_contra', 'action' => 'reverse'],
            ['name' => 'accounting.vouchers.credit_note.view', 'module' => 'accounting', 'entity' => 'vouchers_credit_note', 'action' => 'view'],
            ['name' => 'accounting.vouchers.credit_note.post', 'module' => 'accounting', 'entity' => 'vouchers_credit_note', 'action' => 'post'],
            ['name' => 'accounting.vouchers.credit_note.reverse', 'module' => 'accounting', 'entity' => 'vouchers_credit_note', 'action' => 'reverse'],
            ['name' => 'accounting.vouchers.debit_note.view', 'module' => 'accounting', 'entity' => 'vouchers_debit_note', 'action' => 'view'],
            ['name' => 'accounting.vouchers.debit_note.post', 'module' => 'accounting', 'entity' => 'vouchers_debit_note', 'action' => 'post'],
            ['name' => 'accounting.vouchers.debit_note.reverse', 'module' => 'accounting', 'entity' => 'vouchers_debit_note', 'action' => 'reverse'],
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
            ['slug' => 'purchase_manager', 'name' => 'Purchase Manager', 'tenant_id' => null, 'level' => 40],
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
        $tenantSlug = config('tenancy.local_fallback_slug') ?: 'warrgyizmorsch';
        $tenant = Tenant::query()->where('slug', $tenantSlug)->first();
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
