@extends('layouts.duralux')

@section('title', 'Delivery Challan ' . $challan->challan_number . ' | SaaS ERP')
@section('page-title', 'Subcontract Delivery Challan ' . $challan->challan_number)
@section('breadcrumb', 'Delivery Challan Details')

@section('content')
<div class="erp-single-panel bg-white">

    @if(session('success'))
        <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
    @endif
    @if(session('error'))
        <x-ui.toast :auto="true" type="error" title="{{ session('error') }}" />
    @endif

    <x-ui.odoo-form-ui type="sheet">
        <!-- Action Control Bar -->
        <div class="d-flex align-items-center justify-content-between mb-4 pb-3 border-bottom flex-wrap gap-2">
            <div class="d-flex align-items-center gap-3">
                <x-ui.button href="{{ route('production.subcontract.delivery-challans.index') }}" variant="outline-secondary" size="sm" icon="feather-arrow-left">
                    Back to Challans
                </x-ui.button>
                <span class="badge bg-primary text-white font-monospace fs-13 px-3 py-1.5">{{ $challan->challan_number }}</span>
                <x-ui.status-badge :status="$challan->status" />
            </div>

            <div class="d-flex align-items-center gap-2">
                <x-ui.button href="{{ route('production.subcontract.delivery-challans.print', $challan->id) }}" target="_blank" variant="outline-primary" size="sm" icon="feather-printer">
                    Print Gate Pass (PDF)
                </x-ui.button>

                @if($challan->status === 'draft')
                    <form action="{{ route('production.subcontract.delivery-challans.dispatch', $challan->id) }}" method="POST" class="d-inline">
                        @csrf
                        <x-ui.button type="submit" variant="success" size="sm" icon="feather-send">
                            Dispatch & Deduct Stock
                        </x-ui.button>
                    </form>
                @elseif(in_array($challan->status, ['dispatched', 'vendor_dispatched', 'in_transit']))
                    <x-ui.button type="button" variant="primary" size="sm" icon="feather-check-circle" data-bs-toggle="modal" data-bs-target="#receiveItemsModal">
                        Receive Processed Items
                    </x-ui.button>
                @endif
            </div>
        </div>

        <!-- Master Metadata Section -->
        <div class="row g-4 mb-4 fs-13 text-dark">
            <div class="col-md-4 border-end">
                <h6 class="fs-11 text-uppercase text-muted fw-bold mb-2">Subcontractor / Consignee</h6>
                <div class="fw-bold text-dark fs-14"><i class="feather-truck text-primary me-1"></i>{{ $challan->vendor?->name ?? 'Vendor' }}</div>
                <div class="fs-12 text-muted mt-1">{{ $challan->vendor?->address ?? 'Vendor Address N/A' }}</div>
                <div class="fs-11 font-monospace text-muted mt-1">GSTIN: {{ $challan->vendor?->gst_number ?? 'N/A' }}</div>
            </div>

            <div class="col-md-4 border-end">
                <h6 class="fs-11 text-uppercase text-muted fw-bold mb-2">Production Reference & Warehouse</h6>
                @if($challan->productionOrder)
                    <div class="fs-13">Production Order: <a href="{{ route('production.orders.show', $challan->production_order_id) }}" class="fw-bold text-primary font-monospace">{{ $challan->productionOrder->order_number }}</a></div>
                    <div class="fs-12 text-dark mt-0.5">Product: <strong>{{ $challan->productionOrder->product?->name }}</strong></div>
                @else
                    <div class="text-muted fs-13">Direct Material Dispatch</div>
                @endif

                @if($challan->operation)
                    <div class="fs-12 text-dark mt-1">
                        Operation: <span class="badge bg-soft-primary text-primary font-monospace">Op #{{ $challan->operation->operation_number }} — {{ $challan->operation->name }}</span>
                    </div>
                @endif

                <div class="fs-12 text-dark mt-2">
                    Source Store: <strong class="text-secondary"><i class="feather-home me-1"></i>{{ $challan->warehouse?->name ?? 'N/A' }}</strong>
                </div>
            </div>

            <div class="col-md-4">
                <h6 class="fs-11 text-uppercase text-muted fw-bold mb-2">Transport & Gate Logistics</h6>
                <div class="fs-12 text-dark">Vehicle #: <strong class="font-monospace text-primary">{{ $challan->vehicle_number ?: '—' }}</strong></div>
                <div class="fs-12 text-muted">Transporter: {{ $challan->transporter_name ?: '—' }}</div>
                <div class="fs-12 text-muted">LR / E-Way Bill #: <span class="font-monospace">{{ $challan->lr_number ?: '—' }}</span></div>
                <div class="fs-12 text-muted">Driver Name: {{ $challan->driver_name ?: '—' }}</div>
                <div class="fs-12 text-muted mt-1">Challan Date: <strong>{{ $challan->challan_date->format('d/m/Y') }}</strong></div>
            </div>
        </div>

        <!-- Material Lines Table -->
        <h6 class="fw-bold text-dark mb-3"><i class="feather-box me-1 text-warning"></i>Dispatched Company Material Lines</h6>
        <div class="mb-4">
            <x-ui.odoo-form-ui type="table">
                <thead class="table-light fs-11 text-uppercase text-muted">
                    <tr>
                        <th style="width: 5%">#</th>
                        <th style="width: 35%">Material Description</th>
                        <th style="width: 25%">Source Warehouse</th>
                        <th style="width: 15%" class="text-end">Dispatched Quantity</th>
                        <th style="width: 10%">UOM</th>
                        <th style="width: 10%">Batch / Serial</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($challan->items as $index => $item)
                        <tr>
                            <td class="font-monospace text-muted">{{ $index + 1 }}</td>
                            <td>
                                <div class="fw-bold text-dark">{{ $item->product?->name }}</div>
                                <small class="text-muted font-monospace fs-11">SKU: {{ $item->product?->sku }}</small>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border fs-11"><i class="feather-home me-1 text-primary"></i>{{ $item->warehouse?->name ?? $challan->warehouse?->name ?? 'Default Store' }}</span>
                            </td>
                            <td class="text-end font-monospace fw-bold text-primary fs-14">
                                {{ number_format($item->quantity, 2) }}
                            </td>
                            <td class="text-uppercase font-monospace fs-12">{{ $item->unit_of_measure }}</td>
                            <td class="font-monospace text-muted fs-12">{{ $item->batch_number ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-3">No material items on this challan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.odoo-form-ui>
        </div>

        @if($challan->notes)
            <div class="alert alert-light border p-3 rounded-3 fs-12 mb-0">
                <strong class="text-dark d-block mb-1"><i class="feather-info me-1"></i>Gate Instructions & Remarks:</strong>
                {{ $challan->notes }}
            </div>
        @endif
    </x-ui.odoo-form-ui>

    <!-- Receive Processed Subcontract Items Modal using Common UI Components -->
    @if(in_array($challan->status, ['dispatched', 'vendor_dispatched', 'in_transit']))
        @php
            $whOptions = [];
            foreach ($warehouses ?? [] as $wh) {
                $whOptions[$wh->id] = $wh->name . ' (' . $wh->code . ')';
            }
        @endphp

        <x-ui.modal
            id="receiveItemsModal"
            title="<i class='feather-check-circle me-2 text-primary'></i>Receive Processed Subcontract Items"
            :centered="true"
            formAction="{{ route('production.subcontract.delivery-challans.receive', $challan->id) }}"
            formMethod="POST"
            submitText="Confirm Inward Receipt & Complete"
            closeText="Cancel"
        >
            @php
                $isWipJobWork = $challan->operation?->isWipJobWork() ?? false;
                $defaultQty = $challan->dispatched_wip_qty > 0 ? $challan->dispatched_wip_qty : $challan->items->sum('quantity');
            @endphp

            @if($isWipJobWork)
                <div class="alert alert-info fs-12 mb-3">
                    <strong class="d-block mb-1"><i class="feather-info me-1"></i>Inward Intermediate WIP Receipt:</strong>
                    Receiving processed WIP from vendor will update shopfloor Work In Progress, record operation completion, and unlock the next shopfloor operation. <strong>No Finished Goods stock or raw material backflushing will occur for intermediate WIP.</strong>
                </div>
            @else
                <div class="alert alert-info fs-12 mb-3">
                    <strong class="d-block mb-1"><i class="feather-info me-1"></i>Inward Material Receipt & Backflushing:</strong>
                    Receiving items will add finished/processed stock into your store, backflush consumed company raw materials, update operation progress, and unlock the next shopfloor step.
                </div>
            @endif

            <x-ui.input
                label="Target Product"
                name="target_product_display"
                value="{{ $challan->productionOrder?->product?->name ?? 'Processed Material' }}"
                :disabled="true"
            />

            <div class="row">
                <div class="col-md-6">
                    <x-ui.input
                        label="Quantity Received"
                        name="received_qty"
                        type="number"
                        step="0.01"
                        min="0.01"
                        value="{{ number_format($defaultQty, 2, '.', '') }}"
                        :required="true"
                    />
                </div>
                <div class="col-md-6">
                    <x-ui.input
                        label="Quantity Accepted"
                        name="accepted_qty"
                        type="number"
                        step="0.01"
                        min="0"
                        value="{{ number_format($defaultQty, 2, '.', '') }}"
                        :required="true"
                    />
                </div>
            </div>

            <x-ui.select
                label="Receiving Store"
                name="warehouse_id"
                :options="$whOptions"
                :selected="$challan->warehouse_id"
                :required="true"
            />

            <x-ui.textarea
                label="Inspection Remarks"
                name="remarks"
                rows="2"
                placeholder="e.g. Received 30 pcs after TIG welding inspection PASS"
            />
        </x-ui.modal>
    @endif
</div>
@endsection
