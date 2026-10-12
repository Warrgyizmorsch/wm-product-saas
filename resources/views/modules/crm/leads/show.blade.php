@extends('layouts.duralux')

@section('title', ($lead->company_name ?: ($lead->contact_person ?: __('crm.lead_details'))) . ' | SaaS ERP')
@section('page-title', $lead->company_name ?: ($lead->contact_person ?: __('crm.lead_profile')))
@section('breadcrumb', 'CRM / ' . __('crm.leads') . ' / ' . ($lead->company_name ?: ($lead->contact_person ?: '#' . $lead->id)))

@section('page-back-button')
    <x-ui.button href="{{ route('crm.leads.index') }}" variant="light" size="xs" class="border shadow-2xs d-inline-flex align-items-center justify-content-center p-0" style="width: 28px; height: 28px;" icon="feather-arrow-left" title="{{ __('crm.back_to_leads') ?? 'Back to Leads' }}" />
@endsection



@section('page-actions')
    <style>
        /* ── Chevron / Arrow Status Pipeline (Odoo / ERP Style) ── */
        .so-status-pipeline {
            display: inline-flex;
            align-items: center;
            border-radius: 6px;
            overflow: hidden;
            border: 1px solid #cbd5e1;
            background-color: #f8fafc;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
        }
        .so-status-pipeline .pipeline-step {
            position: relative;
            padding: 6px 16px 6px 24px;
            background-color: #f8fafc;
            color: #64748b;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-flex;
            align-items: center;
            transition: all 0.2s ease;
            border: none;
            cursor: pointer;
            line-height: 1.2;
            white-space: nowrap;
        }
        .so-status-pipeline .pipeline-step:first-child {
            padding-left: 16px;
        }
        .so-status-pipeline .pipeline-step::after {
            content: "";
            position: absolute;
            top: 0;
            right: -10px;
            width: 0;
            height: 0;
            border-top: 14px solid transparent;
            border-bottom: 14px solid transparent;
            border-left: 10px solid #f8fafc;
            z-index: 10;
            transition: all 0.2s ease;
        }
        .so-status-pipeline .pipeline-step::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 0;
            height: 0;
            border-top: 14px solid transparent;
            border-bottom: 14px solid transparent;
            border-left: 10px solid #ffffff;
            z-index: 5;
            transition: all 0.2s ease;
        }
        .so-status-pipeline .pipeline-step:first-child::before {
            display: none;
        }
        .so-status-pipeline .pipeline-step.active {
            background-color: var(--bs-primary, #1e40af);
            color: #ffffff;
            z-index: 11;
        }
        .so-status-pipeline .pipeline-step.active::after {
            border-left-color: var(--bs-primary, #1e40af);
        }
        .so-status-pipeline .pipeline-step.completed {
            background-color: #e2e8f0;
            color: #334155;
            z-index: 6;
        }
        .so-status-pipeline .pipeline-step.completed::after {
            border-left-color: #e2e8f0;
        }
        .so-status-pipeline .pipeline-step:hover:not(.active) {
            background-color: #e2e8f0;
            color: #0f172a;
            z-index: 8;
        }
        .so-status-pipeline .pipeline-step:hover:not(.active)::after {
            border-left-color: #e2e8f0;
        }

        /* Dark mode support */
        html.app-skin-dark .so-status-pipeline {
            background-color: #0f172a !important;
            border-color: #1e293b !important;
        }
        html.app-skin-dark .so-status-pipeline .pipeline-step {
            background-color: #0f172a;
            color: #94a3b8;
        }
        html.app-skin-dark .so-status-pipeline .pipeline-step::after {
            border-left-color: #0f172a;
        }
        html.app-skin-dark .so-status-pipeline .pipeline-step::before {
            border-left-color: #1e293b;
        }
        html.app-skin-dark .so-status-pipeline .pipeline-step.active {
            background-color: var(--bs-primary, #1e40af) !important;
            color: #ffffff !important;
        }
        html.app-skin-dark .so-status-pipeline .pipeline-step.active::after {
            border-left-color: var(--bs-primary, #1e40af) !important;
        }
        html.app-skin-dark .so-status-pipeline .pipeline-step.completed {
            background-color: #1e293b;
            color: #cbd5e1;
        }
        html.app-skin-dark .so-status-pipeline .pipeline-step.completed::after {
            border-left-color: #1e293b;
        }
    </style>
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <!-- Chevron Arrow Pipeline Statusbar -->
        <div class="so-status-pipeline d-inline-flex align-items-center">
            @php
                $coreStatuses = ['New', 'Qualified', 'Dealing', 'Won', 'Lost'];
                $rawList = $leadStatuses ?? \App\Domains\CRM\Models\LeadStatus::getOrderedStatuses();
                $statusesList = collect($rawList)
                    ->filter(function($st) use ($coreStatuses, $lead) {
                        $name = is_string($st) ? $st : ($st->name ?? '');
                        if (stripos($name, 'audit') !== false) {
                            return false;
                        }
                        return in_array($name, $coreStatuses, true) || strcasecmp($name, $lead->status ?? '') === 0;
                    })
                    ->values();
                $currentStatus = $lead->status ?: 'New';
                $statusNames = $statusesList->map(fn($s) => strtolower(is_string($s) ? $s : ($s->name ?? '')))->values()->all();
                $currentIndex = array_search(strtolower($currentStatus), $statusNames);
            @endphp
            @foreach($statusesList as $index => $st)
                @php
                    $stName = is_string($st) ? $st : ($st->name ?? '');
                    $isCurrent = (strtolower($stName) === strtolower($currentStatus));
                    $isCompleted = ($currentIndex !== false && $index < $currentIndex);
                    $stepClass = '';
                    if ($isCurrent) {
                        $stepClass = 'active';
                    } elseif ($isCompleted) {
                        $stepClass = 'completed';
                    }
                @endphp
                <button type="button" 
                        class="pipeline-step {{ $stepClass }}" 
                        onclick="document.getElementById('statusChangeInput').value='{{ $stName }}'; document.getElementById('statusChangeForm').submit();"
                        title="{{ $stName }}">
                    {{ $stName }}
                </button>
            @endforeach
        </div>

        <!-- Pagination Arrows -->
        <div class="d-flex align-items-center border rounded px-1 py-0.5 bg-white shadow-2xs">
            @if(isset($prevLead) && $prevLead)
                <a href="{{ route('crm.leads.show', $prevLead->id) }}" class="btn btn-xs btn-link text-dark p-1 border-0 d-inline-flex align-items-center justify-content-center" title="{{ __('crm.previous_lead') }}">
                    <i class="feather-chevron-left fs-12"></i>
                </a>
            @else
                <button class="btn btn-xs btn-link p-1 border-0 d-inline-flex align-items-center justify-content-center text-muted opacity-50" style="cursor: not-allowed;" disabled>
                    <i class="feather-chevron-left fs-12"></i>
                </button>
            @endif

            @if(isset($nextLead) && $nextLead)
                <a href="{{ route('crm.leads.show', $nextLead->id) }}" class="btn btn-xs btn-link text-dark p-1 border-0 d-inline-flex align-items-center justify-content-center" title="{{ __('crm.next_lead') }}">
                    <i class="feather-chevron-right fs-12"></i>
                </a>
            @else
                <button class="btn btn-xs btn-link p-1 border-0 d-inline-flex align-items-center justify-content-center text-muted opacity-50" style="cursor: not-allowed;" disabled>
                    <i class="feather-chevron-right fs-12"></i>
                </button>
            @endif
        </div>
    </div>
@endsection

@section('content')
    @php
        $tenantSettings = is_array(tenant()?->settings) ? tenant()->settings : [];
        $isQuotationAutoApprove = ($tenantSettings['quotation_approval_policy'] ?? 'approval_required') === 'auto_approve';

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

        // 4. Past Activities (First 5 loaded initially from DB, rest via infinite scroll)
        $pastDoneActivities = $pastDoneActivities ?? collect();
        $pastDoneActivitiesCount = $pastActivitiesCount ?? $pastDoneActivities->count();

        $activeActionableCount = $plannedActivities->count() + $overdueActivities->count() + $todayDoneActivities->count();
        $allActivitiesTotalCount = $activeActionableCount + $pastDoneActivitiesCount;

        $groupedHistory = $lead->histories->groupBy(function($item) {
            return $item->created_at->format('d/m/Y');
        });
    @endphp
    <style>
        /* ==================== ODOO WORKSPACE & CHATTER STYLES ==================== */


        .zoho-lead-card-container {
            overflow: hidden !important;
        }
        .odoo-layout-wrapper {
            background-color: #ffffff !important;
            flex: 1 1 auto !important;
            min-height: 0 !important;
            height: 100% !important;
            overflow: hidden !important;
        }
        .odoo-sheet-col {
            background-color: #ffffff !important;
            padding: 0 !important;
            height: 100% !important;
            overflow-x: hidden !important;
            overflow-y: auto !important;
        }
        .odoo-chatter-col {
            overflow-x: hidden !important;
            overflow-y: auto !important;
        }
        .odoo-sheet-paper {
            box-shadow: none !important;
            border: none !important;
            border-radius: 0 !important;
            background-color: #ffffff !important;
            transition: all 0.2s ease;
        }

        .odoo-notebook-tabs .nav-link {
            color: #64748b;
            border: none;
            border-bottom: 2px solid transparent;
            border-radius: 0;
            padding: 8px 16px;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        .odoo-notebook-tabs .nav-link:hover {
            color: var(--bs-primary);
            border-bottom-color: rgba(30, 64, 175, 0.3);
        }
        .odoo-notebook-tabs .nav-link.active {
            color: var(--bs-primary) !important;
            font-weight: 700 !important;
            border-bottom: 2px solid var(--bs-primary) !important;
            background: transparent !important;
        }
        html.app-skin-dark .odoo-layout-wrapper,
        html.app-skin-dark .odoo-sheet-col {
            background-color: #0b1329 !important;
        }
        html.app-skin-dark .odoo-sheet-paper {
            background-color: #0f172a !important;
            border-color: #1e293b !important;
        }

        html.app-skin-dark .odoo-notebook-tabs {
            border-bottom-color: #1e293b !important;
        }
        html.app-skin-dark .odoo-notebook-tabs .nav-link {
            color: #94a3b8 !important;
        }
        html.app-skin-dark .odoo-notebook-tabs .nav-link:hover {
            color: #60a5fa !important;
        }
        html.app-skin-dark .odoo-notebook-tabs .nav-link.active {
            color: #60a5fa !important;
            border-bottom-color: #3b82f6 !important;
        }
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
        .odoo-feed-divider--past-done span {
            color: #64748b !important;
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

        /* Structured Activity Detail Rows (Light & Dark mode compatible) */
        .activity-detail-row {
            line-height: 1.5;
            color: #374151;
        }
        .activity-detail-key {
            font-weight: 500;
            color: #111827;
        }
        .activity-detail-val {
            color: #4b5563;
        }
        .activity-title-text {
            font-weight: 700;
            color: #111827;
        }

        .odoo-chatter-col {
            background-color: #ffffff;
        }

        /* Dark Mode */
        html.app-skin-dark .activity-detail-row {
            color: #cbd5e1 !important;
        }
        html.app-skin-dark .activity-detail-key {
            color: #f1f5f9 !important;
        }
        html.app-skin-dark .activity-detail-val {
            color: #cbd5e1 !important;
        }
        html.app-skin-dark .activity-title-text {
            color: #f8fafc !important;
        }
        html.app-skin-dark .odoo-activity-item .text-dark {
            color: #f8fafc !important;
        }
        html.app-skin-dark .odoo-activity-item .text-secondary {
            color: #94a3b8 !important;
        }
        html.app-skin-dark .odoo-chatter-col {
            background-color: #0b1329 !important;
        }
        html.app-skin-dark .odoo-chatter-col .bg-white {
            background-color: #0b1329 !important;
        }
        html.app-skin-dark .odoo-chatter-col .btn-white {
            background-color: #162038 !important;
            border-color: #1e293b !important;
            color: #e2e8f0 !important;
        }
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
        #btnLoadPastActivitiesInitial {
            transition: all 0.2s ease;
            background-color: #eff6ff !important;
            border: 1px solid #bfdbfe !important;
            color: #1d4ed8 !important;
        }
        #btnLoadPastActivitiesInitial:hover {
            background-color: #dbeafe !important;
            border-color: #93c5fd !important;
            color: #1e40af !important;
            transform: translateY(-1px);
            box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.15) !important;
        }
        html.app-skin-dark #btnLoadPastActivitiesInitial {
            background-color: rgba(30, 58, 138, 0.3) !important;
            border-color: rgba(59, 130, 246, 0.4) !important;
            color: #93c5fd !important;
        }
        html.app-skin-dark #btnLoadPastActivitiesInitial:hover {
            background-color: rgba(30, 58, 138, 0.55) !important;
            border-color: rgba(59, 130, 246, 0.6) !important;
            color: #ffffff !important;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.3) !important;
        }

        /* Odoo Chatter Navigation & Segmented Tabs */
        .odoo-chatter-nav-pills {
            background-color: #f1f5f9;
            padding: 2px !important;
            border-radius: 6px !important;
            display: inline-flex !important;
            flex-direction: row !important;
            flex-wrap: nowrap !important;
            gap: 2px !important;
            border: 1px solid #e2e8f0 !important;
        }
        .odoo-chatter-nav-pills .nav-link {
            color: #64748b !important;
            border-radius: 5px !important;
            padding: 4px 10px !important;
            font-size: 12px !important;
            font-weight: 600 !important;
            white-space: nowrap !important;
            border: none !important;
            background: transparent !important;
            transition: all 0.15s ease-in-out !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 6px !important;
        }
        .odoo-chatter-nav-pills .nav-link:hover {
            color: var(--bs-primary, #6337fa) !important;
            background-color: rgba(99, 55, 250, 0.08) !important;
        }
        .odoo-chatter-nav-pills .nav-link.active {
            background-color: var(--bs-primary, #6337fa) !important;
            color: #ffffff !important;
            box-shadow: 0 1px 3px rgba(99, 55, 250, 0.25) !important;
        }
        .odoo-chatter-nav-pills .nav-link.active .badge {
            background-color: rgba(255, 255, 255, 0.25) !important;
            color: #ffffff !important;
        }
        html.app-skin-dark .odoo-chatter-nav-pills {
            background-color: #0f172a !important;
            border-color: #1e293b !important;
        }
        html.app-skin-dark .odoo-chatter-nav-pills .nav-link {
            color: #94a3b8 !important;
        }
        html.app-skin-dark .odoo-chatter-nav-pills .nav-link.active {
            background-color: var(--bs-primary, #6337fa) !important;
            color: #ffffff !important;
        }
    </style>

    <!-- Hidden form for stage status updates via clickable/action triggers -->
    <form id="statusChangeForm" action="{{ route('crm.leads.updateStatus', $lead->id) }}" method="POST" style="display: none;">
        @csrf
        @method('PATCH')
        <input type="hidden" name="status" id="statusChangeInput">
    </form>

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

    <!-- Zoho CRM Layout Outer Card Container -->
    <div class="card border-0 shadow-sm bg-white d-flex flex-column zoho-lead-card-container d-print-block" style="height: calc(100vh - 195px); min-height: 550px; overflow: hidden; border-radius: 4px;">
        
        <!-- ==================== ODOO TWO-COLUMN WORKSPACE (SHEET ON LEFT, CHATTER ON RIGHT) ==================== -->
        <div class="d-flex flex-grow-1 overflow-hidden odoo-layout-wrapper" style="min-height: 0;">
            
            <!-- LEFT MAIN SHEET (Lead Overview + Horizontal Notebook Tabs) -->
            @include('modules.crm.leads.partials.odoo-sheet', [
                'lead' => $lead,
                'users' => $users,
                'products' => $products,
                'nextQuotationNumber' => $nextQuotationNumber,
                'leadStatuses' => $leadStatuses,
                'activeQuotation' => $activeQuotation,
                'statusClass' => $statusClass,
                'statusDisplayName' => $statusDisplayName,
                'prevLead' => $prevLead,
                'nextLead' => $nextLead
            ])

            <!-- RIGHT CHATTER BOX (Activity & History Tabs with Quick Actions) -->
            @include('modules.crm.leads.partials.chatter-box', [
                'lead' => $lead,
                'users' => $users,
                'allLeadFollowups' => $allLeadFollowups,
                'plannedActivities' => $plannedActivities,
                'overdueActivities' => $overdueActivities,
                'todayDoneActivities' => $todayDoneActivities,
                'pastDoneActivities' => $pastDoneActivities,
                'pastDoneActivitiesCount' => $pastDoneActivitiesCount,
                'allActivitiesTotalCount' => $allActivitiesTotalCount,
                'groupedHistory' => $groupedHistory
            ])

        </div> <!-- Closes odoo-layout-wrapper -->
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

        /* ==================== MODERN HISTORY TIMELINE STYLES ==================== */
        .history-timeline-main-wrapper {
            position: relative;
            padding-left: 2px;
        }
        .history-timeline-item {
            position: relative;
            padding-left: 38px;
            margin-bottom: 12px;
        }
        .history-timeline-track {
            position: absolute;
            left: 13px;
            top: 26px;
            bottom: -14px;
            width: 2px;
            background: #e2e8f0;
            z-index: 1;
        }
        .history-timeline-item:last-child .history-timeline-track {
            display: none;
        }
        .history-timeline-node {
            position: absolute;
            left: 0;
            top: 2px;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: #ffffff;
            border: 2px solid #cbd5e1;
            z-index: 2;
            transition: transform 0.18s cubic-bezier(0.16, 1, 0.3, 1), box-shadow 0.18s ease;
            box-shadow: 0 1px 3px rgba(0,0,0,0.06);
        }
        .history-timeline-item:hover .history-timeline-node {
            transform: scale(1.1);
            box-shadow: 0 2px 6px rgba(0,0,0,0.12);
        }
        .history-card {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            background: #ffffff;
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .history-card:hover {
            border-color: #cbd5e1;
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.06);
            transform: translateY(-1px);
        }
        .history-bubble {
            background: #f8fafc;
            border-color: #e2e8f0 !important;
        }

        /* Node Color Themes */
        .node-purple { background: #faf5ff !important; border-color: #c084fc !important; color: #9333ea !important; }
        .node-teal { background: #f0fdfa !important; border-color: #5eead4 !important; color: #0d9488 !important; }
        .node-success { background: #f0fdf4 !important; border-color: #86efac !important; color: #16a34a !important; }
        .node-primary { background: #eff6ff !important; border-color: #93c5fd !important; color: #2563eb !important; }
        .node-indigo { background: #eef2ff !important; border-color: #a5b4fc !important; color: #4f46e5 !important; }
        .node-warning { background: #fffbeb !important; border-color: #fcd34d !important; color: #d97706 !important; }
        .node-danger { background: #fff1f2 !important; border-color: #fda4af !important; color: #e11d48 !important; }
        .node-cyan { background: #ecfeff !important; border-color: #67e8f9 !important; color: #0891b2 !important; }
        .node-info { background: #f0f9ff !important; border-color: #7dd3fc !important; color: #0284c7 !important; }

        /* Dark mode overrides */
        html.app-skin-dark .history-card {
            background: #1e293b !important;
            border-color: #334155 !important;
        }
        html.app-skin-dark .history-card:hover {
            border-color: #475569 !important;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.3) !important;
        }
        html.app-skin-dark .history-bubble {
            background: #0f172a !important;
            border-color: #334155 !important;
        }
        html.app-skin-dark .history-timeline-track {
            background: #334155 !important;
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

            // Initialize product-row-select dropdowns in edit lead form
            if ($('.product-row-select').length) {
                $('.product-row-select').select2({
                    theme: "bootstrap-5",
                    width: "100%"
                });
            }

            window.toggleLeadType = function(type) {
                var isB2B = (type === 'b2b');

                var companyFieldNames = ['company_name', 'gstin', 'company_email', 'company_phone'];
                companyFieldNames.forEach(function(name) {
                    var input = document.querySelector('[name="' + name + '"]');
                    if (input) {
                        var group = input.closest('.odoo-form-group') || input.closest('.mb-3') || input.parentElement;
                        if (group) {
                            if (isB2B) {
                                group.style.setProperty('display', 'flex', 'important');
                            } else {
                                group.style.setProperty('display', 'none', 'important');
                            }
                        }
                    }
                });

                ['company_name', 'company_email'].forEach(function(fieldName) {
                    var inputEl = document.querySelector('[name="' + fieldName + '"]');
                    if (inputEl) {
                        var labelEl = inputEl.closest('.odoo-form-group')?.querySelector('.odoo-form-label');
                        if (isB2B) {
                            inputEl.setAttribute('required', 'required');
                            if (labelEl && !labelEl.querySelector('.text-danger')) {
                                labelEl.innerHTML = labelEl.innerHTML.trim() + ' <span class="text-danger">*</span>';
                            }
                        } else {
                            inputEl.removeAttribute('required');
                            if (labelEl) {
                                var star = labelEl.querySelector('.text-danger');
                                if (star) star.remove();
                            }
                        }
                    }
                });

                ['contact_person', 'email'].forEach(function(fieldName) {
                    var inputEl = document.querySelector('[name="' + fieldName + '"]');
                    if (inputEl) {
                        var labelEl = inputEl.closest('.odoo-form-group')?.querySelector('.odoo-form-label');
                        if (!isB2B) {
                            inputEl.setAttribute('required', 'required');
                            if (labelEl && !labelEl.querySelector('.text-danger')) {
                                labelEl.innerHTML = labelEl.innerHTML.trim() + ' <span class="text-danger">*</span>';
                            }
                        } else {
                            inputEl.removeAttribute('required');
                            if (labelEl) {
                                var star = labelEl.querySelector('.text-danger');
                                if (star) star.remove();
                            }
                        }
                    }
                });
            };

            $(document).on('change click', 'input[name="lead_type"]', function() {
                window.toggleLeadType($(this).val());
            });

            if ($('input[name="lead_type"]').length) {
                var initialLeadType = $('input[name="lead_type"]:checked').val() || 'b2b';
                window.toggleLeadType(initialLeadType);
            }

            function updateProductRemoveButtonsState() {
                let rowCount = $('#productItemsBody tr').length;
                if (rowCount <= 1) {
                    $('#productItemsBody tr .remove-product-row-btn').css({'opacity': '0.4', 'cursor': 'not-allowed'});
                } else {
                    $('#productItemsBody tr .remove-product-row-btn').css({'opacity': '0.75', 'cursor': 'pointer'});
                }
            }
            updateProductRemoveButtonsState();

            let itemRowIndex = $('#productItemsBody tr').length || 1;

            $('#addProductRowBtn').on('click', function () {
                let templateHtml = $('#productRowSelectTemplate').html();
                let newSelect = $(templateHtml);
                newSelect.attr('name', 'items[' + itemRowIndex + '][product_id]');

                let newRow = $(`
                    <tr class="lead-item-row border-bottom">
                        <td class="py-1 ps-1 pe-1 align-top"></td>
                        <td class="py-1 px-1 align-top">
                            <input type="text" inputmode="decimal" autocomplete="off" name="items[${itemRowIndex}][quantity]" class="form-control form-control-sm text-center qty-row-input" value="1">
                        </td>
                        <td class="py-1 text-center align-top pt-2">
                            <button type="button" class="btn btn-link text-danger p-0 opacity-75 remove-product-row-btn" title="Remove Product">
                                <i class="feather-trash-2 fs-13"></i>
                            </button>
                        </td>
                    </tr>
                `);

                newRow.find('td:first-child').append(newSelect);
                $('#productItemsBody').append(newRow);

                newSelect.select2({
                    theme: "bootstrap-5",
                    width: "100%"
                });

                itemRowIndex++;
                updateProductRemoveButtonsState();
                calculateAutoExpectedRevenue();
            });

            $(document).on('click', '#productItemsBody .remove-product-row-btn', function (e) {
                e.preventDefault();
                if ($('#productItemsBody tr').length > 1) {
                    $(this).closest('tr').remove();
                    updateProductRemoveButtonsState();
                    calculateAutoExpectedRevenue();
                }
            });

            $(document).on('keydown', '.qty-row-input', function (e) {
                if (['-', '+', 'e', 'E'].includes(e.key)) {
                    e.preventDefault();
                }
            });

            $(document).on('input', '.qty-row-input', function () {
                let rawVal = $(this).val();
                if (rawVal) {
                    let cleanVal = rawVal.replace(/[^0-9.]/g, '');
                    let parts = cleanVal.split('.');
                    if (parts.length > 2) {
                        cleanVal = parts[0] + '.' + parts.slice(1).join('');
                    }
                    if (cleanVal !== rawVal) {
                        $(this).val(cleanVal);
                    }
                }
            });

            function calculateAutoExpectedRevenue() {
                let grandTotal = 0;
                let hasProductSelected = false;

                $('#productItemsBody tr.lead-item-row').each(function() {
                    let select = $(this).find('select.product-row-select');
                    let qtyInput = $(this).find('input.qty-row-input');
                    let selectedOpt = select.find('option:selected');
                    let price = parseFloat(selectedOpt.attr('data-price')) || 0;
                    let qty = parseFloat(qtyInput.val()) || 0;

                    if (selectedOpt.val() && selectedOpt.val() !== '__ADD_NEW__') {
                        hasProductSelected = true;
                        grandTotal += (price * qty);
                    }
                });

                let revenueInput = $('input[name="expected_amount"]');
                if (revenueInput.length && hasProductSelected) {
                    revenueInput.val(grandTotal > 0 ? grandTotal.toFixed(2) : '0.00');
                }
            }

            $(document).on('change change.select2 select2:select', 'select.product-row-select', function() {
                calculateAutoExpectedRevenue();
            });

            $(document).on('input change keyup', 'input.qty-row-input', function() {
                calculateAutoExpectedRevenue();
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



    {{-- Product quick-create modal --}}
    <x-ui.master-modals :masters="['product']" />
@endpush
