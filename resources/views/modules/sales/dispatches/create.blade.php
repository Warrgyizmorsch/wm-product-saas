 @extends('layouts.duralux')

@section('title', __('crm.create_dispatch_order') . ' | SaaS ERP')
@section('page-title', __('crm.create_dispatch_order'))
@section('breadcrumb', __('crm.dispatch_orders_create_breadcrumb'))


@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
    <style>
        .select2-container {
            width: 100% !important;
            max-width: 100% !important;
        }
        #dispatchItemsTable {
            table-layout: fixed;
            width: 100% !important;
        }
        #dispatchItemsTable th {
            white-space: nowrap !important;
            padding-left: 6px !important;
            padding-right: 6px !important;
            font-size: 11px !important;
            vertical-align: middle !important;
        }
        #dispatchItemsTable td {
            vertical-align: top !important;
            padding-bottom: 12px !important;
            overflow: visible !important;
        }
        .qty-error-msg {
            white-space: normal !important;
            word-wrap: break-word !important;
            clear: both !important;
            line-height: 1.3 !important;
            display: block !important;
        }
        .serial-tracking-row td, .batch-tracking-row td {
            background-color: #f8fafc !important;
            border-bottom: 1px solid #e2e8f0 !important;
        }
        .serial-tracking-row .auto-fill-serials-btn {
            margin-right: 6px !important;
        }
        .select2-container--bootstrap-5 .select2-selection--multiple {
            min-height: 28px !important;
            padding: 2px 6px !important;
            border: 1px solid #cbd5e1 !important;
            border-radius: 4px !important;
            background-color: #ffffff !important;
        }
        .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__rendered {
            display: flex !important;
            flex-wrap: wrap !important;
            gap: 3px !important;
            padding: 0 !important;
            margin: 0 !important;
        }
        .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__choice {
            font-size: 11px !important;
            font-family: var(--bs-font-monospace) !important;
            padding: 1px 6px !important;
            margin: 1px !important;
            background-color: #eff6ff !important;
            border: 1px solid #bfdbfe !important;
            color: #1e40af !important;
            border-radius: 3px !important;
            line-height: 1.4 !important;
        }
        .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__choice__remove {
            color: #ef4444 !important;
            margin-right: 4px !important;
            border: none !important;
            background: transparent !important;
            font-size: 11px !important;
            line-height: inherit !important;
        }
        .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__clear {
            display: none !important;
        }
        .select2-container--bootstrap-5 .select2-search--inline .select2-search__field {
            font-size: 11px !important;
            margin: 1px 2px !important;
            height: 22px !important;
            line-height: 22px !important;
        }
    </style>
@endpush

@section('content')
    <div class="erp-single-panel bg-white p-4">

        <form action="{{ route('sales.dispatches.store') }}" method="POST" id="dispatchForm">
            @csrf
            <input type="hidden" name="material_requirement_id" id="deliveryOrderId" value="{{ old('material_requirement_id', $mrId ?? request('material_requirement_id') ?? request('mr_id')) }}">
            <input type="hidden" name="sales_order_id" id="salesOrderId" value="{{ old('sales_order_id', $soId ?? $prefillSalesOrder?->id ?? request('sales_order_id') ?? request('so_id')) }}">

            <x-ui.odoo-form-ui type="sheet">
                <!-- Actions Top Bar -->
                <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 flex-wrap gap-2">
                    <div>
                        <h4 class="fw-bold text-dark mb-0">{{ __('crm.new_dispatch_order') }}</h4>
                        <span class="fs-12 text-muted">{{ __('crm.issue_outward_desc') }}</span>
                    </div>
                </div>

                <!-- Header Details Section -->
                <div class="row g-4 mb-4 fs-13 text-dark">
                    <div class="col-md-6 border-end pe-md-4">
                        <h6 class="fw-bold text-primary mb-3"><i class="feather-layers me-2"></i>{{ __('crm.fulfillment_source_ref') }}</h6>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold fs-12 text-dark">{{ __('crm.so_mr_reference') }}</label>
                            <select id="orderSelectPicker" class="form-select odoo-select2 font-monospace fs-12 border-primary">
                                <option value="">{{ __('crm.choose_so_mr') }}</option>
                                @foreach($pendingDOs as $pDo)
                                    <option value="{{ $pDo->id }}" data-so-id="{{ $pDo->sales_order_id }}" @selected($mrId == $pDo->id || $soId == $pDo->sales_order_id)>
                                        {{ $pDo->salesOrder?->sales_order_number ?? 'N/A' }} ({{ $pDo->requirement_number }}) &mdash; {{ $pDo->salesOrder?->customer?->name ?? 'Customer' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <x-ui.odoo-form-ui type="input" :label="__('crm.customer_name')" id="displayCustomerNameInput" :value="$prefillSalesOrder?->customer?->name ?: '—'" readonly="true" style="font-weight: bold; background-color: #f8fafc;" />

                        <div id="invoicePickerWrapper" class="mb-3 p-3 rounded border border-primary d-none" style="background-color: #eff6ff !important;">
                            <label for="invoiceSelect" class="form-label fw-bold fs-12 text-primary mb-1">
                                <i class="feather-file-text me-1"></i>Dispatch Against Customer Invoice (Optional):
                            </label>
                            <select name="invoice_id" id="invoiceSelect" class="form-select form-select-sm font-monospace fs-12 border-primary bg-white shadow-sm fw-bold text-dark">
                                <option value="">-- Dispatch Full Order / Requirement --</option>
                            </select>
                            <span class="fs-11 text-muted d-block mt-1">Select an advance invoice to dispatch specifically against its items & remaining unfulfilled quantities.</span>
                        </div>

                        <h6 class="fw-bold text-primary mb-3 mt-4"><i class="feather-calendar me-2"></i>2. Dispatch & Logistics Details</h6>
                        <x-ui.odoo-form-ui type="input" inputType="date" label="Dispatch Date" name="dispatch_date" :value="old('dispatch_date', now()->toDateString())" :required="true" />
                        
                        <div class="mb-3">
                            <x-ui.odoo-form-ui type="select" label="Transporter Master" name="transporter_id" id="transporterSelect" :error-text="$errors->first('transporter_id')">
                                <option value="">-- Choose Transporter --</option>
                                <option value="__ADD_NEW__" class="fw-bold text-primary">+ Add New Transporter</option>
                                @foreach($transporters as $t)
                                    <option value="{{ $t->id }}" data-id="{{ $t->transporter_id }}" data-gstin="{{ $t->gstin }}" @selected(old('transporter_id') == $t->id)>
                                        {{ $t->name }} {{ $t->transporter_id ? "({$t->transporter_id})" : ($t->gstin ? "({$t->gstin})" : '') }}
                                    </option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>

                        <x-ui.odoo-form-ui type="input" label="Carrier / Courier Partner" name="carrier" id="carrierInput" :value="old('carrier')" placeholder="e.g. Blue Dart, DHL, Professional Courier" />

                        <x-ui.odoo-form-ui type="select" label="Freight Terms" name="freight_terms" id="freightTermsSelect">
                            <option value="To Pay" @selected(old('freight_terms', $prefillSalesOrder?->freight_terms ?? 'To Pay') == 'To Pay')>To Pay (Freight Collect by Driver from Customer)</option>
                            <option value="To Be Billed" @selected(old('freight_terms', $prefillSalesOrder?->freight_terms ?? '') == 'To Be Billed')>To Be Billed (Prepaid & Added to Invoice)</option>
                            <option value="Prepaid" @selected(old('freight_terms', $prefillSalesOrder?->freight_terms ?? '') == 'Prepaid')>Prepaid (Freight Included / Seller Paid)</option>
                            <option value="Customer Pickup" @selected(old('freight_terms', $prefillSalesOrder?->freight_terms ?? '') == 'Customer Pickup')>Customer Pickup (Self Vehicle)</option>
                        </x-ui.odoo-form-ui>

                        <x-ui.odoo-form-ui type="input" inputType="number" :label="__('purchase.freight_amount') . ' (' . active_currency_symbol() . ')'" name="freight_amount" id="freightAmountInput" :value="old('freight_amount', $prefillSalesOrder?->freight_amount ?? 0)" min="0" step="0.01" />
                    </div>

                    <div class="col-md-6 ps-md-4">
                        <h6 class="fw-bold text-primary mb-3"><i class="feather-truck me-2"></i>3. Vehicle, Driver & Destination Address</h6>
                        <x-ui.odoo-form-ui type="input" label="Vehicle Number" name="vehicle_number" :value="old('vehicle_number')" placeholder="e.g. MH-12-AB-1234" />
                        <x-ui.odoo-form-ui type="input" label="Driver Name" name="driver_name" :value="old('driver_name')" placeholder="Driver's full name" />
                        <x-ui.odoo-form-ui type="input" label="Driver Phone Number" name="driver_phone" :value="old('driver_phone')" placeholder="e.g. +91 98765 43210" />
                        <x-ui.odoo-form-ui type="textarea" label="Delivery / Shipping Address (Ship-To Destination)" name="shipping_address" rows="3" placeholder="Enter delivery location / destination address if different from default billing address...">{{ old('shipping_address') }}</x-ui.odoo-form-ui>
                    </div>
                </div>

                <!-- Line Items Matrix Section -->
                <div class="border-top pt-4">
                    <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom">
                        <div>
                            <h6 class="fw-bold text-dark mb-0 fs-14"><i class="feather-layers me-2 text-primary"></i>Items to Dispatch</h6>
                            <span id="itemsHint" class="fs-12 text-muted">Select a material requirement or scan/add items directly below.</span>
                        </div>
                        <div class="d-flex align-items-center gap-2" style="width: 420px;">
                            <div class="input-group input-group-sm shadow-2xs rounded overflow-hidden" style="border: 1px solid #cbd5e1 !important;">
                                <span class="input-group-text bg-primary text-white border-0 px-3 fw-semibold"><i class="feather-camera me-1"></i> Barcode</span>
                                <input type="text" id="fastBarcodeScanInput" class="form-control border-0 bg-white" placeholder="Scan Barcode / SKU (Press Enter)..." autocomplete="off" style="font-size: 13px;">
                                <button type="button" class="btn btn-primary border-0 px-3" id="fastBarcodeScanBtn"><i class="feather-search"></i></button>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive border rounded bg-white">
                        <x-ui.odoo-form-ui type="table" id="dispatchItemsTable">
                            <thead class="table-light fs-11 text-uppercase fw-bold text-muted">
                                <tr>
                                    <th style="width: 26%;" class="ps-3">Product Details</th>
                                    <th style="width: 20%;">Warehouse Location</th>
                                    <th class="text-end mr-col" style="width: 7%;">Ordered</th>
                                    <th class="text-end mr-col" style="width: 7%;">Available</th>
                                    <th class="text-end mr-col" style="width: 7%;">Reserved</th>
                                    <th class="text-end mr-col" style="width: 7%;">Dispatched</th>
                                    <th class="text-end mr-col" style="width: 7%;">Remaining</th>
                                    <th class="text-end pe-2" style="width: 14%;">Dispatch Qty</th>
                                    <th class="text-center" style="width: 5%;">Action</th>
                                </tr>
                            </thead>
                            <tbody id="dispatchItemsBody" class="fs-13">
                                <tr id="emptyItemsRow">
                                    <td colspan="9" class="text-center py-4 text-muted fs-12">
                                        <i class="feather-info me-1"></i>No items selected.
                                    </td>
                                </tr>
                            </tbody>
                        </x-ui.odoo-form-ui>
                    </div>

                </div>

                <!-- Internal Notes -->
                <div class="border-top pt-4 mt-4">
                    <x-ui.odoo-form-ui type="textarea" label="Internal Notes & Instructions" name="notes" rows="3" placeholder="Special delivery instructions or gate pass notes...">{{ old('notes') }}</x-ui.odoo-form-ui>
                </div>

                <!-- Bottom Action Bar -->
                <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                    <x-ui.button href="{{ route('sales.dispatches.index') }}" variant="light" class="border px-4">
                        Cancel
                    </x-ui.button>
                    <x-ui.button type="submit" id="saveDispatchBtn" variant="primary" icon="feather-save" class="px-4 fw-bold">
                        Save Dispatch Order
                    </x-ui.button>
                </div>
            </x-ui.odoo-form-ui>
        </form>
    </div>

    <!-- MR Picker Modal Component -->
    <x-ui.modal id="deliveryOrderPicker" title="Select Material Requirement" size="lg" :centered="true" :scrollable="true">
        <div class="mb-3">
            <x-ui.odoo-form-ui type="input" id="deliveryOrderSearch" placeholder="Search by material requirement, sales order, or customer..." />
        </div>
        <div id="deliveryOrderList" class="d-grid gap-2">
            <div class="text-center py-4 text-muted">
                <div class="spinner-border spinner-border-sm me-2 text-primary" role="status"></div>Loading material requirements...
            </div>
        </div>
        <x-slot:footer>
            <x-ui.button type="button" variant="light" class="border" data-bs-dismiss="modal">Cancel</x-ui.button>
        </x-slot:footer>
    </x-ui.modal>
    <!-- Quick Transporter Add Modal Component -->
    <x-ui.modal id="quickTransporterModal" :title="'<i class=\'feather-truck text-primary me-2\'></i>' . __('crm.quick_add_transporter')" size="lg" :centered="true" :showFooter="false">
        <form id="quickTransporterForm">
            @csrf
            <div class="p-1">
                <!-- Section 1: Basic Logistics Info -->
                <h6 class="fw-bold text-primary mb-3"><i class="feather-info me-1.5"></i>{{ __('crm.basic_transporter_info') }}</h6>
                <div class="row g-3 mb-3">
                    <div class="col-md-7">
                        <x-ui.odoo-form-ui type="input" :label="__('crm.transporter_name_field')" name="name" placeholder="e.g. V-Trans, TCI Logistics, GATI KWE" :required="true" />
                    </div>
                    <div class="col-md-5">
                        <x-ui.odoo-form-ui type="input" :label="__('crm.transporter_master_code')" name="code" value="{{ $autoCode }}" placeholder="e.g. TRP-0005" />
                    </div>
                    <div class="col-md-7">
                        <x-ui.odoo-form-ui type="input" :label="__('crm.transporter_id_eway')" name="transporter_id" placeholder="e.g. 27AAACM1234F1Z1" />
                    </div>
                    <div class="col-md-5">
                        <x-ui.odoo-form-ui type="select" :label="__('crm.transport_mode')" name="transport_mode" :searchable="false">
                            <option value="road">{{ __('crm.road_transport') }}</option>
                            <option value="rail">{{ __('crm.rail_logistics') }}</option>
                            <option value="air">{{ __('crm.air_freight') }}</option>
                            <option value="sea">{{ __('crm.sea_cargo') }}</option>
                            <option value="multimodal">{{ __('crm.multimodal') }}</option>
                        </x-ui.odoo-form-ui>
                    </div>
                </div>

                <hr class="my-3 text-muted opacity-25">

                <!-- Section 2: Taxation & Contact Info -->
                <h6 class="fw-bold text-primary mb-3"><i class="feather-shield me-1.5"></i>{{ __('crm.taxation_contact_details') }}</h6>
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="input" :label="__('crm.gstin_number_field')" name="gstin" placeholder="e.g. 27AAAAA0000A1Z5" />
                    </div>
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="input" :label="__('crm.pan_number_field')" name="pan_number" placeholder="e.g. ABCDE1234F" />
                    </div>
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="input" inputType="tel" :label="__('crm.primary_office_phone')" name="phone" placeholder="Contact number" oninput="this.value = this.value.replace(/[^0-9]/g, '')" />
                    </div>
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="input" inputType="email" :label="__('crm.official_email_address')" name="email" placeholder="dispatch@transporter.com" />
                    </div>
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="input" :label="__('crm.city')" name="city" placeholder="City" />
                    </div>
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="input" :label="__('crm.state')" name="state" placeholder="State" />
                    </div>
                </div>

                <!-- Footer Action Buttons -->
                <div class="d-flex justify-content-end align-items-center gap-2 pt-3 border-top mt-3">
                    <button type="button" class="btn btn-light border fw-semibold" data-bs-dismiss="modal">{{ __('crm.cancel') }}</button>
                    <button type="submit" class="btn btn-primary fw-bold px-4" id="saveQuickTransporterBtn">
                        <i class="feather-save me-1.5"></i>{{ __('crm.save_transporter_master') }}
                    </button>
                </div>
            </div>
        </form>
    </x-ui.modal>
@endsection

@push('scripts')
    <script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/select2-active.min.js') }}"></script>
    <script>
        function escapeHtml(str) {
            if (str === null || str === undefined) return '';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        // Trigger Quick Add Transporter Modal when "+ Add New Transporter" option is selected
        $(document).on('change', '#transporterSelect', function() {
            if ($(this).val() === '__ADD_NEW__') {
                $(this).val('');
                if ($(this).data('select2')) {
                    $(this).val(null).trigger('change.select2');
                }
                const modalEl = document.getElementById('quickTransporterModal');
                const modalInstance = bootstrap.Modal.getOrCreateInstance(modalEl);
                modalInstance.show();
            }
        });

        // Quick Transporter AJAX Store
        $('#quickTransporterForm').on('submit', function(e) {
            e.preventDefault();
            const form = $(this);
            const $btn = $('#saveQuickTransporterBtn');

            // Clear previous errors
            form.find('.is-invalid').removeClass('is-invalid');
            form.find('.invalid-feedback.ajax-feedback').remove();
            form.find('.transporter-alert-error').remove();

            // Client-side email validation check
            const emailInput = form.find('input[name="email"]');
            const emailVal = emailInput.val().trim();
            if (emailVal && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailVal)) {
                emailInput.addClass('is-invalid');
                emailInput.parent().find('.invalid-feedback.ajax-feedback').remove();
                emailInput.after('<div class="invalid-feedback d-block fs-11 mt-1 ajax-feedback">The email field must be a valid email address.</div>');
                emailInput.focus();
                return false;
            }

            $btn.prop('disabled', true).text('Saving...');

            $.ajax({
                url: "{{ route('platform.transporters.quick-create') }}",
                type: "POST",
                data: form.serialize(),
                success: function(response) {
                    $btn.prop('disabled', false).text('Save Transporter');
                    if (response.success && response.transporter) {
                        const t = response.transporter;
                        const labelText = t.name + (t.transporter_id ? ` (${t.transporter_id})` : (t.gstin ? ` (${t.gstin})` : ''));
                        const newOpt = new Option(labelText, t.id, true, true);
                        $('#transporterSelect').append(newOpt).trigger('change');
                        
                        // Auto-fill Carrier input if empty
                        if (!$('#carrierInput').val()) {
                            $('#carrierInput').val(t.name);
                        }

                        const modalEl = document.getElementById('quickTransporterModal');
                        const modalInstance = bootstrap.Modal.getInstance(modalEl);
                        if (modalInstance) modalInstance.hide();
                        form[0].reset();
                        form.find('.is-invalid').removeClass('is-invalid');
                        form.find('.invalid-feedback.ajax-feedback').remove();
                    }
                },
                error: function(err) {
                    $btn.prop('disabled', false).text('Save Transporter');
                    if (err.status === 422 && err.responseJSON && err.responseJSON.errors) {
                        const errors = err.responseJSON.errors;
                        let firstField = null;
                        $.each(errors, function(field, messages) {
                            const $input = form.find('[name="' + field + '"]');
                            if ($input.length) {
                                $input.addClass('is-invalid');
                                $input.parent().find('.invalid-feedback.ajax-feedback').remove();
                                $input.after('<div class="invalid-feedback d-block fs-11 mt-1 ajax-feedback">' + messages[0] + '</div>');
                                if (!firstField) firstField = $input;
                            } else {
                                form.find('.p-1').prepend('<div class="alert alert-danger py-2 px-3 fs-12 mb-3 transporter-alert-error">' + messages[0] + '</div>');
                            }
                        });
                        if (firstField) {
                            firstField.focus();
                        }
                    } else {
                        const msg = err.responseJSON?.message || 'Error saving transporter. Please check the details and try again.';
                        form.find('.p-1').prepend('<div class="alert alert-danger py-2 px-3 fs-12 mb-3 transporter-alert-error">' + msg + '</div>');
                    }
                }
            });
        });

        // Realtime clear invalid feedback on input/change
        $(document).on('input change', '#quickTransporterForm input, #quickTransporterForm select', function() {
            $(this).removeClass('is-invalid');
            $(this).parent().find('.invalid-feedback.ajax-feedback').remove();
            $('#quickTransporterForm').find('.transporter-alert-error').remove();
        });

        // Email blur validation for quick transporter form
        $(document).on('blur', '#quickTransporterForm input[name="email"]', function() {
            const val = $(this).val().trim();
            if (val && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)) {
                $(this).addClass('is-invalid');
                $(this).parent().find('.invalid-feedback.ajax-feedback').remove();
                $(this).after('<div class="invalid-feedback d-block fs-11 mt-1 ajax-feedback">The email field must be a valid email address.</div>');
            }
        });

        const warehouses = @json($formattedWarehouses);
        const productsList = @json($formattedProducts);

        const deliveryOrderId = document.getElementById('deliveryOrderId');
        const deliveryOrderList = document.getElementById('deliveryOrderList');
        const itemsBody = document.getElementById('dispatchItemsBody');
        const saveButtonTop = document.getElementById('saveDispatchBtn');
        const saveButtonFooter = document.getElementById('saveDispatchBtnFooter');
        const itemsHint = document.getElementById('itemsHint');
        const pickerElement = document.getElementById('deliveryOrderPicker');
        const searchInput = document.getElementById('deliveryOrderSearch');
        const addDirectItemBtn = document.getElementById('addDirectItemBtn');
        const directAddContainer = document.getElementById('directAddContainer');
        const customerSelectCol = document.getElementById('customerSelectCol');
        const mrBanner = document.getElementById('mrBanner');

        let deliveryOrders = [];
        let directItemIndex = 0;

        function initSelect2(context) {
            $(context).find('.odoo-select2').select2({
                theme: 'bootstrap-5',
                width: '100%'
            });
        }

        $(document).ready(function() {
            initSelect2(document);
        });

        function setSaveButtonsDisabled(disabled) {
            if (saveButtonTop) saveButtonTop.disabled = disabled;
            if (saveButtonFooter) saveButtonFooter.disabled = disabled;
        }





        function fetchBatchesForLine(productId, warehouseId, lineIdx) {
            const batchSelect = document.getElementById(`batch_select_${lineIdx}`);
            if (!batchSelect) return;

            const $select = $(batchSelect);
            if ($select.hasClass("select2-hidden-accessible")) {
                $select.select2('destroy');
            }

            batchSelect.innerHTML = '<option value="">Loading FEFO batches...</option>';

            fetch(`{{ route('sales.dispatches.available-batches') }}?product_id=${productId}&warehouse_id=${warehouseId}`)
                .then(res => res.json())
                .then(data => {
                    batchSelect.innerHTML = '<option value="">-- Auto-Deduct Nearest Expiry Batch (FEFO Rule) --</option>';
                    if (data.batches && data.batches.length) {
                        data.batches.forEach((b, index) => {
                            const badge = index === 0 ? '⚡ Earliest Expiry' : '';
                            const expTag = b.is_expired ? `🚨 EXPIRED (${b.expiry_date})` : (b.is_expiring_soon ? `⚠️ Exp: ${b.expiry_date}` : `Exp: ${b.expiry_date}`);
                            const whLabel = b.warehouse_name ? ` in ${b.warehouse_name}` : '';
                            const optText = `${b.batch_number} (${expTag}) [Available: ${b.available_qty}${whLabel}] ${badge}`;
                            const opt = document.createElement('option');
                            opt.value = b.batch_number;
                            opt.textContent = optText;
                            batchSelect.appendChild(opt);
                        });
                    } else {
                        batchSelect.innerHTML = '<option value="">No active batches found in warehouse</option>';
                    }

                    $select.select2({
                        theme: 'bootstrap-5',
                        width: '100%'
                    });
                })
                .catch(() => {
                    batchSelect.innerHTML = '<option value="">-- Auto-Deduct Nearest Expiry Batch --</option>';
                    $select.select2({
                        theme: 'bootstrap-5',
                        width: '100%'
                    });
                });
        }

        function fetchSerialsForLine(productId, warehouseId, lineIdx, preselectedSerials = []) {
            const serialSelect = document.getElementById(`serial_select_${lineIdx}`);
            if (!serialSelect) return;

            const $select = $(serialSelect);
            if ($select.hasClass("select2-hidden-accessible")) {
                $select.select2('destroy');
            }

            const mrItemId = $select.data('mr-item-id') || '';
            const $tr = $(`#dispatch_row_${lineIdx}`).length ? $(`#dispatch_row_${lineIdx}`) : $select.closest('tr');
            const qtyVal = parseFloat($tr.find('.dispatch-qty-input').val());
            const dispatchQty = Math.round(!isNaN(qtyVal) ? qtyVal : 1);

            serialSelect.innerHTML = '<option value="" disabled>Loading available serials...</option>';

            const url = `{{ route('sales.dispatches.available-serials') }}?product_id=${productId}&warehouse_id=${warehouseId}&material_requirement_item_id=${mrItemId}`;

            $.ajax({
                url: url,
                type: 'GET',
                success: function(data) {
                    serialSelect.innerHTML = '';
                    const availableSerials = (data && data.serials) ? data.serials : [];

                    // Combine availableSerials with preselectedSerials
                    const allSerials = [...new Set([...preselectedSerials, ...availableSerials])];

                    // Smart selection: preselectedSerials first, then fill from availableSerials up to dispatchQty
                    let toSelect = [...preselectedSerials];
                    if (toSelect.length < dispatchQty) {
                        for (const sn of availableSerials) {
                            if (toSelect.length >= dispatchQty) break;
                            if (!toSelect.includes(sn)) {
                                toSelect.push(sn);
                            }
                        }
                    } else if (toSelect.length > dispatchQty) {
                        toSelect = toSelect.slice(0, dispatchQty);
                    }

                    if (allSerials.length > 0) {
                        allSerials.forEach(sn => {
                            const isPreselected = toSelect.includes(sn);
                            const isReserved = preselectedSerials.includes(sn);
                            const opt = document.createElement('option');
                            opt.value = sn;
                            opt.textContent = isReserved ? `${sn} (Reserved)` : sn;
                            if (isPreselected) {
                                opt.selected = true;
                            }
                            serialSelect.appendChild(opt);
                        });
                    } else {
                        serialSelect.innerHTML = '<option value="" disabled>No available serials in this warehouse</option>';
                    }

                    $select.select2({
                        theme: 'bootstrap-5',
                        width: '100%',
                        placeholder: 'Search & select serial numbers...',
                        closeOnSelect: false,
                        allowClear: false
                    });

                    if (toSelect.length > 0) {
                        $select.val(toSelect);
                    }

                    updateSerialBadge($select);
                    checkAllDispatchRowsValidation();
                },
                error: function() {
                    serialSelect.innerHTML = '<option value="" disabled>Error loading serials</option>';
                    $select.select2({
                        theme: 'bootstrap-5',
                        width: '100%',
                        placeholder: 'Search & select serial numbers...'
                    });
                }
            });
        }

        // Auto-select if material_requirement_id is passed in URL
        const urlParams = new URLSearchParams(window.location.search);
        const preselectedId = parseInt('{{ $mrId ?? request('material_requirement_id') ?? request('mr_id') }}') || parseInt(urlParams.get('material_requirement_id')) || parseInt(urlParams.get('mr_id')) || null;

        pickerElement.addEventListener('show.bs.modal', () => {
            if (deliveryOrders.length) {
                renderDeliveryOrders();
                return;
            }

            fetch('{{ route('sales.dispatches.pending-mr') }}', { headers: { Accept: 'application/json' } })
                .then(response => response.ok ? response.json() : Promise.reject())
                .then(res => {
                    deliveryOrders = res.data || res || [];
                    renderDeliveryOrders();
                })
                .catch((err) => {
                    console.error('Fetch error:', err);
                    deliveryOrderList.innerHTML = '<div class="alert alert-danger mb-0">Material requirements could not be loaded. Please try again.</div>';
                });
        });

        searchInput.addEventListener('input', renderDeliveryOrders);

        function renderDeliveryOrders() {
            const search = searchInput.value.trim().toLowerCase();
            const filtered = deliveryOrders.filter(order =>
                [order.requirement_number, order.sales_order, order.customer].filter(Boolean).some(value => value.toLowerCase().includes(search))
            );

            if (!filtered.length) {
                deliveryOrderList.innerHTML = '<div class="text-center py-4 text-muted">No pending material requirements found.</div>';
                return;
            }

            deliveryOrderList.innerHTML = filtered.map(order => `
                <button type="button" class="btn btn-light border text-start p-3 delivery-order-option" data-id="${order.id}" data-bs-dismiss="modal">
                    <strong class="d-block text-primary">${escapeHtml(order.requirement_number)}</strong>
                    <span class="fs-12 text-muted">${escapeHtml(order.sales_order || '')} &mdash; ${escapeHtml(order.customer || 'No customer')}</span>
                    <span class="badge bg-soft-secondary text-secondary float-end">${order.items.length} item(s)</span>
                </button>
            `).join('');
        }

        deliveryOrderList.addEventListener('click', event => {
            const option = event.target.closest('.delivery-order-option');
            if (!option) return;

            const order = deliveryOrders.find(item => item.id === Number(option.dataset.id));
            if (!order) return;

            selectDeliveryOrder(order);
            bootstrap.Modal.getOrCreateInstance(pickerElement).hide();
        });

        let activeSalesOrderInvoices = [];
        let activeOrderItems = [];

        function loadInvoicesForSalesOrder(salesOrderId) {
            const $wrapper = $('#invoicePickerWrapper');
            const $select = $('#invoiceSelect');
            if (!salesOrderId) {
                $wrapper.addClass('d-none');
                $select.empty().append('<option value="">-- Dispatch Full Sales Order / Material Requirement --</option>');
                activeSalesOrderInvoices = [];
                return;
            }

            const url = "{{ route('sales.dispatches.sales-order-invoices') }}?sales_order_id=" + salesOrderId;
            $.ajax({
                url: url,
                type: 'GET',
                success: function(res) {
                    if (res && res.invoices && res.invoices.length > 0) {
                        activeSalesOrderInvoices = res.invoices;
                        $select.empty().append('<option value="">-- Dispatch Full Sales Order / Material Requirement --</option>');
                        const currencySymbol = "{{ active_currency_symbol() }}";
                        res.invoices.forEach(function(inv) {
                            $select.append(`<option value="${inv.id}">Invoice #${inv.invoice_number} (${inv.invoice_date}) - {{ __('crm.total') }} ${currencySymbol}${inv.total_amount} [${inv.items.length} unfulfilled item(s)]</option>`);
                        });
                        $wrapper.removeClass('d-none');
                    } else {
                        $wrapper.addClass('d-none');
                        $select.empty().append('<option value="">-- Dispatch Full Sales Order / Material Requirement --</option>');
                        activeSalesOrderInvoices = [];
                    }
                },
                error: function() {
                    $wrapper.addClass('d-none');
                    activeSalesOrderInvoices = [];
                }
            });
        }

        $('#invoiceSelect').on('change', function() {
            const selectedInvId = $(this).val();
            if (selectedInvId) {
                const inv = activeSalesOrderInvoices.find(i => i.id == selectedInvId);
                if (inv && inv.items) {
                    renderDispatchItems(inv.items, true);
                }
            } else {
                if (activeOrderItems && activeOrderItems.length > 0) {
                    renderDispatchItems(activeOrderItems, false);
                }
            }
        });

        function selectDeliveryOrder(order) {
            deliveryOrderId.value = order.id;
            const soId = order.sales_order_id || '';
            if (document.getElementById('salesOrderId')) {
                document.getElementById('salesOrderId').value = soId;
            }

            $('#orderSelectPicker').val(order.id).trigger('change.select2');
            $('#displayCustomerNameInput').val(order.customer || '—');

            if (order.freight_terms) {
                $('#freightTermsSelect').val(order.freight_terms).trigger('change');
            }
            if (order.freight_amount !== undefined) {
                $('#freightAmountInput').val(parseFloat(order.freight_amount).toFixed(2));
            }

            activeOrderItems = order.items || [];
            renderDispatchItems(activeOrderItems, false);

            if (soId) {
                loadInvoicesForSalesOrder(soId);
            } else {
                loadInvoicesForSalesOrder(null);
            }
        }

        $('#orderSelectPicker').on('change', function() {
            const selectedMrId = $(this).val();
            if (!selectedMrId) return;

            if (deliveryOrders && deliveryOrders.length > 0) {
                const targetOrder = deliveryOrders.find(o => o.id == selectedMrId);
                if (targetOrder) {
                    selectDeliveryOrder(targetOrder);
                    return;
                }
            }

            fetch('{{ route('sales.dispatches.pending-mr') }}', { headers: { Accept: 'application/json' } })
                .then(res => res.ok ? res.json() : Promise.reject())
                .then(res => {
                    deliveryOrders = res.data || res || [];
                    const targetOrder = deliveryOrders.find(o => o.id == selectedMrId);
                    if (targetOrder) {
                        selectDeliveryOrder(targetOrder);
                    }
                });
        });

        function renderDispatchItems(items, isInvoice = false) {
            let rowsHtml = '';
            let dispatchableCount = 0;

            items.forEach((item, index) => {
                const options = warehouses.map(w =>
                    `<option value="${w.id}" ${Number(item.warehouse_id) === Number(w.id) ? 'selected' : ''}>${escapeHtml(w.name)}</option>`
                ).join('');

                const itemRefInput = isInvoice 
                    ? `<input type="hidden" name="items[${index}][invoice_item_id]" value="${item.invoice_item_id || item.id}">${item.material_requirement_item_id ? `<input type="hidden" name="items[${index}][material_requirement_item_id]" value="${item.material_requirement_item_id}">` : ''}`
                    : `<input type="hidden" name="items[${index}][material_requirement_item_id]" value="${item.id}">`;

                const pMeta = productsList.find(p => p.id == item.product_id);
                const isSerial = Boolean(item.track_serial_number || (pMeta && pMeta.track_serial_number));
                const isBatch = Boolean(item.track_batch || (pMeta && pMeta.track_batch));

                const initialQty = parseFloat(item.dispatch_qty) || 0;
                let defaultSerials = '';
                if (isSerial && item.reserved_serials && Array.isArray(item.reserved_serials) && item.reserved_serials.length > 0) {
                    defaultSerials = item.reserved_serials.slice(0, Math.round(initialQty)).join('\n');
                }

                if (item.fully_dispatched) {
                    rowsHtml += `
                        <tr class="text-muted bg-light">
                            <td class="ps-3 align-top">
                                <strong class="text-muted">${escapeHtml(item.product_name || 'Unknown product')}</strong>
                                ${item.product_sku ? `<small class="d-block text-muted font-monospace fs-11">SKU: ${escapeHtml(item.product_sku)}</small>` : ''}
                            </td>
                            <td class="align-top"><span class="text-muted fs-12">—</span></td>
                            <td class="text-end align-top">${item.quantity_ordered}</td>
                            <td class="text-end fw-semibold text-success align-top">${(Number(item.available_qty) || 0) % 1 === 0 ? (Number(item.available_qty) || 0) : parseFloat(Number(item.available_qty).toFixed(2))}</td>
                            <td class="text-end align-top">${(Number(item.quantity_reserved) || 0) % 1 === 0 ? (Number(item.quantity_reserved) || 0) : parseFloat(Number(item.quantity_reserved).toFixed(2))}</td>
                            <td class="text-end fw-bold text-success align-top">${item.already_dispatched}</td>
                            <td class="text-end fw-bold text-muted align-top">0</td>
                            <td class="text-end align-top">
                                <span class="badge bg-soft-success text-success fs-11 px-2">Fully Dispatched</span>
                            </td>
                            <td class="text-center align-top">—</td>
                        </tr>`;
                } else {
                    dispatchableCount++;
                    rowsHtml += `
                        <tr data-index="${index}" data-product-id="${item.product_id}" class="dispatch-item-row" id="dispatch_row_${index}">
                            <td class="ps-3 align-middle product-detail-cell">
                                ${itemRefInput}
                                <input type="hidden" name="items[${index}][product_id]" value="${item.product_id}">
                                <div class="d-flex align-items-center gap-1.5 flex-wrap">
                                    <strong class="text-dark fs-13">${escapeHtml(item.product_name || 'Unknown product')}</strong>
                                    ${isSerial ? `<span class="badge bg-soft-info text-info border border-info border-opacity-25 fs-10 px-1.5 py-0.5"><i class="feather-hash me-0.5"></i>Serial</span>` : ''}
                                    ${isBatch ? `<span class="badge bg-soft-warning text-warning border border-warning border-opacity-25 fs-10 px-1.5 py-0.5"><i class="feather-layers me-0.5"></i>Batch</span>` : ''}
                                </div>
                                ${item.product_sku ? `<small class="d-block text-muted font-monospace fs-11">SKU: ${escapeHtml(item.product_sku)}</small>` : ''}
                            </td>
                            <td class="align-middle"><select name="items[${index}][warehouse_id]" class="form-select odoo-table-select odoo-select2 item-warehouse-select" data-index="${index}">${options}</select></td>
                            <td class="text-end fw-semibold mr-col align-middle">${item.quantity_ordered}</td>
                            <td class="text-end fw-semibold text-success mr-col align-middle avail-qty-cell">${(Number(item.available_qty) || 0) % 1 === 0 ? (Number(item.available_qty) || 0) : parseFloat(Number(item.available_qty).toFixed(2))}</td>
                            <td class="text-end fw-semibold text-info mr-col align-middle reserved-qty-cell">${(Number(item.quantity_reserved) || 0) % 1 === 0 ? (Number(item.quantity_reserved) || 0) : parseFloat(Number(item.quantity_reserved).toFixed(2))}</td>
                            <td class="text-end fw-bold mr-col align-middle ${item.already_dispatched > 0 ? 'text-warning' : 'text-muted'}">${item.already_dispatched}</td>
                            <td class="text-end fw-bold text-primary mr-col align-middle">${item.remaining_qty}</td>
                            <td class="text-end pe-2 align-middle qty-cell">
                                <input type="hidden" name="items[${index}][quantity_ordered]" value="${item.quantity_ordered}">
                                <input
                                    type="number"
                                    name="items[${index}][quantity]"
                                    class="odoo-table-input text-end dispatch-qty-input fw-bold"
                                    value="${item.dispatch_qty}"
                                    min="0.0001"
                                    max="${item.remaining_qty}"
                                    data-max="${item.remaining_qty}"
                                    data-index="${index}"
                                    required
                                >
                                <div class="qty-error-msg text-danger fs-11 fw-bold text-end mt-1" style="display: none;"></div>
                            </td>
                            <td class="text-center align-middle pe-2">
                                <button type="button" class="btn btn-sm btn-link text-danger p-0 remove-dispatch-row-btn" title="Remove line item from this dispatch">
                                    <i class="feather-trash-2 fs-15"></i>
                                </button>
                            </td>
                        </tr>`;

                    if (isSerial) {
                        rowsHtml += `
                        <tr class="serial-tracking-row" id="serial_subrow_${index}" data-parent-index="${index}">
                            <td colspan="9" class="py-1.5 px-3 border-top-0">
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <div class="d-flex align-items-center gap-2 flex-shrink-0 me-1">
                                        <span class="fs-11 fw-bold text-dark d-flex align-items-center text-nowrap">
                                            <i class="feather-hash text-primary me-1 fs-12"></i>Serials:
                                        </span>
                                        <span class="badge bg-soft-primary text-primary fs-10 px-2 py-0.5 serial-count-badge" id="serial_badge_${index}">
                                            <span class="serial-entered-count">0</span> / <span class="serial-required-count">${Math.round(initialQty)}</span> Serials
                                        </span>
                                        <button type="button" class="btn btn-xs btn-outline-primary py-0.5 px-2 fs-10 fw-semibold auto-fill-serials-btn" data-index="${index}" data-product-id="${item.product_id}" data-mr-item-id="${item.material_requirement_item_id || item.id || ''}" title="Auto-Select Available Serials">
                                            <i class="feather-zap me-0.5"></i>Auto
                                        </button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary py-0.5 px-2 fs-10 clear-serials-btn" data-index="${index}" title="Clear selection">
                                            Clear
                                        </button>
                                    </div>
                                    <div class="flex-grow-1" style="min-width: 250px;">
                                        <select
                                            name="items[${index}][serial_numbers][]"
                                            id="serial_select_${index}"
                                            class="form-select form-select-sm fs-11 serial-multi-select"
                                            multiple="multiple"
                                            data-index="${index}"
                                            data-product-id="${item.product_id}"
                                            data-mr-item-id="${item.material_requirement_item_id || item.id || ''}"
                                            style="width: 100%;"
                                        ></select>
                                    </div>
                                    <div class="serial-error-msg text-danger fs-11 fw-bold flex-shrink-0" id="serial_error_${index}" style="display: none;"></div>
                                </div>
                            </td>
                        </tr>`;
                    }

                    if (isBatch) {
                        rowsHtml += `
                        <tr class="batch-tracking-row" id="batch_subrow_${index}" data-parent-index="${index}">
                            <td colspan="9" class="py-1.5 px-3 border-top-0">
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <span class="fs-11 fw-bold text-dark d-flex align-items-center flex-shrink-0 text-nowrap">
                                        <i class="feather-layers text-warning me-1 fs-12"></i>Batch Allocation (FEFO):
                                    </span>
                                    <div class="flex-grow-1" style="min-width: 280px; max-width: 480px;">
                                        <select name="items[${index}][batch_number]" id="batch_select_${index}" class="form-select form-select-sm fs-11 odoo-select2 batch-select">
                                            <option value="">-- Auto-Deduct Nearest Expiry Batch (FEFO Rule) --</option>
                                        </select>
                                    </div>
                                    <span class="fs-10 text-muted fst-italic flex-shrink-0"><i class="feather-info me-1"></i>Sorted by earliest expiry date</span>
                                </div>
                            </td>
                        </tr>`;
                    }
                }
            });

            itemsBody.innerHTML = rowsHtml;
            initSelect2(itemsBody);
            itemsHint.textContent = `${dispatchableCount} item(s) available to dispatch ${isInvoice ? '(From Selected Invoice)' : ''}.`;

            // Initialize serial dropdowns and batch dropdowns
            items.forEach((item, index) => {
                const pMeta = productsList.find(p => p.id == item.product_id);
                const isSerial = Boolean(item.track_serial_number || (pMeta && pMeta.track_serial_number));
                const isBatch = Boolean(item.track_batch || (pMeta && pMeta.track_batch));

                if (isSerial) {
                    const preselected = (item.reserved_serials && Array.isArray(item.reserved_serials)) ? item.reserved_serials : [];
                    fetchSerialsForLine(item.product_id, item.warehouse_id, index, preselected);
                }
                if (isBatch) {
                    fetchBatchesForLine(item.product_id, item.warehouse_id, index);
                }
            });

            // Auto-trigger stock check AJAX for each row to fetch real-time warehouse available stock
            $(itemsBody).find('tr').each(function() {
                const $tr = $(this);
                const $whSelect = $tr.find('select[name$="[warehouse_id]"]');
                if ($whSelect.length && $whSelect.val()) {
                    $whSelect.trigger('change');
                }
            });

            setTimeout(function() {
                checkAllDispatchRowsValidation();
            }, 100);
        }

        function escapeHtml(value) {
            const element = document.createElement('div');
            element.textContent = value;
            return element.innerHTML;
        }

        function updateSerialBadge($target, overrideQty = null) {
            if (!$target || !$target.length) return true;
            const idx = $target.data('index');
            const $tr = (idx !== undefined && $(`#dispatch_row_${idx}`).length) ? $(`#dispatch_row_${idx}`) : $target.closest('tr');
            
            let enteredCount = 0;
            if ($target.is('select') || $target.hasClass('serial-multi-select')) {
                const val = $target.val();
                enteredCount = Array.isArray(val) ? val.filter(Boolean).length : (val ? 1 : 0);
            } else {
                const rawVal = ($target.val() || '').trim();
                const serialsArr = rawVal ? rawVal.split(/[\r\n,;]+/).map(s => s.trim()).filter(Boolean) : [];
                enteredCount = serialsArr.length;
            }

            const qtyVal = (overrideQty !== null && !isNaN(overrideQty)) ? overrideQty : parseFloat($tr.find('.dispatch-qty-input').val());
            const dispatchQty = Math.round(!isNaN(qtyVal) ? qtyVal : 0);

            const $badge = $(`#serial_badge_${idx}`);
            const $err = $(`#serial_error_${idx}`).length ? $(`#serial_error_${idx}`) : $target.siblings('.serial-error-msg');
            const $s2Container = $target.next('.select2-container').find('.select2-selection');

            if ($badge.length) {
                if (enteredCount === dispatchQty && dispatchQty > 0) {
                    $badge.removeClass('bg-soft-primary text-primary bg-soft-danger text-danger bg-soft-warning text-warning')
                          .addClass('bg-soft-success text-success border border-success border-opacity-25')
                          .html(`<i class="feather-check-circle me-1"></i>${enteredCount} / ${dispatchQty} Verified`);
                    $target.removeClass('is-invalid border-danger');
                    $s2Container.removeClass('border-danger border-warning').addClass('border-success');
                    $err.hide().empty();
                    return true;
                } else if (enteredCount < dispatchQty) {
                    $badge.removeClass('bg-soft-primary text-primary bg-soft-success text-success')
                          .addClass('bg-soft-warning text-warning border border-warning border-opacity-25')
                          .html(`<i class="feather-alert-circle me-1"></i>${enteredCount} / ${dispatchQty} (Need ${dispatchQty - enteredCount} more)`);
                    $target.addClass('is-invalid border-danger');
                    $s2Container.removeClass('border-success').addClass('border-warning');
                    $err.html(`<i class="feather-alert-circle me-1"></i>${enteredCount === 0 ? 'Select serials' : `Need ${dispatchQty - enteredCount} more`}`).show();
                    return false;
                } else {
                    $badge.removeClass('bg-soft-primary text-primary bg-soft-success text-success')
                          .addClass('bg-soft-danger text-danger border border-danger border-opacity-25')
                          .html(`<i class="feather-alert-triangle me-1"></i>${enteredCount} / ${dispatchQty} (${enteredCount - dispatchQty} extra)`);
                    $target.addClass('is-invalid border-danger');
                    $s2Container.removeClass('border-success').addClass('border-danger');
                    $err.html(`<i class="feather-alert-triangle me-1"></i>${enteredCount - dispatchQty} extra serial(s)`).show();
                    return false;
                }
            }
            return true;
        }

        function validateDispatchRow($tr) {
            const $qtyInput = $tr.find('.dispatch-qty-input, input[name$="[quantity]"], input[name$="[qty]"]');
            if (!$qtyInput.length) return true;

            const val = parseFloat($qtyInput.val());
            const maxAttr = $qtyInput.attr('data-max') || $qtyInput.data('max');
            const remainingMax = (maxAttr !== undefined && maxAttr !== '' && !isNaN(parseFloat(maxAttr))) ? parseFloat(maxAttr) : Infinity;

            const $availCell = $tr.find('.avail-qty-cell');
            const $reservedCell = $tr.find('.reserved-qty-cell');

            let avail = 0;
            if ($availCell.length) {
                avail = parseFloat($availCell.text().trim()) || 0;
            } else if ($tr.attr('data-available-qty') !== undefined) {
                avail = parseFloat($tr.attr('data-available-qty')) || 0;
            }

            let reserved = 0;
            if ($reservedCell.length) {
                reserved = parseFloat($reservedCell.text().trim()) || 0;
            }

            const totalUsableStock = reserved + avail;

            const $qtyCell = $tr.find('.qty-cell');
            let $error = $qtyCell.length ? $qtyCell.find('.qty-error-msg') : $tr.find('.qty-error-msg');

            if (!$error.length) {
                $error = $('<div class="qty-error-msg text-danger fs-11 fw-bold text-end mt-1"></div>');
                if ($qtyCell.length) {
                    $qtyCell.append($error);
                } else {
                    $qtyInput.after($error);
                }
            }

            let errorMsg = '';

            if (isNaN(val) || val <= 0) {
                errorMsg = 'Dispatch Qty must be greater than 0.';
            } else if ($availCell.length && totalUsableStock <= 0) {
                errorMsg = 'No stock available in selected warehouse (Reserved: ' + reserved + ', Available: ' + avail + ').';
            } else if ($availCell.length && val > totalUsableStock) {
                errorMsg = 'Exceeded available stock (' + val + ' requested > ' + totalUsableStock + ' available).';
            } else if (remainingMax !== Infinity && val > remainingMax) {
                errorMsg = 'Exceeded remaining order quantity (' + remainingMax + ').';
            }

            if (errorMsg) {
                $qtyInput.addClass('is-invalid').css({'border-bottom': '2px solid #ef4444', 'color': '#ef4444'});
                $error.css({'display': 'block', 'color': '#ef4444', 'font-size': '11px', 'font-weight': '700', 'margin-top': '4px', 'clear': 'both'})
                      .removeClass('d-none')
                      .html('<span class="text-danger fw-bold fs-11 d-block text-end mt-1"><i class="feather-alert-circle me-1"></i>' + errorMsg + '</span>')
                      .show();

                return false;
            }

            $qtyInput.removeClass('is-invalid').css({'border-bottom': '1px solid #cbd5e1', 'color': 'inherit'});
            $error.addClass('d-none').css('display', 'none').empty().hide();

            // Validate Serial Numbers if row has serial numbers input or multi-select
            const idx = $tr.data('index');
            const $serialInput = (idx !== undefined && $(`#serial_select_${idx}`).length)
                ? $(`#serial_select_${idx}`)
                : $tr.find('.serial-multi-select, .serial-numbers-input');
            if ($serialInput.length) {
                const isSerialValid = updateSerialBadge($serialInput, val);
                if (!isSerialValid) {
                    return false;
                }
            }

            return true;
        }

        function checkAllDispatchRowsValidation() {
            let hasError = false;
            let rowCount = 0;

            $('#dispatchItemsBody tr.dispatch-item-row').each(function() {
                const $tr = $(this);
                if ($tr.find('.dispatch-qty-input, input[name$="[quantity]"], input[name$="[qty]"]').length) {
                    rowCount++;
                    const isValid = validateDispatchRow($tr);
                    if (!isValid) {
                        hasError = true;
                    }
                }
            });

            if (rowCount === 0) {
                setSaveButtonsDisabled(true);
            } else {
                setSaveButtonsDisabled(hasError);
            }

            return !hasError;
        }

        $(document).on('click', '.remove-dispatch-row-btn, .remove-row-btn', function () {
            const $tr = $(this).closest('tr');
            const idx = $tr.data('index');
            if (idx !== undefined) {
                $(`#serial_subrow_${idx}`).remove();
                $(`#batch_subrow_${idx}`).remove();
            }
            $tr.remove();

            const remainingRows = itemsBody.querySelectorAll('tr.dispatch-item-row');
            itemsHint.textContent = `${remainingRows.length} item(s) available to dispatch.`;

            if (remainingRows.length === 0) {
                itemsBody.innerHTML = '<tr id="emptyItemsRow"><td colspan="9" class="text-center py-4 text-muted fs-12"><i class="feather-info me-1"></i>No items selected in this dispatch order.</td></tr>';
                setSaveButtonsDisabled(true);
            } else {
                checkAllDispatchRowsValidation();
            }
        });

        $(document).on('input change keyup blur', '.dispatch-qty-input, input[name$="[quantity]"], input[name$="[qty]"]', function() {
            const $tr = $(this).closest('tr');
            const idx = $tr.data('index');
            const $serialInput = (idx !== undefined && $(`#serial_select_${idx}`).length)
                ? $(`#serial_select_${idx}`)
                : $tr.find('.serial-multi-select, .serial-numbers-input');
            if ($serialInput.length) {
                updateSerialBadge($serialInput, parseFloat($(this).val()));
            }
            checkAllDispatchRowsValidation();
        });

        // Live validation for serial numbers multi-select or input
        $(document).on('change change.select2', '.serial-multi-select', function() {
            updateSerialBadge($(this));
            checkAllDispatchRowsValidation();
        });
        $(document).on('input change keyup blur', '.serial-numbers-input', function() {
            updateSerialBadge($(this));
            checkAllDispatchRowsValidation();
        });

        // 1-Click Auto-Select Serials button
        $(document).on('click', '.auto-fill-serials-btn', function() {
            const idx = $(this).data('index');
            const $tr = (idx !== undefined && $(`#dispatch_row_${idx}`).length) ? $(`#dispatch_row_${idx}`) : $(this).closest('tr');
            const $select = $(`#serial_select_${idx}`);
            const qtyVal = parseFloat($tr.find('.dispatch-qty-input').val());
            const dispatchQty = Math.round(!isNaN(qtyVal) ? qtyVal : 1);

            if ($select.length) {
                const availableOptions = [];
                $select.find('option').each(function() {
                    const val = $(this).val();
                    if (val && !$(this).is(':disabled')) {
                        availableOptions.push(val);
                    }
                });

                if (availableOptions.length > 0) {
                    const toSelect = availableOptions.slice(0, dispatchQty);
                    $select.val(toSelect).trigger('change');
                } else {
                    const prodId = $(this).data('product-id');
                    const whId = $tr.find('select[name$="[warehouse_id]"]').val();
                    fetchSerialsForLine(prodId, whId, idx, []);
                }
                return;
            }

            const $textarea = $(`#serial_input_${idx}`);
            if ($textarea.length) {
                const prodId = $(this).data('product-id');
                const mrItemId = $(this).data('mr-item-id') || '';
                const whId = $tr.find('select[name$="[warehouse_id]"]').val();
                const $btn = $(this);

                $btn.prop('disabled', true).html('<i class="feather-loader animate-spin me-0.5"></i>Loading...');

                const url = `{{ route('sales.dispatches.available-serials') }}?product_id=${prodId}&warehouse_id=${whId}&material_requirement_item_id=${mrItemId}`;
                $.ajax({
                    url: url,
                    type: 'GET',
                    success: function(data) {
                        $btn.prop('disabled', false).html('<i class="feather-zap me-0.5"></i>Auto-Select');
                        if (data.serials && data.serials.length) {
                            const serialsToFill = data.serials.slice(0, Math.round(qtyVal)).join('\n');
                            $textarea.val(serialsToFill).trigger('input');
                        } else {
                            alert('No available or reserved serial numbers found in stock for this product/warehouse.');
                        }
                    },
                    error: function() {
                        $btn.prop('disabled', false).html('<i class="feather-zap me-0.5"></i>Auto-Select');
                        alert('Error fetching serial numbers. Please try again.');
                    }
                });
            }
        });

        // Clear serials button
        $(document).on('click', '.clear-serials-btn', function() {
            const idx = $(this).data('index');
            const $select = $(`#serial_select_${idx}`);
            if ($select.length) {
                $select.val([]).trigger('change');
            }
            const $textarea = $(`#serial_input_${idx}`);
            if ($textarea.length) {
                $textarea.val('').trigger('input');
            }
        });

        document.getElementById('dispatchForm').addEventListener('submit', event => {
            const modeRadio = document.querySelector('input[name="dispatch_mode"]:checked');
            const mode = modeRadio ? modeRadio.value : 'mr';
            if (mode === 'mr' && (!deliveryOrderId.value || !itemsBody.querySelector('input[name$="[quantity]"]'))) {
                event.preventDefault();
                alert('Please select a material requirement with dispatchable items.');
                return false;
            }
            if (!checkAllDispatchRowsValidation()) {
                event.preventDefault();
                alert('Please fix the quantity errors before saving the dispatch order.');
                return false;
            }
        });

        const preselectedSoId = parseInt('{{ $soId ?? $prefillSalesOrder?->id ?? request('sales_order_id') ?? request('so_id') }}') || parseInt(urlParams.get('sales_order_id')) || parseInt(urlParams.get('so_id')) || null;
        const targetSelectId = preselectedId || parseInt($('#orderSelectPicker').val()) || null;

        if (targetSelectId || preselectedSoId) {
            fetch('{{ route('sales.dispatches.pending-mr') }}', { headers: { Accept: 'application/json' } })
                .then(response => response.ok ? response.json() : Promise.reject())
                .then(res => {
                    deliveryOrders = res.data || res || [];
                    let order = null;
                    if (targetSelectId) {
                        order = deliveryOrders.find(o => Number(o.id) === Number(targetSelectId));
                    }
                    if (!order && preselectedSoId) {
                        order = deliveryOrders.find(o => Number(o.sales_order_id) === Number(preselectedSoId));
                    }
                    if (order) {
                        selectDeliveryOrder(order);
                    }
                })
                .catch(err => console.error('Error pre-loading dispatch order:', err));
        }

        // Fast Barcode Scan Lookup for Dispatches
        function handleDispatchBarcodeScan() {
            const input = document.getElementById('fastBarcodeScanInput');
            const code = input ? input.value.trim() : '';
            if (!code) return;

            // Switch to direct mode if in MR mode
            const currentMode = document.querySelector('input[name="dispatch_mode"]:checked')?.value;
            if (currentMode !== 'direct') {
                const directRadio = document.getElementById('mode_direct');
                if (directRadio) {
                    directRadio.checked = true;
                    directRadio.dispatchEvent(new Event('change'));
                }
            }

            $.ajax({
                url: "{{ route('inventory.products.barcodeLookup') }}",
                data: { code: code },
                success: function(data) {
                    if (data.success && data.product) {
                        const prod = data.product;

                        // Ensure product is in productsList
                        const exists = productsList.some(p => p.id == prod.id);
                        if (!exists) {
                            productsList.push({
                                id: prod.id,
                                name: prod.name,
                                sku: prod.sku,
                                track_serial_number: prod.track_serial_number || 0,
                                track_batch: prod.track_batch || 0
                            });
                        }

                        // 1. Look for existing row with SAME product AND SAME warehouse
                        let targetTr = null;
                        const targetWhId = data.warehouse_id || null;

                        $('#dispatchItemsBody tr').each(function() {
                            const tr = $(this);
                            const rowProdId = tr.find('select[name$="[product_id]"]').val();
                            const rowWhId = tr.find('select[name$="[warehouse_id]"]').val();

                            if (rowProdId == prod.id) {
                                if (!targetWhId || !rowWhId || rowWhId == targetWhId) {
                                    targetTr = tr;
                                    return false; // Found matching product + warehouse row
                                }
                            }
                        });

                        let isNewRow = false;
                        // 2. If no matching row found, look for an empty row
                        if (!targetTr) {
                            $('#dispatchItemsBody tr').each(function() {
                                const tr = $(this);
                                const rowProdId = tr.find('select[name$="[product_id]"]').val();
                                if (!rowProdId) {
                                    targetTr = tr;
                                    isNewRow = true;
                                    return false;
                                }
                            });
                        }

                        // 3. If no empty row found, create a new row
                        if (!targetTr) {
                            addDirectItemRow();
                            targetTr = $('#dispatchItemsBody tr').last();
                            isNewRow = true;
                        }

                        if (targetTr && targetTr.length) {
                            const targetSelect = targetTr.find('select[name$="[product_id]"]');

                            if (!targetSelect.find(`option[value="${prod.id}"]`).length) {
                                targetSelect.append(`<option value="${prod.id}" data-serial="${prod.track_serial_number ? '1' : '0'}" data-batch="${prod.track_batch ? '1' : '0'}">${escapeHtml(prod.name)} ${prod.sku ? `(${escapeHtml(prod.sku)})` : ''}</option>`);
                            }
                            if (targetSelect.val() != prod.id) {
                                targetSelect.val(prod.id).trigger('change');
                            }

                            // Auto-select Warehouse for this row if provided
                            if (targetWhId) {
                                const whSelect = targetTr.find('select[name$="[warehouse_id]"]');
                                if (whSelect.length && whSelect.find(`option[value="${targetWhId}"]`).length) {
                                    if (whSelect.val() != targetWhId) {
                                        whSelect.val(targetWhId).trigger('change');
                                    }
                                }
                            }

                            // Qty Increment or Initial Set
                            const qtyInput = targetTr.find('input[name$="[qty]"], input[name$="[dispatch_qty]"], input[name$="[quantity]"]');
                            if (qtyInput.length) {
                                const currentQty = parseFloat(qtyInput.val()) || 0;
                                if (!isNewRow && currentQty > 0) {
                                    qtyInput.val(currentQty + 1).trigger('input').trigger('change');
                                } else {
                                    if (currentQty === 0) {
                                        qtyInput.val(1).trigger('input').trigger('change');
                                    }
                                }
                            }

                            // Auto-fill Serial Number if scanned code is a Serial Number
                            const scannedSn = data.serial_number || (data.is_serial ? code : null);
                            if (scannedSn) {
                                const trIdx = targetTr.data('index');
                                const serialSelect = (trIdx !== undefined && $(`#serial_select_${trIdx}`).length)
                                    ? $(`#serial_select_${trIdx}`)
                                    : targetTr.find('.serial-multi-select');
                                if (serialSelect.length) {
                                    let exists = serialSelect.find(`option[value="${scannedSn}"]`).length > 0;
                                    if (!exists) {
                                        const newOption = new Option(scannedSn, scannedSn, true, true);
                                        serialSelect.append(newOption);
                                    }
                                    let currentVals = serialSelect.val() || [];
                                    if (!currentVals.includes(scannedSn)) {
                                        currentVals.push(scannedSn);
                                        serialSelect.val(currentVals).trigger('change');
                                    }
                                    if (qtyInput.length && currentVals.length > (parseFloat(qtyInput.val()) || 0)) {
                                        qtyInput.val(currentVals.length).trigger('input').trigger('change');
                                    }
                                } else {
                                    const serialTextarea = targetTr.find('textarea[name$="[serial_numbers]"], input[name$="[serial_numbers]"]');
                                    if (serialTextarea.length) {
                                        let currentText = serialTextarea.val().trim();
                                        let serialsArr = currentText ? currentText.split(/[\r\n,;]+/).map(s => s.trim()).filter(Boolean) : [];
                                        if (!serialsArr.includes(scannedSn)) {
                                            serialsArr.push(scannedSn);
                                            serialTextarea.val(serialsArr.join('\n'));
                                        }
                                        if (qtyInput.length && serialsArr.length > (parseFloat(qtyInput.val()) || 0)) {
                                            qtyInput.val(serialsArr.length).trigger('input').trigger('change');
                                        }
                                    }
                                }
                            }
                        }

                        if (input) input.value = '';
                    } else {
                        alert('Product or Serial Number Not Found for code: ' + code);
                        if (input) input.value = '';
                    }
                },
                error: function() {
                    alert('Product or Serial Number Not Found for scanned code: ' + code);
                    if (input) input.value = '';
                }
            });
        }

        document.getElementById('fastBarcodeScanInput')?.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                handleDispatchBarcodeScan();
            }
        });
        document.getElementById('fastBarcodeScanBtn')?.addEventListener('click', function(e) {
            e.preventDefault();
            handleDispatchBarcodeScan();
        });



        // Auto-update Available & Reserved Stock quantity when Warehouse or Product is changed in Dispatch table
        $(document).on('change change.select2', 'select[name$="[warehouse_id]"], select[name$="[product_id]"]', function() {
            const $tr = $(this).closest('tr');
            const warehouseId = $tr.find('select[name$="[warehouse_id]"]').val();
            let productId = $tr.find('input[name$="[product_id]"]').val() || $tr.find('select[name$="[product_id]"]').val();
            let mrItemId = $tr.find('input[name$="[material_requirement_item_id]"]').val();
            let invItemId = $tr.find('input[name$="[invoice_item_id]"]').val();
            let soId = $('#salesOrderId').val();
            const $availCell = $tr.find('.avail-qty-cell');
            const $reservedCell = $tr.find('.reserved-qty-cell');
            const $availBadgeSpan = $tr.find('.avail-qty-span');
            const $badgeBox = $tr.find('.stock-info-badge span');

            if (!productId || !warehouseId) return;

            $.ajax({
                url: "{{ route('inventory.products.stockCheck') }}",
                type: "GET",
                data: {
                    product_id: productId,
                    warehouse_id: warehouseId,
                    material_requirement_item_id: mrItemId || null,
                    invoice_item_id: invItemId || null,
                    sales_order_id: soId || null
                },
                success: function(response) {
                    if (response && response.success) {
                        const avail = parseFloat(response.available_qty) || 0;
                        const itemReserved = parseFloat(response.item_reserved_qty) || 0;
                        const whReserved = parseFloat(response.reserved_qty) || 0;

                        $tr.attr('data-available-qty', avail);

                        const formattedAvail = (avail % 1 === 0) ? avail : parseFloat(avail.toFixed(2));
                        const displayReserved = itemReserved > 0 ? itemReserved : whReserved;
                        const formattedReserved = (displayReserved % 1 === 0) ? displayReserved : parseFloat(displayReserved.toFixed(2));

                        if ($availCell.length) {
                            $availCell.text(formattedAvail);
                            $availCell.removeClass('text-success text-danger');
                            $availCell.addClass(avail > 0 ? 'text-success' : 'text-danger');
                        }

                        if ($reservedCell.length) {
                            $reservedCell.text(formattedReserved);
                        }

                        if ($availBadgeSpan.length) {
                            $availBadgeSpan.text(formattedAvail);
                            if (avail <= 0) {
                                $badgeBox.removeClass('bg-soft-info text-info bg-soft-success text-success').addClass('bg-soft-danger text-danger border-danger');
                            } else {
                                $badgeBox.removeClass('bg-soft-info text-info bg-soft-danger text-danger').addClass('bg-soft-success text-success border-success');
                            }
                        }

                        checkAllDispatchRowsValidation();
                    }
                },
                error: function(err) {
                    console.error('Failed to update stock for warehouse:', err);
                }
            });

            const idx = $tr.data('index');
            if (idx !== undefined && $tr.find(`#batch_select_${idx}`).length) {
                fetchBatchesForLine(productId, warehouseId, idx);
            }
            if (idx !== undefined && $tr.find(`#serial_select_${idx}`).length) {
                fetchSerialsForLine(productId, warehouseId, idx, []);
            }
        });

        $(document).ready(function() {
            const initialSoId = $('#salesOrderId').val();
            if (initialSoId) {
                loadInvoicesForSalesOrder(initialSoId);
            }
        });
    </script>
@endpush
