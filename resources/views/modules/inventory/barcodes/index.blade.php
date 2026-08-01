@extends('layouts.duralux')

@section('title', 'Barcode Label Studio | SaaS ERP')
@section('page-title', 'Barcode & Sticker Label Studio')
@section('breadcrumb', 'Inventory / Barcodes')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
    <style>
        .select2-container {
            width: 100% !important;
            max-width: 100% !important;
        }
        .barcode-preview-box {
            border: 2px dashed #cbd5e1;
            border-radius: 8px;
            padding: 24px;
            background: #f8fafc;
            text-align: center;
        }
        .barcode-sticker-mock {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            border-radius: 6px;
            padding: 12px 16px;
            display: inline-block;
            min-width: 220px;
        }
        .serial-list-box {
            max-height: 180px;
            overflow-y: auto;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 10px;
            background: #ffffff;
        }
    </style>
@endpush

@section('content')
<div class="erp-single-panel bg-white p-4">
    @if (session('error'))
        <x-ui.toast :auto="true" type="error" title="{{ session('error') }}" />
    @endif

    <form action="{{ route('inventory.barcodes.print') }}" method="POST" target="_blank" id="barcodeForm">
        @csrf
        <x-ui.odoo-form-ui type="sheet">
            <!-- Header Bar -->
            <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 flex-wrap gap-2">
                <div>
                    <h4 class="fw-bold text-dark mb-0"><i class="feather-printer text-primary me-2"></i>Barcode & Sticker Label Studio</h4>
                    <span class="fs-12 text-muted">Generate product barcodes or unit-level serial number stickers for inventory tracking.</span>
                </div>
                <div class="d-flex gap-2">
                    <x-ui.button href="{{ route('inventory.products.index') }}" variant="light" class="border">Back to Products</x-ui.button>
                    <x-ui.button type="submit" variant="primary" icon="feather-printer">Generate & Print Label Sheet</x-ui.button>
                </div>
            </div>

            <!-- Printing Mode Selector Pills -->
            <div class="card border rounded-3 p-3 bg-light mb-4">
                <label class="form-label fw-bold text-uppercase fs-11 text-muted mb-2">Barcode Generation Mode</label>
                <div class="d-flex gap-4 flex-wrap">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="print_type" id="modeProduct" value="product" checked>
                        <label class="form-check-label fw-bold text-dark fs-13" for="modeProduct">
                            <i class="feather-box me-1 text-primary"></i> Product Level Barcode (Common Product SKU)
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="print_type" id="modeSerial" value="serial">
                        <label class="form-check-label fw-bold text-dark fs-13" for="modeSerial">
                            <i class="feather-tag me-1 text-success"></i> Serial Number Barcodes (Per-Unit Unique Serial Stickers)
                        </label>
                    </div>
                </div>
            </div>

            <!-- Form Grid -->
            <div class="row g-4 mb-4">
                <div class="col-md-7">
                    <div class="card border rounded-3 p-3 bg-white mb-3 shadow-2xs">
                        <h6 class="fw-bold text-dark mb-3"><i class="feather-sliders text-primary me-2"></i>Label Specifications</h6>
                        
                        <div class="mb-3">
                            <x-ui.odoo-form-ui type="select" label="Select Product" name="product_id" id="productSelect" class="select2-select" :required="true" :error-text="$errors->first('product_id')">
                                <option value="">Select Item to print barcode for...</option>
                                @foreach($products as $prod)
                                    <option value="{{ $prod->id }}" data-sku="{{ $prod->sku }}" data-barcode="{{ $prod->barcode ?: $prod->sku }}" data-price="{{ $prod->selling_price }}">
                                        {{ $prod->name }} (SKU: {{ $prod->sku }} | Barcode: {{ $prod->barcode ?: 'Auto' }})
                                    </option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>

                        <!-- Optional Warehouse Selection -->
                        <div class="mb-3">
                            <x-ui.odoo-form-ui type="select" label="Select Warehouse (Optional)" name="warehouse_id" id="warehouseSelect" class="select2-select">
                                <option value="">-- No Warehouse / Default Warehouse --</option>
                                @foreach($warehouses ?? [] as $wh)
                                    <option value="{{ $wh->id }}">
                                        {{ $wh->name }} {{ $wh->is_default ? '(Default)' : '' }}
                                    </option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                            <span class="fs-11 text-muted"><i class="feather-info me-1"></i>Optional: If selected, the warehouse code is embedded into the barcode for automatic warehouse auto-selection upon scanning.</span>
                        </div>

                        <!-- Product Copies Mode Input -->
                        <div class="row g-3" id="copiesContainer">
                            <div class="col-md-6">
                                <x-ui.odoo-form-ui type="select" label="Sticker Format" name="label_format">
                                    <option value="thermal">Thermal Sticker (50mm x 25mm)</option>
                                    <option value="a4_24">A4 Grid (24 Labels / Page)</option>
                                    <option value="a4_40">A4 Grid (40 Labels / Page)</option>
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="col-md-6">
                                <x-ui.odoo-form-ui type="input" inputType="number" label="Number of Copies" name="copies" value="1" min="1" max="500" />
                            </div>
                        </div>

                        <!-- Serial Numbers Multi-Select Container -->
                        <div class="mt-3" id="serialContainer" style="display: none;">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-0">Select Available Serial Numbers To Print</label>
                                <button type="button" class="btn btn-xs btn-soft-primary fw-bold px-2.5 py-1" id="selectAllSerials">
                                    <i class="feather-check-square me-1"></i>Select All
                                </button>
                            </div>
                            <div class="serial-list-box" id="serialList">
                                <span class="text-muted fs-12 d-block py-2 text-center"><i class="feather-info me-1"></i>Select a product to load available serial numbers...</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Side: Live Sticker Preview -->
                <div class="col-md-5">
                    <div class="card border rounded-3 p-3 bg-white shadow-2xs h-100">
                        <h6 class="fw-bold text-dark mb-3"><i class="feather-eye text-primary me-2"></i>Live Sticker Preview</h6>
                        <div class="barcode-preview-box d-flex flex-column align-items-center justify-content-center h-100">
                            <div class="barcode-sticker-mock text-center" id="stickerMock">
                                <span class="fw-bold text-dark d-block fs-12 mb-0.5" id="previewName">SELECT PRODUCT</span>
                                <small class="text-muted fs-10 d-block mb-2" id="previewSubText">SKU: SKU-XXXX</small>
                                
                                <div class="my-2 py-1 bg-light rounded border px-3 d-inline-block">
                                    <i class="feather-bar-chart-2 fs-28 text-dark d-block"></i>
                                    <span class="fs-10 font-monospace text-dark fw-bold" id="previewCode">BAR-12345678</span>
                                </div>

                                <div class="mt-1">
                                    <span class="fs-11 fw-bold text-dark">MRP: ₹<span id="previewPrice">0.00</span></span>
                                </div>
                            </div>
                            <span class="fs-11 text-muted mt-3" id="previewFootNote"><i class="feather-info me-1"></i>Preview of single sticker label</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer Action Bar -->
            <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                <x-ui.button href="{{ route('inventory.products.index') }}" variant="light" class="border px-4">Discard</x-ui.button>
                <x-ui.button type="submit" variant="primary" icon="feather-printer" class="px-4">Generate Printable Label Sheet</x-ui.button>
            </div>
        </x-ui.odoo-form-ui>
    </form>
</div>

@push('scripts')
<script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
<script src="{{ asset('assets/vendors/js/select2-active.min.js') }}"></script>
<script>
    $(document).ready(function() {
        if (typeof $.fn.select2 === 'function') {
            $('.select2-select').select2({ theme: "bootstrap-5", width: "100%" });
        }

        // Mode Switching Toggle
        $('input[name="print_type"]').on('change', function() {
            const isSerial = $('#modeSerial').is(':checked');
            if (isSerial) {
                $('#serialContainer').slideDown();
                $('#copiesContainer').slideUp();
                loadSerialNumbers();
            } else {
                $('#serialContainer').slideUp();
                $('#copiesContainer').slideDown();
                updatePreviewFromSelect();
            }
        });

        // Product Change Listener
        $('#productSelect').on('change', function() {
            if ($('#modeSerial').is(':checked')) {
                loadSerialNumbers();
            } else {
                updatePreviewFromSelect();
            }
        });

        function updatePreviewFromSelect() {
            const opt = $('#productSelect').find('option:selected');
            const whVal = $('#warehouseSelect').val();
            if (opt.val()) {
                const textParts = opt.text().split('(');
                const name = textParts[0].trim();
                const sku = opt.data('sku') || 'SKU-101';
                let code = opt.data('barcode') || 'BAR-12345';
                if (whVal) {
                    code += '@' + whVal;
                }
                const price = opt.data('price') || '0.00';

                $('#previewName').text(name);
                $('#previewSubText').text('SKU: ' + sku);
                $('#previewCode').text(code);
                $('#previewPrice').text(parseFloat(price).toFixed(2));
            } else {
                $('#previewName').text('SELECT PRODUCT');
                $('#previewSubText').text('SKU: SKU-XXXX');
                $('#previewCode').text('BAR-12345678');
                $('#previewPrice').text('0.00');
            }
        }

        function loadSerialNumbers() {
            const prodId = $('#productSelect').val();
            const $box = $('#serialList');
            if (!prodId) {
                $box.html('<span class="text-muted fs-12 d-block py-2 text-center"><i class="feather-info me-1"></i>Please select a product first...</span>');
                return;
            }

            $box.html('<span class="text-muted fs-12 d-block py-2 text-center"><i class="feather-loader me-1"></i>Loading available serial numbers...</span>');

            fetch("{{ url('/inventory/barcodes/serials') }}/" + prodId)
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.serials && data.serials.length > 0) {
                        let html = '';
                        data.serials.forEach((sn, idx) => {
                            html += `
                                <div class="form-check form-check-sm mb-1">
                                    <input class="form-check-input serial-chk" type="checkbox" name="serial_numbers[]" value="${sn}" id="sn_${idx}" checked>
                                    <label class="form-check-label fs-12 font-monospace text-dark fw-bold" for="sn_${idx}">${sn}</label>
                                </div>
                            `;
                        });
                        $box.html(html);
                        
                        // Update preview with first serial number
                        if (data.serials[0]) {
                            const opt = $('#productSelect').find('option:selected');
                            const textParts = opt.text().split('(');
                            $('#previewName').text(textParts[0].trim());
                            $('#previewSubText').html('<span class="text-primary fw-bold">SN: ' + data.serials[0] + '</span>');
                            $('#previewCode').text(data.serials[0]);
                            $('#previewPrice').text(parseFloat(opt.data('price') || 0).toFixed(2));
                        }
                    } else {
                        $box.html('<span class="text-warning fs-12 d-block py-2 text-center"><i class="feather-alert-circle me-1"></i>No available serial numbers found for this product.</span>');
                    }
                })
                .catch(err => {
                    $box.html('<span class="text-danger fs-12 d-block py-2 text-center">Failed to load serial numbers.</span>');
                });
        }

        $('#selectAllSerials').on('click', function() {
            const chks = $('.serial-chk');
            const allChecked = chks.filter(':checked').length === chks.length;
            chks.prop('checked', !allChecked);
        });

        $(document).on('change', '.serial-chk', function() {
            const val = $(this).val();
            if ($(this).is(':checked')) {
                $('#previewSubText').html('<span class="text-primary fw-bold">SN: ' + val + '</span>');
                $('#previewCode').text(val);
            }
        });
    });
</script>
@endpush
@endsection
