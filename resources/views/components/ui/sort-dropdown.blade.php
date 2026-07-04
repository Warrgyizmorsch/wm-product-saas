@props([
    'label' => 'Sort',
    'offset' => '0, 10'
])

@once
    @push('styles')
        <style>
            .erp-sort-dropdown {
                position: relative !important;
            }
            .erp-sort-dropdown .dropdown-menu {
                position: absolute !important;
                top: 100% !important;
                right: 0 !important;
                left: auto !important;
                transform: none !important;
                display: none !important;
                min-width: 220px !important;
                width: auto !important;
                padding: 8px !important;
                border: 1px solid #e2e8f0 !important;
                border-radius: 8px !important;
                box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05) !important;
                margin-top: 5px !important;
                z-index: 1050 !important;
                transition: none !important;
                background-color: #ffffff !important;
            }
            .erp-sort-dropdown .dropdown-menu.show {
                display: block !important;
                visibility: visible !important;
                opacity: 1 !important;
            }
            .erp-sort-dropdown .dropdown-item {
                display: flex !important;
                align-items: center !important;
                justify-content: space-between !important;
                padding: 8px 12px !important;
                font-size: 13px !important;
                color: #475569 !important;
                border-radius: 6px !important;
                transition: all 0.2s ease-in-out !important;
                background: transparent !important;
                border: none !important;
                width: 100% !important;
                text-align: left !important;
            }
            .erp-sort-dropdown .dropdown-item:hover {
                background-color: #f1f5f9 !important;
                color: #0f172a !important;
            }
            .erp-sort-dropdown .dropdown-item.active {
                background-color: rgba(var(--bs-primary-rgb), 0.08) !important;
                color: var(--bs-primary) !important;
                font-weight: 600 !important;
            }
        </style>
    @endpush
@endonce

<div class="dropdown erp-sort-dropdown" {{ $attributes }}>
    <x-ui.icon-btn type="button" 
                   variant="transparent-dark"
                   title="Sort"
                   size="md"
                   icon="feather-bar-chart"
                   class="sort-toggle-custom" 
                   aria-expanded="false">
        @if($label)
            <span>{{ $label }}</span>
        @endif
    </x-ui.icon-btn>
    <div class="dropdown-menu dropdown-menu-end shadow-lg">
        {{ $slot }}
    </div>
</div>

@once
    @push('scripts')
        <script>
            $(document).ready(function() {
                // Custom toggle handler to prevent Bootstrap double-event conflict on sort dropdowns
                $(document).on('click', '.sort-toggle-custom', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    var parent = $(this).closest('.erp-sort-dropdown');
                    var menu = parent.find('.dropdown-menu');
                    
                    // Close other open dropdowns
                    $('.dropdown-menu.show').not(menu).removeClass('show');
                    $('.dropdown.show').not(parent).removeClass('show');
                    
                    parent.toggleClass('show');
                    menu.toggleClass('show');
                });
                
                // Close dropdown when clicking outside
                $(document).on('click', function(e) {
                    if (!$(e.target).closest('.erp-sort-dropdown').length) {
                        $('.erp-sort-dropdown .dropdown-menu.show').removeClass('show');
                        $('.erp-sort-dropdown.show').removeClass('show');
                    }
                });
            });
        </script>
    @endpush
@endonce
