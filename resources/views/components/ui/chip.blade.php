{{--
    Rounded status/trend chip: <x-ui.chip tone="negative">▼ 4.2%</x-ui.chip>
    tone: positive | negative | pending | info | brand | neutral
--}}
@props(['tone' => 'neutral'])

<span {{ $attributes->class(['ax-chip', 'ax-tone-'.$tone]) }}>{{ $slot }}</span>
