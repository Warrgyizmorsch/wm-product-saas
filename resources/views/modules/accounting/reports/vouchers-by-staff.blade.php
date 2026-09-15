@extends('layouts.duralux')

@section('title', 'Vouchers by Staff | SaaS ERP')
@section('page-title', 'Vouchers by Staff')
@section('breadcrumb', 'Accounting / Reports / Vouchers by Staff')

@section('page-actions')
    @include('modules.accounting.reports.partials.export-buttons', ['report' => 'vouchers-by-staff'])
@endsection

@section('content')
    <x-ui.card class="mb-4">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label fw-semibold fs-12 text-uppercase mb-0 text-dark" for="from">From</label>
                <input type="date" id="from" name="from" value="{{ $from->toDateString() }}" class="form-control">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold fs-12 text-uppercase mb-0 text-dark" for="to">To</label>
                <input type="date" id="to" name="to" value="{{ $to->toDateString() }}" class="form-control">
            </div>
            <div class="col-md-auto">
                <x-ui.button type="submit" variant="primary">Apply</x-ui.button>
            </div>
            <div class="col-md text-md-end fs-13 text-muted">
                Posted and reversed journals &amp; vouchers dated {{ $from->format('d M Y') }} – {{ $to->format('d M Y') }}
            </div>
        </form>
    </x-ui.card>

    <x-ui.card bodyClass="p-0" class="accounting-dense">
        <div class="table-responsive">
            <x-ui.table hoverable class="mb-0">
                <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                    <tr>
                        <th class="ps-4">Staff</th>
                        @foreach ($documentTypes as $label)
                            <th class="text-end">{{ $label }}</th>
                        @endforeach
                        <th class="text-end">Total</th>
                        <th class="text-end">Amount</th>
                        <th class="text-end">Reversed</th>
                        <th class="text-end pe-4">Bank Reconciliations</th>
                    </tr>
                </thead>
                <tbody class="fs-13 text-dark">
                    @forelse ($rows as $row)
                        <tr>
                            <td class="ps-4 fw-semibold">
                                @if ($row['key'] === \App\Domains\Accounting\Services\StaffActivityReportService::SYSTEM)
                                    <span class="text-muted">{{ $row['name'] }}</span>
                                @else
                                    <a href="{{ route('accounting.journals.index', ['posted_by' => $row['key'], 'from' => $from->toDateString(), 'to' => $to->toDateString()]) }}">{{ $row['name'] }}</a>
                                @endif
                            </td>
                            @foreach ($documentTypes as $type => $label)
                                <td class="text-end {{ $row['counts'][$type] === 0 ? 'text-muted' : '' }}">{{ $row['counts'][$type] }}</td>
                            @endforeach
                            <td class="text-end fw-semibold">{{ $row['documents'] }}</td>
                            <td class="text-end">{{ number_format($row['amount'], 2) }}</td>
                            <td class="text-end {{ $row['reversed'] > 0 ? 'text-danger' : 'text-muted' }}">{{ $row['reversed'] }}</td>
                            <td class="text-end pe-4 {{ $row['reconciliations'] === 0 ? 'text-muted' : '' }}">{{ $row['reconciliations'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($documentTypes) + 5 }}" class="text-center py-5 text-muted">
                                <i class="feather-users fs-1 mb-2 d-block"></i>
                                Nothing was posted in this period.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if ($rows !== [])
                    <tfoot>
                        <tr class="fw-bold fs-13 bg-light">
                            <td class="ps-4">Total</td>
                            @foreach ($documentTypes as $type => $label)
                                <td class="text-end">{{ $totals['counts'][$type] }}</td>
                            @endforeach
                            <td class="text-end">{{ $totals['documents'] }}</td>
                            <td class="text-end">{{ number_format($totals['amount'], 2) }}</td>
                            <td class="text-end">{{ $totals['reversed'] }}</td>
                            <td class="text-end pe-4">{{ $totals['reconciliations'] }}</td>
                        </tr>
                    </tfoot>
                @endif
            </x-ui.table>
        </div>
    </x-ui.card>
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
