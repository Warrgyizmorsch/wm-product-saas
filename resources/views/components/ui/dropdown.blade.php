@props([
    'menuClass' => null,
    'menuId' => null,
])

<div {{ $attributes->class(['dropdown']) }}>
    @isset($trigger)
        {{ $trigger }}
    @endisset

    <div @if($menuId) id="{{ $menuId }}" @endif class="{{ trim('dropdown-menu '.$menuClass) }}">
        {{ $slot }}
    </div>
</div>
