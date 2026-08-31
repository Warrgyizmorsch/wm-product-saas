@extends('layouts.duralux')

@section('title', 'GST Summary | SaaS ERP')
@section('page-title', 'GST Summary')
@section('breadcrumb', 'Accounting / Reports / GST Summary')

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

    <x-ui.card bodyClass="p-0" class="accounting-dense">
        <x-ui.table hoverable>
            <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                <tr>
                    <th class="ps-4">Details</th>
                    <th class="text-end">Taxable Amt.</th>
                    <th class="text-end">CGST</th>
                    <th class="text-end">SGST</th>
                    <th class="text-end pe-4">IGST</th>
                </tr>
            </thead>
            <tbody class="fs-13 text-dark">
                <tr class="table-light">
                    <td class="ps-4 fw-bold" colspan="5">Output GST (Sales, net of Credit Notes)</td>
                </tr>
                <tr>
                    <td class="ps-4">Output GST</td>
                    <td class="text-end">{{ number_format($output['taxable'], 2) }}</td>
                    <td class="text-end">{{ number_format($output['cgst'], 2) }}</td>
                    <td class="text-end">{{ number_format($output['sgst'], 2) }}</td>
                    <td class="text-end pe-4">{{ number_format($output['igst'], 2) }}</td>
                </tr>

                <tr class="table-light">
                    <td class="ps-4 fw-bold" colspan="5">Input GST (Purchases, net of Debit Notes)</td>
                </tr>
                <tr>
                    <td class="ps-4">Input GST</td>
                    <td class="text-end">{{ number_format($input['taxable'], 2) }}</td>
                    <td class="text-end">{{ number_format($input['cgst'], 2) }}</td>
                    <td class="text-end">{{ number_format($input['sgst'], 2) }}</td>
                    <td class="text-end pe-4">{{ number_format($input['igst'], 2) }}</td>
                </tr>

                <tr class="table-light">
                    <td class="ps-4 fw-bold" colspan="5">GST Payable</td>
                </tr>
                <tr>
                    <td class="ps-4">GST Payable (Output &minus; Input)</td>
                    <td class="text-end">&mdash;</td>
                    <td class="text-end">{{ number_format($payable['cgst'], 2) }}</td>
                    <td class="text-end">{{ number_format($payable['sgst'], 2) }}</td>
                    <td class="text-end pe-4">{{ number_format($payable['igst'], 2) }}</td>
                </tr>
            </tbody>
            <tfoot>
                <tr class="fw-bold fs-13 bg-light">
                    <td class="ps-4">Total Payable</td>
                    <td class="text-end" colspan="4">{{ number_format($payable['total'], 2) }}</td>
                </tr>
            </tfoot>
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
