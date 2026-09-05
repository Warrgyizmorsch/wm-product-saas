@extends('layouts.duralux')

@section('title', 'Dispatch Order ' . $dispatch->dispatch_number . ' | SaaS ERP')
@section('page-title', 'Dispatch Order ' . $dispatch->dispatch_number)
@section('breadcrumb', 'Sales / Dispatches / ' . $dispatch->dispatch_number)

@section('page-actions')
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <a href="{{ route('sales.dispatches.index') }}" class="btn btn-sm btn-light border px-2 py-1 d-inline-flex align-items-center justify-content-center" title="Back to All Dispatches">
            <i class="feather-arrow-left fs-14"></i>
        </a>

        @if ($dispatch->status === 'Pending')
            <form action="{{ route('sales.dispatches.confirm', $dispatch->id) }}" method="POST" id="confirmDispatchForm" class="d-inline">
                @csrf
                <button type="button" class="btn btn-sm btn-success fw-semibold px-2.5 py-1 fs-12 text-nowrap" onclick="confirmAction({ title: 'Confirm Dispatch Order', message: 'Confirm dispatch for {{ $dispatch->dispatch_number }}? This will reserve warehouse stock for this order.', variant: 'success', confirmText: 'Confirm' }, function() { document.getElementById('confirmDispatchForm').submit(); })">
                    <i class="feather-check-circle me-1"></i> Confirm DO
                </button>
            </form>
        @elseif ($dispatch->status === 'Confirmed')
            <form action="{{ route('sales.dispatches.ship', $dispatch->id) }}" method="POST" id="shipDispatchForm" class="d-inline">
                @csrf
                <button type="button" class="btn btn-sm btn-primary fw-semibold px-2.5 py-1 fs-12 text-nowrap" onclick="confirmAction({ title: 'Ship & Outward Goods', message: 'Ship {{ $dispatch->dispatch_number }}? This will deduct physical stock from warehouse.', variant: 'primary', confirmText: 'Ship' }, function() { document.getElementById('shipDispatchForm').submit(); })">
                    <i class="feather-truck me-1"></i> Ship / Outward
                </button>
            </form>
        @endif

        <a href="{{ route('sales.dispatches.download-challan', $dispatch->id) }}" target="_blank" class="btn btn-sm btn-outline-danger fw-semibold px-2.5 py-1 fs-12 text-nowrap">
            <i class="feather-printer me-1"></i> Challan PDF
        </a>

        <button type="button" class="btn btn-sm btn-outline-secondary fw-semibold px-2.5 py-1 fs-12 text-nowrap" data-bs-toggle="modal" data-bs-target="#uploadPodModal">
            <i class="feather-check-square me-1"></i> {{ $dispatch->status === 'Delivered' ? 'Update POD' : 'Mark Delivered / POD' }}
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
                            <span><strong class="text-dark">Dispatch Date:</strong> <span class="fw-bold text-primary">{{ $dispatch->dispatch_date ? $dispatch->dispatch_date->format('d M Y') : '—' }}</span></span>
                            <span class="text-black-50">•</span>
                            <span><strong class="text-dark">Customer:</strong> <strong class="text-dark">{{ $dispatch->customer?->name ?? 'Direct Customer' }}</strong></span>
                            @if($dispatch->material_requirement_id && $dispatch->materialRequirement)
                                <span class="text-black-50">•</span>
                                <span><strong class="text-dark">Material Requirement:</strong> <a href="{{ route('sales.material-requirements.show', $dispatch->material_requirement_id) }}" class="fw-bold text-primary">{{ $dispatch->materialRequirement->requirement_number }}</a></span>
                            @endif
                            @if($dispatch->sales_order_id && $dispatch->salesOrder)
                                <span class="text-black-50">•</span>
                                <span><strong class="text-dark">Sales Order:</strong> <a href="{{ route('sales.orders.show', $dispatch->sales_order_id) }}" class="fw-bold text-info">{{ $dispatch->salesOrder->sales_order_number }}</a></span>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="text-end">
                    <button type="button" class="btn btn-sm btn-outline-primary fw-semibold" data-bs-toggle="modal" data-bs-target="#updateTrackingModal">
                        <i class="feather-edit-2 me-1"></i>Edit Logistics / Tracking
                    </button>
                </div>
            </div>

            {{-- 2. Single Page Logistics & Transporter Summary Strip --}}
            <div class="bg-light p-3 rounded-3 border mb-4">
                <div class="row g-3 fs-12">
                    <div class="col-md-3 col-6 border-end">
                        <span class="text-muted fs-11 text-uppercase fw-semibold d-block mb-1">Transporter Master</span>
                        <strong class="text-dark fs-13 d-block">{{ $dispatch->transporter?->name ?: ($dispatch->carrier ?: 'Self Pickup / Direct') }}</strong>
                        @if($dispatch->transporter?->transporter_id)
                            <small class="text-muted font-monospace">ID: {{ $dispatch->transporter->transporter_id }}</small>
                        @endif
                    </div>

                    <div class="col-md-3 col-6 border-end">
                        <span class="text-muted fs-11 text-uppercase fw-semibold d-block mb-1">Courier / Tracking #</span>
                        <strong class="font-monospace text-primary fs-13 d-block">{{ $dispatch->tracking_number ?: '—' }}</strong>
                        @if($dispatch->carrier)
                            <small class="text-muted d-block">Partner: {{ $dispatch->carrier }}</small>
                        @endif
                    </div>

                    <div class="col-md-3 col-6 border-end">
                        <span class="text-muted fs-11 text-uppercase fw-semibold d-block mb-1">Vehicle & Driver</span>
                        <strong class="font-monospace text-uppercase text-dark fs-13 d-block">{{ $dispatch->vehicle_number ?: '—' }}</strong>
                        @if($dispatch->driver_name)
                            <small class="text-muted d-block">{{ $dispatch->driver_name }} {{ $dispatch->driver_phone ? "({$dispatch->driver_phone})" : '' }}</small>
                        @endif
                    </div>

                    <div class="col-md-3 col-6">
                        <span class="text-muted fs-11 text-uppercase fw-semibold d-block mb-1">Freight Terms & Obligation</span>
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
                                <span class="fw-bold text-dark fs-12">₹{{ number_format($dispatch->freight_amount, 2) }}</span>
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
                                        <span>Billed Amt:</span>
                                        <strong class="text-dark">₹{{ number_format($actualAmt, 2) }}</strong>
                                    </div>
                                    @if(abs($variance) > 0.01)
                                        <div class="d-flex justify-content-between {{ $variance > 0 ? 'text-danger' : 'text-success' }}">
                                            <span>Variance:</span>
                                            <strong>{{ $variance > 0 ? '+' : '' }}₹{{ number_format($variance, 2) }}</strong>
                                        </div>
                                    @endif
                                </div>
                                @if(Route::has('purchase.bills.show'))
                                    <a href="{{ route('purchase.bills.show', $linkedFreightBill->id) }}" class="btn btn-xs btn-light text-primary border w-100 py-1 mt-1">
                                        <i class="feather-eye me-1"></i>View Freight Bill
                                    </a>
                                @endif
                            </div>
                        @elseif(in_array($dispatch->freight_terms, ['To Pay', 'to_pay', 'Customer Pickup', 'customer_pickup']))
                            <div class="mt-2 pt-1 border-top">
                                <small class="text-muted fs-11 d-block"><i class="feather-info me-1"></i>No Obligation (Collect / Self Pickup)</small>
                            </div>
                        @else
                            <div class="mt-2 pt-1 border-top">
                                <span class="badge bg-soft-warning text-warning border border-warning-subtle fs-11 fw-semibold d-block mb-1">
                                    <i class="feather-clock me-1"></i>Pending Freight Bill
                                </span>
                                @if(Route::has('purchase.bills.create-service'))
                                    @php
                                        $createFreightUrl = route('purchase.bills.create-service', [
                                            'mode'              => 'outbound',
                                            'dispatch_order_id' => $dispatch->id,
                                        ]);
                                    @endphp
                                    <a href="{{ $createFreightUrl }}" class="btn btn-xs btn-primary shadow-sm w-100 py-1 fw-bold">
                                        <i class="feather-file-plus me-1"></i>Create Freight Bill
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
                        <i class="feather-list me-1.5 text-primary"></i>Dispatched Line Items
                    </h6>
                    <span class="badge bg-soft-secondary text-dark fs-11">{{ count($dispatch->items) }} Item(s)</span>
                </div>

                <div class="table-responsive border rounded-3">
                    <table class="table align-middle fs-13 mb-0">
                        <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                            <tr>
                                <th class="ps-3" style="width: 5%;">#</th>
                                <th style="width: 40%;">Product Details</th>
                                <th style="width: 25%;">Warehouse Location</th>
                                <th style="width: 15%;">Batch / Serial Tracking</th>
                                <th class="text-end" style="width: 7.5%;">Order Qty</th>
                                <th class="text-end pe-3" style="width: 7.5%;">Dispatched Qty</th>
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
                                    <td colspan="6" class="text-center py-4 text-muted">No items found.</td>
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
                            <i class="feather-map-pin me-1.5 text-danger"></i>Delivery / Shipping Address (Ship-To)
                        </h6>
                        @if($dispatch->shipping_address)
                            <p class="mb-0 text-dark fs-12 leading-relaxed">{!! nl2br(e($dispatch->shipping_address)) !!}</p>
                        @elseif($dispatch->customer?->address)
                            <p class="mb-0 text-dark fs-12 leading-relaxed">{!! nl2br(e($dispatch->customer->address)) !!}</p>
                        @else
                            <p class="mb-0 text-muted fs-12 italic">Default customer billing location</p>
                        @endif
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="p-3 bg-light rounded-3 border h-100 d-flex flex-column justify-content-between">
                        <div>
                            <h6 class="fw-bold text-dark fs-13 mb-2">
                                <i class="feather-check-square me-1.5 text-success"></i>Proof of Delivery (POD) Status
                            </h6>
                            @if($dispatch->pod_attachment_path)
                                <div class="alert alert-soft-success mb-2 p-2.5 d-flex align-items-center justify-content-between border-success-subtle">
                                    <div>
                                        <span class="fw-bold text-success fs-12"><i class="feather-file-text me-1"></i>POD Document Attached</span>
                                        <small class="d-block text-muted fs-11">Delivered: {{ $dispatch->delivered_at ? $dispatch->delivered_at->format('d M Y, h:i A') : 'Confirmed' }}</small>
                                    </div>
                                    <a href="{{ Storage::url($dispatch->pod_attachment_path) }}" target="_blank" class="btn btn-xs btn-success fw-bold">View POD</a>
                                </div>
                            @elseif($dispatch->status === 'Delivered')
                                <div class="alert alert-soft-info mb-2 p-2.5 d-flex align-items-center justify-content-between">
                                    <div>
                                        <span class="fw-semibold text-info fs-12"><i class="feather-check-circle me-1"></i>Marked Delivered (No File Attached)</span>
                                        <small class="d-block text-muted fs-11">Date: {{ $dispatch->delivered_at ? $dispatch->delivered_at->format('d M Y, h:i A') : 'Confirmed' }}</small>
                                    </div>
                                    <button type="button" class="btn btn-xs btn-outline-info" data-bs-toggle="modal" data-bs-target="#uploadPodModal">Attach POD File</button>
                                </div>
                            @else
                                <p class="text-muted fs-12 mb-2">No Proof of Delivery (POD) uploaded or status is pending delivery.</p>
                            @endif
                        </div>
                        <div class="pt-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary w-100 fw-semibold" data-bs-toggle="modal" data-bs-target="#uploadPodModal">
                                <i class="feather-upload me-1"></i>{{ $dispatch->status === 'Delivered' ? 'Update Delivery / POD' : 'Mark Delivered / Upload POD' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            @if($dispatch->notes)
                <div class="mt-4 pt-3 border-top">
                    <h6 class="fw-bold text-dark fs-12 text-uppercase mb-1"><i class="feather-file-text me-1 text-primary"></i>Internal Dispatch Notes</h6>
                    <p class="mb-0 text-muted fs-12">{{ $dispatch->notes }}</p>
                </div>
            @endif

        </x-ui.odoo-form-ui>
    </div>

    <!-- Upload POD / Mark Delivered Modal -->
    <x-ui.modal id="uploadPodModal" title="Mark Delivered & Attach POD (Optional)" size="md" :centered="true">
        <form method="POST" action="{{ route('sales.dispatches.upload-pod', $dispatch->id) }}" enctype="multipart/form-data" id="uploadPodForm">
            @csrf
            <div class="mb-3">
                <label class="form-label fw-semibold fs-12 text-dark">Signed POD File <span class="text-muted fw-normal">(Optional)</span></label>
                <input type="file" name="pod_file" class="form-control form-control-sm" accept=".pdf,.jpg,.jpeg,.png">
                <span class="fs-10 text-muted mt-1 d-block"><i class="feather-info me-1"></i>Leave blank if marking delivered via tracking/verbal confirmation.</span>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold fs-12 text-dark">Actual Delivery Date & Time</label>
                <input type="datetime-local" name="delivered_at" class="form-control form-control-sm" value="{{ now()->format('Y-m-d\TH:i') }}">
            </div>
        </form>
        <x-slot:footer>
            <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" form="uploadPodForm" class="btn btn-success" style="background-color: #059669; border-color: #059669;"><i class="feather-check me-1"></i>Mark as Delivered</button>
        </x-slot:footer>
    </x-ui.modal>

    <!-- Update Tracking / Logistics Details Modal -->
    <x-ui.modal id="updateTrackingModal" title="Update Tracking & Logistics Information" size="md" :centered="true">
        <form method="POST" action="{{ route('sales.dispatches.update-tracking', $dispatch->id) }}" id="updateTrackingForm">
            @csrf
            <div class="row g-2 mb-3">
                <div class="col-md-7">
                    <label class="form-label fw-semibold fs-12 text-dark">LR / Bilty Number (Truck Transport)</label>
                    <input type="text" name="lr_number" class="form-control form-control-sm font-monospace fw-bold text-uppercase" value="{{ old('lr_number', $dispatch->lr_number) }}" placeholder="e.g. VTRANS-LR-998822">
                </div>
                <div class="col-md-5">
                    <label class="form-label fw-semibold fs-12 text-dark">LR / Bilty Date</label>
                    <input type="date" name="lr_date" class="form-control form-control-sm" value="{{ old('lr_date', $dispatch->lr_date ? $dispatch->lr_date->format('Y-m-d') : '') }}">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold fs-12 text-dark">Tracking / Docket Number (Courier Parcel)</label>
                <input type="text" name="tracking_number" class="form-control form-control-sm font-monospace fw-bold" value="{{ old('tracking_number', $dispatch->tracking_number) }}" placeholder="e.g. BLUEDART-8899772211, TRACK-9090...">
                <span class="fs-10 text-muted mt-1 d-block"><i class="feather-info me-1"></i>Enter courier tracking or AWB/docket number provided after pickup.</span>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold fs-12 text-dark">Carrier / Courier Partner</label>
                <input type="text" name="carrier" class="form-control form-control-sm" value="{{ old('carrier', $dispatch->carrier) }}" placeholder="e.g. BlueDart, DHL, Professional Courier...">
            </div>

            <div class="row g-2 mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold fs-12 text-dark">Vehicle Number</label>
                    <input type="text" name="vehicle_number" class="form-control form-control-sm font-monospace text-uppercase" value="{{ old('vehicle_number', $dispatch->vehicle_number) }}" placeholder="e.g. MH-12-AB-1234">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold fs-12 text-dark">Driver Name</label>
                    <input type="text" name="driver_name" class="form-control form-control-sm" value="{{ old('driver_name', $dispatch->driver_name) }}" placeholder="Driver Full Name">
                </div>
            </div>
            <div class="row g-2 mb-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold fs-12 text-dark">Freight Terms</label>
                    <select name="freight_terms" class="form-select form-select-sm">
                        <option value="To Pay" @selected(old('freight_terms', $dispatch->freight_terms) === 'To Pay')>To Pay (Collect by Driver)</option>
                        <option value="To Be Billed" @selected(old('freight_terms', $dispatch->freight_terms) === 'To Be Billed')>To Be Billed (Prepaid & Add)</option>
                        <option value="Prepaid" @selected(old('freight_terms', $dispatch->freight_terms) === 'Prepaid')>Prepaid (Freight Included)</option>
                        <option value="Customer Pickup" @selected(old('freight_terms', $dispatch->freight_terms) === 'Customer Pickup')>Customer Pickup (Self Vehicle)</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold fs-12 text-dark">Freight Amount (₹)</label>
                    <input type="number" name="freight_amount" class="form-control form-control-sm text-end fw-bold" value="{{ old('freight_amount', (float)$dispatch->freight_amount) }}" min="0" step="0.01">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold fs-12 text-dark">Driver Phone Number</label>
                <input type="text" name="driver_phone" class="form-control form-control-sm" value="{{ old('driver_phone', $dispatch->driver_phone) }}" placeholder="e.g. 9876543210">
            </div>
        </form>
        <x-slot:footer>
            <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" form="updateTrackingForm" class="btn btn-primary" style="background-color: #714B67; border-color: #714B67;">Save Logistics Details</button>
        </x-slot:footer>
    </x-ui.modal>

@endsection
