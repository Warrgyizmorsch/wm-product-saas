@props([
    'href' => 'javascript:void(0);',
    'icon' => null,
    'active' => false,
])

<a href="{{ $href }}" {{ $attributes->class(['dropdown-item', 'active' => $active]) }}>
    @if($icon)
        <i class="{{ $icon }}"></i>
    @endif
    {{ $slot }}
</a>
