@props([
    'title' => '',
    'icon' => 'feather-info',
    'color' => 'primary',
])

<div {{ $attributes->class(['border rounded-3 p-4 bg-white h-100']) }}>
    <div class="d-flex align-items-center gap-2 mb-4">
        <x-ui.icon-tile :icon="$icon" :color="$color" size="sm" />
        <h6 class="fw-bold text-dark mb-0">{{ $title }}</h6>
    </div>
    {{ $slot }}
</div>
