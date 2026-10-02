{{--
    Segmented control (Today | MTD | QTD | YTD).

    Links:  <x-ui.segmented :options="['today' => 'Today', 'mtd' => 'MTD']" active="mtd" :href="fn ($key) => request()->fullUrlWithQuery(['range' => $key])" />
    Radios (inside a form): <x-ui.segmented name="range" :options="[...]" active="mtd" />
--}}
@props([
    'options' => [],
    'active' => null,
    'name' => null,
    'href' => null,
])

@php($groupId = 'seg-'.\Illuminate\Support\Str::random(6))

<div {{ $attributes->class(['ax-segmented']) }} role="group">
    @foreach ($options as $key => $label)
        @php($isActive = (string) $key === (string) $active)
        @if ($name)
            <input type="radio" name="{{ $name }}" id="{{ $groupId }}-{{ $key }}" value="{{ $key }}" @checked($isActive)>
            <label for="{{ $groupId }}-{{ $key }}">{{ $label }}</label>
        @elseif ($href)
            <a href="{{ $href($key) }}" @class(['active' => $isActive]) @if ($isActive) aria-current="true" @endif>{{ $label }}</a>
        @else
            <button type="button" data-value="{{ $key }}" @class(['active' => $isActive])>{{ $label }}</button>
        @endif
    @endforeach
</div>
