@extends('layouts.duralux')

@section('title', 'Balance Sheet | SaaS ERP')
@section('page-title', 'Balance Sheet')
@section('breadcrumb', 'Accounting / Reports / Balance Sheet')

@section('content')
    <x-ui.card class="mb-4">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-6">
                <x-ui.select label="As of Accounting Period" name="period_id" onchange="this.form.submit()" :options="$allPeriods->mapWithKeys(fn ($p) => [
                    $p->id => ($p->fiscalYear?->name) . ' — ' . $p->name . ' (' . $p->status . ')',
                ])->all()" :selected="$period?->id" />
            </div>
        </form>
    </x-ui.card>

    @if (!$period)
        <x-ui.card bodyClass="p-0">
            <div class="text-center py-5 text-muted">
                <i class="feather-bar-chart-2 fs-1 mb-2 d-block"></i>
                No accounting periods exist yet.
            </div>
        </x-ui.card>
    @else
        @php
            // Liabilities column groups, in Busy/Tally order: Profit for the
            // Period (no drill-down — a single computed figure, not a list of
            // accounts), Capital Account, Non-Current Liabilities (only when
            // non-empty), Current Liabilities.
            $mapRow = fn ($row) => ['id' => $row['account']->id, 'name' => $row['account']->name, 'code' => $row['account']->code, 'amount' => $row['balance']];

            $liabilityGroups = [
                [
                    'label' => 'Capital Account',
                    'total' => $totals['equity'],
                    'rows' => $sections['equity']->map($mapRow)->all(),
                ],
            ];
            if ($sections['liability']['non_current']->isNotEmpty()) {
                $liabilityGroups[] = [
                    'label' => 'Non-Current Liabilities',
                    'total' => $totals['liability_non_current'],
                    'rows' => $sections['liability']['non_current']->map($mapRow)->all(),
                ];
            }
            $liabilityGroups[] = [
                'label' => 'Current Liabilities',
                'total' => $totals['liability_current'],
                'rows' => $sections['liability']['current']->map($mapRow)->all(),
            ];

            $assetGroups = [
                [
                    'label' => 'Fixed Assets',
                    'total' => $totals['asset_non_current'],
                    'rows' => $sections['asset']['non_current']->map($mapRow)->all(),
                ],
                [
                    'label' => 'Current Assets',
                    'total' => $totals['asset_current'],
                    'rows' => $sections['asset']['current']->map($mapRow)->all(),
                ],
            ];
        @endphp

        <x-ui.card class="mb-3">
            <div class="d-flex justify-content-between align-items-center fs-13">
                <span class="text-muted">As of {{ \Illuminate\Support\Carbon::parse($period->end_date)->format('d M Y') }} (end of {{ $period->name }})</span>
                <div class="d-flex align-items-center gap-2">
                    @if ($isBalanced)
                        <x-ui.badge variant="success" soft>Balanced</x-ui.badge>
                    @else
                        <x-ui.badge variant="danger" soft>Out of Balance</x-ui.badge>
                    @endif
                    <a href="{{ route('accounting.reports.balance-sheet.pdf', ['period_id' => $period->id]) }}" class="btn btn-sm btn-outline-primary">
                        <i class="feather-download me-1"></i>Download PDF
                    </a>
                </div>
            </div>
        </x-ui.card>

        <x-ui.card bodyClass="p-0" class="accounting-dense">
            <div class="text-center py-3 border-bottom">
                <h5 class="mb-1 fw-bold">Balance Sheet of {{ tenant()?->name }}</h5>
                <div class="text-muted fs-13">as at {{ \Illuminate\Support\Carbon::parse($period->end_date)->format('d M Y') }}</div>
            </div>
            <div class="row g-0 bs-groups">
                <div class="col-lg-6 border-end">
                    <table class="table mb-0 bs-group-table">
                        <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                            <tr>
                                <th class="ps-4">Liabilities</th>
                                <th class="text-end pe-4">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="fs-13 text-dark">
                            <tr>
                                <td class="ps-4 fw-bold text-dark">Profit for the Period</td>
                                <td class="text-end pe-4 fw-bold text-dark">{{ number_format($netIncome, 2) }}</td>
                            </tr>
                            @foreach ($liabilityGroups as $gi => $group)
                                <tr class="bs-group-row" role="button" data-target="liab-{{ $gi }}">
                                    <td class="ps-4 fw-bold text-dark">
                                        <i class="feather-chevron-right bs-chevron me-1"></i>{{ $group['label'] }}
                                    </td>
                                    <td class="text-end pe-4 fw-bold text-dark">{{ number_format($group['total'], 2) }}</td>
                                </tr>
                                @forelse ($group['rows'] as $row)
                                    <tr class="bs-detail-row d-none" data-group="liab-{{ $gi }}">
                                        <td class="ps-5 font-monospace text-muted fw-normal">
                                            <a href="{{ route('accounting.reports.general-ledger', ['chart_of_account_id' => $row['id'], 'period_id' => $period->id]) }}" class="text-muted">{{ $row['code'] }}</a>
                                            <a href="{{ route('accounting.reports.general-ledger', ['chart_of_account_id' => $row['id'], 'period_id' => $period->id]) }}" class="text-muted font-monospace-off">{{ $row['name'] }}</a>
                                        </td>
                                        <td class="text-end pe-4 text-muted fw-normal">{{ number_format($row['amount'], 2) }}</td>
                                    </tr>
                                @empty
                                    <tr class="bs-detail-row d-none" data-group="liab-{{ $gi }}">
                                        <td class="ps-5 text-muted" colspan="2">No accounts.</td>
                                    </tr>
                                @endforelse
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="fw-bold fs-13 bg-light">
                                <td class="ps-4">Total</td>
                                <td class="text-end pe-4">{{ number_format($totals['liability'] + $totals['equity'], 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="col-lg-6">
                    <table class="table mb-0 bs-group-table">
                        <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                            <tr>
                                <th class="ps-4">Assets</th>
                                <th class="text-end pe-4">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="fs-13 text-dark">
                            @foreach ($assetGroups as $gi => $group)
                                <tr class="bs-group-row" role="button" data-target="asset-{{ $gi }}">
                                    <td class="ps-4 fw-bold text-dark">
                                        <i class="feather-chevron-right bs-chevron me-1"></i>{{ $group['label'] }}
                                    </td>
                                    <td class="text-end pe-4 fw-bold text-dark">{{ number_format($group['total'], 2) }}</td>
                                </tr>
                                @forelse ($group['rows'] as $row)
                                    <tr class="bs-detail-row d-none" data-group="asset-{{ $gi }}">
                                        <td class="ps-5 font-monospace text-muted fw-normal">
                                            <a href="{{ route('accounting.reports.general-ledger', ['chart_of_account_id' => $row['id'], 'period_id' => $period->id]) }}" class="text-muted">{{ $row['code'] }}</a>
                                            <a href="{{ route('accounting.reports.general-ledger', ['chart_of_account_id' => $row['id'], 'period_id' => $period->id]) }}" class="text-muted font-monospace-off">{{ $row['name'] }}</a>
                                        </td>
                                        <td class="text-end pe-4 text-muted fw-normal">{{ number_format($row['amount'], 2) }}</td>
                                    </tr>
                                @empty
                                    <tr class="bs-detail-row d-none" data-group="asset-{{ $gi }}">
                                        <td class="ps-5 text-muted" colspan="2">No accounts.</td>
                                    </tr>
                                @endforelse
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr class="fw-bold fs-13 bg-light">
                                <td class="ps-4">Total</td>
                                <td class="text-end pe-4">{{ number_format($totals['asset'], 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </x-ui.card>
    @endif
@endsection

@push('styles')
    <style>
        .accounting-dense table th,
        .accounting-dense table td {
            padding: 6px 10px !important;
            font-size: 12px !important;
        }
        .bs-group-row {
            cursor: pointer;
        }
        .bs-group-row:hover {
            background-color: var(--bs-light, #f8f9fa);
        }
        .bs-chevron {
            font-size: 11px;
            transition: transform 0.15s ease;
            display: inline-block;
        }
        .bs-group-row.is-open .bs-chevron {
            transform: rotate(90deg);
        }
    </style>
@endpush

@push('scripts')
    <script>
        document.querySelectorAll('.bs-group-row').forEach(function (header) {
            header.addEventListener('click', function () {
                var target = header.getAttribute('data-target');
                var isOpen = header.classList.toggle('is-open');
                document.querySelectorAll('.bs-detail-row[data-group="' + target + '"]').forEach(function (row) {
                    row.classList.toggle('d-none', !isOpen);
                });
            });
        });
    </script>
@endpush
