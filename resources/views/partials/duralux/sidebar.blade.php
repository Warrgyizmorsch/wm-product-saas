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
                ['label' => 'Accounts (Companies)', 'route' => 'crm.accounts.index'],
                ['label' => 'Deals (Pipeline)', 'route' => 'crm.deals.kanban'],
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
                ['label' => 'Material Requirements', 'route' => 'inventory.material-requirements.index'],
                ['label' => 'Material Requests (Prod)', 'route' => 'inventory.material-requests.index'],
                ['label' => 'Dispatch Orders', 'route' => 'inventory.dispatches.index'],
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
                ['label' => 'Landed Cost Vouchers', 'route' => 'purchase.landed-costs.index'],
                ['label' => 'Pending Bills', 'route' => 'purchase.bills.pending'],
                ['label' => __('purchase.vendor_bills'), 'route' => 'purchase.bills.index'],
                ['label' => __('purchase.vendor_payments'), 'route' => 'purchase.payments.index'],
            ]],
            ['label' => 'GRN (Goods Receipts)', 'icon' => 'feather-package', 'url' => '#', 'children' => [
                ['label' => __('purchase.pending_grns'), 'route' => 'grns.pending'],
                ['label' => __('purchase.all_goods_receipts'), 'route' => 'grns.index'],
                ['label' => 'New Goods Receipt', 'route' => 'grns.create'],
            ]],
            ['label' => 'Purchase Approvals', 'icon' => 'feather-check-circle', 'url' => '#', 'children' => [
                ['label' => 'PR Approvals', 'route' => 'purchase.pr-approvals.index'],
                ['label' => 'PO Approvals', 'route' => 'purchase.po-approvals.index'],
            ]],
        ],
        __('ui.production') => [
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
            ['label' => 'Production Orders', 'icon' => 'feather-play-circle', 'route' => 'production.orders.index'],
            ['label' => 'Scheduling', 'icon' => 'feather-calendar', 'url' => '#', 'children' => [
                ['label' => 'Production Schedules', 'route' => 'production.schedules.index'],
                ['label' => 'Calendar View',         'route' => 'production.schedules.calendar'],
            ]],
            ['label' => 'Work-in-Progress (WIP)', 'icon' => 'feather-layers', 'route' => 'production.wip.index'],
            ['label' => 'Shop Floor (MES)', 'icon' => 'feather-activity', 'url' => '#', 'children' => [
                ['label' => 'Shop Floor Dashboard',   'route' => 'production.mes.dashboard'],
                ['label' => 'MES Operator Console',   'route' => 'production.mes.operator.dashboard'],
                ['label' => 'Work Center Monitor',    'route' => 'production.mes.work-centers.index'],
                ['label' => 'Machine Monitor',        'route' => 'production.mes.machines.index'],
                ['label' => 'Barcode Scanner',        'route' => 'production.mes.scanner.index'],
            ]],
            ['label' => 'Quality Management', 'icon' => 'feather-check-circle', 'url' => '#', 'children' => [
                ['label' => 'Quality Dashboard',    'route' => 'production.quality.dashboard'],
                ['label' => 'Quality Inspections',  'route' => 'production.inspections.index'],
                ['label' => 'NCR',                  'route' => 'production.ncrs.index'],
                ['label' => 'CAPA',                 'route' => 'production.capas.index'],
                ['label' => 'Rework Orders',        'route' => 'production.rework.index'],
                ['label' => 'Scrap Disposals',      'route' => 'production.scrap.index'],
            ]],
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
                    @php $modSlug = Str::slug($caption); @endphp
                    <li class="nxl-item nxl-caption premium-module-header" data-module="{{ $modSlug }}" onclick="toggleModuleSidebar('{{ $modSlug }}', this)">
                        <div class="premium-module-header-content">
                            <span class="premium-module-header-title">{{ strtoupper($caption) }}</span>
                            <span class="premium-module-accordion-btn">
                                <span class="premium-module-arrow-container">
                                    <i class="feather-chevron-right premium-module-arrow"></i>
                                </span>
                            </span>
                        </div>
                    </li>
                    @foreach ($items as $item)
                        @php
                            $href = isset($item['route']) ? route($item['route']) : ($item['url'] ?? '#');
                            $hasChildren = isset($item['children']) && !empty($item['children']);
                            $isItemActive = isset($item['route']) && request()->routeIs($item['route']);
                            $hasActiveChild = false;

                            if ($hasChildren) {
                                foreach ($item['children'] as $c) {
                                    if (is_array($c) && isset($c['route']) && request()->routeIs($c['route'])) {
                                        $hasActiveChild = true;
                                        break;
                                    }
                                }
                            }
                        @endphp
                        <li class="nxl-item {{ $hasChildren ? 'nxl-hasmenu' : '' }} {{ ($isItemActive || $hasActiveChild) ? 'active nxl-trigger' : '' }} premium-module-child module-{{ $modSlug }}">
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
                                            $childActive = isset($child['route']) && request()->routeIs($child['route']);
                                        @endphp
                                        <li class="nxl-item {{ $childActive ? 'active' : '' }}">
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
</style>

{{-- Accordion Expanded State Persistence Script --}}
<script>
function toggleModuleSidebar(moduleName, headerEl) {
    const isCollapsed = headerEl.classList.contains('collapsed');
    
    if (typeof jQuery !== 'undefined') {
        const $ = jQuery;
        const $header = $(headerEl);
        const $children = $('.premium-module-child.module-' + moduleName);
        
        if (isCollapsed) {
            $header.removeClass('collapsed');
            $children.stop(true, true).slideDown(250);
            localStorage.setItem('wm_sidebar_module_' + moduleName, 'expanded');
        } else {
            $header.addClass('collapsed');
            $children.stop(true, true).slideUp(250);
            localStorage.setItem('wm_sidebar_module_' + moduleName, 'collapsed');
        }
    } else {
        const children = document.querySelectorAll('.premium-module-child.module-' + moduleName);
        if (isCollapsed) {
            headerEl.classList.remove('collapsed');
            children.forEach(c => c.style.display = 'block');
            localStorage.setItem('wm_sidebar_module_' + moduleName, 'expanded');
        } else {
            headerEl.classList.add('collapsed');
            children.forEach(c => c.style.display = 'none');
            localStorage.setItem('wm_sidebar_module_' + moduleName, 'collapsed');
        }
    }
}

document.addEventListener("DOMContentLoaded", function () {
    const headers = document.querySelectorAll('.premium-module-header');
    
    headers.forEach(function (header) {
        const moduleName = header.getAttribute('data-module');
        const savedState = localStorage.getItem('wm_sidebar_module_' + moduleName);
        const children = document.querySelectorAll('.premium-module-child.module-' + moduleName);
        
        let hasActiveChild = false;
        children.forEach(function (child) {
            if (child.classList.contains('active') || child.querySelector('.active') !== null) {
                hasActiveChild = true;
            }
        });
        
        if (hasActiveChild) {
            header.classList.remove('collapsed');
            children.forEach(c => c.style.display = 'block');
            localStorage.setItem('wm_sidebar_module_' + moduleName, 'expanded');
        } else if (savedState === 'collapsed') {
            header.classList.add('collapsed');
            children.forEach(c => c.style.display = 'none');
        } else {
            header.classList.remove('collapsed');
            children.forEach(c => c.style.display = 'block');
        }
    });
});
</script>
