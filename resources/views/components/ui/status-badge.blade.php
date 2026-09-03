@props([
    'status' => 'draft',
    'label' => null,
    'dot' => false,
    'size' => 'md',
])

@php
    $statusMap = [
        'active' => ['bg' => 'bg-soft-success', 'text' => 'text-success', 'dot' => 'bg-success', 'default_label' => 'Active'],
        'inactive' => ['bg' => 'bg-soft-danger', 'text' => 'text-danger', 'dot' => 'bg-danger', 'default_label' => 'Inactive'],
        'available' => ['bg' => 'bg-soft-success', 'text' => 'text-success', 'dot' => 'bg-success', 'default_label' => 'Available'],
        'sold' => ['bg' => 'bg-soft-danger', 'text' => 'text-danger', 'dot' => 'bg-danger', 'default_label' => 'Sold'],
        'reserved' => ['bg' => 'bg-soft-warning', 'text' => 'text-warning', 'dot' => 'bg-warning', 'default_label' => 'Reserved'],
        'returned' => ['bg' => 'bg-soft-info', 'text' => 'text-info', 'dot' => 'bg-info', 'default_label' => 'Returned'],
        'damaged' => ['bg' => 'bg-soft-dark', 'text' => 'text-dark', 'dot' => 'bg-dark', 'default_label' => 'Damaged'],
        'in_progress' => ['bg' => 'bg-soft-primary', 'text' => 'text-primary', 'dot' => 'bg-primary', 'default_label' => 'In Progress'],
        'in_transit' => ['bg' => 'bg-soft-info', 'text' => 'text-info', 'dot' => 'bg-info', 'default_label' => 'In Transit'],
        'sent' => ['bg' => 'bg-soft-info', 'text' => 'text-info', 'dot' => 'bg-info', 'default_label' => 'Sent'],
        'received' => ['bg' => 'bg-soft-warning', 'text' => 'text-warning', 'dot' => 'bg-warning', 'default_label' => 'Received'],
        'confirmed' => ['bg' => 'bg-soft-success', 'text' => 'text-success', 'dot' => 'bg-success', 'default_label' => 'Confirmed'],
        'approved' => ['bg' => 'bg-soft-success', 'text' => 'text-success', 'dot' => 'bg-success', 'default_label' => 'Approved'],
        'completed' => ['bg' => 'bg-soft-teal', 'text' => 'text-teal', 'dot' => 'bg-teal', 'default_label' => 'Completed'],
        'finished' => ['bg' => 'bg-soft-teal', 'text' => 'text-teal', 'dot' => 'bg-teal', 'default_label' => 'Finished'],
        'on_hold' => ['bg' => 'bg-soft-warning', 'text' => 'text-warning', 'dot' => 'bg-warning', 'default_label' => 'On Hold'],
        'delayed' => ['bg' => 'bg-soft-danger', 'text' => 'text-danger', 'dot' => 'bg-danger', 'default_label' => 'Delayed'],
        'blocked' => ['bg' => 'bg-soft-danger', 'text' => 'text-danger', 'dot' => 'bg-danger', 'default_label' => 'Blocked'],
        'draft' => ['bg' => 'bg-soft-secondary', 'text' => 'text-secondary', 'dot' => 'bg-secondary', 'default_label' => 'Draft'],
        'cancelled' => ['bg' => 'bg-soft-dark', 'text' => 'text-dark', 'dot' => 'bg-dark', 'default_label' => 'Cancelled'],
        'out_of_stock' => ['bg' => 'bg-soft-danger', 'text' => 'text-danger', 'dot' => 'bg-danger', 'default_label' => 'Out of Stock'],
        'low_stock' => ['bg' => 'bg-soft-warning', 'text' => 'text-warning', 'dot' => 'bg-warning', 'default_label' => 'Low Stock Alert'],
    ];

    $normalized = strtolower(str_replace(' ', '_', $status));
    $config = $statusMap[$normalized] ?? ['bg' => 'bg-soft-secondary', 'text' => 'text-secondary', 'dot' => 'bg-secondary', 'default_label' => ucfirst($status)];
    $displayLabel = $label ?? $config['default_label'];

    $sizeClass = match($size) {
        'sm' => 'erp-badge--sm',
        'lg' => 'erp-badge--lg',
        default => '',
    };
@endphp

<span {{ $attributes->merge(['class' => 'badge erp-badge ' . $config['bg'] . ' ' . $config['text'] . ' ' . $sizeClass]) }}>
    @if ($dot)
        <span class="rounded-circle d-inline-block {{ $config['dot'] }}" style="width: 6px; height: 6px;"></span>
    @endif
    <span>{{ __($displayLabel) }}</span>
</span>
