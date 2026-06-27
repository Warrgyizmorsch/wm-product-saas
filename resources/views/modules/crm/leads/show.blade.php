@extends('layouts.duralux')

@section('title', 'Lead Details | SaaS ERP')
@section('page-title', 'Lead Details')
@section('breadcrumb', 'Lead details')



@section('content')
    <!-- Success Alerts -->
    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-2" role="alert">
            <div class="d-flex align-items-center">
                <div class="avatar-text avatar-md bg-success text-white me-3">
                    <i class="feather-check-circle"></i>
                </div>
                <div>
                    <h6 class="alert-heading fw-bold mb-1">Success!</h6>
                    <p class="fs-12 mb-0">{{ session('success') }}</p>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Error Alerts -->
    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-2" role="alert">
            <div class="d-flex align-items-center">
                <div class="avatar-text avatar-md bg-danger text-white me-3">
                    <i class="feather-alert-triangle"></i>
                </div>
                <div>
                    <h6 class="alert-heading fw-bold mb-1">Error!</h6>
                    <ul class="fs-12 mb-0 ps-3">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Zoho CRM Style Unified Profile Canvas Card (All-in-one container) -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-4 bg-white">
            
            <!-- Header Banner Section -->
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 pb-3 mb-3 border-bottom">
                <div class="d-flex align-items-center">
                    <!-- Avatar Circle -->
                    <div class="avatar-text avatar-lg bg-soft-primary text-primary fs-4 fw-bold me-3 shadow-sm">
                        {{ strtoupper(substr($lead->company_name, 0, 1)) }}
                    </div>
                    <div>
                        <div class="d-flex align-items-center flex-wrap gap-2">
                            <h4 class="fw-bold text-dark mb-0 me-2">{{ $lead->company_name }}</h4>
                            
                            @php
                                $statusClass = 'bg-soft-primary text-primary';
                                if($lead->status === 'Follow-up Scheduled') $statusClass = 'bg-soft-warning text-warning';
                                elseif($lead->status === 'Contacted') $statusClass = 'bg-soft-info text-info';
                                elseif($lead->status === 'Qualified') $statusClass = 'bg-soft-teal text-teal';
                                elseif($lead->status === 'Converted') $statusClass = 'bg-soft-success text-success';
                                elseif($lead->status === 'Lost') $statusClass = 'bg-soft-danger text-danger';
                            @endphp
                            <span class="badge {{ $statusClass }} px-2 py-0.5 fs-10 fw-semibold">{{ $lead->status ?: 'New' }}</span>
                            <span class="badge bg-soft-secondary text-secondary px-2 py-0.5 fs-10 fw-semibold">{{ $lead->segment ?: 'No Segment' }}</span>
                        </div>
                        <p class="text-muted mb-0 fs-12 mt-0.5">
                            <i class="feather-user me-1 text-muted"></i> {{ $lead->contact_person ?: 'No Contact Person' }}
                        </p>
                    </div>
                </div>
                
                <!-- Quick stats in the banner -->
                <div class="d-flex gap-4 flex-wrap border-start ps-4 d-none d-sm-flex">
                    <div>
                        <span class="text-muted fs-10 text-uppercase fw-semibold d-block">Expected Value</span>
                        <span class="fw-bold text-dark fs-13">₹{{ $lead->expected_amount ? number_format($lead->expected_amount, 2) : '0.00' }}</span>
                    </div>
                    <div>
                        <span class="text-muted fs-10 text-uppercase fw-semibold d-block">Interest</span>
                        <span class="fw-bold text-dark fs-13">{{ $lead->product ?: '—' }}</span>
                    </div>
                    <div>
                        <span class="text-muted fs-10 text-uppercase fw-semibold d-block">Priority</span>
                        <span class="fw-bold text-dark fs-13">
                            @if($lead->priority === 'High')
                                <span class="text-danger"><i class="feather-alert-circle me-1 fs-11"></i>High</span>
                            @elseif($lead->priority === 'Medium')
                                <span class="text-warning"><i class="feather-alert-circle me-1 fs-11"></i>Medium</span>
                            @else
                                <span class="text-success"><i class="feather-alert-circle me-1 fs-11"></i>Low</span>
                            @endif
                        </span>
                    </div>
                </div>
            </div>

            <!-- Zoho Style Navigation Tab Menu -->
            <ul class="nav nav-tabs mb-4" id="leadTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active fw-bold px-3 py-2 fs-13" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview-pane" type="button" role="tab" aria-controls="overview-pane" aria-selected="true">
                        Overview
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-bold px-3 py-2 fs-13" id="timeline-tab" data-bs-toggle="tab" data-bs-target="#timeline-pane" type="button" role="tab" aria-controls="timeline-pane" aria-selected="false">
                        Timeline & Follow-ups
                    </button>
                </li>
            </ul>

            <!-- Tab Contents -->
            <div class="tab-content" id="leadTabsContent">
                <!-- Tab 1: Overview Pane (Direct Zoho Flat Canvas details) -->
                <div class="tab-pane fade show active" id="overview-pane" role="tabpanel" aria-labelledby="overview-tab">
                    
                    <!-- Section 1: Lead Information -->
                    <div class="mb-4">
                        <h6 class="fs-12 text-uppercase fw-bold text-primary mb-3">
                            <i class="feather-info me-2"></i>Lead Information
                        </h6>
                        <div class="row g-3 fs-13 text-dark">
                            <div class="col-md-4 col-sm-6">
                                <span class="text-muted fs-11 text-uppercase fw-bold d-block mb-1">Lead Owner</span>
                                <form action="{{ route('crm.leads.updateOwner', $lead->id) }}" method="POST" class="d-inline">
                                    @csrf
                                    @method('PATCH')
                                    <select name="lead_owner_id" class="form-select form-select-sm py-0.5 px-2 fs-12 fw-bold border-0 bg-light" onchange="this.form.submit()" style="cursor: pointer; width: 140px; display: inline-block;">
                                        <option value="">Unassigned</option>
                                        @foreach($users as $user)
                                            <option value="{{ $user->id }}" {{ $lead->lead_owner_id == $user->id ? 'selected' : '' }}>
                                                {{ $user->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </form>
                            </div>
                            <div class="col-md-4 col-sm-6">
                                <span class="text-muted fs-11 text-uppercase fw-bold d-block mb-1">Lead Status</span>
                                @if ($lead->is_customer || $lead->status === 'Converted')
                                    <div class="mt-0.5"><span class="badge bg-soft-success text-success px-2 py-1 fs-11 fw-bold"><i class="feather-check-circle me-1"></i>Converted</span></div>
                                @else
                                    <form action="{{ route('crm.leads.updateStatus', $lead->id) }}" method="POST" class="d-inline">
                                        @csrf
                                        @method('PATCH')
                                        <select name="status" class="form-select form-select-sm py-0.5 px-2 fs-11 fw-bold border-0 rounded {{ $statusClass }}" onchange="this.form.submit()" style="cursor: pointer; width: 140px; display: inline-block;">
                                            @foreach(['New', 'Follow-up Scheduled', 'Contacted', 'Qualified', 'Converted', 'Lost'] as $statusOption)
                                                <option value="{{ $statusOption }}" {{ ($lead->status ?: 'New') === $statusOption ? 'selected' : '' }} class="bg-white text-dark fw-normal text-start">
                                                    {{ $statusOption }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </form>
                                @endif
                            </div>
                            <div class="col-md-4 col-sm-6">
                                <span class="text-muted fs-11 text-uppercase fw-bold d-block mb-1">Industry Type</span>
                                <span class="text-dark fw-bold">{{ $lead->industry_type ?: '—' }}</span>
                            </div>
                            <div class="col-md-4 col-sm-6">
                                <span class="text-muted fs-11 text-uppercase fw-bold d-block mb-1">Product Interested</span>
                                <span class="text-dark fw-bold">{{ $lead->product ?: '—' }}</span>
                            </div>
                            <div class="col-md-4 col-sm-6">
                                <span class="text-muted fs-11 text-uppercase fw-bold d-block mb-1">Expected Amount</span>
                                <span class="text-dark fw-bold">₹{{ $lead->expected_amount ? number_format($lead->expected_amount, 2) : '0.00' }}</span>
                            </div>
                            <div class="col-md-4 col-sm-6">
                                <span class="text-muted fs-11 text-uppercase fw-bold d-block mb-1">Expected Sale Date</span>
                                <span class="text-dark fw-bold">{{ $lead->expected_sale_date ? $lead->expected_sale_date->format('d/m/Y') : '—' }}</span>
                            </div>
                            <div class="col-md-4 col-sm-6">
                                <span class="text-muted fs-11 text-uppercase fw-bold d-block mb-1">Lead Source</span>
                                <span class="text-dark fw-bold">{{ $lead->source ?: '—' }}</span>
                            </div>
                            <div class="col-md-4 col-sm-6">
                                <span class="text-muted fs-11 text-uppercase fw-bold d-block mb-1">Priority</span>
                                <span class="text-dark fw-bold">{{ $lead->priority ?: '—' }}</span>
                            </div>
                            <div class="col-md-4 col-sm-6">
                                <span class="text-muted fs-11 text-uppercase fw-bold d-block mb-1">Segment</span>
                                <span class="text-dark fw-bold">{{ $lead->segment ?: '—' }}</span>
                            </div>
                            <div class="col-md-4 col-sm-6">
                                <span class="text-muted fs-11 text-uppercase fw-bold d-block mb-1">Created Date</span>
                                <span class="text-dark fw-bold">{{ $lead->created_at ? $lead->created_at->format('d/m/Y h:i A') : '—' }}</span>
                            </div>
                        </div>
                    </div>

                    <hr class="my-4 text-muted opacity-25">

                    <!-- Section 2: Contact & Address details -->
                    <div class="mb-4">
                        <h6 class="fs-12 text-uppercase fw-bold text-primary mb-3">
                            <i class="feather-map-pin me-2"></i>Contact & Address Details
                        </h6>
                        <div class="row g-3 fs-13 text-dark">
                            <div class="col-md-6 border-end pe-4">
                                <span class="text-muted fs-11 text-uppercase fw-bold d-block mb-1">Contact Person</span>
                                <span class="text-dark fw-bold d-block mb-3">{{ $lead->contact_person ?: '—' }}</span>
                                
                                <span class="text-muted fs-11 text-uppercase fw-bold d-block mb-1">Email Address</span>
                                @if ($lead->email)
                                    <a href="mailto:{{ $lead->email }}" class="text-primary fw-bold d-block mb-3">{{ $lead->email }}</a>
                                @else
                                    <span class="text-dark fw-bold d-block mb-3">—</span>
                                @endif
                                
                                <span class="text-muted fs-11 text-uppercase fw-bold d-block mb-1">Phone Number</span>
                                @if ($lead->phone)
                                    <a href="tel:{{ $lead->phone }}" class="text-primary fw-bold d-block">{{ $lead->phone }}</a>
                                @else
                                    <span class="text-dark fw-bold d-block">—</span>
                                @endif
                            </div>
                            <div class="col-md-6 ps-4">
                                <span class="text-muted fs-11 text-uppercase fw-bold d-block mb-1">Street Address</span>
                                <span class="text-dark fw-semibold d-block mb-3">{{ $lead->address ?: '—' }}</span>
                                
                                <span class="text-muted fs-11 text-uppercase fw-bold d-block mb-3">City / State</span>
                                <span class="text-dark fw-semibold d-block mb-3">{{ $lead->city ?: '—' }} / {{ $lead->state ?: '—' }}</span>
                                
                                <span class="text-muted fs-11 text-uppercase fw-bold d-block mb-1">Country</span>
                                <span class="text-dark fw-semibold d-block">{{ $lead->country ?: '—' }}</span>
                            </div>
                        </div>
                    </div>

                    <hr class="my-4 text-muted opacity-25">

                    <!-- Section 3: Requirement Details -->
                    <div class="mb-2">
                        <h6 class="fs-12 text-uppercase fw-bold text-primary mb-3">
                            <i class="feather-file-text me-2"></i>Requirement Details
                        </h6>
                        @if ($lead->requirement)
                            <div class="text-dark fs-13" style="white-space: pre-wrap; line-height: 1.6;">{{ $lead->requirement }}</div>
                        @else
                            <span class="text-muted fs-12 italic">No requirement details provided.</span>
                        @endif
                    </div>
                </div>

                <!-- Tab 2: Timeline Pane -->
                <div class="tab-pane fade" id="timeline-pane" role="tabpanel" aria-labelledby="timeline-tab">
                    <div class="row g-4">
                        <!-- Left: Action cards (Next followup & Initial Call) -->
                        <div class="col-lg-4">
                            <!-- Next Follow-up Card -->
                            <div class="card border-0 shadow-sm mb-3 bg-white">
                                <div class="card-header bg-transparent border-bottom py-2.5 px-3 d-flex justify-content-between align-items-center">
                                    <h6 class="card-title mb-0 fw-bold text-dark fs-13">
                                        <i class="feather-calendar me-2 text-warning"></i>Next Follow-up
                                    </h6>
                                    <button class="btn btn-xs btn-primary py-1 px-2" data-bs-toggle="modal" data-bs-target="#addFollowupModal">
                                        <i class="feather-plus me-1"></i>Add
                                    </button>
                                </div>
                                <div class="card-body text-center py-3 px-3">
                                    @if ($lead->next_followup_date)
                                        <div class="avatar-text avatar-md bg-soft-warning text-warning mx-auto mb-2">
                                            <i class="feather-bell fs-16"></i>
                                        </div>
                                        <h5 class="fw-bold text-dark mb-1 fs-15">{{ $lead->next_followup_date->format('d M, Y') }}</h5>
                                        <p class="text-warning fw-semibold fs-13 mb-0">
                                            <i class="feather-clock me-1"></i>{{ $lead->next_followup_date->format('h:i A') }}
                                        </p>
                                        <small class="text-muted fs-11 mt-1 d-block">Scheduled next follow-up</small>
                                    @else
                                        <div class="avatar-text avatar-md bg-soft-secondary text-secondary mx-auto mb-2">
                                            <i class="feather-bell-off fs-16"></i>
                                        </div>
                                        <p class="text-muted mb-2 fw-semibold fs-13">No Follow-up Scheduled</p>
                                        <button class="btn btn-xs btn-light-brand" data-bs-toggle="modal" data-bs-target="#addFollowupModal">
                                            Schedule Now
                                        </button>
                                    @endif
                                </div>
                            </div>

                            <!-- Call Details Card -->
                            <div class="card border-0 shadow-sm mb-3 bg-white">
                                <div class="card-header bg-transparent border-bottom py-2.5 px-3">
                                    <h6 class="card-title mb-0 fw-bold text-dark fs-13">
                                        <i class="feather-phone-call me-2 text-primary"></i>Initial Call
                                    </h6>
                                </div>
                                <div class="card-body text-center py-3 px-3">
                                    <div class="avatar-text avatar-md bg-soft-primary text-primary mx-auto mb-2">
                                        <i class="feather-phone-call fs-16"></i>
                                    </div>
                                    @if ($lead->call_date)
                                        <h5 class="fw-bold text-dark mb-1 fs-15">{{ $lead->call_date->format('d M, Y') }}</h5>
                                        <p class="text-primary fw-semibold fs-13 mb-0">
                                            <i class="feather-clock me-1"></i>{{ $lead->call_date->format('h:i A') }}
                                        </p>
                                    @else
                                        <p class="text-muted mb-0 fs-13">No call schedule set</p>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- Right: Timeline History Logs -->
                        <div class="col-lg-8">
                            <div class="card border-0 shadow-sm bg-white">
                                <div class="card-header bg-transparent border-bottom py-2.5 px-3 d-flex justify-content-between align-items-center">
                                    <h6 class="card-title mb-0 fw-bold text-dark fs-13">
                                        <i class="feather-activity me-2 text-primary"></i>Timeline History Logs
                                    </h6>
                                    <button class="btn btn-xs btn-light-brand py-1 px-2" data-bs-toggle="modal" data-bs-target="#addFollowupModal">
                                        <i class="feather-plus me-1"></i>Log Interaction
                                    </button>
                                </div>
                                <div class="card-body p-3">
                                    @if($lead->followups->isEmpty())
                                        <div class="text-center py-5 text-muted border border-dashed rounded bg-light-50 fs-12">
                                            <i class="feather-clock fs-24 mb-1.5 d-block text-muted opacity-50"></i>
                                            No logs recorded yet. Log new interactions using the button above.
                                        </div>
                                    @else
                                        <div class="position-relative ps-3 border-start border-2 border-light ms-1 py-1">
                                            @foreach($lead->followups as $followup)
                                                <div class="position-relative mb-3">
                                                    @php
                                                        $dotIcon = 'feather-phone';
                                                        if($followup->type === 'Email') { $dotIcon = 'feather-mail'; }
                                                        elseif($followup->type === 'Meeting') { $dotIcon = 'feather-users'; }
                                                        elseif($followup->type === 'Demo') { $dotIcon = 'feather-monitor'; }
                                                    @endphp
                                                    
                                                    <div class="position-absolute start-0 translate-middle rounded-circle bg-light text-muted border d-flex align-items-center justify-content-center" style="left: -13px !important; width: 22px; height: 22px; z-index: 2;">
                                                        <i class="{{ $dotIcon }} fs-9"></i>
                                                    </div>
                                                    
                                                    <div class="ms-3">
                                                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-1">
                                                            <div>
                                                                <span class="fw-bold text-dark fs-12">{{ $followup->type }}</span>
                                                                @if($followup->status === 'Pending')
                                                                    <span class="badge bg-soft-warning text-warning fs-9 py-0.5 px-1 ms-1 fw-semibold">Pending</span>
                                                                @elseif($followup->status === 'Completed')
                                                                    <span class="badge bg-soft-success text-success fs-9 py-0.5 px-1 ms-1 fw-semibold">Done</span>
                                                                @endif
                                                            </div>
                                                            
                                                            <div class="hstack gap-1">
                                                                @if($followup->status === 'Pending')
                                                                    <form action="{{ route('crm.followups.update', $followup->id) }}" method="POST" class="d-inline">
                                                                        @csrf
                                                                        @method('PUT')
                                                                        <input type="hidden" name="status" value="Completed">
                                                                        <button type="submit" class="btn btn-icon btn-xs btn-soft-success" title="Complete" style="width:18px;height:18px;">
                                                                            <i class="feather-check" style="font-size: 8px;"></i>
                                                                        </button>
                                                                    </form>
                                                                    <button type="button" class="btn btn-icon btn-xs btn-soft-primary" data-bs-toggle="modal" data-bs-target="#editFollowupModal-{{ $followup->id }}" title="Reschedule" style="width:18px;height:18px;">
                                                                        <i class="feather-calendar" style="font-size: 8px;"></i>
                                                                    </button>
                                                                @endif
                                                                <form action="{{ route('crm.followups.destroy', $followup->id) }}" method="POST" class="d-inline">
                                                                    @csrf
                                                                    @method('DELETE')
                                                                    <button type="submit" class="btn btn-icon btn-xs btn-soft-danger" onclick="return confirm('Delete?')" style="width:18px;height:18px;">
                                                                        <i class="feather-trash-2" style="font-size: 8px;"></i>
                                                                    </button>
                                                                </form>
                                                            </div>
                                                        </div>
                                                        <small class="text-muted fs-10 d-block"><i class="feather-calendar me-1"></i>{{ $followup->followup_date->format('d M, h:i A') }}</small>
                                                        @if($followup->notes)
                                                            <div class="bg-light-50 p-1.5 rounded fs-11 text-muted mt-1" style="white-space: pre-wrap; line-height: 1.4;">{{ $followup->notes }}</div>
                                                        @endif
                                                    </div>
                                                </div>

                                                @if($followup->status === 'Pending')
                                                    <!-- Edit Modal -->
                                                    <x-ui.modal 
                                                        id="editFollowupModal-{{ $followup->id }}" 
                                                        title="Reschedule Follow-up" 
                                                        centered
                                                        form-action="{{ route('crm.followups.update', $followup->id) }}"
                                                        form-method="PUT"
                                                        submit-text="Update"
                                                        close-text="Close"
                                                    >
                                                        <div class="mb-3 text-start">
                                                            <label class="form-label fw-bold text-dark fs-12 text-uppercase">Interaction Type <span class="text-danger">*</span></label>
                                                            <select class="form-select" name="type" required>
                                                                <option value="Call" {{ $followup->type === 'Call' ? 'selected' : '' }}>Call</option>
                                                                <option value="Email" {{ $followup->type === 'Email' ? 'selected' : '' }}>Email</option>
                                                                <option value="Meeting" {{ $followup->type === 'Meeting' ? 'selected' : '' }}>Meeting</option>
                                                                <option value="Demo" {{ $followup->type === 'Demo' ? 'selected' : '' }}>Demo</option>
                                                            </select>
                                                        </div>
                                                        <div class="mb-3 text-start">
                                                            <label class="form-label fw-bold text-dark fs-12 text-uppercase">Date & Time <span class="text-danger">*</span></label>
                                                            <div class="input-group">
                                                                <span class="input-group-text bg-light"><i class="feather-calendar text-muted"></i></span>
                                                                <input type="text" class="form-control edit-followup-datepicker" name="followup_date" value="{{ $followup->followup_date->format('Y-m-d h:i A') }}" required>
                                                            </div>
                                                        </div>
                                                        <div class="mb-0 text-start">
                                                            <label class="form-label fw-bold text-dark fs-12 text-uppercase">Discussion Notes</label>
                                                            <textarea class="form-control" name="notes" rows="4">{{ $followup->notes }}</textarea>
                                                        </div>
                                                    </x-ui.modal>
                                                @endif
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Add Follow-up Modal Component -->
    <x-ui.modal 
        id="addFollowupModal" 
        title="Schedule / Log Follow-up" 
        centered
        form-action="{{ route('crm.leads.followups.store', $lead->id) }}"
        form-method="POST"
        submit-text="Save Follow-up"
        close-text="Close"
    >
        <!-- Follow-up Type -->
        <div class="mb-3">
            <label class="form-label fw-bold text-dark">Interaction Type <span class="text-danger">*</span></label>
            <select class="form-select" name="type" required>
                <option value="Call" selected>Call</option>
                <option value="Email">Email</option>
                <option value="Meeting">Meeting</option>
                <option value="Demo">Demo</option>
            </select>
        </div>

        <!-- Date & Time Picker -->
        <div class="mb-3">
            <label class="form-label fw-bold text-dark">Date & Time <span class="text-danger">*</span></label>
            <div class="input-group">
                <span class="input-group-text bg-light"><i class="feather-calendar text-muted"></i></span>
                <input type="text" class="form-control" name="followup_date" id="followup_date_picker" required>
            </div>
        </div>

        <!-- Initial Status -->
        <div class="mb-3">
            <label class="form-label fw-bold text-dark">Status <span class="text-danger">*</span></label>
            <select class="form-select" name="status" required>
                <option value="Pending" selected>Scheduled / Pending</option>
                <option value="Completed">Completed (Log Past Interaction)</option>
            </select>
        </div>

        <!-- Notes / Discussion Points -->
        <div class="mb-0">
            <label class="form-label fw-bold text-dark">Discussion Notes / Plan</label>
            <textarea class="form-control" name="notes" rows="4" placeholder="Enter planned discussion points, questions to ask, or what was discussed..."></textarea>
        </div>
    </x-ui.modal>
@endsection

@push('scripts')
    <script>
        $(function () {
            // Initialize daterangepicker for creating new follow-up
            $('#followup_date_picker').daterangepicker({
                singleDatePicker: true,
                timePicker: true,
                timePickerIncrement: 5,
                locale: {
                    format: 'YYYY-MM-DD hh:mm A'
                }
            });

            // Initialize daterangepicker for rescheduling existing follow-ups
            $('.edit-followup-datepicker').daterangepicker({
                singleDatePicker: true,
                timePicker: true,
                timePickerIncrement: 5,
                locale: {
                    format: 'YYYY-MM-DD hh:mm A'
                }
            });
        });
    </script>
@endpush
