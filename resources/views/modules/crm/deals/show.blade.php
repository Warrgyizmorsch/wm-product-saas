@extends('layouts.duralux')

@section('title', $deal->title . ' | Zoho CRM Deals')
@section('page-title', 'Deal Profile')
@section('breadcrumb', 'CRM / Deals / ' . $deal->deal_number)

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
        vertical-align: middle;
        border-bottom: 1px solid #f1f5f9;
    }
</style>
@endpush

@php
    $isQuotationTabActive = request()->has('create_quotation') || request()->has('edit_quotation') || request()->has('quotation_id') || old('form_type') === 'quotation_create' || old('form_type') === 'quotation_edit';
    $isSalesOrdersTabActive = request()->has('sales_orders_tab');
    
    // Stages array with probabilities
    $allStages = [
        'Qualification'  => 10,
        'Needs Analysis' => 30,
        'Proposal'       => 60,
        'Negotiation'    => 80,
        'Won'            => 100,
        'Lost'           => 0,
    ];
    
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
                            <span class="badge bg-soft-secondary text-secondary px-2 py-0.5 fs-10 fw-semibold">
                                <i class="feather-briefcase me-1"></i>{{ $deal->account->name }}
                            </span>
                        @endif
                    </div>
                    
                    <!-- Subhead Details -->
                    <div class="mt-1 d-flex align-items-center gap-3 fs-11 text-muted">
                        <span><strong class="text-dark">Deal #:</strong> <span class="font-monospace text-primary fw-bold">{{ $deal->deal_number }}</span></span>
                        <span><strong class="text-dark">Owner:</strong> {{ $deal->user ? $deal->user->name : 'Demo Admin' }}</span>
                        @if($deal->contact)
                            <span><strong class="text-dark">Contact:</strong> {{ $deal->contact->name }}</span>
                        @endif
                    </div>
                </div>
            </div>
            
            <!-- Right Action Buttons Toolbar -->
            <div class="d-flex align-items-center gap-2 flex-wrap">
                @if($activeQuotation && ($activeQuotation->status === 'Accepted' || $deal->stage === 'Won'))
                    <a href="{{ route('sales.orders.create', ['quotation_id' => $activeQuotation->id]) }}" class="btn btn-xs btn-success fw-bold py-1 px-3 rounded shadow-sm d-inline-flex align-items-center" style="font-size: 11px;">
                        <i class="feather-shopping-cart me-1"></i> Convert to Sales Order
                    </a>
                @endif

                @if($deal->quotations->isEmpty())
                    <a href="{{ route('crm.deals.show', ['deal' => $deal->id, 'create_quotation' => 1]) }}" class="btn btn-xs btn-success text-white fw-bold py-1 px-3 rounded shadow-sm d-inline-flex align-items-center" style="font-size: 11px;">
                        <i class="feather-file-plus me-1"></i> Create Quotation
                    </a>
                @endif

                <a href="{{ route('crm.deals.edit', $deal) }}" class="btn btn-xs btn-primary fw-bold py-1 px-3 rounded shadow-sm d-inline-flex align-items-center text-white" style="font-family: 'Inter', sans-serif; font-size: 11px;">
                    <i class="feather-edit me-1"></i> Edit Deal
                </a>

                <a href="{{ route('crm.deals.index') }}" class="btn btn-xs btn-outline-secondary fw-bold py-1 px-2.5 rounded bg-white text-dark border-secondary d-inline-flex align-items-center" style="font-size: 11px;">
                    <i class="feather-arrow-left me-1"></i> Back
                </a>

                <!-- Action Dropdown -->
                <x-ui.action-dropdown id="dealProfileActionsDropdown">
                    <li>
                        <a class="dropdown-item py-2" href="{{ route('crm.deals.edit', $deal) }}">
                            <i class="feather-edit me-1.5 text-muted"></i> Edit Deal Details
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item py-2" href="{{ route('crm.deals.show', ['deal' => $deal->id, 'create_quotation' => 1]) }}">
                            <i class="feather-file-text me-1.5 text-muted"></i> Add New Quotation
                        </a>
                    </li>
                </x-ui.action-dropdown>
                
                <!-- Pagination Arrows -->
                <div class="d-flex align-items-center ms-1 border rounded px-1 py-0.5 bg-white">
                    @if($prevDeal)
                        <a href="{{ route('crm.deals.show', $prevDeal) }}" class="btn btn-xs btn-link text-dark p-1 border-0 d-inline-flex align-items-center justify-content-center" title="Previous Deal">
                            <i class="feather-chevron-left fs-12"></i>
                        </a>
                    @else
                        <button class="btn btn-xs btn-link p-1 border-0 d-inline-flex align-items-center justify-content-center text-muted opacity-50" disabled>
                            <i class="feather-chevron-left fs-12"></i>
                        </button>
                    @endif

                    @if($nextDeal)
                        <a href="{{ route('crm.deals.show', $nextDeal) }}" class="btn btn-xs btn-link text-dark p-1 border-0 d-inline-flex align-items-center justify-content-center" title="Next Deal">
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
                    @endphp
                    <div class="zoho-pipeline-step {{ $stepClass }}" onclick="submitDealStage('{{ $stg }}')" title="Click to update deal stage to {{ $stg }} ({{ $prob }}%)">
                        @if($stepClass === 'passed')
                            <i class="feather-check-circle fs-11"></i>
                        @elseif(str_contains($stepClass, 'active'))
                            <i class="feather-disc fs-11"></i>
                        @else
                            <i class="feather-circle fs-10 opacity-50"></i>
                        @endif
                        <span>{{ $stg }} ({{ $prob }}%)</span>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Flash Messages -->
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show fs-13 py-2 px-3 m-3 mb-0" role="alert">
                <i class="feather-check-circle me-2"></i>{{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" style="padding: 0.5rem;"></button>
            </div>
        @endif

        <!-- ==================== TWO-COLUMN FLEX CONTENT ==================== -->
        <div class="d-flex flex-grow-1 overflow-hidden" style="min-height: 0;">
            
            <!-- Left Sidebar Menu (STICKY RELATED LIST) -->
            <div class="zoho-sidebar-col border-end bg-white d-print-none h-100 overflow-auto" style="width: 210px; flex-shrink: 0; user-select: none;">
                <div class="p-3">
                    <h6 class="text-uppercase fw-bold text-muted mb-3" style="font-size: 10px; letter-spacing: 0.8px;">Related List Navigation</h6>
                    <ul class="nav flex-column zoho-sidebar-nav gap-1" id="zohoSidebarLinks">
                        <li class="nav-item">
                            <a href="#sectionDealInfo" class="nav-link active">
                                <i class="feather-info fs-13 text-muted"></i> Deal Information
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#sectionCustomerCard" class="nav-link">
                                <i class="feather-users fs-13 text-muted"></i> Customer Details
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#sectionNotes" class="nav-link">
                                <i class="feather-grid fs-13 text-muted"></i> Notes & Requirements
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#sectionQuotations" class="nav-link">
                                <i class="feather-file-text fs-13 text-muted"></i> Quotations
                                <span class="badge bg-soft-secondary text-muted border">{{ $deal->quotations->count() }}</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#sectionQuotationHistory" class="nav-link">
                                <i class="feather-git-commit fs-13 text-muted"></i> Revision History
                                <span class="badge bg-soft-secondary text-muted border">{{ $deal->quotations->count() }}</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#sectionSalesOrders" class="nav-link">
                                <i class="feather-shopping-cart fs-13 text-muted"></i> Sales Orders
                                <span class="badge bg-soft-secondary text-muted border">{{ $deal->salesOrders->count() }}</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#subtab-interactions" class="nav-link">
                                <i class="feather-calendar fs-13 text-muted"></i> Activities & Calls
                                <span class="badge bg-soft-secondary text-muted border">{{ $followups->count() }}</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#subtab-history" class="nav-link">
                                <i class="feather-clock fs-13 text-muted"></i> History & Audit
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="#sectionDocuments" class="nav-link">
                                <i class="feather-paperclip fs-13 text-muted"></i> Documents
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
                                <i class="feather-grid me-1"></i>Overview
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link px-3 py-1 fw-bold fs-12 {{ $isQuotationTabActive ? 'active' : '' }}" id="quotations-tab" data-bs-toggle="tab" data-bs-target="#quotations-pane" type="button" role="tab">
                                <i class="feather-file-text me-1"></i>Quotation & Proposals ({{ $deal->quotations->count() }})
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link px-3 py-1 fw-bold fs-12 {{ $isSalesOrdersTabActive ? 'active' : '' }}" id="salesorders-tab" data-bs-toggle="tab" data-bs-target="#salesorders-pane" type="button" role="tab">
                                <i class="feather-shopping-cart me-1"></i>Sales Orders ({{ $deal->salesOrders->count() }})
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link px-3 py-1 fw-bold fs-12" id="timeline-tab" data-bs-toggle="tab" data-bs-target="#timeline-pane" type="button" role="tab">
                                <i class="feather-clock me-1"></i>Timeline & Audit
                            </button>
                        </li>
                    </ul>

                    <div class="d-flex align-items-center text-muted fs-11 fw-medium" style="font-family: 'Inter', sans-serif;">
                        <i class="feather-clock me-1.5 text-muted fs-12"></i> 
                        Last Update : {{ $deal->updated_at ? $deal->updated_at->diffForHumans() : 'Recently' }}
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
                                        <span class="fs-11 text-muted text-uppercase fw-bold d-block text-truncate">Estimated Deal Value</span>
                                        <span class="fs-14 fw-extrabold text-dark d-block">₹{{ number_format($deal->estimated_value, 2) }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6 d-flex">
                                <div class="deal-metric-card d-flex align-items-center gap-3 w-100">
                                    <div class="deal-metric-icon bg-soft-primary text-primary">
                                        <i class="feather-pie-chart"></i>
                                    </div>
                                    <div class="min-w-0 flex-grow-1">
                                        <span class="fs-11 text-muted text-uppercase fw-bold d-block text-truncate">Expected Revenue</span>
                                        <span class="fs-14 fw-extrabold text-primary d-block">₹{{ number_format($expectedRevenue, 2) }} <span class="fs-10 text-muted">({{ $deal->probability }}%)</span></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6 d-flex">
                                <div class="deal-metric-card d-flex align-items-center gap-3 w-100">
                                    <div class="deal-metric-icon bg-soft-warning text-warning">
                                        <i class="feather-calendar"></i>
                                    </div>
                                    <div class="min-w-0 flex-grow-1">
                                        <span class="fs-11 text-muted text-uppercase fw-bold d-block text-truncate">Target Closing Date</span>
                                        <span class="fs-14 fw-extrabold text-dark d-block">{{ $deal->closing_date ? $deal->closing_date->format('d M Y') : 'Not Set' }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 col-sm-6 d-flex">
                                <div class="deal-metric-card d-flex align-items-center gap-3 w-100">
                                    <div class="deal-metric-icon bg-soft-purple text-purple">
                                        <i class="feather-briefcase"></i>
                                    </div>
                                    <div class="min-w-0 flex-grow-1">
                                        <span class="fs-11 text-muted text-uppercase fw-bold d-block text-truncate">Account / Customer</span>
                                        <span class="fs-14 fw-extrabold text-dark text-truncate d-block">{{ $deal->account ? $deal->account->name : 'N/A' }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Deal Information Card -->
                        <div class="card border shadow-sm mb-3" style="border-radius: 4px; border-color: #e2e8f0 !important; background-color: #ffffff;" id="sectionDealInfo">
                            <div class="card-body p-3">
                                <div class="d-flex justify-content-between align-items-center pb-2 border-bottom mb-3">
                                    <h5 class="zoho-section-title fs-13 text-dark fw-bold mb-0"><i class="feather-info text-info me-1.5"></i>Deal Information & Stage Controls</h5>
                                </div>
                                <div class="row g-0">
                                    <div class="col-md-6 pe-md-4">
                                        <div class="zoho-field-row">
                                            <div class="zoho-field-label">Deal Title</div>
                                            <div class="zoho-field-value text-dark fw-bold">{{ $deal->title }}</div>
                                        </div>

                                        <div class="zoho-field-row">
                                            <div class="zoho-field-label">Deal Number</div>
                                            <div class="zoho-field-value text-primary font-monospace fw-bold">{{ $deal->deal_number }}</div>
                                        </div>

                                        <div class="zoho-field-row">
                                            <div class="zoho-field-label">Account (Company)</div>
                                            <div class="zoho-field-value text-dark fw-bold">
                                                @if($deal->account)
                                                    <a href="{{ route('crm.accounts.show', $deal->account) }}" class="text-primary hover-underline">{{ $deal->account->name }}</a>
                                                @else
                                                    —
                                                @endif
                                            </div>
                                        </div>

                                        <div class="zoho-field-row">
                                            <div class="zoho-field-label">Contact Person</div>
                                            <div class="zoho-field-value text-dark">{{ $deal->contact ? $deal->contact->name : '—' }}</div>
                                        </div>

                                        <div class="zoho-field-row">
                                            <div class="zoho-field-label">Target Closing Date</div>
                                            <div class="zoho-field-value text-dark">{{ $deal->closing_date ? $deal->closing_date->format('d/m/Y') : '—' }}</div>
                                        </div>
                                    </div>

                                    <div class="col-md-6 ps-md-4">
                                        <div class="zoho-field-row">
                                            <div class="zoho-field-label">Pipeline Stage</div>
                                            <div class="zoho-field-value text-primary fw-bold" style="width: 100%; max-width: 240px;">
                                                <select class="form-select odoo-select2 status-select" name="stage" style="border-radius:0;" onchange="submitDealStage(this.value)">
                                                    <option value="Qualification" @selected($deal->stage === 'Qualification' || $deal->stage === 'New')>Qualification (10%)</option>
                                                    <option value="Needs Analysis" @selected($deal->stage === 'Needs Analysis' || $deal->stage === 'Qualified')>Needs Analysis (30%)</option>
                                                    <option value="Proposal" @selected($deal->stage === 'Proposal')>Proposal (60%)</option>
                                                    <option value="Negotiation" @selected($deal->stage === 'Negotiation')>Negotiation (80%)</option>
                                                    <option value="Won" @selected($deal->stage === 'Won' || $deal->stage === 'Closed Won')>Won (100%)</option>
                                                    <option value="Lost" @selected($deal->stage === 'Lost' || $deal->stage === 'Closed Lost')>Lost (0%)</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="zoho-field-row">
                                            <div class="zoho-field-label">Closing Probability</div>
                                            <div class="zoho-field-value text-info fw-bold">{{ $deal->probability }}%</div>
                                        </div>

                                        <div class="zoho-field-row">
                                            <div class="zoho-field-label">Estimated Revenue</div>
                                            <div class="zoho-field-value text-dark fw-bold">₹{{ number_format($deal->estimated_value, 2) }}</div>
                                        </div>

                                        <div class="zoho-field-row">
                                            <div class="zoho-field-label">Actual Realized Revenue</div>
                                            <div class="zoho-field-value text-success fw-bold">₹{{ number_format($deal->actual_value, 2) }}</div>
                                        </div>

                                        <div class="zoho-field-row">
                                            <div class="zoho-field-label">Lead Source</div>
                                            <div class="zoho-field-value text-dark">{{ $deal->lead_source ?: 'Direct' }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- CUSTOMER ACCOUNT & CONTACT QUICK CARD -->
                        <div class="card border shadow-sm mb-3" style="border-radius: 4px; border-color: #e2e8f0 !important; background-color: #ffffff;" id="sectionCustomerCard">
                            <div class="card-body p-3">
                                <h5 class="fs-13 text-dark fw-bold mb-3"><i class="feather-users text-primary me-1.5"></i>Customer Account & Contact Information</h5>
                                <div class="row g-3">
                                    <div class="col-md-6 border-end">
                                        <div class="p-3 bg-light-50 rounded border">
                                            <div class="fw-bold text-dark fs-14 mb-1">{{ $deal->account ? $deal->account->name : 'No Account Linked' }}</div>
                                            <div class="fs-12 text-muted mb-2"><i class="feather-map-pin me-1"></i>{{ $deal->account ? ($deal->account->billing_address ?: 'Billing address not added') : '—' }}</div>
                                            @if($deal->account && $deal->account->phone)
                                                <div class="fs-12 text-dark"><i class="feather-phone me-1 text-muted"></i>{{ $deal->account->phone }}</div>
                                            @endif
                                            @if($deal->account && $deal->account->email)
                                                <div class="fs-12 text-primary"><i class="feather-mail me-1 text-muted"></i>{{ $deal->account->email }}</div>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="p-3 bg-light-50 rounded border">
                                            <div class="fw-bold text-dark fs-14 mb-1">{{ $deal->contact ? $deal->contact->name : 'No Contact Person' }}</div>
                                            <div class="fs-12 text-muted mb-2">{{ $deal->contact ? ($deal->contact->title ?: 'Primary Contact') : '—' }}</div>
                                            @if($deal->contact && $deal->contact->phone)
                                                <div class="fs-12 text-dark"><i class="feather-phone me-1 text-muted"></i>{{ $deal->contact->phone }}</div>
                                            @endif
                                            @if($deal->contact && $deal->contact->email)
                                                <div class="fs-12 text-primary"><i class="feather-mail me-1 text-muted"></i>{{ $deal->contact->email }}</div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Notes & Summary Box -->
                        <div class="card border shadow-sm mb-3" style="border-radius: 4px; border-color: #e2e8f0 !important; background-color: #ffffff;" id="sectionNotes">
                            <div class="card-body p-3">
                                <h6 class="fs-12 text-muted fw-bold text-uppercase mb-2"><i class="feather-file-text me-1 text-primary"></i>Deal Notes & Requirements Description</h6>
                                <div class="p-3 bg-light rounded text-dark fs-13" style="min-height: 50px;">
                                    {{ $deal->notes ?: 'No specific internal notes or requirements description added.' }}
                                </div>
                            </div>
                        </div>

                        <!-- Documents Card -->
                        <div class="card border shadow-sm mb-3" style="border-radius: 4px; border-color: #e2e8f0 !important; background-color: #ffffff;" id="sectionDocuments">
                            <div class="card-body p-3">
                                <h5 class="fs-13 text-dark fw-bold mb-3"><i class="feather-paperclip me-1.5"></i>Attached Documents & Files</h5>
                                <div class="row g-3">
                                    @forelse($leadDocuments as $doc)
                                        <div class="col-md-4">
                                            <div class="p-2.5 border rounded bg-white d-flex align-items-center justify-content-between">
                                                <div class="d-flex align-items-center gap-2">
                                                    <i class="feather-file-text text-primary fs-20"></i>
                                                    <div>
                                                        <div class="fw-bold text-dark fs-12 text-truncate" style="max-width: 150px;">{{ $doc->file_name }}</div>
                                                        <span class="text-muted fs-10">{{ strtoupper($doc->file_type) }}</span>
                                                    </div>
                                                </div>
                                                <a href="{{ Storage::url($doc->file_path) }}" target="_blank" class="btn btn-xs btn-light border">
                                                    <i class="feather-download"></i>
                                                </a>
                                            </div>
                                        </div>
                                    @empty
                                        <div class="col-12 text-center py-3 text-muted fs-12">No documents attached yet.</div>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ==================== TAB 2: QUOTATIONS & PROPOSALS PANE ==================== -->
                    <div class="tab-pane fade {{ $isQuotationTabActive ? 'show active' : '' }}" id="quotations-pane" role="tabpanel">
                        
                        @if (request()->has('create_quotation') || old('form_type') === 'quotation_create')
                            <!-- CREATE QUOTATION INLINE FORM -->
                            <div class="card border shadow-sm mb-4" style="border-radius: 4px; border-color: #e2e8f0 !important; background-color: #ffffff;">
                                <div class="card-body p-4">
                                    <form action="{{ route('crm.quotations.store') }}" method="POST" id="quotationForm" novalidate>
                                        @csrf
                                        <input type="hidden" name="crm_deal_id" value="{{ $deal->id }}">
                                        <input type="hidden" name="form_type" value="quotation_create">

                                        <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">
                                            <h5 class="fw-bold text-dark mb-0 fs-16"><i class="feather-file-plus text-primary me-2"></i>New Quotation</h5>
                                            <a href="{{ route('crm.deals.show', $deal->id) }}" class="btn btn-sm btn-light border">Cancel</a>
                                        </div>

                                        <div class="row g-4 mb-4 fs-13 text-dark">
                                            <div class="col-md-6">
                                                <x-ui.odoo-form-ui type="input" label="Customer / Account" name="_customer_display"
                                                    :value="$deal->account ? $deal->account->name : ($deal->contact ? $deal->contact->name : 'N/A')"
                                                    readonly="true"
                                                    style="font-weight: bold; color: var(--bs-primary); background-color: #f8f9fa;" />

                                                <x-ui.odoo-form-ui type="input" label="Contact Email" name="email" :value="old('email', $deal->contact ? $deal->contact->email : '')" :errorText="$errors->first('email')" />
                                                <x-ui.odoo-form-ui type="input" label="Contact Phone" name="phone" :value="old('phone', $deal->contact ? $deal->contact->phone : '')" :errorText="$errors->first('phone')" />
                                            </div>
                                            <div class="col-md-6">
                                                <x-ui.odoo-form-ui type="input" label="Quotation Number" name="quotation_number"
                                                    :value="old('quotation_number', $nextQuotationNumber)" readonly="true"
                                                    style="font-weight: bold; color: #495057;"
                                                    :errorText="$errors->first('quotation_number')" />

                                                <x-ui.odoo-form-ui type="input" inputType="date" label="Quotation Date" name="quotation_date"
                                                    :value="old('quotation_date', date('Y-m-d'))" :errorText="$errors->first('quotation_date')" />

                                                <x-ui.odoo-form-ui type="input" inputType="date" label="Expiration Date" name="expiry_date"
                                                    :value="old('expiry_date', date('Y-m-d', strtotime('+30 days')))" :errorText="$errors->first('expiry_date')" />

                                                <x-ui.odoo-form-ui type="select" label="Initial Status" name="status" :required="true" :errorText="$errors->first('status')">
                                                     <option value="Draft" @selected(old('status') === 'Draft')>Draft</option>
                                                     <option value="Pending Approval" @selected(old('status') === 'Pending Approval')>Pending Approval</option>
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
                                                            <th style="width: 45%;">Product Description</th>
                                                            <th class="text-end" style="width: 12%;">Qty</th>
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
                                                <button type="button" class="btn btn-xs btn-outline-primary fw-bold" id="addItemRow" style="font-size: 10px; padding: 2px 8px; text-transform: none !important;">
                                                    <i class="feather-plus me-1"></i>Add a product
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Subtotal / Discount / Totals -->
                                        <div class="row mt-4 pt-3 border-top text-dark fs-13">
                                            <div class="col-md-8">
                                                <div class="pe-md-4">
                                                    <x-ui.odoo-form-ui type="textarea" label="Terms & Conditions" name="terms_conditions" rows="3" :errorText="$errors->first('terms_conditions')">{{ old('terms_conditions') }}</x-ui.odoo-form-ui>
                                                    <x-ui.odoo-form-ui type="textarea" label="Internal Notes" name="notes" rows="2" placeholder="Notes for internal view..." :errorText="$errors->first('notes')">{{ old('notes') }}</x-ui.odoo-form-ui>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="d-flex justify-content-between py-1 border-bottom">
                                                    <span class="text-muted fw-semibold">Subtotal:</span>
                                                    <span class="fw-bold text-dark" id="calcSubtotal">₹0.00</span>
                                                </div>
                                                <div class="d-flex justify-content-between py-1 border-bottom">
                                                    <span class="text-muted fw-semibold">Taxes:</span>
                                                    <span class="fw-bold text-dark" id="calcTax">₹0.00</span>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                                    <span class="text-muted fw-semibold me-2">Discount:</span>
                                                    <x-ui.odoo-form-ui type="input" name="discount" id="discountInput" inputType="number" :value="old('discount', 0)" min="0" step="0.01" class="text-end fw-bold" :errorText="$errors->first('discount')" />
                                                </div>
                                                <div class="d-flex justify-content-between py-2 fs-15 border-bottom bg-light-50 px-2 rounded mt-1.5">
                                                    <span class="text-dark fw-bold">Grand Total:</span>
                                                    <span class="fw-extrabold text-primary" id="calcTotal">₹0.00</span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                                            <a href="{{ route('crm.deals.show', $deal->id) }}" class="btn btn-md btn-light border py-2 px-4 shadow-sm fs-12">Discard</a>
                                            <button type="submit" class="btn btn-md btn-primary py-2 px-5 fw-bold shadow-sm fs-12">Save Quotation</button>
                                        </div>
                                    </form>
                                </div>
                            </div>

                        @elseif ((request()->has('edit_quotation') || old('form_type') === 'quotation_edit') && $activeQuotation)
                            <!-- EDIT QUOTATION INLINE FORM -->
                            <div class="card border shadow-sm mb-4" style="border-radius: 4px; border-color: #e2e8f0 !important; background-color: #ffffff;">
                                <div class="card-body p-4">
                                    <form action="{{ route('crm.quotations.update', $activeQuotation->id) }}" method="POST" id="quotationForm" novalidate>
                                        @csrf
                                        @method('PUT')
                                        <input type="hidden" name="crm_deal_id" value="{{ $deal->id }}">
                                        <input type="hidden" name="form_type" value="quotation_edit">

                                        <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-2">
                                            <h5 class="fw-bold text-dark mb-0 fs-16"><i class="feather-edit text-warning me-2"></i>Edit Quotation: {{ $activeQuotation->quotation_number }}</h5>
                                            <a href="{{ route('crm.deals.show', ['deal' => $deal->id, 'quotation_id' => $activeQuotation->id]) }}" class="btn btn-sm btn-light border">Cancel</a>
                                        </div>

                                        <div class="row g-4 mb-4 fs-13 text-dark">
                                            <div class="col-md-6">
                                                <x-ui.odoo-form-ui type="input" label="Customer / Account" name="_customer_display"
                                                    :value="$deal->account ? $deal->account->name : ($deal->contact ? $deal->contact->name : 'N/A')"
                                                    readonly="true"
                                                    style="font-weight: bold; color: var(--bs-primary); background-color: #f8f9fa;" />

                                                <x-ui.odoo-form-ui type="input" label="Contact Email" name="email" :value="old('email', $activeQuotation->email ?: ($deal->contact ? $deal->contact->email : ''))" :errorText="$errors->first('email')" />
                                                <x-ui.odoo-form-ui type="input" label="Contact Phone" name="phone" :value="old('phone', $activeQuotation->phone ?: ($deal->contact ? $deal->contact->phone : ''))" :errorText="$errors->first('phone')" />
                                            </div>
                                            <div class="col-md-6">
                                                <x-ui.odoo-form-ui type="input" label="Quotation Number" name="quotation_number"
                                                    :value="$activeQuotation->quotation_number" readonly="true"
                                                    style="font-weight: bold; color: #495057;"
                                                    :errorText="$errors->first('quotation_number')" />

                                                <x-ui.odoo-form-ui type="input" inputType="date" label="Quotation Date" name="quotation_date"
                                                    :value="old('quotation_date', $activeQuotation->quotation_date ? \Illuminate\Support\Carbon::parse($activeQuotation->quotation_date)->format('Y-m-d') : date('Y-m-d'))" :errorText="$errors->first('quotation_date')" />

                                                <x-ui.odoo-form-ui type="input" inputType="date" label="Expiration Date" name="expiry_date"
                                                    :value="old('expiry_date', $activeQuotation->expiry_date ? \Illuminate\Support\Carbon::parse($activeQuotation->expiry_date)->format('Y-m-d') : '')" :errorText="$errors->first('expiry_date')" />

                                                <x-ui.odoo-form-ui type="select" label="Status" name="status" :required="true" :errorText="$errors->first('status')">
                                                     <option value="Draft" @selected(old('status', $activeQuotation->status) === 'Draft')>Draft</option>
                                                     <option value="Pending Approval" @selected(old('status', $activeQuotation->status) === 'Pending Approval' || old('status', $activeQuotation->status) === 'Rejected' || old('status', $activeQuotation->status) === 'Quotation Rework' || old('status', $activeQuotation->status) === 'Approved' || old('status', $activeQuotation->status) === 'Declined')>Pending Approval</option>
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
                                                            <th style="width: 45%;">Product Description</th>
                                                            <th class="text-end" style="width: 12%;">Qty</th>
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
                                                <button type="button" class="btn btn-xs btn-outline-primary fw-bold" id="addItemRow" style="font-size: 10px; padding: 2px 8px; text-transform: none !important;">
                                                    <i class="feather-plus me-1"></i>Add a product
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Subtotal / Discount / Totals -->
                                        <div class="row mt-4 pt-3 border-top text-dark fs-13">
                                            <div class="col-md-8">
                                                <div class="pe-md-4">
                                                    <x-ui.odoo-form-ui type="textarea" label="Terms & Conditions" name="terms_conditions" rows="3" :errorText="$errors->first('terms_conditions')">{{ old('terms_conditions', $activeQuotation->terms_conditions) }}</x-ui.odoo-form-ui>
                                                    <x-ui.odoo-form-ui type="textarea" label="Internal Notes" name="notes" rows="2" placeholder="Notes for internal view..." :errorText="$errors->first('notes')">{{ old('notes', $activeQuotation->notes) }}</x-ui.odoo-form-ui>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="d-flex justify-content-between py-1 border-bottom">
                                                    <span class="text-muted fw-semibold">Subtotal:</span>
                                                    <span class="fw-bold text-dark" id="calcSubtotal">₹0.00</span>
                                                </div>
                                                <div class="d-flex justify-content-between py-1 border-bottom">
                                                    <span class="text-muted fw-semibold">Taxes:</span>
                                                    <span class="fw-bold text-dark" id="calcTax">₹0.00</span>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                                    <span class="text-muted fw-semibold me-2">Discount:</span>
                                                    <x-ui.odoo-form-ui type="input" name="discount" id="discountInput" inputType="number" :value="old('discount', $activeQuotation->discount)" min="0" step="0.01" class="text-end fw-bold" :errorText="$errors->first('discount')" />
                                                </div>
                                                <div class="d-flex justify-content-between py-2 fs-15 border-bottom bg-light-50 px-2 rounded mt-1.5">
                                                    <span class="text-dark fw-bold">Grand Total:</span>
                                                    <span class="fw-extrabold text-primary" id="calcTotal">₹0.00</span>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                                            <a href="{{ route('crm.deals.show', ['deal' => $deal->id, 'quotation_id' => $activeQuotation->id]) }}" class="btn btn-md btn-light border py-2 px-4 shadow-sm fs-12">Discard</a>
                                            <button type="submit" class="btn btn-md btn-primary py-2 px-5 fw-bold shadow-sm fs-12">Save Changes</button>
                                        </div>
                                    </form>
                                </div>
                            </div>

                        @elseif($activeQuotation)
                            <!-- ACTIVE QUOTATION DETAILS CARD VIEW -->
                            <div class="card border shadow-sm mb-4" style="border-radius: 4px; border-color: #e2e8f0 !important; background-color: #ffffff;" id="sectionQuotations">
                                <div class="card-body p-4">
                                    <div class="d-flex justify-content-between align-items-center pb-3 border-bottom mb-4 flex-wrap gap-2">
                                        <div>
                                            <h4 class="fw-bold text-dark mb-0 fs-16">Quotation {{ $activeQuotation->quotation_number }}</h4>
                                            <span class="badge bg-light text-dark border font-monospace mt-1">Revision {{ $activeQuotation->revision_number }}</span>
                                        </div>

                                        <div class="d-flex flex-wrap gap-2">
                                            <a href="{{ route('crm.quotations.download', $activeQuotation->id) }}" class="btn btn-xs btn-primary"><i class="feather-printer me-1"></i>Print / Download</a>
                                            <a href="{{ route('crm.quotations.show', $activeQuotation->id) }}" class="btn btn-xs btn-light border"><i class="feather-eye me-1"></i>View Full Quote</a>
                                            <a href="{{ route('crm.deals.show', ['deal' => $deal->id, 'edit_quotation' => 1, 'quotation_id' => $activeQuotation->id]) }}" class="btn btn-xs btn-warning text-dark fw-bold"><i class="feather-edit-2 me-1"></i>Edit Quotation</a>
                                            
                                            @if ($activeQuotation->status === 'Draft' || $activeQuotation->status === 'Quotation Rework')
                                                <form action="{{ route('crm.quotations.updateStatus', $activeQuotation->id) }}" method="POST" class="d-inline m-0">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="status" value="Pending Approval">
                                                    <button type="submit" class="btn btn-xs btn-warning text-dark fw-bold"><i class="feather-send me-1"></i>Send for Approval</button>
                                                </form>
                                            @elseif ($activeQuotation->status === 'Approved')
                                                <form action="{{ route('crm.quotations.updateStatus', $activeQuotation->id) }}" method="POST" class="d-inline m-0">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="status" value="Quotation Sent">
                                                    <button type="submit" class="btn btn-xs btn-primary fw-bold"><i class="feather-send me-1"></i>Mark as Sent</button>
                                                </form>
                                            @elseif ($activeQuotation->status === 'Quotation Sent' || $activeQuotation->status === 'Sent')
                                                <form action="{{ route('crm.quotations.updateStatus', $activeQuotation->id) }}" method="POST" class="d-inline m-0">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="status" value="Accepted">
                                                    <button type="submit" class="btn btn-xs btn-success fw-bold"><i class="feather-check-circle me-1"></i>Accept Quote</button>
                                                </form>
                                                <form action="{{ route('crm.quotations.updateStatus', $activeQuotation->id) }}" method="POST" class="d-inline m-0">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="status" value="Rejected">
                                                    <button type="submit" class="btn btn-xs btn-soft-danger fw-bold"><i class="feather-x-circle me-1"></i>Reject</button>
                                                </form>
                                            @elseif ($activeQuotation->status === 'Accepted')
                                                <a href="{{ route('sales.orders.create', ['quotation_id' => $activeQuotation->id]) }}" class="btn btn-xs btn-success fw-bold">
                                                    <i class="feather-shopping-cart me-1"></i>Convert to Sales Order
                                                </a>
                                            @endif
                                        </div>
                                    </div>

                                    <!-- Quotation Header Grid -->
                                    <div class="row g-4 mb-4 fs-13 text-dark">
                                        <div class="col-md-6 border-end">
                                            <div class="mb-3">
                                                <label class="text-muted fs-11 text-uppercase fw-bold d-block mb-1">Customer / Company</label>
                                                <div class="fw-bold text-dark fs-14">{{ $deal->account ? $deal->account->name : ($deal->contact ? $deal->contact->name : 'N/A') }}</div>
                                            </div>
                                            <div class="row">
                                                <div class="col-6">
                                                    <label class="text-muted fs-11 text-uppercase fw-bold d-block mb-1">Contact Email</label>
                                                    <div class="fs-12 text-primary">{{ $activeQuotation->email ?: ($deal->contact ? $deal->contact->email : '—') }}</div>
                                                </div>
                                                <div class="col-6">
                                                    <label class="text-muted fs-11 text-uppercase fw-bold d-block mb-1">Contact Phone</label>
                                                    <div class="fs-12 text-dark">{{ $activeQuotation->phone ?: ($deal->contact ? $deal->contact->phone : '—') }}</div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6 ps-md-4">
                                            <div class="row">
                                                <div class="col-6 mb-3">
                                                    <label class="text-muted fs-11 text-uppercase fw-bold d-block mb-1">Quotation Date</label>
                                                    <div class="fw-semibold text-dark">{{ $activeQuotation->quotation_date ? \Illuminate\Support\Carbon::parse($activeQuotation->quotation_date)->format('d M Y') : '—' }}</div>
                                                </div>
                                                <div class="col-6 mb-3">
                                                    <label class="text-muted fs-11 text-uppercase fw-bold d-block mb-1">Expiration Date</label>
                                                    <div class="fw-semibold text-danger">{{ $activeQuotation->expiry_date ? \Illuminate\Support\Carbon::parse($activeQuotation->expiry_date)->format('d M Y') : '—' }}</div>
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <label class="text-muted fs-11 text-uppercase fw-bold d-block mb-1">Quotation Status</label>
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
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Order Lines Table -->
                                    <h6 class="fw-bold text-dark mb-2 fs-13">Order Lines</h6>
                                    <div class="table-responsive mb-4">
                                        <x-ui.odoo-form-ui type="table">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>Product Description</th>
                                                    <th class="text-end">Qty</th>
                                                    <th class="text-end">Unit Price (₹)</th>
                                                    <th class="text-end">Tax (%)</th>
                                                    <th class="text-end">Amount (₹)</th>
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
                                                        <td class="text-end font-monospace">₹{{ number_format($item->unit_price, 2) }}</td>
                                                        <td class="text-end font-monospace">{{ $item->tax_rate }}%</td>
                                                        <td class="text-end font-monospace fw-bold text-success">₹{{ number_format($item->total_price, 2) }}</td>
                                                    </tr>
                                                @empty
                                                    <tr>
                                                        <td colspan="6" class="text-center py-3 text-muted">No line items in this quotation.</td>
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
                                                    <strong class="text-muted fs-11 text-uppercase fw-bold d-block">Terms & Conditions:</strong>
                                                    <div class="fs-12 text-muted mt-1">{!! $activeQuotation->terms_conditions !!}</div>
                                                </div>
                                            @endif
                                        </div>
                                        <div class="col-md-5">
                                            <div class="d-flex justify-content-between py-1 border-bottom">
                                                <span class="text-muted">Subtotal:</span>
                                                <span class="fw-bold">₹{{ number_format($activeQuotation->subtotal ?? 0, 2) }}</span>
                                            </div>
                                            <div class="d-flex justify-content-between py-1 border-bottom">
                                                <span class="text-muted">Tax Amount:</span>
                                                <span class="fw-bold">₹{{ number_format($activeQuotation->tax_amount ?? 0, 2) }}</span>
                                            </div>
                                            <div class="d-flex justify-content-between py-2 fs-15 border-bottom bg-light-50 px-2 rounded mt-1">
                                                <span class="fw-bold text-dark">Grand Total:</span>
                                                <span class="fw-extrabold text-primary">₹{{ number_format($activeQuotation->total_amount, 2) }}</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @else
                            <div class="card border shadow-sm mb-4" style="border-radius: 4px; border-color: #e2e8f0 !important; background-color: #ffffff;">
                                <div class="card-body p-4 text-center">
                                    <i class="feather-file-text fs-36 text-muted mb-2 d-block opacity-50"></i>
                                    <h5 class="fw-bold text-dark fs-14">No Quotation Created Yet</h5>
                                    <p class="text-muted fs-12 mb-3">Create a quotation for this deal to generate quotation sheets and track revisions.</p>
                                    <a href="{{ route('crm.deals.show', ['deal' => $deal->id, 'create_quotation' => 1]) }}" class="btn btn-sm btn-success fw-bold px-3">
                                        <i class="feather-plus me-1"></i>Create Quotation Now
                                    </a>
                                </div>
                            </div>
                        @endif

                        <!-- QUOTATION REVISION HISTORY CHIP CARDS -->
                        @if($deal->quotations->count() > 0)
                            <div class="card border shadow-sm mb-3" style="border-radius: 4px; border-color: #e2e8f0 !important; background-color: #ffffff;" id="sectionQuotationHistory">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h5 class="fs-13 text-dark fw-bold mb-0">
                                            <i class="feather-git-commit me-1.5 text-primary"></i>Quotation Revision History
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
                                                        <span class="position-absolute top-0 end-0 translate-middle-y badge rounded-pill bg-primary fs-8 text-uppercase px-1" style="font-size: 8px !important; margin-right: 10px;">Viewing</span>
                                                    @endif
                                                    <div class="avatar-text avatar-sm bg-soft-secondary text-secondary rounded-circle fw-bold d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; font-size: 10px;">
                                                        R{{ $rev->revision_number }}
                                                    </div>
                                                    <div class="d-flex flex-column fs-11" style="font-family: 'Inter', sans-serif;">
                                                        <a href="{{ route('crm.deals.show', ['deal' => $deal->id, 'quotation_id' => $rev->id]) }}" class="fw-bold text-dark text-decoration-none">
                                                            {{ $rev->quotation_number }}
                                                        </a>
                                                        <span class="text-muted mt-0.5" style="font-size: 9px;">₹{{ number_format($rev->total_amount, 2) }}</span>
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
                                        <h5 class="fs-15 text-dark fw-bold mb-0"><i class="feather-shopping-cart text-success me-2"></i>Converted Sales Orders & Realized Revenue</h5>
                                        <span class="text-muted fs-12">Track sales orders, order status, and billing generated from this deal.</span>
                                    </div>
                                    @if($activeQuotation && $activeQuotation->status === 'Accepted')
                                        <a href="{{ route('sales.orders.create', ['quotation_id' => $activeQuotation->id]) }}" class="btn btn-sm btn-success fw-bold px-3">
                                            <i class="feather-plus me-1"></i>New Sales Order
                                        </a>
                                    @endif
                                </div>

                                @if($deal->salesOrders->isEmpty())
                                    <div class="text-center py-5 text-muted border border-dashed rounded bg-light-50">
                                        <i class="feather-shopping-bag fs-36 text-muted mb-2 d-block opacity-50"></i>
                                        <h6 class="fw-bold text-dark fs-13">No Sales Orders Generated Yet</h6>
                                        <p class="fs-12 text-muted max-w-md mx-auto">When a customer accepts a quotation for this deal, convert it into a Sales Order to track fulfillment and invoicing.</p>
                                    </div>
                                @else
                                    <div class="table-responsive">
                                        <x-ui.odoo-form-ui type="table">
                                            <thead>
                                                <tr>
                                                    <th>Sales Order #</th>
                                                    <th>Order Date</th>
                                                    <th>Customer Name</th>
                                                    <th>Total Amount (₹)</th>
                                                    <th>Order Status</th>
                                                    <th class="text-end pe-3">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody class="fs-13 text-dark">
                                                @foreach($deal->salesOrders as $so)
                                                    <tr>
                                                        <td class="font-monospace fw-bold text-primary">{{ $so->order_number }}</td>
                                                        <td>{{ $so->order_date ? \Illuminate\Support\Carbon::parse($so->order_date)->format('d/m/Y') : '—' }}</td>
                                                        <td class="fw-bold text-dark">{{ $so->customer_name }}</td>
                                                        <td class="fw-bold text-success font-monospace">₹{{ number_format($so->total_amount, 2) }}</td>
                                                        <td>
                                                            <span class="badge bg-soft-success text-success border border-success-subtle px-2 py-0.5 fw-bold">
                                                                {{ $so->status }}
                                                            </span>
                                                        </td>
                                                        <td class="text-end pe-3">
                                                            <a href="{{ route('sales.orders.show', $so) }}" class="btn btn-xs btn-soft-primary fw-bold">
                                                                <i class="feather-eye me-1"></i>View Order
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
                                                History & Audit Stream
                                            </button>
                                        </li>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link py-2 px-3 border-0 bg-transparent" id="subtab-interactions-tab" data-bs-toggle="tab" data-bs-target="#subtab-interactions" type="button" role="tab">
                                                Interactions & Scheduled Calls
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
                                                <h5 class="fw-bold text-dark fs-14 mb-0">Timeline History & Audit Stream</h5>
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
                                                    No history events recorded yet.
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
                                            <h5 class="fw-bold text-dark fs-14 mb-0">Interactions / Scheduled Activities</h5>
                                            @if($linkedLead)
                                                <a href="{{ route('crm.leads.show', $linkedLead) }}#followups" class="btn btn-xs text-white fw-bold px-3 py-1.5" style="background-color: #2b304a;">
                                                    <i class="feather-calendar me-1"></i>SCHEDULE ACTIVITY
                                                </a>
                                            @endif
                                        </div>

                                        @if($followups->isEmpty())
                                            <div class="text-center py-5 text-muted border rounded bg-light-50" style="background-color: #f8fafc;">
                                                <i class="feather-calendar fs-30 mb-2 d-block text-muted opacity-50"></i>
                                                <div class="fw-bold text-dark fs-13">No activities scheduled yet.</div>
                                                <div class="fs-11 text-muted">Click "Schedule Activity" to add one.</div>
                                            </div>
                                        @else
                                            <div class="d-flex flex-column gap-2">
                                                @foreach($followups as $f)
                                                    <div class="p-3 border rounded bg-white shadow-2xs">
                                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                                            <div class="d-flex align-items-center gap-2">
                                                                <span class="badge bg-soft-primary text-primary border font-monospace fs-11">{{ $f->interaction_type ?: 'Call' }}</span>
                                                                <span class="fw-bold text-dark fs-13">{{ $f->summary ?: 'Follow-up' }}</span>
                                                            </div>
                                                            <span class="text-muted fs-11"><i class="feather-clock me-1"></i>{{ \Illuminate\Support\Carbon::parse($f->followup_date)->format('d/m/Y h:i A') }}</span>
                                                        </div>
                                                        @if($f->remarks)
                                                            <div class="text-muted fs-12 mt-1">{{ $f->remarks }}</div>
                                                        @endif
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
            </div>
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
                    opts += `<option value="${p.id}" data-selling-price="${p.selling_price || 0}"${sel}>${p.name} ${p.sku ? '('+p.sku+')' : ''}</option>`;
                });
                return opts;
            }

            function getRowHtml(index, selectedId = '') {
                return `
                    <tr class="item-row" data-row-id="${index}">
                        <td class="ps-3">
                            <select name="items[${index}][product_id]" class="odoo-table-select odoo-select2 item-name-input erp-premium-select" required data-master="product" style="width:100%;">
                                ${buildProductOptions(selectedId)}
                            </select>
                            <div class="description-container mt-2" id="desc-container-${index}" style="display: none;">
                                <textarea name="items[${index}][description]" class="form-control odoo-table-input" placeholder="Scope details / custom specifications..."></textarea>
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
                }

                newRow.find('.item-name-input').on('change', function() {
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

            $('#addItemRow').on('click', function() { addRow(); });

            const hasCreateQ = @json(request()->has('create_quotation') || old('form_type') === 'quotation_create');
            const hasEditQ = @json(request()->has('edit_quotation') || old('form_type') === 'quotation_edit');
            const existingItems = @json(old('items') ?: (isset($activeQuotation) ? $activeQuotation->items : []));

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
        });
    </script>
    @endpush
@endsection
