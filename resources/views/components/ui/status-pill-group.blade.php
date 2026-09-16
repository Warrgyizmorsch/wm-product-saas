@props([
    'items' => [], // [['label' => 'Present', 'value' => 32, 'variant' => 'success'], ...]
])

<div {{ $attributes->class(['d-flex flex-wrap gap-2']) }}>
    @foreach($items as $item)
        <span class="badge erp-badge bg-soft-{{ $item['variant'] ?? 'secondary' }} text-{{ $item['variant'] ?? 'secondary' }} fw-semibold fs-11 px-2 py-1">
            {{ $item['label'] }} {{ $item['value'] }}
        </span>
    @endforeach
</div>
