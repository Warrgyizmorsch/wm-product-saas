@extends('layouts.duralux')

@section('title', $lead->exists ? __('crm.edit_crm_lead_title') . ' | SaaS ERP' : __('crm.create_crm_lead_title') . ' | SaaS ERP')
@section('page-title', $lead->exists ? __('crm.edit_call_lead') : __('crm.add_new_call_lead'))
@section('breadcrumb', $lead->exists ? __('crm.edit_lead') : __('crm.create_lead'))

@push('styles')
    <!-- Select2 Theme Styles -->
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
@endpush

@section('page-actions')
    <a href="{{ route('crm.leads.index') }}" class="btn btn-light">
        <i class="feather-arrow-left me-2"></i>{{ __('crm.back_to_listing') }}
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
                        <h3 class="fw-bold text-dark mb-0">{{ $lead->exists ? __('crm.edit_call_lead') : __('crm.new_call_lead') }}</h3>
                        <a href="{{ route('crm.leads.index') }}" class="btn btn-sm btn-light border">{{ __('crm.cancel') }}</a>
                    </div>

                    <div class="row g-4 mb-4 fs-13 text-dark">
                        <!-- Left Column: Scheduling, Company, Contact, and Address Details -->
                        <div class="col-lg-6 border-end">
                            <h6 class="fw-bold text-primary mb-3">{{ __('crm.call_contact_information') }}</h6>
                            
                            <x-ui.odoo-form-ui type="input" :label="__('crm.call_date')" name="call_date" id="call_date_picker" :value="old('call_date', $lead->call_date ? $lead->call_date->format('Y-m-d h:i A') : date('Y-m-d h:i A'))" required="true" />

                            <x-ui.odoo-form-ui type="input" :label="__('crm.company_name')" name="company_name" :value="old('company_name', $lead->company_name)" required="true" :placeholder="__('crm.company_name')" />

                            <x-ui.odoo-form-ui type="input" :label="__('crm.contact_person')" name="contact_person" :value="old('contact_person', $lead->contact_person)" :placeholder="__('crm.contact_person')" />

                            <x-ui.odoo-form-ui type="input" :label="__('crm.contact_email')" name="email" inputType="email" :value="old('email', $lead->email)" placeholder="email@address.com" />

                            <x-ui.odoo-form-ui type="input" :label="__('crm.contact_phone')" name="phone" :value="old('phone', $lead->phone)" :placeholder="__('crm.contact_phone')" />

                            <x-ui.odoo-form-ui type="select" :label="__('crm.lead_owner')" name="lead_owner_id">
                                <option value="">{{ __('crm.select_owner_unassigned') }}</option>
                                @foreach ($users as $user)
                                    <option value="{{ $user->id }}" @selected(old('lead_owner_id', $lead->lead_owner_id) == $user->id)>
                                        {{ $user->name }}
                                    </option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                            
                            <h6 class="fw-bold text-primary mb-3 mt-4">{{ __('crm.address_details') }}</h6>

                            <x-ui.odoo-form-ui type="textarea" :label="__('crm.street_address')" name="address" rows="3" :placeholder="__('crm.street_address_placeholder')">{{ old('address', $lead->address) }}</x-ui.odoo-form-ui>

                            <x-ui.odoo-form-ui type="input" :label="__('crm.country')" name="country" :value="old('country', $lead->country)" :placeholder="__('crm.country')" />

                            <x-ui.odoo-form-ui type="input" :label="__('crm.state')" name="state" :value="old('state', $lead->state)" :placeholder="__('crm.state')" />

                            <x-ui.odoo-form-ui type="input" :label="__('crm.city')" name="city" :value="old('city', $lead->city)" :placeholder="__('crm.city')" />
                        </div>

                        <!-- Right Column: Lead Requirements, Product, Pricing & Classification -->
                        <div class="col-lg-6">
                            <h6 class="fw-bold text-primary mb-3">{{ __('crm.requirements_pricing') }}</h6>

                            <x-ui.odoo-form-ui type="select" :label="__('crm.product')" name="product_id" searchable="true" class="erp-premium-select" data-master="product">
                                <option value="">{{ __('crm.select_product') }}</option>
                                <option value="__ADD_NEW__" class="fw-bold text-primary" data-master="product">{{ __('crm.add_new_product') }}</option>
                                @foreach ($products as $p)
                                    <option value="{{ $p->id }}" @selected(old('product_id', $lead->product_id) == $p->id)>
                                        {{ $p->name }} @if($p->sku) ({{ $p->sku }}) @endif
                                    </option>
                                @endforeach
                            </x-ui.odoo-form-ui>

                            <x-ui.odoo-form-ui type="input" :label="__('crm.expected_amount')" name="expected_amount" inputType="number" :value="old('expected_amount', $lead->expected_amount)" min="0" step="0.01" :placeholder="__('crm.expected_amount_placeholder')" />

                            <x-ui.odoo-form-ui type="input" :label="__('crm.expected_sale')" name="expected_sale_date" inputType="date" :value="old('expected_sale_date', $lead->expected_sale_date ? $lead->expected_sale_date->format('Y-m-d') : '')" />

                            <x-ui.odoo-form-ui type="textarea" :label="__('crm.requirements')" name="requirement" rows="4" :placeholder="__('crm.requirements_placeholder')">{{ old('requirement', $lead->requirement) }}</x-ui.odoo-form-ui>

                            <h6 class="fw-bold text-primary mb-3 mt-4">{{ __('crm.lead_classification') }}</h6>

                            <x-ui.odoo-form-ui type="input" :label="__('crm.industry_type')" name="industry_type" :value="old('industry_type', $lead->industry_type)" :placeholder="__('crm.industry_type')" />

                            <x-ui.odoo-form-ui type="select" :label="__('crm.source')" name="source">
                                <option value="">{{ __('crm.select_option') }}</option>
                                <option value="Cold Call" @selected(old('source', $lead->source) === 'Cold Call')>{{ __('crm.sources.Cold Call') }}</option>
                                <option value="Employee Referral" @selected(old('source', $lead->source) === 'Employee Referral')>{{ __('crm.sources.Employee Referral') }}</option>
                                <option value="Partner" @selected(old('source', $lead->source) === 'Partner')>{{ __('crm.sources.Partner') }}</option>
                                <option value="Web Search" @selected(old('source', $lead->source) === 'Web Search')>{{ __('crm.sources.Web Search') }}</option>
                                <option value="Advertisement" @selected(old('source', $lead->source) === 'Advertisement')>{{ __('crm.sources.Advertisement') }}</option>
                                <option value="Trade Show" @selected(old('source', $lead->source) === 'Trade Show')>{{ __('crm.sources.Trade Show') }}</option>
                            </x-ui.odoo-form-ui>

                            <x-ui.odoo-form-ui type="select" :label="__('crm.priority')" name="priority">
                                <option value="">{{ __('crm.select_option') }}</option>
                                <option value="Low" @selected(old('priority', $lead->priority) === 'Low')>{{ __('crm.priorities.Low') }}</option>
                                <option value="Medium" @selected(old('priority', $lead->priority) === 'Medium')>{{ __('crm.priorities.Medium') }}</option>
                                <option value="High" @selected(old('priority', $lead->priority) === 'High')>{{ __('crm.priorities.High') }}</option>
                                <option value="Urgent" @selected(old('priority', $lead->priority) === 'Urgent')>{{ __('crm.priorities.Urgent') }}</option>
                            </x-ui.odoo-form-ui>

                            <x-ui.odoo-form-ui type="select" :label="__('crm.segment')" name="segment">
                                <option value="">{{ __('crm.select_option') }}</option>
                                <option value="SMB" @selected(old('segment', $lead->segment) === 'SMB')>{{ __('crm.segments.SMB') }}</option>
                                <option value="Mid-Market" @selected(old('segment', $lead->segment) === 'Mid-Market')>{{ __('crm.segments.Mid-Market') }}</option>
                                <option value="Enterprise" @selected(old('segment', $lead->segment) === 'Enterprise')>{{ __('crm.segments.Enterprise') }}</option>
                            </x-ui.odoo-form-ui>
                        </div>
                    </div>

                    <!-- Actions Row -->
                    <div class="d-flex justify-content-end gap-3 border-top pt-4">
                        <a href="{{ route('crm.leads.index') }}" class="btn btn-light px-4 py-2 border">
                            {{ __('crm.cancel') }}
                        </a>
                        <button type="submit" class="btn btn-primary px-5 py-2 fw-semibold" style="background-color: #714B67; border-color: #714B67;">
                            <i class="feather-check-circle me-2"></i>{{ $lead->exists ? __('crm.update_lead') : __('crm.create_lead') }}
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

            // Initialize select2 on the product dropdown
            $('.odoo-select2').select2({
                theme: "bootstrap-5",
                width: "100%"
            });
        });
    </script>
    <x-ui.master-modals :masters="['product']" />
@endpush