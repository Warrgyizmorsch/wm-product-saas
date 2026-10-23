@extends('layouts.duralux')

@section('title', __('purchase.add_new_supplier') . ' | SaaS ERP')
@section('page-title', __('purchase.add_new_supplier_vendor'))
@section('breadcrumb', __('purchase.supply_chain_purchase_vendors_create'))

@section('page-actions')
    <a href="{{ route('purchase.vendors.index') }}" class="btn btn-light border p-2 d-inline-flex align-items-center justify-content-center" title="{{ __('purchase.back_to_suppliers') }}">
        <i class="feather-arrow-left fs-16"></i>
    </a>
@endsection

@section('content')
    <div class="row">
        <div class="col-12">
            <div class="erp-single-panel bg-white p-4 p-md-5 rounded-3 border shadow-sm">
                <form action="{{ route('purchase.vendors.store') }}" method="POST" id="vendorForm" class="odoo-sheet">
                    @csrf

                    {{-- Top Header Bar (Matches Accounts & Transporters Sheet) --}}
                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 border-bottom pb-3 gap-2">
                        <div class="d-flex align-items-center gap-3">
                            <div class="avatar-text avatar-md bg-soft-primary text-primary rounded-3 fs-18">
                                <i class="feather-user-plus"></i>
                            </div>
                            <div>
                                <h4 class="fw-bold text-dark mb-0">{{ __('purchase.new_supplier_master_sheet') }}</h4>
                                <span class="text-muted fs-12">Procurement vendor master, tax & banking setup</span>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <a href="{{ route('purchase.vendors.index') }}" class="btn btn-light border fw-semibold fs-13 px-3 py-2">{{ __('purchase.cancel') }}</a>
                            <button type="submit" class="btn btn-primary fw-bold fs-13 px-4 py-2 shadow-sm">
                                <i class="feather-check-circle me-1.5"></i>{{ __('purchase.save_supplier_master') }}
                            </button>
                        </div>
                    </div>

                    @if ($errors->any())
                        <div class="alert alert-danger mb-4">
                            <h6 class="fw-bold mb-2"><i class="feather-alert-triangle me-1"></i>{{ __('purchase.validation_errors') }}</h6>
                            <ul class="mb-0 ps-3">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="row g-4 mb-4 fs-13 text-dark">
                        {{-- Left Column: Basic Information, Tax & Addresses --}}
                        <div class="col-lg-6 border-end pe-lg-4">
                            {{-- Section 1: Basic Information --}}
                            <h6 class="fw-bold text-primary mb-3"><i class="feather-info me-2"></i>{{ __('purchase.section_1_basic_supplier_info') }}</h6>

                            <x-ui.odoo-form-ui type="input" :label="__('purchase.supplier_vendor_name')" name="name" id="name" :value="old('name')" placeholder="e.g. Acme Supplies Ltd" :required="true" :errorText="$errors->first('name')" />

                            <x-ui.odoo-form-ui type="input" :label="__('purchase.company_trade_name')" name="company_name" id="company_name" :value="old('company_name')" placeholder="e.g. Acme International Pvt Ltd" :errorText="$errors->first('company_name')" />

                            <x-ui.odoo-form-ui type="input" :label="__('purchase.supplier_code_id')" name="code" id="code" :value="old('code', $autoCode)" placeholder="e.g. VEND-0001" :errorText="$errors->first('code')" />

                            <x-ui.odoo-form-ui type="select" :label="__('purchase.active_status')" name="status" id="status" :required="true" :searchable="false" :errorText="$errors->first('status')">
                                <option value="active" {{ old('status', 'active') === 'active' ? 'selected' : '' }}>{{ __('purchase.active') }}</option>
                                <option value="inactive" {{ old('status') === 'inactive' ? 'selected' : '' }}>{{ __('purchase.inactive') }}</option>
                            </x-ui.odoo-form-ui>

                            {{-- Section 2: Taxation & Statutory Info --}}
                            <div class="border-top pt-4 mt-4">
                                <h6 class="fw-bold text-primary mb-3"><i class="feather-shield me-2"></i>{{ __('purchase.section_2_contact_tax_info') }}</h6>

                                <x-ui.odoo-form-ui type="input" :label="__('purchase.gstin_tax_id')" name="gstin" id="gstin" :value="old('gstin')" placeholder="e.g. 27AAAAA0000A1Z5" :errorText="$errors->first('gstin')" />

                                <x-ui.odoo-form-ui type="input" :label="__('purchase.pan_number')" name="pan" id="pan" :value="old('pan')" placeholder="e.g. ABCDE1234F" :errorText="$errors->first('pan')" />
                            </div>

                            {{-- Section 3: Address Information --}}
                            <div class="border-top pt-4 mt-4">
                                <h6 class="fw-bold text-primary mb-3"><i class="feather-map-pin me-2"></i>{{ __('purchase.section_3_address_info') }}</h6>

                                <x-ui.odoo-form-ui type="textarea" :label="__('purchase.primary_office_address')" name="address" id="address" rows="2" placeholder="{{ __('purchase.placeholder_street_address') }}" :errorText="$errors->first('address')">{{ old('address') }}</x-ui.odoo-form-ui>

                                <x-ui.odoo-form-ui type="textarea" :label="__('purchase.billing_address')" name="billing_address" id="billing_address" rows="2" placeholder="{{ __('purchase.placeholder_billing_address') }}" :errorText="$errors->first('billing_address')">{{ old('billing_address') }}</x-ui.odoo-form-ui>

                                <x-ui.odoo-form-ui type="textarea" :label="__('purchase.shipping_warehouse_address')" name="shipping_address" id="shipping_address" rows="2" placeholder="{{ __('purchase.placeholder_shipping_address') }}" :errorText="$errors->first('shipping_address')">{{ old('shipping_address') }}</x-ui.odoo-form-ui>
                            </div>
                        </div>

                        {{-- Right Column: Contact & Bank / Accounting Setup --}}
                        <div class="col-lg-6 ps-lg-4">
                            {{-- Section 4: Contact Details --}}
                            <h6 class="fw-bold text-primary mb-3"><i class="feather-phone me-2"></i>Primary Contact Details</h6>

                            <x-ui.odoo-form-ui type="input" inputType="tel" :label="__('purchase.phone_mobile_number')" name="phone" id="phone" :value="old('phone')" placeholder="e.g. 02240001122" oninput="this.value = this.value.replace(/[^0-9]/g, '')" maxlength="20" :errorText="$errors->first('phone')" />

                            <x-ui.odoo-form-ui type="input" inputType="email" :label="__('purchase.email_address')" name="email" id="email" :value="old('email')" placeholder="e.g. vendor@acme.com" :errorText="$errors->first('email')" />

                            {{-- Section 5: Bank & Accounting Information --}}
                            <div class="border-top pt-4 mt-4">
                                <h6 class="fw-bold text-primary mb-3"><i class="feather-credit-card me-2"></i>{{ __('purchase.section_4_bank_accounting_setup') }}</h6>

                                <x-ui.odoo-form-ui type="input" :label="__('purchase.bank_name')" name="bank_name" id="bank_name" :value="old('bank_name')" placeholder="e.g. HDFC Bank / ICICI Bank" :errorText="$errors->first('bank_name')" />

                                <x-ui.odoo-form-ui type="input" inputType="text" :label="__('purchase.bank_account_number')" name="account_number" id="account_number" :value="old('account_number')" placeholder="e.g. 50100234567890" oninput="this.value = this.value.replace(/[^0-9]/g, '')" maxlength="50" :errorText="$errors->first('account_number')" />

                                <x-ui.odoo-form-ui type="input" :label="__('purchase.ifsc_swift_code')" name="ifsc_code" id="ifsc_code" :value="old('ifsc_code')" placeholder="e.g. HDFC0001234" :errorText="$errors->first('ifsc_code')" />

                                <x-ui.odoo-form-ui type="select" :label="__('purchase.default_payment_terms')" name="payment_terms" id="payment_terms" :searchable="false" :errorText="$errors->first('payment_terms')">
                                    <option value="">{{ __('purchase.choose_payment_terms') }}</option>
                                    @foreach($paymentTerms as $term)
                                        <option value="{{ $term->name }}" {{ old('payment_terms') == $term->name ? 'selected' : '' }}>
                                            {{ $term->name }} ({{ $term->due_days }} Days)
                                        </option>
                                    @endforeach
                                </x-ui.odoo-form-ui>

                                <x-ui.odoo-form-ui type="input" inputType="number" step="0.01" label="{{ __('purchase.opening_balance') }} ({{ active_currency_symbol() }})" name="opening_balance" id="opening_balance" :value="old('opening_balance', '0.00')" placeholder="0.00" :errorText="$errors->first('opening_balance')" />
                                @include('modules.accounting.partials.opening-balance-side', ['party' => 'vendor'])
                            </div>
                        </div>
                    </div>

                    {{-- Bottom Action Buttons --}}
                    <div class="d-flex align-items-center justify-content-end gap-2 mt-4 pt-3 border-top">
                        <a href="{{ route('purchase.vendors.index') }}" class="btn btn-light border px-4 py-2 fs-13 fw-semibold">
                            {{ __('purchase.cancel') }}
                        </a>
                        <button type="submit" form="vendorForm" class="btn btn-primary fw-bold px-4 py-2 fs-13 shadow-sm">
                            <i class="feather-check-circle me-1.5"></i>{{ __('purchase.save_supplier_master') }}
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
        // Enforce numeric only on phone and account_number
        const numericInputs = document.querySelectorAll('input[name="phone"], input[name="account_number"]');
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
        const emailInputs = document.querySelectorAll('input[name="email"]');
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

        const form = document.getElementById('vendorForm');
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
