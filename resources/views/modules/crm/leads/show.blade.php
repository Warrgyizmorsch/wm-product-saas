@extends('layouts.duralux')

@section('title', 'Lead Details | SaaS ERP')
@section('page-title', 'Lead Profile')
@section('breadcrumb', 'CRM / Leads / Profile')

@section('content')
    <!-- Success Alerts -->
    @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm mb-3 d-print-none" role="alert">
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
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-3 d-print-none" role="alert">
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

    <!-- Hidden form for stage status updates via clickable/action triggers -->
    <form id="statusChangeForm" action="{{ route('crm.leads.updateStatus', $lead->id) }}" method="POST" style="display: none;">
        @csrf
        @method('PATCH')
        <input type="hidden" name="status" id="statusChangeInput">
    </form>

    <!-- Zoho CRM Layout Outer Card Container -->
    <div class="card border-0 shadow-sm bg-white d-flex flex-column zoho-lead-card-container d-print-block" style="height: calc(100vh - 195px); min-height: 550px; overflow: hidden; border-radius: 4px;">
        
        <!-- ==================== STICKY HEADER BANNER ==================== -->
        <div class="zoho-header-banner p-3 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-3 d-print-none" style="flex-shrink: 0; background-color: #ffffff; z-index: 100;">
            <div class="d-flex align-items-center">
                <!-- Lead Profile Avatar with Initials -->
                <div class="zoho-avatar bg-soft-primary text-primary fs-5 fw-bold me-3 text-uppercase shadow-sm d-flex align-items-center justify-content-center" style="width: 46px; height: 46px; border-radius: 4px; border: 1px solid rgba(0,0,0,0.05); font-family: 'Inter', sans-serif;">
                    {{ strtoupper(substr($lead->company_name, 0, 1)) }}
                </div>
                
                <!-- Title & Tags -->
                <div>
                    <div class="d-flex align-items-center flex-wrap gap-2">
                        <h4 class="fw-bold text-dark mb-0 fs-15" style="font-family: 'Inter', sans-serif;">
                            {{ $lead->contact_person ?: 'Contact' }} - {{ $lead->company_name }}
                        </h4>
                        
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
                    <!-- Tag Button -->
                    <div class="mt-1 d-flex align-items-center">
                        <button type="button" class="btn btn-xs btn-outline-secondary zoho-tag-btn d-inline-flex align-items-center text-muted px-2 py-0.5 border" style="font-size: 10px; border-radius: 3px;">
                            <i class="feather-tag me-1 fs-9"></i> Add Tags
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Right-side Action Buttons -->
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <!-- Send Email Button -->
                @if ($lead->email)
                    <a href="mailto:{{ $lead->email }}" class="btn btn-xs btn-primary fw-bold py-1 px-2.5 rounded shadow-sm d-inline-flex align-items-center text-white" style="background-color: #1e40af; border-color: #1e40af; font-family: 'Inter', sans-serif; font-size: 11px;">
                        <i class="feather-mail me-1"></i> Email
                    </a>
                @endif
                
                <!-- Back Button -->
                <a href="{{ route('crm.leads.index') }}" class="btn btn-xs btn-outline-secondary fw-bold py-1 px-2.5 rounded bg-white text-dark border-secondary d-inline-flex align-items-center" style="font-family: 'Inter', sans-serif; font-size: 11px;">
                    <i class="feather-arrow-left me-1"></i> Back
                </a>

                <!-- More Actions 3-Dot Dropdown -->
                <div class="dropdown d-inline-block">
                    <button class="btn btn-xs btn-outline-secondary fw-bold py-1 px-2 rounded bg-white text-dark border-secondary d-inline-flex align-items-center justify-content-center" type="button" id="headerMoreActionsDropdown" data-bs-toggle="dropdown" aria-expanded="false" style="font-size: 11px; height: 25px; width: 25px;">
                        <i class="feather-more-horizontal fs-12"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0" aria-labelledby="headerMoreActionsDropdown" style="font-size: 12px; min-width: 140px; border-radius: 4px;">
                        <li>
                            <a class="dropdown-item py-2" href="{{ route('crm.leads.show', ['lead' => $lead->id, 'edit_lead' => 1]) }}">Edit Lead</a>
                        </li>
                    </ul>
                </div>
                
                <!-- Pagination Arrows -->
                <div class="d-flex align-items-center ms-1 border rounded px-1 py-0.5 bg-white">
                    @if($prevLead)
                        <a href="{{ route('crm.leads.show', $prevLead->id) }}" class="btn btn-xs btn-link text-dark p-1 border-0 d-inline-flex align-items-center justify-content-center" title="Previous Lead">
                            <i class="feather-chevron-left fs-12"></i>
                        </a>
                    @else
                        <button class="btn btn-xs btn-link p-1 border-0 d-inline-flex align-items-center justify-content-center text-muted opacity-50" style="cursor: not-allowed;" disabled>
                            <i class="feather-chevron-left fs-12"></i>
                        </button>
                    @endif

                    @if($nextLead)
                        <a href="{{ route('crm.leads.show', $nextLead->id) }}" class="btn btn-xs btn-link text-dark p-1 border-0 d-inline-flex align-items-center justify-content-center" title="Next Lead">
                            <i class="feather-chevron-right fs-12"></i>
                        </a>
                    @else
                        <button class="btn btn-xs btn-link p-1 border-0 d-inline-flex align-items-center justify-content-center text-muted opacity-50" style="cursor: not-allowed;" disabled>
                            <i class="feather-chevron-right fs-12"></i>
                        </button>
                    @endif
                </div>
            </div>
        </div>

        <!-- ==================== ZOHO CRM TWO-COLUMN FLEX CONTENT ==================== -->
        <div class="d-flex flex-grow-1 overflow-hidden" style="min-height: 0;">
            
            <!-- Left Sidebar Menu (STICKY / fixed height column) -->
            <div class="zoho-sidebar-col border-end bg-white d-print-none h-100 overflow-auto" style="width: 200px; flex-shrink: 0; user-select: none;">
                <div class="p-3">
                    <h6 class="text-uppercase fw-bold text-muted mb-3" style="font-size: 10px; letter-spacing: 0.8px;">Related List</h6>
                    <ul class="nav flex-column zoho-sidebar-nav gap-1" id="zohoSidebarLinks">
                        <li class="nav-item">
                            <a href="#sectionNotes" class="nav-link active py-1.5 px-2 fs-12 rounded text-dark fw-medium">Notes</a>
                        </li>
                        <li class="nav-item">
                            <a href="#subtab-interactions" class="nav-link py-1.5 px-2 fs-12 rounded text-dark">Open Activities</a>
                        </li>
                        <li class="nav-item">
                            <a href="#subtab-history" class="nav-link py-1.5 px-2 fs-12 rounded text-dark">History</a>
                        </li>
                        <li class="nav-item">
                            <a href="#sectionLeadInfo" class="nav-link py-1.5 px-2 fs-12 rounded text-dark">Lead Information</a>
                        </li>
                        <li class="nav-item">
                            <a href="#sectionAddressInfo" class="nav-link py-1.5 px-2 fs-12 rounded text-dark">Address Details</a>
                        </li>
                        <li class="nav-item">
                            <a href="#sectionRequirements" class="nav-link py-1.5 px-2 fs-12 rounded text-dark">Requirements</a>
                        </li>
                        @if ($activeQuotation && $activeQuotation->getRevisionHistory()->count() > 1)
                            <li class="nav-item">
                                <a href="#sectionQuotationHistory" class="nav-link py-1.5 px-2 fs-12 rounded text-dark">Quotation Revision History</a>
                            </li>
                        @endif
                    </ul>
                </div>
            </div>

            <!-- Right Content Area (SCROLLABLE column) -->
            <div class="zoho-main-col h-100 overflow-auto flex-grow-1" style="scroll-behavior: smooth; background-color: #f8fafc;" id="zohoMainScrollable">
                
                <!-- Tab Menu Row (Sticky inside the scrollable container) -->
                <div class="d-flex align-items-center justify-content-between border-bottom px-3 py-2 bg-light-50 flex-wrap gap-2 sticky-top" style="z-index: 90; background-color: #f8fafc;">
                    <ul class="nav nav-pills zoho-nav-tabs" id="zohoLeadTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link px-3 py-1 fw-bold fs-12 rounded-pill {{ !request()->has('create_quotation') && !request()->has('edit_quotation') && !request()->has('view_quotation') ? 'active' : '' }}" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview-pane" type="button" role="tab" aria-controls="overview-pane" aria-selected="true">
                                Overview
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link px-3 py-1 fw-bold fs-12 rounded-pill" id="timeline-tab" data-bs-toggle="tab" data-bs-target="#timeline-pane" type="button" role="tab" aria-controls="timeline-pane" aria-selected="false">
                                Timeline
                            </button>
                        </li>
                        @if ($activeQuotation || request()->has('create_quotation'))
                            <li class="nav-item" role="presentation">
                                <button class="nav-link px-3 py-1 fw-bold fs-12 rounded-pill {{ request()->has('create_quotation') || request()->has('edit_quotation') || request()->has('view_quotation') ? 'active' : '' }}" id="quotation-tab" data-bs-toggle="tab" data-bs-target="#quotation-pane" type="button" role="tab" aria-controls="quotation-pane" aria-selected="false">
                                    Quotation
                                </button>
                            </li>
                        @endif
                    </ul>

                    <!-- Clock / Last Update Information -->
                    <div class="d-flex align-items-center text-muted fs-11 fw-medium" style="font-family: 'Inter', sans-serif;">
                        <i class="feather-clock me-1.5 text-muted fs-12"></i> 
                        Last Update : {{ $lead->updated_at ? $lead->updated_at->diffForHumans() : 'Recently' }}
                    </div>
                </div>

                <!-- Main Scrollable Tab Content View -->
                <div class="pt-2 px-3 pb-3 tab-content" id="zohoLeadTabsContent">
                    
                    <!-- ==================== TAB 1: OVERVIEW PANE ==================== -->
                    <div class="tab-pane fade show {{ !request()->has('create_quotation') && !request()->has('edit_quotation') && !request()->has('view_quotation') ? 'active' : '' }}" id="overview-pane" role="tabpanel" aria-labelledby="overview-tab">
                        
                        @if (request()->has('edit_lead'))
                            <!-- ==================== STATE: EDIT LEAD FORM ==================== -->
                            <form action="{{ route('crm.leads.update', $lead->id) }}" method="POST">
                                @csrf
                                @method('PUT')
                                
                                <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 flex-wrap gap-2">
                                    <h5 class="fw-bold text-dark mb-0">Edit Lead details</h5>
                                    <div class="d-flex gap-2">
                                        <a href="{{ route('crm.leads.show', $lead->id) }}" class="btn btn-sm btn-light border fs-12">Cancel</a>
                                        <button type="submit" class="btn btn-sm btn-primary py-1.5 px-3 fw-bold fs-12" style="background-color: #1e40af; border-color: #1e40af;">Save Changes</button>
                                    </div>
                                </div>

                                <div class="row g-4 fs-13 text-dark">
                                    <div class="col-md-6 border-end">
                                        <h6 class="fw-bold text-primary mb-3">Company & Contact Info</h6>
                                        
                                        <div class="odoo-form-group mb-3">
                                            <label class="odoo-form-label">Company Name <span class="text-danger">*</span></label>
                                            <div class="flex-grow-1">
                                                <input type="text" name="company_name" value="{{ old('company_name', $lead->company_name) }}" class="odoo-form-control" required placeholder="e.g. Acme Corporation">
                                            </div>
                                        </div>

                                        <div class="odoo-form-group mb-3">
                                            <label class="odoo-form-label">Contact Person</label>
                                            <div class="flex-grow-1">
                                                <input type="text" name="contact_person" value="{{ old('contact_person', $lead->contact_person) }}" class="odoo-form-control" placeholder="Contact Representative">
                                            </div>
                                        </div>

                                        <div class="odoo-form-group mb-3">
                                            <label class="odoo-form-label">Email Address</label>
                                            <div class="flex-grow-1">
                                                <input type="email" name="email" value="{{ old('email', $lead->email) }}" class="odoo-form-control" placeholder="email@address.com">
                                            </div>
                                        </div>

                                        <div class="odoo-form-group mb-3">
                                            <label class="odoo-form-label">Phone Number</label>
                                            <div class="flex-grow-1">
                                                <input type="text" name="phone" value="{{ old('phone', $lead->phone) }}" class="odoo-form-control" placeholder="+00 000 000 0000">
                                            </div>
                                        </div>

                                        <div class="odoo-form-group mb-3">
                                            <label class="odoo-form-label">Lead Owner</label>
                                            <div class="flex-grow-1">
                                                <select name="lead_owner_id" class="odoo-form-control form-select-sm">
                                                    <option value="">Unassigned</option>
                                                    @foreach($users as $user)
                                                        <option value="{{ $user->id }}" @selected(old('lead_owner_id', $lead->lead_owner_id) == $user->id)>{{ $user->name }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>
                                        
                                        <h6 class="fw-bold text-primary mb-3 mt-4">Location Details</h6>

                                        <div class="odoo-form-group mb-3">
                                            <label class="odoo-form-label">Street Address</label>
                                            <div class="flex-grow-1">
                                                <textarea name="address" rows="2" class="odoo-form-control" placeholder="Street address...">{{ old('address', $lead->address) }}</textarea>
                                            </div>
                                        </div>

                                        <div class="odoo-form-group mb-3">
                                            <label class="odoo-form-label">City / State / Country</label>
                                            <div class="flex-grow-1 d-flex gap-2">
                                                <input type="text" name="city" value="{{ old('city', $lead->city) }}" class="odoo-form-control" placeholder="City">
                                                <input type="text" name="state" value="{{ old('state', $lead->state) }}" class="odoo-form-control" placeholder="State">
                                                <input type="text" name="country" value="{{ old('country', $lead->country) }}" class="odoo-form-control" placeholder="Country">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="col-md-6">
                                        <h6 class="fw-bold text-primary mb-3">Requirements & Pricing</h6>

                                        <div class="odoo-form-group mb-3">
                                            <label class="odoo-form-label">Product Interest</label>
                                            <div class="flex-grow-1">
                                                <select name="product_id" class="odoo-form-control odoo-select2 erp-premium-select" data-master="product" style="width:100%;">
                                                    <option value="">-- Select a Product --</option>
                                                    <option value="__ADD_NEW__" class="fw-bold text-primary" data-master="product">+ Add New Product</option>
                                                    @foreach($products as $prod)
                                                        <option value="{{ $prod->id }}" {{ old('product_id', $lead->product_id) == $prod->id ? 'selected' : '' }}>
                                                            {{ $prod->name }} ({{ $prod->sku }})
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>

                                        <div class="odoo-form-group mb-3">
                                            <label class="odoo-form-label">Expected Revenue (₹)</label>
                                            <div class="flex-grow-1">
                                                <input type="number" name="expected_amount" value="{{ old('expected_amount', $lead->expected_amount) }}" min="0" step="0.01" class="odoo-form-control" placeholder="Expected Revenue (₹)">
                                            </div>
                                        </div>

                                        <div class="odoo-form-group mb-3">
                                            <label class="odoo-form-label">Expected Sale Date</label>
                                            <div class="flex-grow-1">
                                                <input type="date" name="expected_sale_date" value="{{ old('expected_sale_date', $lead->expected_sale_date ? $lead->expected_sale_date->format('Y-m-d') : '') }}" class="odoo-form-control">
                                            </div>
                                        </div>

                                        <h6 class="fw-bold text-primary mb-3 mt-4">Segmentation & Sources</h6>

                                        <div class="odoo-form-group mb-3">
                                            <label class="odoo-form-label">Lead Source</label>
                                            <div class="flex-grow-1">
                                                <select name="source" class="odoo-form-control form-select-sm">
                                                    <option value="Select an Option">Select an Option</option>
                                                    @foreach (['Cold Call', 'Employee Referral', 'Partner', 'Web Search', 'Advertisement', 'Trade Show'] as $srcOption)
                                                        <option value="{{ $srcOption }}" @selected(old('source', $lead->source) === $srcOption)>{{ $srcOption }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>

                                        <div class="odoo-form-group mb-3">
                                            <label class="odoo-form-label">Priority</label>
                                            <div class="flex-grow-1">
                                                <select name="priority" class="odoo-form-control form-select-sm">
                                                    <option value="Select an Option">Select an Option</option>
                                                    @foreach (['Low', 'Medium', 'High'] as $prioOption)
                                                        <option value="{{ $prioOption }}" @selected(old('priority', $lead->priority) === $prioOption)>{{ $prioOption }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>

                                        <div class="odoo-form-group mb-3">
                                            <label class="odoo-form-label">Segment</label>
                                            <div class="flex-grow-1">
                                                <select name="segment" class="odoo-form-control form-select-sm">
                                                    <option value="Select an Option">Select an Option</option>
                                                    @foreach (['SMB', 'Mid-Market', 'Enterprise'] as $segOption)
                                                        <option value="{{ $segOption }}" @selected(old('segment', $lead->segment) === $segOption)>{{ $segOption }}</option>
                                                    @endforeach
                                                </select>
                                            </div>
                                        </div>

                                        <div class="odoo-form-group mb-3">
                                            <label class="odoo-form-label">Industry Type</label>
                                            <div class="flex-grow-1">
                                                <input type="text" name="industry_type" value="{{ old('industry_type', $lead->industry_type) }}" class="odoo-form-control" placeholder="Industry/Vertical">
                                            </div>
                                        </div>

                                        <div class="odoo-form-group mb-3">
                                            <label class="odoo-form-label">Initial Call Date</label>
                                            <div class="flex-grow-1">
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text bg-light border-0 border-bottom rounded-0"><i class="feather-calendar fs-11 text-muted"></i></span>
                                                    <input type="text" class="form-control odoo-form-control" name="call_date" id="lead_call_date_picker" value="{{ old('call_date', $lead->call_date ? $lead->call_date->format('Y-m-d h:i A') : '') }}" placeholder="Call Schedule">
                                                </div>
                                            </div>
                                        </div>

                                        <div class="mt-4">
                                            <label class="form-label fw-bold text-muted text-uppercase mb-1" style="font-size: 10px;">Requirement Description</label>
                                            <textarea name="requirement" rows="4" class="form-control text-dark" style="border: 1px solid #ced4da; padding: 6px; border-radius: 4px;" placeholder="Details about initial inquiry, product scope, business size, etc...">{{ old('requirement', $lead->requirement) }}</textarea>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        @else
                            <!-- ==================== DEFAULT VIEW: ZOHO CRM FIELD CONTAINER ==================== -->
                            <!-- 2. Detailed Fields Section -->
                            <div id="detailedFieldsContainer" style="transition: all 0.3s ease;">
                                <!-- Lead Information Card -->
                                <div class="card border shadow-sm mb-3" style="border-radius: 4px; border-color: #e2e8f0 !important; background-color: #ffffff;" id="sectionLeadInfo">
                                    <div class="card-body p-3">
                                        <div class="d-flex justify-content-between align-items-center pb-2 border-bottom mb-3">
                                            <h5 class="zoho-section-title fs-13 text-dark fw-bold mb-0" style="font-family: 'Inter', sans-serif; border-bottom: none;">Lead Information</h5>
                                            @if($lead->status === 'Qualified' && !$activeQuotation && !request()->has('create_quotation'))
                                                <form action="{{ route('crm.leads.convertToQuotation', $lead->id) }}" method="POST" class="d-inline m-0 p-0">
                                                    @csrf
                                                    <button type="submit" class="btn btn-xs btn-success text-white fw-bold px-2 py-0.5 d-inline-flex align-items-center shadow-sm text-uppercase" style="font-size: 10px; border-radius: 3px; background-color: #16a34a; border-color: #16a34a; white-space: nowrap; line-height: 1.4;">
                                                        <i class="feather-shuffle me-1 fs-9"></i> Convert to Quotation
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                        <div class="row g-0">
                                            <div class="col-md-6 pe-md-4">
                                                <div class="zoho-field-row">
                                                    <div class="zoho-field-label">Lead Owner</div>
                                                    <div class="zoho-field-value text-dark">
                                                        <form action="{{ route('crm.leads.updateOwner', $lead->id) }}" method="POST" class="d-inline">
                                                            @csrf
                                                            @method('PATCH')
                                                            <select name="lead_owner_id" class="form-select form-select-sm fw-semibold d-inline-block w-auto py-0.5 px-2 border-0 bg-transparent text-primary" onchange="this.form.submit()" style="box-shadow: none; font-size: 13px; padding-left: 0;">
                                                                <option value="">Unassigned</option>
                                                                @foreach($users as $user)
                                                                    <option value="{{ $user->id }}" @selected($lead->lead_owner_id == $user->id)>{{ $user->name }}</option>
                                                                @endforeach
                                                            </select>
                                                        </form>
                                                    </div>
                                                </div>
                                                <div class="zoho-field-row">
                                                    <div class="zoho-field-label">Lead Name</div>
                                                    <div class="zoho-field-value text-dark">{{ $lead->contact_person ?: '—' }}</div>
                                                </div>
                                                <div class="zoho-field-row">
                                                    <div class="zoho-field-label">Email</div>
                                                    <div class="zoho-field-value">
                                                        @if($lead->email)
                                                            <a href="mailto:{{ $lead->email }}" class="text-primary hover-underline">{{ $lead->email }}</a>
                                                        @else
                                                            —
                                                        @endif
                                                    </div>
                                                </div>
                                                <div class="zoho-field-row">
                                                    <div class="zoho-field-label">Phone</div>
                                                    <div class="zoho-field-value text-dark">{{ $lead->phone ?: '—' }}</div>
                                                </div>
                                                <div class="zoho-field-row">
                                                    <div class="zoho-field-label">Lead Source</div>
                                                    <div class="zoho-field-value">
                                                        <span class="badge bg-light text-dark border px-2 py-0.5" style="font-size: 11px;">{{ $lead->source ?: '—' }}</span>
                                                    </div>
                                                </div>
                                                <div class="zoho-field-row">
                                                    <div class="zoho-field-label">Product Interested</div>
                                                    <div class="zoho-field-value text-dark">{{ $lead->product?->name ?: '—' }}</div>
                                                </div>
                                                <div class="zoho-field-row">
                                                    <div class="zoho-field-label">Segment</div>
                                                    <div class="zoho-field-value text-dark">{{ $lead->segment ?: '—' }}</div>
                                                </div>
                                            </div>
                                            <div class="col-md-6 ps-md-4">
                                                <div class="zoho-field-row">
                                                    <div class="zoho-field-label">Company</div>
                                                    <div class="zoho-field-value text-dark">{{ $lead->company_name }}</div>
                                                </div>
                                                <div class="zoho-field-row">
                                                    <div class="zoho-field-label">Lead Status</div>
                                                    <div class="zoho-field-value text-primary fw-bold" style="width: 100%; max-width: 250px;">
                                                        <form action="{{ route('crm.leads.updateStatus', $lead->id) }}" method="POST" class="d-inline m-0 p-0 w-100">
                                                            @csrf
                                                            @method('PATCH')
                                                            <select class="form-select odoo-select2 status-select" name="status" style="border-radius:0;">
                                                                <option value="New" @selected($lead->status === 'New' || !$lead->status)>New</option>
                                                                <option value="Contacted" @selected($lead->status === 'Contacted')>Contacted</option>
                                                                <option value="Follow-up Scheduled" @selected($lead->status === 'Follow-up Scheduled')>Follow-up Scheduled</option>
                                                                <option value="Qualified" @selected($lead->status === 'Qualified')>Qualified</option>
                                                                <option value="Converted" @selected($lead->status === 'Converted')>Converted</option>
                                                                <option value="Lost" @selected($lead->status === 'Lost')>Lost</option>
                                                            </select>
                                                        </form>
                                                    </div>
                                                </div>
                                                <div class="zoho-field-row">
                                                    <div class="zoho-field-label">Expected Revenue</div>
                                                    <div class="zoho-field-value text-dark fw-bold">₹{{ $lead->expected_amount ? number_format($lead->expected_amount, 2) : '0.00' }}</div>
                                                </div>
                                                <div class="zoho-field-row">
                                                    <div class="zoho-field-label">Expected Sale Date</div>
                                                    <div class="zoho-field-value text-dark">{{ $lead->expected_sale_date ? $lead->expected_sale_date->format('d/m/Y') : '—' }}</div>
                                                </div>
                                                <div class="zoho-field-row">
                                                    <div class="zoho-field-label">Priority</div>
                                                    <div class="zoho-field-value">
                                                        @php
                                                            $prioBadge = 'bg-secondary';
                                                            if($lead->priority === 'High') $prioBadge = 'bg-danger';
                                                            elseif($lead->priority === 'Medium') $prioBadge = 'bg-warning text-dark';
                                                            elseif($lead->priority === 'Low') $prioBadge = 'bg-info text-white';
                                                        @endphp
                                                        <span class="badge {{ $prioBadge }} px-2 py-0.5" style="font-size: 11px;">{{ $lead->priority ?: '—' }}</span>
                                                    </div>
                                                </div>
                                                <div class="zoho-field-row">
                                                    <div class="zoho-field-label">Industry Type</div>
                                                    <div class="zoho-field-value text-dark">{{ $lead->industry_type ?: '—' }}</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Address Details Card -->
                                <div class="card border shadow-sm mb-3" style="border-radius: 4px; border-color: #e2e8f0 !important; background-color: #ffffff;" id="sectionAddressInfo">
                                    <div class="card-body p-3">
                                        <h5 class="zoho-section-title fs-13 text-dark fw-bold pb-2 border-bottom mb-3" style="font-family: 'Inter', sans-serif;">Address Details</h5>
                                        <div class="row g-0">
                                            <div class="col-md-6 pe-md-4">
                                                <div class="zoho-field-row">
                                                    <div class="zoho-field-label">Street</div>
                                                    <div class="zoho-field-value text-wrap text-dark" style="max-width: 350px;">{{ $lead->address ?: 'No street address specified' }}</div>
                                                </div>
                                                <div class="zoho-field-row">
                                                    <div class="zoho-field-label">State</div>
                                                    <div class="zoho-field-value text-dark">{{ $lead->state ?: '—' }}</div>
                                                </div>
                                                <div class="zoho-field-row">
                                                    <div class="zoho-field-label">Country</div>
                                                    <div class="zoho-field-value text-dark">{{ $lead->country ?: '—' }}</div>
                                                </div>
                                            </div>
                                            <div class="col-md-6 ps-md-4">
                                                <div class="zoho-field-row">
                                                    <div class="zoho-field-label">City</div>
                                                    <div class="zoho-field-value text-dark">{{ $lead->city ?: '—' }}</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Requirements Details Card -->
                                <div class="card border shadow-sm mb-3" style="border-radius: 4px; border-color: #e2e8f0 !important; background-color: #ffffff;" id="sectionRequirements">
                                    <div class="card-body p-3">
                                        <h5 class="zoho-section-title fs-13 text-dark fw-bold pb-2 border-bottom mb-3" style="font-family: 'Inter', sans-serif;">Requirements Details</h5>
                                        @if ($lead->requirement)
                                            <div class="text-dark fs-13 bg-light-50 p-3 border rounded" style="white-space: pre-wrap; line-height: 1.6; font-family: 'Inter', sans-serif;">{{ $lead->requirement }}</div>
                                        @else
                                            <p class="text-muted fs-12 italic mb-0">No requirements details specified for this lead.</p>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Static Notes Display Card -->
                            <div class="card border shadow-sm mb-3" style="border-radius: 4px; border-color: #e2e8f0 !important; background-color: #ffffff; font-family: 'Inter', sans-serif;" id="sectionNotes">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
                                        <h6 class="fw-bold text-dark mb-0 fs-13"><i class="feather-file-text me-2 text-primary"></i>Notes / Logs</h6>
                                        <button class="btn btn-xs btn-primary fw-bold" data-bs-toggle="modal" data-bs-target="#modalLogNote" style="background-color: #1e40af; border-color: #1e40af;"><i class="feather-plus me-1"></i> Add Note</button>
                                    </div>
                                    @if($lead->followups->isEmpty())
                                        <p class="text-muted fs-12 mb-0 italic">No notes created yet. Click "Add Note" to log lead interaction notes.</p>
                                    @else
                                        <div class="activity-feed-compact fs-12 text-dark">
                                            @foreach($lead->followups->take(3) as $followup)
                                                <div class="p-2 border-bottom bg-white rounded mb-2">
                                                    <div class="d-flex justify-content-between text-muted fs-10 mb-1">
                                                        <span class="fw-semibold text-uppercase text-primary">{{ $followup->type }}</span>
                                                        <span>{{ $followup->followup_date->diffForHumans() }}</span>
                                                    </div>
                                                    <p class="mb-0 fw-medium text-dark">{{ $followup->notes }}</p>
                                                </div>
                                            @endforeach
                                            @if($lead->followups->count() > 3)
                                                <a href="javascript:void(0)" onclick="$('#timeline-tab').tab('show')" class="text-primary fs-11 fw-semibold d-inline-block mt-1">View all {{ $lead->followups->count() }} notes in Timeline &rarr;</a>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div> <!-- End TAB 1: OVERVIEW PANE -->

                    <!-- ==================== TAB 2: TIMELINE PANE (ACTIVITIES & HISTORY) ==================== -->
                    <div class="tab-pane fade" id="timeline-pane" role="tabpanel" aria-labelledby="timeline-tab">
                        <div class="card border shadow-sm" style="border-radius: 4px; border-color: #e2e8f0 !important; background-color: #ffffff;">
                            <div class="card-body p-3">
                                
                                <!-- Subtabs selector row -->
                                <div class="border-bottom pb-1 mb-3">
                                    <ul class="nav nav-tabs border-bottom-0 zoho-timeline-subtabs" id="zohoTimelineSubTabs" role="tablist">
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link active py-2 px-3 border-0 bg-transparent" id="subtab-history-tab" data-bs-toggle="tab" data-bs-target="#subtab-history" type="button" role="tab" aria-controls="subtab-history" aria-selected="true">
                                                History
                                            </button>
                                        </li>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link py-2 px-3 border-0 bg-transparent" id="subtab-interactions-tab" data-bs-toggle="tab" data-bs-target="#subtab-interactions" type="button" role="tab" aria-controls="subtab-interactions" aria-selected="false">
                                                Interactions
                                            </button>
                                        </li>
                                    </ul>
                                </div>
                                
                                <!-- Subtabs Content -->
                                <div class="tab-content" id="zohoTimelineSubTabsContent">
                                    
                                    <!-- SUBTAB 1: HISTORY TIMELINE -->
                                    <div class="tab-pane fade show active" id="subtab-history" role="tabpanel" aria-labelledby="subtab-history-tab">
                                        <div class="d-flex align-items-center justify-content-between mb-4 mt-1 flex-wrap gap-2">
                                            <div class="d-flex align-items-center gap-2">
                                                <h5 class="fw-bold text-dark fs-14 mb-0">Timeline History</h5>
                                                <button class="btn btn-xs btn-outline-secondary border-0 p-1" title="Filter History"><i class="feather-filter fs-12"></i></button>
                                            </div>
                                            <div class="text-muted fs-11" style="font-family: 'Inter', sans-serif;">
                                                No upcoming automated actions &bull; <a href="javascript:void(0)" class="text-primary hover-underline">Hide Upcoming Automated Actions</a>
                                            </div>
                                        </div>

                                        <div class="zoho-timeline-container">
                                            @php
                                                $groupedHistory = $lead->histories->groupBy(function($item) {
                                                    return $item->created_at->format('d/m/Y');
                                                });
                                            @endphp

                                            @if($groupedHistory->isEmpty())
                                                <div class="text-center py-5 text-muted border border-dashed rounded bg-white fs-12">
                                                    <i class="feather-clock fs-24 mb-1.5 d-block text-muted opacity-50"></i>
                                                    No history tracking events recorded yet.
                                                </div>
                                            @else
                                                @foreach($groupedHistory as $date => $items)
                                                    <!-- Date Header -->
                                                    <div class="zoho-timeline-date-group">
                                                        <div class="zoho-timeline-date-header">{{ $date }}</div>
                                                        
                                                        @foreach($items as $item)
                                                            <!-- Timeline Row -->
                                                            <div class="zoho-timeline-event d-flex align-items-start">
                                                                <div class="zoho-timeline-line"></div>
                                                                
                                                                @php
                                                                    $icon = 'feather-info';
                                                                    if ($item->event_type === 'created') $icon = 'feather-plus';
                                                                    elseif ($item->event_type === 'assigned') $icon = 'feather-user';
                                                                    elseif ($item->event_type === 'status_changed') $icon = 'feather-refresh-cw';
                                                                    elseif ($item->event_type === 'quotation_created') $icon = 'feather-file-text';
                                                                    elseif ($item->event_type === 'quotation_status_changed') $icon = 'feather-edit';
                                                                    elseif ($item->event_type === 'activity_scheduled') $icon = 'feather-calendar';
                                                                    elseif ($item->event_type === 'activity_completed') $icon = 'feather-check-circle';
                                                                    elseif ($item->event_type === 'activity_deleted') $icon = 'feather-trash-2';
                                                                @endphp
                                                                <div class="zoho-timeline-icon">
                                                                    <i class="{{ $icon }}"></i>
                                                                </div>
                                                                
                                                                <div class="zoho-timeline-content d-flex align-items-center gap-3 w-100">
                                                                    <div class="zoho-timeline-time">{{ $item->created_at->format('h:i A') }}</div>
                                                                    <div>
                                                                        <span class="fs-13 fw-semibold text-dark">{{ $item->notes }}</span>
                                                                        @if($item->old_value || $item->new_value)
                                                                            <span class="fs-11 text-muted ms-2 bg-light px-1.5 py-0.5 rounded">
                                                                                @if($item->old_value)
                                                                                    <del>{{ $item->old_value }}</del> <i class="feather-arrow-right mx-0.5"></i>
                                                                                @endif
                                                                                <strong class="text-success">{{ $item->new_value }}</strong>
                                                                            </span>
                                                                        @endif
                                                                        <div class="text-muted fs-11 mt-0.5">
                                                                            by {{ $item->user?->name ?: 'System' }} {{ $item->created_at->format('d/m/Y') }}
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                @endforeach
                                            @endif
                                        </div>
                                    </div>

                                    <!-- SUBTAB 2: INTERACTIONS (ACTIVITIES) TIMELINE -->
                                    <div class="tab-pane fade" id="subtab-interactions" role="tabpanel" aria-labelledby="subtab-interactions-tab">
                                        <div class="d-flex align-items-center justify-content-between mb-4 mt-1 flex-wrap gap-2">
                                            <h5 class="fw-bold text-dark fs-14 mb-0">Interactions / Scheduled Activities</h5>
                                            <button type="button" class="btn btn-xs btn-outline-primary fw-bold" data-bs-toggle="modal" data-bs-target="#modalScheduleActivity">
                                                <i class="feather-calendar me-1"></i>Schedule Activity
                                            </button>
                                        </div>

                                        <div class="zoho-timeline-container">
                                            @php
                                                $groupedFollowups = $lead->followups->groupBy(function($item) {
                                                    return $item->followup_date->format('d/m/Y');
                                                });
                                            @endphp

                                            @if($groupedFollowups->isEmpty())
                                                <div class="text-center py-5 text-muted border border-dashed rounded bg-white fs-12">
                                                    <i class="feather-clock fs-24 mb-1.5 d-block text-muted opacity-50"></i>
                                                    No activity or interaction logs recorded yet.
                                                </div>
                                            @else
                                                @foreach($groupedFollowups as $date => $items)
                                                    <!-- Date Header -->
                                                    <div class="zoho-timeline-date-group">
                                                        <div class="zoho-timeline-date-header">{{ $date }}</div>
                                                        
                                                        @foreach($items as $item)
                                                            <!-- Timeline Row -->
                                                            <div class="zoho-timeline-event d-flex align-items-start">
                                                                <div class="zoho-timeline-line"></div>
                                                                
                                                                @php
                                                                    $icon = 'feather-phone';
                                                                    if($item->type === 'Email') $icon = 'feather-mail';
                                                                    elseif($item->type === 'Meeting') $icon = 'feather-users';
                                                                    elseif($item->type === 'Demo') $icon = 'feather-monitor';
                                                                @endphp
                                                                <div class="zoho-timeline-icon">
                                                                    <i class="{{ $icon }}"></i>
                                                                </div>
                                                                
                                                                <div class="zoho-timeline-content d-flex align-items-start gap-3 w-100">
                                                                    <div class="zoho-timeline-time mt-0.5">{{ $item->followup_date->format('h:i A') }}</div>
                                                                    <div class="flex-grow-1">
                                                                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                                                            <div>
                                                                                <span class="badge bg-soft-primary text-primary text-uppercase fs-10 px-2 py-0.5 fw-bold me-2">{{ $item->type }}</span>
                                                                                @if($item->status === 'Pending')
                                                                                    <span class="badge bg-soft-warning text-warning fs-10 px-1.5 py-0.5 fw-bold">Pending</span>
                                                                                @else
                                                                                    <span class="badge bg-soft-success text-success fs-10 px-1.5 py-0.5 fw-bold">Completed</span>
                                                                                @endif
                                                                            </div>
                                                                            
                                                                            <!-- Action Buttons -->
                                                                            <div class="d-flex gap-1 d-print-none">
                                                                                @if($item->status === 'Pending')
                                                                                    <form action="{{ route('crm.followups.update', $item->id) }}" method="POST" class="d-inline">
                                                                                        @csrf
                                                                                        @method('PUT')
                                                                                        <input type="hidden" name="status" value="Completed">
                                                                                        <button type="submit" class="btn btn-icon btn-xs btn-soft-success" title="Mark Done" style="width:20px;height:20px;display:inline-flex;align-items:center;justify-content:center;">
                                                                                            <i class="feather-check" style="font-size: 10px;"></i>
                                                                                        </button>
                                                                                    </form>
                                                                                @endif
                                                                                <form action="{{ route('crm.followups.destroy', $item->id) }}" method="POST" class="d-inline">
                                                                                    @csrf
                                                                                    @method('DELETE')
                                                                                    <button type="submit" class="btn btn-icon btn-xs btn-soft-danger" onclick="return confirm('Are you sure you want to delete this log?')" style="width:20px;height:20px;display:inline-flex;align-items:center;justify-content:center;">
                                                                                        <i class="feather-trash-2" style="font-size: 10px;"></i>
                                                                                    </button>
                                                                                </form>
                                                                            </div>
                                                                        </div>
                                                                        
                                                                        <span class="fs-13 fw-semibold text-dark d-block mt-2">{{ $item->notes }}</span>
                                                                        <div class="text-muted fs-11 mt-0.5">
                                                                            by {{ $lead->owner?->name ?: 'System' }} {{ $item->followup_date->format('d/m/Y') }}
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                @endforeach
                                            @endif
                                        </div>
                                    </div>
                                    
                                </div>

                            </div>
                        </div>
                    </div> <!-- End TAB 2: TIMELINE PANE -->

                    <!-- ==================== TAB 3: QUOTATION PANE ==================== -->
                    @if ($activeQuotation || request()->has('create_quotation'))
                        <div class="tab-pane fade show {{ request()->has('create_quotation') || request()->has('edit_quotation') || request()->has('view_quotation') ? 'active' : '' }}" id="quotation-pane" role="tabpanel" aria-labelledby="quotation-tab">
                            <div class="py-1">
                                
                                @if (request()->has('create_quotation'))
                                    <!-- CREATE QUOTATION FORM -->
                                    <form action="{{ route('crm.quotations.store') }}" method="POST" id="quotationForm">
                                        @csrf
                                        <input type="hidden" name="lead_id" value="{{ $lead->id }}">
                                        
                                        <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">
                                            <h5 class="fw-bold text-dark mb-0">New Quotation</h5>
                                            <a href="{{ route('crm.leads.show', $lead->id) }}" class="btn btn-sm btn-light border">Cancel</a>
                                        </div>

                                        <div class="row g-4 mb-4 fs-13 text-dark">
                                            <div class="col-md-6">
                                                <input type="hidden" name="customer_id" value="{{ $customer ? $customer->id : '' }}">
                                                <x-ui.odoo-form-ui type="input" label="Customer" name="_customer_display"
                                                    :value="$lead->contact_person ?: ($lead->company_name ?: 'N/A')"
                                                    readonly="true"
                                                    style="font-weight: bold; color: var(--bs-primary); background-color: #f8f9fa;" />

                                                <x-ui.odoo-form-ui type="input" label="Email" name="email" :value="old('email', $lead->email)" />
                                                <x-ui.odoo-form-ui type="input" label="Phone" name="phone" :value="old('phone', $lead->phone)" />
                                            </div>
                                            <div class="col-md-6">
                                                <x-ui.odoo-form-ui type="input" label="Quotation Number" name="quotation_number"
                                                    :value="old('quotation_number', $nextQuotationNumber)" readonly="true"
                                                    style="font-weight: bold; color: #495057;" />

                                                <x-ui.odoo-form-ui type="input" label="Date" name="quotation_date"
                                                    :value="old('quotation_date', date('Y-m-d'))" />

                                                <x-ui.odoo-form-ui type="input" label="Expiration" name="expiration_date"
                                                    :value="old('expiration_date', date('Y-m-d', strtotime('+30 days')))" />

                                                <x-ui.odoo-form-ui type="select" label="Status" name="status" :required="true">
                                                    <option value="Draft" @selected(old('status') === 'Draft')>Draft</option>
                                                    <option value="Pending Approval" @selected(old('status') === 'Pending Approval')>Send for Approval</option>
                                                </x-ui.odoo-form-ui>
                                            </div>
                                        </div>

                                        <!-- Order Lines Table -->
                                        <div class="border-top pt-4">
                                            <h5 class="fw-bold text-dark mb-3 fs-14">Order Lines</h5>
                                            <div class="table-responsive">
                                                <table class="table odoo-table align-middle" id="itemsTable">
                                                    <thead>
                                                        <tr>
                                                            <th style="width: 45%;">Product / Description</th>
                                                            <th class="text-end" style="width: 12%;">Quantity</th>
                                                            <th class="text-end" style="width: 15%;">Unit Price (₹)</th>
                                                            <th class="text-end" style="width: 12%;">Taxes (%)</th>
                                                            <th class="text-end" style="width: 16%;">Amount</th>
                                                            <th class="text-center" style="width: 5%;"></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <!-- Dynamically generated rows -->
                                                    </tbody>
                                                </table>
                                            </div>
                                            <div class="mt-2.5">
                                                <button type="button" class="btn btn-xs btn-outline-primary fw-bold" id="addItemRow">
                                                    <i class="feather-plus me-1"></i>Add a product
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Subtotal / Discount / Totals -->
                                        <div class="row mt-4 pt-3 border-top text-dark fs-13">
                                            <div class="col-md-8">
                                                <div class="pe-md-4">
                                                    <div class="mb-3">
                                                        <label class="fw-semibold text-muted mb-1 fs-12">Terms & Conditions</label>
                                                        <textarea name="terms_conditions" class="form-control" rows="3" placeholder="Define payment terms, delivery schedules, etc." style="border-radius: 4px; font-size: 13px;">{{ old('terms_conditions') }}</textarea>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="fw-semibold text-muted mb-1 fs-12">Notes</label>
                                                        <textarea name="notes" class="form-control" rows="2" placeholder="Internal remarks or custom notes..." style="border-radius: 4px; font-size: 13px;">{{ old('notes') }}</textarea>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="d-flex justify-content-between py-1 border-bottom">
                                                    <span class="text-muted fw-semibold">Untaxed Amount:</span>
                                                    <span class="fw-bold text-dark" id="calcSubtotal">₹0.00</span>
                                                </div>
                                                <div class="d-flex justify-content-between py-1 border-bottom">
                                                    <span class="text-muted fw-semibold">Taxes:</span>
                                                    <span class="fw-bold text-dark" id="calcTax">₹0.00</span>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                                    <span class="text-muted fw-semibold">Discount (₹):</span>
                                                    <input type="number" name="discount" id="discountInput" class="form-control form-control-sm text-end fw-bold" style="width: 100px; border-radius: 4px;" value="{{ old('discount', 0) }}" min="0" step="0.01">
                                                </div>
                                                <div class="d-flex justify-content-between py-2 fs-15 border-bottom bg-light-50 px-2 rounded mt-1.5">
                                                    <span class="text-dark fw-bold">Total:</span>
                                                    <span class="fw-extrabold text-primary" id="calcTotal">₹0.00</span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                                            <a href="{{ route('crm.leads.show', $lead->id) }}" class="btn btn-md btn-light border py-2 px-4 shadow-sm fs-12">Discard</a>
                                            <button type="submit" class="btn btn-md btn-primary py-2 px-5 fw-bold shadow-sm fs-12" style="background-color: #1e40af; border-color: #1e40af;">Save Quotation</button>
                                        </div>
                                    </form>

                                @elseif (request()->has('edit_quotation') && $activeQuotation)
                                    <!-- EDIT QUOTATION FORM -->
                                    <form action="{{ route('crm.quotations.update', $activeQuotation->id) }}" method="POST" id="quotationForm">
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="lead_id" value="{{ $lead->id }}">

                                        <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">
                                            <h5 class="fw-bold text-dark mb-0">Edit Quotation: {{ $activeQuotation->quotation_number }}</h5>
                                            <a href="{{ route('crm.leads.show', ['lead' => $lead->id, 'view_quotation' => 1]) }}" class="btn btn-sm btn-light border">Cancel</a>
                                        </div>

                                        <div class="row g-4 mb-4 fs-13 text-dark">
                                            <div class="col-md-6">
                                                <input type="hidden" name="customer_id" value="{{ $customer ? $customer->id : '' }}">
                                                <x-ui.odoo-form-ui type="input" label="Customer" name="_customer_display"
                                                    :value="$lead->contact_person ?: ($lead->company_name ?: 'N/A')"
                                                    readonly="true"
                                                    style="font-weight: bold; color: var(--bs-primary); background-color: #f8f9fa;" />

                                                <x-ui.odoo-form-ui type="input" label="Email" name="email" :value="old('email', $activeQuotation->email ?: $lead->email)" />
                                                <x-ui.odoo-form-ui type="input" label="Phone" name="phone" :value="old('phone', $activeQuotation->phone ?: $lead->phone)" />
                                            </div>
                                            <div class="col-md-6">
                                                <x-ui.odoo-form-ui type="input" label="Quotation Number" name="quotation_number"
                                                    :value="$activeQuotation->quotation_number" readonly="true"
                                                    style="font-weight: bold; color: #495057;" />

                                                <x-ui.odoo-form-ui type="input" label="Date" name="quotation_date"
                                                    :value="old('quotation_date', $activeQuotation->quotation_date->format('Y-m-d'))" />

                                                <x-ui.odoo-form-ui type="input" label="Expiration" name="expiration_date"
                                                    :value="old('expiration_date', $activeQuotation->expiration_date ? $activeQuotation->expiration_date->format('Y-m-d') : '')" />

                                                <x-ui.odoo-form-ui type="select" label="Status" name="status" :required="true">
                                                     @if ($activeQuotation->status === 'Draft')
                                                         <option value="Draft" @selected(old('status', $activeQuotation->status) === 'Draft')>Draft</option>
                                                         <option value="Pending Approval" @selected(old('status', $activeQuotation->status) === 'Pending Approval')>Send for Approval</option>
                                                     @elseif ($activeQuotation->status === 'Pending Approval')
                                                         <option value="Pending Approval" @selected(old('status', $activeQuotation->status) === 'Pending Approval')>Pending Approval</option>
                                                     @elseif ($activeQuotation->status === 'Approved')
                                                         <option value="Approved" @selected(old('status', $activeQuotation->status) === 'Approved')>Approved</option>
                                                         <option value="Quotation Sent" @selected(old('status', $activeQuotation->status) === 'Quotation Sent')>Quotation Sent</option>
                                                         <option value="Accepted" @selected(old('status', $activeQuotation->status) === 'Accepted')>Accepted</option>
                                                         <option value="Rejected" @selected(old('status', $activeQuotation->status) === 'Rejected')>Rejected</option>
                                                         <option value="Quotation Rework" @selected(old('status', $activeQuotation->status) === 'Quotation Rework')>Quotation Rework</option>
                                                     @else
                                                         <option value="Draft" @selected(old('status', $activeQuotation->status) === 'Draft')>Draft</option>
                                                         <option value="Pending Approval" @selected(old('status', $activeQuotation->status) === 'Pending Approval')>Pending Approval</option>
                                                         <option value="Approved" @selected(old('status', $activeQuotation->status) === 'Approved')>Approved</option>
                                                         <option value="Quotation Sent" @selected(old('status', $activeQuotation->status) === 'Quotation Sent')>Quotation Sent</option>
                                                         <option value="Accepted" @selected(old('status', $activeQuotation->status) === 'Accepted')>Accepted</option>
                                                         <option value="Rejected" @selected(old('status', $activeQuotation->status) === 'Rejected')>Rejected</option>
                                                         <option value="Quotation Rework" @selected(old('status', $activeQuotation->status) === 'Quotation Rework')>Quotation Rework</option>
                                                     @endif
                                                </x-ui.odoo-form-ui>
                                            </div>
                                        </div>

                                        <!-- Order Lines Table -->
                                        <div class="border-top pt-4">
                                            <h5 class="fw-bold text-dark mb-3 fs-14">Order Lines</h5>
                                            <div class="table-responsive">
                                                <table class="table odoo-table align-middle" id="itemsTable">
                                                    <thead>
                                                        <tr>
                                                            <th style="width: 45%;">Product / Description</th>
                                                            <th class="text-end" style="width: 12%;">Quantity</th>
                                                            <th class="text-end" style="width: 15%;">Unit Price (₹)</th>
                                                            <th class="text-end" style="width: 12%;">Taxes (%)</th>
                                                            <th class="text-end" style="width: 16%;">Amount</th>
                                                            <th class="text-center" style="width: 5%;"></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <!-- Dynamically generated rows -->
                                                    </tbody>
                                                </table>
                                            </div>
                                            <div class="mt-2.5">
                                                <button type="button" class="btn btn-xs btn-outline-primary fw-bold" id="addItemRow">
                                                    <i class="feather-plus me-1"></i>Add a product
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Subtotal / Discount / Totals -->
                                        <div class="row mt-4 pt-3 border-top text-dark fs-13">
                                            <div class="col-md-8">
                                                <div class="pe-md-4">
                                                    <div class="mb-3">
                                                        <label class="fw-semibold text-muted mb-1 fs-12">Terms & Conditions</label>
                                                        <textarea name="terms_conditions" class="form-control" rows="3" placeholder="Define payment terms, delivery schedules, etc." style="border-radius: 4px; font-size: 13px;">{{ old('terms_conditions', $activeQuotation->terms_conditions) }}</textarea>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="fw-semibold text-muted mb-1 fs-12">Notes</label>
                                                        <textarea name="notes" class="form-control" rows="2" placeholder="Internal remarks or custom notes..." style="border-radius: 4px; font-size: 13px;">{{ old('notes', $activeQuotation->notes) }}</textarea>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="d-flex justify-content-between py-1 border-bottom">
                                                    <span class="text-muted fw-semibold">Untaxed Amount:</span>
                                                    <span class="fw-bold text-dark" id="calcSubtotal">₹0.00</span>
                                                </div>
                                                <div class="d-flex justify-content-between py-1 border-bottom">
                                                    <span class="text-muted fw-semibold">Taxes:</span>
                                                    <span class="fw-bold text-dark" id="calcTax">₹0.00</span>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                                    <span class="text-muted fw-semibold">Discount (₹):</span>
                                                    <input type="number" name="discount" id="discountInput" class="form-control form-control-sm text-end fw-bold" style="width: 100px; border-radius: 4px;" value="{{ old('discount', $activeQuotation->discount) }}" min="0" step="0.01">
                                                </div>
                                                <div class="d-flex justify-content-between py-2 fs-15 border-bottom bg-light-50 px-2 rounded mt-1.5">
                                                    <span class="text-dark fw-bold">Total:</span>
                                                    <span class="fw-extrabold text-primary" id="calcTotal">₹0.00</span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                                            <a href="{{ route('crm.leads.show', ['lead' => $lead->id, 'view_quotation' => 1]) }}" class="btn btn-md btn-light border py-2 px-4 shadow-sm fs-12">Discard</a>
                                            <button type="submit" class="btn btn-md btn-primary py-2 px-5 fw-bold shadow-sm fs-12" style="background-color: #1e40af; border-color: #1e40af;">Save Changes</button>
                                        </div>
                                    </form>

                                @elseif ($activeQuotation)
                                    <!-- VIEW QUOTATION DETAILS -->
                                    <div class="odoo-sheet rounded border p-4 bg-white" id="quotation-print-area">
                                        <div class="d-flex justify-content-between align-items-center pb-3 border-bottom mb-4 flex-wrap gap-2 d-print-none">
                                            <h4 class="fw-bold text-dark mb-0 fs-16">Quotation Sheet: {{ $activeQuotation->quotation_number }}</h4>
                                            <div class="d-flex flex-wrap gap-2">
                                                <!-- Hidden Iframe to render exact print format from quotations.show -->
                                                <iframe id="quotationPrintIframe" src="{{ route('crm.quotations.show', $activeQuotation->id) }}" style="display: none; width: 0; height: 0; border: none;"></iframe>
                                                <button onclick="printQuotationIframe()" class="btn btn-sm btn-primary" style="background-color: #1e40af; border-color: #1e40af;"><i class="feather-printer me-1"></i>Print / Download</button>
                                                <a href="{{ route('crm.quotations.show', $activeQuotation->id) }}" class="btn btn-sm btn-light border"><i class="feather-eye me-1"></i>View Full Quotation</a>
                                                <a href="{{ route('crm.leads.show', ['lead' => $lead->id, 'edit_quotation' => 1]) }}" class="btn btn-sm btn-light border"><i class="feather-edit-2 me-1"></i>Edit Quotation</a>
                                                @if ($activeQuotation->status === 'Draft' || $activeQuotation->status === 'Quotation Rework')
                                                     <form action="{{ route('crm.quotations.updateStatus', $activeQuotation->id) }}" method="POST" class="d-inline">
                                                         @csrf
                                                         @method('PATCH')
                                                         <input type="hidden" name="status" value="Pending Approval">
                                                         <button type="submit" class="btn btn-sm btn-warning"><i class="feather-send me-1"></i>Send for Approval</button>
                                                     </form>
                                                 @elseif ($activeQuotation->status === 'Pending Approval')
                                                     <form action="{{ route('crm.quotations.approve', $activeQuotation->id) }}" method="POST" class="d-inline">
                                                         @csrf
                                                         <button type="submit" class="btn btn-sm btn-success"><i class="feather-check me-1"></i>Approve</button>
                                                     </form>
                                                     <form action="{{ route('crm.quotations.reject', $activeQuotation->id) }}" method="POST" class="d-inline">
                                                         @csrf
                                                         <button type="submit" class="btn btn-sm btn-danger"><i class="feather-x me-1"></i>Reject</button>
                                                     </form>
                                                 @elseif ($activeQuotation->status === 'Approved')
                                                     <form action="{{ route('crm.quotations.updateStatus', $activeQuotation->id) }}" method="POST" class="d-inline">
                                                         @csrf
                                                         @method('PATCH')
                                                         <input type="hidden" name="status" value="Quotation Sent">
                                                         <button type="submit" class="btn btn-sm btn-primary" style="background-color: #1e40af; border-color: #1e40af;"><i class="feather-send me-1"></i>Mark Sent</button>
                                                     </form>
                                                 @elseif ($activeQuotation->status === 'Quotation Sent')
                                                     <form action="{{ route('crm.quotations.updateStatus', $activeQuotation->id) }}" method="POST" class="d-inline">
                                                         @csrf
                                                         @method('PATCH')
                                                         <input type="hidden" name="status" value="Accepted">
                                                         <button type="submit" class="btn btn-sm btn-success">Accept Quotation</button>
                                                     </form>
                                                     <form action="{{ route('crm.quotations.updateStatus', $activeQuotation->id) }}" method="POST" class="d-inline">
                                                         @csrf
                                                         @method('PATCH')
                                                         <input type="hidden" name="status" value="Rejected">
                                                         <button type="submit" class="btn btn-sm btn-danger">Reject</button>
                                                     </form>
                                                 @elseif ($activeQuotation->status === 'Accepted')
                                                     <a href="{{ route('sales.orders.create', ['quotation_id' => $activeQuotation->id]) }}" class="btn btn-sm btn-success">
                                                         <i class="feather-shopping-cart me-1"></i>Convert to Sales Order
                                                     </a>
                                                 @endif
                                            </div>
                                        </div>

                                        <!-- Quotation Details Table -->
                                        <div class="row g-4 mb-4 fs-13 text-dark">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="text-muted fs-11 text-uppercase fw-bold d-block mb-1">Customer / Account</label>
                                                    <div class="fw-bold text-dark fs-14">{{ $lead->company_name }}</div>
                                                    <div class="text-muted fs-12">{{ $lead->contact_person }}</div>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="text-muted fs-11 text-uppercase fw-bold d-block mb-1">Billing Address</label>
                                                    <div class="fs-12">{{ $lead->address ?: 'No address specified' }}<br>{{ $lead->city }} {{ $lead->state }} {{ $lead->country }}</div>
                                                </div>
                                            </div>
                                            <div class="col-md-6 border-start-md">
                                                <div class="row">
                                                    <div class="col-6 mb-3">
                                                        <label class="text-muted fs-11 text-uppercase fw-bold d-block mb-1">Date</label>
                                                        <div class="fw-semibold">{{ $activeQuotation->quotation_date->format('d M Y') }}</div>
                                                    </div>
                                                    <div class="col-6 mb-3">
                                                        <label class="text-muted fs-11 text-uppercase fw-bold d-block mb-1">Expiration Date</label>
                                                        <div class="fw-semibold text-danger">{{ $activeQuotation->expiration_date ? $activeQuotation->expiration_date->format('d M Y') : '—' }}</div>
                                                    </div>
                                                </div>
                                                <div class="mb-3">
                                                     <label class="text-muted fs-11 text-uppercase fw-bold d-block mb-1">Quotation Status</label>
                                                     @php
                                                         $activeQuoBadgeClass = 'bg-soft-secondary text-secondary';
                                                         if ($activeQuotation->status === 'Quotation Sent' || $activeQuotation->status === 'Sent') $activeQuoBadgeClass = 'bg-soft-info text-info';
                                                         elseif ($activeQuotation->status === 'Accepted' || $activeQuotation->status === 'Approved') $activeQuoBadgeClass = 'bg-soft-success text-success';
                                                         elseif ($activeQuotation->status === 'Rejected') $activeQuoBadgeClass = 'bg-soft-danger text-danger';
                                                         elseif ($activeQuotation->status === 'Pending Approval') $activeQuoBadgeClass = 'bg-soft-warning text-warning';
                                                         elseif ($activeQuotation->status === 'Quotation Rework') $activeQuoBadgeClass = 'bg-soft-warning text-warning';
                                                     @endphp
                                                     <div class="fw-semibold"><span class="badge {{ $activeQuoBadgeClass }}">{{ $activeQuotation->status }}</span></div>
                                                 </div>
                                            </div>
                                        </div>

                                        <!-- Items Order Lines Table -->
                                        <div class="border-top pt-4">
                                            <h5 class="fw-bold text-dark mb-3 fs-14">Order Lines</h5>
                                            <div class="table-responsive">
                                                <table class="table table-bordered table-sm align-middle fs-13 text-dark">
                                                    <thead class="table-light fs-11 text-uppercase text-muted fw-semibold">
                                                        <tr>
                                                            <th class="ps-3" style="width: 50%;">Product Description</th>
                                                            <th class="text-center" style="width: 10%;">Quantity</th>
                                                            <th class="text-end" style="width: 15%;">Unit Price</th>
                                                            <th class="text-end" style="width: 10%;">Taxes</th>
                                                            <th class="text-end pe-3" style="width: 15%;">Amount</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach ($activeQuotation->items as $item)
                                                            <tr>
                                                                <td class="ps-3">
                                                                    <strong class="text-dark">{{ $item->item_name }}</strong>
                                                                    @if($item->description)
                                                                        <small class="text-muted d-block mt-0.5">{{ $item->description }}</small>
                                                                    @endif
                                                                </td>
                                                                <td class="text-center">{{ $item->quantity }}</td>
                                                                <td class="text-end">₹{{ number_format($item->unit_price, 2) }}</td>
                                                                <td class="text-end">{{ number_format($item->tax_rate, 2) }}%</td>
                                                                <td class="text-end pe-3 fw-bold">₹{{ number_format($item->amount, 2) }}</td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>

                                        <!-- Calculation Totals -->
                                        <div class="row mt-4 pt-3 border-top text-dark fs-13">
                                            <div class="col-md-8">
                                                <div class="pe-md-4">
                                                    @if($activeQuotation->terms_conditions)
                                                        <div class="mb-3">
                                                            <div class="fw-bold text-muted fs-11 text-uppercase mb-1">Terms & Conditions</div>
                                                            <div class="text-dark fs-12 p-2 border bg-light-50 rounded" style="white-space: pre-wrap; line-height: 1.5; font-family: 'Inter', sans-serif;">{{ $activeQuotation->terms_conditions }}</div>
                                                        </div>
                                                    @endif
                                                    @if($activeQuotation->notes)
                                                        <div class="mb-3">
                                                            <div class="fw-bold text-muted fs-11 text-uppercase mb-1">Notes</div>
                                                            <div class="text-dark fs-12 p-2 border bg-light-50 rounded" style="white-space: pre-wrap; line-height: 1.5; font-family: 'Inter', sans-serif;">{{ $activeQuotation->notes }}</div>
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="d-flex justify-content-between py-1 border-bottom">
                                                    <span class="text-muted fw-semibold">Untaxed Amount:</span>
                                                    <span class="fw-bold text-dark">₹{{ number_format($activeQuotation->subtotal, 2) }}</span>
                                                </div>
                                                <div class="d-flex justify-content-between py-1 border-bottom">
                                                    <span class="text-muted fw-semibold">Taxes:</span>
                                                    <span class="fw-bold text-dark">₹{{ number_format($activeQuotation->tax_amount, 2) }}</span>
                                                </div>
                                                @if($activeQuotation->discount > 0)
                                                    <div class="d-flex justify-content-between py-1 border-bottom">
                                                        <span class="text-muted fw-semibold">Discount:</span>
                                                        <span class="fw-bold text-danger">-₹{{ number_format($activeQuotation->discount, 2) }}</span>
                                                    </div>
                                                @endif
                                                <div class="d-flex justify-content-between py-2 fs-15 border-bottom bg-light-50 px-2 rounded mt-1.5">
                                                    <span class="text-dark fw-bold">Total:</span>
                                                    <span class="fw-extrabold text-primary fs-16">₹{{ number_format($activeQuotation->total_amount, 2) }}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Revision History Card inside Lead details -->
                                    @php
                                        $revisions = $activeQuotation->getRevisionHistory();
                                    @endphp
                                    @if($revisions->count() > 1)
                                        <div class="card border shadow-sm mt-3 bg-white d-print-none" id="sectionQuotationHistory" style="border-radius: 4px; border-color: #e2e8f0 !important;">
                                            <div class="card-body p-3 text-dark">
                                                <h6 class="fw-bold mb-3 pb-2 border-bottom text-uppercase fs-11" style="letter-spacing: 0.5px; font-family: 'Inter', sans-serif; font-size: 11px !important;">
                                                    <i class="feather-git-commit me-1.5 text-primary"></i>Quotation Revision History
                                                </h6>
                                                <div class="d-flex flex-wrap gap-2 align-items-center">
                                                    @foreach($revisions as $rev)
                                                        <div class="d-flex align-items-center gap-2 p-2 border rounded bg-white" style="min-width: 170px; border-color: {{ $rev->id === $activeQuotation->id ? '#3b82f6 !important' : '#e2e8f0' }} !important; transition: all 0.2s; position: relative; {{ $rev->id === $activeQuotation->id ? 'box-shadow: 0 0 0 1px rgba(59,130,246,0.1); background-color: #f0f9ff !important;' : '' }}">
                                                            @if($rev->id === $activeQuotation->id)
                                                                <span class="position-absolute top-0 end-0 translate-middle-y badge rounded-pill bg-primary fs-8 text-uppercase px-1" style="font-size: 8px !important; margin-right: 10px;">Viewing</span>
                                                            @endif
                                                            <div class="avatar-text avatar-sm bg-soft-secondary text-secondary rounded-circle fw-bold d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; font-size: 10px;">
                                                                R{{ $rev->revision_number }}
                                                            </div>
                                                            <div class="d-flex flex-column fs-11" style="font-family: 'Inter', sans-serif;">
                                                                <a href="{{ route('crm.leads.show', ['lead' => $lead->id, 'view_quotation' => 1, 'active_quotation_id' => $rev->id]) }}" class="fw-bold text-dark text-decoration-none">
                                                                    {{ $rev->quotation_number }}
                                                                </a>
                                                                <span class="text-muted mt-0.5" style="font-size: 9px;">₹{{ number_format($rev->total_amount, 2) }}</span>
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                @else
                                    <!-- NO QUOTATION EMPTY STATE -->
                                    <div class="card border shadow-sm p-5 text-center bg-white" style="border-radius: 4px; border-color: #e2e8f0 !important;">
                                        <div class="py-4">
                                            <div class="mb-3 text-muted">
                                                <i class="feather-file-text" style="font-size: 48px; color: #cbd5e1;"></i>
                                            </div>
                                            <h5 class="fw-bold text-dark mb-2">No Quotation Found</h5>
                                            
                                            @if($lead->status === 'Qualified')
                                                <p class="text-muted fs-12 mx-auto mb-4" style="max-width: 400px; font-family: 'Inter', sans-serif;">
                                                    This lead has been marked as <strong>Qualified</strong>. You can now generate a quotation draft to begin the order process.
                                                </p>
                                                <form action="{{ route('crm.leads.convertToQuotation', $lead->id) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-success fw-bold px-4 py-2 text-uppercase fs-11" style="background-color: #16a34a; border-color: #16a34a; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                                        <i class="feather-shuffle me-1.5 fs-11"></i> Convert to Quotation
                                                    </button>
                                                </form>
                                            @else
                                                <p class="text-muted fs-12 mx-auto mb-0" style="max-width: 400px; font-family: 'Inter', sans-serif;">
                                                    To generate a quotation for this lead, please set the <strong>Lead Status</strong> to <strong>Qualified</strong> first.
                                                </p>
                                            @endif
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif
                </div> <!-- Closes tab-content -->
            </div> <!-- Closes right-main-panel -->
        </div> <!-- Closes row -->
    </div> <!-- Closes card wrapper -->

    <!-- Log Note Modal -->
    <x-ui.modal id="modalLogNote" title="LOG DISCUSSION NOTE" :centered="true" :formAction="route('crm.leads.followups.store', $lead->id)" formMethod="POST" submitText="Log Note" closeText="Cancel">
        <input type="hidden" name="type" value="Call">
        <input type="hidden" name="status" value="Completed">
        <input type="hidden" name="followup_date" value="{{ date('Y-m-d H:i') }}">
        
        <div class="mb-3 text-start">
            <label class="form-label fs-11 fw-bold text-muted text-uppercase mb-1">Interaction Type</label>
            <select class="form-select form-select-sm fw-semibold text-dark" name="type_select" onchange="this.form.type.value = this.value">
                <option value="Call">Call Log</option>
                <option value="Email">Email sent/received</option>
                <option value="Meeting">Meeting description</option>
                <option value="Demo">System demo log</option>
            </select>
        </div>
        <div class="mb-3 text-start">
            <label class="form-label fs-11 fw-bold text-muted text-uppercase mb-1">Notes / Summary</label>
            <textarea name="notes" class="form-control form-control-sm text-dark" rows="4" required placeholder="Describe what was discussed..."></textarea>
        </div>
    </x-ui.modal>

    <!-- Schedule Activity Modal -->
    <x-ui.modal id="modalScheduleActivity" title="SCHEDULE NEXT ACTIVITY" :centered="true" :formAction="route('crm.leads.followups.store', $lead->id)" formMethod="POST" submitText="Schedule" closeText="Cancel">
        <input type="hidden" name="status" value="Pending">
        
        <div class="mb-3 text-start">
            <label class="form-label fs-11 fw-bold text-muted text-uppercase mb-1">Activity Type</label>
            <select class="form-select form-select-sm fw-semibold text-dark" name="type" required>
                <option value="Call">Scheduled Call</option>
                <option value="Email">Scheduled Email</option>
                <option value="Meeting">Scheduled Meeting</option>
                <option value="Demo">Scheduled Demo</option>
            </select>
        </div>
        <div class="mb-3 text-start">
            <label class="form-label fs-11 fw-bold text-muted text-uppercase mb-1">Due Date & Time</label>
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-light"><i class="feather-calendar fs-11 text-muted"></i></span>
                <input type="text" class="form-control form-control-sm text-dark" name="followup_date" id="inline_activity_datepicker" required autocomplete="off">
            </div>
        </div>
        <div class="mb-3 text-start">
            <label class="form-label fs-11 fw-bold text-muted text-uppercase mb-1">Description / Plan</label>
            <textarea name="notes" class="form-control form-control-sm text-dark" rows="4" placeholder="What needs to be discussed?"></textarea>
        </div>
    </x-ui.modal>
@endsection

@push('styles')
    <!-- Select2 Styles -->
    <link class="d-print-none" rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link class="d-print-none" rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
    <style>
        /* Zoho CRM Inspired Premium Styles */
        .zoho-header-banner {
            background-color: #ffffff;
            border-bottom: 1px solid #cbd5e1;
            font-family: 'Inter', sans-serif;
        }

        /* Open header dropdown on hover and style alignment offset */
        .zoho-header-banner .dropdown:hover .dropdown-menu {
            display: block;
            margin-top: 0;
        }

        .zoho-header-banner .dropdown-menu-end {
            right: 0 !important;
            left: auto !important;
        }

        .zoho-sidebar-col {
            background-color: #ffffff;
        }

        .zoho-sidebar-nav .nav-link {
            color: #475569 !important;
            padding: 8px 12px;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .zoho-sidebar-nav .nav-link:hover {
            background-color: #e2e8f0;
            color: #0f172a !important;
            text-decoration: none;
        }

        .zoho-sidebar-nav .nav-link.active {
            background-color: #cbd5e1;
            color: #000000 !important;
            font-weight: 600;
        }

        .zoho-nav-tabs {
            gap: 6px;
        }

        .zoho-nav-tabs .nav-link {
            border: 1px solid #cbd5e1;
            background-color: #ffffff;
            color: #475569;
            padding: 6px 16px;
            font-size: 12px;
            transition: all 0.2s ease;
        }

        .zoho-nav-tabs .nav-link:hover {
            background-color: #f8fafc;
            color: #0f172a;
        }

        .zoho-nav-tabs .nav-link.active {
            background-color: #eef2f6 !important;
            color: #0f172a !important;
            border-color: #94a3b8 !important;
        }

        .zoho-quick-info-box {
            border-color: #e2e8f0 !important;
        }

        .border-end-md {
            border-right: 1px solid #e2e8f0;
        }

        @media (max-width: 767.98px) {
            .border-end-md {
                border-right: none;
                border-bottom: 1px solid #e2e8f0;
                padding-bottom: 12px;
                margin-bottom: 12px;
            }
        }

        .zoho-section-title {
            letter-spacing: 0.3px;
        }

        .zoho-section-title::after {
            content: '';
            display: block;
            width: 40px;
            height: 2px;
            background-color: #1e40af;
            margin-top: 4px;
        }

        .zoho-field-row {
            display: flex;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px dashed #e2e8f0;
        }
        .zoho-field-label {
            width: 160px;
            color: #64748b;
            font-weight: 500;
            font-size: 13px;
            flex-shrink: 0;
            padding-right: 10px;
        }
        .zoho-field-value {
            color: #0f172a;
            font-weight: 600;
            font-size: 13px;
            word-break: break-word;
            flex-grow: 1;
        }

        /* Zoho CRM Timeline Styles */
        .zoho-timeline-container {
            position: relative;
            padding-left: 10px;
            margin-top: 10px;
        }
        .zoho-timeline-date-group {
            margin-bottom: 25px;
            position: relative;
        }
        .zoho-timeline-date-header {
            font-size: 11px;
            font-weight: 700;
            background-color: #f1f5f9;
            color: #475569;
            padding: 4px 10px;
            border-radius: 4px;
            display: inline-block;
            margin-bottom: 15px;
            font-family: 'Inter', sans-serif;
            border: 1px solid #cbd5e1;
        }
        .zoho-timeline-event {
            position: relative;
            padding-left: 32px;
            margin-bottom: 20px;
        }
        .zoho-timeline-line {
            position: absolute;
            left: 10px;
            top: 20px;
            bottom: -25px;
            width: 1px;
            background-color: #cbd5e1;
            z-index: 1;
        }
        .zoho-timeline-event:last-child .zoho-timeline-line {
            display: none;
        }
        .zoho-timeline-icon {
            position: absolute;
            left: 0;
            top: 0;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            background-color: #ffffff;
            border: 1px solid #cbd5e1;
            z-index: 2;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }
        .zoho-timeline-icon i {
            font-size: 10px;
            color: #64748b;
        }
        .zoho-timeline-time {
            font-size: 11px;
            color: #64748b;
            width: 80px;
            flex-shrink: 0;
            font-weight: 500;
            font-family: 'Inter', sans-serif;
        }
        .zoho-timeline-content {
            font-size: 13px;
            color: #0f172a;
            font-family: 'Inter', sans-serif;
        }
        
        .zoho-timeline-subtabs .nav-link {
            color: #64748b !important;
            border-bottom: 2px solid transparent !important;
            font-weight: 600;
            transition: all 0.2s ease;
            font-size: 12px;
        }
        .zoho-timeline-subtabs .nav-link.active {
            color: #1e40af !important;
            border-bottom: 2px solid #1e40af !important;
            font-weight: 700 !important;
        }

        .cursor-pointer {
            cursor: pointer;
        }

        .hover-scale {
            transition: transform 0.15s ease;
        }
        .hover-scale:hover {
            transform: scale(1.1);
        }

        .activity-feed-compact .p-2 {
            border: 1px solid #e2e8f0 !important;
            transition: border-color 0.15s ease;
        }
        .activity-feed-compact .p-2:hover {
            border-color: #cbd5e1 !important;
        }

        .odoo-chatter-timeline {
            position: relative;
        }

        /* Odoo-style Inputs */
        .odoo-form-group {
            display: flex;
            align-items: center;
        }
        .odoo-form-label {
            width: 140px;
            font-size: 13px;
            font-weight: 700;
            color: #495057;
            margin-bottom: 0;
        }
        .odoo-form-control {
            border: none;
            border-bottom: 1px solid #ced4da;
            border-radius: 0;
            padding: 4px 0;
            background-color: transparent;
            font-size: 13px;
            color: #212529;
            width: 100%;
        }
        .odoo-form-control:focus {
            border-color: #1e40af;
            outline: none;
            box-shadow: none;
        }
        .odoo-form-control[readonly] {
            border-bottom: none;
            background-color: transparent;
            font-weight: bold;
        }

        /* Odoo style Table */
        .odoo-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            font-size: 13px;
        }
        .odoo-table th {
            border-bottom: 2px solid #dee2e6;
            padding: 8px 4px;
            color: #6c757d;
            font-weight: 600;
            text-transform: capitalize;
        }
        .odoo-table td {
            padding: 6px 4px;
            border-bottom: 1px solid #e9ecef;
            vertical-align: middle;
        }
        .odoo-table-input {
            border: none;
            border-bottom: 1px solid transparent;
            background: transparent;
            border-radius: 0;
            padding: 4px 2px;
            width: 100%;
            font-size: 13px;
        }
        .odoo-table-input:focus {
            border-bottom-color: #1e40af;
            outline: none;
            box-shadow: none;
        }
        .odoo-table-select {
            border: none;
            background: transparent;
            padding: 4px 2px;
            width: 100%;
            font-size: 13px;
            cursor: pointer;
        }
        .odoo-table-select:focus {
            border-bottom: 1px solid #1e40af;
            outline: none;
        }
        
        .odoo-action-link {
            color: #00A09D;
            font-weight: 600;
            font-size: 12px;
            text-decoration: none;
            margin-right: 15px;
        }
        .odoo-action-link:hover {
            text-decoration: underline;
        }

        /* Borderless Select2 theme custom override */
        .select2-container--bootstrap-5 .select2-selection {
            border: none !important;
            border-bottom: 1px solid #ced4da !important;
            border-radius: 0 !important;
            background-color: transparent !important;
            padding-left: 2px !important;
            height: auto !important;
            min-height: 25px !important;
        }
        .select2-container--bootstrap-5 .select2-selection:focus,
        .select2-container--bootstrap-5.select2-container--focus .select2-selection {
            border-bottom-color: #1e40af !important;
            box-shadow: none !important;
        }
        .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
            padding-left: 0 !important;
            font-size: 13px !important;
            color: #212529 !important;
        }

        /* Print styles override */
        @media print {
            .zoho-lead-card-container {
                height: auto !important;
                overflow: visible !important;
            }
            .zoho-main-col {
                height: auto !important;
                overflow: visible !important;
            }
            .nxl-sidebar,
            .nxl-navigation,
            .nxl-header,
            .page-header,
            .nxl-footer,
            .d-print-none,
            header,
            footer,
            nav,
            aside,
            .col-lg-4,
            .odoo-chatter-timeline,
            .zoho-sidebar-col,
            .zoho-header-banner,
            .zoho-nav-tabs,
            #zohoLeadTabs,
            #overview-pane,
            #timeline-pane,
            .zoho-quick-info-box,
            .sticky-top,
            .modal,
            .modal-backdrop {
                display: none !important;
            }

            #quotation-pane {
                display: block !important;
                opacity: 1 !important;
                visibility: visible !important;
                padding: 0 !important;
            }

            .zoho-main-col, #zohoMainScrollable {
                height: auto !important;
                overflow: visible !important;
                background-color: #ffffff !important;
                padding: 0 !important;
                margin: 0 !important;
                width: 100% !important;
            }

            body {
                background: #ffffff !important;
                color: #000000 !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            .nxl-container,
            .nxl-content,
            .main-content,
            .bg-white {
                background: #ffffff !important;
                margin: 0 !important;
                padding: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
                box-shadow: none !important;
                border: none !important;
                position: static !important;
            }

            #quotation-print-area {
                border: none !important;
                box-shadow: none !important;
                padding: 8mm 12mm !important;
                margin: 0 !important;
                background: #ffffff !important;
                width: 100% !important;
                position: static !important;
            }

            .table-responsive {
                overflow: visible !important;
            }
            
            table {
                width: 100% !important;
                border-collapse: collapse !important;
            }

            th, td {
                padding: 8px !important;
            }
        }
    </style>
@endpush

@push('scripts')
    <!-- Select2 & Quotation Rows logic -->
    <script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/select2-active.min.js') }}"></script>
<script>
        function printQuotationIframe() {
            var iframe = document.getElementById('quotationPrintIframe');
            if (iframe) {
                iframe.contentWindow.focus();
                iframe.contentWindow.print();
            }
        }

        $(function () {
            // Tab state persistence logic
            var activeTabKey = 'lead_active_tab_' + {{ $lead->id }};
            var activeSubTabKey = 'lead_active_subtab_' + {{ $lead->id }};
            
            // Check query parameters first
            var urlParams = new URLSearchParams(window.location.search);
            var hasParam = urlParams.has('create_quotation') || 
                           urlParams.has('edit_quotation') || 
                           urlParams.has('view_quotation') || 
                           urlParams.has('edit_lead');

            if (hasParam) {
                // If loaded with parameters, set active tab in localStorage
                if (urlParams.has('create_quotation') || urlParams.has('edit_quotation') || urlParams.has('view_quotation')) {
                    localStorage.setItem(activeTabKey, 'quotation-tab');
                } else if (urlParams.has('edit_lead')) {
                    localStorage.setItem(activeTabKey, 'overview-tab');
                }
                
                // Clean up query parameters from the address bar to prevent stuck refresh state
                if (window.history.replaceState) {
                    var cleanUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
                    window.history.replaceState({path: cleanUrl}, '', cleanUrl);
                }
            } else {
                // If clean load, restore tab from localStorage
                var savedTabId = localStorage.getItem(activeTabKey);
                if (savedTabId && $('#' + savedTabId).length) {
                    setTimeout(function() {
                        $('#' + savedTabId).tab('show');
                        
                        // If it's timeline tab, also restore the subtab
                        if (savedTabId === 'timeline-tab') {
                            var savedSubTabId = localStorage.getItem(activeSubTabKey);
                            if (savedSubTabId && $('#' + savedSubTabId).length) {
                                $('#' + savedSubTabId).tab('show');
                            }
                        }
                    }, 50);
                }
            }

            var scrollTargetOnTabShown = null;

            function scrollToElement(targetEl) {
                var scrollContainer = $('#zohoMainScrollable');
                var relativeTop = targetEl.offset().top - scrollContainer.offset().top;
                var scrollTopPosition = scrollContainer.scrollTop() + relativeTop - 50; // Offset for sticky tabs

                scrollContainer.animate({
                    scrollTop: scrollTopPosition
                }, 400);
            }

            // Scroll behavior for related lists links
            $('#zohoSidebarLinks a').on('click', function(e) {
                var targetId = $(this).attr('href');
                if (targetId.startsWith('#')) {
                    var targetEl = $(targetId);
                    if (targetEl.length) {
                        e.preventDefault();
                        
                        // Remove active class from all links and add to clicked one
                        $('#zohoSidebarLinks a').removeClass('active');
                        $(this).addClass('active');

                        var needTabSwitch = false;
                        if (targetId === '#sectionLeadInfo' || targetId === '#sectionAddressInfo' || targetId === '#sectionRequirements' || targetId === '#sectionNotes') {
                            if (!$('#overview-tab').hasClass('active')) {
                                scrollTargetOnTabShown = targetEl;
                                $('#overview-tab').tab('show');
                                needTabSwitch = true;
                            }
                        } else if (targetId === '#subtab-history') {
                            if (!$('#timeline-tab').hasClass('active')) {
                                scrollTargetOnTabShown = targetEl;
                                $('#timeline-tab').tab('show');
                                $('#subtab-history-tab').tab('show');
                                needTabSwitch = true;
                            } else {
                                $('#subtab-history-tab').tab('show');
                            }
                        } else if (targetId === '#subtab-interactions') {
                            if (!$('#timeline-tab').hasClass('active')) {
                                scrollTargetOnTabShown = targetEl;
                                $('#timeline-tab').tab('show');
                                $('#subtab-interactions-tab').tab('show');
                                needTabSwitch = true;
                            } else {
                                $('#subtab-interactions-tab').tab('show');
                            }
                        } else if (targetId === '#sectionQuotationHistory') {
                            if (!$('#quotation-tab').hasClass('active')) {
                                scrollTargetOnTabShown = targetEl;
                                $('#quotation-tab').tab('show');
                                needTabSwitch = true;
                            }
                        }

                        if (!needTabSwitch) {
                            // If tab is already active, scroll immediately
                            scrollToElement(targetEl);
                        }
                    }
                }
            });

            // Handle scroll after tab transition finishes, or reset to top if manual switch
            $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function (e) {
                // Save active tab state in localStorage on change
                if (e.target.id) {
                    if (e.target.id === 'overview-tab' || e.target.id === 'timeline-tab' || e.target.id === 'quotation-tab') {
                        localStorage.setItem(activeTabKey, e.target.id);
                    } else if (e.target.id === 'subtab-history-tab' || e.target.id === 'subtab-interactions-tab') {
                        localStorage.setItem(activeSubTabKey, e.target.id);
                    }
                }

                if (scrollTargetOnTabShown) {
                    scrollToElement(scrollTargetOnTabShown);
                    scrollTargetOnTabShown = null;
                } else {
                    $('#zohoMainScrollable').scrollTop(0);
                }
            });

            // Auto submit status forms when changed in Select2 status selector
            $('.status-select').on('change', function() {
                $(this).closest('form').submit();
            });

            // Initialize inline activity datepicker inside modal
            $('#inline_activity_datepicker').daterangepicker({
                singleDatePicker: true,
                timePicker: true,
                timePickerIncrement: 5,
                parentEl: '#modalScheduleActivity',
                drops: 'auto',
                locale: {
                    format: 'YYYY-MM-DD hh:mm A'
                }
            });

            // Initialize lead call date picker
            if ($('#lead_call_date_picker').length) {
                $('#lead_call_date_picker').daterangepicker({
                    singleDatePicker: true,
                    timePicker: true,
                    timePickerIncrement: 1,
                    locale: {
                        format: 'YYYY-MM-DD hh:mm A'
                    }
                });
            }

            // Initialize searchable select2 dropdowns
            $('.odoo-select2').select2({
                theme: "bootstrap-5",
                width: "100%"
            });

            $('#quotationStatusSelect').on('change', function() {
                $(this).closest('form').submit();
            });

            // ==================== DYNAMIC ITEMS TABLE FOR INLINE FORM ====================
            let rowIndex = 0;

            // Products list from DB — used to build dynamic dropdown options
            @php
                $mappedProducts = $products->map(function($p) {
                    return [
                        'id' => $p->id,
                        'name' => $p->name,
                        'sku' => $p->sku,
                        'unit_cost' => $p->unit_cost
                    ];
                });
            @endphp
            const crmProductsList = @json($mappedProducts);

            function buildProductOptions(selectedId = '') {
                let opts = '<option value="">Select Product...</option>';
                opts += '<option value="__ADD_NEW__" class="fw-bold text-primary" data-master="product">+ Add New Product</option>';
                crmProductsList.forEach(function(p) {
                    const sel = (p.id == selectedId) ? ' selected' : '';
                    opts += `<option value="${p.id}" data-unit-cost="${p.unit_cost ?? 0}"${sel}>${p.name} (${p.sku})</option>`;
                });
                return opts;
            }

            function getRowHtml(index, selectedId = '') {
                return `
                    <tr class="item-row" data-row-id="${index}">
                        <td class="ps-3">
                            <select name="items[${index}][product_id]" class="form-select item-name-input erp-premium-select" data-master="product" required>
                                ${buildProductOptions(selectedId)}
                            </select>
                            <div class="description-container mt-2" id="desc-container-${index}" style="display: none;">
                                <textarea name="items[${index}][description]" class="form-control odoo-table-input" placeholder="Scope/details..."></textarea>
                            </div>
                            <a href="javascript:void(0)" class="toggle-desc-btn text-primary fs-11 mt-1 d-inline-block" data-row-id="${index}">
                                <i class="feather-plus me-1"></i>Add Description
                            </a>
                        </td>
                        <td>
                            <input type="number" name="items[${index}][quantity]" class="odoo-table-input text-end qty-input" value="1" min="1" required style="max-width: 80px; margin-left: auto; text-align: right;">
                        </td>
                        <td>
                            <input type="number" name="items[${index}][unit_price]" class="odoo-table-input text-end price-input" value="0.00" min="0" step="0.01" required style="max-width: 120px; margin-left: auto; text-align: right;">
                        </td>
                        <td>
                            <input type="number" name="items[${index}][tax_rate]" class="odoo-table-input text-end tax-input" value="18.00" min="0" max="100" step="0.01" style="max-width: 80px; margin-left: auto; text-align: right;">
                        </td>
                        <td class="text-end fw-bold text-dark amount-display pe-3">
                            ₹0.00
                        </td>
                        <td class="text-center">
                            <button type="button" class="btn btn-icon btn-sm btn-soft-danger remove-row-btn mt-1">
                                <i class="feather-trash-2"></i>
                            </button>
                        </td>
                    </tr>
                `;
            }

            // Check if activeQuotation items exist (edit state) or prefill from conversion
            const hasCreateQ = @json(request()->has('create_quotation'));
            const hasEditQ = @json(request()->has('edit_quotation'));
            const existingItems = hasEditQ ? @json(isset($activeQuotation) ? $activeQuotation->items : []) : [];
            const prefillProductId = @json($lead->product_id);
            const prefillAmount = @json($lead->expected_amount);

            if (hasCreateQ || hasEditQ) {
                if (existingItems.length > 0) {
                    existingItems.forEach(function(item) {
                        addRow(item);
                    });
                } else if (hasCreateQ && (prefillProductId || prefillAmount)) {
                    addRow({
                        product_id: prefillProductId || '',
                        description: '',
                        quantity: 1,
                        unit_price: parseFloat(prefillAmount) || 0.00,
                        tax_rate: 18.00
                    });
                } else {
                    addRow();
                }
            }

            // Add row action
            $('#addItemRow').on('click', function() {
                addRow();
            });

            // Toggle Description input visibility
            $(document).on('click', '.toggle-desc-btn', function(e) {
                e.preventDefault();
                const idx = $(this).data('row-id');
                const container = $('#desc-container-' + idx);
                if (container.is(':visible')) {
                    container.slideUp(120);
                    container.find('textarea').val('');
                    $(this).html('<i class="feather-plus me-1"></i>Add Description');
                } else {
                    container.slideDown(120);
                    $(this).html('<i class="feather-minus me-1"></i>Remove Description');
                }
            });

            // Remove row action
            $(document).on('click', '.remove-row-btn', function() {
                const rowsCount = $('.item-row').length;
                if (rowsCount > 1) {
                    $(this).closest('tr').remove();
                    calculateTotals();
                } else {
                    alert('You must include at least one item line in a quotation.');
                }
            });

            // Input listener for calculations
            $(document).on('input', '.qty-input, .price-input, .tax-input, #discountInput', function() {
                calculateTotals();
            });

            function addRow(item = null) {
                const selectedId = item ? (item.product_id || '') : '';
                const newRow = $(getRowHtml(rowIndex, selectedId));
                $('#itemsTable tbody').append(newRow);

                // Initialize select2 on the newly added select element
                newRow.find('.item-name-input').select2({
                    theme: "bootstrap-5",
                    width: "100%"
                });

                // Prefill details
                let isPrefilling = false;
                if (item) {
                    isPrefilling = true;
                    newRow.find('.item-name-input').val(item.product_id).trigger('change');
                    newRow.find('textarea').val(item.description || '');
                    if (item.description) {
                        $('#desc-container-' + rowIndex).show();
                        newRow.find('.toggle-desc-btn').html('<i class="feather-minus me-1"></i>Remove Description');
                    }
                    newRow.find('.qty-input').val(item.quantity);
                    newRow.find('.price-input').val(item.unit_price);
                    newRow.find('.tax-input').val(item.tax_rate);
                    isPrefilling = false;
                }

                // Auto-fill unit price from product's unit_cost when product is selected by user
                newRow.find('.item-name-input').on('change', function() {
                    if (isPrefilling) return;
                    const selectedOption = $(this).find('option:selected');
                    const unitCost = parseFloat(selectedOption.attr('data-unit-cost')) || 0;
                    if (unitCost > 0) {
                        $(this).closest('tr').find('.price-input').val(unitCost.toFixed(2));
                        calculateTotals();
                    }
                });

                rowIndex++;
                calculateTotals();
            }

            function calculateTotals() {
                let subtotal = 0;
                let taxTotal = 0;

                $('.item-row').each(function() {
                    const qty = parseInt($(this).find('.qty-input').val()) || 0;
                    const price = parseFloat($(this).find('.price-input').val()) || 0;
                    const taxRate = parseFloat($(this).find('.tax-input').val()) || 0;

                    const amount = qty * price;
                    const tax = amount * (taxRate / 100);

                    subtotal += amount;
                    taxTotal += tax;

                    $(this).find('.amount-display').text('₹' + amount.toFixed(2));
                });

                const discount = parseFloat($('#discountInput').val()) || 0;
                const grandTotal = subtotal + taxTotal - discount;

                $('#calcSubtotal').text('₹' + subtotal.toFixed(2));
                $('#calcTax').text('₹' + taxTotal.toFixed(2));
                $('#calcTotal').text('₹' + Math.max(0, grandTotal).toFixed(2));
            }
        });
    </script>

    {{-- Product quick-create modal --}}
    <x-ui.master-modals :masters="['product']" />
@endpush
