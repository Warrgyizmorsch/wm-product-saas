@php
    $resolvedTenant = tenant();
    $tenantSettings = $resolvedTenant?->settings ?? [];
    $tenantPlan = ucfirst((string) ($resolvedTenant?->plan ?? 'Starter'));
    $branding = tenant_branding($resolvedTenant);

    // Identify user role to determine sidebar options
    $userRole = auth()->user()->role ?? '';
    $userRoleSlug = auth()->user()->primaryRole->slug ?? '';
    
    $isStoreUser = in_array(strtolower($userRole), ['store', 'storekeeper']) 
        || in_array(strtolower($userRoleSlug), ['store', 'storekeeper']);

    if ($isStoreUser) {
        $modules = [
            __('ui.workspace') => [
                ['label' => __('ui.executive_dashboard'), 'icon' => 'feather-home', 'route' => 'dashboard'],
            ],
            'Store Operations' => [
                ['label' => 'Store', 'icon' => 'feather-archive', 'url' => '#', 'children' => [
                    ['label' => 'Delivery Orders', 'route' => 'sales.deliveries.index'],
                    ['label' => 'Dispatch Orders', 'route' => 'sales.dispatches.index'],
                ]],
            ]
        ];
    } else {
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
                    ['label' => 'Contacts'],
                    ['label' => 'Activities'],
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
                    'Milestones',
                    'Tasks',
                    'Timesheets',
                ]],
            ],
            __('ui.supply_chain') => [
                ['label' => 'Store', 'icon' => 'feather-archive', 'url' => '#', 'children' => [
                    ['label' => 'Delivery Orders', 'route' => 'sales.deliveries.index'],
                    ['label' => 'Dispatch Orders', 'route' => 'sales.dispatches.index'],
                ]],
                ['label' => __('ui.inventory'), 'icon' => 'feather-box', 'url' => '#', 'children' => [
                    ['label' => 'Items', 'route' => 'inventory.products.index'],
                    ['label' => 'Warehouses', 'route' => 'inventory.warehouses.index'],
                    'Stock Moves',
                    'Adjustments',
                ]],
                ['label' => __('ui.purchase'), 'icon' => 'feather-truck', 'url' => '#', 'children' => ['Suppliers', 'Requests', 'Purchase Orders', 'Bills']],
            ],
            __('ui.production') => [
                ['label' => 'Production Engineering', 'icon' => 'feather-cpu', 'url' => '#', 'children' => [
                    ['label' => 'BOM', 'route' => 'production.boms.index'],
                    ['label' => 'Routing', 'route' => 'production.routing.index'],
                    ['label' => 'Work Centers', 'route' => 'production.work-centers.index'],
                    ['label' => 'Machines', 'route' => 'production.machines.index'],
                ]],
                ['label' => 'Production Planning', 'icon' => 'feather-calendar', 'url' => '#', 'children' => [
                    ['label' => 'Production Planning', 'route' => 'production.plans.index'],
                    ['label' => 'Scheduling', 'route' => 'production.schedules.index'],
                    ['label' => 'Shifts', 'route' => 'production.shifts.index'],
                    ['label' => 'Calendars', 'route' => 'production.calendars.index'],
                ]],
                ['label' => 'Production Execution', 'icon' => 'feather-play-circle', 'url' => '#', 'children' => [
                    ['label' => 'Production Orders', 'route' => 'production.orders.index'],
                ]],
                ['label' => 'Shop Floor (MES)', 'icon' => 'feather-activity', 'url' => '#', 'children' => [
                    ['label' => 'Shop Floor Dashboard', 'route' => 'production.mes.dashboard'],
                    ['label' => 'MES Operator Console', 'route' => 'production.mes.operator.dashboard'],
                    ['label' => 'Operator Skills', 'route' => 'production.operator-skills.index'],
                    ['label' => 'Barcode Scanner', 'route' => 'production.mes.scanner.index'],
                    ['label' => 'Lot Traceability', 'route' => 'production.mes.traceability.index'],
                    ['label' => 'Production Scan Logs', 'route' => 'production.scan-logs.index'],
                    ['label' => 'Event Timeline', 'route' => 'production.mes.timeline.index'],
                ]],
                ['label' => 'Quality Management', 'icon' => 'feather-check-circle', 'url' => '#', 'children' => [
                    ['label' => 'Quality Dashboard', 'route' => 'production.quality.dashboard'],
                    ['label' => 'Quality Plans', 'route' => 'production.quality-plans.index'],
                    ['label' => 'Quality Inspections', 'route' => 'production.inspections.index'],
                    ['label' => 'NCR', 'route' => 'production.ncrs.index'],
                    ['label' => 'CAPA', 'route' => 'production.capas.index'],
                    ['label' => 'Rework Orders', 'route' => 'production.rework.index'],
                    ['label' => 'Scrap Disposals', 'route' => 'production.scrap.index'],
                    ['label' => 'Deviations & Waivers', 'route' => 'production.deviations.index'],
                ]],
                ['label' => 'Manufacturing Intelligence', 'icon' => 'feather-bar-chart-2', 'url' => '#', 'children' => [
                    ['label' => 'Executive Dashboard', 'route' => 'production.intelligence.dashboard'],
                    ['label' => 'Live Andon Board', 'route' => 'production.intelligence.andon'],
                    ['label' => 'Historical Analytics', 'route' => 'production.intelligence.analytics'],
                    ['label' => 'Manufacturing Reports', 'route' => 'production.intelligence.reports.index'],
                    ['label' => 'KPI Targets', 'route' => 'production.kpi-targets.index'],
                    ['label' => 'Alert Configuration', 'route' => 'production.intelligence.alerts.index'],
                ]],
                ['label' => 'Track Status', 'icon' => 'feather-trending-up', 'route' => 'production.track-status'],
            ],
            'Finance & People' => [
                ['label' => 'Accounting', 'icon' => 'feather-credit-card', 'url' => '#', 'children' => ['Chart of Accounts', 'Journals', 'Ledgers', 'Tax Reports']],
                ['label' => 'HR & Payroll', 'icon' => 'feather-user-check', 'url' => '#', 'children' => [['label' => 'Employees', 'route' => 'hrms.employees.index'], 'Attendance', 'Leave', 'Payroll', ['label' => 'Setting', 'route' => 'hrms.org.index']]],
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
    }
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
