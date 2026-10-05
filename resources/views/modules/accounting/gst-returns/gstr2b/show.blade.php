@extends('layouts.duralux')

@section('title', 'GSTR-2B ' . $import->periodLabel() . ' | SaaS ERP')
@section('page-title', 'GSTR-2B Reconciliation — ' . $import->periodLabel())
@section('breadcrumb', 'Accounting / GST Returns / GSTR-2B Reconciliation')

@php
    $money = fn ($v) => number_format((float) $v, 2);
    $tabs = [
        'all' => ['All documents', null, 'secondary'],
        'mismatch' => ['Mismatch', $summary['mismatch']['count'] ?? 0, 'warning'],
        'missing_in_books' => ['Not in your books', $summary['missing_in_books']['count'] ?? 0, 'danger'],
        'books_only' => ['Not in 2B', $summary['books_only']['count'] ?? 0, 'primary'],
        'matched' => ['Matched', $summary['matched']['count'] ?? 0, 'success'],
        'note' => ['Credit / debit notes', $summary['note']['count'] ?? 0, 'info'],
    ];
    $statusBadge = [
        'matched' => ['Matched', 'success'],
        'mismatch' => ['Mismatch', 'warning'],
        'missing_in_books' => ['Not in books', 'danger'],
        'note' => ['Review', 'info'],
    ];
@endphp

@section('content')
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div class="fs-12 text-muted">
            GSTIN <span class="text-dark fw-semibold">{{ $import->gstin ?? '—' }}</span>
            · Generated on portal {{ $import->generated_on?->format('d M Y') ?? '—' }}
            · {{ $import->file_name }}
            · Matched {{ $import->matched_at?->diffForHumans() ?? '—' }}
        </div>
        <div class="d-flex align-items-center gap-2">
            <x-ui.button href="{{ route('accounting.gst-returns.gstr2b.index') }}" variant="light-brand" size="sm" icon="feather-arrow-left">All periods</x-ui.button>
            @if ($canFile)
                <form method="POST" action="{{ route('accounting.gst-returns.gstr2b.rematch', $import->id) }}" class="d-inline">
                    @csrf
                    <x-ui.button type="submit" variant="primary" size="sm" icon="feather-refresh-cw">Re-run matching</x-ui.button>
                </form>
                <form method="POST" action="{{ route('accounting.gst-returns.gstr2b.destroy', $import->id) }}" class="d-inline">
                    @csrf
                    @method('DELETE')
                    <x-ui.button type="button" variant="light-brand" size="sm" icon="feather-trash-2"
                        data-confirm-title="Remove GSTR-2B"
                        data-confirm-message="Remove the GSTR-2B upload for {{ $import->periodLabel() }}? Your bills are not affected.">Remove</x-ui.button>
                </form>
            @endif
        </div>
    </div>

    <div class="row g-3 mb-1">
        <div class="col-xl col-md-4 col-sm-6">
            <x-ui.stat-widget variant="compact" color="success" icon="feather-check-circle" title="Matched"
                :value="$summary['matched']['count'] ?? 0" :subtitle="'Tax ' . $money($summary['matched']['tax'] ?? 0)" />
        </div>
        <div class="col-xl col-md-4 col-sm-6">
            <x-ui.stat-widget variant="compact" color="warning" icon="feather-alert-triangle" title="Mismatch"
                :value="$summary['mismatch']['count'] ?? 0" :subtitle="'Tax ' . $money($summary['mismatch']['tax'] ?? 0)" />
        </div>
        <div class="col-xl col-md-4 col-sm-6">
            <x-ui.stat-widget variant="compact" color="danger" icon="feather-file-minus" title="Not in your books"
                :value="$summary['missing_in_books']['count'] ?? 0" :subtitle="'Tax ' . $money($summary['missing_in_books']['tax'] ?? 0)" />
        </div>
        <div class="col-xl col-md-6 col-sm-6">
            <x-ui.stat-widget variant="compact" color="primary" icon="feather-clock" title="Not in 2B"
                :value="$summary['books_only']['count'] ?? 0" :subtitle="'Vendor not filed · ITC ' . $money($summary['books_only']['tax'] ?? 0)" />
        </div>
        <div class="col-xl col-md-6 col-sm-12">
            <x-ui.stat-widget variant="compact" color="info" icon="feather-percent" title="ITC as per 2B"
                :value="$money($summary['itc_as_per_2b'] ?? 0)"
                :subtitle="($summary['itc_not_available'] ?? 0) > 0 ? 'Not available: ' . $money($summary['itc_not_available']) : 'All ITC available'" />
        </div>
    </div>

    @if ($withoutGstin > 0)
        <x-ui.alert variant="warning" icon="feather-info" class="fs-12 mb-3">
            {{ $withoutGstin }} taxed {{ \Illuminate\Support\Str::plural('bill', $withoutGstin) }} in {{ $import->periodLabel() }} {{ $withoutGstin === 1 ? 'is' : 'are' }} from vendors with no GSTIN in their vendor record, so {{ $withoutGstin === 1 ? 'it' : 'they' }} can't be matched. Add the GSTIN to the vendor and re-run matching.
        </x-ui.alert>
    @endif

    <x-ui.card bodyClass="p-0" class="accounting-dense">
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 px-4 pt-3 pb-2 border-bottom">
            <ul class="nav gstr2b-tabs gap-1">
                @foreach ($tabs as $key => [$label, $count, $variant])
                    <li class="nav-item">
                        <a href="{{ route('accounting.gst-returns.gstr2b.show', ['import' => $import->id, 'status' => $key]) }}"
                            class="nav-link {{ $filter === $key ? 'active' : '' }}">
                            {{ $label }}
                            @if ($count !== null)
                                <span class="badge erp-badge bg-soft-{{ $variant }} text-{{ $variant }} ms-1">{{ $count }}</span>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>
            @if ($filter !== 'books_only')
                <form method="GET" class="d-flex align-items-center gap-2 gstr2b-search">
                    <input type="hidden" name="status" value="{{ $filter }}">
                    <x-ui.input name="q" :value="$search" placeholder="GSTIN, supplier or invoice no." class="form-control-sm" />
                    <x-ui.button type="submit" variant="light-brand" size="sm" icon="feather-search">Search</x-ui.button>
                </form>
            @endif
        </div>

        @if ($filter === 'books_only')
            <div class="px-4 py-2 fs-12 text-muted border-bottom">
                Bills dated in {{ $import->periodLabel() }} with GST, from vendors with a GSTIN, that are not in this GSTR-2B. The vendor hasn't filed GSTR-1 yet (or used a different GSTIN / invoice number) — follow up before claiming the ITC.
            </div>
            <x-ui.table hoverable>
                <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                    <tr>
                        <th class="ps-4">Bill</th>
                        <th>Vendor</th>
                        <th>Vendor GSTIN</th>
                        <th>Vendor invoice</th>
                        <th>Date</th>
                        <th class="text-end">Taxable</th>
                        <th class="text-end">Tax</th>
                        <th class="text-end pe-4">Total</th>
                    </tr>
                </thead>
                <tbody class="fs-13 text-dark">
                    @forelse ($booksOnly as $bill)
                        @php $a = $service->billAmounts($bill); @endphp
                        <tr>
                            <td class="ps-4"><a href="{{ route('purchase.bills.show', $bill->id) }}" class="fw-semibold">{{ $bill->bill_number }}</a></td>
                            <td>{{ $bill->vendor?->company_name ?: $bill->vendor?->name }}</td>
                            <td class="text-muted">{{ $bill->vendor?->gstin }}</td>
                            <td>{{ $bill->vendor_invoice_number ?: ($bill->vendor_bill_number ?: '—') }}</td>
                            <td class="text-muted" style="white-space: nowrap;">{{ $bill->bill_date?->format('d M Y') }}</td>
                            <td class="text-end">{{ $money($a['taxable']) }}</td>
                            <td class="text-end">{{ $money($a['tax']) }}</td>
                            <td class="text-end pe-4 fw-semibold">{{ $money($a['total']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center py-4 text-muted">Every taxed bill of {{ $import->periodLabel() }} appears in this GSTR-2B.</td></tr>
                    @endforelse
                </tbody>
            </x-ui.table>
        @else
            <x-ui.table hoverable>
                <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                    <tr>
                        <th class="ps-4">Supplier</th>
                        <th>Document</th>
                        <th>Date</th>
                        <th class="text-end">Taxable</th>
                        <th class="text-end">IGST</th>
                        <th class="text-end">CGST</th>
                        <th class="text-end">SGST</th>
                        <th class="text-end">Value</th>
                        <th>Status</th>
                        <th class="pe-4">Your bill / remarks</th>
                    </tr>
                </thead>
                <tbody class="fs-13 text-dark">
                    @forelse ($lines as $line)
                        @php [$badgeLabel, $badgeVariant] = $statusBadge[$line->match_status] ?? [$line->match_status, 'secondary']; @endphp
                        <tr>
                            <td class="ps-4">
                                <div class="fw-semibold">{{ $line->supplier_name ?: '—' }}</div>
                                <div class="fs-11 text-muted">{{ $line->supplier_gstin }}</div>
                            </td>
                            <td style="white-space: nowrap;">
                                {{ $line->document_number }}
                                <div class="fs-11 text-muted">
                                    {{ ['invoice' => 'Invoice', 'credit_note' => 'Credit note', 'debit_note' => 'Debit note'][$line->document_type] ?? $line->document_type }}
                                    @if (in_array($line->section, ['b2ba', 'cdnra'], true)) · amended @endif
                                </div>
                            </td>
                            <td class="text-muted" style="white-space: nowrap;">{{ $line->document_date?->format('d M Y') }}</td>
                            <td class="text-end">{{ $money($line->taxable_value) }}</td>
                            <td class="text-end">{{ $money($line->igst) }}</td>
                            <td class="text-end">{{ $money($line->cgst) }}</td>
                            <td class="text-end">{{ $money($line->sgst) }}</td>
                            <td class="text-end fw-semibold">{{ $money($line->total_value) }}</td>
                            <td>
                                <x-ui.badge :variant="$badgeVariant" soft>{{ $badgeLabel }}</x-ui.badge>
                                @if ($line->itc_available === 'N')
                                    <div><x-ui.badge variant="danger" soft class="mt-1">No ITC</x-ui.badge></div>
                                @endif
                            </td>
                            <td class="pe-4 fs-12 gstr2b-remarks">
                                @if ($line->vendorBill)
                                    <a href="{{ route('purchase.bills.show', $line->vendorBill->id) }}" class="fw-semibold">{{ $line->vendorBill->bill_number }}</a>
                                    <span class="text-muted">· {{ $money($line->vendorBill->grand_total) }}</span>
                                @elseif ($line->match_status === 'missing_in_books')
                                    <span class="text-muted">No bill booked for this invoice.</span>
                                @endif
                                @foreach ($line->differences ?? [] as $difference)
                                    <div class="{{ $line->match_status === 'mismatch' ? 'text-danger' : 'text-muted' }}">{{ $difference }}</div>
                                @endforeach
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="10" class="text-center py-4 text-muted">Nothing here.</td></tr>
                    @endforelse
                </tbody>
            </x-ui.table>

            <x-ui.pagination
                :currentPage="$lines->currentPage()"
                :totalPages="$lines->lastPage()"
                :totalResults="$lines->total()"
                :perPage="$lines->perPage()" />
        @endif
    </x-ui.card>

    <x-ui.confirm-modal />
@endsection

@push('styles')
    <style>
        .accounting-dense table th,
        .accounting-dense table td {
            padding: 8px 10px !important;
            font-size: 12px !important;
            vertical-align: middle;
        }

        .gstr2b-tabs .nav-link {
            font-size: 12px;
            font-weight: 600;
            color: #475569;
            border-radius: 6px;
            padding: 6px 10px;
        }
        .gstr2b-tabs .nav-link:hover { color: var(--bs-primary); }
        .gstr2b-tabs .nav-link.active {
            color: var(--bs-primary);
            background: color-mix(in srgb, var(--bs-primary) 9%, #fff);
        }
        .gstr2b-remarks { min-width: 240px; max-width: 360px; white-space: normal !important; overflow-wrap: anywhere; }
        .gstr2b-search .mb-3 { margin-bottom: 0 !important; }
        .gstr2b-search .form-control { min-width: 240px; }
    </style>
@endpush
