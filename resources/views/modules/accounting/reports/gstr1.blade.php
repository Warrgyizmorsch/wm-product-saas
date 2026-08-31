@extends('layouts.duralux')

@section('title', 'GSTR-1 | SaaS ERP')
@section('page-title', 'GSTR-1')
@section('breadcrumb', 'Accounting / Reports / GSTR-1')

@section('content')
    <x-ui.card class="mb-4">
        <form method="GET" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label class="form-label fw-semibold fs-12 text-uppercase mb-0 text-dark">From</label>
                <input type="date" name="from" value="{{ $from->toDateString() }}" class="form-control">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold fs-12 text-uppercase mb-0 text-dark">To</label>
                <input type="date" name="to" value="{{ $to->toDateString() }}" class="form-control">
            </div>
            <div class="col-md-4">
                <x-ui.button type="submit" variant="primary" size="md">Apply</x-ui.button>
            </div>
        </form>
    </x-ui.card>

    <x-ui.card class="mb-3">
        <div class="d-flex justify-content-between align-items-center fs-13">
            <span class="text-muted">{{ $from->format('d M Y') }} &ndash; {{ $to->format('d M Y') }}</span>
            <span class="text-muted">GSTIN: {{ $filerGstin ?: 'Not configured' }}</span>
        </div>
    </x-ui.card>

    <x-ui.card title="4A/7 — B2B Regular (customer GSTIN on file)" bodyClass="p-0" class="accounting-dense mb-3">
        <x-ui.table hoverable>
            <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                <tr>
                    <th class="ps-4">Invoice #</th>
                    <th>Date</th>
                    <th>Customer</th>
                    <th>GSTIN</th>
                    <th class="text-end">Taxable Value</th>
                    <th class="text-end">CGST</th>
                    <th class="text-end">SGST</th>
                    <th class="text-end pe-4">IGST</th>
                </tr>
            </thead>
            <tbody class="fs-13 text-dark">
                @forelse ($b2b as $invoice)
                    <tr>
                        <td class="ps-4 font-monospace">{{ $invoice->invoice_number }}</td>
                        <td>{{ \Illuminate\Support\Carbon::parse($invoice->invoice_date)->format('d M Y') }}</td>
                        <td>{{ $invoice->customer?->name }}</td>
                        <td class="font-monospace text-muted">{{ $invoice->customer?->gstin }}</td>
                        <td class="text-end">{{ number_format($invoice->subtotal, 2) }}</td>
                        <td class="text-end">{{ number_format($invoice->cgst_amount, 2) }}</td>
                        <td class="text-end">{{ number_format($invoice->sgst_amount, 2) }}</td>
                        <td class="text-end pe-4">{{ number_format($invoice->igst_amount, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center py-3 text-muted">No B2B invoices in this period.</td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr class="fw-bold fs-13 bg-light">
                    <td class="ps-4" colspan="4">Total ({{ $b2bTotals['count'] }} invoices)</td>
                    <td class="text-end">{{ number_format($b2bTotals['taxable'], 2) }}</td>
                    <td class="text-end">{{ number_format($b2bTotals['cgst'], 2) }}</td>
                    <td class="text-end">{{ number_format($b2bTotals['sgst'], 2) }}</td>
                    <td class="text-end pe-4">{{ number_format($b2bTotals['igst'], 2) }}</td>
                </tr>
            </tfoot>
        </x-ui.table>
    </x-ui.card>

    <x-ui.card title="7 — B2C (Others)" bodyClass="p-0" class="accounting-dense mb-3">
        <x-ui.table hoverable>
            <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                <tr>
                    <th class="ps-4">Count</th>
                    <th class="text-end">Taxable Value</th>
                    <th class="text-end">CGST</th>
                    <th class="text-end">SGST</th>
                    <th class="text-end pe-4">IGST</th>
                </tr>
            </thead>
            <tbody class="fs-13 text-dark">
                <tr>
                    <td class="ps-4">{{ $b2cTotals['count'] }} invoices</td>
                    <td class="text-end">{{ number_format($b2cTotals['taxable'], 2) }}</td>
                    <td class="text-end">{{ number_format($b2cTotals['cgst'], 2) }}</td>
                    <td class="text-end">{{ number_format($b2cTotals['sgst'], 2) }}</td>
                    <td class="text-end pe-4">{{ number_format($b2cTotals['igst'], 2) }}</td>
                </tr>
            </tbody>
        </x-ui.table>
    </x-ui.card>

    <x-ui.card title="9B — Credit/Debit Notes (Registered — CDNR)" bodyClass="p-0" class="accounting-dense mb-3">
        <x-ui.table hoverable>
            <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                <tr>
                    <th class="ps-4">Return #</th>
                    <th>Date</th>
                    <th>Customer</th>
                    <th class="text-end">Taxable Value</th>
                    <th class="text-end">CGST</th>
                    <th class="text-end">SGST</th>
                    <th class="text-end pe-4">IGST</th>
                </tr>
            </thead>
            <tbody class="fs-13 text-dark">
                @forelse ($b2bReturns as $row)
                    <tr>
                        <td class="ps-4 font-monospace">{{ $row['return']->return_number }}</td>
                        <td>{{ \Illuminate\Support\Carbon::parse($row['return']->return_date)->format('d M Y') }}</td>
                        <td>{{ $row['return']->customer?->name }}</td>
                        <td class="text-end">{{ number_format($row['split']['taxable'], 2) }}</td>
                        <td class="text-end">{{ number_format($row['split']['cgst'], 2) }}</td>
                        <td class="text-end">{{ number_format($row['split']['sgst'], 2) }}</td>
                        <td class="text-end pe-4">{{ number_format($row['split']['igst'], 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center py-3 text-muted">No registered credit notes in this period.</td>
                    </tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr class="fw-bold fs-13 bg-light">
                    <td class="ps-4" colspan="3">Total ({{ $b2bReturnTotals['count'] }})</td>
                    <td class="text-end">{{ number_format($b2bReturnTotals['taxable'], 2) }}</td>
                    <td class="text-end">{{ number_format($b2bReturnTotals['cgst'], 2) }}</td>
                    <td class="text-end">{{ number_format($b2bReturnTotals['sgst'], 2) }}</td>
                    <td class="text-end pe-4">{{ number_format($b2bReturnTotals['igst'], 2) }}</td>
                </tr>
            </tfoot>
        </x-ui.table>
    </x-ui.card>

    <x-ui.card title="9B — Credit/Debit Notes (Unregistered — CDNUR)" bodyClass="p-0" class="accounting-dense">
        <x-ui.table hoverable>
            <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                <tr>
                    <th class="ps-4">Count</th>
                    <th class="text-end">Taxable Value</th>
                    <th class="text-end">CGST</th>
                    <th class="text-end">SGST</th>
                    <th class="text-end pe-4">IGST</th>
                </tr>
            </thead>
            <tbody class="fs-13 text-dark">
                <tr>
                    <td class="ps-4">{{ $b2cReturnTotals['count'] }} notes</td>
                    <td class="text-end">{{ number_format($b2cReturnTotals['taxable'], 2) }}</td>
                    <td class="text-end">{{ number_format($b2cReturnTotals['cgst'], 2) }}</td>
                    <td class="text-end">{{ number_format($b2cReturnTotals['sgst'], 2) }}</td>
                    <td class="text-end pe-4">{{ number_format($b2cReturnTotals['igst'], 2) }}</td>
                </tr>
            </tbody>
        </x-ui.table>
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
