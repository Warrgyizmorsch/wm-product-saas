@props([
    'variant'   => 'secondary', // primary | secondary | success | danger | warning | info | soft-primary | soft-success | soft-danger | soft-warning | soft-info | light-brand | transparent-dark
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
        'transparent-dark' => 'erp-icon-btn erp-icon-btn--transparent-dark',
        default         => 'erp-icon-btn erp-icon-btn--' . $variant,
    };

    $isLabeled = isset($slot) && trim($slot) !== '';
    $labeledClass = $isLabeled ? 'erp-icon-btn--labeled' : '';
    $classes = trim("btn erp-icon-btn $variantClass $sizeClass $labeledClass");
    $extras  = $title ? 'title="' . e($title) . '" data-bs-toggle="tooltip"' : '';
@endphp

@once
    @push('styles')
        <style>
            .erp-icon-btn--transparent-dark {
                background-color: transparent !important;
                border: 1px solid #212529 !important;
                color: #212529 !important;
                transition: all 0.2s ease-in-out;
            }
            .erp-icon-btn--transparent-dark:hover,
            .erp-icon-btn--transparent-dark:focus,
            .erp-icon-btn--transparent-dark:active {
                background-color: color-mix(in srgb, var(--bs-primary) 10%, transparent) !important;
                border-color: var(--bs-primary) !important;
                color: var(--bs-primary) !important;
            }
        </style>
    @endpush
@endonce

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
