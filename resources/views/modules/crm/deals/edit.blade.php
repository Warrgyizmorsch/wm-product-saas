@extends('layouts.duralux')

@section('title', 'Edit Deal | SaaS ERP')
@section('page-title', 'Edit Deal: ' . $deal->title)
@section('breadcrumb', 'Edit Deal')

@section('page-actions')
    <a href="{{ route('crm.deals.show', $deal) }}" class="btn btn-light">
        <i class="feather-arrow-left me-1"></i>Back to Deal
    </a>
@endsection

@section('content')
    <div class="card border-0 shadow-sm p-4 p-md-5 bg-white mb-4">
        <form action="{{ route('crm.deals.update', $deal) }}" method="POST">
            @csrf
            @method('PUT')

            <!-- Deal Master Details -->
            <h6 class="fw-bold text-primary mb-3"><i class="feather-git-branch me-2"></i>Deal Information</h6>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="select" label="Account (Company) *" name="crm_account_id" required="true">
                        @foreach($accounts as $acc)
                            <option value="{{ $acc->id }}" {{ (string)$deal->crm_account_id === (string)$acc->id ? 'selected' : '' }}>
                                {{ $acc->name }} ({{ $acc->account_number }})
                            </option>
                        @endforeach
                    </x-ui.odoo-form-ui>
                </div>
                <div class="col-md-6">
                    <x-ui.odoo-form-ui type="select" label="Contact Person (Optional)" name="crm_contact_id">
                        <option value="">Select Contact Person</option>
                        @foreach($contacts as $cnt)
                            <option value="{{ $cnt->id }}" {{ (string)$deal->crm_contact_id === (string)$cnt->id ? 'selected' : '' }}>
                                {{ $cnt->name }} ({{ $cnt->role ?: $cnt->designation }})
                            </option>
                        @endforeach
                    </x-ui.odoo-form-ui>
                </div>
                <div class="col-md-8">
                    <x-ui.odoo-form-ui type="input" label="Project / Deal Title *" name="title" :value="old('title', $deal->title)" required="true" />
                </div>
                <div class="col-md-4">
                    <x-ui.odoo-form-ui type="input" inputType="number" label="Estimated Value (₹) *" name="estimated_value" :value="old('estimated_value', $deal->estimated_value)" step="0.01" required="true" />
                </div>
                <div class="col-md-4">
                    <x-ui.odoo-form-ui type="select" label="Pipeline Stage *" name="stage" required="true">
                        <option value="Qualification" {{ $deal->stage === 'Qualification' ? 'selected' : '' }}>Qualification (20%)</option>
                        <option value="Needs Analysis" {{ $deal->stage === 'Needs Analysis' ? 'selected' : '' }}>Needs Analysis (40%)</option>
                        <option value="Proposal" {{ $deal->stage === 'Proposal' ? 'selected' : '' }}>Proposal Sent (60%)</option>
                        <option value="Negotiation" {{ $deal->stage === 'Negotiation' ? 'selected' : '' }}>Negotiation (80%)</option>
                        <option value="Closed Won" {{ $deal->stage === 'Closed Won' ? 'selected' : '' }}>Closed Won (100%)</option>
                        <option value="Closed Lost" {{ $deal->stage === 'Closed Lost' ? 'selected' : '' }}>Closed Lost (0%)</option>
                    </x-ui.odoo-form-ui>
                </div>
                <div class="col-md-4">
                    <x-ui.odoo-form-ui type="input" label="Close Reason / Notes" name="close_reason" :value="old('close_reason', $deal->close_reason)" placeholder="e.g. Lowest Price / Lost to Competitor X" />
                </div>
                <div class="col-md-4">
                    <x-ui.odoo-form-ui type="input" inputType="date" label="Expected Closing Date" name="closing_date" :value="old('closing_date', $deal->closing_date ? $deal->closing_date->format('Y-m-d') : '')" />
                </div>
                <div class="col-md-4">
                    <x-ui.odoo-form-ui type="select" label="Lead Source" name="lead_source">
                        <option value="Direct Inquiry" {{ $deal->lead_source === 'Direct Inquiry' ? 'selected' : '' }}>Direct Inquiry</option>
                        <option value="Website Form" {{ $deal->lead_source === 'Website Form' ? 'selected' : '' }}>Website Form</option>
                        <option value="Meta Ads" {{ $deal->lead_source === 'Meta Ads' ? 'selected' : '' }}>Meta (Facebook/Instagram) Ads</option>
                        <option value="IndiaMART" {{ $deal->lead_source === 'IndiaMART' ? 'selected' : '' }}>IndiaMART</option>
                        <option value="Referral" {{ $deal->lead_source === 'Referral' ? 'selected' : '' }}>Client Referral</option>
                        <option value="Cold Call" {{ $deal->lead_source === 'Cold Call' ? 'selected' : '' }}>Cold Calling</option>
                    </x-ui.odoo-form-ui>
                </div>
                <div class="col-md-12">
                    <x-ui.odoo-form-ui type="textarea" label="Notes & Requirement Summary" name="notes" :value="old('notes', $deal->notes)" />
                </div>
            </div>

            <div class="d-flex gap-2 justify-content-end border-top pt-4">
                <a href="{{ route('crm.deals.show', $deal) }}" class="btn btn-light">Cancel</a>
                <button type="submit" class="btn btn-primary px-4"><i class="feather-check me-1"></i>Update Deal</button>
            </div>
        </form>
    </div>
@endsection
