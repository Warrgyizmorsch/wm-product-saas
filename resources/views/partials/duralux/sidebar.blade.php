@php
    $resolvedTenant = tenant();
    $tenantSettings = $resolvedTenant?->settings ?? [];
    $tenantPlan = ucfirst((string) ($resolvedTenant?->plan ?? 'Starter'));
    $branding = tenant_branding($resolvedTenant);

    $modules = [
        __('ui.workspace') => [
            ['label' => __('ui.executive_dashboard'), 'icon' => 'feather-home', 'route' => 'dashboard'],
            ['label' => 'Tenant Console', 'icon' => 'feather-grid', 'url' => '#', 'children' => [
                ['label' => 'Tenants', 'route' => 'platform.tenants.index'],
                ['label' => 'Subscriptions'],
                ['label' => 'Usage Limits'],
            ]],
            ['label' => __('ui.approvals_center'), 'icon' => 'feather-check-square', 'url' => '#', 'children' => ['Pending', 'Delegated', 'Escalations', 'Workflow Rules']],
        ],
        'Revenue Cycle' => [
            ['label' => __('ui.crm'), 'icon' => 'feather-users', 'url' => '#', 'children' => [
                ['label' => 'Leads', 'route' => 'crm.leads.index'],
                ['label' => 'Track Status', 'route' => 'crm.leads.trackStatus'],
                ['label' => 'Customers', 'route' => 'crm.customers.index'],
            ]],
            ['label' => 'Approvals', 'icon' => 'feather-check-circle', 'url' => '#', 'children' => [
                ['label' => 'Quotation Approval', 'route' => 'crm.approvals.quotations.index'],
            ]],
             ['label' => __('ui.sales'), 'icon' => 'feather-shopping-cart', 'url' => '#', 'children' => [
                ['label' => 'Quotations', 'route' => 'crm.quotations.index'],
                ['label' => 'Sales Orders', 'route' => 'sales.orders.index'],
                ['label' => 'Invoices', 'route' => 'sales.invoices.index'],
                ['label' => 'Receipts (Payments)', 'route' => 'sales.payments.index'],
                ['label' => 'Sales Returns', 'route' => 'sales.returns.index'],
            ]],
            ['label' => __('ui.projects'), 'icon' => 'feather-briefcase', 'url' => '#', 'children' => [
                ['label' => __('ui.projects'), 'route' => 'projects.index'],
                ['label' => __('projects.milestones'), 'route' => 'projects.milestones.index'],
                'Tasks',
                'Timesheets',
            ]],
        ],
        __('ui.supply_chain') => [
            ['label' => 'Store', 'icon' => 'feather-archive', 'url' => '#', 'children' => [
                ['label' => 'Material Requirements', 'route' => 'sales.material-requirements.index'],
                ['label' => 'Material Requests (Prod)', 'route' => 'sales.material-requests.index'],
                ['label' => 'Dispatch Orders', 'route' => 'sales.dispatches.index'],
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
                ['label' => __('purchase.savings_dashboard'), 'route' => 'purchase.rfqs.savings'],
                ['label' => __('ui.purchase_requests') ?: __('purchase.purchase_requests'), 'route' => 'purchase.requisitions.index'],
                ['label' => __('purchase.pending_pr_items'), 'route' => 'purchase.requisitions.pending-items'],
                ['label' => __('purchase.rfqs'), 'route' => 'purchase.rfqs.index'],
                ['label' => __('purchase.purchase_orders'), 'route' => 'purchase.orders.index'],
                ['label' => __('purchase.pending_grns'), 'route' => 'purchase.grns.pending'],
                ['label' => __('purchase.all_goods_receipts'), 'route' => 'purchase.grns.index'],
                ['label' => 'Landed Cost Vouchers', 'route' => 'purchase.landed-costs.index'],
                ['label' => __('purchase.vendor_bills'), 'route' => 'purchase.bills.index'],
                ['label' => __('purchase.vendor_payments'), 'route' => 'purchase.payments.index'],
            ]],
            ['label' => 'Purchase Approvals', 'icon' => 'feather-check-circle', 'url' => '#', 'children' => [
                ['label' => 'PR Approvals', 'route' => 'purchase.pr-approvals.index'],
                ['label' => 'PO Approvals', 'route' => 'purchase.po-approvals.index'],
            ]],
        ],
        __('ui.production') => [
            // ── 1. Production Masters (Setup & Configuration) ──────────────────
            ['label' => 'Production Masters', 'icon' => 'feather-settings', 'url' => '#', 'children' => [
                ['label' => __('production.bom'),          'route' => 'production.boms.index'],
                ['label' => __('production.routing'),       'route' => 'production.routing.index'],
                ['label' => __('production.work_centers'),  'route' => 'production.work-centers.index'],
                ['label' => __('production.machines'),      'route' => 'production.machines.index'],
                ['label' => 'Operator Skills',              'route' => 'production.operator-skills.index'],
                ['label' => 'Quality Plans',                'route' => 'production.quality-plans.index'],
                ['label' => __('production.shifts_sidebar'),    'route' => 'production.shifts.index'],
                ['label' => __('production.calendars_sidebar'), 'route' => 'production.calendars.index'],
            ]],

            // ── 2. Production Orders (Step 1: Create & Release) ───────────────
            ['label' => 'Production Orders', 'icon' => 'feather-play-circle', 'route' => 'production.orders.index'],

            // ── 3. Scheduling (Step 2: Plan Operations) ───────────────────────
            ['label' => 'Scheduling', 'icon' => 'feather-calendar', 'url' => '#', 'children' => [
                ['label' => 'Production Schedules', 'route' => 'production.schedules.index'],
                ['label' => 'Calendar View',         'route' => 'production.schedules.calendar'],
            ]],

            // ── 4. Work-in-Progress (Step 3: Track Execution) ─────────────────
            ['label' => 'Work-in-Progress (WIP)', 'icon' => 'feather-layers', 'route' => 'production.wip.index'],

            // ── 5. Shop Floor — MES (Step 4: Execute on Floor) ───────────────
            ['label' => 'Shop Floor (MES)', 'icon' => 'feather-activity', 'url' => '#', 'children' => [
                ['label' => 'Shop Floor Dashboard',   'route' => 'production.mes.dashboard'],
                ['label' => 'MES Operator Console',   'route' => 'production.mes.operator.dashboard'],
                ['label' => 'Work Center Monitor',    'route' => 'production.mes.work-centers.index'],
                ['label' => 'Machine Monitor',        'route' => 'production.mes.machines.index'],
                ['label' => 'Barcode Scanner',        'route' => 'production.mes.scanner.index'],
            ]],

            // ── 6. Quality Management ──────────────────────────────────────────
            ['label' => 'Quality Management', 'icon' => 'feather-check-circle', 'url' => '#', 'children' => [
                ['label' => 'Quality Dashboard',    'route' => 'production.quality.dashboard'],
                ['label' => 'Quality Inspections',  'route' => 'production.inspections.index'],
                ['label' => 'NCR',                  'route' => 'production.ncrs.index'],
                ['label' => 'CAPA',                 'route' => 'production.capas.index'],
                ['label' => 'Rework Orders',        'route' => 'production.rework.index'],
                ['label' => 'Scrap Disposals',      'route' => 'production.scrap.index'],
            ]],

            // ── 7. Manufacturing Intelligence ──────────────────────────────────
            ['label' => 'Manufacturing Intelligence', 'icon' => 'feather-bar-chart-2', 'url' => '#', 'children' => [
                ['label' => 'Executive Dashboard',    'route' => 'production.intelligence.dashboard'],
                ['label' => 'Live Andon Board',       'route' => 'production.intelligence.andon'],
                ['label' => 'Historical Analytics',   'route' => 'production.intelligence.analytics'],
                ['label' => 'Manufacturing Reports',  'route' => 'production.intelligence.reports.index'],
            ]],
        ],
        'HRMS' => [
            ['label' => 'Employees', 'icon' => 'feather-users', 'route' => 'hrms.employees.index'],
            ['label' => 'Attendance', 'icon' => 'feather-clock', 'route' => 'hrms.attendance.index'],
            ['label' => 'Leave', 'icon' => 'feather-calendar', 'route' => 'hrms.leaves.index'],
            ['label' => 'WFH', 'icon' => 'feather-home', 'route' => 'hrms.wfh.index'],
            ['label' => 'Shift & Overtime', 'icon' => 'feather-activity', 'route' => 'hrms.shift-overtime.index'],
            ['label' => 'Payroll', 'icon' => 'feather-dollar-sign', 'url' => '#'],
            ['label' => 'Setting', 'icon' => 'feather-settings', 'route' => 'hrms.org.index'],
        ],
        'Finance & People' => [
            ['label' => 'Accounting', 'icon' => 'feather-credit-card', 'url' => '#', 'children' => [
                ['label' => 'Chart of Accounts', 'route' => 'accounting.chart-of-accounts.index'],
                ['label' => 'Journals', 'route' => 'accounting.journals.index'],
                ['label' => 'Fiscal Years & Periods', 'route' => 'accounting.fiscal-years.index'],
                ['label' => 'Tax Rates', 'route' => 'accounting.tax-rates.index'],
                ['label' => 'Trial Balance', 'route' => 'accounting.reports.trial-balance'],
                ['label' => 'General Ledger', 'route' => 'accounting.reports.general-ledger'],
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
            ['label' => 'Automation', 'icon' => 'feather-zap', 'url' => '#', 'children' => ['Workflows', 'Alerts', 'Schedulers', 'Webhooks']],
            ['label' => 'Audit & Settings', 'icon' => 'feather-settings', 'url' => '#', 'children' => ['Audit Logs', 'Localization', 'Currencies', 'System Settings']],
        ],
    ];
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
                @foreach ($modules as $caption => $items)
                    <li class="nxl-item nxl-caption">
                        <label>{{ $caption }}</label>
                    </li>
                    @foreach ($items as $item)
                        @php
                            $href = isset($item['route']) ? route($item['route']) : $item['url'];
                            $hasChildren = isset($item['children']);
                        @endphp
                        <li class="nxl-item {{ $hasChildren ? 'nxl-hasmenu' : '' }} {{ isset($item['route']) && request()->routeIs($item['route']) ? 'active' : '' }}">
                            <a href="{{ $hasChildren ? 'javascript:void(0);' : $href }}" class="nxl-link">
                                <span class="nxl-micon"><i class="{{ $item['icon'] }}"></i></span>
                                <span class="nxl-mtext">{{ $item['label'] }}</span>
                                @if ($hasChildren)
                                    <span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                                @endif
                            </a>
                            @if ($hasChildren)
                                <ul class="nxl-submenu">
                                    @foreach ($item['children'] as $child)
                                        @php
                                            $child = is_array($child) ? $child : ['label' => $child];
                                            $childHref = isset($child['route']) ? route($child['route']) : ($child['url'] ?? '#');
                                        @endphp
                                        <li class="nxl-item">
                                            <a class="nxl-link" href="{{ $childHref }}">{{ $child['label'] }}</a>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </li>
                    @endforeach
                @endforeach
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
