@extends('layouts.duralux')

@section('title', 'AR Aging | SaaS ERP')
@section('page-title', 'Accounts Receivable Aging')
@section('breadcrumb', 'Accounting / Reports / AR Aging')

@section('content')
    <x-ui.card class="mb-4">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label fs-12 fw-semibold text-muted">As Of Date</label>
                <input type="date" name="as_of" class="form-control" value="{{ $asOf->toDateString() }}" onchange="this.form.submit()">
            </div>
        </form>
    </x-ui.card>

    <x-ui.card title="Outstanding by Age" bodyClass="p-0" class="accounting-dense">
        <x-ui.table hoverable>
            <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                <tr>
                    <th class="ps-4">Customer</th>
                    <th class="text-end">Not Due</th>
                    <th class="text-end">0-30 Days</th>
                    <th class="text-end">31-60 Days</th>
                    <th class="text-end">61-90 Days</th>
                    <th class="text-end">90+ Days</th>
                    <th class="text-end pe-4">Total</th>
                </tr>
            </thead>
            <tbody class="fs-13 text-dark">
                @forelse ($customers as $row)
                    <tr>
                        <td class="ps-4 fw-semibold">{{ $row['name'] }}</td>
                        <td class="text-end">{{ number_format($row['buckets']['not_due'], 2) }}</td>
                        <td class="text-end">{{ number_format($row['buckets']['0_30'], 2) }}</td>
                        <td class="text-end">{{ number_format($row['buckets']['31_60'], 2) }}</td>
                        <td class="text-end">{{ number_format($row['buckets']['61_90'], 2) }}</td>
                        <td class="text-end">{{ number_format($row['buckets']['90_plus'], 2) }}</td>
                        <td class="text-end pe-4 fw-bold">{{ number_format($row['total'], 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">No outstanding receivables.</td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr class="fw-bold fs-13 bg-light">
                    <td class="ps-4">Total</td>
                    <td class="text-end">{{ number_format($buckets['not_due'], 2) }}</td>
                    <td class="text-end">{{ number_format($buckets['0_30'], 2) }}</td>
                    <td class="text-end">{{ number_format($buckets['31_60'], 2) }}</td>
                    <td class="text-end">{{ number_format($buckets['61_90'], 2) }}</td>
                    <td class="text-end">{{ number_format($buckets['90_plus'], 2) }}</td>
                    <td class="text-end pe-4">{{ number_format($grandTotal, 2) }}</td>
                </tr>
            </tfoot>
        </x-ui.table>
    </x-ui.card>

    @if (count($customers))
        <x-ui.card title="Invoice Detail" bodyClass="p-0" class="accounting-dense mt-4">
            <x-ui.table hoverable>
                <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                    <tr>
                        <th class="ps-4">Customer</th>
                        <th>Invoice #</th>
                        <th>Due Date</th>
                        <th class="text-end">Days Overdue</th>
                        <th>Bucket</th>
                        <th class="text-end pe-4">Balance</th>
                    </tr>
                </thead>
                <tbody class="fs-13 text-dark">
                    @foreach ($customers as $row)
                        @foreach ($row['invoices'] as $item)
                            <tr>
                                <td class="ps-4">{{ $row['name'] }}</td>
                                <td class="font-monospace">{{ $item['invoice']->invoice_number }}</td>
                                <td>{{ $item['due_date']?->format('d M Y') ?? '—' }}</td>
                                <td class="text-end">{{ $item['days_overdue'] }}</td>
                                <td>{{ str_replace('_', '-', $item['bucket']) }}</td>
                                <td class="text-end pe-4">{{ number_format($item['balance'], 2) }}</td>
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </x-ui.table>
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
