@props([
    'variant' => 'primary',
    'size' => null,
    'type' => 'button',
    'href' => null,
    'icon' => null,
    'iconPosition' => 'left'
])

@php
    $classes = 'btn';
    if ($variant) {
        $classes .= ' btn-' . $variant;
    }
    if ($size) {
        $classes .= ' btn-' . $size;
    }
@endphp

@if($href)
    <a href="{{ $href }}" {{ $attributes->class([$classes]) }}>
        @if($icon && $iconPosition === 'left')
            <i class="{{ $icon }} me-2"></i>
        @endif
        {{ $slot }}
        @if($icon && $iconPosition === 'right')
            <i class="{{ $icon }} ms-2"></i>
        @endif
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->class([$classes]) }}>
        @if($icon && $iconPosition === 'left')
            <i class="{{ $icon }} me-2"></i>
        @endif
        {{ $slot }}
        @if($icon && $iconPosition === 'right')
            <i class="{{ $icon }} ms-2"></i>
        @endif
    </button>
@endif
