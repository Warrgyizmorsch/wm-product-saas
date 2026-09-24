@php
    $resolvedTenant = tenant();
    $tenantSettings = $resolvedTenant?->settings ?? [];
    $tenantPlan = ucfirst((string) ($resolvedTenant?->plan ?? 'Starter'));
    $tenantSlug = $resolvedTenant?->slug ?? 'central';

    $resolvedCompany = company();
    $resolvedBranch = branch();

    // The apps this user can open (config/navigation.php), shared with the sidebar's computation.
    $nav = app(\App\Core\Navigation\MenuBuilder::class)->navigation(auth()->user(), request()->route()?->getName());

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

    $companies =($resolvedTenant && \Illuminate\Support\Facades\Schema::hasTable('companies'))
        ? \App\Domains\HRMS\Models\Company::withoutGlobalScopes()
            ->where('tenant_id', $resolvedTenant->id)
            ->orderBy('company_name')
            ->get()
            ->map(fn($company) => [
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
            ->map(fn($branch) => [
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
                        <a href="javascript:void(0);" class="avatar-text avatar-md bg-primary text-white"
                            data-bs-toggle="dropdown" data-bs-auto-close="outside">
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

                    <x-ui.dropdown class="nxl-h-item nxl-mega-menu" menu-class="nxl-h-dropdown erp-module-launcher"
                        menu-id="mega-menu-dropdown">
                        <x-slot name="trigger">
                            <x-ui.button href="javascript:void(0);" variant="light-brand" icon="feather-grid"
                                class="dropdown-toggle" data-bs-toggle="dropdown" data-bs-auto-close="outside"
                                role="button" aria-expanded="false">
                                {{ __('ui.modules') }}
                            </x-ui.button>
                        </x-slot>

                        <div class="erp-app-launcher p-3" style="min-width: 320px; max-width: 560px;">
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <h6 class="fw-bolder text-dark mb-0">{{ __('ui.modules') }}</h6>
                                <a href="{{ route('apps') }}" class="fs-12 text-primary">{{ __('ui.workspace') }} &rarr;</a>
                            </div>
                            <div class="row g-2">
                                @forelse ($nav['apps'] as $app)
                                    <div class="col-sm-6">
                                        <a href="{{ $app['url'] }}" class="dropdown-item erp-module-link d-flex align-items-center gap-2 rounded {{ $app['active'] ? 'active' : '' }}">
                                            <span class="erp-app-icon" style="background: {{ $app['color'] ?? '#3B82F6' }}">
                                                <i class="{{ $app['icon'] }}"></i>
                                            </span>
                                            <span class="erp-module-link-copy">
                                                <span>{{ $app['label'] }}</span>
                                                <small>{{ $app['description'] }}</small>
                                            </span>
                                        </a>
                                    </div>
                                @empty
                                    <div class="col-12 text-muted fs-13">No modules are available for your role.</div>
                                @endforelse
                            </div>
                        </div>
                    </x-ui.dropdown>

                    {{-- Switching tenants lives in Tenant Console (super_admin only, see
                    TenantSwitchController) — the header just shows where you are. --}}
                    <div class="nxl-h-item erp-tenant-switcher d-none d-xl-flex erp-tenant-button">
                        <span class="avatar-text avatar-sm bg-soft-success text-success">
                            <i class="feather-briefcase"></i>
                        </span>
                        <span class="erp-tenant-copy">
                            <strong>{{ $currentTenant['name'] }}</strong>
                            <small>{{ $currentTenant['branch'] }} - {{ $currentTenant['year'] }}</small>
                        </span>
                    </div>

                    @if ($companies->count() > 1)
                        <x-ui.dropdown class="nxl-h-item erp-company-switcher d-none d-xl-flex"
                            menu-class="nxl-h-dropdown erp-company-dropdown">
                            <x-slot name="trigger">
                                <x-ui.button href="javascript:void(0);" variant="light-brand"
                                    class="erp-company-button dropdown-toggle" data-bs-toggle="dropdown"
                                    data-bs-auto-close="outside" role="button" aria-expanded="false">
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
                                <x-ui.dropdown-item href="{{ route('company.switch', $companyOption['id']) }}"
                                    :active="!empty($companyOption['active'])">
                                    <span
                                        class="avatar-text avatar-sm bg-soft-primary text-primary">{{ substr($companyOption['name'], 0, 1) }}</span>
                                    <span class="d-block fw-semibold">{{ $companyOption['name'] }}</span>
                                    @if (!empty($companyOption['active']))
                                        <i class="feather-check ms-auto me-0 text-success"></i>
                                    @endif
                                </x-ui.dropdown-item>
                            @endforeach
                        </x-ui.dropdown>
                    @endif

                    @if ($branches->count() > 1)
                        <x-ui.dropdown class="nxl-h-item erp-branch-switcher d-none d-xl-flex"
                            menu-class="nxl-h-dropdown erp-branch-dropdown">
                            <x-slot name="trigger">
                                <x-ui.button href="javascript:void(0);" variant="light-brand"
                                    class="erp-branch-button dropdown-toggle" data-bs-toggle="dropdown"
                                    data-bs-auto-close="outside" role="button" aria-expanded="false">
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
                                <x-ui.dropdown-item href="{{ route('branch.switch', $branchOption['id']) }}"
                                    :active="!empty($branchOption['active'])">
                                    <span
                                        class="avatar-text avatar-sm bg-soft-primary text-primary">{{ substr($branchOption['name'], 0, 1) }}</span>
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
                <div class="dropdown nxl-h-item nxl-header-search" id="global-search-container"
                    data-search-url="{{ route('global-search') }}">
                    <a href="javascript:void(0);" class="nxl-head-link me-0" data-bs-toggle="dropdown"
                        data-bs-auto-close="outside" id="global-search-toggle">
                        <i class="feather-search"></i>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end nxl-h-dropdown nxl-search-dropdown shadow-lg border-0"
                        id="global-search-dropdown" style="min-width: 420px; max-width: 520px;">
                        <div class="input-group search-form border-bottom">
                            <span class="input-group-text bg-transparent border-0 pe-1">
                                <i class="feather-search fs-6 text-muted" id="search-spinner-icon"></i>
                            </span>
                            <input type="text" class="form-control search-input-field border-0 ps-1"
                                id="global-search-input" placeholder="{{ __('ui.search_placeholder') }}"
                                autocomplete="off">
                            <span class="input-group-text bg-transparent border-0">
                                <button type="button" class="btn-close fs-11" id="global-search-clear"
                                    style="display: none;"></button>
                            </span>
                        </div>

                        {{-- Scope Selector Pills --}}
                        <div class="search-scope-bar px-3 py-2 bg-light-subtle border-bottom">
                            <div class="d-flex align-items-center justify-content-between mb-1">
                                <span class="fs-11 fw-semibold text-muted text-uppercase tracking-wider">Search
                                    in</span>
                                <span class="badge bg-secondary-subtle text-secondary fs-11"
                                    id="global-search-active-scope">All</span>
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
                                    <button type="button"
                                        class="btn btn-xs py-1 px-2 fs-11 fw-medium search-scope-pill {{ $loop->first ? 'btn-primary' : 'btn-outline-secondary' }}"
                                        data-scope="{{ $scope['id'] }}">
                                        {{ $scope['label'] }}
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        {{-- Dynamic Search & Recent Results Container --}}
                        <div class="search-items-wrapper" id="global-search-results-wrapper"
                            style="max-height: 380px; overflow-y: auto;">
                            {{-- Default view: Recent Searches & Tips --}}
                            <div id="global-search-default-view">
                                <div class="recent-result px-3 py-2" id="global-search-recent-section"
                                    style="display: none;">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <span class="fs-12 fw-semibold text-muted text-uppercase">Recent Searches</span>
                                        <button type="button"
                                            class="btn btn-link btn-xs text-muted p-0 text-decoration-none fs-11"
                                            id="clear-recent-searches">Clear History</button>
                                    </div>
                                    <div id="recent-searches-list"></div>
                                    <div class="dropdown-divider my-2"></div>
                                </div>
                                <div class="px-3 py-2">
                                    <p class="fs-11 fw-medium text-muted mb-0 d-flex align-items-center gap-1">
                                        <i class="feather-info text-primary fs-13 me-0.5"></i>
                                        <span>Type at least 2 characters to search. Use <span
                                                class="search-kbd-key">↑</span> <span class="search-kbd-key">↓</span> to
                                            navigate, <span class="search-kbd-key">Enter</span> to open.</span>
                                    </p>
                                </div>
                            </div>

                            {{-- Live Results View --}}
                            <div id="global-search-results-view" style="display: none;">
                                <div id="global-search-results-list" class="py-1"></div>
                            </div>

                            {{-- Empty State View --}}
                            <div id="global-search-empty-view" class="text-center py-4 px-3" style="display: none;">
                                <div
                                    class="avatar-text avatar-md bg-light-subtle rounded-circle mx-auto mb-2 text-muted">
                                    <i class="feather-search fs-4"></i>
                                </div>
                                <p class="fs-13 fw-medium text-dark mb-1">No results found for "<span
                                        id="empty-query-text"></span>"</p>
                                <p class="fs-11 text-muted mb-2">Try searching with another keyword or reset filter to
                                    <strong>All</strong>.</p>
                                <button type="button" class="btn btn-sm btn-outline-primary fs-11 py-1 px-2"
                                    id="reset-scope-btn">Switch to All Modules</button>
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
                        <a href="javascript:void(0);" class="nxl-head-link me-0"
                            onclick="$('body').fullScreenHelper('toggle');">
                            <i class="feather-maximize maximize"></i>
                            <i class="feather-minimize minimize"></i>
                        </a>
                    </div>
                </div>

                <!-- Primary Color Picker -->
                <div class="nxl-h-item d-flex align-items-center justify-content-center me-3">
                    <div class="d-flex align-items-center gap-2 border rounded-pill px-2 py-1 bg-light header-color-picker-wrapper"
                        style="height: 38px;">
                        <i class="feather-aperture text-muted fs-14"></i>
                        <label for="primaryColorPicker" class="header-color-picker-label" title="Choose Primary Color">
                            <span id="primaryColorPreview" class="header-color-preview-circle"></span>
                            <input type="color" id="primaryColorPicker" class="header-color-picker-input"
                                value="#6337fa" title="Choose Primary Color">
                        </label>
                    </div>
                </div>

                <div class="nxl-h-item dark-light-theme">
                    <a href="javascript:void(0);" class="nxl-head-link me-0 dark-button" id="header-dark-mode-btn"
                        title="Dark Mode" aria-label="Switch to dark mode">
                        <i class="feather-moon"></i>
                    </a>
                    <a href="javascript:void(0);" class="nxl-head-link me-0 light-button" id="header-light-mode-btn"
                        style="display: none" title="Light Mode" aria-label="Switch to light mode">
                        <i class="feather-sun"></i>
                    </a>
                </div>

                <div class="dropdown nxl-h-item" id="header-approvals-dropdown">
                    <a href="javascript:void(0);" class="nxl-head-link me-0" data-bs-toggle="dropdown" role="button"
                        data-bs-auto-close="outside" id="header-approvals-trigger" title="{{ __('ui.approvals') }}">
                        <i class="feather-check-square"></i>
                        <span class="badge bg-success nxl-h-badge d-none" id="header-approvals-badge">0</span>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end nxl-h-dropdown nxl-timesheets-menu"
                        style="min-width: 330px;">
                        <div
                            class="d-flex justify-content-between align-items-center timesheets-head px-3 py-2 border-bottom">
                            <h6 class="fw-bold text-dark mb-0">{{ __('ui.approvals') }}</h6>
                            <span class="fs-11 text-muted text-end ms-auto" id="header-approvals-status-text"></span>
                        </div>
                        <div class="timesheets-body erp-approval-list p-0" id="header-approvals-list"
                            style="max-height: 350px; overflow-y: auto;">
                            <div class="text-center py-4 text-muted" id="header-approvals-loading">
                                <span class="spinner-border spinner-border-sm text-primary me-1" role="status"
                                    aria-hidden="true"></span>
                                <span class="fs-12">Loading approvals...</span>
                            </div>
                        </div>
                        <div class="text-center timesheets-footer py-2 border-top" id="header-approvals-footer">
                            <span class="fs-12 text-muted fw-semibold"
                                id="header-approvals-footer-text">{{ __('ui.approvals') }}</span>
                        </div>
                    </div>
                </div>

                @include('partials.topbar-notifications')

                @php
                    $headerAuthUser = auth()->user();
                    $headerAvatarUrl = $headerAuthUser ? $headerAuthUser->avatar_url : '/assets/images/avatar/default.png';
                    $headerProfileUrl = route('profile.show');
                @endphp
                <div class="dropdown nxl-h-item">
                    <a href="javascript:void(0);" data-bs-toggle="dropdown" role="button" data-bs-auto-close="outside">
                        <img src="{{ $headerAvatarUrl }}" alt="user-image"
                            class="img-fluid user-avtar me-0 object-fit-cover"
                            style="width: 38px; height: 38px; border-radius: 50%;"
                            onerror="this.onerror=null; this.src='/assets/images/avatar/default.png';">
                    </a>
                    <div class="dropdown-menu dropdown-menu-end nxl-h-dropdown nxl-user-dropdown">
                        <div class="dropdown-header">
                            <a href="{{ $headerProfileUrl }}" class="d-flex align-items-center text-decoration-none">
                                <img src="{{ $headerAvatarUrl }}" alt="user-image"
                                    class="img-fluid user-avtar object-fit-cover"
                                    style="width: 38px; height: 38px; border-radius: 50%;"
                                    onerror="this.onerror=null; this.src='/assets/images/avatar/default.png';">
                                <div>
                                    <h6 class="text-dark mb-0">{{ auth()->user()->name ?? __('ui.erp_admin') }} <span
                                            class="badge bg-soft-success text-success ms-1">{{ $currentTenant['plan'] }}</span>
                                    </h6>
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
                        <a href="{{ route('account.settings') }}" class="dropdown-item">
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