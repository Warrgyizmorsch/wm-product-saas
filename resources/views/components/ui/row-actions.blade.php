@props([
    'viewUrl' => null,
    'editUrl' => null,
    'deleteUrl' => null,
    'deleteConfirm' => 'Are you sure you want to delete this record?',
])

@once
    @push('styles')
        <style>
            .erp-row-action-btn {
                display: inline-flex !important;
                align-items: center !important;
                justify-content: center !important;
                width: 32px !important;
                height: 32px !important;
                border-radius: 8px !important;
                border: 1.5px solid #cbd5e1 !important;
                background-color: #ffffff !important;
                color: #475569 !important;
                transition: all 0.2s ease !important;
                text-decoration: none !important;
                cursor: pointer !important;
            }
            .erp-row-action-btn:hover {
                background-color: color-mix(in srgb, var(--bs-primary) 12%, transparent) !important;
                border-color: var(--bs-primary) !important;
                color: var(--bs-primary) !important;
            }
            .erp-row-action-btn--danger:hover {
                background-color: color-mix(in srgb, var(--bs-danger) 12%, transparent) !important;
                border-color: var(--bs-danger) !important;
                color: var(--bs-danger) !important;
            }
        </style>
    @endpush
@endonce

<div {{ $attributes->class(['hstack gap-2 justify-content-center']) }}>
    @if($viewUrl)
        <a href="{{ $viewUrl }}" class="erp-row-action-btn" title="View" data-bs-toggle="tooltip">
            <i class="feather feather-eye"></i>
        </a>
    @endif
    @if($editUrl)
        <a href="{{ $editUrl }}" class="erp-row-action-btn" title="Edit" data-bs-toggle="tooltip">
            <i class="feather feather-edit-2"></i>
        </a>
    @endif
    @if($deleteUrl)
        <form action="{{ $deleteUrl }}" method="POST" class="d-inline" onsubmit="return confirm('{{ $deleteConfirm }}');">
            @csrf
            @method('DELETE')
            <button type="submit" class="erp-row-action-btn erp-row-action-btn--danger border-0" title="Delete" data-bs-toggle="tooltip">
                <i class="feather feather-trash-2"></i>
            </button>
        </form>
    @endif
    {{ $slot ?? '' }}
</div>
