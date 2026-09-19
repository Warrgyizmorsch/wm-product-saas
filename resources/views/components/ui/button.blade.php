@props([
    'variant' => 'primary',
    'size' => null,
    'type' => 'button',
    'href' => null,
    'icon' => null,
    'iconPosition' => 'left',
    'badge' => null,
    'badgeClass' => null
])

@php
    $classes = 'btn btn-animated';
    if ($variant) {
        $classes .= ' btn-' . $variant;
    }
    if ($size) {
        $classes .= ' btn-' . $size;
    }
    if ($badge !== null && $badge !== '') {
        $classes .= ' btn-badge-container';
    }
@endphp

@once
    @push('styles')
        <style>
            .btn-animated {
                position: relative;
                overflow: hidden;
                transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1) !important;
                will-change: transform, box-shadow;
            }
            /* Comfortable padding scoped to the component (overrides the global
               .btn padding without touching raw buttons or icon-btns). */
            .btn.btn-animated {
                padding: 8px 12px !important;
                letter-spacing: 0.01em;
            }
            .btn.btn-animated.btn-sm {
                padding: 5px 12px !important;
            }
            .btn.btn-animated.btn-lg {
                padding: 11px 22px !important;
            }
            .btn-animated::before {
                content: '';
                position: absolute;
                top: 0;
                left: -100%;
                width: 100%;
                height: 100%;
                background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.25), transparent);
                transition: left 0.6s ease;
                pointer-events: none;
                z-index: 1;
            }
            .btn-animated:hover::before {
                left: 100%;
            }
            .btn-animated:hover {
                transform: translateY(-2px);
            }
            .btn-animated:active {
                transform: translateY(0) scale(0.98);
            }
            .btn-animated i {
                display: inline-block;
                transition: transform 0.25s ease;
            }
            .btn-animated:hover i.me-2 {
                transform: translateX(-2px);
            }
            .btn-animated:hover i.ms-2 {
                transform: translateX(2px);
            }

            /* Elevation hover shadows per variant */
            .btn-animated.btn-primary:hover {
                box-shadow: 0 6px 16px color-mix(in srgb, var(--bs-primary) 38%, transparent) !important;
            }
            .btn-animated.btn-secondary:hover {
                box-shadow: 0 6px 16px rgba(108, 117, 125, 0.3) !important;
            }
            .btn-animated.btn-success:hover {
                box-shadow: 0 6px 16px rgba(25, 135, 84, 0.35) !important;
            }
            .btn-animated.btn-danger:hover {
                box-shadow: 0 6px 16px rgba(220, 53, 69, 0.35) !important;
            }
            .btn-animated.btn-warning:hover {
                box-shadow: 0 6px 16px rgba(255, 193, 7, 0.35) !important;
            }
            .btn-animated.btn-info:hover {
                box-shadow: 0 6px 16px rgba(13, 202, 240, 0.35) !important;
            }
            .btn-animated.btn-dark:hover {
                box-shadow: 0 6px 16px rgba(33, 37, 41, 0.35) !important;
            }
            .btn-animated.btn-light:hover,
            .btn-animated.btn-outline-primary:hover,
            .btn-animated.btn-outline-secondary:hover,
            .btn-animated.btn-outline-success:hover,
            .btn-animated.btn-outline-danger:hover,
            .btn-animated.btn-outline-dark:hover {
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12) !important;
            }

            /* Dark Mode Support */
            html.app-skin-dark .btn.btn-animated.btn-light {
                background-color: #1c2438 !important;
                border-color: #283c50 !important;
                color: #ffffff !important;
            }
            html.app-skin-dark .btn.btn-animated.btn-light:hover {
                background-color: #283c50 !important;
                border-color: #334155 !important;
                color: #ffffff !important;
            }
            html.app-skin-dark .btn.btn-animated.btn-outline-light {
                border-color: #334155 !important;
                color: #cbd5e1 !important;
            }
            html.app-skin-dark .btn.btn-animated.btn-outline-light:hover {
                background-color: #1c2438 !important;
                color: #ffffff !important;
            }
            html.app-skin-dark .btn.btn-animated.btn-outline-secondary {
                border-color: #475569 !important;
                color: #cbd5e1 !important;
            }
            html.app-skin-dark .btn.btn-animated.btn-outline-secondary:hover {
                background-color: #283c50 !important;
                color: #ffffff !important;
            }
            html.app-skin-dark .btn.btn-animated.btn-outline-dark {
                border-color: #64748b !important;
                color: #f1f5f9 !important;
            }
            html.app-skin-dark .btn.btn-animated.btn-outline-dark:hover {
                background-color: #334155 !important;
                color: #ffffff !important;
            }
        </style>
    @endpush
@endonce

@if($href)
    <a href="{{ $href }}" {{ $attributes->class([$classes]) }}>
        @if($icon && $iconPosition === 'left')
            <i class="{{ $icon }}{{ trim((string)$slot) ? ' me-2' : '' }}"></i>
        @endif
        {{ $slot }}
        @if($icon && $iconPosition === 'right')
            <i class="{{ $icon }}{{ trim((string)$slot) ? ' ms-2' : '' }}"></i>
        @endif
        @if($badge !== null && $badge !== '')
            <span class="btn-badge-count {{ $badgeClass }}">{{ $badge }}</span>
        @endif
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->class([$classes]) }}>
        @if($icon && $iconPosition === 'left')
            <i class="{{ $icon }}{{ trim((string)$slot) ? ' me-2' : '' }}"></i>
        @endif
        {{ $slot }}
        @if($icon && $iconPosition === 'right')
            <i class="{{ $icon }}{{ trim((string)$slot) ? ' ms-2' : '' }}"></i>
        @endif
        @if($badge !== null && $badge !== '')
            <span class="btn-badge-count {{ $badgeClass }}">{{ $badge }}</span>
        @endif
    </button>
@endif
