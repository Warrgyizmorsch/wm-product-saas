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

    <!-- Odoo CRM Unified Workspace Canvas -->
    <div class="card border-0 shadow-sm bg-white" style="overflow: visible;">
        <!-- Hidden form for stage status updates via clickable chevrons -->
        <form id="statusChangeForm" action="{{ route('crm.leads.updateStatus', $lead->id) }}" method="POST" style="display: none;">
            @csrf
            @method('PATCH')
            <input type="hidden" name="status" id="statusChangeInput">
        </form>

        <div class="card-body p-4 bg-white">
            <!-- Header Banner Section -->
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 pb-3 mb-4 border-bottom">
                <div class="d-flex align-items-center">
                    <!-- Avatar Circle -->
                    <div class="avatar-text avatar-lg bg-soft-primary text-primary fs-4 fw-bold me-3 shadow-sm" style="width: 54px; height: 54px; line-height: 54px;">
                        {{ strtoupper(substr($lead->company_name, 0, 1)) }}
                    </div>
                    <div>
                        <div class="d-flex align-items-center flex-wrap gap-2">
                            <h4 class="fw-bold text-dark mb-0 me-2 fs-18">{{ $lead->company_name }}</h4>
                            
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
                        <p class="text-muted mb-0 fs-12 mt-1">
                            <i class="feather-user me-1 text-muted"></i> {{ $lead->owner?->name ?: 'Unassigned' }}
                        </p>
                    </div>
                </div>
                
                <!-- Action Buttons on the Right Side of the Banner -->
                <div class="d-flex gap-2 flex-wrap align-items-center d-print-none">
                    @if ($lead->is_customer || $lead->status === 'Converted')
                        <button class="btn btn-success btn-xs fw-bold text-uppercase fs-10 py-1.5 px-2.5" disabled>
                            <i class="feather-check-circle me-1"></i>Customer Created
                        </button>
                    @elseif ($activeQuotation)
                        <a href="{{ route('crm.quotations.show', $activeQuotation->id) }}" class="btn btn-xs fw-bold text-uppercase fs-10 py-1.5 px-2.5 text-white" style="background-color: var(--bs-primary); border-color: var(--bs-primary);">
                            <i class="feather-file-text me-1"></i>View Quotation
                        </a>
                        <button class="btn btn-xs fw-bold text-uppercase fs-10 py-1.5 px-2.5" disabled style="background-color: #F7E4C3; color: #8A6D3B; border: 1px solid #F7E4C3;">
                            <i class="feather-clock me-1"></i>Quotation Pending
                        </button>
                    @elseif (($lead->status ?: 'New') === 'Qualified' && !$lead->is_customer)
                        <form action="{{ route('crm.leads.convertToQuotation', $lead->id) }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-primary btn-xs fw-bold text-uppercase fs-10 py-1.5 px-2.5" style="background-color: var(--bs-primary); border-color: var(--bs-primary);">
                                <i class="feather-file-plus me-1"></i>Convert to Quotation
                            </button>
                        </form>
                    @endif
                    
                    @if ($lead->status !== 'Converted' && $lead->status !== 'Lost' && !$lead->is_customer)
                        <form action="{{ route('crm.leads.updateStatus', $lead->id) }}" method="POST" class="d-inline">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="status" value="Lost">
                            <button type="submit" class="btn btn-outline-danger btn-xs fw-bold text-uppercase fs-10 py-1.5 px-2.5">Mark Lost</button>
                        </form>
                    @endif
                    
                    <a href="{{ route('crm.leads.show', ['lead' => $lead->id, 'edit_lead' => 1]) }}" class="btn btn-link text-dark btn-xs text-decoration-none fw-bold text-uppercase fs-10 py-1.5 px-2.5">
                        <i class="feather-edit-3 me-1"></i>Edit Lead
                    </a>
                    <a href="{{ route('crm.leads.index') }}" class="btn btn-link text-dark btn-xs text-decoration-none fw-bold text-uppercase fs-10 py-1.5 px-2.5">
                        <i class="feather-arrow-left me-1"></i>Back
                    </a>
                </div>
            </div>

            <!-- Tabs & Status Row -->
            <div class="d-flex justify-content-between align-items-center border-bottom mb-4 flex-wrap gap-3">
                <!-- Zoho Style Navigation Tab Menu -->
                <ul class="nav nav-tabs border-bottom-0 mb-0" id="leadTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-bold px-3 py-2 fs-13 {{ !request()->has('create_quotation') && !request()->has('edit_quotation') && !request()->has('view_quotation') ? 'active' : '' }}" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview-pane" type="button" role="tab" aria-controls="overview-pane" aria-selected="true">
                            Overview
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-bold px-3 py-2 fs-13" id="timeline-tab" data-bs-toggle="tab" data-bs-target="#timeline-pane" type="button" role="tab" aria-controls="timeline-pane" aria-selected="false">
                            Activities & History
                        </button>
                    </li>
                    @if ($activeQuotation || request()->has('create_quotation'))
                        <li class="nav-item" role="presentation">
                            <button class="nav-link fw-bold px-3 py-2 fs-13 {{ request()->has('create_quotation') || request()->has('edit_quotation') || request()->has('view_quotation') ? 'active' : '' }}" id="quotation-tab" data-bs-toggle="tab" data-bs-target="#quotation-pane" type="button" role="tab" aria-controls="quotation-pane" aria-selected="false">
                                Quotation
                            </button>
                        </li>
                    @endif
                </ul>

                <!-- Right Odoo Chevron Status Steps -->
                <div class="d-flex align-items-center odoo-statusbar shadow-sm mb-1 d-print-none">
                    @foreach(['New', 'Follow-up Scheduled', 'Contacted', 'Qualified', 'Converted'] as $statusOption)
                        @php
                            $isActive = ($lead->status ?: 'New') === $statusOption;
                            $statusOrder = ['New', 'Follow-up Scheduled', 'Contacted', 'Qualified', 'Converted', 'Lost'];
                            $currentIdx = array_search($lead->status ?: 'New', $statusOrder);
                            $optIdx = array_search($statusOption, $statusOrder);
                            $isCompleted = $optIdx < $currentIdx;
                        @endphp
                        <div class="odoo-status-step {{ $isActive ? 'active' : '' }} {{ $isCompleted ? 'completed' : '' }}" 
                             data-status="{{ $statusOption }}" 
                             style="cursor: pointer;"
                             title="Click to transition to {{ $statusOption }}">
                            {{ $statusOption }}
                        </div>
                    @endforeach
                    @if($lead->status === 'Lost')
                        <div class="odoo-status-step active bg-danger text-white border-danger" data-status="Lost" style="cursor: pointer;">
                            Lost
                        </div>
                    @endif
                </div>
            </div>

        <!-- Tab Contents -->
        <div class="tab-content" id="leadTabsContent">
            
            <!-- TAB 1: OVERVIEW PANE -->
            <div class="tab-pane fade show {{ !request()->has('create_quotation') && !request()->has('edit_quotation') && !request()->has('view_quotation') ? 'active' : '' }}" id="overview-pane" role="tabpanel" aria-labelledby="overview-tab">
                <div class="py-2">
                            @if (request()->has('edit_lead'))
                                <!-- ==================== STATE 4: EDIT LEAD FORM ==================== -->
                                <form action="{{ route('crm.leads.update', $lead->id) }}" method="POST">
                                    @csrf
                                    @method('PUT')
                                    
                                    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 flex-wrap gap-2">
                                        <h4 class="fw-bold text-dark mb-0">Edit Lead details</h4>
                                        <div class="d-flex gap-2">
                                            <a href="{{ route('crm.leads.show', $lead->id) }}" class="btn btn-sm btn-light border">Cancel</a>
                                            <button type="submit" class="btn btn-sm btn-primary py-1.5 px-3 fw-bold" style="background-color: #714B67; border-color: #714B67;">Save Changes</button>
                                        </div>
                                    </div>

                                    <div class="row g-4 fs-13 text-dark">
                                        <div class="col-md-6 border-end">
                                            <h6 class="fw-bold text-primary mb-3">Company & Contact Info</h6>
                                            
                                            <div class="odoo-form-group">
                                                <label class="odoo-form-label">Company Name <span class="text-danger">*</span></label>
                                                <div class="flex-grow-1">
                                                    <input type="text" name="company_name" value="{{ old('company_name', $lead->company_name) }}" class="odoo-form-control" required placeholder="e.g. Acme Corporation">
                                                </div>
                                            </div>

                                            <div class="odoo-form-group">
                                                <label class="odoo-form-label">Contact Person</label>
                                                <div class="flex-grow-1">
                                                    <input type="text" name="contact_person" value="{{ old('contact_person', $lead->contact_person) }}" class="odoo-form-control" placeholder="Contact Representative">
                                                </div>
                                            </div>

                                            <div class="odoo-form-group">
                                                <label class="odoo-form-label">Email Address</label>
                                                <div class="flex-grow-1">
                                                    <input type="email" name="email" value="{{ old('email', $lead->email) }}" class="odoo-form-control" placeholder="email@address.com">
                                                </div>
                                            </div>

                                            <div class="odoo-form-group">
                                                <label class="odoo-form-label">Phone Number</label>
                                                <div class="flex-grow-1">
                                                    <input type="text" name="phone" value="{{ old('phone', $lead->phone) }}" class="odoo-form-control" placeholder="+00 000 000 0000">
                                                </div>
                                            </div>

                                            <div class="odoo-form-group">
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

                                            <div class="odoo-form-group">
                                                <label class="odoo-form-label">Street Address</label>
                                                <div class="flex-grow-1">
                                                    <textarea name="address" rows="2" class="odoo-form-control" placeholder="Street address...">{{ old('address', $lead->address) }}</textarea>
                                                </div>
                                            </div>

                                            <div class="odoo-form-group">
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

                                            <div class="odoo-form-group">
                                                <label class="odoo-form-label">Product Interest</label>
                                                <div class="flex-grow-1">
                                                    <input type="text" name="product" value="{{ old('product', $lead->product) }}" class="odoo-form-control" placeholder="Interested Product">
                                                </div>
                                            </div>

                                            <div class="odoo-form-group">
                                                <label class="odoo-form-label">Expected Revenue (₹)</label>
                                                <div class="flex-grow-1">
                                                    <input type="number" name="expected_amount" value="{{ old('expected_amount', $lead->expected_amount) }}" min="0" step="0.01" class="odoo-form-control" placeholder="Expected Revenue (₹)">
                                                </div>
                                            </div>

                                            <div class="odoo-form-group">
                                                <label class="odoo-form-label">Expected Sale Date</label>
                                                <div class="flex-grow-1">
                                                    <input type="date" name="expected_sale_date" value="{{ old('expected_sale_date', $lead->expected_sale_date ? $lead->expected_sale_date->format('Y-m-d') : '') }}" class="odoo-form-control">
                                                </div>
                                            </div>

                                            <h6 class="fw-bold text-primary mb-3 mt-4">Segmentation & Sources</h6>

                                            <div class="odoo-form-group">
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

                                            <div class="odoo-form-group">
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

                                            <div class="odoo-form-group">
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

                                            <div class="odoo-form-group">
                                                <label class="odoo-form-label">Industry Type</label>
                                                <div class="flex-grow-1">
                                                    <input type="text" name="industry_type" value="{{ old('industry_type', $lead->industry_type) }}" class="odoo-form-control" placeholder="Industry/Vertical">
                                                </div>
                                            </div>

                                            <div class="odoo-form-group">
                                                <label class="odoo-form-label">Initial Call Date</label>
                                                <div class="flex-grow-1">
                                                    <div class="input-group input-group-sm">
                                                        <span class="input-group-text bg-light border-0 border-bottom rounded-0"><i class="feather-calendar fs-11 text-muted"></i></span>
                                                        <input type="text" class="form-control odoo-form-control" name="call_date" id="call_date_picker" value="{{ old('call_date', $lead->call_date ? $lead->call_date->format('Y-m-d h:i A') : '') }}" placeholder="Call Schedule">
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="mt-4">
                                                <label class="form-label fw-bold text-muted text-uppercase mb-1" style="font-size: 10px;">Requirement Description</label>
                                                <textarea name="requirement" rows="4" class="form-control" style="border: 1px solid #ced4da; padding: 6px; border-radius: 4px;" placeholder="Details about initial inquiry, product scope, business size, etc...">{{ old('requirement', $lead->requirement) }}</textarea>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            @else
                                <!-- ==================== STATE 5: ORIGINAL LEAD DETAILS VIEW (DEFAULT) ==================== -->
                                <div class="mb-4">
                                    <div class="row g-4 fs-13 text-dark">
                                        <div class="col-md-6 border-end">
                                            <table class="table table-sm table-borderless align-middle mb-0">
                                                <tr>
                                                    <td class="text-muted py-2" style="width: 35%;">Lead Owner</td>
                                                    <td class="py-2">
                                                        <form action="{{ route('crm.leads.updateOwner', $lead->id) }}" method="POST" class="d-inline">
                                                            @csrf
                                                            @method('PATCH')
                                                            <select name="lead_owner_id" class="form-select form-select-sm fw-semibold d-inline-block w-auto py-0.5 px-2 border-0 bg-transparent text-primary" onchange="this.form.submit()">
                                                                <option value="">Unassigned</option>
                                                                @foreach($users as $user)
                                                                    <option value="{{ $user->id }}" @selected($lead->lead_owner_id == $user->id)>{{ $user->name }}</option>
                                                                @endforeach
                                                            </select>
                                                        </form>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td class="text-muted py-2">Lead Status</td>
                                                    <td class="py-2">
                                                        @php
                                                            $statusClass = 'bg-soft-primary text-primary';
                                                            if($lead->status === 'Follow-up Scheduled') $statusClass = 'bg-soft-warning text-warning';
                                                            elseif($lead->status === 'Contacted') $statusClass = 'bg-soft-info text-info';
                                                            elseif($lead->status === 'Qualified') $statusClass = 'bg-soft-teal text-teal';
                                                            elseif($lead->status === 'Converted') $statusClass = 'bg-soft-success text-success';
                                                            elseif($lead->status === 'Lost') $statusClass = 'bg-soft-danger text-danger';
                                                        @endphp
                                                        <span class="badge {{ $statusClass }} px-2.5 py-1.5 fs-11 fw-semibold">{{ $lead->status ?: 'New' }}</span>
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td class="text-muted py-2">Contact Representative</td>
                                                    <td class="fw-semibold text-dark py-2">{{ $lead->contact_person ?: '—' }}</td>
                                                </tr>
                                                <tr>
                                                    <td class="text-muted py-2">Email</td>
                                                    <td class="py-2">
                                                        @if($lead->email)
                                                            <a href="mailto:{{ $lead->email }}" class="fw-semibold text-primary"><i class="feather-mail me-1"></i>{{ $lead->email }}</a>
                                                        @else
                                                            <span class="text-muted">No Email</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                                <tr>
                                                    <td class="text-muted py-2">Phone</td>
                                                    <td class="fw-semibold text-dark py-2">{{ $lead->phone ?: '—' }}</td>
                                                </tr>
                                            </table>
                                        </div>
                                        <div class="col-md-6">
                                            <table class="table table-sm table-borderless align-middle mb-0">
                                                <tr>
                                                    <td class="text-muted py-2" style="width: 35%;">Expected Revenue</td>
                                                    <td class="fw-bold text-dark py-2">₹{{ $lead->expected_amount ? number_format($lead->expected_amount, 2) : '0.00' }}</td>
                                                </tr>
                                                <tr>
                                                    <td class="text-muted py-2">Product Interested</td>
                                                    <td class="fw-bold text-dark py-2">{{ $lead->product ?: '—' }}</td>
                                                </tr>
                                                <tr>
                                                    <td class="text-muted py-2">Expected Sale Date</td>
                                                    <td class="fw-semibold text-dark py-2">{{ $lead->expected_sale_date ? $lead->expected_sale_date->format('d/m/Y') : '—' }}</td>
                                                </tr>
                                                <tr>
                                                    <td class="text-muted py-2">Lead Source</td>
                                                    <td class="py-2"><span class="badge bg-soft-secondary text-secondary px-2.5 py-1">{{ $lead->source ?: '—' }}</span></td>
                                                </tr>
                                                <tr>
                                                    <td class="text-muted py-2">Address</td>
                                                    <td class="py-2 text-dark fs-12">{{ $lead->address ?: 'No street address specified' }}<br>{{ $lead->city }} {{ $lead->state }} {{ $lead->country }}</td>
                                                </tr>
                                            </table>
                                        </div>
                                    </div>

                                    <!-- Requirement Section -->
                                    <div class="border-top pt-3 mt-4">
                                        <h6 class="fs-11 text-uppercase fw-bold text-muted mb-2"><i class="feather-file-text me-1 text-primary"></i>Requirements Details</h6>
                                        @if ($lead->requirement)
                                            <div class="text-dark fs-13 bg-light-50 p-3 rounded" style="white-space: pre-wrap; line-height: 1.6;">{{ $lead->requirement }}</div>
                                        @else
                                            <p class="text-muted fs-13 italic mb-0">No requirements details specified for this lead.</p>
                                        @endif
                                    </div>
                                </div>
                            @endif
                </div>
            </div> <!-- End TAB 1: OVERVIEW PANE -->

            @if ($activeQuotation || request()->has('create_quotation'))
            <!-- TAB 3: QUOTATION PANE -->
            <div class="tab-pane fade show {{ request()->has('create_quotation') || request()->has('edit_quotation') || request()->has('view_quotation') ? 'active' : '' }}" id="quotation-pane" role="tabpanel" aria-labelledby="quotation-tab">
                <div class="py-2">
                            @if (request()->has('create_quotation'))
                                <!-- ==================== STATE 1: CREATE QUOTATION FORM ==================== -->
                                <form action="{{ route('crm.quotations.store') }}" method="POST" id="quotationForm">
                                    @csrf
                                    <input type="hidden" name="lead_id" value="{{ $lead->id }}">
                                    
                                    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">
                                        <h3 class="fw-bold text-dark mb-0">New Quotation</h3>
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
                                                <option value="Quotation Sent" @selected(old('status') === 'Quotation Sent')>Quotation Sent</option>
                                                <option value="Accepted" @selected(old('status') === 'Accepted')>Accepted</option>
                                                <option value="Rejected" @selected(old('status') === 'Rejected')>Rejected</option>
                                                <option value="Quotation Rework" @selected(old('status') === 'Quotation Rework')>Quotation Rework</option>
                                            </x-ui.odoo-form-ui>
                                        </div>
                                    </div>

                                    <!-- Odoo Style Items Lines Table -->
                                    <div class="border-top pt-4">
                                        <h5 class="fw-bold text-dark mb-3">Order Lines</h5>
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

                                    <!-- Calculation Totals aligned Right Odoo Sheet Footer -->
                                    <div class="row mt-4 pt-3 border-top justify-content-end text-dark fs-13">
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
                                        <a href="{{ route('crm.leads.show', $lead->id) }}" class="btn btn-md btn-light border py-2 px-4 shadow-sm">Discard</a>
                                        <button type="submit" class="btn btn-md btn-primary py-2 px-5 fw-bold shadow-sm" style="background-color: #714B67; border-color: #714B67;">Save Quotation</button>
                                    </div>
                                </form>

                            @elseif (request()->has('edit_quotation') && $activeQuotation)
                                <!-- ==================== STATE 2: EDIT QUOTATION FORM ==================== -->
                                <form action="{{ route('crm.quotations.update', $activeQuotation->id) }}" method="POST" id="quotationForm">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="lead_id" value="{{ $lead->id }}">

                                    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">
                                        <h3 class="fw-bold text-dark mb-0">Edit Quotation: {{ $activeQuotation->quotation_number }}</h3>
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
                                                <option value="Draft" @selected(old('status', $activeQuotation->status) === 'Draft')>Draft</option>
                                                <option value="Quotation Sent" @selected(old('status', $activeQuotation->status) === 'Quotation Sent')>Quotation Sent</option>
                                                <option value="Accepted" @selected(old('status', $activeQuotation->status) === 'Accepted')>Accepted</option>
                                                <option value="Rejected" @selected(old('status', $activeQuotation->status) === 'Rejected')>Rejected</option>
                                                <option value="Quotation Rework" @selected(old('status', $activeQuotation->status) === 'Quotation Rework')>Quotation Rework</option>
                                            </x-ui.odoo-form-ui>
                                        </div>
                                    </div>

                                    <!-- Odoo Style Items Lines Table -->
                                    <div class="border-top pt-4">
                                        <h5 class="fw-bold text-dark mb-3">Order Lines</h5>
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

                                    <!-- Calculation Totals aligned Right Odoo Sheet Footer -->
                                    <div class="row mt-4 pt-3 border-top justify-content-end text-dark fs-13">
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
                                        <a href="{{ route('crm.leads.show', $lead->id) }}" class="btn btn-md btn-light border py-2 px-4 shadow-sm">Discard</a>
                                        <button type="submit" class="btn btn-md btn-primary py-2 px-5 fw-bold shadow-sm" style="background-color: #714B67; border-color: #714B67;">Save Changes</button>
                                    </div>
                                </form>

                            @elseif ($activeQuotation)
                                <!-- ==================== STATE 3: LINKED QUOTATION DETAILS VIEW ==================== -->
                                <div id="quotation-print-area">
                                    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2.5 flex-wrap gap-2 d-print-none">
                                        <h4 class="fw-bold text-dark mb-0">Quotation Details</h4>
                                        <div class="d-flex gap-2 align-items-center">
                                            <!-- Quotation Status Dropdown -->
                                            <div class="d-inline-block" style="width: 180px; min-width: 180px;">
                                                <form action="{{ route('crm.quotations.updateStatus', $activeQuotation->id) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    @method('PATCH')
                                                    <select name="status" class="form-control status-select" data-select2-selector="status" style="width: 100%; display: inline-block; vertical-align: middle;">
                                                        @foreach(['Draft', 'Quotation Sent', 'Accepted', 'Rejected', 'Quotation Rework'] as $qStatus)
                                                            @php
                                                                $bgClass = 'bg-primary';
                                                                if ($qStatus === 'Quotation Sent') $bgClass = 'bg-info';
                                                                elseif ($qStatus === 'Accepted') $bgClass = 'bg-success';
                                                                elseif ($qStatus === 'Rejected') $bgClass = 'bg-danger';
                                                                elseif ($qStatus === 'Quotation Rework') $bgClass = 'bg-warning';
                                                            @endphp
                                                            <option value="{{ $qStatus }}" data-bg="{{ $bgClass }}" @selected($activeQuotation->status === $qStatus)>
                                                                {{ $qStatus }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </form>
                                            </div>

                                            <a href="{{ route('crm.leads.show', ['lead' => $lead->id, 'edit_quotation' => 1]) }}" class="btn btn-sm btn-light border fw-semibold">
                                                <i class="feather-edit me-1"></i>Edit Quotation
                                            </a>
                                        </div>
                                    </div>

                                    <div class="row g-4 mb-4 fs-13 text-dark">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="text-muted fs-11 text-uppercase fw-bold d-block mb-1">Customer / Client</label>
                                                <div class="fw-bold text-primary fs-15">{{ $lead->company_name }}</div>
                                                @if($lead->contact_person)
                                                    <div class="text-muted fs-12 mt-0.5"><i class="feather-user me-1"></i>{{ $lead->contact_person }}</div>
                                                @endif
                                            </div>
                                            <div class="mb-3">
                                                <label class="text-muted fs-11 text-uppercase fw-bold d-block mb-1">Email</label>
                                                <div class="fw-semibold">{{ $activeQuotation->email ?: ($lead->email ?: '—') }}</div>
                                            </div>
                                            <div class="mb-3">
                                                <label class="text-muted fs-11 text-uppercase fw-bold d-block mb-1">Phone</label>
                                                <div class="fw-semibold">{{ $activeQuotation->phone ?: ($lead->phone ?: '—') }}</div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="text-muted fs-11 text-uppercase fw-bold d-block mb-1">Quotation Ref</label>
                                                <div class="fw-bold text-dark fs-15">{{ $activeQuotation->quotation_number }}</div>
                                            </div>
                                            <div class="mb-3 mb-md-0">
                                                <div class="row">
                                                    <div class="col-6">
                                                        <label class="text-muted fs-11 text-uppercase fw-bold d-block mb-1">Date</label>
                                                        <div class="fw-semibold">{{ $activeQuotation->quotation_date ? $activeQuotation->quotation_date->format('d M Y') : '—' }}</div>
                                                    </div>
                                                    <div class="col-6">
                                                        <label class="text-muted fs-11 text-uppercase fw-bold d-block mb-1">Expires</label>
                                                        <div class="fw-semibold text-danger">{{ $activeQuotation->expiration_date ? $activeQuotation->expiration_date->format('d M Y') : '—' }}</div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <label class="text-muted fs-11 text-uppercase fw-bold d-block mb-1">Quotation Status</label>
                                                <div class="fw-semibold"><span class="badge bg-soft-primary text-primary">{{ $activeQuotation->status }}</span></div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Items Order Lines Table -->
                                    <div class="border-top pt-4">
                                        <h5 class="fw-bold text-dark mb-3">Order Lines</h5>
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
                                    <div class="row mt-4 pt-3 border-top justify-content-end text-dark fs-13">
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
                            @endif
                </div>
            </div>
            @endif

            <!-- TAB 2: ACTIVITIES & HISTORY PANE -->
            <div class="tab-pane fade" id="timeline-pane" role="tabpanel" aria-labelledby="timeline-tab">
                <div class="py-2">
                    
                    <div class="row">
                        <!-- Column 1: Activities (6 cols) -->
                        <div class="col-md-6 border-end">
                            <h5 class="fw-bold text-dark fs-14 mb-3"><i class="feather-activity text-primary me-2"></i>Activities</h5>
                            
                            <!-- Inline Chatter Control Buttons -->
                            <div class="d-flex gap-2 mb-4">
                                <button type="button" class="btn btn-md btn-primary fw-bold px-4" data-bs-toggle="modal" data-bs-target="#modalLogNote" style="background-color: var(--bs-primary); border-color: var(--bs-primary);">
                                    <i class="feather-file-text me-1"></i>Log Note
                                </button>
                                <button type="button" class="btn btn-md btn-outline-primary fw-bold px-4" data-bs-toggle="modal" data-bs-target="#modalScheduleActivity">
                                    <i class="feather-calendar me-1"></i>Schedule Activity
                                </button>
                            </div>

                            <!-- Chatter Timeline Feed -->
                            <div class="odoo-chatter-timeline py-2" style="max-height: 800px; overflow-y: auto; scrollbar-width: thin;">
                                @if($lead->followups->isEmpty())
                                    <div class="text-center py-5 text-muted border border-dashed rounded bg-white fs-12">
                                        <i class="feather-clock fs-24 mb-1.5 d-block text-muted opacity-50"></i>
                                        No chatter history recorded.
                                    </div>
                                @else
                                    <div class="activity-feed-2 ms-3">
                                        @foreach($lead->followups as $followup)
                                            @php
                                                $feedClass = 'feed-item-primary';
                                                $bgBadge = 'bg-soft-primary text-primary';
                                                if($followup->type === 'Email') {
                                                    $feedClass = 'feed-item-info';
                                                    $bgBadge = 'bg-soft-info text-info';
                                                } elseif($followup->type === 'Meeting') {
                                                    $feedClass = 'feed-item-success';
                                                    $bgBadge = 'bg-soft-success text-success';
                                                } elseif($followup->type === 'Demo') {
                                                    $feedClass = 'feed-item-warning';
                                                    $bgBadge = 'bg-soft-warning text-warning';
                                                }
                                                
                                                if($followup->status === 'Pending') {
                                                    $feedClass = 'feed-item-warning';
                                                }
                                            @endphp
                                            <div class="feed-item {{ $feedClass }}">
                                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-1">
                                                    <span class="lead_date fs-10 text-uppercase">
                                                        {{ $followup->followup_date->diffForHumans() }} ({{ $followup->followup_date->format('d M Y, h:i A') }})
                                                    </span>
                                                    
                                                    <!-- Action Buttons -->
                                                    <div class="d-flex gap-1">
                                                        @if($followup->status === 'Pending')
                                                            <form action="{{ route('crm.followups.update', $followup->id) }}" method="POST" class="d-inline">
                                                                @csrf
                                                                @method('PUT')
                                                                <input type="hidden" name="status" value="Completed">
                                                                <button type="submit" class="btn btn-icon btn-xs btn-soft-success" title="Mark Done" style="width:20px;height:20px;display:inline-flex;align-items:center;justify-content:center;">
                                                                    <i class="feather-check" style="font-size: 10px;"></i>
                                                                </button>
                                                            </form>
                                                        @endif
                                                        <form action="{{ route('crm.followups.destroy', $followup->id) }}" method="POST" class="d-inline">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="btn btn-icon btn-xs btn-soft-danger" onclick="return confirm('Are you sure you want to delete this log?')" style="width:20px;height:20px;display:inline-flex;align-items:center;justify-content:center;">
                                                                <i class="feather-trash-2" style="font-size: 10px;"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                                
                                                <div class="mt-1.5">
                                                    <span class="badge {{ $bgBadge }} fs-9 py-0.5 px-1.5 fw-bold text-uppercase">{{ $followup->type }}</span>
                                                    @if($followup->status === 'Pending')
                                                        <span class="badge bg-soft-warning text-warning fs-9 py-0.5 px-1 ms-1 fw-semibold">Pending</span>
                                                    @else
                                                        <span class="badge bg-soft-success text-success fs-9 py-0.5 px-1 ms-1 fw-semibold">Done</span>
                                                    @endif
                                                    
                                                    @if($followup->notes)
                                                        <span class="text fs-12.5 d-block fw-bold text-dark mt-2" style="white-space: pre-wrap; line-height: 1.4;">{{ $followup->notes }}</span>
                                                    @endif
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>
                        
                        <!-- Column 2: Lead History (6 cols) -->
                        <div class="col-md-6 ps-md-4">
                            <h5 class="fw-bold text-dark fs-14 mb-3"><i class="feather-clock text-primary me-2"></i>Lead History</h5>
                            
                            <div class="lead-history-panel py-2" style="max-height: 860px; overflow-y: auto; scrollbar-width: thin;">
                                @if($lead->histories->isEmpty())
                                    <div class="text-center py-5 text-muted border border-dashed rounded bg-white fs-12">
                                        <i class="feather-clock fs-24 mb-1.5 d-block text-muted opacity-50"></i>
                                        No tracking history recorded yet.
                                    </div>
                                @else
                                    <div class="activity-feed-2 ms-3">
                                        @foreach($lead->histories as $history)
                                            @php
                                                $feedClass = 'feed-item-primary';
                                                if ($history->event_type === 'created') {
                                                    $feedClass = 'feed-item-success';
                                                } elseif ($history->event_type === 'assigned') {
                                                    $feedClass = 'feed-item-info';
                                                } elseif ($history->event_type === 'status_changed') {
                                                    $feedClass = 'feed-item-warning';
                                                } elseif ($history->event_type === 'quotation_created') {
                                                    $feedClass = 'feed-item-primary';
                                                } elseif ($history->event_type === 'quotation_status_changed') {
                                                    $feedClass = 'feed-item-secondary';
                                                } elseif ($history->event_type === 'activity_scheduled') {
                                                    $feedClass = 'feed-item-info';
                                                } elseif ($history->event_type === 'activity_completed') {
                                                    $feedClass = 'feed-item-success';
                                                } elseif ($history->event_type === 'activity_deleted') {
                                                    $feedClass = 'feed-item-danger';
                                                }
                                            @endphp
                                            <div class="feed-item {{ $feedClass }}">
                                                <span class="lead_date fs-10 text-uppercase">{{ $history->created_at->diffForHumans() }} ({{ $history->created_at->format('d M Y, h:i A') }})</span>
                                                <span class="text fs-12.5 d-block fw-bold text-dark">{{ $history->notes }}</span>
                                                @if($history->old_value || $history->new_value)
                                                    <div class="mt-1 fs-11 text-muted bg-light p-1.5 rounded d-inline-block">
                                                        @if($history->old_value)
                                                            <span class="text-danger"><del>{{ $history->old_value }}</del></span>
                                                            <i class="feather-arrow-right mx-1"></i>
                                                        @endif
                                                        <span class="text-success fw-bold">{{ $history->new_value }}</span>
                                                    </div>
                                                @endif
                                                <div class="mt-1 text-muted fs-11">
                                                    <span>by {{ $history->user?->name ?: 'System' }}</span>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                    
                </div>
            </div>
            </div>

        </div> <!-- Closes tab-content -->
    </div> <!-- Closes card-body -->
</div> <!-- Closes card -->

<!-- Log Note Modal -->
<x-ui.modal id="modalLogNote" title="LOG DISCUSSION NOTE" :centered="true" :formAction="route('crm.leads.followups.store', $lead->id)" formMethod="POST" submitText="Log Note" closeText="Cancel">
    <input type="hidden" name="type" value="Call">
    <input type="hidden" name="status" value="Completed">
    <input type="hidden" name="followup_date" value="{{ date('Y-m-d H:i') }}">
    
    <div class="mb-3 text-start">
        <label class="form-label fs-11 fw-bold text-muted text-uppercase mb-1">Interaction Type</label>
        <select class="form-select form-select-sm fw-semibold" name="type_select" onchange="this.form.type.value = this.value">
            <option value="Call">Call Log</option>
            <option value="Email">Email sent/received</option>
            <option value="Meeting">Meeting description</option>
            <option value="Demo">System demo log</option>
        </select>
    </div>
    <div class="mb-3 text-start">
        <label class="form-label fs-11 fw-bold text-muted text-uppercase mb-1">Notes / Summary</label>
        <textarea name="notes" class="form-control form-control-sm" rows="4" required placeholder="Describe what was discussed..."></textarea>
    </div>
</x-ui.modal>

<!-- Schedule Activity Modal -->
<x-ui.modal id="modalScheduleActivity" title="SCHEDULE NEXT ACTIVITY" :centered="true" :formAction="route('crm.leads.followups.store', $lead->id)" formMethod="POST" submitText="Schedule" closeText="Cancel">
    <input type="hidden" name="status" value="Pending">
    
    <div class="mb-3 text-start">
        <label class="form-label fs-11 fw-bold text-muted text-uppercase mb-1">Activity Type</label>
        <select class="form-select form-select-sm fw-semibold" name="type" required>
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
            <input type="text" class="form-control form-control-sm" name="followup_date" id="inline_activity_datepicker" required autocomplete="off">
        </div>
    </div>
    <div class="mb-3 text-start">
        <label class="form-label fs-11 fw-bold text-muted text-uppercase mb-1">Description / Plan</label>
        <textarea name="notes" class="form-control form-control-sm" rows="4" placeholder="What needs to be discussed?"></textarea>
    </div>
</x-ui.modal>
@endsection

@push('styles')
    <!-- Select2 Styles -->
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
    <style>
        /* Custom Tab Styles matching Zoho/Duralux theme */
        .nav-tabs {
            border-bottom: 1px solid #dee2e6;
        }
        .nav-tabs .nav-link {
            border: none;
            color: #6c757d;
            background: transparent;
            border-bottom: 2px solid transparent;
            padding: 8px 16px;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        .nav-tabs .nav-link:hover {
            color: var(--bs-primary);
            border-bottom-color: #dee2e6;
        }
        .nav-tabs .nav-link.active {
            color: var(--bs-primary) !important;
            border-bottom: 2px solid var(--bs-primary) !important;
            background: transparent !important;
            font-weight: 700;
        }

        .odoo-statusbar {
            display: flex;
            border-radius: 4px;
            border: 1px solid #dee2e6;
            background-color: #f8f9fa;
            overflow-x: auto;
            scrollbar-width: none; /* Firefox */
            -ms-overflow-style: none; /* IE/Edge */
            flex-wrap: nowrap;
            max-width: 100%;
        }
        .odoo-statusbar::-webkit-scrollbar {
            display: none; /* Chrome/Safari/Opera */
        }
        .odoo-status-step {
            padding: 6px 16px 6px 26px;
            font-size: 11px;
            font-weight: 700;
            color: #6c757d;
            position: relative;
            background-color: #f8f9fa;
            border-right: 1px solid #dee2e6;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            flex-shrink: 0;
        }
        .odoo-status-step:first-child {
            padding-left: 16px;
        }
        .odoo-status-step:last-child {
            border-right: none;
        }
        .odoo-status-step.active {
            background-color: var(--bs-primary);
            color: #ffffff;
        }
        .odoo-status-step::after {
            content: "";
            position: absolute;
            right: -10px;
            top: 0;
            width: 0;
            height: 0;
            border-top: 16px solid transparent;
            border-bottom: 16px solid transparent;
            border-left: 10px solid #f8f9fa;
            z-index: 2;
        }
        .odoo-status-step.active::after {
            border-left-color: var(--bs-primary);
        }
        .odoo-status-step.completed {
            color: var(--bs-primary);
            background-color: #eef2f7;
        }
        .odoo-status-step.completed::after {
            border-left-color: #eef2f7;
        }
        .odoo-sheet {
            background: #ffffff;
            border: 1px solid #dee2e6;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            padding: 24px;
        }
        .odoo-chatter-timeline {
            position: relative;
        }
        .odoo-inline-form {
            animation: slideDown 0.2s ease-out;
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Odoo-style Inputs */
        .odoo-form-group {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        .odoo-form-label {
            width: 130px;
            font-size: 13px;
            font-weight: 700;
            color: #495057;
            margin-bottom: 0;
        }
        .odoo-form-control {
            border: none;
            border-bottom: 1px solid #ced4da;
            border-radius: 0;
            padding: 2px 0;
            background-color: transparent;
            font-size: 13px;
            color: #212529;
            width: 100%;
        }
        .odoo-form-control:focus {
            border-color: #714B67;
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
            border-bottom-color: #714B67;
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
            border-bottom: 1px solid #714B67;
            outline: none;
        }
        
        /* Odoo Action links */
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

        /* Borderless Select2 theme custom override for Odoo Look */
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
            border-bottom-color: #714B67 !important;
            box-shadow: none !important;
        }
        .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
            padding-left: 0 !important;
            font-size: 13px !important;
            color: #212529 !important;
        }

        @media print {
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
            .card-header,
            .col-lg-4,
            .odoo-chatter-timeline {
                display: none !important;
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
            .card {
                background: #ffffff !important;
                margin: 0 !important;
                padding: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
                box-shadow: none !important;
                border: none !important;
                position: static !important;
            }

            .col-lg-8 {
                width: 100% !important;
                max-width: 100% !important;
                flex: 0 0 100% !important;
                padding: 0 !important;
            }

            .odoo-sheet {
                border: none !important;
                box-shadow: none !important;
                padding: 0 !important;
                margin: 0 !important;
                background: #ffffff !important;
            }

            #quotation-print-area {
                width: 100% !important;
                margin: 0 !important;
                padding: 8mm 12mm !important;
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
        $(function () {
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

            // Odoo Status Chevron click handler
            $('.odoo-status-step').on('click', function() {
                var status = $(this).data('status');
                if (status) {
                    $('#statusChangeInput').val(status);
                    $('#statusChangeForm').submit();
                }
            });

            // ==================== DYNAMIC ITEMS TABLE FOR INLINE FORM ====================
            let rowIndex = 0;

            function getRowHtml(index) {
                return `
                    <tr class="item-row" data-row-id="${index}">
                        <td class="ps-3">
                            <select name="items[${index}][item_name]" class="form-select item-name-input" required>
                                <option value="">Select Item</option>
                                <option value="ERP Software License">ERP Software License</option>
                                <option value="Custom ERP Development">Custom ERP Development</option>
                                <option value="SaaS Annual Subscription">SaaS Annual Subscription</option>
                                <option value="CRM Integration Module">CRM Integration Module</option>
                                <option value="Database Migration Services">Database Migration Services</option>
                                <option value="IT Infrastructure Support">IT Infrastructure Support</option>
                                <option value="Training Workshop (per day)">Training Workshop (per day)</option>
                                <option value="Other">Other (Specify in details)</option>
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
            const prefillProduct = @json($lead->product);
            const prefillAmount = @json($lead->expected_amount);

            if (hasCreateQ || hasEditQ) {
                if (existingItems.length > 0) {
                    existingItems.forEach(function(item) {
                        addRow(item);
                    });
                } else if (hasCreateQ && (prefillProduct || prefillAmount)) {
                    addRow({
                        item_name: prefillProduct || 'ERP CRM Integration Product/Service',
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
                const newRow = $(getRowHtml(rowIndex));
                $('#itemsTable tbody').append(newRow);

                // Initialize select2 on the newly added select element
                newRow.find('.item-name-input').select2({
                    theme: "bootstrap-5",
                    width: "100%"
                });

                // Prefill details
                if (item) {
                    newRow.find('.item-name-input').val(item.item_name).trigger('change');
                    newRow.find('textarea').val(item.description || '');
                    if (item.description) {
                        $('#desc-container-' + rowIndex).show();
                        newRow.find('.toggle-desc-btn').html('<i class="feather-minus me-1"></i>Remove Description');
                    }
                    newRow.find('.qty-input').val(item.quantity);
                    newRow.find('.price-input').val(item.unit_price);
                    newRow.find('.tax-input').val(item.tax_rate);
                }

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

            // Odoo Status Steps Click Handler
            $(document).on('click', '.odoo-status-step', function() {
                const newStatus = $(this).data('status');
                if (newStatus) {
                    $('#statusChangeInput').val(newStatus);
                    $('#statusChangeForm').submit();
                }
            });

            // Inline datepicker initialization
            if ($('#inline_activity_datepicker').length) {
                $('#inline_activity_datepicker').daterangepicker({
                    singleDatePicker: true,
                    timePicker: true,
                    timePickerIncrement: 5,
                    locale: {
                        format: 'YYYY-MM-DD hh:mm A'
                    }
                });
            }

            // Toggle inline chatter forms
            $('#btnLogNote').on('click', function() {
                $('#formScheduleActivity').slideUp(150);
                $('#formLogNote').slideToggle(150);
            });

            $('#btnScheduleActivity').on('click', function() {
                $('#formLogNote').slideUp(150);
                $('#formScheduleActivity').slideToggle(150);
            });

            $('#btnCancelLogNote').on('click', function() {
                $('#formLogNote').slideUp(150);
            });

            $('#btnCancelScheduleActivity').on('click', function() {
                $('#formScheduleActivity').slideUp(150);
            });
        });
    </script>
@endpush
