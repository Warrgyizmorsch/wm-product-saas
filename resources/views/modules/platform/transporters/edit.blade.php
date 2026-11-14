@extends('layouts.duralux')

@section('title', __('crm.edit_transporter_master') . ' | ' . $transporter->name)
@section('page-title', __('crm.edit_transporter_master'))
@section('breadcrumb', __('crm.edit_transporter_master'))

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <a href="{{ route('platform.transporters.show', $transporter) }}" class="btn btn-light border p-2 d-inline-flex align-items-center justify-content-center" title="{{ __('crm.view_360_profile') }}">
            <i class="feather-eye fs-16"></i>
        </a>
        <a href="{{ route('platform.transporters.index') }}" class="btn btn-light border p-2 d-inline-flex align-items-center justify-content-center" title="{{ __('crm.back_to_transporters') }}">
            <i class="feather-arrow-left fs-16"></i>
        </a>
    </div>
@endsection

@section('content')
<div class="row">
    <div class="col-12">
        <div class="erp-single-panel bg-white p-4 p-md-5 rounded-3 border shadow-sm">
            <form action="{{ route('platform.transporters.update', $transporter) }}" method="POST" id="transporterForm" class="odoo-sheet">
                @csrf
                @method('PUT')

                {{-- Header Top Bar --}}
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 border-bottom pb-3 gap-2">
                    <div class="d-flex align-items-center gap-3">
                        <div class="avatar-text avatar-md bg-soft-primary text-primary rounded-3 fs-18">
                            <i class="feather-truck"></i>
                        </div>
                        <div>
                            <h4 class="fw-bold text-dark mb-0">{{ __('crm.edit_transporter_master') }}</h4>
                            <span class="text-muted fs-12">{{ $transporter->name }} ({{ $transporter->code ?? 'TRP-#' . $transporter->id }})</span>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <a href="{{ route('platform.transporters.index') }}" class="btn btn-light border fw-semibold fs-13 px-3 py-2">{{ __('crm.cancel') }}</a>
                        <button type="submit" class="btn btn-primary fw-bold fs-13 px-4 py-2 shadow-sm">
                            <i class="feather-check-circle me-1.5"></i>{{ __('crm.save_changes') }}
                        </button>
                    </div>
                </div>

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

                <div class="row g-4 mb-4 fs-13 text-dark">
                    {{-- Left Column: Basic Information, Taxation & Operating Capabilities --}}
                    <div class="col-lg-6 border-end pe-lg-4">
                        {{-- Section 1: Basic Transporter Information --}}
                        <h6 class="fw-bold text-primary mb-3"><i class="feather-truck me-2"></i>{{ __('crm.basic_transporter_info') }}</h6>

                        <x-ui.odoo-form-ui type="input" :label="__('crm.transporter_name_field')" name="name" id="name" :value="old('name', $transporter->name)" placeholder="e.g. V-Trans, TCI Logistics, Blue Dart" :required="true" :errorText="$errors->first('name')" />

                        <x-ui.odoo-form-ui type="input" :label="__('crm.transporter_master_code')" name="code" id="code" :value="old('code', $transporter->code)" placeholder="e.g. TRP-0001" :errorText="$errors->first('code')" />

                        <x-ui.odoo-form-ui type="input" :label="__('crm.transporter_id_eway')" name="transporter_id" id="transporter_id" :value="old('transporter_id', $transporter->transporter_id)" placeholder="e.g. 27AAACM1234F1Z1" :errorText="$errors->first('transporter_id')" />

                        <x-ui.odoo-form-ui type="select" :label="__('crm.transport_mode')" name="transport_mode" id="transport_mode" :searchable="false" :errorText="$errors->first('transport_mode')">
                            <option value="road" {{ old('transport_mode', $transporter->transport_mode ?? 'road') == 'road' ? 'selected' : '' }}>{{ __('crm.road_transport') }}</option>
                            <option value="rail" {{ old('transport_mode', $transporter->transport_mode) == 'rail' ? 'selected' : '' }}>{{ __('crm.rail_logistics') }}</option>
                            <option value="air" {{ old('transport_mode', $transporter->transport_mode) == 'air' ? 'selected' : '' }}>{{ __('crm.air_freight') }}</option>
                            <option value="sea" {{ old('transport_mode', $transporter->transport_mode) == 'sea' ? 'selected' : '' }}>{{ __('crm.sea_cargo') }}</option>
                            <option value="multimodal" {{ old('transport_mode', $transporter->transport_mode) == 'multimodal' ? 'selected' : '' }}>{{ __('crm.multimodal') }}</option>
                        </x-ui.odoo-form-ui>

                        <x-ui.odoo-form-ui type="select" :label="__('crm.active_status')" name="status" id="status" :required="true" :searchable="false" :errorText="$errors->first('status')">
                            <option value="active" {{ old('status', $transporter->status) == 'active' ? 'selected' : '' }}>{{ __('crm.active') }}</option>
                            <option value="inactive" {{ old('status', $transporter->status) == 'inactive' ? 'selected' : '' }}>{{ __('crm.inactive') }}</option>
                        </x-ui.odoo-form-ui>

                        {{-- Section 2: Taxation & Statutory Compliance --}}
                        <div class="border-top pt-4 mt-4">
                            <h6 class="fw-bold text-primary mb-3"><i class="feather-shield me-2"></i>{{ __('crm.taxation_compliance') }}</h6>

                            <x-ui.odoo-form-ui type="input" :label="__('crm.gstin_number_field')" name="gstin" id="gstin" :value="old('gstin', $transporter->gstin)" placeholder="e.g. 27AAAAA0000A1Z5" :errorText="$errors->first('gstin')" />

                            <x-ui.odoo-form-ui type="input" :label="__('crm.pan_number_field')" name="pan_number" id="pan_number" :value="old('pan_number', $transporter->pan_number)" placeholder="e.g. ABCDE1234F" :errorText="$errors->first('pan_number')" />

                            <x-ui.odoo-form-ui type="input" :label="__('crm.sac_code_field')" name="sac_code" id="sac_code" :value="old('sac_code', $transporter->sac_code ?? '996511')" placeholder="996511" :errorText="$errors->first('sac_code')" />

                            <x-ui.odoo-form-ui type="input" :label="__('crm.tds_section_field')" name="tds_section" id="tds_section" :value="old('tds_section', $transporter->tds_section ?? '194C')" placeholder="194C" :errorText="$errors->first('tds_section')" />

                            <x-ui.odoo-form-ui type="input" inputType="number" step="0.01" :label="__('crm.tds_rate_field')" name="tds_rate" id="tds_rate" :value="old('tds_rate', $transporter->tds_rate ?? '1.00')" placeholder="1.00" :errorText="$errors->first('tds_rate')" />

                            <x-ui.odoo-form-ui type="input" :label="__('crm.exemption_cert_ref')" name="declaration_reference" id="declaration_reference" :value="old('declaration_reference', $transporter->declaration_reference)" placeholder="Optional Exemption Cert No." :errorText="$errors->first('declaration_reference')" />

                            <div class="odoo-form-group mb-2">
                                <label class="odoo-form-label">194C Exemption</label>
                                <div class="flex-grow-1">
                                    <div class="form-check form-switch pt-1">
                                        <input class="form-check-input" type="checkbox" name="has_194c_declaration" value="1" id="has_194c_declaration" {{ old('has_194c_declaration', $transporter->has_194c_declaration) ? 'checked' : '' }}>
                                        <label class="form-check-label fw-semibold text-dark fs-12" for="has_194c_declaration">
                                            {{ __('crm.section_194c_declaration') }}
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Section 3: Fleet & Operating Capabilities --}}
                        <div class="border-top pt-4 mt-4">
                            <h6 class="fw-bold text-primary mb-3"><i class="feather-compass me-2"></i>{{ __('crm.fleet_capabilities_routes') }}</h6>

                            <x-ui.odoo-form-ui type="input" :label="__('crm.fleet_types_field')" name="fleet_type" id="fleet_type" :value="old('fleet_type', $transporter->fleet_type)" placeholder="e.g. Open Body Trucks, Closed Containers, Trailers" :errorText="$errors->first('fleet_type')" />

                            <x-ui.odoo-form-ui type="input" :label="__('crm.serviceable_zones_field')" name="serviceable_zones" id="serviceable_zones" :value="old('serviceable_zones', $transporter->serviceable_zones)" placeholder="e.g. North India, Mumbai-Delhi Corridor, All Major Ports" :errorText="$errors->first('serviceable_zones')" />
                        </div>
                    </div>

                    {{-- Right Column: Contact, Address, Coordinator & Banking Setup --}}
                    <div class="col-lg-6 ps-lg-4">
                        {{-- Section 4: Contact & Address Information --}}
                        <h6 class="fw-bold text-primary mb-3"><i class="feather-phone me-2"></i>{{ __('crm.contact_address') }}</h6>

                        <x-ui.odoo-form-ui type="input" inputType="tel" :label="__('crm.primary_office_phone')" name="phone" id="phone" :value="old('phone', $transporter->phone)" placeholder="e.g. 02240001122" oninput="this.value = this.value.replace(/[^0-9]/g, '')" maxlength="20" :errorText="$errors->first('phone')" />

                        <x-ui.odoo-form-ui type="input" inputType="email" :label="__('crm.official_email_address')" name="email" id="email" :value="old('email', $transporter->email)" placeholder="e.g. dispatch@transporter.com" :errorText="$errors->first('email')" />

                        <x-ui.odoo-form-ui type="textarea" :label="__('crm.registered_address')" name="address" id="address" rows="2" placeholder="Full street address..." :errorText="$errors->first('address')">{{ old('address', $transporter->address) }}</x-ui.odoo-form-ui>

                        <x-ui.odoo-form-ui type="input" :label="__('crm.city')" name="city" id="city" :value="old('city', $transporter->city)" :placeholder="__('crm.city')" :errorText="$errors->first('city')" />

                        <x-ui.odoo-form-ui type="input" :label="__('crm.state')" name="state" id="state" :value="old('state', $transporter->state)" :placeholder="__('crm.state')" :errorText="$errors->first('state')" />

                        <x-ui.odoo-form-ui type="input" inputType="text" :label="__('crm.pincode')" name="pincode" id="pincode" :value="old('pincode', $transporter->pincode)" placeholder="e.g. 400001" oninput="this.value = this.value.replace(/[^0-9]/g, '')" maxlength="10" :errorText="$errors->first('pincode')" />

                        {{-- Section 5: Key Contact Coordinator Person --}}
                        <div class="border-top pt-4 mt-4">
                            <h6 class="fw-bold text-primary mb-3"><i class="feather-user me-2"></i>{{ __('crm.key_contact_person') }}</h6>

                            <x-ui.odoo-form-ui type="input" :label="__('crm.coordinator_name')" name="contact_person_name" id="contact_person_name" :value="old('contact_person_name', $transporter->contact_person_name)" placeholder="e.g. Rahul Sharma" :errorText="$errors->first('contact_person_name')" />

                            <x-ui.odoo-form-ui type="input" inputType="tel" :label="__('crm.direct_phone_number')" name="contact_person_phone" id="contact_person_phone" :value="old('contact_person_phone', $transporter->contact_person_phone)" placeholder="e.g. 9876543210" oninput="this.value = this.value.replace(/[^0-9]/g, '')" maxlength="20" :errorText="$errors->first('contact_person_phone')" />

                            <x-ui.odoo-form-ui type="input" inputType="email" :label="__('crm.direct_email_address')" name="contact_person_email" id="contact_person_email" :value="old('contact_person_email', $transporter->contact_person_email)" placeholder="e.g. rahul@transporter.com" :errorText="$errors->first('contact_person_email')" />
                        </div>

                        {{-- Section 6: Banking & Accounting Setup --}}
                        <div class="border-top pt-4 mt-4">
                            <h6 class="fw-bold text-primary mb-3"><i class="feather-credit-card me-2"></i>{{ __('crm.banking_accounting_setup') }}</h6>

                            <x-ui.odoo-form-ui type="input" :label="__('crm.bank_name_field')" name="bank_name" id="bank_name" :value="old('bank_name', $transporter->bank_name)" placeholder="e.g. HDFC Bank / ICICI Bank" :errorText="$errors->first('bank_name')" />

                            <x-ui.odoo-form-ui type="input" :label="__('crm.branch_name_field')" name="branch_name" id="branch_name" :value="old('branch_name', $transporter->branch_name)" :placeholder="__('crm.branch_name_field')" :errorText="$errors->first('branch_name')" />

                            <x-ui.odoo-form-ui type="input" :label="__('crm.account_holder_name')" name="account_name" id="account_name" :value="old('account_name', $transporter->account_name)" :placeholder="__('crm.account_holder_name')" :errorText="$errors->first('account_name')" />

                            <x-ui.odoo-form-ui type="input" inputType="text" :label="__('crm.bank_account_number')" name="account_number" id="account_number" :value="old('account_number', $transporter->account_number)" placeholder="e.g. 50100234567890" oninput="this.value = this.value.replace(/[^0-9]/g, '')" maxlength="50" :errorText="$errors->first('account_number')" />

                            <x-ui.odoo-form-ui type="input" :label="__('crm.ifsc_swift_code')" name="ifsc_code" id="ifsc_code" :value="old('ifsc_code', $transporter->ifsc_code)" placeholder="e.g. HDFC0001234" :errorText="$errors->first('ifsc_code')" />

                            <x-ui.odoo-form-ui type="input" :label="__('crm.payment_credit_terms_field')" name="payment_terms" id="payment_terms" :value="old('payment_terms', $transporter->payment_terms ?? 'Net 30 Days')" placeholder="Net 30 Days" :errorText="$errors->first('payment_terms')" />

                            <x-ui.odoo-form-ui type="input" inputType="number" step="0.01" :label="__('crm.opening_freight_balance') . ' (' . active_currency_symbol() . ')'" name="opening_balance" id="opening_balance" :value="old('opening_balance', $transporter->opening_balance ?? '0.00')" placeholder="0.00" :errorText="$errors->first('opening_balance')" />
                        </div>
                    </div>
                </div>

                {{-- Bottom Action Bar --}}
                <div class="d-flex gap-2 justify-content-end border-top pt-3">
                    <a href="{{ route('platform.transporters.index') }}" class="btn btn-light border px-4 py-2 fs-13">{{ __('crm.cancel') }}</a>
                    <button type="submit" form="transporterForm" class="btn btn-primary px-4 py-2 fs-13 fw-bold shadow-sm">
                        <i class="feather-check-circle me-1.5"></i>{{ __('crm.save_changes') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Enforce numeric only on phone, contact_person_phone, pincode, account_number
        const numericInputs = document.querySelectorAll('input[name="phone"], input[name="contact_person_phone"], input[name="pincode"], input[name="account_number"]');
        numericInputs.forEach(function (inp) {
            inp.addEventListener('keypress', function (e) {
                if (e.isComposing || e.ctrlKey || e.altKey || e.metaKey) return;
                if (!/[0-9]/.test(e.key) && e.key !== 'Backspace' && e.key !== 'Delete' && e.key !== 'ArrowLeft' && e.key !== 'ArrowRight' && e.key !== 'Tab') {
                    e.preventDefault();
                }
            });
            inp.addEventListener('paste', function (e) {
                setTimeout(function () {
                    inp.value = inp.value.replace(/[^0-9]/g, '');
                }, 10);
            });
        });

        // Inline email validation below the input box (NO popup alert!)
        const emailInputs = document.querySelectorAll('input[name="email"], input[name="contact_person_email"]');
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

        function validateEmailField(inp) {
            const val = inp.value.trim();
            const parentGroup = inp.closest('.flex-grow-1') || inp.parentElement;
            let errEl = parentGroup.querySelector('.email-client-error');

            if (val !== '' && !emailRegex.test(val)) {
                inp.classList.add('is-invalid');
                if (!errEl) {
                    errEl = document.createElement('div');
                    errEl.className = 'invalid-feedback d-block fs-11 mt-1 email-client-error';
                    errEl.textContent = 'Please enter a valid email address.';
                    parentGroup.appendChild(errEl);
                }
                return false;
            } else {
                inp.classList.remove('is-invalid');
                if (errEl) {
                    errEl.remove();
                }
                return true;
            }
        }

        emailInputs.forEach(function (inp) {
            inp.addEventListener('blur', function () {
                validateEmailField(inp);
            });
            inp.addEventListener('input', function () {
                if (inp.classList.contains('is-invalid')) {
                    validateEmailField(inp);
                }
            });
        });

        const form = document.getElementById('transporterForm');
        if (form) {
            form.addEventListener('submit', function (e) {
                let hasErr = false;
                emailInputs.forEach(function (inp) {
                    if (!validateEmailField(inp)) {
                        hasErr = true;
                        inp.focus();
                    }
                });
                if (hasErr) {
                    e.preventDefault();
                    e.stopPropagation();
                }
            });
        }
    });
</script>
@endpush
