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
            <!-- Toast Notifications -->
            @if ($errors->any())
                <div class="alert alert-danger mb-3">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- Zoho / Odoo Style Flat Form Sheet -->
            <div class="card border-0 shadow-sm p-4 p-md-5 bg-white">
                <form action="{{ route('inventory.products.store') }}" method="POST" id="productForm" class="odoo-sheet">
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
                    <div class="custom-radio-group mb-4">
                        <span class="custom-radio-label">{{ __('inventory.supplier_method') }} <span class="text-danger">*</span></span>
                        <x-ui.radio name="supplier_method" value="buy" :label="__('inventory.buy')" :checked="old('supplier_method', 'buy') === 'buy'" />
                        <x-ui.radio name="supplier_method" value="manufacture" :label="__('inventory.manufacture')" :checked="old('supplier_method') === 'manufacture'" />
                    </div>

                    <div class="row g-4 mb-4 fs-13 text-dark">
                        <!-- Left Column: Primary details -->
                        <div class="col-lg-6 border-end">
                            <h6 class="fw-bold text-primary mb-3"><i class="feather-info me-2"></i>{{ __('inventory.primary_details') }}</h6>
                            
                            <x-ui.odoo-form-ui type="input" :label="__('inventory.item_name')" name="name" required="true" placeholder="Enter Product/Service Name" />

                            <div class="single-item-only">
                                <x-ui.odoo-form-ui type="input" :label="__('inventory.sku')" name="sku" required="true" placeholder="Enter Unique SKU Code" />
                            </div>

                            <x-ui.odoo-form-ui type="select" :label="__('inventory.unit')" name="uom_id" required="true">
                                <option value="" selected disabled>{{ __('inventory.select_unit') }}</option>
                                @foreach($uoms as $uom)
                                    <option value="{{ $uom->id }}" data-uom-category="{{ strtolower($uom->category ?? 'goods') }}">{{ $uom->name }} ({{ $uom->code }})</option>
                                @endforeach
                            </x-ui.odoo-form-ui>

                            <x-ui.odoo-form-ui type="select" :label="__('inventory.material_type')" name="type" required="true">
                                <option value="finished_good" {{ old('type') === 'finished_good' ? 'selected' : '' }}>{{ __('inventory.finished_good_std') }}</option>
                                <option value="semi_finished" {{ old('type') === 'semi_finished' ? 'selected' : '' }}>{{ __('inventory.semi_finished_comp') }}</option>
                                <option value="raw_material" {{ old('type') === 'raw_material' ? 'selected' : '' }}>{{ __('inventory.raw_material_purch') }}</option>
                                <option value="component" {{ old('type', 'component') === 'component' ? 'selected' : '' }}>{{ __('inventory.component_spare') }}</option>
                                <option value="service" {{ old('type') === 'service' ? 'selected' : '' }} style="display:none;">{{ __('inventory.service') }}</option>
                            </x-ui.odoo-form-ui>

                            <div class="physical-goods-only">
                                <x-ui.odoo-form-ui type="input" :label="__('inventory.brand')" name="brand" placeholder="e.g. Apple, Nike" />
                                
                                <x-ui.odoo-form-ui type="input" :label="__('inventory.manufacturer')" name="manufacturer" placeholder="Manufacturer Name" />
                                
                                <x-ui.odoo-form-ui type="input" :label="__('inventory.mpn')" name="mpn" placeholder="Manufacturer Part Number" />

                                <div class="border-top pt-3 mt-3">
                                    <h6 class="fw-bold text-primary mb-3"><i class="feather-hash me-2"></i>{{ __('inventory.identifiers') }}</h6>
                                    <x-ui.odoo-form-ui type="input" :label="__('inventory.barcode')" name="barcode" placeholder="Barcode (EAN/UPC)" />
                                    <x-ui.odoo-form-ui type="input" :label="__('inventory.upc')" name="upc" placeholder="Universal Product Code" />
                                    <x-ui.odoo-form-ui type="input" :label="__('inventory.ean')" name="ean" placeholder="European Article Number" />
                                    <x-ui.odoo-form-ui type="input" :label="__('inventory.isbn')" name="isbn" placeholder="International Standard Book Number" />
                                </div>
                            </div>
                        </div>

                        <!-- Right Column: Sales & Purchase Accounts -->
                        <div class="col-lg-6">
                            <h6 class="fw-bold text-primary mb-3"><i class="feather-dollar-sign me-2"></i>{{ __('inventory.sales_purchase_info') }}</h6>

                            <x-ui.odoo-form-ui type="input" :label="__('inventory.selling_price')" name="selling_price" inputType="number" step="0.01" placeholder="Selling Price (₹)" required="true" />

                            <x-ui.odoo-form-ui type="select" :label="__('inventory.sales_account')" name="sales_account" required="true">
                                <option value="" selected disabled>Select Sales Account</option>
                                @forelse($salesAccounts as $acc)
                                    <option value="{{ $acc->name }}">{{ $acc->code ? $acc->code . ' - ' : '' }}{{ $acc->name }}</option>
                                @empty
                                    <option value="Sales Income">Sales Income Account</option>
                                    <option value="General Income">General Income Account</option>
                                    <option value="Interest Income">Interest Income Account</option>
                                @endforelse
                            </x-ui.odoo-form-ui>

                            <x-ui.odoo-form-ui type="input" :label="__('inventory.cost_price')" name="cost_price" inputType="number" step="0.01" placeholder="Purchase Cost (₹)" required="true" />

                            <x-ui.odoo-form-ui type="select" :label="__('inventory.purchase_account')" name="purchase_account" required="true">
                                <option value="" selected disabled>Select Purchase Account</option>
                                @forelse($purchaseAccounts as $acc)
                                    <option value="{{ $acc->name }}">{{ $acc->code ? $acc->code . ' - ' : '' }}{{ $acc->name }}</option>
                                @empty
                                    <option value="Cost of Goods Sold">Cost of Goods Sold (COGS)</option>
                                    <option value="Purchases">Purchases Expense Account</option>
                                    <option value="Job Costs">Job Costs Expense Account</option>
                                @endforelse
                            </x-ui.odoo-form-ui>

                            <div class="border-top pt-3 mt-3">
                                <h6 class="fw-bold text-primary mb-3"><i class="feather-percent me-2"></i>{{ __('inventory.taxation_preferred_vendor') }}</h6>
                                <x-ui.odoo-form-ui type="input" :label="__('inventory.hsn_sac_code')" name="hsn_sac" placeholder="e.g. 8471 (HSN) or 9983 (SAC)" />

                                <x-ui.odoo-form-ui type="select" :label="__('inventory.gst_rate')" name="gst_rate">
                                    <option value="0">GST @ 0% (Exempt)</option>
                                    <option value="5">GST @ 5%</option>
                                    <option value="12">GST @ 12%</option>
                                    <option value="18" selected>GST @ 18%</option>
                                    <option value="28">GST @ 28%</option>
                                </x-ui.odoo-form-ui>

                                <x-ui.odoo-form-ui type="select" :label="__('inventory.preferred_vendor')" name="preferred_vendor_id" searchable="true">
                                    <option value="">{{ __('inventory.select_preferred_vendor') }}</option>
                                    @foreach($vendors as $vendor)
                                        <option value="{{ $vendor->id }}">{{ $vendor->name }}</option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </div>

                            <div class="border-top pt-3 mt-3 physical-goods-only">
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
                                    <option value="" selected disabled>Select Inventory Account</option>
                                    @forelse($inventoryAccounts as $acc)
                                        <option value="{{ $acc->name }}">{{ $acc->code ? $acc->code . ' - ' : '' }}{{ $acc->name }}</option>
                                    @empty
                                        <option value="Inventory Asset">Inventory Asset Account</option>
                                        <option value="Raw Materials Stock">Raw Materials Stock</option>
                                        <option value="Finished Goods Stock">Finished Goods Stock</option>
                                    @endforelse
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
                } else {
                    $uomSelect.val('');
                }

                if ($uomSelect.data('select2')) {
                    $uomSelect.trigger('change');
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
                    $('select[name="preferred_vendor_id"]').closest('.odoo-form-group').show();
                    $('select[name="type"]').closest('.odoo-form-group').show();
                    $('.variant-stock-col').show();

                    if (variationType === 'Single') {
                        $('#inventorySection').show();
                        $('#warehouseStocksSection').show();
                        $('select[name="inventory_account"]').prop('required', true);
                    } else {
                        $('#inventorySection').hide();
                        $('#warehouseStocksSection').hide();
                        $('select[name="inventory_account"]').prop('required', false);
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

            // Execute on initial page load
            toggleSections();
        });
    </script>
@endpush
