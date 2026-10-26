@extends('layouts.duralux')

@section('title', 'TDS Receivable | SaaS ERP')
@section('page-title', 'TDS Receivable')
@section('breadcrumb', 'Accounting / TDS / Receivable')

@include('modules.accounting.tds._filter-styles')

@section('content')
    <x-ui.card bodyClass="p-3" class="mb-3">
        <form method="GET" class="tds-filters">
            <div class="tds-filter">
                <label for="tdsFy">Financial year</label>
                <select name="fy" id="tdsFy" class="form-select" data-no-search>
                    @foreach ($fiscalYears as $year)
                        <option value="{{ $year }}" @selected($year === $fy)>{{ $year }}</option>
                    @endforeach
                </select>
            </div>
            <div class="tds-filter">
                <label for="tdsCertificate">Form 16A</label>
                <select name="certificate" id="tdsCertificate" class="form-select" data-no-search>
                    <option value="">All</option>
                    <option value="pending" @selected(request('certificate') === 'pending')>Not received</option>
                </select>
            </div>
            <x-ui.button type="submit" variant="primary">Show</x-ui.button>
            <x-ui.button href="{{ route('accounting.tds.receivable') }}" variant="light" class="border" icon="feather-rotate-ccw">Reset</x-ui.button>
            <div class="tds-filter-summary">
                <div class="fs-11 text-uppercase text-muted fw-bold">TDS withheld by customers</div>
                <div class="fs-5 fw-bold">{{ format_currency((float) $receipts->sum('tds_amount')) }}</div>
            </div>
        </form>
    </x-ui.card>

    <x-ui.card title="Receipts with TDS deducted" bodyClass="p-0" class="accounting-dense">
        <x-ui.table hoverable>
            <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                <tr><th class="ps-3">Date</th><th>Receipt</th><th>Customer</th><th>Section</th><th class="text-end">Received</th><th class="text-end">TDS</th><th>Quarter</th><th class="pe-3">Form 16A</th></tr>
            </thead>
            <tbody class="fs-13">
                @forelse ($receipts as $receipt)
                    <tr>
                        <td class="ps-3">{{ \Illuminate\Support\Carbon::parse($receipt->payment_date)->format('d M Y') }}</td>
                        <td><a href="{{ route('sales.payments.show', $receipt->id) }}">{{ $receipt->payment_number }}</a></td>
                        <td>{{ $receipt->customer?->name }}</td>
                        <td>{{ $receipt->tds_section_code ?: '—' }}</td>
                        <td class="text-end">{{ format_currency((float) $receipt->amount) }}</td>
                        <td class="text-end fw-semibold">{{ format_currency((float) $receipt->tds_amount) }}</td>
                        <td>Q{{ \App\Domains\Accounting\Models\TdsDeduction::quarterFor($receipt->payment_date) }}</td>
                        <td class="pe-3">
                            @if ($canConfigure)
                                <form method="POST" action="{{ route('accounting.tds.receivable.certificate', $receipt->id) }}" class="d-inline">
                                    @csrf
                                    <input type="hidden" name="received" value="{{ $receipt->tds_certificate_received ? 0 : 1 }}">
                                    <button type="submit" class="btn btn-sm {{ $receipt->tds_certificate_received ? 'btn-soft-success' : 'btn-light border' }}">
                                        {{ $receipt->tds_certificate_received ? 'Received' : 'Mark received' }}
                                    </button>
                                </form>
                            @else
                                {{ $receipt->tds_certificate_received ? 'Received' : 'Pending' }}
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center py-4 text-muted">No customer receipts with TDS in this year.</td></tr>
                @endforelse
            </tbody>
        </x-ui.table>
    </x-ui.card>
    <p class="small text-muted mt-2">These amounts sit in TDS Receivable (1640) and are claimed against your income tax. Match them with Form 26AS before filing.</p>
@endsection

@push('styles')
    <style>
        .accounting-dense table th, .accounting-dense table td { padding: 6px 10px !important; font-size: 12px !important; }
    </style>
@endpush
