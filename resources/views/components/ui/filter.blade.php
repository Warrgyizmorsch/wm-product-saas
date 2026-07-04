@props([
    'label' => 'Filter',
    'offset' => '0, 10'
])

@once
    @push('styles')
        <style>
            .erp-filter-dropdown {
                position: relative !important;
            }
            /* Override the theme's forced off-screen coordinates and width limits */
            .erp-filter-dropdown .dropdown-menu {
                position: absolute !important;
                top: 100% !important;
                right: 0 !important;
                left: auto !important;
                transform: none !important;
                display: none !important;
                min-width: 320px !important;
                width: auto !important;
                padding: 18px !important;
                border: 1px solid #e2e8f0 !important;
                border-radius: 8px !important;
                box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05) !important;
                margin-top: 5px !important;
                z-index: 1050 !important;
                transition: none !important;
                background-color: #ffffff !important;
            }
            .erp-filter-dropdown .dropdown-menu.show {
                display: block !important;
                visibility: visible !important;
                opacity: 1 !important;
            }
        </style>
    @endpush
@endonce

<div class="dropdown erp-filter-dropdown" {{ $attributes }}>
    <button type="button" 
            class="btn btn-sm btn-outline-primary dropdown-toggle d-inline-flex align-items-center gap-1" 
            data-bs-toggle="dropdown" 
            data-bs-auto-close="outside"
            aria-expanded="false">
        <i class="feather-filter"></i>
        <span>{{ $label }}</span>
    </button>
    <div class="dropdown-menu dropdown-menu-end p-3 shadow-lg">
        {{ $slot }}
    </div>
</div>
