@props([
    'viewUrl' => null,
    'viewIcon' => 'feather-eye',
    'offset' => '0,21',
    'id' => null
])

@php
    $dropdownId = $id ?? 'dropdown_' . uniqid();
@endphp

<div {{ $attributes->class(['hstack gap-2 justify-content-end']) }}>
    @if($viewUrl)
        <a href="{{ $viewUrl }}" class="avatar-text avatar-md">
            <i class="feather {{ $viewIcon }}"></i>
        </a>
    @endif

    <div class="dropdown" id="{{ $dropdownId }}">
        <a href="javascript:void(0)" class="avatar-text avatar-md dropdown-toggle-custom" data-offset="{{ $offset }}">
            <i class="feather feather-more-horizontal"></i>
        </a>
        <ul class="dropdown-menu dropdown-menu-end" style="margin: 0;">
            {{ $slot }}
        </ul>
    </div>
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
