@extends('layouts.duralux')

@section('title', 'Create CRM Deal | SaaS ERP')
@section('page-title', 'Create New Deal (Project/Opportunity)')
@section('breadcrumb', 'Create Deal')

@section('page-actions')
    <a href="{{ route('crm.deals.index') }}" class="btn btn-light">
        <i class="feather-arrow-left me-1"></i>Back to Deals
    </a>
@endsection

@section('content')
    <div class="card border-0 shadow-sm p-4 p-md-5 bg-white mb-4">
        <form action="{{ route('crm.deals.store') }}" method="POST">
            @csrf

            <!-- Deal Master Details -->
            <h6 class="fw-bold text-primary mb-3"><i class="feather-git-branch me-2"></i>Deal Information</h6>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="select" label="Account (Company) *" name="crm_account_id" required="true">
                        <option value="" disabled selected>Select Company Account</option>
                        @foreach($accounts as $acc)
                            <option value="{{ $acc->id }}" {{ (string)$selectedAccountId === (string)$acc->id ? 'selected' : '' }}>
                                {{ $acc->name }} ({{ $acc->account_number }})
                            </option>
                        @endforeach
                    </x-ui.odoo-form-ui>
                </div>
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="select" label="Contact Person (Optional)" name="crm_contact_id">
                        <option value="" selected>Select Contact Person</option>
                        @foreach($contacts as $cnt)
                            <option value="{{ $cnt->id }}">{{ $cnt->name }} ({{ $cnt->role ?: $cnt->designation }})</option>
                        @endforeach
                    </x-ui.odoo-form-ui>
                </div>
                <div class="col-md-8">
                    <x-ui.odoo-form-ui type="input" label="Project / Deal Title *" name="title" :value="old('title')" required="true" placeholder="e.g. Tiles Supply – ABC Mall Project" />
                </div>
                <div class="col-md-4">
                    <x-ui.odoo-form-ui type="input" inputType="number" label="Estimated Value (₹) *" name="estimated_value" :value="old('estimated_value', '0.00')" step="0.01" required="true" placeholder="0.00" />
                </div>
                <div class="col-md-4">
                    <x-ui.odoo-form-ui type="select" label="Pipeline Stage *" name="stage" required="true">
                        <option value="Qualification" selected>Qualification (20%)</option>
                        <option value="Needs Analysis">Needs Analysis (40%)</option>
                        <option value="Proposal">Proposal Sent (60%)</option>
                        <option value="Negotiation">Negotiation (80%)</option>
                        <option value="Closed Won">Closed Won (100%)</option>
                        <option value="Closed Lost">Closed Lost (0%)</option>
                    </x-ui.odoo-form-ui>
                </div>
                <div class="col-md-4">
                    <x-ui.odoo-form-ui type="input" inputType="date" label="Expected Closing Date" name="closing_date" :value="old('closing_date')" />
                </div>
                <div class="col-md-4">
                    <x-ui.odoo-form-ui type="select" label="Lead Source" name="lead_source">
                        <option value="Direct Inquiry" selected>Direct Inquiry</option>
                        <option value="Website Form">Website Form</option>
                        <option value="Meta Ads">Meta (Facebook/Instagram) Ads</option>
                        <option value="IndiaMART">IndiaMART</option>
                        <option value="Referral">Client Referral</option>
                        <option value="Cold Call">Cold Calling</option>
                    </x-ui.odoo-form-ui>
                </div>
                <div class="col-md-12">
                    <x-ui.odoo-form-ui type="textarea" label="Notes & Requirement Summary" name="notes" placeholder="Describe the project scope, specifications, or key customer notes..." />
                </div>
            </div>

            <div class="d-flex gap-2 justify-content-end border-top pt-4">
                <a href="{{ route('crm.deals.index') }}" class="btn btn-light">Cancel</a>
                <button type="submit" class="btn btn-primary px-4"><i class="feather-check me-1"></i>Save Deal</button>
            </div>
        </form>
    </div>
@endsection
