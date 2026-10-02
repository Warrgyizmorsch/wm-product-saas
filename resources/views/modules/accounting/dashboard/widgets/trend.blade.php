{{-- Income vs expense (ui-reference "income-expense-trend"). --}}
@php
    $hasTrend = array_sum($trend['income']) != 0 || array_sum($trend['expense']) != 0;
@endphp
<x-ui.section class="mb-3 h-100" title="Income vs Expense" badge="6 Months Trend"
    subtitle="Monthly operating income against expense, ending with the selected period">
    <x-slot:actions>
        <span class="d-inline-flex align-items-center gap-3 fs-12 fw-medium">
            <span class="d-inline-flex align-items-center gap-2"><span class="ax-dot is-positive"></span>Income</span>
            <span class="d-inline-flex align-items-center gap-2"><span class="ax-dot is-negative"></span>Expense</span>
        </span>
        <span class="ax-header-divider"></span>
        <a href="{{ route('accounting.reports.profit-loss') }}" class="fs-12 fw-semibold">P&amp;L Statement <i class="feather-arrow-right"></i></a>
    </x-slot:actions>

    <div class="ax-stat-strip mb-3">
        <div><span class="ax-overline">Gross Profit</span><span class="ax-stat-value">{{ $money($profitAndLoss['gross_profit']) }}</span></div>
        <div><span class="ax-overline">Direct Income</span><span class="ax-stat-value">{{ $money($profitAndLoss['direct_income']) }}</span></div>
        <div><span class="ax-overline">Cost of Sales</span><span class="ax-stat-value">{{ $money($profitAndLoss['cogs']) }}</span></div>
    </div>

    @if ($hasTrend)
        <div id="acc-trend-chart" style="min-height: 280px;"></div>
    @else
        <div class="ax-empty"><i class="feather-bar-chart-2"></i>No income or expense posted in the last six months.</div>
    @endif
</x-ui.section>
