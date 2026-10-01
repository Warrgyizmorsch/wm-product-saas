@extends('layouts.duralux')

@section('title', 'Bill Matching | SaaS ERP')
@section('page-title', 'Bill Matching')
@section('breadcrumb', 'Purchase / Bill Matching')

@section('content')
    @if ($canConfigure)
        <x-ui.card class="mb-4">
            <x-slot:title>3-way match settings</x-slot:title>
            <p class="fs-12 text-muted mb-3">Each vendor bill line is checked against its purchase order (rate) and goods receipt (accepted quantity). Service bills with no PO or GRN aren't checked.</p>
            <form method="POST" action="{{ route('purchase.bill-matching.settings') }}">
                @csrf
                @method('PUT')
                <div class="row g-4 bill-match-settings">
                    <div class="col-md-6">
                        <x-ui.select label="When a bill doesn't match" name="mode" :options="$modes" :selected="$settings['mode']" :stacked="true" />
                    </div>
                    <div class="col-md-3">
                        <label for="qtyTolerance" class="form-label fw-semibold fs-13 text-dark mb-2">Quantity tolerance (%)</label>
                        <x-ui.input type="number" name="qty_tolerance_percent" id="qtyTolerance" step="0.01" min="0" max="100"
                            :value="old('qty_tolerance_percent', $settings['qty_tolerance_percent'])"
                            helperText="Bill may exceed accepted qty by this much." />
                    </div>
                    <div class="col-md-3">
                        <label for="priceTolerance" class="form-label fw-semibold fs-13 text-dark mb-2">Price tolerance (%)</label>
                        <x-ui.input type="number" name="price_tolerance_percent" id="priceTolerance" step="0.01" min="0" max="100"
                            :value="old('price_tolerance_percent', $settings['price_tolerance_percent'])"
                            helperText="Bill rate may exceed PO rate by this much." />
                    </div>
                </div>
                <div class="d-flex justify-content-end border-top pt-3 mt-3">
                    <x-ui.button type="submit" variant="primary" size="sm" icon="feather-save">Save settings</x-ui.button>
                </div>
            </form>
        </x-ui.card>
    @else
        <x-ui.alert variant="primary" icon="feather-info" class="fs-12 mb-4">
            Matching mode: <strong>{{ $modes[$settings['mode']] }}</strong>. Tolerance: quantity {{ $settings['qty_tolerance_percent'] }}%, price {{ $settings['price_tolerance_percent'] }}%.
        </x-ui.alert>
    @endif

    <x-ui.card bodyClass="p-0" class="accounting-dense">
        <x-slot:title>
            Bills that don't match
            @if ($onHoldCount > 0)
                <x-ui.badge variant="primary" soft class="ms-2">{{ $onHoldCount }} on hold</x-ui.badge>
            @endif
        </x-slot:title>

        <x-ui.table hoverable>
            <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                <tr>
                    <th class="ps-4">Bill</th>
                    <th>Vendor</th>
                    <th>Date</th>
                    <th>Problems</th>
                    <th class="text-end">Amount</th>
                    <th>Status</th>
                    <th class="text-end pe-4">Actions</th>
                </tr>
            </thead>
            <tbody class="fs-13 text-dark">
                @forelse ($bills as $bill)
                    @php
                        $problems = collect($bill->match_details['lines'] ?? [])->flatMap(fn ($line) => $line['messages'] ?? []);
                    @endphp
                    <tr>
                        <td class="ps-4"><a href="{{ route('purchase.bills.show', $bill->id) }}" class="fw-semibold">{{ $bill->bill_number }}</a></td>
                        <td>{{ $bill->vendor?->name ?? '—' }}</td>
                        <td class="text-muted" style="white-space: nowrap;">{{ $bill->bill_date?->format('d M Y') }}</td>
                        <td class="fs-12">
                            @foreach ($problems->take(2) as $problem)
                                <div class="text-danger">{{ $problem }}</div>
                            @endforeach
                            @if ($problems->count() > 2)
                                <div class="text-muted">+{{ $problems->count() - 2 }} more</div>
                            @endif
                        </td>
                        <td class="text-end fw-semibold">{{ number_format((float) $bill->grand_total, 2) }}</td>
                        <td>
                            @if ($bill->isOnHold())
                                <x-ui.badge variant="primary" soft>On hold</x-ui.badge>
                            @else
                                <x-ui.badge variant="warning" soft>Posted with mismatch</x-ui.badge>
                            @endif
                        </td>
                        <td class="text-end pe-4" style="white-space: nowrap;">
                            <div class="d-inline-flex align-items-center gap-2">
                                <x-ui.button href="{{ route('purchase.bills.show', $bill->id) }}" variant="light-brand" size="sm" icon="feather-eye">View</x-ui.button>
                                @if ($bill->isOnHold() && $canRelease && (int) $bill->created_by !== (int) auth()->id())
                                    <x-ui.button type="button" variant="primary" size="sm" icon="feather-unlock"
                                        data-bs-toggle="modal" data-bs-target="#releaseHoldModal"
                                        data-release-url="{{ route('purchase.bills.release-hold', $bill->id) }}"
                                        data-bill-number="{{ $bill->bill_number }}">Release</x-ui.button>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">Every checked bill matches its PO and GRN.</td>
                    </tr>
                @endforelse
            </tbody>
        </x-ui.table>

        <x-ui.pagination
            :currentPage="$bills->currentPage()"
            :totalPages="$bills->lastPage()"
            :totalResults="$bills->total()"
            :perPage="$bills->perPage()" />
    </x-ui.card>

    @if ($canRelease)
        @include('modules.purchase.bill-matching._release-modal')
    @endif
@endsection

@push('styles')
    <style>
        .accounting-dense table th,
        .accounting-dense table td {
            padding: 8px 10px !important;
            font-size: 12px !important;
            vertical-align: middle;
        }

        .bill-match-settings .mb-3 {
            margin-bottom: 0 !important;
        }
    </style>
@endpush
