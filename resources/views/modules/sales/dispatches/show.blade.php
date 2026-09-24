@extends('layouts.duralux')

@section('title', __('crm.dispatch_order') . ' ' . $dispatch->dispatch_number . ' | SaaS ERP')
@section('page-title', __('crm.dispatch_order') . ' ' . $dispatch->dispatch_number)
@section('breadcrumb', __('crm.sales_breadcrumb') . ' / ' . $dispatch->dispatch_number)

@section('page-actions')
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <a href="{{ route('sales.dispatches.index') }}" class="btn btn-sm btn-light border px-2 py-1 d-inline-flex align-items-center justify-content-center" title="{{ __('crm.back_to_dispatches') }}">
            <i class="feather-arrow-left fs-14"></i>
        </a>

        @if ($dispatch->status === 'Pending')
            <form action="{{ route('sales.dispatches.confirm', $dispatch->id) }}" method="POST" id="confirmDispatchForm" class="d-inline">
                @csrf
                <button type="button" class="btn btn-sm btn-success fw-semibold px-2.5 py-1 fs-12 text-nowrap" onclick="confirmAction({ title: '{{ __('crm.confirm_dispatch_order') }}', message: '{{ __('crm.confirm_dispatch') }} {{ $dispatch->dispatch_number }}?', variant: 'success', confirmText: '{{ __('crm.confirm') }}' }, function() { document.getElementById('confirmDispatchForm').submit(); })">
                    <i class="feather-check-circle me-1"></i> {{ __('crm.confirm_do') }}
                </button>
            </form>
        @elseif ($dispatch->status === 'Confirmed')
            <form action="{{ route('sales.dispatches.ship', $dispatch->id) }}" method="POST" id="shipDispatchForm" class="d-inline">
                @csrf
                <button type="button" class="btn btn-sm btn-primary fw-semibold px-2.5 py-1 fs-12 text-nowrap" onclick="confirmAction({ title: '{{ __('crm.ship_outward') }}', message: '{{ __('crm.ship_outward') }} {{ $dispatch->dispatch_number }}?', variant: 'primary', confirmText: '{{ __('crm.ship_outward') }}' }, function() { document.getElementById('shipDispatchForm').submit(); })">
                    <i class="feather-truck me-1"></i> {{ __('crm.ship_outward') }}
                </button>
            </form>
        @endif

        <a href="{{ route('sales.dispatches.download-challan', $dispatch->id) }}" target="_blank" class="btn btn-sm btn-outline-danger fw-semibold px-2.5 py-1 fs-12 text-nowrap">
            <i class="feather-printer me-1"></i> {{ __('crm.challan_pdf') }}
        </a>

        <button type="button" class="btn btn-sm btn-outline-secondary fw-semibold px-2.5 py-1 fs-12 text-nowrap" data-bs-toggle="modal" data-bs-target="#uploadPodModal">
            <i class="feather-check-square me-1"></i> {{ $dispatch->status === 'Delivered' ? __('crm.update_pod') : __('crm.mark_delivered_pod') }}
        </button>
    </div>
@endsection

@section('content')
    <div class="erp-single-panel bg-white p-4 rounded-3 border shadow-sm">
        <x-ui.odoo-form-ui type="sheet">

            {{-- 1. Single Page Header: Dispatch Document Title & Status --}}
            <div class="d-flex align-items-start justify-content-between flex-wrap gap-3 pb-3 border-bottom mb-4">
                <div class="d-flex align-items-start gap-3">
                    <div class="avatar-text avatar-xl bg-soft-primary text-primary rounded-3 d-flex align-items-center justify-content-center fw-bold fs-20 shadow-sm flex-shrink-0" style="width: 56px; height: 56px;">
                        <i class="feather-truck fs-24"></i>
                    </div>
                    <div>
                        <div class="d-flex align-items-center gap-3 flex-wrap">
                            <h4 class="fw-bold text-dark mb-0 fs-20 me-1">{{ $dispatch->dispatch_number }}</h4>
                            @php
                                $statusType = 'secondary';
                                if ($dispatch->status === 'Confirmed') $statusType = 'warning';
                                elseif ($dispatch->status === 'Dispatched' || $dispatch->status === 'Shipped') $statusType = 'info';
                                elseif ($dispatch->status === 'Delivered') $statusType = 'active';
                                elseif ($dispatch->status === 'Invoiced') $statusType = 'dark';
                            @endphp
                            <x-ui.status-badge :status="$statusType" :label="$dispatch->status" dot="true" size="sm" />
                        </div>

                        <div class="d-flex align-items-center gap-3 text-muted fs-12 mt-2 flex-wrap">
                            <span><strong class="text-dark">{{ __('crm.dispatch_date') }}:</strong> <span class="fw-bold text-primary">{{ $dispatch->dispatch_date ? $dispatch->dispatch_date->format('d M Y') : '—' }}</span></span>
                            <span class="text-black-50">•</span>
                            <span><strong class="text-dark">{{ __('crm.customer') }}:</strong> <strong class="text-dark">{{ $dispatch->customer?->name ?? 'Direct Customer' }}</strong></span>
                            @if($dispatch->material_requirement_id && $dispatch->materialRequirement)
                                <span class="text-black-50">•</span>
                                <span><strong class="text-dark">{{ __('crm.material_requirement') }}:</strong> <a href="{{ route('inventory.material-requirements.show', $dispatch->material_requirement_id) }}" class="fw-bold text-primary">{{ $dispatch->materialRequirement->requirement_number }}</a></span>
                            @endif
                            @if($dispatch->sales_order_id && $dispatch->salesOrder)
                                <span class="text-black-50">•</span>
                                <span><strong class="text-dark">{{ __('crm.sales_order') }}:</strong> <a href="{{ route('sales.orders.show', $dispatch->sales_order_id) }}" class="fw-bold text-info">{{ $dispatch->salesOrder->sales_order_number }}</a></span>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="text-end">
                    <button type="button" class="btn btn-sm btn-outline-primary fw-semibold" data-bs-toggle="modal" data-bs-target="#updateTrackingModal">
                        <i class="feather-edit-2 me-1"></i>{{ __('crm.edit_logistics_tracking') }}
                    </button>
                </div>
            </div>

            {{-- 2. Single Page Logistics & Transporter Summary Strip --}}
            <div class="bg-light p-3 rounded-3 border mb-4">
                <div class="row g-3 fs-12">
                    <div class="col-md-3 col-6 border-end">
                        <span class="text-muted fs-11 text-uppercase fw-semibold d-block mb-1">{{ __('crm.transporter_master') }}</span>
                        <strong class="text-dark fs-13 d-block">{{ $dispatch->transporter?->name ?: ($dispatch->carrier ?: __('crm.self_pickup_direct')) }}</strong>
                        @if($dispatch->transporter?->transporter_id)
                            <small class="text-muted font-monospace">ID: {{ $dispatch->transporter->transporter_id }}</small>
                        @endif
                    </div>

                    <div class="col-md-3 col-6 border-end">
                        <span class="text-muted fs-11 text-uppercase fw-semibold d-block mb-1">{{ __('crm.courier_tracking') }}</span>
                        <strong class="font-monospace text-primary fs-13 d-block">{{ $dispatch->tracking_number ?: '—' }}</strong>
                        @if($dispatch->carrier)
                            <small class="text-muted d-block">{{ __('crm.partner') }} {{ $dispatch->carrier }}</small>
                        @endif
                    </div>

                    <div class="col-md-3 col-6 border-end">
                        <span class="text-muted fs-11 text-uppercase fw-semibold d-block mb-1">{{ __('crm.vehicle_driver') }}</span>
                        <strong class="font-monospace text-uppercase text-dark fs-13 d-block">{{ $dispatch->vehicle_number ?: '—' }}</strong>
                        @if($dispatch->driver_name)
                            <small class="text-muted d-block">{{ $dispatch->driver_name }} {{ $dispatch->driver_phone ? "({$dispatch->driver_phone})" : '' }}</small>
                        @endif
                    </div>

                    <div class="col-md-3 col-6">
                        <span class="text-muted fs-11 text-uppercase fw-semibold d-block mb-1">{{ __('crm.freight_terms_obligation') }}</span>
                        @php
                            $termClass = match($dispatch->freight_terms) {
                                'To Be Billed' => 'bg-soft-success text-success border-success-subtle',
                                'FOR Site', 'Prepaid' => 'bg-soft-info text-info border-info-subtle',
                                'Customer Pickup' => 'bg-soft-warning text-warning border-warning-subtle',
                                default => 'bg-soft-primary text-primary border-primary-subtle',
                            };
                            $linkedFreightBill = $dispatch->freightBill ?? $dispatch->vendorBills->first();
                        @endphp
                        <div class="hstack gap-1.5 flex-wrap">
                            <span class="badge {{ $termClass }} border fs-11 fw-semibold">{{ $dispatch->freight_terms ?: 'To Be Billed' }}</span>
                            @if($dispatch->freight_amount > 0)
                                <span class="fw-bold text-dark fs-12">{{ format_currency($dispatch->freight_amount) }}</span>
                            @endif
                        </div>
                        @if($dispatch->lr_number)
                            <small class="text-muted d-block mt-1"><i class="feather-file-text me-1"></i>LR: {{ $dispatch->lr_number }} {{ $dispatch->lr_date ? '('.$dispatch->lr_date->format('d M Y').')' : '' }}</small>
                        @endif

                        @if($linkedFreightBill && $linkedFreightBill->status !== 'Cancelled')
                            @php
                                $expectedAmt = (float) $dispatch->freight_amount;
                                $actualAmt   = (float) $linkedFreightBill->grand_total;
                                $variance    = round($actualAmt - $expectedAmt, 2);
                            @endphp
                            <div class="mt-2 pt-1.5 border-top">
                                <span class="badge bg-soft-success text-success border border-success-subtle fs-11 fw-bold d-block mb-1 text-truncate" title="Freight Bill {{ $linkedFreightBill->bill_number }}">
                                    <i class="feather-check-circle me-1"></i>Bill {{ $linkedFreightBill->bill_number }} Linked
                                </span>
                                <div class="fs-11 text-muted">
                                    <div class="d-flex justify-content-between">
                                        <span>{{ __('crm.billed_amt') }}</span>
                                        <strong class="text-dark">{{ format_currency($actualAmt) }}</strong>
                                    </div>
                                    @if(abs($variance) > 0.01)
                                        <div class="d-flex justify-content-between {{ $variance > 0 ? 'text-danger' : 'text-success' }}">
                                            <span>{{ __('crm.variance') }}</span>
                                            <strong>{{ $variance > 0 ? '+' : '' }}{{ format_currency($variance) }}</strong>
                                        </div>
                                    @endif
                                </div>
                                @if(Route::has('purchase.bills.show'))
                                    <a href="{{ route('purchase.bills.show', $linkedFreightBill->id) }}" class="btn btn-xs btn-light text-primary border w-100 py-1 mt-1">
                                        <i class="feather-eye me-1"></i>{{ __('crm.view_freight_bill') }}
                                    </a>
                                @endif
                            </div>
                        @elseif(in_array($dispatch->freight_terms, ['To Pay', 'to_pay', 'Customer Pickup', 'customer_pickup']))
                            <div class="mt-2 pt-1 border-top">
                                <small class="text-muted fs-11 d-block"><i class="feather-info me-1"></i>{{ __('crm.no_obligation') }}</small>
                            </div>
                        @else
                            <div class="mt-2 pt-1 border-top">
                                <span class="badge bg-soft-warning text-warning border border-warning-subtle fs-11 fw-semibold d-block mb-1">
                                    <i class="feather-clock me-1"></i>{{ __('crm.pending_freight_bill') }}
                                </span>
                                @if(Route::has('purchase.bills.create-service'))
                                    @php
                                        $createFreightUrl = route('purchase.bills.create-service', [
                                            'mode'              => 'outbound',
                                            'dispatch_order_id' => $dispatch->id,
                                        ]);
                                    @endphp
                                    <a href="{{ $createFreightUrl }}" class="btn btn-xs btn-primary shadow-sm w-100 py-1 fw-bold">
                                        <i class="feather-file-plus me-1"></i>{{ __('crm.create_freight_bill') }}
                                    </a>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- 3. Dispatched Line Items Table --}}
            <div class="mb-4">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <h6 class="fw-bold text-dark mb-0 fs-14">
                        <i class="feather-list me-1.5 text-primary"></i>{{ __('crm.dispatched_line_items') }}
                    </h6>
                    <span class="badge bg-soft-secondary text-dark fs-11">{{ __('crm.items_count', ['count' => count($dispatch->items)]) }}</span>
                </div>

                <div class="table-responsive border rounded-3">
                    <table class="table align-middle fs-13 mb-0">
                        <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                            <tr>
                                <th class="ps-3" style="width: 5%;">#</th>
                                <th style="width: 40%;">{{ __('crm.product_details') }}</th>
                                <th style="width: 25%;">{{ __('crm.warehouse_location') }}</th>
                                <th style="width: 15%;">{{ __('crm.batch_serial_tracking') }}</th>
                                <th class="text-end" style="width: 7.5%;">{{ __('crm.order_qty') }}</th>
                                <th class="text-end pe-3" style="width: 7.5%;">{{ __('crm.dispatched_qty') }}</th>
                            </tr>
                        </thead>
                        <tbody class="text-dark">
                            @forelse ($dispatch->items as $index => $item)
                                <tr>
                                    <td class="ps-3 text-muted fs-12">{{ $index + 1 }}</td>
                                    <td>
                                        <strong class="text-dark">{{ $item->product?->name }}</strong>
                                        @if ($item->product?->sku)
                                            <small class="text-muted d-block font-monospace fs-10">SKU: {{ $item->product->sku }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="fw-semibold text-dark"><i class="feather-box me-1 text-muted"></i>{{ $item->warehouse?->name ?? 'Main Warehouse' }}</span>
                                    </td>
                                    <td>
                                        @if($item->batch_number)
                                            <span class="badge bg-soft-info text-info font-monospace fs-11">Batch: {{ $item->batch_number }}</span>
                                        @endif
                                        @if($item->serial_numbers)
                                            <span class="badge bg-soft-success text-success font-monospace fs-11 ms-1">SN: {{ is_array($item->serial_numbers) ? implode(', ', $item->serial_numbers) : $item->serial_numbers }}</span>
                                        @endif
                                        @if(!$item->batch_number && !$item->serial_numbers)
                                            <span class="text-muted fs-11">—</span>
                                        @endif
                                    </td>
                                    <td class="text-end fw-semibold">{{ (int)$item->quantity_ordered }}</td>
                                    <td class="text-end fw-bold text-success pe-3 fs-14">{{ (int)$item->quantity_dispatched }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center py-4 text-muted">{{ __('crm.no_items_found') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- 4. Bottom Cards Row: Shipping Address & POD Document Status --}}
            <div class="row g-4 pt-2">
                <div class="col-md-6">
                    <div class="p-3 bg-light rounded-3 border h-100">
                        <h6 class="fw-bold text-dark fs-13 mb-2">
                            <i class="feather-map-pin me-1.5 text-danger"></i>{{ __('crm.delivery_shipping_address') }}
                        </h6>
                        @if($dispatch->shipping_address)
                            <p class="mb-0 text-dark fs-12 leading-relaxed">{!! nl2br(e($dispatch->shipping_address)) !!}</p>
                        @elseif($dispatch->customer?->address)
                            <p class="mb-0 text-dark fs-12 leading-relaxed">{!! nl2br(e($dispatch->customer->address)) !!}</p>
                        @else
                            <p class="mb-0 text-muted fs-12 italic">{{ __('crm.default_billing_location') }}</p>
                        @endif
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="p-3 bg-light rounded-3 border h-100 d-flex flex-column justify-content-between">
                        <div>
                            <h6 class="fw-bold text-dark fs-13 mb-2">
                                <i class="feather-check-square me-1.5 text-success"></i>{{ __('crm.proof_of_delivery') }}
                            </h6>
                            @if($dispatch->pod_attachment_path)
                                <div class="alert alert-soft-success mb-2 p-2.5 d-flex align-items-center justify-content-between border-success-subtle">
                                    <div>
                                        <span class="fw-bold text-success fs-12"><i class="feather-file-text me-1"></i>{{ __('crm.pod_document_attached') }}</span>
                                        <small class="d-block text-muted fs-11">{{ $dispatch->delivered_at ? $dispatch->delivered_at->format('d M Y, h:i A') : 'Confirmed' }}</small>
                                    </div>
                                    <a href="{{ Storage::url($dispatch->pod_attachment_path) }}" target="_blank" class="btn btn-xs btn-success fw-bold">View POD</a>
                                </div>
                            @elseif($dispatch->status === 'Delivered')
                                <div class="alert alert-soft-info mb-2 p-2.5 d-flex align-items-center justify-content-between">
                                    <div>
                                        <span class="fw-semibold text-info fs-12"><i class="feather-check-circle me-1"></i>{{ __('crm.marked_delivered_no_file') }}</span>
                                        <small class="d-block text-muted fs-11">{{ $dispatch->delivered_at ? $dispatch->delivered_at->format('d M Y, h:i A') : 'Confirmed' }}</small>
                                    </div>
                                    <button type="button" class="btn btn-xs btn-outline-info" data-bs-toggle="modal" data-bs-target="#uploadPodModal">{{ __('crm.attach_pod_file') }}</button>
                                </div>
                            @else
                                <p class="text-muted fs-12 mb-2">{{ __('crm.no_pod_uploaded') }}</p>
                            @endif
                        </div>
                        <div class="pt-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary w-100 fw-semibold" data-bs-toggle="modal" data-bs-target="#uploadPodModal">
                                <i class="feather-upload me-1"></i>{{ $dispatch->status === 'Delivered' ? __('crm.update_delivery_pod') : __('crm.mark_delivered_upload_pod') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            @if($dispatch->notes)
                <div class="mt-4 pt-3 border-top">
                    <h6 class="fw-bold text-dark fs-12 text-uppercase mb-1"><i class="feather-file-text me-1 text-primary"></i>{{ __('crm.internal_dispatch_notes') }}</h6>
                    <p class="mb-0 text-muted fs-12">{{ $dispatch->notes }}</p>
                </div>
            @endif

        </x-ui.odoo-form-ui>
    </div>

    <!-- Upload POD / Mark Delivered Modal -->
    <x-ui.modal id="uploadPodModal" :title="__('crm.mark_delivered_attach_pod')" size="md" :centered="true">
        <form method="POST" action="{{ route('sales.dispatches.upload-pod', $dispatch->id) }}" enctype="multipart/form-data" id="uploadPodForm">
            @csrf
            <div class="mb-3">
                <label class="form-label fw-semibold fs-12 text-dark">{{ __('crm.signed_pod_file') }} <span class="text-muted fw-normal">{{ __('crm.optional') }}</span></label>
                <input type="file" name="pod_file" class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png">
                <span class="fs-10 text-muted mt-1 d-block"><i class="feather-info me-1"></i>{{ __('crm.leave_blank_if_delivered') }}</span>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold fs-12 text-dark">{{ __('crm.actual_delivery_datetime') }}</label>
                <input type="datetime-local" name="delivered_at" class="form-control form-control-sm" value="{{ now()->format('Y-m-d\TH:i') }}">
            </div>
        </form>
        <x-slot:footer>
            <button type="button" class="btn btn-light border" data-bs-dismiss="modal">{{ __('crm.cancel') }}</button>
            <button type="submit" form="uploadPodForm" class="btn btn-success" style="background-color: #059669; border-color: #059669;"><i class="feather-check me-1"></i>{{ __('crm.mark_as_delivered') }}</button>
        </x-slot:footer>
    </x-ui.modal>

    <!-- Update Tracking / Logistics Details Modal -->
    <x-ui.modal id="updateTrackingModal" :title="__('crm.update_tracking_logistics')" size="md" :centered="true">
        <form method="POST" action="{{ route('sales.dispatches.update-tracking', $dispatch->id) }}" id="updateTrackingForm">
            @csrf
            <div class="row g-2 mb-3">
                <div class="col-md-7">
                    <label class="form-label fw-semibold fs-12 text-dark">{{ __('crm.lr_bilty_number') }}</label>
                    <input type="text" name="lr_number" class="form-control form-control-sm font-monospace fw-bold text-uppercase" value="{{ old('lr_number', $dispatch->lr_number) }}" placeholder="e.g. VTRANS-LR-998822">
                </div>
                <div class="col-md-5">
                    <label class="form-label fw-semibold fs-12 text-dark">{{ __('crm.lr_bilty_date') }}</label>
                    <input type="date" name="lr_date" class="form-control form-control-sm" value="{{ old('lr_date', $dispatch->lr_date ? $dispatch->lr_date->format('Y-m-d') : '') }}">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold fs-12 text-dark">{{ __('crm.tracking_docket_number') }}</label>
                <input type="text" name="tracking_number" class="form-control form-control-sm font-monospace fw-bold" value="{{ old('tracking_number', $dispatch->tracking_number) }}" placeholder="e.g. BLUEDART-8899772211, TRACK-9090...">
                <span class="fs-10 text-muted mt-1 d-block"><i class="feather-info me-1"></i>{{ __('crm.tracking_help') }}</span>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold fs-12 text-dark">{{ __('crm.carrier_courier_partner') }}</label>
                <input type="text" name="carrier" class="form-control form-control-sm" value="{{ old('carrier', $dispatch->carrier) }}" placeholder="e.g. BlueDart, DHL, Professional Courier...">
            </div>

            <div class="row g-2 mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold fs-12 text-dark">{{ __('crm.vehicle_number') }}</label>
                    <input type="text" name="vehicle_number" class="form-control form-control-sm font-monospace text-uppercase" value="{{ old('vehicle_number', $dispatch->vehicle_number) }}" placeholder="e.g. MH-12-AB-1234">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold fs-12 text-dark">{{ __('crm.driver_name') }}</label>
                    <input type="text" name="driver_name" class="form-control form-control-sm" value="{{ old('driver_name', $dispatch->driver_name) }}" placeholder="Driver Full Name">
                </div>
            </div>
            <div class="row g-2 mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold fs-12 text-dark">{{ __('crm.freight_terms') }}</label>
                    <select name="freight_terms" class="form-select form-select-sm">
                        <option value="To Pay" @selected(old('freight_terms', $dispatch->freight_terms) === 'To Pay')>{{ __('crm.freight_to_pay') }}</option>
                        <option value="To Be Billed" @selected(old('freight_terms', $dispatch->freight_terms) === 'To Be Billed')>{{ __('crm.freight_to_be_billed') }}</option>
                        <option value="Prepaid" @selected(old('freight_terms', $dispatch->freight_terms) === 'Prepaid')>{{ __('crm.freight_prepaid') }}</option>
                        <option value="Customer Pickup" @selected(old('freight_terms', $dispatch->freight_terms) === 'Customer Pickup')>{{ __('crm.freight_customer_pickup') }}</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold fs-12 text-dark">{{ __('crm.freight_amount') }}</label>
                    <input type="number" name="freight_amount" class="form-control form-control-sm text-end fw-bold" value="{{ old('freight_amount', (float)$dispatch->freight_amount) }}" min="0" step="0.01">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold fs-12 text-dark">{{ __('crm.driver_phone_number') }}</label>
                <input type="text" name="driver_phone" class="form-control form-control-sm" value="{{ old('driver_phone', $dispatch->driver_phone) }}" placeholder="e.g. 9876543210">
            </div>
        </form>
        <x-slot:footer>
            <button type="button" class="btn btn-light border" data-bs-dismiss="modal">{{ __('crm.cancel') }}</button>
            <button type="submit" form="updateTrackingForm" class="btn btn-primary" style="background-color: #714B67; border-color: #714B67;">{{ __('crm.save_logistics_details') }}</button>
        </x-slot:footer>
    </x-ui.modal>

@endsection
