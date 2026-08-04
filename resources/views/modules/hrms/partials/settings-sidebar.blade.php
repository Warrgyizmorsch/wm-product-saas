@php
    $isSalary = request()->routeIs('hrms.salary-structure.index') || request()->query('tab') === 'salary-structure';
    $isLeave = request()->routeIs('hrms.leave-structure.index') || request()->routeIs('hrms.leave-structure.transition');
    $isPenalty = request()->routeIs('hrms.penalization-policy.index');
    $isRoster = request()->routeIs('hrms.roster.index');
    $isAsset = request()->routeIs('hrms.assets.index');
    $isOrg = !$isSalary && !$isLeave && !$isPenalty && !$isRoster && !$isAsset;
@endphp

<div class="settings-sidebar-panel h-100">
    <div class="settings-sidebar-header py-4 px-4 border-bottom">
        <h6 class="fw-bold mb-0 text-dark" style="font-size: 15px; letter-spacing: 0.5px;">{{ __('hrms.sidebar.settings') }}</h6>
    </div>
    <div class="settings-sidebar-body py-3 px-3">
        <div class="nav flex-column nav-pills gap-1" id="settingsSubSidebar" role="tablist" aria-orientation="vertical">
            <a class="nav-link {{ $isOrg ? 'active' : '' }} d-flex align-items-center text-start transition-all" id="org-structure-menu" href="{{ route('hrms.org.index') }}" role="tab" aria-controls="org-structure-pane" aria-selected="{{ $isOrg ? 'true' : 'false' }}">
                <i class="feather-settings me-3 fs-16"></i>
                <span>{{ __('hrms.sidebar.org_structure') }}</span>
            </a>
            <a class="nav-link {{ $isSalary ? 'active' : '' }} d-flex align-items-center text-start transition-all" id="salary-structure-menu" href="{{ route('hrms.salary-structure.index') }}" role="tab" aria-controls="salary-structure-pane" aria-selected="{{ $isSalary ? 'true' : 'false' }}">
                <i class="feather-dollar-sign me-3 fs-16"></i>
                <span>{{ __('hrms.sidebar.salary_structure') }}</span>
            </a>
            <a class="nav-link {{ $isLeave ? 'active' : '' }} d-flex align-items-center text-start transition-all" id="leave-structure-menu" href="{{ route('hrms.leave-structure.index') }}" role="tab" aria-selected="{{ $isLeave ? 'true' : 'false' }}">
                <i class="feather-calendar me-3 fs-16"></i>
                <span>{{ __('hrms.sidebar.leave_structure') }}</span>
            </a>
            <a class="nav-link {{ $isRoster ? 'active' : '' }} d-flex align-items-center text-start transition-all" id="shift-roster-menu" href="{{ route('hrms.roster.index') }}" role="tab" aria-selected="{{ $isRoster ? 'true' : 'false' }}">
                <i class="feather-clock me-3 fs-16"></i>
                <span>{{ __('hrms.sidebar.shift_roster') }}</span>
            </a>
            <a class="nav-link {{ $isPenalty ? 'active' : '' }} d-flex align-items-center text-start transition-all" id="penalization-policy-menu" href="{{ route('hrms.penalization-policy.index') }}" role="tab" aria-selected="{{ $isPenalty ? 'true' : 'false' }}">
                <i class="feather-alert-octagon me-3 fs-16"></i>
                <span>{{ __('hrms.sidebar.penalization_policy') }}</span>
            </a>
            <a class="nav-link {{ $isAsset ? 'active' : '' }} d-flex align-items-center text-start transition-all" id="asset-management-menu" href="{{ route('hrms.assets.index') }}" role="tab" aria-selected="{{ $isAsset ? 'true' : 'false' }}">
                <i class="feather-package me-3 fs-16"></i>
                <span>{{ __('hrms.sidebar.asset_management') }}</span>
            </a>
        </div>
    </div>
</div>

<style>
    /* Fixed/Sticky settings sidebar panel styles */
    @media (min-width: 992px) {
        .nxl-container, .nxl-content, .main-content {
            overflow: visible !important;
            overflow-x: visible !important;
            overflow-y: visible !important;
        }
        .settings-container {
            align-items: flex-start !important;
        }
        .settings-sidebar-col {
            position: sticky !important;
            top: 92px !important;
            height: calc(100vh - 120px) !important;
            align-self: flex-start !important;
            z-index: 100 !important;
        }
        .settings-sidebar-panel {
            display: flex !important;
            flex-direction: column !important;
            height: 100% !important;
        }
        .settings-sidebar-body {
            flex-grow: 1 !important;
            overflow-y: auto !important;
        }
    }

    /* Premium dynamic settings sidebar shadow styles */
    #settingsSubSidebar .nav-link {
        background-color: transparent;
        transition: all 0.25s ease-in-out;
        border-radius: 8px !important;
        font-size: 14px;
        font-weight: 500;
        color: #475569 !important;
        padding: 12px 16px !important;
        border: 0 !important;
        display: flex;
        align-items: center;
        width: 100%;
        margin-left: 6px; /* Offset spacing to accommodate the left shadow */
    }
    #settingsSubSidebar .nav-link:hover {
        background-color: #f1f5f9;
        color: #1e293b !important;
    }
    #settingsSubSidebar .nav-link.active {
        background-color: var(--bs-primary) !important; /* Dynamically matches the active primary theme color */
        color: #ffffff !important;
        font-weight: 600;
        border: none !important;
        /* Renders a solid dynamic contrast offset shadow/shape on the left side of the active item */
        box-shadow: -6px 0 0 0 color-mix(in srgb, var(--bs-primary) 70%, #555555) !important;
    }
    #settingsSubSidebar .nav-link i {
        transition: all 0.25s ease;
    }
    #settingsSubSidebar .nav-link.active i {
        color: #ffffff !important;
        transform: scale(1.1);
    }

    /* Select2 Odoo look & duplicate fix overrides specifically inside HRMS Settings */
    select.select2-hidden-accessible {
        display: none !important;
        visibility: hidden !important;
        position: absolute !important;
        width: 1px !important;
        height: 1px !important;
        overflow: hidden !important;
        opacity: 0 !important;
        pointer-events: none !important;
    }
    .select2-container--bootstrap-5 .select2-dropdown {
        border: 1px solid #cbd5e1 !important;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05) !important;
        border-radius: 8px !important;
        overflow: hidden !important;
        z-index: 1060 !important;
    }
    .select2-container--bootstrap-5 .select2-search .select2-search__field {
        border-radius: 4px !important;
        border: 1px solid #cbd5e1 !important;
        font-size: 13px !important;
        padding: 6px 10px !important;
        background-color: #ffffff !important;
    }
    body .select2-results__option:not([role=group]) {
        background-color: #ffffff !important;
        background: #ffffff !important;
        color: #1e293b !important;
        padding: 8px 12px !important;
        font-size: 13px !important;
    }
    body .select2-results__option:not([role=group]).select2-results__option--highlighted {
        background-color: #333a4d !important;
        background: #333a4d !important;
        color: #ffffff !important;
    }
    body .select2-results__option[aria-selected=true] {
        background-color: #333a4d !important;
        color: #ffffff !important;
    }

    /* Chevron Up by default for active sort items that do not contain an icon */
    .erp-sort-dropdown .dropdown-item.active:not(:has(i))::after {
        content: '';
        display: inline-block;
        width: 12px;
        height: 12px;
        background-repeat: no-repeat;
        background-position: center;
        background-size: contain;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%233b82f6' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='18 15 12 9 6 15'%3E%3C/polyline%3E%3C/svg%3E") !important;
        margin-left: 12px;
    }
    /* Chevron Down for active sort items matching descending keywords */
    .erp-sort-dropdown .dropdown-item.active[data-sort*="desc"]:not(:has(i))::after,
    .erp-sort-dropdown .dropdown-item.active[data-sort*="high"]:not(:has(i))::after,
    .erp-sort-dropdown .dropdown-item.active[data-sort*="oldest"]:not(:has(i))::after,
    .erp-sort-dropdown .dropdown-item.active[onclick*="desc"]:not(:has(i))::after,
    .erp-sort-dropdown .dropdown-item.active[onclick*="high"]:not(:has(i))::after,
    .erp-sort-dropdown .dropdown-item.active[onclick*="oldest"]:not(:has(i))::after,
    .erp-sort-dropdown .dropdown-item.active[href*="desc"]:not(:has(i))::after,
    .erp-sort-dropdown .dropdown-item.active[href*="high"]:not(:has(i))::after,
    .erp-sort-dropdown .dropdown-item.active[href*="oldest"]:not(:has(i))::after {
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%233b82f6' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E") !important;
    }

    /* Standard styling and flex-order swap for buttons inside filter dropdowns */
    .erp-filter-dropdown form .d-flex {
        display: flex !important;
        justify-content: flex-start !important;
        gap: 10px !important;
        margin-top: 24px !important;
    }
    .erp-filter-dropdown form button[type="submit"],
    .erp-filter-dropdown form .btn-primary,
    .erp-filter-dropdown form .roster-filter-apply-btn {
        order: 1 !important;
        background-color: #5d4037 !important; /* Dark brown theme color */
        border-color: #5d4037 !important;
        color: #ffffff !important;
        font-weight: 700 !important;
        font-size: 11px !important;
        letter-spacing: 0.05em !important;
        padding: 8px 20px !important;
        border-radius: 8px !important;
        text-transform: uppercase !important;
        height: auto !important;
        width: auto !important;
        flex: none !important;
        box-shadow: none !important;
    }
    .erp-filter-dropdown form a.btn-light,
    .erp-filter-dropdown form .btn-light,
    .erp-filter-dropdown form .roster-filter-reset-btn {
        order: 2 !important;
        background-color: #f1f5f9 !important; /* Light grey/blue color */
        border: 1px solid #cbd5e1 !important;
        color: #475569 !important;
        font-weight: 700 !important;
        font-size: 11px !important;
        letter-spacing: 0.05em !important;
        padding: 8px 20px !important;
        border-radius: 8px !important;
        text-transform: uppercase !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        height: auto !important;
        width: auto !important;
        flex: none !important;
        box-shadow: none !important;
    }

    /* ── Table Responsive Dropdown Visibility Fix ── */
    .table-responsive {
        position: relative;
    }
    .table-responsive:has(.dropdown.show) {
        overflow: visible !important;
    }

    /* ── Custom Sort Dropdown Chevrons ── */
    .erp-sort-dropdown .dropdown-item.active:not(:has(i))::after {
        content: '';
        display: inline-block;
        width: 12px;
        height: 12px;
        background-repeat: no-repeat;
        background-position: center;
        background-size: contain;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%233b82f6' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='18 15 12 9 6 15'%3E%3C/polyline%3E%3C/svg%3E") !important;
        margin-left: 12px;
    }
    .erp-sort-dropdown .dropdown-item.active[data-sort*="desc"]:not(:has(i))::after,
    .erp-sort-dropdown .dropdown-item.active[data-sort*="high"]:not(:has(i))::after,
    .erp-sort-dropdown .dropdown-item.active[data-sort*="oldest"]:not(:has(i))::after,
    .erp-sort-dropdown .dropdown-item.active[onclick*="desc"]:not(:has(i))::after,
    .erp-sort-dropdown .dropdown-item.active[onclick*="high"]:not(:has(i))::after,
    .erp-sort-dropdown .dropdown-item.active[onclick*="oldest"]:not(:has(i))::after,
    .erp-sort-dropdown .dropdown-item.active[href*="desc"]:not(:has(i))::after,
    .erp-sort-dropdown .dropdown-item.active[href*="high"]:not(:has(i))::after,
    .erp-sort-dropdown .dropdown-item.active[href*="oldest"]:not(:has(i))::after {
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%233b82f6' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E") !important;
    }
</style>

@push('scripts')
<script>
    $(document).ready(function() {
        // Prevent clicks inside Select2 container or dropdown from bubbling to document click handlers
        $(document).on('click', '.select2-container, .select2-dropdown', function(e) {
            e.stopPropagation();
        });

        // Prevent Bootstrap from closing the filter dropdown when interacting with Select2 elements
        $(document).on('hide.bs.dropdown', '.erp-filter-dropdown', function (e) {
            if ($('.select2-container--open').length) {
                e.preventDefault();
            }
        });

        // Re-initialize select2 inside dropdowns when opened to calculate correct width and prevent overlap
        $(document).on('shown.bs.dropdown', '.dropdown', function () {
            var dropdown = $(this);
            dropdown.find('.odoo-select2').each(function () {
                var select = $(this);
                if (select.hasClass('select2-hidden-accessible')) {
                    select.select2('destroy');
                }
                select.select2({
                    theme: "bootstrap-5",
                    width: "100%"
                });
            });
        });

        // Dynamically standardize filter dropdown buttons text (Apply -> APPLY FILTERS, Reset -> RESET)
        function updateFilterButtonLabels() {
            $('.erp-filter-dropdown form').each(function() {
                var form = $(this);
                var applyBtn = form.find('button[type="submit"], .btn-primary, .roster-filter-apply-btn');
                if (applyBtn.length) {
                    applyBtn.text('APPLY FILTERS');
                }
                var resetBtn = form.find('a.btn-light, .btn-light, .roster-filter-reset-btn');
                if (resetBtn.length) {
                    resetBtn.text('RESET');
                }
            });
        }
        updateFilterButtonLabels();
        $(document).ajaxComplete(updateFilterButtonLabels);
    });
</script>
@endpush
