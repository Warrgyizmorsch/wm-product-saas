@php
    $resolvedTenant = tenant();
    $tenantSettings = $resolvedTenant?->settings ?? [];
    $tenantPlan = ucfirst((string) ($resolvedTenant?->plan ?? 'Starter'));
    $branding = tenant_branding($resolvedTenant);

    $authUser = auth()->user();
    $isHrAdmin = $authUser && ($authUser->hasHrPermission('hr.settings.manage') || $authUser->hasHrPermission('hrms.leave_requests.approve'));

    $modules = [
        __('ui.workspace') => [
            ['label' => __('ui.executive_dashboard'), 'icon' => 'feather-home', 'route' => 'dashboard'],
            ['label' => 'Tenant Console', 'icon' => 'feather-grid', 'url' => '#', 'children' => [
                ['label' => 'Tenants', 'route' => 'platform.tenants.index'],
                ['label' => 'Plans', 'route' => 'platform.plans.index'],
                ['label' => 'Currencies', 'route' => 'platform.currencies.index'],
                ['label' => 'Subscriptions', 'route' => 'platform.subscription.index'],
                ['label' => 'Usage Limits', 'route' => 'platform.usage.index'],
                ['label' => 'Payment Terms', 'route' => 'platform.payment-terms.index'],
                ['label' => 'Email & SMTP Setup', 'route' => 'platform.emailSettings.index'],
                ['label' => 'WhatsApp Web Setup', 'route' => 'platform.whatsappSettings.index'],
            ]],
            ['label' => __('ui.approvals_center'), 'icon' => 'feather-check-square', 'url' => '#', 'children' => ['Pending', 'Delegated', 'Escalations', 'Workflow Rules']],
        ],
        'Revenue Cycle' => [
            ['label' => __('crm.dashboard_sidebar') ?: 'CRM Executive Dashboard', 'icon' => 'feather-grid', 'route' => 'crm.dashboard'],
            ['label' => __('ui.crm'), 'icon' => 'feather-users', 'url' => '#', 'children' => [
                ['label' => __('crm.leads') ?: 'Leads', 'route' => 'crm.leads.index'],
                ['label' => __('crm.deals_sidebar') ?: 'Deals (Pipeline)', 'route' => 'crm.deals.index'],
                ['label' => __('crm.accounts_sidebar') ?: 'Accounts (Companies)', 'route' => 'crm.accounts.index'],
                ['label' => __('crm.customers_sidebar') ?: 'Customers', 'route' => 'crm.customers.index'],
                ['label' => __('crm.track_status_sidebar') ?: 'Track Status', 'route' => 'crm.leads.trackStatus'],
            ]],
            ['label' => __('crm.crm_masters_sidebar') ?: 'CRM Masters', 'icon' => 'feather-settings', 'url' => '#', 'children' => [
                ['label' => __('crm.lead_status_master') ?: 'Lead Status Master', 'route' => 'crm.masters.lead-statuses.index'],
                ['label' => __('crm.deal_stage_master') ?: 'Deal Stage Master', 'route' => 'crm.masters.deal-statuses.index'],
                ['label' => __('crm.crm_sales_settings') ?: 'CRM & Sales Settings', 'route' => 'crm.settings.index'],
            ]],
            ['label' => 'Approvals', 'icon' => 'feather-check-circle', 'url' => '#', 'children' => [
                ['label' => 'Quotation Approval', 'route' => 'crm.approvals.quotations.index'],
            ]],
            ['label' => __('ui.projects'), 'icon' => 'feather-briefcase', 'url' => '#', 'children' => [
                ['label' => __('ui.projects'), 'route' => 'projects.index'],
                ['label' => __('projects.milestones'), 'route' => 'projects.milestones.index'],
                'Tasks',
                'Timesheets',
            ]],
        ],
        __('ui.supply_chain') => [
            ['label' => 'Supply Chain Dashboard', 'icon' => 'feather-grid', 'route' => 'supply-chain.dashboard'],
            ['label' => __('ui.sales'), 'icon' => 'feather-shopping-cart', 'url' => '#', 'children' => [
                ['label' => 'Quotations', 'route' => 'crm.quotations.index'],
                ['label' => 'Sales Orders', 'route' => 'sales.orders.index'],
                ['label' => 'Invoices', 'route' => 'sales.invoices.index'],
                ['label' => 'Receipts (Payments)', 'route' => 'sales.payments.index'],
                ['label' => 'Sales Returns', 'route' => 'sales.returns.index'],
            ]],
            ['label' => 'Store', 'icon' => 'feather-archive', 'url' => '#', 'children' => [
                ['label' => 'Material Requirements', 'route' => 'inventory.material-requirements.index'],
                ['label' => 'MRP & Shortage Analysis', 'route' => 'inventory.mrp-shortage.index'],
                ['label' => 'Material Requests (Prod)', 'route' => 'inventory.material-requests.index'],
                ['label' => 'Dispatch Orders', 'route' => 'inventory.dispatches.index'],
                ['label' => 'Transporters Master', 'route' => 'platform.transporters.index'],
            ]],
            ['label' => __('ui.inventory'), 'icon' => 'feather-box', 'url' => '#', 'children' => [
                ['label' => __('inventory.products'), 'route' => 'inventory.products.index'],
                ['label' => __('inventory.warehouses'), 'route' => 'inventory.warehouses.index'],
                ['label' => __('inventory.serial_numbers'), 'route' => 'inventory.serial-numbers.index'],
                ['label' => __('inventory.batches_fefo'), 'route' => 'inventory.batches.index'],
                ['label' => 'Stock Transfers', 'route' => 'inventory.transfers.index'],
                ['label' => 'Stock Adjustments', 'route' => 'inventory.adjustments.index'],
                ['label' => 'Stock Ledger History', 'route' => 'inventory.transactions.index'],
                ['label' => 'Stock Reservations', 'route' => 'inventory.reservations.index'],
                ['label' => 'Barcode Labels', 'route' => 'inventory.barcodes.index'],
                ['label' => 'Low Stock Report', 'route' => 'inventory.reports.low-stock'],
                ['label' => 'Stock Valuation Report', 'route' => 'inventory.reports.valuation'],
            ]],
            ['label' => __('ui.purchase'), 'icon' => 'feather-truck', 'url' => '#', 'children' => [
                ['label' => 'Vendors / Suppliers', 'route' => 'purchase.vendors.index'],
                ['label' => __('purchase.savings_dashboard'), 'route' => 'purchase.rfqs.savings'],
                ['label' => __('ui.purchase_requests') ?: __('purchase.purchase_requests'), 'route' => 'purchase.requisitions.index'],
                ['label' => __('purchase.pending_pr_items'), 'route' => 'purchase.requisitions.pending-items'],
                ['label' => __('purchase.rfqs'), 'route' => 'purchase.rfqs.index'],
                ['label' => __('purchase.purchase_orders'), 'route' => 'purchase.orders.index'],
                ['label' => 'Landed Cost Vouchers', 'route' => 'purchase.landed-costs.index'],
                ['label' => __('purchase.vendor_bills'), 'route' => 'purchase.bills.index'],
                ['label' => __('purchase.vendor_payments'), 'route' => 'purchase.payments.index'],
                ['label' => __('purchase.advance_payments'), 'route' => 'purchase.advances.index'],
                ['label' => __('purchase.purchase_returns'), 'route' => 'purchase.returns.index'],
            ]],
            ['label' => __('purchase.goods_receipts'), 'icon' => 'feather-package', 'url' => '#', 'children' => [
                ['label' => __('purchase.pending_grns'), 'route' => 'grns.pending'],
                ['label' => __('purchase.all_goods_receipts'), 'route' => 'grns.index'],
                ['label' => __('purchase.new_goods_receipt'), 'route' => 'grns.create'],
            ]],
            ['label' => __('purchase.purchase_approvals'), 'icon' => 'feather-check-circle', 'url' => '#', 'children' => [
                ['label' => __('purchase.pr_approvals'), 'route' => 'purchase.pr-approvals.index'],
                ['label' => __('purchase.po_approvals'), 'route' => 'purchase.po-approvals.index'],
            ]],
        ],
        __('ui.production') => [
            ['label' => __('production.production_dashboard'), 'icon' => 'feather-grid', 'route' => 'production.dashboard'],
            ['label' => __('production.execution'), 'icon' => 'feather-play-circle', 'url' => '#', 'children' => [
                ['label' => __('production.production_orders'),        'route' => 'production.orders.index'],
                ['label' => __('production.shop_floor_mes'),          'route' => 'production.mes.dashboard'],
                ['label' => __('production.work_in_progress_wip'),    'route' => 'production.wip.index'],
                ['label' => __('production.job_cards_operations'),    'route' => 'production.mes.operator.my-operations'],
            ]],
            ['label' => __('production.quality_management'), 'icon' => 'feather-check-circle', 'url' => '#', 'children' => [
                ['label' => __('production.quality_dashboard'),       'route' => 'production.quality.dashboard'],
                ['label' => __('production.quality_inspections'),     'route' => 'production.inspections.index'],
                ['label' => __('production.ncr'),                     'route' => 'production.ncrs.index'],
                ['label' => __('production.capa'),                    'route' => 'production.capas.index'],
                ['label' => __('production.rework_orders'),           'route' => 'production.rework.index'],
                ['label' => __('production.scrap_disposals'),         'route' => 'production.scrap.index'],
            ]],
            ['label' => __('production.subcontracting'), 'icon' => 'feather-truck', 'url' => '#', 'children' => [
                ['label' => __('production.delivery_challans_gate_passes'), 'route' => 'production.subcontract.delivery-challans.index'],
                ['label' => __('production.vendor_sla_analytics'),          'route' => 'production.subcontract.analytics'],
                ['label' => __('production.subcontract_settings'),          'route' => 'production.settings.index'],
            ]],
            ['label' => __('production.engineering'), 'icon' => 'feather-settings', 'url' => '#', 'children' => [
                ['label' => __('production.bom'),                     'route' => 'production.boms.index'],
                ['label' => __('production.routing'),                 'route' => 'production.routing.index'],
                ['label' => __('production.eco_engineering_changes'), 'route' => 'production.ecos.index'],
                ['label' => __('production.work_centers'),            'route' => 'production.work-centers.index'],
                ['label' => __('production.machines'),                'route' => 'production.machines.index'],
                ['label' => __('production.operator_skills'),         'route' => 'production.operator-skills.index'],
                ['label' => __('production.shifts_sidebar'),          'route' => 'production.shifts.index'],
                ['label' => __('production.calendars_sidebar'),       'route' => 'production.calendars.index'],
            ]],
            ['label' => __('production.performance'), 'icon' => 'feather-bar-chart-2', 'url' => '#', 'children' => [
                ['label' => __('production.variance_and_performance'),'route' => 'production.variances.index'],
                ['label' => __('production.executive_dashboard'),     'route' => 'production.intelligence.dashboard'],
                ['label' => __('production.live_andon_board'),        'route' => 'production.intelligence.andon'],
                ['label' => __('production.manufacturing_reports'),   'route' => 'production.intelligence.reports.index'],
            ]],
            ['label' => __('production.machine_maintenance'), 'icon' => 'feather-tool', 'url' => '#', 'children' => [
                ['label' => __('production.maintenance_dashboard'),   'route' => 'production.maintenance.dashboard'],
                ['label' => __('production.work_orders'),             'route' => 'production.maintenance.work-orders.index'],
                ['label' => __('production.pm_schedules'),            'route' => 'production.maintenance.schedules.index'],
            ]],
            ['label' => __('production.advanced_planning'), 'icon' => 'feather-cpu', 'url' => '#', 'children' => [
                ['label' => __('production.production_plans'),        'route' => 'production.plans.index'],
                ['label' => __('production.production_schedules'),    'route' => 'production.schedules.index'],
                ['label' => __('production.calendar_schedule_view'),  'route' => 'production.schedules.calendar'],
                ['label' => __('production.capacity_planning'),       'route' => 'production.capacity.index'],
                ['label' => __('production.planning_scenarios'),      'route' => 'production.schedules.scenarios.index'],
                ['label' => __('production.planning_exceptions'),     'route' => 'production.planning-exceptions.index'],
            ]],
        ],
        'HRMS' => array_values(array_filter([
            ['label' => 'HRMS Dashboard', 'icon' => 'feather-home', 'route' => 'hrms.dashboard'],
            $isHrAdmin ? ['label' => 'HRMS Masters', 'icon' => 'feather-settings', 'url' => '#', 'children' => array_values(array_filter([
                ['label' => 'Org Structure', 'route' => 'hrms.org.index'],
                ['label' => 'Salary Structure', 'route' => 'hrms.salary-structure.index'],
                ['label' => 'Leave Structure', 'route' => 'hrms.leave-structure.index'],
                ['label' => 'Shift Roster', 'route' => 'hrms.roster.index'],
                ['label' => 'Penalization Policy', 'route' => 'hrms.penalization-policy.index'],
                (\App\Domains\HRMS\Models\AttendanceRule::where('office_biometric', true)
                    ->when($resolvedTenant, fn($q) => $q->where('tenant_id', $resolvedTenant->id))
                    ->exists())
                    ? ['label' => 'Biometric Devices', 'route' => 'hrms.biometric-devices.index']
                    : null,
                ['label' => 'Asset Management', 'route' => 'hrms.assets.index'],
                ['label' => 'Document Master', 'route' => 'hrms.documents-master.index'],
                ['label' => 'Holiday Calendar', 'route' => 'hrms.holidays.index'],
                ['label' => 'Expense Policies', 'route' => 'hrms.expense-policy.index'],
                ['label' => 'Offboarding Policies', 'route' => 'hrms.offboarding-policies.index'],
            ]))] : null,
            $isHrAdmin ? ['label' => 'Employees', 'icon' => 'feather-users', 'route' => 'hrms.employees.index'] : null,
            $isHrAdmin ? ['label' => 'Documents', 'icon' => 'feather-file-text', 'route' => 'hrms.documents.index'] : null,
            ['label' => 'Assets', 'icon' => 'feather-package', 'url' => '#', 'children' => array_values(array_filter([
                $isHrAdmin ? ['label' => 'Employees Assets', 'route' => 'hrms.assets-module.index'] : null,
                ['label' => 'My Assets', 'route' => 'hrms.assets-module.my-assets'],
            ]))],
            ['label' => 'Attendance', 'icon' => 'feather-clock', 'url' => '#', 'children' => array_values(array_filter([
                $isHrAdmin ? ['label' => 'Employees Attendance', 'route' => 'hrms.attendance.index'] : null,
                ['label' => 'My Attendance', 'route' => 'hrms.attendance.myAttendance'],
            ]))],
            ['label' => 'Leave', 'icon' => 'feather-calendar', 'route' => 'hrms.leaves.index'],
            ['label' => 'WFH', 'icon' => 'feather-home', 'route' => 'hrms.wfh.index'],
            ['label' => 'Shift & Overtime', 'icon' => 'feather-activity', 'route' => 'hrms.shift-overtime.index'],
            ['label' => 'Travel & Expenses', 'icon' => 'feather-navigation', 'route' => 'hrms.travel-expense.index'],
            $isHrAdmin ? ['label' => 'PIP (Performance)', 'icon' => 'feather-trending-up', 'route' => 'hrms.pip.index'] : null,
            ['label' => 'Broadcasts', 'icon' => 'feather-radio', 'route' => 'hrms.broadcasts.index'],
            ['label' => 'Helpdesk', 'icon' => 'feather-life-buoy', 'route' => 'hrms.helpdesk.tickets.index'],
            ['label' => 'Recruitment', 'icon' => 'feather-user-check', 'route' => 'hrms.recruitment.index'],
            ['label' => 'Payroll', 'icon' => 'feather-dollar-sign', 'url' => '#', 'children' => array_values(array_filter([
                $isHrAdmin ? ['label' => 'Payroll Processing', 'route' => 'hrms.payroll.index'] : null,
                ['label' => 'My Payslips', 'route' => 'hrms.payroll.mySalary'],
            ]))],
        ])),
        'Finance & People' => [
            ['label' => 'Accounting', 'icon' => 'feather-credit-card', 'url' => '#', 'children' => [
                ['label' => 'Chart of Accounts', 'route' => 'accounting.chart-of-accounts.index'],
                ['label' => 'Cost Centers', 'route' => 'accounting.cost-centers.index'],
                ['label' => 'Fixed Asset Register', 'route' => 'accounting.fixed-assets.index'],
                ['label' => 'Asset Categories', 'route' => 'accounting.fixed-assets.categories.index'],
                ['label' => 'Depreciation', 'route' => 'accounting.fixed-assets.depreciation.index'],
                ['label' => 'Asset Disposals', 'route' => 'accounting.fixed-assets.disposals.index'],
                ['label' => 'Asset Write-offs', 'route' => 'accounting.fixed-assets.write-offs.index'],
                ['label' => 'Asset Revaluations', 'route' => 'accounting.fixed-assets.revaluations.index'],
                ['label' => 'Budgets', 'route' => 'accounting.budgets.index'],
                ['label' => 'Journals', 'route' => 'accounting.journals.index'],
                ['label' => 'Payment Vouchers', 'route' => 'accounting.vouchers.payment.index'],
                ['label' => 'Receipt Vouchers', 'route' => 'accounting.vouchers.receipt.index'],
                ['label' => 'Contra Vouchers', 'route' => 'accounting.vouchers.contra.index'],
                ['label' => 'Credit Notes', 'route' => 'accounting.vouchers.credit_note.index'],
                ['label' => 'Debit Notes', 'route' => 'accounting.vouchers.debit_note.index'],
                ['label' => 'Bank Reconciliation', 'route' => 'accounting.bank-reconciliation.index'],
                ['label' => 'Fiscal Years & Periods', 'route' => 'accounting.fiscal-years.index'],
                ['label' => 'Tax Rates', 'route' => 'accounting.tax-rates.index'],
                ['label' => 'Exchange Rates', 'route' => 'accounting.exchange-rates.index'],
                ['label' => 'Day Book', 'route' => 'accounting.reports.day-book'],
                ['label' => 'Trial Balance', 'route' => 'accounting.reports.trial-balance'],
                ['label' => 'General Ledger', 'route' => 'accounting.reports.general-ledger'],
                ['label' => 'Party Ledger', 'route' => 'accounting.reports.party-ledger'],
                ['label' => 'Balance Sheet', 'route' => 'accounting.reports.balance-sheet'],
                ['label' => 'Profit & Loss', 'route' => 'accounting.reports.profit-loss'],
                ['label' => 'AR Aging', 'route' => 'accounting.reports.ar-aging'],
                ['label' => 'AP Aging', 'route' => 'accounting.reports.ap-aging'],
                ['label' => 'Cash Flow', 'route' => 'accounting.reports.cash-flow'],
                ['label' => 'GST Summary', 'route' => 'accounting.reports.gst-summary'],
                ['label' => 'GSTR-1', 'route' => 'accounting.reports.gstr1'],
                ['label' => 'GSTR-3B', 'route' => 'accounting.reports.gstr3b'],
                ['label' => 'Audit Trail', 'route' => 'accounting.reports.audit-trail'],
                ['label' => 'Budget vs Actual', 'route' => 'accounting.reports.budget-vs-actual'],
            ]],
            ['label' => 'Reports & BI', 'icon' => 'feather-bar-chart-2', 'url' => '#', 'children' => ['Financials', 'Sales Analytics', 'Inventory Aging', 'Payroll Summary']],
        ],
        __('ui.platform_admin') => [
            ['label' => __('ui.access_control'), 'icon' => 'feather-shield', 'url' => '#', 'children' => [
                ['label' => 'Users', 'route' => 'access.users.index'],
                ['label' => 'Roles', 'route' => 'access.roles.index'],
                ['label' => 'Permissions', 'route' => 'access.roles.index'],
                'Teams',
                'Policies',
            ]],
            ['label' => 'Automation', 'icon' => 'feather-zap', 'url' => '#', 'children' => [
                ['label' => 'Notification Master', 'route' => 'platform.notification-rules.index'],
                ['label' => 'Email Settings (SMTP)', 'route' => 'platform.emailSettings.index'],
                ['label' => 'WhatsApp Settings', 'route' => 'platform.whatsappSettings.index'],
                ['label' => 'GST & E-Invoice Settings', 'route' => 'platform.gstSettings.index'],
                'Workflows',
                'Schedulers',
                'Webhooks',
            ]],
            ['label' => 'Audit & Settings', 'icon' => 'feather-settings', 'url' => '#', 'children' => [
                ['label' => 'Currencies', 'route' => 'platform.currencies.index'],
                ['label' => 'Payment Terms', 'route' => 'platform.payment-terms.index'],
                ['label' => 'Transporters', 'route' => 'platform.transporters.index'],
                ['label' => 'Tenants', 'route' => 'platform.tenants.index'],
                ['label' => 'Plans', 'route' => 'platform.plans.index'],
                'Audit Logs',
            ]],
        ],
    ];

    // Two independent filters, both must pass for a module to show:
    // 1. tenant_allowed_modules() — is this module in the tenant's subscribed plan?
    // 2. AccessService::allowedModulesFor() — does this user's role have any
    //    permission grant in this module at all? A CRM-only plan with an
    //    HR-role user viewing it would otherwise show CRM items that role
    //    can't actually do anything with.
    // Either returning null means "unrestricted" for that dimension.
    $allowedModules = tenant_allowed_modules();
    $allowedModulesForUser = auth()->user()
        ? app(\App\Services\Access\AccessService::class)->allowedModulesFor(auth()->user())
        : null;

    if ($allowedModules !== null || $allowedModulesForUser !== null) {
        $isRouteAllowed = function (?string $routeName) use ($allowedModules, $allowedModulesForUser) {
            if ($routeName === null) {
                return true;
            }

            $module = explode('.', $routeName)[0];

            if (! in_array($module, \App\Http\Middleware\EnsureTenantModuleAccess::GATED_MODULES, true)) {
                return true;
            }

            if ($allowedModules !== null && ! in_array($module, $allowedModules, true)) {
                return false;
            }

            if ($allowedModulesForUser !== null && ! in_array($module, $allowedModulesForUser, true)) {
                return false;
            }

            return true;
        };

        foreach ($modules as $caption => &$items) {
            foreach ($items as $key => &$item) {
                if (isset($item['children']) && !empty($item['children'])) {
                    // A placeholder child with no 'route' (an unimplemented
                    // link, e.g. 'Tasks') would otherwise always pass the
                    // filter below regardless of module, keeping the whole
                    // group visible even after every real route in it was
                    // correctly hidden. Decide the group's module from the
                    // first routed child instead, and drop the entire group
                    // — placeholders included — if that module is disallowed.
                    $firstRoute = null;

                    foreach ($item['children'] as $child) {
                        if (is_array($child) && isset($child['route'])) {
                            $firstRoute = $child['route'];
                            break;
                        }
                    }

                    if ($firstRoute !== null && ! $isRouteAllowed($firstRoute)) {
                        unset($items[$key]);
                        continue;
                    }

                    $item['children'] = array_values(array_filter($item['children'], function ($child) use ($isRouteAllowed) {
                        $childRoute = is_array($child) ? ($child['route'] ?? null) : null;

                        return $isRouteAllowed($childRoute);
                    }));

                    if (empty($item['children'])) {
                        unset($items[$key]);
                    }
                } elseif (isset($item['route']) && ! $isRouteAllowed($item['route'])) {
                    unset($items[$key]);
                }
            }
            unset($item);

            $items = array_values($items);

            if (empty($items)) {
                unset($modules[$caption]);
            }
        }
        unset($items);
    }
    // Menu entries live in each module's Routes/menu.php and are filtered by plan,
    // role, permission and route existence — see App\Core\Navigation\MenuBuilder.
    // While you are inside an app (CRM, Inventory, ...) only that app's menu is listed.
    $nav = app(\App\Core\Navigation\MenuBuilder::class)->navigation(auth()->user(), request()->route()?->getName());
    $sections = $nav['sections'];
@endphp

<nav class="nxl-navigation">
    <div class="navbar-wrapper">
        <div class="m-header">
            <a href="{{ route('dashboard') }}" class="b-brand erp-tenant-brand">
                @if ($branding['has_full_logo'])
                    <img src="{{ $branding['full_logo'] }}" alt="{{ $branding['name'] }}" class="logo logo-lg logo-full erp-brand-logo-full">
                @else
                    <span class="logo logo-lg logo-full erp-brand-wordmark">{{ $branding['name'] }}</span>
                @endif

                @if ($branding['has_abbr_logo'])
                    <img src="{{ $branding['abbr_logo'] }}" alt="{{ $branding['name'] }}" class="logo logo-sm logo-abbr erp-brand-logo-abbr">
                @else
                    <span class="logo logo-sm logo-abbr erp-brand-mark">{{ strtoupper(substr($branding['name'], 0, 1)) }}</span>
                @endif
            </a>
        </div>
        <div class="navbar-content">
            <ul class="nxl-navbar">
                @if ($nav['app'] !== null)
                    @php
                        $currentApp = $nav['apps'][$nav['app']];
                    @endphp
                    <li class="nxl-item nxl-caption app-context" data-nav-app="{{ $nav['app'] }}">
                        <a href="{{ route('dashboard') }}" class="app-context-home">
                            <i class="feather-arrow-left"></i> {{ __('ui.workspace') }}
                        </a>
                        <button type="button" class="app-context-current" id="app-switcher-toggle" aria-expanded="false" aria-controls="app-switcher-list">
                            <span class="app-context-icon erp-app-icon" style="background: {{ $currentApp['color'] ?? '#3B82F6' }}"><i class="{{ $currentApp['icon'] }}"></i></span>
                            <span class="app-context-name">{{ $currentApp['label'] }}</span>
                            <i class="feather-chevron-down app-context-caret"></i>
                        </button>
                        <div class="app-context-list" id="app-switcher-list" hidden>
                            @foreach ($nav['apps'] as $app)
                                <a href="{{ $app['url'] }}" class="app-context-option {{ $app['active'] ? 'active' : '' }}">
                                    <span class="erp-app-icon erp-app-icon-sm" style="background: {{ $app['color'] ?? '#3B82F6' }}"><i class="{{ $app['icon'] }}"></i></span>
                                    <span>{{ $app['label'] }}</span>
                                </a>
                            @endforeach
                        </div>
                    </li>
                    @foreach ($nav['items'] as $item)
                        @include('partials.duralux.sidebar-item', ['item' => $item])
                    @endforeach
                @else
                    {{-- On the workspace home, the sidebar stays to workspace-level items (not every
                         module stacked) — modules are opened from the Apps grid or the header launcher. --}}
                    <li class="nxl-item nxl-caption">
                        <span>{{ __('ui.workspace') }}</span>
                    </li>
                    <li class="nxl-item {{ request()->routeIs('apps') ? 'active' : '' }}">
                        <a href="{{ route('apps') }}" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-grid"></i></span>
                            <span class="nxl-mtext">{{ __('ui.modules') }}</span>
                        </a>
                    </li>
                    @foreach ($sections as $section)
                        @continue($section['key'] !== 'workspace')
                        @foreach ($section['items'] as $item)
                            @include('partials.duralux.sidebar-item', ['item' => $item, 'class' => 'module-'.$section['slug']])
                        @endforeach
                    @endforeach
                @endif
            </ul>
            <div class="card text-center">
                <div class="card-body">
                    <i class="feather-activity fs-4 text-dark"></i>
                    <h6 class="mt-4 text-dark fw-bolder">{{ $resolvedTenant?->name ?? 'Central Workspace' }}</h6>
                    <p class="fs-11 my-3 text-dark">{{ $tenantSettings['branch'] ?? 'Main Office' }}<br>{{ $tenantPlan }} Plan</p>
                    <a href="{{ route('dashboard') }}" class="btn btn-primary text-dark w-100">{{ __('ui.tenant_dashboard') }}</a>
                </div>
            </div>
        </div>
    </div>
</nav>

{{-- PREMIUM SIDEBAR ACCORDION & TIMELINE DESIGN SYSTEM --}}
<style>
:root {
    --sidebar-primary: var(--bs-primary, #3B82F6);
    --sidebar-primary-hover: var(--bs-primary, #2563EB);
    --sidebar-text: #475569;
    --sidebar-heading: #64748b;
    --sidebar-muted: #94a3b8;
}

.nxl-navigation .nxl-navbar a {
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1) !important;
    border: 1px solid transparent;
}

/* Hover Link */
.nxl-navigation .nxl-navbar li:hover > a {
    color: var(--bs-primary) !important;
    transform: translateX(4px);
    background: rgba(var(--bs-primary-rgb, 59, 130, 246), 0.08) !important;
    border-radius: 10px;
}

/* Hover Icon & Arrow */
.nxl-navigation .nxl-navbar li:hover > a .nxl-micon i,
.nxl-navigation .nxl-navbar li:hover > a .nxl-arrow i {
    color: var(--bs-primary) !important;
    transition: color 0.25s ease;
}

/* Active Main Item Link (Soft Light Primary Background + Primary Text) */
.nxl-navigation .nxl-navbar > li.active > a,
.nxl-navigation .nxl-navbar li.nxl-hasmenu.active > a {
    background: rgba(var(--bs-primary-rgb, 59, 130, 246), 0.12) !important;
    color: var(--bs-primary) !important;
    border-radius: 10px !important;
    border: 1px solid rgba(var(--bs-primary-rgb, 59, 130, 246), 0.22) !important;
    box-shadow: 0 2px 8px rgba(var(--bs-primary-rgb, 59, 130, 246), 0.08) !important;
}

/* Active Main Item Icon, Arrow, and Text */
.nxl-navigation .nxl-navbar > li.active > a .nxl-micon,
.nxl-navigation .nxl-navbar > li.active > a .nxl-micon i,
.nxl-navigation .nxl-navbar > li.active > a .nxl-mtext,
.nxl-navigation .nxl-navbar > li.active > a .nxl-arrow,
.nxl-navigation .nxl-navbar > li.active > a .nxl-arrow i,
.nxl-navigation .nxl-navbar li.nxl-hasmenu.active > a .nxl-micon,
.nxl-navigation .nxl-navbar li.nxl-hasmenu.active > a .nxl-micon i,
.nxl-navigation .nxl-navbar li.nxl-hasmenu.active > a .nxl-mtext,
.nxl-navigation .nxl-navbar li.nxl-hasmenu.active > a .nxl-arrow i {
    color: var(--bs-primary) !important;
    font-weight: 700 !important;
}

/* Module Headers */
.premium-module-header {
    cursor: pointer;
    user-select: none;
    padding: 22px 24px 10px 24px !important;
    background: transparent !important;
    border: none !important;
    display: block !important;
}

.premium-module-header-content {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
}

.premium-module-header-title {
    font-size: 10px !important;
    font-weight: 800 !important;
    letter-spacing: 1.2px;
    text-transform: uppercase;
    color: var(--sidebar-heading) !important;
    transition: color 0.25s ease;
}

.premium-module-header:hover .premium-module-header-title {
    color: var(--bs-primary) !important;
}

.premium-module-accordion-btn {
    color: var(--sidebar-muted);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: all 0.25s ease;
}

.premium-module-header:hover .premium-module-accordion-btn {
    color: var(--bs-primary);
}

/* Chevron */
.premium-module-arrow-container {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important;
}

.premium-module-arrow {
    font-size: 13px !important;
}

.premium-module-header .premium-module-arrow-container {
    transform: rotate(90deg) !important;
}

.premium-module-header.collapsed .premium-module-arrow-container {
    transform: rotate(0deg) !important;
}

/* Timeline */
.nxl-navigation .nxl-submenu {
    position: relative;
    padding-left: 20px !important;
    margin-left: 32px !important;
    margin-top: 6px !important;
    margin-bottom: 8px !important;
    border-left: 1.5px dashed rgba(var(--bs-primary-rgb, 59, 130, 246), 0.4) !important;
    transition: border-color 0.3s ease;
    background: transparent !important;
}

.nxl-navigation .nxl-navbar li:hover > .nxl-submenu {
    border-left-color: var(--bs-primary) !important;
}

.nxl-navigation .nxl-navbar li.active > .nxl-submenu {
    border-left-color: var(--bs-primary) !important;
}

/* Timeline Nodes */
.nxl-navigation .nxl-submenu li {
    position: relative;
    list-style: none !important;
}

.nxl-navigation .nxl-submenu li::before {
    content: "";
    position: absolute;
    left: -21px;
    top: 50%;
    transform: translateY(-50%);
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background-color: rgba(var(--bs-primary-rgb, 59, 130, 246), 0.45);
    border: 1.5px solid #fff;
    transition: all 0.25s ease;
    z-index: 5;
}

.nxl-navigation .nxl-submenu li:hover::before {
    background-color: var(--bs-primary) !important;
    transform: translateY(-50%) scale(1.5);
    box-shadow: 0 0 10px var(--bs-primary);
}

.nxl-navigation .nxl-submenu li.active::before {
    background-color: var(--bs-primary) !important;
    transform: translateY(-50%) scale(1.5) !important;
    box-shadow: 0 0 8px rgba(var(--bs-primary-rgb, 59, 130, 246), 0.6) !important;
}

.nxl-navigation .nxl-submenu li a::before {
    display: none !important;
}

/* Submenu Links */
.nxl-navigation .nxl-submenu .nxl-link {
    transition: all 0.2s ease !important;
    font-size: 13px !important;
    font-weight: 500 !important;
    color: var(--sidebar-text) !important;
    padding: 8px 10px !important;
    background: transparent !important;
    border: none !important;
}

.nxl-navigation .nxl-submenu .nxl-link:hover {
    padding-left: 12px !important;
    color: var(--bs-primary) !important;
    transform: none !important;
}

/* Active Submenu Item (Soft Light Primary Background + Primary Text) */
.nxl-navigation .nxl-submenu li.active > .nxl-link {
    background: rgba(var(--bs-primary-rgb, 59, 130, 246), 0.15) !important;
    color: var(--bs-primary) !important;
    font-weight: 700 !important;
    border-radius: 8px !important;
    padding-left: 12px !important;
    border: 1px solid rgba(var(--bs-primary-rgb, 59, 130, 246), 0.22) !important;
}

/* Nested Submenus */
.nxl-navigation .nxl-submenu .nxl-submenu {
    border-left: 1.5px dashed var(--sidebar-timeline) !important;
    padding-left: 15px !important;
    margin-left: 15px !important;
}

.nxl-navigation .navbar-content .nxl-submenu .nxl-link {
    margin-left: 0 !important;
}

/* =========================================================
   DARK MODE SUPPORT FOR SIDEBAR NAVIGATION & SUBMENUS
   ========================================================= */
html.app-skin-dark {
    --sidebar-text: #cbd5e1;
    --sidebar-heading: #94a3b8;
    --sidebar-muted: #64748b;
}

html.app-skin-dark .nxl-navigation .nxl-navbar a {
    color: #cbd5e1;
}

/* Hover Main Item Link in Dark Mode */
html.app-skin-dark .nxl-navigation .nxl-navbar li:hover > a {
    color: #ffffff !important;
    background: rgba(255, 255, 255, 0.07) !important;
}

html.app-skin-dark .nxl-navigation .nxl-navbar li:hover > a .nxl-micon i,
html.app-skin-dark .nxl-navigation .nxl-navbar li:hover > a .nxl-arrow i {
    color: #ffffff !important;
}

/* Open / Active Main Item (e.g. Execution tab) in Dark Mode */
html.app-skin-dark .nxl-navigation .nxl-navbar > li.active > a,
html.app-skin-dark .nxl-navigation .nxl-navbar li.nxl-hasmenu.active > a,
html.app-skin-dark .nxl-navigation .nxl-navbar li.nxl-hasmenu.open > a,
html.app-skin-dark .nxl-navigation .nxl-navbar li.nxl-hasmenu.nxl-trigger > a {
    background: color-mix(in srgb, var(--bs-primary) 22%, transparent) !important;
    border: 1px solid color-mix(in srgb, var(--bs-primary) 40%, transparent) !important;
    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.25) !important;
}

html.app-skin-dark .nxl-navigation .nxl-navbar > li.active > a .nxl-mtext,
html.app-skin-dark .nxl-navigation .nxl-navbar li.nxl-hasmenu.active > a .nxl-mtext,
html.app-skin-dark .nxl-navigation .nxl-navbar li.nxl-hasmenu.open > a .nxl-mtext,
html.app-skin-dark .nxl-navigation .nxl-navbar li.nxl-hasmenu.nxl-trigger > a .nxl-mtext {
    color: #ffffff !important;
    font-weight: 700 !important;
}

html.app-skin-dark .nxl-navigation .nxl-navbar > li.active > a .nxl-micon,
html.app-skin-dark .nxl-navigation .nxl-navbar > li.active > a .nxl-micon i,
html.app-skin-dark .nxl-navigation .nxl-navbar li.nxl-hasmenu.active > a .nxl-micon,
html.app-skin-dark .nxl-navigation .nxl-navbar li.nxl-hasmenu.active > a .nxl-micon i,
html.app-skin-dark .nxl-navigation .nxl-navbar li.nxl-hasmenu.open > a .nxl-micon i,
html.app-skin-dark .nxl-navigation .nxl-navbar li.nxl-hasmenu.nxl-trigger > a .nxl-micon i {
    color: color-mix(in srgb, var(--bs-primary) 70%, #ffffff) !important;
}

html.app-skin-dark .nxl-navigation .nxl-navbar > li.active > a .nxl-arrow,
html.app-skin-dark .nxl-navigation .nxl-navbar > li.active > a .nxl-arrow i,
html.app-skin-dark .nxl-navigation .nxl-navbar li.nxl-hasmenu.active > a .nxl-arrow i,
html.app-skin-dark .nxl-navigation .nxl-navbar li.nxl-hasmenu.open > a .nxl-arrow i,
html.app-skin-dark .nxl-navigation .nxl-navbar li.nxl-hasmenu.nxl-trigger > a .nxl-arrow i {
    color: #ffffff !important;
}

/* Timeline border in Dark Mode */
html.app-skin-dark .nxl-navigation .nxl-submenu {
    border-left: 1.5px dashed color-mix(in srgb, var(--bs-primary) 45%, transparent) !important;
}

html.app-skin-dark .nxl-navigation .nxl-navbar li:hover > .nxl-submenu,
html.app-skin-dark .nxl-navigation .nxl-navbar li.active > .nxl-submenu {
    border-left-color: var(--bs-primary) !important;
}

/* Timeline node dots in Dark Mode */
html.app-skin-dark .nxl-navigation .nxl-submenu li::before {
    background-color: #0f172a !important;
    border: 1.5px solid #94a3b8 !important;
}

html.app-skin-dark .nxl-navigation .nxl-submenu li:hover::before {
    background-color: color-mix(in srgb, var(--bs-primary) 75%, #ffffff) !important;
    border-color: #ffffff !important;
    box-shadow: 0 0 10px color-mix(in srgb, var(--bs-primary) 65%, transparent) !important;
}

html.app-skin-dark .nxl-navigation .nxl-submenu li.active::before {
    background-color: color-mix(in srgb, var(--bs-primary) 70%, #ffffff) !important;
    border: 2px solid #ffffff !important;
    box-shadow: 0 0 12px color-mix(in srgb, var(--bs-primary) 70%, transparent) !important;
}

/* Submenu Links in Dark Mode (High contrast, bright & crisp) */
html.app-skin-dark .nxl-navigation .nxl-submenu .nxl-link {
    color: #cbd5e1 !important;
}

html.app-skin-dark .nxl-navigation .nxl-submenu .nxl-link:hover {
    color: #ffffff !important;
    background: rgba(255, 255, 255, 0.08) !important;
}

/* Active Submenu Item (e.g. Production Orders) in Dark Mode */
html.app-skin-dark .nxl-navigation .nxl-submenu li.active > .nxl-link {
    background: color-mix(in srgb, var(--bs-primary) 32%, transparent) !important;
    color: #ffffff !important;
    font-weight: 700 !important;
    border: 1px solid color-mix(in srgb, var(--bs-primary) 55%, transparent) !important;
    box-shadow: 0 2px 8px color-mix(in srgb, var(--bs-primary) 35%, transparent) !important;
}

/* Module Section Header in Dark Mode */
html.app-skin-dark .premium-module-header-title {
    color: #94a3b8 !important;
}

html.app-skin-dark .premium-module-header:hover .premium-module-header-title {
    color: #ffffff !important;
}

html.app-skin-dark .premium-module-accordion-btn {
    color: #64748b !important;
}

html.app-skin-dark .premium-module-header:hover .premium-module-accordion-btn {
    color: #ffffff !important;
}
</style>

{{-- App context header (shown while you are inside an app) --}}
<style>
.nxl-navigation .app-context { padding: 14px 18px 8px 18px !important; display: block !important; }
.nxl-navigation .app-context-home {
    display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 600;
    color: var(--sidebar-muted, #94a3b8) !important; padding: 0 !important; margin-bottom: 10px; border: 0 !important; background: transparent !important;
}
.nxl-navigation .app-context-home:hover { color: var(--bs-primary) !important; transform: none !important; background: transparent !important; }
.nxl-navigation .app-context-current {
    width: 100%; display: flex; align-items: center; gap: 10px; padding: 8px 10px; cursor: pointer; font-size: 15px;
    border: 1px solid rgba(var(--bs-primary-rgb, 59, 130, 246), 0.22); border-radius: 12px;
    background: rgba(var(--bs-primary-rgb, 59, 130, 246), 0.08); color: var(--bs-primary); font-weight: 700; font-size: 14px; text-align: left;
}
.nxl-navigation .app-context-icon {
    flex: 0 0 34px; width: 34px; height: 34px; display: inline-flex; align-items: center; justify-content: center;
    border-radius: 10px; background: var(--bs-primary, #3B82F6); color: #fff;
}
.nxl-navigation .app-context-icon i { font-size: 17px !important; color: #fff !important; }
.nxl-navigation .app-context-name { flex: 1; font-size: 15px !important; font-weight: 700 !important; color: var(--bs-primary) !important; text-transform: none !important; letter-spacing: 0 !important; line-height: 1.2; }
.nxl-navigation .app-context-caret { transition: transform .2s; }
.nxl-navigation .app-context-current[aria-expanded="true"] .app-context-caret { transform: rotate(180deg); }
.nxl-navigation .app-context-list { margin-top: 6px; border: 1px solid rgba(0, 0, 0, 0.08); border-radius: 12px; padding: 4px; background: var(--bs-body-bg, #fff); }
.nxl-navigation .app-context-list[hidden] { display: none; }
.nxl-navigation .app-context-option {
    display: flex; align-items: center; gap: 10px; padding: 8px 10px; border-radius: 8px; font-size: 13px; font-weight: 500;
    color: var(--sidebar-text, #475569) !important; text-transform: none; border: 0 !important;
}
.nxl-navigation .app-context-option:hover, .nxl-navigation .app-context-option.active { background: rgba(var(--bs-primary-rgb, 59, 130, 246), 0.1) !important; color: var(--bs-primary) !important; transform: none !important; }
</style>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const toggle = document.getElementById('app-switcher-toggle');
    const list = document.getElementById('app-switcher-list');
    if (!toggle || !list) return;

    toggle.addEventListener('click', function () {
        const open = list.hasAttribute('hidden');
        list.toggleAttribute('hidden', !open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
});
</script>

