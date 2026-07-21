@props([
    'priority' => 'medium',
    'label' => null,
    'icon' => true,
])

@php
    $priorityMap = [
        'urgent' => ['bg' => 'bg-soft-danger', 'text' => 'text-danger', 'icon' => 'feather-alert-octagon', 'label' => 'Urgent'],
        'high' => ['bg' => 'bg-soft-warning', 'text' => 'text-warning', 'icon' => 'feather-arrow-up', 'label' => 'High'],
        'medium' => ['bg' => 'bg-soft-info', 'text' => 'text-info', 'icon' => 'feather-minus', 'label' => 'Medium'],
        'normal' => ['bg' => 'bg-soft-info', 'text' => 'text-info', 'icon' => 'feather-minus', 'label' => 'Normal'],
        'low' => ['bg' => 'bg-soft-secondary', 'text' => 'text-secondary', 'icon' => 'feather-arrow-down', 'label' => 'Low'],
    ];

    $normalized = strtolower(trim($priority));
    $config = $priorityMap[$normalized] ?? ['bg' => 'bg-soft-secondary', 'text' => 'text-secondary', 'icon' => 'feather-flag', 'label' => ucfirst($priority)];
    $displayLabel = $label ?? $config['label'];
@endphp

<span {{ $attributes->merge(['class' => 'badge ' . $config['bg'] . ' ' . $config['text'] . ' px-2 py-1 fs-11 fw-semibold d-inline-flex align-items-center gap-1']) }}>
    @if ($icon)
        <i class="{{ $config['icon'] }} fs-10"></i>
    @endif
    <span>{{ __($displayLabel) }}</span>
</span>
