{{-- Headline card. $which: revenue | expenses | net_profit | cash --}}
@php
    $changeBadge = function (?float $change, bool $higherIsBetter = true): array {
        if ($change === null) {
            return ['class' => 'bg-soft-secondary text-secondary', 'text' => 'No prior data'];
        }
        if ($change == 0) {
            return ['class' => 'bg-soft-secondary text-secondary', 'text' => '0.0%'];
        }
        $good = ($change > 0) === $higherIsBetter;

        return [
            'class' => $good ? 'bg-soft-success text-success' : 'bg-soft-danger text-danger',
            'text' => ($change > 0 ? '▲ ' : '▼ ') . number_format(abs($change), 1) . '%',
        ];
    };

    $cards = [
        'revenue' => ['title' => 'Revenue', 'kpi' => $kpis['income'], 'icon' => 'feather-trending-up', 'color' => 'success', 'higherIsBetter' => true],
        'expenses' => ['title' => 'Expenses', 'kpi' => $kpis['expense'], 'icon' => 'feather-trending-down', 'color' => 'danger', 'higherIsBetter' => false],
        'net_profit' => ['title' => 'Net Profit', 'kpi' => $kpis['net_profit'], 'icon' => 'feather-award', 'color' => $kpis['net_profit']['current'] < 0 ? 'danger' : 'primary', 'higherIsBetter' => true],
    ];
@endphp

@if ($which === 'cash')
    <div class="card stretch stretch-full border-0 shadow-sm mb-3">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <span class="fs-12 text-uppercase text-muted fw-semibold d-block mb-1">Cash &amp; Bank</span>
                    <h3 class="fw-bold mb-0 text-dark">{{ $money($cash['total']) }}</h3>
                </div>
                <x-ui.icon-tile icon="feather-briefcase" color="info" size="lg" />
            </div>
            <div class="mt-3 fs-12 text-muted">
                @if ($burn['runway_months'] !== null)
                    <span class="badge {{ $burn['runway_months'] < 3 ? 'bg-soft-danger text-danger' : 'bg-soft-warning text-warning' }}">{{ number_format($burn['runway_months'], 1) }} months runway</span>
                @else
                    <span class="badge bg-soft-success text-success">No net burn</span>
                @endif
                <span class="ms-1">as of {{ $asOf->format('d M Y') }}</span>
            </div>
        </div>
    </div>
@else
    @php
        $card = $cards[$which];
        $badge = $changeBadge($card['kpi']['change'], $card['higherIsBetter']);
    @endphp
    <div class="card stretch stretch-full border-0 shadow-sm mb-3">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <span class="fs-12 text-uppercase text-muted fw-semibold d-block mb-1">{{ $card['title'] }}</span>
                    <h3 class="fw-bold mb-0 text-dark">{{ $money($card['kpi']['current']) }}</h3>
                </div>
                <x-ui.icon-tile :icon="$card['icon']" :color="$card['color']" size="lg" />
            </div>
            <div class="mt-3 fs-12">
                <span class="badge {{ $badge['class'] }}">{{ $badge['text'] }}</span>
                <span class="text-muted ms-1">vs {{ $money($card['kpi']['previous']) }} previous</span>
            </div>
        </div>
    </div>
@endif
