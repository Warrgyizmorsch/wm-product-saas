@props([
    'variant' => 'primary',
    'soft' => false
])

@php
    if ($soft) {
        $classes = 'badge bg-soft-' . $variant . ' text-' . $variant;
    } else {
        $classes = 'badge bg-' . $variant;
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
