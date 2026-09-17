@props([
    'icon' => 'feather-activity',
    'value' => '0',
    'label' => '',
    'color' => 'primary',
])

<div {{ $attributes->class(['d-flex align-items-center gap-2 px-3 py-2 bg-white border rounded-3']) }}>
    <span class="avatar-text avatar-sm bg-soft-{{ $color }} text-{{ $color }} rounded-circle d-flex align-items-center justify-content-center flex-shrink-0">
        <i class="{{ $icon }} fs-13"></i>
    </span>
    <div class="lh-1">
        <span class="fw-bold fs-14 text-dark d-block">{{ $value }}</span>
        <span class="fs-11 text-muted">{{ $label }}</span>
    </div>
</div>
