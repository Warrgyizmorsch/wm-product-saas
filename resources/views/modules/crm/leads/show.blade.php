@extends('layouts.duralux')

@section('title', __('crm.lead_details') . ' | SaaS ERP')
@section('page-title', __('crm.lead_profile'))
@section('breadcrumb', 'CRM / ' . __('crm.leads') . ' / ' . __('crm.profile'))

@section('content')
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
                            $statusKey = $lead->status === 'Converted' ? 'Won' : ($lead->status ?: 'New');
                            $statusClass = 'bg-soft-primary text-primary';
                            if($statusKey === 'Qualified') $statusClass = 'bg-soft-teal text-teal';
                            elseif($statusKey === 'Won') $statusClass = 'bg-soft-success text-success';
                            elseif($statusKey === 'Lost') $statusClass = 'bg-soft-danger text-danger';
                        @endphp
                        <span class="badge {{ $statusClass }} px-2 py-0.5 fs-10 fw-semibold">{{ __('crm.statuses.' . $statusKey) }}</span>
                        @if($lead->segment && $lead->segment !== 'Select an Option')
                            <span class="badge bg-soft-secondary text-secondary px-2 py-0.5 fs-10 fw-semibold">{{ __('crm.segments.' . $lead->segment) ?? $lead->segment }}</span>
                        @endif
                    </div>
                    <!-- Tag Button -->
                    <div class="mt-1 d-flex align-items-center">
                        <button type="button" class="btn btn-xs btn-outline-secondary zoho-tag-btn d-inline-flex align-items-center text-muted px-2 py-0.5 border" style="font-size: 10px; border-radius: 3px;">
                            <i class="feather-tag me-1 fs-9"></i> {{ __('crm.add_tags') }}
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Right-side Action Buttons -->
            <div class="d-flex align-items-center gap-2 flex-wrap">
                @if($lead->crm_account_id)
                    <a href="{{ route('crm.accounts.show', $lead->crm_account_id) }}" class="btn btn-xs btn-soft-primary fw-bold py-1 px-2.5 rounded shadow-sm d-inline-flex align-items-center" style="font-size: 11px;">
                        <i class="feather-briefcase me-1"></i> View Account
                    </a>
                    @if($lead->crm_deal_id)
                        <a href="{{ route('crm.deals.show', $lead->crm_deal_id) }}" class="btn btn-xs btn-soft-success fw-bold py-1 px-2.5 rounded shadow-sm d-inline-flex align-items-center" style="font-size: 11px;">
                            <i class="feather-git-branch me-1"></i> View Deal
                        </a>
                    @endif
                @elseif($lead->status === 'Qualified')
                    <form action="{{ route('crm.leads.qualify', $lead->id) }}" method="POST" class="d-inline m-0 p-0">
                        @csrf
                        @method('PATCH')
                        <x-ui.button type="submit" variant="warning" size="xs" icon="feather-user-check" class="text-dark fw-bold py-1 px-2.5 rounded shadow-sm">
                            CONVERT TO ACCOUNT & DEAL
                        </x-ui.button>
                    </form>
                @endif

                <!-- Send Email Button -->
                @if ($lead->email)
                    <a href="mailto:{{ $lead->email }}" class="btn btn-xs btn-primary fw-bold py-1 px-2.5 rounded shadow-sm d-inline-flex align-items-center text-white" style="background-color: #1e40af; border-color: #1e40af; font-family: 'Inter', sans-serif; font-size: 11px;">
                        <i class="feather-mail me-1"></i> {{ __('crm.email') }}
                    </a>
                @endif
                
                <!-- Back Button -->
                <a href="{{ route('crm.leads.index') }}" class="btn btn-xs btn-outline-secondary fw-bold py-1 px-2.5 rounded bg-white text-dark border-secondary d-inline-flex align-items-center" style="font-family: 'Inter', sans-serif; font-size: 11px;">
                    <i class="feather-arrow-left me-1"></i> {{ __('crm.back') }}
                </a>

                <!-- More Actions 3-Dot Dropdown using common component -->
                <x-ui.action-dropdown id="leadProfileActionsDropdown">
                    <li>
                        <a class="dropdown-item py-2" href="{{ route('crm.leads.show', ['lead' => $lead->id, 'edit_lead' => 1]) }}">
                            <i class="feather-edit me-1.5 text-muted"></i> {{ __('crm.edit_lead') }}
                        </a>
                    </li>
                </x-ui.action-dropdown>
                
                <!-- Pagination Arrows -->
                <div class="d-flex align-items-center ms-1 border rounded px-1 py-0.5 bg-white">
                    @if($prevLead)
                        <a href="{{ route('crm.leads.show', $prevLead->id) }}" class="btn btn-xs btn-link text-dark p-1 border-0 d-inline-flex align-items-center justify-content-center" :title="__('crm.previous_lead')">
                            <i class="feather-chevron-left fs-12"></i>
                        </a>
                    @else
                        <button class="btn btn-xs btn-link p-1 border-0 d-inline-flex align-items-center justify-content-center text-muted opacity-50" style="cursor: not-allowed;" disabled>
                            <i class="feather-chevron-left fs-12"></i>
                        </button>
                    @endif

                    @if($nextLead)
                        <a href="{{ route('crm.leads.show', $nextLead->id) }}" class="btn btn-xs btn-link text-dark p-1 border-0 d-inline-flex align-items-center justify-content-center" :title="__('crm.next_lead')">
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
                    <h6 class="text-uppercase fw-bold text-muted mb-3" style="font-size: 10px; letter-spacing: 0.8px;">{{ __('crm.related_list') }}</h6>
                    <ul class="nav flex-column zoho-sidebar-nav gap-1" id="zohoSidebarLinks">
                        <li class="nav-item">
                            <a href="#sectionNotes" class="nav-link active py-1.5 px-2 fs-12 rounded text-dark fw-medium">{{ __('crm.notes') }}</a>
                        </li>
                        <li class="nav-item">
                            <a href="#subtab-interactions" class="nav-link py-1.5 px-2 fs-12 rounded text-dark">{{ __('crm.activities') }}</a>
                        </li>
                        <li class="nav-item">
                            <a href="#subtab-history" class="nav-link py-1.5 px-2 fs-12 rounded text-dark">{{ __('crm.history') }}</a>
                        </li>
                        <li class="nav-item">
                            <a href="#sectionLeadInfo" class="nav-link py-1.5 px-2 fs-12 rounded text-dark">{{ __('crm.lead_information') }}</a>
                        </li>
                        <li class="nav-item">
                            <a href="#sectionAddressInfo" class="nav-link py-1.5 px-2 fs-12 rounded text-dark">{{ __('crm.address_details') }}</a>
                        </li>
                        <li class="nav-item">
                            <a href="#sectionRequirements" class="nav-link py-1.5 px-2 fs-12 rounded text-dark">{{ __('crm.requirements') }}</a>
                        </li>
                        <li class="nav-item">
                            <a href="#sectionDocuments" class="nav-link py-1.5 px-2 fs-12 rounded text-dark">{{ __('crm.lead_documents') }}</a>
                        </li>
                        @if ($activeQuotation && $activeQuotation->getRevisionHistory()->count() > 1)
                            <li class="nav-item">
                                <a href="#sectionQuotationHistory" class="nav-link py-1.5 px-2 fs-12 rounded text-dark">{{ __('crm.quotation_revision_history') }}</a>
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
                                {{ __('crm.overview') }}
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link px-3 py-1 fw-bold fs-12 rounded-pill" id="timeline-tab" data-bs-toggle="tab" data-bs-target="#timeline-pane" type="button" role="tab" aria-controls="timeline-pane" aria-selected="false">
                                {{ __('crm.timeline') }}
                            </button>
                        </li>
                        @if ($activeQuotation || request()->has('create_quotation'))
                            <li class="nav-item" role="presentation">
                                <button class="nav-link px-3 py-1 fw-bold fs-12 rounded-pill {{ request()->has('create_quotation') || request()->has('edit_quotation') || request()->has('view_quotation') ? 'active' : '' }}" id="quotation-tab" data-bs-toggle="tab" data-bs-target="#quotation-pane" type="button" role="tab" aria-controls="quotation-pane" aria-selected="false">
                                    {{ __('crm.quotation') }}
                                </button>
                            </li>
                        @endif
                    </ul>

                    <!-- Clock / Last Update Information -->
                    <div class="d-flex align-items-center text-muted fs-11 fw-medium" style="font-family: 'Inter', sans-serif;">
                        <i class="feather-clock me-1.5 text-muted fs-12"></i> 
                        {{ __('crm.last_update') }} : {{ $lead->updated_at ? $lead->updated_at->diffForHumans() : 'Recently' }}
                    </div>
                </div>

                <!-- Main Scrollable Tab Content View -->
                <div class="pt-2 px-3 pb-3 tab-content" id="zohoLeadTabsContent">
                    
                    <!-- ==================== TAB 1: OVERVIEW PANE ==================== -->
                    <div class="tab-pane fade show {{ !request()->has('create_quotation') && !request()->has('edit_quotation') && !request()->has('view_quotation') && old('form_type') !== 'quotation_create' && old('form_type') !== 'quotation_edit' ? 'active' : '' }}" id="overview-pane" role="tabpanel" aria-labelledby="overview-tab">
                        
                        @if (request()->has('edit_lead') || old('form_type') === 'lead_edit')
                            <!-- ==================== STATE: EDIT LEAD FORM ==================== -->
                            <div class="card border shadow-sm" style="border-radius: 4px; border-color: #e2e8f0 !important; background-color: #ffffff;">
                                <div class="card-body p-3">
                                    <form action="{{ route('crm.leads.update', $lead->id) }}" method="POST" class="odoo-sheet" novalidate>
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="form_type" value="lead_edit">
                                        
                                        <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2 flex-wrap gap-2">
                                            <h5 class="fw-bold text-dark mb-0">{{ __('crm.edit_lead_details') }}</h5>
                                            <div class="d-flex gap-2">
                                                <a href="{{ route('crm.leads.show', $lead->id) }}" class="btn btn-sm btn-light border fs-12">{{ __('crm.cancel') }}</a>
                                                <button type="submit" class="btn btn-sm btn-primary py-1.5 px-3 fw-bold fs-12" style="background-color: #1e40af; border-color: #1e40af;">{{ __('crm.save_changes') }}</button>
                                            </div>
                                        </div>

                                        <div class="row g-4 fs-13 text-dark">
                                            <!-- Left Column: Contact Info, Products, Pricing, Requirements -->
                                            <div class="col-md-6 border-end">
                                                 <!-- B2B vs B2C Segment Toggle -->
                                                 <div class="mb-3 p-3 bg-soft-primary rounded-3 border border-primary-subtle shadow-2xs">
                                                     <label class="fw-bold text-dark mb-2 d-block fs-13"><i class="feather-layers me-1 text-primary"></i> Customer Type (Lead Segment):</label>
                                                     <div class="d-flex gap-4">
                                                         <div class="form-check form-check-inline">
                                                             <input class="form-check-input" type="radio" name="lead_type" id="edit_lead_type_b2b" value="b2b" {{ old('lead_type', $lead->lead_type ?: 'b2b') === 'b2b' ? 'checked' : '' }} onchange="toggleLeadType('b2b')">
                                                             <label class="form-check-label fw-bold text-dark cursor-pointer" for="edit_lead_type_b2b">
                                                                 🏢 B2B (Business Client)
                                                             </label>
                                                         </div>
                                                         <div class="form-check form-check-inline">
                                                             <input class="form-check-input" type="radio" name="lead_type" id="edit_lead_type_b2c" value="b2c" {{ old('lead_type', $lead->lead_type) === 'b2c' ? 'checked' : '' }} onchange="toggleLeadType('b2c')">
                                                             <label class="form-check-label fw-bold text-dark cursor-pointer" for="edit_lead_type_b2c">
                                                                 👤 B2C (Individual Customer)
                                                             </label>
                                                         </div>
                                                     </div>
                                                 </div>

                                                <h6 class="fw-bold text-primary mb-3">{{ __('crm.company_contact_info') }}</h6>
                                                
                                                <x-ui.odoo-form-ui type="input" :label="__('crm.call_date')" name="call_date" id="lead_call_date_picker" :value="old('call_date', $lead->call_date ? $lead->call_date->format('Y-m-d h:i A') : '')" required="true" :errorText="$errors->first('call_date')" />

                                                <x-ui.odoo-form-ui type="input" :label="__('crm.company_name')" name="company_name" id="edit_company_name_input" :value="old('company_name', $lead->company_name)" :placeholder="__('crm.company_name')" :errorText="$errors->first('company_name')" />

                                                <x-ui.odoo-form-ui type="input" label="GSTIN / Tax No." name="gstin" id="edit_gstin_input" :value="old('gstin', $lead->gstin ?? '')" placeholder="e.g. 27AAAAA0000A1Z5" />

                                                <x-ui.odoo-form-ui type="input" label="Company Email" name="company_email" id="edit_company_email_input" inputType="email" :value="old('company_email', $lead->company_email ?? '')" placeholder="company@office.com" />

                                                <x-ui.odoo-form-ui type="input" label="Company Phone" name="company_phone" id="edit_company_phone_input" :value="old('company_phone', $lead->company_phone ?? '')" placeholder="Company Landline / Phone" oninput="this.value = this.value.replace(/[^0-9]/g, '')" />

                                                <x-ui.odoo-form-ui type="input" :label="__('crm.contact_person')" name="contact_person" :value="old('contact_person', $lead->contact_person)" :placeholder="__('crm.contact_person')" :errorText="$errors->first('contact_person')" />

                                                <x-ui.odoo-form-ui type="input" :label="__('crm.contact_email')" name="email" inputType="email" :value="old('email', $lead->email)" placeholder="email@address.com" :errorText="$errors->first('email')" />

                                                <x-ui.odoo-form-ui type="input" :label="__('crm.contact_phone')" name="phone" :value="old('phone', $lead->phone)" :placeholder="__('crm.contact_phone')" :errorText="$errors->first('phone')" oninput="this.value = this.value.replace(/[^0-9]/g, '')" />

                                                <x-ui.odoo-form-ui type="select" :label="__('crm.lead_owner')" name="lead_owner_id" :errorText="$errors->first('lead_owner_id')">
                                                    <option value="">{{ __('crm.select_owner_unassigned') }}</option>
                                                    @foreach($users as $user)
                                                        <option value="{{ $user->id }}" @selected(old('lead_owner_id', $lead->lead_owner_id) == $user->id)>{{ $user->name }}</option>
                                                    @endforeach
                                                </x-ui.odoo-form-ui>

                                                 @php
                                                     $savedAddlContacts = old('additional_contacts', $lead->additional_contacts ?: []);
                                                 @endphp

                                                 <style>
                                                     .addl-contact-card .odoo-form-label {
                                                         width: 85px !important;
                                                         min-width: 85px !important;
                                                         white-space: nowrap !important;
                                                         padding-right: 6px !important;
                                                     }
                                                 </style>

                                                 <div class="my-3 border-top pt-3">
                                                     <div class="d-flex align-items-center justify-content-between mb-2">
                                                         <div class="d-flex align-items-center gap-2">
                                                             <h6 class="fw-bold text-dark mb-0 fs-13">Additional Contacts</h6>
                                                             <span class="badge bg-soft-primary text-primary rounded-circle px-2 py-0.5 font-monospace fs-11" id="showAddlContactCountBadge">{{ count($savedAddlContacts) }}</span>
                                                         </div>
                                                         <button type="button" class="btn btn-xs btn-primary fw-bold px-2.5 py-1 text-uppercase text-white d-inline-flex align-items-center" id="showCloneContactMainBtn" style="border-radius: 4px; font-size: 11px;">
                                                             <i class="feather-plus me-1 fs-12"></i> CLONE CONTACT
                                                         </button>
                                                     </div>

                                                     <div id="showAdditionalContactsRepeaterContainer" class="d-flex flex-column gap-2">
                                                         @forelse($savedAddlContacts as $idx => $ac)
                                                             <div class="addl-contact-card p-2 px-3 mb-1 bg-white position-relative shadow-2xs" style="border: 1.5px solid var(--bs-primary) !important; border-radius: 8px !important;">
                                                                 <div class="d-flex align-items-center justify-content-between mb-1 pb-1 border-bottom">
                                                                     <span class="fs-11 fw-bold text-muted text-uppercase letter-spacing-1"><i class="feather-user me-1 text-primary"></i> Contact Person #<span class="contact-num">{{ $loop->iteration }}</span></span>
                                                                     <button type="button" class="btn btn-xs btn-soft-danger rounded-circle remove-contact-btn p-0 d-inline-flex align-items-center justify-content-center" title="Delete Contact" style="width: 22px; height: 22px; border-radius: 50%;">
                                                                         <i class="feather-trash-2 text-danger fs-11"></i>
                                                                     </button>
                                                                 </div>
                                                                 <div class="row g-2">
                                                                     <div class="col-md-6">
                                                                         <x-ui.odoo-form-ui type="input" label="Name" name="additional_contacts[{{ $idx }}][name]" :value="$ac['name'] ?? ''" placeholder="Contact Name" class="contact-name-input" />
                                                                     </div>
                                                                     <div class="col-md-6">
                                                                         <x-ui.odoo-form-ui type="input" label="Phone No." name="additional_contacts[{{ $idx }}][phone]" :value="$ac['phone'] ?? ''" placeholder="Phone Number" class="contact-phone-input" oninput="this.value = this.value.replace(/[^0-9]/g, '')" />
                                                                     </div>
                                                                     <div class="col-md-12">
                                                                         <x-ui.odoo-form-ui type="input" label="Email" name="additional_contacts[{{ $idx }}][email]" inputType="email" :value="$ac['email'] ?? ''" placeholder="Email" class="contact-email-input" />
                                                                     </div>
                                                                 </div>
                                                             </div>
                                                         @empty
                                                         @endforelse
                                                     </div>
                                                </div>

                                                 <h6 class="fw-bold text-primary mb-3 mt-4">{{ __('crm.address_details') }}</h6>

                                                 <x-ui.odoo-form-ui type="textarea" :label="__('crm.street_address')" name="address" rows="3" :placeholder="__('crm.street_address_placeholder')" :errorText="$errors->first('address')">{{ old('address', $lead->address) }}</x-ui.odoo-form-ui>

                                                 <x-ui.odoo-form-ui type="input" :label="__('crm.country')" name="country" :value="old('country', $lead->country)" :placeholder="__('crm.country')" :errorText="$errors->first('country')" />

                                                 <x-ui.odoo-form-ui type="input" :label="__('crm.state')" name="state" :value="old('state', $lead->state)" :placeholder="__('crm.state')" :errorText="$errors->first('state')" />

                                                 <x-ui.odoo-form-ui type="input" :label="__('crm.city')" name="city" :value="old('city', $lead->city)" :placeholder="__('crm.city')" :errorText="$errors->first('city')" />
                                             </div>

                                            <!-- Right Column: Requirements, Lead Classification & Revenue -->
                                            <div class="col-md-6">
                                                <h6 class="fw-bold text-primary mb-3">{{ __('crm.requirements') }}</h6>

                                                <x-ui.odoo-form-ui type="textarea" :label="__('crm.requirements')" name="requirement" rows="3" :placeholder="__('crm.requirements_placeholder')" :errorText="$errors->first('requirement')">{{ old('requirement', $lead->requirement) }}</x-ui.odoo-form-ui>

                                                <h6 class="fw-bold text-primary mb-3 mt-4">{{ __('crm.lead_classification') }}</h6>

                                                <x-ui.odoo-form-ui type="input" :label="__('crm.industry_type')" name="industry_type" :value="old('industry_type', $lead->industry_type)" :placeholder="__('crm.industry_type')" :errorText="$errors->first('industry_type')" />

                                                <x-ui.odoo-form-ui type="select" :label="__('crm.lead_source')" name="source" :errorText="$errors->first('source')">
                                                    <option value="">{{ __('crm.select_an_option') }}</option>
                                                    @foreach (['Cold Call', 'Employee Referral', 'Partner', 'Web Search', 'Advertisement', 'Trade Show'] as $srcOption)
                                                        <option value="{{ $srcOption }}" @selected(old('source', $lead->source) === $srcOption)>{{ __('crm.sources.' . $srcOption) ?? $srcOption }}</option>
                                                    @endforeach
                                                </x-ui.odoo-form-ui>

                                                <x-ui.odoo-form-ui type="select" :label="__('crm.priority')" name="priority" :errorText="$errors->first('priority')">
                                                    <option value="">{{ __('crm.select_an_option') }}</option>
                                                    @foreach (['Low', 'Medium', 'High'] as $prioOption)
                                                        <option value="{{ $prioOption }}" @selected(old('priority', $lead->priority) === $prioOption)>{{ __('crm.priorities.' . $prioOption) ?? $prioOption }}</option>
                                                    @endforeach
                                                </x-ui.odoo-form-ui>

                                                <x-ui.odoo-form-ui type="select" :label="__('crm.segment')" name="segment" :errorText="$errors->first('segment')">
                                                    <option value="">{{ __('crm.select_an_option') }}</option>
                                                    @foreach (['SMB', 'Mid-Market', 'Enterprise'] as $segOption)
                                                        <option value="{{ $segOption }}" @selected(old('segment', $lead->segment) === $segOption)>{{ __('crm.segments.' . $segOption) ?? $segOption }}</option>
                                                    @endforeach
                                                </x-ui.odoo-form-ui>

                                                <!-- Ultra Compact Product & Quantity Repeater Table Style (Right Side) -->
                                                <style>
                                                    #editProductItemsTable {
                                                        table-layout: fixed !important;
                                                        width: 100% !important;
                                                    }
                                                    #editProductItemsTable .select2-container {
                                                        width: 100% !important;
                                                        max-width: 100% !important;
                                                    }
                                                    #editProductItemsTable .select2-container .select2-selection--single {
                                                        height: 32px !important;
                                                        padding: 2px 8px !important;
                                                        font-size: 12px !important;
                                                        border-color: #dee2e6 !important;
                                                    }
                                                    #editProductItemsTable .select2-container .select2-selection--single .select2-selection__rendered {
                                                        line-height: 26px !important;
                                                        white-space: nowrap !important;
                                                        overflow: hidden !important;
                                                        text-overflow: ellipsis !important;
                                                        padding-left: 0 !important;
                                                        padding-right: 15px !important;
                                                    }
                                                    #editProductItemsTable .select2-container--bootstrap-5 .select2-selection--single .select2-selection__arrow {
                                                        height: 30px !important;
                                                    }
                                                    #editProductItemsTable .qty-row-input {
                                                        height: 32px !important;
                                                        font-size: 13px !important;
                                                        font-weight: 600 !important;
                                                        border-color: #dee2e6 !important;
                                                        background-color: #ffffff !important;
                                                        padding: 2px 4px !important;
                                                    }
                                                </style>

                                                <div class="mb-3 mt-4" id="editProductItemsContainer">
                                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                                        <label class="form-label fw-bold text-dark fs-12 mb-0">
                                                            <i class="feather-package me-1 text-primary"></i>{{ __('crm.product') }} & Quantity
                                                        </label>
                                                        <button type="button" class="btn btn-xs btn-outline-primary fw-semibold px-2 py-1 fs-11" id="editAddProductRowBtn" style="border-radius: 6px;">
                                                            <i class="feather-plus me-1"></i>Add Product
                                                        </button>
                                                    </div>
                                                    
                                                    <div class="border rounded-3 bg-white p-2 shadow-sm" style="max-height: 270px; overflow-x: hidden; overflow-y: auto;">
                                                        <table class="table table-sm table-borderless align-middle mb-0" id="editProductItemsTable">
                                                            <thead>
                                                                <tr class="border-bottom text-muted fs-11" style="background-color: #f8fafc;">
                                                                    <th style="width: 58%; font-weight: 600;" class="py-1 ps-2">Product</th>
                                                                    <th style="width: 28%; font-weight: 600;" class="py-1 text-center">Qty</th>
                                                                    <th style="width: 14%; font-weight: 600;" class="py-1 text-center"></th>
                                                                </tr>
                                                            </thead>
                                                            <tbody id="editProductItemsBody">
                                                                @php
                                                                    $savedItems = old('items', $lead->product_items ?? []);
                                                                    if (empty($savedItems) && !empty($lead->product_ids)) {
                                                                        foreach ($lead->product_ids as $pid) {
                                                                            $savedItems[] = ['product_id' => $pid, 'quantity' => 1];
                                                                        }
                                                                    }
                                                                    if (empty($savedItems)) {
                                                                        $savedItems = [['product_id' => '', 'quantity' => 1]];
                                                                    }
                                                                    $finished = $products->filter(fn($p) => $p->type === 'finished_good');
                                                                    $semiFinished = $products->filter(fn($p) => $p->type === 'semi_finished');
                                                                    $services = $products->filter(fn($p) => $p->item_type === 'Service' || $p->type === 'service');
                                                                    $others = $products->filter(fn($p) => !in_array($p->type, ['finished_good', 'semi_finished', 'service']) && $p->item_type !== 'Service');
                                                                @endphp

                                                                @foreach($savedItems as $idx => $item)
                                                                    <tr class="lead-item-row border-bottom">
                                                                        <td class="py-1 ps-1 pe-1 align-top">
                                                                            <select name="items[{{ $idx }}][product_id]" class="form-select form-select-sm odoo-select2 product-row-select" searchable="true" data-master="product">
                                                                                <option value="">Select Product...</option>
                                                                                <option value="__ADD_NEW__" class="fw-bold text-primary" data-master="product">+ {{ __('crm.add_new_product') }}</option>
                                                                                
                                                                                @if($finished->count())
                                                                                    <optgroup label="📦 Finished Goods">
                                                                                        @foreach($finished as $p)
                                                                                            <option value="{{ $p->id }}" @selected(($item['product_id'] ?? '') == $p->id)>
                                                                                                {{ $p->name }} @if($p->sku) ({{ $p->sku }}) @endif
                                                                                            </option>
                                                                                        @endforeach
                                                                                    </optgroup>
                                                                                @endif

                                                                                @if($semiFinished->count())
                                                                                    <optgroup label="⚙️ Semi-Finished Goods">
                                                                                        @foreach($semiFinished as $p)
                                                                                            <option value="{{ $p->id }}" @selected(($item['product_id'] ?? '') == $p->id)>
                                                                                                {{ $p->name }} @if($p->sku) ({{ $p->sku }}) @endif
                                                                                            </option>
                                                                                        @endforeach
                                                                                    </optgroup>
                                                                                @endif

                                                                                @if($services->count())
                                                                                    <optgroup label="🛠️ Services">
                                                                                        @foreach($services as $p)
                                                                                            <option value="{{ $p->id }}" @selected(($item['product_id'] ?? '') == $p->id)>
                                                                                                {{ $p->name }} @if($p->sku) ({{ $p->sku }}) @endif
                                                                                            </option>
                                                                                        @endforeach
                                                                                    </optgroup>
                                                                                @endif

                                                                                @if($others->count())
                                                                                    <optgroup label="🧱 Raw Materials & Components">
                                                                                        @foreach($others as $p)
                                                                                            <option value="{{ $p->id }}" @selected(($item['product_id'] ?? '') == $p->id)>
                                                                                                {{ $p->name }} @if($p->sku) ({{ $p->sku }}) @endif
                                                                                            </option>
                                                                                        @endforeach
                                                                                    </optgroup>
                                                                                @endif
                                                                            </select>
                                                                        </td>
                                                                        <td class="py-1 px-1 align-top">
                                                                            <input type="number" name="items[{{ $idx }}][quantity]" class="form-control form-control-sm text-center qty-row-input @error('items.'.$idx.'.quantity') is-invalid @enderror" value="{{ $item['quantity'] ?? 1 }}" min="1" step="1">
                                                                            @error('items.'.$idx.'.quantity')
                                                                                <div class="text-danger fs-11 mt-1 fw-semibold text-center qty-error-msg">{{ $message }}</div>
                                                                            @enderror
                                                                        </td>
                                                                        <td class="py-1 text-center align-top pt-2">
                                                                            <button type="button" class="btn btn-link text-danger p-0 opacity-75 remove-product-row-btn" title="Remove Product">
                                                                                <i class="feather-trash-2 fs-13"></i>
                                                                            </button>
                                                                        </td>
                                                                    </tr>
                                                                @endforeach
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>

                                                <template id="editProductRowSelectTemplate">
                                                    <select class="form-select form-select-sm product-row-select" searchable="true" data-master="product">
                                                        <option value="">Select Product...</option>
                                                        <option value="__ADD_NEW__" class="fw-bold text-primary" data-master="product">+ {{ __('crm.add_new_product') }}</option>
                                                        
                                                        @if($finished->count())
                                                            <optgroup label="📦 Finished Goods">
                                                                @foreach($finished as $p)
                                                                    <option value="{{ $p->id }}">{{ $p->name }} @if($p->sku) ({{ $p->sku }}) @endif</option>
                                                                @endforeach
                                                            </optgroup>
                                                        @endif

                                                        @if($semiFinished->count())
                                                            <optgroup label="⚙️ Semi-Finished Goods">
                                                                @foreach($semiFinished as $p)
                                                                    <option value="{{ $p->id }}">{{ $p->name }} @if($p->sku) ({{ $p->sku }}) @endif</option>
                                                                @endforeach
                                                            </optgroup>
                                                        @endif

                                                        @if($services->count())
                                                            <optgroup label="🛠️ Services">
                                                                @foreach($services as $p)
                                                                    <option value="{{ $p->id }}">{{ $p->name }} @if($p->sku) ({{ $p->sku }}) @endif</option>
                                                                @endforeach
                                                            </optgroup>
                                                        @endif

                                                        @if($others->count())
                                                            <optgroup label="🧱 Raw Materials & Components">
                                                                @foreach($others as $p)
                                                                    <option value="{{ $p->id }}">{{ $p->name }} @if($p->sku) ({{ $p->sku }}) @endif</option>
                                                                @endforeach
                                                            </optgroup>
                                                        @endif
                                                    </select>
                                                </template>

                                                <x-ui.odoo-form-ui type="input" :label="__('crm.expected_revenue_label')" name="expected_amount" inputType="number" :value="old('expected_amount', $lead->expected_amount)" min="0" step="0.01" :placeholder="__('crm.expected_revenue_label')" :errorText="$errors->first('expected_amount')" />

                                                <x-ui.odoo-form-ui type="input" :label="__('crm.expected_sale_date')" name="expected_sale_date" inputType="date" :value="old('expected_sale_date', $lead->expected_sale_date ? $lead->expected_sale_date->format('Y-m-d') : '')" :errorText="$errors->first('expected_sale_date')" />
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        @else
                            <!-- ==================== DEFAULT VIEW: ZOHO CRM FIELD CONTAINER ==================== -->
                            <!-- 2. Detailed Fields Section -->
                            <div id="detailedFieldsContainer" style="transition: all 0.3s ease;">
                                <!-- Lead Information Card -->
                                <div class="card border shadow-sm mb-3" style="border-radius: 4px; border-color: #e2e8f0 !important; background-color: #ffffff;" id="sectionLeadInfo">
                                    <div class="card-body p-3">
                                        <div class="d-flex justify-content-between align-items-center pb-2 border-bottom mb-3">
                                            <h5 class="zoho-section-title fs-13 text-dark fw-bold mb-0" style="font-family: 'Inter', sans-serif; border-bottom: none;">{{ __('crm.lead_information') }}</h5>
                                        </div>
                                        <div class="row g-0">
                                            <div class="col-md-6 pe-md-4">
                                                 <div class="zoho-field-row">
                                                     <div class="zoho-field-label">{{ __('crm.company_name') }}</div>
                                                     <div class="zoho-field-value text-dark fw-bold">{{ $lead->company_name ?: '—' }}</div>
                                                 </div>

                                                 <div class="zoho-field-row">
                                                     <div class="zoho-field-label">GSTIN / Tax No.</div>
                                                     <div class="zoho-field-value text-dark fw-semibold">{{ $lead->gstin ?: '—' }}</div>
                                                 </div>

                                                 <div class="zoho-field-row">
                                                     <div class="zoho-field-label">Company Email</div>
                                                     <div class="zoho-field-value">
                                                         @if($lead->company_email)
                                                             <a href="mailto:{{ $lead->company_email }}" class="text-primary hover-underline">{{ $lead->company_email }}</a>
                                                         @else
                                                             —
                                                         @endif
                                                     </div>
                                                 </div>

                                                 <div class="zoho-field-row">
                                                     <div class="zoho-field-label">Company Phone</div>
                                                     <div class="zoho-field-value text-dark">{{ $lead->company_phone ?: '—' }}</div>
                                                 </div>

                                                 <div class="zoho-field-row">
                                                     <div class="zoho-field-label">{{ __('crm.contact_person') }}</div>
                                                     <div class="zoho-field-value text-dark fw-semibold">{{ $lead->contact_person ?: '—' }}</div>
                                                 </div>

                                                 <div class="zoho-field-row">
                                                     <div class="zoho-field-label">Contact Email</div>
                                                     <div class="zoho-field-value">
                                                         @if($lead->email)
                                                             <a href="mailto:{{ $lead->email }}" class="text-primary hover-underline">{{ $lead->email }}</a>
                                                         @else
                                                             —
                                                         @endif
                                                     </div>
                                                 </div>

                                                 <div class="zoho-field-row">
                                                     <div class="zoho-field-label">Contact Phone</div>
                                                     <div class="zoho-field-value text-dark">{{ $lead->phone ?: '—' }}</div>
                                                 </div>

                                                 <div class="zoho-field-row">
                                                     <div class="zoho-field-label">{{ __('crm.lead_owner') }}</div>
                                                     <div class="zoho-field-value text-dark fw-bold">{{ $lead->owner?->name ?: 'Unassigned' }}</div>
                                                 </div>

                                                 @php
                                                     $allAddlContacts = $lead->additional_contacts ?: [];
                                                 @endphp

                                                 @if(!empty($allAddlContacts))
                                                     <div class="mt-4 mb-3 p-3 border rounded-3" style="background-color: #f8fafc; border-color: #e2e8f0 !important;">
                                                         <div class="d-flex align-items-center justify-content-between mb-2">
                                                             <span class="fs-11 fw-bold text-uppercase text-primary letter-spacing-1">
                                                                 <i class="feather-users me-1 text-primary"></i> Additional Contacts ({{ count($allAddlContacts) }})
                                                             </span>
                                                         </div>
                                                         <div class="d-flex flex-column gap-2">
                                                             @foreach($allAddlContacts as $ac)
                                                                 @if(!empty($ac['name']) || !empty($ac['phone']) || !empty($ac['email']))
                                                                     <div class="p-2 border rounded-2 bg-white shadow-2xs">
                                                                         <div class="d-flex align-items-center justify-content-between border-bottom pb-1 mb-1">
                                                                             <span class="fw-bold text-dark fs-12">
                                                                                 <i class="feather-user me-1 text-primary fs-11"></i>{{ $ac['name'] ?: 'N/A' }}
                                                                             </span>
                                                                             <span class="badge bg-soft-primary text-primary fs-10 fw-semibold">Contact #{{ $loop->iteration }}</span>
                                                                         </div>
                                                                         <div class="d-flex flex-wrap gap-3 fs-11 text-muted">
                                                                             @if(!empty($ac['phone']))
                                                                                 <span><i class="feather-phone me-1 text-success fs-10"></i><strong class="text-dark">{{ $ac['phone'] }}</strong></span>
                                                                             @endif
                                                                             @if(!empty($ac['email']))
                                                                                 <span><i class="feather-mail me-1 text-info fs-10"></i><a href="mailto:{{ $ac['email'] }}" class="text-primary hover-underline">{{ $ac['email'] }}</a></span>
                                                                             @endif
                                                                         </div>
                                                                     </div>
                                                                 @endif
                                                             @endforeach
                                                         </div>
                                                     </div>
                                                 @endif
                                             </div>
                                             <div class="col-md-6 ps-md-4">
                                                 <div class="zoho-field-row">
                                                     <div class="zoho-field-label">{{ __('crm.lead_status') }}</div>
                                                     <div class="zoho-field-value text-primary fw-bold" style="width: 100%; max-width: 250px;">
                                                         @if($lead->status === 'Won' || $lead->is_customer)
                                                             <span class="badge bg-soft-success text-success px-2.5 py-1 fs-12 fw-bold"><i class="feather-check-circle me-1"></i>Won</span>
                                                         @else
                                                             <form action="{{ route('crm.leads.updateStatus', $lead->id) }}" method="POST" class="d-inline m-0 p-0 w-100">
                                                                 @csrf
                                                                 @method('PATCH')
                                                                 <select class="form-select odoo-select2 status-select" name="status" onchange="this.form.submit()" style="border-radius:0;">
                                                                     <option value="New" @selected($lead->status === 'New' || !$lead->status)>{{ __('crm.statuses.New') }}</option>
                                                                     <option value="Qualified" @selected($lead->status === 'Qualified')>{{ __('crm.statuses.Qualified') }}</option>
                                                                     <option value="Won" @selected($lead->status === 'Won')>{{ __('crm.statuses.Won') }}</option>
                                                                     <option value="Lost" @selected($lead->status === 'Lost')>{{ __('crm.statuses.Lost') }}</option>
                                                                 </select>
                                                             </form>
                                                         @endif
                                                     </div>
                                                 </div>
                                                 <div class="zoho-field-row">
                                                     <div class="zoho-field-label">{{ __('crm.expected_revenue_label') }}</div>
                                                     <div class="zoho-field-value text-dark fw-bold">₹{{ $lead->expected_amount ? number_format($lead->expected_amount, 2) : '0.00' }}</div>
                                                 </div>
                                                 <div class="zoho-field-row">
                                                     <div class="zoho-field-label">{{ __('crm.expected_sale_date') }}</div>
                                                     <div class="zoho-field-value text-dark">{{ $lead->expected_sale_date ? $lead->expected_sale_date->format('d/m/Y') : '—' }}</div>
                                                 </div>
                                                 <div class="zoho-field-row">
                                                     <div class="zoho-field-label">{{ __('crm.priority') }}</div>
                                                     <div class="zoho-field-value">
                                                         @php
                                                             $prioBadge = 'bg-secondary';
                                                             if($lead->priority === 'High') $prioBadge = 'bg-danger';
                                                             elseif($lead->priority === 'Medium') $prioBadge = 'bg-warning text-dark';
                                                             elseif($lead->priority === 'Low') $prioBadge = 'bg-info text-white';
                                                         @endphp
                                                         <span class="badge {{ $prioBadge }} px-2 py-0.5" style="font-size: 11px;">{{ ($lead->priority && $lead->priority !== 'Select an Option') ? __('crm.priorities.' . $lead->priority) : '—' }}</span>
                                                     </div>
                                                 </div>
                                                 <div class="zoho-field-row">
                                                     <div class="zoho-field-label">{{ __('crm.industry_type') }}</div>
                                                     <div class="zoho-field-value text-dark">{{ $lead->industry_type ?: '—' }}</div>
                                                 </div>
                                                 <div class="zoho-field-row">
                                                     <div class="zoho-field-label">{{ __('crm.segment') }}</div>
                                                     <div class="zoho-field-value text-dark">{{ ($lead->segment && $lead->segment !== 'Select an Option') ? __('crm.segments.' . $lead->segment) : '—' }}</div>
                                                 </div>
                                                 <div class="zoho-field-row">
                                                     <div class="zoho-field-label">{{ __('crm.lead_source') }}</div>
                                                     <div class="zoho-field-value">
                                                         <span class="badge bg-light text-dark border px-2 py-0.5" style="font-size: 11px;">{{ ($lead->source && $lead->source !== 'Select an Option') ? __('crm.sources.' . $lead->source) : '—' }}</span>
                                                     </div>
                                                 </div>
                                                 <div class="zoho-field-row align-items-start">
                                                     <div class="zoho-field-label mt-1">{{ __('crm.product_interest') }}</div>
                                                     <div class="zoho-field-value text-dark">
                                                         @php
                                                             $pItems = $lead->product_items ?: [];
                                                         @endphp
                                                         @if(!empty($pItems))
                                                             <div class="d-flex flex-column gap-1">
                                                                 @foreach($pItems as $pi)
                                                                     @php
                                                                         $prod = $products->firstWhere('id', $pi['product_id']);
                                                                     @endphp
                                                                     @if($prod)
                                                                         <div class="d-flex align-items-center justify-content-between bg-soft-primary border border-primary-subtle rounded px-2 py-1" style="font-size: 11px; max-width: 280px;">
                                                                             <span class="text-primary fw-medium text-truncate me-2" title="{{ $prod->name }}">{{ $prod->name }}</span>
                                                                             <span class="badge bg-primary text-white font-mono px-1.5 py-0.5">x{{ $pi['quantity'] ?? 1 }}</span>
                                                                         </div>
                                                                     @endif
                                                                 @endforeach
                                                             </div>
                                                         @elseif($lead->products->count())
                                                             <div class="d-flex flex-column gap-1">
                                                                 @foreach($lead->products as $p)
                                                                     <div class="bg-soft-primary border border-primary-subtle rounded px-2 py-1 text-primary fw-medium" style="font-size: 11px; max-width: 280px;">
                                                                         {{ $p->name }}
                                                                     </div>
                                                                 @endforeach
                                                             </div>
                                                         @else
                                                             —
                                                         @endif
                                                     </div>
                                                 </div>
                                             </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Address Details Card -->
                                <div class="card border shadow-sm mb-3" style="border-radius: 4px; border-color: #e2e8f0 !important; background-color: #ffffff;" id="sectionAddressInfo">
                                    <div class="card-body p-3">
                                        <h5 class="zoho-section-title fs-13 text-dark fw-bold pb-2 border-bottom mb-3" style="font-family: 'Inter', sans-serif;">{{ __('crm.address_details') }}</h5>
                                        <div class="row g-0">
                                            <div class="col-md-6 pe-md-4">
                                                <div class="zoho-field-row">
                                                    <div class="zoho-field-label">{{ __('crm.street') }}</div>
                                                    <div class="zoho-field-value text-wrap text-dark" style="max-width: 350px;">{{ $lead->address ?: __('crm.no_street_address') }}</div>
                                                </div>
                                                <div class="zoho-field-row">
                                                    <div class="zoho-field-label">{{ __('crm.state') }}</div>
                                                    <div class="zoho-field-value text-dark">{{ $lead->state ?: '—' }}</div>
                                                </div>
                                                <div class="zoho-field-row">
                                                    <div class="zoho-field-label">{{ __('crm.country') }}</div>
                                                    <div class="zoho-field-value text-dark">{{ $lead->country ?: '—' }}</div>
                                                </div>
                                            </div>
                                            <div class="col-md-6 ps-md-4">
                                                <div class="zoho-field-row">
                                                    <div class="zoho-field-label">{{ __('crm.city') }}</div>
                                                    <div class="zoho-field-value text-dark">{{ $lead->city ?: '—' }}</div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Requirements Details Card -->
                                <div class="card border shadow-sm mb-3" style="border-radius: 4px; border-color: #e2e8f0 !important; background-color: #ffffff;" id="sectionRequirements">
                                    <div class="card-body p-3">
                                        <h5 class="zoho-section-title fs-13 text-dark fw-bold pb-2 border-bottom mb-3" style="font-family: 'Inter', sans-serif;">{{ __('crm.requirements_details') }}</h5>
                                        @if ($lead->requirement)
                                            <div class="text-dark fs-13 bg-light-50 p-3 border rounded" style="white-space: pre-wrap; line-height: 1.6; font-family: 'Inter', sans-serif;">{{ $lead->requirement }}</div>
                                        @else
                                            <p class="text-muted fs-12 italic mb-0">{{ __('crm.no_requirements_specified') }}</p>
                                        @endif
                                    </div>
                                </div>

                               
                            </div>
                            
                            <!-- Static Notes Display Card -->
                            <div class="card border shadow-sm mb-3" style="border-radius: 4px; border-color: #e2e8f0 !important; background-color: #ffffff; font-family: 'Inter', sans-serif;" id="sectionNotes">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
                                        <h6 class="fw-bold text-dark mb-0 fs-13"><i class="feather-file-text me-2 text-primary"></i>{{ __('crm.notes_logs') }}</h6>
                                        <button class="btn btn-xs btn-primary fw-bold" data-bs-toggle="modal" data-bs-target="#modalLogNote" style="background-color: #1e40af; border-color: #1e40af;"><i class="feather-plus me-1"></i> {{ __('crm.add_note') }}</button>
                                    </div>
                                    @if($lead->followups->isEmpty())
                                        <p class="text-muted fs-12 mb-0 italic">{{ __('crm.no_notes_created') }}</p>
                                    @else
                                        <div class="activity-feed-compact fs-12 text-dark">
                                            @foreach($lead->followups->take(3) as $followup)
                                                <div class="p-2 border-bottom bg-white rounded mb-2">
                                                    <div class="d-flex justify-content-between text-muted fs-10 mb-1">
                                                        <span class="fw-semibold text-uppercase text-primary">{{ __('crm.interaction_types.' . $followup->type) ?? $followup->type }}</span>
                                                        <span>{{ $followup->followup_date->diffForHumans() }}</span>
                                                    </div>
                                                    <p class="mb-0 fw-medium text-dark">{{ $followup->notes }}</p>
                                                </div>
                                            @endforeach
                                            @if($lead->followups->count() > 3)
                                                <a href="javascript:void(0)" onclick="$('#timeline-tab').tab('show')" class="text-primary fs-11 fw-semibold d-inline-block mt-1">{{ __('crm.view_all_notes', ['count' => $lead->followups->count()]) }}</a>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            </div>
                             <!-- Lead Documents Card -->
                                <div class="card border shadow-sm mb-3" style="border-radius: 4px; border-color: #e2e8f0 !important; background-color: #ffffff;" id="sectionDocuments">
                                    <div class="card-body p-3">
                                        <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
                                            <h6 class="fw-bold text-dark mb-0 fs-13"><i class="feather-folder me-2 text-primary"></i>{{ __('crm.lead_documents') }}</h6>
                                            <form action="{{ route('crm.leads.documents.upload', $lead->id) }}" method="POST" enctype="multipart/form-data" class="m-0 p-0" id="leadDocUploadForm">
                                                @csrf
                                                <button type="button" class="btn btn-xs btn-primary fw-bold" onclick="document.getElementById('leadDocInput').click();" style="background-color: #1e40af; border-color: #1e40af;"><i class="feather-upload me-1"></i> {{ __('crm.upload') }}</button>
                                                <input type="file" name="documents[]" id="leadDocInput" onchange="if (this.files &amp;&amp; this.files.length > 0) { document.getElementById('leadDocUploadForm').submit(); }" multiple style="display: none;">
                                            </form>
                                        </div>

                                        @if($lead->leadDocuments->isEmpty())
                                            <div class="text-center py-4 border border-dashed rounded bg-light-subtle">
                                                <i class="feather-file-text fs-24 text-muted mb-1 d-block opacity-50"></i>
                                                <div class="text-muted fs-12">{{ __('crm.no_documents_uploaded') }}</div>
                                            </div>
                                        @else
                                            <div class="row g-3">
                                                @foreach($lead->leadDocuments as $document)
                                                    @php
                                                        $ext = strtolower(pathinfo($document->file_name, PATHINFO_EXTENSION) ?: $document->file_type);
                                                        $fileTypeCategory = 'other';

                                                        if (in_array($ext, ['xlsx', 'xls', 'csv'])) {
                                                            $fileTypeCategory = 'excel';
                                                        } elseif ($ext === 'pdf') {
                                                            $fileTypeCategory = 'pdf';
                                                        } elseif (in_array($ext, ['doc', 'docx'])) {
                                                            $fileTypeCategory = 'word';
                                                        } elseif (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'])) {
                                                            $fileTypeCategory = 'image';
                                                        } elseif (in_array($ext, ['zip', 'rar', '7z', 'tar', 'gz'])) {
                                                            $fileTypeCategory = 'archive';
                                                        }
                                                    @endphp
                                                    <div class="col-md-6">
                                                        <div class="p-3 border rounded-3 d-flex align-items-center justify-content-between h-100 shadow-2xs" style="background-color: #f8fafc; border-color: #e2e8f0 !important;">
                                                            <div class="d-flex align-items-center overflow-hidden me-2" style="gap: 12px;">
                                                                <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px;">
                                                                    @if($fileTypeCategory === 'excel')
                                                                        <!-- MS Excel Logo -->
                                                                        <svg xmlns="http://www.w3.org/2000/svg" width="38" height="38" viewBox="0 0 36 36" fill="none">
                                                                            <rect width="36" height="36" rx="6" fill="#107C41"/>
                                                                            <path d="M10.5 9L16.5 18L10.5 27H14.25L18 21.375L21.75 27H25.5L19.5 18L25.5 9H21.75L18 14.625L14.25 9H10.5Z" fill="white"/>
                                                                        </svg>
                                                                    @elseif($fileTypeCategory === 'word')
                                                                        <!-- MS Word Logo -->
                                                                        <svg xmlns="http://www.w3.org/2000/svg" width="38" height="38" viewBox="0 0 36 36" fill="none">
                                                                            <rect width="36" height="36" rx="6" fill="#185ABD"/>
                                                                            <path d="M9 9L12.75 27H15.75L18 17.25L20.25 27H23.25L27 9H23.7L21.45 20.7L19.05 9H16.95L14.55 20.7L12.3 9H9Z" fill="white"/>
                                                                        </svg>
                                                                    @elseif($fileTypeCategory === 'pdf')
                                                                        <!-- PDF Logo -->
                                                                        <svg xmlns="http://www.w3.org/2000/svg" width="38" height="38" viewBox="0 0 36 36" fill="none">
                                                                            <rect width="36" height="36" rx="6" fill="#E11D48"/>
                                                                            <text x="50%" y="58%" dominant-baseline="middle" text-anchor="middle" fill="white" font-size="12" font-weight="900" font-family="'Inter', sans-serif" letter-spacing="0.5">PDF</text>
                                                                        </svg>
                                                                    @elseif($fileTypeCategory === 'image')
                                                                        <!-- Image Logo -->
                                                                        <svg xmlns="http://www.w3.org/2000/svg" width="38" height="38" viewBox="0 0 36 36" fill="none">
                                                                            <rect width="36" height="36" rx="6" fill="#0891B2"/>
                                                                            <circle cx="13" cy="13" r="3" fill="white"/>
                                                                            <path d="M7.5 27L14.25 18.75L18.75 24.75L24 16.5L28.5 27H7.5Z" fill="white"/>
                                                                        </svg>
                                                                    @elseif($fileTypeCategory === 'archive')
                                                                        <!-- Zip Logo -->
                                                                        <svg xmlns="http://www.w3.org/2000/svg" width="38" height="38" viewBox="0 0 36 36" fill="none">
                                                                            <rect width="36" height="36" rx="6" fill="#D97706"/>
                                                                            <path d="M18 6V21M18 21L12 15M18 21L24 15M9 27H27" stroke="white" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                                                                        </svg>
                                                                    @else
                                                                        <!-- Default Document Logo -->
                                                                        <svg xmlns="http://www.w3.org/2000/svg" width="38" height="38" viewBox="0 0 36 36" fill="none">
                                                                            <rect width="36" height="36" rx="6" fill="#475569"/>
                                                                            <path d="M10.5 9H25.5M10.5 15H25.5M10.5 21H19.5M10.5 27H16.5" stroke="white" stroke-width="2.5" stroke-linecap="round"/>
                                                                        </svg>
                                                                    @endif
                                                                </div>
                                                                <div class="overflow-hidden">
                                                                    <a href="{{ route('crm.leads.documents.view', $document->id) }}" target="_blank" class="fw-bold text-dark text-decoration-none hover-primary fs-12 text-truncate d-block mb-1" title="Click to view file: {{ $document->file_name }}">
                                                                        {{ $document->file_name }}
                                                                    </a>
                                                                    <div class="text-muted fs-11 d-flex align-items-center gap-1.5 flex-wrap">
                                                                        <span class="badge bg-white text-secondary border px-1.5 py-0.5 text-uppercase fw-semibold" style="font-size: 9px; border-color: #cbd5e1 !important;">{{ strtoupper($ext) }}</span>
                                                                        <span>{{ round($document->size / 1024, 2) }} KB</span>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="d-flex align-items-center gap-2 flex-shrink-0">
                                                                <a href="{{ route('crm.leads.documents.download', $document->id) }}" class="btn btn-xs btn-soft-success rounded-circle p-0 d-inline-flex align-items-center justify-content-center border" style="width: 30px; height: 30px; border-color: #bbf7d0 !important;" title="Download Document">
                                                                    <i class="feather-download fs-13 text-success"></i>
                                                                </a>
                                                                <form action="{{ route('crm.leads.documents.delete', $document->id) }}" method="POST" class="m-0 p-0" id="deleteDocForm_{{ $document->id }}">
                                                                    @csrf
                                                                    @method('DELETE')
                                                                    <button type="button" class="btn btn-xs btn-soft-danger rounded-circle p-0 d-inline-flex align-items-center justify-content-center border" style="width: 30px; height: 30px; border-color: #fecdd3 !important;" title="Delete Document" onclick="confirmAction({ title: 'Delete Document', message: '{{ __('crm.confirm_delete_document') }}', variant: 'danger', confirmText: 'Delete' }, function() { document.getElementById('deleteDocForm_{{ $document->id }}').submit(); })">
                                                                        <i class="feather-trash-2 fs-13 text-danger"></i>
                                                                    </button>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                @endforeach
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
                                                {{ __('crm.history') }}
                                            </button>
                                        </li>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link py-2 px-3 border-0 bg-transparent" id="subtab-interactions-tab" data-bs-toggle="tab" data-bs-target="#subtab-interactions" type="button" role="tab" aria-controls="subtab-interactions" aria-selected="false">
                                                {{ __('crm.interactions') }}
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
                                                <h5 class="fw-bold text-dark fs-14 mb-0">{{ __('crm.timeline_history') }}</h5>
                                                <button class="btn btn-xs btn-outline-secondary border-0 p-1" :title="__('crm.filter_history')"><i class="feather-filter fs-12"></i></button>
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
                                                    {{ __('crm.no_history_events') }}
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
                                        <div class="d-flex align-items-center justify-content-between mb-3 mt-1 flex-wrap gap-2">
                                            <h5 class="fw-bold text-dark fs-14 mb-0">{{ __('crm.interactions_scheduled_activities') }}</h5>
                                            <x-ui.button variant="primary" size="sm" icon="feather-calendar" data-bs-toggle="modal" data-bs-target="#modalScheduleActivity">
                                                {{ __('crm.schedule_activity') }}
                                            </x-ui.button>
                                        </div>

                                        @php
                                            $groupedFollowups = $lead->followups->reject(function($item) {
                                                return $item->status === 'Rescheduled' && $item->rescheduledTo->isNotEmpty();
                                            })->groupBy(function($item) {
                                                return $item->followup_date->format('d/m/Y');
                                            });
                                        @endphp

                                        @if($groupedFollowups->isEmpty())
                                            <div class="text-center py-5 text-muted border border-dashed rounded-3 bg-light fs-12" style="border-color:#cbd5e1!important;">
                                                <i class="feather-calendar fs-28 d-block mb-2 opacity-40"></i>
                                                <span class="fw-semibold">No activities scheduled yet.</span><br>
                                                <span class="fs-11">Click &ldquo;Schedule Activity&rdquo; to add one.</span>
                                            </div>
                                        @else
                                            @foreach($groupedFollowups as $date => $items)
                                                @php
                                                    $hasNotConnected = $items->contains(fn($i) => $i->status === 'Not Connected');
                                                    $hasCancelled    = $items->contains(fn($i) => $i->status === 'Cancelled');
                                                    $hasCompleted    = $items->contains(fn($i) => $i->status === 'Completed');
                                                    $hasRescheduled  = $items->contains(fn($i) => $i->status === 'Rescheduled');

                                                    $dateBadgeBg     = '#eff6ff';
                                                    $dateBadgeColor  = '#1d4ed8';
                                                    $dateBadgeBorder = '#93c5fd';

                                                    if ($hasNotConnected) {
                                                        $dateBadgeBg     = '#fff7ed';
                                                        $dateBadgeColor  = '#c2410c';
                                                        $dateBadgeBorder = '#fdba74';
                                                    } elseif ($hasCancelled) {
                                                        $dateBadgeBg     = '#fef2f2';
                                                        $dateBadgeColor  = '#b91c1c';
                                                        $dateBadgeBorder = '#fca5a5';
                                                    } elseif ($hasCompleted) {
                                                        $dateBadgeBg     = '#f0fdf4';
                                                        $dateBadgeColor  = '#15803d';
                                                        $dateBadgeBorder = '#86efac';
                                                    } elseif ($hasRescheduled) {
                                                        $dateBadgeBg     = '#faf5ff';
                                                        $dateBadgeColor  = '#6b21a8';
                                                        $dateBadgeBorder = '#d8b4fe';
                                                    }
                                                @endphp

                                                <!-- Date Header -->
                                                <div class="activity-date-group mb-3">
                                                    <div class="activity-date-badge mb-2" style="background: {{ $dateBadgeBg }}; color: {{ $dateBadgeColor }}; border: 1px solid {{ $dateBadgeBorder }}; font-weight: 700;">
                                                        <i class="feather-calendar fs-10 me-1"></i>{{ $date }}
                                                    </div>

                                                    @foreach($items as $item)
                                                        @php
                                                            $actIcon = 'feather-phone-call';
                                                            $actIconBg = 'bg-soft-primary';
                                                            $actIconColor = 'text-primary';
                                                            if($item->type === 'Email')   { $actIcon = 'feather-mail';    $actIconBg = 'bg-soft-warning'; $actIconColor = 'text-warning'; }
                                                            elseif($item->type === 'Meeting') { $actIcon = 'feather-users';  $actIconBg = 'bg-soft-purple';  $actIconColor = 'text-purple'; }
                                                            elseif($item->type === 'Demo')    { $actIcon = 'feather-monitor'; $actIconBg = 'bg-soft-danger';  $actIconColor = 'text-danger'; }

                                                            $statusBadgeClass = 'bg-primary text-white';
                                                            $statusLabel = 'Pending';
                                                            if($item->status === 'Completed') { $statusBadgeClass = 'bg-success text-white'; $statusLabel = 'Connected'; }
                                                            elseif($item->status === 'Not Connected') { $statusBadgeClass = 'bg-warning text-white'; $statusLabel = 'Not Connected'; }
                                                            elseif($item->status === 'Cancelled') { $statusBadgeClass = 'bg-danger text-white'; $statusLabel = 'Cancelled'; }
                                                            elseif($item->status === 'Rescheduled') { $statusBadgeClass = 'bg-purple text-white'; $statusLabel = 'Rescheduled'; }
                                                        @endphp

                                                        <div class="activity-card mb-2">
                                                            <div class="activity-card-inner">

                                                                <!-- Top row: Icon + Type + Time + Status -->
                                                                <div class="d-flex align-items-center gap-2 mb-2 flex-wrap">
                                                                    <div class="activity-type-icon {{ $actIconBg }} {{ $actIconColor }} flex-shrink-0">
                                                                        <i class="{{ $actIcon }}"></i>
                                                                    </div>
                                                                    <span class="fw-bold text-dark fs-13">{{ __('crm.activity_types.' . $item->type) ?? $item->type }}</span>
                                                                    <span class="activity-time-chip"><i class="feather-clock fs-9 me-1"></i>{{ $item->followup_date->format('h:i A') }}</span>
                                                                    <span class="badge rounded-pill {{ $statusBadgeClass }} px-2.5 py-1 fs-10 fw-semibold" @if($item->status !== 'Pending') title="Status updated on {{ $item->updated_at->format('d/m/Y h:i A') }}" @endif>{{ $statusLabel }}</span>

                                                                    @php
                                                                        $lastRescheduledDate = $item->rescheduledFrom?->followup_date;
                                                                    @endphp
                                                                    @if($lastRescheduledDate)
                                                                        <span class="badge bg-soft-info text-info border border-info border-opacity-25 px-2 py-1 fs-10 fw-semibold ms-auto" title="Rescheduled from {{ $lastRescheduledDate->format('d/m/Y h:i A') }}">
                                                                            <i class="feather-refresh-cw me-1 fs-9"></i>Rescheduled from {{ $lastRescheduledDate->format('d/m/Y h:i A') }}
                                                                        </span>
                                                                    @endif
                                                                </div>

                                                                <!-- Notes / Messages (Original + Reschedule + Latest) -->
                                                                @php
                                                                    $prevItem = $item->rescheduledFrom;
                                                                    $prevNotes = [];
                                                                    while($prevItem) {
                                                                        if ($prevItem->notes && !in_array($prevItem->notes, array_column($prevNotes, 'notes')) && $prevItem->notes !== $item->notes) {
                                                                            $prevNotes[] = [
                                                                                'date' => $prevItem->followup_date,
                                                                                'notes' => $prevItem->notes,
                                                                            ];
                                                                        }
                                                                        $prevItem = $prevItem->rescheduledFrom;
                                                                    }
                                                                    $chronologicalPrevNotes = array_reverse($prevNotes);
                                                                @endphp

                                                                @if(!empty($chronologicalPrevNotes))
                                                                    @foreach($chronologicalPrevNotes as $idx => $pNote)
                                                                        @php
                                                                            $isFirstOriginal = ($idx === 0);
                                                                            $labelTitle = $isFirstOriginal ? 'Original Note' : 'Reschedule Note';
                                                                            $iconClass = $isFirstOriginal ? 'feather-file-text' : 'feather-clock';
                                                                        @endphp
                                                                        <div class="activity-notes activity-notes--original opacity-75 mb-1" style="border-left-color: {{ $isFirstOriginal ? '#94a3b8' : '#cbd5e1' }}; background: #f8fafc;">
                                                                            <span class="fs-10 fw-bold text-muted text-uppercase d-block mb-1">
                                                                                <i class="{{ $iconClass }} me-1 fs-9"></i>{{ $labelTitle }} ({{ $pNote['date']->format('d/m/Y') }}):
                                                                            </span>
                                                                            <span class="text-secondary">{{ $pNote['notes'] }}</span>
                                                                        </div>
                                                                    @endforeach
                                                                @endif

                                                                @if($item->notes)
                                                                    <div class="activity-notes">
                                                                        @if(!empty($chronologicalPrevNotes))
                                                                            <span class="fs-10 fw-bold text-primary text-uppercase d-block mb-1">
                                                                                <i class="feather-edit-2 me-1 fs-9"></i>Latest Note:
                                                                            </span>
                                                                        @endif
                                                                        {{ $item->notes }}
                                                                    </div>
                                                                @endif

                                                                <!-- Attribution -->
                                                                <div class="activity-by mt-1 d-flex align-items-center flex-wrap gap-2">
                                                                    <span>
                                                                        <i class="feather-user fs-9 me-1"></i>by {{ $lead->owner?->name ?: 'System' }} &bull; Scheduled: {{ $item->followup_date->format('d M Y, h:i A') }}
                                                                    </span>
                                                                    @if($item->taggedUsers->isNotEmpty())
                                                                        @foreach($item->taggedUsers as $tUser)
                                                                            <span class="badge bg-soft-info text-info fs-10 px-2 py-1 border border-info border-opacity-25" title="Tagged User">
                                                                                <i class="feather-at-sign me-1"></i>Tagged: <strong>{{ $tUser->name }}</strong>
                                                                            </span>
                                                                        @endforeach
                                                                    @endif
                                                                    @if($item->status !== 'Pending')
                                                                        <span class="text-muted ms-auto fs-10">
                                                                            <i class="feather-clock fs-9 me-1 text-primary"></i>Status Updated: <strong class="text-dark">{{ $item->updated_at->format('d M Y, h:i A') }}</strong>
                                                                        </span>
                                                                    @endif
                                                                </div>

                                                                <!-- Action Buttons — horizontal row at bottom, only for Pending -->
                                                                @if($item->status === 'Pending')
                                                                    <div class="activity-footer-actions d-flex gap-2 mt-3 d-print-none">
                                                                        <!-- Connected -->
                                                                        <x-ui.button type="button" variant="soft-success" size="sm" icon="feather-phone-call" data-bs-toggle="modal" data-bs-target="#statusModal_{{ $item->id }}_Completed">Connected</x-ui.button>

                                                                        <!-- Not Connected -->
                                                                        <x-ui.button type="button" variant="soft-warning" size="sm" icon="feather-phone-off" data-bs-toggle="modal" data-bs-target="#statusModal_{{ $item->id }}_NotConnected">Not Connected</x-ui.button>

                                                                        <!-- Cancelled -->
                                                                        <x-ui.button type="button" variant="soft-danger" size="sm" icon="feather-x-circle" data-bs-toggle="modal" data-bs-target="#statusModal_{{ $item->id }}_Cancelled">Cancelled</x-ui.button>

                                                                        <!-- Reschedule -->
                                                                        <x-ui.button type="button" variant="soft-primary" size="sm" icon="feather-refresh-cw" data-bs-toggle="modal" data-bs-target="#rescheduleModal_{{ $item->id }}">Reschedule</x-ui.button>
                                                                    </div>
                                                                @endif
                                                            </div>
                                                        </div>

                                                        @if($item->status === 'Pending')
                                                            @php
                                                                $currentTaggedIds = $item->taggedUsers->pluck('id')->toArray();
                                                            @endphp

                                                            <!-- Status Modal: Connected / Completed -->
                                                            <x-ui.modal
                                                                :id="'statusModal_' . $item->id . '_Completed'"
                                                                title="Update Activity: Mark as Connected"
                                                                size="md"
                                                                :centered="true"
                                                                :formAction="route('crm.followups.update', $item->id)"
                                                                formMethod="PUT"
                                                                submitText="Save &amp; Mark Connected"
                                                                :closeText="__('crm.cancel')"
                                                            >
                                                                <input type="hidden" name="status" value="Completed">
                                                                <x-ui.odoo-form-ui type="select" label="Tag / Assign Persons" name="tagged_user_ids[]" :multiple="true" :searchable="true">
                                                                    @foreach($users as $u)
                                                                        <option value="{{ $u->id }}" @selected(in_array($u->id, $currentTaggedIds))>{{ $u->name }} ({{ $u->email }})</option>
                                                                    @endforeach
                                                                </x-ui.odoo-form-ui>
                                                                <x-ui.odoo-form-ui type="textarea" label="Notes / Discussion Summary" name="notes" rows="3" placeholder="Enter notes or discussion outcome...">{{ $item->notes }}</x-ui.odoo-form-ui>
                                                            </x-ui.modal>

                                                            <!-- Status Modal: Not Connected -->
                                                            <x-ui.modal
                                                                :id="'statusModal_' . $item->id . '_NotConnected'"
                                                                title="Update Activity: Mark as Not Connected"
                                                                size="md"
                                                                :centered="true"
                                                                :formAction="route('crm.followups.update', $item->id)"
                                                                formMethod="PUT"
                                                                submitText="Save Status"
                                                                :closeText="__('crm.cancel')"
                                                            >
                                                                <input type="hidden" name="status" value="Not Connected">
                                                                <x-ui.odoo-form-ui type="select" label="Tag / Assign Persons" name="tagged_user_ids[]" :multiple="true" :searchable="true">
                                                                    @foreach($users as $u)
                                                                        <option value="{{ $u->id }}" @selected(in_array($u->id, $currentTaggedIds))>{{ $u->name }} ({{ $u->email }})</option>
                                                                    @endforeach
                                                                </x-ui.odoo-form-ui>
                                                                <x-ui.odoo-form-ui type="textarea" label="Notes / Reason" name="notes" rows="3" placeholder="Reason / notes...">{{ $item->notes }}</x-ui.odoo-form-ui>
                                                            </x-ui.modal>

                                                            <!-- Status Modal: Cancelled -->
                                                            <x-ui.modal
                                                                :id="'statusModal_' . $item->id . '_Cancelled'"
                                                                title="Update Activity: Cancel Activity"
                                                                size="md"
                                                                :centered="true"
                                                                :formAction="route('crm.followups.update', $item->id)"
                                                                formMethod="PUT"
                                                                submitText="Confirm Cancel"
                                                                :closeText="__('crm.cancel')"
                                                            >
                                                                <input type="hidden" name="status" value="Cancelled">
                                                                <x-ui.odoo-form-ui type="select" label="Tag / Assign Persons" name="tagged_user_ids[]" :multiple="true" :searchable="true">
                                                                    @foreach($users as $u)
                                                                        <option value="{{ $u->id }}" @selected(in_array($u->id, $currentTaggedIds))>{{ $u->name }} ({{ $u->email }})</option>
                                                                    @endforeach
                                                                </x-ui.odoo-form-ui>
                                                                <x-ui.odoo-form-ui type="textarea" label="Cancellation Note" name="notes" rows="3" placeholder="Reason for cancellation...">{{ $item->notes }}</x-ui.odoo-form-ui>
                                                            </x-ui.modal>

                                                            <!-- Reschedule Modal -->
                                                            <x-ui.modal
                                                                :id="'rescheduleModal_' . $item->id"
                                                                title="Reschedule Activity"
                                                                size="md"
                                                                :centered="true"
                                                                :formAction="route('crm.followups.update', $item->id)"
                                                                formMethod="PUT"
                                                                :submitText="'Confirm Reschedule'"
                                                                :closeText="__('crm.cancel')"
                                                            >
                                                                <input type="hidden" name="is_reschedule" value="1">
                                                                <p class="text-muted fs-11 mb-3">
                                                                    <i class="{{ $actIcon }} me-1"></i>
                                                                    {{ __('crm.activity_types.' . $item->type) ?? $item->type }}
                                                                    &bull; Current: <strong>{{ $item->followup_date->format('d M Y, h:i A') }}</strong>
                                                                </p>
                                                                <x-ui.odoo-form-ui type="input" inputType="text" label="New Date & Time" name="followup_date" class="reschedule-datepicker" :required="true" autocomplete="off" placeholder="Pick new date & time" />
                                                                <x-ui.odoo-form-ui type="select" label="Tag / Assign Persons" name="tagged_user_ids[]" :multiple="true" :searchable="true">
                                                                    @foreach($users as $u)
                                                                        <option value="{{ $u->id }}" @selected(in_array($u->id, $currentTaggedIds))>{{ $u->name }} ({{ $u->email }})</option>
                                                                    @endforeach
                                                                </x-ui.odoo-form-ui>
                                                                <x-ui.odoo-form-ui type="textarea" label="Note (optional)" name="notes" rows="2" placeholder="Reason for rescheduling...">{{ $item->notes }}</x-ui.odoo-form-ui>
                                                            </x-ui.modal>
                                                        @endif
                                                    @endforeach
                                                </div>
                                            @endforeach
                                        @endif
                                    </div>
                                    
                                </div>

                            </div>
                        </div>
                    </div> <!-- End TAB 2: TIMELINE PANE -->

                    <!-- ==================== TAB 3: QUOTATION PANE ==================== -->
                    @if ($activeQuotation || request()->has('create_quotation') || old('form_type') === 'quotation_create' || old('form_type') === 'quotation_edit')
                        <div class="tab-pane fade show {{ request()->has('create_quotation') || request()->has('edit_quotation') || request()->has('view_quotation') || old('form_type') === 'quotation_create' || old('form_type') === 'quotation_edit' ? 'active' : '' }}" id="quotation-pane" role="tabpanel" aria-labelledby="quotation-tab">
                            <div class="py-1">
                                
                                @if (request()->has('create_quotation') || old('form_type') === 'quotation_create')
                                    <!-- CREATE QUOTATION FORM -->
                                    <div class="card border shadow-sm" style="border-radius: 4px; border-color: #e2e8f0 !important; background-color: #ffffff;">
                                        <div class="card-body p-3">
                                            <form action="{{ route('crm.quotations.store') }}" method="POST" id="quotationForm" novalidate>
                                        @csrf
                                        <input type="hidden" name="lead_id" value="{{ $lead->id }}">
                                        <input type="hidden" name="form_type" value="quotation_create">
                                        
                                        @if ($errors->any() && old('form_type') === 'quotation_create')
                                            <div class="alert alert-danger py-2 px-3 mb-3 fs-12 shadow-sm border-0 bg-soft-danger text-danger" style="border-radius: 4px;">
                                                <ul class="mb-0 ps-3">
                                                    @foreach ($errors->all() as $error)
                                                        @if (str_contains($error, 'items.'))
                                                            <li>{{ str_replace(['items.', '.product_id', '.quantity', '.unit_price', 'product id'], ['Item Line #', ' Product', ' Quantity', ' Price', 'Product'], $error) }}</li>
                                                        @else
                                                            <li>{{ $error }}</li>
                                                        @endif
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @endif
                                        
                                        <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">
                                            <h5 class="fw-bold text-dark mb-0">{{ __('crm.new_quotation') }}</h5>
                                            <a href="{{ route('crm.leads.show', $lead->id) }}" class="btn btn-sm btn-light border">{{ __('crm.cancel') }}</a>
                                        </div>

                                        <div class="row g-4 mb-4 fs-13 text-dark">
                                            <div class="col-md-6">
                                                <x-ui.odoo-form-ui type="input" :label="__('crm.customer')" name="_customer_display"
                                                    :value="$lead->contact_person ?: ($lead->company_name ?: 'N/A')"
                                                    readonly="true"
                                                    style="font-weight: bold; color: var(--bs-primary); background-color: #f8f9fa;" />

                                                <x-ui.odoo-form-ui type="input" :label="__('crm.email')" name="email" :value="old('email', $lead->email)" :errorText="$errors->first('email')" />
                                                <x-ui.odoo-form-ui type="input" :label="__('crm.contact_phone')" name="phone" :value="old('phone', $lead->phone)" :errorText="$errors->first('phone')" />
                                            </div>
                                            <div class="col-md-6">
                                                <x-ui.odoo-form-ui type="input" :label="__('crm.quotation_number')" name="quotation_number"
                                                    :value="old('quotation_number', $nextQuotationNumber)" readonly="true"
                                                    style="font-weight: bold; color: #495057;"
                                                    :errorText="$errors->first('quotation_number')" />

                                                <x-ui.odoo-form-ui type="input" inputType="date" :label="__('crm.date')" name="quotation_date"
                                                    :value="old('quotation_date', date('Y-m-d'))" :errorText="$errors->first('quotation_date')" />

                                                <x-ui.odoo-form-ui type="input" inputType="date" :label="__('crm.expiration')" name="expiry_date"
                                                    :value="old('expiry_date', date('Y-m-d', strtotime('+30 days')))" :errorText="$errors->first('expiry_date')" />

                                                <x-ui.odoo-form-ui type="select" :label="__('crm.status')" name="status" :required="true" :errorText="$errors->first('status')">
                                                     <option value="Draft" @selected(old('status') === 'Draft')>{{ __('crm.quotation_statuses.Draft') }}</option>
                                                     <option value="Pending Approval" @selected(old('status') === 'Pending Approval')>{{ __('crm.quotation_statuses.Pending Approval') }}</option>
                                                 </x-ui.odoo-form-ui>
                                            </div>
                                        </div>

                                        <!-- Order Lines Table -->
                                        <div class="border-top pt-4">
                                            <h5 class="fw-bold text-dark mb-3 fs-14">{{ __('crm.order_lines') }}</h5>
                                            <div class="table-responsive">
                                                <table class="table odoo-table align-middle" id="itemsTable">
                                                    <thead>
                                                        <tr>
                                                            <th style="width: 45%;">{{ __('crm.product_description') }}</th>
                                                            <th class="text-end" style="width: 12%;">{{ __('crm.quantity') }}</th>
                                                            <th class="text-end" style="width: 15%;">{{ __('crm.unit_price') }} (₹)</th>
                                                            <th class="text-end" style="width: 12%;">{{ __('crm.taxes') }} (%)</th>
                                                            <th class="text-end" style="width: 16%;">{{ __('crm.amount') }}</th>
                                                            <th class="text-center" style="width: 5%;"></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <!-- Dynamically generated rows -->
                                                    </tbody>
                                                </table>
                                            </div>
                                            <div class="mt-2.5">
                                                <button type="button" class="btn btn-xs btn-outline-primary fw-bold" id="addItemRow" style="font-size: 10px; padding: 2px 8px; text-transform: none !important;">
                                                    <i class="feather-plus me-1"></i>{{ __('crm.add_product') }}
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Subtotal / Discount / Totals -->
                                         <div class="row mt-4 pt-3 border-top text-dark fs-13">
                                             <div class="col-md-8">
                                                 <div class="pe-md-4">
                                                     <x-ui.odoo-form-ui type="editor" :label="__('crm.terms_conditions')" name="terms_conditions" editorHeight="ht-150" :errorText="$errors->first('terms_conditions')">{!! old('terms_conditions') !!}</x-ui.odoo-form-ui>
                                                     <x-ui.odoo-form-ui type="textarea" :label="__('crm.notes')" name="notes" rows="2" :placeholder="__('crm.notes_placeholder')" :errorText="$errors->first('notes')">{{ old('notes') }}</x-ui.odoo-form-ui>
                                                 </div>
                                             </div>
                                             <div class="col-md-4">
                                                 <div class="d-flex justify-content-between py-1 border-bottom">
                                                     <span class="text-muted fw-semibold">{{ __('crm.untaxed_amount') }}:</span>
                                                     <span class="fw-bold text-dark" id="calcSubtotal">₹0.00</span>
                                                 </div>
                                                 <div class="d-flex justify-content-between py-1 border-bottom">
                                                     <span class="text-muted fw-semibold">{{ __('crm.taxes') }}:</span>
                                                     <span class="fw-bold text-dark" id="calcTax">₹0.00</span>
                                                 </div>
                                                 <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                                     <span class="text-muted fw-semibold me-2">{{ __('crm.discount_colon') }}</span>
                                                     <x-ui.odoo-form-ui type="input" name="discount" id="discountInput" inputType="number" :value="old('discount', 0)" min="0" step="0.01" class="text-end fw-bold" :errorText="$errors->first('discount')" />
                                                 </div>
                                                 <div class="d-flex justify-content-between py-2 fs-15 border-bottom bg-light-50 px-2 rounded mt-1.5">
                                                     <span class="text-dark fw-bold">{{ __('crm.total_colon') }}</span>
                                                     <span class="fw-extrabold text-primary" id="calcTotal">₹0.00</span>
                                                 </div>
                                             </div>
                                         </div>

                                        <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                                            <a href="{{ route('crm.leads.show', $lead->id) }}" class="btn btn-md btn-light border py-2 px-4 shadow-sm fs-12">{{ __('crm.discard') }}</a>
                                            <button type="submit" class="btn btn-md btn-primary py-2 px-5 fw-bold shadow-sm fs-12" style="background-color: #1e40af; border-color: #1e40af;">{{ __('crm.save_quotation') }}</button>
                                        </div>
                                    </form>
                                </div>
                            </div>

                        @elseif ((request()->has('edit_quotation') || old('form_type') === 'quotation_edit') && $activeQuotation)
                            <!-- EDIT QUOTATION FORM -->
                            <div class="card border shadow-sm" style="border-radius: 4px; border-color: #e2e8f0 !important; background-color: #ffffff;">
                                <div class="card-body p-3">
                                    <form action="{{ route('crm.quotations.update', $activeQuotation->id) }}" method="POST" id="quotationForm" novalidate>
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="lead_id" value="{{ $lead->id }}">
                                        <input type="hidden" name="form_type" value="quotation_edit">
                                        
                                        @if ($errors->any() && old('form_type') === 'quotation_edit')
                                            <div class="alert alert-danger py-2 px-3 mb-3 fs-12 shadow-sm border-0 bg-soft-danger text-danger" style="border-radius: 4px;">
                                                <ul class="mb-0 ps-3">
                                                    @foreach ($errors->all() as $error)
                                                        @if (str_contains($error, 'items.'))
                                                            <li>{{ str_replace(['items.', '.product_id', '.quantity', '.unit_price', 'product id'], ['Item Line #', ' Product', ' Quantity', ' Price', 'Product'], $error) }}</li>
                                                        @else
                                                            <li>{{ $error }}</li>
                                                        @endif
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @endif

                                        <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">
                                            <h5 class="fw-bold text-dark mb-0">{{ __('crm.edit_quotation_with_number', ['number' => $activeQuotation->quotation_number]) }}</h5>
                                            <a href="{{ route('crm.leads.show', ['lead' => $lead->id, 'view_quotation' => 1]) }}" class="btn btn-sm btn-light border">{{ __('crm.cancel') }}</a>
                                        </div>

                                        <div class="row g-4 mb-4 fs-13 text-dark">
                                            <div class="col-md-6">
                                                <x-ui.odoo-form-ui type="input" :label="__('crm.customer')" name="_customer_display"
                                                    :value="$lead->contact_person ?: ($lead->company_name ?: 'N/A')"
                                                    readonly="true"
                                                    style="font-weight: bold; color: var(--bs-primary); background-color: #f8f9fa;" />

                                                <x-ui.odoo-form-ui type="input" :label="__('crm.email')" name="email" :value="old('email', $activeQuotation->email ?: $lead->email)" :errorText="$errors->first('email')" />
                                                <x-ui.odoo-form-ui type="input" :label="__('crm.contact_phone')" name="phone" :value="old('phone', $activeQuotation->phone ?: $lead->phone)" :errorText="$errors->first('phone')" />
                                            </div>
                                            <div class="col-md-6">
                                                <x-ui.odoo-form-ui type="input" :label="__('crm.quotation_number')" name="quotation_number"
                                                    :value="$activeQuotation->quotation_number" readonly="true"
                                                    style="font-weight: bold; color: #495057;"
                                                    :errorText="$errors->first('quotation_number')" />

                                                <x-ui.odoo-form-ui type="input" inputType="date" :label="__('crm.date')" name="quotation_date"
                                                    :value="old('quotation_date', $activeQuotation->quotation_date->format('Y-m-d'))" :errorText="$errors->first('quotation_date')" />

                                                <x-ui.odoo-form-ui type="input" inputType="date" :label="__('crm.expiration')" name="expiry_date"
                                                    :value="old('expiry_date', $activeQuotation->expiry_date ? $activeQuotation->expiry_date->format('Y-m-d') : '')" :errorText="$errors->first('expiry_date')" />

                                                <x-ui.odoo-form-ui type="select" :label="__('crm.status')" name="status" :required="true" :errorText="$errors->first('status')">
                                                     <option value="Draft" @selected(old('status', $activeQuotation->status) === 'Draft')>{{ __('crm.quotation_statuses.Draft') }}</option>
                                                     <option value="Pending Approval" @selected(old('status', $activeQuotation->status) === 'Pending Approval' || old('status', $activeQuotation->status) === 'Rejected' || old('status', $activeQuotation->status) === 'Quotation Rework' || old('status', $activeQuotation->status) === 'Approved' || old('status', $activeQuotation->status) === 'Declined')>{{ __('crm.quotation_statuses.Pending Approval') }}</option>
                                                </x-ui.odoo-form-ui>
                                            </div>
                                        </div>

                                        <!-- Order Lines Table -->
                                        <div class="border-top pt-4">
                                            <h5 class="fw-bold text-dark mb-3 fs-14">{{ __('crm.order_lines') }}</h5>
                                            <div class="table-responsive">
                                                <table class="table odoo-table align-middle" id="itemsTable">
                                                    <thead>
                                                        <tr>
                                                            <th style="width: 45%;">{{ __('crm.product_description') }}</th>
                                                            <th class="text-end" style="width: 12%;">{{ __('crm.quantity') }}</th>
                                                            <th class="text-end" style="width: 15%;">{{ __('crm.unit_price') }} (₹)</th>
                                                            <th class="text-end" style="width: 12%;">{{ __('crm.taxes') }} (%)</th>
                                                            <th class="text-end" style="width: 16%;">{{ __('crm.amount') }}</th>
                                                            <th class="text-center" style="width: 5%;"></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <!-- Dynamically generated rows -->
                                                    </tbody>
                                                </table>
                                            </div>
                                            <div class="mt-2.5">
                                                <button type="button" class="btn btn-xs btn-outline-primary fw-bold" id="addItemRow" style="font-size: 10px; padding: 2px 8px; text-transform: none !important;">
                                                    <i class="feather-plus me-1"></i>{{ __('crm.add_product') }}
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Subtotal / Discount / Totals -->
                                        <div class="row mt-4 pt-3 border-top text-dark fs-13">
                                            <div class="col-md-8">
                                                <div class="pe-md-4">
                                                    <x-ui.odoo-form-ui type="editor" :label="__('crm.terms_conditions')" name="terms_conditions" editorHeight="ht-150" :errorText="$errors->first('terms_conditions')">{!! old('terms_conditions', $activeQuotation->terms_conditions) !!}</x-ui.odoo-form-ui>
                                                    <x-ui.odoo-form-ui type="textarea" :label="__('crm.notes')" name="notes" rows="2" :placeholder="__('crm.notes_placeholder')" :errorText="$errors->first('notes')">{{ old('notes', $activeQuotation->notes) }}</x-ui.odoo-form-ui>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="d-flex justify-content-between py-1 border-bottom">
                                                    <span class="text-muted fw-semibold">{{ __('crm.untaxed_amount') }}:</span>
                                                    <span class="fw-bold text-dark" id="calcSubtotal">₹0.00</span>
                                                </div>
                                                <div class="d-flex justify-content-between py-1 border-bottom">
                                                    <span class="text-muted fw-semibold">{{ __('crm.taxes') }}:</span>
                                                    <span class="fw-bold text-dark" id="calcTax">₹0.00</span>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                                    <span class="text-muted fw-semibold me-2">{{ __('crm.discount_colon') }}</span>
                                                    <x-ui.odoo-form-ui type="input" name="discount" id="discountInput" inputType="number" :value="old('discount', $activeQuotation->discount)" min="0" step="0.01" class="text-end fw-bold" :errorText="$errors->first('discount')" />
                                                </div>
                                                <div class="d-flex justify-content-between py-2 fs-15 border-bottom bg-light-50 px-2 rounded mt-1.5">
                                                    <span class="text-dark fw-bold">{{ __('crm.total_colon') }}</span>
                                                    <span class="fw-extrabold text-primary" id="calcTotal">₹0.00</span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                                            <a href="{{ route('crm.leads.show', ['lead' => $lead->id, 'view_quotation' => 1]) }}" class="btn btn-md btn-light border py-2 px-4 shadow-sm fs-12">Discard</a>
                                            <button type="submit" class="btn btn-md btn-primary py-2 px-5 fw-bold shadow-sm fs-12" style="background-color: #1e40af; border-color: #1e40af;">{{ __('crm.save_changes') }}</button>
                                        </div>
                                    </form>
                                </div>
                            </div>

                        @elseif ($activeQuotation)
                                    <!-- VIEW QUOTATION DETAILS -->
                                    <div class="odoo-sheet rounded border p-4 bg-white" id="quotation-print-area">
                                        <div class="d-flex justify-content-between align-items-center pb-3 border-bottom mb-4 flex-wrap gap-2 d-print-none">
                                            <h4 class="fw-bold text-dark mb-0 fs-16">{{ __('crm.quotation_sheet_with_number', ['number' => $activeQuotation->quotation_number]) }}</h4>
                                            <div class="d-flex flex-wrap gap-2">
                                                <a href="{{ route('crm.quotations.download', $activeQuotation->id) }}" class="btn btn-sm btn-primary" style="background-color: #1e40af; border-color: #1e40af;"><i class="feather-printer me-1"></i>{{ __('crm.print_download') }}</a>
                                                <a href="{{ route('crm.quotations.show', $activeQuotation->id) }}" class="btn btn-sm btn-light border"><i class="feather-eye me-1"></i>{{ __('crm.view_full_quotation') }}</a>
                                                <a href="{{ route('crm.leads.show', ['lead' => $lead->id, 'edit_quotation' => 1]) }}" class="btn btn-sm btn-light border"><i class="feather-edit-2 me-1"></i>{{ __('crm.edit_quotation') }}</a>
                                                @if ($activeQuotation->status === 'Draft' || $activeQuotation->status === 'Quotation Rework')
                                                     <form action="{{ route('crm.quotations.updateStatus', $activeQuotation->id) }}" method="POST" class="d-inline">
                                                         @csrf
                                                         @method('PATCH')
                                                         <input type="hidden" name="status" value="Pending Approval">
                                                         <button type="submit" class="btn btn-sm btn-warning"><i class="feather-send me-1"></i>{{ __('crm.send_for_approval') }}</button>
                                                     </form>
                                                 @elseif ($activeQuotation->status === 'Approved')
                                                     <form action="{{ route('crm.quotations.updateStatus', $activeQuotation->id) }}" method="POST" class="d-inline">
                                                         @csrf
                                                         @method('PATCH')
                                                         <input type="hidden" name="status" value="Quotation Sent">
                                                         <button type="submit" class="btn btn-sm btn-primary" style="background-color: #1e40af; border-color: #1e40af;"><i class="feather-send me-1"></i>{{ __('crm.mark_sent') }}</button>
                                                     </form>
                                                 @elseif ($activeQuotation->status === 'Quotation Sent')
                                                     <form action="{{ route('crm.quotations.updateStatus', $activeQuotation->id) }}" method="POST" class="d-inline">
                                                         @csrf
                                                         @method('PATCH')
                                                         <input type="hidden" name="status" value="Accepted">
                                                         <button type="submit" class="btn btn-sm btn-success">{{ __('crm.accept_quotation') }}</button>
                                                     </form>
                                                 @elseif ($activeQuotation->status === 'Accepted')
                                                     <a href="{{ route('sales.orders.create', ['quotation_id' => $activeQuotation->id]) }}" class="btn btn-sm btn-success">
                                                         <i class="feather-shopping-cart me-1"></i>{{ __('crm.convert_to_sales_order') }}
                                                     </a>
                                                 @endif
                                            </div>
                                        </div>

                                        @if (in_array($activeQuotation->status, ['Rejected', 'Declined']))
                                             <div class="alert alert-danger border-danger border-start border-4 shadow-sm mb-4 d-print-none" role="alert" style="background-color: #fff5f5;">
                                                 <div class="d-flex align-items-start">
                                                     <div class="avatar-text avatar-md bg-danger text-white me-3 mt-0.5 rounded-circle flex-shrink-0">
                                                         <i class="feather-x-circle fs-18"></i>
                                                     </div>
                                                     <div class="flex-grow-1">
                                                         <h6 class="alert-heading fw-bold text-danger mb-1"><i class="feather-alert-triangle me-1"></i> Quotation Rejected</h6>
                                                         <p class="fs-13 text-dark mb-0">
                                                             <strong>Rejection Reason / Remarks:</strong> 
                                                             <span class="text-danger fw-semibold">{{ $activeQuotation->rejection_reason ?: 'No specific reason provided.' }}</span>
                                                         </p>
                                                     </div>
                                                 </div>
                                             </div>
                                         @endif

                                        <!-- Quotation Details Table -->
                                        <div class="row g-4 mb-4 fs-13 text-dark">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="text-muted fs-11 text-uppercase fw-bold d-block mb-1">{{ __('crm.customer_account') }}</label>
                                                    <div class="fw-bold text-dark fs-14">{{ $lead->company_name }}</div>
                                                    <div class="text-muted fs-12">{{ $lead->contact_person }}</div>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="text-muted fs-11 text-uppercase fw-bold d-block mb-1">{{ __('crm.billing_address') }}</label>
                                                    <div class="fs-12">{{ $lead->address ?: __('crm.no_address_specified') }}<br>{{ $lead->city }} {{ $lead->state }} {{ $lead->country }}</div>
                                                </div>
                                            </div>
                                            <div class="col-md-6 border-start-md">
                                                <div class="row">
                                                    <div class="col-6 mb-3">
                                                        <label class="text-muted fs-11 text-uppercase fw-bold d-block mb-1">Date</label>
                                                        <div class="fw-semibold">{{ $activeQuotation->quotation_date->format('d M Y') }}</div>
                                                    </div>
                                                    <div class="col-6 mb-3">
                                                        <label class="text-muted fs-11 text-uppercase fw-bold d-block mb-1">{{ __('crm.expiration') }}</label>
                                                        <div class="fw-semibold text-danger">{{ $activeQuotation->expiration_date ? $activeQuotation->expiration_date->format('d M Y') : '—' }}</div>
                                                    </div>
                                                </div>
                                                <div class="mb-3">
                                                     <label class="text-muted fs-11 text-uppercase fw-bold d-block mb-1">{{ __('crm.quotation_status') }}</label>
                                                     @php
                                                          $activeQuoBadgeClass = 'bg-soft-secondary text-secondary';
                                                          if ($activeQuotation->status === 'Quotation Sent' || $activeQuotation->status === 'Sent') $activeQuoBadgeClass = 'bg-soft-info text-info';
                                                          elseif ($activeQuotation->status === 'Accepted' || $activeQuotation->status === 'Approved' || $activeQuotation->status === 'Won' || $activeQuotation->status === 'Converted') $activeQuoBadgeClass = 'bg-soft-success text-success';
                                                          elseif ($activeQuotation->status === 'Rejected') $activeQuoBadgeClass = 'bg-soft-danger text-danger';
                                                          elseif ($activeQuotation->status === 'Pending Approval') $activeQuoBadgeClass = 'bg-soft-warning text-warning';
                                                          elseif ($activeQuotation->status === 'Quotation Rework') $activeQuoBadgeClass = 'bg-soft-warning text-warning';

                                                          $quoStatusText = \Illuminate\Support\Facades\Lang::has('crm.quotation_statuses.' . $activeQuotation->status) 
                                                              ? __('crm.quotation_statuses.' . $activeQuotation->status) 
                                                              : ($activeQuotation->status === 'Converted' ? __('crm.quotation_statuses.Converted') : $activeQuotation->status);
                                                      @endphp
                                                      <div class="fw-semibold"><span class="badge {{ $activeQuoBadgeClass }}">{{ $quoStatusText }}</span></div>
                                                 </div>
                                            </div>
                                        </div>

                                        <!-- Items Order Lines Table -->
                                        <div class="border-top pt-4">
                                            <h5 class="fw-bold text-dark mb-3 fs-14">{{ __('crm.order_lines') }}</h5>
                                            <div class="table-responsive">
                                                <table class="table table-bordered table-sm align-middle fs-13 text-dark">
                                                    <thead class="table-light fs-11 text-uppercase text-muted fw-semibold">
                                                        <tr>
                                                            <th class="ps-3" style="width: 50%;">{{ __('crm.product_description') }}</th>
                                                            <th class="text-center" style="width: 10%;">{{ __('crm.quantity') }}</th>
                                                            <th class="text-end" style="width: 15%;">{{ __('crm.unit_price') }}</th>
                                                            <th class="text-end" style="width: 10%;">{{ __('crm.taxes') }}</th>
                                                            <th class="text-end pe-3" style="width: 15%;">{{ __('crm.amount') }}</th>
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
                                                            <div class="fw-bold text-muted fs-11 text-uppercase mb-1">{{ __('crm.terms_conditions') }}</div>
                                                            <div class="text-dark fs-12 p-2 border bg-light-50 rounded terms-conditions-content" style="line-height: 1.5; font-family: 'Inter', sans-serif;">{!! $activeQuotation->terms_conditions !!}</div>
                                                        </div>
                                                    @endif
                                                    @if($activeQuotation->notes)
                                                        <div class="mb-3">
                                                            <div class="fw-bold text-muted fs-11 text-uppercase mb-1">{{ __('crm.notes') }}</div>
                                                            <div class="text-dark fs-12 p-2 border bg-light-50 rounded" style="white-space: pre-wrap; line-height: 1.5; font-family: 'Inter', sans-serif;">{{ $activeQuotation->notes }}</div>
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="d-flex justify-content-between py-1 border-bottom">
                                                    <span class="text-muted fw-semibold">{{ __('crm.untaxed_amount') }}:</span>
                                                    <span class="fw-bold text-dark">₹{{ number_format($activeQuotation->subtotal, 2) }}</span>
                                                </div>
                                                <div class="d-flex justify-content-between py-1 border-bottom">
                                                    <span class="text-muted fw-semibold">{{ __('crm.taxes') }}:</span>
                                                    <span class="fw-bold text-dark">₹{{ number_format($activeQuotation->tax_amount, 2) }}</span>
                                                </div>
                                                @if($activeQuotation->discount > 0)
                                                    <div class="d-flex justify-content-between py-1 border-bottom">
                                                        <span class="text-muted fw-semibold">{{ __('crm.discount') }}:</span>
                                                        <span class="fw-bold text-danger">-₹{{ number_format($activeQuotation->discount, 2) }}</span>
                                                    </div>
                                                @endif
                                                <div class="d-flex justify-content-between py-2 fs-15 border-bottom bg-light-50 px-2 rounded mt-1.5">
                                                    <span class="text-dark fw-bold">{{ __('crm.total_colon') }}</span>
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
                                                    <i class="feather-git-commit me-1.5 text-primary"></i>{{ __('crm.quotation_revision_history') }}
                                                </h6>
                                                <div class="d-flex flex-wrap gap-2 align-items-center">
                                                    @foreach($revisions as $rev)
                                                        <div class="d-flex align-items-center gap-2 p-2 border rounded bg-white" style="min-width: 170px; border-color: {{ $rev->id === $activeQuotation->id ? '#3b82f6 !important' : '#e2e8f0' }} !important; transition: all 0.2s; position: relative; {{ $rev->id === $activeQuotation->id ? 'box-shadow: 0 0 0 1px rgba(59,130,246,0.1); background-color: #f0f9ff !important;' : '' }}">
                                                            @if($rev->id === $activeQuotation->id)
                                                                <span class="position-absolute top-0 end-0 translate-middle-y badge rounded-pill bg-primary fs-8 text-uppercase px-1" style="font-size: 8px !important; margin-right: 10px;">{{ __('crm.viewing') }}</span>
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
                                            <h5 class="fw-bold text-dark mb-2">{{ __('crm.no_quotation_found') }}</h5>
                                            
                                            @if($lead->status === 'Qualified')
                                                <p class="text-muted fs-12 mx-auto mb-4" style="max-width: 400px; font-family: 'Inter', sans-serif;">
                                                    {!! __('crm.no_quotation_qualified_help') !!}
                                                </p>
                                                <form action="{{ route('crm.leads.convertToQuotation', $lead->id) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-success fw-bold px-4 py-2 text-uppercase fs-11" style="background-color: #16a34a; border-color: #16a34a; border-radius: 4px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                                                        <i class="feather-shuffle me-1.5 fs-11"></i> {{ __('crm.convert_to_quotation') }}
                                                    </button>
                                                </form>
                                            @else
                                                <p class="text-muted fs-12 mx-auto mb-0" style="max-width: 400px; font-family: 'Inter', sans-serif;">
                                                    {!! __('crm.no_quotation_unqualified_help') !!}
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
    <x-ui.modal id="modalLogNote" :title="__('crm.log_discussion_note')" :centered="true" :formAction="route('crm.leads.followups.store', $lead->id)" formMethod="POST" :submitText="__('crm.add_note')" :closeText="__('crm.cancel')">
        <input type="hidden" name="type" value="Call">
        <input type="hidden" name="status" value="Completed">
        <input type="hidden" name="followup_date" value="{{ date('Y-m-d H:i') }}">
        
        <x-ui.odoo-form-ui type="select" :label="__('crm.interaction_type')" name="type_select" onchange="this.form.type.value = this.value">
            <option value="Call">{{ __('crm.interaction_types.Call') }}</option>
            <option value="Email">{{ __('crm.interaction_types.Email') }}</option>
            <option value="Meeting">{{ __('crm.interaction_types.Meeting') }}</option>
            <option value="Demo">{{ __('crm.interaction_types.Demo') }}</option>
        </x-ui.odoo-form-ui>

        <x-ui.odoo-form-ui type="select" label="Tag / Assign Persons" name="tagged_user_ids[]" :multiple="true" :searchable="true">
            @foreach($users as $u)
                <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
            @endforeach
        </x-ui.odoo-form-ui>

        <x-ui.odoo-form-ui type="textarea" :label="__('crm.notes_summary')" name="notes" rows="4" :required="true" :placeholder="__('crm.notes_summary_placeholder')" />
    </x-ui.modal>

    <!-- Schedule Activity Modal -->
    <x-ui.modal id="modalScheduleActivity" :title="__('crm.schedule_next_activity')" :centered="true" :formAction="route('crm.leads.followups.store', $lead->id)" formMethod="POST" :submitText="__('crm.schedule')" :closeText="__('crm.cancel')">
        <input type="hidden" name="status" value="Pending">
        
        <x-ui.odoo-form-ui type="select" :label="__('crm.activity_type')" name="type" :required="true">
            <option value="Call">{{ __('crm.activity_types.Call') }}</option>
            <option value="Email">{{ __('crm.activity_types.Email') }}</option>
            <option value="Meeting">{{ __('crm.activity_types.Meeting') }}</option>
            <option value="Demo">{{ __('crm.activity_types.Demo') }}</option>
        </x-ui.odoo-form-ui>

        <x-ui.odoo-form-ui type="input" inputType="datetime-local" :label="__('crm.due_date_time')" name="followup_date" id="inline_activity_datepicker" :required="true" />

        <x-ui.odoo-form-ui type="select" label="Tag / Assign Persons" name="tagged_user_ids[]" :multiple="true" :searchable="true">
            @foreach($users as $u)
                <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
            @endforeach
        </x-ui.odoo-form-ui>

        <x-ui.odoo-form-ui type="textarea" :label="__('crm.description_plan')" name="notes" rows="4" :placeholder="__('crm.activity_plan_placeholder')" />
    </x-ui.modal>
@endsection

@push('styles')
    <!-- Select2 Styles -->
    <link class="d-print-none" rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link class="d-print-none" rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
    <style>
        /* Quill Editor terms spacing layout adjustments */
        .terms-conditions-content p {
            margin-bottom: 4px !important;
            line-height: 1.4 !important;
        }
        .terms-conditions-content p:last-child {
            margin-bottom: 0 !important;
        }

        .daterangepicker {
            z-index: 99999 !important;
        }

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
            border-radius: 8px;
        }

        .zoho-sidebar-nav .nav-link:hover {
            background-color: color-mix(in srgb, var(--bs-primary) 8%, transparent);
            color: var(--bs-primary, #1e40af) !important;
            text-decoration: none;
        }

        .zoho-sidebar-nav .nav-link.active {
            background-color: var(--bs-primary, #1e40af) !important;
            color: #ffffff !important;
            font-weight: 600 !important;
            box-shadow: 0 4px 12px color-mix(in srgb, var(--bs-primary) 30%, transparent);
            border-radius: 8px;
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

        /* Zoho CRM Timeline Styles (legacy, kept for history tab) */
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

        /* ===== Activity Card Styles (Interactions Tab) ===== */
        .activity-date-badge {
            display: inline-flex;
            align-items: center;
            font-size: 11px;
            font-weight: 700;
            color: #475569;
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            border-radius: 20px;
            padding: 3px 10px;
            font-family: 'Inter', sans-serif;
        }

        .activity-card {
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            background: #ffffff;
            transition: box-shadow 0.18s ease, border-color 0.18s ease;
        }
        .activity-card:hover {
            box-shadow: 0 4px 16px rgba(30,64,175,0.07);
            border-color: #bfdbfe;
        }
        .activity-card-inner {
            padding: 12px 14px;
        }

        .activity-type-icon {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            margin-top: 2px;
        }

        .activity-time-chip {
            display: inline-flex;
            align-items: center;
            font-size: 10px;
            font-weight: 600;
            color: #64748b;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            padding: 2px 8px;
        }

        .activity-notes {
            font-size: 12px;
            font-weight: 500;
            color: #334155;
            background: #f8fafc;
            border-left: 3px solid #93c5fd;
            border-radius: 0 6px 6px 0;
            padding: 5px 10px;
            margin: 6px 0;
            font-style: italic;
            line-height: 1.5;
        }
        .activity-card--done .activity-notes {
            border-left-color: #86efac;
        }

        .activity-by {
            font-size: 10px;
            color: #94a3b8;
            font-weight: 500;
            margin-top: 4px;
            display: flex;
            align-items: center;
        }

        /* Footer action buttons — horizontal row at bottom of card */
        .activity-footer-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            padding-top: 10px;
            border-top: 1px dashed #e2e8f0;
        }
        .activity-footer-actions .erp-icon-btn {
            flex-shrink: 0;
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
            vertical-align: top !important;
        }
        .odoo-table-input {
            border: none;
            border-bottom: 1px solid #cbd5e1 !important;
            background: transparent;
            border-radius: 0;
            padding: 4px 2px;
            width: 100%;
            font-size: 13px;
            transition: border-color 0.2s ease-in-out;
        }
        .odoo-table-input:hover {
            border-bottom-color: #94a3b8 !important;
        }
        .odoo-table-input:focus {
            border-bottom-color: var(--bs-primary) !important;
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
    @if ($errors->any())
        <script>
            (function() {
                var activeTabKey = 'lead_active_tab_' + {{ $lead->id }};
                @if (old('form_type') === 'quotation_create' || old('form_type') === 'quotation_edit')
                    localStorage.setItem(activeTabKey, 'quotation-tab');
                @else
                    localStorage.setItem(activeTabKey, 'overview-tab');
                @endif
            })();
        </script>
    @endif
    <!-- Select2 & Quotation Rows logic -->
    <script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/select2-active.min.js') }}"></script>
    <script>
        $(function () {
            // Tab state persistence logic
            var activeTabKey = 'lead_active_tab_' + {{ $lead->id }};
            var activeSubTabKey = 'lead_active_subtab_' + {{ $lead->id }};
            
            // Check URL Hash first if present
            var hash = window.location.hash;
            if (hash === '#timeline' || hash === '#timeline-pane' || hash === '#subtab-interactions' || hash === '#subtab-history') {
                localStorage.setItem(activeTabKey, 'timeline-tab');
                if (hash === '#subtab-interactions') {
                    localStorage.setItem(activeSubTabKey, 'subtab-interactions-tab');
                } else if (hash === '#subtab-history') {
                    localStorage.setItem(activeSubTabKey, 'subtab-history-tab');
                }
            } else if (hash === '#overview' || hash === '#overview-pane') {
                localStorage.setItem(activeTabKey, 'overview-tab');
            } else if (hash === '#quotation' || hash === '#quotation-pane') {
                localStorage.setItem(activeTabKey, 'quotation-tab');
            }

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
                // Restore tab from localStorage
                var savedTabId = localStorage.getItem(activeTabKey);
                if (savedTabId && $('#' + savedTabId).length) {
                    setTimeout(function() {
                        var mainTabEl = document.getElementById(savedTabId);
                        if (mainTabEl) {
                            bootstrap.Tab.getOrCreateInstance(mainTabEl).show();
                        }
                        
                        // If it's timeline tab, also restore the subtab
                        if (savedTabId === 'timeline-tab') {
                            var savedSubTabId = localStorage.getItem(activeSubTabKey) || 'subtab-interactions-tab';
                            var subTabEl = document.getElementById(savedSubTabId);
                            if (subTabEl) {
                                bootstrap.Tab.getOrCreateInstance(subTabEl).show();
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
                        if (targetId === '#sectionLeadInfo' || targetId === '#sectionAddressInfo' || targetId === '#sectionRequirements' || targetId === '#sectionNotes' || targetId === '#sectionDocuments') {
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

            // Handle scroll after tab transition finishes, and save active tab state in localStorage
            $(document).on('shown.bs.tab', 'button[data-bs-toggle="tab"], a[data-bs-toggle="tab"]', function (e) {
                if (e.target.id) {
                    if (e.target.id === 'overview-tab' || e.target.id === 'timeline-tab' || e.target.id === 'quotation-tab') {
                        localStorage.setItem(activeTabKey, e.target.id);
                    } else if (e.target.id === 'subtab-history-tab' || e.target.id === 'subtab-interactions-tab') {
                        localStorage.setItem(activeSubTabKey, e.target.id);
                        localStorage.setItem(activeTabKey, 'timeline-tab');
                    }
                }

                if (scrollTargetOnTabShown) {
                    scrollToElement(scrollTargetOnTabShown);
                    scrollTargetOnTabShown = null;
                }
            });

            // Auto submit status forms when changed in Select2 status selector
            $('.status-select').on('change', function() {
                $(this).closest('form').submit();
            });

            // Auto submit owner forms when changed in Select2 owner selector
            $('.owner-select').on('change', function() {
                $(this).closest('form').submit();
            });

            // Initialize reschedule datepickers when their modal opens
            $('[id^="rescheduleModal_"]').on('shown.bs.modal', function() {
                var $picker = $(this).find('.reschedule-datepicker');
                if (!$picker.data('daterangepicker')) {
                    $picker.daterangepicker({
                        singleDatePicker: true,
                        timePicker: true,
                        timePickerIncrement: 5,
                        drops: 'up',
                        locale: {
                            format: 'YYYY-MM-DD hh:mm A'
                        }
                    });
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

            const leadDocUploadBtn = $('#leadDocUploadBtn');
            const leadDocInput = $('#leadDocInput');

            leadDocUploadBtn.on('click', function() {
                leadDocInput.trigger('click');
            });

            leadDocInput.on('change', function() {
                if (this.files.length > 0) {
                    $(this).closest('form').submit();
                }
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
                        'selling_price' => (float) ($p->selling_price ?: ($p->parent?->selling_price ?: 0))
                    ];
                });
            @endphp
            const crmProductsList = @json($mappedProducts);

            function buildProductOptions(selectedId = '') {
                let opts = '<option value="">{{ __('crm.select_product') }}</option>';
                opts += '<option value="__ADD_NEW__" class="fw-bold text-primary" data-master="product">+ {{ __('crm.add_new_product') }}</option>';
                crmProductsList.forEach(function(p) {
                    const sel = (p.id == selectedId) ? ' selected' : '';
                    opts += `<option value="${p.id}" data-selling-price="${p.selling_price ?? 0}"${sel}>${p.name} (${p.sku})</option>`;
                });
                return opts;
            }

            function getRowHtml(index, selectedId = '') {
                return `
                    <tr class="item-row" data-row-id="${index}">
                        <td class="ps-3">
                            <select name="items[${index}][product_id]" class="odoo-table-select odoo-select2 item-name-input erp-premium-select" required data-master="product">
                                ${buildProductOptions(selectedId)}
                            </select>
                            <div class="description-container mt-2" id="desc-container-${index}" style="display: none;">
                                <textarea name="items[${index}][description]" class="form-control odoo-table-input" placeholder="{{ __('crm.scope_details_placeholder') }}"></textarea>
                            </div>
                            <a href="javascript:void(0)" class="toggle-desc-btn text-primary fs-11 mt-1 d-inline-block" data-row-id="${index}">
                                <i class="feather-plus me-1"></i>{{ __('crm.add_description') }}
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
            const hasCreateQ = @json(request()->has('create_quotation') || old('form_type') === 'quotation_create');
            const hasEditQ = @json(request()->has('edit_quotation') || old('form_type') === 'quotation_edit');
            const existingItems = @json(old('items') ?: (isset($activeQuotation) ? $activeQuotation->items : []));
            const prefillProductItems = @json($lead->product_items ?: []);
            const prefillProductIds = @json($lead->product_ids ?: []);
            const prefillAmount = @json($lead->expected_amount);

            if (hasCreateQ || hasEditQ) {
                if (existingItems.length > 0) {
                    existingItems.forEach(function(item) {
                        addRow(item);
                    });
                } else if (hasCreateQ && (prefillProductItems.length > 0 || prefillProductIds.length > 0 || prefillAmount)) {
                    if (prefillProductItems.length > 0) {
                        prefillProductItems.forEach(function(pItem) {
                            const pObj = crmProductsList.find(p => p.id == pItem.product_id);
                            const unitPrice = (pObj && parseFloat(pObj.selling_price) > 0) ? parseFloat(pObj.selling_price) : (parseFloat(prefillAmount) || 0.00);
                            addRow({
                                product_id: pItem.product_id || '',
                                description: '',
                                quantity: parseFloat(pItem.quantity) || 1,
                                unit_price: unitPrice,
                                tax_rate: 18.00
                            });
                        });
                    } else if (prefillProductIds.length > 0) {
                        prefillProductIds.forEach(function(pid) {
                            const pObj = crmProductsList.find(p => p.id == pid);
                            const unitPrice = (pObj && parseFloat(pObj.selling_price) > 0) ? parseFloat(pObj.selling_price) : (parseFloat(prefillAmount) || 0.00);
                            addRow({
                                product_id: pid || '',
                                description: '',
                                quantity: 1,
                                unit_price: unitPrice,
                                tax_rate: 18.00
                            });
                        });
                    } else {
                        addRow({
                            product_id: '',
                            description: '',
                            quantity: 1,
                            unit_price: parseFloat(prefillAmount) || 0.00,
                            tax_rate: 18.00
                        });
                    }
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
                    $(this).html('<i class="feather-plus me-1"></i>{{ __('crm.add_description') }}');
                } else {
                    container.slideDown(120);
                    $(this).html('<i class="feather-minus me-1"></i>{{ __('crm.remove_description') }}');
                }
            });

            // Remove row action
            $(document).on('click', '.remove-row-btn', function() {
                const rowsCount = $('.item-row').length;
                if (rowsCount > 1) {
                    $(this).closest('tr').remove();
                    calculateTotals();
                } else {
                    confirmAction({
                        title: 'Warning',
                        message: "{{ __('crm.alert_at_least_one_item') }}",
                        variant: 'warning',
                        confirmText: 'OK'
                    });
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

                // Check validation errors and show message under the inputs
                const validationErrors = @json($errors->toArray());
                const errorKey = `items.${rowIndex}.product_id`;
                if (validationErrors[errorKey]) {
                    newRow.find('.item-name-input').addClass('is-invalid');
                    newRow.find('.item-name-input').closest('td').append(`
                        <div class="invalid-feedback d-block mt-1">${validationErrors[errorKey][0]}</div>
                    `);
                }
                const qtyErrorKey = `items.${rowIndex}.quantity`;
                if (validationErrors[qtyErrorKey]) {
                    newRow.find('.qty-input').addClass('is-invalid');
                    newRow.find('.qty-input').closest('td').append(`
                        <div class="invalid-feedback d-block mt-1 text-end">${validationErrors[qtyErrorKey][0]}</div>
                    `);
                }
                const priceErrorKey = `items.${rowIndex}.unit_price`;
                if (validationErrors[priceErrorKey]) {
                    newRow.find('.price-input').addClass('is-invalid');
                    newRow.find('.price-input').closest('td').append(`
                        <div class="invalid-feedback d-block mt-1 text-end">${validationErrors[priceErrorKey][0]}</div>
                    `);
                }

                // Prefill details
                let isPrefilling = false;
                if (item) {
                    isPrefilling = true;
                    newRow.find('.item-name-input').val(item.product_id).trigger('change');
                    newRow.find('textarea').val(item.description || '');
                    if (item.description) {
                        $('#desc-container-' + rowIndex).show();
                        newRow.find('.toggle-desc-btn').html('<i class="feather-minus me-1"></i>{{ __('crm.remove_description') }}');
                    }
                    newRow.find('.qty-input').val(item.quantity);

                    let finalUnitPrice = parseFloat(item.unit_price);
                    if (isNaN(finalUnitPrice) || finalUnitPrice === 0) {
                        const foundProd = crmProductsList.find(p => p.id == item.product_id);
                        if (foundProd && parseFloat(foundProd.selling_price) > 0) {
                            finalUnitPrice = parseFloat(foundProd.selling_price);
                        } else {
                            finalUnitPrice = 0.00;
                        }
                    }
                    newRow.find('.price-input').val(finalUnitPrice.toFixed(2));
                    newRow.find('.tax-input').val(item.tax_rate);
                    isPrefilling = false;
                }

                // Auto-fill unit price from product's selling_price when product is selected by user
                newRow.find('.item-name-input').on('change', function() {
                    if (isPrefilling) return;
                    const selectedOption = $(this).find('option:selected');
                    const sellingPrice = parseFloat(selectedOption.attr('data-selling-price')) || 0;
                    $(this).closest('tr').find('.price-input').val(sellingPrice.toFixed(2));
                    calculateTotals();
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

            // Edit Lead Form Product Rows JavaScript
            function updateEditRemoveButtonsState() {
                const rows = $('#editProductItemsBody tr');
                if (rows.length <= 1) {
                    rows.find('.remove-product-row-btn').attr('disabled', true).addClass('opacity-50');
                } else {
                    rows.find('.remove-product-row-btn').removeAttr('disabled').removeClass('opacity-50');
                }
            }

            updateEditRemoveButtonsState();

            let editItemRowIndex = $('#editProductItemsBody tr').length;

            $('#editAddProductRowBtn').on('click', function () {
                let templateHtml = $('#editProductRowSelectTemplate').html();
                let newSelect = $(templateHtml);
                newSelect.attr('name', 'items[' + editItemRowIndex + '][product_id]');

                let newRow = $(`
                    <tr class="lead-item-row border-bottom">
                        <td class="py-1 ps-1 pe-1 align-top"></td>
                        <td class="py-1 px-1 align-top">
                            <input type="number" name="items[${editItemRowIndex}][quantity]" class="form-control form-control-sm text-center qty-row-input" value="1" min="1" step="1">
                        </td>
                        <td class="py-1 text-center align-top pt-2">
                            <button type="button" class="btn btn-link text-danger p-0 opacity-75 remove-product-row-btn" title="Remove Product">
                                <i class="feather-trash-2 fs-13"></i>
                            </button>
                        </td>
                    </tr>
                `);

                newRow.find('td:first-child').append(newSelect);
                $('#editProductItemsBody').append(newRow);

                newSelect.select2({
                    theme: "bootstrap-5",
                    width: "100%"
                });

                editItemRowIndex++;
                updateEditRemoveButtonsState();
            });

            $(document).on('click', '#editProductItemsBody .remove-product-row-btn', function (e) {
                e.preventDefault();
                if ($('#editProductItemsBody tr').length > 1) {
                    $(this).closest('tr').remove();
                    updateEditRemoveButtonsState();
                }
            });

            // Show Page Inline Edit Mode Additional Contacts JS
            function updateShowContactNumbersAndNames() {
                var cards = $('#showAdditionalContactsRepeaterContainer .addl-contact-card');
                $('#showAddlContactCountBadge').text(cards.length);
                cards.each(function(index) {
                    $(this).find('.contact-num').text(index + 1);
                    $(this).find('.contact-name-input').attr('name', 'additional_contacts[' + index + '][name]');
                    $(this).find('.contact-email-input').attr('name', 'additional_contacts[' + index + '][email]');
                    $(this).find('.contact-phone-input').attr('name', 'additional_contacts[' + index + '][phone]');
                });
            }

            function addShowAddlContactCard(cloneValues) {
                var count = $('#showAdditionalContactsRepeaterContainer .addl-contact-card').length;
                var nameVal = cloneValues ? (cloneValues.name || '') : '';
                var emailVal = cloneValues ? (cloneValues.email || '') : '';
                var phoneVal = cloneValues ? (cloneValues.phone || '') : '';

                var html = `
                    <div class="addl-contact-card p-2 px-3 mb-1 bg-white position-relative shadow-2xs" style="border: 1.5px solid var(--bs-primary) !important; border-radius: 8px !important;">
                        <div class="d-flex align-items-center justify-content-between mb-1 pb-1 border-bottom">
                            <span class="fs-11 fw-bold text-muted text-uppercase letter-spacing-1"><i class="feather-user me-1 text-primary"></i> Contact Person #<span class="contact-num">${count + 1}</span></span>
                            <button type="button" class="btn btn-xs btn-soft-danger rounded-circle remove-contact-btn p-0 d-inline-flex align-items-center justify-content-center" title="Delete Contact" style="width: 22px; height: 22px; border-radius: 50%;">
                                <i class="feather-trash-2 text-danger fs-11"></i>
                            </button>
                        </div>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <div class="odoo-form-group">
                                    <label class="odoo-form-label">Name</label>
                                    <div class="flex-grow-1">
                                        <input type="text" name="additional_contacts[${count}][name]" class="odoo-form-control contact-name-input" value="${nameVal}" placeholder="Contact Name">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="odoo-form-group">
                                    <label class="odoo-form-label">Phone No.</label>
                                    <div class="flex-grow-1">
                                        <input type="text" name="additional_contacts[${count}][phone]" class="odoo-form-control contact-phone-input" value="${phoneVal}" placeholder="Phone Number" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="odoo-form-group">
                                    <label class="odoo-form-label">Email</label>
                                    <div class="flex-grow-1">
                                        <input type="email" name="additional_contacts[${count}][email]" class="odoo-form-control contact-email-input" value="${emailVal}" placeholder="Email">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;

                $('#showAdditionalContactsRepeaterContainer').append(html);
                updateShowContactNumbersAndNames();
            }

            // Main + CLONE CONTACT Button Handler
            $('#showCloneContactMainBtn').on('click', function() {
                var lastCard = $('#showAdditionalContactsRepeaterContainer .addl-contact-card').last();
                var values = null;
                if (lastCard.length > 0) {
                    values = {
                        name: lastCard.find('.contact-name-input').val(),
                        email: lastCard.find('.contact-email-input').val(),
                        phone: lastCard.find('.contact-phone-input').val()
                    };
                }
                addShowAddlContactCard(values);
            });

            // Delete Contact Button Handler
            $(document).on('click', '#showAdditionalContactsRepeaterContainer .remove-contact-btn', function() {
                $(this).closest('.addl-contact-card').remove();
                updateShowContactNumbersAndNames();
            window.toggleLeadType = function(type) {
                var fieldNames = ['company_name', 'gstin', 'company_email', 'company_phone'];
                fieldNames.forEach(function(name) {
                    var inputs = document.querySelectorAll('[name="' + name + '"]');
                    inputs.forEach(function(input) {
                        var group = input.closest('.odoo-form-group') || input.closest('.mb-3') || input.parentElement;
                        if (group) {
                            if (type === 'b2c') {
                                group.style.setProperty('display', 'none', 'important');
                            } else {
                                group.style.setProperty('display', 'flex', 'important');
                            }
                        }
                    });
                });
            };

            $(document).on('change click', 'input[name="lead_type"]', function() {
                window.toggleLeadType($(this).val());
            });

            // Initial trigger on load
            var currentLeadType = $('input[name="lead_type"]:checked').val() || 'b2b';
            window.toggleLeadType(currentLeadType);

            // Auto submit form on status change (handles both native and Select2 dropdowns)
            $(document).on('change change.select2', '.status-select', function() {
                var form = $(this).closest('form');
                if (form.length) {
                    form[0].submit();
                }
            });
        });

        function openRejectModal(actionUrl, quotationNumber = '') {
            $('#rejectQuotationForm').attr('action', actionUrl);
            if (quotationNumber) {
                $('#rejectModalQuotationNumber').text('(' + quotationNumber + ')');
            } else {
                $('#rejectModalQuotationNumber').text('');
            }
            $('#rejectionReasonInput').val('');
            const modalEl = document.getElementById('rejectQuotationModal');
            let modal = bootstrap.Modal.getInstance(modalEl);
            if (!modal) {
                modal = new bootstrap.Modal(modalEl);
            }
            modal.show();
        }
    </script>

    <!-- Rejection Reason Modal -->
    <div class="modal fade" id="rejectQuotationModal" tabindex="-1" aria-labelledby="rejectQuotationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-3">
                <form id="rejectQuotationForm" method="POST" action="">
                    @csrf
                    <div class="modal-header bg-soft-danger text-danger border-bottom-0">
                        <h5 class="modal-title fw-bold" id="rejectQuotationModalLabel">
                            <i class="feather-x-circle me-2"></i>Reject Quotation <span id="rejectModalQuotationNumber" class="text-dark"></span>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <p class="text-muted fs-12 mb-3">Please specify the reason for rejecting this quotation. This reason will be saved in audit history and displayed on the quotation detail screen.</p>
                        
                        <div class="mb-3 text-start">
                            <label for="rejectionReasonInput" class="form-label fw-bold text-dark fs-12 mb-1">Rejection Reason / Remarks <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="rejectionReasonInput" name="rejection_reason" rows="4" placeholder="Enter reason for rejection (e.g., Price too high, Scope changed, Customer declined, etc.)..." required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer bg-light border-top-0 px-4 py-3">
                        <button type="button" class="btn btn-light btn-sm border text-uppercase fs-11 fw-bold" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger btn-sm px-4 fw-bold text-uppercase fs-11" style="background-color: #ea580c; border-color: #ea580c;">
                            <i class="feather-x-circle me-1"></i> Confirm Rejection
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Product quick-create modal --}}
    <x-ui.master-modals :masters="['product']" />
@endpush
