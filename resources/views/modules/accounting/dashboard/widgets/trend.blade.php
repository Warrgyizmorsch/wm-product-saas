@php
    $hasTrend = array_sum($trend['income']) != 0 || array_sum($trend['expense']) != 0;
@endphp
<x-ui.card title="Income vs Expense (6 months)" class="mb-3" stretch>
    <x-slot:headerAction>
        <a href="{{ route('accounting.reports.profit-loss') }}" class="fs-12">Profit &amp; Loss <i class="feather-arrow-right"></i></a>
    </x-slot:headerAction>
    <div class="d-flex flex-wrap gap-4 mb-2 fs-13 text-muted">
        <span>Gross profit <strong class="text-dark">{{ $money($profitAndLoss['gross_profit']) }}</strong></span>
        <span>Direct income <strong class="text-dark">{{ $money($profitAndLoss['direct_income']) }}</strong></span>
        <span>Cost of sales <strong class="text-dark">{{ $money($profitAndLoss['cogs']) }}</strong></span>
    </div>
    @if ($hasTrend)
        <div id="acc-trend-chart" style="min-height: 300px;"></div>
    @else
        <div class="text-center py-5 text-muted"><i class="feather-bar-chart-2 fs-1 mb-2 d-block"></i>No income or expense posted in the last six months.</div>
    @endif
</x-ui.card>
