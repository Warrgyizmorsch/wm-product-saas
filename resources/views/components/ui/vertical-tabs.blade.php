@props([
    'id',
    'tabs' => [] // array of ['id' => '', 'label' => '', 'active' => true/false, 'icon' => '']
])

@once
    @push('styles')
        <style>
            .erp-vertical-tabs {
                border-right: none;
                gap: 6px;
            }
            .erp-vertical-tabs .nav-link {
                border: none !important;
                background: transparent !important;
                color: #64748b !important;
                font-size: 13px;
                font-weight: 600;
                padding: 10px 14px;
                border-radius: 8px !important;
                transition: all 0.25s ease-in-out;
                display: flex;
                align-items: center;
                text-align: left;
                width: 100%;
                border-left: 1px solid transparent !important;
            }
            .erp-vertical-tabs .nav-link i {
                font-size: 15px;
                transition: transform 0.25s ease;
            }
            .erp-vertical-tabs .nav-link:hover {
                color: var(--bs-primary) !important;
                background-color: rgba(var(--bs-primary-rgb), 0.04) !important;
            }
            .erp-vertical-tabs .nav-link:hover i {
                transform: scale(1.1);
            }
            .erp-vertical-tabs .nav-link.active {
                color: #ffffff !important;
                background-color: var(--bs-primary) !important;
                border-left: 1px solid rgba(255, 255, 255, 0.4) !important;
                font-weight: 700;
                box-shadow: 0 4px 12px rgba(var(--bs-primary-rgb), 0.4);
            }
            .erp-vertical-tabs .nav-link.active i {
                transform: scale(1.15);
                color: #ffffff !important;
            }
        </style>
    @endpush
@endonce

<div class="nav flex-column nav-pills erp-vertical-tabs" id="{{ $id }}" role="tablist" aria-orientation="vertical" {{ $attributes }}>
    @foreach($tabs as $tab)
        <button class="nav-link {{ ($tab['active'] ?? false) ? 'active' : '' }}" 
                id="{{ $tab['id'] }}-tab" 
                data-bs-toggle="pill" 
                data-bs-target="#{{ $tab['id'] }}" 
                type="button" 
                role="tab" 
                aria-controls="{{ $tab['id'] }}" 
                aria-selected="{{ ($tab['active'] ?? false) ? 'true' : 'false' }}">
            @if(!empty($tab['icon']))
                <i class="{{ $tab['icon'] }} me-2"></i>
            @endif
            <span>{{ $tab['label'] }}</span>
        </button>
    @endforeach
</div>
