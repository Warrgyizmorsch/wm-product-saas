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
            <div class="card border-0 shadow-sm p-4 p-md-5">
                <form action="{{ $lead->exists ? route('crm.leads.update', $lead->id) : route('crm.leads.store') }}" method="POST">
                    @csrf
                    @if ($lead->exists)
                        @method('PUT')
                    @endif

                    <div class="row g-5">
                        <!-- Left Column: Scheduling, Company, Contact, and Address Details -->
                        <div class="col-lg-6 border-end-lg">
                            <!-- Call Details Subheading -->
                            <h5 class="fw-bold text-dark border-bottom pb-2 mb-4">
                                <i class="feather-phone-call me-2 text-primary"></i>Call Details
                            </h5>

                            <div class="mb-4">
                                <label class="form-label fw-bold text-dark">Call Date & Time <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="feather-calendar text-muted"></i></span>
                                    <input type="text" class="form-control" name="call_date" id="call_date_picker" value="{{ old('call_date', $lead->call_date ? $lead->call_date->format('Y-m-d h:i A') : date('Y-m-d h:i A')) }}" required>
                                </div>
                            </div>

                            <!-- Company & Contact Details Subheading -->
                            <h5 class="fw-bold text-dark border-bottom pb-2 mb-4 mt-5">
                                <i class="feather-user me-2 text-primary"></i>Company & Contact Details
                            </h5>

                            <div class="mb-3">
                                <label class="form-label fw-bold text-dark">Company Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="company_name" value="{{ old('company_name', $lead->company_name) }}" placeholder="e.g. Acme Corporation" required>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold text-dark">Contact Person Name</label>
                                <input type="text" class="form-control" name="contact_person" value="{{ old('contact_person', $lead->contact_person) }}" placeholder="e.g. John Doe">
                            </div>

                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-dark">Contact Email</label>
                                    <input type="email" class="form-control" name="email" value="{{ old('email', $lead->email) }}" placeholder="john.doe@company.com">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-dark">Contact Phone/Mobile</label>
                                    <input type="text" class="form-control" name="phone" value="{{ old('phone', $lead->phone) }}" placeholder="e.g. +91 98765 43210">
                                </div>
                            </div>

                            <!-- Address Details Subheading -->
                            <h5 class="fw-bold text-dark border-bottom pb-2 mb-4 mt-5">
                                <i class="feather-map-pin me-2 text-primary"></i>Address Details
                            </h5>

                            <div class="mb-3">
                                <label class="form-label fw-bold text-dark">Street Address</label>
                                <textarea class="form-control" name="address" rows="3" placeholder="Enter building, street, and area details...">{{ old('address', $lead->address) }}</textarea>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-dark">Country</label>
                                    <input type="text" class="form-control" name="country" value="{{ old('country', $lead->country) }}" placeholder="e.g. India">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-dark">State</label>
                                    <input type="text" class="form-control" name="state" value="{{ old('state', $lead->state) }}" placeholder="e.g. Maharashtra">
                                </div>
                                <div class="col-12 mt-3">
                                    <label class="form-label fw-bold text-dark">City</label>
                                    <input type="text" class="form-control" name="city" value="{{ old('city', $lead->city) }}" placeholder="e.g. Mumbai">
                                </div>
                            </div>
                        </div>

                        <!-- Right Column: Lead Requirements, Product, Pricing & Classification -->
                        <div class="col-lg-6">
                            <!-- Requirements & Pricing Subheading -->
                            <h5 class="fw-bold text-dark border-bottom pb-2 mb-4">
                                <i class="feather-file-text me-2 text-primary"></i>Requirements & Pricing
                            </h5>

                            <div class="mb-3">
                                <label class="form-label fw-bold text-dark">Product Interested In</label>
                                <input type="text" class="form-control" name="product" value="{{ old('product', $lead->product) }}" placeholder="e.g. ERP Software, Industrial Machinery">
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold text-dark">Requirement Description</label>
                                <textarea class="form-control" name="requirement" rows="5" placeholder="Describe client requirements, project specifications, products interested in...">{{ old('requirement', $lead->requirement) }}</textarea>
                            </div>

                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-dark">Expected Amount (₹)</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light">₹</span>
                                        <input type="number" class="form-control" name="expected_amount" value="{{ old('expected_amount', $lead->expected_amount) }}" min="0" step="0.01" placeholder="e.g. 50000">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold text-dark">Expected Sale Date</label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-light"><i class="feather-calendar text-muted"></i></span>
                                        <input type="date" class="form-control" name="expected_sale_date" value="{{ old('expected_sale_date', $lead->expected_sale_date ? $lead->expected_sale_date->format('Y-m-d') : '') }}">
                                    </div>
                                </div>
                            </div>

                            <!-- Classification Subheading -->
                            <h5 class="fw-bold text-dark border-bottom pb-2 mb-4 mt-5">
                                <i class="feather-tag me-2 text-primary"></i>Lead Classification
                            </h5>

                            @php
                                $currentSource = old('source', $lead->source);
                                $currentPriority = old('priority', $lead->priority);
                                $currentSegment = old('segment', $lead->segment);
                                $currentIndustry = old('industry_type', $lead->industry_type);
                            @endphp

                            <div class="mb-3">
                                <label class="form-label fw-bold text-dark">Industry Type</label>
                                <input type="text" class="form-control" name="industry_type" value="{{ $currentIndustry }}" placeholder="e.g. Manufacturing, Healthcare, IT, Retail">
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-bold text-dark">Assign Lead Owner</label>
                                <select class="form-select" name="lead_owner_id" data-select2-selector="default">
                                    <option value="">Select Owner (Unassigned)</option>
                                    @foreach($users as $user)
                                        <option value="{{ $user->id }}" {{ old('lead_owner_id', $lead->lead_owner_id) == $user->id ? 'selected' : '' }}>
                                            {{ $user->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label fw-bold text-dark">Source</label>
                                    <select class="form-select" name="source" data-select2-selector="default">
                                        <option value="">Select Option</option>
                                        <option value="Cold Call" {{ $currentSource == 'Cold Call' ? 'selected' : '' }}>Cold Call</option>
                                        <option value="Employee Referral" {{ $currentSource == 'Employee Referral' ? 'selected' : '' }}>Employee Referral</option>
                                        <option value="Partner" {{ $currentSource == 'Partner' ? 'selected' : '' }}>Partner</option>
                                        <option value="Web Search" {{ $currentSource == 'Web Search' ? 'selected' : '' }}>Web Search</option>
                                        <option value="Advertisement" {{ $currentSource == 'Advertisement' ? 'selected' : '' }}>Advertisement</option>
                                        <option value="Trade Show" {{ $currentSource == 'Trade Show' ? 'selected' : '' }}>Trade Show</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold text-dark">Priority</label>
                                    <select class="form-select" name="priority" data-select2-selector="priority">
                                        <option value="">Select Option</option>
                                        <option value="Low" data-bg="bg-success" {{ $currentPriority == 'Low' ? 'selected' : '' }}>Low</option>
                                        <option value="Medium" data-bg="bg-warning" {{ $currentPriority == 'Medium' ? 'selected' : '' }}>Medium</option>
                                        <option value="High" data-bg="bg-danger" {{ $currentPriority == 'High' ? 'selected' : '' }}>High</option>
                                        <option value="Urgent" data-bg="bg-danger" {{ $currentPriority == 'Urgent' ? 'selected' : '' }}>Urgent</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-bold text-dark">Segment</label>
                                    <select class="form-select" name="segment" data-select2-selector="status">
                                        <option value="">Select Option</option>
                                        <option value="SMB" data-bg="bg-info" {{ $currentSegment == 'SMB' ? 'selected' : '' }}>SMB</option>
                                        <option value="Mid-Market" data-bg="bg-primary" {{ $currentSegment == 'Mid-Market' ? 'selected' : '' }}>Mid-Market</option>
                                        <option value="Enterprise" data-bg="bg-success" {{ $currentSegment == 'Enterprise' ? 'selected' : '' }}>Enterprise</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Divider -->
                    <hr class="border-top-dashed my-5">

                    <!-- Actions Row -->
                    <div class="d-flex justify-content-end gap-3">
                        <a href="{{ route('crm.leads.index') }}" class="btn btn-light px-4 py-2">
                            Cancel
                        </a>
                        <button type="submit" class="btn btn-primary px-5 py-2 fw-semibold">
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