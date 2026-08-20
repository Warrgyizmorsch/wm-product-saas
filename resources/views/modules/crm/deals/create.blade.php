@extends('layouts.duralux')

@section('title', 'Create CRM Deal | SaaS ERP')
@section('page-title', 'Create New Deal (Project/Opportunity)')
@section('breadcrumb', 'Create Deal')

@push('styles')
    <!-- Select2 Theme Styles -->
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
@endpush

@section('page-actions')
    <a href="{{ route('crm.deals.index') }}" class="btn btn-light">
        <i class="feather-arrow-left me-1"></i>Back to Deals
    </a>
@endsection

@section('content')
    <div class="card border-0 shadow-sm p-4 p-md-5 bg-white mb-4">
        <form action="{{ route('crm.deals.store') }}" method="POST" id="dealCreateForm">
            @csrf

            <!-- Deal Master Details (2-Column Layout matching Leads) -->
            <div class="row g-4 mb-4">
                <!-- Left Column: Company & Contact Details, Deal Overview -->
                <div class="col-lg-6">
                    <h6 class="fw-bold text-primary mb-3"><i class="feather-briefcase me-2"></i>Company & Contact Details</h6>

                    <x-ui.odoo-form-ui type="select" label="Account (Company) *" name="crm_account_id" id="crm_account_id" required="true">
                        <option value="" disabled selected>Select Company Account</option>
                        @foreach($accounts as $acc)
                            <option value="{{ $acc->id }}" {{ (string)old('crm_account_id', $selectedAccountId) === (string)$acc->id ? 'selected' : '' }}>
                                {{ $acc->name }} ({{ $acc->account_number }})
                            </option>
                        @endforeach
                    </x-ui.odoo-form-ui>

                    <x-ui.odoo-form-ui type="select" label="Contact Person (Optional)" name="crm_contact_id" id="crm_contact_id" data-master="contact">
                        <option value="">Select Contact Person</option>
                        <option value="__ADD_NEW__" class="fw-bold text-primary" data-master="contact">+ Add New Contact Person</option>
                        @foreach($contacts as $cnt)
                            <option value="{{ $cnt->id }}" @selected(old('crm_contact_id') == $cnt->id)>
                                {{ $cnt->name }} @if($cnt->role || $cnt->designation) ({{ $cnt->designation ?: $cnt->role }}) @endif
                            </option>
                        @endforeach
                    </x-ui.odoo-form-ui>

                    <x-ui.odoo-form-ui type="select" label="Deal Owner / Manager" name="owner_id">
                        <option value="">Select Deal Owner...</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}" @selected(old('owner_id', auth()->id()) == $user->id)>
                                {{ $user->name }}
                            </option>
                        @endforeach
                    </x-ui.odoo-form-ui>

                    <!-- Additional Contacts Section (Cloned Contacts matching Leads) -->
                    <div class="my-3 border-top pt-3">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <div class="d-flex align-items-center gap-2">
                                <h6 class="fw-bold text-dark mb-0 fs-13">Additional Contacts</h6>
                                <span class="badge bg-soft-primary text-primary rounded-circle px-2 py-0.5 font-monospace fs-11" id="additionalContactsBadge">0</span>
                            </div>
                            <button type="button" class="btn btn-xs btn-primary fw-bold px-2.5 py-1 text-uppercase text-white d-inline-flex align-items-center" id="cloneContactBtn" style="border-radius: 4px; font-size: 11px;">
                                <i class="feather-plus me-1 fs-12"></i> CLONE CONTACT
                            </button>
                        </div>

                        <div id="additionalContactsRepeaterContainer" class="d-flex flex-column gap-2">
                        </div>
                    </div>

                    <h6 class="fw-bold text-primary mb-3 mt-4"><i class="feather-file-text me-2"></i>Deal Overview</h6>

                    <x-ui.odoo-form-ui type="input" label="Project / Deal Title *" name="title" :value="old('title')" required="true" placeholder="e.g. Tiles Supply – ABC Mall Project" />

                    <x-ui.odoo-form-ui type="input" inputType="number" label="Estimated Value (₹) *" name="estimated_value" :value="old('estimated_value', '0.00')" step="0.01" required="true" placeholder="0.00" />
                </div>

                <!-- Right Column: Classification, Notes & Products -->
                <div class="col-lg-6">
                    <h6 class="fw-bold text-primary mb-3"><i class="feather-grid me-2"></i>Deal Classification</h6>

                    <x-ui.odoo-form-ui type="select" label="Pipeline Stage *" name="stage" required="true">
                        @foreach($dealStatuses as $st)
                            <option value="{{ $st->name }}" @selected(old('stage', $dealStatuses->first()?->name) == $st->name)>
                                {{ $st->name }}
                            </option>
                        @endforeach
                    </x-ui.odoo-form-ui>

                    <x-ui.odoo-form-ui type="input" inputType="date" label="Expected Closing Date" name="closing_date" :value="old('closing_date')" />

                    <x-ui.odoo-form-ui type="select" label="Lead Source" name="lead_source">
                        <option value="">Select Lead Source...</option>
                        <option value="Direct Inquiry" {{ old('lead_source') === 'Direct Inquiry' ? 'selected' : '' }}>Direct Inquiry</option>
                        <option value="Website Form" {{ old('lead_source') === 'Website Form' ? 'selected' : '' }}>Website Form</option>
                        <option value="Meta Ads" {{ old('lead_source') === 'Meta Ads' ? 'selected' : '' }}>Meta (Facebook/Instagram) Ads</option>
                        <option value="IndiaMART" {{ old('lead_source') === 'IndiaMART' ? 'selected' : '' }}>IndiaMART</option>
                        <option value="Referral" {{ old('lead_source') === 'Referral' ? 'selected' : '' }}>Client Referral</option>
                        <option value="Cold Call" {{ old('lead_source') === 'Cold Call' ? 'selected' : '' }}>Cold Calling</option>
                    </x-ui.odoo-form-ui>

                    <h6 class="fw-bold text-primary mb-3 mt-4"><i class="feather-file-minus me-2"></i>Requirements & Summary</h6>

                    <x-ui.odoo-form-ui type="textarea" label="Notes & Requirement Summary" name="notes" placeholder="Describe the project scope, specifications, or key customer notes..." />

                    <style>
                        #productItemsTable {
                            table-layout: fixed !important;
                            width: 100% !important;
                        }
                        #productItemsTable td, #productItemsTable th {
                            overflow: hidden !important;
                        }
                        #productItemsTable .select2-container {
                            width: 100% !important;
                            max-width: 100% !important;
                        }
                        #productItemsTable .select2-container .select2-selection--single {
                            height: 32px !important;
                            padding: 2px 8px !important;
                            font-size: 13px !important;
                            border-color: #dee2e6 !important;
                            display: flex !important;
                            align-items: center !important;
                        }
                        #productItemsTable .select2-container .select2-selection--single .select2-selection__rendered {
                            line-height: 26px !important;
                            white-space: nowrap !important;
                            overflow: hidden !important;
                            text-overflow: ellipsis !important;
                            padding-left: 0 !important;
                            padding-right: 18px !important;
                            display: block !important;
                            width: 100% !important;
                        }
                        #productItemsTable .select2-container--bootstrap-5 .select2-selection--single .select2-selection__arrow {
                            height: 30px !important;
                        }
                        #productItemsTable .qty-row-input {
                            height: 32px !important;
                            font-size: 13px !important;
                            font-weight: 600 !important;
                            border-color: #dee2e6 !important;
                            background-color: #ffffff !important;
                            width: 100% !important;
                        }
                        .remove-product-row-btn {
                            transition: transform 0.15s ease-in-out, opacity 0.15s ease-in-out;
                        }
                        .remove-product-row-btn:hover {
                            opacity: 1 !important;
                            transform: scale(1.15);
                        }
                    </style>

                    <div class="mb-3 mt-3" id="productItemsContainer">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label fw-bold text-dark fs-12 mb-0">
                                <i class="feather-package me-1 text-primary"></i>Interested Products & Quantity
                            </label>
                            <button type="button" class="btn btn-xs btn-outline-primary fw-semibold px-2 py-1 fs-11" id="addProductRowBtn" style="border-radius: 6px;">
                                <i class="feather-plus me-1"></i>Add Product
                            </button>
                        </div>
                        
                        <div class="border rounded-3 bg-white p-2 shadow-sm" style="max-height: 270px; overflow-y: auto;">
                            <table class="table table-sm table-borderless align-middle mb-0" id="productItemsTable" style="table-layout: fixed; width: 100%;">
                                <thead>
                                    <tr class="border-bottom text-muted fs-11" style="background-color: #f8fafc;">
                                        <th style="width: 70%; font-weight: 600;" class="py-1 ps-2">Product</th>
                                        <th style="width: 20%; font-weight: 600;" class="py-1 text-center">Qty</th>
                                        <th style="width: 10%; font-weight: 600;" class="py-1 text-center"></th>
                                    </tr>
                                </thead>
                                <tbody id="productItemsBody">
                                    @php
                                        $products = $products ?? \App\Domains\Inventory\Models\Product::sellable()->with('parent')->orderBy('name')->get();
                                        $savedItems = old('items', []);
                                        if (empty($savedItems)) {
                                            $savedItems = [['product_id' => '', 'quantity' => 1]];
                                        }
                                        $finished = $products->filter(fn($p) => $p->type === 'finished_good');
                                        $semiFinished = $products->filter(fn($p) => $p->type === 'semi_finished');
                                        $services = $products->filter(fn($p) => $p->item_type === 'Service' || $p->type === 'service');
                                        $others = $products->filter(fn($p) => !in_array($p->type, ['finished_good', 'semi_finished', 'service']) && $p->item_type !== 'Service');
                                    @endphp

                                    @foreach($savedItems as $idx => $item)
                                        <tr class="lead-item-row border-bottom">
                                            <td class="py-1 ps-1 pe-1 align-top">
                                                <select name="items[{{ $idx }}][product_id]" class="form-select form-select-sm odoo-select2 product-row-select" searchable="true" data-master="product">
                                                    <option value="">Select Product...</option>
                                                    <option value="__ADD_NEW__" class="fw-bold text-primary" data-master="product">+ Add New Product</option>

                                                    @if($finished->count())
                                                        <optgroup label="📦 Finished Goods">
                                                            @foreach($finished as $p)
                                                                @php $pPrice = ($p->selling_price > 0) ? $p->selling_price : (($p->unit_cost > 0) ? $p->unit_cost : ($p->cost_price ?? 0)); @endphp
                                                                <option value="{{ $p->id }}" data-price="{{ $pPrice }}" @selected(($item['product_id'] ?? '') == $p->id)>
                                                                    {{ $p->name }} @if($p->sku) ({{ $p->sku }}) @endif
                                                                </option>
                                                            @endforeach
                                                        </optgroup>
                                                    @endif

                                                    @if($semiFinished->count())
                                                        <optgroup label="⚙️ Semi-Finished Goods">
                                                            @foreach($semiFinished as $p)
                                                                @php $pPrice = ($p->selling_price > 0) ? $p->selling_price : (($p->unit_cost > 0) ? $p->unit_cost : ($p->cost_price ?? 0)); @endphp
                                                                <option value="{{ $p->id }}" data-price="{{ $pPrice }}" @selected(($item['product_id'] ?? '') == $p->id)>
                                                                    {{ $p->name }} @if($p->sku) ({{ $p->sku }}) @endif
                                                                </option>
                                                            @endforeach
                                                        </optgroup>
                                                    @endif

                                                    @if($services->count())
                                                        <optgroup label="🛠️ Services">
                                                            @foreach($services as $p)
                                                                @php $pPrice = ($p->selling_price > 0) ? $p->selling_price : (($p->unit_cost > 0) ? $p->unit_cost : ($p->cost_price ?? 0)); @endphp
                                                                <option value="{{ $p->id }}" data-price="{{ $pPrice }}" @selected(($item['product_id'] ?? '') == $p->id)>
                                                                    {{ $p->name }} @if($p->sku) ({{ $p->sku }}) @endif
                                                                </option>
                                                            @endforeach
                                                        </optgroup>
                                                    @endif

                                                    @if($others->count())
                                                        <optgroup label="🧱 Raw Materials & Components">
                                                            @foreach($others as $p)
                                                                @php $pPrice = ($p->selling_price > 0) ? $p->selling_price : (($p->unit_cost > 0) ? $p->unit_cost : ($p->cost_price ?? 0)); @endphp
                                                                <option value="{{ $p->id }}" data-price="{{ $pPrice }}" @selected(($item['product_id'] ?? '') == $p->id)>
                                                                    {{ $p->name }} @if($p->sku) ({{ $p->sku }}) @endif
                                                                </option>
                                                            @endforeach
                                                        </optgroup>
                                                    @endif
                                                </select>
                                            </td>
                                            <td class="py-1 px-1 align-top">
                                                 <input type="text" inputmode="decimal" autocomplete="off" name="items[{{ $idx }}][quantity]" class="form-control form-control-sm text-center qty-row-input" value="{{ $item['quantity'] ?? 1 }}">
                                            </td>
                                            <td class="py-1 text-center align-top pt-2">
                                                <button type="button" class="btn btn-link text-danger p-0 opacity-75 remove-product-row-btn" title="Remove Product">
                                                    <i class="feather-trash-2 fs-13"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <template id="productRowSelectTemplate">
                        <select class="form-select form-select-sm product-row-select" searchable="true" data-master="product">
                            <option value="">Select Product...</option>
                            <option value="__ADD_NEW__" class="fw-bold text-primary" data-master="product">+ Add New Product</option>

                            @if($finished->count())
                                <optgroup label="📦 Finished Goods">
                                    @foreach($finished as $p)
                                        @php $pPrice = ($p->selling_price > 0) ? $p->selling_price : (($p->unit_cost > 0) ? $p->unit_cost : ($p->cost_price ?? 0)); @endphp
                                        <option value="{{ $p->id }}" data-price="{{ $pPrice }}">{{ $p->name }} @if($p->sku) ({{ $p->sku }}) @endif</option>
                                    @endforeach
                                </optgroup>
                            @endif

                            @if($semiFinished->count())
                                <optgroup label="⚙️ Semi-Finished Goods">
                                    @foreach($semiFinished as $p)
                                        @php $pPrice = ($p->selling_price > 0) ? $p->selling_price : (($p->unit_cost > 0) ? $p->unit_cost : ($p->cost_price ?? 0)); @endphp
                                        <option value="{{ $p->id }}" data-price="{{ $pPrice }}">{{ $p->name }} @if($p->sku) ({{ $p->sku }}) @endif</option>
                                    @endforeach
                                </optgroup>
                            @endif

                            @if($services->count())
                                <optgroup label="🛠️ Services">
                                    @foreach($services as $p)
                                        @php $pPrice = ($p->selling_price > 0) ? $p->selling_price : (($p->unit_cost > 0) ? $p->unit_cost : ($p->cost_price ?? 0)); @endphp
                                        <option value="{{ $p->id }}" data-price="{{ $pPrice }}">{{ $p->name }} @if($p->sku) ({{ $p->sku }}) @endif</option>
                                    @endforeach
                                </optgroup>
                            @endif

                            @if($others->count())
                                <optgroup label="🧱 Raw Materials & Components">
                                    @foreach($others as $p)
                                        @php $pPrice = ($p->selling_price > 0) ? $p->selling_price : (($p->unit_cost > 0) ? $p->unit_cost : ($p->cost_price ?? 0)); @endphp
                                        <option value="{{ $p->id }}" data-price="{{ $pPrice }}">{{ $p->name }} @if($p->sku) ({{ $p->sku }}) @endif</option>
                                    @endforeach
                                </optgroup>
                            @endif
                        </select>
                    </template>
                </div>
            </div>
        </div>

            <div class="d-flex gap-2 justify-content-end border-top pt-4">
                <a href="{{ route('crm.deals.index') }}" class="btn btn-light">Cancel</a>
                <button type="submit" class="btn btn-primary px-4"><i class="feather-check me-1"></i>Save Deal</button>
            </div>
        </form>
    </div>

    <x-ui.master-modals :masters="['product', 'contact']" />
@endsection

@push('scripts')
    <!-- Select2 Vendor & Theme Active JS -->
    <script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/select2-active.min.js') }}"></script>
    <script>
        function loadAccountContacts(accountId, selectedContactId) {
            if (!accountId) return;
            let contactSelect = $('#crm_contact_id');
            $.ajax({
                url: '/crm/accounts/' + accountId + '/contacts-list',
                method: 'GET',
                success: function (res) {
                    if (res.success) {
                        let currentVal = selectedContactId || contactSelect.val();
                        contactSelect.empty();
                        contactSelect.append('<option value="">Select Contact Person</option>');
                        contactSelect.append('<option value="__ADD_NEW__" class="fw-bold text-primary" data-master="contact">+ Add New Contact Person</option>');
                        $.each(res.contacts, function (i, c) {
                            let subInfo = c.designation || c.role || '';
                            let label = c.name + (subInfo ? ' (' + subInfo + ')' : '');
                            let sel = (currentVal && currentVal == c.id) ? 'selected' : '';
                            contactSelect.append(`<option value="${c.id}" ${sel}>${label}</option>`);
                        });
                        contactSelect.trigger('change').trigger('change.select2');
                    }
                }
            });
        }

        $(function () {
            // Register change listener on Account dropdown
            $(document).on('change select2:select', '#crm_account_id', function () {
                let accId = $(this).val();
                if (accId) {
                    loadAccountContacts(accId);
                } else {
                    let contactSelect = $('#crm_contact_id');
                    contactSelect.empty();
                    contactSelect.append('<option value="">Select Company Account First...</option>');
                    contactSelect.append('<option value="__ADD_NEW__" class="fw-bold text-primary" data-master="contact">+ Add New Contact Person</option>');
                    contactSelect.trigger('change.select2');
                }
            });

            // If account pre-selected on load, fetch contacts immediately
            let initAccId = $('#crm_account_id').val();
            if (initAccId) {
                loadAccountContacts(initAccId, '{{ old("crm_contact_id") }}');
            } else {
                let contactSelect = $('#crm_contact_id');
                contactSelect.empty();
                contactSelect.append('<option value="">Select Company Account First...</option>');
                contactSelect.append('<option value="__ADD_NEW__" class="fw-bold text-primary" data-master="contact">+ Add New Contact Person</option>');
                contactSelect.trigger('change.select2');
            }

            // Direct explicit listener for opening Contact Quick Create Modal
            $(document).on('select2:select change', '#crm_contact_id', function (e) {
                var selectedVal = $(this).val();
                if (e && e.params && e.params.data && e.params.data.id === '__ADD_NEW__') {
                    selectedVal = '__ADD_NEW__';
                }
                if (selectedVal === '__ADD_NEW__') {
                    $(this).val('').trigger('change.select2');
                    var accId = $('#crm_account_id').val();
                    if (!accId) {
                        alert('Please select an Account (Company) first before adding a new Contact Person.');
                        return false;
                    }
                    var modalEl = $('#quickCreateModal_contact');
                    if (modalEl.length) {
                        var modalForm = modalEl.find('.quick-create-form');
                        if (modalForm.length) {
                            if (!modalForm.find('input[name="crm_account_id"]').length) {
                                modalForm.append('<input type="hidden" name="crm_account_id" value="' + accId + '">');
                            } else {
                                modalForm.find('input[name="crm_account_id"]').val(accId);
                            }
                        }
                        modalEl.data('trigger-select', $('#crm_contact_id'));
                        if (typeof modalEl.modal === 'function') {
                            modalEl.modal('show');
                        } else if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                            var bsModal = bootstrap.Modal.getOrCreateInstance(modalEl[0]);
                            bsModal.show();
                        }
                    }
                }
            });

            // Inject currently selected crm_account_id into quick create contact modal & guard selection
            $(document).on('show.bs.modal', '#quickCreateModal_contact', function (e) {
                var currentAccId = $('#crm_account_id').val();
                if (!currentAccId) {
                    e.preventDefault();
                    alert('Please select an Account (Company) first before adding a new Contact Person.');
                    return false;
                }
                var modalForm = $(this).find('.quick-create-form');
                if (modalForm.length) {
                    if (!modalForm.find('input[name="crm_account_id"]').length) {
                        modalForm.append('<input type="hidden" name="crm_account_id" value="' + currentAccId + '">');
                    } else {
                        modalForm.find('input[name="crm_account_id"]').val(currentAccId);
                    }
                }
            });

            // Additional Contacts Repeater (matching Leads Create)
            var addlContactIdx = 0;
            function updateContactBadge() {
                var count = $('#additionalContactsRepeaterContainer .addl-contact-card').length;
                $('#additionalContactsBadge').text(count);
            }

            $('#cloneContactBtn').on('click', function (e) {
                e.preventDefault();
                addlContactIdx++;
                var card = `
                    <div class="addl-contact-card p-2 px-3 mb-1 bg-white position-relative shadow-2xs" style="border: 1.5px solid var(--bs-primary) !important; border-radius: 8px !important;">
                        <div class="d-flex align-items-center justify-content-between mb-1 pb-1 border-bottom">
                            <span class="fs-11 fw-bold text-muted text-uppercase letter-spacing-1"><i class="feather-user me-1 text-primary"></i> CONTACT PERSON #<span class="contact-num">${addlContactIdx}</span></span>
                            <button type="button" class="btn btn-xs btn-soft-danger rounded-circle remove-contact-btn p-0 d-inline-flex align-items-center justify-content-center" title="Delete Contact" style="width: 22px; height: 22px; border-radius: 50%;">
                                <i class="feather-trash-2 text-danger fs-11"></i>
                            </button>
                        </div>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <div class="odoo-form-group d-flex align-items-center gap-2">
                                    <label class="odoo-form-label fs-13 text-muted mb-0" style="width: 85px !important; min-width: 85px !important;">Name</label>
                                    <div class="flex-grow-1">
                                        <input type="text" name="additional_contacts[${addlContactIdx}][name]" class="form-control odoo-form-control contact-name-input" placeholder="Contact Name" />
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="odoo-form-group d-flex align-items-center gap-2">
                                    <label class="odoo-form-label fs-13 text-muted mb-0" style="width: 85px !important; min-width: 85px !important;">Designation</label>
                                    <div class="flex-grow-1">
                                        <input type="text" name="additional_contacts[${addlContactIdx}][designation]" class="form-control odoo-form-control contact-designation-input" placeholder="Designation / Role" />
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="odoo-form-group d-flex align-items-center gap-2">
                                    <label class="odoo-form-label fs-13 text-muted mb-0" style="width: 85px !important; min-width: 85px !important;">Phone No.</label>
                                    <div class="flex-grow-1">
                                        <input type="tel" name="additional_contacts[${addlContactIdx}][phone]" class="form-control odoo-form-control contact-phone-input" placeholder="Phone Number" oninput="this.value = this.value.replace(/[^0-9+]/g, '')" />
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="odoo-form-group d-flex align-items-center gap-2">
                                    <label class="odoo-form-label fs-13 text-muted mb-0" style="width: 85px !important; min-width: 85px !important;">Email</label>
                                    <div class="flex-grow-1">
                                        <input type="email" name="additional_contacts[${addlContactIdx}][email]" class="form-control odoo-form-control contact-email-input" placeholder="Email" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                $('#additionalContactsRepeaterContainer').append(card);
                updateContactBadge();
            });

            $(document).on('click', '.remove-contact-btn', function (e) {
                e.preventDefault();
                $(this).closest('.addl-contact-card').remove();
                updateContactBadge();
            });

            // Initialize select2 on product dropdowns
            $('.product-row-select').select2({
                theme: "bootstrap-5",
                width: "100%"
            });

            function updateRemoveButtonsState() {
                let rowCount = $('#productItemsBody tr').length;
                if (rowCount <= 1) {
                    $('#productItemsBody tr .remove-product-row-btn').css({'opacity': '0.4', 'cursor': 'pointer'});
                } else {
                    $('#productItemsBody tr .remove-product-row-btn').css({'opacity': '0.75', 'cursor': 'pointer'});
                }
            }

            updateRemoveButtonsState();
            let itemRowIndex = $('#productItemsBody tr').length;

            $('#addProductRowBtn').on('click', function () {
                let templateHtml = $('#productRowSelectTemplate').html();
                let newSelect = $(templateHtml);
                newSelect.attr('name', 'items[' + itemRowIndex + '][product_id]');

                let newRow = $(`
                    <tr class="lead-item-row border-bottom">
                        <td class="py-1 ps-1 pe-1 align-top"></td>
                        <td class="py-1 px-1 align-top">
                            <input type="text" inputmode="decimal" autocomplete="off" name="items[${itemRowIndex}][quantity]" class="form-control form-control-sm text-center qty-row-input" value="1">
                        </td>
                        <td class="py-1 text-center align-top pt-2">
                            <button type="button" class="btn btn-link text-danger p-0 opacity-75 remove-product-row-btn" title="Remove Product">
                                <i class="feather-trash-2 fs-13"></i>
                            </button>
                        </td>
                    </tr>
                `);

                newRow.find('td:first-child').append(newSelect);
                $('#productItemsBody').append(newRow);

                newSelect.select2({
                    theme: "bootstrap-5",
                    width: "100%"
                });

                itemRowIndex++;
                updateRemoveButtonsState();
            });

            $(document).on('click', '.remove-product-row-btn', function (e) {
                e.preventDefault();
                if ($('#productItemsBody tr').length > 1) {
                    $(this).closest('tr').remove();
                    updateRemoveButtonsState();
                }
            });

            // Prevent invalid characters (minus, plus, e) in quantity inputs
            $(document).on('keydown', '.qty-row-input', function (e) {
                if (['-', '+', 'e', 'E'].includes(e.key)) {
                    e.preventDefault();
                }
            });

            // Sanitize quantity input to allow positive numbers and single decimal dot only, preserving cursor
            $(document).on('input', '.qty-row-input', function () {
                let rawVal = $(this).val();
                if (rawVal) {
                    let cleanVal = rawVal.replace(/[^0-9.]/g, '');
                    let parts = cleanVal.split('.');
                    if (parts.length > 2) {
                        cleanVal = parts[0] + '.' + parts.slice(1).join('');
                    }
                    if (cleanVal !== rawVal) {
                        $(this).val(cleanVal);
                    }
                }
            });

            function calculateAutoExpectedRevenue() {
                let grandTotal = 0;
                let hasProductSelected = false;

                $('#productItemsBody tr.lead-item-row').each(function() {
                    let select = $(this).find('select.product-row-select');
                    let qtyInput = $(this).find('input.qty-row-input');
                    let selectedOpt = select.find('option:selected');
                    let price = parseFloat(selectedOpt.attr('data-price')) || 0;
                    let qty = parseFloat(qtyInput.val()) || 0;

                    if (selectedOpt.val() && selectedOpt.val() !== '__ADD_NEW__') {
                        hasProductSelected = true;
                        grandTotal += (price * qty);
                    }
                });

                let revenueInput = $('#expected_amount, input[name="expected_amount"], #estimated_value, input[name="estimated_value"], #expected_revenue, input[name="expected_revenue"]');
                if (revenueInput.length && hasProductSelected) {
                    revenueInput.val(grandTotal > 0 ? grandTotal.toFixed(2) : '0.00');
                }
            }

            $(document).on('change change.select2 select2:select', 'select.product-row-select', function() {
                calculateAutoExpectedRevenue();
            });

            $(document).on('input change keyup', 'input.qty-row-input', function() {
                calculateAutoExpectedRevenue();
            });

            $('#addProductRowBtn').on('click', function () {
                setTimeout(calculateAutoExpectedRevenue, 60);
            });

            $(document).on('click', '.remove-product-row-btn', function () {
                setTimeout(calculateAutoExpectedRevenue, 60);
            });

            setTimeout(calculateAutoExpectedRevenue, 100);
        });
    </script>
@endpush
