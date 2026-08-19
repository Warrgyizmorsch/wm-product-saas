@extends('layouts.duralux')

@section('title', 'Create CRM Account | SaaS ERP')
@section('page-title', 'Create New Account (Company)')
@section('breadcrumb', 'Create Account')

@section('page-actions')
    <a href="{{ route('crm.accounts.index') }}" class="btn btn-light border p-2 d-inline-flex align-items-center justify-content-center" title="Back to Accounts">
        <i class="feather-arrow-left fs-16"></i>
    </a>
@endsection

@section('content')
    <div class="erp-single-panel bg-white p-4 p-md-5 rounded-3 border shadow-sm">
        <form action="{{ route('crm.accounts.store') }}" method="POST" id="accountForm" class="odoo-sheet">
                @csrf

                {{-- Header Top Bar (Matches Leads Form 100%) --}}
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 border-bottom pb-3 gap-2">
                    <div class="d-flex align-items-center gap-3">
                        <div class="avatar-text avatar-md bg-soft-primary text-primary rounded-3 fs-18">
                            <i class="feather-briefcase"></i>
                        </div>
                        <div>
                            <h4 class="fw-bold text-dark mb-0">Create New Account (Company)</h4>
                            <span class="text-muted fs-12">Register a new B2B company account and primary contact details in CRM.</span>
                        </div>
                    </div>
                </div>

                <div class="row g-4 mb-4 fs-13 text-dark">
                    {{-- Left Column: Company Master Details --}}
                    <div class="col-lg-6 border-end pe-lg-4">
                        <h6 class="fw-bold text-primary mb-3"><i class="feather-briefcase me-2"></i>Company Master Details</h6>
                        
                        <x-ui.odoo-form-ui type="input" label="Company Name" name="name" :value="old('name')" :required="true" placeholder="e.g. ABC Builders Pvt Ltd" />

                        <x-ui.odoo-form-ui type="input" label="GSTIN / Tax Registration No." name="gstin" :value="old('gstin')" placeholder="e.g. 27AAAAA0000A1Z5" />

                        <x-ui.odoo-form-ui type="input" inputType="email" label="Company Email" name="email" :value="old('email')" placeholder="info@company.com" />

                        <x-ui.odoo-form-ui type="input" label="Company Phone / Landline" name="phone" :value="old('phone')" placeholder="022-40001122" />

                        <x-ui.odoo-form-ui type="input" label="Website URL" name="website" :value="old('website')" placeholder="https://company.com" />

                        <x-ui.odoo-form-ui type="input" label="Industry Type" name="industry_type" :value="old('industry_type')" placeholder="e.g. Construction / Infrastructure" />

                        <x-ui.odoo-form-ui type="input" inputType="number" label="Credit Limit (₹)" name="credit_limit" :value="old('credit_limit', '0.00')" step="0.01" placeholder="0.00" />

                        <x-ui.odoo-form-ui type="select" label="Account Manager / Owner" name="owner_id">
                            <option value="">Select Account Manager...</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}" @selected(old('owner_id', auth()->id()) == $user->id)>
                                    {{ $user->name }}
                                </option>
                            @endforeach
                        </x-ui.odoo-form-ui>
                    </div>

                    {{-- Right Column: Primary Contact & Address Details --}}
                    <div class="col-lg-6 ps-lg-4">
                        <h6 class="fw-bold text-primary mb-3"><i class="feather-user me-2"></i>Primary Contact Person</h6>
                        
                        <x-ui.odoo-form-ui type="input" label="Contact Name" name="contact_name" :value="old('contact_name')" placeholder="e.g. Rahul Sharma" />

                        <x-ui.odoo-form-ui type="input" label="Designation / Title" name="designation" :value="old('designation')" placeholder="e.g. Purchase Head" />

                        <x-ui.odoo-form-ui type="select" label="Buying Center Role" name="role">
                            <option value="Purchase Decision Maker" selected>Purchase Decision Maker</option>
                            <option value="Technical Evaluator">Technical Evaluator</option>
                            <option value="Finance">Finance / Accounts</option>
                            <option value="Influencer">Influencer</option>
                            <option value="End User">End User</option>
                        </x-ui.odoo-form-ui>

                        <x-ui.odoo-form-ui type="input" inputType="email" label="Contact Work Email" name="contact_email" :value="old('contact_email')" placeholder="rahul@company.com" />

                        <x-ui.odoo-form-ui type="input" label="Contact Mobile Number" name="contact_phone" :value="old('contact_phone')" placeholder="9876543210" />

                        <div class="border-top pt-4 mt-4">
                            <h6 class="fw-bold text-primary mb-3"><i class="feather-map-pin me-2"></i>Address & Location</h6>
                            
                            <x-ui.odoo-form-ui type="input" label="Street Address" name="street" :value="old('street')" placeholder="Building, Street, Landmark" />

                            <x-ui.odoo-form-ui type="input" label="City" name="city" :value="old('city')" placeholder="Mumbai" />

                            <x-ui.odoo-form-ui type="input" label="State" name="state" :value="old('state')" placeholder="Maharashtra" />

                            <x-ui.odoo-form-ui type="input" label="Country" name="country" :value="old('country', 'India')" placeholder="India" />

                            <x-ui.odoo-form-ui type="input" label="Zip / Postal Code" name="zip_code" :value="old('zip_code')" placeholder="400001" />
                        </div>
                    </div>
                </div>

                {{-- Bottom Action Bar --}}
                <div class="d-flex gap-2 justify-content-end border-top pt-3">
                    <a href="{{ route('crm.accounts.index') }}" class="btn btn-light border px-4 py-2 fs-13">CANCEL</a>
                    <button type="submit" form="accountForm" class="btn btn-primary px-4 py-2 fs-13 fw-bold shadow-sm">
                        <i class="feather-check-circle me-1.5"></i>SAVE ACCOUNT
                    </button>
                </div>
            </form>
    </div>
@endsection
