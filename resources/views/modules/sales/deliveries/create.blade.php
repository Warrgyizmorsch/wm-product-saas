@extends('layouts.duralux')

@section('title', 'Create Delivery Order | SaaS ERP')
@section('page-title', 'Create Delivery Order')
@section('breadcrumb', 'Sales / Deliveries / Create')

@section('content')
    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-4" role="alert">
            <div class="d-flex align-items-center">
                <div class="avatar-text avatar-md bg-danger text-white me-3">
                    <i class="feather-alert-triangle"></i>
                </div>
                <div>
                    <h6 class="alert-heading fw-bold mb-1">Error!</h6>
                    <ul class="fs-12 mb-0 ps-3">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <form action="{{ route('sales.deliveries.store') }}" method="POST" id="deliveryForm">
        @csrf
        <input type="hidden" name="sales_order_id" value="{{ $salesOrder->id }}">

        <x-ui.odoo-form-ui type="sheet" class="erp-single-panel bg-white p-4">
            <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">
                <div>
                    <h5 class="fw-bold text-dark mb-0">New Delivery Order (Fulfillment)</h5>
                    <span class="fs-12 text-muted">Fulfillment for Sales Order: <strong>{{ $salesOrder->sales_order_number }}</strong></span>
                </div>
                <a href="{{ route('sales.orders.show', $salesOrder->id) }}" class="btn btn-sm btn-light border">Cancel</a>
            </div>

            <div class="row g-4 mb-4 fs-13 text-dark">
                <!-- Column 1: Order Refs & Carrier -->
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="input" label="Customer" name="_customer_display" :value="$salesOrder->customer?->name" readonly="true" style="font-weight: bold; background-color: #f8f9fa;" />

                    <x-ui.odoo-form-ui type="input" label="Carrier / Courier" name="carrier" :value="old('carrier')" placeholder="e.g. FedEx, Blue Dart, DHL" />

                    <x-ui.odoo-form-ui type="input" label="Tracking Number" name="tracking_number" :value="old('tracking_number')" placeholder="AWB tracking reference..." />
                </div>

                <!-- Column 2: Delivery Meta -->
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="input" label="Delivery Number" name="delivery_number" :value="old('delivery_number', $nextDeliveryNumber)" :readonly="true" :required="true" style="font-weight: bold; color: #495057;" />

                    <x-ui.odoo-form-ui type="input" inputType="date" label="Delivery Date" name="delivery_date" :value="old('delivery_date', date('Y-m-d'))" :required="true" />
                </div>
            </div>

            <!-- Notes -->
            <div class="row g-4 mt-1 border-top pt-3 fs-13 text-dark">
                <div class="col-md-12">
                    <x-ui.odoo-form-ui type="textarea" label="Internal Notes" name="notes" rows="2" placeholder="Private internal shipping remarks...">{{ old('notes') }}</x-ui.odoo-form-ui>
                </div>
            </div>

            <!-- Delivery Lines Table -->
            <div class="border-top pt-4 mt-4">
                <h5 class="fw-bold text-dark mb-3 fs-14">Items to Ship</h5>
                <div class="table-responsive">
                    <x-ui.odoo-form-ui type="table" id="deliveryItemsTable">
                        <thead>
                            <tr>
                                <th style="width: 35%;">Product Details</th>
                                <th style="width: 25%;">Source Warehouse</th>
                                <th class="text-end" style="width: 13%;">Ordered Qty</th>
                                <th class="text-end" style="width: 13%;">Shipped Qty</th>
                                <th class="text-end pe-3" style="width: 14%;">Qty to Deliver</th>
                            </tr>
                        </thead>
                        <tbody class="fs-13 text-dark">
                            @foreach ($salesOrder->items as $item)
                                @php
                                    $shipped = $shippedQuantities[$item->id] ?? 0.0;
                                    $remaining = max(0.0, (float)$item->quantity - $shipped);
                                    $isService = $item->product?->type === 'Service';
                                @endphp
                                @if (!$isService && $item->product_id)
                                    <tr>
                                        <td>
                                            <strong class="text-dark">{{ $item->item_name }}</strong>
                                            @if($item->product?->sku)
                                                <small class="text-muted d-block mt-0.5">SKU: {{ $item->product->sku }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            @php
                                                $firstAlloc = $salesOrder->stockAllocations->where('sales_order_item_id', $item->id)->first();
                                                $selectedWhId = $firstAlloc ? $firstAlloc->warehouse_id : null;
                                            @endphp
                                            <select name="items[{{ $item->id }}][warehouse_id]" class="form-select form-select-sm" style="max-width: 220px;" required>
                                                <option value="">Select Warehouse...</option>
                                                @foreach ($warehouses as $wh)
                                                    <option value="{{ $wh->id }}" {{ $selectedWhId == $wh->id ? 'selected' : '' }}>
                                                        {{ $wh->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td class="text-end fw-semibold">{{ (int)$item->quantity }}</td>
                                        <td class="text-end text-muted">{{ (int)$shipped }}</td>
                                        <td class="text-end pe-3">
                                            @if ($remaining > 0)
                                                <input type="number" 
                                                       name="items[{{ $item->id }}][quantity]" 
                                                       class="odoo-table-input text-end fw-bold text-primary qty-to-ship-input" 
                                                       value="{{ (int)$remaining }}" 
                                                       min="0" 
                                                       max="{{ (int)$remaining }}" 
                                                       required 
                                                       style="width: 100px; margin-left: auto;">
                                            @else
                                                <span class="badge bg-soft-success text-success px-2 py-0.5 fs-11">Fully Delivered</span>
                                                <input type="hidden" name="items[{{ $item->id }}][quantity]" value="0">
                                            @endif
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </x-ui.odoo-form-ui>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                <a href="{{ route('sales.orders.show', $salesOrder->id) }}" class="btn btn-md btn-light border py-2 px-4 shadow-sm fs-12">Discard</a>
                <button type="submit" class="btn btn-md btn-primary py-2 px-5 fw-bold shadow-sm fs-12" style="background-color: #1e40af; border-color: #1e40af;">Save Delivery Order</button>
            </div>
        </x-ui.odoo-form-ui>
    </form>
@endsection
