@php
    $checklistDone = collect($checklist)->where('ok', true)->count();
@endphp
<x-ui.card title="Month-End Close" class="mb-3" stretch>
    <x-slot:headerAction>
        <span class="badge {{ $checklistDone === count($checklist) ? 'bg-soft-success text-success' : 'bg-soft-warning text-warning' }}">{{ $checklistDone }}/{{ count($checklist) }} done</span>
    </x-slot:headerAction>
    @foreach ($checklist as $item)
        <a href="{{ route($item['route']) }}" class="d-flex align-items-start gap-2 py-2 {{ $loop->last ? '' : 'border-bottom' }} text-dark">
            <i class="{{ $item['ok'] ? 'feather-check-circle text-success' : 'feather-alert-circle text-danger' }} mt-1"></i>
            <span class="flex-grow-1">
                <span class="d-block fs-13 fw-semibold">{{ $item['label'] }}</span>
                <span class="d-block fs-12 text-muted">{{ $item['detail'] }}</span>
            </span>
            <i class="feather-chevron-right text-muted mt-1"></i>
        </a>
    @endforeach
</x-ui.card>
