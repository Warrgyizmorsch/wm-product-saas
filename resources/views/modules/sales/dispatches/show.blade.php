@extends('layouts.duralux')

@section('title', __('crm.dispatch_order') . ' ' . $dispatch->dispatch_number . ' | SaaS ERP')
@section('page-title', __('crm.dispatch_order') . ' ' . $dispatch->dispatch_number)
@section('breadcrumb', __('crm.sales_breadcrumb') . ' / ' . $dispatch->dispatch_number)

@section('page-actions')
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <a href="{{ route('sales.dispatches.index') }}" class="btn btn-sm btn-light border px-2 py-1 d-inline-flex align-items-center justify-content-center" title="{{ __('crm.back_to_dispatches') }}">
            <i class="feather-arrow-left fs-14"></i>
        </a>

        <!-- Chevron Status Pipeline for Dispatch Order -->
        <div class="do-status-pipeline d-inline-flex align-items-center d-print-none me-1">
            @php
                $doStatuses = ['Draft', 'Confirmed', 'Shipped', 'Delivered'];
                if ($dispatch->status === 'Cancelled') {
                    $doStatuses[] = 'Cancelled';
                }
                $currentDoStatus = match($dispatch->status) {
                    'Pending', 'Draft' => 'Draft',
                    'Confirmed' => 'Confirmed',
                    'Dispatched', 'Shipped' => 'Shipped',
                    'Delivered' => 'Delivered',
                    'Cancelled' => 'Cancelled',
                    default => $dispatch->status
                };
                $doIndex = array_search($currentDoStatus, $doStatuses);
            @endphp
            @foreach($doStatuses as $idx => $st)
                @php
                    $cls = '';
                    if ($currentDoStatus === $st) {
                        $cls = 'active';
                        if ($st === 'Cancelled') $cls .= ' step-danger';
                        elseif ($st === 'Confirmed') $cls .= ' step-warning';
                        elseif ($st === 'Delivered') $cls .= ' step-success';
                    } elseif ($doIndex !== false && $idx < $doIndex) {
                        $cls = 'completed';
                    }
                @endphp
                <span class="pipeline-step {{ $cls }}">
                    @if($st === 'Draft') Draft
                    @elseif($st === 'Confirmed') Confirmed
                    @elseif($st === 'Shipped') Shipped
                    @elseif($st === 'Delivered') Delivered
                    @elseif($st === 'Cancelled') Cancelled
                    @else {{ $st }}
                    @endif
                </span>
            @endforeach
        </div>

        {{-- Primary Action Button --}}
        @if ($dispatch->status === 'Pending')
            <form action="{{ route('sales.dispatches.confirm', $dispatch->id) }}" method="POST" id="confirmDispatchForm" class="d-inline">
                @csrf
                <button type="button" class="btn btn-sm btn-success fw-semibold px-2.5 py-1 fs-12 text-nowrap shadow-2xs" onclick="confirmAction({ title: '{{ __('crm.confirm_dispatch_order') }}', message: '{{ __('crm.confirm_dispatch') }} {{ $dispatch->dispatch_number }}?', variant: 'success', confirmText: '{{ __('crm.confirm') }}' }, function() { document.getElementById('confirmDispatchForm').submit(); })">
                    <i class="feather-check-circle me-1"></i> {{ __('crm.confirm_do') }}
                </button>
            </form>
        @elseif ($dispatch->status === 'Confirmed')
            <form action="{{ route('sales.dispatches.ship', $dispatch->id) }}" method="POST" id="shipDispatchForm" class="d-inline">
                @csrf
                <button type="button" class="btn btn-sm btn-primary fw-semibold px-2.5 py-1 fs-12 text-nowrap shadow-2xs" onclick="confirmAction({ title: '{{ __('crm.ship_outward') }}', message: '{{ __('crm.ship_outward') }} {{ $dispatch->dispatch_number }}?', variant: 'primary', confirmText: '{{ __('crm.ship_outward') }}' }, function() { document.getElementById('shipDispatchForm').submit(); })">
                    <i class="feather-truck me-1"></i> {{ __('crm.ship_outward') }}
                </button>
            </form>
        @elseif (in_array($dispatch->status, ['Shipped', 'Dispatched']))
            <button type="button" class="btn btn-sm btn-success fw-semibold px-2.5 py-1 fs-12 text-nowrap shadow-2xs" data-bs-toggle="modal" data-bs-target="#uploadPodModal">
                <i class="feather-check-square me-1"></i> {{ __('crm.mark_delivered_pod') }}
            </button>
        @endif

        {{-- More Actions Dropdown --}}
        <div class="dropdown d-inline-block d-print-none">
            <button class="btn btn-sm btn-light border fw-semibold px-2.5 py-1 dropdown-toggle fs-12" type="button" id="doMoreActionsDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="feather-more-horizontal me-1 text-muted"></i>More
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm fs-13" aria-labelledby="doMoreActionsDropdown">
                <li>
                    <a href="{{ route('sales.dispatches.download-challan', $dispatch->id) }}" target="_blank" class="dropdown-item py-1.5">
                        <i class="feather-file-text me-2 text-danger"></i>{{ __('crm.challan_pdf') ?? 'Delivery Challan PDF' }}
                    </a>
                </li>
                <li>
                    <a href="javascript:void(0)" onclick="window.print()" class="dropdown-item py-1.5">
                        <i class="feather-printer me-2 text-muted"></i>Print Challan
                    </a>
                </li>
                <li>
                    <button type="button" class="dropdown-item py-1.5" data-bs-toggle="modal" data-bs-target="#updateTrackingModal">
                        <i class="feather-truck me-2 text-primary"></i>{{ __('crm.edit_logistics_tracking') }}
                    </button>
                </li>
                @if ($dispatch->status === 'Delivered')
                    <li>
                        <button type="button" class="dropdown-item py-1.5" data-bs-toggle="modal" data-bs-target="#uploadPodModal">
                            <i class="feather-check-square me-2 text-success"></i>{{ __('crm.update_pod') }}
                        </button>
                    </li>
                @endif
                @if (in_array($dispatch->status, ['Pending', 'Confirmed']))
                    <li><hr class="dropdown-divider my-1"></li>
                    <li>
                        <button type="button" class="dropdown-item py-1.5 text-danger" data-bs-toggle="modal" data-bs-target="#cancelDispatchModal">
                            <i class="feather-x-circle me-2 text-danger"></i>Cancel Dispatch Order
                        </button>
                    </li>
                @endif
            </ul>
        </div>
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
                            <h3 class="fw-bold text-dark mb-0 fs-22 tracking-tight">{{ $dispatch->dispatch_number }}</h3>
                            @php
                                $badgeDetails = match($dispatch->status) {
                                    'Confirmed' => ['cls' => 'bg-soft-warning text-warning border border-warning-subtle', 'icon' => 'feather-box', 'label' => 'Confirmed • Stock Allocated / Reserved'],
                                    'Shipped', 'Dispatched' => ['cls' => 'bg-soft-info text-info border border-info-subtle', 'icon' => 'feather-truck', 'label' => 'In Transit • Dispatched'],
                                    'Delivered' => ['cls' => 'bg-soft-success text-success border border-success-subtle', 'icon' => 'feather-check-circle', 'label' => 'Delivered • POD Verified'],
                                    'Cancelled' => ['cls' => 'bg-soft-danger text-danger border border-danger-subtle', 'icon' => 'feather-x-circle', 'label' => 'Cancelled'],
                                    default => ['cls' => 'bg-soft-secondary text-secondary border border-secondary-subtle', 'icon' => 'feather-clock', 'label' => 'Draft / Pending Confirmation'],
                                };
                            @endphp
                            <span class="badge {{ $badgeDetails['cls'] }} px-3 py-1.5 fs-11 fw-bold text-uppercase shadow-2xs d-inline-flex align-items-center">
                                <i class="{{ $badgeDetails['icon'] }} me-1.5 fs-12"></i>{{ $badgeDetails['label'] }}
                            </span>
                        </div>

                        <div class="d-flex align-items-center gap-3 text-muted fs-12 mt-2 flex-wrap">
                            <span>
                                <i class="feather-calendar me-1 text-muted"></i>
                                <strong class="text-dark">{{ __('crm.dispatch_date') }}:</strong> 
                                <span class="fw-bold text-primary">{{ $dispatch->dispatch_date ? $dispatch->dispatch_date->format('d M Y') : '—' }}</span>
                            </span>
                            <span class="text-black-50">&bull;</span>
                            <span>
                                <i class="feather-user me-1 text-primary"></i>
                                <strong class="text-dark">{{ __('crm.customer') }}:</strong> 
                                @if($dispatch->customer)
                                    <a href="{{ route('crm.customers.show', $dispatch->customer_id) }}" class="fw-bold text-dark hover-underline">
                                        {{ $dispatch->customer->name }}
                                    </a>
                                @else
                                    <strong class="text-dark">Direct Customer</strong>
                                @endif
                            </span>
                            @if($dispatch->sales_order_id && $dispatch->salesOrder)
                                <span class="text-black-50">&bull;</span>
                                <span>
                                    <i class="feather-shopping-bag me-1 text-info"></i>
                                    <strong class="text-dark">{{ __('crm.sales_order') }}:</strong> 
                                    <a href="{{ route('sales.orders.show', $dispatch->sales_order_id) }}" class="badge bg-soft-info text-info border border-info-subtle fw-bold fs-11 px-2 py-0.5">
                                        {{ $dispatch->salesOrder->sales_order_number }}
                                    </a>
                                </span>
                            @endif
                            @if($dispatch->material_requirement_id && $dispatch->materialRequirement)
                                <span class="text-black-50">&bull;</span>
                                <span>
                                    <i class="feather-layers me-1 text-secondary"></i>
                                    <strong class="text-dark">{{ __('crm.material_requirement') }}:</strong> 
                                    <a href="{{ route('inventory.material-requirements.show', $dispatch->material_requirement_id) }}" class="font-monospace fw-bold text-primary">
                                        {{ $dispatch->materialRequirement->requirement_number }}
                                    </a>
                                </span>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Right Summary Block --}}
                <div class="d-flex align-items-center gap-3 text-end">
                    <div class="p-2 px-3 bg-light rounded-3 border text-end d-none d-sm-block">
                        <span class="text-muted fs-11 text-uppercase fw-semibold d-block">Total Quantity</span>
                        <span class="fw-extrabold text-primary fs-16 font-monospace">{{ (float)$dispatch->items->sum('quantity_dispatched') }} Units</span>
                        <small class="text-muted d-block fs-11">{{ $dispatch->items->count() }} item(s)</small>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary fw-semibold" data-bs-toggle="modal" data-bs-target="#updateTrackingModal">
                        <i class="feather-edit-2 me-1"></i>{{ __('crm.edit_logistics_tracking') }}
                    </button>
                </div>
            </div>

            @if($dispatch->status === 'Cancelled' && $dispatch->cancellation_reason)
                <div class="alert alert-danger py-2.5 px-3 fs-13 mb-4 d-flex align-items-start border border-danger-subtle">
                    <i class="feather-alert-triangle me-2 fs-16 text-danger mt-0.5 flex-shrink-0"></i>
                    <div>
                        <strong>Cancellation Reason:</strong> {{ $dispatch->cancellation_reason }}
                    </div>
                </div>
            @endif

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
                                <th class="ps-3" style="width: 4%;">#</th>
                                <th style="width: 32%;">{{ __('crm.product_details') }}</th>
                                <th style="width: 20%;">{{ __('crm.warehouse_location') }}</th>
                                <th style="width: 14%;">{{ __('crm.batch_serial_tracking') }}</th>
                                <th class="text-center" style="width: 16%;">LINE STATUS</th>
                                <th class="text-end" style="width: 7%;">ORDER QTY</th>
                                <th class="text-end pe-3" style="width: 7%;">{{ in_array($dispatch->status, ['Shipped', 'Dispatched', 'Delivered']) ? 'SHIPPED QTY' : 'ALLOCATED QTY' }}</th>
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
                                        @if ($item->salesOrderItem)
                                            <small class="text-muted d-block mt-0.5 fs-11">
                                                Linked SO Item: <span class="font-monospace text-primary">#{{ $item->sales_order_item_id }}</span>
                                            </small>
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
                                    <td class="text-center">
                                        @if ($dispatch->status === 'Confirmed')
                                            <span class="badge bg-soft-warning text-warning border border-warning-subtle fs-11 px-2 py-0.5">
                                                <i class="feather-box me-1"></i>Allocated (Stock Reserved)
                                            </span>
                                        @elseif (in_array($dispatch->status, ['Shipped', 'Dispatched']))
                                            <span class="badge bg-soft-info text-info border border-info-subtle fs-11 px-2 py-0.5">
                                                <i class="feather-truck me-1"></i>In Transit
                                            </span>
                                        @elseif ($dispatch->status === 'Delivered')
                                            <span class="badge bg-soft-success text-success border border-success-subtle fs-11 px-2 py-0.5">
                                                <i class="feather-check-circle me-1"></i>Delivered (POD)
                                            </span>
                                        @elseif ($dispatch->status === 'Cancelled')
                                            <span class="badge bg-soft-danger text-danger border border-danger-subtle fs-11 px-2 py-0.5">
                                                <i class="feather-x me-1"></i>Cancelled
                                            </span>
                                        @else
                                            <span class="badge bg-soft-secondary text-secondary fs-11 px-2 py-0.5">
                                                Draft / Pending
                                            </span>
                                        @endif
                                    </td>
                                    <td class="text-end fw-semibold">{{ (int)$item->quantity_ordered }}</td>
                                    <td class="text-end fw-bold {{ in_array($dispatch->status, ['Shipped', 'Dispatched', 'Delivered']) ? 'text-success' : 'text-primary' }} pe-3 fs-14">
                                        {{ (int)$item->quantity_dispatched }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">{{ __('crm.no_items_found') }}</td>
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

    <!-- Cancel Dispatch Order Modal -->
    @if(in_array($dispatch->status, ['Pending', 'Confirmed']))
        <x-ui.modal id="cancelDispatchModal" :title="'<i class=\'feather-x-circle me-2 text-danger\'></i>' . 'Cancel Dispatch Order'" :centered="true" :formAction="route('sales.dispatches.cancel', $dispatch->id)" formMethod="POST">
            <div class="mb-3">
                <div class="alert alert-danger py-2.5 px-3 fs-13 mb-3 d-flex align-items-start">
                    <i class="feather-alert-circle me-2 fs-16 text-danger mt-0.5 flex-shrink-0"></i>
                    <div>
                        <strong>Are you sure?</strong> You are about to cancel Dispatch Order <strong>{{ $dispatch->dispatch_number }}</strong>. All reserved inventory for this dispatch order will be automatically unallocated and restored to available warehouse stock.
                    </div>
                </div>
                <label for="dispatchCancelReason" class="form-label fw-semibold fs-13">Reason for Cancellation</label>
                <textarea name="reason" id="dispatchCancelReason" class="form-control fs-13" rows="3" placeholder="Enter reason (e.g. Shipment cancelled by customer, logistics re-allocated...)"></textarea>
            </div>

            <x-slot:footer>
                <button type="button" class="btn btn-light-brand" data-bs-dismiss="modal">{{ __('crm.close') ?? 'Close' }}</button>
                <button type="submit" class="btn btn-danger fw-bold">
                    <i class="feather-x-circle me-1"></i>Confirm Cancellation
                </button>
            </x-slot:footer>
        </x-ui.modal>
    @endif

@endsection

@push('styles')
<style>
    .do-status-pipeline {
        display: inline-flex;
        border-radius: 4px;
        overflow: hidden;
        border: 1px solid #e2e8f0;
        background-color: #f8fafc;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        font-size: 11px;
        font-weight: 600;
        letter-spacing: 0.3px;
        text-transform: uppercase;
    }

    .do-status-pipeline .pipeline-step {
        position: relative;
        padding: 5px 14px 5px 22px;
        color: #64748b;
        background-color: #f8fafc;
        transition: all 0.2s ease;
        white-space: nowrap;
        display: inline-flex;
        align-items: center;
    }

    .do-status-pipeline .pipeline-step:first-child {
        padding-left: 12px;
    }

    .do-status-pipeline .pipeline-step::after {
        content: "";
        position: absolute;
        top: 0;
        right: -8px;
        width: 0;
        height: 0;
        border-top: 13px solid transparent;
        border-bottom: 13px solid transparent;
        border-left: 8px solid #f8fafc;
        z-index: 10;
        transition: all 0.2s ease;
    }

    .do-status-pipeline .pipeline-step::before {
        content: "";
        position: absolute;
        top: 0;
        left: 0;
        width: 0;
        height: 0;
        border-top: 13px solid transparent;
        border-bottom: 13px solid transparent;
        border-left: 8px solid #ffffff;
        z-index: 5;
        transition: all 0.2s ease;
    }

    .do-status-pipeline .pipeline-step:first-child::before {
        display: none;
    }

    .do-status-pipeline .pipeline-step.active {
        background-color: var(--bs-primary, #1e40af);
        color: #ffffff;
        z-index: 11;
    }

    .do-status-pipeline .pipeline-step.active::after {
        border-left-color: var(--bs-primary, #1e40af);
    }

    .do-status-pipeline .pipeline-step.completed {
        background-color: #e2e8f0;
        color: #334155;
        z-index: 6;
    }

    .do-status-pipeline .pipeline-step.completed::after {
        border-left-color: #e2e8f0;
    }

    .do-status-pipeline .pipeline-step.active.step-warning {
        background-color: #d97706 !important;
        color: #ffffff !important;
    }

    .do-status-pipeline .pipeline-step.active.step-warning::after {
        border-left-color: #d97706 !important;
    }

    .do-status-pipeline .pipeline-step.active.step-danger {
        background-color: #dc2626 !important;
        color: #ffffff !important;
    }

    .do-status-pipeline .pipeline-step.active.step-danger::after {
        border-left-color: #dc2626 !important;
    }

    .do-status-pipeline .pipeline-step.active.step-success {
        background-color: #16a34a !important;
        color: #ffffff !important;
    }

    .do-status-pipeline .pipeline-step.active.step-success::after {
        border-left-color: #16a34a !important;
    }

    /* Dark mode support */
    html.app-skin-dark .do-status-pipeline {
        background-color: #0f172a !important;
        border-color: #1e293b !important;
    }

    html.app-skin-dark .do-status-pipeline .pipeline-step {
        background-color: #0f172a;
        color: #94a3b8;
    }

    html.app-skin-dark .do-status-pipeline .pipeline-step::after {
        border-left-color: #0f172a;
    }

    html.app-skin-dark .do-status-pipeline .pipeline-step::before {
        border-left-color: #1e293b;
    }

    html.app-skin-dark .do-status-pipeline .pipeline-step.completed {
        background-color: #1e293b;
        color: #cbd5e1;
    }

    html.app-skin-dark .do-status-pipeline .pipeline-step.completed::after {
        border-left-color: #1e293b;
    }
</style>
@endpush
