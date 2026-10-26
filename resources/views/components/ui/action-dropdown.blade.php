@props([
    'viewUrl' => null,
    'viewIcon' => 'feather-eye',
    'offset' => '0,21',
    'id' => null,
    'align' => 'center'
])

@php
    $dropdownId = $id ?? 'dropdown_' . uniqid();
@endphp

@once
    @push('styles')
        <style>
            /* Ensure table-responsive never resets horizontal scroll position when dropdown opens */
            .table-responsive,
            .table-responsive:has(.dropdown.show),
            .table-responsive:has(.action-dropdown-wrapper.show) {
                overflow-x: auto !important;
            }

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
                flex-shrink: 0 !important;
            }

            .action-dropdown-btn:hover,
            .action-dropdown-btn.active {
                background-color: color-mix(in srgb, var(--bs-primary) 12%, transparent) !important;
                border-color: var(--bs-primary) !important;
                color: var(--bs-primary) !important;
            }

            .action-dropdown-wrapper {
                position: relative !important;
                display: inline-block !important;
                flex-shrink: 0 !important;
            }

            /* Fixed positioning so the menu floats directly in the viewport, never clipped by table-responsive and never resetting scrollLeft */
            .action-dropdown-wrapper .dropdown-menu {
                position: fixed !important;
                margin: 0 !important;
                transform: none !important;
                z-index: 1070 !important;
                box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.15), 0 8px 10px -6px rgba(0, 0, 0, 0.1) !important;
            }

            /* Dark Mode Support */
            html.app-skin-dark .action-dropdown-btn {
                border-color: #283c50 !important;
                background-color: #1c2438 !important;
                color: #cbd5e1 !important;
            }

            html.app-skin-dark .action-dropdown-btn:hover,
            html.app-skin-dark .action-dropdown-btn.active {
                background-color: color-mix(in srgb, var(--bs-primary) 25%, #1c2438) !important;
                border-color: var(--bs-primary) !important;
                color: #ffffff !important;
            }
        </style>
    @endpush
@endonce

<div {{ $attributes->class(['hstack gap-2', 'justify-content-' . ($align ?? 'center')]) }}>
    @if($viewUrl)
        <a href="{{ $viewUrl }}" class="action-dropdown-btn" title="View Details" data-bs-toggle="tooltip">
            <i class="feather {{ $viewIcon }}"></i>
        </a>
    @endif

    @if(isset($extraActions))
        {{ $extraActions }}
    @endif

    @if(isset($slot) && trim($slot) !== '')
        <div class="dropdown action-dropdown-wrapper" id="{{ $dropdownId }}">
            <a href="javascript:void(0)" class="action-dropdown-btn dropdown-toggle-custom" data-offset="{{ $offset }}"
                title="More Actions">
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
            $(document).ready(function () {
                // Custom toggle handler: calculates fixed coordinates to preserve table-responsive horizontal scroll
                $(document).on('click', '.dropdown-toggle-custom', function (e) {
                    e.preventDefault();
                    e.stopPropagation();

                    var btn = $(this);
                    var parent = btn.closest('.dropdown');
                    var menu = parent.find('.dropdown-menu');

                    var willShow = !menu.hasClass('show');

                    // Close any other open dropdowns
                    $('.dropdown-menu.show').not(menu).removeClass('show');
                    $('.dropdown.show').not(parent).removeClass('show');

                    if (willShow) {
                        // Temporarily measure dimensions
                        menu.css({
                            'display': 'block',
                            'visibility': 'hidden',
                            'position': 'fixed'
                        });

                        var menuWidth = menu.outerWidth() || 185;
                        var menuHeight = menu.outerHeight() || 150;
                        var rect = btn[0].getBoundingClientRect();
                        var winWidth = $(window).width();
                        var winHeight = $(window).height();

                        // Vertical positioning (open downwards by default, flip upwards if near screen bottom)
                        var top = rect.bottom + 4;
                        if (rect.bottom + menuHeight > winHeight && rect.top > menuHeight) {
                            top = rect.top - menuHeight - 4;
                        }

                        // Horizontal positioning (align right edge of menu to right edge of button)
                        var left = rect.right - menuWidth;
                        if (left < 10) {
                            left = rect.left;
                        }
                        if (left + menuWidth > winWidth - 10) {
                            left = winWidth - menuWidth - 10;
                        }

                        menu.css({
                            'position': 'fixed',
                            'top': top + 'px',
                            'left': left + 'px',
                            'right': 'auto',
                            'bottom': 'auto',
                            'display': '',
                            'visibility': '',
                            'z-index': '1070'
                        });

                        parent.addClass('show');
                        menu.addClass('show');
                    } else {
                        parent.removeClass('show');
                        menu.removeClass('show');
                    }
                });

                // Close dropdown when clicking an item
                $(document).on('click', '.action-dropdown-wrapper .dropdown-item', function () {
                    $('.dropdown-menu.show').removeClass('show');
                    $('.dropdown.show').removeClass('show');
                });

                // Close dropdown when clicking outside
                $(document).on('click', function (e) {
                    if (!$(e.target).closest('.dropdown-menu').length && !$(e.target).closest('.dropdown-toggle-custom').length) {
                        $('.dropdown-menu.show').removeClass('show');
                        $('.dropdown.show').removeClass('show');
                    }
                });

                // Close dropdown when scrolling table or window so it doesn't float disconnected
                window.addEventListener('scroll', function (e) {
                    if (e.target && $(e.target).closest('.dropdown-menu').length) {
                        return;
                    }
                    if ($('.dropdown-menu.show').length) {
                        $('.dropdown-menu.show').removeClass('show');
                        $('.dropdown.show').removeClass('show');
                    }
                }, true);
            });
        </script>
    @endpush
@endonce