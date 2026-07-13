@props([
    'label' => 'Filter',
    'offset' => '0, 10'
])

@once
    @push('styles')
        <style>
            .erp-filter-dropdown {
                position: relative !important;
            }
            /* Override the theme's forced off-screen coordinates and width limits */
            .erp-filter-dropdown .dropdown-menu {
                position: absolute !important;
                top: 100% !important;
                right: 0 !important;
                left: auto !important;
                transform: none !important;
                display: none !important;
                min-width: 320px !important;
                width: auto !important;
                padding: 18px !important;
                border: 1px solid #e2e8f0 !important;
                border-radius: 8px !important;
                box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05) !important;
                margin-top: 5px !important;
                z-index: 1050 !important;
                transition: none !important;
                background-color: #ffffff !important;
            }
            .erp-filter-dropdown .dropdown-menu.show {
                display: block !important;
                visibility: visible !important;
                opacity: 1 !important;
            }
            /* Make form input lines clearly visible inside the filter dropdown */
            .erp-filter-dropdown .odoo-table-input {
                border-bottom: 1px solid #ced4da !important;
            }
        </style>
    @endpush
@endonce

<div class="dropdown erp-filter-dropdown" {{ $attributes }}>
    <x-ui.icon-btn type="button" 
                   variant="transparent-dark"
                   title="Filter"
                   size="md"
                   icon="feather-filter"
                   class="filter-toggle-custom" 
                   aria-expanded="false">
        @if($label)
            <span>{{ $label }}</span>
        @endif
    </x-ui.icon-btn>
    <div class="dropdown-menu dropdown-menu-end theme-filter-dropdown-menu shadow-lg">
        {{ $slot }}
    </div>
</div>

@once
    @push('scripts')
        <script>
            $(document).ready(function() {
                // Custom toggle handler to prevent Bootstrap double-event conflict on filters
                $(document).on('click', '.filter-toggle-custom', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    var parent = $(this).closest('.erp-filter-dropdown');
                    var menu = parent.find('.dropdown-menu');
                    
                    // Close other open dropdowns
                    $('.dropdown-menu.show').not(menu).removeClass('show');
                    $('.dropdown.show').not(parent).removeClass('show');
                    
                    parent.toggleClass('show');
                    menu.toggleClass('show');
                });
                
                // Close dropdown when clicking outside (respecting auto-close outside behavior)
                $(document).on('click', function(e) {
                    if (!$(e.target).closest('.erp-filter-dropdown').length) {
                        $('.erp-filter-dropdown .dropdown-menu.show').removeClass('show');
                        $('.erp-filter-dropdown.show').removeClass('show');
                    }
                });
            });
        </script>
    @endpush
@endonce
