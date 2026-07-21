@props([
    'icon' => 'feather-box',
    'image' => null,
    'color' => 'primary',
    'size' => 'md', // sm, md, lg, xl
    'shape' => 'rounded', // rounded, circle, square
])

@php
    $sizeClasses = [
        'sm' => 'avatar-sm fs-14',
        'md' => 'avatar-md fs-16',
        'lg' => 'avatar-lg fs-20',
        'xl' => 'avatar-xl fs-24',
    ];
    $sizeClass = $sizeClasses[$size] ?? 'avatar-md fs-16';

    $shapeClass = match($shape) {
        'circle' => 'rounded-circle',
        'square' => 'rounded-0',
        default => 'rounded-3',
    };
@endphp

<div {{ $attributes->merge(['class' => 'avatar-text ' . $sizeClass . ' ' . $shapeClass . ' bg-soft-' . $color . ' text-' . $color . ' d-inline-flex align-items-center justify-content-center flex-shrink-0 shadow-xs']) }}>
    @if ($image)
        <img src="{{ asset($image) }}" alt="Icon" class="img-fluid {{ $shapeClass }}">
    @else
        <i class="{{ $icon }}"></i>
    @endif
</div>
