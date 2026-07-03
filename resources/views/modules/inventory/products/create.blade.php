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
    <a href="#" class="btn btn-light" onclick="history.back(); return false;">
        <i class="feather-arrow-left me-2"></i>Back
    </a>
@endsection

@section('content')
    <div class="row">
        <div class="col-12">
            <!-- Zoho / Odoo Style Flat Form Sheet -->
            <div class="card border-0 shadow-sm p-4 p-md-5 bg-white">
                <form action="#" method="POST" id="productForm" class="odoo-sheet" onsubmit="event.preventDefault(); alert('Product and variants structure logged to console! (UI Only Mode)'); console.log(getFormData());">
                    @csrf

                    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">
                        <h3 class="fw-bold text-dark mb-0">New Item / Product</h3>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-light border" onclick="history.back();">Cancel</button>
                            <button type="submit" class="btn btn-sm btn-primary">Save Product</button>
                        </div>
                    </div>

                    <!-- Radio Type Selector in Zoho style -->
                    <div class="custom-radio-group mb-3">
                        <span class="custom-radio-label">Item Type <span class="text-danger">*</span></span>
                        <label class="custom-radio-option">
                            <input type="radio" name="item_type" value="Goods" checked>
                            <span>Goods (Physical Product)</span>
                        </label>
                        <label class="custom-radio-option ms-3">
                            <input type="radio" name="item_type" value="Service">
                            <span>Service (Labor / Subscription)</span>
                        </label>
                    </div>

                    <!-- Radio variation selector (Single vs Variant) -->
                    <div class="custom-radio-group mb-4" id="variationTypeWrapper">
                        <span class="custom-radio-label">Variation <span class="text-danger">*</span></span>
                        <label class="custom-radio-option">
                            <input type="radio" name="variation_type" value="Single" checked>
                            <span>Single Item</span>
                        </label>
                        <label class="custom-radio-option ms-3">
                            <input type="radio" name="variation_type" value="Variant">
                            <span>Contains Variants (e.g. Size, Color)</span>
                        </label>
                    </div>

                    <div class="row g-4 mb-4 fs-13 text-dark">
                        <!-- Left Column: Primary details -->
                        <div class="col-lg-6 border-end">
                            <h6 class="fw-bold text-primary mb-3"><i class="feather-info me-2"></i>Primary Details</h6>
                            
                            <x-ui.odoo-form-ui type="input" label="Item Name" name="name" required="true" placeholder="Enter Product/Service Name" />

                            <div class="single-item-only">
                                <x-ui.odoo-form-ui type="input" label="SKU" name="sku" required="true" placeholder="Enter Unique SKU Code" />
                            </div>

                            <x-ui.odoo-form-ui type="select" label="Unit" name="unit" required="true">
                                <option value="pcs">Pcs (Pieces)</option>
                                <option value="box">Box</option>
                                <option value="kg">Kg (Kilograms)</option>
                                <option value="ltr">Ltr (Liters)</option>
                                <option value="bndl">Bndl (Bundle)</option>
                                <option value="m">M (Meters)</option>
                                <option value="hr">Hr (Hours)</option>
                            </x-ui.odoo-form-ui>

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
                                <option value="1">Acme Supplies Ltd</option>
                                <option value="2">Apex Trade Corp</option>
                                <option value="3">Matrix Logistics</option>
                            </x-ui.odoo-form-ui>
                        </div>

                        <!-- Right Column: Sales & Purchase Accounts -->
                        <div class="col-lg-6">
                            <h6 class="fw-bold text-primary mb-3"><i class="feather-dollar-sign me-2"></i>Sales & Purchase Information</h6>

                            <x-ui.odoo-form-ui type="input" label="Selling Price" name="selling_price" inputType="number" step="0.01" placeholder="Selling Price (₹)" required="true" />

                            <x-ui.odoo-form-ui type="select" label="Sales Account" name="sales_account" required="true">
                                <option value="Sales">Sales Income Account</option>
                                <option value="General Income">General Income Account</option>
                                <option value="Interest Income">Interest Income Account</option>
                            </x-ui.odoo-form-ui>

                            <x-ui.odoo-form-ui type="input" label="Cost Price" name="cost_price" inputType="number" step="0.01" placeholder="Purchase Cost (₹)" required="true" />

                            <x-ui.odoo-form-ui type="select" label="Purchase Account" name="purchase_account" required="true">
                                <option value="Cost of Goods Sold">Cost of Goods Sold (COGS)</option>
                                <option value="Purchases">Purchases Expense Account</option>
                                <option value="Job Costs">Job Costs Expense Account</option>
                            </x-ui.odoo-form-ui>
                        </div>
                    </div>

                    <!-- Inventory tracking section (Only relevant for Goods & Single variation) -->
                    <div id="inventorySection" class="border-top pt-4 mt-4 single-item-only">
                        <h6 class="fw-bold text-primary mb-3"><i class="feather-box me-2"></i>Inventory Tracking & Opening Stock</h6>
                        
                        <div class="row g-4 fs-13 text-dark">
                            <div class="col-lg-6 border-end">
                                <x-ui.odoo-form-ui type="select" label="Inventory Account" name="inventory_account" required="true">
                                    <option value="Inventory Asset" selected>Inventory Asset Account</option>
                                    <option value="Raw Materials Stock">Raw Materials Stock</option>
                                    <option value="Finished Goods Stock">Finished Goods Stock</option>
                                </x-ui.odoo-form-ui>

                                <x-ui.odoo-form-ui type="input" label="Reorder Point" name="reorder_point" inputType="number" placeholder="Alert limit when stock falls below" />
                            </div>

                            <div class="col-lg-6">
                                <x-ui.odoo-form-ui type="input" label="Opening Stock" name="opening_stock" inputType="number" placeholder="Initial quantity on hand" />

                                <x-ui.odoo-form-ui type="input" label="Opening Stock Rate" name="opening_stock_rate" inputType="number" step="0.01" placeholder="Cost per unit for opening stock (₹)" />
                            </div>
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
                                        <select class="form-select form-select-sm attribute-name-select" style="border-radius: 0;">
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
                                                <span class="tag-badge" data-val="Red">Red <span class="remove-tag">&times;</span></span>
                                                <span class="tag-badge" data-val="Blue">Blue <span class="remove-tag">&times;</span></span>
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
                        <button type="button" class="btn btn-light border" onclick="history.back();">Cancel</button>
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
                    // Hide preferred vendor for services
                    $('select[name="preferred_vendor_id"]').closest('.odoo-form-group').slideUp();
                } else {
                    hsnLabel.html('HSN Code');
                    $('input[name="hsn_sac"]').attr('placeholder', 'e.g. 8471 (HSN)');
                    // Show preferred vendor for goods
                    $('select[name="preferred_vendor_id"]').closest('.odoo-form-group').slideDown();
                }

                if (itemType === 'Service') {
                    // Hide inventory sections for Services
                    $('#inventorySection').slideUp();
                    $('select[name="inventory_account"]').prop('required', false);
                    $('.variant-stock-col').hide();
                } else {
                    $('.variant-stock-col').show();
                    if (variationType === 'Single') {
                        $('#inventorySection').slideDown();
                        $('select[name="inventory_account"]').prop('required', true);
                    } else {
                        $('#inventorySection').slideUp();
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
                $(this).siblings('.attribute-custom-name').toggle(isCustom).prop('required', isCustom);
                generateMatrix();
            });

            // Handle tag badges additions in inputs
            $(document).on('keydown', '.tag-input', function(e) {
                if (e.key === 'Enter' || e.key === ',') {
                    e.preventDefault();
                    const val = $(this).val().trim().replace(/,/g, '');
                    if (val) {
                        const wrapper = $(this).siblings('.tags-wrapper');
                        // Prevent duplicates
                        let exists = false;
                        wrapper.find('.tag-badge').each(function() {
                            if ($(this).attr('data-val').toLowerCase() === val.toLowerCase()) {
                                exists = true;
                            }
                        });

                        if (!exists) {
                            wrapper.append(`<span class="tag-badge" data-val="${val}">${val} <span class="remove-tag">&times;</span></span>`);
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
                                <select class="form-select form-select-sm attribute-name-select" style="border-radius: 0;">
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

            // Input monitors on attributes name inputs to regenerate table
            $(document).on('input', '.attribute-custom-name', function() {
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
                    // Update matrix helper titles & values without overwriting dirty variant fields
                    $('#variantsMatrixBody tr').each(function() {
                        const skuInput = $(this).find('input[name*="[sku]"]');
                        const sellInput = $(this).find('input[name*="[selling_price]"]');
                        const costInput = $(this).find('input[name*="[cost_price]"]');
                        
                        // Only change values if they were unmodified or empty
                        const parentSku = $('input[name="sku"]').val().trim() || 'SKU';
                        const parentSell = $('input[name="selling_price"]').val().trim();
                        const parentCost = $('input[name="cost_price"]').val().trim();
                    });
                }
            });

            // Helper function to extract current form data values for debug log
            window.getFormData = function() {
                const data = {
                    item_type: $('input[name="item_type"]:checked').val(),
                    variation_type: $('input[name="variation_type"]:checked').val(),
                    name: $('input[name="name"]').val(),
                    unit: $('select[name="unit"]').val(),
                    hsn_sac: $('input[name="hsn_sac"]').val(),
                    gst_rate: $('select[name="gst_rate"]').val(),
                    selling_price: $('input[name="selling_price"]').val(),
                    cost_price: $('input[name="cost_price"]').val(),
                    sales_account: $('select[name="sales_account"]').val(),
                    purchase_account: $('select[name="purchase_account"]').val(),
                };

                if (data.variation_type === 'Single') {
                    data.sku = $('input[name="sku"]').val();
                    data.opening_stock = $('input[name="opening_stock"]').val();
                    data.opening_stock_rate = $('input[name="opening_stock_rate"]').val();
                    data.reorder_point = $('input[name="reorder_point"]').val();
                } else {
                    data.variants = [];
                    $('#variantsMatrixBody tr').each(function() {
                        data.variants.push({
                            label: $(this).find('td:first-child').text().trim().replace(/Variant #\d+\s+/, ''),
                            sku: $(this).find('input[name*="[sku]"]').val(),
                            selling_price: $(this).find('input[name*="[selling_price]"]').val(),
                            cost_price: $(this).find('input[name*="[cost_price]"]').val(),
                            opening_stock: $(this).find('input[name*="[opening_stock]"]').val(),
                            reorder_point: $(this).find('input[name*="[reorder_point]"]').val()
                        });
                    });
                }
                return data;
            };
        });
    </script>
@endpush
