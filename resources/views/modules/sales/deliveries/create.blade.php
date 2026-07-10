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
                <x-ui.button href="{{ route('sales.orders.show', $salesOrder->id) }}" variant="light" size="sm" class="border">Cancel</x-ui.button>
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
                                                $selectedWhId = $item->warehouse_id;
                                            @endphp
                                            <select name="items[{{ $item->id }}][warehouse_id]" 
                                                    class="form-select form-select-sm warehouse-select" 
                                                    data-product-id="{{ $item->product_id }}" 
                                                    data-item-id="{{ $item->id }}" 
                                                    style="max-width: 220px;" required>
                                                <option value="">Select Warehouse...</option>
                                                @foreach ($warehouses as $wh)
                                                    <option value="{{ $wh->id }}" {{ $selectedWhId == $wh->id ? 'selected' : '' }}>
                                                        {{ $wh->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <span class="available-qty-display d-block text-muted fs-11 mt-1 font-monospace" id="avail-qty-{{ $item->id }}">Available: 0</span>
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
                                                       data-product-id="{{ $item->product_id }}"
                                                       data-item-id="{{ $item->id }}"
                                                       required 
                                                       style="width: 100px; margin-left: auto;">
                                                <div class="text-end text-danger fw-semibold mt-1 fs-11 validation-error-msg" id="error-{{ $item->id }}" style="display: none;"></div>
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
                <x-ui.button href="{{ route('sales.orders.show', $salesOrder->id) }}" variant="light" size="md" class="border py-2 px-4 fs-12 shadow-sm">Discard</x-ui.button>
                <x-ui.button type="submit" variant="primary" size="md" class="py-2 px-5 fw-bold fs-12 shadow-sm" style="background-color: #1e40af; border-color: #1e40af;">Save Delivery Order</x-ui.button>
            </div>
        </x-ui.odoo-form-ui>
    </form>
@endsection

@push('scripts')
<script>
    window.productWarehouseStocks = @json($stockMap);

    $(document).ready(function() {
        // Trigger validation on warehouse change and quantity change
        $(document).on('change', '.warehouse-select', function() {
            validateRow($(this).data('item-id'));
        });

        $(document).on('input change', '.qty-to-ship-input', function() {
            validateRow($(this).data('item-id'));
        });

        // Run validation on page load for all items
        $('.warehouse-select').each(function() {
            validateRow($(this).data('item-id'));
        });

        function validateRow(itemId) {
            const whSelect = $(`.warehouse-select[data-item-id="${itemId}"]`);
            const qtyInput = $(`.qty-to-ship-input[data-item-id="${itemId}"]`);
            const availDisplay = $(`#avail-qty-${itemId}`);
            const errorDisplay = $(`#error-${itemId}`);

            if (!whSelect.length || !qtyInput.length) return;

            const productId = whSelect.data('product-id');
            const warehouseId = whSelect.val();
            const qtyVal = parseFloat(qtyInput.val()) || 0;

            let availableStock = 0;
            if (warehouseId && window.productWarehouseStocks[productId] && window.productWarehouseStocks[productId][warehouseId] !== undefined) {
                availableStock = parseFloat(window.productWarehouseStocks[productId][warehouseId]);
            }

            // Update display
            if (warehouseId) {
                availDisplay.text(`Available: ${availableStock}`);
                availDisplay.removeClass('text-muted text-danger text-success');
                if (availableStock <= 0) {
                    availDisplay.addClass('text-danger');
                } else {
                    availDisplay.addClass('text-success');
                }
            } else {
                availDisplay.text('Available: 0');
                availDisplay.removeClass('text-danger text-success').addClass('text-muted');
            }

            // Validation check
            let isInvalid = false;
            let errorMsg = '';

            if (warehouseId && qtyVal > availableStock) {
                isInvalid = true;
                errorMsg = `Exceeds available stock (${availableStock})!`;
            }

            if (isInvalid) {
                qtyInput.addClass('is-invalid');
                errorDisplay.text(errorMsg).show();
            } else {
                qtyInput.removeClass('is-invalid');
                errorDisplay.hide().text('');
            }

            checkOverallFormValidity();
        }

        function checkOverallFormValidity() {
            let formHasErrors = false;

            // Check for is-invalid class on any input
            $('.qty-to-ship-input').each(function() {
                if ($(this).hasClass('is-invalid')) {
                    formHasErrors = true;
                }
            });

            // Check if any warehouse select has value and is invalid
            $('.warehouse-select').each(function() {
                const itemId = $(this).data('item-id');
                const qtyInput = $(`.qty-to-ship-input[data-item-id="${itemId}"]`);
                if (qtyInput.length && parseFloat(qtyInput.val()) > 0 && !$(this).val()) {
                    // Warehouse is required if shipping > 0
                    formHasErrors = true;
                }
            });

            const submitBtn = $('#deliveryForm button[type="submit"]');
            if (formHasErrors) {
                submitBtn.attr('disabled', 'disabled');
            } else {
                submitBtn.removeAttr('disabled');
            }
        }
    });
</script>
@endpush
