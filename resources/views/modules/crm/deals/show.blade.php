@extends('layouts.duralux')

@section('title', $deal->title . ' | SaaS ERP')
@section('page-title', __('crm.deal_profile'))
@section('breadcrumb', 'CRM / ' . __('crm.deals') . ' / ' . $deal->deal_number)

@push('styles')
<style>
    /* ==========================================================================
       ZOHO CRM DEALS PREMIUM STYLING & DESIGN SYSTEM
       ========================================================================== */

    /* Related List Left Sidebar Navigation */
    .zoho-sidebar-nav .nav-link {
        font-size: 12px;
        color: #475569;
        border-radius: 4px;
        padding: 7px 12px;
        font-weight: 500;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .zoho-sidebar-nav .nav-link:hover {
        background-color: #f1f5f9;
        color: var(--bs-primary);
    }
    .zoho-sidebar-nav .nav-link.active {
        background-color: var(--bs-primary) !important;
        color: #ffffff !important;
        font-weight: 600 !important;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    }
    .zoho-sidebar-nav .nav-link.active i {
        color: #ffffff !important;
    }
    .zoho-sidebar-nav .nav-link .badge {
        margin-left: auto;
        font-size: 10px;
    }

    /* Main Tab Navigation Header */
    .zoho-nav-tabs {
        gap: 6px;
    }
    .zoho-nav-tabs .nav-link {
        border: 1px solid #cbd5e1;
        background-color: #ffffff;
        color: #475569;
        padding: 6px 18px;
        font-size: 12px;
        font-weight: 600;
        border-radius: 20px !important;
        transition: all 0.2s ease;
    }
    .zoho-nav-tabs .nav-link:hover {
        background-color: #f8fafc;
        color: #0f172a;
        border-color: #94a3b8;
    }
    .zoho-nav-tabs .nav-link.active {
        background-color: var(--bs-primary) !important;
        color: #ffffff !important;
        border-color: var(--bs-primary) !important;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.15);
    }

    /* Timeline Subtabs */
    .zoho-timeline-subtabs .nav-link {
        font-size: 13px;
        font-weight: 600;
        color: #64748b;
        border-bottom: 2px solid transparent !important;
        padding: 6px 16px !important;
        background: transparent !important;
        border-top: none !important;
        border-left: none !important;
        border-right: none !important;
    }
    .zoho-timeline-subtabs .nav-link.active {
        color: var(--bs-primary) !important;
        border-bottom: 2.5px solid var(--bs-primary) !important;
    }

    /* Dotted Field Rows */
    .zoho-field-row {
        display: flex;
        align-items: center;
        padding: 8px 0;
        border-bottom: 1px dotted #e2e8f0;
    }
    .zoho-field-label {
        width: 40%;
        font-size: 12px;
        color: #64748b;
        font-weight: 500;
    }
    .zoho-field-value {
        width: 60%;
        font-size: 13px;
        color: #0f172a;
    }

    /* Zoho CRM Signature Deal Stage Chevron Pipeline Bar */
    .zoho-deal-pipeline-strip {
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        padding: 6px 16px;
    }
    .zoho-pipeline-chevron-container {
        display: flex;
        align-items: center;
        gap: 6px;
        overflow-x: auto;
        padding: 6px 4px 6px 4px;
    }
    .zoho-pipeline-step {
        flex: 1;
        min-width: 135px;
        padding: 7px 12px;
        font-size: 11px;
        font-weight: 600;
        text-align: center;
        border-radius: 4px;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        user-select: none;
        border: 1px solid #cbd5e1;
        background-color: #ffffff;
        color: #475569;
        box-shadow: 0 1px 2px rgba(0,0,0,0.02);
    }
    .zoho-pipeline-step:hover {
        background-color: #f1f5f9;
        border-color: var(--bs-primary);
        color: #0f172a;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    }
    .zoho-pipeline-step.passed {
        background-color: #f1f5f9;
        color: var(--bs-primary);
        border-color: #cbd5e1;
    }
    .zoho-pipeline-step.active {
        background-color: var(--bs-primary) !important;
        background: var(--bs-primary) !important;
        color: #ffffff !important;
        border-color: var(--bs-primary) !important;
        box-shadow: 0 3px 6px rgba(0, 0, 0, 0.15);
    }
    .zoho-pipeline-step.stage-won.active,
    .zoho-pipeline-step.stage-closed-won.active,
    .zoho-pipeline-step.active-won {
        background: linear-gradient(135deg, #15803d 0%, #22c55e 100%);
        color: #ffffff !important;
        border-color: #15803d !important;
        box-shadow: 0 3px 6px rgba(21, 128, 61, 0.25);
    }
    .zoho-pipeline-step.stage-lost.active,
    .zoho-pipeline-step.stage-closed-lost.active,
    .zoho-pipeline-step.active-lost {
        background: var(--bs-primary) !important;
        color: #ffffff !important;
        border-color: transparent !important;
        box-shadow: 0 3px 6px rgba(0, 0, 0, 0.15);
    }

    /* Deal Metric KPI Snapshot Cards */
    .deal-metric-card {
        background: #ffffff;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        padding: 12px 14px;
        transition: all 0.2s ease;
        box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        height: 100% !important;
        min-height: 72px;
    }
    .deal-metric-card:hover {
        border-color: #cbd5e1;
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        transform: translateY(-1px);
    }
    .deal-metric-icon {
        width: 38px;
        height: 38px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        flex-shrink: 0;
    }

    /* Timeline Stream Styles */
    .zoho-timeline-container {
        position: relative;
        padding-left: 20px;
    }
    .zoho-timeline-date-header {
        font-size: 11px;
        font-weight: 700;
        color: #475569;
        background: #f1f5f9;
        padding: 2px 10px;
        border-radius: 12px;
        display: inline-block;
        margin-bottom: 14px;
        border: 1px solid #cbd5e1;
    }
    .zoho-timeline-event {
        position: relative;
        padding-left: 28px;
        padding-bottom: 20px;
    }
    .zoho-timeline-line {
        position: absolute;
        left: 11px;
        top: 24px;
        bottom: 0;
        width: 2px;
        background: #e2e8f0;
    }
    .zoho-timeline-event:last-child .zoho-timeline-line {
        display: none;
    }
    .zoho-timeline-icon {
        position: absolute;
        left: 0;
        top: 2px;
        width: 24px;
        height: 24px;
        border-radius: 50%;
        background: #ffffff;
        border: 1px solid #cbd5e1;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 11px;
        color: #64748b;
    }

    /* Odoo Table UI Overrides */
    .table.odoo-table {
        margin-bottom: 0;
    }
    .table.odoo-table th {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        color: #475569;
        background-color: #f8fafc;
        border-bottom: 1.5px solid #cbd5e1;
        padding: 10px 12px;
        font-weight: 700;
    }
    .table.odoo-table td {
        padding: 10px 12px;
        vertical-align: top;
        border-bottom: 1px solid #f1f5f9;
    }

    /* Odoo Table Input Underlines (Referenced from Lead Quotations) */
    .table.odoo-table .odoo-table-input {
        border: none !important;
        border-bottom: 1px solid #cbd5e1 !important;
        background: transparent !important;
        border-radius: 0 !important;
        padding: 4px 2px !important;
        width: 100%;
        font-size: 13px !important;
        transition: border-color 0.2s ease-in-out;
    }
    .table.odoo-table .odoo-table-input:hover {
        border-bottom-color: #94a3b8 !important;
    }
    .table.odoo-table .odoo-table-input:focus {
        border-bottom-color: var(--bs-primary) !important;
        outline: none !important;
        box-shadow: none !important;
    }

    /* Borderless Select2 theme custom override for Odoo Table */
    .table.odoo-table .select2-container--bootstrap-5 .select2-selection {
        border: none !important;
        border-bottom: 1px solid #ced4da !important;
        border-radius: 0 !important;
        background-color: transparent !important;
        padding-left: 2px !important;
        height: auto !important;
        min-height: 28px !important;
        box-shadow: none !important;
    }
    .table.odoo-table .select2-container--bootstrap-5 .select2-selection:focus,
    .table.odoo-table .select2-container--bootstrap-5.select2-container--focus .select2-selection {
        border-bottom-color: var(--bs-primary) !important;
        box-shadow: none !important;
    }
    .table.odoo-table .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
        padding-left: 0 !important;
        font-size: 13px !important;
        color: #212529 !important;
        line-height: 26px !important;
    }

    /* Hide number input spinners for clean right alignment */
    .table.odoo-table input[type="number"]::-webkit-outer-spin-button,
    .table.odoo-table input[type="number"]::-webkit-inner-spin-button {
        -webkit-appearance: none !important;
        margin: 0 !important;
    }
    .table.odoo-table input[type="number"] {
        -moz-appearance: textfield !important;
    }
</style>
@endpush

@php
    $isQuotationTabActive = request()->has('create_quotation') || request()->has('edit_quotation') || request()->has('quotation_id') || old('form_type') === 'quotation_create' || old('form_type') === 'quotation_edit';
    $isSalesOrdersTabActive = request()->has('sales_orders_tab');
    
    // Stages array with probabilities dynamically built from DealStatus master
    if (isset($dealStatuses) && $dealStatuses->isNotEmpty()) {
        $allStages = [];
        foreach ($dealStatuses as $st) {
            $prob = $st->probability ?? match(strtolower($st->name)) {
                'qualification' => 10,
                'needs analysis' => 30,
                'proposal' => 60,
                'negotiation' => 80,
                'won', 'closed won' => 100,
                'lost', 'closed lost' => 0,
                default => 50,
            };
            $allStages[$st->name] = $prob;
        }
    } else {
        $allStages = [
            'Qualification'  => 10,
            'Needs Analysis' => 30,
            'Proposal'       => 60,
            'Negotiation'    => 80,
            'Won'            => 100,
            'Lost'           => 0,
        ];
    }
    
    $currentStageKey = $deal->stage;
    if ($currentStageKey === 'New') $currentStageKey = 'Qualification';
    if ($currentStageKey === 'Qualified') $currentStageKey = 'Needs Analysis';
    if ($currentStageKey === 'Closed Won') $currentStageKey = 'Won';
    if ($currentStageKey === 'Closed Lost') $currentStageKey = 'Lost';

    $stageOrder = array_keys($allStages);
    $currentIndex = array_search($currentStageKey, $stageOrder);
    if ($currentIndex === false) $currentIndex = 0;

    $expectedRevenue = $deal->estimated_value * ($deal->probability / 100);
@endphp

@section('content')
    @php
        $tenantSettings = is_array(tenant()?->settings) ? tenant()->settings : [];
        $isQuotationAutoApprove = ($tenantSettings['quotation_approval_policy'] ?? 'approval_required') === 'auto_approve';
    @endphp

    <!-- Hidden Stage Change Form -->
    <form id="dealStageForm" action="{{ route('crm.deals.updateStage', $deal) }}" method="POST" style="display: none;">
        @csrf
        @method('PATCH')
        <input type="hidden" name="stage" id="dealStageInput">
    </form>

    <!-- Outer Card Container matching Zoho CRM Layout -->
    <div class="card border-0 shadow-sm bg-white d-flex flex-column zoho-lead-card-container d-print-block" style="height: calc(100vh - 195px); min-height: 550px; overflow: hidden; border-radius: 6px;">
        
        <!-- ==================== STICKY TOP HEADER BANNER ==================== -->
        <div class="zoho-header-banner p-3 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-3 d-print-none" style="flex-shrink: 0; background-color: #ffffff; z-index: 100;">
            <div class="d-flex align-items-center">
                <!-- Deal Profile Avatar with Initials -->
                <div class="zoho-avatar bg-soft-primary text-primary fs-5 fw-bold me-3 text-uppercase shadow-sm d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; border-radius: 6px; border: 1px solid rgba(30,64,175,0.15); font-family: 'Inter', sans-serif;">
                    {{ strtoupper(substr($deal->title, 0, 1)) }}
                </div>
                
                <!-- Title & Badges -->
                <div>
                    <div class="d-flex align-items-center flex-wrap gap-2">
                        <h4 class="fw-bold text-dark mb-0 fs-16" style="font-family: 'Inter', sans-serif;">
                            {{ $deal->title }}
                        </h4>
                        
                        @php
                            $stageColors = [
                                'New'            => 'info',
                                'Qualified'      => 'primary',
                                'Qualification'  => 'info',
                                'Needs Analysis' => 'primary',
                                'Proposal'       => 'warning',
                                'Negotiation'    => 'purple',
                                'Won'            => 'success',
                                'Closed Won'     => 'success',
                                'Lost'           => 'secondary',
                                'Closed Lost'    => 'secondary',
                            ];
                            $badgeColor = $stageColors[$deal->stage] ?? 'primary';
                        @endphp
                        <span class="badge bg-soft-{{ $badgeColor }} text-{{ $badgeColor }} border border-{{ $badgeColor }}-subtle px-2.5 py-1 fs-10 fw-bold">
                            {{ $deal->stage }} ({{ $deal->probability }}%)
                        </span>
                        
                        @if($deal->account)
                            <span class="badge bg-soft-secondary text-secondary px-2 py-0.5 fs-10 fw-semibold text-truncate d-inline-block align-middle ms-1" style="max-width: 180px;" title="{{ $deal->account->name }}">
                                <i class="feather-briefcase me-1"></i>{{ $deal->account->name }}
                            </span>
                        @endif
                    </div>
                    
                    <!-- Subhead Details -->
                    <div class="mt-1 d-flex align-items-center gap-3 fs-11 text-muted">
                        <span><strong class="text-dark">{{ __('crm.deal_no') }}:</strong> <span class="font-monospace text-primary fw-bold">{{ $deal->deal_number }}</span></span>
                        <span><strong class="text-dark">{{ __('crm.deal_owner') }}:</strong> {{ $deal->owner?->name ?: ($deal->user?->name ?: __('crm.unassigned')) }}</span>
                        @if($deal->contact)
                            <span><strong class="text-dark">{{ __('crm.contact_person') }}:</strong> {{ $deal->contact->name }}</span>
                        @endif
                    </div>
                </div>
            </div>
            
            <!-- Right Action Buttons Toolbar -->
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <a href="{{ route('crm.deals.index') }}" class="btn btn-xs btn-outline-secondary fw-bold py-1 px-2 rounded bg-white text-dark border-secondary d-inline-flex align-items-center" title="{{ __('crm.back_to_deal') }}" style="font-size: 13px;">
                    <i class="feather-arrow-left"></i>
                </a>


                @php
                    $hasAcceptedQuotation = $deal->quotations->contains(fn($q) => in_array($q->status, ['Accepted', 'Converted', 'Won']));
                    $isDealWon = in_array(strtolower((string)$deal->stage), ['won', 'closed won']);
                    $hasCustomer = !empty($deal->account?->customer_id) && $isDealWon;
                    $acceptedQuote = $deal->quotations->firstWhere('status', 'Accepted') ?: ($deal->quotations->firstWhere('status', 'Converted') ?: $activeQuotation);
                @endphp

                <button type="button" class="btn btn-xs btn-primary fw-bold py-1 px-2.5 rounded shadow-2xs d-inline-flex align-items-center text-white btn-open-deal-followup-offcanvas" data-bs-toggle="offcanvas" data-bs-target="#dealFollowupOffcanvas">
                    <i class="feather-calendar me-1"></i> + {{ __('crm.followup') }}
                </button>

                @if($deal->quotations->isEmpty())
                    <a href="{{ route('crm.deals.show', ['deal' => $deal->id, 'create_quotation' => 1]) }}" class="btn btn-xs btn-outline-primary fw-bold py-1 px-3 rounded shadow-sm d-inline-flex align-items-center" style="font-size: 11px;">
                        <i class="feather-file-plus me-1"></i> + {{ __('crm.new_quotation') }}
                    </a>
                @endif

                @if($hasAcceptedQuotation && !$hasCustomer)
                    <a href="{{ route('crm.deals.showConvertForm', $deal->id) }}" class="btn btn-xs btn-warning text-dark fw-bold py-1 px-3 rounded shadow-sm d-inline-flex align-items-center" style="font-size: 11px;">
                        <i class="feather-user-check me-1"></i> {{ __('crm.convert_to_customer') }}
                    </a>
                @endif

                @if($hasCustomer)
                    <span class="badge bg-soft-success text-success fw-bold px-2.5 py-1.5 fs-11 me-1">
                        <i class="feather-check-circle me-1"></i> {{ __('crm.customer_converted') }}
                    </span>
                    @if($acceptedQuote && in_array($acceptedQuote->status, ['Accepted', 'Converted', 'Won']))
                        <a href="{{ route('sales.orders.create', ['quotation_id' => $acceptedQuote->id]) }}" class="btn btn-xs btn-success fw-bold py-1 px-3 rounded shadow-sm d-inline-flex align-items-center" style="font-size: 11px;">
                            <i class="feather-shopping-cart me-1"></i> {{ __('crm.convert_to_sales_order') }}
                        </a>
                    @endif
                @endif


                <!-- Action Dropdown -->
                <x-ui.action-dropdown id="dealProfileActionsDropdown">
                    <li>
                        <a class="dropdown-item py-2 btn-open-deal-followup-offcanvas" href="javascript:void(0)" data-bs-toggle="offcanvas" data-bs-target="#dealFollowupOffcanvas" data-mode="log_note" style="white-space: normal; max-width: 250px;">
                            <i class="feather-calendar me-1.5 text-muted"></i> {{ __('crm.log_interaction_next_followup') }}
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item py-2 btn-open-deal-followup-offcanvas" href="javascript:void(0)" data-bs-toggle="offcanvas" data-bs-target="#dealFollowupOffcanvas" data-mode="schedule" style="white-space: normal; max-width: 250px;">
                            <i class="feather-clock me-1.5 text-muted"></i> {{ __('crm.schedule_next_activity_btn') }}
                        </a>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <a class="dropdown-item py-2" href="{{ route('crm.deals.edit', $deal) }}" style="white-space: normal; max-width: 250px;">
                            <i class="feather-edit me-1.5 text-muted"></i> {{ __('crm.edit_deal_details') }}
                        </a>
                    </li>
                </x-ui.action-dropdown>
                
                <!-- Pagination Arrows -->
                <div class="d-flex align-items-center ms-1 border rounded px-1 py-0.5 bg-white">
                    @if($prevDeal)
                        <a href="{{ route('crm.deals.show', $prevDeal) }}" class="btn btn-xs btn-link text-dark p-1 border-0 d-inline-flex align-items-center justify-content-center" title="{{ __('crm.previous_deal') }}">
                            <i class="feather-chevron-left fs-12"></i>
                        </a>
                    @else
                        <button class="btn btn-xs btn-link p-1 border-0 d-inline-flex align-items-center justify-content-center text-muted opacity-50" disabled>
                            <i class="feather-chevron-left fs-12"></i>
                        </button>
                    @endif

                    @if($nextDeal)
                        <a href="{{ route('crm.deals.show', $nextDeal) }}" class="btn btn-xs btn-link text-dark p-1 border-0 d-inline-flex align-items-center justify-content-center" title="{{ __('crm.next_deal') }}">
                            <i class="feather-chevron-right fs-12"></i>
                        </a>
                    @else
                        <button class="btn btn-xs btn-link p-1 border-0 d-inline-flex align-items-center justify-content-center text-muted opacity-50" disabled>
                            <i class="feather-chevron-right fs-12"></i>
                        </button>
                    @endif
                </div>
            </div>
        </div>

        <!-- ==================== ZOHO CRM DEALS CHEVRON PIPELINE PROGRESS STRIP ==================== -->
        <div class="zoho-deal-pipeline-strip d-print-none">
            <div class="zoho-pipeline-chevron-container">
                @foreach($allStages as $stg => $prob)
                    @php
                        $stgIndex = array_search($stg, $stageOrder);
                        $stepClass = 'upcoming';
                        $stgSlug = \Illuminate\Support\Str::slug($stg);
                        
                        if ($stg === $currentStageKey) {
                            if ($stg === 'Won') $stepClass = 'active stage-won active-won';
                            elseif ($stg === 'Lost') $stepClass = 'active stage-lost active-lost';
                            else $stepClass = 'active stage-' . $stgSlug;
                        } elseif ($currentStageKey !== 'Lost' && $stgIndex < $currentIndex) {
                            $stepClass = 'passed';
                        }
                        $stgTitleTrans = __('crm.stages.' . $stg);
                        if ($stgTitleTrans === 'crm.stages.' . $stg) {
                            $stgTitleTrans = $stg;
                        }
                    @endphp
                    <div class="zoho-pipeline-step {{ $stepClass }}" onclick="submitDealStage('{{ $stg }}')" title="Click to update deal stage to {{ $stgTitleTrans }} ({{ $prob }}%)">
                        @if($stepClass === 'passed')
                            <i class="feather-check-circle fs-11"></i>
                        @elseif(str_contains($stepClass, 'active'))
                            <i class="feather-disc fs-11"></i>
                        @else
                            <i class="feather-circle fs-10 opacity-50"></i>
                        @endif
                        <span>{{ $stgTitleTrans }} ({{ $prob }}%)</span>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Flash Toast Messages -->
        @if(session('success'))
            <x-ui.toast :auto="true" title="{{ session('success') }}" type="success" delay="5000" />
        @endif

        @if(session('error'))
            <x-ui.toast :auto="true" title="{{ session('error') }}" type="error" delay="6000" />
        @endif

        @if($errors->any())
            <x-ui.toast :auto="true" title="{{ $errors->first() }}" type="error" delay="6000" />
        @endif

        <!-- ==================== TWO-COLUMN FLEX CONTENT ==================== -->
        <div class="d-flex flex-grow-1 overflow-hidden" style="min-height: 0;">
            
            <!-- Left Sidebar Menu (STICKY RELATED LIST) -->
            <div class="zoho-sidebar-col border-end bg-white d-print-none h-100 overflow-auto" style="width: 210px; flex-shrink: 0; user-select: none;">
                <div class="p-3">
                    <h6 class="text-uppercase fw-bold text-muted mb-3" style="font-size: 10px; letter-spacing: 0.8px;">{{ __('crm.related_list_navigation') }}</h6>
                    <ul class="nav flex-column zoho-sidebar-nav gap-1" id="zohoSidebarLinks">
                        <li class="nav-item">
                            <a href="#sectionDealInfo" class="nav-link active">
                                <i class="feather-info fs-13 text-muted"></i> {{ __('crm.deal_information') }}
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#sectionDealProducts" class="nav-link">
                                <i class="feather-box fs-13 text-muted"></i> {{ __('crm.product_interest') }}
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#sectionCustomerCard" class="nav-link">
                                <i class="feather-users fs-13 text-muted"></i> {{ __('crm.customer_account_contact_info') }}
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#sectionNotes" class="nav-link">
                                <i class="feather-grid fs-13 text-muted"></i> {{ __('crm.notes_requirement_summary') }}
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#sectionQuotations" class="nav-link">
                                <i class="feather-file-text fs-13 text-muted"></i> {{ __('crm.quotation_proposals') }}
                                <span class="badge bg-soft-secondary text-muted border">{{ $deal->quotations->count() }}</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#sectionQuotationHistory" class="nav-link">
                                <i class="feather-git-commit fs-13 text-muted"></i> {{ __('crm.quotation_revision_history') }}
                                <span class="badge bg-soft-secondary text-muted border">{{ $deal->quotations->count() }}</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#sectionSalesOrders" class="nav-link">
                                <i class="feather-shopping-cart fs-13 text-muted"></i> {{ __('crm.sales_orders') }}
                                <span class="badge bg-soft-secondary text-muted border">{{ $deal->salesOrders->count() }}</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#subtab-interactions" class="nav-link">
                                <i class="feather-calendar fs-13 text-muted"></i> {{ __('crm.activities') }}
                                <span class="badge bg-soft-secondary text-muted border">{{ $followups->count() }}</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#subtab-history" class="nav-link">
                                <i class="feather-clock fs-13 text-muted"></i> {{ __('crm.timeline_audit') }}
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#sectionDocuments" class="nav-link">
                                <i class="feather-paperclip fs-13 text-muted"></i> {{ __('crm.lead_documents') }}
                                <span class="badge bg-soft-secondary text-muted border">{{ $leadDocuments->count() }}</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Right Content Scrollable Column -->
            <div class="zoho-main-col h-100 overflow-auto flex-grow-1" style="scroll-behavior: smooth; background-color: #f8fafc;" id="zohoMainScrollable">
                
                <!-- Sticky Top Tab Row -->
                <div class="d-flex align-items-center justify-content-between border-bottom px-3 py-2 flex-wrap gap-2 sticky-top" style="z-index: 90; background-color: #f8fafc;">
                    <ul class="nav nav-pills zoho-nav-tabs" id="zohoDealTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link px-3 py-1 fw-bold fs-12 {{ (!$isQuotationTabActive && !$isSalesOrdersTabActive) ? 'active' : '' }}" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview-pane" type="button" role="tab">
                                <i class="feather-grid me-1"></i>{{ __('crm.overview') }}
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link px-3 py-1 fw-bold fs-12 {{ $isQuotationTabActive ? 'active' : '' }}" id="quotations-tab" data-bs-toggle="tab" data-bs-target="#quotations-pane" type="button" role="tab">
                                <i class="feather-file-text me-1"></i>{{ __('crm.quotation_proposals') }} ({{ $deal->quotations->count() }})
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link px-3 py-1 fw-bold fs-12 {{ $isSalesOrdersTabActive ? 'active' : '' }}" id="salesorders-tab" data-bs-toggle="tab" data-bs-target="#salesorders-pane" type="button" role="tab">
                                <i class="feather-shopping-cart me-1"></i>{{ __('crm.sales_orders') }} ({{ $deal->salesOrders->count() }})
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link px-3 py-1 fw-bold fs-12" id="timeline-tab" data-bs-toggle="tab" data-bs-target="#timeline-pane" type="button" role="tab">
                                <i class="feather-clock me-1"></i>{{ __('crm.timeline_audit') }}
                            </button>
                        </li>
                    </ul>

                    <div class="d-flex align-items-center text-muted fs-11 fw-medium" style="font-family: 'Inter', sans-serif;">
                        <i class="feather-clock me-1.5 text-muted fs-12"></i> 
                        {{ __('crm.last_update') }} : {{ $deal->updated_at ? $deal->updated_at->diffForHumans() : __('crm.recently') }}
                    </div>
                </div>

                <!-- Main Scrollable Tab Content View -->
                <div class="pt-2 px-3 pb-3 tab-content" id="zohoDealTabsContent">
                    
                    <!-- ==================== TAB 1: OVERVIEW PANE ==================== -->
                    <div class="tab-pane fade show {{ (!$isQuotationTabActive && !$isSalesOrdersTabActive) ? 'active' : '' }}" id="overview-pane" role="tabpanel">
                        
                        <!-- ZOHO DEAL KPI METRICS CARDS STRIP -->
                        <div class="row g-3 mb-3 align-items-stretch">
                            <div class="col-md-3 col-sm-6 d-flex">
                                <div class="deal-metric-card d-flex align-items-center gap-3 w-100">
                                    <div class="deal-metric-icon bg-soft-success text-success">
                                        <i class="feather-dollar-sign"></i>
                                    </div>
                                     <div class="min-w-0 flex-grow-1">
                                        <span class="fs-11 text-muted text-uppercase fw-bold d-block text-truncate">{{ __('crm.estimated_deal_value') }}</span>
                                        <span class="fs-14 fw-extrabold text-dark d-block">{{ format_currency($deal->estimated_value) }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6 d-flex">
                                <div class="deal-metric-card d-flex align-items-center gap-3 w-100">
                                    <div class="deal-metric-icon bg-soft-primary text-primary">
                                        <i class="feather-pie-chart"></i>
                                    </div>
                                    <div class="min-w-0 flex-grow-1">
                                        <span class="fs-11 text-muted text-uppercase fw-bold d-block text-truncate">{{ __('crm.expected_revenue') }}</span>
                                        <span class="fs-14 fw-extrabold text-primary d-block">{{ format_currency($expectedRevenue) }} <span class="fs-10 text-muted">({{ $deal->probability }}%)</span></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6 d-flex">
                                <div class="deal-metric-card d-flex align-items-center gap-3 w-100">
                                    <div class="deal-metric-icon bg-soft-warning text-warning">
                                        <i class="feather-calendar"></i>
                                    </div>
                                    <div class="min-w-0 flex-grow-1">
                                        <span class="fs-11 text-muted text-uppercase fw-bold d-block text-truncate">{{ __('crm.target_closing_date') }}</span>
                                        <span class="fs-14 fw-extrabold text-dark d-block">{{ $deal->closing_date ? $deal->closing_date->format('d M Y') : 'Not Set' }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6 d-flex">
                                <div class="deal-metric-card d-flex align-items-center gap-3 w-100 overflow-hidden" style="min-width: 0;">
                                    <div class="deal-metric-icon bg-soft-purple text-purple flex-shrink-0">
                                        <i class="feather-briefcase"></i>
                                    </div>
                                    <div class="min-w-0 flex-grow-1 overflow-hidden">
                                        <span class="fs-11 text-muted text-uppercase fw-bold d-block text-truncate">{{ __('crm.customer_account') }}</span>
                                        <span class="fs-13 fw-extrabold text-dark text-truncate d-block" title="{{ $deal->account ? $deal->account->name : ($linkedLead ? ($linkedLead->company_name ?: $linkedLead->contact_person) : 'N/A') }}">
                                            {{ $deal->account ? $deal->account->name : ($linkedLead ? ($linkedLead->company_name ?: $linkedLead->contact_person) : 'N/A') }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- AI DEAL HEALTH & INTELLIGENCE CARD -->
                        @php
                            $isSynced = !empty($deal->health_synced_at);
                            $riskVal = $isSynced ? ucfirst(strtolower($deal->risk_level ?: 'Low')) : 'Not Synced';
                            $riskBadgeStyle = match($riskVal) {
                                'High' => 'bg-danger text-white',
                                'Medium' => 'bg-warning text-dark',
                                'Low' => 'bg-success text-white',
                                default => 'bg-secondary text-white',
                            };
                            $scoreVal = $isSynced ? ($deal->health_score ?: 'N/A') : 'Not Synced';
                            if (is_numeric($scoreVal)) {
                                $scoreVal .= '%';
                            }
                        @endphp
                        <div class="card border shadow-sm mb-3" style="border-radius: 6px; border-color: #cbd5e1 !important; background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);">
                            <div class="card-body p-3">
                                <div class="d-flex justify-content-between align-items-center pb-2 border-bottom mb-3">
                                    <h5 class="fs-13 text-dark fw-bold mb-0 d-flex align-items-center gap-1.5">
                                        <i class="feather-cpu text-primary fs-16"></i>
                                        <span>{{ __('crm.ai_deal_health') }}</span>
                                    </h5>
                                    <div class="d-flex align-items-center gap-2">
                                        <a href="https://love14-deal-health-scoring.hf.space/auth/login?user_id={{ auth()->id() ?? 1 }}&next={{ urlencode(url()->current()) }}" 
                                           id="googleAuthBadge" 
                                           target="_blank" 
                                           class="badge bg-secondary text-white text-decoration-none px-2.5 py-1 fs-11 fw-bold d-inline-flex align-items-center"
                                           title="Google OAuth Connection Status">
                                            <i class="feather-loader spin me-1"></i>Checking Gmail Auth...
                                        </a>
                                        <button type="button" id="btnSyncHealth" class="btn btn-xs btn-outline-primary fw-bold px-2.5 py-1 fs-11 rounded-1">
                                            <i class="feather-refresh-cw me-1"></i>{{ __('crm.sync_ai_health') }}
                                        </button>
                                        <button type="button" id="btnGenerateDraft" class="btn btn-xs btn-primary fw-bold px-2.5 py-1 fs-11 rounded-1">
                                            <i class="feather-mail me-1"></i>{{ __('crm.generate_ai_draft') }}
                                        </button>
                                    </div>
                                </div>

                                <div class="row g-3 align-items-center mb-3">
                                    <div class="col-md-4">
                                        <div class="p-2.5 rounded border bg-white d-flex align-items-center justify-content-between">
                                            <div>
                                                <span class="fs-11 text-muted fw-bold d-block text-uppercase">{{ __('crm.deal_health_score') }}</span>
                                                <span class="fs-18 fw-extrabold text-dark" id="healthScoreDisplay">{{ $scoreVal === 'Not Synced' ? __('crm.not_synced') : $scoreVal }}</span>
                                            </div>
                                            <span class="badge {{ $riskBadgeStyle }} px-2.5 py-1 fs-11 fw-bold" id="riskLevelDisplay">
                                                {{ $isSynced ? ($riskVal . ' Risk') : __('crm.not_synced') }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="p-2.5 rounded border bg-white">
                                            <span class="fs-11 text-muted fw-bold d-block text-uppercase">{{ __('crm.client_sentiment') }}</span>
                                            <span class="fs-13 fw-bold text-dark" id="sentimentDisplay">
                                                <i class="feather-smile text-primary me-1"></i>{{ $isSynced ? ($deal->sentiment_score ?: 'Neutral') : __('crm.not_synced') }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="p-2.5 rounded border bg-white">
                                            <span class="fs-11 text-muted fw-bold d-block text-uppercase">{{ __('crm.last_ai_sync') }}</span>
                                            <span class="fs-12 fw-semibold text-muted" id="syncedAtDisplay">
                                                <i class="feather-clock me-1"></i>{{ $isSynced ? $deal->health_synced_at->diffForHumans() : __('crm.never_synced') }}
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <div class="p-3 rounded border bg-soft-info border-info-subtle">
                                    <div class="fw-bold text-info-emphasis fs-12 mb-1 d-flex align-items-center gap-1">
                                        <i class="feather-zap me-1"></i>{{ __('crm.ai_recommended_action') }}
                                    </div>
                                    <div class="fs-12 text-dark fw-medium" id="nextActionDisplay">
                                        {{ $isSynced ? ($deal->next_best_action ?: 'No specific action recommended by AI.') : __('crm.sync_ai_health_notice') }}
                                    </div>
                                </div>

                                <div id="syncDiagnosticNotice" class="alert alert-light border fs-11 text-muted p-2 mt-2.5 mb-0 d-none">
                                    <i class="feather-info text-primary me-1"></i><span id="syncDiagnosticText"></span>
                                </div>
                            </div>
                        </div>

                        <!-- Deal Information Card -->
                        <div class="card border shadow-sm mb-3" style="border-radius: 4px; border-color: #e2e8f0 !important; background-color: #ffffff;" id="sectionDealInfo">
                            <div class="card-body p-3">
                                <div class="d-flex justify-content-between align-items-center pb-2 border-bottom mb-3">
                                     <h5 class="zoho-section-title fs-13 text-dark fw-bold mb-0"><i class="feather-info text-info me-1.5"></i>{{ __('crm.deal_information_controls') }}</h5>
                                </div>
                                <div class="row g-0">
                                    <div class="col-md-6 pe-md-4">
                                        <div class="zoho-field-row">
                                            <div class="zoho-field-label">{{ __('crm.project_deal_title') }}</div>
                                            <div class="zoho-field-value text-dark fw-bold text-break">{{ $deal->title }}</div>
                                        </div>

                                        <div class="zoho-field-row">
                                            <div class="zoho-field-label">{{ __('crm.deal_no') }}</div>
                                            <div class="zoho-field-value text-primary font-monospace fw-bold">{{ $deal->deal_number }}</div>
                                        </div>

                                        <div class="zoho-field-row">
                                            <div class="zoho-field-label">{{ __('crm.account_company') }}</div>
                                            <div class="zoho-field-value text-dark fw-bold text-break">
                                                @if($deal->account)
                                                    <a href="{{ route('crm.accounts.show', $deal->account) }}" class="text-primary hover-underline text-break" title="{{ $deal->account->name }}">{{ $deal->account->name }}</a>
                                                @elseif($linkedLead)
                                                    <a href="{{ route('crm.leads.show', $linkedLead->id) }}" class="text-primary hover-underline text-break" title="{{ $linkedLead->company_name ?: $linkedLead->contact_person }}">
                                                        {{ $linkedLead->company_name ?: $linkedLead->contact_person }}
                                                    </a>
                                                @else
                                                    —
                                                @endif
                                            </div>
                                        </div>

                                        <div class="zoho-field-row">
                                            <div class="zoho-field-label">{{ __('crm.contact_person') }}</div>
                                            <div class="zoho-field-value text-dark">{{ $deal->contact ? $deal->contact->name : ($linkedLead ? ($linkedLead->contact_person ?: $linkedLead->company_name) : '—') }}</div>
                                        </div>

                                        <div class="zoho-field-row">
                                            <div class="zoho-field-label">{{ __('crm.designation_role') }}</div>
                                            <div class="zoho-field-value text-dark">{{ ($deal->contact && $deal->contact->designation) ? $deal->contact->designation : '—' }}</div>
                                        </div>

                                        <div class="zoho-field-row">
                                            <div class="zoho-field-label">{{ __('crm.target_closing_date') }}</div>
                                            <div class="zoho-field-value text-dark">{{ $deal->closing_date ? $deal->closing_date->format('d/m/Y') : '—' }}</div>
                                        </div>
                                    </div>

                                    <div class="col-md-6 ps-md-4">
                                        <div class="zoho-field-row">
                                            <div class="zoho-field-label">{{ __('crm.closing_probability') }}</div>
                                            <div class="zoho-field-value text-info fw-bold">{{ $deal->probability }}%</div>
                                        </div>

                                        <div class="zoho-field-row">
                                            <div class="zoho-field-label">{{ __('crm.estimated_revenue_val') }}</div>
                                            <div class="zoho-field-value text-dark fw-bold">{{ format_currency($deal->estimated_value) }}</div>
                                        </div>

                                        <div class="zoho-field-row">
                                            <div class="zoho-field-label">{{ __('crm.actual_realized_revenue') }}</div>
                                            <div class="zoho-field-value text-success fw-bold">{{ format_currency($deal->actual_value) }}</div>
                                        </div>

                                        <div class="zoho-field-row">
                                            <div class="zoho-field-label">{{ __('crm.lead_source') }}</div>
                                            <div class="zoho-field-value text-dark">{{ ($deal->lead_source && !in_array($deal->lead_source, ['Select an Option', 'Select an option', 'Select Option'], true)) ? (\Illuminate\Support\Facades\Lang::has('crm.sources.' . $deal->lead_source) ? __('crm.sources.' . $deal->lead_source) : $deal->lead_source) : '—' }}</div>
                                        </div>

                                        <div class="zoho-field-row">
                                            <div class="zoho-field-label">{{ __('crm.deal_owner_manager') }}</div>
                                            <div class="zoho-field-value text-dark fw-bold">{{ $deal->owner?->name ?: ($deal->user?->name ?: __('crm.unassigned')) }}</div>
                                        </div>

                                        <div class="zoho-field-row">
                                            <div class="zoho-field-label">{{ __('crm.lead_owner') }}</div>
                                            <div class="zoho-field-value text-primary fw-bold">{{ $deal->account?->owner?->name ?: __('crm.unassigned') }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Deal Interested Products & Quantities Section -->
                        @php
                            $rawItems = $deal->product_items ?: [];
                            if (empty($rawItems) && !empty($deal->product_ids)) {
                                foreach ($deal->product_ids as $pid) {
                                    $rawItems[] = ['product_id' => (int)$pid, 'quantity' => 1.0];
                                }
                            }
                            if (empty($rawItems) && $linkedLead) {
                                $rawItems = $linkedLead->product_items ?: [];
                                if (empty($rawItems) && !empty($linkedLead->product_ids)) {
                                    foreach ($linkedLead->product_ids as $pid) {
                                        $rawItems[] = ['product_id' => (int)$pid, 'quantity' => 1.0];
                                    }
                                }
                            }
                        @endphp
                        @if(!empty($rawItems))
                            @php
                                $pIds = array_column($rawItems, 'product_id');
                                $dealProductsMap = \App\Domains\Inventory\Models\Product::whereIn('id', $pIds)->get()->keyBy('id');
                            @endphp
                            @if($dealProductsMap->isNotEmpty())
                                <div class="card border shadow-sm mb-3" style="border-radius: 4px; border-color: #e2e8f0 !important; background-color: #ffffff;" id="sectionDealProducts">
                                    <div class="card-body p-3">
                                        <div class="d-flex justify-content-between align-items-center pb-2 border-bottom mb-3">
                                            <h5 class="zoho-section-title fs-13 text-dark fw-bold mb-0">
                                                <i class="feather-box text-primary me-1.5"></i>{{ __('crm.interested_products_quantity') }}
                                            </h5>
                                            <span class="badge bg-soft-primary text-primary fs-11 fw-semibold">{{ count($rawItems) }} {{ __('crm.products_selected') }}</span>
                                        </div>
                                        <div class="table-responsive">
                                            <table class="table table-sm table-bordered align-middle mb-0 fs-13">
                                                <thead class="table-light text-muted">
                                                    <tr>
                                                        <th>{{ __('crm.product') }}</th>
                                                        <th>SKU</th>
                                                        <th class="text-center">{{ __('crm.qty') }}</th>
                                                        <th class="text-end">{{ __('crm.unit_price') }} ({{ active_currency_symbol() }})</th>
                                                        <th class="text-end">{{ __('crm.total_estimated_value') }} ({{ active_currency_symbol() }})</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @php $grandProductTotal = 0; @endphp
                                                    @foreach($rawItems as $item)
                                                        @php
                                                            $pObj = $dealProductsMap->get($item['product_id']);
                                                            if (!$pObj) continue;
                                                            $pQty = floatval($item['quantity'] ?? 1);
                                                            $pPrice = floatval($pObj->selling_price ?: $pObj->unit_cost ?: 0);
                                                            $lineVal = $pQty * $pPrice;
                                                            $grandProductTotal += $lineVal;
                                                        @endphp
                                                        <tr>
                                                            <td class="fw-bold text-dark">
                                                                <a href="{{ route('inventory.products.show', $pObj) }}" class="text-dark hover-underline" target="_blank">{{ $pObj->name }}</a>
                                                            </td>
                                                            <td class="font-monospace text-muted">{{ $pObj->sku }}</td>
                                                            <td class="text-center fw-bold text-primary">{{ number_format($pQty, 0) }} {{ $pObj->uom?->code ?? 'Pcs' }}</td>
                                                            <td class="text-end">{{ format_currency($pPrice) }}</td>
                                                            <td class="text-end fw-bold text-success">{{ format_currency($lineVal) }}</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                                @if($grandProductTotal > 0)
                                                    <tfoot class="table-light fw-bold">
                                                        <tr>
                                                            <td colspan="4" class="text-end text-uppercase fs-12">{{ __('crm.total_estimated_product_value') }}:</td>
                                                            <td class="text-end text-success fs-14">{{ format_currency($grandProductTotal) }}</td>
                                                        </tr>
                                                    </tfoot>
                                                @endif
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        @endif

                        <!-- CUSTOMER ACCOUNT & CONTACT QUICK CARD -->
                        <div class="card border shadow-sm mb-3" style="border-radius: 4px; border-color: #e2e8f0 !important; background-color: #ffffff;" id="sectionCustomerCard">
                            <div class="card-body p-3">
                                <h5 class="fs-13 text-dark fw-bold mb-3"><i class="feather-users text-primary me-1.5"></i>{{ __('crm.customer_account_contact_info') }}</h5>
                                <div class="row g-3">
                                    <div class="col-md-6 border-end">
                                        <div class="p-3 bg-light-50 rounded border">
                                            <div class="fw-bold text-dark fs-14 mb-1">
                                                {{ $deal->account ? $deal->account->name : ($linkedLead ? ($linkedLead->company_name ?: $linkedLead->contact_person) : __('crm.no_account_linked')) }}
                                            </div>
                                            <div class="fs-12 text-muted mb-2"><i class="feather-map-pin me-1"></i>{{ $deal->account ? ($deal->account->billing_address ?: __('crm.billing_address_not_added')) : ($linkedLead ? ($linkedLead->address ?: __('crm.address_not_added')) : '—') }}</div>
                                            @php
                                                $accPhone = $deal->account?->phone ?: ($linkedLead?->company_phone ?: $linkedLead?->phone);
                                                $accEmail = $deal->account?->email ?: ($linkedLead?->company_email ?: $linkedLead?->email);
                                            @endphp
                                            @if($accPhone)
                                                <div class="fs-12 text-dark"><i class="feather-phone me-1 text-muted"></i>{{ $accPhone }}</div>
                                            @endif
                                            @if($accEmail)
                                                <div class="fs-12 text-primary"><i class="feather-mail me-1 text-muted"></i>{{ $accEmail }}</div>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="p-3 bg-light-50 rounded border">
                                            @php
                                                $cName = $deal->contact?->name ?: ($linkedLead?->contact_person ?: $linkedLead?->company_name);
                                                $cTitle = $deal->contact?->designation ?: ($deal->contact?->role ?: ($linkedLead?->designation ?: __('crm.primary_contact')));
                                                $cPhone = $deal->contact?->phone ?: ($linkedLead?->phone ?: $linkedLead?->company_phone);
                                                $cEmail = $deal->contact?->email ?: ($linkedLead?->email ?: $linkedLead?->company_email);
                                            @endphp
                                            <div class="fw-bold text-dark fs-14 mb-1">{{ $cName ?: __('crm.no_contact_person') }}</div>
                                            <div class="fs-12 text-muted mb-2">{{ $cTitle ?: '—' }}</div>
                                            @if($cPhone)
                                                <div class="fs-12 text-dark"><i class="feather-phone me-1 text-muted"></i>{{ $cPhone }}</div>
                                            @endif
                                            @if($cEmail)
                                                <div class="fs-12 text-primary"><i class="feather-mail me-1 text-muted"></i>{{ $cEmail }}</div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Requirements / Notes Details Card (Click to Edit like Lead) -->
                        <div class="card border shadow-sm mb-3" style="border-radius: 4px; border-color: #e2e8f0 !important; background-color: #ffffff;" id="sectionNotes">
                            <div class="card-body p-3">
                                <div class="d-flex align-items-center justify-content-between pb-2 border-bottom mb-3">
                                    <h5 class="zoho-section-title fs-13 text-dark fw-bold mb-0" style="font-family: 'Inter', sans-serif; border-bottom: none;">
                                        <i class="feather-file-text text-primary me-1.5"></i>{{ __('crm.requirements_summary') }}
                                    </h5>
                                    <span class="text-muted fs-11 d-none d-sm-inline-block"><i class="feather-info me-1 text-primary"></i>{{ __('crm.click_box_to_edit') }}</span>
                                </div>

                                @php
                                    $currentReq = !empty($deal->notes) ? $deal->notes : (!empty($linkedLead?->requirement) ? $linkedLead->requirement : '');
                                @endphp

                                <!-- View Mode (Clickable to Edit) -->
                                <div id="viewDealRequirementBlock">
                                    @if (!empty($currentReq))
                                        <div class="position-relative requirement-clickable-box p-3 rounded shadow-2xs" onclick="enableDealRequirementEdit()" title="Click anywhere to edit requirement" style="cursor: pointer; background: #f8fafc; border: 1px solid #cbd5e1; transition: all 0.2s ease;">
                                            <div class="d-flex align-items-start justify-content-between gap-3">
                                                <div class="text-dark fs-13 flex-grow-1" style="white-space: pre-wrap; line-height: 1.6; font-family: 'Inter', sans-serif;" id="viewDealRequirementText">{{ $currentReq }}</div>
                                                <span class="badge bg-white text-primary border shadow-2xs px-2.5 py-1.5 fs-11 flex-shrink-0 edit-hint-badge" style="border-color: #cbd5e1 !important; transition: all 0.2s ease;">
                                                    <i class="feather-edit-2 me-1"></i>Click to Edit
                                                </span>
                                            </div>
                                        </div>
                                    @else
                                        <div class="position-relative requirement-empty-box p-4 rounded text-center cursor-pointer" onclick="enableDealRequirementEdit()" title="{{ __('crm.click_to_add_requirements') }}" style="cursor: pointer; background: #f8fafc; border: 1px dashed #cbd5e1; transition: all 0.2s ease;">
                                            <div class="avatar-text avatar-md bg-soft-primary text-primary rounded-circle mx-auto mb-2">
                                                <i class="feather-edit-3 fs-5"></i>
                                            </div>
                                            <h6 class="fw-bold text-dark fs-13 mb-1">{{ __('crm.no_requirements_specified') }}</h6>
                                            <p class="text-muted fs-12 mb-0">{{ __('crm.click_to_add_requirements') }}</p>
                                        </div>
                                    @endif
                                </div>

                                <!-- Edit Mode -->
                                <div id="editDealRequirementBlock" style="display: none;">
                                    <form id="ajaxDealRequirementForm" action="{{ route('crm.deals.updateRequirement', $deal->id) }}" method="POST">
                                        @csrf
                                        @method('PATCH')
                                        <div class="mb-2">
                                            <textarea name="notes" id="dealRequirementInput" rows="4" class="form-control form-control-sm shadow-2xs fs-13" placeholder="{{ __('crm.requirements_placeholder') }}" style="border-color: var(--bs-primary); border-radius: 6px; font-family: 'Inter', sans-serif;" oninput="updateDealReqCharCount(this)">{{ old('notes', $currentReq) }}</textarea>
                                        </div>
                                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                            <span class="text-muted fs-11">
                                                <i class="feather-corner-down-left me-1"></i>Press <kbd class="bg-light text-dark border px-1 py-0.5 rounded fs-10">Ctrl + Enter</kbd> or click save
                                            </span>
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="text-muted fs-11 me-2" id="dealReqCharCounter">0 chars</span>
                                                <button type="button" class="btn btn-xs btn-light border px-3 py-1.5 fw-bold rounded" onclick="cancelDealRequirementEdit()">{{ __('crm.cancel_caps') }}</button>
                                                <button type="submit" id="btnSaveDealRequirement" class="btn btn-xs btn-primary px-3 py-1.5 fw-bold shadow-2xs text-white rounded d-inline-flex align-items-center" style="background-color: var(--bs-primary); border-color: var(--bs-primary);">
                                                    <i class="feather-check me-1"></i> {{ __('crm.save_requirement') }}
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <!-- Documents Card -->
                        <div class="card border shadow-sm mb-3" style="border-radius: 4px; border-color: #e2e8f0 !important; background-color: #ffffff;" id="sectionDocuments">
                            <div class="card-body p-3">
                                <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
                                    <h6 class="fw-bold text-dark mb-0 fs-13"><i class="feather-folder me-2 text-primary"></i>{{ __('crm.attached_documents_files') }}</h6>
                                    <form action="{{ route('crm.deals.documents.upload', $deal->id) }}" method="POST" enctype="multipart/form-data" class="m-0 p-0" id="dealDocUploadForm">
                                        @csrf
                                        <button type="button" class="btn btn-xs btn-primary fw-bold" onclick="document.getElementById('dealDocInput').click();" style="background-color: #1e40af; border-color: #1e40af;"><i class="feather-upload me-1"></i> {{ __('crm.upload') }}</button>
                                        <input type="file" name="documents[]" id="dealDocInput" onchange="if (this.files &amp;&amp; this.files.length > 0) { document.getElementById('dealDocUploadForm').submit(); }" multiple style="display: none;">
                                    </form>
                                </div>

                                @if($leadDocuments->isEmpty())
                                    <div class="text-center py-4 border border-dashed rounded bg-light-subtle">
                                        <i class="feather-file-text fs-24 text-muted mb-1 d-block opacity-50"></i>
                                        <div class="text-muted fs-12">{{ __('crm.no_documents_attached_deal') }}</div>
                                    </div>

                                @else
                                    <div class="row g-3">
                                        @foreach($leadDocuments as $document)
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
                                                                <svg xmlns="http://www.w3.org/2000/svg" width="38" height="38" viewBox="0 0 36 36" fill="none">
                                                                    <rect width="36" height="36" rx="6" fill="#107C41"/>
                                                                    <path d="M10.5 9L16.5 18L10.5 27H14.25L18 21.375L21.75 27H25.5L19.5 18L25.5 9H21.75L18 14.625L14.25 9H10.5Z" fill="white"/>
                                                                </svg>
                                                            @elseif($fileTypeCategory === 'word')
                                                                <svg xmlns="http://www.w3.org/2000/svg" width="38" height="38" viewBox="0 0 36 36" fill="none">
                                                                    <rect width="36" height="36" rx="6" fill="#185ABD"/>
                                                                    <path d="M9 9L12.75 27H15.75L18 17.25L20.25 27H23.25L27 9H23.7L21.45 20.7L19.05 9H16.95L14.55 20.7L12.3 9H9Z" fill="white"/>
                                                                </svg>
                                                            @elseif($fileTypeCategory === 'pdf')
                                                                <svg xmlns="http://www.w3.org/2000/svg" width="38" height="38" viewBox="0 0 36 36" fill="none">
                                                                    <rect width="36" height="36" rx="6" fill="#E11D48"/>
                                                                    <text x="50%" y="58%" dominant-baseline="middle" text-anchor="middle" fill="white" font-size="12" font-weight="900" font-family="'Inter', sans-serif" letter-spacing="0.5">PDF</text>
                                                                </svg>
                                                            @elseif($fileTypeCategory === 'image')
                                                                <svg xmlns="http://www.w3.org/2000/svg" width="38" height="38" viewBox="0 0 36 36" fill="none">
                                                                    <rect width="36" height="36" rx="6" fill="#0891B2"/>
                                                                    <circle cx="13" cy="13" r="3" fill="white"/>
                                                                    <path d="M7.5 27L14.25 18.75L18.75 24.75L24 16.5L28.5 27H7.5Z" fill="white"/>
                                                                </svg>
                                                            @elseif($fileTypeCategory === 'archive')
                                                                <svg xmlns="http://www.w3.org/2000/svg" width="38" height="38" viewBox="0 0 36 36" fill="none">
                                                                    <rect width="36" height="36" rx="6" fill="#D97706"/>
                                                                    <path d="M18 6V21M18 21L12 15M18 21L24 15M9 27H27" stroke="white" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                                                                </svg>
                                                            @else
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
                                                            <button type="button" class="btn btn-xs btn-soft-danger rounded-circle p-0 d-inline-flex align-items-center justify-content-center border" style="width: 30px; height: 30px; border-color: #fecdd3 !important;" title="Delete Document" onclick="confirmAction({ title: 'Delete Document', message: 'Are you sure you want to delete this document?', variant: 'danger', confirmText: 'Delete' }, function() { document.getElementById('deleteDocForm_{{ $document->id }}').submit(); })">
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
                    </div>

                    <!-- ==================== TAB 2: QUOTATIONS & PROPOSALS PANE ==================== -->
                    <div class="tab-pane fade {{ $isQuotationTabActive ? 'show active' : '' }}" id="quotations-pane" role="tabpanel">
                        
                        @if (request()->has('create_quotation') || old('form_type') === 'quotation_create')
                            <!-- CREATE QUOTATION INLINE FORM -->
                            <div class="card border shadow-sm mb-4" style="border-radius: 4px; border-color: #e2e8f0 !important; background-color: #ffffff;" id="sectionQuotations">
                                <div class="card-body p-4">
                                    <form action="{{ route('crm.quotations.store') }}" method="POST" id="quotationForm" novalidate>
                                        @csrf
                                        <input type="hidden" name="crm_deal_id" value="{{ $deal->id }}">
                                        <input type="hidden" name="form_type" value="quotation_create">

                                        <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">
                                            <h5 class="fw-bold text-dark mb-0 fs-16"><i class="feather-file-plus text-primary me-2"></i>{{ __('crm.new_quotation_title') }}</h5>
                                            <a href="{{ route('crm.deals.show', $deal->id) }}" class="btn btn-sm btn-light border">{{ __('crm.cancel') }}</a>
                                        </div>

                                        <div class="row g-4 mb-4 fs-13 text-dark">
                                            <div class="col-md-6">
                                                <x-ui.odoo-form-ui type="input" :label="__('crm.customer_account')" name="_customer_display"
                                                    :value="$deal->account ? $deal->account->name : ($deal->contact ? $deal->contact->name : ($linkedLead ? ($linkedLead->company_name ?: $linkedLead->contact_person) : 'N/A'))"
                                                    readonly="true"
                                                    style="font-weight: bold; color: var(--bs-primary); background-color: #f8f9fa;" />

                                                <x-ui.odoo-form-ui type="input" :label="__('crm.contact_email')" name="email" :value="old('email', $deal->contact ? $deal->contact->email : ($linkedLead ? ($linkedLead->company_email ?: $linkedLead->email) : ''))" :errorText="$errors->first('email')" />
                                                <x-ui.odoo-form-ui type="input" :label="__('crm.contact_phone')" name="phone" :value="old('phone', $deal->contact ? $deal->contact->phone : ($linkedLead ? ($linkedLead->company_phone ?: $linkedLead->phone) : ''))" :errorText="$errors->first('phone')" />
                                            </div>
                                            <div class="col-md-6">
                                                <x-ui.odoo-form-ui type="input" :label="__('crm.quotation_number')" name="quotation_number"
                                                    :value="old('quotation_number', $nextQuotationNumber)" readonly="true"
                                                    style="font-weight: bold; color: #495057;"
                                                    :errorText="$errors->first('quotation_number')" />

                                                <x-ui.odoo-form-ui type="input" inputType="date" :label="__('crm.quotation_date')" name="quotation_date"
                                                    :value="old('quotation_date', date('Y-m-d'))" :errorText="$errors->first('quotation_date')" />

                                                <x-ui.odoo-form-ui type="input" inputType="date" :label="__('crm.expiration_date')" name="expiry_date"
                                                    :value="old('expiry_date', date('Y-m-d', strtotime('+30 days')))" :errorText="$errors->first('expiry_date')" />

                                                @if(!$isQuotationAutoApprove)
                                                    <x-ui.odoo-form-ui type="select" :label="__('crm.initial_status')" name="status" :required="true" :errorText="$errors->first('status')">
                                                         <option value="Draft" @selected(old('status') === 'Draft')>{{ __('crm.draft') }}</option>
                                                         <option value="Pending Approval" @selected(old('status') === 'Pending Approval')>{{ __('crm.sent_for_approval') }}</option>
                                                     </x-ui.odoo-form-ui>
                                                @endif
                                            </div>
                                        </div>

                                        <!-- Order Lines Table -->
                                        <div class="border-top pt-4">
                                            <h5 class="fw-bold text-dark mb-3 fs-14">{{ __('crm.order_lines') }}</h5>
                                            <div class="table-responsive">
                                                <x-ui.odoo-form-ui type="table" id="itemsTable">
                                                    <thead>
                                                        <tr>
                                                            <th style="width: 38%;">{{ __('crm.product_description') }}</th>
                                                            <th class="text-end" style="width: 10%;">{{ __('crm.qty') }}</th>
                                                            <th class="text-end" style="width: 18%;">{{ __('crm.unit_price') }} ({{ active_currency_symbol() }})</th>
                                                            <th class="text-end" style="width: 12%;">{{ __('crm.taxes') }} (%)</th>
                                                            <th class="text-end pe-3" style="width: 17%;">{{ __('crm.amount') }}</th>
                                                            <th class="text-center" style="width: 5%;"></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <!-- Dynamically generated rows -->
                                                    </tbody>
                                                </x-ui.odoo-form-ui>
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
                                                    <x-ui.odoo-form-ui type="textarea" :label="__('crm.notes')" name="notes" rows="2" placeholder="Notes for internal view..." :errorText="$errors->first('notes')">{{ old('notes') }}</x-ui.odoo-form-ui>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="d-flex justify-content-between py-1 border-bottom">
                                                    <span class="text-muted fw-semibold">{{ __('crm.subtotal') }}</span>
                                                    <span class="fw-bold text-dark" id="calcSubtotal">{{ active_currency_symbol() }}0.00</span>
                                                </div>
                                                <div class="d-flex justify-content-between py-1 border-bottom">
                                                    <span class="text-muted fw-semibold">{{ __('crm.taxes_label') }}</span>
                                                    <span class="fw-bold text-dark" id="calcTax">{{ active_currency_symbol() }}0.00</span>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                                    <span class="text-muted fw-semibold me-2">{{ __('crm.discount_colon') }}</span>
                                                    <x-ui.odoo-form-ui type="input" name="discount" id="discountInput" inputType="number" :value="old('discount', 0)" min="0" step="0.01" class="text-end fw-bold" :errorText="$errors->first('discount')" />
                                                </div>
                                                <div class="d-flex justify-content-between py-2 fs-15 border-bottom bg-light-50 px-2 rounded mt-1.5">
                                                    <span class="text-dark fw-bold">{{ __('crm.grand_total') }}</span>
                                                    <span class="fw-extrabold text-primary" id="calcTotal">{{ active_currency_symbol() }}0.00</span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                                            <a href="{{ route('crm.deals.show', $deal->id) }}" class="btn btn-md btn-light border py-2 px-4 shadow-sm fs-12">{{ __('crm.discard') }}</a>
                                            <button type="submit" class="btn btn-md btn-primary py-2 px-5 fw-bold shadow-sm fs-12">{{ __('crm.save_quotation') }}</button>
                                        </div>
                                    </form>
                                </div>
                            </div>

                        @elseif ((request()->has('edit_quotation') || old('form_type') === 'quotation_edit') && $activeQuotation && $activeQuotation->status !== 'Accepted')
                            <!-- EDIT QUOTATION INLINE FORM -->
                            <div class="card border shadow-sm mb-4" style="border-radius: 4px; border-color: #e2e8f0 !important; background-color: #ffffff;" id="sectionQuotations">
                                <div class="card-body p-4">
                                    <form action="{{ route('crm.quotations.update', $activeQuotation->id) }}" method="POST" id="quotationForm" novalidate>
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="crm_deal_id" value="{{ $deal->id }}">
                                        <input type="hidden" name="form_type" value="quotation_edit">

                                        <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">
                                            <h5 class="fw-bold text-dark mb-0 fs-16"><i class="feather-edit text-warning me-2"></i>{{ __('crm.edit_quotation_title') }}: {{ $activeQuotation->quotation_number }}</h5>
                                            <a href="{{ route('crm.deals.show', ['deal' => $deal->id, 'quotation_id' => $activeQuotation->id]) }}" class="btn btn-sm btn-light border">{{ __('crm.cancel') }}</a>
                                        </div>

                                        <div class="row g-4 mb-4 fs-13 text-dark">
                                            <div class="col-md-6">
                                                <x-ui.odoo-form-ui type="input" :label="__('crm.customer_account')" name="_customer_display"
                                                    :value="$deal->account ? $deal->account->name : ($deal->contact ? $deal->contact->name : ($linkedLead ? ($linkedLead->company_name ?: $linkedLead->contact_person) : 'N/A'))"
                                                    readonly="true"
                                                    style="font-weight: bold; color: var(--bs-primary); background-color: #f8f9fa;" />

                                                <x-ui.odoo-form-ui type="input" :label="__('crm.contact_email')" name="email" :value="old('email', $activeQuotation->email ?: ($deal->contact ? $deal->contact->email : ($linkedLead ? ($linkedLead->company_email ?: $linkedLead->email) : '')))" :errorText="$errors->first('email')" />
                                                <x-ui.odoo-form-ui type="input" :label="__('crm.contact_phone')" name="phone" :value="old('phone', $activeQuotation->phone ?: ($deal->contact ? $deal->contact->phone : ($linkedLead ? ($linkedLead->company_phone ?: $linkedLead->phone) : '')))" :errorText="$errors->first('phone')" />
                                            </div>
                                            <div class="col-md-6">
                                                <x-ui.odoo-form-ui type="input" :label="__('crm.quotation_number')" name="quotation_number"
                                                    :value="$activeQuotation->quotation_number" readonly="true"
                                                    style="font-weight: bold; color: #495057;"
                                                    :errorText="$errors->first('quotation_number')" />

                                                <x-ui.odoo-form-ui type="input" inputType="date" :label="__('crm.quotation_date')" name="quotation_date"
                                                    :value="old('quotation_date', $activeQuotation->quotation_date ? \Illuminate\Support\Carbon::parse($activeQuotation->quotation_date)->format('Y-m-d') : date('Y-m-d'))" :errorText="$errors->first('quotation_date')" />

                                                <x-ui.odoo-form-ui type="input" inputType="date" :label="__('crm.expiration_date')" name="expiry_date"
                                                    :value="old('expiry_date', $activeQuotation->expiry_date ? \Illuminate\Support\Carbon::parse($activeQuotation->expiry_date)->format('Y-m-d') : '')" :errorText="$errors->first('expiry_date')" />

                                                @if(!$isQuotationAutoApprove)
                                                    <x-ui.odoo-form-ui type="select" :label="__('crm.status')" name="status" :required="true" :errorText="$errors->first('status')">
                                                         <option value="Draft" @selected(old('status', $activeQuotation->status) === 'Draft')>{{ __('crm.draft') }}</option>
                                                         <option value="Pending Approval" @selected(old('status', $activeQuotation->status) === 'Pending Approval' || old('status', $activeQuotation->status) === 'Rejected' || old('status', $activeQuotation->status) === 'Quotation Rework' || old('status', $activeQuotation->status) === 'Approved' || old('status', $activeQuotation->status) === 'Declined')>{{ __('crm.sent_for_approval') }}</option>
                                                     </x-ui.odoo-form-ui>
                                                @endif
                                            </div>
                                        </div>

                                        <!-- Order Lines Table -->
                                        <div class="border-top pt-4">
                                            <h5 class="fw-bold text-dark mb-3 fs-14">{{ __('crm.order_lines') }}</h5>
                                            <div class="table-responsive">
                                                <x-ui.odoo-form-ui type="table" id="itemsTable">
                                                    <thead>
                                                        <tr>
                                                            <th style="width: 38%;">{{ __('crm.product_description') }}</th>
                                                            <th class="text-end" style="width: 10%;">{{ __('crm.qty') }}</th>
                                                            <th class="text-end" style="width: 18%;">{{ __('crm.unit_price') }} ({{ active_currency_symbol() }})</th>
                                                            <th class="text-end" style="width: 12%;">{{ __('crm.taxes') }} (%)</th>
                                                            <th class="text-end pe-3" style="width: 17%;">{{ __('crm.amount') }}</th>
                                                            <th class="text-center" style="width: 5%;"></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <!-- Dynamically generated rows -->
                                                    </tbody>
                                                </x-ui.odoo-form-ui>
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
                                                    <x-ui.odoo-form-ui type="textarea" :label="__('crm.notes')" name="notes" rows="2" placeholder="Notes for internal view..." :errorText="$errors->first('notes')">{{ old('notes', $activeQuotation->notes) }}</x-ui.odoo-form-ui>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="d-flex justify-content-between py-1 border-bottom">
                                                    <span class="text-muted fw-semibold">{{ __('crm.subtotal') }}</span>
                                                    <span class="fw-bold text-dark" id="calcSubtotal">{{ active_currency_symbol() }}0.00</span>
                                                </div>
                                                <div class="d-flex justify-content-between py-1 border-bottom">
                                                    <span class="text-muted fw-semibold">{{ __('crm.taxes_label') }}</span>
                                                    <span class="fw-bold text-dark" id="calcTax">{{ active_currency_symbol() }}0.00</span>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                                    <span class="text-muted fw-semibold me-2">{{ __('crm.discount_colon') }}</span>
                                                    <x-ui.odoo-form-ui type="input" name="discount" id="discountInput" inputType="number" :value="old('discount', $activeQuotation->discount)" min="0" step="0.01" class="text-end fw-bold" :errorText="$errors->first('discount')" />
                                                </div>
                                                <div class="d-flex justify-content-between py-2 fs-15 border-bottom bg-light-50 px-2 rounded mt-1.5">
                                                    <span class="text-dark fw-bold">{{ __('crm.grand_total') }}</span>
                                                    <span class="fw-extrabold text-primary" id="calcTotal">{{ active_currency_symbol() }}0.00</span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                                            <a href="{{ route('crm.deals.show', ['deal' => $deal->id, 'quotation_id' => $activeQuotation->id]) }}" class="btn btn-md btn-light border py-2 px-4 shadow-sm fs-12">{{ __('crm.discard') }}</a>
                                            <button type="submit" class="btn btn-md btn-primary py-2 px-5 fw-bold shadow-sm fs-12">{{ __('crm.save_changes') }}</button>
                                        </div>
                                    </form>
                                </div>
                            </div>

                        @elseif($activeQuotation)
                            <!-- ACTIVE QUOTATION DETAILS CARD VIEW -->
                            @if (in_array($activeQuotation->status, ['Rejected', 'Declined']))
                                <div class="alert alert-danger border-danger border-start border-4 shadow-sm mb-3 d-print-none" role="alert" style="background-color: #fff5f5;">
                                    <div class="d-flex align-items-start">
                                        <div class="avatar-text avatar-md bg-danger text-white me-3 mt-0.5 rounded-circle flex-shrink-0 d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                                            <i class="feather-x-circle fs-18"></i>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="alert-heading fw-bold text-danger mb-1"><i class="feather-alert-triangle me-1"></i> Quotation Rejected by Client</h6>
                                            <p class="fs-13 text-dark mb-0">
                                                <strong>Rejection Reason / Client Feedback:</strong> 
                                                <span class="text-danger fw-semibold">{{ $activeQuotation->rejection_reason ?: 'No specific reason provided.' }}</span>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            @endif
                            <div class="card border shadow-sm mb-4" style="border-radius: 4px; border-color: #e2e8f0 !important; background-color: #ffffff;" id="sectionQuotations">
                                <div class="card-body p-4">
                                    <div class="d-flex justify-content-between align-items-center pb-3 border-bottom mb-4 flex-wrap gap-2">
                                        <div>
                                            <h4 class="fw-bold text-dark mb-0 fs-16">{{ __('crm.quotation') }} {{ $activeQuotation->quotation_number }}</h4>
                                            <span class="badge bg-light text-dark border font-monospace mt-1">Revision {{ $activeQuotation->revision_number }}</span>
                                        </div>

                                        <div class="d-flex align-items-center flex-wrap gap-2">
                                            <!-- WhatsApp Action Button -->
                                            <button type="button" 
                                                    class="action-dropdown-btn action-btn-wa btn-open-send-quote-wa-modal" 
                                                    data-quotation-id="{{ $activeQuotation->id }}" 
                                                    data-quotation-num="{{ $activeQuotation->quotation_number }}" 
                                                    data-client-phone="{{ $activeQuotation->phone ?: ($deal->contact?->phone ?: ($linkedLead?->company_phone ?: $linkedLead?->phone)) }}" 
                                                    data-deal-title="{{ addslashes($deal->title) }}"
                                                    title="Send PDF via WhatsApp" data-bs-toggle="tooltip">
                                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path fill-rule="evenodd" clip-rule="evenodd" d="M12 2C6.477 2 2 6.477 2 12c0 2.15.68 4.14 1.838 5.776L2.5 21.5l3.876-1.303A9.957 9.957 0 0012 22c5.523 0 10-4.477 10-10S17.523 2 12 2zm0 18a7.96 7.96 0 01-4.086-1.125l-.293-.174-2.295.771.785-2.238-.191-.304A7.96 7.96 0 014 12c0-4.418 3.582-8 8-8s8 3.582 8 8-3.582 8-8 8z" fill="#25D366"/><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.447-.521.149-.174.198-.298.298-.497.099-.198.05-.372-.025-.521-.075-.149-.669-1.612-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414-.074-.124-.272-.198-.57-.347z" fill="#25D366"/></svg>
                                            </button>

                                            <!-- Send Email Action Button -->
                                            <button type="button" 
                                                    class="action-dropdown-btn action-btn-email btn-open-send-quote-modal" 
                                                    data-quotation-id="{{ $activeQuotation->id }}" 
                                                    data-quotation-num="{{ $activeQuotation->quotation_number }}" 
                                                    data-client-email="{{ $activeQuotation->email ?: ($deal->contact?->email ?: ($linkedLead?->company_email ?: $linkedLead?->email)) }}" 
                                                    data-deal-title="{{ addslashes($deal->title) }}"
                                                    title="Send PDF via Email" data-bs-toggle="tooltip">
                                                <i class="feather-mail text-primary fs-14"></i>
                                            </button>

                                            <!-- Download PDF Action Button -->
                                            <a href="{{ route('crm.quotations.download', $activeQuotation->id) }}" 
                                               class="action-dropdown-btn action-btn-pdf" 
                                               title="Download PDF Document" data-bs-toggle="tooltip">
                                                <i class="feather-download text-danger fs-14"></i>
                                            </a>

                                            <!-- Status / Workflow Action Buttons -->
                                            @if ($activeQuotation->status === 'Draft' || $activeQuotation->status === 'Quotation Rework')
                                                <form action="{{ route('crm.quotations.updateStatus', $activeQuotation->id) }}" method="POST" class="d-inline m-0">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="status" value="Pending Approval">
                                                    <button type="submit" class="btn btn-sm btn-warning text-dark fw-bold px-2.5 py-1.5"><i class="feather-send me-1"></i>Submit Approval</button>
                                                </form>
                                            @elseif ($activeQuotation->status === 'Approved')
                                                <form action="{{ route('crm.quotations.updateStatus', $activeQuotation->id) }}" method="POST" class="d-inline m-0">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="status" value="Quotation Sent">
                                                    <button type="submit" class="btn btn-sm btn-primary fw-bold px-2.5 py-1.5"><i class="feather-send me-1"></i>Mark as Sent</button>
                                                </form>
                                            @elseif ($activeQuotation->status === 'Quotation Sent' || $activeQuotation->status === 'Sent')
                                                <form action="{{ route('crm.quotations.updateStatus', $activeQuotation->id) }}" method="POST" class="d-inline m-0">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="status" value="Accepted">
                                                    <button type="submit" class="btn btn-sm btn-success fw-bold px-2.5 py-1.5"><i class="feather-check-circle me-1"></i>Accept Quote</button>
                                                </form>
                                                <form action="{{ route('crm.quotations.updateStatus', $activeQuotation->id) }}" method="POST" class="d-inline m-0">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="status" value="Rejected">
                                                    <button type="submit" class="btn btn-sm btn-soft-danger fw-bold px-2.5 py-1.5"><i class="feather-x-circle me-1"></i>Reject</button>
                                                </form>
                                            @endif

                                            <!-- Dropdown Menu for More Options -->
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-light border fw-bold px-2.5 py-1.5 dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="{{ __('crm.more') }}">
                                                    <i class="feather-more-horizontal me-1"></i>{{ __('crm.more') }}
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end shadow border-0 fs-12">
                                                    <li>
                                                        <a class="dropdown-item" href="{{ route('crm.quotations.show', $activeQuotation->id) }}">
                                                            <i class="feather-eye me-2 text-info"></i>{{ __('crm.view_full_quotation_sheet') }}
                                                        </a>
                                                    </li>
                                                    @if ($activeQuotation->status !== 'Accepted')
                                                        <li>
                                                            <a class="dropdown-item" href="{{ route('crm.deals.show', ['deal' => $deal->id, 'edit_quotation' => 1, 'quotation_id' => $activeQuotation->id]) }}">
                                                                <i class="feather-edit-2 me-2 text-warning"></i>{{ __('crm.edit_quotation_title') }}
                                                            </a>
                                                        </li>
                                                    @endif
                                                    <li>
                                                        <a class="dropdown-item" href="{{ route('crm.quotations.download', $activeQuotation->id) }}?print=1" target="_blank">
                                                            <i class="feather-printer me-2 text-secondary"></i>{{ __('crm.print_quotation') }}
                                                        </a>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Quotation Header Grid -->
                                    <div class="row g-4 mb-4 fs-13 text-dark">
                                        <div class="col-md-6 border-end">
                                            <div class="mb-3">
                                                <label class="text-muted fs-11 text-uppercase fw-bold d-block mb-1">{{ __('crm.customer_account') }}</label>
                                                <div class="fw-bold text-dark fs-14">
                                                    {{ $deal->account ? $deal->account->name : ($deal->contact ? $deal->contact->name : ($linkedLead ? ($linkedLead->company_name ?: $linkedLead->contact_person) : 'N/A')) }}
                                                </div>
                                            </div>
                                            <div class="row">
                                                <div class="col-6">
                                                    <label class="text-muted fs-11 text-uppercase fw-bold d-block mb-1">{{ __('crm.contact_email') }}</label>
                                                    <div class="fs-12 text-primary d-flex align-items-center flex-nowrap gap-1">
                                                        <span class="fw-semibold text-truncate" title="{{ $activeQuotation->email ?: ($deal->contact ? $deal->contact->email : ($linkedLead ? ($linkedLead->company_email ?: $linkedLead->email) : '—')) }}">{{ $activeQuotation->email ?: ($deal->contact ? $deal->contact->email : ($linkedLead ? ($linkedLead->company_email ?: $linkedLead->email) : '—')) }}</span>
                                                        @if ($activeQuotation->email ?: ($deal->contact?->email ?: ($linkedLead?->company_email ?: $linkedLead?->email)))
                                                            <button type="button" 
                                                                    class="action-dropdown-btn action-btn-email btn-open-send-quote-modal ms-1 flex-shrink-0" 
                                                                    data-quotation-id="{{ $activeQuotation->id }}" 
                                                                    data-quotation-num="{{ $activeQuotation->quotation_number }}" 
                                                                    data-client-email="{{ $activeQuotation->email ?: ($deal->contact?->email ?: ($linkedLead?->company_email ?: $linkedLead?->email)) }}" 
                                                                    data-deal-title="{{ addslashes($deal->title) }}" 
                                                                    title="Send Quotation PDF via Email" data-bs-toggle="tooltip"
                                                                    style="width: 24px !important; height: 24px !important; min-width: 24px !important; border-radius: 6px !important;">
                                                                <i class="feather-mail text-primary fs-11"></i>
                                                            </button>
                                                        @endif
                                                    </div>
                                                </div>
                                                <div class="col-6">
                                                    <label class="text-muted fs-11 text-uppercase fw-bold d-block mb-1">{{ __('crm.contact_phone') }}</label>
                                                    <div class="fs-12 text-dark d-flex align-items-center flex-nowrap gap-1">
                                                        <span class="fw-semibold text-truncate">{{ $activeQuotation->phone ?: ($deal->contact ? $deal->contact->phone : ($linkedLead ? ($linkedLead->company_phone ?: $linkedLead->phone) : '—')) }}</span>
                                                        @if ($activeQuotation->phone ?: ($deal->contact?->phone ?: ($linkedLead?->company_phone ?: $linkedLead?->phone)))
                                                            <button type="button" 
                                                                    class="action-dropdown-btn action-btn-wa btn-open-send-quote-wa-modal ms-1 flex-shrink-0" 
                                                                    data-quotation-id="{{ $activeQuotation->id }}" 
                                                                    data-quotation-num="{{ $activeQuotation->quotation_number }}" 
                                                                    data-client-phone="{{ $activeQuotation->phone ?: ($deal->contact?->phone ?: ($linkedLead?->company_phone ?: $linkedLead?->phone)) }}" 
                                                                    data-deal-title="{{ addslashes($deal->title) }}" 
                                                                    title="Send Quotation PDF via WhatsApp" data-bs-toggle="tooltip"
                                                                    style="width: 24px !important; height: 24px !important; min-width: 24px !important; border-radius: 6px !important;">
                                                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none"><path fill-rule="evenodd" clip-rule="evenodd" d="M12 2C6.477 2 2 6.477 2 12c0 2.15.68 4.14 1.838 5.776L2.5 21.5l3.876-1.303A9.957 9.957 0 0012 22c5.523 0 10-4.477 10-10S17.523 2 12 2zm0 18a7.96 7.96 0 01-4.086-1.125l-.293-.174-2.295.771.785-2.238-.191-.304A7.96 7.96 0 014 12c0-4.418 3.582-8 8-8s8 3.582 8 8-3.582 8-8 8z" fill="#25D366"/><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.447-.521.149-.174.198-.298.298-.497.099-.198.05-.372-.025-.521-.075-.149-.669-1.612-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414-.074-.124-.272-.198-.57-.347z" fill="#25D366"/></svg>
                                                            </button>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6 ps-md-4">
                                            <div class="row">
                                                <div class="col-6 mb-3">
                                                    <label class="text-muted fs-11 text-uppercase fw-bold d-block mb-1">{{ __('crm.quotation_date') }}</label>
                                                    <div class="fw-semibold text-dark">{{ $activeQuotation->quotation_date ? \Illuminate\Support\Carbon::parse($activeQuotation->quotation_date)->format('d M Y') : '—' }}</div>
                                                </div>
                                                <div class="col-6 mb-3">
                                                    <label class="text-muted fs-11 text-uppercase fw-bold d-block mb-1">{{ __('crm.expiration_date') }}</label>
                                                    <div class="fw-semibold text-danger">{{ $activeQuotation->expiry_date ? \Illuminate\Support\Carbon::parse($activeQuotation->expiry_date)->format('d M Y') : '—' }}</div>
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <label class="text-muted fs-11 text-uppercase fw-bold d-block mb-1">{{ __('crm.quotation_status') }}</label>
                                                @php
                                                    $qColors = [
                                                        'Draft' => 'secondary',
                                                        'Approved' => 'info',
                                                        'Sent' => 'primary',
                                                        'Quotation Sent' => 'primary',
                                                        'Accepted' => 'success',
                                                        'Rejected' => 'danger',
                                                    ];
                                                    $qBadgeColor = $qColors[$activeQuotation->status] ?? 'secondary';
                                                @endphp
                                                <span class="badge bg-soft-{{ $qBadgeColor }} text-{{ $qBadgeColor }} border border-{{ $qBadgeColor }}-subtle px-2.5 py-1 fw-bold fs-12">
                                                    {{ $activeQuotation->status }}
                                                </span>
                                                @if ($activeQuotation->status === 'Rejected' && $activeQuotation->rejection_reason)
                                                    <div class="mt-1.5 fs-12 text-danger fw-semibold d-flex align-items-center">
                                                        <i class="feather-alert-triangle me-1 fs-13"></i>
                                                        <span><strong>Reason:</strong> {{ $activeQuotation->rejection_reason }}</span>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Order Lines Table -->
                                    <h6 class="fw-bold text-dark mb-2 fs-13">{{ __('crm.order_lines') }}</h6>
                                    <div class="table-responsive mb-4">
                                        <x-ui.odoo-form-ui type="table">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>{{ __('crm.product_description') }}</th>
                                                    <th class="text-end">{{ __('crm.qty') }}</th>
                                                    <th class="text-end">{{ __('crm.unit_price') }} ({{ active_currency_symbol() }})</th>
                                                    <th class="text-end">{{ __('crm.taxes') }} (%)</th>
                                                    <th class="text-end">{{ __('crm.amount') }} ({{ active_currency_symbol() }})</th>
                                                </tr>
                                            </thead>
                                            <tbody class="fs-13 text-dark">
                                                @forelse($activeQuotation->items as $item)
                                                    <tr>
                                                        <td>{{ $loop->iteration }}</td>
                                                        <td>
                                                            <strong class="text-dark">{{ $item->product ? $item->product->name : ($item->description ?: 'Item') }}</strong>
                                                            @if($item->product && $item->product->sku)
                                                                <span class="text-muted fs-11 font-monospace ms-1">(SKU: {{ $item->product->sku }})</span>
                                                            @endif
                                                        </td>
                                                        <td class="text-end font-monospace fw-bold">{{ $item->quantity }}</td>
                                                        <td class="text-end font-monospace">{{ format_currency($item->unit_price) }}</td>
                                                        <td class="text-end font-monospace">{{ $item->tax_rate }}%</td>
                                                        <td class="text-end font-monospace fw-bold text-success">{{ format_currency($item->total_price ?: ($item->amount ?: ($item->quantity * $item->unit_price))) }}</td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="6" class="text-center py-3 text-muted">{{ __('crm.no_line_items_quotation') }}</td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </x-ui.odoo-form-ui>
                                    </div>

                                    <!-- Totals Summary -->
                                    <div class="row pt-3 border-top text-dark fs-13">
                                        <div class="col-md-7">
                                            @if($activeQuotation->terms_conditions)
                                                <div class="mb-2">
                                                    <strong class="text-muted fs-11 text-uppercase fw-bold d-block">{{ __('crm.terms_conditions') }}:</strong>
                                                    <div class="fs-12 text-muted mt-1">{!! $activeQuotation->terms_conditions !!}</div>
                                                </div>
                                            @endif
                                        </div>
                                        <div class="col-md-5">
                                            <div class="d-flex justify-content-between py-1 border-bottom">
                                                <span class="text-muted">{{ __('crm.subtotal') }}</span>
                                                <span class="fw-bold">{{ format_currency($activeQuotation->subtotal ?? 0) }}</span>
                                            </div>
                                            <div class="d-flex justify-content-between py-1 border-bottom">
                                                <span class="text-muted">{{ __('crm.taxes_label') }}</span>
                                                <span class="fw-bold">{{ format_currency($activeQuotation->tax ?? $activeQuotation->tax_amount ?? 0) }}</span>
                                            </div>
                                            <div class="d-flex justify-content-between py-2 fs-15 border-bottom bg-light-50 px-2 rounded mt-1">
                                                <span class="fw-bold text-dark">{{ __('crm.grand_total') }}</span>
                                                <span class="fw-extrabold text-primary">{{ format_currency($activeQuotation->total_amount) }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @else
                            <div class="card border shadow-sm mb-4" style="border-radius: 4px; border-color: #e2e8f0 !important; background-color: #ffffff;" id="sectionQuotations">
                                <div class="card-body p-4 text-center">
                                    <i class="feather-file-text fs-36 text-muted mb-2 d-block opacity-50"></i>
                                    <h5 class="fw-bold text-dark fs-14">{{ __('crm.no_quotation_created_yet') }}</h5>
                                    <p class="text-muted fs-12 mb-3">{{ __('crm.create_quotation_desc') }}</p>
                                    <div class="d-flex justify-content-center">
                                        <a href="{{ route('crm.deals.show', ['deal' => $deal->id, 'create_quotation' => 1]) }}" class="btn btn-sm btn-success fw-bold px-4 py-2 d-inline-flex align-items-center justify-content-center shadow-xs" style="width: auto !important; max-width: fit-content !important;">
                                            <i class="feather-plus me-1.5 fs-13"></i>{{ __('crm.create_quotation_now') }}
                                        </a>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <!-- QUOTATION REVISION HISTORY CHIP CARDS -->
                        @if($deal->quotations->count() > 0)
                            <div class="card border shadow-sm mb-3" style="border-radius: 4px; border-color: #e2e8f0 !important; background-color: #ffffff;" id="sectionQuotationHistory">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h5 class="fs-13 text-dark fw-bold mb-0">
                                            <i class="feather-git-commit me-1.5 text-primary"></i>{{ __('crm.quotation_revision_history') }}
                                        </h5>
                                    </div>

                                    @php
                                        $revisions = $activeQuotation ? $activeQuotation->getRevisionHistory() : $deal->quotations;
                                    @endphp

                                    @if($revisions->count() > 0)
                                        <div class="d-flex flex-wrap gap-2 align-items-center">
                                            @foreach($revisions as $rev)
                                                <div class="d-flex align-items-center gap-2 p-2 border rounded bg-white" style="min-width: 170px; border-color: {{ $activeQuotation && $rev->id === $activeQuotation->id ? '#3b82f6 !important' : '#e2e8f0' }} !important; transition: all 0.2s; position: relative; {{ $activeQuotation && $rev->id === $activeQuotation->id ? 'box-shadow: 0 0 0 1px rgba(59,130,246,0.1); background-color: #f0f9ff !important;' : '' }}">
                                                    @if($activeQuotation && $rev->id === $activeQuotation->id)
                                                        <span class="position-absolute top-0 end-0 translate-middle-y badge rounded-pill bg-primary fs-8 text-uppercase px-1" style="font-size: 8px !important; margin-right: 10px;">{{ __('crm.viewing') }}</span>
                                                    @endif
                                                    <div class="avatar-text avatar-sm bg-soft-secondary text-secondary rounded-circle fw-bold d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; font-size: 10px;">
                                                        R{{ $rev->revision_number }}
                                                    </div>
                                                    <div class="d-flex flex-column fs-11" style="font-family: 'Inter', sans-serif;">
                                                        <a href="{{ route('crm.deals.show', ['deal' => $deal->id, 'quotation_id' => $rev->id]) }}" class="fw-bold text-dark text-decoration-none">
                                                            {{ $rev->quotation_number }}
                                                        </a>
                                                        <div class="d-flex align-items-center gap-1 mt-0.5">
                                                            <span class="text-muted" style="font-size: 9px;">{{ format_currency($rev->total_amount) }}</span>
                                                            @if($rev->status === 'Rejected')
                                                                <span class="badge bg-soft-danger text-danger px-1 py-0" style="font-size: 8px;">Rejected</span>
                                                            @elseif($rev->status === 'Accepted')
                                                                <span class="badge bg-soft-success text-success px-1 py-0" style="font-size: 8px;">Accepted</span>
                                                            @endif
                                                        </div>
                                                        @if($rev->status === 'Rejected' && $rev->rejection_reason)
                                                            <span class="text-danger mt-0.5 text-truncate" style="font-size: 9px; max-width: 140px;" title="{{ $rev->rejection_reason }}">
                                                                <i class="feather-info me-0.5"></i>{{ $rev->rejection_reason }}
                                                            </span>
                                                        @endif
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>

                    <!-- ==================== TAB 3: SALES ORDERS & REVENUE REALIZATION PANE ==================== -->
                    <div class="tab-pane fade {{ $isSalesOrdersTabActive ? 'show active' : '' }}" id="salesorders-pane" role="tabpanel">
                        <div class="card border shadow-sm mb-4" style="border-radius: 4px; border-color: #e2e8f0 !important; background-color: #ffffff;" id="sectionSalesOrders">
                            <div class="card-body p-4">
                                <div class="d-flex justify-content-between align-items-center pb-3 border-bottom mb-4">
                                    <div>
                                        <h5 class="fs-15 text-dark fw-bold mb-0"><i class="feather-shopping-cart text-success me-2"></i>{{ __('crm.converted_sales_orders_revenue') }}</h5>
                                        <span class="text-muted fs-12">{{ __('crm.track_sales_orders_desc') }}</span>
                                    </div>
                                    @if($activeQuotation && $activeQuotation->status === 'Accepted')
                                        <a href="{{ route('sales.orders.create', ['quotation_id' => $activeQuotation->id]) }}" class="btn btn-sm btn-success fw-bold px-3">
                                            <i class="feather-plus me-1"></i>{{ __('crm.new_sales_order') }}
                                        </a>
                                    @endif
                                </div>

                                @if($deal->salesOrders->isEmpty())
                                    <div class="text-center py-5 text-muted border border-dashed rounded bg-light-50">
                                        <i class="feather-shopping-bag fs-36 text-muted mb-2 d-block opacity-50"></i>
                                        <h6 class="fw-bold text-dark fs-13">{{ __('crm.no_sales_orders_generated') }}</h6>
                                        <p class="fs-12 text-muted max-w-md mx-auto">{{ __('crm.sales_order_convert_desc') }}</p>
                                    </div>
                                @else
                                    <div class="table-responsive">
                                        <x-ui.odoo-form-ui type="table">
                                            <thead>
                                                <tr>
                                                    <th>{{ __('crm.sales_order_num') }}</th>
                                                    <th>{{ __('crm.order_date') }}</th>
                                                    <th>{{ __('crm.customer_name') }}</th>
                                                    <th>{{ __('crm.total_amount') }} ({{ active_currency_symbol() }})</th>
                                                    <th>{{ __('crm.order_status') }}</th>
                                                    <th class="text-end pe-3">{{ __('crm.actions') }}</th>
                                                </tr>
                                            </thead>
                                            <tbody class="fs-13 text-dark">
                                                @foreach($deal->salesOrders as $so)
                                                    <tr>
                                                        <td class="font-monospace fw-bold text-primary">{{ $so->order_number }}</td>
                                                        <td>{{ $so->order_date ? \Illuminate\Support\Carbon::parse($so->order_date)->format('d/m/Y') : '—' }}</td>
                                                        <td class="fw-bold text-dark">{{ $so->customer_name }}</td>
                                                        <td class="fw-bold text-success font-monospace">{{ format_currency($so->total_amount) }}</td>
                                                        <td>
                                                            <span class="badge bg-soft-success text-success border border-success-subtle px-2 py-0.5 fw-bold">
                                                                {{ $so->status }}
                                                            </span>
                                                        </td>
                                                        <td class="text-end pe-3">
                                                            <a href="{{ route('sales.orders.show', $so) }}" class="btn btn-xs btn-soft-primary fw-bold">
                                                                <i class="feather-eye me-1"></i>{{ __('crm.view_order') }}
                                                            </a>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </x-ui.odoo-form-ui>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- ==================== TAB 4: TIMELINE & AUDIT LOG PANE ==================== -->
                    <div class="tab-pane fade" id="timeline-pane" role="tabpanel">
                        <div class="card border shadow-sm" style="border-radius: 4px; border-color: #e2e8f0 !important; background-color: #ffffff;">
                            <div class="card-body p-3">
                                
                                <!-- Subtabs Selector Row -->
                                <div class="border-bottom pb-1 mb-3">
                                    <ul class="nav nav-tabs border-bottom-0 zoho-timeline-subtabs" id="zohoTimelineSubTabs" role="tablist">
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link active py-2 px-3 border-0 bg-transparent" id="subtab-history-tab" data-bs-toggle="tab" data-bs-target="#subtab-history" type="button" role="tab">
                                                {{ __('crm.history_audit_stream') }}
                                            </button>
                                        </li>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link py-2 px-3 border-0 bg-transparent" id="subtab-interactions-tab" data-bs-toggle="tab" data-bs-target="#subtab-interactions" type="button" role="tab">
                                                {{ __('crm.interactions_scheduled_calls') }}
                                            </button>
                                        </li>
                                    </ul>
                                </div>
                                
                                <!-- Subtabs Content -->
                                <div class="tab-content" id="zohoTimelineSubTabsContent">
                                    
                                    <!-- SUBTAB 1: HISTORY TIMELINE -->
                                    <div class="tab-pane fade show active" id="subtab-history" role="tabpanel">
                                        <div class="d-flex align-items-center justify-content-between mb-4 mt-1 flex-wrap gap-2">
                                            <div class="d-flex align-items-center gap-2">
                                                <h5 class="fw-bold text-dark fs-14 mb-0">{{ __('crm.timeline_history_audit_stream') }}</h5>
                                            </div>
                                        </div>

                                        <div class="zoho-timeline-container">
                                            @php
                                                $groupedHistory = $histories->groupBy(function($item) {
                                                    return $item->created_at ? $item->created_at->format('d/m/Y') : date('d/m/Y');
                                                });
                                            @endphp

                                            @if($groupedHistory->isEmpty())
                                                <div class="text-center py-5 text-muted border border-dashed rounded bg-white fs-12">
                                                    <i class="feather-clock fs-24 mb-1.5 d-block text-muted opacity-50"></i>
                                                    {{ __('crm.no_history_events') }}
                                                </div>
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
                                                                    elseif ($item->event_type === 'status_changed' || $item->event_type === 'status_updated') $icon = 'feather-refresh-cw';
                                                                    elseif ($item->event_type === 'quotation_created') $icon = 'feather-file-text';
                                                                    elseif ($item->event_type === 'activity_scheduled') $icon = 'feather-calendar';
                                                                @endphp
                                                                <div class="zoho-timeline-icon">
                                                                    <i class="{{ $icon }}"></i>
                                                                </div>

                                                                <div class="zoho-timeline-content d-flex align-items-center justify-content-between w-100 ms-2">
                                                                    <div>
                                                                        <span class="fs-13 fw-semibold text-dark">
                                                                            {{ $item->notes ?: ucwords(str_replace('_', ' ', $item->event_type)) }}
                                                                        </span>
                                                                        @if($item->old_value || $item->new_value)
                                                                            <span class="fs-11 text-muted ms-2 bg-light px-1.5 py-0.5 rounded border">
                                                                                @if($item->old_value)
                                                                                    <del>{{ $item->old_value }}</del> <i class="feather-arrow-right mx-0.5"></i>
                                                                                @endif
                                                                                <strong class="text-success">{{ $item->new_value }}</strong>
                                                                            </span>
                                                                        @endif
                                                                        <div class="text-muted fs-11 mt-0.5">
                                                                            by {{ $item->user?->name ?: 'Demo Admin' }} {{ $item->created_at ? $item->created_at->format('d/m/Y') : '' }}
                                                                        </div>
                                                                    </div>
                                                                    <div class="zoho-timeline-time text-muted fs-11 ms-3" style="white-space: nowrap;">
                                                                        {{ $item->created_at ? $item->created_at->format('h:i A') : '' }}
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                @endforeach
                                            @endif
                                        </div>
                                    </div>

                                    <!-- SUBTAB 2: INTERACTIONS & ACTIVITIES -->
                                    <div class="tab-pane fade" id="subtab-interactions" role="tabpanel">
                                        <div class="d-flex align-items-center justify-content-between mb-4 mt-1 flex-wrap gap-2">
                                            <h5 class="fw-bold text-dark fs-14 mb-0">{{ __('crm.interactions_scheduled_activities') }}</h5>
                                        </div>

                                        @php
                                            $groupedFollowups = $followups->reject(function($item) {
                                                return $item->status === 'Rescheduled' && $item->rescheduledTo->isNotEmpty();
                                            })->groupBy(function($item) {
                                                return $item->followup_date->format('d/m/Y');
                                            });
                                        @endphp

                                        @if($groupedFollowups->isEmpty())
                                            <div class="text-center py-5 text-muted border border-dashed rounded-3 bg-light fs-12" style="border-color:#cbd5e1!important;">
                                                <i class="feather-calendar fs-28 d-block mb-2 opacity-40"></i>
                                                <span class="fw-semibold">{{ __('crm.no_activities_scheduled') }}</span><br>
                                                <span class="fs-11">{{ __('crm.click_schedule_activity') }}</span>
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
                                                            $statusLabel = __('crm.pending') ?? 'Pending';
                                                            if($item->status === 'Completed') { $statusBadgeClass = 'bg-success text-white'; $statusLabel = __('crm.connected'); }
                                                            elseif($item->status === 'Not Connected') { $statusBadgeClass = 'bg-warning text-white'; $statusLabel = __('crm.not_connected'); }
                                                            elseif($item->status === 'Cancelled') { $statusBadgeClass = 'bg-danger text-white'; $statusLabel = __('crm.cancelled'); }
                                                            elseif($item->status === 'Rescheduled') { $statusBadgeClass = 'bg-purple text-white'; $statusLabel = __('crm.reschedule'); }
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

                                                                <!-- Notes & Meeting / Calendar Buttons -->
                                                                @php
                                                                    $rawNotes = $item->notes ?? '';
                                                                    $meetUrlFromNotes = null;
                                                                    $cleanNotes = $rawNotes;
                                                                    if (preg_match('/(Google Meet:\s*)(https?:\/\/\S+)/i', $rawNotes, $m)) {
                                                                        $meetUrlFromNotes = $m[2];
                                                                        $cleanNotes = trim(preg_replace('/\n?Google Meet:\s*https?:\/\/\S+/i', '', $rawNotes));
                                                                    }
                                                                    $meetLink = $item->google_meet_link ?? $meetUrlFromNotes;
                                                                    $calEventLink = null;
                                                                    if (!empty($item->google_event_id) && !str_starts_with($item->google_event_id, 'g_evt_')) {
                                                                        $calEventLink = 'https://calendar.google.com/calendar/r';
                                                                    }
                                                                @endphp

                                                                @if($cleanNotes)
                                                                    <div class="activity-notes">
                                                                        {{ $cleanNotes }}
                                                                    </div>
                                                                @endif

                                                                @if($meetLink && $item->is_google_meet && $item->status === 'Pending')
                                                                    <div class="mt-2 d-inline-block me-2">
                                                                        <a href="{{ $meetLink }}" target="_blank"
                                                                           class="d-inline-flex align-items-center gap-1 px-3 py-1 rounded-pill text-white fw-semibold fs-11 text-decoration-none"
                                                                           style="background: linear-gradient(135deg, #1a73e8, #0d47a1); box-shadow: 0 2px 6px rgba(26,115,232,0.35);">
                                                                            <i class="feather-video me-1"></i> {{ __('crm.join_google_meet') }}
                                                                        </a>
                                                                    </div>
                                                                @endif

                                                                @if($calEventLink)
                                                                    <div class="mt-2 d-inline-block">
                                                                        <a href="{{ $calEventLink }}" target="_blank"
                                                                           class="d-inline-flex align-items-center gap-1 px-3 py-1 rounded-pill text-white fw-semibold fs-11 text-decoration-none"
                                                                           style="background: linear-gradient(135deg, #34a853, #1e7e34); box-shadow: 0 2px 6px rgba(52,168,83,0.35);">
                                                                            <i class="feather-calendar me-1"></i> {{ __('crm.view_in_google_calendar') }}
                                                                        </a>
                                                                    </div>
                                                                @endif

                                                                <!-- Attribution -->
                                                                <div class="activity-by mt-1 d-flex align-items-center flex-wrap gap-2">
                                                                    <span>
                                                                        <i class="feather-user fs-9 me-1"></i>by {{ $deal->owner?->name ?: ($linkedLead?->owner?->name ?: 'System') }} &bull; Scheduled: {{ $item->followup_date->format('d M Y, h:i A') }}
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
                                                                        <x-ui.button type="button" variant="soft-success" size="sm" icon="feather-phone-call" data-bs-toggle="modal" data-bs-target="#statusModal_{{ $item->id }}_Completed">{{ __('crm.connected') }}</x-ui.button>

                                                                        <!-- Not Connected -->
                                                                        <x-ui.button type="button" variant="soft-warning" size="sm" icon="feather-phone-off" data-bs-toggle="modal" data-bs-target="#statusModal_{{ $item->id }}_NotConnected">{{ __('crm.not_connected') }}</x-ui.button>

                                                                        <!-- Cancelled -->
                                                                        <x-ui.button type="button" variant="soft-danger" size="sm" icon="feather-x-circle" data-bs-toggle="modal" data-bs-target="#statusModal_{{ $item->id }}_Cancelled">{{ __('crm.cancelled') }}</x-ui.button>

                                                                        <!-- Reschedule -->
                                                                        <x-ui.button type="button" variant="soft-primary" size="sm" icon="feather-refresh-cw" data-bs-toggle="modal" data-bs-target="#rescheduleModal_{{ $item->id }}">{{ __('crm.reschedule') }}</x-ui.button>
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
                                                                :title="__('crm.mark_as_connected')"
                                                                size="md"
                                                                :centered="true"
                                                                :formAction="route('crm.followups.update', $item->id)"
                                                                formMethod="PUT"
                                                                :submitText="__('crm.save_mark_connected')"
                                                                :closeText="__('crm.cancel')"
                                                            >
                                                                <input type="hidden" name="status" value="Completed">
                                                                <x-ui.odoo-form-ui type="select" :label="__('crm.tag_assign_persons')" name="tagged_user_ids[]" :multiple="true" :searchable="true">
                                                                    @foreach($users as $u)
                                                                        <option value="{{ $u->id }}" @selected(in_array($u->id, $currentTaggedIds))>{{ $u->name }} ({{ $u->email }})</option>
                                                                    @endforeach
                                                                </x-ui.odoo-form-ui>
                                                                <x-ui.odoo-form-ui type="textarea" :label="__('crm.notes_discussion_summary')" name="notes" rows="3" placeholder="Enter notes or discussion outcome...">{{ $item->notes }}</x-ui.odoo-form-ui>
                                                            </x-ui.modal>

                                                            <!-- Status Modal: Not Connected -->
                                                            <x-ui.modal
                                                                :id="'statusModal_' . $item->id . '_NotConnected'"
                                                                :title="__('crm.mark_as_not_connected')"
                                                                size="md"
                                                                :centered="true"
                                                                :formAction="route('crm.followups.update', $item->id)"
                                                                formMethod="PUT"
                                                                :submitText="__('crm.save_status')"
                                                                :closeText="__('crm.cancel')"
                                                            >
                                                                <input type="hidden" name="status" value="Not Connected">
                                                                <x-ui.odoo-form-ui type="select" :label="__('crm.tag_assign_persons')" name="tagged_user_ids[]" :multiple="true" :searchable="true">
                                                                    @foreach($users as $u)
                                                                        <option value="{{ $u->id }}" @selected(in_array($u->id, $currentTaggedIds))>{{ $u->name }} ({{ $u->email }})</option>
                                                                    @endforeach
                                                                </x-ui.odoo-form-ui>
                                                                <x-ui.odoo-form-ui type="textarea" :label="__('crm.notes_reason')" name="notes" rows="3" placeholder="Reason / notes...">{{ $item->notes }}</x-ui.odoo-form-ui>
                                                            </x-ui.modal>

                                                            <!-- Status Modal: Cancelled -->
                                                            <x-ui.modal
                                                                :id="'statusModal_' . $item->id . '_Cancelled'"
                                                                :title="__('crm.cancel_activity')"
                                                                size="md"
                                                                :centered="true"
                                                                :formAction="route('crm.followups.update', $item->id)"
                                                                formMethod="PUT"
                                                                :submitText="__('crm.confirm_cancel')"
                                                                :closeText="__('crm.cancel')"
                                                            >
                                                                <input type="hidden" name="status" value="Cancelled">
                                                                <x-ui.odoo-form-ui type="select" :label="__('crm.tag_assign_persons')" name="tagged_user_ids[]" :multiple="true" :searchable="true">
                                                                    @foreach($users as $u)
                                                                        <option value="{{ $u->id }}" @selected(in_array($u->id, $currentTaggedIds))>{{ $u->name }} ({{ $u->email }})</option>
                                                                    @endforeach
                                                                </x-ui.odoo-form-ui>
                                                                <x-ui.odoo-form-ui type="textarea" :label="__('crm.cancellation_note')" name="notes" rows="3" placeholder="Reason for cancellation...">{{ $item->notes }}</x-ui.odoo-form-ui>
                                                            </x-ui.modal>

                                                            <!-- Reschedule Modal -->
                                                            <x-ui.modal
                                                                :id="'rescheduleModal_' . $item->id"
                                                                :title="__('crm.reschedule_activity')"
                                                                size="md"
                                                                :centered="true"
                                                                :formAction="route('crm.followups.update', $item->id)"
                                                                formMethod="PUT"
                                                                :submitText="__('crm.confirm_reschedule')"
                                                                :closeText="__('crm.cancel')"
                                                            >
                                                                <input type="hidden" name="is_reschedule" value="1">
                                                                <p class="text-muted fs-11 mb-3">
                                                                    <i class="{{ $actIcon }} me-1"></i>
                                                                    {{ __('crm.activity_types.' . $item->type) ?? $item->type }}
                                                                    &bull; Current: <strong>{{ $item->followup_date->format('d M Y, h:i A') }}</strong>
                                                                </p>
                                                                <x-ui.odoo-form-ui type="input" inputType="datetime-local" :label="__('crm.new_date_time')" name="followup_date" :required="true" />
                                                                <x-ui.odoo-form-ui type="select" :label="__('crm.tag_assign_persons')" name="tagged_user_ids[]" :multiple="true" :searchable="true">
                                                                    @foreach($users as $u)
                                                                        <option value="{{ $u->id }}" @selected(in_array($u->id, $currentTaggedIds))>{{ $u->name }} ({{ $u->email }})</option>
                                                                    @endforeach
                                                                </x-ui.odoo-form-ui>
                                                                <x-ui.odoo-form-ui type="textarea" :label="__('crm.note_optional')" name="notes" rows="2" :placeholder="__('crm.reason_rescheduling')">{{ $item->notes }}</x-ui.odoo-form-ui>
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
                    </div>

                </div>
            </div>
        </div>
    </div>

    <!-- Offcanvas Drawer: Deal Followup / Schedule Activity -->
    <div class="offcanvas offcanvas-end border-0 shadow-lg d-print-none" tabindex="-1" id="dealFollowupOffcanvas" aria-labelledby="dealFollowupOffcanvasLabel" style="width: 490px; max-width: 92vw;">
        <div class="offcanvas-header bg-light border-bottom py-3 px-4">
            <div class="d-flex align-items-center gap-2">
                <div class="avatar-text avatar-sm bg-soft-primary text-primary rounded-circle">
                    <i class="feather-calendar"></i>
                </div>
                <div>
                    <h5 class="offcanvas-title fw-bold text-dark fs-14 mb-0" id="dealFollowupOffcanvasTitle">{{ __('crm.log_schedule_activity_for', ['title' => $deal->title]) }}</h5>
                    <span class="text-muted fs-11">{{ __('crm.log_interaction_sub') }}</span>
                </div>
            </div>
            <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        
        <div class="offcanvas-body p-4 bg-white">
            <form action="{{ route('crm.deals.followups.store', $deal->id) }}" method="POST" id="dealFollowupForm" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="action_mode" id="dealOffcanvasActionMode" value="log_note">

                <!-- 2-Mode Switcher Tabs -->
                <div class="p-1 bg-light rounded-3 mb-4 d-flex gap-1 border">
                    <button type="button" class="btn btn-sm flex-fill fw-bold text-center border-0 deal-offcanvas-mode-btn active btn-primary text-white shadow-sm" data-mode="log_note" style="font-size: 12px; padding: 8px 6px; background-color: var(--bs-primary); border-radius: 6px; transition: all 0.2s ease;">
                        {{ __('crm.log_discussion_next') }}
                    </button>
                    <button type="button" class="btn btn-sm flex-fill fw-bold text-center border-0 deal-offcanvas-mode-btn" data-mode="schedule" style="font-size: 12px; padding: 8px 6px; color: #64748b; background-color: transparent; border-radius: 6px; transition: all 0.2s ease;">
                        {{ __('crm.direct_schedule_activity') }}
                    </button>
                </div>

                <!-- Past Interaction Section (Tab 1: Log Activity) -->
                <div id="dealSectionPastInteraction">
                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark fs-12 mb-1">{{ __('crm.followup_interaction_type') }}</label>
                        <select name="type" id="dealOffcanvasFollowupType" class="form-select form-select-sm shadow-2xs">
                            <option value="Call">{{ __('crm.activity_types.Call') ?? 'Call' }}</option>
                            <option value="Email">{{ __('crm.activity_types.Email') ?? 'Email' }}</option>
                            <option value="Meeting">{{ __('crm.activity_types.Meeting') ?? 'Meeting' }}</option>
                            <option value="Demo">{{ __('crm.activity_types.Demo') ?? 'Demo' }}</option>
                            <option value="WhatsApp">WhatsApp</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark fs-12 mb-1">{{ __('crm.followup_status_outcome') }}</label>
                        <select name="status" id="dealOffcanvasFollowupStatus" class="form-select form-select-sm shadow-2xs">
                            <option value="Connected">{{ __('crm.outcomes.Connected') ?? 'Connected' }}</option>
                            <option value="Not Connected">{{ __('crm.outcomes.Not Connected') ?? 'Not Connected' }}</option>
                            <option value="Not Answering">{{ __('crm.outcomes.Not Answering') ?? 'Not Answering' }}</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark fs-12 mb-1">{{ __('crm.discussion_notes_summary') }}</label>
                        <textarea name="notes" id="dealOffcanvasNotes" rows="3" class="form-control form-control-sm shadow-2xs" placeholder="Write discussion notes..."></textarea>
                    </div>

                    <!-- Next Follow-up Section inside Log Mode -->
                    <div class="border-top pt-3 mt-3">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <h6 class="fs-12 fw-bold text-dark mb-0">
                                <i class="feather-calendar text-primary me-1"></i> {{ __('crm.next_activity_schedule') }}
                            </h6>
                            <button type="button" class="btn btn-xs btn-outline-primary fw-bold px-2.5 py-1 rounded-pill d-inline-flex align-items-center gap-1" id="btnToggleDealNextScheduleShow">
                                <i class="feather-plus fs-11" id="iconToggleDealNextScheduleShow"></i>
                                <span id="textToggleDealNextScheduleShow">{{ __('crm.schedule_next_activity_btn') }}</span>
                            </button>
                        </div>
                        
                        <div id="containerDealNextScheduleFieldsShow" class="mt-3 p-3 bg-light rounded-3 border" style="display: none;">
                            <div class="mb-2">
                                <label class="form-label fw-bold text-dark fs-12 mb-1">{{ __('crm.next_activity_title') }}</label>
                                <input type="text" name="next_title" id="dealOffcanvasNextTitle" class="form-control form-control-sm shadow-2xs" placeholder="{{ __('crm.next_activity_title_placeholder') }}" value="">
                            </div>

                            <div class="row g-2 mb-2">
                                <div class="col-6">
                                    <label class="form-label fw-bold text-dark fs-12 mb-1">{{ __('crm.next_activity_type') }}</label>
                                    <select name="next_activity_type" id="dealOffcanvasNextActivityType" class="form-select form-select-sm shadow-2xs">
                                        <option value="Call">{{ __('crm.activity_types.Call') ?? 'Call' }}</option>
                                        <option value="Meeting">{{ __('crm.activity_types.Meeting') ?? 'Meeting' }}</option>
                                        <option value="Demo">{{ __('crm.activity_types.Demo') ?? 'Demo' }}</option>
                                        <option value="Email">{{ __('crm.activity_types.Email') ?? 'Email' }}</option>
                                        <option value="WhatsApp">WhatsApp</option>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="form-label fw-bold text-dark fs-12 mb-1">{{ __('crm.duration_minutes') }}</label>
                                    <select name="next_duration_minutes" id="dealOffcanvasNextDuration" class="form-select form-select-sm shadow-2xs">
                                        <option value="15">{{ __('crm.duration_options.15') ?? '15 Mins' }}</option>
                                        <option value="30" selected>{{ __('crm.duration_options.30') ?? '30 Mins' }}</option>
                                        <option value="45">{{ __('crm.duration_options.45') ?? '45 Mins' }}</option>
                                        <option value="60">{{ __('crm.duration_options.60') ?? '60 Mins (1 Hr)' }}</option>
                                        <option value="90">{{ __('crm.duration_options.90') ?? '90 Mins' }}</option>
                                        <option value="120">{{ __('crm.duration_options.120') ?? '120 Mins' }}</option>
                                    </select>
                                </div>
                            </div>

                            <div class="mb-2">
                                <label class="form-label fw-bold text-dark fs-12 mb-1">{{ __('crm.next_followup_datetime_optional') }}</label>
                                <input type="datetime-local" name="next_followup_date" id="dealOffcanvasNextFollowupDate" class="form-control form-control-sm shadow-2xs">
                            </div>

                            <div class="p-3 my-3 bg-white rounded-3 border shadow-2xs">
                                <div class="row g-2">
                                    <div class="col-6">
                                        <div class="form-check form-switch mb-0 p-2 border rounded-2 bg-light d-flex align-items-center justify-content-between" style="min-height: 38px;">
                                            <label class="form-check-label fw-bold fs-11 text-dark mb-0 pe-1" for="dealOffcanvasNextSyncGoogle" style="cursor: pointer;">
                                                <i class="feather-calendar text-danger me-1"></i> {{ __('crm.google_calendar') }}
                                            </label>
                                            <input type="hidden" name="next_sync_google_calendar" value="0">
                                            <input class="form-check-input ms-0 mt-0" type="checkbox" name="next_sync_google_calendar" value="1" id="dealOffcanvasNextSyncGoogle" style="cursor: pointer;">
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="form-check form-switch mb-0 p-2 border rounded-2 bg-light d-flex align-items-center justify-content-between" style="min-height: 38px;">
                                            <label class="form-check-label fw-bold fs-11 text-dark mb-0 pe-1" for="dealOffcanvasNextCreateMeet" style="cursor: pointer;">
                                                <i class="feather-video text-primary me-1"></i> {{ __('crm.google_meet_video') }}
                                            </label>
                                            <input type="hidden" name="next_create_meet_link" value="0">
                                            <input class="form-check-input ms-0 mt-0" type="checkbox" name="next_create_meet_link" value="1" id="dealOffcanvasNextCreateMeet" style="cursor: pointer;">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <x-ui.modal-form-ui type="input" name="next_guest_emails" id="dealOffcanvasNextGuestEmails" :label="__('crm.guest_attendee_emails')" :placeholder="__('crm.guest_emails_placeholder')" />

                            <div class="mt-3">
                                <label class="form-label fw-bold text-dark fs-12 mb-1">{{ __('crm.tag_assign_persons') }}</label>
                                <select name="tagged_user_ids[]" id="dealOffcanvasTagUser" class="form-select form-select-sm shadow-2xs" multiple data-placeholder="{{ __('crm.select_persons_to_tag') }}">
                                    @foreach(($users ?? \App\Models\User::orderBy('name')->get()) as $u)
                                        <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Direct Schedule Section (Tab 2: Schedule Activity) -->
                <div id="dealSectionDirectSchedule" style="display: none;">
                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark fs-12 mb-1">{{ __('crm.event_meeting_title') }}</label>
                        <input type="text" name="title" id="dealOffcanvasEventTitle" class="form-control form-control-sm shadow-2xs" placeholder="{{ __('crm.event_title_placeholder') }}" value="CRM Followup Call">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark fs-12 mb-1">{{ __('crm.activity_type') }} <span class="text-danger">*</span></label>
                        <select name="schedule_type" id="dealOffcanvasScheduleType" class="form-select form-select-sm shadow-2xs" onchange="$('#dealOffcanvasFollowupType').val(this.value)">
                            <option value="Call">{{ __('crm.activity_types.Call') ?? 'Call' }}</option>
                            <option value="Meeting">{{ __('crm.activity_types.Meeting') ?? 'Meeting' }}</option>
                            <option value="Demo">{{ __('crm.activity_types.Demo') ?? 'Demo' }}</option>
                            <option value="Email">{{ __('crm.activity_types.Email') ?? 'Email' }}</option>
                            <option value="WhatsApp">WhatsApp</option>
                        </select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-bold text-dark fs-12 mb-1">{{ __('crm.due_date_time') }} <span class="text-danger">*</span></label>
                            <input type="datetime-local" name="followup_date" id="dealOffcanvasFollowupDate" class="form-control form-control-sm shadow-2xs">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold text-dark fs-12 mb-1">{{ __('crm.duration_minutes') }}</label>
                            <select name="duration_minutes" id="dealOffcanvasDuration" class="form-select form-select-sm shadow-2xs">
                                <option value="15">{{ __('crm.duration_options.15') ?? '15 Mins' }}</option>
                                <option value="30" selected>{{ __('crm.duration_options.30') ?? '30 Mins' }}</option>
                                <option value="45">{{ __('crm.duration_options.45') ?? '45 Mins' }}</option>
                                <option value="60">{{ __('crm.duration_options.60') ?? '60 Mins (1 Hr)' }}</option>
                                <option value="90">{{ __('crm.duration_options.90') ?? '90 Mins' }}</option>
                                <option value="120">{{ __('crm.duration_options.120') ?? '120 Mins' }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="p-3 bg-light rounded-3 border mb-3 shadow-2xs">
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" name="sync_google_calendar" value="1" id="dealOffcanvasSyncGoogle" checked>
                                <label class="form-check-label fw-bold fs-12 text-dark" for="dealOffcanvasSyncGoogle">
                                    <i class="feather-calendar text-danger me-1"></i> {{ __('crm.google_calendar') }}
                                </label>
                            </div>
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" name="create_meet_link" value="1" id="dealOffcanvasCreateMeet">
                                <label class="form-check-label fw-bold fs-12 text-dark" for="dealOffcanvasCreateMeet">
                                    <i class="feather-video text-primary me-1"></i> {{ __('crm.google_meet_video') }}
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark fs-12 mb-1">{{ __('crm.guest_attendee_emails') }}</label>
                        <input type="text" name="guest_emails" id="dealOffcanvasGuestEmails" class="form-control form-control-sm shadow-2xs" placeholder="{{ __('crm.guest_emails_placeholder') }}">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark fs-12 mb-1">{{ __('crm.description_plan') }}</label>
                        <textarea name="schedule_notes" id="dealOffcanvasScheduleNotes" rows="3" class="form-control form-control-sm shadow-2xs" placeholder="{{ __('crm.agenda_plan_placeholder') }}" oninput="$('#dealOffcanvasNotes').val(this.value)"></textarea>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold text-dark fs-12 mb-1">{{ __('crm.deal_stage') }}</label>
                    <select name="stage" id="dealOffcanvasStage" class="form-select form-select-sm shadow-2xs">
                        @php
                            $dStatuses = $dealStatuses ?? \App\Domains\CRM\Models\DealStatus::getOrderedStatuses();
                        @endphp
                        @foreach($dStatuses as $stg)
                            <option value="{{ $stg->name }}" @selected(old('stage', $deal->stage) === $stg->name)>{{ $stg->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="d-flex align-items-center justify-content-end gap-2 border-top pt-3">
                    <button type="button" class="btn btn-light border px-4 py-2 fs-13 fw-bold text-uppercase" data-bs-dismiss="offcanvas">{{ __('crm.close') }}</button>
                    <button type="submit" class="btn btn-primary px-4 py-2 fs-13 fw-bold text-uppercase shadow-sm">{{ __('crm.save') }}</button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
        function submitDealStage(stageVal) {
            document.getElementById('dealStageInput').value = stageVal;
            document.getElementById('dealStageForm').submit();
        }

        $(document).ready(function() {
            const crmProductsList = @json($products ?? []);
            let rowIndex = 0;

            function buildProductOptions(selectedId = '') {
                let opts = '<option value="">Select Product...</option>';
                crmProductsList.forEach(function(p) {
                    const sel = (p.id == selectedId) ? ' selected' : '';
                    const price = parseFloat(p.selling_price || p.unit_cost || 0);
                    const taxRate = (p.gst_rate !== null && p.gst_rate !== undefined) ? parseFloat(p.gst_rate) : 18;
                    opts += `<option value="${p.id}" data-selling-price="${price}" data-tax-rate="${taxRate}"${sel}>${p.name} ${p.sku ? '('+p.sku+')' : ''}</option>`;
                });
                return opts;
            }

            function getRowHtml(index, selectedId = '') {
                return `
                    <tr class="item-row" data-row-id="${index}">
                        <td class="ps-2">
                            <select name="items[${index}][product_id]" class="odoo-table-select odoo-select2 item-name-input erp-premium-select" required data-master="product" style="width:100%;">
                                ${buildProductOptions(selectedId)}
                            </select>
                            <div class="description-container mt-2" id="desc-container-${index}" style="display: none;">
                                <textarea name="items[${index}][description]" class="form-control odoo-table-input" placeholder="Scope details / custom specifications..." rows="2"></textarea>
                            </div>
                            <a href="javascript:void(0)" class="toggle-desc-btn text-primary fs-11 mt-1 d-inline-block" data-row-id="${index}">
                                <i class="feather-plus me-1"></i>Add Description
                            </a>
                        </td>
                        <td>
                            <input type="number" name="items[${index}][quantity]" class="odoo-table-input text-end qty-input" value="1" min="0.01" step="any" required style="width: 100%; max-width: 90px; margin-left: auto; text-align: right;">
                        </td>
                        <td>
                            <input type="number" name="items[${index}][unit_price]" class="odoo-table-input text-end price-input" value="0.00" min="0" step="0.01" required style="width: 100%; max-width: 140px; margin-left: auto; text-align: right;">
                        </td>
                        <td>
                            <input type="number" name="items[${index}][tax_rate]" class="odoo-table-input text-end tax-input" value="18.00" min="0" max="100" step="0.01" style="width: 100%; max-width: 90px; margin-left: auto; text-align: right;">
                        </td>
                        <td class="text-end fw-bold text-dark amount-display pe-3" style="font-size: 13px; padding-top: 8px;">
                            {{ active_currency_symbol() }}0.00
                        </td>
                        <td class="text-center" style="padding-top: 6px;">
                            <button type="button" class="btn btn-icon btn-sm btn-soft-danger remove-row-btn">
                                <i class="feather-trash-2"></i>
                            </button>
                        </td>
                    </tr>
                `;
            }

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

            $(document).on('click', '.remove-row-btn', function() {
                if ($('.item-row').length > 1) {
                    $(this).closest('tr').remove();
                    calculateTotals();
                }
            });

            $(document).on('input change', '.qty-input, .price-input, .tax-input, #discountInput', function() {
                calculateTotals();
            });

            function addRow(item = null) {
                const selectedId = item ? (item.product_id || '') : '';
                const newRow = $(getRowHtml(rowIndex, selectedId));
                $('#itemsTable tbody').append(newRow);

                newRow.find('.item-name-input').select2({
                    theme: "bootstrap-5",
                    width: "100%"
                });

                if (item) {
                    newRow.find('textarea').val(item.description || '');
                    if (item.description) {
                        $('#desc-container-' + rowIndex).show();
                        newRow.find('.toggle-desc-btn').html('<i class="feather-minus me-1"></i>Remove Description');
                    }
                    newRow.find('.qty-input').val(item.quantity || 1);
                    let finalUnitPrice = parseFloat(item.unit_price);
                    if (isNaN(finalUnitPrice) || finalUnitPrice === 0) {
                        const foundProd = crmProductsList.find(p => p.id == item.product_id);
                        if (foundProd && parseFloat(foundProd.selling_price || foundProd.unit_cost || 0) > 0) {
                            finalUnitPrice = parseFloat(foundProd.selling_price || foundProd.unit_cost || 0);
                        } else {
                            finalUnitPrice = 0.00;
                        }
                    }
                    newRow.find('.price-input').val(finalUnitPrice.toFixed(2));
                    newRow.find('.tax-input').val(item.tax_rate !== undefined && item.tax_rate !== null ? parseFloat(item.tax_rate).toFixed(2) : '18.00');
                }

                newRow.find('.item-name-input').on('change', function() {
                    const selectedOption = $(this).find('option:selected');
                    const sellingPrice = parseFloat(selectedOption.attr('data-selling-price')) || 0;
                    const taxRate = parseFloat(selectedOption.attr('data-tax-rate'));
                    $(this).closest('tr').find('.price-input').val(sellingPrice.toFixed(2));
                    if (!isNaN(taxRate)) {
                        $(this).closest('tr').find('.tax-input').val(taxRate.toFixed(2));
                    }
                    calculateTotals();
                });

                rowIndex++;
                calculateTotals();
            }

            function calculateTotals() {
                let subtotal = 0;
                let taxTotal = 0;

                $('.item-row').each(function() {
                    const qty = parseFloat($(this).find('.qty-input').val()) || 0;
                    const price = parseFloat($(this).find('.price-input').val()) || 0;
                    const taxRate = parseFloat($(this).find('.tax-input').val()) || 0;

                    const amount = qty * price;
                    const tax = amount * (taxRate / 100);

                    subtotal += amount;
                    taxTotal += tax;

                    $(this).find('.amount-display').text('{{ active_currency_symbol() }}' + amount.toFixed(2));
                });

                const discount = parseFloat($('#discountInput').val()) || 0;
                const grandTotal = subtotal + taxTotal - discount;

                $('#calcSubtotal').text('{{ active_currency_symbol() }}' + subtotal.toFixed(2));
                $('#calcTax').text('{{ active_currency_symbol() }}' + taxTotal.toFixed(2));
                $('#calcTotal').text('{{ active_currency_symbol() }}' + Math.max(0, grandTotal).toFixed(2));
            }


            $('#addItemRow').on('click', function() { addRow(); });

            const hasCreateQ = @json(request()->has('create_quotation') || old('form_type') === 'quotation_create');
            const hasEditQ = @json(request()->has('edit_quotation') || old('form_type') === 'quotation_edit');
            const prefilledDealItems = @json($prefilledDealItems ?? []);
            const existingItems = @json(old('items') ?: (request()->has('create_quotation') && !empty($prefilledDealItems) ? $prefilledDealItems : (isset($activeQuotation) ? $activeQuotation->items : [])));

            if (hasCreateQ || hasEditQ) {
                if (existingItems && existingItems.length > 0) {
                    existingItems.forEach(function(item) {
                        addRow(item);
                    });
                } else {
                    addRow();
                }
            }

            // Scroll Spy for Overview Sections
            let isManualClick = false;
            $('#zohoMainScrollable').on('scroll', function() {
                if (isManualClick) return;
                const scrollContainer = this;
                const containerTop = scrollContainer.getBoundingClientRect().top;
                const stickyHeader = document.querySelector('.sticky-top');
                const stickyHeaderHeight = stickyHeader ? stickyHeader.offsetHeight : 50;

                const sections = ['#sectionDealInfo', '#sectionCustomerCard', '#sectionNotes', '#sectionDocuments'];
                let currentSection = null;

                sections.forEach(function(secId) {
                    const el = document.querySelector(secId);
                    if (el) {
                        const rect = el.getBoundingClientRect();
                        if (rect.top - containerTop <= stickyHeaderHeight + 60) {
                            currentSection = secId;
                        }
                    }
                });

                if (currentSection && $('#overview-pane').hasClass('active')) {
                    $('#zohoSidebarLinks a').removeClass('active');
                    $('#zohoSidebarLinks a[href="' + currentSection + '"]').addClass('active');
                }
            });

            let pendingScrollTarget = null;

            function scrollToTarget(targetHash) {
                if (!targetHash) return;
                const targetEl = document.querySelector(targetHash);
                const scrollContainer = document.getElementById('zohoMainScrollable');
                if (targetEl && scrollContainer) {
                    const containerTop = scrollContainer.getBoundingClientRect().top;
                    const targetTop = targetEl.getBoundingClientRect().top;
                    const stickyHeader = document.querySelector('.sticky-top');
                    const stickyHeaderHeight = stickyHeader ? (stickyHeader.offsetHeight + 10) : 65;
                    const offset = targetTop - containerTop + scrollContainer.scrollTop - stickyHeaderHeight;
                    scrollContainer.scrollTo({ top: Math.max(0, offset), behavior: 'smooth' });
                } else if (scrollContainer && (targetHash === '#sectionQuotations' || targetHash === '#sectionSalesOrders')) {
                    scrollContainer.scrollTo({ top: 0, behavior: 'smooth' });
                }
            }

            // Smooth Related List Sidebar Navigation & Tab Synchronization
            $('#zohoSidebarLinks a').on('click', function(e) {
                e.preventDefault();
                const targetHash = $(this).attr('href');
                
                $('#zohoSidebarLinks a').removeClass('active');
                $(this).addClass('active');
                isManualClick = true;

                // 1. Identify Target Main Tab and Subtab
                let targetTabBtnId = null;
                let targetSubtabBtnId = null;

                if (['#sectionDealInfo', '#sectionCustomerCard', '#sectionNotes', '#sectionDocuments'].includes(targetHash)) {
                    targetTabBtnId = 'overview-tab';
                } else if (targetHash === '#sectionQuotations' || targetHash === '#sectionQuotationHistory') {
                    targetTabBtnId = 'quotations-tab';
                } else if (targetHash === '#sectionSalesOrders') {
                    targetTabBtnId = 'salesorders-tab';
                } else if (targetHash === '#subtab-interactions' || targetHash === '#subtab-history') {
                    targetTabBtnId = 'timeline-tab';
                    targetSubtabBtnId = (targetHash === '#subtab-interactions') ? 'subtab-interactions-tab' : 'subtab-history-tab';
                }

                // 2. Subtab handling if applicable
                if (targetSubtabBtnId) {
                    const subtabBtn = document.getElementById(targetSubtabBtnId);
                    if (subtabBtn) {
                        bootstrap.Tab.getOrCreateInstance(subtabBtn).show();
                    }
                }

                // 3. Main Tab activation & Scrolling
                const tabBtn = document.getElementById(targetTabBtnId);
                const isAlreadyActive = tabBtn && tabBtn.classList.contains('active');

                pendingScrollTarget = targetHash;

                if (isAlreadyActive) {
                    scrollToTarget(targetHash);
                    pendingScrollTarget = null;
                    setTimeout(function() { isManualClick = false; }, 400);
                } else if (tabBtn) {
                    bootstrap.Tab.getOrCreateInstance(tabBtn).show();
                }
            });

            // Tab state persistence logic
            var activeTabKey = 'deal_active_tab_' + {{ $deal->id }};
            var activeSubTabKey = 'deal_active_subtab_' + {{ $deal->id }};

            const isQTabActive = @json($isQuotationTabActive);
            const isSOTabActive = @json($isSalesOrdersTabActive);
            if (isQTabActive) {
                localStorage.setItem(activeTabKey, 'quotations-tab');
            } else if (isSOTabActive) {
                localStorage.setItem(activeTabKey, 'salesorders-tab');
            }

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
            } else if (hash === '#quotations' || hash === '#quotations-pane') {
                localStorage.setItem(activeTabKey, 'quotations-tab');
            } else if (hash === '#salesorders' || hash === '#salesorders-pane') {
                localStorage.setItem(activeTabKey, 'salesorders-tab');
            }

            // Restore saved tab from localStorage
            var savedTabId = localStorage.getItem(activeTabKey);
            if (savedTabId && $('#' + savedTabId).length) {
                setTimeout(function() {
                    var mainTabEl = document.getElementById(savedTabId);
                    if (mainTabEl) {
                        bootstrap.Tab.getOrCreateInstance(mainTabEl).show();
                    }
                    
                    if (savedTabId === 'timeline-tab') {
                        var savedSubTabId = localStorage.getItem(activeSubTabKey) || 'subtab-interactions-tab';
                        var subTabEl = document.getElementById(savedSubTabId);
                        if (subTabEl) {
                            bootstrap.Tab.getOrCreateInstance(subTabEl).show();
                        }
                    }
                }, 50);
            }

            $(document).on('shown.bs.tab', 'button[data-bs-toggle="tab"], a[data-bs-toggle="tab"]', function (e) {
                if (e.target.id) {
                    if (['overview-tab', 'timeline-tab', 'quotations-tab', 'salesorders-tab'].includes(e.target.id)) {
                        localStorage.setItem(activeTabKey, e.target.id);
                    } else if (['subtab-history-tab', 'subtab-interactions-tab'].includes(e.target.id)) {
                        localStorage.setItem(activeSubTabKey, e.target.id);
                        localStorage.setItem(activeTabKey, 'timeline-tab');
                    }
                }
            });

            // Perform scroll once top tab is fully shown
            $('#zohoDealTabs button[data-bs-toggle="tab"]').on('shown.bs.tab', function(e) {
                if (pendingScrollTarget) {
                    const targetToScroll = pendingScrollTarget;
                    pendingScrollTarget = null;
                    setTimeout(function() {
                        scrollToTarget(targetToScroll);
                        setTimeout(function() { isManualClick = false; }, 400);
                    }, 60);
                } else {
                    const targetPaneId = $(e.target).attr('data-bs-target');
                    if (targetPaneId === '#overview-pane') {
                        $('#zohoSidebarLinks a').removeClass('active');
                        $('#zohoSidebarLinks a[href="#sectionDealInfo"]').addClass('active');
                    } else if (targetPaneId === '#quotations-pane') {
                        $('#zohoSidebarLinks a').removeClass('active');
                        $('#zohoSidebarLinks a[href="#sectionQuotations"]').addClass('active');
                    } else if (targetPaneId === '#salesorders-pane') {
                        $('#zohoSidebarLinks a').removeClass('active');
                        $('#zohoSidebarLinks a[href="#sectionSalesOrders"]').addClass('active');
                    } else if (targetPaneId === '#timeline-pane') {
                        $('#zohoSidebarLinks a').removeClass('active');
                        $('#zohoSidebarLinks a[href="#subtab-history"]').addClass('active');
                    }
                }
            });

            const dealDocUploadBtn = $('#dealDocUploadBtn');
            const dealDocInput = $('#dealDocInput');

            dealDocUploadBtn.on('click', function() {
                dealDocInput.trigger('click');
            });

            dealDocInput.on('change', function() {
                if (this.files.length > 0) {
                    $(this).closest('form').submit();
                }
            });
        });

        window.enableDealRequirementEdit = function() {
            $('#viewDealRequirementBlock').hide();
            $('#editDealRequirementBlock').show();
            var input = $('#dealRequirementInput');
            input.focus();
            if (input.val()) {
                var len = input.val().length;
                input[0].setSelectionRange(len, len);
            }
            updateDealReqCharCount(input[0]);
        };

        window.cancelDealRequirementEdit = function() {
            $('#editDealRequirementBlock').hide();
            $('#viewDealRequirementBlock').show();
        };

        window.updateDealReqCharCount = function(el) {
            var len = el ? el.value.length : 0;
            $('#dealReqCharCounter').text(len + ' chars');
        };

        function escapeDealHtml(text) {
            return text
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }

        $(document).on('keydown', '#dealRequirementInput', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.keyCode === 13) {
                e.preventDefault();
                $('#ajaxDealRequirementForm').submit();
            }
        });

        $(document).on('submit', '#ajaxDealRequirementForm', function(e) {
            e.preventDefault();
            var form = $(this);
            var btn = $('#btnSaveDealRequirement');
            var originalHtml = btn.html();

            btn.attr('disabled', true).html('<i class="feather-loader me-1"></i> SAVING...');

            $.ajax({
                url: form.attr('action'),
                type: 'POST',
                data: form.serialize(),
                dataType: 'json',
                headers: {
                    'Accept': 'application/json'
                },
                success: function(res) {
                    btn.attr('disabled', false).html(originalHtml);
                    if (res.success) {
                        var reqText = res.requirement || res.notes || '';
                        var viewBlock = $('#viewDealRequirementBlock');

                        if (reqText.trim().length > 0) {
                            viewBlock.html(`
                                <div class="position-relative requirement-clickable-box p-3 rounded shadow-2xs" onclick="enableDealRequirementEdit()" title="Click anywhere to edit requirement" style="cursor: pointer; background: #f8fafc; border: 1px solid #cbd5e1; transition: all 0.2s ease;">
                                    <div class="d-flex align-items-start justify-content-between gap-3">
                                        <div class="text-dark fs-13 flex-grow-1" style="white-space: pre-wrap; line-height: 1.6; font-family: 'Inter', sans-serif;" id="viewDealRequirementText">${escapeDealHtml(reqText)}</div>
                                        <span class="badge bg-white text-primary border shadow-2xs px-2.5 py-1.5 fs-11 flex-shrink-0 edit-hint-badge" style="border-color: #cbd5e1 !important; transition: all 0.2s ease;">
                                            <i class="feather-edit-2 me-1"></i>Click to Edit
                                        </span>
                                    </div>
                                </div>
                            `);
                        } else {
                            viewBlock.html(`
                                <div class="position-relative requirement-empty-box p-4 rounded text-center cursor-pointer" onclick="enableDealRequirementEdit()" title="Click to add requirement" style="cursor: pointer; background: #f8fafc; border: 1px dashed #cbd5e1; transition: all 0.2s ease;">
                                    <div class="avatar-text avatar-md bg-soft-primary text-primary rounded-circle mx-auto mb-2">
                                        <i class="feather-edit-3 fs-5"></i>
                                    </div>
                                    <h6 class="fw-bold text-dark fs-13 mb-1">No Requirements Details Specified</h6>
                                    <p class="text-muted fs-12 mb-0">Click here to add deal notes, requirements, or scope of work.</p>
                                </div>
                            `);
                        }

                        cancelDealRequirementEdit();
                        if (typeof Toast !== 'undefined' && Toast.fire) {
                            Toast.fire({ icon: 'success', title: res.message || 'Requirements updated successfully!' });
                        } else if (typeof toastr !== 'undefined') {
                            toastr.success(res.message || 'Requirements updated successfully!');
                        }
                    }
                },
                error: function(xhr) {
                    btn.attr('disabled', false).html(originalHtml);
                    alert('Failed to save requirements. Please try again.');
                }
            });
        });

        window.submitDealStage = function(stage) {
            var form = $('#dealStageForm');
            if (form.length) {
                $('#dealStageInput').val(stage);
                form.submit();
            }
        };

        // Toggle Offcanvas Mode (Exact replica of Lead switchOffcanvasMode)
        function switchDealOffcanvasMode(mode) {
            $('.deal-offcanvas-mode-btn').removeClass('active btn-primary text-white shadow-sm').css({'background-color': 'transparent', 'color': '#64748b', 'box-shadow': 'none'});
            var activeBtn = $('.deal-offcanvas-mode-btn[data-mode="' + mode + '"]');
            activeBtn.addClass('active btn-primary text-white shadow-sm').css({'background-color': 'var(--bs-primary)', 'color': '#ffffff', 'box-shadow': '0 2px 4px rgba(0,0,0,0.15)'});
            
            $('#dealOffcanvasActionMode').val(mode);

            if (mode === 'log_note') {
                $('#dealSectionPastInteraction, #dealSectionLogInteraction').show();
                $('#dealSectionDirectSchedule').hide();
                $('#dealOffcanvasFollowupDate').removeAttr('required');
            } else if (mode === 'schedule') {
                $('#dealSectionPastInteraction, #dealSectionLogInteraction').hide();
                $('#dealSectionDirectSchedule').show();
                $('#dealOffcanvasFollowupDate').attr('required', 'required');
            }
        }

        $(document).on('click', '.deal-offcanvas-mode-btn', function() {
            switchDealOffcanvasMode($(this).attr('data-mode'));
        });

        function initDealTagUserSelect2() {
            if ($('#dealOffcanvasTagUser').length && $.fn.select2) {
                if ($('#dealOffcanvasTagUser').hasClass('select2-hidden-accessible')) {
                    $('#dealOffcanvasTagUser').select2('destroy');
                }
                $('#dealOffcanvasTagUser').select2({
                    theme: 'bootstrap-5',
                    placeholder: 'Select persons to tag...',
                    allowClear: true,
                    dropdownParent: $('#dealFollowupOffcanvas'),
                    width: '100%'
                });
            }
        }

        // Open and populate Offcanvas drawer for Deal Followup / Schedule Activity
        $(document).on('click', '.btn-open-deal-followup-offcanvas', function() {
            var dealId = $(this).attr('data-deal-id') || '{{ $deal->id }}';
            var dealTitle = $(this).attr('data-deal-title') || '{{ addslashes($deal->title) }}';
            var mode = $(this).attr('data-mode') || 'log_note';

            $('#dealFollowupOffcanvasTitle').text('Log / Schedule Activity for ' + dealTitle);
            $('#dealFollowupForm').attr('action', '/crm/deals/' + dealId + '/followups');
            $('#dealOffcanvasNotes, #dealOffcanvasScheduleNotes, #dealOffcanvasNextFollowupDate, #dealOffcanvasNextTitle, #dealOffcanvasNextGuestEmails').val('');
            $('#dealOffcanvasNextSyncGoogle, #dealOffcanvasNextCreateMeet').prop('checked', false);

            $('#containerDealNextScheduleFieldsShow').hide();
            $('#iconToggleDealNextScheduleShow').removeClass('feather-minus').addClass('feather-plus');
            $('#textToggleDealNextScheduleShow').text('Schedule Next Activity');

            initDealTagUserSelect2();
            if ($('#dealOffcanvasTagUser').hasClass('select2-hidden-accessible')) {
                $('#dealOffcanvasTagUser').val(null).trigger('change');
            }

            switchDealOffcanvasMode(mode);
        });

        $(document).on('click', '#btnToggleDealNextScheduleShow', function() {
            var container = $('#containerDealNextScheduleFieldsShow');
            var icon = $('#iconToggleDealNextScheduleShow');
            var text = $('#textToggleDealNextScheduleShow');
            if (container.is(':visible')) {
                container.slideUp(200);
                icon.removeClass('feather-minus').addClass('feather-plus');
                text.text('Schedule Next Activity');
                $('#dealOffcanvasNextTitle, #dealOffcanvasNextFollowupDate, #dealOffcanvasNextGuestEmails').val('');
                $('#dealOffcanvasNextSyncGoogle, #dealOffcanvasNextCreateMeet').prop('checked', false);
            } else {
                container.slideDown(200);
                icon.removeClass('feather-plus').addClass('feather-minus');
                text.text('Remove Next Activity');
            }
        });

        $('#dealFollowupOffcanvas').on('shown.bs.offcanvas', function () {
            initDealTagUserSelect2();
        });

        // Auto-check Google Auth Status on Page Load
        function checkGoogleAuthStatus() {
            $.ajax({
                url: "{{ route('crm.deals.googleAuthStatus') }}",
                method: "GET",
                data: {
                    user_id: "{{ request('user_id', auth()->id() ?? 1) }}"
                },
                success: function (res) {
                    const badge = $('#googleAuthBadge');
                    if (res && res.is_connected) {
                        const emailLabel = res.connected_email ? ' (' + res.connected_email + ')' : '';
                        badge.attr('class', 'badge bg-success text-white text-decoration-none px-2.5 py-1 fs-11 fw-bold d-inline-flex align-items-center')
                             .attr('href', (res && res.login_url) ? res.login_url : '#')
                             .attr('title', 'Connected Email: ' + (res.connected_email || 'Google Workspace') + ' (Click to Re-connect or Manage)')
                             .html('<i class="feather-check-circle me-1"></i>Gmail Connected' + emailLabel);
                    } else {
                        badge.attr('class', 'badge bg-danger text-white text-decoration-none px-2.5 py-1 fs-11 fw-bold d-inline-flex align-items-center')
                             .attr('href', (res && res.login_url) ? res.login_url : '#')
                             .attr('title', 'Click to authenticate Google Account')
                             .html('<i class="feather-alert-triangle me-1"></i>Gmail Disconnected (Click to Connect)');
                    }
                },
                error: function () {
                    const badge = $('#googleAuthBadge');
                    badge.attr('class', 'badge bg-warning text-dark text-decoration-none px-2.5 py-1 fs-11 fw-bold d-inline-flex align-items-center')
                         .html('<i class="feather-alert-circle me-1"></i>Auth Check Error');
                }
            });
        }
        checkGoogleAuthStatus();

        // Handle AI Health Sync
        $('#btnSyncHealth').on('click', function () {
            const btn = $(this);
            const origHtml = btn.html();
            btn.prop('disabled', true).html('<i class="feather-loader spin me-1"></i>Syncing...');

            $.ajax({
                url: "{{ route('crm.deals.syncHealth', $deal->id) }}",
                method: "POST",
                data: {
                    _token: "{{ csrf_token() }}"
                },
                success: function (res) {
                    btn.prop('disabled', false).html(origHtml);

                    if (res.message) {
                        $('#syncDiagnosticText').html('<strong>Sync Diagnostic:</strong> ' + res.message);
                        $('#syncDiagnosticNotice').removeClass('d-none');
                    }

                    if (res.success) {
                        $('#healthScoreDisplay').text(res.health_score);
                        $('#sentimentDisplay').html('<i class="feather-smile text-success me-1"></i>' + res.sentiment_score);
                        $('#nextActionDisplay').text(res.next_best_action);
                        $('#syncedAtDisplay').html('<i class="feather-clock me-1"></i>' + res.health_synced_at);

                        const risk = (res.risk_level || 'Low').toLowerCase();
                        let badgeClass = 'bg-success text-white';
                        if (risk === 'high') badgeClass = 'bg-danger text-white';
                        else if (risk === 'medium') badgeClass = 'bg-warning text-dark';

                        $('#riskLevelDisplay').attr('class', 'badge px-2.5 py-1 fs-11 fw-bold ' + badgeClass).text(res.risk_level + ' Risk');

                        // Silent update on success without opening modal popup
                    } else {
                        if (res.auth_connected === false) {
                            const badge = $('#googleAuthBadge');
                            badge.attr('class', 'badge bg-danger text-white text-decoration-none px-2.5 py-1 fs-11 fw-bold d-inline-flex align-items-center')
                                 .attr('href', res.login_url || '#')
                                 .attr('target', '_blank')
                                 .attr('title', 'Token Expired - Click to Re-authenticate Google Account')
                                 .html('<i class="feather-alert-triangle me-1"></i>Gmail Expired (Click to Re-connect)');
                        }
                        showNotificationModal(false, 'AI Sync Notification', res.message || 'API Sync Failed.');
                    }
                },
                error: function () {
                    btn.prop('disabled', false).html(origHtml);
                    showNotificationModal(false, 'Connection Error', 'API Server Error: Could not connect to Deal Health Engine.');
                }
            });
        });

        // Handle AI Email Draft Generation
        $('#btnGenerateDraft').on('click', function () {
            const btn = $(this);
            const origHtml = btn.html();
            btn.prop('disabled', true).html('<i class="feather-loader spin me-1"></i>Generating Draft...');

            $.ajax({
                url: "{{ route('crm.deals.generateDraftReply', $deal->id) }}",
                method: "POST",
                data: {
                    _token: "{{ csrf_token() }}"
                },
                success: function (res) {
                    btn.prop('disabled', false).html(origHtml);
                    if (res.success) {
                        $('#aiDraftSubject').val(res.subject);
                        $('#aiDraftBody').val(res.body);
                        const draftModal = new bootstrap.Modal(document.getElementById('aiDraftModal'));
                        draftModal.show();
                    }
                },
                error: function () {
                    btn.prop('disabled', false).html(origHtml);
                    if (typeof toastr !== 'undefined') {
                        toastr.error('Failed to generate AI Draft Reply.');
                    }
                }
            });
        });

        // Handle Copy to Clipboard
        $('#btnCopyDraft').on('click', function () {
            const text = $('#aiDraftBody').val();
            navigator.clipboard.writeText(text).then(function() {
                if (typeof toastr !== 'undefined') {
                    toastr.success('AI Draft copied to clipboard!');
                } else {
                    alert('Copied to clipboard!');
                }
            });
        });
    </script>

    <!-- AI EMAIL DRAFT MODAL -->
    <x-ui.modal id="aiDraftModal" title="<i class='feather-mail text-primary me-1.5'></i>AI Generated Email Reply Draft" size="lg" :centered="true" :showFooter="false">
        <div class="mb-3">
            <x-ui.modal-form-ui type="input" label="Subject" id="aiDraftSubject" readonly="true" />
        </div>
        <div class="mb-3">
            <x-ui.modal-form-ui type="textarea" label="Message Body" id="aiDraftBody" rows="10" />
        </div>
        <div class="d-flex justify-content-between align-items-center pt-2 border-top">
            <span class="fs-11 text-muted"><i class="feather-info me-1"></i>You can edit the message before sending or copying.</span>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-sm btn-outline-secondary fw-bold" data-bs-dismiss="modal">Close</button>
                <button type="button" id="btnCopyDraft" class="btn btn-sm btn-primary fw-bold">
                    <i class="feather-copy me-1"></i>Copy to Clipboard
                </button>
            </div>
        </div>
    </x-ui.modal>

    <!-- SEND QUOTATION EMAIL MODAL WITH PDF ATTACHMENT -->
    <x-ui.modal id="sendQuotationEmailModal" title="<i class='feather-send text-success me-1.5'></i>Send Quotation PDF Email to Client" size="lg" :centered="true" :showFooter="false">
        <form id="sendQuotationEmailForm" action="" method="POST">
            @csrf
            @php
                $availableSmtps = \App\Models\EmailConfiguration::where('is_active', true)->orderByDesc('is_default')->get();
            @endphp
            @if($availableSmtps->isNotEmpty())
                <div class="mb-3">
                    <x-ui.modal-form-ui type="select" label="From SMTP Account" name="account_id" :searchable="false">
                        @foreach($availableSmtps as $s)
                            <option value="{{ $s->id }}" @selected($s->is_default)>{{ $s->name }} ({{ $s->email_address }})</option>
                        @endforeach
                    </x-ui.modal-form-ui>
                </div>
            @endif

            <div class="mb-3">
                <x-ui.modal-form-ui type="input" inputType="email" label="Client Email Address (To)" name="to_email" id="sendQuoteToEmail" placeholder="client@company.com" :required="true" />
            </div>

            <div class="mb-3">
                <x-ui.modal-form-ui type="input" label="Subject" name="subject" id="sendQuoteSubject" :required="true" />
            </div>

            <div class="p-2.5 rounded border bg-light-subtle mb-3 d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <i class="feather-paperclip text-primary fs-16"></i>
                    <span class="fs-12 fw-bold text-dark" id="sendQuotePdfBadge">Quotation.pdf</span>
                    <span class="badge bg-soft-danger text-danger border px-1.5 py-0.5 fs-10">PDF Attached</span>
                </div>
                <span class="fs-11 text-muted">Auto-generated via DomPDF</span>
            </div>

            <div class="mb-3">
                <x-ui.modal-form-ui type="textarea" label="Message Body" name="body_html" id="sendQuoteBody" rows="6" :required="true" />
            </div>

            <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                <span class="fs-11 text-muted"><i class="feather-info me-1"></i>Email will be dispatched immediately via SMTP server.</span>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary fw-bold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="btnSubmitSendQuoteEmail" class="btn btn-sm btn-success fw-bold px-4">
                        <i class="feather-send me-1"></i>Send Email Now
                    </button>
                </div>
            </div>
        </form>
    </x-ui.modal>

    <!-- SEND QUOTATION WHATSAPP MODAL -->
    <x-ui.modal id="sendQuotationWhatsAppModal" title="<i class='feather-message-circle text-success me-1.5'></i>Send Quotation PDF via WhatsApp" size="lg" :centered="true" :showFooter="false">
        <form id="sendQuotationWhatsAppForm" action="" method="POST" enctype="multipart/form-data">
            @csrf
            
            <!-- WhatsApp Connection Status Banner -->
            <div id="waStatusContainer" class="p-3 rounded border mb-3 text-start fs-12 bg-light">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                        <span id="waStatusBadge" class="badge bg-secondary">Checking WhatsApp...</span>
                        <span id="waStatusText" class="text-muted fs-11">Connecting to WhatsApp Baileys bridge...</span>
                    </div>
                    <button type="button" id="btnConnectWA" class="btn btn-xs btn-outline-success fw-bold d-none">
                        <i class="feather-smartphone me-1"></i>Connect / QR Scan
                    </button>
                </div>
                <div id="waQrContainer" class="text-center mt-3 d-none">
                    <p class="fs-12 fw-bold text-dark mb-1">Scan QR Code from WhatsApp app (Linked Devices)</p>
                    <img id="waQrImg" src="" alt="WhatsApp QR Code" class="img-thumbnail" style="max-width: 200px;">
                    <p class="fs-11 text-muted mt-1">Open WhatsApp on your phone -> Settings/Menu -> Linked Devices -> Link a Device</p>
                </div>
            </div>

            <div class="mb-3">
                <x-ui.modal-form-ui type="input" label="Recipient Mobile / WhatsApp Number" name="phone" id="sendWaPhone" placeholder="9876543210 (Country code 91 auto-added)" :required="true" />
            </div>

            <!-- Enhanced PDF Attachment Box -->
            <div class="card border mb-3 bg-light-subtle shadow-2xs">
                <div class="card-body p-3">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div class="d-flex align-items-center gap-2.5">
                            <div class="avatar avatar-sm bg-soft-success text-success rounded d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                                <i class="feather-file-text fs-16"></i>
                            </div>
                            <div>
                                <div class="d-flex align-items-center gap-2 flex-wrap">
                                    <span class="fs-12 fw-bold text-dark text-break" id="sendWaPdfBadge">Quotation.pdf</span>
                                    <span class="badge bg-soft-success text-success border px-2 py-0.5 fs-10" id="sendWaPdfStatusBadge">Auto Generated PDF</span>
                                </div>
                                <span class="fs-11 text-muted d-block mt-0.5" id="sendWaPdfSubText">Base64 PDF Attachment generated from ERP System</span>
                            </div>
                        </div>

                        <div class="d-flex align-items-center gap-2">
                            <!-- View PDF Button -->
                            <button type="button" class="btn btn-xs btn-outline-primary fw-bold d-inline-flex align-items-center px-2.5 py-1" id="btnPreviewWaPdf" title="View / Preview attached PDF">
                                <i class="feather-eye me-1"></i>View PDF
                            </button>
                            
                            <!-- Change / Upload Custom PDF Button -->
                            <button type="button" class="btn btn-xs btn-outline-secondary fw-bold d-inline-flex align-items-center px-2.5 py-1" id="btnTriggerCustomWaPdf" title="Upload a custom PDF from your system">
                                <i class="feather-upload me-1"></i>Change PDF
                            </button>

                            <!-- Reset to default ERP PDF Button -->
                            <button type="button" class="btn btn-xs btn-outline-danger fw-bold d-none align-items-center px-2 py-1" id="btnResetCustomWaPdf" title="Reset to default ERP Generated PDF">
                                <i class="feather-x me-1"></i>Reset
                            </button>
                        </div>
                    </div>

                    <!-- Hidden file input for custom PDF upload -->
                    <input type="file" name="custom_pdf" id="inputCustomWaPdf" class="d-none" accept="application/pdf">
                </div>
            </div>

            <div class="mb-3">
                <x-ui.modal-form-ui type="textarea" label="Caption / Message Text" name="caption" id="sendWaCaption" rows="5" :required="true" />
            </div>

            <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                <span class="fs-11 text-muted"><i class="feather-info me-1"></i>Document will be sent directly via linked WhatsApp account.</span>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary fw-bold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" id="btnSubmitSendQuoteWA" class="btn btn-sm btn-success fw-bold px-4">
                        <i class="feather-send me-1"></i>Send WhatsApp PDF
                    </button>
                </div>
            </div>
        </form>
    </x-ui.modal>

    <!-- RESULT NOTIFICATION MODAL (COMMON COMPONENT) -->
    <x-ui.modal id="waResultModal" title="<i class='feather-info me-1.5 text-primary'></i>System Notification" size="lg" :centered="true" :showFooter="false">
        <div class="py-4 px-3 text-center">
            <div id="waResultIcon" class="mb-3 d-flex justify-content-center"></div>
            <h4 id="waResultTitle" class="fw-bold text-dark mb-3 fs-18"></h4>
            <div id="waResultMessage" class="alert alert-light border text-center fs-13 mb-4 font-monospace p-3.5 text-break shadow-2xs rounded-3 mx-auto" style="max-width: 520px; background-color: #f8fafc; border-color: #e2e8f0 !important; color: #334155; line-height: 1.6;"></div>
            <div class="d-flex justify-content-center mt-3">
                <button type="button" class="btn btn-primary fw-bold px-5 py-2 fs-13 shadow-2xs rounded-3" data-bs-dismiss="modal" style="min-width: 140px;">OK</button>
            </div>
        </div>
    </x-ui.modal>

    <script>
        let defaultQuotationPdfName = 'Quotation.pdf';
        let defaultQuotationPdfUrl = '';
        let customWaPdfFile = null;

        function showNotificationModal(isSuccess, title, message) {
            const iconHtml = isSuccess 
                ? '<div class="avatar avatar-xl bg-soft-success text-success rounded-circle mx-auto mb-2 d-flex align-items-center justify-content-center shadow-2xs" style="width: 64px; height: 64px; border: 2px solid rgba(34, 197, 94, 0.2);"><i class="feather-check-circle fs-32"></i></div>'
                : '<div class="avatar avatar-xl bg-soft-danger text-danger rounded-circle mx-auto mb-2 d-flex align-items-center justify-content-center shadow-2xs" style="width: 64px; height: 64px; border: 2px solid rgba(239, 68, 68, 0.2);"><i class="feather-alert-triangle fs-32"></i></div>';
            
            $('#waResultIcon').html(iconHtml);
            $('#waResultTitle').text(title).attr('class', isSuccess ? 'fw-bold text-success mb-2 fs-18' : 'fw-bold text-danger mb-2 fs-18');
            $('#waResultMessage').text(message);
            
            const modalEl = document.getElementById('waResultModal');
            const modal = new bootstrap.Modal(modalEl);
            modal.show();
        }

        function checkWhatsAppStatus() {
            $.ajax({
                url: "{{ route('platform.whatsapp.status') }}",
                method: "GET",
                success: function(res) {
                    if (res.status === 'connected') {
                        $('#waStatusBadge').attr('class', 'badge bg-success').text('Connected');
                        const userName = res.user ? (res.user.name || res.user.id || 'Linked Account') : 'Linked Account';
                        $('#waStatusText').text('Connected: ' + userName);
                        $('#waQrContainer').addClass('d-none');
                        $('#btnConnectWA').addClass('d-none');
                        $('#btnSubmitSendQuoteWA').prop('disabled', false);
                    } else if (res.status === 'qr' && res.qr) {
                        $('#waStatusBadge').attr('class', 'badge bg-warning text-dark').text('Scan QR');
                        $('#waStatusText').text('Open WhatsApp app on your phone to scan QR code');
                        $('#waQrImg').attr('src', res.qr);
                        $('#waQrContainer').removeClass('d-none');
                        $('#btnConnectWA').addClass('d-none');
                        $('#btnSubmitSendQuoteWA').prop('disabled', true);
                    } else if (res.status === 'connecting') {
                        $('#waStatusBadge').attr('class', 'badge bg-info text-dark').text('Connecting...');
                        $('#waStatusText').text('Initializing Baileys socket...');
                        $('#waQrContainer').addClass('d-none');
                        $('#btnConnectWA').addClass('d-none');
                        $('#btnSubmitSendQuoteWA').prop('disabled', true);
                    } else {
                        $('#waStatusBadge').attr('class', 'badge bg-danger').text('Disconnected');
                        $('#waStatusText').text(res.message || 'No WhatsApp account linked.');
                        $('#waQrContainer').addClass('d-none');
                        $('#btnConnectWA').removeClass('d-none');
                        $('#btnSubmitSendQuoteWA').prop('disabled', true);
                    }
                },
                error: function() {
                    $('#waStatusBadge').attr('class', 'badge bg-danger').text('Bridge Offline');
                    $('#waStatusText').text('Node.js WhatsApp bridge is offline. Start Node.js server (services/whatsapp-bridge).');
                    $('#btnConnectWA').removeClass('d-none');
                }
            });
        }

        $(document).on('click', '#btnConnectWA', function() {
            $('#waStatusBadge').attr('class', 'badge bg-info text-dark').text('Connecting...');
            $('#waStatusText').text('Requesting QR code connection...');
            $.ajax({
                url: "{{ route('platform.whatsapp.connect') }}",
                method: "POST",
                data: { _token: "{{ csrf_token() }}" },
                success: function() {
                    setTimeout(checkWhatsAppStatus, 1500);
                }
            });
        });

        function resetWaPdfToDefault() {
            customWaPdfFile = null;
            $('#inputCustomWaPdf').val('');
            $('#sendWaPdfBadge').text(defaultQuotationPdfName);
            $('#sendWaPdfStatusBadge').attr('class', 'badge bg-soft-success text-success border px-2 py-0.5 fs-10').text('Auto Generated PDF');
            $('#sendWaPdfSubText').text('Base64 PDF Attachment generated from ERP System');
            $('#btnResetCustomWaPdf').addClass('d-none').removeClass('d-inline-flex');
        }

        $(document).on('click', '#btnTriggerCustomWaPdf', function() {
            $('#inputCustomWaPdf').click();
        });

        $(document).on('change', '#inputCustomWaPdf', function(e) {
            const file = e.target.files && e.target.files[0];
            if (file) {
                if (file.type !== 'application/pdf' && !file.name.toLowerCase().endsWith('.pdf')) {
                    alert('Please select a valid PDF file.');
                    resetWaPdfToDefault();
                    return;
                }
                customWaPdfFile = file;
                const sizeKb = (file.size / 1024).toFixed(1);
                $('#sendWaPdfBadge').text(file.name + ' (' + sizeKb + ' KB)');
                $('#sendWaPdfStatusBadge').attr('class', 'badge bg-soft-primary text-primary border px-2 py-0.5 fs-10').text('Custom PDF Selected');
                $('#sendWaPdfSubText').text('Custom document attached from your system');
                $('#btnResetCustomWaPdf').removeClass('d-none').addClass('d-inline-flex');
            }
        });

        $(document).on('click', '#btnResetCustomWaPdf', function() {
            resetWaPdfToDefault();
        });

        $(document).on('click', '#btnPreviewWaPdf', function() {
            if (customWaPdfFile) {
                const fileUrl = URL.createObjectURL(customWaPdfFile);
                window.open(fileUrl, '_blank');
            } else if (defaultQuotationPdfUrl) {
                window.open(defaultQuotationPdfUrl, '_blank');
            }
        });

        $(document).on('click', '.btn-open-send-quote-wa-modal', function () {
            const qId = $(this).attr('data-quotation-id');
            const qNum = $(this).attr('data-quotation-num');
            const cPhone = $(this).attr('data-client-phone') || '';
            const dTitle = $(this).attr('data-deal-title') || '{{ addslashes($deal->title) }}';

            defaultQuotationPdfName = 'Quotation_' + qNum + '.pdf';
            defaultQuotationPdfUrl = '/crm/quotations/' + qId + '/download?preview=1';

            $('#sendQuotationWhatsAppForm').attr('action', '/crm/quotations/' + qId + '/send-whatsapp');
            $('#sendWaPhone').val(cPhone);
            resetWaPdfToDefault();

            const defaultCaption = "Dear Valued Client,\n\nPlease find attached Quotation *" + qNum + "* for your review regarding " + dTitle + ".\n\n👉 *Please respond with one of the options below:*\n1️⃣ Reply *1* or *ACCEPT* to Accept Quotation\n2️⃣ Reply *2* or *REJECT [reason]* to Reject Quotation\n\nThank you,\nSales Team";
            $('#sendWaCaption').val(defaultCaption);

            const sendWaModal = new bootstrap.Modal(document.getElementById('sendQuotationWhatsAppModal'));
            sendWaModal.show();
            checkWhatsAppStatus();
        });

        $(document).on('submit', '#sendQuotationWhatsAppForm', function (e) {
            e.preventDefault();
            const form = $(this);
            const btn = $('#btnSubmitSendQuoteWA');
            const origHtml = btn.html();

            btn.prop('disabled', true).html('<i class="feather-loader spin me-1"></i>Sending WhatsApp...');

            const formData = new FormData(this);

            $.ajax({
                url: form.attr('action'),
                method: "POST",
                data: formData,
                processData: false,
                contentType: false,
                cache: false,
                success: function (res) {
                    btn.prop('disabled', false).html(origHtml);
                    const modalEl = document.getElementById('sendQuotationWhatsAppModal');
                    const modal = bootstrap.Modal.getInstance(modalEl);
                    if (modal) modal.hide();

                    showNotificationModal(true, "WhatsApp Message Delivered!", res.message);
                },
                error: function (xhr) {
                    btn.prop('disabled', false).html(origHtml);
                    const errMsg = xhr.responseJSON ? xhr.responseJSON.message : 'Failed to send WhatsApp document.';
                    const modalEl = document.getElementById('sendQuotationWhatsAppModal');
                    const modal = bootstrap.Modal.getInstance(modalEl);
                    if (modal) modal.hide();

                    showNotificationModal(false, "WhatsApp Dispatch Failed", errMsg);
                }
            });
        });

        $(document).on('click', '.btn-open-send-quote-modal', function () {
            const qId = $(this).attr('data-quotation-id');
            const qNum = $(this).attr('data-quotation-num');
            const cEmail = $(this).attr('data-client-email') || '';
            const dTitle = $(this).attr('data-deal-title') || '{{ addslashes($deal->title) }}';

            $('#sendQuotationEmailForm').attr('action', '/crm/quotations/' + qId + '/send-email');
            $('#sendQuoteToEmail').val(cEmail);
            $('#sendQuoteSubject').val('Quotation ' + qNum + ' - ' + dTitle);
            $('#sendQuotePdfBadge').text('Quotation_' + qNum + '.pdf');

            const defaultBody = "Dear Valued Client,\n\nPlease find attached Quotation " + qNum + " for your review regarding " + dTitle + ".\n\nWe look forward to your feedback. Please let us know if you have any questions.\n\nBest regards,\nSales Team";
            $('#sendQuoteBody').val(defaultBody);

            const sendModal = new bootstrap.Modal(document.getElementById('sendQuotationEmailModal'));
            sendModal.show();
        });

        $(document).on('submit', '#sendQuotationEmailForm', function (e) {
            e.preventDefault();
            const form = $(this);
            const btn = $('#btnSubmitSendQuoteEmail');
            const origHtml = btn.html();

            btn.prop('disabled', true).html('<i class="feather-loader spin me-1"></i>Sending Email...');

            $.ajax({
                url: form.attr('action'),
                method: "POST",
                data: form.serialize(),
                success: function (res) {
                    btn.prop('disabled', false).html(origHtml);
                    const modalEl = document.getElementById('sendQuotationEmailModal');
                    const modal = bootstrap.Modal.getInstance(modalEl);
                    if (modal) modal.hide();

                    showNotificationModal(true, "Quotation Email Dispatched!", res.message);
                },
                error: function (xhr) {
                    btn.prop('disabled', false).html(origHtml);
                    const errMsg = xhr.responseJSON ? xhr.responseJSON.message : 'Failed to send Quotation Email.';
                    const modalEl = document.getElementById('sendQuotationEmailModal');
                    const modal = bootstrap.Modal.getInstance(modalEl);
                    if (modal) modal.hide();

                    showNotificationModal(false, "Email Dispatch Failed", errMsg);
                }
            });
        });
    </script>
    @endpush

    @push('styles')
    <style>
        .daterangepicker {
            z-index: 99999 !important;
        }

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

        .activity-footer-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            padding-top: 10px;
            border-top: 1px dashed #e2e8f0;
        }
    </style>


    @endpush
@endsection
