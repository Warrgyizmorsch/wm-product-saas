{{--
    Top header — mirrors ui-reference/code.html: breadcrumb on the left; search, currency, the
    primary "Create" action, approvals, notifications and the user menu on the right. Language,
    theme, brand colour and full-screen live in the user menu. Data: App\Support\ShellContext.
--}}
@php
    $shell ??= app(\App\Support\ShellContext::class)->resolve();

    // Quick-create targets — only the ones this install actually has.
    $quickCreates = collect([
        ['label' => __('ui.journal_entry'), 'icon' => 'feather-book', 'route' => 'accounting.journals.create'],
        ['label' => __('ui.sales_invoice'), 'icon' => 'feather-file-plus', 'route' => 'sales.invoices.create'],
        ['label' => 'Sales Order', 'icon' => 'feather-shopping-cart', 'route' => 'sales.orders.create'],
        ['label' => 'Purchase Order', 'icon' => 'feather-truck', 'route' => 'purchase.orders.create'],
        ['label' => 'Lead', 'icon' => 'feather-user-plus', 'route' => 'crm.leads.create'],
        ['label' => __('ui.customer'), 'icon' => 'feather-users', 'route' => 'crm.customers.create'],
        ['label' => 'Product', 'icon' => 'feather-box', 'route' => 'inventory.products.create'],
        ['label' => __('ui.stock_adjustment'), 'icon' => 'feather-package', 'route' => 'inventory.adjustments.create'],
    ])->filter(fn ($item) => \Illuminate\Support\Facades\Route::has($item['route']));

    $headerUser = auth()->user();
    $headerAvatarUrl = $headerUser ? $headerUser->avatar_url : '/assets/images/avatar/default.png';
    $languages = collect(config('localization.supported', []));
    $appLabel = $shell['current_app']['label'] ?? __('ui.workspace');
@endphp

<header class="nxl-header ax-header">
    <div class="header-wrapper">
        {{-- Left: breadcrumb --}}
        <div class="header-left d-flex align-items-center gap-3 min-w-0">
            <a href="javascript:void(0);" class="nxl-head-mobile-toggler d-lg-none" id="mobile-collapse" aria-label="{{ __('Menu') }}">
                <div class="hamburger hamburger--arrowturn">
                    <div class="hamburger-box">
                        <div class="hamburger-inner"></div>
                    </div>
                </div>
            </a>
            {{-- The theme's mini-menu toggles; the sidebar's collapse button clicks these. --}}
            <div class="nxl-navigation-toggle d-none">
                <a href="javascript:void(0);" id="menu-mini-button"><i class="feather-align-left"></i></a>
                <a href="javascript:void(0);" id="menu-expend-button" style="display: none"><i class="feather-arrow-right"></i></a>
            </div>

            <nav aria-label="Breadcrumb" class="ax-breadcrumb">
                <a href="{{ route('dashboard') }}">{{ __('Workspaces') }}</a>
                <i class="feather-chevron-right"></i>
                @if ($shell['current_app'])
                    <a href="{{ $shell['current_app']['url'] }}" class="d-none d-md-inline">{{ $appLabel }}</a>
                @else
                    <span class="d-none d-md-inline">{{ $shell['tenant_name'] }}</span>
                @endif
                <i class="feather-chevron-right d-none d-md-inline"></i>
                <span class="ax-breadcrumb-current">@yield('breadcrumb', __('ui.dashboard'))</span>
            </nav>
        </div>

        {{-- Right: global actions --}}
        <div class="header-right ms-auto">
            <div class="d-flex align-items-center gap-2">
                @include('partials.duralux.header-search')

                <span class="ax-currency-pill d-none d-md-inline-flex" title="{{ __('Amounts are in :code', ['code' => $shell['currency']['code']]) }}">
                    <span class="ax-dot is-positive"></span>{{ $shell['currency']['code'] }} ({{ $shell['currency']['symbol'] }})
                </span>

                @hasSection('header-cta')
                    {{-- A page can put its own primary action here (e.g. "New Journal" on the Accounting dashboard). --}}
                    @yield('header-cta')
                @elseif ($quickCreates->isNotEmpty())
                    <div class="dropdown nxl-h-item">
                        <button type="button" class="btn btn-primary ax-create-btn" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="feather-plus"></i><span class="d-none d-sm-inline">{{ __('Create') }}</span>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end ax-create-menu">
                            <h6 class="dropdown-header">{{ __('Quick create') }}</h6>
                            @foreach ($quickCreates as $item)
                                <a href="{{ route($item['route']) }}" class="dropdown-item d-flex align-items-center gap-2">
                                    <i class="{{ $item['icon'] }}"></i><span>{{ $item['label'] }}</span>
                                </a>
                            @endforeach
                            <div class="dropdown-divider"></div>
                            <a href="{{ route('apps') }}" class="dropdown-item d-flex align-items-center gap-2">
                                <i class="feather-grid"></i><span>{{ __('All modules') }}</span>
                            </a>
                        </div>
                    </div>
                @endif

                <span class="ax-header-divider d-none d-md-block"></span>

                {{-- Approvals (assets/js/approval-center.js) --}}
                <div class="dropdown nxl-h-item" id="header-approvals-dropdown">
                    <a href="javascript:void(0);" class="nxl-head-link ax-icon-btn me-0" data-bs-toggle="dropdown" role="button"
                        data-bs-auto-close="outside" id="header-approvals-trigger" title="{{ __('ui.approvals') }}">
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

                @include('partials.topbar-notifications')

                {{-- User menu --}}
                <div class="dropdown nxl-h-item ax-user">
                    <a href="javascript:void(0);" class="ax-avatar" data-bs-toggle="dropdown" role="button" data-bs-auto-close="outside" aria-label="{{ __('Account') }}">
                        <img src="{{ $headerAvatarUrl }}" alt="" class="user-avtar object-fit-cover"
                            onerror="this.onerror=null; this.src='/assets/images/avatar/default.png';">
                        <span class="ax-avatar-status"></span>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end nxl-h-dropdown nxl-user-dropdown ax-user-menu">
                        <a href="{{ route('profile.show') }}" class="ax-user-card">
                            <img src="{{ $headerAvatarUrl }}" alt="" class="object-fit-cover"
                                onerror="this.onerror=null; this.src='/assets/images/avatar/default.png';">
                            <span class="min-w-0">
                                <span class="ax-user-name">{{ $headerUser->name ?? __('ui.erp_admin') }}</span>
                                <span class="ax-user-email">{{ $headerUser->email ?? '' }}</span>
                            </span>
                            <span class="badge bg-soft-success ms-auto">{{ $shell['plan'] }}</span>
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="{{ route('profile.show') }}" class="dropdown-item"><i class="feather-user"></i><span>My Profile</span></a>
                        <a href="{{ route('account.settings') }}" class="dropdown-item"><i class="feather-settings"></i><span>{{ __('ui.account_settings') }}</span></a>
                        @if (\Illuminate\Support\Facades\Route::has('access.roles.index'))
                            <a href="{{ route('access.roles.index') }}" class="dropdown-item"><i class="feather-shield"></i><span>{{ __('ui.roles_permissions') }}</span></a>
                        @endif

                        <div class="dropdown-divider"></div>
                        <h6 class="dropdown-header">{{ __('Preferences') }}</h6>
                        <div class="ax-pref-row">
                            <span><i class="feather-moon"></i>{{ __('Theme') }}</span>
                            <span class="ax-segmented">
                                <button type="button" class="light-button" id="header-light-mode-btn">{{ __('Light') }}</button>
                                <button type="button" class="dark-button" id="header-dark-mode-btn">{{ __('Dark') }}</button>
                            </span>
                        </div>
                        <div class="ax-pref-row">
                            <span><i class="feather-aperture"></i>{{ __('Brand colour') }}</span>
                            <label for="primaryColorPicker" class="header-color-picker-label" title="Choose Primary Color">
                                <span id="primaryColorPreview" class="header-color-preview-circle"></span>
                                <input type="color" id="primaryColorPicker" class="header-color-picker-input" value="#4f46e5" title="Choose Primary Color">
                            </label>
                        </div>
                        @if ($languages->count() > 1)
                            <div class="ax-pref-row">
                                <span><i class="feather-globe"></i>{{ __('ui.select_language') }}</span>
                                <span class="d-flex flex-wrap justify-content-end gap-1">
                                    @foreach ($languages as $locale => $language)
                                        <a href="{{ route('locale.switch', $locale) }}" @class(['ax-lang', 'active' => app()->getLocale() === $locale]) title="{{ $language['name'] }}">
                                            <img src="{{ asset('assets/vendors/img/flags/1x1/'.$language['flag'].'.svg') }}" alt="{{ $language['name'] }}">
                                        </a>
                                    @endforeach
                                </span>
                            </div>
                        @endif
                        <a href="javascript:void(0);" class="dropdown-item" onclick="$('body').fullScreenHelper('toggle');"><i class="feather-maximize"></i><span>{{ __('Full screen') }}</span></a>

                        <div class="dropdown-divider"></div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="dropdown-item border-0 bg-transparent w-100 text-start"><i class="feather-log-out"></i><span>{{ __('ui.logout') }}</span></button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>

<script src="{{ asset('assets/js/global-search.js') }}" defer></script>
<script src="{{ asset('assets/js/approval-center.js') }}" defer></script>
<script>
    // Ctrl/⌘ + K opens global search.
    document.addEventListener('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
            var toggle = document.getElementById('global-search-toggle');
            if (!toggle || !window.bootstrap) return;
            e.preventDefault();
            bootstrap.Dropdown.getOrCreateInstance(toggle).show();
            setTimeout(function () { document.getElementById('global-search-input')?.focus(); }, 50);
        }
    });
</script>
