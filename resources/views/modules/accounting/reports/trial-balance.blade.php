@extends('layouts.duralux')

@section('title', 'Trial Balance | SaaS ERP')
@section('page-title', 'Trial Balance')
@section('breadcrumb', 'Accounting / Reports / Trial Balance')

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
                        <td class="ps-4 fw-bold font-monospace">{{ $row['account']?->code }}</td>
                        <td>{{ $row['account']?->name }}</td>
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
