@extends('layouts.duralux')

@section('title', 'Cash Flow Statement | SaaS ERP')
@section('page-title', 'Cash Flow Statement')
@section('breadcrumb', 'Accounting / Reports / Cash Flow')

@section('content')
    <x-ui.card class="mb-4">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-6">
                <x-ui.select label="Accounting Period" name="period_id" onchange="this.form.submit()" :options="$allPeriods->mapWithKeys(fn ($p) => [
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
                <span class="text-muted">
                    {{ \Illuminate\Support\Carbon::parse($period->start_date)->format('d M Y') }}
                    &ndash;
                    {{ \Illuminate\Support\Carbon::parse($period->end_date)->format('d M Y') }}
                    ({{ $period->name }})
                </span>
                @if ($isReconciled)
                    <x-ui.badge variant="success" soft>Reconciled to Cash & Bank</x-ui.badge>
                @else
                    <x-ui.badge variant="danger" soft>Out of Balance</x-ui.badge>
                @endif
            </div>
        </x-ui.card>

        <div class="row g-4">
            <div class="col-lg-8">
                <x-ui.card title="Operating Activities" bodyClass="p-0" class="accounting-dense mb-3">
                    <x-ui.table hoverable>
                        <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                            <tr>
                                <th class="ps-4">Code</th>
                                <th>Account</th>
                                <th class="text-end pe-4">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="fs-13 text-dark">
                            <tr>
                                <td class="ps-4 text-muted">—</td>
                                <td class="text-muted">Net Profit for the Period</td>
                                <td class="text-end pe-4">{{ number_format($netProfit, 2) }}</td>
                            </tr>
                            @forelse ($operating as $row)
                                <tr>
                                    <td class="ps-4 fw-bold font-monospace">{{ $row['account']->code }}</td>
                                    <td>{{ $row['account']->name }}</td>
                                    <td class="text-end pe-4">{{ number_format($row['amount'], 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center py-3 text-muted">No working-capital movement.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr class="fw-bold fs-13 bg-light">
                                <td class="ps-4" colspan="2">Net Cash from Operating Activities</td>
                                <td class="text-end pe-4">{{ number_format($operatingTotal, 2) }}</td>
                            </tr>
                        </tfoot>
                    </x-ui.table>
                </x-ui.card>

                <x-ui.card title="Investing Activities" bodyClass="p-0" class="accounting-dense mb-3">
                    <x-ui.table hoverable>
                        <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                            <tr>
                                <th class="ps-4">Code</th>
                                <th>Account</th>
                                <th class="text-end pe-4">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="fs-13 text-dark">
                            @forelse ($investing as $row)
                                <tr>
                                    <td class="ps-4 fw-bold font-monospace">{{ $row['account']->code }}</td>
                                    <td>{{ $row['account']->name }}</td>
                                    <td class="text-end pe-4">{{ number_format($row['amount'], 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center py-3 text-muted">No investing activity.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr class="fw-bold fs-13 bg-light">
                                <td class="ps-4" colspan="2">Net Cash from Investing Activities</td>
                                <td class="text-end pe-4">{{ number_format($investingTotal, 2) }}</td>
                            </tr>
                        </tfoot>
                    </x-ui.table>
                </x-ui.card>

                <x-ui.card title="Financing Activities" bodyClass="p-0" class="accounting-dense">
                    <x-ui.table hoverable>
                        <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                            <tr>
                                <th class="ps-4">Code</th>
                                <th>Account</th>
                                <th class="text-end pe-4">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="fs-13 text-dark">
                            @forelse ($financing as $row)
                                <tr>
                                    <td class="ps-4 fw-bold font-monospace">{{ $row['account']->code }}</td>
                                    <td>{{ $row['account']->name }}</td>
                                    <td class="text-end pe-4">{{ number_format($row['amount'], 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center py-3 text-muted">No financing activity.</td>
                                </tr>
                            @endforelse
                        </tbody>
                        <tfoot>
                            <tr class="fw-bold fs-13 bg-light">
                                <td class="ps-4" colspan="2">Net Cash from Financing Activities</td>
                                <td class="text-end pe-4">{{ number_format($financingTotal, 2) }}</td>
                            </tr>
                        </tfoot>
                    </x-ui.table>
                </x-ui.card>
            </div>

            <div class="col-lg-4">
                <x-ui.card title="Summary" bodyClass="p-0" class="accounting-dense">
                    <x-ui.table>
                        <tbody class="fs-13 text-dark">
                            <tr>
                                <td class="ps-4">Operating Activities</td>
                                <td class="text-end pe-4">{{ number_format($operatingTotal, 2) }}</td>
                            </tr>
                            <tr>
                                <td class="ps-4">Investing Activities</td>
                                <td class="text-end pe-4">{{ number_format($investingTotal, 2) }}</td>
                            </tr>
                            <tr>
                                <td class="ps-4">Financing Activities</td>
                                <td class="text-end pe-4">{{ number_format($financingTotal, 2) }}</td>
                            </tr>
                            <tr class="fw-semibold table-light">
                                <td class="ps-4">Net Change in Cash</td>
                                <td class="text-end pe-4">{{ number_format($netChangeInCash, 2) }}</td>
                            </tr>
                            <tr>
                                <td class="ps-4">Opening Cash & Bank Balance</td>
                                <td class="text-end pe-4">{{ number_format($openingCash, 2) }}</td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="fw-bold fs-14 bg-light">
                                <td class="ps-4">Closing Cash & Bank Balance</td>
                                <td class="text-end pe-4">{{ number_format($closingCash, 2) }}</td>
                            </tr>
                        </tfoot>
                    </x-ui.table>
                </x-ui.card>
            </div>
        </div>
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
