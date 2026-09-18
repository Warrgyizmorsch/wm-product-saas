@extends('layouts.duralux')

@section('title', __('crm.add_new_transporter') . ' | Enterprise ERP')
@section('page-title', __('crm.add_new_transporter'))
@section('breadcrumb', __('crm.platform_transporters_create_breadcrumb'))

@section('page-actions')
    <x-ui.button href="{{ route('platform.transporters.index') }}" variant="light" icon="feather-arrow-left" class="border">
        {{ __('crm.back_to_transporters') }}
    </x-ui.button>
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <form action="{{ route('platform.transporters.store') }}" method="POST">
            @csrf

            <x-ui.odoo-form-ui type="sheet" class="shadow-sm rounded border-0">
                <div class="border-bottom py-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div class="d-flex align-items-center">
                        <h5 class="fw-bold text-dark mb-0 me-3">
                            <i class="feather-truck text-primary me-2"></i>{{ __('crm.new_transporter_master_sheet') }}
                        </h5>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <a href="{{ route('platform.transporters.index') }}" class="btn btn-light border fw-semibold">{{ __('crm.cancel') }}</a>
                        <button type="submit" class="btn btn-primary fw-bold px-4 py-2">
                            <i class="feather-save me-1"></i>{{ __('crm.save_transporter_master') }}
                        </button>
                    </div>
                </div>

                <div class="p-4 p-md-5">
                    @if ($errors->any())
                        <div class="alert alert-danger mb-4">
                            <h6 class="fw-bold mb-2"><i class="feather-alert-triangle me-1"></i>{{ __('crm.validation_errors') }}</h6>
                            <ul class="mb-0 ps-3">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <!-- Section 1: Basic Information -->
                    <h6 class="fw-bold text-primary mb-3"><i class="feather-info me-2"></i>{{ __('crm.basic_transporter_info') }}</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input" :label="__('crm.transporter_name_field')" name="name" id="name" value="{{ old('name') }}" placeholder="e.g. V-Trans, TCI Logistics, GATI KWE, Blue Dart" :required="true" :error-text="$errors->first('name')" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input" :label="__('crm.transporter_master_code')" name="code" id="code" value="{{ old('code', $autoCode) }}" placeholder="e.g. TRP-0001" :error-text="$errors->first('code')" />
                        </div>
                        <div class="col-md-4">
                            <x-ui.odoo-form-ui type="input" :label="__('crm.transporter_id_eway')" name="transporter_id" id="transporter_id" value="{{ old('transporter_id') }}" placeholder="e.g. 27AAACM1234F1Z1" :error-text="$errors->first('transporter_id')" />
                        </div>
                        <div class="col-md-4">
                            <x-ui.odoo-form-ui type="select" :label="__('crm.transport_mode')" name="transport_mode" id="transport_mode" :searchable="false" :error-text="$errors->first('transport_mode')">
                                <option value="road" {{ old('transport_mode', 'road') == 'road' ? 'selected' : '' }}>{{ __('crm.road_transport') }}</option>
                                <option value="rail" {{ old('transport_mode') == 'rail' ? 'selected' : '' }}>{{ __('crm.rail_logistics') }}</option>
                                <option value="air" {{ old('transport_mode') == 'air' ? 'selected' : '' }}>{{ __('crm.air_freight') }}</option>
                                <option value="sea" {{ old('transport_mode') == 'sea' ? 'selected' : '' }}>{{ __('crm.sea_cargo') }}</option>
                                <option value="multimodal" {{ old('transport_mode') == 'multimodal' ? 'selected' : '' }}>{{ __('crm.multimodal') }}</option>
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="col-md-4">
                            <x-ui.odoo-form-ui type="select" :label="__('crm.active_status')" name="status" id="status" :required="true" :searchable="false" :error-text="$errors->first('status')">
                                <option value="active" {{ old('status', 'active') == 'active' ? 'selected' : '' }}>{{ __('crm.active') }}</option>
                                <option value="inactive" {{ old('status') == 'inactive' ? 'selected' : '' }}>{{ __('crm.inactive') }}</option>
                            </x-ui.odoo-form-ui>
                        </div>
                    </div>

                    <hr class="my-4">

                    <!-- Section 2: Taxation & Compliance -->
                    <h6 class="fw-bold text-primary mb-3"><i class="feather-shield me-2"></i>{{ __('crm.taxation_compliance') }}</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <x-ui.odoo-form-ui type="input" :label="__('crm.gstin_number_field')" name="gstin" id="gstin" value="{{ old('gstin') }}" placeholder="e.g. 27AAAAA0000A1Z5" :error-text="$errors->first('gstin')" />
                        </div>
                        <div class="col-md-4">
                            <x-ui.odoo-form-ui type="input" :label="__('crm.pan_number_field')" name="pan_number" id="pan_number" value="{{ old('pan_number') }}" placeholder="e.g. ABCDE1234F" :error-text="$errors->first('pan_number')" />
                        </div>
                        <div class="col-md-4">
                            <x-ui.odoo-form-ui type="input" :label="__('crm.sac_code_field')" name="sac_code" id="sac_code" value="{{ old('sac_code', '996511') }}" placeholder="996511" :error-text="$errors->first('sac_code')" />
                        </div>
                        <div class="col-md-4">
                            <x-ui.odoo-form-ui type="input" :label="__('crm.tds_section_field')" name="tds_section" id="tds_section" value="{{ old('tds_section', '194C') }}" placeholder="194C" :error-text="$errors->first('tds_section')" />
                        </div>
                        <div class="col-md-4">
                            <x-ui.odoo-form-ui type="input" inputType="number" step="0.01" :label="__('crm.tds_rate_field')" name="tds_rate" id="tds_rate" value="{{ old('tds_rate', '1.00') }}" placeholder="1.00" :error-text="$errors->first('tds_rate')" />
                        </div>
                        <div class="col-md-4">
                            <x-ui.odoo-form-ui type="input" :label="__('crm.exemption_cert_ref')" name="declaration_reference" id="declaration_reference" value="{{ old('declaration_reference') }}" placeholder="Optional Exemption Cert No." :error-text="$errors->first('declaration_reference')" />
                        </div>
                        <div class="col-12 mt-2">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="has_194c_declaration" value="1" id="has_194c_declaration" {{ old('has_194c_declaration') ? 'checked' : '' }}>
                                <label class="form-check-label fw-bold text-dark fs-13" for="has_194c_declaration">
                                    {{ __('crm.section_194c_declaration') }}
                                </label>
                            </div>
                        </div>
                    </div>

                    <hr class="my-4">

                    <!-- Section 3: Contact & Address Information -->
                    <h6 class="fw-bold text-primary mb-3"><i class="feather-map-pin me-2"></i>{{ __('crm.contact_address') }}</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input" :label="__('crm.primary_office_phone')" name="phone" id="phone" value="{{ old('phone') }}" placeholder="e.g. +91 9876543210" :error-text="$errors->first('phone')" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input" inputType="email" :label="__('crm.official_email_address')" name="email" id="email" value="{{ old('email') }}" placeholder="e.g. dispatch@transporter.com" :error-text="$errors->first('email')" />
                        </div>
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="textarea" :label="__('crm.registered_address')" name="address" id="address" rows="2" placeholder="Full street address..." :error-text="$errors->first('address')">{{ old('address') }}</x-ui.odoo-form-ui>
                        </div>
                        <div class="col-md-4">
                            <x-ui.odoo-form-ui type="input" :label="__('crm.city')" name="city" id="city" value="{{ old('city') }}" :placeholder="__('crm.city')" :error-text="$errors->first('city')" />
                        </div>
                        <div class="col-md-4">
                            <x-ui.odoo-form-ui type="input" :label="__('crm.state')" name="state" id="state" value="{{ old('state') }}" :placeholder="__('crm.state')" :error-text="$errors->first('state')" />
                        </div>
                        <div class="col-md-4">
                            <x-ui.odoo-form-ui type="input" :label="__('crm.pincode')" name="pincode" id="pincode" value="{{ old('pincode') }}" :placeholder="__('crm.pincode')" :error-text="$errors->first('pincode')" />
                        </div>
                    </div>

                    <hr class="my-4">

                    <!-- Section 4: Fleet & Operating Capabilities -->
                    <h6 class="fw-bold text-primary mb-3"><i class="feather-truck me-2"></i>{{ __('crm.fleet_capabilities_routes') }}</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input" :label="__('crm.fleet_types_field')" name="fleet_type" id="fleet_type" value="{{ old('fleet_type') }}" placeholder="e.g. Open Body Trucks, Closed Containers, Trailers, Reefers" :error-text="$errors->first('fleet_type')" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input" :label="__('crm.serviceable_zones_field')" name="serviceable_zones" id="serviceable_zones" value="{{ old('serviceable_zones') }}" placeholder="e.g. North India, Mumbai-Delhi Corridor, All Major Ports" :error-text="$errors->first('serviceable_zones')" />
                        </div>
                    </div>

                    <hr class="my-4">

                    <!-- Section 5: Banking & Accounting Setup -->
                    <h6 class="fw-bold text-primary mb-3"><i class="feather-credit-card me-2"></i>{{ __('crm.banking_accounting_setup') }}</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <x-ui.odoo-form-ui type="input" :label="__('crm.bank_name_field')" name="bank_name" id="bank_name" value="{{ old('bank_name') }}" placeholder="e.g. HDFC Bank / ICICI Bank" :error-text="$errors->first('bank_name')" />
                        </div>
                        <div class="col-md-4">
                            <x-ui.odoo-form-ui type="input" :label="__('crm.branch_name_field')" name="branch_name" id="branch_name" value="{{ old('branch_name') }}" :placeholder="__('crm.branch_name_field')" :error-text="$errors->first('branch_name')" />
                        </div>
                        <div class="col-md-4">
                            <x-ui.odoo-form-ui type="input" :label="__('crm.account_holder_name')" name="account_name" id="account_name" value="{{ old('account_name') }}" :placeholder="__('crm.account_holder_name')" :error-text="$errors->first('account_name')" />
                        </div>
                        <div class="col-md-4">
                            <x-ui.odoo-form-ui type="input" :label="__('crm.bank_account_number')" name="account_number" id="account_number" value="{{ old('account_number') }}" :placeholder="__('crm.bank_account_number')" :error-text="$errors->first('account_number')" />
                        </div>
                        <div class="col-md-4">
                            <x-ui.odoo-form-ui type="input" :label="__('crm.ifsc_swift_code')" name="ifsc_code" id="ifsc_code" value="{{ old('ifsc_code') }}" placeholder="IFSC Code" :error-text="$errors->first('ifsc_code')" />
                        </div>
                        <div class="col-md-4">
                            <x-ui.odoo-form-ui type="input" :label="__('crm.payment_credit_terms_field')" name="payment_terms" id="payment_terms" value="{{ old('payment_terms', 'Net 30 Days') }}" placeholder="Net 30 Days" :error-text="$errors->first('payment_terms')" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input" inputType="number" step="0.01" :label="__('crm.opening_freight_balance') . ' (' . active_currency_symbol() . ')'" name="opening_balance" id="opening_balance" value="{{ old('opening_balance', '0.00') }}" placeholder="0.00" :error-text="$errors->first('opening_balance')" />
                        </div>
                    </div>

                    <hr class="my-4">

                    <!-- Section 6: Key Contact Persons -->
                    <h6 class="fw-bold text-primary mb-3"><i class="feather-users me-2"></i>{{ __('crm.key_contact_person') }}</h6>
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <x-ui.odoo-form-ui type="input" :label="__('crm.coordinator_name')" name="contact_person_name" id="contact_person_name" value="{{ old('contact_person_name') }}" placeholder="e.g. Rahul Sharma" :error-text="$errors->first('contact_person_name')" />
                        </div>
                        <div class="col-md-4">
                            <x-ui.odoo-form-ui type="input" :label="__('crm.direct_phone_number')" name="contact_person_phone" id="contact_person_phone" value="{{ old('contact_person_phone') }}" :placeholder="__('crm.direct_phone_number')" :error-text="$errors->first('contact_person_phone')" />
                        </div>
                        <div class="col-md-4">
                            <x-ui.odoo-form-ui type="input" inputType="email" :label="__('crm.direct_email_address')" name="contact_person_email" id="contact_person_email" value="{{ old('contact_person_email') }}" placeholder="email@domain.com" :error-text="$errors->first('contact_person_email')" />
                        </div>
                    </div>

                </div>
            </x-ui.odoo-form-ui>
        </form>
    </div>
</div>
@endsection
