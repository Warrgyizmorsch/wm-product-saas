@extends('layouts.duralux')

@section('title', __('inventory.new_inventory_item') . ' | SaaS ERP')
@section('page-title', __('inventory.new_item'))
@section('breadcrumb', __('inventory.inventory_items_create'))

@push('styles')
    <!-- Select2 Theme Styles -->
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
    <style>
        .custom-radio-group {
            display: flex;
            gap: 20px;
            margin-bottom: 15px;
            align-items: center;
        }
        .custom-radio-label {
            font-size: 13px;
            font-weight: 700;
            color: #495057;
            width: 130px;
            margin-bottom: 0;
        }
        .custom-radio-option {
            display: flex;
            align-items: center;
            gap: 5px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
        }
        .custom-radio-option input {
            cursor: pointer;
        }
        .attribute-card {
            border: 1px dashed #ced4da;
            background-color: #f8f9fa;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 15px;
        }
        .tag-badge {
            display: inline-flex;
            align-items: center;
            background-color: #714B67;
            color: white;
            border-radius: 3px;
            padding: 2px 8px;
            margin-right: 5px;
            margin-bottom: 5px;
            font-size: 12px;
        }
        .tag-badge .remove-tag {
            margin-left: 6px;
            cursor: pointer;
            font-weight: bold;
        }
        .tag-badge .remove-tag:hover {
            color: #ffc107;
        }
        .tag-input-container {
            border: 1px solid #ced4da;
            background: white;
            padding: 5px;
            border-radius: 4px;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            min-height: 38px;
        }
        .tag-input {
            border: none;
            outline: none;
            flex-grow: 1;
            padding: 4px;
            font-size: 13px;
            min-width: 120px;
        }

        /* Modern ERP Media Uploader Styles */
        .erp-media-box {
            background: #ffffff;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            padding: 20px;
        }
        .erp-media-dropzone {
            border: 1.5px dashed #cbd5e1;
            background: #f8fafc;
            border-radius: 8px;
            transition: all 0.2s ease-in-out;
        }
        .erp-media-dropzone:hover {
            border-color: #3b82f6;
            background: #f1f5f9;
        }
        .variant-thumb-box {
            transition: all 0.2s ease-in-out;
            cursor: pointer;
        }
        .variant-thumb-box:hover {
            border-color: #3b82f6 !important;
            box-shadow: 0 2px 8px rgba(59, 130, 246, 0.25) !important;
            transform: scale(1.05);
        }
        .main-img-preview-box {
            width: 150px;
            height: 150px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            background: #ffffff;
            position: relative;
            overflow: hidden;
            box-shadow: 0 2px 6px rgba(0,0,0,0.06);
            margin: 0 auto 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .main-img-preview-box img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .main-img-hover-actions {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(15, 23, 42, 0.6);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            opacity: 0;
            transition: opacity 0.2s ease;
        }
        .main-img-preview-box:hover .main-img-hover-actions {
            opacity: 1;
        }
        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(115px, 1fr));
            gap: 12px;
        }
        .gallery-card-item {
            position: relative;
            border-radius: 8px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            overflow: hidden;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
            aspect-ratio: 1 / 1;
        }
        .gallery-card-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            border-color: #cbd5e1;
        }
        .gallery-card-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .gallery-remove-btn {
            position: absolute;
            top: 6px;
            right: 6px;
            width: 24px;
            height: 24px;
            background: #ef4444;
            color: #ffffff;
            border: none;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            cursor: pointer;
            box-shadow: 0 2px 5px rgba(0,0,0,0.25);
            transition: background 0.15s ease, transform 0.15s ease;
            z-index: 5;
        }
        .gallery-remove-btn:hover {
            background: #dc2626;
            transform: scale(1.12);
            color: #ffffff;
        }
        .gallery-card-info {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(transparent, rgba(15, 23, 42, 0.8));
            color: #ffffff;
            font-size: 10px;
            padding: 14px 6px 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .gallery-add-more-box {
            border: 1.5px dashed #3b82f6;
            background: rgba(59, 130, 246, 0.04);
            border-radius: 8px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s ease;
            aspect-ratio: 1 / 1;
            color: #3b82f6;
        }
        .gallery-add-more-box:hover {
            background: rgba(59, 130, 246, 0.1);
            border-color: #2563eb;
            transform: translateY(-2px);
        }
    </style>
@endpush

@section('page-actions')
    <a href="{{ route('inventory.products.index') }}" class="btn btn-light">
        <i class="feather-arrow-left me-2"></i>{{ __('inventory.back') }}
    </a>
@endsection

@section('content')
    <div class="row">
        <div class="col-12">

            <!-- Zoho / Odoo Style Flat Form Sheet -->
            <div class="card border-0 shadow-sm p-4 p-md-5 bg-white">
                <form action="{{ route('inventory.products.store') }}" method="POST" id="productForm" class="odoo-sheet" enctype="multipart/form-data">
                    @csrf

                    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">
                        <h3 class="fw-bold text-dark mb-0">{{ __('inventory.new_item_product') }}</h3>
                        <div class="d-flex gap-2">
                            <a href="{{ route('inventory.products.index') }}" class="btn btn-sm btn-light border">{{ __('inventory.cancel') }}</a>
                            <button type="submit" class="btn btn-sm btn-primary">{{ __('inventory.save_product') }}</button>
                        </div>
                    </div>

                    <!-- Radio Type Selector in Zoho style -->
                    <div class="custom-radio-group mb-3">
                        <span class="custom-radio-label">{{ __('inventory.item_type') }} <span class="text-danger">*</span></span>
                        <x-ui.radio name="item_type" value="Goods" :label="__('inventory.goods_physical_product')" :checked="old('item_type', 'Goods') === 'Goods'" />
                        <x-ui.radio name="item_type" value="Service" :label="__('inventory.service_labor')" :checked="old('item_type') === 'Service'" />
                    </div>

                    <!-- Variation type in Zoho style -->
                    <div class="custom-radio-group mb-4">
                        <span class="custom-radio-label">{{ __('inventory.variation') }} <span class="text-danger">*</span></span>
                        <x-ui.radio name="variation_type" value="Single" :label="__('inventory.single_item')" :checked="old('variation_type', 'Single') === 'Single'" />
                        <x-ui.radio name="variation_type" value="Variant" :label="__('inventory.contains_variants')" :checked="old('variation_type') === 'Variant'" />
                    </div>

                    <!-- Supplier Method Selector in Zoho style -->
                    <div class="custom-radio-group mb-4 supplier-method-container">
                        <span class="custom-radio-label">{{ __('inventory.supplier_method') }} <span class="text-danger">*</span></span>
                        <x-ui.radio name="supplier_method" value="trade" :label="__('inventory.buy')" :checked="in_array(old('supplier_method', 'trade'), ['trade', 'buy'], true)" />
                        <x-ui.radio name="supplier_method" value="manufacture" :label="__('inventory.manufacture')" :checked="old('supplier_method') === 'manufacture'" />
                    </div>

                    <div class="row g-4 mb-4 fs-13 text-dark">
                        <!-- Left Column: Primary details -->
                        <div class="col-lg-6 border-end">
                            <h6 class="fw-bold text-primary mb-3"><i class="feather-info me-2"></i>{{ __('inventory.primary_details') }}</h6>
                            
                            <x-ui.odoo-form-ui type="input" :label="__('inventory.item_name')" name="name" :value="old('name')" required="true" :placeholder="__('inventory.product_name_placeholder')" :errorText="$errors->first('name')" />

                            <div class="single-item-only">
                                <x-ui.odoo-form-ui type="input" :label="__('inventory.sku')" name="sku" :value="old('sku')" required="true" :placeholder="__('inventory.sku_placeholder')" :errorText="$errors->first('sku')" />
                            </div>

                            <x-ui.odoo-form-ui type="select" :label="__('inventory.unit')" name="uom_id" required="true" :errorText="$errors->first('uom_id')">
                                <option value="" disabled {{ old('uom_id') ? '' : 'selected' }}>{{ __('inventory.select_unit') }}</option>
                                <option value="__add_unit__" class="fw-bold text-primary">{{ __('inventory.add_new_unit') }}</option>
                                @foreach($uoms as $uom)
                                    <option value="{{ $uom->id }}" data-uom-category="{{ strtolower($uom->category ?? 'goods') }}" {{ old('uom_id') == $uom->id ? 'selected' : '' }}>{{ $uom->name }} ({{ $uom->code }})</option>
                                @endforeach
                            </x-ui.odoo-form-ui>

                            <x-ui.odoo-form-ui type="select" :label="__('inventory.material_type')" name="type" required="true" :errorText="$errors->first('type')">
                                <option value="finished_good" {{ old('type', 'finished_good') === 'finished_good' ? 'selected' : '' }}>{{ __('inventory.finished_good_std') }}</option>
                                <option value="semi_finished" {{ old('type') === 'semi_finished' ? 'selected' : '' }}>{{ __('inventory.semi_finished_comp') }}</option>
                                <option value="raw_material" {{ old('type') === 'raw_material' ? 'selected' : '' }}>{{ __('inventory.raw_material_purch') }}</option>
                                <option value="component" {{ old('type', 'component') === 'component' ? 'selected' : '' }}>{{ __('inventory.component_spare') }}</option>
                                <option value="service" {{ old('type') === 'service' ? 'selected' : '' }} style="display:none;">{{ __('inventory.service') }}</option>
                            </x-ui.odoo-form-ui>

                            <x-ui.odoo-form-ui type="select" :label="__('inventory.default_production_model')" name="default_production_model" :errorText="$errors->first('default_production_model')">
                                <option value="pure_manufacturing" {{ old('default_production_model', 'pure_manufacturing') === 'pure_manufacturing' ? 'selected' : '' }}>{{ __('inventory.pure_manufacturing') }}</option>
                                <option value="subcontract_complete" {{ old('default_production_model') === 'subcontract_complete' ? 'selected' : '' }}>{{ __('inventory.subcontract_complete') }}</option>
                                <option value="subcontract_company_material" {{ old('default_production_model') === 'subcontract_company_material' ? 'selected' : '' }}>{{ __('inventory.subcontract_company_material') }}</option>
                                <option value="hybrid" {{ old('default_production_model') === 'hybrid' ? 'selected' : '' }}>{{ __('inventory.hybrid_manufacturing') }}</option>
                            </x-ui.odoo-form-ui>

                            <div class="physical-goods-only">
                                <x-ui.odoo-form-ui type="input" :label="__('inventory.brand')" name="brand" :value="old('brand')" :placeholder="__('inventory.brand_placeholder')" :errorText="$errors->first('brand')" />
                                
                                <x-ui.odoo-form-ui type="input" :label="__('inventory.manufacturer')" name="manufacturer" :value="old('manufacturer')" :placeholder="__('inventory.manufacturer_placeholder')" :errorText="$errors->first('manufacturer')" />
                                
                                <x-ui.odoo-form-ui type="input" :label="__('inventory.mpn')" name="mpn" :value="old('mpn')" :placeholder="__('inventory.mpn_placeholder')" :errorText="$errors->first('mpn')" />

                                <div class="border-top pt-3 mt-3">
                                    <h6 class="fw-bold text-primary mb-3"><i class="feather-hash me-2"></i>{{ __('inventory.identifiers') }}</h6>
                                    <x-ui.odoo-form-ui type="input" :label="__('inventory.barcode')" name="barcode" :value="old('barcode')" :placeholder="__('inventory.barcode_placeholder')" :errorText="$errors->first('barcode')" />
                                    <x-ui.odoo-form-ui type="input" :label="__('inventory.upc')" name="upc" :value="old('upc')" :placeholder="__('inventory.upc_placeholder')" :errorText="$errors->first('upc')" />
                                    <x-ui.odoo-form-ui type="input" :label="__('inventory.ean')" name="ean" :value="old('ean')" :placeholder="__('inventory.ean_placeholder')" :errorText="$errors->first('ean')" />
                                    <x-ui.odoo-form-ui type="input" :label="__('inventory.isbn')" name="isbn" :value="old('isbn')" :placeholder="__('inventory.isbn_placeholder')" :errorText="$errors->first('isbn')" />
                                </div>
                            </div>
                        </div>

                        <!-- Right Column: Sales & Purchase Accounts -->
                        <div class="col-lg-6">
                            <h6 class="fw-bold text-primary mb-3"><i class="feather-dollar-sign me-2"></i>{{ __('inventory.sales_purchase_info') }}</h6>

                            <x-ui.odoo-form-ui type="input" :label="__('inventory.selling_price')" name="selling_price" :value="old('selling_price')" inputType="number" step="0.01" :placeholder="__('inventory.selling_price')" :errorText="$errors->first('selling_price')" />

                            <x-ui.odoo-form-ui type="select" :label="__('inventory.sales_account')" name="sales_account" :errorText="$errors->first('sales_account')">
                                <option value="" {{ old('sales_account') ? '' : 'selected' }}>{{ __('inventory.select_sales_account') }}</option>
                                @forelse($salesAccounts as $acc)
                                    <option value="{{ $acc->name }}" {{ old('sales_account') === $acc->name ? 'selected' : '' }}>{{ $acc->code ? $acc->code . ' - ' : '' }}{{ $acc->name }}</option>
                                @empty
                                    <option value="Sales Income" {{ old('sales_account') === 'Sales Income' ? 'selected' : '' }}>{{ __('inventory.sales_income_account') }}</option>
                                    <option value="General Income" {{ old('sales_account') === 'General Income' ? 'selected' : '' }}>{{ __('inventory.general_income_account') }}</option>
                                    <option value="Interest Income" {{ old('sales_account') === 'Interest Income' ? 'selected' : '' }}>{{ __('inventory.interest_income_account') }}</option>
                                @endforelse
                            </x-ui.odoo-form-ui>

                            <x-ui.odoo-form-ui type="input" :label="__('inventory.cost_price')" name="cost_price" :value="old('cost_price')" inputType="number" step="0.01" :placeholder="__('inventory.purchase_cost')" :errorText="$errors->first('cost_price')" />

                            <x-ui.odoo-form-ui type="select" :label="__('inventory.purchase_account')" name="purchase_account" :errorText="$errors->first('purchase_account')">
                                <option value="" {{ old('purchase_account') ? '' : 'selected' }}>{{ __('inventory.select_purchase_account') }}</option>
                                @forelse($purchaseAccounts as $acc)
                                    <option value="{{ $acc->name }}" {{ old('purchase_account') === $acc->name ? 'selected' : '' }}>{{ $acc->code ? $acc->code . ' - ' : '' }}{{ $acc->name }}</option>
                                @empty
                                    <option value="Cost of Goods Sold" {{ old('purchase_account') === 'Cost of Goods Sold' ? 'selected' : '' }}>{{ __('inventory.cogs_account') }}</option>
                                    <option value="Purchases" {{ old('purchase_account') === 'Purchases' ? 'selected' : '' }}>{{ __('inventory.purchases_expense_account') }}</option>
                                    <option value="Job Costs" {{ old('purchase_account') === 'Job Costs' ? 'selected' : '' }}>{{ __('inventory.job_costs_account') }}</option>
                                @endforelse
                            </x-ui.odoo-form-ui>

                            <div class="border-top pt-3 mt-3">
                                <h6 class="fw-bold text-primary mb-3"><i class="feather-percent me-2"></i>{{ __('inventory.taxation_preferred_vendor') }}</h6>
                                <x-ui.odoo-form-ui type="input" :label="__('inventory.hsn_sac_code')" name="hsn_sac" :value="old('hsn_sac')" :placeholder="__('inventory.hsn_placeholder')" :errorText="$errors->first('hsn_sac')" />

                                <x-ui.odoo-form-ui type="select" :label="__('inventory.gst_rate')" name="gst_rate" :errorText="$errors->first('gst_rate')">
                                    <option value="0" {{ old('gst_rate') == '0' ? 'selected' : '' }}>{{ __('inventory.gst_exempt') }}</option>
                                    <option value="5" {{ old('gst_rate') == '5' ? 'selected' : '' }}>5%</option>
                                    <option value="12" {{ old('gst_rate') == '12' ? 'selected' : '' }}>12%</option>
                                    <option value="18" {{ old('gst_rate', '18') == '18' ? 'selected' : '' }}>18%</option>
                                    <option value="28" {{ old('gst_rate') == '28' ? 'selected' : '' }}>28%</option>
                                </x-ui.odoo-form-ui>

                                <x-ui.odoo-form-ui type="select" :label="__('inventory.preferred_vendor')" name="preferred_vendor_id" searchable="true" :errorText="$errors->first('preferred_vendor_id')">
                                    <option value="">{{ __('inventory.select_preferred_vendor') }}</option>
                                    @foreach($vendors as $vendor)
                                        <option value="{{ $vendor->id }}" {{ old('preferred_vendor_id') == $vendor->id ? 'selected' : '' }}>{{ $vendor->name }}</option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </div>

                            <div class="border-top pt-3 mt-3 physical-goods-only">
                                <h6 class="fw-bold text-primary mb-3"><i class="feather-maximize me-2"></i>{{ __('inventory.dimensions_weight') }}</h6>
                                <div class="odoo-form-group">
                                    <label class="odoo-form-label">{{ __('inventory.dimensions') }}</label>
                                    <div class="d-flex gap-2 flex-grow-1">
                                        <input type="number" step="0.01" name="length" placeholder="{{ __('inventory.length') }}" class="odoo-form-control text-center" style="width: 25%;">
                                        <input type="number" step="0.01" name="width" placeholder="{{ __('inventory.width') }}" class="odoo-form-control text-center" style="width: 25%;">
                                        <input type="number" step="0.01" name="height" placeholder="{{ __('inventory.height') }}" class="odoo-form-control text-center" style="width: 25%;">
                                        <select name="dimension_unit" class="form-select form-select-sm" style="border-radius: 0; border: none; border-bottom: 1px solid #ced4da; width: 25%;">
                                            <option value="cm">cm</option>
                                            <option value="in">in</option>
                                            <option value="mm">mm</option>
                                            <option value="m">m</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="odoo-form-group mt-2">
                                    <label class="odoo-form-label">{{ __('inventory.weight') }}</label>
                                    <div class="d-flex gap-2 flex-grow-1">
                                        <input type="number" step="0.01" name="weight" placeholder="{{ __('inventory.weight') }}" class="odoo-form-control" style="width: 70%;">
                                        <select name="weight_unit" class="form-select form-select-sm" style="border-radius: 0; border: none; border-bottom: 1px solid #ced4da; width: 30%;">
                                            <option value="kg">kg</option>
                                            <option value="g">g</option>
                                            <option value="lb">lb</option>
                                            <option value="oz">oz</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Inventory tracking section (Relevant for Goods: Single and Variant) -->
                    <div id="inventorySection" class="border-top pt-4 mt-4 physical-goods-only">
                        <h6 class="fw-bold text-primary mb-3"><i class="feather-box me-2"></i>{{ __('inventory.inventory_tracking_settings') }}</h6>
                        
                        <div class="row g-4 fs-13 text-dark">
                            <div class="col-lg-6 border-end">
                                <x-ui.odoo-form-ui type="select" :label="__('inventory.inventory_account')" name="inventory_account" :errorText="$errors->first('inventory_account')">
                                    @forelse($inventoryAccounts as $acc)
                                        @php
                                            $isDefault = old('inventory_account') 
                                                ? (old('inventory_account') === $acc->name) 
                                                : ($acc->code == '1200' || strtolower($acc->name) === 'inventory'  || $loop->first);
                                        @endphp
                                        <option value="{{ $acc->name }}" {{ $isDefault ? 'selected' : '' }}>{{ $acc->code ? $acc->code . ' - ' : '' }}{{ $acc->name }}</option>
                                    @empty
                                        <option value="Inventory Asset" {{ old('inventory_account', 'Inventory Asset') === 'Inventory Asset' ? 'selected' : '' }}>1200 - {{ __('inventory.inventory_asset_account') }}</option>
                                        <option value="Raw Materials Stock" {{ old('inventory_account') === 'Raw Materials Stock' ? 'selected' : '' }}>{{ __('inventory.raw_materials_stock') }}</option>
                                        <option value="Finished Goods Stock" {{ old('inventory_account') === 'Finished Goods Stock' ? 'selected' : '' }}>{{ __('inventory.finished_goods_stock') }}</option>
                                    @endforelse
                                </x-ui.odoo-form-ui>

                                <x-ui.odoo-form-ui type="input" :label="__('inventory.reorder_point')" name="reorder_point" :value="old('reorder_point')" inputType="number" :placeholder="__('inventory.reorder_point_help')" :errorText="$errors->first('reorder_point')" />

                                <x-ui.odoo-form-ui type="input" :label="__('inventory.min_order_qty')" name="minimum_order_qty" :value="old('minimum_order_qty')" inputType="number" :placeholder="__('inventory.min_order_qty_help')" :errorText="$errors->first('minimum_order_qty')" />

                                <x-ui.odoo-form-ui type="input" :label="__('inventory.order_multiple')" name="order_multiple" :value="old('order_multiple')" inputType="number" :placeholder="__('inventory.order_multiple_help')" :errorText="$errors->first('order_multiple')" />

                                <x-ui.odoo-form-ui type="select" :label="__('inventory.valuation_method')" name="inventory_valuation_method" required="true">
                                    <option value="FIFO" selected>{{ __('inventory.fifo_method') }}</option>
                                    <option value="Weighted Average">{{ __('inventory.weighted_average') }}</option>
                                </x-ui.odoo-form-ui>
                            </div>

                            <div class="col-lg-6">
                                <div class="odoo-form-group">
                                    <label class="odoo-form-label">{{ __('inventory.advanced_tracking') }}</label>
                                    <div class="flex-grow-1">
                                        <div class="form-check form-check-inline mt-1">
                                            <input class="form-check-input" type="checkbox" name="track_serial_number" id="trackSerial" value="1">
                                            <label class="form-check-label" for="trackSerial">{{ __('inventory.track_serials') }}</label>
                                        </div>
                                        <div class="form-check form-check-inline mt-1 ms-3">
                                            <input class="form-check-input" type="checkbox" name="track_batch" id="trackBatch" value="1">
                                            <label class="form-check-label" for="trackBatch">{{ __('inventory.track_batches') }}</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Opening Stock by Warehouse (Single variation Goods only) -->
                    <div id="warehouseStocksSection" class="border-top pt-4 mt-4 single-item-only">
                        <h6 class="fw-bold text-primary mb-3"><i class="feather-home me-2"></i>{{ __('inventory.opening_stock_by_warehouse') }}</h6>
                        <div class="table-responsive">
                            <x-ui.odoo-form-ui type="table">
                                <thead class="table-light text-muted">
                                    <tr>
                                        <th>{{ __('inventory.warehouse_code') }}</th>
                                        <th>{{ __('inventory.warehouse_name') }}</th>
                                        <th>{{ __('inventory.quantity_on_hand') }}</th>
                                        <th>{{ __('inventory.unit_cost') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($warehouses as $wh)
                                        <tr>
                                            <td class="fw-semibold text-dark">{{ $wh->code }}</td>
                                            <td class="text-muted">{{ $wh->name }}</td>
                                            <td>
                                                <x-ui.odoo-form-ui type="input" inputType="number" name="warehouse_stocks[{{ $wh->id }}][quantity]" placeholder="0" />
                                            </td>
                                            <td>
                                                <x-ui.odoo-form-ui type="input" inputType="number" name="warehouse_stocks[{{ $wh->id }}][unit_cost]" placeholder="0.00" step="0.01" />
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </x-ui.odoo-form-ui>
                        </div>
                    </div>

                    <!-- Zoho Dynamic Variants Section (Only relevant when variation_type is 'Variant') -->
                    <div id="variantsSection" class="border-top pt-4 mt-4" style="display: none;">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="fw-bold text-primary mb-0"><i class="feather-git-branch me-2"></i>{{ __('inventory.attributes_options_builder') }}</h6>
                            <x-ui.button type="button" variant="soft-primary" size="sm" id="addAttributeBtn" icon="feather-plus">
                                {{ __('inventory.add_attribute') }}
                            </x-ui.button>
                        </div>

                        <!-- Attributes List -->
                        <div id="attributesContainer">
                            <!-- Template Row 1 (Preloaded: Color) -->
                            <div class="attribute-card" data-index="0">
                                <div class="row align-items-center">
                                    <div class="col-md-3">
                                        <label class="fs-12 fw-bold text-dark mb-1">{{ __('inventory.attribute_name') }}</label>
                                        <select name="attributes[0][name]" class="form-select form-select-sm attribute-name-select" style="border-radius: 0;">
                                            <option value="Color">Color</option>
                                            <option value="Size">Size</option>
                                            <option value="Material">Material</option>
                                            <option value="Style">Style</option>
                                            <option value="Custom">Custom...</option>
                                        </select>
                                        <input type="text" class="form-control form-control-sm attribute-custom-name mt-1" placeholder="Custom Attribute Name" style="display: none; border-radius: 0;">
                                    </div>
                                    <div class="col-md-8">
                                        <label class="fs-12 fw-bold text-dark mb-1">{{ __('inventory.options_help') }}</label>
                                        <div class="tag-input-container">
                                            <span class="tags-wrapper">
                                                <span class="tag-badge" data-val="Red">
                                                    Red <span class="remove-tag">&times;</span>
                                                    <input type="hidden" name="attributes[0][options][]" value="Red">
                                                </span>
                                                <span class="tag-badge" data-val="Blue">
                                                    Blue <span class="remove-tag">&times;</span>
                                                    <input type="hidden" name="attributes[0][options][]" value="Blue">
                                                </span>
                                            </span>
                                            <input type="text" class="tag-input" placeholder="{{ __('inventory.options_placeholder') }}">
                                        </div>
                                    </div>
                                    <div class="col-md-1 text-center mt-3 mt-md-0">
                                        <button type="button" class="btn btn-sm btn-soft-danger remove-attribute-btn"><i class="feather-trash-2"></i></button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Dynamic Matrix Spreadsheet Table -->
                        <div id="variantsMatrixContainer" class="mt-4" style="display: none;">
                            <x-ui.table :title="__('inventory.configure_variants')" bordered="true" class="variants-table-container">
                                <thead class="table-light fw-bold text-uppercase text-muted">
                                    <tr>
                                        <th style="width: 170px;" class="text-center">{{ __('inventory.image_media') }}</th>
                                        <th>{{ __('inventory.variant_details') }}</th>
                                        <th>{{ __('inventory.sku') }} *</th>
                                        <th>{{ __('inventory.selling_price') }}</th>
                                        <th>{{ __('inventory.cost_price') }}</th>
                                        <th>{{ __('inventory.opening_stock') }}</th>
                                        <th>{{ __('inventory.reorder_point') }}</th>
                                    </tr>
                                </thead>
                                <tbody id="variantsMatrixBody">
                                    <!-- Computed Variant Rows will be injected here by Javascript -->
                                </tbody>
                            </x-ui.table>
                        </div>
                    </div>

                    <!-- Media & Product Images Section -->
                    <div class="border-top pt-4 mt-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <div>
                                <h6 class="fw-bold text-primary mb-0 d-flex align-items-center gap-2">
                                    <i class="feather-image"></i>
                                    <span>{{ __('inventory.product_media_gallery') }}</span>
                                </h6>
                                <p class="fs-12 text-muted mb-0 mt-0.5">{{ __('inventory.product_media_desc') }}</p>
                            </div>
                            <x-ui.badge variant="primary" :soft="true">
                                <i class="feather-info me-1"></i>JPG, PNG, WebP (Max 5MB)
                            </x-ui.badge>
                        </div>
                        
                        <div class="row g-4">
                            <!-- 1. Main Display Thumbnail -->
                            <div class="col-lg-4 border-end">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <label class="fw-bold text-dark fs-12 mb-0">{{ __('inventory.main_showcase_image') }}</label>
                                    <x-ui.badge variant="primary" :soft="true">{{ __('inventory.catalog_pos') }}</x-ui.badge>
                                </div>
                                
                                <div class="erp-media-dropzone p-3 text-center position-relative">
                                    <div class="main-img-preview-box">
                                        <img id="mainImagePreview" src="{{ asset('assets/images/icons/default-product.svg') }}" alt="{{ __('inventory.main_image') }}" class="d-none">
                                        
                                        <div id="mainImagePlaceholder" class="text-center p-3">
                                            <div class="avatar avatar-md bg-soft-primary text-primary rounded-circle mx-auto mb-2 d-flex align-items-center justify-content-center">
                                                <i class="feather-camera fs-18"></i>
                                            </div>
                                            <span class="fs-12 fw-semibold text-dark d-block">{{ __('inventory.upload_main_image') }}</span>
                                            <span class="fs-11 text-muted d-block">{{ __('inventory.recommended_size_800') }}</span>
                                        </div>

                                        <div id="mainImageHoverActions" class="main-img-hover-actions">
                                            <label class="btn btn-xs btn-light shadow-sm cursor-pointer mb-0" title="{{ __('inventory.change_image') }}">
                                                <i class="feather-edit-2 me-1"></i>{{ __('inventory.change_image') }}
                                                <input type="file" name="main_image" id="mainImageInput" accept="image/jpeg,image/png,image/webp" class="d-none" onchange="previewMainImage(this)">
                                            </label>
                                            <button type="button" class="btn btn-xs btn-danger shadow-sm" onclick="clearMainImage()" title="{{ __('inventory.remove_image') }}">
                                                <i class="feather-trash-2"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <div id="mainImageDefaultActions">
                                        <label class="btn btn-sm btn-outline-primary cursor-pointer mb-0">
                                            <i class="feather-upload me-1"></i>{{ __('inventory.browse_image') }}
                                            <input type="file" name="main_image" id="mainImageTriggerInput" accept="image/jpeg,image/png,image/webp" class="d-none" onchange="syncMainImageInput(this)">
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- 2. Detail Gallery Images -->
                            <div class="col-lg-8">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <label class="fw-bold text-dark fs-12 mb-0">{{ __('inventory.detail_gallery_images') }}</label>
                                        <x-ui.badge variant="info" :soft="true" id="detailCountBadge">{{ __('inventory.images_count_label', ['count' => 0]) }}</x-ui.badge>
                                    </div>
                                    <label class="btn btn-sm btn-outline-primary cursor-pointer mb-0">
                                        <i class="feather-plus me-1"></i>{{ __('inventory.add_detail_images') }}
                                        <input type="file" name="detail_images[]" id="detailImagesInput" accept="image/jpeg,image/png,image/webp" multiple class="d-none" onchange="previewDetailImages(this)">
                                    </label>
                                </div>
                                
                                <div id="detailImagesContainer" class="erp-media-dropzone p-3" style="min-height: 190px;">
                                    <div id="detailImagesEmpty" class="text-center py-4">
                                        <div class="avatar avatar-lg bg-soft-info text-info rounded-circle mx-auto mb-2 d-flex align-items-center justify-content-center">
                                            <i class="feather-images fs-22"></i>
                                        </div>
                                        <h6 class="fs-13 fw-bold text-dark mb-1">{{ __('inventory.no_gallery_images_added') }}</h6>
                                        <p class="fs-12 text-muted mb-2">{{ __('inventory.detail_images_help') }}</p>
                                        <label class="btn btn-sm btn-soft-primary cursor-pointer mb-0">
                                            <i class="feather-upload me-1"></i>{{ __('inventory.choose_photos') }}
                                            <input type="file" accept="image/jpeg,image/png,image/webp" multiple class="d-none" onchange="previewDetailImages(this)">
                                        </label>
                                    </div>
                                    <div id="detailImagesGrid" class="gallery-grid d-none">
                                        <!-- Dynamic preview cards with individual remove button injected here -->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Additional Notes -->
                    <div class="border-top pt-4 mt-4">
                        <h6 class="fw-bold text-primary mb-3"><i class="feather-edit-3 me-2"></i>{{ __('inventory.description_item_notes') }}</h6>
                        <x-ui.odoo-form-ui type="textarea" :label="__('inventory.internal_notes')" name="description" rows="3" :placeholder="__('inventory.enter_internal_notes')" :value="old('description')" :errorText="$errors->first('description')"></x-ui.odoo-form-ui>
                    </div>

                    <!-- Action buttons footer -->
                    <div class="d-flex justify-content-end gap-2 mt-4 border-top pt-3">
                        <a href="{{ route('inventory.products.index') }}" class="btn btn-light border">{{ __('inventory.cancel') }}</a>
                        <button type="submit" class="btn btn-primary">{{ __('inventory.save_product') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <!-- Select2 JS -->
    <script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
    <script>
        window.variantMediaStore = window.variantMediaStore || {};

        $(document).ready(function() {
            let attributeIndex = 1;

            $('input[name="item_type"]').on('change', function() {
                toggleSections();
            });

            $('input[name="variation_type"]').on('change', function() {
                toggleSections();
            });

            const $uomSelect = $('select[name="uom_id"]');
            const originalUomOptions = $uomSelect.find('option').clone();

            function filterUomOptions() {
                const itemType = $('input[name="item_type"]:checked').val() || 'Goods';
                const isService = itemType === 'Service';
                const currentVal = $uomSelect.val();

                $uomSelect.empty();

                originalUomOptions.each(function() {
                    const uomCat = ($(this).attr('data-uom-category') || '').toLowerCase();
                    let showOption = true;
                    if (isService) {
                        if (uomCat && !['service', 'hours', 'unit', 'both', ''].includes(uomCat)) {
                            showOption = false;
                        }
                    } else {
                        if (uomCat === 'service') {
                            showOption = false;
                        }
                    }

                    if (showOption) {
                        $uomSelect.append($(this).clone());
                    }
                });

                if (currentVal && $uomSelect.find(`option[value="${currentVal}"]`).length) {
                    $uomSelect.val(currentVal);
                } else {
                    $uomSelect.val('');
                }

                if ($uomSelect.data('select2')) {
                    $uomSelect.trigger('change');
                }
            }

            function setFieldRequired(fieldName, isRequired) {
                const $input = $(`input[name="${fieldName}"]`);
                $input.prop('required', isRequired);

                const $label = $input.closest('.odoo-form-group').find('.odoo-form-label');
                if (isRequired) {
                    $label.css('color', '#dc3545');
                    if ($label.find('.text-danger').length === 0) {
                        $label.append(' <span class="text-danger">*</span>');
                    }
                } else {
                    $label.css('color', '');
                    $label.find('.text-danger').remove();
                }
            }

            function toggleSections() {
                const itemType = $('input[name="item_type"]:checked').val();
                const variationType = $('input[name="variation_type"]:checked').val();

                filterUomOptions();

                const hsnLabel = $('input[name="hsn_sac"]').closest('.odoo-form-group').find('.odoo-form-label');
                if (itemType === 'Service') {
                    hsnLabel.html('SAC Code');
                    $('input[name="hsn_sac"]').attr('placeholder', 'e.g. 9983 (SAC)');
                    $('.physical-goods-only').hide();
                    $('.supplier-method-container').hide();
                    $('select[name="preferred_vendor_id"]').closest('.odoo-form-group').hide();
                    $('select[name="type"]').val('service').trigger('change').closest('.odoo-form-group').hide();
                    $('#inventorySection').hide();
                    $('#warehouseStocksSection').hide();
                    $('select[name="inventory_account"]').prop('required', false);
                    $('.variant-stock-col').hide();
                } else {
                    hsnLabel.html('HSN Code');
                    $('input[name="hsn_sac"]').attr('placeholder', 'e.g. 8471 (HSN)');
                    $('.physical-goods-only').show();
                    $('.supplier-method-container').show();
                    $('select[name="preferred_vendor_id"]').closest('.odoo-form-group').show();
                    $('select[name="type"]').closest('.odoo-form-group').show();
                    $('.variant-stock-col').show();

                    $('#inventorySection').show();
                    $('select[name="inventory_account"]').prop('required', false);

                    if (variationType === 'Single') {
                        $('#warehouseStocksSection').show();
                    } else {
                        $('#warehouseStocksSection').hide();
                    }
                }

                if (variationType === 'Variant') {
                    $('.single-item-only').hide();
                    $('input[name="sku"]').prop('required', false);
                    $('#variantsSection').show();
                    generateMatrix();
                } else {
                    if (itemType !== 'Service') {
                        $('.single-item-only').show();
                    }
                    $('input[name="sku"]').prop('required', true);
                    $('#variantsSection').hide();
                }
            }

            // ── Attribute Name select: show/hide custom input ──────────────────
            $(document).on('change', '.attribute-name-select', function() {
                const isCustom = $(this).val() === 'Custom';
                const inputCustom = $(this).siblings('.attribute-custom-name');
                inputCustom.toggle(isCustom).prop('required', isCustom);

                const cardIndex = $(this).closest('.attribute-card').attr('data-index');
                if (isCustom) {
                    $(this).removeAttr('name');
                    inputCustom.attr('name', `attributes[${cardIndex}][name]`);
                } else {
                    $(this).attr('name', `attributes[${cardIndex}][name]`);
                    inputCustom.removeAttr('name');
                }
                generateMatrix();
            });

            $(document).on('input', '.attribute-custom-name', function() {
                generateMatrix();
            });

            // ── Tag input: add tag on Enter or Comma ───────────────────────────
            $(document).on('keydown', '.tag-input', function(e) {
                if (e.key === 'Enter' || e.key === ',') {
                    e.preventDefault();
                    const val = $(this).val().trim().replace(/,/g, '');
                    if (val) {
                        const wrapper = $(this).siblings('.tags-wrapper');
                        const cardIndex = $(this).closest('.attribute-card').attr('data-index');

                        let exists = false;
                        wrapper.find('.tag-badge').each(function() {
                            if ($(this).attr('data-val').toLowerCase() === val.toLowerCase()) {
                                exists = true;
                            }
                        });

                        if (!exists) {
                            wrapper.append(`
                                <span class="tag-badge" data-val="${val}">
                                    ${val} <span class="remove-tag">&times;</span>
                                    <input type="hidden" name="attributes[${cardIndex}][options][]" value="${val}">
                                </span>
                            `);
                            generateMatrix();
                        }
                        $(this).val('');
                    }
                }
            });

            // ── Remove tag ─────────────────────────────────────────────────────
            $(document).on('click', '.remove-tag', function() {
                $(this).closest('.tag-badge').remove();
                generateMatrix();
            });

            // ── Add Attribute row ──────────────────────────────────────────────
            $('#addAttributeBtn').on('click', function() {
                const html = `
                    <div class="attribute-card" data-index="${attributeIndex}">
                        <div class="row align-items-center">
                            <div class="col-md-3">
                                <label class="fs-12 fw-bold text-dark mb-1">Attribute Name</label>
                                <select name="attributes[${attributeIndex}][name]" class="form-select form-select-sm attribute-name-select" style="border-radius: 0;">
                                    <option value="Size">Size</option>
                                    <option value="Color">Color</option>
                                    <option value="Material">Material</option>
                                    <option value="Style">Style</option>
                                    <option value="Custom">Custom...</option>
                                </select>
                                <input type="text" class="form-control form-control-sm attribute-custom-name mt-1" placeholder="Custom Attribute Name" style="display: none; border-radius: 0;">
                            </div>
                            <div class="col-md-8">
                                <label class="fs-12 fw-bold text-dark mb-1">Options (Type option value and press Enter or Comma)</label>
                                <div class="tag-input-container">
                                    <span class="tags-wrapper"></span>
                                    <input type="text" class="tag-input" placeholder="e.g. Small, Medium, Large">
                                </div>
                            </div>
                            <div class="col-md-1 text-center mt-3 mt-md-0">
                                <button type="button" class="btn btn-sm btn-soft-danger remove-attribute-btn"><i class="feather-trash-2"></i></button>
                            </div>
                        </div>
                    </div>
                `;
                $('#attributesContainer').append(html);
                attributeIndex++;
                generateMatrix();
            });

            // ── Remove Attribute row ───────────────────────────────────────────
            $(document).on('click', '.remove-attribute-btn', function() {
                $(this).closest('.attribute-card').remove();
                generateMatrix();
            });

            // ── Auto-propagate Selling Price & Cost Price to Matrix Rows ───────
            $(document).on('input', 'input[name="selling_price"]', function() {
                const val = $(this).val();
                $('#variantsMatrixBody tr').each(function() {
                    const $sellingInput = $(this).find('input.variant-selling-price');
                    if ($sellingInput.length && (!$sellingInput.val() || $sellingInput.attr('data-auto-filled') === 'true')) {
                        $sellingInput.val(val);
                        $sellingInput.attr('data-auto-filled', 'true');
                    }
                });
            });

            $(document).on('input', 'input[name="cost_price"]', function() {
                const val = $(this).val();
                $('#variantsMatrixBody tr').each(function() {
                    const $costInput = $(this).find('input.variant-cost-price');
                    if ($costInput.length && (!$costInput.val() || $costInput.attr('data-auto-filled') === 'true')) {
                        $costInput.val(val);
                        $costInput.attr('data-auto-filled', 'true');
                    }
                });
            });

            $(document).on('input', '#variantsMatrixBody input.variant-selling-price, #variantsMatrixBody input.variant-cost-price', function() {
                $(this).attr('data-auto-filled', 'false');
            });

            // ── Dynamic Combination Matrix (Cartesian Product) ─────────────────
            function generateMatrix() {
                const attributes = [];
                $('#attributesContainer .attribute-card').each(function() {
                    const selectVal = $(this).find('.attribute-name-select').val();
                    const name = selectVal === 'Custom'
                        ? $(this).find('.attribute-custom-name').val().trim()
                        : selectVal;
                    const options = [];

                    $(this).find('.tag-badge').each(function() {
                        options.push($(this).attr('data-val'));
                    });

                    if (name && options.length > 0) {
                        attributes.push({ name: name, options: options });
                    }
                });

                if (attributes.length === 0) {
                    $('#variantsMatrixContainer').hide();
                    return;
                }

                const cartesian = (sets) => {
                    return sets.reduce((acc, set) => {
                        return acc.flatMap(x => set.map(y => [...x, y]));
                    }, [[]]);
                };

                const optionSets = attributes.map(a => a.options);
                const combinations = cartesian(optionSets);

                const parentName = $('input[name="name"]').val().trim() || 'Product';
                const defaultSellingPrice = $('input[name="selling_price"]').val() || '';
                const defaultCostPrice = $('input[name="cost_price"]').val() || '';

                const tbody = $('#variantsMatrixBody');
                tbody.empty();

                combinations.forEach((combo, index) => {
                    const comboTextParts = combo.map((val, idx) => `${attributes[idx].name}: ${val}`);
                    const comboString = comboTextParts.join(', ');
                    const autoSku = combo.join('-').toUpperCase().replace(/\s+/g, '');

                    const store = window.variantMediaStore && window.variantMediaStore[index] ? window.variantMediaStore[index] : null;
                    const mainImgUrl = store && store.mainPreviewUrl ? store.mainPreviewUrl : '';
                    const photoCount = store ? ((store.mainFile ? 1 : 0) + (store.detailDT ? store.detailDT.files.length : 0)) : 0;

                    const rowHtml = `
                        <tr id="variant_row_${index}">
                            <td class="text-center align-middle" style="width: 170px;">
                                <div class="d-inline-flex align-items-center justify-content-center gap-2 py-1">
                                    <div class="variant-thumb-box position-relative rounded-2 border bg-light d-flex align-items-center justify-content-center shadow-2xs" 
                                         style="width: 40px; height: 40px; overflow: hidden; border-color: #e2e8f0; flex-shrink: 0;"
                                         onclick="openVariantMediaModal(${index})"
                                         title="Click to manage Main & Gallery photos for this variant">
                                        <img id="v_row_thumb_${index}" src="${mainImgUrl || ''}" class="w-100 h-100 object-fit-cover ${mainImgUrl ? '' : 'd-none'}" alt="Variant">
                                        <i id="v_row_icon_${index}" class="feather-camera text-muted fs-15 ${mainImgUrl ? 'd-none' : ''}"></i>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-soft-primary d-inline-flex align-items-center gap-1.5 px-2.5 py-1 rounded-2 shadow-none border-0 text-nowrap" onclick="openVariantMediaModal(${index})" title="Manage Photos for this variant">
                                        <i class="feather-image fs-12"></i>
                                        <span class="fs-11 fw-semibold">Photos</span>
                                        <span class="badge bg-primary text-white rounded-pill px-1.5 py-0.5 fs-10" id="v_row_count_${index}">${photoCount}</span>
                                    </button>
                                    <input type="file" name="variants[${index}][main_image]" id="v_input_main_${index}" accept="image/jpeg,image/png,image/webp" class="d-none">
                                    <input type="file" name="variants[${index}][detail_images][]" id="v_input_detail_${index}" accept="image/jpeg,image/png,image/webp" multiple class="d-none">
                                </div>
                            </td>
                            <td class="fw-semibold text-dark">
                                <span class="badge bg-soft-primary text-primary me-2">Variant #${index + 1}</span>
                                <span class="variant-name-label">${parentName} (${comboString})</span>
                                <input type="hidden" name="variants[${index}][attributes]" value="${comboString}">
                            </td>
                            <td>
                                <input type="text" name="variants[${index}][sku]" value="${autoSku}" class="form-control form-control-sm py-1 fw-semibold" required style="border-radius: 0; min-width: 140px;">
                            </td>
                            <td>
                                <input type="number" step="0.01" name="variants[${index}][selling_price]" value="${defaultSellingPrice}" class="form-control form-control-sm py-1 variant-selling-price" style="border-radius: 0; min-width: 100px;" data-auto-filled="${defaultSellingPrice ? 'true' : 'false'}">
                            </td>
                            <td>
                                <input type="number" step="0.01" name="variants[${index}][cost_price]" value="${defaultCostPrice}" class="form-control form-control-sm py-1 variant-cost-price" style="border-radius: 0; min-width: 100px;" data-auto-filled="${defaultCostPrice ? 'true' : 'false'}">
                            </td>
                            <td class="variant-stock-col">
                                <input type="number" step="0.01" name="variants[${index}][opening_stock]" value="0" class="form-control form-control-sm py-1" style="border-radius: 0; min-width: 80px;">
                            </td>
                            <td>
                                <input type="number" step="0.01" name="variants[${index}][reorder_point]" value="0" class="form-control form-control-sm py-1" style="border-radius: 0; min-width: 80px;">
                            </td>
                        </tr>
                    `;
                    tbody.append(rowHtml);
                });

                if (combinations.length > 0 && combinations[0].length > 0) {
                    $('#variantsMatrixContainer').show();
                } else {
                    $('#variantsMatrixContainer').hide();
                }
            }

            // Execute on initial page load
            toggleSections();

            function clearQuickUnitErrors() {
                $('#quickAddUnitForm .is-invalid').removeClass('is-invalid');
                $('#quickAddUnitForm .quick-field-error').remove();
                $('#quickUnitAlert').addClass('d-none').text('');
            }

            $(document).on('change', 'select[name="uom_id"]', function() {
                if ($(this).val() === '__add_unit__') {
                    $(this).val('').trigger('change.select2');
                    clearQuickUnitErrors();
                    const modalEl = document.getElementById('quickAddUnitModal');
                    if (modalEl) {
                        const bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
                        bsModal.show();
                    }
                }
            });

            $('#quick_uom_name, #quick_uom_code').on('input change', function() {
                $(this).removeClass('is-invalid');
                $(this).closest('.form-ui-group').find('.quick-field-error').remove();
            });

            $('#quick_uom_name').on('input', function() {
                if (!$('#quick_uom_code').data('manually-edited')) {
                    const code = $(this).val().trim().toUpperCase().substring(0, 5);
                    $('#quick_uom_code').val(code);
                }
            });

            $('#quick_uom_code').on('input', function() {
                $(this).data('manually-edited', true);
            });

            $('#quickAddUnitForm').on('submit', function(e) {
                e.preventDefault();
                clearQuickUnitErrors();

                const $nameInput = $('#quick_uom_name');
                const $codeInput = $('#quick_uom_code');
                let hasError = false;

                if (!$.trim($nameInput.val())) {
                    $nameInput.addClass('is-invalid');
                    $nameInput.closest('.form-ui-group').append('<div class="invalid-feedback d-block fs-11 mt-1 quick-field-error">Please enter Unit Name</div>');
                    hasError = true;
                }

                if (!$.trim($codeInput.val())) {
                    $codeInput.addClass('is-invalid');
                    $codeInput.closest('.form-ui-group').append('<div class="invalid-feedback d-block fs-11 mt-1 quick-field-error">Please enter Unit Code / Symbol</div>');
                    hasError = true;
                }

                if (hasError) {
                    return false;
                }

                const $form = $(this);
                const $btn = $('#saveQuickUnitBtn');
                const $alert = $('#quickUnitAlert');

                $btn.prop('disabled', true).html('<i class="feather-loader spinner-border spinner-border-sm me-1"></i> Saving...');

                $.ajax({
                    url: $form.attr('action'),
                    method: 'POST',
                    data: $form.serialize(),
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        $btn.prop('disabled', false).html('<i class="feather-check me-1"></i>Save & Select Unit');
                        
                        if (response && response.id) {
                            const optionText = response.name + (response.code ? ' (' + response.code + ')' : '');
                            const newOptHtml = `<option value="${response.id}" data-uom-category="both">${optionText}</option>`;
                            
                            const $select = $('select[name="uom_id"]');
                            $select.find('option[value="__add_unit__"]').after(newOptHtml);
                            
                            if (typeof originalUomOptions !== 'undefined') {
                                originalUomOptions = $select.find('option').clone();
                            }

                            $select.val(response.id).trigger('change');

                            $form[0].reset();
                            clearQuickUnitErrors();
                            $('#quick_uom_code').data('manually-edited', false);

                            const modalEl = document.getElementById('quickAddUnitModal');
                            if (modalEl) {
                                const bsModal = bootstrap.Modal.getInstance(modalEl);
                                if (bsModal) bsModal.hide();
                            }
                        }
                    },
                    error: function(xhr) {
                        $btn.prop('disabled', false).html('<i class="feather-check me-1"></i>Save & Select Unit');
                        
                        if (xhr.responseJSON && xhr.responseJSON.errors) {
                            const errors = xhr.responseJSON.errors;
                            if (errors.name) {
                                $nameInput.addClass('is-invalid');
                                $nameInput.closest('.form-ui-group').append(`<div class="invalid-feedback d-block fs-11 mt-1 quick-field-error">${errors.name[0]}</div>`);
                            }
                            if (errors.code) {
                                $codeInput.addClass('is-invalid');
                                $codeInput.closest('.form-ui-group').append(`<div class="invalid-feedback d-block fs-11 mt-1 quick-field-error">${errors.code[0]}</div>`);
                            }
                        } else {
                            let msg = 'Failed to create Unit of Measure.';
                            if (xhr.responseJSON && xhr.responseJSON.message) {
                                msg = xhr.responseJSON.message;
                            }
                            $alert.removeClass('d-none').html(msg);
                        }
                    }
                });
            });

            // ── Product Media & Detail Gallery Handlers ────────────────────────
            window.syncMainImageInput = function(triggerInput) {
                if (triggerInput.files && triggerInput.files[0]) {
                    const mainInput = document.getElementById('mainImageInput');
                    const dt = new DataTransfer();
                    dt.items.add(triggerInput.files[0]);
                    mainInput.files = dt.files;
                    window.previewMainImage(mainInput);
                }
            };

            window.previewMainImage = function(input) {
                if (input.files && input.files[0]) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        $('#mainImagePreview').attr('src', e.target.result).removeClass('d-none');
                        $('#mainImagePlaceholder').addClass('d-none');
                        $('#mainImageHoverActions').css('display', 'flex');
                        $('#mainImageDefaultActions').addClass('d-none');
                    };
                    reader.readAsDataURL(input.files[0]);
                }
            };

            window.clearMainImage = function() {
                $('#mainImageInput').val('');
                $('#mainImageTriggerInput').val('');
                $('#mainImagePreview').attr('src', '').addClass('d-none');
                $('#mainImagePlaceholder').removeClass('d-none');
                $('#mainImageHoverActions').removeAttr('style');
                $('#mainImageDefaultActions').removeClass('d-none');
            };

            // Detail Gallery DataTransfer & Dynamic Previews
            window.detailFilesDT = new DataTransfer();

            window.previewDetailImages = function(input) {
                if (!input.files || input.files.length === 0) return;

                // Add newly selected files to existing DataTransfer
                Array.from(input.files).forEach(file => {
                    window.detailFilesDT.items.add(file);
                });

                // Keep real input element in sync
                const mainDetailInput = document.getElementById('detailImagesInput');
                if (mainDetailInput) {
                    mainDetailInput.files = window.detailFilesDT.files;
                }

                window.renderDetailPreviews();
            };

            window.removeDetailImage = function(indexToRemove) {
                const newDT = new DataTransfer();
                Array.from(window.detailFilesDT.files).forEach((file, idx) => {
                    if (idx !== indexToRemove) {
                        newDT.items.add(file);
                    }
                });
                window.detailFilesDT = newDT;

                const mainDetailInput = document.getElementById('detailImagesInput');
                if (mainDetailInput) {
                    mainDetailInput.files = window.detailFilesDT.files;
                }

                window.renderDetailPreviews();
            };

            window.renderDetailPreviews = function() {
                const grid = $('#detailImagesGrid');
                const empty = $('#detailImagesEmpty');
                const badge = $('#detailCountBadge');
                const files = Array.from(window.detailFilesDT.files);

                grid.empty();

                if (files.length === 0) {
                    empty.removeClass('d-none');
                    grid.addClass('d-none');
                    badge.text('0 images');
                    return;
                }

                empty.addClass('d-none');
                grid.removeClass('d-none');
                badge.text(`${files.length} image${files.length > 1 ? 's' : ''}`);

                files.forEach((file, idx) => {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const cardHtml = `
                            <div class="gallery-card-item" id="detail_img_${idx}">
                                <img src="${e.target.result}" alt="${file.name}">
                                <button type="button" class="gallery-remove-btn" onclick="removeDetailImage(${idx})" title="Remove ${file.name}">
                                    <i class="feather-x"></i>
                                </button>
                                <div class="gallery-card-info" title="${file.name}">
                                    ${file.name}
                                </div>
                            </div>
                        `;
                        grid.append(cardHtml);
                    };
                    reader.readAsDataURL(file);
                });

                // Append + Add More button card
                const addMoreHtml = `
                    <label class="gallery-add-more-box mb-0" title="Add more detail images">
                        <i class="feather-plus fs-20 mb-1"></i>
                        <span class="fs-11 fw-semibold">Add More</span>
                        <input type="file" accept="image/jpeg,image/png,image/webp" multiple class="d-none" onchange="previewDetailImages(this)">
                    </label>
                `;
                grid.append(addMoreHtml);
            };

            // ── Variant Media Manager ──────────────────────────────────────────
            window.variantMediaStore = {};
            let currentVModalIndex = null;

            function getOrCreateVMediaStore(index) {
                if (!window.variantMediaStore[index]) {
                    window.variantMediaStore[index] = {
                        mainFile: null,
                        mainPreviewUrl: null,
                        detailDT: new DataTransfer()
                    };
                }
                return window.variantMediaStore[index];
            }

            window.openVariantMediaModal = function(index) {
                currentVModalIndex = index;
                const store = getOrCreateVMediaStore(index);

                // Set variant title badge
                const row = $(`#variant_row_${index}`);
                const titleText = row.find('.variant-name-label').text().trim() || `Variant #${index + 1}`;
                $('#vModalVariantTitle').text(titleText);

                // Render main image state
                renderVModalMainImage(store);

                // Render detail gallery state
                renderVModalDetailImages(store);

                const modalEl = document.getElementById('variantMediaModal');
                if (modalEl) {
                    const bsModal = bootstrap.Modal.getOrCreateInstance(modalEl);
                    bsModal.show();
                }
            };

            function renderVModalMainImage(store) {
                const previewImg = $('#vModalMainPreview');
                const placeholder = $('#vModalMainPlaceholder');
                const hoverActions = $('#vModalMainHoverActions');
                const defaultActions = $('#vModalMainDefaultActions');

                if (store.mainPreviewUrl) {
                    previewImg.attr('src', store.mainPreviewUrl).removeClass('d-none');
                    placeholder.addClass('d-none');
                    hoverActions.css('display', 'flex');
                    defaultActions.addClass('d-none');
                } else {
                    previewImg.attr('src', '').addClass('d-none');
                    placeholder.removeClass('d-none');
                    hoverActions.removeAttr('style');
                    defaultActions.removeClass('d-none');
                }
            }

            window.vModalOnMainImageChange = function(input) {
                if (!input.files || !input.files[0] || currentVModalIndex === null) return;
                const file = input.files[0];
                const store = getOrCreateVMediaStore(currentVModalIndex);
                store.mainFile = file;

                const reader = new FileReader();
                reader.onload = function(e) {
                    store.mainPreviewUrl = e.target.result;
                    renderVModalMainImage(store);

                    // Update row thumbnail in matrix table
                    $(`#v_row_thumb_${currentVModalIndex}`).attr('src', e.target.result).removeClass('d-none');
                    $(`#v_row_icon_${currentVModalIndex}`).addClass('d-none');

                    // Sync hidden file input in row
                    const mainInput = document.getElementById(`v_input_main_${currentVModalIndex}`);
                    if (mainInput) {
                        const dt = new DataTransfer();
                        dt.items.add(file);
                        mainInput.files = dt.files;
                    }

                    updateVRowPhotoCount(currentVModalIndex);
                };
                reader.readAsDataURL(file);
            };

            window.vModalClearMainImage = function() {
                if (currentVModalIndex === null) return;
                const store = getOrCreateVMediaStore(currentVModalIndex);
                store.mainFile = null;
                store.mainPreviewUrl = null;
                renderVModalMainImage(store);

                // Update row thumbnail in matrix table
                $(`#v_row_thumb_${currentVModalIndex}`).attr('src', '').addClass('d-none');
                $(`#v_row_icon_${currentVModalIndex}`).removeClass('d-none');

                // Sync hidden file input in row
                const mainInput = document.getElementById(`v_input_main_${currentVModalIndex}`);
                if (mainInput) {
                    mainInput.value = '';
                }

                updateVRowPhotoCount(currentVModalIndex);
            };

            function renderVModalDetailImages(store) {
                const grid = $('#vModalDetailGrid');
                const empty = $('#vModalDetailEmpty');
                const badge = $('#vModalDetailCountBadge');
                const files = Array.from(store.detailDT.files);

                grid.empty();

                if (files.length === 0) {
                    empty.removeClass('d-none');
                    grid.addClass('d-none');
                    badge.text('0 images');
                    return;
                }

                empty.addClass('d-none');
                grid.removeClass('d-none');
                badge.text(`${files.length} image${files.length > 1 ? 's' : ''}`);

                files.forEach((file, idx) => {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const cardHtml = `
                            <div class="gallery-card-item">
                                <img src="${e.target.result}" alt="${file.name}">
                                <button type="button" class="gallery-remove-btn" onclick="vModalRemoveDetailImage(${idx})" title="Remove ${file.name}">
                                    <i class="feather-x"></i>
                                </button>
                                <div class="gallery-card-info" title="${file.name}">
                                    ${file.name}
                                </div>
                            </div>
                        `;
                        grid.append(cardHtml);
                    };
                    reader.readAsDataURL(file);
                });

                // Append + Add More button card
                const addMoreHtml = `
                    <label class="gallery-add-more-box mb-0" title="Add more detail images">
                        <i class="feather-plus fs-18 mb-1"></i>
                        <span class="fs-10 fw-semibold">Add More</span>
                        <input type="file" accept="image/jpeg,image/png,image/webp" multiple class="d-none" onchange="vModalOnDetailImagesChange(this)">
                    </label>
                `;
                grid.append(addMoreHtml);
            }

            window.vModalOnDetailImagesChange = function(input) {
                if (!input.files || input.files.length === 0 || currentVModalIndex === null) return;
                const store = getOrCreateVMediaStore(currentVModalIndex);

                Array.from(input.files).forEach(file => {
                    store.detailDT.items.add(file);
                });

                // Sync hidden detail input in row
                const detailInput = document.getElementById(`v_input_detail_${currentVModalIndex}`);
                if (detailInput) {
                    detailInput.files = store.detailDT.files;
                }

                renderVModalDetailImages(store);
                updateVRowPhotoCount(currentVModalIndex);
            };

            window.vModalRemoveDetailImage = function(indexToRemove) {
                if (currentVModalIndex === null) return;
                const store = getOrCreateVMediaStore(currentVModalIndex);

                const newDT = new DataTransfer();
                Array.from(store.detailDT.files).forEach((file, idx) => {
                    if (idx !== indexToRemove) {
                        newDT.items.add(file);
                    }
                });
                store.detailDT = newDT;

                // Sync hidden detail input in row
                const detailInput = document.getElementById(`v_input_detail_${currentVModalIndex}`);
                if (detailInput) {
                    detailInput.files = store.detailDT.files;
                }

                renderVModalDetailImages(store);
                updateVRowPhotoCount(currentVModalIndex);
            };

            function updateVRowPhotoCount(index) {
                const store = window.variantMediaStore[index];
                const count = store ? ((store.mainFile ? 1 : 0) + (store.detailDT ? store.detailDT.files.length : 0)) : 0;
                $(`#v_row_count_${index}`).text(count);
            }

            // Sync all variant files before form submit
            $('#productForm').on('submit', function() {
                if (window.variantMediaStore) {
                    Object.keys(window.variantMediaStore).forEach(idx => {
                        const store = window.variantMediaStore[idx];
                        if (store) {
                            if (store.mainFile) {
                                const mainIn = document.getElementById(`v_input_main_${idx}`);
                                if (mainIn) {
                                    const dt = new DataTransfer();
                                    dt.items.add(store.mainFile);
                                    mainIn.files = dt.files;
                                }
                            }
                            if (store.detailDT && store.detailDT.files.length > 0) {
                                const detailIn = document.getElementById(`v_input_detail_${idx}`);
                                if (detailIn) {
                                    detailIn.files = store.detailDT.files;
                                }
                            }
                        }
                    });
                }
            });
        });
    </script>

    <!-- Variant Media Modal (Dedicated Multi-Angle & Main Image Uploader per Variant) -->
    <!-- Variant Media Modal (Dedicated Multi-Angle & Main Image Uploader per Variant) -->
    <x-ui.modal id="variantMediaModal" :title="'<i class=\'feather-image text-primary me-2\'></i>' . __('inventory.variant_photos_gallery')" centered="true" size="lg" :showFooter="false">
        <div class="p-1">
            <div class="d-flex justify-content-between align-items-center pb-3 mb-3 border-bottom">
                <div>
                    <h6 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                        <span>{{ __('inventory.variation') }}:</span>
                        <span id="vModalVariantTitle" class="badge bg-soft-primary text-primary fs-12"></span>
                    </h6>
                    <span class="fs-11 text-muted">{{ __('inventory.variant_media_desc') }}</span>
                </div>
            </div>

            <div class="row g-3">
                <!-- 1. Variant Main Image -->
                <div class="col-md-5 border-end">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <label class="fw-bold text-dark fs-12 mb-0">{{ __('inventory.variant_main_image') }}</label>
                        <x-ui.badge variant="primary" :soft="true">{{ __('inventory.showcase') }}</x-ui.badge>
                    </div>
                    
                    <div class="erp-media-dropzone p-3 text-center position-relative">
                        <div class="main-img-preview-box" style="width: 130px; height: 130px;">
                            <img id="vModalMainPreview" src="" alt="{{ __('inventory.variant_main_image') }}" class="d-none">
                            
                            <div id="vModalMainPlaceholder" class="text-center p-2">
                                <div class="avatar avatar-md bg-soft-primary text-primary rounded-circle mx-auto mb-1 d-flex align-items-center justify-content-center">
                                    <i class="feather-camera fs-16"></i>
                                </div>
                                <span class="fs-11 fw-semibold text-dark d-block">{{ __('inventory.main_thumbnail') }}</span>
                                <span class="fs-10 text-muted d-block">800×800 px</span>
                            </div>

                            <div id="vModalMainHoverActions" class="main-img-hover-actions">
                                <label class="btn btn-xs btn-light shadow-sm cursor-pointer mb-0" title="{{ __('inventory.change_image') }}">
                                    <i class="feather-edit-2 me-1"></i>{{ __('inventory.change_image') }}
                                    <input type="file" accept="image/jpeg,image/png,image/webp" class="d-none" onchange="vModalOnMainImageChange(this)">
                                </label>
                                <button type="button" class="btn btn-xs btn-danger shadow-sm" onclick="vModalClearMainImage()" title="{{ __('inventory.remove_image') }}">
                                    <i class="feather-trash-2"></i>
                                </button>
                            </div>
                        </div>

                        <div id="vModalMainDefaultActions">
                            <label class="btn btn-xs btn-outline-primary cursor-pointer mb-0">
                                <i class="feather-upload me-1"></i>{{ __('inventory.browse_main') }}
                                <input type="file" accept="image/jpeg,image/png,image/webp" class="d-none" onchange="vModalOnMainImageChange(this)">
                            </label>
                        </div>
                    </div>
                </div>

                <!-- 2. Variant Detail Gallery Images -->
                <div class="col-md-7">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div class="d-flex align-items-center gap-2">
                            <label class="fw-bold text-dark fs-12 mb-0">{{ __('inventory.variant_detail_gallery') }}</label>
                            <x-ui.badge variant="info" :soft="true" id="vModalDetailCountBadge">{{ __('inventory.images_count_label', ['count' => 0]) }}</x-ui.badge>
                        </div>
                        <label class="btn btn-xs btn-outline-primary cursor-pointer mb-0">
                            <i class="feather-plus me-1"></i>{{ __('inventory.add_detail_images') }}
                            <input type="file" accept="image/jpeg,image/png,image/webp" multiple class="d-none" onchange="vModalOnDetailImagesChange(this)">
                        </label>
                    </div>
                    
                    <div id="vModalDetailContainer" class="erp-media-dropzone p-2.5" style="min-height: 160px;">
                        <div id="vModalDetailEmpty" class="text-center py-3">
                            <i class="feather-images fs-22 text-muted d-block mb-1"></i>
                            <p class="fs-11 text-muted mb-1">{{ __('inventory.variant_detail_empty_desc') }}</p>
                            <label class="btn btn-xs btn-soft-primary cursor-pointer mb-0">
                                <i class="feather-upload me-1"></i>{{ __('inventory.choose_photos') }}
                                <input type="file" accept="image/jpeg,image/png,image/webp" multiple class="d-none" onchange="vModalOnDetailImagesChange(this)">
                            </label>
                        </div>
                        <div id="vModalDetailGrid" class="gallery-grid d-none"></div>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                <button type="button" class="btn btn-sm btn-primary" data-bs-dismiss="modal">
                    <i class="feather-check me-1"></i>{{ __('inventory.done_apply') }}
                </button>
            </div>
        </div>
    </x-ui.modal>

    <!-- Quick Add Unit Modal (Common Component Modal) -->
    <x-ui.modal id="quickAddUnitModal" :title="'<i class=\'feather-package text-primary me-2\'></i>' . __('inventory.add_new_uom')" centered="true" :showFooter="false">
        <form id="quickAddUnitForm" method="POST" action="{{ route('uoms.quick-create') }}" novalidate>
            @csrf
            <x-ui.modal-form-ui 
                type="input" 
                :label="__('inventory.unit_name')" 
                name="name" 
                id="quick_uom_name" 
                required="true" 
                :placeholder="__('inventory.unit_name_placeholder')" 
            />

            <x-ui.modal-form-ui 
                type="input" 
                :label="__('inventory.unit_code_symbol')" 
                name="code" 
                id="quick_uom_code" 
                required="true" 
                :placeholder="__('inventory.unit_code_placeholder')" 
            />

            <div id="quickUnitAlert" class="alert alert-danger d-none fs-12 py-2 my-2"></div>

            <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">{{ __('inventory.cancel') }}</button>
                <button type="submit" id="saveQuickUnitBtn" class="btn btn-sm btn-primary">
                    <i class="feather-check me-1"></i>{{ __('inventory.save_select_unit') }}
                </button>
            </div>
        </form>
    </x-ui.modal>
@endpush
