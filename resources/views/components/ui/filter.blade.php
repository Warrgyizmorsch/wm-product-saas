@props([
    'label' => 'Filter',
    'offset' => '0, 10'
])

@once
    @push('styles')
        <style>
            .erp-filter-dropdown .dropdown-toggle {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 6px 12px;
                border: 1px solid #ced4da;
                border-radius: 6px;
                background-color: #ffffff;
                color: #495057;
                font-size: 13px;
                font-weight: 600;
                transition: all 0.2s ease-in-out;
                text-decoration: none;
            }
            .erp-filter-dropdown .dropdown-toggle:hover,
            .erp-filter-dropdown .dropdown-toggle[aria-expanded="true"] {
                border-color: var(--bs-primary);
                color: var(--bs-primary);
                background-color: rgba(var(--bs-primary-rgb), 0.04);
            }
            .erp-filter-dropdown .dropdown-menu {
                min-width: 280px;
                padding: 16px;
                border: 1px solid #e2e8f0;
                border-radius: 8px;
                box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
                margin-top: 5px !important;
            }
        </style>
    @endpush
@endonce

<div class="dropdown erp-filter-dropdown" {{ $attributes }}>
    <a href="javascript:void(0);" 
       class="dropdown-toggle" 
       data-bs-toggle="dropdown" 
       data-bs-offset="{{ $offset }}" 
       aria-expanded="false">
        <i class="feather-filter"></i>
        <span>{{ $label }}</span>
    </a>
    <div class="dropdown-menu dropdown-menu-start">
        {{ $slot }}
    </div>
</div>
