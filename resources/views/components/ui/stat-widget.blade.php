{{-- Older KPI API kept for existing screens; renders the design-system KPI tile (x-ui.kpi-card). --}}
@props([
    'title' => '',
    'value' => '0',
    'subtitle' => null,
    'trend' => null,
    'trendDirection' => 'up', // up, down, neutral
    'icon' => 'feather-activity',
    'color' => 'primary', // primary, success, warning, danger, info, teal
    'variant' => 'standard', // standard, compact
])

@php
    $tone = match ($color) {
        'success', 'teal' => 'positive',
        'danger' => 'negative',
        'warning' => 'pending',
        'info' => 'info',
        'primary' => 'brand',
        default => 'neutral',
    };
    $trendTone = match ($trendDirection) {
        'up' => 'positive',
        'down' => 'negative',
        default => 'neutral',
    };
    $trendArrow = match ($trendDirection) {
        'up' => '▲',
        'down' => '▼',
        default => '•',
    };
@endphp

<x-ui.kpi-card
    :title="$title"
    :value="$value"
    :icon="$icon"
    :tone="$tone"
    :subtitle="$subtitle"
    :attributes="$attributes->class(['mb-3', 'p-3' => $variant === 'compact'])"
>
    @isset($chart)
        <div class="mt-3">{{ $chart }}</div>
    @endisset

    @if ($trend || isset($footer))
        <x-slot:footer>
            @if ($trend)
                <x-ui.chip :tone="$trendTone">{{ $trendArrow }} {{ $trend }}</x-ui.chip>
            @endif
            @isset($footer)
                <span>{{ $footer }}</span>
            @endisset
        </x-slot:footer>
    @endif
</x-ui.kpi-card>
