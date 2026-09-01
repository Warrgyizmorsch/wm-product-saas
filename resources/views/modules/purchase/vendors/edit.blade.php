@extends('layouts.duralux')

@section('title', 'Edit Supplier | ' . $vendor->name)
@section('page-title', 'Edit Supplier / Vendor')
@section('breadcrumb', 'Supply Chain / Purchase / Vendors / Edit')

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <x-ui.button href="{{ route('purchase.vendors.show', $vendor->id) }}" variant="light" icon="feather-eye" class="border">
            View 360° Profile
        </x-ui.button>
        <x-ui.button href="{{ route('purchase.vendors.index') }}" variant="light" icon="feather-arrow-left" class="border">
            Back to Suppliers
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
                            <h5 class="fw-bold text-dark mb-0 me-3"><i class="feather-edit text-primary me-2"></i>Edit Supplier: {{ $vendor->name }}</h5>
                            <span class="badge bg-soft-primary text-primary px-2.5 py-1 fw-bold fs-11 font-monospace">{{ $vendor->code }}</span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <a href="{{ route('purchase.vendors.show', $vendor->id) }}" class="btn btn-light border fw-semibold">Cancel</a>
                            <button type="submit" class="btn btn-primary fw-bold px-4 py-2">
                                <i class="feather-save me-1"></i>Update Supplier Master
                            </button>
                        </div>
                    </div>

                    <div class="p-4 p-md-5">
                        @if ($errors->any())
                            <div class="alert alert-danger mb-4">
                                <h6 class="fw-bold mb-2"><i class="feather-alert-triangle me-1"></i>Validation Errors:</h6>
                                <ul class="mb-0 ps-3">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <!-- Section 1: Basic Information -->
                        <h6 class="fw-bold text-primary mb-3"><i class="feather-info me-2"></i>1. Basic Supplier Information</h6>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <x-ui.odoo-form-ui type="input" label="Supplier / Vendor Name" name="name" id="name" value="{{ old('name', $vendor->name) }}" placeholder="e.g. Acme Supplies Ltd" :required="true" :error-text="$errors->first('name')" />
                            </div>
                            <div class="col-md-6">
                                <x-ui.odoo-form-ui type="input" label="Company / Trade Name" name="company_name" id="company_name" value="{{ old('company_name', $vendor->company_name) }}" placeholder="e.g. Acme International Pvt Ltd" :error-text="$errors->first('company_name')" />
                            </div>
                            <div class="col-md-6">
                                <x-ui.odoo-form-ui type="input" label="Supplier Code / ID" name="code" id="code" value="{{ old('code', $vendor->code) }}" placeholder="e.g. VEND-0001" :required="true" :error-text="$errors->first('code')" />
                            </div>
                            <div class="col-md-6">
                                <x-ui.odoo-form-ui type="select" label="Active Status" name="status" id="status" :required="true" :error-text="$errors->first('status')">
                                    <option value="active" {{ old('status', strtolower($vendor->status)) == 'active' ? 'selected' : '' }}>Active</option>
                                    <option value="inactive" {{ old('status', strtolower($vendor->status)) == 'inactive' ? 'selected' : '' }}>Inactive</option>
                                </x-ui.odoo-form-ui>
                            </div>
                        </div>

                        <hr class="my-4">

                        <!-- Section 2: Contact & Tax Info -->
                        <h6 class="fw-bold text-primary mb-3"><i class="feather-phone-call me-2"></i>2. Contact & Tax Information</h6>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <x-ui.odoo-form-ui type="input" inputType="email" label="Email Address" name="email" id="email" value="{{ old('email', $vendor->email) }}" placeholder="e.g. vendor@acme.com" :error-text="$errors->first('email')" />
                            </div>
                            <div class="col-md-6">
                                <x-ui.odoo-form-ui type="input" label="Phone / Mobile Number" name="phone" id="phone" value="{{ old('phone', $vendor->phone) }}" placeholder="e.g. +91 9876543210" :error-text="$errors->first('phone')" />
                            </div>
                            <div class="col-md-6">
                                <x-ui.odoo-form-ui type="input" label="GSTIN / TAX Identification Number" name="gstin" id="gstin" value="{{ old('gstin', $vendor->gstin) }}" placeholder="e.g. 27AAAAA0000A1Z5" :error-text="$errors->first('gstin')" />
                            </div>
                            <div class="col-md-6">
                                <x-ui.odoo-form-ui type="input" label="PAN Number" name="pan" id="pan" value="{{ old('pan', $vendor->pan) }}" placeholder="e.g. ABCDE1234F" :error-text="$errors->first('pan')" />
                            </div>
                        </div>

                        <hr class="my-4">

                        <!-- Section 3: Address Information -->
                        <h6 class="fw-bold text-primary mb-3"><i class="feather-map-pin me-2"></i>3. Address Details</h6>
                        <div class="row g-3 mb-4">
                            <div class="col-md-12">
                                <x-ui.odoo-form-ui type="textarea" label="Primary Office Address" name="address" id="address" rows="2" placeholder="Street Address, City, State, Pincode..." :error-text="$errors->first('address')">{{ old('address', $vendor->address) }}</x-ui.odoo-form-ui>
                            </div>
                            <div class="col-md-6">
                                <x-ui.odoo-form-ui type="textarea" label="Billing Address" name="billing_address" id="billing_address" rows="2" placeholder="Billing Address for Invoicing..." :error-text="$errors->first('billing_address')">{{ old('billing_address', $vendor->billing_address) }}</x-ui.odoo-form-ui>
                            </div>
                            <div class="col-md-6">
                                <x-ui.odoo-form-ui type="textarea" label="Shipping / Dispatch Warehouse Address" name="shipping_address" id="shipping_address" rows="2" placeholder="Dispatch warehouse address..." :error-text="$errors->first('shipping_address')">{{ old('shipping_address', $vendor->shipping_address) }}</x-ui.odoo-form-ui>
                            </div>
                        </div>

                        <hr class="my-4">

                        <!-- Section 4: Bank & Accounting Information -->
                        <h6 class="fw-bold text-primary mb-3"><i class="feather-credit-card me-2"></i>4. Banking & Accounting Setup</h6>
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <x-ui.odoo-form-ui type="input" label="Bank Name" name="bank_name" id="bank_name" value="{{ old('bank_name', $vendor->bank_name) }}" placeholder="e.g. HDFC Bank / ICICI Bank" :error-text="$errors->first('bank_name')" />
                            </div>
                            <div class="col-md-4">
                                <x-ui.odoo-form-ui type="input" label="Bank Account Number" name="account_number" id="account_number" value="{{ old('account_number', $vendor->account_number) }}" placeholder="e.g. 50100234567890" :error-text="$errors->first('account_number')" />
                            </div>
                            <div class="col-md-4">
                                <x-ui.odoo-form-ui type="input" label="IFSC / SWIFT Code" name="ifsc_code" id="ifsc_code" value="{{ old('ifsc_code', $vendor->ifsc_code) }}" placeholder="e.g. HDFC0001234" :error-text="$errors->first('ifsc_code')" />
                            </div>
                            <div class="col-md-6">
                                <x-ui.odoo-form-ui type="select" label="Default Payment Terms" name="payment_terms" id="payment_terms" :error-text="$errors->first('payment_terms')">
                                    <option value="">-- Choose Payment Terms --</option>
                                    <option value="Immediate / Due on Receipt" {{ old('payment_terms', $vendor->payment_terms) == 'Immediate / Due on Receipt' ? 'selected' : '' }}>Immediate / Due on Receipt</option>
                                    <option value="Net 15 Days" {{ old('payment_terms', $vendor->payment_terms) == 'Net 15 Days' ? 'selected' : '' }}>Net 15 Days</option>
                                    <option value="Net 30 Days" {{ old('payment_terms', $vendor->payment_terms) == 'Net 30 Days' ? 'selected' : '' }}>Net 30 Days</option>
                                    <option value="Net 45 Days" {{ old('payment_terms', $vendor->payment_terms) == 'Net 45 Days' ? 'selected' : '' }}>Net 45 Days</option>
                                    <option value="Net 60 Days" {{ old('payment_terms', $vendor->payment_terms) == 'Net 60 Days' ? 'selected' : '' }}>Net 60 Days</option>
                                    <option value="50% Advance, 50% Delivery" {{ old('payment_terms', $vendor->payment_terms) == '50% Advance, 50% Delivery' ? 'selected' : '' }}>50% Advance, 50% Delivery</option>
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="col-md-6">
                                <x-ui.odoo-form-ui type="input" inputType="number" step="0.01" label="Opening Balance ({{ active_currency_symbol() }})" name="opening_balance" id="opening_balance" value="{{ old('opening_balance', $vendor->opening_balance) }}" placeholder="0.00" :error-text="$errors->first('opening_balance')" />
                            </div>
                        </div>

                    </div>
                </x-ui.odoo-form-ui>
            </form>
        </div>
    </div>
@endsection
