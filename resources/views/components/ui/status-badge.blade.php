@props([
    'status' => 'draft',
    'label' => null,
    'dot' => true,
    'size' => 'md',
])

@php
    $statusMap = [
        'active' => ['bg' => 'bg-soft-success', 'text' => 'text-success', 'dot' => 'bg-success', 'default_label' => 'Active'],
        'in_progress' => ['bg' => 'bg-soft-primary', 'text' => 'text-primary', 'dot' => 'bg-primary', 'default_label' => 'In Progress'],
        'completed' => ['bg' => 'bg-soft-teal', 'text' => 'text-teal', 'dot' => 'bg-teal', 'default_label' => 'Completed'],
        'finished' => ['bg' => 'bg-soft-teal', 'text' => 'text-teal', 'dot' => 'bg-teal', 'default_label' => 'Finished'],
        'on_hold' => ['bg' => 'bg-soft-warning', 'text' => 'text-warning', 'dot' => 'bg-warning', 'default_label' => 'On Hold'],
        'delayed' => ['bg' => 'bg-soft-danger', 'text' => 'text-danger', 'dot' => 'bg-danger', 'default_label' => 'Delayed'],
        'blocked' => ['bg' => 'bg-soft-danger', 'text' => 'text-danger', 'dot' => 'bg-danger', 'default_label' => 'Blocked'],
        'draft' => ['bg' => 'bg-soft-secondary', 'text' => 'text-secondary', 'dot' => 'bg-secondary', 'default_label' => 'Draft'],
        'cancelled' => ['bg' => 'bg-soft-dark', 'text' => 'text-dark', 'dot' => 'bg-dark', 'default_label' => 'Cancelled'],
    ];

    $normalized = strtolower(str_replace(' ', '_', $status));
    $config = $statusMap[$normalized] ?? ['bg' => 'bg-soft-secondary', 'text' => 'text-secondary', 'dot' => 'bg-secondary', 'default_label' => ucfirst($status)];
    $displayLabel = $label ?? $config['default_label'];

    $paddingClass = match($size) {
        'sm' => 'px-2 py-1 fs-11',
        'lg' => 'px-3 py-2 fs-13',
        default => 'px-2.5 py-1.5 fs-12',
    };
@endphp

<span {{ $attributes->merge(['class' => 'badge ' . $config['bg'] . ' ' . $config['text'] . ' ' . $paddingClass . ' fw-bold d-inline-flex align-items-center gap-1.5']) }}>
    @if ($dot)
        <span class="rounded-circle d-inline-block {{ $config['dot'] }}" style="width: 6px; height: 6px;"></span>
    @endif
    <span>{{ __($displayLabel) }}</span>
</span>
