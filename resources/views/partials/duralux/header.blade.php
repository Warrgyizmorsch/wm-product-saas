@php
    $resolvedTenant = tenant();
    $tenantSettings = $resolvedTenant?->settings ?? [];
    $tenantPlan = ucfirst((string) ($resolvedTenant?->plan ?? 'Starter'));
    $tenantSlug = $resolvedTenant?->slug ?? 'central';

    $resolvedCompany = company();
    $resolvedBranch = branch();

    // Switching tenants is a platform-admin capability — TenantSwitchController
    // already enforces this on the action, but the dropdown itself had no
    // visibility gate, so a plain tenant owner could see every other tenant's
    // name in the list even though clicking one would 403. Company/Branch
    // switching stays open to any authenticated user, since it's scoped to
    // their own tenant's data, not a cross-tenant capability.
    $isPlatformAdmin = auth()->user()
        ? app(\App\Services\Access\AccessService::class)->allows(auth()->user(), 'platform.tenants.manage')
        : false;

    // Live current period, not a stored setting — same lookup the Accounting
    // engine itself uses (FiscalPeriodService::periodForDate), so this label
    // always matches whatever period journals actually post into. Naturally
    // respects the request's tenant/company/branch scope since it queries
    // AccountingPeriod through Eloquent, which carries those global scopes.
    $currentPeriod = \Illuminate\Support\Facades\Schema::hasTable('accounting_periods')
        ? app(\App\Domains\Accounting\Services\FiscalPeriodService::class)->periodForDate(now())
        : null;

    $currentTenant = [
        'name' => $resolvedTenant?->name ?? 'Central Workspace',
        'code' => strtoupper(str_replace('-', ' ', $tenantSlug)),
        'plan' => $tenantPlan,
        'branch' => $resolvedBranch?->name ?? ($tenantSettings['branch'] ?? 'Main Office'),
        // The ledger's currency belongs to the company, not the tenant.
        'currency' => $resolvedCompany ? company_currency()['code'] : ($tenantSettings['currency'] ?? 'INR'),
        'year' => $currentPeriod?->fiscalYear?->name ?? ($tenantSettings['financial_year'] ?? 'FY ' . now()->format('Y')),
    ];

    $currentCompany = [
        'name' => $resolvedCompany?->company_name ?? 'No Company',
        'code' => $resolvedCompany?->gst_number ?? '',
        'currency' => $currentTenant['currency'],
    ];

    $tenants = \Illuminate\Support\Facades\Schema::hasTable('tenants')
        ? \App\Models\Tenant::query()
            ->whereIn('status', \App\Models\Tenant::accessibleStatuses())
            ->orderBy('name')
            ->get()
            ->map(fn ($tenant) => [
                'name' => $tenant->name,
                'code' => strtoupper($tenant->slug),
                'slug' => $tenant->slug,
                'active' => $resolvedTenant?->is($tenant) ?? false,
            ])
        : collect();

    $companies = ($resolvedTenant && \Illuminate\Support\Facades\Schema::hasTable('companies'))
        ? \App\Domains\HRMS\Models\Company::withoutGlobalScopes()
            ->where('tenant_id', $resolvedTenant->id)
            ->orderBy('company_name')
            ->get()
            ->map(fn ($company) => [
                'id' => $company->id,
                'name' => $company->company_name,
                'active' => $resolvedCompany?->is($company) ?? false,
            ])
        : collect();

    $branches = ($resolvedCompany && \Illuminate\Support\Facades\Schema::hasTable('branches'))
        ? \App\Domains\HRMS\Models\Branch::withoutGlobalScopes()
            ->where('company_id', $resolvedCompany->id)
            ->orderBy('name')
            ->get()
            ->map(fn ($branch) => [
                'id' => $branch->id,
                'name' => $branch->name,
                'active' => $resolvedBranch?->is($branch) ?? false,
            ])
        : collect();

    $quickCreates = [
        ['label' => __('ui.customer'), 'icon' => 'feather-user-plus'],
        ['label' => __('ui.sales_invoice'), 'icon' => 'feather-file-plus'],
        ['label' => 'Purchase Order', 'icon' => 'feather-truck'],
        ['label' => __('ui.stock_adjustment'), 'icon' => 'feather-package'],
        ['label' => __('ui.employee'), 'icon' => 'feather-user-check'],
        ['label' => __('ui.journal_entry'), 'icon' => 'feather-credit-card'],
    ];

    $moduleTabs = [
        [
            'target' => 'erp-front-office',
            'size' => 'sm',
            'title' => __('ui.front_office'),
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
            'title' => __('ui.operations'),
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
            'title' => __('ui.back_office'),
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
            'title' => __('ui.platform'),
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
                        <span>{{ __('ui.back') }}</span>
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
                                <span>{{ __('ui.configure_number_series') }}</span>
                            </a>
                        </div>
                    </div>

                    <x-ui.dropdown class="nxl-h-item nxl-mega-menu" menu-class="nxl-h-dropdown erp-module-launcher" menu-id="mega-menu-dropdown">
                        <x-slot name="trigger">
                            <x-ui.button href="javascript:void(0);" variant="light-brand" icon="feather-grid" class="dropdown-toggle" data-bs-toggle="dropdown" data-bs-auto-close="outside" role="button" aria-expanded="false">
                                {{ __('ui.modules') }}
                            </x-ui.button>
                        </x-slot>

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
                                                        <x-ui.dropdown-item class="erp-module-link">
                                                            <span class="avatar-text avatar-md bg-soft-primary text-primary">
                                                                <i class="{{ $module['icon'] }}"></i>
                                                            </span>
                                                            <span class="erp-module-link-copy">
                                                                <span>{{ $module['label'] }}</span>
                                                                <small>{{ $module['meta'] }}</small>
                                                            </span>
                                                            <i class="feather-arrow-right ms-auto me-0"></i>
                                                        </x-ui.dropdown-item>
                                                    </div>
                                                @endforeach
                                            </div>

                                            <hr class="border-top-dashed">
                                            <div class="erp-module-footer">
                                                <x-ui.badge variant="success" soft>Enterprise</x-ui.badge>
                                                <span class="fs-11 text-muted">Tenant scoped</span>
                                                <span class="fs-11 text-muted">Role aware</span>
                                                <a href="javascript:void(0);" class="fs-12 fw-bold text-primary ms-auto">Access Control &rarr;</a>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                    </x-ui.dropdown>

                    @if ($isPlatformAdmin)
                        <x-ui.dropdown class="nxl-h-item erp-tenant-switcher d-none d-xl-flex" menu-class="nxl-h-dropdown erp-tenant-dropdown">
                            <x-slot name="trigger">
                                <x-ui.button href="javascript:void(0);" variant="light-brand" class="erp-tenant-button dropdown-toggle" data-bs-toggle="dropdown" data-bs-auto-close="outside" role="button" aria-expanded="false">
                                    <span class="avatar-text avatar-sm bg-soft-success text-success">
                                        <i class="feather-briefcase"></i>
                                    </span>
                                    <span class="erp-tenant-copy">
                                        <strong>{{ $currentTenant['name'] }}</strong>
                                        <small>{{ $currentTenant['branch'] }} - {{ $currentTenant['year'] }}</small>
                                    </span>
                                    <i class="feather-chevron-down ms-2"></i>
                                </x-ui.button>
                            </x-slot>

                                <div class="px-4 py-3 border-bottom">
                                    <h6 class="mb-1">{{ __('ui.switch_tenant') }}</h6>
                                    <p class="fs-11 text-muted mb-0">{{ $currentTenant['currency'] }} - {{ $currentTenant['plan'] }} Plan</p>
                                </div>
                                @foreach ($tenants as $tenant)
                                    <x-ui.dropdown-item href="{{ route('tenant.switch', $tenant['slug']) }}" :active="!empty($tenant['active'])">
                                        <span class="avatar-text avatar-sm bg-soft-primary text-primary">{{ substr($tenant['name'], 0, 1) }}</span>
                                        <span>
                                            <span class="d-block fw-semibold">{{ $tenant['name'] }}</span>
                                            <span class="fs-11 text-muted">{{ $tenant['code'] }}</span>
                                        </span>
                                        @if (!empty($tenant['active']))
                                            <i class="feather-check ms-auto me-0 text-success"></i>
                                        @endif
                                    </x-ui.dropdown-item>
                                @endforeach
                                <div class="dropdown-divider"></div>
                                <x-ui.dropdown-item href="{{ route('platform.tenants.create') }}" icon="feather-plus">
                                    <span>{{ __('ui.add_tenant') }}</span>
                                </x-ui.dropdown-item>
                        </x-ui.dropdown>
                    @else
                        {{-- Non-platform-admin users (tenant owners, staff) get the same info display
                             but no switcher — switching tenants is a platform-admin-only capability,
                             enforced server-side by TenantSwitchController; hiding the list here too
                             avoids exposing every other tenant's name to a plain tenant owner. --}}
                        <div class="nxl-h-item erp-tenant-switcher d-none d-xl-flex erp-tenant-button">
                            <span class="avatar-text avatar-sm bg-soft-success text-success">
                                <i class="feather-briefcase"></i>
                            </span>
                            <span class="erp-tenant-copy">
                                <strong>{{ $currentTenant['name'] }}</strong>
                                <small>{{ $currentTenant['branch'] }} - {{ $currentTenant['year'] }}</small>
                            </span>
                        </div>
                    @endif

                    @if ($companies->count() > 1)
                        <x-ui.dropdown class="nxl-h-item erp-company-switcher d-none d-xl-flex" menu-class="nxl-h-dropdown erp-company-dropdown">
                            <x-slot name="trigger">
                                <x-ui.button href="javascript:void(0);" variant="light-brand" class="erp-company-button dropdown-toggle" data-bs-toggle="dropdown" data-bs-auto-close="outside" role="button" aria-expanded="false">
                                    <span class="avatar-text avatar-sm bg-soft-info text-info">
                                        <i class="feather-home"></i>
                                    </span>
                                    <span class="erp-tenant-copy">
                                        <strong>{{ $currentCompany['name'] }}</strong>
                                        <small>{{ $currentCompany['currency'] }} · {{ __('ui.switch_company') }}</small>
                                    </span>
                                    <i class="feather-chevron-down ms-2"></i>
                                </x-ui.button>
                            </x-slot>

                                <div class="px-4 py-3 border-bottom">
                                    <h6 class="mb-1">{{ __('ui.switch_company') }}</h6>
                                </div>
                                @foreach ($companies as $companyOption)
                                    <x-ui.dropdown-item href="{{ route('company.switch', $companyOption['id']) }}" :active="!empty($companyOption['active'])">
                                        <span class="avatar-text avatar-sm bg-soft-primary text-primary">{{ substr($companyOption['name'], 0, 1) }}</span>
                                        <span class="d-block fw-semibold">{{ $companyOption['name'] }}</span>
                                        @if (!empty($companyOption['active']))
                                            <i class="feather-check ms-auto me-0 text-success"></i>
                                        @endif
                                    </x-ui.dropdown-item>
                                @endforeach
                        </x-ui.dropdown>
                    @endif

                    @if ($branches->count() > 1)
                        <x-ui.dropdown class="nxl-h-item erp-branch-switcher d-none d-xl-flex" menu-class="nxl-h-dropdown erp-branch-dropdown">
                            <x-slot name="trigger">
                                <x-ui.button href="javascript:void(0);" variant="light-brand" class="erp-branch-button dropdown-toggle" data-bs-toggle="dropdown" data-bs-auto-close="outside" role="button" aria-expanded="false">
                                    <span class="avatar-text avatar-sm bg-soft-warning text-warning">
                                        <i class="feather-map-pin"></i>
                                    </span>
                                    <span class="erp-tenant-copy">
                                        <strong>{{ $currentTenant['branch'] }}</strong>
                                        <small>{{ __('ui.switch_branch') }}</small>
                                    </span>
                                    <i class="feather-chevron-down ms-2"></i>
                                </x-ui.button>
                            </x-slot>

                                <div class="px-4 py-3 border-bottom">
                                    <h6 class="mb-1">{{ __('ui.switch_branch') }}</h6>
                                </div>
                                @foreach ($branches as $branchOption)
                                    <x-ui.dropdown-item href="{{ route('branch.switch', $branchOption['id']) }}" :active="!empty($branchOption['active'])">
                                        <span class="avatar-text avatar-sm bg-soft-primary text-primary">{{ substr($branchOption['name'], 0, 1) }}</span>
                                        <span class="d-block fw-semibold">{{ $branchOption['name'] }}</span>
                                        @if (!empty($branchOption['active']))
                                            <i class="feather-check ms-auto me-0 text-success"></i>
                                        @endif
                                    </x-ui.dropdown-item>
                                @endforeach
                        </x-ui.dropdown>
                    @endif
                </div>
            </div>
        </div>

        <div class="header-right ms-auto">
            <div class="d-flex align-items-center">
                <div class="dropdown nxl-h-item nxl-header-search" id="global-search-container" data-search-url="{{ route('global-search') }}">
                    <a href="javascript:void(0);" class="nxl-head-link me-0" data-bs-toggle="dropdown" data-bs-auto-close="outside" id="global-search-toggle">
                        <i class="feather-search"></i>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end nxl-h-dropdown nxl-search-dropdown shadow-lg border-0" id="global-search-dropdown" style="min-width: 420px; max-width: 520px;">
                        <div class="input-group search-form border-bottom">
                            <span class="input-group-text bg-transparent border-0 pe-1">
                                <i class="feather-search fs-6 text-muted" id="search-spinner-icon"></i>
                            </span>
                            <input type="text" class="form-control search-input-field border-0 ps-1" id="global-search-input" placeholder="{{ __('ui.search_placeholder') }}" autocomplete="off">
                            <span class="input-group-text bg-transparent border-0">
                                <button type="button" class="btn-close fs-11" id="global-search-clear" style="display: none;"></button>
                            </span>
                        </div>
                        
                        {{-- Scope Selector Pills --}}
                        <div class="search-scope-bar px-3 py-2 bg-light-subtle border-bottom">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <span class="fs-11 fw-semibold text-muted text-uppercase tracking-wider">Search in</span>
                                <span class="badge bg-secondary-subtle text-secondary fs-11" id="global-search-active-scope">All</span>
                            </div>
                            <div class="d-flex flex-wrap gap-1" id="search-scope-pills">
                                @php
                                    $searchScopes = [
                                        ['id' => 'all', 'label' => 'All'],
                                        ['id' => 'navigation', 'label' => 'Menu'],
                                        ['id' => 'sales', 'label' => 'Sales'],
                                        ['id' => 'purchase', 'label' => 'Purchase'],
                                        ['id' => 'inventory', 'label' => 'Inventory'],
                                        ['id' => 'production', 'label' => 'Production'],
                                        ['id' => 'accounting', 'label' => 'Accounting'],
                                        ['id' => 'hrms', 'label' => 'HRMS'],
                                        ['id' => 'projects', 'label' => 'Projects'],
                                        ['id' => 'crm', 'label' => 'CRM'],
                                    ];
                                @endphp
                                @foreach ($searchScopes as $scope)
                                    <button type="button" class="btn btn-xs py-1 px-2 fs-11 fw-medium search-scope-pill {{ $loop->first ? 'btn-primary' : 'btn-outline-secondary' }}" data-scope="{{ $scope['id'] }}">
                                        {{ $scope['label'] }}
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        {{-- Dynamic Search & Recent Results Container --}}
                        <div class="search-items-wrapper" id="global-search-results-wrapper" style="max-height: 380px; overflow-y: auto;">
                            {{-- Default view: Recent Searches & Tips --}}
                            <div id="global-search-default-view">
                                <div class="recent-result px-3 py-2" id="global-search-recent-section" style="display: none;">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <span class="fs-12 fw-semibold text-muted text-uppercase">Recent Searches</span>
                                        <button type="button" class="btn btn-link btn-xs text-muted p-0 text-decoration-none fs-11" id="clear-recent-searches">Clear History</button>
                                    </div>
                                    <div id="recent-searches-list"></div>
                                    <div class="dropdown-divider my-2"></div>
                                </div>
                                <div class="px-3 py-2">
                                    <p class="fs-11 fw-medium text-muted mb-0 d-flex align-items-center gap-1">
                                        <i class="feather-info text-primary fs-13 me-0.5"></i>
                                        <span>Type at least 2 characters to search. Use <span class="search-kbd-key">↑</span> <span class="search-kbd-key">↓</span> to navigate, <span class="search-kbd-key">Enter</span> to open.</span>
                                    </p>
                                </div>
                            </div>

                            {{-- Live Results View --}}
                            <div id="global-search-results-view" style="display: none;">
                                <div id="global-search-results-list" class="py-1"></div>
                            </div>

                            {{-- Empty State View --}}
                            <div id="global-search-empty-view" class="text-center py-4 px-3" style="display: none;">
                                <div class="avatar-text avatar-md bg-light-subtle rounded-circle mx-auto mb-2 text-muted">
                                    <i class="feather-search fs-4"></i>
                                </div>
                                <p class="fs-13 fw-medium text-dark mb-1">No results found for "<span id="empty-query-text"></span>"</p>
                                <p class="fs-11 text-muted mb-2">Try searching with another keyword or reset filter to <strong>All</strong>.</p>
                                <button type="button" class="btn btn-sm btn-outline-primary fs-11 py-1 px-2" id="reset-scope-btn">Switch to All Modules</button>
                            </div>
                        </div>
                    </div>
                </div>

                <script src="{{ asset('assets/js/global-search.js') }}" defer></script>
                <script src="{{ asset('assets/js/approval-center.js') }}" defer></script>

                @include('partials.duralux.language-switcher')
                {{-- Currency is auto-resolved from tenant settings via SetCurrency middleware. --}}

                <div class="nxl-h-item d-none d-sm-flex">
                    <div class="full-screen-switcher">
                        <a href="javascript:void(0);" class="nxl-head-link me-0" onclick="$('body').fullScreenHelper('toggle');">
                            <i class="feather-maximize maximize"></i>
                            <i class="feather-minimize minimize"></i>
                        </a>
                    </div>
                </div>

                <!-- Primary Color Picker -->
                <div class="nxl-h-item d-flex align-items-center justify-content-center me-3">
                    <div class="d-flex align-items-center gap-2 border rounded-pill px-2 py-1 bg-light" style="height: 38px;">
                        <i class="feather-aperture text-muted fs-14"></i>
                        <input type="color" id="primaryColorPicker" class="form-control form-control-color border-0 bg-transparent p-0" style="width: 22px; height: 22px; cursor: pointer; border-radius: 50% !important;" value="#0000FF" title="Choose Primary Color">
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

                <div class="dropdown nxl-h-item" id="header-approvals-dropdown">
                    <a href="javascript:void(0);" class="nxl-head-link me-0" data-bs-toggle="dropdown" role="button" data-bs-auto-close="outside" id="header-approvals-trigger" title="{{ __('ui.approvals') }}">
                        <i class="feather-check-square"></i>
                        <span class="badge bg-success nxl-h-badge d-none" id="header-approvals-badge">0</span>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end nxl-h-dropdown nxl-timesheets-menu" style="min-width: 330px;">
                        <div class="d-flex justify-content-between align-items-center timesheets-head px-3 py-2 border-bottom">
                            <h6 class="fw-bold text-dark mb-0">{{ __('ui.approvals') }}</h6>
                            <span class="fs-11 text-muted text-end ms-auto" id="header-approvals-status-text"></span>
                        </div>
                        <div class="timesheets-body erp-approval-list p-0" id="header-approvals-list" style="max-height: 350px; overflow-y: auto;">
                            <div class="text-center py-4 text-muted" id="header-approvals-loading">
                                <span class="spinner-border spinner-border-sm text-primary me-1" role="status" aria-hidden="true"></span>
                                <span class="fs-12">Loading approvals...</span>
                            </div>
                        </div>
                        <div class="text-center timesheets-footer py-2 border-top" id="header-approvals-footer">
                            <span class="fs-12 text-muted fw-semibold" id="header-approvals-footer-text">{{ __('ui.approvals') }}</span>
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
                            <h6 class="fw-bold text-dark mb-0">{{ __('ui.notifications') }}</h6>
                            <a href="javascript:void(0);" class="fs-11 text-success text-end ms-auto">
                                <i class="feather-check"></i>
                                <span>{{ __('ui.mark_as_read') }}</span>
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
                            <a href="javascript:void(0);" class="fs-13 fw-semibold text-dark">{{ __('ui.all_notifications') }}</a>
                        </div>
                    </div>
                </div>

                @php
                    $headerAuthUser = auth()->user();
                    $headerEmployee = null;
                    if ($headerAuthUser) {
                        $headerEmployee = \App\Domains\HRMS\Models\Employee::resolveForUser($headerAuthUser);
                    }
                    $headerProfileUrl = $headerEmployee ? route('hrms.employees.show', $headerEmployee->id) : (Route::has('hrms.employees.index') ? route('hrms.employees.index') : 'javascript:void(0);');
                @endphp
                <div class="dropdown nxl-h-item">
                    <a href="javascript:void(0);" data-bs-toggle="dropdown" role="button" data-bs-auto-close="outside">
                        @if($headerEmployee && !empty($headerEmployee->photo))
                            <img src="{{ asset('storage/' . $headerEmployee->photo) }}" alt="user-image" class="img-fluid user-avtar me-0 object-fit-cover" style="width: 38px; height: 38px; border-radius: 50%;">
                        @else
                            <img src="{{ asset('assets/images/avatar/1.png') }}" alt="user-image" class="img-fluid user-avtar me-0">
                        @endif
                    </a>
                    <div class="dropdown-menu dropdown-menu-end nxl-h-dropdown nxl-user-dropdown">
                        <div class="dropdown-header">
                            <a href="{{ $headerProfileUrl }}" class="d-flex align-items-center text-decoration-none">
                                @if($headerEmployee && !empty($headerEmployee->photo))
                                    <img src="{{ asset('storage/' . $headerEmployee->photo) }}" alt="user-image" class="img-fluid user-avtar object-fit-cover" style="width: 38px; height: 38px; border-radius: 50%;">
                                @else
                                    <img src="{{ asset('assets/images/avatar/1.png') }}" alt="user-image" class="img-fluid user-avtar">
                                @endif
                                <div>
                                    <h6 class="text-dark mb-0">{{ auth()->user()->name ?? __('ui.erp_admin') }} <span class="badge bg-soft-success text-success ms-1">{{ $currentTenant['plan'] }}</span></h6>
                                    <span class="fs-12 fw-medium text-muted">{{ auth()->user()->email ?? '' }}</span>
                                </div>
                            </a>
                        </div>
                        <a href="{{ $headerProfileUrl }}" class="dropdown-item">
                            <i class="feather-user"></i>
                            <span>My Profile</span>
                        </a>
                        <a href="javascript:void(0);" class="dropdown-item">
                            <i class="feather-briefcase"></i>
                            <span>{{ $currentTenant['name'] }}</span>
                        </a>
                        <a href="javascript:void(0);" class="dropdown-item">
                            <i class="feather-calendar"></i>
                            <span>{{ $currentTenant['year'] }}</span>
                        </a>
                        <a href="{{ route('access.roles.index') }}" class="dropdown-item">
                            <i class="feather-shield"></i>
                            <span>{{ __('ui.roles_permissions') }}</span>
                        </a>
                        <a href="javascript:void(0);" class="dropdown-item">
                            <i class="feather-settings"></i>
                            <span>{{ __('ui.account_settings') }}</span>
                        </a>
                        <div class="dropdown-divider"></div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="dropdown-item border-0 bg-transparent w-100 text-start">
                                <i class="feather-log-out"></i>
                                <span>{{ __('ui.logout') }}</span>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>
