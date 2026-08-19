@extends('layouts.duralux')

@section('title', $lead->exists ? __('crm.edit_crm_lead_title') . ' | SaaS ERP' : __('crm.create_crm_lead_title') . ' | SaaS ERP')
@section('page-title', $lead->exists ? __('crm.edit_call_lead') : __('crm.add_new_call_lead'))
@section('breadcrumb', $lead->exists ? __('crm.edit_lead') : __('crm.create_lead'))

@push('styles')
    <!-- Select2 Theme Styles -->
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
@endpush

@section('page-actions')
    <a href="{{ route('crm.leads.index') }}" class="btn btn-light">
        <i class="feather-arrow-left me-2"></i>{{ __('crm.back_to_listing') }}
    </a>
@endsection

@section('content')
    <div class="row">
        <div class="col-12">
            <!-- Professional Flat Form Sheet -->
            <div class="card border-0 shadow-sm p-4 p-md-5 bg-white">
                <form action="{{ $lead->exists ? route('crm.leads.update', $lead->id) : route('crm.leads.store') }}" method="POST" id="leadForm" class="odoo-sheet">
                    @csrf
                    @if ($lead->exists)
                        @method('PUT')
                    @endif

                    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="avatar-text avatar-md bg-soft-primary text-primary rounded-3 fs-18">
                                <i class="feather-user-plus"></i>
                            </div>
                            <div>
                                <h4 class="fw-bold text-dark mb-0">{{ $lead->exists ? __('crm.edit_call_lead') : __('crm.new_call_lead') }}</h4>
                                <span class="text-muted fs-12">Fill in the details below to {{ $lead->exists ? 'update the' : 'create a new' }} CRM Call Lead.</span>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <a href="{{ route('crm.leads.index') }}" class="btn btn-light border px-4 py-2 fs-13">
                                {{ __('crm.cancel') }}
                            </a>
                            <button type="submit" class="btn btn-primary px-4 py-2 fs-13 fw-bold shadow-sm">
                                <i class="feather-check-circle me-1.5"></i>{{ $lead->exists ? __('crm.update_lead') : 'SAVE LEAD' }}
                            </button>
                        </div>
                    </div>

                    <div class="row g-4 mb-4 fs-13 text-dark">
                        <!-- Left Column: Scheduling, Company, Contact, and Address Details -->
                        <div class="col-lg-6 border-end">
                            <!-- B2B vs B2C Segment Toggle -->
                            <div class="mb-4 p-3 bg-soft-primary rounded-3 border border-primary-subtle shadow-2xs">
                                <label class="fw-bold text-dark mb-2 d-block fs-13"><i class="feather-layers me-1 text-primary"></i> Customer Type (Lead Segment):</label>
                                <div class="d-flex gap-4">
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="lead_type" id="lead_type_b2b" value="b2b" {{ old('lead_type', $lead->lead_type ?: 'b2b') === 'b2b' ? 'checked' : '' }} onchange="toggleLeadType('b2b')">
                                        <label class="form-check-label fw-bold text-dark cursor-pointer" for="lead_type_b2b">
                                            🏢 B2B (Business Client)
                                        </label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="lead_type" id="lead_type_b2c" value="b2c" {{ old('lead_type', $lead->lead_type) === 'b2c' ? 'checked' : '' }} onchange="toggleLeadType('b2c')">
                                        <label class="form-check-label fw-bold text-dark cursor-pointer" for="lead_type_b2c">
                                            👤 B2C (Individual Customer)
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <h6 class="fw-bold text-primary mb-3">{{ __('crm.call_contact_information') }}</h6>
                            
                            <x-ui.odoo-form-ui type="input" :label="__('crm.call_date')" name="call_date" id="call_date_picker" :value="old('call_date', $lead->call_date ? $lead->call_date->format('Y-m-d h:i A') : date('Y-m-d h:i A'))" required="true" />

                            <x-ui.odoo-form-ui type="input" :label="__('crm.company_name')" name="company_name" id="company_name_input" :value="old('company_name', $lead->company_name)" :placeholder="__('crm.company_name')" :required="old('lead_type', $lead->lead_type ?: 'b2b') === 'b2b'" :errorText="$errors->first('company_name')" />

                            <x-ui.odoo-form-ui type="input" label="GSTIN / Tax No." name="gstin" id="gstin_input" :value="old('gstin', $lead->gstin ?? '')" placeholder="e.g. 27AAAAA0000A1Z5" :errorText="$errors->first('gstin')" />

                            <x-ui.odoo-form-ui type="input" label="Company Email" name="company_email" id="company_email_input" inputType="email" :value="old('company_email', $lead->company_email ?? '')" placeholder="company@office.com" :required="old('lead_type', $lead->lead_type ?: 'b2b') === 'b2b'" :errorText="$errors->first('company_email')" />

                            <x-ui.odoo-form-ui type="input" label="Company Phone" name="company_phone" id="company_phone_input" :value="old('company_phone', $lead->company_phone ?? '')" placeholder="Company Landline / Phone" oninput="this.value = this.value.replace(/[^0-9]/g, '')" :errorText="$errors->first('company_phone')" />

                            <x-ui.odoo-form-ui type="input" :label="__('crm.contact_person')" name="contact_person" id="contact_person_input" :value="old('contact_person', $lead->contact_person)" :placeholder="__('crm.contact_person')" :required="old('lead_type', $lead->lead_type) === 'b2c'" :errorText="$errors->first('contact_person')" />

                            <x-ui.odoo-form-ui type="input" label="Designation / Role" name="designation" id="designation_input" :value="old('designation', $lead->designation)" placeholder="e.g. Purchase Manager / Doctor" :errorText="$errors->first('designation')" />

                            <x-ui.odoo-form-ui type="input" :label="__('crm.contact_email')" name="email" id="email_input" inputType="email" :value="old('email', $lead->email)" placeholder="email@address.com" :required="old('lead_type', $lead->lead_type) === 'b2c'" :errorText="$errors->first('email')" />

                            <x-ui.odoo-form-ui type="input" :label="__('crm.contact_phone')" name="phone" id="phone_input" :value="old('phone', $lead->phone)" :placeholder="__('crm.contact_phone')" oninput="this.value = this.value.replace(/[^0-9]/g, '')" />

                            <x-ui.odoo-form-ui type="select" :label="__('crm.lead_owner')" name="lead_owner_id">
                                <option value="">{{ __('crm.select_owner_unassigned') }}</option>
                                @foreach ($users as $user)
                                    <option value="{{ $user->id }}" @selected(old('lead_owner_id', $lead->lead_owner_id ?? auth()->id()) == $user->id)>
                                        {{ $user->name }}
                                    </option>
                                @endforeach
                            </x-ui.odoo-form-ui>

                            @php
                                $savedAddlContacts = old('additional_contacts', $lead->additional_contacts ?: []);
                            @endphp

                            <style>
                                .addl-contact-card .odoo-form-label {
                                    width: 85px !important;
                                }
                                .remove-contact-btn {
                                    transition: all 0.2s ease;
                                }
                                .remove-contact-btn:hover {
                                    transform: scale(1.1);
                                }
                            </style>

                            <div class="my-3 border-top pt-3">
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <h6 class="fw-bold text-dark mb-0 fs-13">Additional Contacts</h6>
                                        <span class="badge bg-soft-primary text-primary rounded-circle px-2 py-0.5 font-monospace fs-11" id="addlContactCountBadge">{{ count($savedAddlContacts) }}</span>
                                    </div>
                                    <button type="button" class="btn btn-xs btn-primary fw-bold px-2.5 py-1 text-uppercase text-white d-inline-flex align-items-center" id="cloneContactMainBtn" style="border-radius: 4px; font-size: 11px;">
                                        <i class="feather-plus me-1 fs-12"></i> CLONE CONTACT
                                    </button>
                                </div>

                                <div id="additionalContactsRepeaterContainer" class="d-flex flex-column gap-2">
                                    @forelse($savedAddlContacts as $idx => $ac)
                                        <div class="addl-contact-card p-2 px-3 mb-1 bg-white position-relative shadow-2xs" style="border: 1.5px solid var(--bs-primary) !important; border-radius: 8px !important;">
                                            <div class="d-flex align-items-center justify-content-between mb-1 pb-1 border-bottom">
                                                <span class="fs-11 fw-bold text-muted text-uppercase letter-spacing-1"><i class="feather-user me-1 text-primary"></i> Contact Person #<span class="contact-num">{{ $loop->iteration }}</span></span>
                                                <button type="button" class="btn btn-xs btn-soft-danger rounded-circle remove-contact-btn p-0 d-inline-flex align-items-center justify-content-center" title="Delete Contact" style="width: 22px; height: 22px; border-radius: 50%;">
                                                    <i class="feather-trash-2 text-danger fs-11"></i>
                                                </button>
                                            </div>
                                            <div class="row g-2">
                                                <div class="col-md-6">
                                                    <x-ui.odoo-form-ui type="input" label="Name" name="additional_contacts[{{ $idx }}][name]" :value="$ac['name'] ?? ''" placeholder="Contact Name" class="contact-name-input" />
                                                </div>
                                                <div class="col-md-6">
                                                    <x-ui.odoo-form-ui type="input" label="Designation" name="additional_contacts[{{ $idx }}][designation]" :value="$ac['designation'] ?? ''" placeholder="Designation / Role" class="contact-designation-input" />
                                                </div>
                                                <div class="col-md-6">
                                                    <x-ui.odoo-form-ui type="input" label="Phone No." name="additional_contacts[{{ $idx }}][phone]" :value="$ac['phone'] ?? ''" placeholder="Phone Number" class="contact-phone-input" oninput="this.value = this.value.replace(/[^0-9]/g, '')" />
                                                </div>
                                                <div class="col-md-6">
                                                    <x-ui.odoo-form-ui type="input" label="Email" name="additional_contacts[{{ $idx }}][email]" inputType="email" :value="$ac['email'] ?? ''" placeholder="Email" class="contact-email-input" />
                                                </div>
                                            </div>
                                        </div>
                                    @empty
                                    @endforelse
                                </div>
                            </div>
                            
                            <h6 class="fw-bold text-primary mb-3 mt-4">{{ __('crm.address_details') }}</h6>

                            <x-ui.odoo-form-ui type="textarea" :label="__('crm.street_address')" name="address" rows="3" :placeholder="__('crm.street_address_placeholder')">{{ old('address', $lead->address) }}</x-ui.odoo-form-ui>

                            <x-ui.odoo-form-ui type="input" :label="__('crm.country')" name="country" :value="old('country', $lead->country)" :placeholder="__('crm.country')" />

                            <x-ui.odoo-form-ui type="input" :label="__('crm.state')" name="state" :value="old('state', $lead->state)" :placeholder="__('crm.state')" />

                            <x-ui.odoo-form-ui type="input" :label="__('crm.city')" name="city" :value="old('city', $lead->city)" :placeholder="__('crm.city')" />
                        </div>

                        <!-- Right Column: Requirements, Lead Classification, Products & Revenue -->
                        <div class="col-lg-6">
                            <h6 class="fw-bold text-primary mb-3">{{ __('crm.requirements') }}</h6>

                            <x-ui.odoo-form-ui type="textarea" :label="__('crm.requirements')" name="requirement" rows="3" :placeholder="__('crm.requirements_placeholder')">{{ old('requirement', $lead->requirement) }}</x-ui.odoo-form-ui>

                            <h6 class="fw-bold text-primary mb-3 mt-4">{{ __('crm.lead_classification') }}</h6>

                            <x-ui.odoo-form-ui type="input" :label="__('crm.industry_type')" name="industry_type" :value="old('industry_type', $lead->industry_type)" :placeholder="__('crm.industry_type')" />

                            <x-ui.odoo-form-ui type="select" :label="__('crm.source')" name="source">
                                <option value="">{{ __('crm.select_option') }}</option>
                                <option value="Cold Call" @selected(old('source', $lead->source) === 'Cold Call')>{{ __('crm.sources.Cold Call') }}</option>
                                <option value="Employee Referral" @selected(old('source', $lead->source) === 'Employee Referral')>{{ __('crm.sources.Employee Referral') }}</option>
                                <option value="Partner" @selected(old('source', $lead->source) === 'Partner')>{{ __('crm.sources.Partner') }}</option>
                                <option value="Web Search" @selected(old('source', $lead->source) === 'Web Search')>{{ __('crm.sources.Web Search') }}</option>
                                <option value="Advertisement" @selected(old('source', $lead->source) === 'Advertisement')>{{ __('crm.sources.Advertisement') }}</option>
                                <option value="Trade Show" @selected(old('source', $lead->source) === 'Trade Show')>{{ __('crm.sources.Trade Show') }}</option>
                            </x-ui.odoo-form-ui>

                            <x-ui.odoo-form-ui type="select" :label="__('crm.priority')" name="priority">
                                <option value="">{{ __('crm.select_option') }}</option>
                                <option value="Low" @selected(old('priority', $lead->priority) === 'Low')>{{ __('crm.priorities.Low') }}</option>
                                <option value="Medium" @selected(old('priority', $lead->priority) === 'Medium')>{{ __('crm.priorities.Medium') }}</option>
                                <option value="High" @selected(old('priority', $lead->priority) === 'High')>{{ __('crm.priorities.High') }}</option>
                                <option value="Urgent" @selected(old('priority', $lead->priority) === 'Urgent')>{{ __('crm.priorities.Urgent') }}</option>
                            </x-ui.odoo-form-ui>

                            <x-ui.odoo-form-ui type="select" :label="__('crm.segment')" name="segment">
                                <option value="">{{ __('crm.select_option') }}</option>
                                <option value="SMB" @selected(old('segment', $lead->segment) === 'SMB')>{{ __('crm.segments.SMB') }}</option>
                                <option value="Mid-Market" @selected(old('segment', $lead->segment) === 'Mid-Market')>{{ __('crm.segments.Mid-Market') }}</option>
                                <option value="Enterprise" @selected(old('segment', $lead->segment) === 'Enterprise')>{{ __('crm.segments.Enterprise') }}</option>
                            </x-ui.odoo-form-ui>

                            <!-- Ultra Compact Product & Quantity Repeater Table (On Right Side) -->
                            <style>
                                #productItemsTable {
                                    table-layout: fixed !important;
                                    width: 100% !important;
                                }
                                #productItemsTable .select2-container {
                                    width: 100% !important;
                                }
                                #productItemsTable .select2-container .select2-selection--single {
                                    height: 32px !important;
                                    padding: 2px 8px !important;
                                    font-size: 13px !important;
                                    border-color: #dee2e6 !important;
                                }
                                #productItemsTable .select2-container .select2-selection--single .select2-selection__rendered {
                                    line-height: 26px !important;
                                    white-space: nowrap !important;
                                    overflow: hidden !important;
                                    text-overflow: ellipsis !important;
                                    padding-left: 0 !important;
                                    padding-right: 15px !important;
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
                                }
                                .remove-product-row-btn {
                                    transition: transform 0.15s ease-in-out, opacity 0.15s ease-in-out;
                                }
                                .remove-product-row-btn:hover {
                                    opacity: 1 !important;
                                    transform: scale(1.15);
                                }
                            </style>

                            <div class="mb-3 mt-4" id="productItemsContainer">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <label class="form-label fw-bold text-dark fs-12 mb-0">
                                        <i class="feather-package me-1 text-primary"></i>{{ __('crm.product') }} & Quantity
                                    </label>
                                    <button type="button" class="btn btn-xs btn-outline-primary fw-semibold px-2 py-1 fs-11" id="addProductRowBtn" style="border-radius: 6px;">
                                        <i class="feather-plus me-1"></i>Add Product
                                    </button>
                                </div>
                                
                                <div class="border rounded-3 bg-white p-2 shadow-sm" style="max-height: 270px; overflow-y: auto;">
                                    <table class="table table-sm table-borderless align-middle mb-0" id="productItemsTable">
                                        <thead>
                                            <tr class="border-bottom text-muted fs-11" style="background-color: #f8fafc;">
                                                <th style="width: 65%; font-weight: 600;" class="py-1 ps-2">Product</th>
                                                <th style="width: 23%; font-weight: 600;" class="py-1 text-center">Qty</th>
                                                <th style="width: 12%; font-weight: 600;" class="py-1 text-center"></th>
                                            </tr>
                                        </thead>
                                        <tbody id="productItemsBody">
                                            @php
                                                $savedItems = old('items', $lead->product_items ?? []);
                                                if (empty($savedItems) && !empty($lead->product_ids)) {
                                                    foreach ($lead->product_ids as $pid) {
                                                        $savedItems[] = ['product_id' => $pid, 'quantity' => 1];
                                                    }
                                                }
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
                                                            <option value="__ADD_NEW__" class="fw-bold text-primary" data-master="product">+ {{ __('crm.add_new_product') }}</option>
                                                            
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
                                                        <input type="text" inputmode="decimal" autocomplete="off" name="items[{{ $idx }}][quantity]" class="form-control form-control-sm text-center qty-row-input @error('items.'.$idx.'.quantity') is-invalid @enderror" value="{{ $item['quantity'] ?? 1 }}">
                                                        @error('items.'.$idx.'.quantity')
                                                            <div class="text-danger fs-11 mt-1 fw-semibold text-center qty-error-msg">{{ $message }}</div>
                                                        @enderror
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
                                    <option value="__ADD_NEW__" class="fw-bold text-primary" data-master="product">+ {{ __('crm.add_new_product') }}</option>
                                    
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

                            <x-ui.odoo-form-ui type="input" :label="__('crm.expected_amount')" name="expected_amount" inputType="number" :value="old('expected_amount', $lead->expected_amount)" min="0" step="0.01" :placeholder="__('crm.expected_amount_placeholder')" />

                            <x-ui.odoo-form-ui type="input" :label="__('crm.expected_sale')" name="expected_sale_date" inputType="date" :value="old('expected_sale_date', $lead->expected_sale_date ? $lead->expected_sale_date->format('Y-m-d') : '')" />
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <!-- Select2 Vendor & Theme Active JS -->
    <script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/select2-active.min.js') }}"></script>
    <script>
        $(function () {
            // Initialize dynamic single daterangepicker for Call Date
            $('#call_date_picker').daterangepicker({
                singleDatePicker: true,
                timePicker: true,
                timePickerIncrement: 1,
                locale: {
                    format: 'YYYY-MM-DD hh:mm A'
                }
            });

            // Initialize select2 on the product dropdowns
            $('.product-row-select').select2({
                theme: "bootstrap-5",
                width: "100%"
            });

            // Function to manage remove button state cleanly
            function updateRemoveButtonsState() {
                let rowCount = $('#productItemsBody tr').length;
                if (rowCount <= 1) {
                    $('#productItemsBody tr .remove-product-row-btn')
                        .css({'opacity': '0.4', 'cursor': 'pointer'});
                } else {
                    $('#productItemsBody tr .remove-product-row-btn')
                        .css({'opacity': '0.75', 'cursor': 'pointer'});
                }
            }

            // Run on page load
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
                } else {
                    if (typeof showAppToast === 'function') {
                        showAppToast('warning', 'At least one product item is required.');
                    } else if (typeof Swal !== 'undefined') {
                        Swal.mixin({
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 4000,
                            timerProgressBar: true
                        }).fire({
                            icon: 'warning',
                            title: 'At least one product item is required.'
                        });
                    }
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

            // Live Quantity Validation Listener
            $(document).on('input change', '.qty-row-input', function () {
                let val = parseFloat($(this).val());
                let cell = $(this).closest('td');
                let errDiv = cell.find('.qty-error-msg');

                if (isNaN(val) || val < 1) {
                    $(this).addClass('is-invalid');
                    if (!errDiv.length) {
                        cell.append('<div class="text-danger fs-11 mt-1 fw-semibold text-center qty-error-msg">Minimum quantity 1 is required.</div>');
                    } else {
                        errDiv.removeClass('d-none').text('Minimum quantity 1 is required.');
                    }
                } else {
                    $(this).removeClass('is-invalid');
                    errDiv.addClass('d-none');
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

                let revenueInput = $('#expected_amount, input[name="expected_amount"], #expected_revenue, input[name="expected_revenue"], #estimated_value, input[name="estimated_value"]');
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

            // Additional Contacts Repeater Logic matching reference UI design
            function updateContactNumbersAndNames() {
                var cards = $('#additionalContactsRepeaterContainer .addl-contact-card');
                $('#addlContactCountBadge').text(cards.length);
                cards.each(function(index) {
                    $(this).find('.contact-num').text(index + 1);
                    $(this).find('.contact-name-input').attr('name', 'additional_contacts[' + index + '][name]');
                    $(this).find('.contact-designation-input').attr('name', 'additional_contacts[' + index + '][designation]');
                    $(this).find('.contact-email-input').attr('name', 'additional_contacts[' + index + '][email]');
                    $(this).find('.contact-phone-input').attr('name', 'additional_contacts[' + index + '][phone]');
                });
            }

            function addAddlContactCard(cloneValues) {
                var count = $('#additionalContactsRepeaterContainer .addl-contact-card').length;
                var nameVal = cloneValues ? (cloneValues.name || '') : '';
                var desigVal = cloneValues ? (cloneValues.designation || '') : '';
                var emailVal = cloneValues ? (cloneValues.email || '') : '';
                var phoneVal = cloneValues ? (cloneValues.phone || '') : '';

                var html = `
                    <div class="addl-contact-card p-2 px-3 mb-1 bg-white position-relative shadow-2xs" style="border: 1.5px solid var(--bs-primary) !important; border-radius: 8px !important;">
                        <div class="d-flex align-items-center justify-content-between mb-1 pb-1 border-bottom">
                            <span class="fs-11 fw-bold text-muted text-uppercase letter-spacing-1"><i class="feather-user me-1 text-primary"></i> Contact Person #<span class="contact-num">${count + 1}</span></span>
                            <button type="button" class="btn btn-xs btn-soft-danger rounded-circle remove-contact-btn p-0 d-inline-flex align-items-center justify-content-center" title="Delete Contact" style="width: 22px; height: 22px; border-radius: 50%;">
                                <i class="feather-trash-2 text-danger fs-11"></i>
                            </button>
                        </div>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <div class="odoo-form-group">
                                    <label class="odoo-form-label">Name</label>
                                    <div class="flex-grow-1">
                                        <input type="text" name="additional_contacts[${count}][name]" class="odoo-form-control contact-name-input" value="${nameVal}" placeholder="Contact Name">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="odoo-form-group">
                                    <label class="odoo-form-label">Designation</label>
                                    <div class="flex-grow-1">
                                        <input type="text" name="additional_contacts[${count}][designation]" class="odoo-form-control contact-designation-input" value="${desigVal}" placeholder="Designation / Role">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="odoo-form-group">
                                    <label class="odoo-form-label">Phone No.</label>
                                    <div class="flex-grow-1">
                                        <input type="text" name="additional_contacts[${count}][phone]" class="odoo-form-control contact-phone-input" value="${phoneVal}" placeholder="Phone Number" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="odoo-form-group">
                                    <label class="odoo-form-label">Email</label>
                                    <div class="flex-grow-1">
                                        <input type="email" name="additional_contacts[${count}][email]" class="odoo-form-control contact-email-input" value="${emailVal}" placeholder="Email">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;

                $('#additionalContactsRepeaterContainer').append(html);
                updateContactNumbersAndNames();
            }

            // Main + CLONE CONTACT Button Handler
            $('#cloneContactMainBtn').on('click', function() {
                var lastCard = $('#additionalContactsRepeaterContainer .addl-contact-card').last();
                var values = null;
                if (lastCard.length > 0) {
                    values = {
                        name: lastCard.find('.contact-name-input').val(),
                        email: lastCard.find('.contact-email-input').val(),
                        phone: lastCard.find('.contact-phone-input').val()
                    };
                }
                addAddlContactCard(values);
            });

            // Delete Contact Button Handler
            $(document).on('click', '#additionalContactsRepeaterContainer .remove-contact-btn', function() {
                $(this).closest('.addl-contact-card').remove();
                updateContactNumbersAndNames();
            });

            // --- B2B vs B2C Context-Aware & Dismissal-Smart Duplicate Check Engine ---
            const dismissedMatchKeys = new Set();
            let currentActiveMatchKey = null;

            function showDuplicateModal() {
                const modalEl = document.getElementById('duplicateAccountModal');
                if (!modalEl) return;
                if (typeof $(modalEl).modal === 'function') {
                    $(modalEl).modal('show');
                } else if (window.bootstrap && bootstrap.Modal) {
                    const modalInstance = bootstrap.Modal.getOrCreateInstance(modalEl);
                    modalInstance.show();
                }
            }

            function performDuplicateCheck(blurredFieldName) {
                const leadType = $('input[name="lead_type"]:checked').val() || 'b2b';

                let gstin = '';
                let phone = '';
                let email = '';
                let companyName = '';

                if (leadType === 'b2b') {
                    // In B2B mode, check ONLY using Company fields (Ignore Contact Person fields)
                    const b2bFields = [
                        'company_name', 'company_name_input',
                        'gstin', 'gstin_input',
                        'company_email', 'company_email_input',
                        'company_phone', 'company_phone_input'
                    ];
                    if (blurredFieldName && !b2bFields.includes(blurredFieldName)) {
                        return; // Do NOT check duplicates for Contact Person fields in B2B mode
                    }

                    companyName = ($('input[name="company_name"]').val() || $('#company_name_input').val() || '').trim();
                    gstin = ($('input[name="gstin"]').val() || $('#gstin_input').val() || '').trim();
                    email = ($('input[name="company_email"]').val() || $('#company_email_input').val() || '').trim();
                    phone = ($('input[name="company_phone"]').val() || $('#company_phone_input').val() || '').trim();
                } else {
                    // In B2C mode, check ONLY using Individual Person Contact fields
                    const b2cFields = [
                        'contact_person', 'contact_person_input',
                        'email', 'email_input', 'contact_email',
                        'phone', 'phone_input', 'contact_phone'
                    ];
                    if (blurredFieldName && !b2cFields.includes(blurredFieldName)) {
                        return; // Ignore company fields in B2C mode
                    }

                    companyName = ($('input[name="contact_person"]').val() || $('#contact_person_input').val() || '').trim();
                    email = ($('input[name="email"], input[name="contact_email"]').val() || $('#email_input').val() || '').trim();
                    phone = ($('input[name="phone"], input[name="contact_phone"]').val() || $('#phone_input').val() || '').trim();
                }

                if (!gstin && !phone && !email && !companyName) {
                    return;
                }

                $.ajax({
                    url: '{{ route("crm.leads.checkDuplicate") }}',
                    type: 'POST',
                    data: {
                        _token: '{{ csrf_token() }}',
                        lead_type: leadType,
                        gstin: gstin,
                        phone: phone,
                        email: email,
                        company_name: companyName
                    },
                    success: function(res) {
                        if (res.matched || res.is_duplicate) {
                            const matchKey = res.match_key || (res.matched ? ('acc_' + res.account_id + '_' + res.matched_by) : ('lead_' + (res.lead ? res.lead.id : '') + '_' + res.matched_by));

                            // If user has already closed/dismissed this exact match notification, DO NOT show it again!
                            if (dismissedMatchKeys.has(matchKey)) {
                                return;
                            }

                            currentActiveMatchKey = matchKey;

                            if (res.matched) {
                                $('#dupModalHeaderTitle').html('<i class="feather-alert-triangle me-2 text-warning fs-4"></i>Existing Customer Account Found!');
                                $('#dupModalNotice').html('System detected an existing customer account matching <strong class="text-dark">' + (res.matched_by || '') + '</strong>. To prevent duplicate data, you can create a <strong>New Deal</strong> directly under this Account.');
                                $('#dupAccountNameText').text(res.account_name + ' (' + res.account_number + ')');
                                $('#dupAccountGstinText').text(res.gstin || 'N/A');
                                
                                $('#dupRevenueContainer').html('<span><i class="feather-dollar-sign me-1 text-success"></i>Lifetime Revenue: <strong id="dupAccountRevenueText" class="text-success">₹' + res.lifetime_revenue + '</strong></span>');
                                $('#dupDealsContainer').html('<span><i class="feather-layers me-1 text-info"></i>Active Deals: <strong id="dupAccountDealsText" class="text-dark">' + res.open_deals_count + ' Open Deals</strong></span>');
                                $('#dupDateContainer').html('<span><i class="feather-calendar me-1 text-primary"></i>Last Purchase: <strong id="dupAccountLastPurchaseText" class="text-dark">' + res.last_purchase_date + '</strong></span>');
                                
                                $('#dupViewAccountBtn').html('<i class="feather-external-link me-1"></i>View Account').attr('href', '/crm/accounts/' + res.account_id);
                                $('#dupCreateDealBtn').show().html('<i class="feather-plus me-1"></i>Create New Deal').attr('href', '/crm/deals/create?account_id=' + res.account_id);
                                
                                showDuplicateModal();
                            } else if (res.is_duplicate) {
                                const leadName = res.lead ? (res.lead.company_name || res.lead.contact_person || ('Lead #' + res.lead.id)) : 'Existing Lead';
                                const expAmt = res.lead && res.lead.expected_amount ? parseFloat(res.lead.expected_amount).toFixed(2) : '0.00';
                                const createdAt = res.lead && res.lead.created_at ? (typeof res.lead.created_at === 'string' ? res.lead.created_at.substring(0, 10) : res.lead.created_at) : 'N/A';

                                $('#dupModalHeaderTitle').html('<i class="feather-copy me-2 text-warning fs-4"></i>Duplicate Lead Found!');
                                $('#dupModalNotice').html('<div class="alert alert-warning border-0 shadow-2xs mb-0 py-2 px-3 fs-13"><i class="feather-alert-circle me-1 text-warning"></i>' + (res.message || 'Duplicate Lead Found!') + '</div>');
                                $('#dupAccountNameText').text(leadName);
                                $('#dupAccountGstinText').text('Lead #' + (res.lead ? res.lead.id : ''));
                                
                                $('#dupRevenueContainer').html('<span><i class="feather-dollar-sign me-1 text-success"></i>Expected Amount: <strong id="dupAccountRevenueText" class="text-success">₹' + expAmt + '</strong></span>');
                                $('#dupDealsContainer').html('<span><i class="feather-tag me-1 text-info"></i>Status: <strong id="dupAccountDealsText" class="text-dark">' + (res.lead ? (res.lead.status || 'New') : 'N/A') + '</strong></span>');
                                $('#dupDateContainer').html('<span><i class="feather-calendar me-1 text-primary"></i>Created On: <strong id="dupAccountLastPurchaseText" class="text-dark">' + createdAt + '</strong></span>');

                                $('#dupViewAccountBtn').html('<i class="feather-eye me-1"></i>View Lead').attr('href', '/crm/leads/' + (res.lead ? res.lead.id : ''));
                                $('#dupCreateDealBtn').hide();
                                
                                showDuplicateModal();
                            }
                        }
                    }
                });
            }

            // Track modal close/dismissal so the same duplicate match isn't repeatedly shown
            $('#duplicateAccountModal').on('hidden.bs.modal', function() {
                if (currentActiveMatchKey) {
                    dismissedMatchKeys.add(currentActiveMatchKey);
                    currentActiveMatchKey = null;
                }
            });

            window.toggleLeadType = function(type) {
                var isB2B = (type === 'b2b');

                // 1. Handle Company Fields visibility
                var companyFieldNames = ['company_name', 'gstin', 'company_email', 'company_phone'];
                companyFieldNames.forEach(function(name) {
                    var input = document.querySelector('[name="' + name + '"]');
                    if (input) {
                        var group = input.closest('.odoo-form-group') || input.closest('.mb-3') || input.parentElement;
                        if (group) {
                            if (isB2B) {
                                group.style.setProperty('display', 'flex', 'important');
                            } else {
                                group.style.setProperty('display', 'none', 'important');
                            }
                        }
                    }
                });

                // 2. Company Name & Company Email are REQUIRED in B2B mode, OPTIONAL in B2C mode
                ['company_name', 'company_email'].forEach(function(fieldName) {
                    var inputEl = document.querySelector('[name="' + fieldName + '"]');
                    if (inputEl) {
                        var labelEl = inputEl.closest('.odoo-form-group')?.querySelector('.odoo-form-label');
                        if (isB2B) {
                            inputEl.setAttribute('required', 'required');
                            if (labelEl) {
                                labelEl.style.setProperty('color', '#dc3545', 'important');
                                if (!labelEl.querySelector('.text-danger')) {
                                    labelEl.innerHTML = labelEl.innerHTML.trim() + ' <span class="text-danger">*</span>';
                                }
                            }
                        } else {
                            inputEl.removeAttribute('required');
                            if (labelEl) {
                                labelEl.style.removeProperty('color');
                                var star = labelEl.querySelector('.text-danger');
                                if (star) star.remove();
                            }
                        }
                    }
                });

                // 3. Contact Person & Contact Email are REQUIRED in B2C mode, OPTIONAL in B2B mode
                ['contact_person', 'email'].forEach(function(fieldName) {
                    var inputEl = document.querySelector('[name="' + fieldName + '"]');
                    if (inputEl) {
                        var labelEl = inputEl.closest('.odoo-form-group')?.querySelector('.odoo-form-label');
                        if (!isB2B) {
                            inputEl.setAttribute('required', 'required');
                            if (labelEl) {
                                labelEl.style.setProperty('color', '#dc3545', 'important');
                                if (!labelEl.querySelector('.text-danger')) {
                                    labelEl.innerHTML = labelEl.innerHTML.trim() + ' <span class="text-danger">*</span>';
                                }
                            }
                        } else {
                            inputEl.removeAttribute('required');
                            if (labelEl) {
                                labelEl.style.removeProperty('color');
                                var star = labelEl.querySelector('.text-danger');
                                if (star) star.remove();
                            }
                        }
                    }
                });
            };

            $(document).on('change click', 'input[name="lead_type"]', function() {
                window.toggleLeadType($(this).val());
            });

            // Initial trigger on load
            var initialLeadType = $('input[name="lead_type"]:checked').val() || 'b2b';
            window.toggleLeadType(initialLeadType);

            // Trigger duplicate check strictly on blur event (when user moves out of an input box)
            $(document).on('blur', '#gstin_input, #phone_input, #email_input, #company_name_input, #company_email_input, #company_phone_input, #contact_person_input, input[name="gstin"], input[name="phone"], input[name="email"], input[name="company_name"], input[name="company_email"], input[name="company_phone"], input[name="contact_person"], input[name="contact_email"], input[name="contact_phone"]', function() {
                const fieldName = $(this).attr('name') || $(this).attr('id');
                performDuplicateCheck(fieldName);
            });
        });
    </script>

    <!-- Modal: Duplicate Account / Lead Found Alert -->
    <div class="modal fade" id="duplicateAccountModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered border-0">
            <div class="modal-content shadow-lg rounded-3 border-0">
                <div class="modal-header bg-warning-subtle text-warning-emphasis border-bottom">
                    <h5 class="modal-title fw-bold text-dark d-flex align-items-center" id="dupModalHeaderTitle">
                        <i class="feather-alert-triangle me-2 text-warning fs-4"></i>Existing Customer Found!
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div id="dupModalNotice" class="mb-3">
                        <p class="text-muted fs-13 mb-0">
                            System detected an existing record matching <strong id="dupMatchedByText" class="text-dark"></strong>.
                        </p>
                    </div>

                    <div class="card border border-light-subtle rounded-3 p-3 bg-light mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="fw-bold text-primary mb-0" id="dupAccountNameText">Company Name</h6>
                            <span class="badge bg-white text-dark border font-monospace" id="dupAccountGstinText">GSTIN</span>
                        </div>
                        <div class="row g-2 fs-12 text-muted mt-1">
                            <div class="col-6" id="dupRevenueContainer">
                                <span><i class="feather-dollar-sign me-1 text-success"></i>Revenue: <strong id="dupAccountRevenueText" class="text-success">₹0.00</strong></span>
                            </div>
                            <div class="col-6" id="dupDealsContainer">
                                <span><i class="feather-layers me-1 text-info"></i>Active: <strong id="dupAccountDealsText" class="text-dark">0 Deals</strong></span>
                            </div>
                            <div class="col-12 mt-1" id="dupDateContainer">
                                <span><i class="feather-calendar me-1 text-primary"></i>Date: <strong id="dupAccountLastPurchaseText" class="text-dark">N/A</strong></span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Ignore & Continue</button>
                    <a href="#" id="dupViewAccountBtn" class="btn btn-soft-primary" target="_blank"><i class="feather-external-link me-1"></i>View Record</a>
                    <a href="#" id="dupCreateDealBtn" class="btn btn-warning fw-bold px-3"><i class="feather-plus me-1"></i>Create New Deal</a>
                </div>
            </div>
        </div>
    </div>

    <x-ui.master-modals :masters="['product']" />
@endpush