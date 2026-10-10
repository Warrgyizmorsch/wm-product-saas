@extends('layouts.duralux')

@section('title', __('crm.lead_details') . ' | SaaS ERP')
@section('page-title', __('crm.lead_profile'))
@section('breadcrumb', 'CRM / ' . __('crm.leads') . ' / ' . __('crm.profile'))

@section('content')
    @php
        $tenantSettings = is_array(tenant()?->settings) ? tenant()->settings : [];
        $isQuotationAutoApprove = ($tenantSettings['quotation_approval_policy'] ?? 'approval_required') === 'auto_approve';
    @endphp
    <style>
        .requirement-clickable-box {
            cursor: pointer;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-left: 4px solid var(--bs-primary) !important;
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .requirement-clickable-box:hover {
            border-color: var(--bs-primary) !important;
            border-left: 4px solid var(--bs-primary) !important;
            background: #ffffff !important;
            box-shadow: 0 6px 20px rgba(30, 64, 175, 0.1) !important;
            transform: translateY(-1px);
        }
        .requirement-clickable-box:hover .edit-hint-badge {
            background-color: var(--bs-primary) !important;
            color: #ffffff !important;
            border-color: var(--bs-primary) !important;
        }
        .requirement-clickable-box:hover .edit-hint-badge i {
            color: #ffffff !important;
        }
        .requirement-empty-box {
            border: 2px dashed #cbd5e1 !important;
            background-color: #f8fafc;
            transition: all 0.25s ease;
        }
        .requirement-empty-box:hover {
            border-color: var(--bs-primary) !important;
            background-color: #eff6ff !important;
        }
        .quotation-rev-chip {
            min-width: 170px;
            transition: all 0.2s ease;
            position: relative;
            background-color: #ffffff;
            border: 1px solid #e2e8f0;
        }
        .quotation-rev-chip:hover {
            border-color: #cbd5e1;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        .quotation-rev-chip.active {
            border-color: var(--bs-primary) !important;
            background-color: #f0f9ff;
            box-shadow: 0 0 0 1px rgba(59, 130, 246, 0.2);
        }

        /* ==========================================================================
           DARK MODE SUPPORT (html.app-skin-dark)
           ========================================================================== */
        html.app-skin-dark .quotation-rev-chip {
            background-color: #162038 !important;
            border-color: #1e293b !important;
        }
        html.app-skin-dark .quotation-rev-chip:hover {
            background-color: #1e293b !important;
            border-color: #334155 !important;
        }
        html.app-skin-dark .quotation-rev-chip.active {
            background-color: #1e293b !important;
            border-color: #3b82f6 !important;
            box-shadow: 0 0 0 1px rgba(59, 130, 246, 0.4) !important;
        }
        html.app-skin-dark .zoho-lead-card-container,
        html.app-skin-dark .zoho-main-col,
        html.app-skin-dark #zohoMainScrollable,
        html.app-skin-dark .tab-content,
        html.app-skin-dark .tab-pane {
            background-color: #0b1329 !important;
            border-color: #1e293b !important;
            color: #e2e8f0 !important;
        }
        html.app-skin-dark .zoho-sidebar-col {
            background-color: #0f172a !important;
            border-color: #1e293b !important;
        }
        html.app-skin-dark .sticky-top,
        html.app-skin-dark div[style*="background-color: #f8fafc"],
        html.app-skin-dark div[style*="background-color:#f8fafc"] {
            background-color: #0b1329 !important;
            border-color: #1e293b !important;
        }
        html.app-skin-dark .zoho-header-banner {
            background-color: #111827 !important;
            border-bottom-color: #1e293b !important;
        }
        html.app-skin-dark .zoho-sidebar-nav {
            background-color: #0f172a !important;
            border-color: #1e293b !important;
        }
        html.app-skin-dark .zoho-sidebar-nav .nav-link {
            color: #94a3b8 !important;
        }
        html.app-skin-dark .zoho-sidebar-nav .nav-link:hover {
            background-color: #1e293b !important;
            color: #60a5fa !important;
        }
        html.app-skin-dark .zoho-sidebar-nav .nav-link.active {
            background-color: var(--bs-primary) !important;
            color: #ffffff !important;
        }
        html.app-skin-dark .zoho-nav-tabs .nav-link {
            border-color: #334155 !important;
            background-color: #162038 !important;
            color: #94a3b8 !important;
        }
        html.app-skin-dark .zoho-nav-tabs .nav-link:hover {
            background-color: #1e293b !important;
            color: #f8fafc !important;
            border-color: #475569 !important;
        }
        html.app-skin-dark .zoho-nav-tabs .nav-link.active {
            background-color: var(--bs-primary) !important;
            color: #ffffff !important;
            border-color: var(--bs-primary) !important;
        }
        html.app-skin-dark .zoho-field-row {
            border-bottom-color: #1e293b !important;
        }
        html.app-skin-dark .zoho-field-label {
            color: #94a3b8 !important;
        }
        html.app-skin-dark .zoho-field-value {
            color: #f8fafc !important;
        }
        html.app-skin-dark .zoho-lead-card-container .card,
        html.app-skin-dark div[style*="background-color: #ffffff"],
        html.app-skin-dark div[style*="background-color:#ffffff"],
        html.app-skin-dark .zoho-lead-card-container .bg-white,
        html.app-skin-dark .bg-white {
            background-color: #111827 !important;
            border-color: #1e293b !important;
            color: #e2e8f0 !important;
        }
        html.app-skin-dark .zoho-lead-card-container .bg-light,
        html.app-skin-dark .bg-light {
            background-color: #162038 !important;
            border-color: #1e293b !important;
        }
        html.app-skin-dark .zoho-lead-card-container .border,
        html.app-skin-dark .zoho-lead-card-container .border-bottom,
        html.app-skin-dark .zoho-lead-card-container .border-top,
        html.app-skin-dark .zoho-lead-card-container .border-end,
        html.app-skin-dark .zoho-lead-card-container .border-start,
        html.app-skin-dark .border {
            border-color: #1e293b !important;
        }
        html.app-skin-dark .zoho-lead-card-container .text-dark,
        html.app-skin-dark .text-dark {
            color: #f8fafc !important;
        }
        html.app-skin-dark .zoho-lead-card-container .text-muted,
        html.app-skin-dark .text-muted {
            color: #94a3b8 !important;
        }
        html.app-skin-dark .requirement-clickable-box {
            background: linear-gradient(135deg, #162038 0%, #1e293b 100%) !important;
        }
        html.app-skin-dark .requirement-clickable-box:hover {
            background: #1e293b !important;
        }
        html.app-skin-dark .requirement-empty-box {
            background-color: #162038 !important;
            border-color: #334155 !important;
        }
        html.app-skin-dark .table-responsive table,
        html.app-skin-dark .table {
            color: #f1f5f9 !important;
            border-color: #1e293b !important;
        }
        html.app-skin-dark .table th,
        html.app-skin-dark .table.odoo-table th {
            background-color: #162038 !important;
            color: #94a3b8 !important;
            border-color: #1e293b !important;
            border-bottom-color: #1e293b !important;
        }
        html.app-skin-dark .table td,
        html.app-skin-dark .table.odoo-table td {
            border-color: #1e293b !important;
            border-bottom-color: #1e293b !important;
            color: #f1f5f9 !important;
        }
        html.app-skin-dark input,
        html.app-skin-dark select,
        html.app-skin-dark textarea,
        html.app-skin-dark .form-control,
        html.app-skin-dark .form-select {
            background-color: #162038 !important;
            border-color: #334155 !important;
            color: #f8fafc !important;
        }
        html.app-skin-dark .zoho-header-banner .btn-outline-secondary,
        html.app-skin-dark .btn-outline-secondary {
            background-color: #162038 !important;
            color: #f8fafc !important;
            border-color: #334155 !important;
        }
        html.app-skin-dark .modal-content,
        html.app-skin-dark .offcanvas {
            background-color: #0f172a !important;
            color: #f8fafc !important;
            border-color: #1e293b !important;
        }
        html.app-skin-dark .dropdown-menu {
            background-color: #0f172a !important;
            border-color: #1e293b !important;
        }
        html.app-skin-dark .dropdown-item {
            color: #cbd5e1 !important;
        }
        html.app-skin-dark .dropdown-item:hover {
            background-color: #1e293b !important;
            color: #60a5fa !important;
        }

        /* ==================== ODOO-STYLE CHATTER & ACTIVITY FEED ==================== */
        .odoo-chatter-feed {
            width: 100%;
        }
        .activity-feed-container .erp-horizontal-tabs,
        .activity-feed-container ul.nav.nav-tabs.erp-horizontal-tabs {
            margin-bottom: 0.5rem !important;
            padding-bottom: 2px !important;
            border-bottom: 1px solid #f1f5f9 !important;
        }
        .activity-feed-container .odoo-chatter-feed .activity-section-block:first-child .odoo-feed-divider {
            margin-top: 8px !important;
        }
        .odoo-feed-divider {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 16px 0 10px 0;
            color: #64748b;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            user-select: none;
            transition: opacity 0.15s ease;
        }
        .odoo-feed-divider:hover {
            opacity: 0.85;
        }
        .odoo-feed-divider .toggle-arrow {
            display: inline-block;
            transition: transform 0.2s ease;
            font-size: 11px;
            vertical-align: middle;
        }
        .odoo-feed-divider.collapsed .toggle-arrow,
        .odoo-feed-divider[aria-expanded="false"] .toggle-arrow {
            transform: rotate(-90deg);
        }
        .odoo-feed-divider::before,
        .odoo-feed-divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid #e2e8f0;
        }
        .odoo-feed-divider span {
            padding: 0 16px;
            color: #475569;
            letter-spacing: 0.2px;
        }
        .odoo-feed-divider--planned span {
            color: #16a34a !important;
            font-weight: 700;
        }
        .odoo-feed-divider--overdue span {
            color: #dc2626 !important;
            font-weight: 700;
        }
        .odoo-feed-divider--today-done span {
            color: #d97706 !important;
            font-weight: 700;
        }
        .odoo-feed-divider--history span {
            color: #64748b !important;
            font-weight: 600;
        }

        .odoo-activity-item {
            padding: 12px 6px;
            border-bottom: 1px solid #f1f5f9;
            transition: background-color 0.15s ease;
        }
        .odoo-activity-item:last-child {
            border-bottom: none;
        }
        .odoo-activity-item:hover {
            background-color: rgba(248, 250, 252, 0.8);
            border-radius: 4px;
        }

        .odoo-info-icon {
            color: #94a3b8;
            font-size: 12px;
            transition: color 0.15s ease;
        }
        .odoo-info-icon:hover {
            color: #1e293b;
        }
        .odoo-info-icon:hover {
            color: #1e293b;
        }

        /* Odoo Dark Tooltip / Popover */
        .popover {
            background-color: #0f172a !important;
            border: 1px solid #1e293b !important;
            border-radius: 6px !important;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.4), 0 8px 10px -6px rgba(0, 0, 0, 0.3) !important;
            z-index: 1060;
        }
        .popover .popover-header {
            background-color: #1e293b !important;
            color: #f8fafc !important;
            border-bottom: 1px solid #334155 !important;
            font-size: 12px !important;
            font-weight: 700 !important;
            padding: 8px 12px !important;
        }
        .popover .popover-body {
            color: #f8fafc !important;
            font-size: 11.5px !important;
            padding: 8px 12px !important;
            line-height: 1.6 !important;
        }
        .popover .popover-arrow::before,
        .popover .popover-arrow::after {
            border-top-color: #0f172a !important;
            border-bottom-color: #0f172a !important;
        }

        /* Dark Mode */
        html.app-skin-dark .odoo-feed-divider::before,
        html.app-skin-dark .odoo-feed-divider::after {
            border-bottom-color: #1e293b !important;
        }
        html.app-skin-dark .odoo-feed-divider span {
            color: #94a3b8;
        }
        html.app-skin-dark .odoo-activity-item {
            border-bottom-color: #1e293b !important;
            background: transparent !important;
        }
        html.app-skin-dark .odoo-activity-item:hover {
            background-color: rgba(30, 41, 59, 0.4) !important;
        }
        html.app-skin-dark .odoo-type-badge {
            border-color: #0b1329 !important;
        }
        html.app-skin-dark .call-recording-player-box {
            background: #162038 !important;
            border-color: #1e293b !important;
        }
    </style>

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
                            $statusKey = $lead->status ?: 'New';
                            $presetSoftClasses = [
                                'bg-soft-primary text-primary',
                                'bg-soft-info text-info',
                                'bg-soft-teal text-teal',
                                'bg-soft-success text-success',
                                'bg-soft-warning text-warning',
                                'bg-soft-danger text-danger',
                                'bg-soft-secondary text-secondary',
                            ];
                            $statusClass = match(strtolower($statusKey)) {
                                'new' => 'bg-soft-primary text-primary',
                                'qualified' => 'bg-soft-teal text-teal',
                                'dealing' => 'bg-soft-info text-info',
                                'won' => 'bg-soft-success text-success',
                                'lost' => 'bg-soft-danger text-danger',
                                default => $presetSoftClasses[abs(crc32($statusKey)) % count($presetSoftClasses)],
                            };
                            $statusDisplayName = \Illuminate\Support\Facades\Lang::has('crm.statuses.' . $statusKey) ? __('crm.statuses.' . $statusKey) : $statusKey;
                        @endphp
                        <span class="badge {{ $statusClass }} px-2 py-0.5 fs-10 fw-semibold">{{ $statusDisplayName }}</span>
                        @if($lead->segment && $lead->segment !== 'Select an Option')
                            @php
                                $segmentDisplayName = \Illuminate\Support\Facades\Lang::has('crm.segments.' . $lead->segment) ? __('crm.segments.' . $lead->segment) : $lead->segment;
                            @endphp
                            <span class="badge bg-soft-secondary text-secondary px-2 py-0.5 fs-10 fw-semibold">{{ $segmentDisplayName }}</span>
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
                <!-- Back Button (Arrow only) -->
                <a href="{{ route('crm.leads.index') }}" class="btn btn-xs btn-light text-dark border py-1 px-2 rounded shadow-2xs d-inline-flex align-items-center me-1" title="Back to Leads" style="font-size: 11px;">
                    <i class="feather-arrow-left"></i>
                </a>

                @if($lead->crm_deal_id)
                    <a href="{{ route('crm.deals.show', $lead->crm_deal_id) }}" class="btn btn-xs btn-soft-success fw-bold py-1 px-2 rounded shadow-2xs d-inline-flex align-items-center" style="font-size: 11px;">
                        <i class="feather-git-branch me-1"></i> {{ __('crm.view_deal') }}
                    </a>
                @else
                    <form action="{{ route('crm.leads.qualify', $lead->id) }}" method="POST" class="d-inline m-0 p-0">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="btn btn-xs btn-warning text-dark fw-bold py-1 px-2 rounded shadow-2xs d-inline-flex align-items-center" style="font-size: 11px;">
                            <i class="feather-user-check me-1"></i> {{ __('crm.convert_to_deal') }}
                        </button>
                    </form>
                @endif

                @if($lead->crm_account_id)
                    <a href="{{ route('crm.accounts.show', $lead->crm_account_id) }}" class="btn btn-xs btn-soft-primary fw-bold py-1 px-2 rounded shadow-2xs d-inline-flex align-items-center" style="font-size: 11px;">
                        <i class="feather-briefcase me-1"></i> {{ __('crm.view_account') }}
                    </a>
                @endif


                <button type="button" class="btn btn-xs btn-primary fw-bold py-1 px-2 rounded shadow-2xs d-inline-flex align-items-center text-white btn-open-followup-offcanvas" 
                        data-bs-toggle="offcanvas" 
                        data-bs-target="#leadFollowupOffcanvas" 
                        data-lead-id="{{ $lead->id }}" 
                        data-lead-name="{{ $lead->company_name }}" 
                        data-lead-status="{{ $lead->status }}" 
                        data-lead-priority="{{ $lead->priority }}" 
                        data-next-followup="{{ $lead->next_followup_date ? $lead->next_followup_date->format('Y-m-d\TH:i') : '' }}"
                        style="background-color: var(--bs-primary); border-color: var(--bs-primary); font-size: 11px;">
                    <i class="feather-calendar me-1"></i> + {{ __('crm.followup') }}
                </button>


                <!-- More Actions 3-Dot Dropdown using common component -->
                @if (!in_array(strtolower($lead->status ?? ''), ['dealing', 'won']))
                <x-ui.action-dropdown id="leadProfileActionsDropdown">
                    <li>
                        <a class="dropdown-item py-2" href="{{ route('crm.leads.show', ['lead' => $lead->id, 'edit_lead' => 1]) }}">
                            <i class="feather-edit me-1.5 text-muted"></i> {{ __('crm.edit_lead') }}
                        </a>
                    </li>
                </x-ui.action-dropdown>
                @endif
                
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
                            <a href="#sectionLeadInfo" class="nav-link active py-1.5 px-2 fs-12 rounded text-dark fw-medium">{{ __('crm.lead_information') }}</a>
                        </li>
                        <li class="nav-item">
                            <a href="#sectionLeadProducts" class="nav-link py-1.5 px-2 fs-12 rounded text-dark">{{ __('crm.product_and_quantity') }}</a>
                        </li>
                        <li class="nav-item">
                            <a href="#sectionAddressInfo" class="nav-link py-1.5 px-2 fs-12 rounded text-dark">{{ __('crm.address_details') }}</a>
                        </li>
                        <li class="nav-item">
                            <a href="#sectionRequirements" class="nav-link py-1.5 px-2 fs-12 rounded text-dark">{{ __('crm.requirements') }}</a>
                        </li>
                        <li class="nav-item">
                            <a href="#sectionActivities" class="nav-link py-1.5 px-2 fs-12 rounded text-dark">{{ __('crm.activities') }}</a>
                        </li>
                        <li class="nav-item">
                            <a href="#sectionNotes" class="nav-link py-1.5 px-2 fs-12 rounded text-dark">{{ __('crm.notes') }}</a>
                        </li>
                        <li class="nav-item">
                            <a href="#subtab-history" class="nav-link py-1.5 px-2 fs-12 rounded text-dark">{{ __('crm.history') }}</a>
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
                            <button class="nav-link px-3 py-1 fw-bold fs-12 {{ !request()->has('create_quotation') && !request()->has('edit_quotation') && !request()->has('view_quotation') && request('tab') !== 'interactions' && request('tab') !== 'timeline' ? 'active' : '' }}" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview-pane" type="button" role="tab" aria-controls="overview-pane" aria-selected="{{ request('tab') !== 'interactions' && request('tab') !== 'timeline' ? 'true' : 'false' }}">
                                <i class="feather-grid me-1"></i>{{ __('crm.overview') }}
                            </button>
                        </li>
                        @if ($activeQuotation || request()->has('create_quotation'))
                            <li class="nav-item" role="presentation">
                                <button class="nav-link px-3 py-1 fw-bold fs-12 {{ request()->has('create_quotation') || request()->has('edit_quotation') || request()->has('view_quotation') ? 'active' : '' }}" id="quotation-tab" data-bs-toggle="tab" data-bs-target="#quotation-pane" type="button" role="tab" aria-controls="quotation-pane" aria-selected="false">
                                    <i class="feather-file-text me-1"></i>{{ __('crm.quotation_proposals') }} @if($activeQuotation)({{ $lead->quotations ? $lead->quotations->count() : 1 }})@endif
                                </button>
                            </li>
                        @endif
                        <li class="nav-item" role="presentation">
                            <button class="nav-link px-3 py-1 fw-bold fs-12 {{ request('tab') === 'interactions' || request('tab') === 'timeline' ? 'active' : '' }}" id="timeline-tab" data-bs-toggle="tab" data-bs-target="#timeline-pane" type="button" role="tab" aria-controls="timeline-pane" aria-selected="{{ request('tab') === 'interactions' || request('tab') === 'timeline' ? 'true' : 'false' }}">
                                <i class="feather-clock me-1"></i>{{ __('crm.timeline_audit') }}
                            </button>
                        </li>
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
                    <div class="tab-pane fade {{ !request()->has('create_quotation') && !request()->has('edit_quotation') && !request()->has('view_quotation') && old('form_type') !== 'quotation_create' && old('form_type') !== 'quotation_edit' && request('tab') !== 'interactions' && request('tab') !== 'timeline' ? 'show active' : '' }}" id="overview-pane" role="tabpanel" aria-labelledby="overview-tab">
                        
                        @if ((request()->has('edit_lead') || old('form_type') === 'lead_edit') && !in_array(strtolower($lead->status ?? ''), ['dealing', 'won']))
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
                                                     <label class="fw-bold text-dark mb-2 d-block fs-13"><i class="feather-layers me-1 text-primary"></i> {{ __('crm.customer_type_lead_segment') }}</label>
                                                     <div class="d-flex gap-4">
                                                         <div class="form-check form-check-inline">
                                                             <input class="form-check-input" type="radio" name="lead_type" id="edit_lead_type_b2b" value="b2b" {{ old('lead_type', $lead->lead_type ?: 'b2b') === 'b2b' ? 'checked' : '' }} onchange="toggleLeadType('b2b')">
                                                             <label class="form-check-label fw-bold text-dark cursor-pointer" for="edit_lead_type_b2b">
                                                                 {{ __('crm.b2b_business_client') }}
                                                             </label>
                                                         </div>
                                                         <div class="form-check form-check-inline">
                                                             <input class="form-check-input" type="radio" name="lead_type" id="edit_lead_type_b2c" value="b2c" {{ old('lead_type', $lead->lead_type) === 'b2c' ? 'checked' : '' }} onchange="toggleLeadType('b2c')">
                                                             <label class="form-check-label fw-bold text-dark cursor-pointer" for="edit_lead_type_b2c">
                                                                 {{ __('crm.b2c_individual_customer') }}
                                                             </label>
                                                         </div>
                                                     </div>
                                                 </div>

                                                <h6 class="fw-bold text-primary mb-3">{{ __('crm.company_contact_info') }}</h6>
                                                
                                                <x-ui.odoo-form-ui type="input" :label="__('crm.call_date')" name="call_date" id="lead_call_date_picker" :value="old('call_date', $lead->call_date ? $lead->call_date->format('Y-m-d h:i A') : '')" required="true" :errorText="$errors->first('call_date')" />

                                                <x-ui.odoo-form-ui type="input" :label="__('crm.company_name')" name="company_name" id="edit_company_name_input" :value="old('company_name', $lead->company_name)" :placeholder="__('crm.company_name')" :errorText="$errors->first('company_name')" />

                                                <x-ui.odoo-form-ui type="input" :label="__('crm.gstin_tax_no')" name="gstin" id="edit_gstin_input" :value="old('gstin', $lead->gstin ?? '')" :placeholder="__('crm.gstin_placeholder')" />

                                                <x-ui.odoo-form-ui type="input" :label="__('crm.company_email')" name="company_email" id="edit_company_email_input" inputType="email" :value="old('company_email', $lead->company_email ?? '')" :placeholder="__('crm.company_email_placeholder')" />

                                                <x-ui.odoo-form-ui type="input" :label="__('crm.company_phone')" name="company_phone" id="edit_company_phone_input" :value="old('company_phone', $lead->company_phone ?? '')" :placeholder="__('crm.company_phone_placeholder')" oninput="this.value = this.value.replace(/[^0-9]/g, '')" />

                                                <x-ui.odoo-form-ui type="input" :label="__('crm.contact_person')" name="contact_person" :value="old('contact_person', $lead->contact_person)" :placeholder="__('crm.contact_person')" :errorText="$errors->first('contact_person')" />

                                                <x-ui.odoo-form-ui type="input" :label="__('crm.designation_role')" name="designation" :value="old('designation', $lead->designation)" :placeholder="__('crm.designation_placeholder')" :errorText="$errors->first('designation')" />

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
                                                             <h6 class="fw-bold text-dark mb-0 fs-13">{{ __('crm.additional_contacts') }}</h6>
                                                             <span class="badge bg-soft-primary text-primary rounded-circle px-2 py-0.5 font-monospace fs-11" id="showAddlContactCountBadge">{{ count($savedAddlContacts) }}</span>
                                                         </div>
                                                         <button type="button" class="btn btn-xs btn-primary fw-bold px-2.5 py-1 text-uppercase text-white d-inline-flex align-items-center" id="showCloneContactMainBtn" style="border-radius: 4px; font-size: 11px;">
                                                             <i class="feather-plus me-1 fs-12"></i> {{ __('crm.clone_contact') }}
                                                         </button>
                                                     </div>

                                                     <div id="showAdditionalContactsRepeaterContainer" class="d-flex flex-column gap-2">
                                                         @forelse($savedAddlContacts as $idx => $ac)
                                                             <div class="addl-contact-card p-2 px-3 mb-1 bg-white position-relative shadow-2xs" style="border: 1.5px solid var(--bs-primary) !important; border-radius: 8px !important;">
                                                                 <div class="d-flex align-items-center justify-content-between mb-1 pb-1 border-bottom">
                                                                     <span class="fs-11 fw-bold text-muted text-uppercase letter-spacing-1"><i class="feather-user me-1 text-primary"></i> {{ __('crm.contact_person_num') }}<span class="contact-num">{{ $loop->iteration }}</span></span>
                                                                     <button type="button" class="btn btn-xs btn-soft-danger rounded-circle remove-contact-btn p-0 d-inline-flex align-items-center justify-content-center" title="Delete Contact" style="width: 22px; height: 22px; border-radius: 50%;">
                                                                         <i class="feather-trash-2 text-danger fs-11"></i>
                                                                     </button>
                                                                 </div>
                                                                 <div class="row g-2">
                                                                     <div class="col-md-6">
                                                                         <x-ui.odoo-form-ui type="input" :label="__('crm.name')" name="additional_contacts[{{ $idx }}][name]" :value="$ac['name'] ?? ''" :placeholder="__('crm.contact_name_placeholder')" class="contact-name-input" />
                                                                     </div>
                                                                     <div class="col-md-6">
                                                                         <x-ui.odoo-form-ui type="input" :label="__('crm.phone_no')" name="additional_contacts[{{ $idx }}][phone]" :value="$ac['phone'] ?? ''" :placeholder="__('crm.phone_number_placeholder')" class="contact-phone-input" oninput="this.value = this.value.replace(/[^0-9]/g, '')" />
                                                                     </div>
                                                                     <div class="col-md-12">
                                                                         <x-ui.odoo-form-ui type="input" :label="__('crm.contact_email')" name="additional_contacts[{{ $idx }}][email]" inputType="email" :value="$ac['email'] ?? ''" :placeholder="__('crm.email_placeholder')" class="contact-email-input" />
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
                                                    @foreach (['Direct Inquiry', 'Website Form', 'Web Search', 'Meta Ads', 'IndiaMART', 'TradeIndia', 'Justdial', 'Cold Call', 'Referral', 'Employee Referral', 'Partner', 'Advertisement', 'Trade Show', 'WhatsApp Bot', 'WhatsApp', 'Email', 'Phone Call', 'Walk In', 'LinkedIn', 'Google Ads', 'Other'] as $srcOption)
                                                        <option value="{{ $srcOption }}" @selected(old('source', $lead->source) === $srcOption)>{{ \Illuminate\Support\Facades\Lang::has('crm.sources.' . $srcOption) ? __('crm.sources.' . $srcOption) : $srcOption }}</option>
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
                                                        border-color: #dee2e6;
                                                        padding: 2px 4px !important;
                                                    }
                                                    html.app-skin-dark #editProductItemsTable .qty-row-input {
                                                        background-color: #121a2d !important;
                                                        border-color: #283c50 !important;
                                                        color: #ffffff !important;
                                                    }
                                                </style>

                                                 <div class="mb-3 mt-4" id="editProductItemsContainer">
                                                     <div class="d-flex justify-content-between align-items-center mb-2">
                                                         <label class="form-label fw-bold text-dark fs-12 mb-0">
                                                             <i class="feather-package me-1 text-primary"></i>{{ __('crm.product_and_quantity') }}
                                                         </label>
                                                         <button type="button" class="btn btn-xs btn-outline-primary fw-semibold px-2 py-1 fs-11" id="editAddProductRowBtn" style="border-radius: 6px;">
                                                             <i class="feather-plus me-1"></i>{{ __('crm.add_product_btn') }}
                                                         </button>
                                                     </div>
                                                     
                                                     <div class="border rounded-3 bg-white p-2 shadow-sm" style="max-height: 270px; overflow-x: hidden; overflow-y: auto;">
                                                         <table class="table table-sm table-borderless align-middle mb-0" id="editProductItemsTable">
                                                             <thead>
                                                                 <tr class="border-bottom text-muted fs-11" style="background-color: #f8fafc;">
                                                                     <th style="width: 58%; font-weight: 600;" class="py-1 ps-2">{{ __('crm.product') }}</th>
                                                                     <th style="width: 28%; font-weight: 600;" class="py-1 text-center">{{ __('crm.qty') }}</th>
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
                                                                                 <option value="">{{ __('crm.select_product') }}</option>
                                                                                 <option value="__ADD_NEW__" class="fw-bold text-primary" data-master="product">+ {{ __('crm.add_new_product') }}</option>
                                                                                 
                                                                                 @if($finished->count())
                                                                                     <optgroup label="{{ __('crm.optgroup_finished_goods') }}">
                                                                                         @foreach($finished as $p)
                                                                                             <option value="{{ $p->id }}" @selected(($item['product_id'] ?? '') == $p->id)>
                                                                                                 {{ $p->name }} @if($p->sku) ({{ $p->sku }}) @endif
                                                                                             </option>
                                                                                         @endforeach
                                                                                     </optgroup>
                                                                                 @endif

                                                                                 @if($semiFinished->count())
                                                                                     <optgroup label="{{ __('crm.optgroup_semi_finished') }}">
                                                                                         @foreach($semiFinished as $p)
                                                                                             <option value="{{ $p->id }}" @selected(($item['product_id'] ?? '') == $p->id)>
                                                                                                 {{ $p->name }} @if($p->sku) ({{ $p->sku }}) @endif
                                                                                             </option>
                                                                                         @endforeach
                                                                                     </optgroup>
                                                                                 @endif

                                                                                 @if($services->count())
                                                                                     <optgroup label="{{ __('crm.optgroup_services') }}">
                                                                                         @foreach($services as $p)
                                                                                             <option value="{{ $p->id }}" @selected(($item['product_id'] ?? '') == $p->id)>
                                                                                                 {{ $p->name }} @if($p->sku) ({{ $p->sku }}) @endif
                                                                                             </option>
                                                                                         @endforeach
                                                                                     </optgroup>
                                                                                 @endif

                                                                                 @if($others->count())
                                                                                     <optgroup label="{{ __('crm.optgroup_raw_materials') }}">
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
                                                     <div class="zoho-field-label">{{ __('crm.gstin_tax_no') }}</div>
                                                     <div class="zoho-field-value text-dark fw-semibold">{{ $lead->gstin ?: '—' }}</div>
                                                 </div>

                                                 <div class="zoho-field-row">
                                                     <div class="zoho-field-label">{{ __('crm.company_email') }}</div>
                                                     <div class="zoho-field-value">
                                                         @if($lead->company_email)
                                                             <a href="mailto:{{ $lead->company_email }}" class="text-primary hover-underline">{{ $lead->company_email }}</a>
                                                         @else
                                                             —
                                                         @endif
                                                     </div>
                                                 </div>

                                                 <div class="zoho-field-row">
                                                     <div class="zoho-field-label">{{ __('crm.company_phone') }}</div>
                                                     <div class="zoho-field-value text-dark">{{ $lead->company_phone ?: '—' }}</div>
                                                 </div>

                                                 <div class="zoho-field-row">
                                                     <div class="zoho-field-label">{{ __('crm.contact_person') }}</div>
                                                     <div class="zoho-field-value text-dark fw-semibold">{{ $lead->contact_person ?: '—' }}</div>
                                                 </div>

                                                 <div class="zoho-field-row">
                                                      <div class="zoho-field-label">{{ __('crm.designation_role') }}</div>
                                                      <div class="zoho-field-value text-dark">{{ $lead->designation ?: '—' }}</div>
                                                  </div>

                                                 <div class="zoho-field-row">
                                                     <div class="zoho-field-label">{{ __('crm.contact_email') }}</div>
                                                     <div class="zoho-field-value">
                                                         @if($lead->email)
                                                             <a href="mailto:{{ $lead->email }}" class="text-primary hover-underline">{{ $lead->email }}</a>
                                                         @else
                                                             —
                                                         @endif
                                                     </div>
                                                 </div>

                                                 <div class="zoho-field-row">
                                                     <div class="zoho-field-label">{{ __('crm.contact_phone') }}</div>
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
                                                                                 @if(!empty($ac['designation']))
                                                                                     <span class="text-muted fw-normal ms-1 fs-11">({{ $ac['designation'] }})</span>
                                                                                 @endif
                                                                             </span>
                                                                             <span class="badge bg-soft-primary text-primary fs-10 fw-semibold">Contact #{{ $loop->iteration }}</span>
                                                                         </div>
                                                                         <div class="d-flex flex-wrap gap-3 fs-11 text-muted">
                                                                             @if(!empty($ac['designation']))
                                                                                 <span><i class="feather-briefcase me-1 text-primary fs-10"></i><strong class="text-dark">{{ $ac['designation'] }}</strong></span>
                                                                             @endif
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
                                                                 <select name="status" class="form-control status-select" data-select2-selector="status" onchange="this.form.submit()" style="width: 100%;">
                                                                     @php
                                                                         $statusesList = $leadStatuses ?? \App\Domains\CRM\Models\LeadStatus::getOrderedStatuses();
                                                                     @endphp
                                                                     @foreach($statusesList as $ls)
                                                                         @php
                                                                             $statusOption = $ls->name;
                                                                             $bgClass = match(strtolower($statusOption)) {
                                                                                 'new' => 'bg-primary',
                                                                                 'qualified' => 'bg-teal',
                                                                                 'won' => 'bg-success',
                                                                                 'lost' => 'bg-danger',
                                                                                 default => ($ls->color ?: 'bg-primary'),
                                                                             };
                                                                         @endphp
                                                                         <option value="{{ $statusOption }}" data-bg="{{ $bgClass }}" {{ ($lead->status ?: 'New') === $statusOption ? 'selected' : '' }}>
                                                                             {{ $statusOption }}
                                                                         </option>
                                                                     @endforeach
                                                                 </select>
                                                             </form>
                                                         @endif
                                                     </div>
                                                 </div>
                                                 <div class="zoho-field-row">
                                                     <div class="zoho-field-label">{{ __('crm.expected_revenue_label') }}</div>
                                                     <div class="zoho-field-value text-dark fw-bold">{{ format_currency($lead->expected_amount ?? 0) }}</div>
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
                                                         <span class="badge bg-light text-dark border px-2 py-0.5" style="font-size: 11px;">{{ ($lead->source && !in_array($lead->source, ['Select an Option', 'Select an option', 'Select Option'], true)) ? (\Illuminate\Support\Facades\Lang::has('crm.sources.' . $lead->source) ? __('crm.sources.' . $lead->source) : $lead->source) : '—' }}</span>
                                                     </div>
                                                 </div>
                                             </div>
                                        </div>
                                    </div>
                                </div>

                                @if(!empty($lead->utm_source) || !empty($lead->utm_medium) || !empty($lead->utm_campaign) || !empty($lead->utm_term) || !empty($lead->utm_content))
                                <!-- UTM Parameters (Marketing Attribution) Card -->
                                <div class="card border shadow-sm mb-3" style="border-radius: 4px; border-color: #e2e8f0 !important; background-color: #ffffff;" id="sectionUtmParams">
                                    <div class="card-body p-3">
                                        <div class="d-flex justify-content-between align-items-center pb-2 border-bottom mb-3">
                                            <h5 class="zoho-section-title fs-13 text-dark fw-bold mb-0" style="border-bottom: none;">
                                                <i class="feather-compass text-primary me-1.5"></i>UTM Parameters (Marketing Attribution)
                                            </h5>
                                            @if($lead->utm_source || $lead->utm_medium || $lead->utm_campaign || $lead->utm_term || $lead->utm_content)
                                                <span class="badge bg-soft-success text-success fs-10 fw-semibold px-2 py-0.5"><i class="feather-check-circle me-1"></i>Tracked</span>
                                            @else
                                                <span class="badge bg-light text-muted border fs-10 fw-normal px-2 py-0.5">No UTM Data</span>
                                            @endif
                                        </div>
                                        <div class="row g-2">
                                            <div class="col-md-4">
                                                <div class="zoho-field-row">
                                                    <div class="zoho-field-label">UTM Source</div>
                                                    <div class="zoho-field-value">
                                                        @if($lead->utm_source)
                                                            <span class="badge bg-soft-primary text-primary font-monospace px-2 py-1 fs-11 border border-primary-subtle">{{ $lead->utm_source }}</span>
                                                        @else
                                                            <span class="text-muted">—</span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="zoho-field-row">
                                                    <div class="zoho-field-label">UTM Medium</div>
                                                    <div class="zoho-field-value">
                                                        @if($lead->utm_medium)
                                                            <span class="badge bg-soft-info text-info font-monospace px-2 py-1 fs-11 border border-info-subtle">{{ $lead->utm_medium }}</span>
                                                        @else
                                                            <span class="text-muted">—</span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="zoho-field-row">
                                                    <div class="zoho-field-label">UTM Campaign</div>
                                                    <div class="zoho-field-value">
                                                        @if($lead->utm_campaign)
                                                            <span class="badge bg-soft-teal text-teal font-monospace px-2 py-1 fs-11 border border-teal-subtle">{{ $lead->utm_campaign }}</span>
                                                        @else
                                                            <span class="text-muted">—</span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="zoho-field-row">
                                                    <div class="zoho-field-label">UTM Term</div>
                                                    <div class="zoho-field-value text-dark font-monospace fs-12">
                                                        {{ $lead->utm_term ?: '—' }}
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="zoho-field-row">
                                                    <div class="zoho-field-label">UTM Content</div>
                                                    <div class="zoho-field-value text-dark font-monospace fs-12">
                                                        {{ $lead->utm_content ?: '—' }}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                @endif

                                <!-- Lead Interested Products & Quantities Section -->
                                @php
                                    $leadItems = $lead->product_items ?: [];
                                    if (empty($leadItems) && !empty($lead->product_ids)) {
                                        foreach ($lead->product_ids as $pid) {
                                            $leadItems[] = ['product_id' => (int)$pid, 'quantity' => 1.0];
                                        }
                                    }
                                    $leadPIds = !empty($leadItems) ? array_column($leadItems, 'product_id') : [];
                                    $leadProductsMap = !empty($leadPIds) ? \App\Domains\Inventory\Models\Product::whereIn('id', $leadPIds)->get()->keyBy('id') : collect();
                                @endphp

                                <div class="card border shadow-sm mb-3" style="border-radius: 4px; border-color: #e2e8f0 !important; background-color: #ffffff;" id="sectionLeadProducts">
                                    <div class="card-body p-3">
                                        <div class="d-flex justify-content-between align-items-center pb-2 border-bottom mb-3">
                                            <h5 class="zoho-section-title fs-13 text-dark fw-bold mb-0" style="border-bottom: none;">
                                                <i class="feather-box text-primary me-1.5"></i>{{ __('crm.product_and_quantity') }}
                                            </h5>
                                            @if($leadProductsMap->isNotEmpty())
                                                <span class="badge bg-soft-primary text-primary fs-11 fw-semibold">{{ count($leadItems) }} {{ __('crm.products_selected') }}</span>
                                            @endif
                                        </div>
                                        @if($leadProductsMap->isNotEmpty())
                                            <div class="table-responsive">
                                                <table class="table table-sm table-bordered align-middle mb-0 fs-13">
                                                    <thead class="table-light text-muted">
                                                        <tr>
                                                            <th>{{ __('crm.product_description') }}</th>
                                                            <th>SKU</th>
                                                            <th class="text-center">{{ __('crm.quantity') }}</th>
                                                            <th class="text-end">{{ __('crm.unit_price') }} ({{ active_currency_symbol() }})</th>
                                                            <th class="text-end">{{ __('crm.total_estimated_value') }} ({{ active_currency_symbol() }})</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @php $grandLeadProductTotal = 0; @endphp
                                                        @foreach($leadItems as $item)
                                                            @php
                                                                $pObj = $leadProductsMap->get($item['product_id']);
                                                                if (!$pObj) continue;
                                                                $pQty = floatval($item['quantity'] ?? 1);
                                                                $pPrice = floatval($pObj->selling_price ?: $pObj->unit_cost ?: 0);
                                                                $lineVal = $pQty * $pPrice;
                                                                $grandLeadProductTotal += $lineVal;
                                                            @endphp
                                                            <tr>
                                                                <td class="fw-bold text-dark">
                                                                    <a href="{{ route('inventory.products.show', $pObj) }}" class="text-dark hover-underline" target="_blank">{{ $pObj->name }}</a>
                                                                </td>
                                                                <td class="font-monospace text-muted">{{ $pObj->sku ?: '—' }}</td>
                                                                <td class="text-center fw-bold text-primary">{{ number_format($pQty, 0) }} {{ $pObj->uom?->code ?? 'Pcs' }}</td>
                                                                <td class="text-end">{{ format_currency($pPrice) }}</td>
                                                                <td class="text-end fw-bold text-success">{{ format_currency($lineVal) }}</td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                    @if($grandLeadProductTotal > 0)
                                                        <tfoot class="table-light fw-bold">
                                                            <tr>
                                                                <td colspan="4" class="text-end text-uppercase fs-12">{{ __('crm.total_estimated_product_value') }}:</td>
                                                                <td class="text-end text-success fs-14">{{ format_currency($grandLeadProductTotal) }}</td>
                                                            </tr>
                                                        </tfoot>
                                                    @endif
                                                </table>
                                            </div>
                                        @else
                                            <p class="text-muted fs-12 mb-0 py-2">{{ __('crm.no_specific_products') }}</p>
                                        @endif
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
                                        <div class="d-flex align-items-center justify-content-between pb-2 border-bottom mb-3">
                                            <h5 class="zoho-section-title fs-13 text-dark fw-bold mb-0" style="font-family: 'Inter', sans-serif; border-bottom: none;">
                                                <i class="feather-file-text text-primary me-1.5"></i>{{ __('crm.requirements_details') }}
                                            </h5>
                                            <span class="text-muted fs-11 d-none d-sm-inline-block"><i class="feather-info me-1 text-primary"></i>{{ __('crm.click_box_to_edit') }}</span>
                                        </div>

                                        <!-- View Mode (Clickable to Edit) -->
                                        <div id="viewRequirementBlock">
                                            @if ($lead->requirement)
                                                <div class="position-relative requirement-clickable-box p-3 rounded shadow-2xs" onclick="enableRequirementEdit()" title="Click anywhere to edit requirement">
                                                    <div class="d-flex align-items-start justify-content-between gap-3">
                                                        <div class="text-dark fs-13 flex-grow-1" style="white-space: pre-wrap; line-height: 1.6; font-family: 'Inter', sans-serif;" id="viewRequirementText">{{ $lead->requirement }}</div>
                                                        <span class="badge bg-white text-primary border shadow-2xs px-2.5 py-1.5 fs-11 flex-shrink-0 edit-hint-badge" style="border-color: #cbd5e1 !important; transition: all 0.2s ease;">
                                                            <i class="feather-edit-2 me-1"></i>{{ __('crm.click_to_edit') }}
                                                        </span>
                                                    </div>
                                                </div>
                                            @else
                                                <div class="position-relative requirement-empty-box p-4 rounded text-center cursor-pointer" onclick="enableRequirementEdit()" title="Click to add requirement">
                                                    <div class="avatar-text avatar-md bg-soft-primary text-primary rounded-circle mx-auto mb-2">
                                                        <i class="feather-edit-3 fs-5"></i>
                                                    </div>
                                                    <h6 class="fw-bold text-dark fs-13 mb-1">{{ __('crm.no_requirements_details_specified') }}</h6>
                                                    <p class="text-muted fs-12 mb-0">{{ __('crm.click_here_to_add_requirements') }}</p>
                                                </div>
                                            @endif
                                        </div>

                                        <!-- Edit Mode -->
                                        <div id="editRequirementBlock" style="display: none;">
                                            <form id="ajaxRequirementForm" action="{{ route('crm.leads.updateRequirement', $lead->id) }}" method="POST">
                                                @csrf
                                                @method('PATCH')
                                                <div class="mb-2">
                                                    <textarea name="requirement" id="requirementInput" rows="4" class="form-control form-control-sm shadow-2xs fs-13" placeholder="Enter detailed requirements or specifications for this lead..." style="border-color: var(--bs-primary); border-radius: 6px; font-family: 'Inter', sans-serif;" oninput="updateReqCharCount(this)">{{ old('requirement', $lead->requirement) }}</textarea>
                                                </div>
                                                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                                    <span class="text-muted fs-11">
                                                        <i class="feather-corner-down-left me-1"></i>{{ __('crm.press_ctrl_enter_or_save') }}
                                                    </span>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <span class="text-muted fs-11 me-2" id="reqCharCounter">0 chars</span>
                                                        <button type="button" class="btn btn-xs btn-light border px-3 py-1.5 fw-bold rounded" onclick="cancelRequirementEdit()">{{ strtoupper(__('crm.cancel')) }}</button>
                                                        <button type="submit" id="btnSaveRequirement" class="btn btn-xs btn-primary px-3 py-1.5 fw-bold shadow-2xs text-white rounded d-inline-flex align-items-center" style="background-color: var(--bs-primary); border-color: var(--bs-primary);">
                                                            <i class="feather-check me-1"></i> {{ strtoupper(__('crm.save_requirement')) }}
                                                        </button>
                                                    </div>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                               
                            </div>
                            
                            <!-- Scheduled Activities & Chatter Feed Card (#sectionActivities) -->
                            <div class="card border shadow-sm mb-3" style="border-radius: 4px; border-color: #e2e8f0 !important; background-color: #ffffff;" id="sectionActivities">
                                <div class="card-body p-3">
                                    @php
                                        $allLeadFollowups = $lead->followups->reject(function($item) {
                                            return $item->status === 'Rescheduled' && $item->rescheduledTo->isNotEmpty();
                                        });

                                        $now = now();
                                        $todayStart = $now->copy()->startOfDay();
                                        $todayEnd = $now->copy()->endOfDay();

                                        // 1. Today's Executed Activities (Done / Logged Today)
                                        $todayDoneActivities = $allLeadFollowups->filter(function($i) use ($todayStart, $todayEnd) {
                                            return $i->status !== 'Pending' && $i->updated_at >= $todayStart && $i->updated_at <= $todayEnd;
                                        })->sortByDesc('updated_at');

                                        // 2. Planned Activities (All pending scheduled for today or future)
                                        $plannedActivities = $allLeadFollowups->filter(function($i) use ($todayStart) {
                                            return $i->status === 'Pending' && $i->followup_date >= $todayStart;
                                        })->sortBy('followup_date');

                                        // 3. Overdue Activities (Pending before today)
                                        $overdueActivities = $allLeadFollowups->filter(function($i) use ($todayStart) {
                                            return $i->status === 'Pending' && $i->followup_date < $todayStart;
                                        })->sortBy('followup_date');

                                        // 4. Past Activities & History (Done / Logged before today)
                                        $pastDoneActivities = $allLeadFollowups->filter(function($i) use ($todayStart) {
                                            return $i->status !== 'Pending' && $i->updated_at < $todayStart;
                                        })->sortByDesc('updated_at');

                                        $hasAnyPending = $plannedActivities->isNotEmpty() || $overdueActivities->isNotEmpty();
                                    @endphp

                                    <div class="d-flex align-items-center justify-content-between pb-2 border-bottom mb-2 flex-wrap gap-2">
                                        <h5 class="zoho-section-title fs-13 text-dark fw-bold mb-0" style="border-bottom: none;">
                                            <i class="feather-calendar text-primary me-1.5"></i>{{ __('crm.interactions_scheduled_activities') }}
                                        </h5>
                                    </div>

                                    @php
                                        $activeActionableCount = $plannedActivities->count() + $overdueActivities->count() + $todayDoneActivities->count();
                                        $activityTabsOverview = [
                                            [
                                                'id' => 'tab-act-all-ov',
                                                'label' => __('crm.active_tasks'),
                                                'icon' => 'feather-zap',
                                                'active' => true,
                                                'badge' => $activeActionableCount,
                                                'badgeClass' => 'bg-dark text-white'
                                            ],
                                            [
                                                'id' => 'tab-act-planned-ov',
                                                'label' => __('crm.planned_activities'),
                                                'icon' => 'feather-calendar',
                                                'badge' => $plannedActivities->count(),
                                                'badgeClass' => 'bg-success text-white'
                                            ],
                                            [
                                                'id' => 'tab-act-overdue-ov',
                                                'label' => __('crm.overdue_activities'),
                                                'icon' => 'feather-alert-circle',
                                                'badge' => $overdueActivities->count(),
                                                'badgeClass' => 'bg-danger text-white'
                                            ],
                                            [
                                                'id' => 'tab-act-today-ov',
                                                'label' => __('crm.today_activity'),
                                                'icon' => 'feather-check-circle',
                                                'badge' => $todayDoneActivities->count(),
                                                'badgeClass' => 'bg-warning text-dark'
                                            ],
                                        ];
                                    @endphp

                                    <div class="activity-feed-container">
                                        <!-- Quick Filter Horizontal Tabs (Common Component) -->
                                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-1">
                                            <div class="flex-grow-1 overflow-auto">
                                                <x-ui.horizontal-tabs id="activityFilterTabsOverview" :tabs="$activityTabsOverview" class="mb-0 border-0 pb-0" />
                                            </div>
                                            <div class="d-flex align-items-center gap-2 flex-shrink-0 mb-1">
                                                <a href="javascript:void(0)" onclick="$('#timeline-tab').tab('show'); $('#subtab-interactions-tab').tab('show');" class="btn btn-link text-primary fs-11 p-0 text-decoration-none fw-semibold">
                                                    <i class="feather-clock me-0.5"></i> {{ __('crm.full_history') }} ({{ $pastDoneActivities->count() }}) <i class="feather-arrow-right ms-0.5"></i>
                                                </a>
                                            </div>
                                        </div>

                                        <!-- ODOO ACTIVITY FEED -->
                                        <div class="odoo-chatter-feed">
                                            <!-- 1. PLANNED ACTIVITIES (Upcoming from Today Onwards) -->
                                            @if($plannedActivities->isNotEmpty())
                                                <div class="activity-section-block" data-activity-section="planned">
                                                    <div class="odoo-feed-divider odoo-feed-divider--planned" data-bs-toggle="collapse" data-bs-target="#collapsePlanned_overview" aria-expanded="true" aria-controls="collapsePlanned_overview">
                                                        <span><i class="feather-chevron-down toggle-arrow me-1"></i> {{ __('crm.planned_activities') }} ({{ $plannedActivities->count() }})</span>
                                                    </div>
                                                    <div class="collapse show odoo-activity-list mb-3" id="collapsePlanned_overview">
                                                        @foreach($plannedActivities->take(4) as $item)
                                                            @include('modules.crm.leads.partials.activity-card-item', ['item' => $item, 'category' => 'planned', 'lead' => $lead, 'users' => $users])
                                                        @endforeach
                                                        @if($plannedActivities->count() > 4)
                                                            <div class="collapse" id="collapseMorePlannedOverview">
                                                                @foreach($plannedActivities->slice(4) as $item)
                                                                    @include('modules.crm.leads.partials.activity-card-item', ['item' => $item, 'category' => 'planned', 'lead' => $lead, 'users' => $users])
                                                                @endforeach
                                                            </div>
                                                            <div class="text-center py-1">
                                                                <button class="btn btn-xs btn-light border text-primary rounded-pill px-3 fs-11 fw-medium" type="button" data-bs-toggle="collapse" data-bs-target="#collapseMorePlannedOverview" aria-expanded="false" aria-controls="collapseMorePlannedOverview" onclick="this.style.display='none'">
                                                                    <i class="feather-chevron-down me-1"></i> {{ __('crm.view_all_planned_activities', ['count' => $plannedActivities->count()]) }}
                                                                </button>
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>
                                            @endif

                                            <!-- 2. OVERDUE ACTIVITIES (Pending past today) -->
                                            @if($overdueActivities->isNotEmpty())
                                                <div class="activity-section-block" data-activity-section="overdue">
                                                    <div class="odoo-feed-divider odoo-feed-divider--overdue" data-bs-toggle="collapse" data-bs-target="#collapseOverdue_overview" aria-expanded="true" aria-controls="collapseOverdue_overview">
                                                        <span><i class="feather-chevron-down toggle-arrow me-1"></i> {{ __('crm.overdue_activities') }} ({{ $overdueActivities->count() }})</span>
                                                    </div>
                                                    <div class="collapse show odoo-activity-list mb-3" id="collapseOverdue_overview">
                                                        @foreach($overdueActivities as $item)
                                                            @include('modules.crm.leads.partials.activity-card-item', ['item' => $item, 'category' => 'overdue', 'lead' => $lead, 'users' => $users])
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endif

                                            <!-- 3. TODAY'S ACTIVITIES (Done/Logged Today) -->
                                            @if($todayDoneActivities->isNotEmpty())
                                                <div class="activity-section-block" data-activity-section="today">
                                                    <div class="odoo-feed-divider odoo-feed-divider--today-done" data-bs-toggle="collapse" data-bs-target="#collapseToday_overview" aria-expanded="true" aria-controls="collapseToday_overview">
                                                        <span><i class="feather-chevron-down toggle-arrow me-1"></i> {{ __('crm.today_activity') }} ({{ $todayDoneActivities->count() }})</span>
                                                    </div>
                                                    <div class="collapse show odoo-activity-list mb-3" id="collapseToday_overview">
                                                        @foreach($todayDoneActivities as $item)
                                                            @include('modules.crm.leads.partials.activity-card-item', ['item' => $item, 'category' => 'today_done', 'lead' => $lead, 'users' => $users])
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endif

                                            <!-- Fallback if no pending activities and no today actions -->
                                            @if(!$hasAnyPending && $todayDoneActivities->isEmpty())
                                                <div class="activity-section-block" data-activity-section="planned">
                                                    <div class="odoo-feed-divider odoo-feed-divider--planned">
                                                        <span><i class="feather-chevron-down toggle-arrow me-1"></i> {{ __('crm.planned_activities') }}</span>
                                                    </div>
                                                    <div class="text-center py-4 text-muted fs-12">
                                                        <i class="feather-calendar fs-18 me-1 opacity-60"></i> {{ __('crm.no_pending_activities') }}
                                                    </div>
                                                </div>
                                            @endif

                                            <!-- 4. PAST ACTIVITIES COMPACT SUMMARY ROW -->
                                            @if($pastDoneActivities->isNotEmpty())
                                                <div class="d-flex align-items-center justify-content-between p-2.5 bg-light rounded border mt-2">
                                                    <div class="d-flex align-items-center gap-2">
                                                        <div class="rounded bg-white border p-1 d-flex align-items-center justify-content-center text-muted" style="width: 28px; height: 28px;">
                                                            <i class="feather-clock fs-13 text-secondary"></i>
                                                        </div>
                                                        <div>
                                                            <span class="fs-12 fw-semibold text-dark d-block mb-0">{{ $pastDoneActivities->count() }} {{ __('crm.past_activity_logs') }}</span>
                                                            <span class="text-muted fs-11">{{ __('crm.previous_calls_notes_logged') }}</span>
                                                        </div>
                                                    </div>
                                                    <a href="javascript:void(0)" onclick="$('#timeline-tab').tab('show'); $('#subtab-interactions-tab').tab('show');" class="btn btn-xs btn-outline-primary fw-medium px-2.5 py-1 rounded">
                                                        {{ __('crm.view_in_timeline') }} <i class="feather-arrow-right ms-1"></i>
                                                    </a>
                                                </div>
                                            @endif
                                        </div>
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
                                                    @if(!empty($followup->recording_url))
                                                        <div class="mt-2 pt-1.5 border-top">
                                                            <div class="d-flex align-items-center justify-content-between mb-1">
                                                                <span class="fs-10 fw-bold text-primary"><i class="feather-phone-call me-1"></i>Recording Audio</span>
                                                                <a href="{{ asset($followup->recording_url) }}" download class="fs-10 text-primary hover-underline"><i class="feather-download"></i></a>
                                                            </div>
                                                            <audio controls preload="none" class="w-100" style="height: 28px;">
                                                                <source src="{{ asset($followup->recording_url) }}">
                                                            </audio>
                                                        </div>
                                                    @endif
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
                    <div class="tab-pane fade {{ request('tab') === 'interactions' || request('tab') === 'timeline' ? 'show active' : '' }}" id="timeline-pane" role="tabpanel" aria-labelledby="timeline-tab">
                        <div class="card border shadow-sm" style="border-radius: 4px; border-color: #e2e8f0 !important; background-color: #ffffff;">
                            <div class="card-body p-3">
                                
                                <!-- Subtabs selector row -->
                                <div class="border-bottom pb-1 mb-3">
                                    <ul class="nav nav-tabs border-bottom-0 zoho-timeline-subtabs" id="zohoTimelineSubTabs" role="tablist">
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link {{ request('tab') !== 'interactions' ? 'active' : '' }} py-2 px-3 border-0 bg-transparent" id="subtab-history-tab" data-bs-toggle="tab" data-bs-target="#subtab-history" type="button" role="tab" aria-controls="subtab-history" aria-selected="{{ request('tab') !== 'interactions' ? 'true' : 'false' }}">
                                                {{ __('crm.history') }}
                                            </button>
                                        </li>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link {{ request('tab') === 'interactions' ? 'active' : '' }} py-2 px-3 border-0 bg-transparent" id="subtab-interactions-tab" data-bs-toggle="tab" data-bs-target="#subtab-interactions" type="button" role="tab" aria-controls="subtab-interactions" aria-selected="{{ request('tab') === 'interactions' ? 'true' : 'false' }}">
                                                {{ __('crm.interactions') }}
                                            </button>
                                        </li>
                                    </ul>
                                </div>
                                
                                <!-- Subtabs Content -->
                                <div class="tab-content" id="zohoTimelineSubTabsContent">
                                    
                                    <!-- SUBTAB 1: HISTORY TIMELINE -->
                                    <div class="tab-pane fade {{ request('tab') !== 'interactions' ? 'show active' : '' }}" id="subtab-history" role="tabpanel" aria-labelledby="subtab-history-tab">
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
                                                @foreach($groupedHistory->take(3) as $date => $items)
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

                                                @if($groupedHistory->count() > 3)
                                                    <div class="collapse" id="collapseMoreAuditHistory">
                                                        @foreach($groupedHistory->slice(3) as $date => $items)
                                                            <div class="zoho-timeline-date-group">
                                                                <div class="zoho-timeline-date-header">{{ $date }}</div>
                                                                @foreach($items as $item)
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
                                                    </div>
                                                    <div class="text-center py-2">
                                                        <button class="btn btn-sm btn-outline-secondary rounded-pill px-3 fs-11 fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#collapseMoreAuditHistory" aria-expanded="false" aria-controls="collapseMoreAuditHistory" onclick="this.style.display='none'">
                                                            <i class="feather-chevron-down me-1"></i> Load Older History ({{ $groupedHistory->count() - 3 }} more dates)
                                                        </button>
                                                    </div>
                                                @endif
                                            @endif
                                        </div>
                                    </div>

                                    <!-- SUBTAB 2: INTERACTIONS (ACTIVITIES) TIMELINE -->
                                    <div class="tab-pane fade {{ request('tab') === 'interactions' ? 'show active' : '' }}" id="subtab-interactions" role="tabpanel" aria-labelledby="subtab-interactions-tab">
                                        
                                        @php
                                            $allLeadFollowups = $lead->followups->reject(function($item) {
                                                return $item->status === 'Rescheduled' && $item->rescheduledTo->isNotEmpty();
                                            });

                                            $now = now();
                                            $todayStart = $now->copy()->startOfDay();
                                            $todayEnd = $now->copy()->endOfDay();

                                            // 1. Today's Executed Activities (Done / Logged Today)
                                            $todayDoneActivities = $allLeadFollowups->filter(function($i) use ($todayStart, $todayEnd) {
                                                return $i->status !== 'Pending' && $i->updated_at >= $todayStart && $i->updated_at <= $todayEnd;
                                            })->sortByDesc('updated_at');

                                            // 2. Planned Activities (All pending scheduled for today or future)
                                            $plannedActivities = $allLeadFollowups->filter(function($i) use ($todayStart) {
                                                return $i->status === 'Pending' && $i->followup_date >= $todayStart;
                                            })->sortBy('followup_date');

                                            // 3. Overdue Activities (Pending before today)
                                            $overdueActivities = $allLeadFollowups->filter(function($i) use ($todayStart) {
                                                return $i->status === 'Pending' && $i->followup_date < $todayStart;
                                            })->sortBy('followup_date');

                                            // 4. Past Activities & History (Done / Logged before today)
                                            $pastDoneActivities = $allLeadFollowups->filter(function($i) use ($todayStart) {
                                                return $i->status !== 'Pending' && $i->updated_at < $todayStart;
                                            })->sortByDesc('updated_at');

                                            $hasAnyPending = $plannedActivities->isNotEmpty() || $overdueActivities->isNotEmpty();
                                        @endphp

                                        <!-- Top Action Section Title -->
                                        <div class="d-flex align-items-center justify-content-between mb-3 mt-1 flex-wrap gap-2">
                                            <div class="d-flex align-items-center gap-2">
                                                <h5 class="fw-bold text-dark fs-14 mb-0">{{ __('crm.interactions_scheduled_activities') }}</h5>
                                            </div>
                                        </div>

                                        @php
                                            $activityTabsTimeline = [
                                                [
                                                    'id' => 'tab-act-all-tl',
                                                    'label' => 'All',
                                                    'icon' => 'feather-list',
                                                    'active' => true,
                                                    'badge' => $allLeadFollowups->count(),
                                                    'badgeClass' => 'bg-dark text-white'
                                                ],
                                                [
                                                    'id' => 'tab-act-planned-tl',
                                                    'label' => __('crm.planned_activities'),
                                                    'icon' => 'feather-calendar',
                                                    'badge' => $plannedActivities->count(),
                                                    'badgeClass' => 'bg-success text-white'
                                                ],
                                                [
                                                    'id' => 'tab-act-overdue-tl',
                                                    'label' => __('crm.overdue_activities'),
                                                    'icon' => 'feather-alert-circle',
                                                    'badge' => $overdueActivities->count(),
                                                    'badgeClass' => 'bg-danger text-white'
                                                ],
                                                [
                                                    'id' => 'tab-act-today-tl',
                                                    'label' => __('crm.today_activity'),
                                                    'icon' => 'feather-check-circle',
                                                    'badge' => $todayDoneActivities->count(),
                                                    'badgeClass' => 'bg-warning text-dark'
                                                ],
                                                [
                                                    'id' => 'tab-act-history-tl',
                                                    'label' => __('crm.past_activities_history'),
                                                    'icon' => 'feather-clock',
                                                    'badge' => $pastDoneActivities->count(),
                                                    'badgeClass' => 'bg-secondary text-white'
                                                ],
                                            ];
                                        @endphp

                                        <div class="activity-feed-container">
                                            <!-- Quick Filter Horizontal Tabs (Common Component) -->
                                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-1">
                                                <div class="flex-grow-1 overflow-auto">
                                                    <x-ui.horizontal-tabs id="activityFilterTabsTimeline" :tabs="$activityTabsTimeline" class="mb-0 border-0 pb-0" />
                                                </div>
                                                <div class="d-flex align-items-center gap-2 flex-shrink-0 mb-1">
                                                    <a href="javascript:void(0)" class="btn btn-link text-muted fs-11 p-0 text-decoration-none hover-primary btn-expand-all-activities">
                                                        <i class="feather-maximize-2 me-0.5"></i> {{ __('crm.expand_all') }}
                                                    </a>
                                                    <span class="text-muted opacity-50">&bull;</span>
                                                    <a href="javascript:void(0)" class="btn btn-link text-muted fs-11 p-0 text-decoration-none hover-primary btn-collapse-all-activities">
                                                        <i class="feather-minimize-2 me-0.5"></i> {{ __('crm.collapse_all') }}
                                                    </a>
                                                </div>
                                            </div>

                                            <!-- ODOO ACTIVITY FEED -->
                                            <div class="odoo-chatter-feed px-1">
                                                
                                                <!-- 1. PLANNED ACTIVITIES (Upcoming from Today Onwards) -->
                                                @if($plannedActivities->isNotEmpty())
                                                    <div class="activity-section-block" data-activity-section="planned">
                                                        <div class="odoo-feed-divider odoo-feed-divider--planned" data-bs-toggle="collapse" data-bs-target="#collapsePlanned_timeline" aria-expanded="true" aria-controls="collapsePlanned_timeline">
                                                            <span><i class="feather-chevron-down toggle-arrow me-1"></i> {{ __('crm.planned_activities') }} ({{ $plannedActivities->count() }})</span>
                                                        </div>
                                                        <div class="collapse show odoo-activity-list mb-3" id="collapsePlanned_timeline">
                                                            @foreach($plannedActivities as $item)
                                                                @include('modules.crm.leads.partials.activity-card-item', ['item' => $item, 'category' => 'planned', 'lead' => $lead, 'users' => $users])
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                @endif

                                                <!-- 2. OVERDUE ACTIVITIES (Pending past today) -->
                                                @if($overdueActivities->isNotEmpty())
                                                    <div class="activity-section-block" data-activity-section="overdue">
                                                        <div class="odoo-feed-divider odoo-feed-divider--overdue" data-bs-toggle="collapse" data-bs-target="#collapseOverdue_timeline" aria-expanded="true" aria-controls="collapseOverdue_timeline">
                                                            <span><i class="feather-chevron-down toggle-arrow me-1"></i> {{ __('crm.overdue_activities') }} ({{ $overdueActivities->count() }})</span>
                                                        </div>
                                                        <div class="collapse show odoo-activity-list mb-3" id="collapseOverdue_timeline">
                                                            @foreach($overdueActivities as $item)
                                                                @include('modules.crm.leads.partials.activity-card-item', ['item' => $item, 'category' => 'overdue', 'lead' => $lead, 'users' => $users])
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                @endif

                                                <!-- 3. TODAY'S ACTIVITIES (Done/Logged Today) -->
                                                @if($todayDoneActivities->isNotEmpty())
                                                    <div class="activity-section-block" data-activity-section="today">
                                                        <div class="odoo-feed-divider odoo-feed-divider--today-done" data-bs-toggle="collapse" data-bs-target="#collapseToday_timeline" aria-expanded="true" aria-controls="collapseToday_timeline">
                                                            <span><i class="feather-chevron-down toggle-arrow me-1"></i> {{ __('crm.today_activity') }} ({{ $todayDoneActivities->count() }})</span>
                                                        </div>
                                                        <div class="collapse show odoo-activity-list mb-3" id="collapseToday_timeline">
                                                            @foreach($todayDoneActivities as $item)
                                                                @include('modules.crm.leads.partials.activity-card-item', ['item' => $item, 'category' => 'today_done', 'lead' => $lead, 'users' => $users])
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                @endif

                                                <!-- Fallback if no pending activities -->
                                                @if(!$hasAnyPending && $todayDoneActivities->isEmpty())
                                                    <div class="activity-section-block" data-activity-section="planned">
                                                        <div class="odoo-feed-divider odoo-feed-divider--planned">
                                                            <span><i class="feather-chevron-down toggle-arrow me-1"></i> {{ __('crm.planned_activities') }}</span>
                                                        </div>
                                                        <div class="text-center py-4 text-muted fs-12">
                                                            <i class="feather-calendar fs-18 me-1 opacity-60"></i> {{ __('crm.no_pending_activities') }}
                                                        </div>
                                                    </div>
                                                @endif

                                                <!-- 4. PAST ACTIVITIES & HISTORY (Done before today) -->
                                                <div class="activity-section-block" data-activity-section="history">
                                                    <div class="odoo-feed-divider odoo-feed-divider--history mt-4" data-bs-toggle="collapse" data-bs-target="#collapseHistory_timeline" aria-expanded="true" aria-controls="collapseHistory_timeline">
                                                        <span><i class="feather-chevron-down toggle-arrow me-1"></i> {{ __('crm.past_activities_history') }}</span>
                                                    </div>

                                                    <div class="collapse show mb-3" id="collapseHistory_timeline">
                                                        @if($pastDoneActivities->isEmpty())
                                                            <div class="text-center py-4 text-muted fs-12">
                                                                <i class="feather-clock fs-14 me-1 opacity-60"></i> {{ __('crm.no_past_history_logs') }}
                                                            </div>
                                                        @else
                                                            <div class="odoo-activity-list mb-3">
                                                                @foreach($pastDoneActivities->take(5) as $item)
                                                                    @include('modules.crm.leads.partials.activity-card-item', ['item' => $item, 'category' => 'history', 'lead' => $lead, 'users' => $users])
                                                                @endforeach

                                                                @if($pastDoneActivities->count() > 5)
                                                                    <div class="collapse" id="collapseMorePastActivitiesTimeline">
                                                                        @foreach($pastDoneActivities->slice(5) as $item)
                                                                            @include('modules.crm.leads.partials.activity-card-item', ['item' => $item, 'category' => 'history', 'lead' => $lead, 'users' => $users])
                                                                        @endforeach
                                                                    </div>
                                                                    <div class="text-center mt-2 mb-1">
                                                                        <button class="btn btn-sm btn-outline-secondary rounded-pill px-3 fs-11 fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#collapseMorePastActivitiesTimeline" aria-expanded="false" aria-controls="collapseMorePastActivitiesTimeline" onclick="this.style.display='none'">
                                                                            <i class="feather-chevron-down me-1"></i> {{ __('crm.load_older_activities') }} (+{{ $pastDoneActivities->count() - 5 }})
                                                                        </button>
                                                                    </div>
                                                                @endif
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

                                                @if(!$isQuotationAutoApprove)
                                                    <x-ui.odoo-form-ui type="select" :label="__('crm.status')" name="status" :required="true" :errorText="$errors->first('status')">
                                                         <option value="Draft" @selected(old('status') === 'Draft')>{{ __('crm.quotation_statuses.Draft') }}</option>
                                                         <option value="Pending Approval" @selected(old('status') === 'Pending Approval')>{{ __('crm.quotation_statuses.Pending Approval') }}</option>
                                                     </x-ui.odoo-form-ui>
                                                @endif
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
                                                            <th class="text-end" style="width: 15%;">{{ __('crm.unit_price') }} ({{ active_currency_symbol() }})</th>
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
                                                     <span class="fw-bold text-dark" id="calcSubtotal">{{ active_currency_symbol() }}0.00</span>
                                                 </div>
                                                 <div class="d-flex justify-content-between py-1 border-bottom">
                                                     <span class="text-muted fw-semibold">{{ __('crm.taxes') }}:</span>
                                                     <span class="fw-bold text-dark" id="calcTax">{{ active_currency_symbol() }}0.00</span>
                                                 </div>
                                                 <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                                     <span class="text-muted fw-semibold me-2">{{ __('crm.discount_colon') }}</span>
                                                     <x-ui.odoo-form-ui type="input" name="discount" id="discountInput" inputType="number" :value="old('discount', 0)" min="0" step="0.01" class="text-end fw-bold" :errorText="$errors->first('discount')" />
                                                 </div>
                                                 <div class="d-flex justify-content-between py-2 fs-15 border-bottom bg-light-50 px-2 rounded mt-1.5">
                                                     <span class="text-dark fw-bold">{{ __('crm.total_colon') }}</span>
                                                     <span class="fw-extrabold text-primary" id="calcTotal">{{ active_currency_symbol() }}0.00</span>
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

                        @elseif ((request()->has('edit_quotation') || old('form_type') === 'quotation_edit') && $activeQuotation && $activeQuotation->status !== 'Accepted')
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

                                                @if(!$isQuotationAutoApprove)
                                                    <x-ui.odoo-form-ui type="select" :label="__('crm.status')" name="status" :required="true" :errorText="$errors->first('status')">
                                                         <option value="Draft" @selected(old('status', $activeQuotation->status) === 'Draft')>{{ __('crm.quotation_statuses.Draft') }}</option>
                                                         <option value="Pending Approval" @selected(old('status', $activeQuotation->status) === 'Pending Approval' || old('status', $activeQuotation->status) === 'Rejected' || old('status', $activeQuotation->status) === 'Quotation Rework' || old('status', $activeQuotation->status) === 'Approved' || old('status', $activeQuotation->status) === 'Declined')>{{ __('crm.quotation_statuses.Pending Approval') }}</option>
                                                    </x-ui.odoo-form-ui>
                                                @endif
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
                                                            <th class="text-end" style="width: 15%;">{{ __('crm.unit_price') }} ({{ active_currency_symbol() }})</th>
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
                                                    <span class="fw-bold text-dark" id="calcSubtotal">{{ active_currency_symbol() }}0.00</span>
                                                </div>
                                                <div class="d-flex justify-content-between py-1 border-bottom">
                                                    <span class="text-muted fw-semibold">{{ __('crm.taxes') }}:</span>
                                                    <span class="fw-bold text-dark" id="calcTax">{{ active_currency_symbol() }}0.00</span>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                                    <span class="text-muted fw-semibold me-2">{{ __('crm.discount_colon') }}</span>
                                                    <x-ui.odoo-form-ui type="input" name="discount" id="discountInput" inputType="number" :value="old('discount', $activeQuotation->discount)" min="0" step="0.01" class="text-end fw-bold" :errorText="$errors->first('discount')" />
                                                </div>
                                                <div class="d-flex justify-content-between py-2 fs-15 border-bottom bg-light-50 px-2 rounded mt-1.5">
                                                    <span class="text-dark fw-bold">{{ __('crm.total_colon') }}</span>
                                                    <span class="fw-extrabold text-primary" id="calcTotal">{{ active_currency_symbol() }}0.00</span>
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
                                                @if ($activeQuotation->status !== 'Accepted')
                                                     <a href="{{ route('crm.leads.show', ['lead' => $lead->id, 'edit_quotation' => 1]) }}" class="btn btn-sm btn-light border"><i class="feather-edit-2 me-1"></i>{{ __('crm.edit_quotation') }}</a>
                                                @endif
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
                                                      <a href="{{ $activeQuotation->crm_deal_id ? route('crm.deals.showConvertForm', $activeQuotation->crm_deal_id) : route('crm.quotations.showConvertForm', $activeQuotation->id) }}" class="btn btn-sm btn-warning text-dark fw-bold px-2.5 py-1.5">
                                                          <i class="feather-user-check me-1"></i>{{ __('crm.convert_to_customer') }}
                                                      </a>
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
                                                                <td class="text-end">{{ format_currency($item->unit_price) }}</td>
                                                                <td class="text-end">{{ number_format($item->tax_rate, 2) }}%</td>
                                                                <td class="text-end pe-3 fw-bold">{{ format_currency($item->amount) }}</td>
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
                                                    <span class="fw-bold text-dark">{{ format_currency($activeQuotation->subtotal) }}</span>
                                                </div>
                                                <div class="d-flex justify-content-between py-1 border-bottom">
                                                    <span class="text-muted fw-semibold">{{ __('crm.taxes') }}:</span>
                                                    <span class="fw-bold text-dark">{{ format_currency($activeQuotation->tax_amount) }}</span>
                                                </div>
                                                @if($activeQuotation->discount > 0)
                                                    <div class="d-flex justify-content-between py-1 border-bottom">
                                                        <span class="text-muted fw-semibold">{{ __('crm.discount') }}:</span>
                                                        <span class="fw-bold text-danger">-{{ format_currency($activeQuotation->discount) }}</span>
                                                    </div>
                                                @endif
                                                <div class="d-flex justify-content-between py-2 fs-15 border-bottom bg-light-50 px-2 rounded mt-1.5">
                                                    <span class="text-dark fw-bold">{{ __('crm.total_colon') }}</span>
                                                    <span class="fw-extrabold text-primary fs-16">{{ format_currency($activeQuotation->total_amount) }}</span>
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
                                                        <div class="quotation-rev-chip d-flex align-items-center gap-2 p-2 rounded {{ $rev->id === $activeQuotation->id ? 'active' : '' }}">
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
                                                                <span class="text-muted mt-0.5" style="font-size: 9px;">{{ format_currency($rev->total_amount) }}</span>
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

    <!-- Floating Right-Side CRM Mobile Softphone Dialer Widget for Leads -->
    <div id="crmFloatingSoftphoneWidget" class="crm-softphone-dock shadow-2xl" style="display: none; position: fixed; bottom: 24px; right: 24px; z-index: 1055; width: 320px; max-width: calc(100vw - 48px); border-radius: 20px; overflow: hidden; background: #ffffff; border: 1px solid rgba(226, 232, 240, 0.9); box-shadow: 0 20px 40px -15px rgba(15, 23, 42, 0.35), 0 0 0 1px rgba(0,0,0,0.06); transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);">
        
        <!-- Header: Modern Sleek Phone Header with Clearly Visible Action Buttons -->
        <div class="d-flex align-items-center justify-content-between crm-softphone-header" id="softphoneHeaderBar" style="cursor: pointer;">
            <div class="d-flex align-items-center gap-2.5">
                <div class="d-flex align-items-center justify-content-center rounded-circle" style="width: 28px; height: 28px; background: rgba(16, 185, 129, 0.2); border: 1px solid rgba(16, 185, 129, 0.4); color: #34d399;">
                    <i class="feather-phone" style="font-size: 13px;"></i>
                </div>
                <span class="text-white fw-bold fs-13" style="letter-spacing: 0.2px;">Phone Dialer</span>
                <span id="softphoneStatusPill" style="display: none;">Ready</span>
            </div>
            <div class="d-flex align-items-center gap-2">
                <button type="button" class="btn p-0 d-flex align-items-center justify-content-center crm-header-action-btn" id="btnMinimizeSoftphone" title="Minimize / Expand" style="width: 30px; height: 30px; text-decoration: none; cursor: pointer;">
                    <i class="feather-minus" id="iconSoftphoneMinMax" style="font-size: 15px; font-weight: bold;"></i>
                </button>
                <button type="button" class="btn p-0 d-flex align-items-center justify-content-center crm-header-close-btn" id="btnCloseSoftphone" title="Close Dialer" style="width: 30px; height: 30px; text-decoration: none; cursor: pointer;">
                    <i class="feather-x" style="font-size: 16px; font-weight: bold;"></i>
                </button>
            </div>
        </div>

        <!-- Body Content: Pure White in Light Mode, Dark in Dark Mode -->
        <div id="softphoneBody" class="p-3 crm-softphone-body" style="background: #ffffff; max-height: 80vh; overflow-y: auto;">
            
            <!-- Caller Display Section (Clean Minimalist Centered Mobile Style) -->
            <div class="text-center mb-3">
                <div class="avatar-text bg-primary text-white rounded-circle fw-bold mx-auto mb-1.5 shadow-sm d-flex align-items-center justify-content-center" id="dialerContactAvatar" style="width: 48px; height: 48px; font-size: 18px; letter-spacing: -0.5px;">
                    C
                </div>
                <div class="fw-bold text-dark fs-14 text-truncate px-2" id="dialerContactName">Customer</div>
                <div class="text-muted fs-11 text-truncate px-2" id="dialerContactSub">Direct Call</div>
            </div>

            <!-- Phone Screen Number Display with Centered Clear/Backspace -->
            <div class="position-relative mb-3">
                <input type="text" class="form-control form-control-lg text-center fw-bold fs-18 text-dark bg-white border shadow-2xs rounded-3 crm-dialer-display" id="dialerDisplayNumber" placeholder="Enter number" style="letter-spacing: 1px; font-family: monospace; height: 48px; padding-left: 48px; padding-right: 48px; line-height: 48px;">
                <button type="button" class="btn p-0 position-absolute" id="btnDialerBackspace" title="Backspace" style="right: 8px; top: 0; bottom: 0; margin: auto 0; width: 34px; height: 34px; display: flex; align-items: center; justify-content: center; border: none; background: transparent; text-decoration: none; border-radius: 50%; z-index: 5; cursor: pointer;">
                    <i class="feather-delete" style="font-size: 18px; line-height: 1; display: inline-flex; align-items: center; justify-content: center;"></i>
                </button>
            </div>

            <!-- Mobile Round Keypad (3x4) -->
            <div id="dialerIdlePanel">
                <div class="p-1 mb-3">
                    <div class="row g-2 text-center">
                        <div class="col-4"><button type="button" class="btn btn-white w-100 py-2 border rounded-3 shadow-2xs dialpad-key crm-dialpad-btn" data-key="1"><span class="d-block fw-bold fs-15 lh-1">1</span><span class="fs-8 text-muted d-block mt-0.5" style="letter-spacing: 1px;">&nbsp;</span></button></div>
                        <div class="col-4"><button type="button" class="btn btn-white w-100 py-2 border rounded-3 shadow-2xs dialpad-key crm-dialpad-btn" data-key="2"><span class="d-block fw-bold fs-15 lh-1">2</span><span class="fs-8 text-muted d-block mt-0.5" style="letter-spacing: 1px;">ABC</span></button></div>
                        <div class="col-4"><button type="button" class="btn btn-white w-100 py-2 border rounded-3 shadow-2xs dialpad-key crm-dialpad-btn" data-key="3"><span class="d-block fw-bold fs-15 lh-1">3</span><span class="fs-8 text-muted d-block mt-0.5" style="letter-spacing: 1px;">DEF</span></button></div>
                        <div class="col-4"><button type="button" class="btn btn-white w-100 py-2 border rounded-3 shadow-2xs dialpad-key crm-dialpad-btn" data-key="4"><span class="d-block fw-bold fs-15 lh-1">4</span><span class="fs-8 text-muted d-block mt-0.5" style="letter-spacing: 1px;">GHI</span></button></div>
                        <div class="col-4"><button type="button" class="btn btn-white w-100 py-2 border rounded-3 shadow-2xs dialpad-key crm-dialpad-btn" data-key="5"><span class="d-block fw-bold fs-15 lh-1">5</span><span class="fs-8 text-muted d-block mt-0.5" style="letter-spacing: 1px;">JKL</span></button></div>
                        <div class="col-4"><button type="button" class="btn btn-white w-100 py-2 border rounded-3 shadow-2xs dialpad-key crm-dialpad-btn" data-key="6"><span class="d-block fw-bold fs-15 lh-1">6</span><span class="fs-8 text-muted d-block mt-0.5" style="letter-spacing: 1px;">MNO</span></button></div>
                        <div class="col-4"><button type="button" class="btn btn-white w-100 py-2 border rounded-3 shadow-2xs dialpad-key crm-dialpad-btn" data-key="7"><span class="d-block fw-bold fs-15 lh-1">7</span><span class="fs-8 text-muted d-block mt-0.5" style="letter-spacing: 1px;">PQRS</span></button></div>
                        <div class="col-4"><button type="button" class="btn btn-white w-100 py-2 border rounded-3 shadow-2xs dialpad-key crm-dialpad-btn" data-key="8"><span class="d-block fw-bold fs-15 lh-1">8</span><span class="fs-8 text-muted d-block mt-0.5" style="letter-spacing: 1px;">TUV</span></button></div>
                        <div class="col-4"><button type="button" class="btn btn-white w-100 py-2 border rounded-3 shadow-2xs dialpad-key crm-dialpad-btn" data-key="9"><span class="d-block fw-bold fs-15 lh-1">9</span><span class="fs-8 text-muted d-block mt-0.5" style="letter-spacing: 1px;">WXYZ</span></button></div>
                        <div class="col-4"><button type="button" class="btn btn-white w-100 py-2 border rounded-3 shadow-2xs dialpad-key crm-dialpad-btn" data-key="*"><span class="d-block fw-bold fs-15 lh-1">*</span><span class="fs-8 text-muted d-block mt-0.5">&nbsp;</span></button></div>
                        <div class="col-4"><button type="button" class="btn btn-white w-100 py-2 border rounded-3 shadow-2xs dialpad-key crm-dialpad-btn" data-key="0"><span class="d-block fw-bold fs-15 lh-1">0</span><span class="fs-8 text-muted d-block mt-0.5">+</span></button></div>
                        <div class="col-4"><button type="button" class="btn btn-white w-100 py-2 border rounded-3 shadow-2xs dialpad-key crm-dialpad-btn" data-key="#"><span class="d-block fw-bold fs-15 lh-1">#</span><span class="fs-8 text-muted d-block mt-0.5">&nbsp;</span></button></div>
                    </div>
                </div>
            </div>

            <!-- Active In-Call Panel -->
            <div id="dialerInCallPanel" style="display: none;">
                <div class="p-3 bg-white rounded-3 border mb-3 shadow-2xs text-center crm-dialer-card">
                    <div class="d-flex align-items-center justify-content-center gap-1.5 mb-1.5">
                        <span class="spinner-grow spinner-grow-sm text-danger" style="width: 8px; height: 8px;" role="status"></span>
                        <span class="badge bg-soft-danger text-danger fs-10 fw-bold px-2 py-0.5" id="dialerLiveStatusTag">
                            Calling...
                        </span>
                        <span class="badge bg-soft-success text-success fs-11 fw-bold px-2 py-0.5" id="dialerLiveTimer">00:00</span>
                    </div>
                    <div class="fw-bold text-dark fs-14" id="inCallTargetDisplay">+91 0000000000</div>
                    <span class="text-muted fs-10" id="inCallSubtext">Telephony Active</span>

                    <!-- Audio Waveform -->
                    <div class="d-flex align-items-center justify-content-center gap-1 mt-2.5" style="height: 18px;">
                        <div class="bg-success rounded-pill audio-wave-bar" style="width: 3px; height: 12px; animation: pulseWave 1s infinite alternate;"></div>
                        <div class="bg-success rounded-pill audio-wave-bar" style="width: 3px; height: 18px; animation: pulseWave 0.7s infinite alternate 0.2s;"></div>
                        <div class="bg-success rounded-pill audio-wave-bar" style="width: 3px; height: 10px; animation: pulseWave 0.9s infinite alternate 0.4s;"></div>
                        <div class="bg-success rounded-pill audio-wave-bar" style="width: 3px; height: 16px; animation: pulseWave 0.6s infinite alternate 0.1s;"></div>
                        <div class="bg-success rounded-pill audio-wave-bar" style="width: 3px; height: 12px; animation: pulseWave 0.8s infinite alternate 0.3s;"></div>
                    </div>
                </div>

                <!-- Call Notes / Transcript Input -->
                <div class="mb-3">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="fw-bold fs-10 text-muted text-uppercase">Voice Notes:</span>
                        <span class="text-muted fs-9" id="liveSpeechStatus">Listening...</span>
                    </div>
                    <textarea class="form-control fs-11 bg-white" id="dialerCallNotes" rows="2" placeholder="Voice transcript & notes..."></textarea>
                </div>
            </div>

            <!-- Dial Button Action Container -->
            <div>
                <!-- 1. Start Call -->
                <div id="dialerStartAction">
                    <button type="button" class="btn btn-success w-100 py-2.5 fs-13 fw-bold d-flex align-items-center justify-content-center gap-2 shadow-sm rounded-pill" id="btnStartRealCall" style="background: linear-gradient(135deg, #059669 0%, #10b981 100%);">
                        <i class="feather-phone-call fs-14"></i>
                        <span>Start Call</span>
                    </button>
                </div>

                <!-- 2. Disconnect Call -->
                <div id="dialerEndAction" style="display: none;">
                    <button type="button" class="btn btn-danger w-100 py-2.5 fs-13 fw-bold d-flex align-items-center justify-content-center gap-2 shadow-sm rounded-pill" id="btnDisconnectAndAnalyze" style="background: linear-gradient(135deg, #e11d48 0%, #be123c 100%);">
                        <i class="feather-phone-off fs-14"></i>
                        <span id="btnDisconnectText">End Call & Save Summary</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal 2: Gemini AI Call Analysis & Confirmation Popup -->
    <x-ui.modal id="geminiAiCallReviewModal" size="lg" :centered="true" :static="true" :showFooter="false">
        <x-slot:title>
            <div class="d-flex align-items-center gap-2">
                <div class="avatar-text avatar-sm bg-primary text-white rounded-circle shadow-xs">
                    <i class="feather-cpu fs-14"></i>
                </div>
                <div>
                    <div class="d-flex align-items-center gap-2">
                        <h5 class="modal-title fs-14 fw-bold mb-0 text-dark" id="geminiAiCallReviewModalLabel">Gemini AI Call Recording Analysis & Next Steps</h5>
                        <span class="badge bg-soft-primary text-primary fs-10 fw-bold px-2 py-0.5 rounded-pill shadow-xs">Gemini Flash AI</span>
                    </div>
                    <span class="fs-11 text-muted">Audio transcribed & analyzed. Review before confirming activity to CRM.</span>
                </div>
            </div>
        </x-slot:title>

        <!-- Target Lead Quick Header -->
        <div class="bg-light p-3 rounded-3 border mb-3 shadow-xs d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted fs-10 text-uppercase fw-bold">Target Lead / Company</span>
                <div class="fw-bold text-dark fs-13" id="aiModalLeadName">Lead Name</div>
                <span class="text-muted fs-11" id="aiModalDialedNumber">+91 0000000000</span>
            </div>
            <div class="text-end">
                <span class="text-muted fs-10 text-uppercase fw-bold d-block">AI Sentiment</span>
                <span class="badge bg-soft-success text-success border border-success-subtle fs-11 fw-bold" id="aiModalSentimentBadge">Positive / Interested</span>
            </div>
        </div>

        <form id="geminiAiApprovalForm">
            <input type="hidden" id="aiModalLeadId" value="">
            <input type="hidden" id="aiModalCallDuration" value="0">
            <input type="hidden" id="aiModalDialedPhoneVal" value="">
            <input type="hidden" id="aiModalRecordingUrl" value="">

            <!-- Audio Recording Preview & Playback -->
            <div id="aiModalAudioPreviewContainer" class="p-3 mb-3 bg-white rounded-3 border shadow-xs" style="display: none; border-color: #bfdbfe !important; background: linear-gradient(135deg, #f8fafc 0%, #eff6ff 100%) !important;">
                <div class="d-flex align-items-center justify-content-between mb-2.5 pb-2 border-bottom border-primary border-opacity-10 flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <div class="avatar-text avatar-xs bg-primary text-white rounded-circle d-flex align-items-center justify-content-center shadow-xs" style="width: 26px; height: 26px; font-size: 11px;">
                            <i class="feather-phone-call"></i>
                        </div>
                        <div>
                            <span class="fw-bold text-dark fs-12 d-block">Call Recording Audio</span>
                            <span class="text-muted fs-10">Play & verify speech before CRM confirmation</span>
                        </div>
                    </div>
                    <span class="badge bg-soft-primary text-primary border border-primary-subtle fs-10 fw-bold px-2.5 py-1 rounded-pill">
                        <i class="feather-disc me-1 fs-9"></i>2-Way Audio
                    </span>
                </div>
                <div class="px-0.5 pt-0.5">
                    <audio id="aiModalAudioPlayer" controls preload="metadata" class="w-100" style="height: 38px; border-radius: 25px; outline: none;">
                        Your browser does not support audio playback.
                    </audio>
                </div>
            </div>

            <!-- 1. Voice Recording Transcription (Speech-to-Text) -->
            <div class="mb-3">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <label class="form-label fw-bold fs-12 text-dark mb-0">
                        <i class="feather-volume-2 text-primary me-1"></i> Audio Recording Transcript (What was spoken)
                    </label>
                    <span class="badge bg-soft-indigo text-indigo fs-10">Gemini Audio Speech-to-Text</span>
                </div>
                <textarea class="form-control fs-12 bg-white" id="aiModalTranscript" rows="2" placeholder="Transcribed conversation text..."></textarea>
            </div>

            <!-- 2. Discussion Summary -->
            <div class="mb-3">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <label class="form-label fw-bold fs-12 text-dark mb-0">
                        <i class="feather-file-text text-primary me-1"></i> Discussion Summary & Outcomes
                    </label>
                    <span class="text-muted fs-10">Editable</span>
                </div>
                <textarea class="form-control fs-12 bg-white" id="aiModalDiscussionSummary" rows="3" required placeholder="Discussion details analyzed by Gemini..."></textarea>
            </div>

            <!-- 3. Next Follow-up & Activity Details -->
            <div class="p-3 bg-light rounded-3 border mb-3 shadow-xs">
                <div class="d-flex align-items-center gap-1.5 mb-2.5 pb-2 border-bottom">
                    <i class="feather-calendar text-primary fs-13"></i>
                    <h6 class="fs-12 fw-bold text-dark mb-0">Proposed Next Follow-up & Activity Schedule</h6>
                </div>

                <div class="row g-2 mb-2">
                    <div class="col-md-5">
                        <label class="form-label fw-semibold fs-11 text-muted mb-1">Next Activity Type</label>
                        <select class="form-select form-select-sm fs-12" id="aiModalNextActivityType">
                            <option value="Call">📞 Call Back</option>
                            <option value="Meeting">🤝 Meeting</option>
                            <option value="Demo">💻 Demo</option>
                            <option value="WhatsApp">💬 WhatsApp Follow-up</option>
                            <option value="Email">✉️ Email Follow-up</option>
                        </select>
                    </div>
                    <div class="col-md-7">
                        <label class="form-label fw-semibold fs-11 text-muted mb-1">Next Follow-up Date & Time</label>
                        <input type="datetime-local" class="form-control form-control-sm fs-12" id="aiModalNextFollowupDate">
                    </div>
                </div>

                <div class="mb-1">
                    <label class="form-label fw-semibold fs-11 text-muted mb-1">Next Activity Agenda / Title</label>
                    <input type="text" class="form-control form-control-sm fs-12" id="aiModalNextActivityTitle" placeholder="e.g. Follow-up call for quotation and order confirmation">
                </div>
            </div>

            <!-- 4. Suggested Lead Status & Previous Meeting Notice -->
            <div class="row g-2 mb-3">
                <div class="col-md-6">
                    <div class="p-2.5 bg-white rounded-3 border shadow-xs h-100">
                        <label class="form-label fw-semibold fs-11 text-muted mb-1">Suggested Lead Status</label>
                        <select class="form-select form-select-sm fs-12" id="aiModalLeadStatus">
                            @foreach($leadStatuses as $ls)
                                <option value="{{ $ls->name }}">{{ $ls->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="p-2.5 bg-soft-info border border-info-subtle rounded-3 text-dark fs-11 h-100 d-flex align-items-center">
                        <div>
                            <div class="fw-bold text-info mb-0.5"><i class="feather-check-circle me-1"></i>Meeting Auto-Sync</div>
                            <span class="text-muted fs-10">Any pending meeting/follow-up will be marked as Completed with this log.</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 5. Follow-up Confirmation Message Preview -->
            <div class="mb-2">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <label class="form-label fw-bold fs-12 text-dark mb-0">
                        <i class="feather-send text-success me-1"></i> Follow-up Message Preview (WhatsApp / Email)
                    </label>
                    <span class="text-muted fs-10">Auto Drafted</span>
                </div>
                <textarea class="form-control fs-12 bg-white" id="aiModalFollowupMessage" rows="3" placeholder="AI drafted message for client..."></textarea>
            </div>

            <div class="d-flex align-items-center justify-content-between border-top pt-3 mt-3">
                <button type="button" class="btn btn-outline-secondary fs-12 py-2 px-3 fw-semibold" data-bs-dismiss="modal">
                    Cancel
                </button>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-light border fs-12 py-2 px-3 fw-semibold text-dark" id="btnSkipAndLogOnly">
                        <i class="feather-save me-1"></i> Save Log Only
                    </button>
                    <button type="button" class="btn btn-primary fs-12 py-2 px-4 fw-bold shadow-sm" id="btnApproveAndSchedule" style="background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);">
                        <i class="feather-check-circle me-1"></i> Approve & Save Call to CRM
                    </button>
                </div>
            </div>
        </form>
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

        /* Softphone Floating Widget Light & Dark Mode Styles */
        .crm-softphone-dock {
            background: #ffffff !important;
            border: 1px solid rgba(226, 232, 240, 0.9) !important;
            color: #1e293b;
        }
        .crm-softphone-header {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%) !important;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08) !important;
            padding: 11px 15px !important;
        }
        .crm-softphone-body {
            background: #ffffff !important;
        }
        .crm-header-action-btn {
            color: #f1f5f9 !important;
            background: rgba(255, 255, 255, 0.12) !important;
            border: 1px solid rgba(255, 255, 255, 0.18) !important;
            border-radius: 8px !important;
            transition: all 0.2s ease;
        }
        .crm-header-action-btn:hover {
            color: #ffffff !important;
            background: rgba(255, 255, 255, 0.25) !important;
            border-color: rgba(255, 255, 255, 0.35) !important;
            transform: scale(1.05);
        }
        .crm-header-close-btn {
            color: #ffffff !important;
            background: #ef4444 !important;
            border: 1px solid #dc2626 !important;
            border-radius: 8px !important;
            box-shadow: 0 2px 6px rgba(239, 68, 68, 0.35) !important;
            transition: all 0.2s ease;
        }
        .crm-header-close-btn:hover {
            color: #ffffff !important;
            background: #dc2626 !important;
            border-color: #b91c1c !important;
            box-shadow: 0 4px 10px rgba(239, 68, 68, 0.5) !important;
            transform: scale(1.05);
        }
        .crm-dialer-display {
            background: #ffffff !important;
            color: #0f172a !important;
            border-color: #e2e8f0 !important;
        }
        .crm-dialpad-btn {
            background: #ffffff !important;
            color: #0f172a !important;
            border-color: #e2e8f0 !important;
            transition: all 0.15s ease;
        }
        .crm-dialpad-btn:hover {
            background: #f8fafc !important;
            border-color: #cbd5e1 !important;
        }
        .crm-dialer-card {
            background: #ffffff !important;
            border-color: #e2e8f0 !important;
        }

        /* Dark Mode Theme Support */
        [data-bs-theme="dark"] .crm-softphone-dock,
        html.app-skin-dark .crm-softphone-dock {
            background: #1e293b !important;
            border-color: #334155 !important;
            color: #f8fafc !important;
            box-shadow: 0 20px 45px -10px rgba(0, 0, 0, 0.7), 0 0 0 1px rgba(255, 255, 255, 0.08) !important;
        }
        [data-bs-theme="dark"] .crm-softphone-header,
        html.app-skin-dark .crm-softphone-header {
            background: #1e293b !important;
            border-bottom-color: #334155 !important;
        }
        [data-bs-theme="dark"] .crm-softphone-body,
        html.app-skin-dark .crm-softphone-body {
            background: #0f172a !important;
        }
        [data-bs-theme="dark"] .crm-header-action-btn,
        html.app-skin-dark .crm-header-action-btn {
            color: #cbd5e1 !important;
            background: #334155 !important;
        }
        [data-bs-theme="dark"] .crm-header-action-btn:hover,
        html.app-skin-dark .crm-header-action-btn:hover {
            color: #ffffff !important;
            background: #475569 !important;
        }
        [data-bs-theme="dark"] .crm-header-close-btn,
        html.app-skin-dark .crm-header-close-btn {
            color: #cbd5e1 !important;
            background: #334155 !important;
        }
        [data-bs-theme="dark"] .crm-header-close-btn:hover,
        html.app-skin-dark .crm-header-close-btn:hover {
            color: #f87171 !important;
            background: rgba(239, 68, 68, 0.25) !important;
        }
        [data-bs-theme="dark"] .crm-dialer-display,
        html.app-skin-dark .crm-dialer-display {
            background: #1e293b !important;
            color: #ffffff !important;
            border-color: #334155 !important;
        }
        [data-bs-theme="dark"] .crm-dialpad-btn,
        html.app-skin-dark .crm-dialpad-btn {
            background: #1e293b !important;
            color: #ffffff !important;
            border-color: #334155 !important;
        }
        [data-bs-theme="dark"] .crm-dialpad-btn:hover,
        html.app-skin-dark .crm-dialpad-btn:hover {
            background: #334155 !important;
            border-color: #475569 !important;
        }
        [data-bs-theme="dark"] .crm-dialer-card,
        html.app-skin-dark .crm-dialer-card {
            background: #1e293b !important;
            border-color: #334155 !important;
        }
        [data-bs-theme="dark"] .crm-softphone-dock .text-dark,
        html.app-skin-dark .crm-softphone-dock .text-dark {
            color: #f8fafc !important;
        }
        [data-bs-theme="dark"] .crm-softphone-dock .text-muted,
        html.app-skin-dark .crm-softphone-dock .text-muted {
            color: #94a3b8 !important;
        }
        [data-bs-theme="dark"] .crm-softphone-dock textarea,
        html.app-skin-dark .crm-softphone-dock textarea {
            background: #1e293b !important;
            color: #ffffff !important;
            border-color: #334155 !important;
        }
        #btnDialerBackspace {
            transition: all 0.15s ease;
            color: #94a3b8 !important;
        }
        #btnDialerBackspace:hover {
            color: #ef4444 !important;
            background: #fee2e2 !important;
        }
        [data-bs-theme="dark"] #btnDialerBackspace:hover,
        html.app-skin-dark #btnDialerBackspace:hover {
            background: rgba(239, 68, 68, 0.2) !important;
            color: #f87171 !important;
        }
        @keyframes pulseWave {
            0% { height: 6px; opacity: 0.5; }
            100% { height: 18px; opacity: 1; }
        }
    </style>
@endpush

@push('scripts')
    <!-- Twilio Voice WebRTC SDK for 2-Way Live Calling -->
    <script src="https://cdn.jsdelivr.net/npm/@twilio/voice-sdk@2.11.1/dist/twilio.min.js"></script>
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
            
            // Check URL Query Param & Hash first
            var urlParams = new URLSearchParams(window.location.search);
            var tabParam = urlParams.get('tab');
            var hash = window.location.hash;

            if (tabParam === 'interactions' || tabParam === 'timeline' || hash === '#timeline' || hash === '#timeline-pane' || hash === '#subtab-interactions' || hash === '#subtab-history') {
                localStorage.setItem(activeTabKey, 'timeline-tab');
                if (tabParam === 'interactions' || hash === '#subtab-interactions') {
                    localStorage.setItem(activeSubTabKey, 'subtab-interactions-tab');
                } else if (hash === '#subtab-history') {
                    localStorage.setItem(activeSubTabKey, 'subtab-history-tab');
                }
            } else if (tabParam === 'overview' || hash === '#overview' || hash === '#overview-pane') {
                localStorage.setItem(activeTabKey, 'overview-tab');
            } else if (tabParam === 'quotation' || hash === '#quotation' || hash === '#quotation-pane') {
                localStorage.setItem(activeTabKey, 'quotation-tab');
            }

            // Always clean up hash or temporary query params from address bar so URL remains clean
            if (window.history && window.history.replaceState) {
                var cleanUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
                window.history.replaceState(null, '', cleanUrl);
            }

            // Scroll Spy for Overview Sections
            let isManualClick = false;
            $('#zohoMainScrollable').on('scroll', function() {
                if (isManualClick) return;
                const scrollContainer = this;
                const containerTop = scrollContainer.getBoundingClientRect().top;
                const stickyHeader = document.querySelector('.sticky-top');
                const stickyHeaderHeight = stickyHeader ? stickyHeader.offsetHeight : 50;

                const sections = ['#sectionLeadInfo', '#sectionLeadProducts', '#sectionAddressInfo', '#sectionRequirements', '#sectionNotes', '#sectionDocuments'];
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

            function syncSidebarWithCurrentTab(targetId) {
                if (targetId === 'overview-tab' || targetId === '#overview-pane') {
                    const scrollContainer = document.getElementById('zohoMainScrollable');
                    if (scrollContainer) {
                        const containerTop = scrollContainer.getBoundingClientRect().top;
                        const stickyHeader = document.querySelector('.sticky-top');
                        const stickyHeaderHeight = stickyHeader ? stickyHeader.offsetHeight : 50;
                        const sections = ['#sectionLeadInfo', '#sectionLeadProducts', '#sectionAddressInfo', '#sectionRequirements', '#sectionNotes', '#sectionDocuments'];
                        let currentSection = '#sectionLeadInfo';
                        sections.forEach(function(secId) {
                            const el = document.querySelector(secId);
                            if (el) {
                                const rect = el.getBoundingClientRect();
                                if (rect.top - containerTop <= stickyHeaderHeight + 60) {
                                    currentSection = secId;
                                }
                            }
                        });
                        $('#zohoSidebarLinks a').removeClass('active');
                        $('#zohoSidebarLinks a[href="' + currentSection + '"]').addClass('active');
                    }
                } else if (targetId === 'quotation-tab' || targetId === '#quotation-pane') {
                    $('#zohoSidebarLinks a').removeClass('active');
                    $('#zohoSidebarLinks a[href="#sectionQuotationHistory"]').addClass('active');
                } else if (targetId === 'timeline-tab' || targetId === '#timeline-pane') {
                    const isInteractionsActive = $('#subtab-interactions-tab').hasClass('active') || $('#subtab-interactions').hasClass('active') || localStorage.getItem(activeSubTabKey) === 'subtab-interactions-tab';
                    const activeSubtabHref = isInteractionsActive ? '#subtab-interactions' : '#subtab-history';
                    $('#zohoSidebarLinks a').removeClass('active');
                    $('#zohoSidebarLinks a[href="' + activeSubtabHref + '"]').addClass('active');
                } else if (targetId === 'subtab-interactions-tab' || targetId === '#subtab-interactions') {
                    $('#zohoSidebarLinks a').removeClass('active');
                    $('#zohoSidebarLinks a[href="#subtab-interactions"]').addClass('active');
                } else if (targetId === 'subtab-history-tab' || targetId === '#subtab-history') {
                    $('#zohoSidebarLinks a').removeClass('active');
                    $('#zohoSidebarLinks a[href="#subtab-history"]').addClass('active');
                }
            }

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
                    syncSidebarWithCurrentTab(savedTabId);
                }, 60);
            } else {
                if ($('#timeline-tab').hasClass('active')) {
                    syncSidebarWithCurrentTab('timeline-tab');
                }
            }

            var scrollTargetOnTabShown = null;

            function scrollToElement(targetEl) {
                var scrollContainer = $('#zohoMainScrollable');
                if (!scrollContainer.length || !targetEl.length) return;
                var relativeTop = targetEl.offset().top - scrollContainer.offset().top;
                var scrollTopPosition = scrollContainer.scrollTop() + relativeTop - 50; // Offset for sticky tabs

                scrollContainer.animate({
                    scrollTop: scrollTopPosition
                }, 400);
            }

            // Smooth Related List Sidebar Navigation & Tab Synchronization (Zero URL hash modification)
            $('#zohoSidebarLinks a').on('click', function(e) {
                e.preventDefault();
                var targetId = $(this).attr('href');
                if (!targetId || !targetId.startsWith('#')) return;

                // Remove active class from all sidebar links and add to clicked one
                $('#zohoSidebarLinks a').removeClass('active');
                $(this).addClass('active');
                isManualClick = true;

                // Ensure URL hash is removed from browser bar
                if (window.history && window.history.replaceState) {
                    var cleanUrl = window.location.protocol + "//" + window.location.host + window.location.pathname;
                    window.history.replaceState(null, '', cleanUrl);
                }

                var targetTabBtnId = null;
                var targetSubtabBtnId = null;

                if (['#sectionNotes', '#sectionLeadInfo', '#sectionLeadProducts', '#sectionAddressInfo', '#sectionRequirements', '#sectionDocuments'].includes(targetId)) {
                    targetTabBtnId = 'overview-tab';
                } else if (targetId === '#sectionQuotationHistory') {
                    targetTabBtnId = 'quotation-tab';
                } else if (targetId === '#subtab-interactions' || targetId === '#subtab-history') {
                    targetTabBtnId = 'timeline-tab';
                    targetSubtabBtnId = (targetId === '#subtab-interactions') ? 'subtab-interactions-tab' : 'subtab-history-tab';
                }

                // Activate subtab first if target is a subtab
                if (targetSubtabBtnId) {
                    var subTabEl = document.getElementById(targetSubtabBtnId);
                    if (subTabEl) {
                        bootstrap.Tab.getOrCreateInstance(subTabEl).show();
                    }
                }

                var targetEl = $(targetId);
                var mainTabEl = targetTabBtnId ? document.getElementById(targetTabBtnId) : null;
                var isAlreadyActive = mainTabEl && mainTabEl.classList.contains('active');

                if (isAlreadyActive) {
                    if (targetEl.length) {
                        scrollToElement(targetEl);
                    }
                    setTimeout(function() { isManualClick = false; }, 500);
                } else if (mainTabEl) {
                    if (targetEl.length) {
                        scrollTargetOnTabShown = targetEl;
                    }
                    bootstrap.Tab.getOrCreateInstance(mainTabEl).show();
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
                    if (!scrollTargetOnTabShown) {
                        syncSidebarWithCurrentTab(e.target.id);
                    }
                }

                if (scrollTargetOnTabShown) {
                    scrollToElement(scrollTargetOnTabShown);
                    scrollTargetOnTabShown = null;
                    setTimeout(function() { isManualClick = false; }, 500);
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
                            <input type="number" name="items[${index}][unit_price]" class="odoo-table-input text-end price-input" value="0.00" min="0.01" step="0.01" required style="max-width: 120px; margin-left: auto; text-align: right;">
                        </td>
                        <td>
                            <input type="number" name="items[${index}][tax_rate]" class="odoo-table-input text-end tax-input" value="18.00" min="0" max="100" step="0.01" style="max-width: 80px; margin-left: auto; text-align: right;">
                        </td>
                        <td class="text-end fw-bold text-dark amount-display pe-3">
                            {{ active_currency_symbol() }}0.00
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

                    const currSym = window.AppCurrency?.symbol || @json(active_currency_symbol());
                    $(this).find('.amount-display').text(currSym + amount.toFixed(2));
                });

                const discount = parseFloat($('#discountInput').val()) || 0;
                const grandTotal = subtotal + taxTotal - discount;

                const currSym = window.AppCurrency?.symbol || @json(active_currency_symbol());
                $('#calcSubtotal').text(currSym + subtotal.toFixed(2));
                $('#calcTax').text(currSym + taxTotal.toFixed(2));
                $('#calcTotal').text(currSym + Math.max(0, grandTotal).toFixed(2));
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
            });

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

            // Initialize Bootstrap Popovers for Odoo Info (i) tooltips
            var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
            var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
                return new bootstrap.Popover(popoverTriggerEl);
            });

            // Auto submit form on status change (handles both native and Select2 dropdowns)
            $(document).on('change change.select2', '.status-select', function() {
                var form = $(this).closest('form');
                if (form.length) {
                    form[0].submit();
                }
            });
            // Toggle Offcanvas Mode (Log Activity & Next Followup vs Schedule Direct Activity)
            function switchOffcanvasMode(mode) {
                $('.offcanvas-mode-btn').removeClass('active btn-primary text-white shadow-sm').css({'background-color': 'transparent', 'color': '#64748b', 'box-shadow': 'none'});
                var activeBtn = $('.offcanvas-mode-btn[data-mode="' + mode + '"]');
                activeBtn.addClass('active btn-primary text-white shadow-sm').css({'background-color': 'var(--bs-primary)', 'color': '#ffffff'});
                $('#offcanvasActionMode').val(mode);

                if (mode === 'log_note') {
                    $('#sectionPastInteraction, #sectionLogInteraction').show();
                    $('#sectionDirectSchedule').hide();
                    $('#offcanvasFollowupDate').removeAttr('required');
                } else {
                    $('#sectionPastInteraction, #sectionLogInteraction').hide();
                    $('#sectionDirectSchedule').show();
                    $('#offcanvasFollowupDate').attr('required', 'required');
                }
            }

            $(document).on('click', '.offcanvas-mode-btn', function() {
                switchOffcanvasMode($(this).attr('data-mode'));
            });

            window.enableRequirementEdit = function() {
                $('#viewRequirementBlock').hide();
                $('#editRequirementBlock').show();
                var el = document.getElementById('requirementInput');
                if (el) {
                    updateReqCharCount(el);
                    el.focus();
                }
            };

            window.cancelRequirementEdit = function() {
                $('#editRequirementBlock').hide();
                $('#viewRequirementBlock').show();
            };

            window.updateReqCharCount = function(el) {
                var len = el ? el.value.length : 0;
                $('#reqCharCounter').text(len + ' chars');
            };

            function escapeHtml(text) {
                return text
                    .replace(/&/g, "&amp;")
                    .replace(/</g, "&lt;")
                    .replace(/>/g, "&gt;")
                    .replace(/"/g, "&quot;")
                    .replace(/'/g, "&#039;");
            }

            $(document).on('keydown', '#requirementInput', function(e) {
                if ((e.ctrlKey || e.metaKey) && e.keyCode === 13) {
                    e.preventDefault();
                    $('#ajaxRequirementForm').submit();
                }
            });

            $(document).on('submit', '#ajaxRequirementForm', function(e) {
                e.preventDefault();
                var form = $(this);
                var btn = $('#btnSaveRequirement');
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
                            var reqText = res.requirement || '';
                            var viewBlock = $('#viewRequirementBlock');

                            if (reqText.trim().length > 0) {
                                viewBlock.html(`
                                    <div class="position-relative requirement-clickable-box p-3 rounded shadow-2xs" onclick="enableRequirementEdit()" title="Click anywhere to edit requirement">
                                        <div class="d-flex align-items-start justify-content-between gap-3">
                                            <div class="text-dark fs-13 flex-grow-1" style="white-space: pre-wrap; line-height: 1.6; font-family: 'Inter', sans-serif;" id="viewRequirementText">${escapeHtml(reqText)}</div>
                                            <span class="badge bg-white text-primary border shadow-2xs px-2.5 py-1.5 fs-11 flex-shrink-0 edit-hint-badge" style="border-color: #cbd5e1 !important; transition: all 0.2s ease;">
                                                <i class="feather-edit-2 me-1"></i>Click to Edit
                                            </span>
                                        </div>
                                    </div>
                                `);
                            } else {
                                viewBlock.html(`
                                    <div class="position-relative requirement-empty-box p-4 rounded text-center cursor-pointer" onclick="enableRequirementEdit()" title="Click to add requirement">
                                        <div class="avatar-text avatar-md bg-soft-primary text-primary rounded-circle mx-auto mb-2">
                                            <i class="feather-edit-3 fs-5"></i>
                                        </div>
                                        <h6 class="fw-bold text-dark fs-13 mb-1">No Requirements Details Specified</h6>
                                        <p class="text-muted fs-12 mb-0">Click here to add client requirements, scope of work, or project specifications.</p>
                                    </div>
                                `);
                            }

                            cancelRequirementEdit();
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

            window.toggleNextScheduleFieldsShow = function(show) {
                var container = $('#containerNextScheduleFieldsShow');
                var btn = $('#btnToggleNextScheduleShow');
                var icon = $('#iconToggleNextScheduleShow');
                var text = $('#textToggleNextScheduleShow');

                if (show === undefined) {
                    show = (container.css('display') === 'none');
                }

                if (show) {
                    container.slideDown(200);
                    btn.removeClass('btn-outline-primary').addClass('btn-soft-danger');
                    icon.removeClass('feather-plus').addClass('feather-x');
                    text.text('Remove Next Activity');
                } else {
                    container.slideUp(200);
                    btn.removeClass('btn-soft-danger').addClass('btn-outline-primary');
                    icon.removeClass('feather-x').addClass('feather-plus');
                    text.text('Schedule Next Activity');
                    $('#offcanvasNextTitle, #offcanvasNextFollowupDate, #offcanvasNextGuestEmails').val('');
                }
            };

            $(document).on('click', '#btnToggleNextScheduleShow', function() {
                toggleNextScheduleFieldsShow();
            });

            // Open and populate Offcanvas drawer for Lead Followup / Schedule Activity
            $(document).on('click', '.btn-open-followup-offcanvas', function() {
                var leadId = $(this).attr('data-lead-id') || '{{ $lead->id }}';
                var leadName = $(this).attr('data-lead-name') || '{{ $lead->company_name }}';
                var leadStatus = $(this).attr('data-lead-status') || '{{ $lead->status }}';
                var leadPriority = $(this).attr('data-lead-priority') || '{{ $lead->priority }}';
                var nextFollowup = $(this).attr('data-next-followup') || '{{ $lead->next_followup_date ? $lead->next_followup_date->format("Y-m-d\TH:i") : "" }}';

                $('#leadFollowupForm').attr('action', '{{ url("crm/leads") }}/' + leadId + '/followups');
                $('#leadFollowupOffcanvasTitle').text('Edit Followup for ' + leadName);

                $('#offcanvasLeadStatus').val(leadStatus || 'New');
                $('#offcanvasLeadPriority').val(leadPriority || 'Medium');
                $('#offcanvasFollowupDate').val('');
                $('#offcanvasNotes, #offcanvasScheduleNotes').val('');

                // Next schedule section reset — blank, collapsed
                $('#offcanvasNextFollowupDate').val('');
                toggleNextScheduleFieldsShow(false);

                if ($('#offcanvasTagUser').length && $.fn.select2) {
                    if ($('#offcanvasTagUser').hasClass('select2-hidden-accessible')) {
                        $('#offcanvasTagUser').select2('destroy');
                    }
                    $('#offcanvasTagUser').select2({
                        theme: "bootstrap-5",
                        width: "100%",
                        dropdownParent: $('#leadFollowupOffcanvas'),
                        placeholder: "Select persons to tag..."
                    });
                    $('#offcanvasTagUser').val(null).trigger('change');
                }

                switchOffcanvasMode('log_note');
            });

            // =========================================================================
            // ACTIVITY FEED QUICK FILTERING VIA HORIZONTAL TABS & EXPAND / COLLAPSE ALL
            // =========================================================================
            $(document).on('click', '.activity-feed-container .erp-horizontal-tabs .nav-link, .activity-feed-container .erp-horizontal-tabs button', function(e) {
                var tabId = ($(this).attr('id') || $(this).attr('data-bs-target') || '').toLowerCase();
                var container = $(this).closest('.activity-feed-container');

                if (tabId.indexOf('planned') !== -1) {
                    container.find('.activity-section-block').hide();
                    container.find('.activity-section-block[data-activity-section="planned"]').stop(true, true).fadeIn(150).find('.collapse').collapse('show');
                } else if (tabId.indexOf('overdue') !== -1) {
                    container.find('.activity-section-block').hide();
                    container.find('.activity-section-block[data-activity-section="overdue"]').stop(true, true).fadeIn(150).find('.collapse').collapse('show');
                } else if (tabId.indexOf('today') !== -1) {
                    container.find('.activity-section-block').hide();
                    container.find('.activity-section-block[data-activity-section="today"]').stop(true, true).fadeIn(150).find('.collapse').collapse('show');
                } else if (tabId.indexOf('history') !== -1) {
                    container.find('.activity-section-block').hide();
                    container.find('.activity-section-block[data-activity-section="history"]').stop(true, true).fadeIn(150).find('.collapse').collapse('show');
                } else {
                    // All
                    container.find('.activity-section-block').stop(true, true).fadeIn(150);
                }
            });

            $(document).on('click', '.btn-expand-all-activities', function(e) {
                e.preventDefault();
                var container = $(this).closest('.activity-feed-container');
                container.find('.collapse').collapse('show');
            });

            $(document).on('click', '.btn-collapse-all-activities', function(e) {
                e.preventDefault();
                var container = $(this).closest('.activity-feed-container');
                container.find('.collapse').collapse('hide');
            });

            // =========================================================================
            // CRM SOFTPHONE DIALER, AUDIO RECORDING & TWILIO / GEMINI AI INTEGRATION
            // =========================================================================
            var activeCallLeadId = {{ $lead->id }};
            var activeCallLeadName = @json($lead->contact_person ?: $lead->company_name);
            var activeCallLeadCompany = @json($lead->company_name);
            var activeCallDurationSeconds = 0;
            var activeCallInterval = null;
            var activeAudioStream = null;
            var activeMediaRecorder = null;
            var audioChunks = [];
            var speechRecognition = null;
            var isSpeechRecording = false;
            var speechFinalTranscript = '';
            var isSoftphoneMinimized = false;

            function formatCallTimer(sec) {
                var m = Math.floor(sec / 60);
                var s = sec % 60;
                return (m < 10 ? '0' + m : m) + ':' + (s < 10 ? '0' + s : s);
            }

            // Initialize Speech Recognition if supported
            var SpeechRecognitionClass = window.SpeechRecognition || window.webkitSpeechRecognition;
            if (SpeechRecognitionClass) {
                speechRecognition = new SpeechRecognitionClass();
                speechRecognition.continuous = true;
                speechRecognition.interimResults = true;
                speechRecognition.lang = 'hi-IN';

                speechRecognition.onresult = function(event) {
                    var interimTranscript = '';
                    for (var i = event.resultIndex; i < event.results.length; ++i) {
                        var chunk = event.results[i][0].transcript;
                        if (event.results[i].isFinal) {
                            speechFinalTranscript += chunk + ' ';
                        } else {
                            interimTranscript += chunk;
                        }
                    }
                    var fullText = (speechFinalTranscript + ' ' + interimTranscript).trim();
                    if (fullText) {
                        $('#dialerCallNotes').val(fullText);
                        $('#liveSpeechStatus').html('<span class="text-success fw-bold"><i class="feather-mic"></i> Converting speech to text...</span>');
                    }
                };

                speechRecognition.onerror = function(event) {
                    console.warn('Speech recognition warning:', event.error);
                };

                speechRecognition.onend = function() {
                    if (isSpeechRecording && speechRecognition) {
                        setTimeout(function() {
                            if (isSpeechRecording && speechRecognition) {
                                try { speechRecognition.start(); } catch(e) {}
                            }
                        }, 200);
                    }
                };
            }

            // Web Audio Ringing Tone Generator
            var ringingAudioCtx = null;
            var isRingingPlaying = false;
            var ringingCadenceTimer = null;
            var activeTwilioCallSid = null;

            function startBrowserRingingTone() {
                try {
                    stopBrowserRingingTone();
                    var AudioContextClass = window.AudioContext || window.webkitAudioContext;
                    if (!AudioContextClass) return;
                    ringingAudioCtx = new AudioContextClass();
                    isRingingPlaying = true;

                    function playDualRingPulse() {
                        if (!isRingingPlaying || !ringingAudioCtx) return;
                        try {
                            if (ringingAudioCtx.state === 'suspended') {
                                ringingAudioCtx.resume();
                            }
                            var now = ringingAudioCtx.currentTime;

                            // Standard Dual Frequency Phone Ring Cadence (440Hz + 480Hz)
                            var osc1 = ringingAudioCtx.createOscillator();
                            var osc2 = ringingAudioCtx.createOscillator();
                            var gain = ringingAudioCtx.createGain();

                            osc1.type = 'sine';
                            osc1.frequency.setValueAtTime(440, now);
                            osc2.type = 'sine';
                            osc2.frequency.setValueAtTime(480, now);

                            gain.gain.setValueAtTime(0.04, now);
                            gain.gain.exponentialRampToValueAtTime(0.001, now + 1.8);

                            osc1.connect(gain);
                            osc2.connect(gain);
                            gain.connect(ringingAudioCtx.destination);

                            osc1.start(now);
                            osc2.start(now);
                            osc1.stop(now + 2.0);
                            osc2.stop(now + 2.0);

                            // Next ring cycle in 4 seconds
                            ringingCadenceTimer = setTimeout(function() {
                                if (isRingingPlaying) {
                                    playDualRingPulse();
                                }
                            }, 4000);
                        } catch(e) {
                            console.warn('Ringing pulse error:', e);
                        }
                    }

                    playDualRingPulse();
                } catch(err) {
                    console.warn('Audio ringing error:', err);
                }
            }

            function stopBrowserRingingTone() {
                isRingingPlaying = false;
                if (ringingCadenceTimer) {
                    clearTimeout(ringingCadenceTimer);
                    ringingCadenceTimer = null;
                }
                if (ringingAudioCtx) {
                    try {
                        ringingAudioCtx.close();
                    } catch(e) {}
                    ringingAudioCtx = null;
                }
            }

            // Minimize / Expand Floating Softphone
            function toggleSoftphoneMinimize() {
                isSoftphoneMinimized = !isSoftphoneMinimized;
                if (isSoftphoneMinimized) {
                    $('#softphoneBody').slideUp(200);
                    $('#iconSoftphoneMinMax').removeClass('feather-minus').addClass('feather-maximize-2');
                    $('#crmFloatingSoftphoneWidget').css({'width': '240px'});
                } else {
                    $('#softphoneBody').slideDown(200);
                    $('#iconSoftphoneMinMax').removeClass('feather-maximize-2').addClass('feather-minus');
                    $('#crmFloatingSoftphoneWidget').css({'width': '320px'});
                }
            }

            $('#btnMinimizeSoftphone').on('click', function(e) {
                e.stopPropagation();
                toggleSoftphoneMinimize();
            });

            $('#softphoneHeaderBar').on('click', function() {
                if (isSoftphoneMinimized) {
                    toggleSoftphoneMinimize();
                }
            });

            // Close Softphone Floating Widget
            $('#btnCloseSoftphone').on('click', function(e) {
                e.stopPropagation();
                if ($('#dialerInCallPanel').is(':visible')) {
                    if (confirm('A call is currently in progress. Do you want to disconnect?')) {
                        $('#btnDisconnectAndAnalyze').trigger('click');
                    } else {
                        return;
                    }
                }
                stopBrowserRingingTone();
                stopActiveCallMedia();
                $('#crmFloatingSoftphoneWidget').fadeOut(250);
            });

            // Click-to-Call / Open Right-Side Mobile Softphone Dialer Trigger
            $(document).on('click', '.btn-crm-click-to-call', function(e) {
                e.preventDefault();
                var btn = $(this);
                activeCallLeadId = btn.attr('data-lead-id') || '{{ $lead->id }}';
                activeCallLeadName = btn.attr('data-lead-name') || @json($lead->contact_person ?: $lead->company_name);
                activeCallLeadCompany = btn.attr('data-lead-company') || @json($lead->company_name);
                var phoneToDial = btn.attr('data-lead-phone') || '';

                // Populate Clean Calling Info Header
                $('#dialerContactName').text(activeCallLeadName);
                $('#dialerContactSub').text(activeCallLeadCompany && activeCallLeadCompany !== activeCallLeadName ? activeCallLeadCompany : 'Direct Call');
                $('#dialerContactAvatar').text((activeCallLeadName.trim().charAt(0) || 'C').toUpperCase());

                // Set Phone Number directly in Screen Display
                $('#dialerDisplayNumber').val(phoneToDial);

                // Reset UI to Idle / Ready to Call State
                $('#dialerIdlePanel').show();
                $('#dialerInCallPanel').hide();
                $('#dialerStartAction').show();
                $('#dialerEndAction').hide();
                $('#softphoneStatusPill').text('Ready').removeClass('bg-danger bg-warning').addClass('bg-success');
                $('#dialerLiveTimer').text('00:00');
                $('#dialerCallNotes').val('');
                activeCallDurationSeconds = 0;
                audioChunks = [];
                activeTwilioCallSid = null;

                if (isSoftphoneMinimized) {
                    toggleSoftphoneMinimize();
                }
                $('#crmFloatingSoftphoneWidget').fadeIn(250);
            });

            // Dialpad Key Click
            $(document).on('click', '.dialpad-key', function() {
                var digit = $(this).attr('data-key');
                var cur = $('#dialerDisplayNumber').val();
                $('#dialerDisplayNumber').val(cur + digit);
            });

            $('#btnDialerBackspace').on('click', function() {
                var cur = $('#dialerDisplayNumber').val();
                if (cur.length > 0) {
                    $('#dialerDisplayNumber').val(cur.slice(0, -1));
                }
            });

            // Twilio WebRTC Device Initialization
            var twilioDevice = null;
            var activeTwilioCall = null;

            function setupTwilioVoiceDevice() {
                if (typeof Twilio === 'undefined' || !Twilio.Device) {
                    console.log('Twilio WebRTC Voice SDK not yet loaded.');
                    return;
                }

                fetch("{{ route('crm.twilio.voiceToken') }}")
                    .then(function(r) { return r.json(); })
                    .then(function(res) {
                        if (res.success && res.token) {
                            try {
                                twilioDevice = new Twilio.Device(res.token, {
                                    codecPreferences: ['opus', 'pcmu'],
                                    fakeLocalDTMF: true,
                                    enableRingingState: true
                                });

                                twilioDevice.on('registered', function() {
                                    console.log('Twilio 2-Way Voice Softphone registered and ready.');
                                    $('#softphoneStatusPill').text('Ready (2-Way)').addClass('bg-success');
                                });

                                twilioDevice.on('incoming', function(conn) {
                                    conn.accept();
                                });

                                twilioDevice.on('error', function(err) {
                                    console.warn('Twilio Device warning:', err);
                                });

                                twilioDevice.register();
                            } catch(e) {
                                console.warn('Twilio Device register error:', e);
                            }
                        }
                    })
                    .catch(function(err) {
                        console.warn('Twilio voice token fetch error:', err);
                    });
            }

            setTimeout(setupTwilioVoiceDevice, 500);

            function triggerRestOutboundCall(targetNumber) {
                var csrfToken = $('meta[name="csrf-token"]').attr('content');
                var initiateUrl = "{{ url('crm/leads') }}/" + activeCallLeadId + "/call-ai/initiate-call";

                fetch(initiateUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({ to_number: targetNumber })
                })
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    if (res.success) {
                        activeTwilioCallSid = res.call_sid || null;
                        $('#dialerLiveStatusTag').html('<i class="feather-phone-call me-1"></i> Phone Ringing: ' + (res.to || targetNumber));
                        $('#inCallSubtext').text('Twilio call connected to ' + (res.to || targetNumber) + '. Waiting for answer...');

                        if (activeTwilioCallSid) {
                            startTwilioCallStatusPolling(activeCallLeadId, activeTwilioCallSid);
                        }
                    } else {
                        console.warn('Twilio initiate status:', res.message);
                        $('#inCallSubtext').text(res.message || 'Call initiated via browser.');
                    }
                })
                .catch(function(err) {
                    console.error('Twilio initiate error:', err);
                    $('#inCallSubtext').text('Call in progress on browser.');
                });
            }

            // Start Real Outbound Call
            $('#btnStartRealCall').on('click', function() {
                var targetNumber = $('#dialerDisplayNumber').val().trim();
                if (!targetNumber) {
                    alert('Please enter or select a phone number to call.');
                    $('#dialerDisplayNumber').focus();
                    return;
                }

                if (!activeCallLeadId) {
                    alert('No active lead selected.');
                    return;
                }

                // 1. Play realistic phone ringing sound in browser
                startBrowserRingingTone();

                // 2. Switch UI to In-Call Panel
                $('#dialerIdlePanel').hide();
                $('#dialerInCallPanel').fadeIn(250);
                $('#dialerStartAction').hide();
                $('#dialerEndAction').show();
                $('#softphoneStatusPill').text('Calling...').removeClass('bg-success').addClass('bg-danger');
                $('#dialerLiveStatusTag').html('<i class="feather-phone-call me-1"></i> Ringing Mobile...');
                $('#inCallTargetDisplay').text(targetNumber + ' (' + activeCallLeadName + ')');
                $('#inCallSubtext').text('Connecting 2-way live audio with customer phone...');

                // 3. Start Live Timer
                activeCallDurationSeconds = 0;
                $('#dialerLiveTimer').text('00:00');
                if (activeCallInterval) clearInterval(activeCallInterval);
                activeCallInterval = setInterval(function() {
                    activeCallDurationSeconds++;
                    $('#dialerLiveTimer').text(formatCallTimer(activeCallDurationSeconds));
                }, 1000);

                // 4. Try Twilio WebRTC in-browser two-way calling first
                if (twilioDevice && twilioDevice.state === 'registered') {
                    try {
                        twilioDevice.connect({ params: { To: targetNumber, lead_id: activeCallLeadId } })
                            .then(function(call) {
                                activeTwilioCall = call;
                                activeTwilioCallSid = call.parameters.CallSid || null;

                                call.on('ringing', function() {
                                    $('#softphoneStatusPill').text('Ringing...').removeClass('bg-success').addClass('bg-danger');
                                    $('#dialerLiveStatusTag').html('<i class="feather-phone-call me-1"></i> Phone Ringing: ' + targetNumber);
                                    $('#inCallSubtext').text('Ringing mobile phone... Waiting for answer.');
                                });

                                call.on('accept', function() {
                                    stopBrowserRingingTone();
                                    $('#softphoneStatusPill').text('Connected (2-Way)').removeClass('bg-danger bg-warning').addClass('bg-success');
                                    $('#dialerLiveStatusTag').html('<i class="feather-phone-call me-1 text-success"></i> Two-Way Live Audio Connected');
                                    $('#inCallSubtext').text('Connected! Speak directly into your microphone.');
                                });

                                call.on('disconnect', function() {
                                    stopBrowserRingingTone();
                                    activeTwilioCall = null;
                                    $('#btnDisconnectAndAnalyze').trigger('click');
                                });

                                call.on('error', function(err) {
                                    console.warn('Twilio Call error:', err);
                                    triggerRestOutboundCall(targetNumber);
                                });
                            })
                            .catch(function(err) {
                                console.warn('WebRTC connect error, falling back to REST bridge:', err);
                                triggerRestOutboundCall(targetNumber);
                            });
                    } catch(e) {
                        triggerRestOutboundCall(targetNumber);
                    }
                } else {
                    triggerRestOutboundCall(targetNumber);
                }

                // 5. Start Audio MediaRecorder via Microphone for Gemini AI
                audioChunks = [];
                if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
                    navigator.mediaDevices.getUserMedia({ audio: true })
                        .then(function(stream) {
                            activeAudioStream = stream;
                            var options = { mimeType: 'audio/webm' };
                            if (!MediaRecorder.isTypeSupported('audio/webm')) {
                                options = { mimeType: 'audio/mp4' };
                                if (!MediaRecorder.isTypeSupported('audio/mp4')) {
                                    options = {};
                                }
                            }
                            try {
                                activeMediaRecorder = new MediaRecorder(stream, options);
                                activeMediaRecorder.ondataavailable = function(e) {
                                    if (e.data && e.data.size > 0) {
                                        audioChunks.push(e.data);
                                    }
                                };
                                activeMediaRecorder.start(250);
                                $('#liveSpeechStatus').text('Microphone active • Recording audio...');
                            } catch(recErr) {
                                console.warn('MediaRecorder error:', recErr);
                            }
                        })
                        .catch(function(err) {
                            console.warn('Microphone permission not granted:', err);
                            $('#liveSpeechStatus').text('Microphone not available • Taking text notes');
                        });
                }

                // 6. Start Speech Recognition if supported
                speechFinalTranscript = '';
                $('#dialerCallNotes').val('');
                if (speechRecognition) {
                    try {
                        isSpeechRecording = true;
                        speechRecognition.start();
                        $('#liveSpeechStatus').html('<span class="text-success"><i class="feather-mic"></i> Listening... Speak in Hindi or English</span>');
                    } catch(e) {
                        try { speechRecognition.stop(); } catch(err) {}
                        setTimeout(function() {
                            if (isSpeechRecording) {
                                try { speechRecognition.start(); } catch(err) {}
                            }
                        }, 150);
                    }
                }
            });

            // Twilio Polling
            var activeTwilioPollTimer = null;
            function startTwilioCallStatusPolling(leadId, callSid) {
                if (activeTwilioPollTimer) clearInterval(activeTwilioPollTimer);
                if (!callSid || !leadId) return;

                var pollUrl = "{{ url('crm/leads') }}/" + leadId + "/call-ai/status";
                var csrfToken = $('meta[name="csrf-token"]').attr('content');

                activeTwilioPollTimer = setInterval(function() {
                    if (!activeTwilioCallSid) {
                        clearInterval(activeTwilioPollTimer);
                        activeTwilioPollTimer = null;
                        return;
                    }

                    fetch(pollUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken
                        },
                        body: JSON.stringify({ call_sid: callSid })
                    })
                    .then(function(r) { return r.json(); })
                    .then(function(res) {
                        if (!res.success) return;
                        var st = (res.status || '').toLowerCase();

                        if (st === 'in-progress') {
                            stopBrowserRingingTone();
                            $('#softphoneStatusPill').text('Connected').removeClass('bg-danger bg-warning').addClass('bg-success');
                            $('#dialerLiveStatusTag').html('<i class="feather-phone-call me-1 text-success"></i> Connected • Speaking');
                            $('#inCallSubtext').text('Call connected! Speak directly with customer.');
                        } else if (st === 'busy' || st === 'no-answer' || st === 'canceled' || st === 'failed') {
                            stopBrowserRingingTone();
                            stopActiveCallMedia();
                            if (activeTwilioPollTimer) {
                                clearInterval(activeTwilioPollTimer);
                                activeTwilioPollTimer = null;
                            }
                            activeTwilioCallSid = null;

                            var reasonText = (st === 'busy' ? 'Call Rejected / Busy' : (st === 'no-answer' ? 'No Answer' : 'Call Ended / Declined'));
                            $('#softphoneStatusPill').text(reasonText).removeClass('bg-success').addClass('bg-danger');
                            $('#dialerLiveStatusTag').html('<i class="feather-phone-missed me-1 text-danger"></i> ' + reasonText);
                            $('#inCallSubtext').html('<span class="text-danger fw-bold">' + reasonText + '</span> • Logged in Lead Interactions.');

                            setTimeout(function() {
                                if (!$('#dialerStartAction').is(':visible')) {
                                    $('#dialerInCallPanel').hide();
                                    $('#dialerIdlePanel').fadeIn(200);
                                    $('#dialerStartAction').show();
                                    $('#dialerEndAction').hide();
                                    $('#softphoneStatusPill').text('Ready').removeClass('bg-danger bg-warning').addClass('bg-success');
                                }
                            }, 3500);
                        } else if (st === 'completed') {
                            stopBrowserRingingTone();
                            if (activeTwilioPollTimer) {
                                clearInterval(activeTwilioPollTimer);
                                activeTwilioPollTimer = null;
                            }
                            
                            if (activeCallDurationSeconds >= 3 || (res.duration && res.duration >= 3)) {
                                $('#btnDisconnectAndAnalyze').trigger('click');
                            } else {
                                stopActiveCallMedia();
                                activeTwilioCallSid = null;
                                $('#softphoneStatusPill').text('Call Disconnected').removeClass('bg-success').addClass('bg-warning');
                                $('#dialerLiveStatusTag').html('<i class="feather-phone-off me-1 text-warning"></i> Call Ended');
                                $('#inCallSubtext').text('Call disconnected by recipient.');

                                setTimeout(function() {
                                    $('#dialerInCallPanel').hide();
                                    $('#dialerIdlePanel').fadeIn(200);
                                    $('#dialerStartAction').show();
                                    $('#dialerEndAction').hide();
                                    $('#softphoneStatusPill').text('Ready').removeClass('bg-danger bg-warning').addClass('bg-success');
                                }, 3000);
                            }
                        }
                    })
                    .catch(function(e) {
                        console.warn('Twilio poll status error:', e);
                    });
                }, 1500);
            }

            function stopActiveCallMedia() {
                stopBrowserRingingTone();
                if (activeTwilioCall) {
                    try { activeTwilioCall.disconnect(); } catch(e) {}
                    activeTwilioCall = null;
                }
                if (activeTwilioPollTimer) {
                    clearInterval(activeTwilioPollTimer);
                    activeTwilioPollTimer = null;
                }
                if (activeCallInterval) {
                    clearInterval(activeCallInterval);
                    activeCallInterval = null;
                }
                if (speechRecognition && isSpeechRecording) {
                    isSpeechRecording = false;
                    try { speechRecognition.stop(); } catch(e) {}
                }
                if (activeMediaRecorder && activeMediaRecorder.state !== 'inactive') {
                    try { activeMediaRecorder.stop(); } catch(e) {}
                }
                if (activeAudioStream) {
                    try {
                        activeAudioStream.getTracks().forEach(function(track) { track.stop(); });
                    } catch(e) {}
                    activeAudioStream = null;
                }
            }

            // Disconnect Call & Analyze Audio / Transcript with Gemini AI
            $('#btnDisconnectAndAnalyze').on('click', function() {
                if (!activeCallLeadId) return;

                var currentCallSid = activeTwilioCallSid;
                var csrfToken = $('meta[name="csrf-token"]').attr('content');

                var terminateUrl = "{{ url('crm/leads') }}/" + activeCallLeadId + "/call-ai/terminate-call";
                fetch(terminateUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({ call_sid: currentCallSid || '' })
                }).catch(function(){});

                stopActiveCallMedia();
                activeTwilioCallSid = null;

                var notes = $('#dialerCallNotes').val().trim();
                var dialedPhone = $('#dialerDisplayNumber').val().trim();

                var btn = $(this);
                var origHtml = btn.html();
                btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Gemini AI Processing...');

                var analyzeUrl = "{{ url('crm/leads') }}/" + activeCallLeadId + "/call-ai/analyze";

                var formData = new FormData();
                formData.append('call_notes', notes);
                formData.append('duration_seconds', activeCallDurationSeconds || 60);
                formData.append('dialed_number', dialedPhone);
                if (currentCallSid) {
                    formData.append('call_sid', currentCallSid);
                }

                if (audioChunks && audioChunks.length > 0) {
                    var audioBlob = new Blob(audioChunks, { type: 'audio/webm' });
                    formData.append('audio_file', audioBlob, 'call_recording.webm');
                }

                fetch(analyzeUrl, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json'
                    },
                    body: formData
                })
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    btn.prop('disabled', false).html(origHtml);

                    if (res.success) {
                        $('#crmFloatingSoftphoneWidget').fadeOut(250);

                        $('#aiModalLeadId').val(activeCallLeadId);
                        $('#aiModalCallDuration').val(activeCallDurationSeconds);
                        $('#aiModalDialedPhoneVal').val(dialedPhone);
                        $('#aiModalRecordingUrl').val(res.recording_url || '');
                        $('#aiModalLeadName').text(activeCallLeadName + (activeCallLeadCompany ? ' (' + activeCallLeadCompany + ')' : ''));
                        $('#aiModalDialedNumber').text('Dialed: ' + (res.dialed_number || dialedPhone || 'Contact Number'));

                        if (res.recording_url) {
                            $('#aiModalAudioPreviewContainer').show();
                            var audioSrc = res.recording_url.startsWith('http') ? res.recording_url : ('/' + res.recording_url.replace(/^\/+/, ''));
                            $('#aiModalAudioPlayer').attr('src', audioSrc);
                        } else {
                            $('#aiModalAudioPreviewContainer').hide();
                            $('#aiModalAudioPlayer').removeAttr('src');
                        }

                        var sentiment = res.sentiment || 'Interested';
                        $('#aiModalSentimentBadge').text(sentiment);
                        if (sentiment.toLowerCase().indexOf('hot') > -1 || sentiment.toLowerCase().indexOf('positive') > -1) {
                            $('#aiModalSentimentBadge').attr('class', 'badge bg-soft-success text-success border border-success-subtle fs-11 fw-bold');
                        } else if (sentiment.toLowerCase().indexOf('cold') > -1 || sentiment.toLowerCase().indexOf('not interested') > -1) {
                            $('#aiModalSentimentBadge').attr('class', 'badge bg-soft-danger text-danger border border-danger-subtle fs-11 fw-bold');
                        } else {
                            $('#aiModalSentimentBadge').attr('class', 'badge bg-soft-warning text-warning border border-warning-subtle fs-11 fw-bold');
                        }

                        $('#aiModalTranscript').val(res.transcript || '');
                        $('#aiModalDiscussionSummary').val(res.discussion_summary || res.summary || '');
                        $('#aiModalNextActivityType').val(res.next_activity_type || 'Call');
                        $('#aiModalNextFollowupDate').val(res.next_followup_date || '');
                        $('#aiModalNextActivityTitle').val(res.next_activity_title || (res.summary === 'Not answering' ? 'Retry Call' : 'Follow-up Call'));
                        $('#aiModalLeadStatus').val(res.suggested_lead_status || 'Contacted');
                        $('#aiModalFollowupMessage').val(res.followup_message_preview || '');

                        setTimeout(function() {
                            var reviewModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('geminiAiCallReviewModal'));
                            reviewModal.show();
                        }, 300);
                    } else {
                        alert(res.message || 'Error processing AI call analysis.');
                    }
                })
                .catch(function(err) {
                    btn.prop('disabled', false).html(origHtml);
                    console.error('Call analysis error:', err);
                    alert('An error occurred during Gemini AI processing.');
                });
            });

            // Approve & Schedule Activity in CRM
            $('#btnApproveAndSchedule').on('click', function(e) {
                e.preventDefault();
                var leadId = $('#aiModalLeadId').val() || activeCallLeadId;
                if (!leadId) return;

                var payload = {
                    summary: $('#aiModalDiscussionSummary').val(),
                    transcript: $('#aiModalTranscript').val(),
                    next_activity_type: $('#aiModalNextActivityType').val(),
                    next_action: $('#aiModalNextActivityTitle').val() || $('#aiModalNextActivityType').val(),
                    next_followup_date: $('#aiModalNextFollowupDate').val(),
                    lead_status: $('#aiModalLeadStatus').val(),
                    sentiment: $('#aiModalSentimentBadge').text(),
                    followup_message_preview: $('#aiModalFollowupMessage').val(),
                    recording_url: $('#aiModalRecordingUrl').val() || null,
                    duration_seconds: parseInt($('#aiModalCallDuration').val()) || activeCallDurationSeconds
                };

                var btn = $(this);
                var origHtml = btn.html();
                btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Applying to CRM...');

                var csrfToken = $('meta[name="csrf-token"]').attr('content');
                var confirmUrl = "{{ url('crm/leads') }}/" + leadId + "/call-ai/confirm";

                fetch(confirmUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify(payload)
                })
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    btn.prop('disabled', false).html(origHtml);

                    if (res.success) {
                        var reviewModalEl = document.getElementById('geminiAiCallReviewModal');
                        var reviewModal = bootstrap.Modal.getInstance(reviewModalEl);
                        if (reviewModal) reviewModal.hide();

                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Call Saved & Activity Scheduled!',
                                html: '<p class="fs-12 text-muted mb-2">' + (res.message || 'Call logged & next follow-up scheduled successfully.') + '</p>' +
                                      (payload.next_followup_date ? '<div class="badge bg-soft-primary text-primary fs-11 p-2">📅 Next Follow-up: ' + payload.next_followup_date.replace('T', ' ') + '</div>' : ''),
                                confirmButtonColor: '#4f46e5',
                                confirmButtonText: 'Great, Done!'
                            }).then(function() {
                                window.location.reload();
                            });
                        } else {
                            alert(res.message || 'Call and follow-up saved successfully!');
                            window.location.reload();
                        }
                    } else {
                        alert(res.message || 'Error saving call follow-up.');
                    }
                })
                .catch(function(err) {
                    btn.prop('disabled', false).html(origHtml);
                    console.error('Save error:', err);
                    alert('An error occurred while saving the activity.');
                });
            });

            // Skip & Log Only
            $('#btnSkipAndLogOnly').on('click', function(e) {
                e.preventDefault();
                var leadId = $('#aiModalLeadId').val() || activeCallLeadId;
                if (!leadId) return;

                var payload = {
                    summary: $('#aiModalDiscussionSummary').val(),
                    transcript: $('#aiModalTranscript').val(),
                    next_activity_type: null,
                    next_action: null,
                    next_followup_date: null,
                    lead_status: $('#aiModalLeadStatus').val(),
                    sentiment: $('#aiModalSentimentBadge').text(),
                    followup_message_preview: null,
                    recording_url: $('#aiModalRecordingUrl').val() || null,
                    duration_seconds: parseInt($('#aiModalCallDuration').val()) || activeCallDurationSeconds
                };

                var csrfToken = $('meta[name="csrf-token"]').attr('content');
                var confirmUrl = "{{ url('crm/leads') }}/" + leadId + "/call-ai/confirm";

                fetch(confirmUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify(payload)
                })
                .then(function(r) { return r.json(); })
                .then(function(res) {
                    var reviewModalEl = document.getElementById('geminiAiCallReviewModal');
                    var reviewModal = bootstrap.Modal.getInstance(reviewModalEl);
                    if (reviewModal) reviewModal.hide();
                    window.location.reload();
                });
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

    <!-- Offcanvas Drawer: Edit Followup / Schedule Activity -->
    <div class="offcanvas offcanvas-end border-0 shadow-lg d-print-none" tabindex="-1" id="leadFollowupOffcanvas" aria-labelledby="leadFollowupOffcanvasLabel" style="width: 490px; max-width: 92vw;">
        <div class="offcanvas-header bg-light border-bottom py-3 px-4">
            <div class="d-flex align-items-center gap-2">
                <div class="avatar-text avatar-sm bg-soft-primary text-primary rounded-circle">
                    <i class="feather-calendar"></i>
                </div>
                <div>
                    <h5 class="offcanvas-title fw-bold text-dark fs-14 mb-0" id="leadFollowupOffcanvasTitle">{{ __('crm.edit_followup_for', ['company' => $lead->company_name]) }}</h5>
                    <span class="text-muted fs-11">{{ __('crm.log_interaction_next_followup') }}</span>
                </div>
            </div>
            <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        
        <div class="offcanvas-body p-4 bg-white">
            <form action="{{ route('crm.leads.followups.store', $lead->id) }}" method="POST" id="leadFollowupForm" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="action_mode" id="offcanvasActionMode" value="log_note">

                <!-- 2-Mode Switcher Tabs -->
                <div class="p-1 bg-light rounded-3 mb-4 d-flex gap-1 border">
                    <button type="button" class="btn btn-sm flex-fill fw-bold text-center border-0 offcanvas-mode-btn active btn-primary text-white shadow-sm" data-mode="log_note" style="font-size: 12px; padding: 8px 6px; background-color: var(--bs-primary); border-radius: 6px; transition: all 0.2s ease;">
                        {{ __('crm.log_discussion_next') }}
                    </button>
                    <button type="button" class="btn btn-sm flex-fill fw-bold text-center border-0 offcanvas-mode-btn" data-mode="schedule" style="font-size: 12px; padding: 8px 6px; color: #64748b; background-color: transparent; border-radius: 6px; transition: all 0.2s ease;">
                        {{ __('crm.direct_schedule_activity') }}
                    </button>
                </div>

                <!-- Past Interaction Section (Tab 1: Log Activity) -->
                <div id="sectionPastInteraction">
                    <x-ui.modal-form-ui type="select" name="type" id="offcanvasFollowupType" :label="__('crm.followup_interaction_type')" :searchable="true">
                        <option value="Call">{{ __('crm.interaction_types.Call') }}</option>
                        <option value="Email">{{ __('crm.interaction_types.Email') }}</option>
                        <option value="Meeting">{{ __('crm.interaction_types.Meeting') }}</option>
                        <option value="Demo">{{ __('crm.interaction_types.Demo') }}</option>
                        <option value="WhatsApp">{{ __('crm.activity_types.WhatsApp') }}</option>
                    </x-ui.modal-form-ui>

                    <x-ui.modal-form-ui type="select" name="status" id="offcanvasFollowupStatus" :label="__('crm.followup_status_outcome')" :searchable="true">
                        <option value="Connected">{{ __('crm.outcomes.Connected') }}</option>
                        <option value="Not Connected">{{ __('crm.outcomes.Not Connected') }}</option>
                        <option value="Not Answering">{{ __('crm.outcomes.Not Answering') }}</option>
                    </x-ui.modal-form-ui>

                    <x-ui.modal-form-ui type="textarea" name="notes" id="offcanvasNotes" :label="__('crm.notes_summary')" rows="3" :placeholder="__('crm.notes_summary_placeholder')" />

                    <!-- Next Follow-up Section inside Log Mode -->
                    <div class="border-top pt-3 mt-3">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <h6 class="fs-12 fw-bold text-dark mb-0">
                                <i class="feather-calendar text-primary me-1"></i> {{ __('crm.next_activity_schedule') }}
                            </h6>
                            <button type="button" class="btn btn-xs btn-outline-primary fw-bold px-2.5 py-1 rounded-pill d-inline-flex align-items-center gap-1" id="btnToggleNextScheduleShow">
                                <i class="feather-plus fs-11" id="iconToggleNextScheduleShow"></i>
                                <span id="textToggleNextScheduleShow">{{ __('crm.schedule_next_activity_btn') }}</span>
                            </button>
                        </div>
                        
                        <div id="containerNextScheduleFieldsShow" class="mt-3 p-3 bg-light rounded-3 border" style="display: none;">
                            <x-ui.modal-form-ui type="input" name="next_title" id="offcanvasNextTitle" :label="__('crm.next_activity_title')" :placeholder="__('crm.next_activity_title_placeholder')" value="" />

                            <div class="row g-2">
                                <div class="col-6">
                                    <x-ui.modal-form-ui type="select" name="next_activity_type" id="offcanvasNextActivityType" :label="__('crm.next_activity_type')" :searchable="true">
                                        <option value="Call">{{ __('crm.interaction_types.Call') }}</option>
                                        <option value="Meeting">{{ __('crm.interaction_types.Meeting') }}</option>
                                        <option value="Demo">{{ __('crm.interaction_types.Demo') }}</option>
                                        <option value="Email">{{ __('crm.interaction_types.Email') }}</option>
                                        <option value="WhatsApp">{{ __('crm.activity_types.WhatsApp') }}</option>
                                    </x-ui.modal-form-ui>
                                </div>
                                <div class="col-6">
                                    <x-ui.modal-form-ui type="select" name="next_duration_minutes" id="offcanvasNextDuration" :label="__('crm.duration_minutes')" :searchable="true">
                                        <option value="15">{{ __('crm.duration_options.15') }}</option>
                                        <option value="30" selected>{{ __('crm.duration_options.30') }}</option>
                                        <option value="45">{{ __('crm.duration_options.45') }}</option>
                                        <option value="60">{{ __('crm.duration_options.60') }}</option>
                                        <option value="90">{{ __('crm.duration_options.90') }}</option>
                                        <option value="120">{{ __('crm.duration_options.120') }}</option>
                                    </x-ui.modal-form-ui>
                                </div>
                            </div>

                            <x-ui.modal-form-ui type="input" inputType="datetime-local" name="next_followup_date" id="offcanvasNextFollowupDate" :label="__('crm.next_followup_datetime_optional')" />

                            <div class="p-3 my-3 bg-white rounded-3 border shadow-2xs">
                                <div class="row g-2">
                                    <div class="col-6">
                                        <div class="p-2 border rounded-2 bg-light d-flex align-items-center justify-content-between" style="min-height: 38px;">
                                            <label class="fw-bold fs-11 text-dark mb-0 pe-1 d-flex align-items-center gap-1 c-pointer" for="offcanvasNextSyncGoogle">
                                                <i class="feather-calendar text-danger"></i> {{ __('crm.google_calendar') }}
                                            </label>
                                            <input type="hidden" name="next_sync_google_calendar" value="0">
                                            <x-ui.checkbox name="next_sync_google_calendar" id="offcanvasNextSyncGoogle" value="1" />
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="p-2 border rounded-2 bg-light d-flex align-items-center justify-content-between" style="min-height: 38px;">
                                            <label class="fw-bold fs-11 text-dark mb-0 pe-1 d-flex align-items-center gap-1 c-pointer" for="offcanvasNextCreateMeet">
                                                <i class="feather-video text-primary"></i> {{ __('crm.google_meet_video') }}
                                            </label>
                                            <input type="hidden" name="next_create_meet_link" value="0">
                                            <x-ui.checkbox name="next_create_meet_link" id="offcanvasNextCreateMeet" value="1" />
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <x-ui.modal-form-ui type="input" name="next_guest_emails" id="offcanvasNextGuestEmails" :label="__('crm.guest_attendee_emails')" :placeholder="__('crm.guest_emails_placeholder')" />

                            <x-ui.modal-form-ui type="select" name="tagged_user_ids[]" id="offcanvasTagUser" :label="__('crm.tag_assign_persons')" multiple="true" :searchable="true">
                                @foreach($users as $u)
                                    <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                                @endforeach
                            </x-ui.modal-form-ui>
                        </div>
                    </div>
                </div>

                <!-- Direct Schedule Section (Tab 2: Schedule Activity) -->
                <div id="sectionDirectSchedule" style="display: none;">
                    <x-ui.modal-form-ui type="input" name="title" id="offcanvasEventTitle" :label="__('crm.event_meeting_title')" :placeholder="__('crm.event_title_placeholder')" value="" />

                    <x-ui.modal-form-ui type="select" name="schedule_type" id="offcanvasScheduleType" :label="__('crm.activity_type')" :searchable="true" onchange="$('#offcanvasFollowupType').val(this.value)">
                        <option value="Call">{{ __('crm.interaction_types.Call') }}</option>
                        <option value="Meeting">{{ __('crm.interaction_types.Meeting') }}</option>
                        <option value="Demo">{{ __('crm.interaction_types.Demo') }}</option>
                        <option value="Email">{{ __('crm.interaction_types.Email') }}</option>
                        <option value="WhatsApp">{{ __('crm.activity_types.WhatsApp') }}</option>
                    </x-ui.modal-form-ui>

                    <div class="row g-2">
                        <div class="col-6">
                            <x-ui.modal-form-ui type="input" inputType="datetime-local" name="followup_date" id="offcanvasFollowupDate" :label="__('crm.due_date_time')" value="" />
                        </div>
                        <div class="col-6">
                            <x-ui.modal-form-ui type="select" name="duration_minutes" id="offcanvasDuration" :label="__('crm.duration_minutes')" :searchable="true">
                                <option value="15">{{ __('crm.duration_options.15') }}</option>
                                <option value="30" selected>{{ __('crm.duration_options.30') }}</option>
                                <option value="45">{{ __('crm.duration_options.45') }}</option>
                                <option value="60">{{ __('crm.duration_options.60') }}</option>
                                <option value="90">{{ __('crm.duration_options.90') }}</option>
                                <option value="120">{{ __('crm.duration_options.120') }}</option>
                            </x-ui.modal-form-ui>
                        </div>
                    </div>

                    <div class="p-3 my-3 bg-white rounded-3 border shadow-2xs">
                        <div class="row g-2">
                            <div class="col-6">
                                <div class="p-2 border rounded-2 bg-light d-flex align-items-center justify-content-between" style="min-height: 38px;">
                                    <label class="fw-bold fs-11 text-dark mb-0 pe-1 d-flex align-items-center gap-1 c-pointer" for="offcanvasSyncGoogle">
                                        <i class="feather-calendar text-danger"></i> {{ __('crm.google_calendar') }}
                                    </label>
                                    <input type="hidden" name="sync_google_calendar" value="0">
                                    <x-ui.checkbox name="sync_google_calendar" id="offcanvasSyncGoogle" value="1" />
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-2 border rounded-2 bg-light d-flex align-items-center justify-content-between" style="min-height: 38px;">
                                    <label class="fw-bold fs-11 text-dark mb-0 pe-1 d-flex align-items-center gap-1 c-pointer" for="offcanvasCreateMeet">
                                        <i class="feather-video text-primary"></i> {{ __('crm.google_meet_video') }}
                                    </label>
                                    <input type="hidden" name="create_meet_link" value="0">
                                    <x-ui.checkbox name="create_meet_link" id="offcanvasCreateMeet" value="1" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <x-ui.modal-form-ui type="input" name="guest_emails" id="offcanvasGuestEmails" :label="__('crm.guest_attendee_emails')" :placeholder="__('crm.guest_emails_placeholder')" />

                    <x-ui.modal-form-ui type="textarea" name="schedule_notes" id="offcanvasScheduleNotes" :label="__('crm.description_plan')" rows="3" :placeholder="__('crm.agenda_plan_placeholder')" oninput="$('#offcanvasNotes').val(this.value)" />
                </div>

                <div class="d-flex align-items-center justify-content-end gap-2 border-top pt-3">
                    <button type="button" class="btn btn-light border px-4 py-2 fs-13 fw-bold text-uppercase" data-bs-dismiss="offcanvas">{{ __('crm.close') }}</button>
                    <button type="submit" class="btn btn-primary px-4 py-2 fs-13 fw-bold text-uppercase shadow-sm">{{ __('crm.save') }}</button>
                </div>
            </form>
        </div>
    </div>

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
                        
                        <x-ui.modal-form-ui type="textarea" name="rejection_reason" id="rejectionReasonInput" label="Rejection Reason / Remarks *" rows="4" placeholder="Enter reason for rejection (e.g., Price too high, Scope changed, Customer declined, etc.)..." required />
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
