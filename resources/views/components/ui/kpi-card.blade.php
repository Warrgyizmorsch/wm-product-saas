{{--
    KPI metric tile (DESIGN.md → "KPI Metric Tiles").

    <x-ui.kpi-card title="Total Operating Revenue" value="₹1,618,675.46" icon="feather-trending-up" tone="positive">
        <x-slot:footer>
            <x-ui.chip tone="positive">▲ 12.4% <span class="ax-chip-meta">vs ₹1.4M</span></x-ui.chip>
            <span>Cycle: MTD</span>
        </x-slot:footer>
    </x-ui.kpi-card>

    tone       positive | negative | pending | info | brand | neutral — colours the icon tile
    valueTone  same set — colours the figure itself (e.g. profit green, loss red); null = ink
    The decimals of a value like "₹2,220.00" are de-emphasised automatically.
--}}
@props([
    'title' => '',
    'value' => '0',
    'icon' => null,
    'tone' => 'neutral',
    'valueTone' => null,
    'subtitle' => null,
    'href' => null,
])

@php
    $value = (string) $value;
    $fraction = null;
    if (preg_match('/^(.*\d)(\.\d+)$/u', $value, $m)) {
        [$value, $fraction] = [$m[1], $m[2]];
    }
    $valueClass = match ($valueTone) {
        'positive' => 'is-positive',
        'negative' => 'is-negative',
        default => '',
    };
    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }} @if ($href) href="{{ $href }}" @endif {{ $attributes->class(['ax-kpi', 'text-decoration-none' => $href]) }}>
    <div>
        <div class="ax-kpi-head">
            <span class="ax-kpi-title">{{ $title }}</span>
            @if ($icon)
                <span class="ax-kpi-icon ax-tone-{{ $tone }}"><i class="{{ $icon }}"></i></span>
            @endif
        </div>
        <div class="ax-kpi-value {{ $valueClass }}">
            <span>{{ $value }}</span>@if ($fraction)<span class="ax-kpi-fraction">{{ $fraction }}</span>@endif
        </div>
        @if ($subtitle)
            <div class="ax-kpi-sub">{{ $subtitle }}</div>
        @endif
        {{ $slot }}
    </div>
    @isset($footer)
        <div class="ax-kpi-foot">{{ $footer }}</div>
    @endisset
</{{ $tag }}>
