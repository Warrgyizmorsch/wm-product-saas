@php
    $resolvedTenant = tenant();
    $tenantSettings = $resolvedTenant?->settings ?? [];
    $tenantPlan = ucfirst((string) ($resolvedTenant?->plan ?? 'Starter'));
    $tenantSlug = $resolvedTenant?->slug ?? 'central';

    $currentTenant = [
        'name' => $resolvedTenant?->name ?? 'Central Workspace',
        'code' => strtoupper(str_replace('-', ' ', $tenantSlug)),
        'plan' => $tenantPlan,
        'branch' => $tenantSettings['branch'] ?? 'Main Office',
        'currency' => $tenantSettings['currency'] ?? 'INR',
        'year' => $tenantSettings['financial_year'] ?? 'FY ' . now()->format('Y'),
    ];

    $tenants = \Illuminate\Support\Facades\Schema::hasTable('tenants')
        ? \App\Models\Tenant::query()
            ->orderBy('name')
            ->get()
            ->map(fn ($tenant) => [
                'name' => $tenant->name,
                'code' => strtoupper($tenant->slug),
                'slug' => $tenant->slug,
                'active' => $resolvedTenant?->is($tenant) ?? false,
            ])
        : collect();

    $quickCreates = [
        ['label' => 'Customer', 'icon' => 'feather-user-plus'],
        ['label' => 'Sales Invoice', 'icon' => 'feather-file-plus'],
        ['label' => 'Purchase Order', 'icon' => 'feather-truck'],
        ['label' => 'Stock Adjustment', 'icon' => 'feather-package'],
        ['label' => 'Employee', 'icon' => 'feather-user-check'],
        ['label' => 'Journal Entry', 'icon' => 'feather-credit-card'],
    ];

    $moduleTabs = [
        [
            'target' => 'erp-front-office',
            'size' => 'sm',
            'title' => 'Front Office',
            'icon' => 'feather-users',
            'description' => 'Customer lifecycle, pipeline, sales execution, and project delivery.',
            'modules' => [
                ['label' => 'CRM', 'icon' => 'feather-users', 'meta' => 'Leads, contacts, activities'],
                ['label' => 'Sales', 'icon' => 'feather-shopping-cart', 'meta' => 'Quotes, orders, invoices'],
                ['label' => 'Projects', 'icon' => 'feather-briefcase', 'meta' => 'Milestones, tasks, timesheets'],
                ['label' => 'Customers', 'icon' => 'feather-user-check', 'meta' => 'Accounts and contacts'],
                ['label' => 'Receivables', 'icon' => 'feather-dollar-sign', 'meta' => 'Collections and aging'],
                ['label' => 'Contracts', 'icon' => 'feather-file-text', 'meta' => 'Terms and renewals'],
            ],
        ],
        [
            'target' => 'erp-operations',
            'size' => 'md',
            'title' => 'Operations',
            'icon' => 'feather-box',
            'description' => 'Procurement, inventory movement, production planning, and quality control.',
            'modules' => [
                ['label' => 'Inventory', 'icon' => 'feather-box', 'meta' => 'Items, stock, warehouses'],
                ['label' => 'Purchase', 'icon' => 'feather-truck', 'meta' => 'Suppliers, POs, bills'],
                ['label' => 'Production', 'icon' => 'feather-cpu', 'meta' => 'BOM, work orders, QC'],
                ['label' => 'Warehouses', 'icon' => 'feather-map-pin', 'meta' => 'Bins, transfers, counts'],
                ['label' => 'Suppliers', 'icon' => 'feather-briefcase', 'meta' => 'Vendor master data'],
                ['label' => 'Quality', 'icon' => 'feather-check-circle', 'meta' => 'Inspection and claims'],
            ],
        ],
        [
            'target' => 'erp-back-office',
            'size' => 'lg',
            'title' => 'Back Office',
            'icon' => 'feather-credit-card',
            'description' => 'Accounting, payroll, compliance reports, and management dashboards.',
            'modules' => [
                ['label' => 'Accounting', 'icon' => 'feather-credit-card', 'meta' => 'Ledgers, journals, tax'],
                ['label' => 'HR & Payroll', 'icon' => 'feather-user-check', 'meta' => 'Employees, leave, salary'],
                ['label' => 'Reports', 'icon' => 'feather-bar-chart-2', 'meta' => 'Financial and BI reports'],
                ['label' => 'Tax', 'icon' => 'feather-percent', 'meta' => 'GST, VAT, compliance'],
                ['label' => 'Payables', 'icon' => 'feather-file-minus', 'meta' => 'Bills and payments'],
                ['label' => 'Analytics', 'icon' => 'feather-pie-chart', 'meta' => 'KPIs and dashboards'],
            ],
        ],
        [
            'target' => 'erp-platform',
            'size' => 'xl',
            'title' => 'Platform',
            'icon' => 'feather-shield',
            'description' => 'Tenant administration, access policies, workflow automation, and audit trail.',
            'modules' => [
                ['label' => 'Tenants', 'icon' => 'feather-grid', 'meta' => 'Companies, branches, plans'],
                ['label' => 'Roles', 'icon' => 'feather-shield', 'meta' => 'Permissions, teams, policies'],
                ['label' => 'Audit Logs', 'icon' => 'feather-activity', 'meta' => 'Security and data history'],
                ['label' => 'Workflows', 'icon' => 'feather-zap', 'meta' => 'Approvals and automation'],
                ['label' => 'Localization', 'icon' => 'feather-globe', 'meta' => 'Languages and currencies'],
                ['label' => 'Integrations', 'icon' => 'feather-link-2', 'meta' => 'APIs and webhooks'],
            ],
        ],
    ];

    $languages = [
        ['code' => 'sa', 'name' => 'Arabic'],
        ['code' => 'bd', 'name' => 'Bengali'],
        ['code' => 'cn', 'name' => 'Chinese'],
        ['code' => 'nl', 'name' => 'Dutch'],
        ['code' => 'us', 'name' => 'English', 'active' => true],
        ['code' => 'fr', 'name' => 'French'],
        ['code' => 'de', 'name' => 'German'],
        ['code' => 'in', 'name' => 'Hindi'],
        ['code' => 'ru', 'name' => 'Russian'],
        ['code' => 'es', 'name' => 'Spanish'],
        ['code' => 'tr', 'name' => 'Turkish'],
        ['code' => 'pk', 'name' => 'Urdu'],
    ];
@endphp

<header class="nxl-header">
    <div class="header-wrapper">
        <div class="header-left d-flex align-items-center gap-4">
            <a href="javascript:void(0);" class="nxl-head-mobile-toggler" id="mobile-collapse">
                <div class="hamburger hamburger--arrowturn">
                    <div class="hamburger-box">
                        <div class="hamburger-inner"></div>
                    </div>
                </div>
            </a>

            <div class="nxl-navigation-toggle">
                <a href="javascript:void(0);" id="menu-mini-button">
                    <i class="feather-align-left"></i>
                </a>
                <a href="javascript:void(0);" id="menu-expend-button" style="display: none">
                    <i class="feather-arrow-right"></i>
                </a>
            </div>

            <div class="nxl-lavel-mega-menu-toggle d-flex d-lg-none">
                <a href="javascript:void(0);" id="nxl-lavel-mega-menu-open">
                    <i class="feather-grid"></i>
                </a>
            </div>

            <div class="nxl-drp-link nxl-lavel-mega-menu">
                <div class="nxl-lavel-mega-menu-toggle d-flex d-lg-none">
                    <a href="javascript:void(0)" id="nxl-lavel-mega-menu-hide">
                        <i class="feather-arrow-left me-2"></i>
                        <span>Back</span>
                    </a>
                </div>

                <div class="nxl-lavel-mega-menu-wrapper d-flex gap-3">
                    <div class="dropdown nxl-h-item nxl-lavel-menu">
                        <a href="javascript:void(0);" class="avatar-text avatar-md bg-primary text-white" data-bs-toggle="dropdown" data-bs-auto-close="outside">
                            <i class="feather-plus"></i>
                        </a>
                        <div class="dropdown-menu nxl-h-dropdown">
                            @foreach ($quickCreates as $item)
                                <a href="javascript:void(0);" class="dropdown-item">
                                    <i class="{{ $item['icon'] }}"></i>
                                    <span>{{ $item['label'] }}</span>
                                </a>
                            @endforeach
                            <div class="dropdown-divider"></div>
                            <a href="javascript:void(0);" class="dropdown-item">
                                <i class="feather-settings"></i>
                                <span>Configure Number Series</span>
                            </a>
                        </div>
                    </div>

                    <div class="dropdown nxl-h-item nxl-mega-menu">
                        <a href="javascript:void(0);" class="btn btn-light-brand" data-bs-toggle="dropdown" data-bs-auto-close="outside">
                            <i class="feather-grid me-2"></i>
                            Modules
                        </a>
                        <div class="dropdown-menu nxl-h-dropdown erp-module-launcher" id="mega-menu-dropdown">
                            <div class="d-lg-flex align-items-start">
                                <div class="nav flex-column nxl-mega-menu-tabs" role="tablist" aria-orientation="vertical">
                                    @foreach ($moduleTabs as $index => $tab)
                                        <button class="nav-link {{ $index === 0 ? 'active' : '' }} nxl-mega-menu-{{ $tab['size'] }}" data-bs-toggle="pill" data-bs-target="#{{ $tab['target'] }}" type="button" role="tab">
                                            <span class="menu-icon">
                                                <i class="{{ $tab['icon'] }}"></i>
                                            </span>
                                            <span class="menu-title">{{ $tab['title'] }}</span>
                                            <span class="menu-arrow">
                                                <i class="feather-chevron-right"></i>
                                            </span>
                                        </button>
                                    @endforeach
                                </div>

                                <div class="tab-content nxl-mega-menu-tabs-content">
                                    @foreach ($moduleTabs as $index => $tab)
                                        <div class="tab-pane fade {{ $index === 0 ? 'show active' : '' }}" id="{{ $tab['target'] }}" role="tabpanel">
                                            <div class="d-lg-flex align-items-start justify-content-between mb-4">
                                                <div>
                                                    <h6 class="fw-bolder text-dark">{{ $tab['title'] }}</h6>
                                                    <p class="fs-12 text-muted mb-0 text-truncate-2-line">{{ $tab['description'] }}</p>
                                                </div>
                                                <a href="javascript:void(0);" class="fs-13 text-primary mt-2 mt-lg-0">Open Module &rarr;</a>
                                            </div>

                                            <div class="row g-3 erp-mega-module-grid">
                                                @foreach ($tab['modules'] as $module)
                                                    <div class="col-lg-4">
                                                        <a href="javascript:void(0);" class="dropdown-item erp-module-link">
                                                            <span class="avatar-text avatar-md bg-soft-primary text-primary">
                                                                <i class="{{ $module['icon'] }}"></i>
                                                            </span>
                                                            <span class="erp-module-link-copy">
                                                                <span>{{ $module['label'] }}</span>
                                                                <small>{{ $module['meta'] }}</small>
                                                            </span>
                                                            <i class="feather-arrow-right ms-auto me-0"></i>
                                                        </a>
                                                    </div>
                                                @endforeach
                                            </div>

                                            <hr class="border-top-dashed">
                                            <div class="erp-module-footer">
                                                <span class="badge bg-soft-success text-success">Enterprise</span>
                                                <span class="fs-11 text-muted">Tenant scoped</span>
                                                <span class="fs-11 text-muted">Role aware</span>
                                                <a href="javascript:void(0);" class="fs-12 fw-bold text-primary ms-auto">Access Control &rarr;</a>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="dropdown nxl-h-item erp-tenant-switcher d-none d-xl-flex">
                        <a href="javascript:void(0);" class="btn btn-light-brand erp-tenant-button" data-bs-toggle="dropdown" data-bs-auto-close="outside">
                            <span class="avatar-text avatar-sm bg-soft-success text-success">
                                <i class="feather-briefcase"></i>
                            </span>
                            <span class="erp-tenant-copy">
                                <strong>{{ $currentTenant['name'] }}</strong>
                                <small>{{ $currentTenant['branch'] }} - {{ $currentTenant['year'] }}</small>
                            </span>
                            <i class="feather-chevron-down ms-2"></i>
                        </a>
                        <div class="dropdown-menu nxl-h-dropdown erp-tenant-dropdown">
                            <div class="px-4 py-3 border-bottom">
                                <h6 class="mb-1">Switch Tenant</h6>
                                <p class="fs-11 text-muted mb-0">{{ $currentTenant['currency'] }} - {{ $currentTenant['plan'] }} Plan</p>
                            </div>
                            @foreach ($tenants as $tenant)
                                <a href="{{ route('tenant.switch', $tenant['slug']) }}" class="dropdown-item {{ !empty($tenant['active']) ? 'active' : '' }}">
                                    <span class="avatar-text avatar-sm bg-soft-primary text-primary">{{ substr($tenant['name'], 0, 1) }}</span>
                                    <span>
                                        <span class="d-block fw-semibold">{{ $tenant['name'] }}</span>
                                        <span class="fs-11 text-muted">{{ $tenant['code'] }}</span>
                                    </span>
                                    @if (!empty($tenant['active']))
                                        <i class="feather-check ms-auto me-0 text-success"></i>
                                    @endif
                                </a>
                            @endforeach
                            <div class="dropdown-divider"></div>
                            <a href="javascript:void(0);" class="dropdown-item">
                                <i class="feather-plus"></i>
                                <span>Add Tenant</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="header-right ms-auto">
            <div class="d-flex align-items-center">
                <div class="dropdown nxl-h-item nxl-header-search">
                    <a href="javascript:void(0);" class="nxl-head-link me-0" data-bs-toggle="dropdown" data-bs-auto-close="outside">
                        <i class="feather-search"></i>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end nxl-h-dropdown nxl-search-dropdown">
                        <div class="input-group search-form">
                            <span class="input-group-text">
                                <i class="feather-search fs-6 text-muted"></i>
                            </span>
                            <input type="text" class="form-control search-input-field" placeholder="Search customer, invoice, item, employee, vendor">
                            <span class="input-group-text">
                                <button type="button" class="btn-close"></button>
                            </span>
                        </div>
                        <div class="dropdown-divider mt-0"></div>
                        <div class="search-items-wrapper">
                            <div class="searching-for px-4 py-2">
                                <p class="fs-11 fw-medium text-muted">I'm searching for...</p>
                                <div class="d-flex flex-wrap gap-1">
                                    @foreach (['Customers', 'Invoices', 'Items', 'Vendors', 'Employees', 'Ledger', 'Orders', 'Reports'] as $searchScope)
                                        <a href="javascript:void(0);" class="flex-fill border rounded py-1 px-2 text-center fs-11 fw-semibold">{{ $searchScope }}</a>
                                    @endforeach
                                </div>
                            </div>
                            <div class="dropdown-divider"></div>
                            <div class="recent-result px-4 py-2">
                                <h4 class="fs-13 fw-normal text-gray-600 mb-3">Recent <span class="badge small bg-gray-200 rounded ms-1 text-dark">3</span></h4>
                                @foreach ([
                                    ['icon' => 'feather-file-text', 'title' => 'Monthly GST summary', 'path' => 'Accounting / reports'],
                                    ['icon' => 'feather-package', 'title' => 'Low stock approvals', 'path' => 'Inventory / approvals'],
                                    ['icon' => 'feather-users', 'title' => 'Enterprise customer list', 'path' => 'CRM / customers'],
                                ] as $recent)
                                    <div class="d-flex align-items-center justify-content-between mb-4">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="avatar-text rounded">
                                                <i class="{{ $recent['icon'] }}"></i>
                                            </div>
                                            <div>
                                                <a href="javascript:void(0);" class="font-body fw-bold d-block mb-1">{{ $recent['title'] }}</a>
                                                <p class="fs-11 text-muted mb-0">{{ $recent['path'] }}</p>
                                            </div>
                                        </div>
                                        <a href="javascript:void(0);" class="avatar-text avatar-md">
                                            <i class="feather-chevron-right"></i>
                                        </a>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                <div class="dropdown nxl-h-item nxl-header-language d-none d-sm-flex">
                    <a href="javascript:void(0);" class="nxl-head-link me-0 nxl-language-link" data-bs-toggle="dropdown" data-bs-auto-close="outside">
                        <img src="{{ asset('assets/vendors/img/flags/4x3/us.svg') }}" alt="English" class="img-fluid wd-20">
                    </a>
                    <div class="dropdown-menu dropdown-menu-end nxl-h-dropdown nxl-language-dropdown">
                        <div class="dropdown-divider mt-0"></div>
                        <div class="language-items-wrapper">
                            <div class="select-language px-4 py-2 hstack justify-content-between gap-4">
                                <div class="lh-lg">
                                    <h6 class="mb-0">Select Language</h6>
                                    <p class="fs-11 text-muted mb-0">12 languages available!</p>
                                </div>
                                <a href="javascript:void(0);" class="avatar-text avatar-md" data-bs-toggle="tooltip" title="Add Language">
                                    <i class="feather-plus"></i>
                                </a>
                            </div>
                            <div class="dropdown-divider"></div>
                            <div class="row px-4 pt-3">
                                @foreach ($languages as $language)
                                    <div class="col-sm-4 col-6 language_select {{ !empty($language['active']) ? 'active' : '' }}">
                                        <a href="javascript:void(0);" class="d-flex align-items-center gap-2" data-language="{{ $language['name'] }}" data-flag="{{ asset('assets/vendors/img/flags/4x3/' . $language['code'] . '.svg') }}">
                                            <div class="avatar-image avatar-sm">
                                                <img src="{{ asset('assets/vendors/img/flags/1x1/' . $language['code'] . '.svg') }}" alt="{{ $language['name'] }}" class="img-fluid">
                                            </div>
                                            <span>{{ $language['name'] }}</span>
                                        </a>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                <div class="nxl-h-item d-none d-sm-flex">
                    <div class="full-screen-switcher">
                        <a href="javascript:void(0);" class="nxl-head-link me-0" onclick="$('body').fullScreenHelper('toggle');">
                            <i class="feather-maximize maximize"></i>
                            <i class="feather-minimize minimize"></i>
                        </a>
                    </div>
                </div>

                <div class="nxl-h-item dark-light-theme">
                    <a href="javascript:void(0);" class="nxl-head-link me-0 dark-button">
                        <i class="feather-moon"></i>
                    </a>
                    <a href="javascript:void(0);" class="nxl-head-link me-0 light-button" style="display: none">
                        <i class="feather-sun"></i>
                    </a>
                </div>

                <div class="dropdown nxl-h-item">
                    <a href="javascript:void(0);" class="nxl-head-link me-0" data-bs-toggle="dropdown" role="button" data-bs-auto-close="outside">
                        <i class="feather-check-square"></i>
                        <span class="badge bg-success nxl-h-badge">7</span>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end nxl-h-dropdown nxl-timesheets-menu">
                        <div class="d-flex justify-content-between align-items-center timesheets-head">
                            <h6 class="fw-bold text-dark mb-0">Approvals</h6>
                            <a href="javascript:void(0);" class="fs-11 text-success text-end ms-auto">
                                <i class="feather-clock"></i>
                                <span>Due Today</span>
                            </a>
                        </div>
                        <div class="timesheets-body erp-approval-list">
                            @foreach (['Vendor bill over limit', 'Payroll finalization', 'Stock transfer request'] as $approval)
                                <a href="javascript:void(0);" class="d-flex align-items-center justify-content-between py-2">
                                    <span class="fs-12 fw-semibold text-dark">{{ $approval }}</span>
                                    <i class="feather-chevron-right"></i>
                                </a>
                            @endforeach
                        </div>
                        <div class="text-center timesheets-footer">
                            <a href="javascript:void(0);" class="fs-13 fw-semibold text-dark">All Approvals</a>
                        </div>
                    </div>
                </div>

                <div class="dropdown nxl-h-item">
                    <a class="nxl-head-link me-3" data-bs-toggle="dropdown" href="#" role="button" data-bs-auto-close="outside">
                        <i class="feather-bell"></i>
                        <span class="badge bg-danger nxl-h-badge">3</span>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end nxl-h-dropdown nxl-notifications-menu">
                        <div class="d-flex justify-content-between align-items-center notifications-head">
                            <h6 class="fw-bold text-dark mb-0">Notifications</h6>
                            <a href="javascript:void(0);" class="fs-11 text-success text-end ms-auto">
                                <i class="feather-check"></i>
                                <span>Mark as Read</span>
                            </a>
                        </div>
                        @foreach ([
                            ['avatar' => '2.png', 'name' => 'Sales Team', 'body' => 'New enterprise quotation needs margin approval.', 'time' => '2 minutes ago'],
                            ['avatar' => '3.png', 'name' => 'Inventory', 'body' => '18 items crossed reorder level.', 'time' => '36 minutes ago'],
                            ['avatar' => '4.png', 'name' => 'Payroll', 'body' => 'June payroll draft is ready for review.', 'time' => '53 minutes ago'],
                        ] as $notification)
                            <div class="notifications-item">
                                <img src="{{ asset('assets/images/avatar/' . $notification['avatar']) }}" alt="" class="rounded me-3 border">
                                <div class="notifications-desc">
                                    <a href="javascript:void(0);" class="font-body text-truncate-2-line">
                                        <span class="fw-semibold text-dark">{{ $notification['name'] }}</span>
                                        {{ $notification['body'] }}
                                    </a>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="notifications-date text-muted border-bottom border-bottom-dashed">{{ $notification['time'] }}</div>
                                        <a href="javascript:void(0);" class="text-danger">
                                            <i class="feather-x fs-12"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                        <div class="text-center notifications-footer">
                            <a href="javascript:void(0);" class="fs-13 fw-semibold text-dark">All Notifications</a>
                        </div>
                    </div>
                </div>

                <div class="dropdown nxl-h-item">
                    <a href="javascript:void(0);" data-bs-toggle="dropdown" role="button" data-bs-auto-close="outside">
                        <img src="{{ asset('assets/images/avatar/1.png') }}" alt="user-image" class="img-fluid user-avtar me-0">
                    </a>
                    <div class="dropdown-menu dropdown-menu-end nxl-h-dropdown nxl-user-dropdown">
                        <div class="dropdown-header">
                            <div class="d-flex align-items-center">
                                <img src="{{ asset('assets/images/avatar/1.png') }}" alt="user-image" class="img-fluid user-avtar">
                                <div>
                                    <h6 class="text-dark mb-0">ERP Admin <span class="badge bg-soft-success text-success ms-1">{{ $currentTenant['plan'] }}</span></h6>
                                    <span class="fs-12 fw-medium text-muted">admin@saas-erp.local</span>
                                </div>
                            </div>
                        </div>
                        <a href="javascript:void(0);" class="dropdown-item">
                            <i class="feather-briefcase"></i>
                            <span>{{ $currentTenant['name'] }}</span>
                        </a>
                        <a href="javascript:void(0);" class="dropdown-item">
                            <i class="feather-calendar"></i>
                            <span>{{ $currentTenant['year'] }}</span>
                        </a>
                        <a href="javascript:void(0);" class="dropdown-item">
                            <i class="feather-shield"></i>
                            <span>Roles & Permissions</span>
                        </a>
                        <a href="javascript:void(0);" class="dropdown-item">
                            <i class="feather-settings"></i>
                            <span>Account Settings</span>
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="javascript:void(0);" class="dropdown-item">
                            <i class="feather-log-out"></i>
                            <span>Logout</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>
