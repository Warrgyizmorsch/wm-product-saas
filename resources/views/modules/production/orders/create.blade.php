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
