@extends('layouts.duralux')

@section('title', __('inventory.edit_inventory_item') . ' | SaaS ERP')
@section('page-title', __('inventory.edit_item'))
@section('breadcrumb', __('inventory.inventory_items_edit'))

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
                <form action="{{ route('inventory.products.update', $product) }}" method="POST" id="productForm" class="odoo-sheet">
                    @csrf
                    @method('PUT')

                    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">
                        <h3 class="fw-bold text-dark mb-0">{{ __('inventory.edit_item_title', ['name' => $product->name]) }}</h3>
                        <div class="d-flex gap-2">
                            <a href="{{ route('inventory.products.index') }}" class="btn btn-sm btn-light border">{{ __('inventory.cancel') }}</a>
                            <button type="submit" class="btn btn-sm btn-primary">{{ __('inventory.update_product') }}</button>
                        </div>
                    </div>

                    <!-- Radio Type Selector in Zoho style (Readonly in edit mode to preserve references) -->
                    <div class="custom-radio-group mb-3">
                        <span class="custom-radio-label">{{ __('inventory.item_type') }}</span>
                        <span class="fw-semibold text-dark">{{ $product->item_type }}</span>
                        <input type="hidden" name="item_type" value="{{ $product->item_type }}">
                    </div>

                    <!-- Variation type (Readonly) -->
                    <div class="custom-radio-group mb-4">
                        <span class="custom-radio-label">{{ __('inventory.variation') }}</span>
                        <span class="fw-semibold text-dark">{{ $product->variation_type }}</span>
                        <input type="hidden" name="variation_type" value="{{ $product->variation_type }}">
                    </div>

                    <!-- Supplier Method Selector -->
                    <div class="custom-radio-group mb-4">
                        <span class="custom-radio-label">{{ __('inventory.supplier_method') }} <span class="text-danger">*</span></span>
                        <x-ui.radio name="supplier_method" value="buy" :label="__('inventory.buy')" :checked="$product->supplier_method === 'buy' || is_null($product->supplier_method)" />
                        <x-ui.radio name="supplier_method" value="manufacture" :label="__('inventory.manufacture')" :checked="$product->supplier_method === 'manufacture'" />
                    </div>

                    <div class="row g-4 mb-4 fs-13 text-dark">
                        <!-- Left Column: Primary details -->
                        <div class="col-lg-6 border-end">
                            <h6 class="fw-bold text-primary mb-3"><i class="feather-info me-2"></i>{{ __('inventory.primary_details') }}</h6>
                            
                            <x-ui.odoo-form-ui type="input" :label="__('inventory.item_name')" name="name" value="{{ $product->name }}" required="true" placeholder="Enter Product/Service Name" />

                            @if($product->variation_type === 'Single')
                                <x-ui.odoo-form-ui type="input" :label="__('inventory.sku')" name="sku" value="{{ $product->sku }}" required="true" placeholder="Enter Unique SKU Code" />
                            @else
                                <div class="odoo-form-group">
                                    <label class="odoo-form-label">{{ __('inventory.sku') }}</label>
                                    <span class="fw-semibold text-dark">{{ $product->sku }}</span>
                                    <input type="hidden" name="sku" value="{{ $product->sku }}">
                                </div>
                            @endif

                            <x-ui.odoo-form-ui type="select" :label="__('inventory.unit')" name="uom_id" required="true">
                                <option value="">{{ __('inventory.select_unit') }}</option>
                                @foreach($uoms as $uom)
                                    <option value="{{ $uom->id }}" data-uom-category="{{ strtolower($uom->category ?? 'goods') }}" {{ $product->uom_id == $uom->id ? 'selected' : '' }}>
                                        {{ $uom->name }} ({{ $uom->code }})
                                    </option>
                                @endforeach
                            </x-ui.odoo-form-ui>

                            <x-ui.odoo-form-ui type="select" :label="__('inventory.material_type')" name="type" required="true">
                                <option value="finished_good" {{ $product->type === 'finished_good' ? 'selected' : '' }}>Finished Good</option>
                                <option value="semi_finished" {{ $product->type === 'semi_finished' ? 'selected' : '' }}>Semi Finished</option>
                                <option value="raw_material" {{ $product->type === 'raw_material' ? 'selected' : '' }}>Raw Material</option>
                                <option value="component" {{ $product->type === 'component' ? 'selected' : '' }}>Component</option>
                                <option value="service" {{ $product->type === 'service' ? 'selected' : '' }}>Service</option>
                            </x-ui.odoo-form-ui>

                            <x-ui.odoo-form-ui type="select" :label="__('inventory.status')" name="status" required="true">
                                <option value="active" {{ $product->status === 'active' ? 'selected' : '' }}>Active</option>
                                <option value="inactive" {{ $product->status === 'inactive' ? 'selected' : '' }}>Inactive</option>
                            </x-ui.odoo-form-ui>

                            <div class="physical-goods-only">
                                <x-ui.odoo-form-ui type="input" :label="__('inventory.brand')" name="brand" value="{{ $product->brand }}" placeholder="e.g. Apple, Nike" />
                                
                                <x-ui.odoo-form-ui type="input" :label="__('inventory.manufacturer')" name="manufacturer" value="{{ $product->manufacturer }}" placeholder="Manufacturer Name" />
                                
                                <x-ui.odoo-form-ui type="input" :label="__('inventory.mpn')" name="mpn" value="{{ $product->mpn }}" placeholder="Manufacturer Part Number" />

                                <div class="border-top pt-3 mt-3">
                                    <h6 class="fw-bold text-primary mb-3"><i class="feather-hash me-2"></i>{{ __('inventory.identifiers') }}</h6>
                                    <x-ui.odoo-form-ui type="input" :label="__('inventory.barcode')" name="barcode" value="{{ $product->barcode }}" placeholder="Barcode (EAN/UPC)" />
                                    <x-ui.odoo-form-ui type="input" :label="__('inventory.upc')" name="upc" value="{{ $product->upc }}" placeholder="Universal Product Code" />
                                    <x-ui.odoo-form-ui type="input" :label="__('inventory.ean')" name="ean" value="{{ $product->ean }}" placeholder="European Article Number" />
                                    <x-ui.odoo-form-ui type="input" :label="__('inventory.isbn')" name="isbn" value="{{ $product->isbn }}" placeholder="International Standard Book Number" />
                                </div>
                            </div>
                        </div>

                        <!-- Right Column: Sales & Purchase Accounts -->
                        <div class="col-lg-6">
                            <h6 class="fw-bold text-primary mb-3"><i class="feather-dollar-sign me-2"></i>{{ __('inventory.sales_purchase_info') }}</h6>

                            <x-ui.odoo-form-ui type="input" :label="__('inventory.selling_price')" name="selling_price" value="{{ $product->selling_price }}" inputType="number" step="0.01" placeholder="Selling Price (₹)" required="true" />

                            <x-ui.odoo-form-ui type="select" :label="__('inventory.sales_account')" name="sales_account" required="true">
                                <option value="" disabled {{ empty($product->sales_account) ? 'selected' : '' }}>Select Sales Account</option>
                                @forelse($salesAccounts as $acc)
                                    <option value="{{ $acc->name }}" {{ $product->sales_account === $acc->name ? 'selected' : '' }}>
                                        {{ $acc->code ? $acc->code . ' - ' : '' }}{{ $acc->name }}
                                    </option>
                                @empty
                                    <option value="Sales Income" {{ $product->sales_account === 'Sales Income' ? 'selected' : '' }}>Sales Income Account</option>
                                    <option value="General Income" {{ $product->sales_account === 'General Income' ? 'selected' : '' }}>General Income Account</option>
                                    <option value="Interest Income" {{ $product->sales_account === 'Interest Income' ? 'selected' : '' }}>Interest Income Account</option>
                                @endforelse
                            </x-ui.odoo-form-ui>

                            <x-ui.odoo-form-ui type="input" :label="__('inventory.cost_price')" name="cost_price" value="{{ $product->cost_price }}" inputType="number" step="0.01" placeholder="Purchase Cost (₹)" required="true" />

                            <x-ui.odoo-form-ui type="select" :label="__('inventory.purchase_account')" name="purchase_account" required="true">
                                <option value="" disabled {{ empty($product->purchase_account) ? 'selected' : '' }}>Select Purchase Account</option>
                                @forelse($purchaseAccounts as $acc)
                                    <option value="{{ $acc->name }}" {{ $product->purchase_account === $acc->name ? 'selected' : '' }}>
                                        {{ $acc->code ? $acc->code . ' - ' : '' }}{{ $acc->name }}
                                    </option>
                                @empty
                                    <option value="Cost of Goods Sold" {{ $product->purchase_account === 'Cost of Goods Sold' ? 'selected' : '' }}>Cost of Goods Sold (COGS)</option>
                                    <option value="Purchases" {{ $product->purchase_account === 'Purchases' ? 'selected' : '' }}>Purchases Expense Account</option>
                                    <option value="Job Costs" {{ $product->purchase_account === 'Job Costs' ? 'selected' : '' }}>Job Costs Expense Account</option>
                                @endforelse
                            </x-ui.odoo-form-ui>

                            <div class="border-top pt-3 mt-3">
                                <h6 class="fw-bold text-primary mb-3"><i class="feather-percent me-2"></i>{{ __('inventory.taxation_preferred_vendor') }}</h6>
                                <x-ui.odoo-form-ui type="input" :label="__('inventory.hsn_sac_code')" name="hsn_sac" value="{{ $product->hsn_sac }}" placeholder="e.g. 8471 (HSN) or 9983 (SAC)" />

                                <x-ui.odoo-form-ui type="select" :label="__('inventory.gst_rate')" name="gst_rate">
                                    <option value="0" {{ $product->gst_rate == 0 ? 'selected' : '' }}>GST @ 0% (Exempt)</option>
                                    <option value="5" {{ $product->gst_rate == 5 ? 'selected' : '' }}>GST @ 5%</option>
                                    <option value="12" {{ $product->gst_rate == 12 ? 'selected' : '' }}>GST @ 12%</option>
                                    <option value="18" {{ $product->gst_rate == 18 ? 'selected' : '' }}>GST @ 18%</option>
                                    <option value="28" {{ $product->gst_rate == 28 ? 'selected' : '' }}>GST @ 28%</option>
                                </x-ui.odoo-form-ui>

                                <x-ui.odoo-form-ui type="select" :label="__('inventory.preferred_vendor')" name="preferred_vendor_id" searchable="true">
                                    <option value="">{{ __('inventory.select_preferred_vendor') }}</option>
                                    @foreach($vendors as $vendor)
                                        <option value="{{ $vendor->id }}" {{ $product->preferred_vendor_id == $vendor->id ? 'selected' : '' }}>{{ $vendor->name }}</option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </div>

                            <div class="border-top pt-3 mt-3 physical-goods-only">
                                <h6 class="fw-bold text-primary mb-3"><i class="feather-maximize me-2"></i>Dimensions & Weight</h6>
                                <div class="odoo-form-group">
                                    <label class="odoo-form-label">Dimensions</label>
                                    <div class="d-flex gap-2 flex-grow-1">
                                        <input type="number" step="0.01" name="length" value="{{ $product->length }}" placeholder="Length" class="odoo-form-control text-center" style="width: 25%;">
                                        <input type="number" step="0.01" name="width" value="{{ $product->width }}" placeholder="Width" class="odoo-form-control text-center" style="width: 25%;">
                                        <input type="number" step="0.01" name="height" value="{{ $product->height }}" placeholder="Height" class="odoo-form-control text-center" style="width: 25%;">
                                        <select name="dimension_unit" class="form-select form-select-sm" style="border-radius: 0; border: none; border-bottom: 1px solid #ced4da; width: 25%;">
                                            <option value="cm" {{ ($product->dimension_unit ?? 'cm') === 'cm' ? 'selected' : '' }}>cm</option>
                                            <option value="in" {{ ($product->dimension_unit ?? '') === 'in' ? 'selected' : '' }}>in</option>
                                            <option value="mm" {{ ($product->dimension_unit ?? '') === 'mm' ? 'selected' : '' }}>mm</option>
                                            <option value="m" {{ ($product->dimension_unit ?? '') === 'm' ? 'selected' : '' }}>m</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="odoo-form-group mt-2">
                                    <label class="odoo-form-label">Weight</label>
                                    <div class="d-flex gap-2 flex-grow-1">
                                        <input type="number" step="0.01" name="weight" value="{{ $product->weight }}" placeholder="Weight" class="odoo-form-control" style="width: 70%;">
                                        <select name="weight_unit" class="form-select form-select-sm" style="border-radius: 0; border: none; border-bottom: 1px solid #ced4da; width: 30%;">
                                            <option value="kg" {{ ($product->weight_unit ?? 'kg') === 'kg' ? 'selected' : '' }}>kg</option>
                                            <option value="g" {{ ($product->weight_unit ?? '') === 'g' ? 'selected' : '' }}>g</option>
                                            <option value="lb" {{ ($product->weight_unit ?? '') === 'lb' ? 'selected' : '' }}>lb</option>
                                            <option value="oz" {{ ($product->weight_unit ?? '') === 'oz' ? 'selected' : '' }}>oz</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Inventory tracking section -->
                    <div id="inventorySection" class="border-top pt-4 mt-4">
                        <h6 class="fw-bold text-primary mb-3"><i class="feather-box me-2"></i>Inventory Tracking & Settings</h6>
                        
                        <div class="row g-4 fs-13 text-dark">
                            <div class="col-lg-6 border-end">
                                <x-ui.odoo-form-ui type="select" label="Inventory Account" name="inventory_account" required="true">
                                    <option value="" disabled {{ empty($product->inventory_account) ? 'selected' : '' }}>Select Inventory Account</option>
                                    @forelse($inventoryAccounts as $acc)
                                        <option value="{{ $acc->name }}" {{ ($product->inventory_account ?? '') === $acc->name ? 'selected' : '' }}>
                                            {{ $acc->code ? $acc->code . ' - ' : '' }}{{ $acc->name }}
                                        </option>
                                    @empty
                                        <option value="Inventory Asset" {{ ($product->inventory_account ?? 'Inventory Asset') === 'Inventory Asset' ? 'selected' : '' }}>Inventory Asset Account</option>
                                        <option value="Raw Materials Stock" {{ ($product->inventory_account ?? '') === 'Raw Materials Stock' ? 'selected' : '' }}>Raw Materials Stock</option>
                                        <option value="Finished Goods Stock" {{ ($product->inventory_account ?? '') === 'Finished Goods Stock' ? 'selected' : '' }}>Finished Goods Stock</option>
                                    @endforelse
                                </x-ui.odoo-form-ui>

                                <x-ui.odoo-form-ui type="input" label="Reorder Point" name="reorder_point" value="{{ $product->reorder_point }}" inputType="number" placeholder="Alert limit when stock falls below" />

                                <x-ui.odoo-form-ui type="select" label="Inventory Valuation Method" name="inventory_valuation_method" required="true">
                                    <option value="FIFO" {{ ($product->inventory_valuation_method ?? 'FIFO') === 'FIFO' ? 'selected' : '' }}>FIFO (First-In, First-Out)</option>
                                    <option value="Weighted Average" {{ ($product->inventory_valuation_method ?? '') === 'Weighted Average' ? 'selected' : '' }}>Weighted Average</option>
                                </x-ui.odoo-form-ui>
                            </div>

                            <div class="col-lg-6">
                                <div class="odoo-form-group">
                                    <label class="odoo-form-label">Advanced tracking</label>
                                    <div class="flex-grow-1">
                                        <div class="form-check form-check-inline mt-1">
                                            <input class="form-check-input" type="checkbox" name="track_serial_number" id="trackSerial" value="1" {{ $product->track_serial_number ? 'checked' : '' }}>
                                            <label class="form-check-label" for="trackSerial">Track Serial Numbers</label>
                                        </div>
                                        <div class="form-check form-check-inline mt-1 ms-3">
                                            <input class="form-check-input" type="checkbox" name="track_batch" id="trackBatch" value="1" {{ $product->track_batch ? 'checked' : '' }}>
                                            <label class="form-check-label" for="trackBatch">Track Batches</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    @if($product->variation_type === 'Single')
                        <!-- Opening Stock by Warehouse (Single Items) -->
                        <div id="warehouseStocksSection" class="border-top pt-4 mt-4">
                            <h6 class="fw-bold text-primary mb-3"><i class="feather-home me-2"></i>Update Warehouse Stock</h6>
                            <div class="table-responsive">
                                <x-ui.odoo-form-ui type="table">
                                    <thead class="table-light text-muted">
                                        <tr>
                                            <th>Warehouse Code</th>
                                            <th>Warehouse Name</th>
                                            <th>Quantity on Hand</th>
                                            <th>Unit Cost (₹)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($warehouses as $wh)
                                            @php
                                                $qty = $warehouseStocksMap[$wh->id] ?? 0;
                                                $cost = $warehouseCostsMap[$wh->id] ?? 0;
                                            @endphp
                                            <tr>
                                                <td class="fw-semibold text-dark">{{ $wh->code }}</td>
                                                <td class="text-muted">{{ $wh->name }}</td>
                                                <td>
                                                    <x-ui.odoo-form-ui type="input" inputType="number" name="warehouse_stocks[{{ $wh->id }}][quantity]" value="{{ $qty }}" placeholder="0" />
                                                </td>
                                                <td>
                                                    <x-ui.odoo-form-ui type="input" inputType="number" name="warehouse_stocks[{{ $wh->id }}][unit_cost]" value="{{ $cost }}" placeholder="0.00" step="0.01" />
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </x-ui.odoo-form-ui>
                            </div>
                        </div>
                    @else
                        <!-- Dynamic Attributes & Options Builder (Variant Master Product Edit) -->
                        <div id="variantsSection" class="border-top pt-4 mt-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="fw-bold text-primary mb-0"><i class="feather-git-branch me-2"></i>Attributes & Options Builder</h6>
                                <div class="d-flex gap-2">
                                    <a href="{{ route('inventory.products.opening-stock', $product) }}" class="btn btn-sm btn-soft-secondary">
                                        <i class="feather-box me-1"></i>Warehouse Stock
                                    </a>
                                    <button type="button" class="btn btn-sm btn-soft-primary" id="addAttributeBtn">
                                        <i class="feather-plus me-1"></i>Add Attribute
                                    </button>
                                </div>
                            </div>

                            <!-- Attributes List -->
                            <div id="attributesContainer">
                                @php
                                    $attributesConfig = $product->attributes_config ?? [];
                                    if (empty($attributesConfig)) {
                                        $attributesConfig = [['name' => 'Color', 'values' => ['Red', 'Blue']]];
                                    }
                                @endphp

                                @foreach($attributesConfig as $idx => $attr)
                                    <div class="attribute-card" data-index="{{ $idx }}">
                                        <div class="row align-items-center">
                                            <div class="col-md-3">
                                                <label class="fs-12 fw-bold text-dark mb-1">Attribute Name</label>
                                                @php $attrName = $attr['name'] ?? ''; @endphp
                                                <select name="attributes[{{ $idx }}][name]" class="form-select form-select-sm attribute-name-select" style="border-radius: 0;">
                                                    <option value="Color" {{ $attrName === 'Color' ? 'selected' : '' }}>Color</option>
                                                    <option value="Size" {{ $attrName === 'Size' ? 'selected' : '' }}>Size</option>
                                                    <option value="Material" {{ $attrName === 'Material' ? 'selected' : '' }}>Material</option>
                                                    <option value="Style" {{ $attrName === 'Style' ? 'selected' : '' }}>Style</option>
                                                    <option value="Custom" {{ !in_array($attrName, ['Color', 'Size', 'Material', 'Style']) ? 'selected' : '' }}>Custom...</option>
                                                </select>
                                                <input type="text" class="form-control form-control-sm attribute-custom-name mt-1" 
                                                       value="{{ !in_array($attrName, ['Color', 'Size', 'Material', 'Style']) ? $attrName : '' }}" 
                                                       placeholder="Custom Attribute Name" 
                                                       style="{{ !in_array($attrName, ['Color', 'Size', 'Material', 'Style']) ? 'display: block;' : 'display: none;' }} border-radius: 0;"
                                                       @if(!in_array($attrName, ['Color', 'Size', 'Material', 'Style'])) name="attributes[{{ $idx }}][name]" @endif>
                                            </div>
                                            <div class="col-md-8">
                                                <label class="fs-12 fw-bold text-dark mb-1">Options (Type option value and press Enter or Comma)</label>
                                                <div class="tag-input-container">
                                                    <span class="tags-wrapper">
                                                        @foreach($attr['values'] ?? [] as $optVal)
                                                            <span class="tag-badge" data-val="{{ $optVal }}">
                                                                {{ $optVal }} <span class="remove-tag">&times;</span>
                                                                <input type="hidden" name="attributes[{{ $idx }}][options][]" value="{{ $optVal }}">
                                                            </span>
                                                        @endforeach
                                                    </span>
                                                    <input type="text" class="tag-input" placeholder="e.g. Red, Blue, Green">
                                                </div>
                                            </div>
                                            <div class="col-md-1 text-center mt-3 mt-md-0">
                                                <button type="button" class="btn btn-sm btn-soft-danger remove-attribute-btn"><i class="feather-trash-2"></i></button>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <!-- Dynamic Matrix Spreadsheet Table -->
                            <div id="variantsMatrixContainer" class="mt-4">
                                <x-ui.table title="Configure Generated Item Variants" bordered="true" class="variants-table-container">
                                    <thead class="table-light fw-bold text-uppercase text-muted">
                                        <tr>
                                            <th>Variant Details</th>
                                            <th>SKU *</th>
                                            <th>Selling Price (₹)</th>
                                            <th>Cost Price (₹)</th>
                                            <th>Opening Stock</th>
                                            <th>Reorder Point</th>
                                        </tr>
                                    </thead>
                                    <tbody id="variantsMatrixBody">
                                        <!-- Computed Variant Rows will be injected here by Javascript -->
                                    </tbody>
                                </x-ui.table>
                            </div>
                        </div>
                    @endif

                    <!-- Additional Notes -->
                    <div class="border-top pt-4 mt-4">
                        <h6 class="fw-bold text-primary mb-3"><i class="feather-edit-3 me-2"></i>Description / Item Notes</h6>
                        <x-ui.odoo-form-ui type="textarea" label="Internal Notes" name="description" rows="3" placeholder="Enter internal specifications, item descriptions or notes...">{{ $product->description }}</x-ui.odoo-form-ui>
                    </div>

                    <!-- Action buttons footer -->
                    <div class="d-flex justify-content-end gap-2 mt-4 border-top pt-3">
                        <a href="{{ route('inventory.products.index') }}" class="btn btn-light border">Cancel</a>
                        <button type="submit" class="btn btn-primary">Update Product</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <!-- Select2 JS -->
    <script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>

    @if($product->variation_type === 'Variant')
    @php
        $existingVariantsMap = [];
        foreach($product->variants as $v) {
            $label = $v->variant_values['label'] ?? '';
            if (!$label && !empty($v->variant_values)) {
                $parts = [];
                foreach($v->variant_values as $k => $val) {
                    $parts[] = "{$k}: {$val}";
                }
                $label = implode(' | ', $parts);
            }
            $existingVariantsMap[$label ?: $v->name] = [
                'id' => $v->id,
                'sku' => $v->sku,
                'selling_price' => $v->selling_price,
                'cost_price' => $v->cost_price,
                'opening_stock' => $v->opening_stock,
                'reorder_point' => $v->reorder_point,
                'name' => $v->name
            ];
        }
    @endphp

    <script>
        $(document).ready(function() {
            let attributeIndex = {{ count($attributesConfig ?? []) }};
            const existingVariantsMap = @json($existingVariantsMap);

            // Setup custom attribute type display
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

            // Handle tag badges additions in inputs
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

            // Handle removing tags
            $(document).on('click', '.remove-tag', function() {
                $(this).closest('.tag-badge').remove();
                generateMatrix();
            });

            // Handle Add Attribute row
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

            // Remove Attribute row
            $(document).on('click', '.remove-attribute-btn', function() {
                $(this).closest('.attribute-card').remove();
                generateMatrix();
            });

            // Dynamic Combinations Matrix Generator (Cartesian Product)
            function generateMatrix() {
                const attributes = [];
                $('#attributesContainer .attribute-card').each(function() {
                    const selectVal = $(this).find('.attribute-name-select').val();
                    const name = selectVal === 'Custom' ? $(this).find('.attribute-custom-name').val().trim() : selectVal;
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

                const parentName = $('input[name="name"]').val().trim() || '{{ $product->name }}';
                const parentSku = '{{ $product->sku }}';
                const defaultSellingPrice = '{{ $product->selling_price }}';
                const defaultCostPrice = '{{ $product->cost_price }}';

                const tbody = $('#variantsMatrixBody');
                tbody.empty();

                combinations.forEach((combo, index) => {
                    // Build combination text string e.g. "Color: Red, Size: M"
                    const comboTextParts = combo.map((val, idx) => `${attributes[idx].name}: ${val}`);
                    const comboString = comboTextParts.join(', ');

                    // Check if existing variant matches
                    const existing = existingVariantsMap[comboString] || existingVariantsMap[parentName + ' (' + comboString + ')'];
                    
                    const variantId = existing ? existing.id : '';
                    const generatedSku = existing ? existing.sku : (parentSku + '-' + combo.join('-').toUpperCase().replace(/\s+/g, ''));
                    const sellingPrice = existing ? existing.selling_price : defaultSellingPrice;
                    const costPrice = existing ? existing.cost_price : defaultCostPrice;
                    const openingStock = existing ? existing.opening_stock : 0;
                    const reorderPoint = existing ? existing.reorder_point : 0;

                    const rowHtml = `
                        <tr>
                            <td class="fw-semibold text-dark">
                                <span class="badge bg-soft-primary text-primary me-2">Variant #${index + 1}</span>
                                <span>${parentName} (${comboString})</span>
                                <input type="hidden" name="variants[${index}][attributes]" value="${comboString}">
                                ${variantId ? `<input type="hidden" name="variants[${index}][id]" value="${variantId}">` : ''}
                            </td>
                            <td>
                                <input type="text" name="variants[${index}][sku]" value="${generatedSku}" class="form-control form-control-sm py-1 fw-semibold" required style="border-radius: 0; min-width: 140px;">
                            </td>
                            <td>
                                <input type="number" step="0.01" name="variants[${index}][selling_price]" value="${sellingPrice}" class="form-control form-control-sm py-1" style="border-radius: 0; min-width: 100px;">
                            </td>
                            <td>
                                <input type="number" step="0.01" name="variants[${index}][cost_price]" value="${costPrice}" class="form-control form-control-sm py-1" style="border-radius: 0; min-width: 100px;">
                            </td>
                            <td>
                                <input type="number" step="0.01" name="variants[${index}][opening_stock]" value="${openingStock}" class="form-control form-control-sm py-1" style="border-radius: 0; min-width: 80px;">
                            </td>
                            <td>
                                <input type="number" step="0.01" name="variants[${index}][reorder_point]" value="${reorderPoint}" class="form-control form-control-sm py-1" style="border-radius: 0; min-width: 80px;">
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

            // Initial trigger on load
            generateMatrix();
        });
    </script>
    @endif
    <script>
        $(document).ready(function() {
            const itemType = '{{ $product->item_type }}';

            const $uomSelect = $('select[name="uom_id"]');
            const originalUomOptions = $uomSelect.find('option').clone();

            function filterUomOptions() {
                const targetCategory = itemType === 'Service' ? 'service' : 'goods';
                const currentVal = $uomSelect.val();

                $uomSelect.empty();

                originalUomOptions.each(function() {
                    const uomCategory = $(this).attr('data-uom-category');
                    if (!uomCategory || uomCategory === targetCategory || uomCategory === 'both') {
                        $uomSelect.append($(this).clone());
                    }
                });

                if (currentVal && $uomSelect.find(`option[value="${currentVal}"]`).length) {
                    $uomSelect.val(currentVal);
                }

                if ($uomSelect.data('select2')) {
                    $uomSelect.trigger('change');
                }
            }

            filterUomOptions();

            if (itemType === 'Service') {
                $('.physical-goods-only').hide();
                $('select[name="preferred_vendor_id"]').closest('.odoo-form-group').hide();
                $('select[name="type"]').prop('required', false).closest('.odoo-form-group').hide();
                $('#inventorySection').hide();
                $('#warehouseStocksSection').hide();
                $('select[name="inventory_account"]').prop('required', false);
                $('select[name="inventory_valuation_method"]').prop('required', false);
                const hsnLabel = $('input[name="hsn_sac"]').closest('.odoo-form-group').find('.odoo-form-label');
                hsnLabel.html('SAC Code');
            }
        });
    </script>
@endpush
