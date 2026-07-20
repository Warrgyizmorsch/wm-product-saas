@props([
    'id',
    'tabs' => [] // array of ['id' => '', 'label' => '', 'active' => true/false, 'icon' => '']
])

@once
    @push('styles')
        <style>
            .erp-horizontal-tabs {
                border-bottom: 2px solid #e2e8f0;
                gap: 8px;
                overflow-x: auto;
                overflow-y: hidden;
                flex-wrap: nowrap;
                white-space: nowrap;
                -webkit-overflow-scrolling: touch;
                scrollbar-width: none; /* Firefox */
                -ms-overflow-style: none; /* IE and Edge */
            }
            .erp-horizontal-tabs::-webkit-scrollbar {
                display: none; /* Chrome, Safari, Opera */
                width: 0;
                height: 0;
            }
            .erp-horizontal-tabs .nav-item {
                margin-bottom: -2px;
                flex-shrink: 0;
            }
            .erp-horizontal-tabs .nav-link {
                border: none !important;
                border-bottom: 3px solid transparent !important;
                background: transparent !important;
                color: #64748b !important;
                font-size: 13px;
                font-weight: 600;
                padding: 10px 16px;
                transition: all 0.25s ease;
                display: flex;
                align-items: center;
                border-top-left-radius: 4px;
                border-top-right-radius: 4px;
                white-space: nowrap;
                flex-shrink: 0;
            }
            .erp-horizontal-tabs .nav-link i {
                font-size: 14px;
                transition: transform 0.2s ease;
            }
            .erp-horizontal-tabs .nav-link:hover {
                color: var(--bs-primary) !important;
                border-bottom-color: #cbd5e1 !important;
            }
            .erp-horizontal-tabs .nav-link:hover i {
                transform: translateY(-1px);
            }
            .erp-horizontal-tabs .nav-link.active {
                color: var(--bs-primary) !important;
                border-bottom-color: var(--bs-primary) !important;
                font-weight: 700;
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
