{{-- Headline KPI tile (ui-reference "KeyMetricsCards"). $which: revenue | expenses | net_profit | cash --}}
@php
    // "▲ 12.4%" chip: green when the move is good for the business, red when it isn't.
    $change = function (array $kpi, bool $higherIsBetter = true): array {
        if ($kpi['change'] === null) {
            return ['tone' => 'neutral', 'text' => 'No prior data'];
        }
        if ($kpi['change'] == 0) {
            return ['tone' => 'neutral', 'text' => '0.0%'];
        }

        return [
            'tone' => ($kpi['change'] > 0) === $higherIsBetter ? 'positive' : 'negative',
            'text' => ($kpi['change'] > 0 ? '▲ ' : '▼ ').number_format(abs($kpi['change']), 1).'%',
        ];
    };

    $cards = [
        'revenue' => ['title' => 'Total Revenue', 'kpi' => $kpis['income'], 'icon' => 'feather-trending-up', 'tone' => 'positive', 'higherIsBetter' => true],
        'expenses' => ['title' => 'Total Expenses', 'kpi' => $kpis['expense'], 'icon' => 'feather-trending-down', 'tone' => 'negative', 'higherIsBetter' => false],
        'net_profit' => ['title' => 'Net Accounting Profit', 'kpi' => $kpis['net_profit'], 'icon' => 'feather-shield', 'tone' => $kpis['net_profit']['current'] < 0 ? 'negative' : 'positive', 'higherIsBetter' => true],
    ];
@endphp

@if ($which === 'cash')
    <x-ui.kpi-card class="mb-3" title="Cash & Bank Liquidity" :value="$money($cash['total'])" icon="feather-home" tone="info">
        <x-slot:footer>
            @if ($burn['runway_months'] !== null)
                <x-ui.chip :tone="$burn['runway_months'] < 3 ? 'negative' : 'pending'">{{ number_format($burn['runway_months'], 1) }} months runway</x-ui.chip>
            @else
                <x-ui.chip tone="positive">No net burn</x-ui.chip>
            @endif
            <a href="{{ route('accounting.bank-reconciliation.index') }}" class="fw-semibold">Reconcile <i class="feather-chevron-right"></i></a>
        </x-slot:footer>
    </x-ui.kpi-card>
@else
    @php
        $card = $cards[$which];
        $badge = $change($card['kpi'], $card['higherIsBetter']);
        $current = (float) $card['kpi']['current'];
    @endphp
    <x-ui.kpi-card class="mb-3" :title="$card['title']" :value="$money($current)" :icon="$card['icon']" :tone="$card['tone']"
        :value-tone="$which === 'net_profit' ? ($current < 0 ? 'negative' : ($current > 0 ? 'positive' : null)) : null">
        <x-slot:footer>
            <x-ui.chip :tone="$badge['tone']">{{ $badge['text'] }} <span class="ax-chip-meta">vs {{ $money($card['kpi']['previous']) }}</span></x-ui.chip>
            @if ($which === 'net_profit')
                <x-ui.chip :tone="$current < 0 ? 'negative' : 'positive'">{{ $current < 0 ? 'Deficit' : 'Surplus' }}</x-ui.chip>
            @else
                <span>{{ $period->days() }} days</span>
            @endif
        </x-slot:footer>
    </x-ui.kpi-card>
@endif
