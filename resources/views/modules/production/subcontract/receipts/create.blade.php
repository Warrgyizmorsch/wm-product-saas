@extends('layouts.duralux')

@section('title', 'Receive from vendor | SaaS ERP')
@section('page-title', 'Receive from vendor — Op #' . $operation->operation_number)
@section('breadcrumb', 'Subcontract receipt')

{{-- SF-4 / SF-5 (docs/production_ui_rules_review_2026-11-27.md): the one receipt page; each booked receipt writes a GRN (no PO)
     and the vendor bill is made from that GRN with the Service Bill form (head "Job Work"). --}}
@section('content')
@php
    $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 4, '.', ''), '0'), '.') ?: '0';
    $order = $operation->order;
    $qcRequired = (bool) ($operation->quality_required || ($operation->routingOperation?->quality_required ?? false));
    $canReceive = $challan || !$needsSlip;
    $maxQty = $challan ? $challan->unitsAtVendor() : $openWithoutSlip;
@endphp
<div class="erp-single-panel bg-white">

    @if(session('success')) <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" /> @endif
    @if(session('error')) <x-ui.toast :auto="true" type="error" title="{{ session('error') }}" /> @endif

    <x-ui.odoo-form-ui type="sheet">
        <div class="d-flex align-items-center justify-content-between mb-4 pb-3 border-bottom flex-wrap gap-2">
            <div class="d-flex align-items-center gap-3 flex-wrap">
                @if($source === \App\Domains\Production\Models\ProductionSubcontractReceipt::SOURCE_SHOP_FLOOR)
                    <x-ui.button href="{{ route('production.mes.dashboard') }}" variant="outline-secondary" icon="feather-arrow-left">Shop floor</x-ui.button>
                @elseif($challan)
                    <x-ui.button href="{{ route('production.subcontract.delivery-challans.show', $challan->id) }}" variant="outline-secondary" icon="feather-arrow-left">Dispatch slip</x-ui.button>
                @else
                    <x-ui.button href="{{ route('production.orders.show', $operation->production_order_id) }}" variant="outline-secondary" icon="feather-arrow-left">Order</x-ui.button>
                @endif
                <span class="fw-bold fs-14 text-dark"><i class="feather-download-cloud text-info me-1"></i>Receive from {{ $operation->vendor?->name ?? 'vendor' }}</span>
                @if($qcRequired)<a href="{{ route('production.quality.operation-inspection.show', ['op' => $operation->id]) }}" class="badge bg-soft-warning text-dark border text-decoration-none" title="Received units wait for the operation's QC — open its inspection page">QC on receipt <i class="feather-external-link ms-1"></i></a>@endif
                @if($operation->vendor_supplies_materials)<span class="badge bg-soft-info text-info border"><i class="feather-package me-1"></i>Vendor uses own materials</span>@endif
            </div>
        </div>

        <div class="row g-4 mb-4 fs-13 text-dark">
            <div class="col-md-6 border-end">
                <h6 class="fs-11 text-uppercase text-muted fw-bold mb-2">Production</h6>
                @if($order)
                    <div>Order <a href="{{ route('production.orders.show', $order->id) }}" class="fw-bold text-primary font-monospace">{{ $order->order_number }}</a> · {{ $order->product?->name }}</div>
                @endif
                <div class="fs-12 mt-1">Op #{{ $operation->operation_number }} — {{ $operation->name }}</div>
                <div class="fs-12 text-muted mt-1">Good units booked so far <strong>{{ $fmt($operation->quantity_produced) }}</strong> of {{ $fmt($operation->target_produced_qty ?: ($order?->quantity_ordered ?? 0)) }}</div>
            </div>
            <div class="col-md-6">
                <h6 class="fs-11 text-uppercase text-muted fw-bold mb-2">With the vendor</h6>
                @if($needsSlip)
                    @forelse($slipsAtVendor as $slip)
                        <div class="fs-12">
                            <a href="{{ route('production.subcontract.receipts.create', ['operation_id' => $operation->id, 'challan_id' => $slip->id, 'source' => $source]) }}"
                               class="font-monospace {{ $challan && $challan->id === $slip->id ? 'fw-bold text-primary' : 'text-dark' }}">{{ $slip->challan_number }}</a>
                            — {{ $fmt($slip->unitsAtVendor()) }} unit(s) at the vendor, expected back {{ $slip->expected_return_date?->format('d/m/Y') ?? '—' }}
                        </div>
                    @empty
                        <div class="alert alert-light border fs-12 mb-0">
                            <i class="feather-info me-1"></i>Nothing is with the vendor for this operation. Make and dispatch a dispatch slip first.
                            <a href="{{ route('production.subcontract.delivery-challans.create', ['production_order_id' => $operation->production_order_id, 'operation_id' => $operation->id]) }}" class="fw-bold ms-1">Create dispatch slip</a>
                        </div>
                    @endforelse
                @else
                    <div class="fs-12">Nothing of ours goes to the vendor on this operation (no dispatch slip). Still to receive: <strong>{{ $fmt($openWithoutSlip) }}</strong> unit(s).</div>
                @endif
            </div>
        </div>

        @if($canReceive)
            <form action="{{ route('production.subcontract.receipts.store') }}" method="POST" class="border rounded-3 p-3 mb-4">
                @csrf
                <input type="hidden" name="operation_id" value="{{ $operation->id }}">
                <input type="hidden" name="source" value="{{ $source }}">
                @if($challan)<input type="hidden" name="delivery_challan_id" value="{{ $challan->id }}">@endif

                <h6 class="fw-bold text-dark mb-2">New receipt{{ $challan ? ' on ' . $challan->challan_number : '' }}</h6>
                <div class="alert alert-light border fs-12 mb-3">
                    <i class="feather-info me-1"></i>Up to <strong>{{ $fmt($maxQty) }}</strong> unit(s) can be received. The receipt books the good units on the operation and writes a GRN; no stock moves.
                    <strong>Rejected</strong> units go back to the vendor for rework (shop-floor disposition makes the rework slip); <strong>scrapped</strong> units are written off.
                    @if($qcRequired) This operation has QC: the units wait for "Run QC" on the shop floor before they count. @endif
                </div>
                <div class="row">
                    <div class="col-md-4"><x-ui.input label="Received" name="received_qty" type="number" step="any" min="0" value="{{ old('received_qty', $fmt($maxQty)) }}" :required="true" /></div>
                    <div class="col-md-4"><x-ui.input label="Rejected (back to vendor)" name="rejected_qty" type="number" step="any" min="0" value="{{ old('rejected_qty', 0) }}" /></div>
                    <div class="col-md-4"><x-ui.input label="Scrapped" name="scrapped_qty" type="number" step="any" min="0" value="{{ old('scrapped_qty', 0) }}" /></div>
                </div>

                @php $materialLines = $challan ? $challan->items->where('item_type', 'material') : collect(); @endphp
                @if($materialLines->isNotEmpty())
                    <div class="fs-12 fw-bold mt-2 mb-1">Our unused material the vendor sent back (optional)</div>
                    @foreach($materialLines as $item)
                        <div class="row align-items-center mb-1">
                            <div class="col-md-8 fs-12">{{ $item->product?->name }} <span class="text-muted">(at vendor {{ $fmt(max(0, (float) $item->quantity - (float) $item->consumed_qty - (float) $item->returned_qty - (float) $item->scrapped_qty)) }} {{ $item->unit_of_measure }})</span></div>
                            <div class="col-md-4"><input type="number" step="any" min="0" class="form-control form-control-sm" name="returns[{{ $item->product_id }}]" value="{{ old('returns.' . $item->product_id, 0) }}"></div>
                        </div>
                    @endforeach
                @endif

                @if($challan)
                    <label class="form-check fs-12 mt-2">
                        <input type="checkbox" class="form-check-input" name="final" value="1">
                        <span class="form-check-label">Last receipt on this slip (close it even if units are missing)</span>
                    </label>
                @endif
                <x-ui.textarea label="Remarks" name="remarks" rows="2" placeholder="Vendor delivery note / invoice reference…" :value="old('remarks')" />
                <div class="d-flex justify-content-end mt-2">
                    <x-ui.button type="submit" variant="primary" icon="feather-check">Record receipt</x-ui.button>
                </div>
            </form>
        @endif

        <h6 class="fw-bold text-dark mb-2"><i class="feather-list me-1 text-info"></i>Receipts of this operation</h6>
        <x-ui.odoo-form-ui type="table">
            <thead class="table-light fs-11 text-uppercase text-muted">
                <tr><th>Receipt</th><th>When / where</th><th>Slip</th><th class="text-end">Received</th><th class="text-end">Good</th><th class="text-end">Rejected</th><th class="text-end">Scrapped</th><th>Status</th><th>GRN</th><th>Vendor bill</th></tr>
            </thead>
            <tbody>
                @forelse($history as $r)
                    @php $bill = $r->grn?->vendorBills->firstWhere(fn ($b) => strcasecmp((string) $b->status, 'Cancelled') !== 0); @endphp
                    <tr>
                        <td class="font-monospace fw-bold">{{ $r->receipt_number }}</td>
                        <td class="fs-12">{{ $r->received_at?->format('d/m/Y H:i') }} · {{ $r->source === 'shop_floor' ? 'Shop floor' : 'Subcontracting menu' }} · {{ $r->receiver?->name }}</td>
                        <td class="font-monospace fs-12">{{ $r->challan?->challan_number ?? '—' }}</td>
                        <td class="text-end font-monospace">{{ $fmt($r->received_qty) }}</td>
                        <td class="text-end font-monospace text-success">{{ $fmt($r->accepted_qty) }}</td>
                        <td class="text-end font-monospace text-danger">{{ $fmt($r->rejected_qty) }}</td>
                        <td class="text-end font-monospace">{{ $fmt($r->scrapped_qty) }}</td>
                        <td><span class="badge {{ $r->status === 'qc_pending' ? 'bg-soft-warning text-dark' : 'bg-soft-success text-success' }}">{{ $r->status === 'qc_pending' ? 'Waiting for QC' : ($r->status === 'rejected_in_qc' ? 'Rejected in QC' : 'Booked') }}</span></td>
                        <td class="font-monospace fs-12">{{ $r->grn?->grn_number ?? '—' }}</td>
                        <td class="fs-12">
                            @if($bill)
                                <a href="{{ route('purchase.bills.show', $bill->id) }}" class="font-monospace fw-bold">{{ $bill->bill_number }}</a>
                            @elseif($r->grn)
                                <a href="{{ route('purchase.bills.create-service', ['mode' => 'inbound', 'grn_id' => $r->grn->id, 'vendor_id' => $r->grn->vendor_id, 'service_head' => 'Job Work']) }}"
                                   class="btn btn-sm btn-outline-primary py-0 px-2 fs-11"><i class="feather-file-plus me-1"></i>Create vendor bill</a>
                            @else
                                <span class="text-muted">after QC</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="text-center text-muted py-3">Nothing received yet.</td></tr>
                @endforelse
            </tbody>
        </x-ui.odoo-form-ui>
    </x-ui.odoo-form-ui>
</div>
@endsection
