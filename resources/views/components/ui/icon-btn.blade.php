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
                background-color: #ffffff !important;
                border: 1px solid #cbd5e1 !important;
                color: #0f172a !important;
                border-radius: 8px !important;
                transition: all 0.2s ease-in-out;
                font-weight: 500 !important;
                font-size: 13px !important;
                height: 36px !important;
                display: inline-flex !important;
                align-items: center !important;
                gap: 8px !important;
            }
            .erp-icon-btn--transparent-dark:hover,
            .erp-icon-btn--transparent-dark:focus,
            .erp-icon-btn--transparent-dark:active {
                background-color: #f1f5f9 !important;
                border-color: #94a3b8 !important;
                color: #0f172a !important;
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
