{{--
    Platform console (/admin) layout. Same theme, head and scripts as layouts.duralux,
    but none of the tenant shell (company/branch switcher, app launcher, plan banners):
    the console runs with no current tenant. Sidebar entries: Platform/Routes/admin-menu.php.
    Pages use the same sections as duralux: title, page-title, page-badge, page-actions,
    page-back-button, breadcrumb, content, plus the styles/scripts stacks.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="ltr">

<head>
    @include('layouts.partials.head')
</head>

<body class="ax-platform-console">
    @php
        $adminUser = auth()->user();
        $adminMenu = app(\App\Domains\Platform\Support\AdminMenu::class)->build($adminUser);
    @endphp

    <nav class="nxl-navigation ax-sidebar">
        <div class="navbar-wrapper">
            <div class="m-header ax-brand-row">
                <a href="{{ route('admin.dashboard') }}" class="b-brand ax-brand-link" title="{{ config('app.name') }}">
                    <span class="ax-brand-expanded">
                        <span class="ax-brand-mark"><i class="feather-shield"></i></span>
                        <span class="ax-brand-name">{{ config('app.name', 'SaaS ERP') }} <small class="d-block text-muted fw-normal">Platform Console</small></span>
                    </span>
                    <span class="ax-brand-mark ax-brand-collapsed"><i class="feather-shield"></i></span>
                </a>
            </div>

            <div class="navbar-content">
                <ul class="nxl-navbar">
                    @foreach ($adminMenu as $section)
                        <li class="nxl-item nxl-caption"><span>{{ $section['label'] }}</span></li>
                        @foreach ($section['items'] as $item)
                            <li @class(['nxl-item', 'active' => $item['active']])>
                                <a href="{{ $item['url'] }}" class="nxl-link">
                                    <span class="nxl-micon"><i class="{{ $item['icon'] }}"></i></span>
                                    <span class="nxl-mtext">{{ $item['label'] }}</span>
                                </a>
                            </li>
                        @endforeach
                    @endforeach
                </ul>
            </div>
        </div>
    </nav>

    <header class="nxl-header ax-header">
        <div class="header-wrapper">
            <div class="header-left d-flex align-items-center gap-3 min-w-0">
                <a href="javascript:void(0);" class="nxl-head-mobile-toggler d-lg-none" id="mobile-collapse" aria-label="Menu">
                    <div class="hamburger hamburger--arrowturn">
                        <div class="hamburger-box">
                            <div class="hamburger-inner"></div>
                        </div>
                    </div>
                </a>
                <div class="nxl-navigation-toggle d-none">
                    <a href="javascript:void(0);" id="menu-mini-button"><i class="feather-align-left"></i></a>
                    <a href="javascript:void(0);" id="menu-expend-button" style="display: none"><i class="feather-arrow-right"></i></a>
                </div>
                <nav aria-label="Breadcrumb" class="ax-breadcrumb">
                    <a href="{{ route('admin.dashboard') }}">Platform Console</a>
                    <i class="feather-chevron-right"></i>
                    <span class="ax-breadcrumb-current">@yield('breadcrumb', View::getSection('page-title', 'Dashboard'))</span>
                </nav>
            </div>

            <div class="header-right ms-auto">
                <div class="d-flex align-items-center gap-2">
                    <div class="dropdown">
                        <a href="javascript:void(0);" class="ax-avatar" data-bs-toggle="dropdown" role="button" aria-label="Account">
                            <img src="{{ $adminUser?->avatar_url ?? '/assets/images/avatar/default.png' }}" alt=""
                                onerror="this.onerror=null; this.src='/assets/images/avatar/default.png';">
                        </a>
                        <div class="dropdown-menu dropdown-menu-end">
                            <div class="dropdown-header">
                                <div class="fw-semibold text-dark">{{ $adminUser?->name }}</div>
                                <div class="fs-12 text-muted">{{ $adminUser?->email }}</div>
                            </div>
                            <div class="dropdown-divider"></div>
                            <form method="POST" action="{{ route('admin.logout') }}">
                                @csrf
                                <button type="submit" class="dropdown-item border-0 bg-transparent w-100 text-start"><i class="feather-log-out me-2"></i><span>Sign out</span></button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <main class="nxl-container">
        <div class="nxl-content">
            <div class="page-header ax-context-bar">
                <div class="page-header-left d-flex align-items-center gap-2 min-w-0">
                    @hasSection('page-back-button')
                        <div class="me-1">@yield('page-back-button')</div>
                    @endif
                    <div class="page-header-title d-flex flex-wrap align-items-center gap-2 min-w-0">
                        <h5 class="ax-page-title mb-0">@yield('page-title', 'Dashboard')</h5>
                        @yield('page-badge')
                    </div>
                </div>
                <div class="page-header-right ms-auto">
                    <div class="page-header-right-items">
                        <div class="d-flex align-items-center gap-2 page-header-right-items-wrapper">
                            @yield('page-actions')
                        </div>
                    </div>
                </div>
            </div>

            <div class="main-content">
                @if(session('success'))
                    <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
                @endif
                @if(session('error'))
                    <x-ui.toast :auto="true" type="error" title="{{ session('error') }}" />
                @endif
                @if(session('warning'))
                    <x-ui.toast :auto="true" type="warning" title="{{ session('warning') }}" />
                @endif
                @yield('content')
            </div>
        </div>
    </main>

    @include('layouts.partials.scripts')
</body>

</html>
