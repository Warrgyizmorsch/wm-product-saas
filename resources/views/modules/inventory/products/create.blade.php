@extends('layouts.duralux')

@section('title', 'Create Inventory Item | SaaS ERP')
@section('page-title', 'Add New Item')
@section('breadcrumb', 'Inventory / Items / Create')

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
        .tag-input-container {
            border: 1px solid #ced4da;
            padding: 4px;
            background-color: #fff;
            min-height: 38px;
            border-radius: 0;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
        }
        .tag-input-container input {
            border: none;
            outline: none;
            flex-grow: 1;
            padding: 4px;
            font-size: 13px;
        }
        .variants-table-container {
            max-height: 400px;
            overflow-y: auto;
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
                <form action="{{ route('inventory.products.store') }}" method="POST" id="productForm" class="odoo-sheet">
                    @csrf

                    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">
                        <h3 class="fw-bold text-dark mb-0">New Item / Product</h3>
                        <div class="d-flex gap-2">
                            <a href="{{ route('inventory.products.index') }}" class="btn btn-sm btn-light border">Cancel</a>
                            <button type="submit" class="btn btn-sm btn-primary">Save Product</button>
                        </div>
                    </div>

                    <!-- Radio Type Selector in Zoho style -->
                    <div class="custom-radio-group mb-3">
                        <span class="custom-radio-label">Item Type <span class="text-danger">*</span></span>
                        <x-ui.radio name="item_type" value="Goods" label="Goods (Physical Product)" :checked="true" />
                        <x-ui.radio name="item_type" value="Service" label="Service (Labor / Subscription)" />
                    </div>

                    <!-- Radio variation selector (Single vs Variant) -->
                    <div class="custom-radio-group mb-4" id="variationTypeWrapper">
                        <span class="custom-radio-label">Variation <span class="text-danger">*</span></span>
                        <x-ui.radio name="variation_type" value="Single" label="Single Item" :checked="true" />
                        <x-ui.radio name="variation_type" value="Variant" label="Contains Variants (e.g. Size, Color)" />
                    </div>

                    <!-- Supplier Method Selector -->
                    <div class="custom-radio-group mb-4">
                        <span class="custom-radio-label">Supplier Method <span class="text-danger">*</span></span>
                        <x-ui.radio name="supplier_method" value="buy" label="Buy" :checked="true" />
                        <x-ui.radio name="supplier_method" value="manufacture" label="Manufacture" />
                    </div>

                    <div class="row g-4 mb-4 fs-13 text-dark">
                        <!-- Left Column: Primary details -->
                        <div class="col-lg-6 border-end">
                            <h6 class="fw-bold text-primary mb-3"><i class="feather-info me-2"></i>Primary Details</h6>
                            
                            <x-ui.odoo-form-ui type="input" label="Item Name" name="name" required="true" placeholder="Enter Product/Service Name" />

                            <div class="single-item-only">
                                <x-ui.odoo-form-ui type="input" label="SKU" name="sku" required="true" placeholder="Enter Unique SKU Code" />
                            </div>

                            <x-ui.odoo-form-ui type="select" label="Unit" name="uom_id" required="true">
                                @foreach($uoms as $uom)
                                    <option value="{{ $uom->id }}">{{ $uom->name }} ({{ $uom->code }})</option>
                                @endforeach
                            </x-ui.odoo-form-ui>

                            <x-ui.odoo-form-ui type="select" label="Material Type" name="type" required="true">
                                <option value="finished_good" {{ old('type') === 'finished_good' ? 'selected' : '' }}>Finished Good (Standard Sales/Assembly)</option>
                                <option value="semi_finished" {{ old('type') === 'semi_finished' ? 'selected' : '' }}>Semi-Finished Good (Assembly Components)</option>
                                <option value="raw_material" {{ old('type') === 'raw_material' ? 'selected' : '' }}>Raw Material (Purchase Only)</option>
                                <option value="component" {{ old('type', 'component') === 'component' ? 'selected' : '' }}>Component (Spare / Standard Part)</option>
                                <option value="service" {{ old('type') === 'service' ? 'selected' : '' }} style="display:none;">Service</option>
                            </x-ui.odoo-form-ui>

                            <x-ui.odoo-form-ui type="input" label="Brand" name="brand" placeholder="e.g. Apple, Nike" />
                            
                            <x-ui.odoo-form-ui type="input" label="Manufacturer" name="manufacturer" placeholder="Manufacturer Name" />
                            
                            <x-ui.odoo-form-ui type="input" label="MPN" name="mpn" placeholder="Manufacturer Part Number" />

                            <div class="border-top pt-3 mt-3">
                                <h6 class="fw-bold text-primary mb-3"><i class="feather-hash me-2"></i>Identifiers</h6>
                                <x-ui.odoo-form-ui type="input" label="Barcode" name="barcode" placeholder="Barcode (EAN/UPC)" />
                                <x-ui.odoo-form-ui type="input" label="UPC" name="upc" placeholder="Universal Product Code" />
                                <x-ui.odoo-form-ui type="input" label="EAN" name="ean" placeholder="European Article Number" />
                                <x-ui.odoo-form-ui type="input" label="ISBN" name="isbn" placeholder="International Standard Book Number" />
                            </div>
                        </div>

                        <!-- Right Column: Sales & Purchase Accounts -->
                        <div class="col-lg-6">
                            <h6 class="fw-bold text-primary mb-3"><i class="feather-dollar-sign me-2"></i>Sales & Purchase Information</h6>

                            <x-ui.odoo-form-ui type="input" label="Selling Price" name="selling_price" inputType="number" step="0.01" placeholder="Selling Price (₹)" required="true" />

                            <x-ui.odoo-form-ui type="select" label="Sales Account" name="sales_account" required="true">
                                <option value="Sales Income">Sales Income Account</option>
                                <option value="General Income">General Income Account</option>
                                <option value="Interest Income">Interest Income Account</option>
                            </x-ui.odoo-form-ui>

                            <x-ui.odoo-form-ui type="input" label="Cost Price" name="cost_price" inputType="number" step="0.01" placeholder="Purchase Cost (₹)" required="true" />

                            <x-ui.odoo-form-ui type="select" label="Purchase Account" name="purchase_account" required="true">
                                <option value="Cost of Goods Sold">Cost of Goods Sold (COGS)</option>
                                <option value="Purchases">Purchases Expense Account</option>
                                <option value="Job Costs">Job Costs Expense Account</option>
                            </x-ui.odoo-form-ui>

                            <div class="border-top pt-3 mt-3">
                                <h6 class="fw-bold text-primary mb-3"><i class="feather-percent me-2"></i>Taxation & Preferred Vendor</h6>
                                <x-ui.odoo-form-ui type="input" label="HSN/SAC Code" name="hsn_sac" placeholder="e.g. 8471 (HSN) or 9983 (SAC)" />

                                <x-ui.odoo-form-ui type="select" label="GST Rate" name="gst_rate">
                                    <option value="0">GST @ 0% (Exempt)</option>
                                    <option value="5">GST @ 5%</option>
                                    <option value="12">GST @ 12%</option>
                                    <option value="18" selected>GST @ 18%</option>
                                    <option value="28">GST @ 28%</option>
                                </x-ui.odoo-form-ui>

                                <x-ui.odoo-form-ui type="select" label="Preferred Vendor" name="preferred_vendor_id" searchable="true">
                                    <option value="">Select Preferred Supplier...</option>
                                    @foreach($vendors as $vendor)
                                        <option value="{{ $vendor->id }}">{{ $vendor->name }}</option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </div>

                            <div class="border-top pt-3 mt-3">
                                <h6 class="fw-bold text-primary mb-3"><i class="feather-maximize me-2"></i>Dimensions & Weight</h6>
                                <div class="odoo-form-group">
                                    <label class="odoo-form-label">Dimensions</label>
                                    <div class="d-flex gap-2 flex-grow-1">
                                        <input type="number" step="0.01" name="length" placeholder="Length" class="odoo-form-control text-center" style="width: 25%;">
                                        <input type="number" step="0.01" name="width" placeholder="Width" class="odoo-form-control text-center" style="width: 25%;">
                                        <input type="number" step="0.01" name="height" placeholder="Height" class="odoo-form-control text-center" style="width: 25%;">
                                        <select name="dimension_unit" class="form-select form-select-sm" style="border-radius: 0; border: none; border-bottom: 1px solid #ced4da; width: 25%;">
                                            <option value="cm">cm</option>
                                            <option value="in">in</option>
                                            <option value="mm">mm</option>
                                            <option value="m">m</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="odoo-form-group mt-2">
                                    <label class="odoo-form-label">Weight</label>
                                    <div class="d-flex gap-2 flex-grow-1">
                                        <input type="number" step="0.01" name="weight" placeholder="Weight" class="odoo-form-control" style="width: 70%;">
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

                    <!-- Inventory tracking section (Only relevant for Goods & Single variation) -->
                    <div id="inventorySection" class="border-top pt-4 mt-4 single-item-only">
                        <h6 class="fw-bold text-primary mb-3"><i class="feather-box me-2"></i>Inventory Tracking & Settings</h6>
                        
                        <div class="row g-4 fs-13 text-dark">
                            <div class="col-lg-6 border-end">
                                <x-ui.odoo-form-ui type="select" label="Inventory Account" name="inventory_account" required="true">
                                    <option value="Inventory Asset" selected>Inventory Asset Account</option>
                                    <option value="Raw Materials Stock">Raw Materials Stock</option>
                                    <option value="Finished Goods Stock">Finished Goods Stock</option>
                                </x-ui.odoo-form-ui>

                                <x-ui.odoo-form-ui type="input" label="Reorder Point" name="reorder_point" inputType="number" placeholder="Alert limit when stock falls below" />

                                <x-ui.odoo-form-ui type="select" label="Inventory Valuation Method" name="inventory_valuation_method" required="true">
                                    <option value="FIFO" selected>FIFO (First-In, First-Out)</option>
                                    <option value="Weighted Average">Weighted Average</option>
                                </x-ui.odoo-form-ui>
                            </div>

                            <div class="col-lg-6">
                                <div class="odoo-form-group">
                                    <label class="odoo-form-label">Advanced tracking</label>
                                    <div class="flex-grow-1">
                                        <div class="form-check form-check-inline mt-1">
                                            <input class="form-check-input" type="checkbox" name="track_serial_number" id="trackSerial" value="1">
                                            <label class="form-check-label" for="trackSerial">Track Serial Numbers</label>
                                        </div>
                                        <div class="form-check form-check-inline mt-1 ms-3">
                                            <input class="form-check-input" type="checkbox" name="track_batch" id="trackBatch" value="1">
                                            <label class="form-check-label" for="trackBatch">Track Batches</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Opening Stock by Warehouse (Single variation Goods only) -->
                    <div id="warehouseStocksSection" class="border-top pt-4 mt-4 single-item-only">
                        <h6 class="fw-bold text-primary mb-3"><i class="feather-home me-2"></i>Opening Stock by Warehouse</h6>
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
                            <h6 class="fw-bold text-primary mb-0"><i class="feather-git-branch me-2"></i>Attributes & Options Builder</h6>
                            <button type="button" class="btn btn-sm btn-soft-primary" id="addAttributeBtn">
                                <i class="feather-plus me-1"></i>Add Attribute
                            </button>
                        </div>

                        <!-- Attributes List -->
                        <div id="attributesContainer">
                            <!-- Template Row 1 (Preloaded: Color) -->
                            <div class="attribute-card" data-index="0">
                                <div class="row align-items-center">
                                    <div class="col-md-3">
                                        <label class="fs-12 fw-bold text-dark mb-1">Attribute Name</label>
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
                                        <label class="fs-12 fw-bold text-dark mb-1">Options (Type option value and press Enter or Comma)</label>
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
                                            <input type="text" class="tag-input" placeholder="e.g. Red, Blue, Green">
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

                    <!-- Additional Notes -->
                    <div class="border-top pt-4 mt-4">
                        <h6 class="fw-bold text-primary mb-3"><i class="feather-edit-3 me-2"></i>Description / Item Notes</h6>
                        <x-ui.odoo-form-ui type="textarea" label="Internal Notes" name="description" rows="3" placeholder="Enter internal specifications, item descriptions or notes..."></x-ui.odoo-form-ui>
                    </div>

                    <!-- Action buttons footer -->
                    <div class="d-flex justify-content-end gap-2 mt-4 border-top pt-3">
                        <a href="{{ route('inventory.products.index') }}" class="btn btn-light border">Cancel</a>
                        <button type="submit" class="btn btn-primary">Save Product</button>
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
        $(document).ready(function() {
            let attributeIndex = 1; // Start counter for additional attributes

            // Toggle Inventory Section based on Goods/Service
            $('input[name="item_type"]').on('change', function() {
                toggleSections();
            });

            // Toggle Variants Section based on Variation Type
            $('input[name="variation_type"]').on('change', function() {
                toggleSections();
            });

            function toggleSections() {
                const itemType = $('input[name="item_type"]:checked').val();
                const variationType = $('input[name="variation_type"]:checked').val();

                // HSN vs SAC label change dynamically
                const hsnLabel = $('input[name="hsn_sac"]').closest('.odoo-form-group').find('.odoo-form-label');
                if (itemType === 'Service') {
                    hsnLabel.html('SAC Code');
                    $('input[name="hsn_sac"]').attr('placeholder', 'e.g. 9983 (SAC)');
                    // Hide preferred vendor and dimensions for services
                    $('select[name="preferred_vendor_id"]').closest('.odoo-form-group').slideUp();
                    $('input[name="length"]').closest('.border-top').slideUp();
                    // Hide Material Type and set default to service for services
                    $('select[name="type"]').val('service').trigger('change').closest('.odoo-form-group').slideUp();
                } else {
                    hsnLabel.html('HSN Code');
                    $('input[name="hsn_sac"]').attr('placeholder', 'e.g. 8471 (HSN)');
                    // Show preferred vendor and dimensions for goods
                    $('select[name="preferred_vendor_id"]').closest('.odoo-form-group').slideDown();
                    $('input[name="length"]').closest('.border-top').slideDown();
                    // Show Material Type for goods
                    $('select[name="type"]').closest('.odoo-form-group').slideDown();
                }

                if (itemType === 'Service') {
                    // Hide inventory sections for Services
                    $('#inventorySection').slideUp();
                    $('#warehouseStocksSection').slideUp();
                    $('select[name="inventory_account"]').prop('required', false);
                    $('.variant-stock-col').hide();
                } else {
                    $('.variant-stock-col').show();
                    if (variationType === 'Single') {
                        $('#inventorySection').slideDown();
                        $('#warehouseStocksSection').slideDown();
                        $('select[name="inventory_account"]').prop('required', true);
                    } else {
                        $('#inventorySection').slideUp();
                        $('#warehouseStocksSection').slideUp();
                        $('select[name="inventory_account"]').prop('required', false);
                    }
                }

                if (variationType === 'Variant') {
                    $('.single-item-only').slideUp();
                    $('input[name="sku"]').prop('required', false);
                    $('#variantsSection').slideDown();
                    generateMatrix();
                } else {
                    $('.single-item-only').slideDown();
                    $('input[name="sku"]').prop('required', true);
                    $('#variantsSection').slideUp();
                }
            }

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
                        
                        // Prevent duplicates
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

                // Cartesian Product calculation helper
                const cartesian = (sets) => {
                    return sets.reduce((acc, set) => {
                        return acc.flatMap(x => set.map(y => [...x, y]));
                    }, [[]]);
                };

                const optionSets = attributes.map(a => a.options);
                const combinations = cartesian(optionSets);

                const parentName = $('input[name="name"]').val().trim() || 'Product';
                const parentSku = $('input[name="sku"]').val().trim() || 'SKU';
                const sellingPrice = $('input[name="selling_price"]').val().trim() || '';
                const costPrice = $('input[name="cost_price"]').val().trim() || '';
                const itemType = $('input[name="item_type"]:checked').val();

                const tbody = $('#variantsMatrixBody');
                tbody.empty();

                combinations.forEach((combo, index) => {
                    // Create human readable variant tag, e.g. "Color: Red, Size: S"
                    const detailsStr = combo.map((val, idx) => `${attributes[idx].name}: ${val}`).join(', ');
                    // Auto-generated helper SKU code, e.g. SKU-RED-S
                    const skuCodeSuffix = combo.map(val => val.toString().toUpperCase().replace(/\s+/g, '')).join('-');
                    const autoSku = parentSku ? `${parentSku}-${skuCodeSuffix}` : skuCodeSuffix;

                    let stockInputsHtml = '';
                    if (itemType !== 'Service') {
                        stockInputsHtml = `
                            <td>
                                <input type="number" name="variants[${index}][opening_stock]" class="form-control form-control-sm py-1" placeholder="0" style="border-radius: 0; min-width: 80px;">
                            </td>
                            <td>
                                <input type="number" name="variants[${index}][reorder_point]" class="form-control form-control-sm py-1" placeholder="0" style="border-radius: 0; min-width: 80px;">
                            </td>
                        `;
                    }

                    const rowHtml = `
                        <tr>
                            <td class="fw-semibold text-dark">
                                <span class="badge bg-soft-primary text-primary me-2">Variant #${index + 1}</span>
                                <span>${parentName} (${detailsStr})</span>
                                <input type="hidden" name="variants[${index}][attributes]" value="${detailsStr}">
                            </td>
                            <td>
                                <input type="text" name="variants[${index}][sku]" class="form-control form-control-sm py-1" value="${autoSku}" required style="border-radius: 0; min-width: 140px;">
                            </td>
                            <td>
                                <input type="number" name="variants[${index}][selling_price]" class="form-control form-control-sm py-1" value="${sellingPrice}" step="0.01" style="border-radius: 0; min-width: 100px;">
                            </td>
                            <td>
                                <input type="number" name="variants[${index}][cost_price]" class="form-control form-control-sm py-1" value="${costPrice}" step="0.01" style="border-radius: 0; min-width: 100px;">
                            </td>
                            ${stockInputsHtml}
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

            // Sync matrix helper fields when parent details are typed
            $('input[name="name"], input[name="sku"], input[name="selling_price"], input[name="cost_price"]').on('input', function() {
                if ($('input[name="variation_type"]:checked').val() === 'Variant') {
                    generateMatrix();
                }
            });

            // Initial toggle check
            toggleSections();
        });
    </script>
@endpush
