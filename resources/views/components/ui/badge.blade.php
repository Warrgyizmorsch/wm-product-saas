@props([
    'variant' => 'primary',
    'soft' => false
])

@php
    if ($soft) {
        $classes = 'badge erp-badge bg-soft-' . $variant . ' text-' . $variant;
    } else {
        $classes = 'badge erp-badge bg-' . $variant;
        if ($variant === 'warning' || $variant === 'light') {
            $classes .= ' text-dark';
        } else {
            $classes .= ' text-white';
        }
    }
@endphp

<span {{ $attributes->class([$classes]) }}>
    {{ $slot }}
</span>
