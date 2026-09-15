@php
    $resolvedTenant = tenant();
    $tenantSettings = $resolvedTenant?->settings ?? [];
    $tenantPlan = ucfirst((string) ($resolvedTenant?->plan ?? 'Starter'));
    $branding = tenant_branding($resolvedTenant);

    // Menu entries live in each module's Routes/menu.php and are filtered by plan,
    // role, permission and route existence — see App\Core\Navigation\MenuBuilder.
    $sections = app(\App\Core\Navigation\MenuBuilder::class)->build(auth()->user(), request()->route()?->getName());
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
                @foreach ($sections as $section)
                    <li class="nxl-item nxl-caption premium-module-header" data-module="{{ $section['slug'] }}" onclick="toggleModuleSidebar('{{ $section['slug'] }}', this)">
                        <div class="premium-module-header-content">
                            <span class="premium-module-header-title">{{ strtoupper($section['label']) }}</span>
                            <span class="premium-module-accordion-btn">
                                <span class="premium-module-arrow-container">
                                    <i class="feather-chevron-right premium-module-arrow"></i>
                                </span>
                            </span>
                        </div>
                    </li>
                    @foreach ($section['items'] as $item)
                        @php
                            $hasChildren = $item['children'] !== [];
                        @endphp
                        <li class="nxl-item {{ $hasChildren ? 'nxl-hasmenu' : '' }} {{ $item['active'] ? 'active nxl-trigger' : '' }} premium-module-child module-{{ $section['slug'] }}">
                            <a href="{{ $hasChildren ? 'javascript:void(0);' : $item['url'] }}" class="nxl-link">
                                <span class="nxl-micon"><i class="{{ $item['icon'] }}"></i></span>
                                <span class="nxl-mtext">{{ $item['label'] }}</span>
                                @if ($hasChildren)
                                    <span class="nxl-arrow"><i class="feather-chevron-right"></i></span>
                                @endif
                            </a>
                            @if ($hasChildren)
                                <ul class="nxl-submenu">
                                    @foreach ($item['children'] as $child)
                                        <li class="nxl-item {{ $child['active'] ? 'active' : '' }}">
                                            <a class="nxl-link" href="{{ $child['url'] }}">{{ $child['label'] }}</a>
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
