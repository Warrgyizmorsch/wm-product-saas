@extends('layouts.duralux')

@section('title', 'Create CRM Account | SaaS ERP')
@section('page-title', 'Create New Account (Company)')
@section('breadcrumb', 'Create Account')

@section('page-actions')
    <a href="{{ route('crm.accounts.index') }}" class="btn btn-light">
        <i class="feather-arrow-left me-1"></i>Back to Accounts
    </a>
@endsection

@section('content')
    <div class="card border-0 shadow-sm p-4 p-md-5 bg-white mb-4">
        <form action="{{ route('crm.accounts.store') }}" method="POST">
            @csrf

            <!-- Company Master Details -->
            <h6 class="fw-bold text-primary mb-3"><i class="feather-briefcase me-2"></i>Company Master Details</h6>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="input" label="Company Name" name="name" :value="old('name')" required="true" placeholder="e.g. ABC Builders Pvt Ltd" />
                </div>
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="input" label="GSTIN / Tax Registration No." name="gstin" :value="old('gstin')" placeholder="e.g. 27AAAAA0000A1Z5" />
                </div>
                <div class="col-md-4">
                    <x-ui.odoo-form-ui type="input" inputType="email" label="Company Email" name="email" :value="old('email')" placeholder="info@company.com" />
                </div>
                <div class="col-md-4">
                    <x-ui.odoo-form-ui type="input" label="Company Phone / Landline" name="phone" :value="old('phone')" placeholder="022-40001122" />
                </div>
                <div class="col-md-4">
                    <x-ui.odoo-form-ui type="input" label="Website URL" name="website" :value="old('website')" placeholder="https://company.com" />
                </div>
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="input" label="Industry Type" name="industry_type" :value="old('industry_type')" placeholder="e.g. Construction / Infrastructure" />
                </div>
                <div class="col-md-3">
                    <x-ui.odoo-form-ui type="input" inputType="number" label="Credit Limit (₹)" name="credit_limit" :value="old('credit_limit', '0.00')" step="0.01" placeholder="0.00" />
                </div>
                <div class="col-md-3">
                    <x-ui.odoo-form-ui type="select" label="Account Manager / Owner" name="owner_id">
                        <option value="">Select Account Manager...</option>
                        @foreach($users as $user)
                            <option value="{{ $user->id }}" @selected(old('owner_id', auth()->id()) == $user->id)>
                                {{ $user->name }}
                            </option>
                        @endforeach
                    </x-ui.odoo-form-ui>
                </div>
            </div>

            <!-- Primary Contact Details -->
            <div class="border-top pt-4 mb-4">
                <h6 class="fw-bold text-primary mb-3"><i class="feather-user me-2"></i>Primary Contact Person</h6>
                <div class="row g-3">
                    <div class="col-md-4">
                        <x-ui.odoo-form-ui type="input" label="Contact Name" name="contact_name" :value="old('contact_name')" placeholder="e.g. Rahul Sharma" />
                    </div>
                    <div class="col-md-4">
                        <x-ui.odoo-form-ui type="input" label="Designation / Title" name="designation" :value="old('designation')" placeholder="e.g. Purchase Head" />
                    </div>
                    <div class="col-md-4">
                        <x-ui.odoo-form-ui type="select" label="Buying Center Role" name="role">
                            <option value="Purchase Decision Maker" selected>Purchase Decision Maker</option>
                            <option value="Technical Evaluator">Technical Evaluator</option>
                            <option value="Finance">Finance / Accounts</option>
                            <option value="Influencer">Influencer</option>
                            <option value="End User">End User</option>
                        </x-ui.odoo-form-ui>
                    </div>
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="input" inputType="email" label="Contact Work Email" name="contact_email" :value="old('contact_email')" placeholder="rahul@company.com" />
                    </div>
                    <div class="col-md-6">
                        <x-ui.odoo-form-ui type="input" label="Contact Mobile Number" name="contact_phone" :value="old('contact_phone')" placeholder="9876543210" />
                    </div>
                </div>
            </div>

            <!-- Address Details -->
            <div class="border-top pt-4 mb-4">
                <h6 class="fw-bold text-primary mb-3"><i class="feather-map-pin me-2"></i>Address & Location</h6>
                <div class="row g-3">
                    <div class="col-md-12">
                        <x-ui.odoo-form-ui type="input" label="Street Address" name="street" :value="old('street')" placeholder="Building, Street, Landmark" />
                    </div>
                    <div class="col-md-3">
                        <x-ui.odoo-form-ui type="input" label="City" name="city" :value="old('city')" placeholder="Mumbai" />
                    </div>
                    <div class="col-md-3">
                        <x-ui.odoo-form-ui type="input" label="State" name="state" :value="old('state')" placeholder="Maharashtra" />
                    </div>
                    <div class="col-md-3">
                        <x-ui.odoo-form-ui type="input" label="Country" name="country" :value="old('country', 'India')" placeholder="India" />
                    </div>
                    <div class="col-md-3">
                        <x-ui.odoo-form-ui type="input" label="Zip / Postal Code" name="zip_code" :value="old('zip_code')" placeholder="400001" />
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2 justify-content-end border-top pt-4">
                <a href="{{ route('crm.accounts.index') }}" class="btn btn-light">Cancel</a>
                <button type="submit" class="btn btn-primary px-4"><i class="feather-check me-1"></i>Save Account</button>
            </div>
        </form>
    </div>
@endsection
