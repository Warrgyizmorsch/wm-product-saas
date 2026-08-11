@push('styles')
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
</style>
@endpush

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
