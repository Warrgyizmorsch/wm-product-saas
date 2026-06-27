@props([
    'variant'   => 'secondary', // primary | secondary | success | danger | warning | info | soft-primary | soft-success | soft-danger | soft-warning | soft-info | light-brand
    'size'      => 'sm',        // sm | md | lg | null
    'icon'      => null,        // feather class e.g. 'feather-edit'
    'href'      => null,        // renders as <a> when provided
    'title'     => null,        // tooltip text
    'type'      => 'button',
    'target'    => null,
])

@php
    $sizeClass = match($size) {
        'lg'    => 'btn-lg',
        'md'    => '',
        default => 'btn-sm',
    };

    // Map variant to CSS class
    $variantClass = match($variant) {
        'soft-primary'  => 'erp-icon-btn erp-icon-btn--primary',
        'soft-success'  => 'erp-icon-btn erp-icon-btn--success',
        'soft-danger'   => 'erp-icon-btn erp-icon-btn--danger',
        'soft-warning'  => 'erp-icon-btn erp-icon-btn--warning',
        'soft-info'     => 'erp-icon-btn erp-icon-btn--info',
        'light-brand'   => 'erp-icon-btn erp-icon-btn--light',
        default         => 'erp-icon-btn erp-icon-btn--' . $variant,
    };

    $classes = trim("btn erp-icon-btn $variantClass $sizeClass");
    $extras  = $title ? 'title="' . e($title) . '" data-bs-toggle="tooltip"' : '';
@endphp

@if($href)
    <a href="{{ $href }}"
       {{ $attributes->class([$classes]) }}
       @if($target) target="{{ $target }}" @endif
       {!! $extras !!}>
        @if($icon)
            <i class="{{ $icon }}"></i>
        @endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}"
            {{ $attributes->class([$classes]) }}
            {!! $extras !!}>
        @if($icon)
            <i class="{{ $icon }}"></i>
        @endif
        {{ $slot }}
    </button>
@endif
