@props([
    'id',
    'tabs' => [] // array of ['id' => '', 'label' => '', 'active' => true/false, 'icon' => '']
])

@once
    @push('styles')
        <style>
            .erp-horizontal-tabs {
                border-bottom: 2px solid #e2e8f0;
                gap: 6px;
                overflow-x: auto;
                overflow-y: hidden;
                flex-wrap: nowrap;
                white-space: nowrap;
                -webkit-overflow-scrolling: touch;
                scrollbar-width: none; /* Firefox */
                -ms-overflow-style: none; /* IE and Edge */
                padding-bottom: 4px;
                padding-top: 2px;
            }
            .erp-horizontal-tabs::-webkit-scrollbar {
                display: none; /* Chrome, Safari, Opera */
                width: 0;
                height: 0;
            }
            .erp-horizontal-tabs .nav-item {
                margin-bottom: 0;
                flex-shrink: 0;
            }
            .erp-horizontal-tabs .nav-link {
                border: 1px solid transparent !important;
                background: transparent !important;
                color: #64748b !important;
                font-size: 13px;
                font-weight: 600;
                padding: 6px 14px;
                transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
                display: flex;
                align-items: center;
                border-radius: 6px !important;
                white-space: nowrap;
                flex-shrink: 0;
            }
            .erp-horizontal-tabs .nav-link i {
                font-size: 14px;
                transition: transform 0.2s ease, color 0.2s ease;
            }
            .erp-horizontal-tabs .nav-link:hover {
                color: var(--bs-primary) !important;
                background-color: color-mix(in srgb, var(--bs-primary) 8%, transparent) !important;
                border-color: color-mix(in srgb, var(--bs-primary) 15%, transparent) !important;
            }
            .erp-horizontal-tabs .nav-link:hover i {
                transform: translateY(-1px);
            }
            .erp-horizontal-tabs .nav-link.active {
                background-color: var(--bs-primary) !important;
                color: #ffffff !important;
                font-weight: 700;
                border-color: var(--bs-primary) !important;
                box-shadow: 0 2px 6px color-mix(in srgb, var(--bs-primary) 30%, transparent);
            }
            .erp-horizontal-tabs .nav-link.active i {
                color: #ffffff !important;
            }
        </style>
    @endpush
@endonce

<ul class="nav nav-tabs erp-horizontal-tabs" id="{{ $id }}" role="tablist" {{ $attributes }}>
    @foreach($tabs as $tab)
        <li class="nav-item" role="presentation">
            <button class="nav-link {{ ($tab['active'] ?? false) ? 'active' : '' }}" 
                    id="{{ $tab['id'] }}-tab" 
                    data-bs-toggle="tab" 
                    data-bs-target="#{{ $tab['id'] }}" 
                    type="button" 
                    role="tab" 
                    aria-controls="{{ $tab['id'] }}" 
                    aria-selected="{{ ($tab['active'] ?? false) ? 'true' : 'false' }}">
                @if(!empty($tab['icon']))
                    <i class="{{ $tab['icon'] }} me-2"></i>
                @endif
                {{ $tab['label'] }}
            </button>
        </li>
    @endforeach
</ul>
