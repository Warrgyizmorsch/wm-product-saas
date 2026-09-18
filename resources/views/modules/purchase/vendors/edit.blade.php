@extends('layouts.duralux')

@section('title', __('purchase.edit_supplier') . ' | ' . $vendor->name)
@section('page-title', __('purchase.edit_supplier_vendor'))
@section('breadcrumb', __('purchase.supply_chain_purchase_vendors_edit'))

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <x-ui.button href="{{ route('purchase.vendors.show', $vendor->id) }}" variant="light" icon="feather-eye" class="border">
            {{ __('purchase.view_360_profile') }}
        </x-ui.button>
        <x-ui.button href="{{ route('purchase.vendors.index') }}" variant="light" icon="feather-arrow-left" class="border">
            {{ __('purchase.back_to_suppliers') }}
        </x-ui.button>
    </div>
@endsection

@section('content')
    <div class="row">
        <div class="col-12">
            <form action="{{ route('purchase.vendors.update', $vendor->id) }}" method="POST">
                @csrf
                @method('PUT')

                <x-ui.odoo-form-ui type="sheet" class="shadow-sm rounded border-0">
                    <div class="border-bottom py-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div class="d-flex align-items-center">
                            <h5 class="fw-bold text-dark mb-0 me-3"><i class="feather-edit text-primary me-2"></i>{{ __('purchase.edit_supplier') }}: {{ $vendor->name }}</h5>
                            <span class="badge bg-soft-primary text-primary px-2.5 py-1 fw-bold fs-11 font-monospace">{{ $vendor->code }}</span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <a href="{{ route('purchase.vendors.show', $vendor->id) }}" class="btn btn-light border fw-semibold">{{ __('purchase.cancel') }}</a>
                            <button type="submit" class="btn btn-primary fw-bold px-4 py-2">
                                <i class="feather-save me-1"></i>{{ __('purchase.update_supplier_master') }}
                            </button>
                        </div>
                    </div>

                    <div class="p-4 p-md-5">
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

                        <!-- Section 1: Basic Information -->
                        <h6 class="fw-bold text-primary mb-3"><i class="feather-info me-2"></i>{{ __('purchase.section_1_basic_supplier_info') }}</h6>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <x-ui.odoo-form-ui type="input" :label="__('purchase.supplier_vendor_name')" name="name" id="name" value="{{ old('name', $vendor->name) }}" placeholder="e.g. Acme Supplies Ltd" :required="true" :error-text="$errors->first('name')" />
                            </div>
                            <div class="col-md-6">
                                <x-ui.odoo-form-ui type="input" :label="__('purchase.company_trade_name')" name="company_name" id="company_name" value="{{ old('company_name', $vendor->company_name) }}" placeholder="e.g. Acme International Pvt Ltd" :error-text="$errors->first('company_name')" />
                            </div>
                            <div class="col-md-6">
                                <x-ui.odoo-form-ui type="input" :label="__('purchase.supplier_code_id')" name="code" id="code" value="{{ old('code', $vendor->code) }}" placeholder="e.g. VEND-0001" :required="true" :error-text="$errors->first('code')" />
                            </div>
                            <div class="col-md-6">
                                <x-ui.odoo-form-ui type="select" :label="__('purchase.active_status')" name="status" id="status" :required="true" :error-text="$errors->first('status')">
                                    <option value="active" {{ old('status', strtolower($vendor->status)) == 'active' ? 'selected' : '' }}>{{ __('purchase.active') }}</option>
                                    <option value="inactive" {{ old('status', strtolower($vendor->status)) == 'inactive' ? 'selected' : '' }}>{{ __('purchase.inactive') }}</option>
                                </x-ui.odoo-form-ui>
                            </div>
                        </div>

                        <hr class="my-4">

                        <!-- Section 2: Contact & Tax Info -->
                        <h6 class="fw-bold text-primary mb-3"><i class="feather-phone-call me-2"></i>{{ __('purchase.section_2_contact_tax_info') }}</h6>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <x-ui.odoo-form-ui type="input" inputType="email" :label="__('purchase.email_address')" name="email" id="email" value="{{ old('email', $vendor->email) }}" placeholder="e.g. vendor@acme.com" :error-text="$errors->first('email')" />
                            </div>
                            <div class="col-md-6">
                                <x-ui.odoo-form-ui type="input" :label="__('purchase.phone_mobile_number')" name="phone" id="phone" value="{{ old('phone', $vendor->phone) }}" placeholder="e.g. +91 9876543210" :error-text="$errors->first('phone')" />
                            </div>
                            <div class="col-md-6">
                                <x-ui.odoo-form-ui type="input" :label="__('purchase.gstin_tax_id')" name="gstin" id="gstin" value="{{ old('gstin', $vendor->gstin) }}" placeholder="e.g. 27AAAAA0000A1Z5" :error-text="$errors->first('gstin')" />
                            </div>
                            <div class="col-md-6">
                                <x-ui.odoo-form-ui type="input" :label="__('purchase.pan_number')" name="pan" id="pan" value="{{ old('pan', $vendor->pan) }}" placeholder="e.g. ABCDE1234F" :error-text="$errors->first('pan')" />
                            </div>
                        </div>

                        <hr class="my-4">

                        <!-- Section 3: Address Information -->
                        <h6 class="fw-bold text-primary mb-3"><i class="feather-map-pin me-2"></i>{{ __('purchase.section_3_address_info') }}</h6>
                        <div class="row g-3 mb-4">
                            <div class="col-md-12">
                                <x-ui.odoo-form-ui type="textarea" :label="__('purchase.primary_office_address')" name="address" id="address" rows="2" placeholder="{{ __('purchase.placeholder_street_address') }}" :error-text="$errors->first('address')">{{ old('address', $vendor->address) }}</x-ui.odoo-form-ui>
                            </div>
                            <div class="col-md-6">
                                <x-ui.odoo-form-ui type="textarea" :label="__('purchase.billing_address')" name="billing_address" id="billing_address" rows="2" placeholder="{{ __('purchase.placeholder_billing_address') }}" :error-text="$errors->first('billing_address')">{{ old('billing_address', $vendor->billing_address) }}</x-ui.odoo-form-ui>
                            </div>
                            <div class="col-md-6">
                                <x-ui.odoo-form-ui type="textarea" :label="__('purchase.shipping_warehouse_address')" name="shipping_address" id="shipping_address" rows="2" placeholder="{{ __('purchase.placeholder_shipping_address') }}" :error-text="$errors->first('shipping_address')">{{ old('shipping_address', $vendor->shipping_address) }}</x-ui.odoo-form-ui>
                            </div>
                        </div>

                        <hr class="my-4">

                        <!-- Section 4: Bank & Accounting Information -->
                        <h6 class="fw-bold text-primary mb-3"><i class="feather-credit-card me-2"></i>{{ __('purchase.section_4_bank_accounting_setup') }}</h6>
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <x-ui.odoo-form-ui type="input" :label="__('purchase.bank_name')" name="bank_name" id="bank_name" value="{{ old('bank_name', $vendor->bank_name) }}" placeholder="e.g. HDFC Bank / ICICI Bank" :error-text="$errors->first('bank_name')" />
                            </div>
                            <div class="col-md-4">
                                <x-ui.odoo-form-ui type="input" :label="__('purchase.bank_account_number')" name="account_number" id="account_number" value="{{ old('account_number', $vendor->account_number) }}" placeholder="e.g. 50100234567890" :error-text="$errors->first('account_number')" />
                            </div>
                            <div class="col-md-4">
                                <x-ui.odoo-form-ui type="input" :label="__('purchase.ifsc_swift_code')" name="ifsc_code" id="ifsc_code" value="{{ old('ifsc_code', $vendor->ifsc_code) }}" placeholder="e.g. HDFC0001234" :error-text="$errors->first('ifsc_code')" />
                            </div>
                            <div class="col-md-6">
                                <x-ui.odoo-form-ui type="select" :label="__('purchase.default_payment_terms')" name="payment_terms" id="payment_terms" :error-text="$errors->first('payment_terms')">
                                    <option value="">{{ __('purchase.choose_payment_terms') }}</option>
                                    @if(isset($paymentTerms) && count($paymentTerms))
                                        @foreach($paymentTerms as $term)
                                            <option value="{{ $term->name }}" {{ old('payment_terms', $vendor->payment_terms) == $term->name ? 'selected' : '' }}>
                                                {{ $term->name }} ({{ $term->due_days }} Days)
                                            </option>
                                        @endforeach
                                    @else
                                        <option value="Immediate / Due on Receipt" {{ old('payment_terms', $vendor->payment_terms) == 'Immediate / Due on Receipt' ? 'selected' : '' }}>Immediate / Due on Receipt</option>
                                        <option value="Net 15 Days" {{ old('payment_terms', $vendor->payment_terms) == 'Net 15 Days' ? 'selected' : '' }}>Net 15 Days</option>
                                        <option value="Net 30 Days" {{ old('payment_terms', $vendor->payment_terms) == 'Net 30 Days' ? 'selected' : '' }}>Net 30 Days</option>
                                        <option value="Net 45 Days" {{ old('payment_terms', $vendor->payment_terms) == 'Net 45 Days' ? 'selected' : '' }}>Net 45 Days</option>
                                        <option value="Net 60 Days" {{ old('payment_terms', $vendor->payment_terms) == 'Net 60 Days' ? 'selected' : '' }}>Net 60 Days</option>
                                        <option value="50% Advance, 50% Delivery" {{ old('payment_terms', $vendor->payment_terms) == '50% Advance, 50% Delivery' ? 'selected' : '' }}>50% Advance, 50% Delivery</option>
                                    @endif
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="col-md-6">
                                <x-ui.odoo-form-ui type="input" inputType="number" step="0.01" label="{{ __('purchase.opening_balance') }} ({{ active_currency_symbol() }})" name="opening_balance" id="opening_balance" value="{{ old('opening_balance', $vendor->opening_balance) }}" placeholder="0.00" :error-text="$errors->first('opening_balance')" />
                            </div>
                        </div>

                    </div>
                </x-ui.odoo-form-ui>
            </form>
        </div>
    </div>
@endsection

