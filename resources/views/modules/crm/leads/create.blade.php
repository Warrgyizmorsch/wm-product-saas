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
        <div class="col-xxl-9 col-xl-10 mx-auto">
            <!-- Form Card -->
            <form action="{{ $lead->exists ? route('crm.leads.update', $lead->id) : route('crm.leads.store') }}" method="POST">
                @csrf
                @if ($lead->exists)
                    @method('PUT')
                @endif

                <!-- Card 1: Call Details -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-transparent border-bottom py-3">
                        <h5 class="card-title mb-0 fw-bold text-dark">
                            <i class="feather-phone-call me-2 text-primary"></i>Call Schedule
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-4 mb-3 mb-md-0">
                                <label class="form-label fw-semibold text-dark">Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="call_date_date" value="{{ old('call_date_date', $lead->call_date ? $lead->call_date->format('Y-m-d') : date('Y-m-d')) }}" required>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label fw-semibold text-dark">Time <span class="text-danger">*</span></label>
                                <div class="row g-2">
                                    <div class="col-4">
                                        @php
                                            $currentHour = old('call_date_hour', $lead->call_date ? $lead->call_date->format('h') : date('h'));
                                        @endphp
                                        <select class="form-select" name="call_date_hour" data-select2-selector="default" required>
                                            @for ($h = 1; $h <= 12; $h++)
                                                <option value="{{ sprintf('%02d', $h) }}" {{ $currentHour == $h ? 'selected' : '' }}>{{ sprintf('%02d', $h) }}</option>
                                            @endfor
                                        </select>
                                    </div>
                                    <div class="col-4">
                                        @php
                                            $currentMinute = old('call_date_minute', $lead->call_date ? $lead->call_date->format('i') : date('i'));
                                            // Round to nearest 5 for selector display
                                            $roundedMinute = round(intval($currentMinute) / 5) * 5;
                                            if ($roundedMinute >= 60) $roundedMinute = 55;
                                        @endphp
                                        <select class="form-select" name="call_date_minute" data-select2-selector="default" required>
                                            @for ($m = 0; $m < 60; $m += 5)
                                                <option value="{{ sprintf('%02d', $m) }}" {{ $roundedMinute == $m ? 'selected' : '' }}>{{ sprintf('%02d', $m) }}</option>
                                            @endfor
                                        </select>
                                    </div>
                                    <div class="col-4">
                                        @php
                                            $currentAmPm = old('call_date_ampm', $lead->call_date ? $lead->call_date->format('A') : date('A'));
                                        @endphp
                                        <select class="form-select" name="call_date_ampm" data-select2-selector="default" required>
                                            <option value="AM" {{ $currentAmPm == 'AM' ? 'selected' : '' }}>AM</option>
                                            <option value="PM" {{ $currentAmPm == 'PM' ? 'selected' : '' }}>PM</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 2: Contact & Company Details -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-transparent border-bottom py-3">
                        <h5 class="card-title mb-0 fw-bold text-dark">
                            <i class="feather-user me-2 text-primary"></i>Company & Contact Details
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-dark">Company Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="company_name" value="{{ old('company_name', $lead->company_name) }}" placeholder="Enter company name..." required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-dark">Contact Person Name</label>
                                <input type="text" class="form-control" name="contact_person" value="{{ old('contact_person', $lead->contact_person) }}" placeholder="Enter contact person name...">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-dark">Contact Email</label>
                                <input type="email" class="form-control" name="email" value="{{ old('email', $lead->email) }}" placeholder="example@email.com">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-dark">Contact Phone/Mobile</label>
                                <input type="text" class="form-control" name="phone" value="{{ old('phone', $lead->phone) }}" placeholder="Phone or mobile number...">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 3: Requirements & Deal Pricing -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-transparent border-bottom py-3">
                        <h5 class="card-title mb-0 fw-bold text-dark">
                            <i class="feather-file-text me-2 text-primary"></i>Requirements & Pricing
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label fw-semibold text-dark">Requirement Description</label>
                            <textarea class="form-control" name="requirement" rows="4" placeholder="Enter specific lead requirements...">{{ old('requirement', $lead->requirement) }}</textarea>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-dark">Expected Lead Amount (₹)</label>
                                <input type="number" class="form-control" name="expected_amount" value="{{ old('expected_amount', $lead->expected_amount) }}" min="0" step="0.01" placeholder="e.g. 50000">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-dark">Expected Sale Date</label>
                                <input type="date" class="form-control" name="expected_sale_date" value="{{ old('expected_sale_date', $lead->expected_sale_date ? $lead->expected_sale_date->format('Y-m-d') : '') }}">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Card 4: Classification -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-transparent border-bottom py-3">
                        <h5 class="card-title mb-0 fw-bold text-dark">
                            <i class="feather-tag me-2 text-primary"></i>Lead Classification
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label fw-semibold text-dark">Source</label>
                                @php
                                    $currentSource = old('source', $lead->source);
                                @endphp
                                <select class="form-select" name="source" data-select2-selector="default">
                                    <option value="">Select an Option</option>
                                    <option value="Cold Call" {{ $currentSource == 'Cold Call' ? 'selected' : '' }}>Cold Call</option>
                                    <option value="Employee Referral" {{ $currentSource == 'Employee Referral' ? 'selected' : '' }}>Employee Referral</option>
                                    <option value="Partner" {{ $currentSource == 'Partner' ? 'selected' : '' }}>Partner</option>
                                    <option value="Web Search" {{ $currentSource == 'Web Search' ? 'selected' : '' }}>Web Search</option>
                                    <option value="Advertisement" {{ $currentSource == 'Advertisement' ? 'selected' : '' }}>Advertisement</option>
                                    <option value="Trade Show" {{ $currentSource == 'Trade Show' ? 'selected' : '' }}>Trade Show</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold text-dark">Priority</label>
                                @php
                                    $currentPriority = old('priority', $lead->priority);
                                @endphp
                                <select class="form-select" name="priority" data-select2-selector="priority">
                                    <option value="">Select an Option</option>
                                    <option value="Low" data-bg="bg-success" {{ $currentPriority == 'Low' ? 'selected' : '' }}>Low</option>
                                    <option value="Medium" data-bg="bg-warning" {{ $currentPriority == 'Medium' ? 'selected' : '' }}>Medium</option>
                                    <option value="High" data-bg="bg-danger" {{ $currentPriority == 'High' ? 'selected' : '' }}>High</option>
                                    <option value="Urgent" data-bg="bg-danger" {{ $currentPriority == 'Urgent' ? 'selected' : '' }}>Urgent</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold text-dark">Segment</label>
                                @php
                                    $currentSegment = old('segment', $lead->segment);
                                @endphp
                                <select class="form-select" name="segment" data-select2-selector="status">
                                    <option value="">Select an Option</option>
                                    <option value="SMB" data-bg="bg-info" {{ $currentSegment == 'SMB' ? 'selected' : '' }}>SMB</option>
                                    <option value="Mid-Market" data-bg="bg-primary" {{ $currentSegment == 'Mid-Market' ? 'selected' : '' }}>Mid-Market</option>
                                    <option value="Enterprise" data-bg="bg-success" {{ $currentSegment == 'Enterprise' ? 'selected' : '' }}>Enterprise</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Submit / Cancel Actions -->
                <div class="d-flex gap-2 justify-content-end mb-5">
                    <a href="{{ route('crm.leads.index') }}" class="btn btn-light btn-lg">Cancel</a>
                    <button type="submit" class="btn btn-primary btn-lg px-4">
                        <i class="feather-check-circle me-2"></i>{{ $lead->exists ? 'Update Lead Details' : 'Save Lead Details' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <!-- Select2 Vendor & Theme Active JS -->
    <script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/select2-active.min.js') }}"></script>
@endpush
