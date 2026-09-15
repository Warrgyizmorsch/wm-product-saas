@extends('layouts.duralux')

@section('title', 'Accounting Dashboard | SaaS ERP')
@section('page-title', 'Accounting Dashboard')
@section('breadcrumb', 'Accounting / Dashboard')

@section('page-actions')
    <a href="{{ route('accounting.journals.create') }}" class="btn btn-primary">
        <i class="feather-plus me-2"></i>New Journal
    </a>
@endsection

@section('content')
    @php
        // Ledger amounts carry no symbol, like every Accounting screen — the layout
        // shows "Amounts in <company currency>" once per page.
        $money = fn ($amount) => number_format((float) $amount, 2);

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

        $kpiCards = [
            ['title' => 'Revenue', 'kpi' => $kpis['income'], 'icon' => 'feather-trending-up', 'color' => 'success', 'higherIsBetter' => true],
            ['title' => 'Expenses', 'kpi' => $kpis['expense'], 'icon' => 'feather-trending-down', 'color' => 'danger', 'higherIsBetter' => false],
            ['title' => 'Net Profit', 'kpi' => $kpis['net_profit'], 'icon' => 'feather-award', 'color' => $kpis['net_profit']['current'] < 0 ? 'danger' : 'primary', 'higherIsBetter' => true],
        ];

        $charts = [
            'trend' => $trend,
            'receivables' => ['labels' => array_values(\App\Domains\Accounting\Services\Dashboard\PartyBalances::BUCKETS), 'values' => array_values($receivables['buckets'])],
            'payables' => ['labels' => array_values(\App\Domains\Accounting\Services\Dashboard\PartyBalances::BUCKETS), 'values' => array_values($payables['buckets'])],
            'expenses' => ['labels' => array_column($expenseBreakdown, 'label'), 'values' => array_column($expenseBreakdown, 'amount')],
        ];
        $hasTrend = array_sum($trend['income']) != 0 || array_sum($trend['expense']) != 0;
        $gstDaysLeft = fn ($due) => (int) round($today->diffInDays($due, false));
        $checklistDone = collect($checklist)->where('ok', true)->count();
    @endphp

    {{-- Period filter --}}
    <x-ui.card class="mb-3">
        <form method="GET" action="{{ route('accounting.dashboard') }}" class="row g-2 align-items-end" id="dashboard-period-form">
            <div class="col-md-3">
                <label class="form-label fs-12 text-uppercase fw-semibold text-muted mb-1" for="preset">Period</label>
                <select name="preset" id="preset" class="form-select form-select-sm">
                    @foreach ($presets as $value => $label)
                        <option value="{{ $value }}" @selected($period->preset === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 custom-range {{ $period->preset === 'custom' ? '' : 'd-none' }}">
                <label class="form-label fs-12 text-uppercase fw-semibold text-muted mb-1" for="from">From</label>
                <input type="date" name="from" id="from" class="form-control form-control-sm" value="{{ $period->from->toDateString() }}">
            </div>
            <div class="col-md-2 custom-range {{ $period->preset === 'custom' ? '' : 'd-none' }}">
                <label class="form-label fs-12 text-uppercase fw-semibold text-muted mb-1" for="to">To</label>
                <input type="date" name="to" id="to" class="form-control form-control-sm" value="{{ $period->to->toDateString() }}">
            </div>
            <div class="col-md-auto">
                <button type="submit" class="btn btn-sm btn-primary">Apply</button>
            </div>
            <div class="col-md text-md-end fs-12 text-muted">
                <div><strong class="text-dark">{{ $period->label() }}</strong> ({{ $period->days() }} days)</div>
                <div>compared with {{ $period->previousLabel() }}</div>
            </div>
        </form>
    </x-ui.card>

    {{-- Headline KPIs --}}
    <div class="row g-3">
        @foreach ($kpiCards as $card)
            @php
                $badge = $changeBadge($card['kpi']['change'], $card['higherIsBetter']);
            @endphp
            <div class="col-xxl-3 col-md-6">
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
            </div>
        @endforeach
        <div class="col-xxl-3 col-md-6">
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
        </div>
    </div>

    {{-- Trend + financial health --}}
    <div class="row g-3">
        <div class="col-lg-8">
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
        </div>
        <div class="col-lg-4">
            <x-ui.card title="Financial Health" bodyClass="p-0" class="accounting-dense mb-3" stretch>
                @php
                    $ratioRows = [
                        ['Gross margin', $ratios['gross_margin'] === null ? '—' : number_format($ratios['gross_margin'], 1) . '%', 'Direct income less cost of sales'],
                        ['Net margin', $ratios['net_margin'] === null ? '—' : number_format($ratios['net_margin'], 1) . '%', 'Net profit as % of income'],
                        ['Current ratio', $ratios['current_ratio'] === null ? '—' : number_format($ratios['current_ratio'], 2) . 'x', 'Healthy at 1.5x or more'],
                        ['Quick ratio', $ratios['quick_ratio'] === null ? '—' : number_format($ratios['quick_ratio'], 2) . 'x', 'Excludes inventory; healthy at 1x or more'],
                        ['Working capital', $money($ratios['working_capital']), 'Current assets less current liabilities'],
                        ['Days sales outstanding', $ratios['dso'] === null ? '—' : $ratios['dso'] . ' days', 'Average days to collect'],
                        ['Days payable outstanding', $ratios['dpo'] === null ? '—' : $ratios['dpo'] . ' days', 'Average days to pay'],
                        ['Avg monthly expense', $money($burn['monthly_expense']), 'Last 3 months'],
                        ['Cash runway', $burn['runway_months'] === null ? 'Not burning cash' : number_format($burn['runway_months'], 1) . ' months', 'Cash ÷ average net burn'],
                    ];
                @endphp
                <x-ui.table>
                    <tbody class="fs-13 text-dark">
                        @foreach ($ratioRows as [$label, $value, $hint])
                            <tr>
                                <td class="ps-4">
                                    {{ $label }}
                                    <div class="fs-11 text-muted">{{ $hint }}</div>
                                </td>
                                <td class="text-end pe-4 fw-semibold text-nowrap">{{ $value }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.table>
            </x-ui.card>
        </div>
    </div>

    {{-- Receivables & payables --}}
    <div class="row g-3">
        @foreach ([
            ['title' => 'Receivables', 'data' => $receivables, 'chart' => 'acc-ar-chart', 'party' => 'Top customers', 'route' => 'accounting.reports.ar-aging', 'noun' => 'invoice(s)'],
            ['title' => 'Payables', 'data' => $payables, 'chart' => 'acc-ap-chart', 'party' => 'Top vendors', 'route' => 'accounting.reports.ap-aging', 'noun' => 'bill(s)'],
        ] as $side)
            <div class="col-lg-6">
                <x-ui.card :title="$side['title'] . ' Aging'" class="mb-3" stretch>
                    <x-slot:headerAction>
                        <a href="{{ route($side['route']) }}" class="fs-12">Full report <i class="feather-arrow-right"></i></a>
                    </x-slot:headerAction>
                    <div class="d-flex flex-wrap gap-4 mb-2 fs-13 text-muted">
                        <span>Outstanding <strong class="text-dark">{{ $money($side['data']['total']) }}</strong></span>
                        <span>Overdue <strong class="{{ $side['data']['overdue'] > 0 ? 'text-danger' : 'text-dark' }}">{{ $money($side['data']['overdue']) }}</strong></span>
                        <span>{{ $side['data']['count'] }} {{ $side['noun'] }}</span>
                    </div>
                    @if ($side['data']['total'] > 0)
                        <div id="{{ $side['chart'] }}" style="min-height: 220px;"></div>
                        <div class="fs-11 text-uppercase fw-semibold text-muted mt-2 mb-1">{{ $side['party'] }}</div>
                        <table class="table table-sm mb-0 fs-13">
                            <tbody>
                                @foreach ($side['data']['top'] as $party)
                                    <tr>
                                        <td class="ps-0">{{ $party['name'] }}</td>
                                        <td class="text-end pe-0">{{ $money($party['amount']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <div class="text-center py-5 text-muted"><i class="feather-check-circle fs-1 mb-2 d-block"></i>Nothing outstanding.</div>
                    @endif
                </x-ui.card>
            </div>
        @endforeach
    </div>

    {{-- Forecast, tax, expense mix --}}
    <div class="row g-3">
        <div class="col-lg-4">
            <x-ui.card :title="$forecast['days'] . '-Day Cash Forecast'" bodyClass="p-0" class="accounting-dense mb-3" stretch>
                <x-ui.table>
                    <tbody class="fs-13 text-dark">
                        <tr><td class="ps-4">Cash &amp; bank today</td><td class="text-end pe-4">{{ $money($forecast['opening']) }}</td></tr>
                        <tr><td class="ps-4">+ Receivables due</td><td class="text-end pe-4 text-success">{{ $money($forecast['inflow']) }}</td></tr>
                        <tr><td class="ps-4">− Payables due</td><td class="text-end pe-4 text-danger">{{ $money($forecast['outflow']) }}</td></tr>
                    </tbody>
                    <tfoot>
                        <tr class="fw-bold fs-14 bg-light">
                            <td class="ps-4">Projected cash</td>
                            <td class="text-end pe-4 {{ $forecast['closing'] < 0 ? 'text-danger' : '' }}">{{ $money($forecast['closing']) }}</td>
                        </tr>
                    </tfoot>
                </x-ui.table>
                <div class="px-4 py-2 fs-11 text-muted">Includes amounts already overdue. Assumes everything due is collected and paid on time.</div>
            </x-ui.card>
        </div>
        <div class="col-lg-4">
            <x-ui.card title="GST &amp; TDS" bodyClass="p-0" class="accounting-dense mb-3" stretch>
                <x-slot:headerAction>
                    <a href="{{ route('accounting.reports.gst-summary') }}" class="fs-12">GST Summary <i class="feather-arrow-right"></i></a>
                </x-slot:headerAction>
                <x-ui.table>
                    <tbody class="fs-13 text-dark">
                        <tr class="table-light"><td class="ps-4 fw-semibold" colspan="2">Return for {{ $gst['return_month'] }}</td></tr>
                        <tr><td class="ps-4">Output GST</td><td class="text-end pe-4">{{ $money($gst['output']) }}</td></tr>
                        <tr><td class="ps-4">Input tax credit</td><td class="text-end pe-4">({{ $money($gst['input']) }})</td></tr>
                        <tr class="fw-semibold"><td class="ps-4">Net GST payable</td><td class="text-end pe-4">{{ $money($gst['payable']) }}</td></tr>
                        @foreach (['GSTR-1' => $gst['gstr1_due'], 'GSTR-3B' => $gst['gstr3b_due']] as $return => $due)
                            @php
                                $daysLeft = $gstDaysLeft($due);
                            @endphp
                            <tr>
                                <td class="ps-4">{{ $return }} due {{ $due->format('d M') }}</td>
                                <td class="text-end pe-4">
                                    <span class="badge {{ $daysLeft < 0 ? 'bg-soft-secondary text-secondary' : ($daysLeft <= 3 ? 'bg-soft-danger text-danger' : 'bg-soft-warning text-warning') }}">
                                        {{ $daysLeft < 0 ? 'Date passed' : ($daysLeft === 0 ? 'Due today' : $daysLeft . ' day(s) left') }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                        <tr class="table-light"><td class="ps-4 fw-semibold" colspan="2">Building up</td></tr>
                        <tr><td class="ps-4">GST for {{ $gst['accruing_month'] }} so far</td><td class="text-end pe-4">{{ $money($gst['accruing_payable']) }}</td></tr>
                        <tr><td class="ps-4">TDS payable</td><td class="text-end pe-4">{{ $money($tdsPayable) }}</td></tr>
                    </tbody>
                </x-ui.table>
            </x-ui.card>
        </div>
        <div class="col-lg-4">
            <x-ui.card title="Where the Money Went" class="mb-3" stretch>
                @if (count($expenseBreakdown) > 0)
                    <div id="acc-expense-chart" style="min-height: 280px;"></div>
                @else
                    <div class="text-center py-5 text-muted"><i class="feather-pie-chart fs-1 mb-2 d-block"></i>No expenses in this period.</div>
                @endif
            </x-ui.card>
        </div>
    </div>

    {{-- Month-end close + budgets --}}
    <div class="row g-3">
        <div class="col-lg-6">
            <x-ui.card title="Month-End Close" class="mb-3" stretch>
                <x-slot:headerAction>
                    <span class="badge {{ $checklistDone === count($checklist) ? 'bg-soft-success text-success' : 'bg-soft-warning text-warning' }}">{{ $checklistDone }}/{{ count($checklist) }} done</span>
                </x-slot:headerAction>
                @foreach ($checklist as $item)
                    <a href="{{ route($item['route']) }}" class="d-flex align-items-start gap-2 py-2 {{ $loop->last ? '' : 'border-bottom' }} text-dark">
                        <i class="{{ $item['ok'] ? 'feather-check-circle text-success' : 'feather-alert-circle text-danger' }} mt-1"></i>
                        <span class="flex-grow-1">
                            <span class="d-block fs-13 fw-semibold">{{ $item['label'] }}</span>
                            <span class="d-block fs-12 text-muted">{{ $item['detail'] }}</span>
                        </span>
                        <i class="feather-chevron-right text-muted mt-1"></i>
                    </a>
                @endforeach
            </x-ui.card>
        </div>
        <div class="col-lg-6">
            <x-ui.card title="Budget Alerts" bodyClass="p-0" class="accounting-dense mb-3" stretch>
                <x-slot:headerAction>
                    <a href="{{ route('accounting.reports.budget-vs-actual') }}" class="fs-12">Budget vs Actual <i class="feather-arrow-right"></i></a>
                </x-slot:headerAction>
                <x-ui.table hoverable>
                    <tbody class="fs-13 text-dark">
                        @forelse ($budgetAlerts as $alert)
                            <tr>
                                <td class="ps-4">
                                    <span class="fw-semibold">{{ $alert['account'] }}</span>
                                    @if ($alert['cost_center'])<span class="text-muted"> · {{ $alert['cost_center'] }}</span>@endif
                                    <div class="progress mt-1" style="height: 5px;">
                                        <div class="progress-bar {{ $alert['status'] === 'over' ? 'bg-danger' : 'bg-warning' }}" style="width: {{ min($alert['percent'], 100) }}%"></div>
                                    </div>
                                    <div class="fs-11 text-muted mt-1">{{ $money($alert['actual']) }} of {{ $money($alert['budgeted']) }} · {{ $alert['budget'] }}</div>
                                </td>
                                <td class="text-end pe-4 align-middle">
                                    <span class="badge {{ $alert['status'] === 'over' ? 'bg-danger' : 'bg-warning' }}">{{ number_format($alert['percent'], 1) }}%</span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="text-center py-5 text-muted"><i class="feather-check-circle fs-1 mb-2 d-block"></i>Every budget line is under 80% used.</td></tr>
                        @endforelse
                    </tbody>
                </x-ui.table>
            </x-ui.card>
        </div>
    </div>

    {{-- Balances, reconciliation, journals --}}
    <div class="row g-3">
        <div class="col-lg-5">
            <x-ui.card title="Cash & Bank Balances" bodyClass="p-0" class="accounting-dense mb-3">
                <x-ui.table hoverable>
                    <tbody class="fs-13 text-dark">
                        @forelse ($cash['accounts'] as $row)
                            <tr>
                                <td class="ps-4"><span class="fw-bold font-monospace me-1">{{ $row['account']->code }}</span>{{ $row['account']->name }}</td>
                                <td class="text-end pe-4 {{ $row['balance'] < 0 ? 'text-danger' : '' }}">{{ $money($row['balance']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="text-center py-4 text-muted">No cash or bank activity yet.</td></tr>
                        @endforelse
                    </tbody>
                </x-ui.table>
            </x-ui.card>

            <x-ui.card title="Bank Reconciliation" bodyClass="p-0" class="accounting-dense mb-3">
                <x-slot:headerAction>
                    <a href="{{ route('accounting.bank-reconciliation.index') }}" class="fs-12">Reconcile <i class="feather-arrow-right"></i></a>
                </x-slot:headerAction>
                <x-ui.table hoverable>
                    <tbody class="fs-13 text-dark">
                        @forelse ($bankAccounts as $bank)
                            <tr>
                                <td class="ps-4">
                                    {{ $bank['account']->name }}
                                    <div class="fs-11 text-muted">Last reconciled: {{ $bank['last_reconciled']?->format('d M Y') ?? 'never' }}</div>
                                </td>
                                <td class="text-end pe-4 align-middle">
                                    @if ($bank['in_progress'])
                                        <span class="badge bg-soft-info text-info">In progress</span>
                                    @elseif ($bank['stale'])
                                        <span class="badge bg-soft-danger text-danger">Due</span>
                                    @else
                                        <span class="badge bg-soft-success text-success">Up to date</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="text-center py-4 text-muted">No bank accounts in the chart of accounts.</td></tr>
                        @endforelse
                    </tbody>
                </x-ui.table>
            </x-ui.card>
        </div>
        <div class="col-lg-7">
            <x-ui.card title="Recent Journals" bodyClass="p-0" class="accounting-dense mb-3">
                <x-slot:headerAction>
                    <a href="{{ route('accounting.journals.index') }}" class="fs-12">All journals <i class="feather-arrow-right"></i></a>
                </x-slot:headerAction>
                <x-ui.table hoverable>
                    <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                        <tr>
                            <th class="ps-4">Date</th>
                            <th>Number</th>
                            <th>Source</th>
                            <th>Memo</th>
                            <th class="text-end pe-4">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="fs-13 text-dark">
                        @forelse ($recentJournals as $journal)
                            <tr>
                                <td class="ps-4 text-nowrap">{{ $journal->journal_date?->format('d M Y') }}</td>
                                <td class="text-nowrap">
                                    <a href="{{ route('accounting.journals.show', $journal) }}" class="fw-semibold">{{ $journal->journal_number }}</a>
                                    @if ($journal->status === \App\Domains\Accounting\Models\Journal::STATUS_REVERSED)
                                        <span class="badge bg-soft-secondary text-secondary ms-1">Reversed</span>
                                    @endif
                                </td>
                                <td class="text-capitalize">{{ str_replace('_', ' ', $journal->voucher_type ?? $journal->source) }}</td>
                                <td class="text-muted">{{ \Illuminate\Support\Str::limit($journal->memo, 40) }}</td>
                                <td class="text-end pe-4">{{ $money($journal->total_debit) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center py-4 text-muted">No journals posted yet.</td></tr>
                        @endforelse
                    </tbody>
                </x-ui.table>
            </x-ui.card>

            <x-ui.card title="Reports">
                <div class="row g-2 fs-13">
                    @foreach ([
                        'Profit & Loss' => 'accounting.reports.profit-loss',
                        'Balance Sheet' => 'accounting.reports.balance-sheet',
                        'Trial Balance' => 'accounting.reports.trial-balance',
                        'Cash Flow' => 'accounting.reports.cash-flow',
                        'General Ledger' => 'accounting.reports.general-ledger',
                        'Day Book' => 'accounting.reports.day-book',
                        'GSTR-1' => 'accounting.reports.gstr1',
                        'GSTR-3B' => 'accounting.reports.gstr3b',
                    ] as $label => $routeName)
                        <div class="col-sm-3 col-6">
                            <a href="{{ route($routeName) }}" class="d-block text-dark"><i class="feather-file-text me-1 text-muted"></i>{{ $label }}</a>
                        </div>
                    @endforeach
                </div>
            </x-ui.card>
        </div>
    </div>
@endsection

@push('styles')
    <style>
        .accounting-dense table th,
        .accounting-dense table td {
            padding: 6px 10px !important;
            font-size: 12px !important;
        }
    </style>
@endpush

@push('scripts')
    <script src="{{ asset('assets/vendors/js/apexcharts.min.js') }}"></script>
    <script>
        (function () {
            const presetSelect = document.getElementById('preset');
            if (presetSelect) {
                presetSelect.addEventListener('change', function () {
                    const isCustom = this.value === 'custom';
                    document.querySelectorAll('#dashboard-period-form .custom-range').forEach((el) => el.classList.toggle('d-none', !isCustom));
                    if (!isCustom) {
                        this.form.submit();
                    }
                });
            }

            if (typeof ApexCharts === 'undefined') {
                return;
            }

            const charts = @json($charts);
            const format = (value, digits) => Number(value).toLocaleString(undefined, {
                minimumFractionDigits: digits,
                maximumFractionDigits: digits,
            });
            const render = (id, options) => {
                const element = document.getElementById(id);
                if (element) {
                    new ApexCharts(element, Object.assign({ dataLabels: { enabled: false } }, options)).render();
                }
            };

            render('acc-trend-chart', {
                chart: { type: 'bar', height: 300, toolbar: { show: false }, fontFamily: 'inherit' },
                series: [
                    { name: 'Income', data: charts.trend.income },
                    { name: 'Expense', data: charts.trend.expense },
                ],
                xaxis: { categories: charts.trend.labels },
                colors: ['#17c666', '#ea4d4d'],
                plotOptions: { bar: { columnWidth: '45%', borderRadius: 3 } },
                legend: { position: 'top', horizontalAlign: 'right' },
                yaxis: { labels: { formatter: (value) => format(value, 0) } },
                tooltip: { y: { formatter: (value) => format(value, 2) } },
            });

            [['acc-ar-chart', charts.receivables], ['acc-ap-chart', charts.payables]].forEach(([id, data]) => render(id, {
                chart: { type: 'bar', height: 220, toolbar: { show: false }, fontFamily: 'inherit' },
                series: [{ name: 'Outstanding', data: data.values }],
                xaxis: { categories: data.labels },
                colors: ['#17c666', '#ffa21d', '#f97316', '#ea4d4d', '#b91c1c'],
                plotOptions: { bar: { distributed: true, columnWidth: '50%', borderRadius: 3 } },
                legend: { show: false },
                yaxis: { labels: { formatter: (value) => format(value, 0) } },
                tooltip: { y: { formatter: (value) => format(value, 2) } },
            }));

            render('acc-expense-chart', {
                chart: { type: 'donut', height: 280, fontFamily: 'inherit' },
                series: charts.expenses.values,
                labels: charts.expenses.labels,
                legend: { position: 'bottom' },
                tooltip: { y: { formatter: (value) => format(value, 2) } },
            });
        })();
    </script>
@endpush
