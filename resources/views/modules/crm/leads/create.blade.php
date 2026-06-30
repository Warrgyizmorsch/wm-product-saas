@extends('layouts.duralux')

@section('title', $lead->exists ? 'Edit CRM Lead | SaaS ERP' : 'Create CRM Lead | SaaS ERP')
@section('page-title', $lead->exists ? 'Edit Call / Lead' : 'Add New Call / Lead')
@section('breadcrumb', $lead->exists ? 'Edit Lead' : 'Create Lead')

@push('styles')
    <!-- Select2 Theme Styles -->
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
@endpush

@section('page-actions')
    <a href="{{ route('crm.leads.index') }}" class="btn btn-light">
        <i class="feather-arrow-left me-2"></i>Back to Listing
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

                    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">
                        <h3 class="fw-bold text-dark mb-0">{{ $lead->exists ? 'Edit Call / Lead' : 'New Call / Lead' }}</h3>
                        <a href="{{ route('crm.leads.index') }}" class="btn btn-sm btn-light border">Cancel</a>
                    </div>

                    <div class="row g-4 mb-4 fs-13 text-dark">
                        <!-- Left Column: Scheduling, Company, Contact, and Address Details -->
                        <div class="col-lg-6 border-end">
                            <h6 class="fw-bold text-primary mb-3">Call & Contact Information</h6>
                            
                            <x-ui.odoo-form-ui type="input" label="Call Date" name="call_date" id="call_date_picker" :value="old('call_date', $lead->call_date ? $lead->call_date->format('Y-m-d h:i A') : date('Y-m-d h:i A'))" required="true" />

                            <x-ui.odoo-form-ui type="input" label="Company Name" name="company_name" :value="old('company_name', $lead->company_name)" required="true" placeholder="Company Name" />

                            <x-ui.odoo-form-ui type="input" label="Contact Person" name="contact_person" :value="old('contact_person', $lead->contact_person)" placeholder="Contact Person" />

                            <x-ui.odoo-form-ui type="input" label="Contact Email" name="email" inputType="email" :value="old('email', $lead->email)" placeholder="email@address.com" />

                            <x-ui.odoo-form-ui type="input" label="Contact Phone" name="phone" :value="old('phone', $lead->phone)" placeholder="Phone/Mobile" />

                            <x-ui.odoo-form-ui type="select" label="Lead Owner" name="lead_owner_id">
                                <option value="">Select Owner (Unassigned)</option>
                                @foreach ($users as $user)
                                    <option value="{{ $user->id }}" @selected(old('lead_owner_id', $lead->lead_owner_id) == $user->id)>
                                        {{ $user->name }}
                                    </option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                            
                            <h6 class="fw-bold text-primary mb-3 mt-4">Address Details</h6>

                            <x-ui.odoo-form-ui type="textarea" label="Street Address" name="address" rows="3" placeholder="Street address...">{{ old('address', $lead->address) }}</x-ui.odoo-form-ui>

                            <x-ui.odoo-form-ui type="input" label="Country" name="country" :value="old('country', $lead->country)" placeholder="Country" />

                            <x-ui.odoo-form-ui type="input" label="State" name="state" :value="old('state', $lead->state)" placeholder="State" />

                            <x-ui.odoo-form-ui type="input" label="City" name="city" :value="old('city', $lead->city)" placeholder="City" />
                        </div>

                        <!-- Right Column: Lead Requirements, Product, Pricing & Classification -->
                        <div class="col-lg-6">
                            <h6 class="fw-bold text-primary mb-3">Requirements & Pricing</h6>

                            <x-ui.odoo-form-ui type="input" label="Product" name="product" :value="old('product', $lead->product)" placeholder="Interested Product" />

                            <x-ui.odoo-form-ui type="input" label="Expected Amount" name="expected_amount" inputType="number" :value="old('expected_amount', $lead->expected_amount)" min="0" step="0.01" placeholder="Expected Revenue (₹)" />

                            <x-ui.odoo-form-ui type="input" label="Expected Sale" name="expected_sale_date" inputType="date" :value="old('expected_sale_date', $lead->expected_sale_date ? $lead->expected_sale_date->format('Y-m-d') : '')" />

                            <x-ui.odoo-form-ui type="textarea" label="Requirements" name="requirement" rows="4" placeholder="Describe requirements...">{{ old('requirement', $lead->requirement) }}</x-ui.odoo-form-ui>

                            <h6 class="fw-bold text-primary mb-3 mt-4">Lead Classification</h6>

                            <x-ui.odoo-form-ui type="input" label="Industry Type" name="industry_type" :value="old('industry_type', $lead->industry_type)" placeholder="Industry Type" />

                            <x-ui.odoo-form-ui type="select" label="Source" name="source">
                                <option value="">Select Option</option>
                                <option value="Cold Call" @selected(old('source', $lead->source) === 'Cold Call')>Cold Call</option>
                                <option value="Employee Referral" @selected(old('source', $lead->source) === 'Employee Referral')>Employee Referral</option>
                                <option value="Partner" @selected(old('source', $lead->source) === 'Partner')>Partner</option>
                                <option value="Web Search" @selected(old('source', $lead->source) === 'Web Search')>Web Search</option>
                                <option value="Advertisement" @selected(old('source', $lead->source) === 'Advertisement')>Advertisement</option>
                                <option value="Trade Show" @selected(old('source', $lead->source) === 'Trade Show')>Trade Show</option>
                            </x-ui.odoo-form-ui>

                            <x-ui.odoo-form-ui type="select" label="Priority" name="priority">
                                <option value="">Select Option</option>
                                <option value="Low" @selected(old('priority', $lead->priority) === 'Low')>Low</option>
                                <option value="Medium" @selected(old('priority', $lead->priority) === 'Medium')>Medium</option>
                                <option value="High" @selected(old('priority', $lead->priority) === 'High')>High</option>
                                <option value="Urgent" @selected(old('priority', $lead->priority) === 'Urgent')>Urgent</option>
                            </x-ui.odoo-form-ui>

                            <x-ui.odoo-form-ui type="select" label="Segment" name="segment">
                                <option value="">Select Option</option>
                                <option value="SMB" @selected(old('segment', $lead->segment) === 'SMB')>SMB</option>
                                <option value="Mid-Market" @selected(old('segment', $lead->segment) === 'Mid-Market')>Mid-Market</option>
                                <option value="Enterprise" @selected(old('segment', $lead->segment) === 'Enterprise')>Enterprise</option>
                            </x-ui.odoo-form-ui>
                        </div>
                    </div>

                    <!-- Actions Row -->
                    <div class="d-flex justify-content-end gap-3 border-top pt-4">
                        <a href="{{ route('crm.leads.index') }}" class="btn btn-light px-4 py-2 border">
                            Cancel
                        </a>
                        <button type="submit" class="btn btn-primary px-5 py-2 fw-semibold" style="background-color: #714B67; border-color: #714B67;">
                            <i class="feather-check-circle me-2"></i>{{ $lead->exists ? 'Update Lead' : 'Create Lead' }}
                        </button>
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
        });
    </script>
@endpush