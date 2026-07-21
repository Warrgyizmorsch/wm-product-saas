@extends('layouts.duralux')

@section('title', __('production.create_production_plan') . ' | SaaS ERP')
@section('page-title', __('production.create_production_plan'))
@section('breadcrumb', __('production.create_production_plan'))

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/select2-active.min.js') }}"></script>
    <script>
        $(document).ready(function() {
            $('#production_order_request_select').on('change', function() {
                var selectedOption = $(this).find('option:selected');
                var productId = selectedOption.data('product-id');
                var qty = selectedOption.data('qty');
                var salesOrderId = selectedOption.data('sales-order-id');
                var salesOrderItemId = selectedOption.data('sales-order-item-id');
 
                if (productId !== undefined && productId !== '') {
                    $('#product_select').val(productId).trigger('change');
                }
                if (qty !== undefined && qty !== '') {
                    $('input[name="quantity"]').val(qty);
                }
                $('#sales_order_id').val(salesOrderId || '');
                $('#sales_order_item_id').val(salesOrderItemId || '');
            });
 
            if ($('#production_order_request_select').val()) {
                $('#production_order_request_select').trigger('change');
            }
 
            // Setup AJAX dynamic dropdown triggers
            $('#product_select').on('change', function() {
                var productId = $(this).val();
                if (!productId) {
                    $('#bom_select').html('<option value="">' + @js(__('production.none_auto_select')) + '</option>').trigger('change');
                    $('#routing_select').html('<option value="">' + @js(__('production.none_auto_select')) + '</option>').trigger('change');
                    return;
                }
 
                // Show loading
                $('#bom_select').html('<option value="">' + @js(__('production.loading')) + '</option>').trigger('change');
                $('#routing_select').html('<option value="">' + @js(__('production.loading')) + '</option>').trigger('change');
 
                $.ajax({
                    url: "{{ route('production.plans.engineering-options') }}",
                    method: 'GET',
                    data: { product_id: productId },
                    success: function(response) {
                        var bomHtml = '<option value="">' + @js(__('production.none_auto_select_bom')) + '</option>';
                        if (response.boms && response.boms.length > 0) {
                            response.boms.forEach(function(bom) {
                                bomHtml += '<option value="' + bom.id + '">' + bom.bom_number + ' - ' + (bom.bom_name || '') + ' (v' + bom.version + ')</option>';
                            });
                        } else {
                            bomHtml += '<option value="" disabled>' + @js(__('production.no_approved_bom_found')) + '</option>';
                        }
                        $('#bom_select').html(bomHtml).trigger('change');
 
                        var routingHtml = '<option value="">' + @js(__('production.none_auto_select_routing')) + '</option>';
                        if (response.routings && response.routings.length > 0) {
                            response.routings.forEach(function(rt) {
                                routingHtml += '<option value="' + rt.id + '">' + rt.routing_number + ' - ' + rt.name + ' (v' + rt.version + ')</option>';
                            });
                        } else {
                            routingHtml += '<option value="" disabled>' + @js(__('production.no_active_routing_found')) + '</option>';
                        }
                        $('#routing_select').html(routingHtml).trigger('change');
                    },
                    error: function() {
                        $('#bom_select').html('<option value="">' + @js(__('production.error_loading_options')) + '</option>').trigger('change');
                        $('#routing_select').html('<option value="">' + @js(__('production.error_loading_options')) + '</option>').trigger('change');
                    }
                });
            });
        });
    </script>
@endpush

@section('content')
    <div class="erp-single-panel">
        <!-- Validation Errors -->
        @if ($errors->any())
            <x-ui.toast :auto="true" type="error" title="{{ __('production.validation_failed') }}: {{ $errors->first() }}" />
        @endif

        @if (session('error'))
            <x-ui.toast :auto="true" type="error" title="{{ session('error') }}" />
        @endif

        <form method="POST" action="{{ route('production.plans.store') }}">
            @csrf
            
            <x-ui.odoo-form-ui type="sheet">
                <!-- Header with Close Button -->
                <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
                    <h4 class="fw-bold text-dark mb-0">{{ __('production.new_production_plan') }}</h4>
                    <a href="{{ route('production.plans.index') }}" class="btn btn-sm btn-light border">{{ __('production.cancel') }}</a>
                </div>

                <div class="row g-4 fs-13 text-dark">
                    <!-- Left Column -->
                    <div class="col-md-6 border-end">
                        <x-ui.odoo-form-ui type="input" :label="__('production.plan_name')" name="name" placeholder="{{ __('production.plan_name_placeholder') }}" :value="old('name')" :required="true" />

                        <x-ui.odoo-form-ui type="select" :label="__('production.sales_order_request')" name="production_order_request_id" id="production_order_request_select">
                            <option value="">{{ __('production.select_draft_sales_request') }}</option>
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
                                    — Qty: {{ rtrim(rtrim(number_format((float) $request->quantity_requested, 2, '.', ''), '0'), '.') }}

                                </option>
                            @endforeach
                        </x-ui.odoo-form-ui>
                        <input type="hidden" name="sales_order_id" id="sales_order_id" value="{{ old('sales_order_id') }}">
                        <input type="hidden" name="sales_order_item_id" id="sales_order_item_id" value="{{ old('sales_order_item_id') }}">
                        
                        <x-ui.odoo-form-ui type="select" :label="__('production.item_to_produce')" name="product_id" id="product_select" :required="true">
                            <option value="">{{ __('production.select_product_to_produce') }}</option>
                            @foreach ($products as $p)
                                <option value="{{ $p->id }}" @selected(old('product_id') == $p->id)>{{ $p->name }} ({{ $p->sku }})</option>
                            @endforeach
                        </x-ui.odoo-form-ui>

                        <x-ui.odoo-form-ui type="select" :label="__('production.bill_of_materials')" name="bom_id" id="bom_select">
                            <option value="">{{ __('production.none_auto_select_bom') }}</option>
                            @foreach($boms as $bom)
                                <option value="{{ $bom->id }}" @selected(old('bom_id') == $bom->id)>
                                    {{ $bom->bom_number }} - {{ $bom->bom_name }} (v{{ $bom->version }})
                                </option>
                            @endforeach
                        </x-ui.odoo-form-ui>

                        <x-ui.odoo-form-ui type="select" :label="__('production.routing')" name="routing_id" id="routing_select">
                            <option value="">{{ __('production.none_auto_select_routing') }}</option>
                            @foreach($routings as $rt)
                                <option value="{{ $rt->id }}" @selected(old('routing_id') == $rt->id)>
                                    {{ $rt->routing_number }} - {{ $rt->name }} (v{{ $rt->version }})
                                </option>
                            @endforeach
                        </x-ui.odoo-form-ui>
                    </div>

                    <!-- Right Column -->
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="input" :label="__('production.target_quantity')" name="quantity" inputType="number" placeholder="e.g. 50.00" :value="old('quantity', '1.00')" :required="true" />
                        
                        <x-ui.odoo-form-ui type="input" :label="__('production.planned_start_date')" name="start_date" inputType="date" :value="old('start_date', date('Y-m-d'))" :required="true" />
                        
                        <x-ui.odoo-form-ui type="input" :label="__('production.planned_end_date')" name="end_date" inputType="date" :value="old('end_date', date('Y-m-d', strtotime('+7 days')))" :required="true" />

                        <x-ui.odoo-form-ui type="textarea" :label="__('production.description')" name="description" placeholder="{{ __('production.plan_description_placeholder') }}" rows="4">{{ old('description') }}</x-ui.odoo-form-ui>
                    </div>
                </div>

                <!-- Footer Action Buttons -->
                <div class="d-flex gap-2 pt-3 border-top mt-4">
                    <button type="submit" class="btn btn-primary px-4">{{ __('production.create_plan') }}</button>
                    <a href="{{ route('production.plans.index') }}" class="btn btn-secondary px-4">{{ __('production.cancel') }}</a>
                </div>
            </x-ui.odoo-form-ui>
        </form>
    </div>
@endsection
