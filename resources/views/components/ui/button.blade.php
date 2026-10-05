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

{{-- Styling comes from the global design system (public/assets/css/apex-ui.css). --}}

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
