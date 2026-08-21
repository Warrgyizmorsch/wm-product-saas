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
        <x-ui.card class="mb-3">
            <div class="d-flex justify-content-between align-items-center fs-13">
                <span class="text-muted">As of {{ \Illuminate\Support\Carbon::parse($period->end_date)->format('d M Y') }} (end of {{ $period->name }})</span>
                @if ($isBalanced)
                    <x-ui.badge variant="success" soft>Balanced</x-ui.badge>
                @else
                    <x-ui.badge variant="danger" soft>Out of Balance</x-ui.badge>
                @endif
            </div>
        </x-ui.card>

        <div class="row g-4">
            <div class="col-lg-6">
                <x-ui.card title="Assets" bodyClass="p-0" class="accounting-dense">
                    <x-ui.table hoverable>
                        <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                            <tr>
                                <th class="ps-4">Code</th>
                                <th>Account</th>
                                <th class="text-end pe-4">Balance</th>
                            </tr>
                        </thead>
                        <tbody class="fs-13 text-dark">
                            <tr class="table-light">
                                <td class="ps-4 fw-bold text-uppercase fs-11 text-muted" colspan="3">Current Assets</td>
                            </tr>
                            @forelse ($sections['asset']['current'] as $row)
                                <tr>
                                    <td class="ps-4 fw-bold font-monospace">{{ $row['account']->code }}</td>
                                    <td>{{ $row['account']->name }}</td>
                                    <td class="text-end pe-4">{{ number_format($row['balance'], 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center py-3 text-muted">No current asset balances.</td>
                                </tr>
                            @endforelse
                            <tr class="fw-semibold">
                                <td class="ps-4" colspan="2">Total Current Assets</td>
                                <td class="text-end pe-4">{{ number_format($totals['asset_current'], 2) }}</td>
                            </tr>

                            <tr class="table-light">
                                <td class="ps-4 fw-bold text-uppercase fs-11 text-muted" colspan="3">Non-Current Assets</td>
                            </tr>
                            @forelse ($sections['asset']['non_current'] as $row)
                                <tr>
                                    <td class="ps-4 fw-bold font-monospace">{{ $row['account']->code }}</td>
                                    <td>{{ $row['account']->name }}</td>
                                    <td class="text-end pe-4">{{ number_format($row['balance'], 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center py-3 text-muted">No non-current asset balances.</td>
                                </tr>
                            @endforelse
                            <tr class="fw-semibold">
                                <td class="ps-4" colspan="2">Total Non-Current Assets</td>
                                <td class="text-end pe-4">{{ number_format($totals['asset_non_current'], 2) }}</td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="fw-bold fs-13 bg-light">
                                <td class="ps-4" colspan="2">Total Assets</td>
                                <td class="text-end pe-4">{{ number_format($totals['asset'], 2) }}</td>
                            </tr>
                        </tfoot>
                    </x-ui.table>
                </x-ui.card>
            </div>

            <div class="col-lg-6">
                <x-ui.card title="Liabilities" bodyClass="p-0" class="accounting-dense mb-4">
                    <x-ui.table hoverable>
                        <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                            <tr>
                                <th class="ps-4">Code</th>
                                <th>Account</th>
                                <th class="text-end pe-4">Balance</th>
                            </tr>
                        </thead>
                        <tbody class="fs-13 text-dark">
                            <tr class="table-light">
                                <td class="ps-4 fw-bold text-uppercase fs-11 text-muted" colspan="3">Current Liabilities</td>
                            </tr>
                            @forelse ($sections['liability']['current'] as $row)
                                <tr>
                                    <td class="ps-4 fw-bold font-monospace">{{ $row['account']->code }}</td>
                                    <td>{{ $row['account']->name }}</td>
                                    <td class="text-end pe-4">{{ number_format($row['balance'], 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center py-3 text-muted">No current liability balances.</td>
                                </tr>
                            @endforelse
                            <tr class="fw-semibold">
                                <td class="ps-4" colspan="2">Total Current Liabilities</td>
                                <td class="text-end pe-4">{{ number_format($totals['liability_current'], 2) }}</td>
                            </tr>

                            <tr class="table-light">
                                <td class="ps-4 fw-bold text-uppercase fs-11 text-muted" colspan="3">Non-Current Liabilities</td>
                            </tr>
                            @forelse ($sections['liability']['non_current'] as $row)
                                <tr>
                                    <td class="ps-4 fw-bold font-monospace">{{ $row['account']->code }}</td>
                                    <td>{{ $row['account']->name }}</td>
                                    <td class="text-end pe-4">{{ number_format($row['balance'], 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center py-3 text-muted">No non-current liability balances.</td>
                                </tr>
                            @endforelse
                            <tr class="fw-semibold">
                                <td class="ps-4" colspan="2">Total Non-Current Liabilities</td>
                                <td class="text-end pe-4">{{ number_format($totals['liability_non_current'], 2) }}</td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="fw-bold fs-13 bg-light">
                                <td class="ps-4" colspan="2">Total Liabilities</td>
                                <td class="text-end pe-4">{{ number_format($totals['liability'], 2) }}</td>
                            </tr>
                        </tfoot>
                    </x-ui.table>
                </x-ui.card>

                <x-ui.card title="Equity" bodyClass="p-0" class="accounting-dense">
                    <x-ui.table hoverable>
                        <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                            <tr>
                                <th class="ps-4">Code</th>
                                <th>Account</th>
                                <th class="text-end pe-4">Balance</th>
                            </tr>
                        </thead>
                        <tbody class="fs-13 text-dark">
                            @forelse ($sections['equity'] as $row)
                                <tr>
                                    <td class="ps-4 fw-bold font-monospace">{{ $row['account']->code }}</td>
                                    <td>{{ $row['account']->name }}</td>
                                    <td class="text-end pe-4">{{ number_format($row['balance'], 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center py-4 text-muted">No equity balances.</td>
                                </tr>
                            @endforelse
                            <tr>
                                <td class="ps-4 text-muted">—</td>
                                <td class="text-muted">Current Year Earnings (Income − Expenses)</td>
                                <td class="text-end pe-4">{{ number_format($netIncome, 2) }}</td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="fw-bold fs-13 bg-light">
                                <td class="ps-4" colspan="2">Total Equity</td>
                                <td class="text-end pe-4">{{ number_format($totals['equity'], 2) }}</td>
                            </tr>
                        </tfoot>
                    </x-ui.table>
                </x-ui.card>
            </div>
        </div>

        <x-ui.card class="mt-4">
            <div class="d-flex justify-content-between fs-14 fw-bold">
                <span>Total Assets: {{ number_format($totals['asset'], 2) }}</span>
                <span>Total Liabilities + Equity: {{ number_format($totals['liability'] + $totals['equity'], 2) }}</span>
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
    </style>
@endpush
