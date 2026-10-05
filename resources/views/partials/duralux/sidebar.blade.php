{{--
    App sidebar — mirrors ui-reference/code.html: brand row, branch / fiscal-year context card,
    the current app expanded under its group caption, the other apps under "Enterprise Modules",
    and a pinned status footer. Data comes from App\Support\ShellContext ($shell, set in the layout);
    menu entries come from each module's Routes/menu.php via App\Core\Navigation\MenuBuilder.
    Look & feel: public/assets/css/apex-ui.css. Keeps the theme's nxl-* classes so its JS still
    drives submenu expand/collapse, the mini (icon-rail) mode and the mobile drawer.
--}}
@php
    $shell ??= app(\App\Support\ShellContext::class)->resolve();
    $nav = $shell['nav'];
    $branding = $shell['branding'];
    $currentApp = $shell['current_app'];
    $otherApps = collect($nav['apps'])->reject(fn ($app) => $app['key'] === $nav['app']);
@endphp

<nav class="nxl-navigation ax-sidebar">
    <div class="navbar-wrapper">
        {{-- Brand --}}
        <div class="m-header">
            <a href="{{ route('dashboard') }}" class="b-brand erp-tenant-brand">
                @if ($branding['has_full_logo'])
                    <img src="{{ $branding['full_logo'] }}" alt="{{ $branding['name'] }}" class="logo logo-lg logo-full erp-brand-logo-full">
                @else
                    <span class="logo logo-lg logo-full ax-brand">
                        <span class="ax-brand-tile"><i class="feather-sliders"></i></span>
                        <span class="ax-brand-copy">
                            <span class="erp-brand-wordmark">{{ $branding['name'] }}</span>
                            <span class="ax-brand-sub">{{ $shell['tenant_name'] }} · {{ $shell['plan'] }}</span>
                        </span>
                    </span>
                @endif

                @if ($branding['has_abbr_logo'])
                    <img src="{{ $branding['abbr_logo'] }}" alt="{{ $branding['name'] }}" class="logo logo-sm logo-abbr erp-brand-logo-abbr">
                @else
                    <span class="logo logo-sm logo-abbr erp-brand-mark ax-brand-tile"><i class="feather-sliders"></i></span>
                @endif
            </a>
            <button type="button" class="ax-sidebar-collapse d-none d-lg-inline-flex" id="ax-sidebar-collapse" title="{{ __('Collapse sidebar') }}" aria-label="{{ __('Collapse sidebar') }}">
                <i class="feather-chevrons-left"></i>
            </button>
        </div>

        {{-- Branch / fiscal-year context (switches company and branch) --}}
        <div class="ax-context">
            <div class="dropdown">
                <button type="button" class="ax-context-card" data-bs-toggle="dropdown" aria-expanded="false"
                    @disabled($shell['companies']->count() <= 1 && $shell['branches']->count() <= 1)>
                    <span class="ax-dot is-live"></span>
                    <span class="ax-context-copy">
                        <span class="ax-context-title">{{ $shell['branch_name'] }}</span>
                        <span class="ax-context-sub">{{ $shell['fiscal_year'] }} · {{ $shell['company_name'] }}</span>
                    </span>
                    @if ($shell['companies']->count() > 1 || $shell['branches']->count() > 1)
                        <i class="feather-chevron-down ax-context-caret"></i>
                    @endif
                </button>
                <div class="dropdown-menu ax-context-menu">
                    @if ($shell['branches']->count() > 1)
                        <h6 class="dropdown-header">{{ __('ui.switch_branch') }}</h6>
                        @foreach ($shell['branches'] as $option)
                            <a href="{{ route('branch.switch', $option['id']) }}" @class(['dropdown-item d-flex align-items-center gap-2', 'active' => $option['active']])>
                                <i class="feather-map-pin"></i><span class="text-truncate">{{ $option['name'] }}</span>
                                @if ($option['active'])<i class="feather-check ms-auto"></i>@endif
                            </a>
                        @endforeach
                    @endif
                    @if ($shell['companies']->count() > 1)
                        @if ($shell['branches']->count() > 1)<div class="dropdown-divider"></div>@endif
                        <h6 class="dropdown-header">{{ __('ui.switch_company') }}</h6>
                        @foreach ($shell['companies'] as $option)
                            <a href="{{ route('company.switch', $option['id']) }}" @class(['dropdown-item d-flex align-items-center gap-2', 'active' => $option['active']])>
                                <i class="feather-home"></i><span class="text-truncate">{{ $option['name'] }}</span>
                                @if ($option['active'])<i class="feather-check ms-auto"></i>@endif
                            </a>
                        @endforeach
                    @endif
                </div>
            </div>
        </div>

        {{-- Navigation --}}
        <div class="navbar-content">
            <ul class="nxl-navbar">
                @if ($currentApp !== null)
                    <li class="nxl-item nxl-caption"><span>{{ $currentApp['group'] ?? $currentApp['label'] }}</span></li>

                    {{-- The app you are in, expanded --}}
                    <li class="nxl-item nxl-hasmenu active nxl-trigger ax-app-current" data-nav-app="{{ $nav['app'] }}">
                        <a href="javascript:void(0);" class="nxl-link">
                            <span class="nxl-micon ax-app-icon"><i class="{{ $currentApp['icon'] }}"></i></span>
                            <span class="nxl-mtext">{{ $currentApp['label'] }}</span>
                            <span class="nxl-arrow"><i class="feather-chevron-down"></i></span>
                        </a>
                        <ul class="nxl-submenu">
                            @foreach ($nav['items'] as $item)
                                @include('partials.duralux.sidebar-item', ['item' => $item, 'nested' => true])
                            @endforeach
                        </ul>
                    </li>
                @else
                    {{-- Workspace home: workspace-level entries --}}
                    <li class="nxl-item nxl-caption"><span>{{ __('ui.workspace') }}</span></li>
                    <li @class(['nxl-item', 'active' => request()->routeIs('apps')])>
                        <a href="{{ route('apps') }}" class="nxl-link">
                            <span class="nxl-micon"><i class="feather-grid"></i></span>
                            <span class="nxl-mtext">{{ __('ui.modules') }}</span>
                        </a>
                    </li>
                    @foreach ($nav['sections'] as $section)
                        @continue($section['key'] !== 'workspace')
                        @foreach ($section['items'] as $item)
                            @include('partials.duralux.sidebar-item', ['item' => $item, 'class' => 'module-'.$section['slug']])
                        @endforeach
                    @endforeach
                @endif

                {{-- Every other app the user can open --}}
                @if ($otherApps->isNotEmpty())
                    <li class="nxl-item nxl-caption"><span>{{ __('Enterprise Modules') }}</span></li>
                    @foreach ($otherApps as $app)
                        <li class="nxl-item ax-app-link" data-app="{{ $app['key'] }}">
                            <a href="{{ $app['url'] }}" class="nxl-link" title="{{ $app['description'] ?? $app['label'] }}">
                                <span class="nxl-micon"><i class="{{ $app['icon'] }}"></i></span>
                                <span class="nxl-mtext">{{ $app['label'] }}</span>
                            </a>
                        </li>
                    @endforeach
                @endif
            </ul>
        </div>

        {{-- Status footer --}}
        <div class="ax-nav-footer">
            <div class="ax-nav-footer-row">
                <span class="d-inline-flex align-items-center gap-2 text-truncate">
                    <span class="ax-dot is-live"></span>
                    <span class="text-truncate">{{ $shell['tenant_name'] }}</span>
                </span>
                <span class="ax-mono">{{ $shell['plan'] }}</span>
            </div>
            @if ($shell['seat_limit'] > 0)
                <div class="ax-meter" title="{{ $shell['seats_used'] }} / {{ $shell['seat_limit'] }} users">
                    <span style="width: {{ min(100, (int) round($shell['seats_used'] / $shell['seat_limit'] * 100)) }}%"></span>
                </div>
            @endif
            <div class="ax-nav-footer-row">
                <span>{{ $shell['seat_limit'] > 0 ? $shell['seats_used'].' / '.$shell['seat_limit'].' users' : $shell['branch_name'] }}</span>
                @if ($shell['tenant'] && \Illuminate\Support\Facades\Route::has('platform.subscription.index') && auth()->user()?->can('viewSubscription', $shell['tenant']))
                    <a href="{{ route('platform.subscription.index') }}">{{ __('Billing') }}</a>
                @endif
            </div>
        </div>
    </div>
</nav>

<script>
// Sidebar collapse button → the theme's own mini-menu toggles (kept hidden in the header).
document.addEventListener('DOMContentLoaded', function () {
    var collapse = document.getElementById('ax-sidebar-collapse');
    if (!collapse) return;
    collapse.addEventListener('click', function () {
        var mini = document.getElementById('menu-mini-button');
        var expand = document.getElementById('menu-expend-button');
        var target = document.documentElement.classList.contains('minimenu') ? expand : mini;
        if (target) target.click();
    });
});
</script>
