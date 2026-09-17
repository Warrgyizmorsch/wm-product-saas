@extends('layouts.duralux')

@section('title', 'Trial Balance | SaaS ERP')
@section('page-title', 'Trial Balance')
@section('breadcrumb', 'Accounting / Reports / Trial Balance')

@section('page-actions')
    @include('modules.accounting.reports.partials.export-buttons', ['report' => 'trial-balance'])
@endsection

@section('content')
    <x-ui.card class="mb-4">
        <x-ui.filter-toolbar :resetUrl="route('accounting.reports.trial-balance')" searchLabel="View">
            <x-ui.filter-field label="Accounting Period" col="col-md-6">
                <select name="period_id" class="form-select form-select-sm">
                    @foreach ($allPeriods as $p)
                        <option value="{{ $p->id }}" @selected($period?->id == $p->id)>{{ $p->fiscalYear?->name }} — {{ $p->name }} ({{ $p->status }})</option>
                    @endforeach
                </select>
            </x-ui.filter-field>
            <x-ui.filter-field label="Cost Center" col="col-md-6">
                <select name="cost_center_id" class="form-select form-select-sm">
                    <option value="" @selected(!$costCenterId)>All Cost Centers</option>
                    @foreach ($costCenters as $c)
                        <option value="{{ $c->id }}" @selected($costCenterId == $c->id)>{{ $c->code }} — {{ $c->name }}</option>
                    @endforeach
                </select>
            </x-ui.filter-field>
        </x-ui.filter-toolbar>
    </x-ui.card>

    <x-ui.card title="Trial Balance{{ $period ? ' — ' . $period->name : '' }}" bodyClass="p-0">
        <x-ui.table hoverable>
            <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                <tr>
                    <th class="ps-4">Code</th>
                    <th>Account</th>
                    <th class="text-capitalize">Type</th>
                    <th class="text-end">Debit</th>
                    <th class="text-end pe-4">Credit</th>
                </tr>
            </thead>
            <tbody class="fs-13 text-dark">
                @forelse ($rows as $row)
                    <tr>
                        @if ($row['account'])
                            <td class="ps-4 fw-bold font-monospace">
                                <a href="{{ route('accounting.reports.general-ledger', ['chart_of_account_id' => $row['account']->id, 'period_id' => $period->id]) }}">{{ $row['account']->code }}</a>
                            </td>
                            <td>
                                <a href="{{ route('accounting.reports.general-ledger', ['chart_of_account_id' => $row['account']->id, 'period_id' => $period->id]) }}" class="text-dark">{{ $row['account']->name }}</a>
                            </td>
                        @else
                            <td class="ps-4 fw-bold font-monospace"></td>
                            <td></td>
                        @endif
                        <td class="text-capitalize text-muted">{{ $row['account']?->type }}</td>
                        <td class="text-end">{{ $row['debit'] > 0 ? number_format($row['debit'], 2) : '—' }}</td>
                        <td class="text-end pe-4">{{ $row['credit'] > 0 ? number_format($row['credit'], 2) : '—' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center py-5 text-muted">
                            <i class="feather-bar-chart-2 fs-1 mb-2 d-block"></i>
                            @if (!$period)
                                No accounting periods exist yet.
                            @else
                                No posted activity in this period.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
            @if ($rows->isNotEmpty())
                <tfoot>
                    <tr class="fw-bold fs-13 bg-light">
                        <td class="ps-4" colspan="3">Total</td>
                        <td class="text-end">{{ number_format($totals['debit'], 2) }}</td>
                        <td class="text-end pe-4">{{ number_format($totals['credit'], 2) }}</td>
                    </tr>
                </tfoot>
            @endif
        </x-ui.table>
    </x-ui.card>
@endsection
