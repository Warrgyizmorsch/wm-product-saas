@props([
    'viewUrl' => null,
    'viewIcon' => 'feather-eye',
    'offset' => '0,21',
    'id' => null
])

@php
    $dropdownId = $id ?? 'dropdown_' . uniqid();
@endphp

@once
    @push('styles')
        <style>
            .action-dropdown-btn {
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
                width: 32px !important;
                height: 32px !important;
                border-radius: 8px !important;
                border: 1.5px solid #cbd5e1 !important;
                background-color: #ffffff !important;
                color: #475569 !important;
                transition: all 0.28s ease !important;
                text-decoration: none !important;
                cursor: pointer !important;
            }
            .action-dropdown-btn:hover {
                background-color: color-mix(in srgb, var(--bs-primary) 10%, transparent) !important;
                border-color: var(--bs-primary) !important;
                color: var(--bs-primary) !important;
            }
            /* Explicitly align dropdown menu to the right edge and open leftwards */
            .dropdown {
                position: relative !important;
            }
            .dropdown-menu-end {
                right: 0 !important;
                left: auto !important;
                transform: none !important;
            }
        </style>
    @endpush
@endonce

<div {{ $attributes->class(['hstack gap-2 justify-content-end']) }}>
    @if($viewUrl)
        <a href="{{ $viewUrl }}" class="action-dropdown-btn" title="View Details" data-bs-toggle="tooltip">
            <i class="feather {{ $viewIcon }}"></i>
        </a>
    @endif

    @if(isset($slot) && trim($slot) !== '')
        <div class="dropdown" id="{{ $dropdownId }}">
            <a href="javascript:void(0)" class="action-dropdown-btn dropdown-toggle-custom" data-offset="{{ $offset }}" title="More Actions" data-bs-toggle="tooltip">
                <i class="feather feather-more-horizontal"></i>
            </a>
            <ul class="dropdown-menu dropdown-menu-end" style="margin: 0;">
                {{ $slot }}
            </ul>
        </div>
    @endif
</div>

@once
    @push('scripts')
        <script>
            $(document).ready(function() {
                // Custom toggle handler to prevent Bootstrap double-event conflict
                $(document).on('click', '.dropdown-toggle-custom', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    var parent = $(this).closest('.dropdown');
                    var menu = parent.find('.dropdown-menu');
                    
                    // Close other open dropdowns
                    $('.dropdown-menu.show').not(menu).removeClass('show');
                    $('.dropdown.show').not(parent).removeClass('show');
                    
                    parent.toggleClass('show');
                    menu.toggleClass('show');
                });
                
                // Close dropdown when clicking outside
                $(document).on('click', function(e) {
                    if (!$(e.target).closest('.dropdown').length) {
                        $('.dropdown-menu.show').removeClass('show');
                        $('.dropdown.show').removeClass('show');
                    }
                });
            });
        </script>
    @endpush
@endonce
