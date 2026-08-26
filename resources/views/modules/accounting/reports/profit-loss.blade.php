@extends('layouts.duralux')

@section('title', 'Profit & Loss | SaaS ERP')
@section('page-title', 'Profit & Loss')
@section('breadcrumb', 'Accounting / Reports / Profit & Loss')

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
            <div class="fs-13 text-muted">
                {{ \Illuminate\Support\Carbon::parse($period->start_date)->format('d M Y') }}
                &ndash;
                {{ \Illuminate\Support\Carbon::parse($period->end_date)->format('d M Y') }}
                ({{ $period->name }})
            </div>
        </x-ui.card>

        <div class="row g-4">
            <div class="col-lg-7">
                @foreach ([
                    ['key' => \App\Domains\Accounting\Models\ChartOfAccount::SUBTYPE_DIRECT_INCOME, 'label' => 'Direct Income', 'total' => $directIncome],
                    ['key' => \App\Domains\Accounting\Models\ChartOfAccount::SUBTYPE_COGS, 'label' => 'Cost of Goods Sold', 'total' => $cogs],
                    ['key' => \App\Domains\Accounting\Models\ChartOfAccount::SUBTYPE_OPERATING_EXPENSE, 'label' => 'Operating Expenses', 'total' => $operatingExpense],
                    ['key' => \App\Domains\Accounting\Models\ChartOfAccount::SUBTYPE_INDIRECT_INCOME, 'label' => 'Indirect Income', 'total' => $indirectIncome],
                    ['key' => \App\Domains\Accounting\Models\ChartOfAccount::SUBTYPE_INDIRECT_EXPENSE, 'label' => 'Indirect Expenses', 'total' => $indirectExpense],
                ] as $group)
                    <x-ui.card :title="$group['label']" bodyClass="p-0" class="accounting-dense mb-3">
                        <x-ui.table hoverable>
                            <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                                <tr>
                                    <th class="ps-4">Code</th>
                                    <th>Account</th>
                                    <th class="text-end pe-4">Amount</th>
                                </tr>
                            </thead>
                            <tbody class="fs-13 text-dark">
                                @forelse ($sections[$group['key']] as $row)
                                    <tr>
                                        <td class="ps-4 fw-bold font-monospace">{{ $row['account']->code }}</td>
                                        <td>{{ $row['account']->name }}</td>
                                        <td class="text-end pe-4">{{ number_format($row['amount'], 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center py-3 text-muted">No activity.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                            <tfoot>
                                <tr class="fw-bold fs-13 bg-light">
                                    <td class="ps-4" colspan="2">Total {{ $group['label'] }}</td>
                                    <td class="text-end pe-4">{{ number_format($group['total'], 2) }}</td>
                                </tr>
                            </tfoot>
                        </x-ui.table>
                    </x-ui.card>
                @endforeach
            </div>

            <div class="col-lg-5">
                <x-ui.card title="Summary" bodyClass="p-0" class="accounting-dense">
                    <x-ui.table>
                        <tbody class="fs-13 text-dark">
                            <tr>
                                <td class="ps-4">Direct Income</td>
                                <td class="text-end pe-4">{{ number_format($directIncome, 2) }}</td>
                            </tr>
                            <tr>
                                <td class="ps-4">Cost of Goods Sold</td>
                                <td class="text-end pe-4">({{ number_format($cogs, 2) }})</td>
                            </tr>
                            <tr class="fw-semibold table-light">
                                <td class="ps-4">Gross Profit</td>
                                <td class="text-end pe-4">{{ number_format($grossProfit, 2) }}</td>
                            </tr>
                            <tr>
                                <td class="ps-4">Operating Expenses</td>
                                <td class="text-end pe-4">({{ number_format($operatingExpense, 2) }})</td>
                            </tr>
                            <tr class="fw-semibold table-light">
                                <td class="ps-4">Operating Profit</td>
                                <td class="text-end pe-4">{{ number_format($operatingProfit, 2) }}</td>
                            </tr>
                            <tr>
                                <td class="ps-4">Indirect Income</td>
                                <td class="text-end pe-4">{{ number_format($indirectIncome, 2) }}</td>
                            </tr>
                            <tr>
                                <td class="ps-4">Indirect Expenses</td>
                                <td class="text-end pe-4">({{ number_format($indirectExpense, 2) }})</td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="fw-bold fs-14 bg-light">
                                <td class="ps-4">Net Profit</td>
                                <td class="text-end pe-4">{{ number_format($netProfit, 2) }}</td>
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
