@props([
    'label' => null,
    'col' => 'col-6 col-md-3 col-lg-2',
])

<div {{ $attributes->class([$col]) }}>
    @if($label)
        <label class="form-label fw-semibold fs-12 text-uppercase text-muted mb-1">{{ $label }}</label>
    @endif
    {{ $slot }}
</div>
