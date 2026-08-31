@extends('layouts.duralux')

@section('title', 'GSTR-3B | SaaS ERP')
@section('page-title', 'GSTR-3B')
@section('breadcrumb', 'Accounting / Reports / GSTR-3B')

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

    <x-ui.card title="3.1 — Outward Taxable Supplies (net of Credit Notes)" bodyClass="p-0" class="accounting-dense mb-3">
        <x-ui.table hoverable>
            <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                <tr>
                    <th class="ps-4">Nature of Supply</th>
                    <th class="text-end">Taxable Value</th>
                    <th class="text-end">IGST</th>
                    <th class="text-end">CGST</th>
                    <th class="text-end pe-4">SGST</th>
                </tr>
            </thead>
            <tbody class="fs-13 text-dark">
                <tr>
                    <td class="ps-4">Outward taxable supplies (other than zero rated, nil rated and exempted)</td>
                    <td class="text-end">{{ number_format($outward['taxable'], 2) }}</td>
                    <td class="text-end">{{ number_format($outward['igst'], 2) }}</td>
                    <td class="text-end">{{ number_format($outward['cgst'], 2) }}</td>
                    <td class="text-end pe-4">{{ number_format($outward['sgst'], 2) }}</td>
                </tr>
            </tbody>
        </x-ui.table>
    </x-ui.card>

    <x-ui.card title="4 — Input Tax Credit Available (net of Debit Notes)" bodyClass="p-0" class="accounting-dense mb-3">
        <x-ui.table hoverable>
            <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                <tr>
                    <th class="ps-4">Details</th>
                    <th class="text-end">Taxable Value</th>
                    <th class="text-end">IGST</th>
                    <th class="text-end">CGST</th>
                    <th class="text-end pe-4">SGST</th>
                </tr>
            </thead>
            <tbody class="fs-13 text-dark">
                <tr>
                    <td class="ps-4">ITC available on inward supplies</td>
                    <td class="text-end">{{ number_format($itc['taxable'], 2) }}</td>
                    <td class="text-end">{{ number_format($itc['igst'], 2) }}</td>
                    <td class="text-end">{{ number_format($itc['cgst'], 2) }}</td>
                    <td class="text-end pe-4">{{ number_format($itc['sgst'], 2) }}</td>
                </tr>
            </tbody>
        </x-ui.table>
    </x-ui.card>

    <x-ui.card title="Net GST Payable" bodyClass="p-0" class="accounting-dense">
        <x-ui.table hoverable>
            <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                <tr>
                    <th class="ps-4">Tax Head</th>
                    <th class="text-end pe-4">Amount</th>
                </tr>
            </thead>
            <tbody class="fs-13 text-dark">
                <tr>
                    <td class="ps-4">IGST</td>
                    <td class="text-end pe-4">{{ number_format($netPayable['igst'], 2) }}</td>
                </tr>
                <tr>
                    <td class="ps-4">CGST</td>
                    <td class="text-end pe-4">{{ number_format($netPayable['cgst'], 2) }}</td>
                </tr>
                <tr>
                    <td class="ps-4">SGST</td>
                    <td class="text-end pe-4">{{ number_format($netPayable['sgst'], 2) }}</td>
                </tr>
            </tbody>
            <tfoot>
                <tr class="fw-bold fs-13 bg-light">
                    <td class="ps-4">Total Net Payable</td>
                    <td class="text-end pe-4">{{ number_format($netPayable['total'], 2) }}</td>
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
