@props([
    'variant' => 'primary',
    'dismissible' => false,
    'icon' => null
])

<div {{ $attributes->class([
    'alert',
    'alert-' . $variant,
    'alert-dismissible fade show' => $dismissible,
    'd-flex align-items-center' => $icon
]) }} role="alert">
    @if($icon)
        <i class="{{ $icon }} me-3 fs-5"></i>
    @endif
    <div>
        {{ $slot }}
    </div>
    @if($dismissible)
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    @endif
</div>
