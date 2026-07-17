@extends('layouts.duralux')

@section('title', 'Create Production Order | SaaS ERP')
@section('page-title', 'Create Direct Production Order')
@section('breadcrumb', 'Create Order')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/select2-active.min.js') }}"></script>
    <script>
        $(document).ready(function () {
            $('#production_order_request_select').on('change', function () {
                var selectedOption = $(this).find('option:selected');
                var productId = selectedOption.data('product-id');
                var qty = selectedOption.data('qty');
                var salesOrderId = selectedOption.data('sales-order-id');
                var salesOrderItemId = selectedOption.data('sales-order-item-id');

                if (productId !== undefined && productId !== '') {
                    $('#product_select').val(productId).trigger('change');
                }
                if (qty !== undefined && qty !== '') {
                    $('input[name="quantity_ordered"]').val(qty);
                }
                $('#sales_order_id').val(salesOrderId || '');
                $('#sales_order_item_id').val(salesOrderItemId || '');
            });

            if ($('#production_order_request_select').val()) {
                $('#production_order_request_select').trigger('change');
            }

            // Handle Sales Order item autofill
            $('#product_select').on('change', function () {
                var selectedOption = $(this).find('option:selected');
                var qty = selectedOption.data('qty');
                var soItemId = selectedOption.data('so-item-id');
                
                if (qty !== undefined) {
                    $('input[name="quantity_ordered"]').val(qty);
                }
                if (soItemId !== undefined) {
                    $('#sales_order_item_id').val(soItemId);
                }
            });

            // When product changes, dynamically load approved BOMs and active Routings
            $('#product_select').on('change', function () {
                var productId = $(this).val();
                if (!productId) {
                    $('#bom_select').html('<option value="">— No BOM available —</option>').trigger('change');
                    $('#routing_select').html('<option value="">— No Routing available —</option>').trigger('change');
                    return;
                }
                $.ajax({
                    url: "{{ route('production.plans.engineering-options') }}",
                    method: 'GET',
                    data: { product_id: productId },
                    success: function (response) {
                        var bomHtml = '<option value="">Auto-select (Latest Approved BOM)</option>';
                        (response.boms || []).forEach(function (bom) {
                            bomHtml += '<option value="' + bom.id + '">' + bom.bom_number + ' — ' + (bom.bom_name || '') + ' (v' + bom.version + ')</option>';
                        });
                        $('#bom_select').html(bomHtml).trigger('change');

                        var rtHtml = '<option value="">Auto-select (Default Active Routing)</option>';
                        (response.routings || []).forEach(function (rt) {
                            rtHtml += '<option value="' + rt.id + '">' + rt.routing_number + ' — ' + rt.name + ' (v' + rt.version + ')</option>';
                        });
                        $('#routing_select').html(rtHtml).trigger('change');
                    }
                });
            });

            function loadBOMPreview() {
                var productId = $('#product_select').val();
                var bomId = $('#bom_select').val();
                var qty = $('input[name="quantity_ordered"]').val() || 1.0;

                if (!productId) {
                    $('#bom-preview-container').addClass('d-none');
                    return;
                }

                $.ajax({
                    url: "{{ route('production.plans.bom-explosion') }}",
                    method: 'GET',
                    data: { product_id: productId, bom_id: bomId, quantity: qty },
                    success: function(response) {
                        if (response.success && response.items.length > 0) {
                            var html = '';
                            response.items.forEach(function(item) {
                                var indent = '';
                                var nameHtml = '';
                                var dotCount = (item.prefix.match(/\./g) || []).length;
                                if (dotCount > 0) {
                                    indent = '↳ ';
                                    nameHtml = '<div style="margin-left: ' + (dotCount * 15) + 'px;"><span class="text-muted fw-bold">' + indent + '</span><span class="text-dark fw-normal">' + item.component_name + '</span><div class="text-muted fs-11" style="padding-left: 15px;">SKU: ' + item.component_sku + '</div></div>';
                                } else {
                                    nameHtml = '<div class="fw-bold text-dark">' + item.component_name + '</div><div class="text-muted fs-11">SKU: ' + item.component_sku + '</div>';
                                }

                                html += '<tr style="' + (dotCount > 0 ? 'background-color: #fcfcfc;' : '') + '">';
                                html += '<td class="fw-semibold">' + item.prefix + '</td>';
                                html += '<td>' + nameHtml + '</td>';
                                html += '<td>' + item.type + '</td>';
                                html += '<td class="text-center fw-semibold">' + parseFloat(item.quantity_required).toFixed(4) + '</td>';
                                html += '<td class="text-center text-muted">' + parseFloat(item.available_quantity).toFixed(4) + '</td>';
                                html += '<td class="text-center fw-bold ' + (item.for_production_qty > 0 ? 'text-danger' : 'text-success') + '">' + parseFloat(item.for_production_qty).toFixed(4) + '</td>';
                                html += '<td class="text-center">' + item.uom + '</td>';
                                html += '<td class="text-center font-monospace">$' + parseFloat(item.rate).toFixed(2) + '</td>';
                                html += '<td class="text-center font-monospace fw-bold">$' + parseFloat(item.amount).toFixed(2) + '</td>';
                                html += '<td class="text-muted fs-12">' + (item.notes || '—') + '</td>';
                                html += '</tr>';
                            });
                            $('#bom-preview-table-body').html(html);
                            $('#bom-preview-warehouse').text(response.warehouse_name);
                            $('#bom-preview-container').removeClass('d-none');
                        } else {
                            $('#bom-preview-container').addClass('d-none');
                        }
                    },
                    error: function() {
                        $('#bom-preview-container').addClass('d-none');
                    }
                });
            }

            // Bind preview loading events
            $('#product_select, #bom_select').on('change', loadBOMPreview);
            $('input[name="quantity_ordered"]').on('input change', loadBOMPreview);
        });
    </script>
@endpush

@section('content')
    <div class="erp-single-panel">
        <!-- Validation Errors -->
        @if ($errors->any())
            <x-ui.toast :auto="true" type="error" title="Validation Failed: {{ $errors->first() }}" />
        @endif

        @if (session('error'))
            <x-ui.toast :auto="true" type="error" title="{{ session('error') }}" />
        @endif

        <form method="POST" action="{{ route('production.orders.store') }}">
            @csrf

            <x-ui.odoo-form-ui type="sheet">
                <!-- Header with Close Button -->
                <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
                    <div>
                        <h4 class="fw-bold text-dark mb-0">New Production Order</h4>
                        <small class="text-muted fs-12">BOM and Routing versions are frozen automatically once the order is released.</small>
                    </div>
                    <a href="{{ route('production.orders.index') }}" class="btn btn-sm btn-light border">Cancel</a>
                </div>

                <div class="row g-4 fs-13 text-dark">
                    <!-- Left Column -->
                    <div class="col-md-6 border-end">
                        @if(!isset($salesOrder) || !$salesOrder)
                            <x-ui.odoo-form-ui type="select" label="Sales Order Request" name="production_order_request_id" id="production_order_request_select">
                                <option value="">Select Draft Sales Request...</option>
                                @foreach($productionOrderRequests as $request)
                                    @php
                                        $deliveryItem = $request->materialRequirementItem;
                                        $delivery = $deliveryItem?->materialRequirement;
                                        $sales = $delivery?->salesOrder ?? $deliveryItem?->salesOrderItem?->salesOrder;
                                        $product = $request->product;
                                    @endphp
                                    <option value="{{ $request->id }}"
                                        data-product-id="{{ $request->product_id }}"
                                        data-qty="{{ $request->quantity_requested }}"
                                        data-sales-order-id="{{ $sales?->id }}"
                                        data-sales-order-item-id="{{ $deliveryItem?->sales_order_item_id }}"
                                        @selected(old('production_order_request_id') == $request->id)>
                                        {{ $sales?->sales_order_number ?? 'Sales Order #' . ($sales?->id ?? 'N/A') }}
                                        @if($delivery)
                                            / {{ $delivery->requirement_number }}
                                        @endif
                                        @if($product)
                                            — {{ $product->name }} ({{ $product->sku }})
                                        @endif
                                        — Qty: {{ rtrim(rtrim(number_format((float) $request->quantity_requested, 4, '.', ''), '0'), '.') }}
                                    </option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        @endif

                        @if(isset($salesOrder) && $salesOrder)
                            <input type="hidden" name="sales_order_id" value="{{ $salesOrder->id }}">
                            <input type="hidden" name="sales_order_item_id" id="sales_order_item_id" value="{{ old('sales_order_item_id') }}">

                            <x-ui.odoo-form-ui type="input" label="Sales Order" name="sales_order_number" :value="$salesOrder->sales_order_number" readonly="true" style="font-weight: bold; background-color: #f8f9fa;" />

                            <x-ui.odoo-form-ui type="select" label="Target Product (from Sales Order)" name="product_id" id="product_select" :required="true">
                                <option value="">Select Target Product...</option>
                                @foreach($salesOrderItems as $item)
                                    @if($item->product)
                                        <option value="{{ $item->product->id }}" data-qty="{{ $item->quantity }}" data-so-item-id="{{ $item->id }}" @selected(old('product_id') == $item->product->id)>
                                            {{ $item->product->name }} ({{ $item->product->sku }}) — Qty: {{ (int)$item->quantity }}
                                        </option>
                                    @endif
                                @endforeach
                            </x-ui.odoo-form-ui>
                        @else
                            <input type="hidden" name="sales_order_id" id="sales_order_id" value="{{ old('sales_order_id') }}">
                            <input type="hidden" name="sales_order_item_id" id="sales_order_item_id" value="{{ old('sales_order_item_id') }}">

                            <x-ui.odoo-form-ui type="select" label="Target Product" name="product_id" id="product_select" :required="true">
                                <option value="">Select Finished Good or Semi-Finished Product...</option>
                                @foreach($products as $product)
                                    <option value="{{ $product->id }}" @selected(old('product_id') == $product->id)>
                                        {{ $product->name }} ({{ $product->sku }})
                                    </option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        @endif

                        <x-ui.odoo-form-ui type="select" label="Bill of Materials" name="bom_id" id="bom_select">
                            <option value="">Auto-select (Latest Approved BOM)</option>
                            @foreach($boms ?? [] as $bom)
                                <option value="{{ $bom->id }}" @selected(old('bom_id') == $bom->id)>
                                    {{ $bom->bom_number }} — {{ $bom->bom_name }} (v{{ $bom->version }})
                                </option>
                            @endforeach
                        </x-ui.odoo-form-ui>

                        <x-ui.odoo-form-ui type="select" label="Process Routing" name="routing_id" id="routing_select">
                            <option value="">Auto-select (Default Active Routing)</option>
                            @foreach($routings ?? [] as $rt)
                                <option value="{{ $rt->id }}" @selected(old('routing_id') == $rt->id)>
                                    {{ $rt->routing_number }} — {{ $rt->name }} (v{{ $rt->version }})
                                </option>
                            @endforeach
                        </x-ui.odoo-form-ui>
                    </div>

                    <!-- Right Column -->
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="input" label="Qty to Manufacture" name="quantity_ordered" inputType="number" placeholder="e.g. 10.0000" :value="old('quantity_ordered', '1.0000')" :required="true" />

                        <x-ui.odoo-form-ui type="input" label="Start Date" name="start_date" inputType="date" :value="old('start_date', date('Y-m-d'))" :required="true" />
                        
                        <x-ui.odoo-form-ui type="input" label="End Date" name="end_date" inputType="date" :value="old('end_date', date('Y-m-d', strtotime('+3 days')))" :required="true" />

                        <x-ui.odoo-form-ui type="textarea" label="Remarks" name="description" placeholder="Enter special manufacturing instructions, operator notes, or customer references..." rows="4">{{ old('description') }}</x-ui.odoo-form-ui>
                    </div>
                </div>

                {{-- BOM Preview Section --}}
                <div class="card border mt-4 d-none" id="bom-preview-container">
                    <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                        <h6 class="fw-bold text-dark mb-0">
                            <i class="feather-box me-2 text-primary"></i>BOM Components &amp; Availability Preview
                        </h6>
                        <span class="fs-12 text-muted">Stock Warehouse: <strong id="bom-preview-warehouse">—</strong></span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-hover align-middle mb-0 fs-13">
                            <thead class="bg-soft-light text-uppercase fs-11 fw-semibold text-muted">
                                <tr>
                                    <th style="width: 5%">Sr.No</th>
                                    <th style="width: 25%">Component Name</th>
                                    <th style="width: 12%">Type</th>
                                    <th class="text-center" style="width: 10%">Qty Required</th>
                                    <th class="text-center" style="width: 10%">Available Qty</th>
                                    <th class="text-center" style="width: 12%">For Production Qty</th>
                                    <th class="text-center" style="width: 7%">UOM</th>
                                    <th class="text-center" style="width: 8%">Rate/Unit</th>
                                    <th class="text-center" style="width: 11%">Total Amount</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody id="bom-preview-table-body" class="text-dark">
                                {{-- Dynamically populated via AJAX --}}
                            </tbody>
                        </table>
                    </div>
                </div>

                {{-- Footer Actions --}}
                <div class="d-flex gap-2 pt-3 border-top mt-4">
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="feather-check-circle me-2"></i>Create Production Order
                    </button>
                    <a href="{{ route('production.orders.index') }}" class="btn btn-secondary px-4">Cancel</a>
                </div>
            </x-ui.odoo-form-ui>
        </form>
    </div>
@endsection
