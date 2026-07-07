@extends('layouts.duralux')

@section('title', 'Edit Inventory Item | SaaS ERP')
@section('page-title', 'Edit Item')
@section('breadcrumb', 'Inventory / Items / Edit')

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
    </style>
@endpush

@section('page-actions')
    <a href="{{ route('inventory.products.index') }}" class="btn btn-light">
        <i class="feather-arrow-left me-2"></i>Back
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
                        <h3 class="fw-bold text-dark mb-0">Edit Item: {{ $product->name }}</h3>
                        <div class="d-flex gap-2">
                            <a href="{{ route('inventory.products.index') }}" class="btn btn-sm btn-light border">Cancel</a>
                            <button type="submit" class="btn btn-sm btn-primary">Update Product</button>
                        </div>
                    </div>

                    <!-- Radio Type Selector in Zoho style (Readonly in edit mode to preserve references) -->
                    <div class="custom-radio-group mb-3">
                        <span class="custom-radio-label">Item Type</span>
                        <span class="fw-semibold text-dark">{{ $product->item_type }}</span>
                        <input type="hidden" name="item_type" value="{{ $product->item_type }}">
                    </div>

                    <!-- Variation type (Readonly) -->
                    <div class="custom-radio-group mb-4">
                        <span class="custom-radio-label">Variation</span>
                        <span class="fw-semibold text-dark">{{ $product->variation_type }}</span>
                        <input type="hidden" name="variation_type" value="{{ $product->variation_type }}">
                    </div>

                    <div class="row g-4 mb-4 fs-13 text-dark">
                        <!-- Left Column: Primary details -->
                        <div class="col-lg-6 border-end">
                            <h6 class="fw-bold text-primary mb-3"><i class="feather-info me-2"></i>Primary Details</h6>
                            
                            <x-ui.odoo-form-ui type="input" label="Item Name" name="name" value="{{ $product->name }}" required="true" placeholder="Enter Product/Service Name" />

                            @if($product->variation_type === 'Single')
                                <x-ui.odoo-form-ui type="input" label="SKU" name="sku" value="{{ $product->sku }}" required="true" placeholder="Enter Unique SKU Code" />
                            @else
                                <div class="odoo-form-group">
                                    <label class="odoo-form-label">SKU</label>
                                    <span class="fw-semibold text-dark">{{ $product->sku }}</span>
                                    <input type="hidden" name="sku" value="{{ $product->sku }}">
                                </div>
                            @endif

                            <x-ui.odoo-form-ui type="select" label="Unit" name="uom_id" required="true">
                                @foreach($uoms as $uom)
                                    <option value="{{ $uom->id }}" {{ $product->uom_id == $uom->id ? 'selected' : '' }}>
                                        {{ $uom->name }} ({{ $uom->code }})
                                    </option>
                                @endforeach
                            </x-ui.odoo-form-ui>

                            @if($product->item_type === 'Goods')
                                <x-ui.odoo-form-ui type="select" label="Material Type" name="type" required="true">
                                    <option value="finished_good" {{ old('type', $product->type) === 'finished_good' ? 'selected' : '' }}>Finished Good (Standard Sales/Assembly)</option>
                                    <option value="semi_finished" {{ old('type', $product->type) === 'semi_finished' ? 'selected' : '' }}>Semi-Finished Good (Assembly Components)</option>
                                    <option value="raw_material" {{ old('type', $product->type) === 'raw_material' ? 'selected' : '' }}>Raw Material (Purchase Only)</option>
                                    <option value="component" {{ old('type', $product->type) === 'component' ? 'selected' : '' }}>Component (Spare / Standard Part)</option>
                                </x-ui.odoo-form-ui>
                            @else
                                <input type="hidden" name="type" value="service">
                            @endif

                            <x-ui.odoo-form-ui type="select" label="Status" name="status" required="true">
                                <option value="active" {{ $product->status === 'active' ? 'selected' : '' }}>Active</option>
                                <option value="inactive" {{ $product->status === 'inactive' ? 'selected' : '' }}>Inactive</option>
                            </x-ui.odoo-form-ui>

                            <x-ui.odoo-form-ui type="input" label="Brand" name="brand" value="{{ $product->brand }}" placeholder="e.g. Apple, Nike" />
                            
                            <x-ui.odoo-form-ui type="input" label="Manufacturer" name="manufacturer" value="{{ $product->manufacturer }}" placeholder="Manufacturer Name" />
                            
                            <x-ui.odoo-form-ui type="input" label="MPN" name="mpn" value="{{ $product->mpn }}" placeholder="Manufacturer Part Number" />

                            <div class="border-top pt-3 mt-3">
                                <h6 class="fw-bold text-primary mb-3"><i class="feather-hash me-2"></i>Identifiers</h6>
                                <x-ui.odoo-form-ui type="input" label="Barcode" name="barcode" value="{{ $product->barcode }}" placeholder="Barcode (EAN/UPC)" />
                                <x-ui.odoo-form-ui type="input" label="UPC" name="upc" value="{{ $product->upc }}" placeholder="Universal Product Code" />
                                <x-ui.odoo-form-ui type="input" label="EAN" name="ean" value="{{ $product->ean }}" placeholder="European Article Number" />
                                <x-ui.odoo-form-ui type="input" label="ISBN" name="isbn" value="{{ $product->isbn }}" placeholder="International Standard Book Number" />
                            </div>
                        </div>

                        <!-- Right Column: Sales & Purchase Accounts -->
                        <div class="col-lg-6">
                            <h6 class="fw-bold text-primary mb-3"><i class="feather-dollar-sign me-2"></i>Sales & Purchase Information</h6>

                            <x-ui.odoo-form-ui type="input" label="Selling Price" name="selling_price" value="{{ $product->selling_price }}" inputType="number" step="0.01" placeholder="Selling Price (₹)" required="true" />

                            <x-ui.odoo-form-ui type="select" label="Sales Account" name="sales_account" required="true">
                                <option value="Sales Income" {{ $product->sales_account === 'Sales Income' ? 'selected' : '' }}>Sales Income Account</option>
                                <option value="General Income" {{ $product->sales_account === 'General Income' ? 'selected' : '' }}>General Income Account</option>
                                <option value="Interest Income" {{ $product->sales_account === 'Interest Income' ? 'selected' : '' }}>Interest Income Account</option>
                            </x-ui.odoo-form-ui>

                            <x-ui.odoo-form-ui type="input" label="Cost Price" name="cost_price" value="{{ $product->cost_price }}" inputType="number" step="0.01" placeholder="Purchase Cost (₹)" required="true" />

                            <x-ui.odoo-form-ui type="select" label="Purchase Account" name="purchase_account" required="true">
                                <option value="Cost of Goods Sold" {{ $product->purchase_account === 'Cost of Goods Sold' ? 'selected' : '' }}>Cost of Goods Sold (COGS)</option>
                                <option value="Purchases" {{ $product->purchase_account === 'Purchases' ? 'selected' : '' }}>Purchases Expense Account</option>
                                <option value="Job Costs" {{ $product->purchase_account === 'Job Costs' ? 'selected' : '' }}>Job Costs Expense Account</option>
                            </x-ui.odoo-form-ui>

                            <div class="border-top pt-3 mt-3">
                                <h6 class="fw-bold text-primary mb-3"><i class="feather-percent me-2"></i>Taxation & Preferred Vendor</h6>
                                <x-ui.odoo-form-ui type="input" label="HSN/SAC Code" name="hsn_sac" value="{{ $product->hsn_sac }}" placeholder="e.g. 8471 (HSN) or 9983 (SAC)" />

                                <x-ui.odoo-form-ui type="select" label="GST Rate" name="gst_rate">
                                    <option value="0" {{ $product->gst_rate == 0 ? 'selected' : '' }}>GST @ 0% (Exempt)</option>
                                    <option value="5" {{ $product->gst_rate == 5 ? 'selected' : '' }}>GST @ 5%</option>
                                    <option value="12" {{ $product->gst_rate == 12 ? 'selected' : '' }}>GST @ 12%</option>
                                    <option value="18" {{ $product->gst_rate == 18 ? 'selected' : '' }}>GST @ 18%</option>
                                    <option value="28" {{ $product->gst_rate == 28 ? 'selected' : '' }}>GST @ 28%</option>
                                </x-ui.odoo-form-ui>

                                <x-ui.odoo-form-ui type="select" label="Preferred Vendor" name="preferred_vendor_id" searchable="true">
                                    <option value="">Select Preferred Supplier...</option>
                                    @foreach($vendors as $vendor)
                                        <option value="{{ $vendor->id }}" {{ $product->preferred_vendor_id == $vendor->id ? 'selected' : '' }}>
                                            {{ $vendor->name }}
                                        </option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </div>

                            <div class="border-top pt-3 mt-3">
                                <h6 class="fw-bold text-primary mb-3"><i class="feather-maximize me-2"></i>Dimensions & Weight</h6>
                                <div class="odoo-form-group">
                                    <label class="odoo-form-label">Dimensions</label>
                                    <div class="d-flex gap-2 flex-grow-1">
                                        <input type="number" step="0.01" name="length" value="{{ $product->length }}" placeholder="Length" class="odoo-form-control text-center" style="width: 25%;">
                                        <input type="number" step="0.01" name="width" value="{{ $product->width }}" placeholder="Width" class="odoo-form-control text-center" style="width: 25%;">
                                        <input type="number" step="0.01" name="height" value="{{ $product->height }}" placeholder="Height" class="odoo-form-control text-center" style="width: 25%;">
                                        <select name="dimension_unit" class="form-select form-select-sm" style="border-radius: 0; border: none; border-bottom: 1px solid #ced4da; width: 25%;">
                                            <option value="cm" {{ $product->dimension_unit === 'cm' ? 'selected' : '' }}>cm</option>
                                            <option value="in" {{ $product->dimension_unit === 'in' ? 'selected' : '' }}>in</option>
                                            <option value="mm" {{ $product->dimension_unit === 'mm' ? 'selected' : '' }}>mm</option>
                                            <option value="m" {{ $product->dimension_unit === 'm' ? 'selected' : '' }}>m</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="odoo-form-group mt-2">
                                    <label class="odoo-form-label">Weight</label>
                                    <div class="d-flex gap-2 flex-grow-1">
                                        <input type="number" step="0.01" name="weight" value="{{ $product->weight }}" placeholder="Weight" class="odoo-form-control" style="width: 70%;">
                                        <select name="weight_unit" class="form-select form-select-sm" style="border-radius: 0; border: none; border-bottom: 1px solid #ced4da; width: 30%;">
                                            <option value="kg" {{ $product->weight_unit === 'kg' ? 'selected' : '' }}>kg</option>
                                            <option value="g" {{ $product->weight_unit === 'g' ? 'selected' : '' }}>g</option>
                                            <option value="lb" {{ $product->weight_unit === 'lb' ? 'selected' : '' }}>lb</option>
                                            <option value="oz" {{ $product->weight_unit === 'oz' ? 'selected' : '' }}>oz</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Inventory tracking section (Only relevant for Goods) -->
                    @if($product->item_type === 'Goods')
                        <div id="inventorySection" class="border-top pt-4 mt-4">
                            <h6 class="fw-bold text-primary mb-3"><i class="feather-box me-2"></i>Inventory Tracking & Settings</h6>
                            
                            <div class="row g-4 fs-13 text-dark">
                                <div class="col-lg-6 border-end">
                                    <x-ui.odoo-form-ui type="select" label="Inventory Account" name="inventory_account" required="true">
                                        <option value="Inventory Asset" {{ $product->inventory_account === 'Inventory Asset' ? 'selected' : '' }}>Inventory Asset Account</option>
                                        <option value="Raw Materials Stock" {{ $product->inventory_account === 'Raw Materials Stock' ? 'selected' : '' }}>Raw Materials Stock</option>
                                        <option value="Finished Goods Stock" {{ $product->inventory_account === 'Finished Goods Stock' ? 'selected' : '' }}>Finished Goods Stock</option>
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

                        <!-- Opening Stock by Warehouse -->
                        @if($product->variation_type === 'Single')
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
                        @endif
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
@endpush
