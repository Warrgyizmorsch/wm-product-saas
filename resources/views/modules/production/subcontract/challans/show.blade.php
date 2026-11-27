@extends('layouts.duralux')

@section('title', 'Dispatch Slip ' . $challan->challan_number . ' | SaaS ERP')
@section('page-title', 'Subcontract Dispatch Slip ' . $challan->challan_number)
@section('breadcrumb', 'Dispatch Slip')

{{-- TF-04 / TF-06 (docs/subcontracting_module_audit_2026-11-26.md): what went to the vendor, what came back, and the receipts --}}
@section('content')
@php
    $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 4, '.', ''), '0'), '.') ?: '0';
    $atVendor = $challan->isAtVendor();
    $qcRequired = $challan->operation ? (bool) ($challan->operation->quality_required || ($challan->operation->routingOperation?->quality_required ?? false)) : false;
@endphp
<div class="erp-single-panel bg-white">

    @if(session('success')) <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" /> @endif
    @if(session('error')) <x-ui.toast :auto="true" type="error" title="{{ session('error') }}" /> @endif
    @if(session('warning')) <x-ui.toast :auto="true" type="warning" title="{{ session('warning') }}" /> @endif

    <x-ui.odoo-form-ui type="sheet">
        <div class="d-flex align-items-center justify-content-between mb-4 pb-3 border-bottom flex-wrap gap-2">
            <div class="d-flex align-items-center gap-3">
                <x-ui.button href="{{ route('production.subcontract.delivery-challans.index') }}" variant="outline-secondary" icon="feather-arrow-left">Back</x-ui.button>
                <span class="badge bg-primary text-white font-monospace fs-13 px-3 py-1.5">{{ $challan->challan_number }}</span>
                <x-ui.status-badge :status="$challan->status" />
                @if($challan->type === 'vendor_rework')
                    <span class="badge bg-soft-danger text-danger border">Rework</span>
                @endif
            </div>
            <div class="d-flex align-items-center gap-2">
                <x-ui.button href="{{ route('production.subcontract.delivery-challans.print', $challan->id) }}" target="_blank" variant="outline-primary" icon="feather-printer">Print gate pass</x-ui.button>
                @if($challan->status === \App\Domains\Production\Models\DeliveryChallan::STATUS_DRAFT)
                    <form action="{{ route('production.subcontract.delivery-challans.dispatch', $challan->id) }}" method="POST" class="d-inline">
                        @csrf
                        <x-ui.button type="submit" variant="success" icon="feather-send">Dispatch to vendor</x-ui.button>
                    </form>
                @elseif($atVendor)
                    <x-ui.button type="button" variant="primary" icon="feather-download-cloud" data-bs-toggle="modal" data-bs-target="#receiveItemsModal">Receive from vendor</x-ui.button>
                @endif
            </div>
        </div>

        <div class="row g-4 mb-4 fs-13 text-dark">
            <div class="col-md-4 border-end">
                <h6 class="fs-11 text-uppercase text-muted fw-bold mb-2">Vendor</h6>
                <div class="fw-bold text-dark fs-14"><i class="feather-truck text-primary me-1"></i>{{ $challan->vendor?->name ?? 'Vendor' }}</div>
                <div class="fs-12 text-muted mt-1">{{ $challan->vendor?->address ?? '' }}</div>
                <div class="fs-11 font-monospace text-muted mt-1">GSTIN: {{ $challan->vendor?->gst_number ?? 'N/A' }}</div>
            </div>
            <div class="col-md-4 border-end">
                <h6 class="fs-11 text-uppercase text-muted fw-bold mb-2">Production</h6>
                @if($challan->productionOrder)
                    <div>Order <a href="{{ route('production.orders.show', $challan->production_order_id) }}" class="fw-bold text-primary font-monospace">{{ $challan->productionOrder->order_number }}</a> · {{ $challan->productionOrder->product?->name }}</div>
                @endif
                @if($challan->operation)
                    <div class="fs-12 mt-1">Op #{{ $challan->operation->operation_number }} — {{ $challan->operation->name }}
                        @if($qcRequired)<span class="badge bg-soft-warning text-dark ms-1">QC</span>@endif</div>
                @endif
                <div class="fs-12 mt-2">
                    Units sent <strong>{{ $fmt($challan->dispatched_wip_qty) }}</strong> ·
                    received <strong>{{ $fmt($challan->received_qty) }}</strong>
                    ({{ $fmt($challan->accepted_qty) }} good, {{ $fmt($challan->rejected_qty) }} rejected, {{ $fmt($challan->scrapped_qty) }} scrapped) ·
                    at vendor <strong class="{{ $challan->unitsAtVendor() > 0 ? 'text-warning' : 'text-success' }}">{{ $fmt($challan->unitsAtVendor()) }}</strong>
                </div>
            </div>
            <div class="col-md-4">
                <h6 class="fs-11 text-uppercase text-muted fw-bold mb-2">Transport</h6>
                <div class="fs-12">Vehicle: <strong class="font-monospace">{{ $challan->vehicle_number ?: '—' }}</strong> · LR: {{ $challan->lr_number ?: '—' }}</div>
                <div class="fs-12 text-muted">Transporter: {{ $challan->transporter_name ?: '—' }} · Driver: {{ $challan->driver_name ?: '—' }}</div>
                <div class="fs-12 text-muted mt-1">Slip date <strong>{{ $challan->challan_date?->format('d/m/Y') }}</strong> · expected back <strong>{{ $challan->expected_return_date?->format('d/m/Y') ?? '—' }}</strong></div>
                @if($challan->dispatched_at)<div class="fs-12 text-muted">Dispatched {{ $challan->dispatched_at->format('d/m/Y H:i') }}</div>@endif
            </div>
        </div>

        <h6 class="fw-bold text-dark mb-2"><i class="feather-box me-1 text-warning"></i>Lines</h6>
        <div class="mb-4">
            <x-ui.odoo-form-ui type="table">
                <thead class="table-light fs-11 text-uppercase text-muted">
                    <tr>
                        <th>Line</th>
                        <th class="text-end">Sent</th>
                        <th class="text-end">Used by vendor</th>
                        <th class="text-end">Returned unused</th>
                        <th class="text-end">At vendor</th>
                        <th>Unit</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($challan->items as $item)
                        @php $lineAtVendor = max(0, (float) $item->quantity - (float) $item->consumed_qty - (float) $item->returned_qty - (float) $item->scrapped_qty); @endphp
                        <tr>
                            <td>
                                <span class="badge {{ $item->item_type === 'wip' ? 'bg-soft-warning text-dark' : 'bg-soft-primary text-primary' }} me-1">{{ $item->item_type === 'wip' ? 'WIP' : 'Material' }}</span>
                                <strong>{{ $item->product?->name }}</strong> <small class="text-muted font-monospace">{{ $item->product?->sku }}</small>
                            </td>
                            <td class="text-end font-monospace fw-bold">{{ $fmt($item->quantity) }}</td>
                            <td class="text-end font-monospace">{{ $item->item_type === 'wip' ? '—' : $fmt($item->consumed_qty) }}</td>
                            <td class="text-end font-monospace">{{ $item->item_type === 'wip' ? '—' : $fmt($item->returned_qty) }}</td>
                            <td class="text-end font-monospace {{ $lineAtVendor > 0 ? 'text-warning' : '' }}">{{ $item->item_type === 'wip' ? '—' : $fmt($lineAtVendor) }}</td>
                            <td class="text-uppercase font-monospace fs-12">{{ $item->unit_of_measure }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-3">No lines.</td></tr>
                    @endforelse
                </tbody>
            </x-ui.odoo-form-ui>
        </div>

        <h6 class="fw-bold text-dark mb-2"><i class="feather-download-cloud me-1 text-info"></i>Receipts</h6>
        <div class="mb-4">
            <x-ui.odoo-form-ui type="table">
                <thead class="table-light fs-11 text-uppercase text-muted">
                    <tr><th>Receipt</th><th>When / where</th><th class="text-end">Received</th><th class="text-end">Good</th><th class="text-end">Rejected</th><th class="text-end">Scrapped</th><th>Status</th><th>System GRN</th></tr>
                </thead>
                <tbody>
                    @forelse($receipts as $r)
                        <tr>
                            <td class="font-monospace fw-bold">{{ $r->receipt_number }}</td>
                            <td class="fs-12">{{ $r->received_at?->format('d/m/Y H:i') }} · {{ $r->source === 'shop_floor' ? 'Shop floor' : 'Subcontracting menu' }} · {{ $r->receiver?->name }}</td>
                            <td class="text-end font-monospace">{{ $fmt($r->received_qty) }}</td>
                            <td class="text-end font-monospace text-success">{{ $fmt($r->accepted_qty) }}</td>
                            <td class="text-end font-monospace text-danger">{{ $fmt($r->rejected_qty) }}</td>
                            <td class="text-end font-monospace">{{ $fmt($r->scrapped_qty) }}</td>
                            <td><span class="badge {{ $r->status === 'qc_pending' ? 'bg-soft-warning text-dark' : 'bg-soft-success text-success' }}">{{ $r->status === 'qc_pending' ? 'Waiting for QC' : ($r->status === 'rejected_in_qc' ? 'Rejected in QC' : 'Booked') }}</span></td>
                            <td class="font-monospace fs-12">{{ $r->grn?->grn_number ?? ($challan->type === 'vendor_rework' ? 'not billed (rework)' : '—') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-3">Nothing received yet.</td></tr>
                    @endforelse
                </tbody>
            </x-ui.odoo-form-ui>
        </div>

        @if($challan->notes)
            <div class="alert alert-light border p-3 rounded-3 fs-12 mb-0"><strong class="d-block mb-1"><i class="feather-info me-1"></i>Notes</strong>{{ $challan->notes }}</div>
        @endif
    </x-ui.odoo-form-ui>

    @if($atVendor)
        <x-ui.modal id="receiveItemsModal" title="Receive from vendor — {{ $challan->challan_number }}" :centered="true" size="lg"
            formAction="{{ route('production.subcontract.delivery-challans.receive', $challan->id) }}" formMethod="POST"
            submitText="Record receipt" closeText="Cancel">
            <div class="alert alert-soft-info border border-info-subtle fs-12 mb-3">
                <i class="feather-info me-1"></i>
                {{ $fmt($challan->unitsAtVendor()) }} unit(s) are still with the vendor. The receipt books the good units on the operation, records the material the vendor used, updates the subcontract PO (system GRN) and makes the vendor bill possible.
                No stock is moved and nothing is backflushed.
                @if($qcRequired) <strong>This operation has QC:</strong> the units wait for "Run QC" on the shop floor before they count. @endif
            </div>
            <div class="row">
                <div class="col-md-4"><x-ui.input label="Received" name="received_qty" type="number" step="any" min="0" value="{{ $fmt($challan->unitsAtVendor()) }}" :required="true" /></div>
                <div class="col-md-4"><x-ui.input label="Rejected" name="rejected_qty" type="number" step="any" min="0" value="0" /></div>
                <div class="col-md-4"><x-ui.input label="Scrapped" name="scrapped_qty" type="number" step="any" min="0" value="0" /></div>
            </div>
            @php $materialLines = $challan->items->where('item_type', 'material'); @endphp
            @if($materialLines->isNotEmpty())
                <div class="fs-12 fw-bold mt-2 mb-1">Unused material sent back (optional)</div>
                @foreach($materialLines as $item)
                    <div class="row align-items-center mb-1">
                        <div class="col-md-8 fs-12">{{ $item->product?->name }} <span class="text-muted">(at vendor {{ $fmt(max(0, (float) $item->quantity - (float) $item->consumed_qty - (float) $item->returned_qty - (float) $item->scrapped_qty)) }} {{ $item->unit_of_measure }})</span></div>
                        <div class="col-md-4"><input type="number" step="any" min="0" class="form-control form-control-sm" name="returns[{{ $item->product_id }}]" value="0"></div>
                    </div>
                @endforeach
            @endif
            <label class="form-check fs-12 mt-2">
                <input type="checkbox" class="form-check-input" name="final" value="1">
                <span class="form-check-label">Last receipt on this slip (close it even if units are missing)</span>
            </label>
            <x-ui.textarea label="Remarks" name="remarks" rows="2" placeholder="Vendor delivery note / invoice reference…" />
        </x-ui.modal>
    @endif
</div>
@endsection
