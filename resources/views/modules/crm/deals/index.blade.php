@extends('layouts.duralux')

@section('title', __('crm.deals') . ' | SaaS ERP')
@section('page-title', __('crm.deals_pipeline'))
@section('breadcrumb', __('crm.deals'))

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <x-ui.import-export-dropdown 
            type="deals" 
            :can-import="false" 
            :can-download-template="false" 
            export-route="{{ route('crm.deals.export') }}" />
        <x-ui.button href="{{ route('crm.deals.create') }}" variant="primary" icon="feather-plus">
            {{ __('crm.new_deal') }}
        </x-ui.button>
    </div>
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('assets/vendors/css/sweetalert2.min.css') }}">
<style>
    /* Zoho/Odoo CRM Status Filter Tabs */
    .crm-status-tabs-wrapper {
        display: flex;
        align-items: center;
        border-bottom: 2px solid #e2e8f0;
        background-color: transparent;
        padding-left: 0;
        padding-right: 0;
        overflow-x: auto;
        scrollbar-width: none; /* Hide scrollbar Firefox */
        -ms-overflow-style: none;  /* IE/Edge */
    }
    .crm-status-tabs-wrapper::-webkit-scrollbar {
        display: none; /* Hide scrollbar Chrome/Safari */
    }
    .crm-status-tab {
        display: inline-flex;
        align-items: center;
        padding: 10px 16px;
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.3px;
        color: #64748b;
        text-decoration: none;
        white-space: nowrap;
        border-bottom: 3px solid transparent;
        margin-bottom: -2px;
        transition: all 0.2s ease;
    }
    .crm-status-tab:hover {
        color: var(--bs-primary);
        background-color: color-mix(in srgb, var(--bs-primary) 6%, transparent);
    }
    .crm-status-tab.active {
        color: var(--bs-primary);
        border-bottom-color: var(--bs-primary);
        background-color: color-mix(in srgb, var(--bs-primary) 8%, transparent);
    }
    .crm-status-tab--won.active {
        color: #10b981;
        border-bottom-color: #10b981;
        background-color: rgba(16, 185, 129, 0.08);
    }
    .crm-status-tab--lost.active {
        color: #ef4444;
        border-bottom-color: #ef4444;
        background-color: rgba(239, 68, 68, 0.08);
    }

    .table-deal-row:hover {
        background-color: #f8fafc;
    }
    html.app-skin-dark .table-deal-row:hover {
        background-color: #162038 !important;
    }
    .deal-row-selected {
        background-color: rgba(30, 64, 175, 0.05) !important;
    }
    .cursor-pointer {
        cursor: pointer;
    }

    /* Hide scrollbars on responsive table container */
    .table-responsive {
        overflow-x: auto;
        scrollbar-width: none;
        -ms-overflow-style: none;
    }
    .table-responsive::-webkit-scrollbar {
        display: none;
    }

    /* Select2 status selector matching Lead listing */
    .select2-container--bootstrap-5 .select2-selection--single {
        padding: 2px 8px;
        height: auto;
        font-size: 11px;
        font-weight: 600;
    }
    .status-select + .select2-container {
        min-width: 140px !important;
        width: 140px !important;
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
        background: #f1f5f9 !important;
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
    [data-bs-theme="dark"] .crm-softphone-body,
    html.app-skin-dark .crm-softphone-body {
        background: #0f172a !important;
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
        color: #64748b;
    }
    #btnDialerBackspace:hover {
        color: #ef4444 !important;
        background-color: rgba(239, 68, 68, 0.12) !important;
    }
    #btnDialerBackspace:active {
        transform: scale(0.92);
    }
    [data-bs-theme="dark"] #btnDialerBackspace,
    html.app-skin-dark #btnDialerBackspace {
        color: #94a3b8 !important;
    }
    [data-bs-theme="dark"] #btnDialerBackspace:hover,
    html.app-skin-dark #btnDialerBackspace:hover {
        color: #f87171 !important;
        background-color: rgba(239, 68, 68, 0.22) !important;
    }
    #btnCloseSoftphone:hover {
        color: #ef4444 !important;
        background-color: rgba(239, 68, 68, 0.25) !important;
    }
    #btnMinimizeSoftphone:hover {
        color: #ffffff !important;
        background-color: rgba(255, 255, 255, 0.18) !important;
    }

    /* Custom Tooltip Popup Styling (Solid, High Contrast, Dark & Light Mode) */
    .tooltip.show {
        opacity: 1 !important;
    }
    .custom-white-tooltip {
        opacity: 1 !important;
        z-index: 1080 !important;
    }
    .custom-white-tooltip .tooltip-inner {
        background-color: #ffffff !important;
        color: #0f172a !important;
        border: 1px solid #cbd5e1 !important;
        box-shadow: 0 16px 36px -4px rgba(15, 23, 42, 0.18), 0 6px 14px -2px rgba(15, 23, 42, 0.08) !important;
        padding: 12px 14px !important;
        border-radius: 10px !important;
        max-width: 330px !important;
        text-align: left !important;
        line-height: 1.45 !important;
        opacity: 1 !important;
        font-family: inherit !important;
    }
    .custom-white-tooltip .crm-tooltip-note {
        background-color: #f8fafc !important;
        border-color: #e2e8f0 !important;
    }
    .custom-white-tooltip.bs-tooltip-top .tooltip-arrow::before {
        border-top-color: #cbd5e1 !important;
    }
    .custom-white-tooltip.bs-tooltip-bottom .tooltip-arrow::before {
        border-bottom-color: #cbd5e1 !important;
    }
    .custom-white-tooltip.bs-tooltip-start .tooltip-arrow::before {
        border-left-color: #cbd5e1 !important;
    }
    .custom-white-tooltip.bs-tooltip-end .tooltip-arrow::before {
        border-right-color: #cbd5e1 !important;
    }

    /* Dark Mode Tooltip Support */
    [data-bs-theme="dark"] .custom-white-tooltip .tooltip-inner,
    html.app-skin-dark .custom-white-tooltip .tooltip-inner {
        background-color: #1e293b !important;
        color: #f8fafc !important;
        border: 1px solid #334155 !important;
        box-shadow: 0 20px 40px -4px rgba(0, 0, 0, 0.7), 0 8px 16px -2px rgba(0, 0, 0, 0.5) !important;
    }
    [data-bs-theme="dark"] .custom-white-tooltip .text-dark,
    html.app-skin-dark .custom-white-tooltip .text-dark {
        color: #f8fafc !important;
    }
    [data-bs-theme="dark"] .custom-white-tooltip .text-muted,
    html.app-skin-dark .custom-white-tooltip .text-muted {
        color: #94a3b8 !important;
    }
    [data-bs-theme="dark"] .custom-white-tooltip .bg-light,
    [data-bs-theme="dark"] .custom-white-tooltip .crm-tooltip-note,
    html.app-skin-dark .custom-white-tooltip .bg-light,
    html.app-skin-dark .custom-white-tooltip .crm-tooltip-note {
        background-color: #0f172a !important;
        border-color: #334155 !important;
        color: #e2e8f0 !important;
    }
    [data-bs-theme="dark"] .custom-white-tooltip .border-light-subtle,
    html.app-skin-dark .custom-white-tooltip .border-light-subtle {
        border-color: #334155 !important;
    }
    [data-bs-theme="dark"] .custom-white-tooltip.bs-tooltip-top .tooltip-arrow::before,
    html.app-skin-dark .custom-white-tooltip.bs-tooltip-top .tooltip-arrow::before {
        border-top-color: #334155 !important;
    }
    [data-bs-theme="dark"] .custom-white-tooltip.bs-tooltip-bottom .tooltip-arrow::before,
    html.app-skin-dark .custom-white-tooltip.bs-tooltip-bottom .tooltip-arrow::before {
        border-bottom-color: #334155 !important;
    }
</style>
@endpush

@section('content')

    @php
        $sortBy = request('sort_by', 'id');
        $sortOrder = request('sort_order', 'desc');
    @endphp

    <div class="erp-single-panel">
        @if ($errors->any())
            <div class="alert alert-danger mb-3 alert-dismissible fade show fs-12 py-2" role="alert">
                <ul class="mb-0 ps-3 text-start">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" style="padding: 0.75rem 1rem;"></button>
            </div>
        @endif

        {{-- 1. Header: Title & Actions --}}
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <h5 class="fw-bold text-dark mb-0">{{ __('crm.deals_listing') }}</h5>
            
            <div class="d-flex align-items-center flex-wrap gap-2">
                <!-- Normal Toolbar (Search, View Switcher, Sort, Filter) -->
                <div id="normal-toolbar" class="d-flex align-items-center flex-wrap gap-2">
                    <!-- Outside Search Box (HRMS Style) -->
                    <form method="GET" action="{{ route('crm.deals.index') }}" class="d-flex align-items-center bg-light border rounded px-2.5 py-0.5 me-1" style="height: 34px; min-width: 240px;">
                        @foreach(request()->except(['search', 'page']) as $k => $v)
                            @if(is_scalar($v) && $v !== '')
                                <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                            @endif
                        @endforeach
                        <i class="feather-search text-muted me-2" style="font-size: 13px;"></i>
                        <input type="text" name="search" class="form-control border-0 bg-transparent p-0 fs-12 text-dark" placeholder="{{ __('crm.search_placeholder_deals') }}" value="{{ request('search') }}" style="box-shadow: none; outline: none;">
                        @if(request('search'))
                            <a href="{{ route('crm.deals.index', request()->except(['search', 'page'])) }}" class="text-muted text-decoration-none ms-1" title="Clear Search">
                                <i class="feather-x fs-12"></i>
                            </a>
                        @endif
                    </form>

                    <x-ui.view-switcher />

                    <x-ui.sort-dropdown :label="__('crm.sort')">
                        <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'id', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'id' && $sortOrder === 'desc' ? 'active' : '' }}">
                            <span>{{ __('crm.sort_latest_deals') }}</span>
                        </a>
                        <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'title', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'title' && $sortOrder === 'asc' ? 'active' : '' }}">
                            <span>{{ __('crm.sort_title_az') }}</span>
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'estimated_value', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'estimated_value' && $sortOrder === 'desc' ? 'active' : '' }}">
                            <span>{{ __('crm.sort_highest_deal_value') }}</span>
                        </a>
                    </x-ui.sort-dropdown>

                    <form method="GET" action="{{ route('crm.deals.index') }}" class="d-inline">
                        <x-ui.filter :label="__('crm.filter')" offset="0, 5">
                            <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('crm.filter_options') }}</h6>
                            <div class="mb-3">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('crm.search_keywords') }}</label>
                                <x-ui.odoo-form-ui type="input" name="search" :placeholder="__('crm.search_placeholder_deals')" value="{{ request('search') }}" />
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('crm.pipeline_stage') }}</label>
                                <x-ui.odoo-form-ui type="select" name="stage">
                                    <option value="">{{ __('crm.all_stages') }}</option>
                                    @foreach($dealStatuses as $st)
                                        <option value="{{ $st->name }}" {{ request('stage') === $st->name ? 'selected' : '' }}>{{ $st->name }}</option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </div>
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('crm.date_from') }}</label>
                                    <x-ui.odoo-form-ui type="input" inputType="date" name="date_from" value="{{ request('date_from') }}" />
                                </div>
                                <div class="col-6">
                                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('crm.date_to') }}</label>
                                    <x-ui.odoo-form-ui type="input" inputType="date" name="date_to" value="{{ request('date_to') }}" />
                                </div>
                            </div>
                            <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                                <a href="{{ route('crm.deals.index') }}" class="btn btn-xs btn-light border">{{ __('crm.reset') }}</a>
                                <button type="submit" class="btn btn-xs btn-primary">{{ __('crm.apply_filters') }}</button>
                            </div>
                        </x-ui.filter>
                    </form>
                </div>

                <!-- Bulk Actions Toolbar (initially hidden, shows when checkboxes are selected) -->
                <div id="bulk-actions-toolbar" class="d-flex gap-2 d-none">
                    <x-ui.bulk-actions :label="__('crm.selected_actions') . ' (0)'" id="bulk-actions-dropdown">
                        <button type="button" class="dropdown-item text-primary" onclick="openBulkAssignDrawer()">
                            <i class="feather-user-check me-2 text-primary"></i> {{ __('crm.assign_to_sales_rep') }}
                        </button>
                        <div class="dropdown-divider"></div>
                        <button type="button" class="dropdown-item text-secondary" onclick="clearDealSelections()">
                            <i class="feather-x me-2 text-secondary"></i> {{ __('crm.deselect') }}
                        </button>
                    </x-ui.bulk-actions>
                </div>
            </div>
        </div>

        {{-- 2. Stage Filter Tabs --}}
        <div class="mb-2" style="border-bottom: 2px solid #e2e8f0;">
            <div class="crm-status-tabs-wrapper">
                <a href="{{ request()->fullUrlWithQuery(['stage' => null, 'page' => null]) }}" class="crm-status-tab {{ empty($stage) ? 'active' : '' }}">
                    {{ __('crm.tabs.all') }} ({{ $stageCounts['all'] ?? 0 }})
                </a>
                @foreach($dealStatuses as $st)
                    @php
                        $tabClass = match(strtolower($st->name)) {
                            'won', 'closed won' => 'crm-status-tab--won',
                            'lost', 'closed lost' => 'crm-status-tab--lost',
                            default => '',
                        };
                        $stageLabel = __('crm.stages.' . $st->name);
                        if ($stageLabel === 'crm.stages.' . $st->name) {
                            $stageLabel = $st->name;
                        }
                    @endphp
                    <a href="{{ request()->fullUrlWithQuery(['stage' => $st->name, 'page' => null]) }}" class="crm-status-tab {{ $tabClass }} {{ $stage === $st->name ? 'active' : '' }}">
                        {{ mb_strtoupper($stageLabel) }} ({{ $stageCounts[$st->name] ?? 0 }})
                    </a>
                @endforeach
            </div>
        </div>

        {{-- 3. Data Table (Common UI Element like Lead Listing) --}}
        <div class="table-responsive">
            <x-ui.odoo-form-ui type="table" id="dealsTable" class="mb-0">
                <thead>
                    <tr>
                        <th style="width: 35px;" class="text-center">
                            <input type="checkbox" class="form-check-input" id="selectAllDealsCheckbox" title="Select All Deals">
                        </th>
                        <th style="width: 10%;">{{ __('crm.deal_no') }}</th>
                        <th style="width: 16%;">{{ __('crm.project_deal_title') }}</th>
                        <th style="width: 14%;">{{ __('crm.account_client') }}</th>
                        <th style="width: 13%;">{{ __('crm.deal_owner') }}</th>
                        <th style="width: 13%;">{{ __('crm.phone_email') }}</th>
                        <th style="width: 10%;" class="text-end pe-3">{{ __('crm.est_value') }} ({{ active_currency_symbol() }})</th>
                        <th style="width: 10%;">{{ __('crm.closing_date_status') }}</th>
                        <th style="width: 8%;">{{ __('crm.stage') }}</th>
                        <th style="width: 6%;">{{ __('crm.health_percent') }}</th>
                        <th style="width: 4%;" class="text-end pe-3">{{ __('crm.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($deals as $deal)
                        @php
                            $normalizedStage = $deal->stage;
                            if ($normalizedStage === 'Closed Won') $normalizedStage = 'Won';
                            if ($normalizedStage === 'Closed Lost') $normalizedStage = 'Lost';
                            if ($normalizedStage === 'New') $normalizedStage = 'Qualification';
                            if ($normalizedStage === 'Qualified') $normalizedStage = 'Needs Analysis';

                            $stageColors = [
                                'Qualification'  => 'info',
                                'Needs Analysis' => 'primary',
                                'Proposal'       => 'warning',
                                'Negotiation'    => 'purple',
                                'Won'            => 'success',
                                'Lost'           => 'danger',
                            ];
                            $badgeColor = $stageColors[$normalizedStage] ?? 'secondary';

                            // Check closing date status
                            $isOverdue = false;
                            $isClosingToday = false;
                            if ($deal->closing_date && !in_array($normalizedStage, ['Won', 'Lost'])) {
                                $closingDate = \Illuminate\Support\Carbon::parse($deal->closing_date)->startOfDay();
                                $today = \Illuminate\Support\Carbon::today();
                                if ($closingDate->isToday()) {
                                    $isClosingToday = true;
                                } elseif ($closingDate->isPast()) {
                                    $isOverdue = true;
                                }
                            }

                            $dLead = $deal->lead;
                            $companyName = $deal->account ? $deal->account->name : ($dLead ? ($dLead->company_name ?: $dLead->contact_person) : null);
                            $contactName = $deal->contact ? $deal->contact->name : ($deal->account ? $deal->account->primaryContact?->name : ($dLead ? ($dLead->contact_person ?: $dLead->company_name) : null));
                            $phone = $deal->contact?->phone ?: ($deal->account?->phone ?: ($dLead?->company_phone ?: $dLead?->phone));
                            $email = $deal->contact?->email ?: ($deal->account?->email ?: ($dLead?->company_email ?: $dLead?->email));

                            $dealNumbers = [];
                            if (!empty($deal->contact?->phone)) {
                                $dealNumbers[] = [
                                    'type' => 'Contact Person',
                                    'label' => ($deal->contact->name ? $deal->contact->name . ': ' : '') . $deal->contact->phone,
                                    'number' => $deal->contact->phone,
                                    'is_primary' => true
                                ];
                            }
                            if (!empty($deal->account?->phone) && ($deal->account->phone !== ($deal->contact?->phone ?? ''))) {
                                $dealNumbers[] = [
                                    'type' => 'Company Phone',
                                    'label' => ($deal->account->name ? $deal->account->name . ': ' : '') . $deal->account->phone,
                                    'number' => $deal->account->phone,
                                    'is_primary' => empty($dealNumbers)
                                ];
                            }
                            if ($dLead) {
                                if (!empty($dLead->phone) && !in_array($dLead->phone, array_column($dealNumbers, 'number'))) {
                                    $dealNumbers[] = [
                                        'type' => 'Lead Phone',
                                        'label' => ($dLead->contact_person ? $dLead->contact_person . ': ' : '') . $dLead->phone,
                                        'number' => $dLead->phone,
                                        'is_primary' => empty($dealNumbers)
                                    ];
                                }
                                if (!empty($dLead->company_phone) && !in_array($dLead->company_phone, array_column($dealNumbers, 'number'))) {
                                    $dealNumbers[] = [
                                        'type' => 'Lead Company Phone',
                                        'label' => ($dLead->company_name ? $dLead->company_name . ': ' : '') . $dLead->company_phone,
                                        'number' => $dLead->company_phone,
                                        'is_primary' => empty($dealNumbers)
                                    ];
                                }
                            }
                        @endphp
                        <tr class="table-deal-row" id="dealRow_{{ $deal->id }}">
                            <td class="text-center">
                                <input type="checkbox" class="form-check-input deal-select-checkbox" 
                                       value="{{ $deal->id }}" 
                                       data-deal-name="{{ e($deal->title) }}">
                            </td>
                            <td class="font-monospace fw-bold">
                                @php
                                    $dFollowups = $deal->followups ?: collect();
                                    if ($dFollowups->isEmpty() && $dLead && $dLead->relationLoaded('followups')) {
                                        $dFollowups = $dLead->followups ?: collect();
                                    }
                                    $dPendingFollowup = $dFollowups->where('status', 'Pending')->sortBy('followup_date')->first();
                                    $dLastInteraction = $dFollowups->whereIn('status', ['Completed', 'Not Answering', 'Not Connected'])->sortByDesc('followup_date')->first();
                                @endphp
                                <a href="{{ route('crm.deals.show', $deal) }}" class="text-primary text-decoration-none hover-underline fs-13">
                                    {{ $deal->deal_number ?: ('DL-' . str_pad($deal->id, 5, '0', STR_PAD_LEFT)) }}
                                </a>
                                <div class="text-muted fs-11 mt-0.5 font-sans fw-normal" title="Deal Creation Date">
                                    <i class="feather-calendar me-1 text-primary"></i>{{ $deal->created_at ? $deal->created_at->format('d/m/Y') : 'N/A' }}
                                </div>

                                <!-- Odoo-Style Next Activity Smart Badge for Deals -->
                                @if ($dPendingFollowup && $dPendingFollowup->followup_date)
                                    @php
                                        $dfDate = \Carbon\Carbon::parse($dPendingFollowup->followup_date);
                                        $dIsOverdue = $dfDate->lt(now()->startOfDay());
                                        $dIsToday = $dfDate->isToday();
                                        $dIsTomorrow = $dfDate->isTomorrow();
                                        
                                        $dPillClass = $dIsOverdue ? 'bg-danger-subtle text-danger border-danger-subtle' : 
                                                    ($dIsToday ? 'bg-warning-subtle text-warning-emphasis border-warning-subtle' : 'bg-success-subtle text-success border-success-subtle');
                                        
                                        $dTimeLabel = $dIsOverdue ? ('Overdue (' . $dfDate->format('d M') . ')') :
                                                    ($dIsToday ? ('Today ' . $dfDate->format('h:i A')) :
                                                    ($dIsTomorrow ? ('Tomorrow ' . $dfDate->format('h:i A')) : $dfDate->format('d M, h:i A')));
                                        
                                        $dActIcon = str_contains(strtolower($dPendingFollowup->title ?? ''), 'meeting') ? 'feather-video' :
                                                  (str_contains(strtolower($dPendingFollowup->title ?? ''), 'demo') ? 'feather-monitor' : 'feather-phone-call');

                                        $dStatusBadge = $dIsOverdue 
                                            ? "<span class='badge bg-danger text-white fs-9 px-1.5 py-0.5 rounded-pill'>Overdue</span>" 
                                            : ($dIsToday 
                                                ? "<span class='badge bg-warning text-dark fs-9 px-1.5 py-0.5 rounded-pill'>Due Today</span>" 
                                                : "<span class='badge bg-success text-white fs-9 px-1.5 py-0.5 rounded-pill'>Upcoming</span>");

                                        $dCleanNote = $dPendingFollowup->notes;
                                        $dCleanNote = preg_replace('/^Scheduled action:[^\n]+\n/i', '', $dCleanNote);
                                        $dCleanNote = preg_replace('/^Context from call:\s*/i', '', $dCleanNote);
                                        $dCleanNote = trim((string)$dCleanNote);
                                                   
                                        $dTooltipHtml = "<div class='crm-activity-tooltip text-start'>" .
                                            "<div class='d-flex align-items-center justify-content-between gap-2 pb-1.5 mb-2 border-bottom border-light-subtle'>" .
                                                "<div class='d-flex align-items-center gap-1.5 overflow-hidden'>" .
                                                    "<span class='avatar-text avatar-xs bg-soft-primary text-primary rounded-circle d-inline-flex align-items-center justify-content-center' style='width: 20px; height: 20px; font-size: 10px;'><i class='{$dActIcon}'></i></span>" .
                                                    "<span class='fw-bold text-dark fs-12 text-truncate'>" . e($dPendingFollowup->title ?: 'Follow-up Activity') . "</span>" .
                                                "</div>" .
                                                "{$dStatusBadge}" .
                                            "</div>" .
                                            "<div class='d-flex align-items-center gap-1.5 fs-11 mb-2'>" .
                                                "<i class='feather-calendar text-muted fs-11'></i>" .
                                                "<span class='text-muted'>Scheduled:</span>" .
                                                "<strong class='text-dark'>" . $dfDate->format('d M Y, h:i A') . "</strong>" .
                                            "</div>" .
                                            (!empty($dCleanNote) ? (
                                                "<div class='mb-2'>" .
                                                    "<div class='crm-tooltip-note p-2 rounded bg-light border border-light-subtle'>" .
                                                        "<div class='text-muted fs-10 fw-semibold mb-0.5 d-flex align-items-center gap-1'><i class='feather-message-square fs-9 text-primary'></i> Note / Context:</div>" .
                                                        "<div class='text-dark fs-11' style='line-height: 1.4; word-break: break-word;'>" . e(\Illuminate\Support\Str::limit($dCleanNote, 160)) . "</div>" .
                                                    "</div>" .
                                                "</div>"
                                            ) : "") .
                                            ($dLastInteraction && $dLastInteraction->followup_date ? (
                                                "<div class='pt-1.5 mt-1 border-top border-light-subtle d-flex align-items-center justify-content-between text-muted fs-10'>" .
                                                    "<span><i class='feather-check-circle text-success me-1'></i>Last Touch:</span>" .
                                                    "<strong class='text-dark'>" . \Carbon\Carbon::parse($dLastInteraction->followup_date)->diffForHumans() . "</strong>" .
                                                "</div>"
                                            ) : "") .
                                            "</div>";
                                    @endphp
                                    <div class="mt-1 font-sans">
                                        <span class="badge {{ $dPillClass }} border font-monospace px-1.5 py-0.5 fs-10 d-inline-flex align-items-center gap-1 cursor-pointer"
                                              style="letter-spacing: -0.2px; max-width: 100%; font-weight: 500;"
                                              data-bs-toggle="tooltip"
                                              data-bs-html="true"
                                              data-bs-placement="top"
                                              data-bs-custom-class="custom-white-tooltip"
                                              title="{{ $dTooltipHtml }}">
                                            <i class="{{ $dActIcon }} fs-10"></i>
                                            <span class="text-truncate">{{ $dTimeLabel }}</span>
                                        </span>
                                    </div>
                                @elseif ($dLastInteraction && $dLastInteraction->followup_date)
                                    @php
                                        $dlDate = \Carbon\Carbon::parse($dLastInteraction->followup_date);
                                        $dCleanLastNote = $dLastInteraction->notes;
                                        $dCleanLastNote = preg_replace('/^Scheduled action:[^\n]+\n/i', '', $dCleanLastNote);
                                        $dCleanLastNote = preg_replace('/^Context from call:\s*/i', '', $dCleanLastNote);
                                        $dCleanLastNote = trim((string)$dCleanLastNote);

                                        $dlTooltip = "<div class='crm-activity-tooltip text-start'>" .
                                            "<div class='d-flex align-items-center justify-content-between gap-2 pb-1.5 mb-2 border-bottom border-light-subtle'>" .
                                                "<div class='d-flex align-items-center gap-1.5'>" .
                                                    "<span class='avatar-text avatar-xs bg-soft-success text-success rounded-circle d-inline-flex align-items-center justify-content-center' style='width: 20px; height: 20px; font-size: 10px;'><i class='feather-check-circle'></i></span>" .
                                                    "<span class='fw-bold text-dark fs-12'>Last Deal Interaction</span>" .
                                                "</div>" .
                                                "<span class='badge bg-light text-muted border fs-9 px-1.5 py-0.5 rounded-pill'>" . $dlDate->diffForHumans() . "</span>" .
                                            "</div>" .
                                            "<div class='d-flex align-items-center gap-1.5 fs-11 mb-2'>" .
                                                "<i class='feather-calendar text-muted fs-11'></i>" .
                                                "<span class='text-muted'>Date:</span>" .
                                                "<strong class='text-dark'>" . $dlDate->format('d M Y, h:i A') . "</strong>" .
                                            "</div>" .
                                            (!empty($dCleanLastNote) ? (
                                                "<div>" .
                                                    "<div class='crm-tooltip-note p-2 rounded bg-light border border-light-subtle'>" .
                                                        "<div class='text-muted fs-10 fw-semibold mb-0.5 d-flex align-items-center gap-1'><i class='feather-file-text fs-9 text-success'></i> Discussion Summary:</div>" .
                                                        "<div class='text-dark fs-11' style='line-height: 1.4; word-break: break-word;'>" . e(\Illuminate\Support\Str::limit($dCleanLastNote, 160)) . "</div>" .
                                                    "</div>" .
                                                "</div>"
                                            ) : "") .
                                            "</div>";
                                    @endphp
                                    <div class="mt-1 font-sans">
                                        <span class="badge bg-light text-muted border px-1.5 py-0.5 fs-10 d-inline-flex align-items-center gap-1 cursor-pointer"
                                              data-bs-toggle="tooltip"
                                              data-bs-html="true"
                                              data-bs-placement="top"
                                              data-bs-custom-class="custom-white-tooltip"
                                              title="{{ $dlTooltip }}">
                                            <i class="feather-check fs-9 text-success"></i>
                                            <span>Last: {{ $dlDate->diffForHumans() }}</span>
                                        </span>
                                    </div>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('crm.deals.show', $deal) }}" class="fw-bold text-dark text-decoration-none hover-primary d-block" style="line-height: 1.3;">
                                    {{ $deal->title }}
                                </a>
                                @if($deal->lead_source && !in_array($deal->lead_source, ['Select an Option', 'Select an option', 'Select Option'], true))
                                    <div class="text-muted fs-11 mt-0.5"><i class="feather-globe me-1 text-primary"></i>{{ \Illuminate\Support\Facades\Lang::has('crm.sources.' . $deal->lead_source) ? __('crm.sources.' . $deal->lead_source) : $deal->lead_source }}</div>
                                @endif
                            </td>
                            <td>
                                @if($deal->account)
                                    <a href="{{ route('crm.accounts.show', $deal->account) }}" class="fw-bold text-dark text-decoration-none d-block">
                                        <i class="feather-briefcase me-1 text-primary"></i>{{ $deal->account->name }}
                                    </a>
                                @elseif($companyName)
                                    @if($dLead)
                                        <a href="{{ route('crm.leads.show', $dLead->id) }}" class="fw-bold text-dark text-decoration-none d-block" title="View Lead Details">
                                            <i class="feather-briefcase me-1 text-primary"></i>{{ $companyName }}
                                        </a>
                                    @else
                                        <span class="fw-bold text-dark d-block"><i class="feather-briefcase me-1 text-primary"></i>{{ $companyName }}</span>
                                    @endif
                                @else
                                    <span class="fw-semibold text-dark">—</span>
                                @endif

                                @if($contactName && $contactName !== $companyName)
                                    <span class="text-muted fs-11 d-block"><i class="feather-user me-1 text-muted"></i>{{ $contactName }}</span>
                                @endif
                            </td>
                            <td id="dealOwnerCell_{{ $deal->id }}">
                                <div class="d-flex align-items-center cursor-pointer p-1 rounded" 
                                     onclick="openSingleAssignDrawer({{ $deal->id }}, '{{ e($deal->title) }}', '{{ $deal->owner_id }}', '{{ e($deal->owner?->name ?: __('crm.unassigned')) }}')"
                                     title="{{ $deal->owner ? __('crm.change_owner') : __('crm.assign_deal_owner') }}"
                                     style="transition: background-color 0.15s ease;">
                                    <div class="rounded-circle me-2 d-flex align-items-center justify-content-center text-white fw-bold shadow-xs owner-avatar-circle" 
                                         style="width: 28px; height: 28px; background-color: {{ $deal->owner ? '#1e40af' : '#64748b' }}; font-size: 11px; flex-shrink: 0;"
                                         title="{{ $deal->owner?->name ?: __('crm.unassigned') }}">
                                        {{ strtoupper(substr($deal->owner?->name ?: 'U', 0, 1)) }}
                                    </div>
                                    <div>
                                        @if($deal->owner)
                                            <span class="d-block fw-semibold text-dark fs-12 owner-name-text" style="line-height: 1.2;">{{ $deal->owner->name }}</span>
                                            <span class="text-muted fs-10 d-block owner-email-text">{{ $deal->owner->email }}</span>
                                        @else
                                            <span class="badge bg-soft-warning text-warning border border-warning-subtle fs-10 fw-semibold d-inline-flex align-items-center gap-1 py-0.5 px-2">
                                                <i class="feather-user-plus fs-9"></i> {{ __('crm.unassigned') }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td>
                                @if ($phone)
                                    <div class="d-flex align-items-center gap-1.5">
                                        <a href="javascript:void(0)" 
                                           class="text-dark fw-semibold fs-12 text-decoration-none btn-crm-deal-click-to-call hover-primary"
                                           title="Click to call via Softphone & Gemini AI"
                                           data-deal-id="{{ $deal->id }}"
                                           data-deal-title="{{ addslashes($deal->title) }}"
                                           data-deal-company="{{ addslashes($companyName ?: '') }}"
                                           data-contact-name="{{ addslashes($contactName ?: '') }}"
                                           data-deal-stage="{{ $deal->stage }}"
                                           data-deal-phone="{{ $phone }}"
                                           data-deal-numbers="{{ json_encode($dealNumbers) }}">
                                            {{ $phone }}
                                        </a>
                                    </div>
                                @else
                                    <div class="d-flex align-items-center gap-1.5">
                                        <span class="text-muted fs-11">—</span>
                                    </div>
                                @endif
                                @if ($email)
                                    <span class="text-muted fs-11 d-block text-truncate mt-0.5" style="max-width: 160px;" title="{{ $email }}">
                                        <i class="feather-mail fs-11 me-1 text-muted"></i>{{ $email }}
                                    </span>
                                @endif
                            </td>
                            <td class="text-end pe-3 fw-bold text-success fs-14">
                                {{ format_currency($deal->actual_value ?: $deal->estimated_value) }}
                            </td>
                            <td>
                                @if($deal->closing_date)
                                    <div class="fw-semibold text-dark fs-12">
                                        <i class="feather-calendar me-1 text-muted"></i>{{ \Illuminate\Support\Carbon::parse($deal->closing_date)->format('d M Y') }}
                                    </div>
                                @else
                                    <span class="text-muted">—</span>
                                @endif

                                @if($isOverdue)
                                    <span class="badge bg-danger text-white px-2 py-0.5 mt-1 fs-10 fw-bold">
                                        <i class="feather-alert-triangle me-1"></i>{{ __('crm.overdue_followup') }}
                                    </span>
                                @elseif($isClosingToday)
                                    <span class="badge bg-warning text-dark px-2 py-0.5 mt-1 fs-10 fw-bold">
                                        <i class="feather-clock me-1"></i>{{ __('crm.closing_today') }}
                                    </span>
                                @elseif($normalizedStage === 'Won')
                                    <span class="badge bg-soft-success text-success px-2 py-0.5 mt-1 fs-10 fw-bold">
                                        <i class="feather-check-circle me-1"></i>{{ __('crm.deal_closed') }}
                                    </span>
                                @elseif($normalizedStage === 'Lost')
                                    <span class="badge bg-soft-danger text-danger px-2 py-0.5 mt-1 fs-10 fw-bold">
                                        <i class="feather-x-circle me-1"></i>{{ __('crm.deal_lost') }}
                                    </span>
                                @else
                                    <span class="badge bg-soft-info text-info px-2 py-0.5 mt-1 fs-10 fw-semibold">
                                        {{ __('crm.followup_active') }}
                                    </span>
                                @endif
                            </td>
                            <td>
                                @if ($normalizedStage === 'Won')
                                    <span class="badge bg-soft-success text-success px-2.5 py-1 fs-11 fw-bold">
                                        <i class="feather-check-circle me-1"></i>{{ __('crm.statuses.Won') ?? 'Won' }}
                                    </span>
                                @elseif ($normalizedStage === 'Lost')
                                    <span class="badge bg-soft-danger text-danger px-2.5 py-1 fs-11 fw-bold">
                                        <i class="feather-x-circle me-1"></i>{{ __('crm.statuses.Lost') ?? 'Lost' }}
                                    </span>
                                @else
                                    <div class="d-flex flex-column gap-1">
                                        <form action="{{ route('crm.deals.updateStage', $deal->id) }}" method="POST" class="d-inline">
                                            @csrf
                                            @method('PATCH')
                                            <select name="stage" class="form-control status-select" data-select2-selector="status" onchange="this.form.submit()" style="width: 145px;">
                                                @foreach($dealStatuses as $stOption)
                                                    @php
                                                        $bgClass = match(strtolower($stOption->name)) {
                                                            'qualification' => 'bg-info',
                                                            'needs analysis' => 'bg-primary',
                                                            'proposal' => 'bg-warning',
                                                            'negotiation' => 'bg-teal',
                                                            'won', 'closed won' => 'bg-success',
                                                            'lost', 'closed lost' => 'bg-danger',
                                                            default => str_replace('bg-', '', $stOption->color ?: 'bg-primary'),
                                                        };
                                                        if (!str_starts_with($bgClass, 'bg-')) {
                                                            $bgClass = 'bg-' . $bgClass;
                                                        }
                                                        $stOptLabel = __('crm.stages.' . $stOption->name);
                                                        if ($stOptLabel === 'crm.stages.' . $stOption->name) {
                                                            $stOptLabel = $stOption->name;
                                                        }
                                                    @endphp
                                                    <option value="{{ $stOption->name }}" data-bg="{{ $bgClass }}" {{ $normalizedStage === $stOption->name ? 'selected' : '' }}>
                                                        {{ $stOptLabel }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </form>
                                    </div>
                                @endif
                            </td>
                            <td>
                                @if(!$deal->health_synced_at && !$deal->health_score)
                                    <span class="badge bg-light text-muted border px-2 py-1 fs-11 fw-medium">
                                        <i class="feather-clock me-1"></i>{{ __('crm.not_synced') }}
                                    </span>
                                @else
                                    @php
                                        $riskVal = ucfirst(strtolower($deal->risk_level ?: 'Low'));
                                        $badgeStyle = match($riskVal) {
                                            'High' => 'bg-danger text-white',
                                            'Medium' => 'bg-warning text-dark',
                                            'Low' => 'bg-success text-white',
                                            default => 'bg-secondary text-white',
                                        };
                                        $scoreDisplay = $deal->health_score ?: 'N/A';
                                        if (is_numeric($scoreDisplay)) {
                                            $scoreDisplay .= '%';
                                        }
                                    @endphp
                                    <span class="badge {{ $badgeStyle }} px-2 py-1 fs-11 fw-bold d-inline-flex align-items-center" title="{{ $deal->next_best_action ?: 'AI Deal Health Score' }}">
                                        @if($riskVal === 'High')
                                            <i class="feather-alert-octagon me-1"></i>
                                        @elseif($riskVal === 'Medium')
                                            <i class="feather-alert-circle me-1"></i>
                                        @else
                                            <i class="feather-check-circle me-1"></i>
                                        @endif
                                        {{ $scoreDisplay }} ({{ $riskVal }})
                                    </span>
                                @endif
                            </td>
                            <td class="text-end pe-3">
                                @php
                                    $activeQuotation = $deal->quotations->firstWhere('is_current', true) ?: $deal->quotations->first();
                                    $hasAcceptedQuotation = $deal->quotations->contains(fn($q) => in_array($q->status, ['Accepted', 'Converted', 'Won']));
                                    $isDealWon = in_array(strtolower((string)$deal->stage), ['won', 'closed won']);
                                    $hasCustomer = !empty($deal->account?->customer_id) && $isDealWon;
                                    $acceptedQuote = $deal->quotations->firstWhere('status', 'Accepted') ?: ($deal->quotations->firstWhere('status', 'Converted') ?: $activeQuotation);
                                @endphp

                                <x-ui.action-dropdown :viewUrl="route('crm.deals.show', $deal)">
                                    <x-slot:extraActions>
                                        <button type="button" 
                                                class="action-dropdown-btn btn-crm-deal-click-to-call text-success" 
                                                title="Open Phone Dialer & Gemini AI Assistant" 
                                                data-deal-id="{{ $deal->id }}"
                                                data-deal-title="{{ addslashes($deal->title) }}"
                                                data-deal-company="{{ addslashes($companyName ?: '') }}"
                                                data-contact-name="{{ addslashes($contactName ?: '') }}"
                                                data-deal-stage="{{ $deal->stage }}"
                                                data-deal-phone="{{ $phone ?: '' }}"
                                                data-deal-numbers="{{ json_encode($dealNumbers) }}">
                                            <i class="feather-phone-call"></i>
                                        </button>
                                        <button type="button" 
                                                class="action-dropdown-btn btn-open-deal-followup-offcanvas" 
                                                title="{{ __('crm.log_schedule_followup') }}" 
                                                data-bs-toggle="offcanvas" 
                                                data-bs-target="#dealFollowupOffcanvas"
                                                data-deal-id="{{ $deal->id }}"
                                                data-deal-title="{{ addslashes($deal->title) }}"
                                                data-deal-stage="{{ $deal->stage }}">
                                            <i class="feather-calendar text-primary"></i>
                                        </button>
                                    </x-slot:extraActions>

                                    {{-- Assign / Change Deal Owner --}}
                                    <li>
                                        <a href="javascript:void(0)" class="dropdown-item" onclick="openSingleAssignDrawer({{ $deal->id }}, '{{ e($deal->title) }}', '{{ $deal->owner_id }}', '{{ e($deal->owner?->name ?: __('crm.unassigned')) }}')">
                                            <i class="feather-user-check me-2 text-primary fs-12"></i>{{ $deal->owner_id ? __('crm.change_owner') : __('crm.assign_deal_owner') }}
                                        </a>
                                    </li>

                                    <li>
                                        <a href="javascript:void(0)" class="dropdown-item btn-open-deal-followup-offcanvas" data-bs-toggle="offcanvas" data-bs-target="#dealFollowupOffcanvas" data-deal-id="{{ $deal->id }}" data-deal-title="{{ addslashes($deal->title) }}" data-deal-stage="{{ $deal->stage }}">
                                            <i class="feather-calendar me-2 text-primary fs-12"></i>{{ __('crm.log_schedule_followup') }}
                                        </a>
                                    </li>

                                    @if($activeQuotation)
                                        @if($activeQuotation->status === 'Draft' || $activeQuotation->status === 'Quotation Rework')
                                            <li>
                                                <form action="{{ route('crm.quotations.updateStatus', $activeQuotation->id) }}" method="POST">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="status" value="Pending Approval">
                                                    <button type="submit" class="dropdown-item fw-bold text-warning-emphasis">
                                                        <i class="feather-send me-2 text-warning fs-12"></i>{{ __('crm.submit_approval_quotation') }}
                                                    </button>
                                                </form>
                                            </li>
                                        @elseif($activeQuotation->status === 'Approved')
                                            <li>
                                                <form action="{{ route('crm.quotations.updateStatus', $activeQuotation->id) }}" method="POST">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="status" value="Quotation Sent">
                                                    <button type="submit" class="dropdown-item fw-bold text-primary">
                                                        <i class="feather-send me-2 text-primary fs-12"></i>{{ __('crm.mark_quotation_as_sent') }}
                                                    </button>
                                                </form>
                                            </li>
                                        @elseif($activeQuotation->status === 'Quotation Sent' || $activeQuotation->status === 'Sent')
                                            <li>
                                                <form action="{{ route('crm.quotations.updateStatus', $activeQuotation->id) }}" method="POST">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="status" value="Accepted">
                                                    <button type="submit" class="dropdown-item fw-bold text-success">
                                                        <i class="feather-check-circle me-2 text-success fs-12"></i>{{ __('crm.accept_quotation') }}
                                                    </button>
                                                </form>
                                            </li>
                                            <li>
                                                <form action="{{ route('crm.quotations.updateStatus', $activeQuotation->id) }}" method="POST">
                                                    @csrf
                                                    @method('PATCH')
                                                    <input type="hidden" name="status" value="Rejected">
                                                    <button type="submit" class="dropdown-item fw-bold text-danger">
                                                        <i class="feather-x-circle me-2 text-danger fs-12"></i>{{ __('crm.reject_quotation') }}
                                                    </button>
                                                </form>
                                            </li>
                                        @endif
                                    @endif

                                    @if($hasAcceptedQuotation && !$hasCustomer)
                                        <li>
                                            <a href="{{ route('crm.deals.showConvertForm', $deal->id) }}" class="dropdown-item fw-bold text-warning-emphasis">
                                                <i class="feather-user-check me-2 text-warning fs-12"></i>{{ __('crm.convert_to_customer') }}
                                            </a>
                                        </li>
                                    @endif

                                    @if($hasCustomer && $acceptedQuote && in_array($acceptedQuote->status, ['Accepted', 'Converted', 'Won']))
                                        <li>
                                            <a href="{{ route('sales.orders.create', ['quotation_id' => $acceptedQuote->id]) }}" class="dropdown-item fw-bold text-success">
                                                <i class="feather-shopping-cart me-2 text-success fs-12"></i>{{ __('crm.convert_to_sales_order') }}
                                            </a>
                                        </li>
                                    @endif

                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <a href="{{ route('crm.deals.edit', $deal) }}" class="dropdown-item">
                                            <i class="feather-edit me-2 text-muted fs-12"></i>{{ __('crm.edit_deal_details') }}
                                        </a>
                                    </li>
                                    <li>
                                        <form action="{{ route('crm.deals.destroy', $deal->id) }}" method="POST" id="deleteDealForm_{{ $deal->id }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="button" class="dropdown-item text-danger fw-semibold" onclick="confirmAction({ title: '{{ __('crm.delete_deal') }}', message: '{{ __('crm.confirm_delete_deal', ['title' => addslashes($deal->title)]) }}', variant: 'danger', confirmText: '{{ __('crm.delete_deal') }}', onConfirm: function() { document.getElementById('deleteDealForm_{{ $deal->id }}').submit(); } })">
                                                <i class="feather-trash-2 me-2 text-danger fs-12"></i>{{ __('crm.delete_deal') }}
                                            </button>
                                        </form>
                                    </li>
                                </x-ui.action-dropdown>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="text-center py-5 text-muted">
                                <i class="feather-folder fs-1 text-muted d-block mb-2"></i>
                                {{ __('crm.no_deals_found') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.odoo-form-ui>
        </div>
    </div>

    <!-- Offcanvas Drawer: Quick Single & Bulk Deal Assignment -->
    <div class="offcanvas offcanvas-end border-0 shadow-lg" tabindex="-1" id="assignDealOffcanvas" aria-labelledby="assignDealOffcanvasLabel" style="width: 460px; max-width: 92vw;">
        <div class="offcanvas-header bg-light border-bottom py-3 px-4">
            <div class="d-flex align-items-center gap-2">
                <div class="avatar-text avatar-sm bg-soft-primary text-primary rounded-circle">
                    <i class="feather-user-check"></i>
                </div>
                <div>
                    <h5 class="offcanvas-title fw-bold text-dark fs-14 mb-0" id="assignDealOffcanvasTitle">{{ __('crm.assign_deal_owner') }}</h5>
                    <span class="text-muted fs-11" id="assignDealOffcanvasSubtitle">{{ __('crm.select_sales_rep') }}</span>
                </div>
            </div>
            <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        
        <div class="offcanvas-body p-4 bg-white">
            <form id="assignDealForm">
                @csrf
                <input type="hidden" name="assign_mode" id="assignModeInput" value="single">
                <input type="hidden" name="single_deal_id" id="assignSingleDealId" value="">
                <div id="assignBulkDealIdsContainer"></div>

                <!-- Deal Info / Target Preview Card -->
                <div class="p-3 mb-3 bg-light rounded-3 border" id="assignTargetSummaryCard">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="text-muted fs-11 fw-bold text-uppercase" id="assignTargetTypeLabel">{{ __('crm.project_deal_title') }}</span>
                        <span class="badge bg-soft-info text-info border border-info-subtle fs-10" id="assignCurrentOwnerBadge">{{ __('crm.unassigned') }}</span>
                    </div>
                    <div class="fw-bold text-dark fs-13" id="assignTargetNameDisplay">Deal Title</div>
                </div>

                <!-- Assignee Selector -->
                <div class="mb-3">
                    <label class="form-label fw-bold fs-12 text-dark mb-1">
                        {{ __('crm.select_sales_rep') }} <span class="text-danger">*</span>
                    </label>
                    <select name="deal_owner_id" id="assignDealOwnerSelect" class="form-select form-select-sm fs-12 py-2" required>
                        <option value="">{{ __('crm.choose_sales_rep_placeholder') }}</option>
                        @foreach($users as $u)
                            <option value="{{ $u->id }}" data-email="{{ $u->email }}">{{ $u->name }} ({{ $u->email }})</option>
                        @endforeach
                    </select>
                </div>

                <!-- Optional Assignment Note -->
                <div class="mb-4">
                    <label class="form-label fw-bold fs-12 text-dark mb-1">
                        {{ __('crm.assignment_note_reason') }} <span class="text-muted fw-normal fs-11">({{ __('crm.optional') ?? 'Optional' }})</span>
                    </label>
                    <textarea name="note" id="assignNoteInput" class="form-control fs-12" rows="3" placeholder="e.g. Assigned to senior sales consultant..."></textarea>
                </div>

                <!-- Action Buttons -->
                <div class="d-flex align-items-center justify-content-end gap-2 border-top pt-3">
                    <button type="button" class="btn btn-light border px-4 py-2 fs-13 fw-bold text-uppercase" data-bs-dismiss="offcanvas">{{ __('crm.close') }}</button>
                    <button type="submit" class="btn btn-primary px-4 py-2 fs-13 fw-bold text-uppercase shadow-sm d-flex align-items-center gap-1.5" id="btnSubmitDealAssign">
                        <i class="feather-check"></i>
                        <span>{{ __('crm.confirm_assignment') }}</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Offcanvas Drawer: Edit Followup / Schedule Activity (Exact Replica of Leads Offcanvas) -->
    <div class="offcanvas offcanvas-end border-0 shadow-lg" tabindex="-1" id="dealFollowupOffcanvas" aria-labelledby="dealFollowupOffcanvasLabel" style="width: 490px; max-width: 92vw;">
        <div class="offcanvas-header bg-light border-bottom py-3 px-4">
            <div class="d-flex align-items-center gap-2">
                <div class="avatar-text avatar-sm bg-soft-primary text-primary rounded-circle">
                    <i class="feather-calendar"></i>
                </div>
                <div>
                    <h5 class="offcanvas-title fw-bold text-dark fs-14 mb-0" id="dealFollowupOffcanvasTitle">{{ __('crm.edit_followup') }}</h5>
                    <span class="text-muted fs-11">{{ __('crm.log_interaction_next_followup') }}</span>
                </div>
            </div>
            <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        
        <div class="offcanvas-body p-4 bg-white">
            <form action="" method="POST" id="dealFollowupForm" enctype="multipart/form-data">
                @csrf
                <input type="hidden" name="action_mode" id="dealOffcanvasActionMode" value="log_note">

                <!-- 2-Mode Switcher Tabs (Exact Lead Replica) -->
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
                    <x-ui.modal-form-ui type="select" name="type" id="dealOffcanvasFollowupType" :label="__('crm.followup_interaction_type')" :searchable="true">
                        @foreach(['Call', 'Email', 'Meeting', 'Demo', 'WhatsApp'] as $tOpt)
                            <option value="{{ $tOpt }}">{{ __('crm.interaction_types.' . $tOpt) ?? $tOpt }}</option>
                        @endforeach
                    </x-ui.modal-form-ui>

                    <x-ui.modal-form-ui type="select" name="status" id="dealOffcanvasFollowupStatus" :label="__('crm.followup_status_outcome')" :searchable="true">
                        @foreach(['Connected', 'Not Connected', 'Not Answering'] as $outOpt)
                            <option value="{{ $outOpt }}">{{ __('crm.outcomes.' . $outOpt) ?? $outOpt }}</option>
                        @endforeach
                    </x-ui.modal-form-ui>

                    <x-ui.modal-form-ui type="textarea" name="notes" id="dealOffcanvasNotes" :label="__('crm.notes_summary')" rows="3" :placeholder="__('crm.notes_summary_placeholder')" />

                    <!-- Next Follow-up Section inside Log Mode -->
                    <div class="border-top pt-3 mt-3">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <h6 class="fs-12 fw-bold text-dark mb-0">
                                <i class="feather-calendar text-primary me-1"></i> {{ __('crm.next_activity_schedule') }}
                            </h6>
                            <button type="button" class="btn btn-xs btn-outline-primary fw-bold px-2.5 py-1 rounded-pill d-inline-flex align-items-center gap-1" id="btnToggleDealNextScheduleIndex">
                                <i class="feather-plus fs-11" id="iconToggleDealNextScheduleIndex"></i>
                                <span id="textToggleDealNextScheduleIndex">{{ __('crm.schedule_next_activity_btn') }}</span>
                            </button>
                        </div>
                        
                        <div id="containerDealNextScheduleFieldsIndex" class="mt-3 p-3 bg-light rounded-3 border" style="display: none;">
                            <x-ui.modal-form-ui type="input" name="next_title" id="dealOffcanvasNextTitleIndex" :label="__('crm.next_activity_title')" :placeholder="__('crm.next_activity_title_placeholder')" value="" />

                            <div class="row g-2">
                                <div class="col-6">
                                    <x-ui.modal-form-ui type="select" name="next_activity_type" id="dealOffcanvasNextActivityTypeIndex" :label="__('crm.activity_type')" :searchable="true">
                                        @foreach(['Call', 'Meeting', 'Demo', 'Email', 'WhatsApp'] as $actOpt)
                                            <option value="{{ $actOpt }}">{{ __('crm.activity_types.' . $actOpt) ?? $actOpt }}</option>
                                        @endforeach
                                    </x-ui.modal-form-ui>
                                </div>
                                <div class="col-6">
                                    <x-ui.modal-form-ui type="select" name="next_duration_minutes" id="dealOffcanvasNextDurationIndex" :label="__('crm.duration_minutes')" :searchable="true">
                                        @foreach(['15', '30', '45', '60', '90', '120'] as $durOpt)
                                            <option value="{{ $durOpt }}" @selected($durOpt === '30')>{{ __('crm.duration_options.' . $durOpt) ?? ($durOpt . ' Mins') }}</option>
                                        @endforeach
                                    </x-ui.modal-form-ui>
                                </div>
                            </div>

                            <x-ui.modal-form-ui type="input" inputType="datetime-local" name="next_followup_date" id="dealOffcanvasNextFollowupDateIndex" :label="__('crm.next_followup_datetime_optional')" />

                            <div class="p-3 my-3 bg-white rounded-3 border shadow-2xs">
                                <div class="row g-2">
                                    <div class="col-6">
                                        <div class="p-2 border rounded-2 bg-light d-flex align-items-center justify-content-between" style="min-height: 38px;">
                                            <label class="fw-bold fs-11 text-dark mb-0 pe-1 d-flex align-items-center gap-1 c-pointer" for="dealOffcanvasNextSyncGoogleIndex">
                                                <i class="feather-calendar text-danger"></i> {{ __('crm.google_calendar') }}
                                            </label>
                                            <input type="hidden" name="next_sync_google_calendar" value="0">
                                            <x-ui.checkbox name="next_sync_google_calendar" id="dealOffcanvasNextSyncGoogleIndex" value="1" />
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="p-2 border rounded-2 bg-light d-flex align-items-center justify-content-between" style="min-height: 38px;">
                                            <label class="fw-bold fs-11 text-dark mb-0 pe-1 d-flex align-items-center gap-1 c-pointer" for="dealOffcanvasNextCreateMeetIndex">
                                                <i class="feather-video text-primary"></i> {{ __('crm.google_meet_video') }}
                                            </label>
                                            <input type="hidden" name="next_create_meet_link" value="0">
                                            <x-ui.checkbox name="next_create_meet_link" id="dealOffcanvasNextCreateMeetIndex" value="1" />
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <x-ui.modal-form-ui type="input" name="next_guest_emails" id="dealOffcanvasNextGuestEmailsIndex" :label="__('crm.guest_attendee_emails')" :placeholder="__('crm.guest_emails_placeholder')" />

                            <div class="mt-3">
                                <label class="form-label fw-bold text-dark fs-12 mb-1">{{ __('crm.tag_assign_persons') }}</label>
                                <select name="tagged_user_ids[]" id="dealOffcanvasTagUser" class="form-select form-select-sm shadow-2xs" multiple data-placeholder="{{ __('crm.select_persons_to_tag') }}">
                                    @foreach($users as $u)
                                        <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Direct Schedule Section (Tab 2: Schedule Activity) -->
                <div id="dealSectionDirectSchedule" style="display: none;">
                    <x-ui.modal-form-ui type="input" name="title" id="dealOffcanvasEventTitle" :label="__('crm.event_meeting_title')" :placeholder="__('crm.event_title_placeholder')" value="CRM Followup Call" />

                    <x-ui.modal-form-ui type="select" name="schedule_type" id="dealOffcanvasScheduleType" :label="__('crm.activity_type')" :searchable="true" onchange="$('#dealOffcanvasFollowupType').val(this.value)">
                        @foreach(['Call', 'Meeting', 'Demo', 'Email', 'WhatsApp'] as $actOpt)
                            <option value="{{ $actOpt }}">{{ __('crm.activity_types.' . $actOpt) ?? $actOpt }}</option>
                        @endforeach
                    </x-ui.modal-form-ui>

                    <div class="row g-2">
                        <div class="col-6">
                            <x-ui.modal-form-ui type="input" inputType="datetime-local" name="followup_date" id="dealOffcanvasFollowupDate" :label="__('crm.due_date_time')" />
                        </div>
                        <div class="col-6">
                            <x-ui.modal-form-ui type="select" name="duration_minutes" id="dealOffcanvasDuration" :label="__('crm.duration_minutes')" :searchable="true">
                                @foreach(['15', '30', '45', '60', '90', '120'] as $durOpt)
                                    <option value="{{ $durOpt }}" @selected($durOpt === '30')>{{ __('crm.duration_options.' . $durOpt) ?? ($durOpt . ' Mins') }}</option>
                                @endforeach
                            </x-ui.modal-form-ui>
                        </div>
                    </div>

                    <div class="p-3 my-3 bg-white rounded-3 border shadow-2xs">
                        <div class="row g-2">
                            <div class="col-6">
                                <div class="p-2 border rounded-2 bg-light d-flex align-items-center justify-content-between" style="min-height: 38px;">
                                    <label class="fw-bold fs-11 text-dark mb-0 pe-1 d-flex align-items-center gap-1 c-pointer" for="dealOffcanvasSyncGoogle">
                                        <i class="feather-calendar text-danger"></i> {{ __('crm.google_calendar') }}
                                    </label>
                                    <input type="hidden" name="sync_google_calendar" value="0">
                                    <x-ui.checkbox name="sync_google_calendar" id="dealOffcanvasSyncGoogle" value="1" :checked="true" />
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="p-2 border rounded-2 bg-light d-flex align-items-center justify-content-between" style="min-height: 38px;">
                                    <label class="fw-bold fs-11 text-dark mb-0 pe-1 d-flex align-items-center gap-1 c-pointer" for="dealOffcanvasCreateMeet">
                                        <i class="feather-video text-primary"></i> {{ __('crm.google_meet_video') }}
                                    </label>
                                    <input type="hidden" name="create_meet_link" value="0">
                                    <x-ui.checkbox name="create_meet_link" id="dealOffcanvasCreateMeet" value="1" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <x-ui.modal-form-ui type="input" name="guest_emails" id="dealOffcanvasGuestEmails" :label="__('crm.guest_attendee_emails')" :placeholder="__('crm.guest_emails_placeholder')" />

                    <x-ui.modal-form-ui type="textarea" name="schedule_notes" id="dealOffcanvasScheduleNotes" :label="__('crm.description_plan')" rows="3" :placeholder="__('crm.agenda_plan_placeholder')" oninput="$('#dealOffcanvasNotes').val(this.value)" />
                </div>

                <x-ui.modal-form-ui type="select" name="stage" id="dealOffcanvasStage" :label="__('crm.deal_stage')" :searchable="true">
                    @foreach($dealStatuses as $stg)
                        <option value="{{ $stg->name }}">{{ $stg->name }}</option>
                    @endforeach
                </x-ui.modal-form-ui>

                <div class="d-flex align-items-center justify-content-end gap-2 border-top pt-3">
                    <button type="button" class="btn btn-light border px-4 py-2 fs-13 fw-bold text-uppercase" data-bs-dismiss="offcanvas">{{ strtoupper(__('crm.close')) }}</button>
                    <button type="submit" class="btn btn-primary px-4 py-2 fs-13 fw-bold text-uppercase shadow-sm">{{ strtoupper(__('crm.save')) }}</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Floating Right-Side CRM Mobile Softphone Dialer Widget for Deals -->
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
                        <span class="fw-bold fs-10 text-muted text-uppercase">Live Speech Notes:</span>
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

    <!-- Modal 2: Gemini AI Deal Call Analysis & Confirmation Popup (Human-in-the-Loop using common component) -->
    <x-ui.modal id="geminiAiCallReviewModal" size="lg" :centered="true" :static="true" :showFooter="false">
        <x-slot:title>
            <div class="d-flex align-items-center gap-2">
                <div class="avatar-text avatar-sm bg-primary text-white rounded-circle shadow-xs">
                    <i class="feather-cpu fs-14"></i>
                </div>
                <div>
                    <div class="d-flex align-items-center gap-2">
                        <h5 class="modal-title fs-14 fw-bold mb-0 text-dark" id="geminiAiCallReviewModalLabel">Gemini AI Deal Call Recording Analysis & Next Steps</h5>
                        <span class="badge bg-soft-primary text-primary fs-10 fw-bold px-2 py-0.5 rounded-pill shadow-xs">Gemini Flash AI</span>
                    </div>
                    <span class="fs-11 text-muted">Audio transcribed & analyzed. Review before confirming activity to Deal.</span>
                </div>
            </div>
        </x-slot:title>

        <!-- Target Deal Quick Header -->
        <div class="bg-light p-3 rounded-3 border mb-3 shadow-xs d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted fs-10 text-uppercase fw-bold">Target Deal / Account</span>
                <div class="fw-bold text-dark fs-13" id="aiModalDealName">Deal Title</div>
                <span class="text-muted fs-11" id="aiModalDialedNumber">+91 0000000000</span>
            </div>
            <div class="text-end">
                <span class="text-muted fs-10 text-uppercase fw-bold d-block">AI Sentiment</span>
                <span class="badge bg-soft-success text-success border border-success-subtle fs-11 fw-bold" id="aiModalSentimentBadge">Positive / Interested</span>
            </div>
        </div>

        <form id="geminiAiDealApprovalForm">
            <input type="hidden" id="aiModalDealId" value="">
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
                            <span class="text-muted fs-10">Play & verify speech before Deal confirmation</span>
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
                    <span class="badge bg-soft-info text-info fs-10">Gemini Audio Speech-to-Text</span>
                </div>
                <textarea class="form-control fs-12 bg-white" id="aiModalTranscript" rows="2" placeholder="Transcribed conversation text..."></textarea>
            </div>

            <!-- 2. Discussion Summary -->
            <div class="mb-3">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <label class="form-label fw-bold fs-12 text-dark mb-0">
                        <i class="feather-file-text text-primary me-1"></i> Discussion Summary & Next Milestones
                    </label>
                    <span class="text-muted fs-10">Editable</span>
                </div>
                <textarea class="form-control fs-12 bg-white" id="aiModalDiscussionSummary" rows="3" required placeholder="Discussion details analyzed by Gemini..."></textarea>
            </div>

            <!-- 3. Next Follow-up & Activity Details -->
            <div class="p-3 bg-light rounded-3 border mb-3 shadow-xs">
                <div class="d-flex align-items-center gap-1.5 mb-2.5 pb-2 border-bottom">
                    <i class="feather-calendar text-primary fs-13"></i>
                    <h6 class="fs-12 fw-bold text-dark mb-0">Proposed Next Activity & Schedule</h6>
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
                    <input type="text" class="form-control form-control-sm fs-12" id="aiModalNextActivityTitle" placeholder="e.g. Follow-up call for quotation revision and order closing">
                </div>
            </div>

            <!-- 4. Suggested Deal Stage & Sync Notice -->
            <div class="row g-2 mb-3">
                <div class="col-md-6">
                    <div class="p-2.5 bg-white rounded-3 border shadow-xs h-100">
                        <label class="form-label fw-semibold fs-11 text-muted mb-1">Suggested Deal Stage</label>
                        <select class="form-select form-select-sm fs-12" id="aiModalDealStage">
                            @foreach($dealStatuses as $ds)
                                <option value="{{ $ds->name }}">{{ $ds->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="p-2.5 bg-soft-info border border-info-subtle rounded-3 text-dark fs-11 h-100 d-flex align-items-center">
                        <div>
                            <div class="fw-bold text-info mb-0.5"><i class="feather-check-circle me-1"></i>Deal Timeline Auto-Sync</div>
                            <span class="text-muted fs-10">Follow-up recorded to Deal Interactions & Timeline automatically.</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 5. Follow-up Confirmation Message Preview -->
            <div class="mb-2">
                <label class="form-label fw-semibold fs-11 text-muted mb-1">
                    <i class="feather-send text-success me-1"></i> Customer Follow-up Message Note
                </label>
                <textarea class="form-control fs-11 bg-white" id="aiModalFollowupMessage" rows="2" placeholder="Summary message note for WhatsApp/Email..."></textarea>
            </div>

            <!-- Footer: Actions -->
            <div class="d-flex align-items-center justify-content-between pt-3 border-top mt-3">
                <button type="button" class="btn btn-light border px-3 py-2 fs-12 fw-semibold" data-bs-dismiss="modal">Cancel / Edit Later</button>
                <button type="button" class="btn btn-primary px-4 py-2 fs-12 fw-bold d-flex align-items-center gap-1.5 shadow-sm" id="btnApproveAndScheduleDeal">
                    <i class="feather-check-circle fs-13"></i>
                    <span id="btnApproveDealText">Approve & Save to Deal</span>
                </button>
            </div>
        </form>
    </x-ui.modal>
@endsection

@push('scripts')
<!-- Twilio Voice WebRTC SDK for 2-Way Live Calling -->
<script src="https://cdn.jsdelivr.net/npm/@twilio/voice-sdk@2.11.1/dist/twilio.min.js"></script>
<script src="{{ asset('assets/vendors/js/sweetalert2.all.min.js') }}"></script>
<script>
    $(function () {
        $(document).on('change change.select2', '.status-select, .stage-select', function() {
            var form = $(this).closest('form');
            if (form.length) {
                form[0].submit();
            }
        });

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

        $(document).on('click', '#btnToggleDealNextScheduleIndex', function() {
            var container = $('#containerDealNextScheduleFieldsIndex');
            var icon = $('#iconToggleDealNextScheduleIndex');
            var text = $('#textToggleDealNextScheduleIndex');
            if (container.is(':visible')) {
                container.slideUp(200);
                icon.removeClass('feather-minus').addClass('feather-plus');
                text.text('Schedule Next Activity');
                $('#dealOffcanvasNextTitleIndex, #dealOffcanvasNextFollowupDateIndex, #dealOffcanvasNextGuestEmailsIndex').val('');
                $('#dealOffcanvasNextSyncGoogleIndex, #dealOffcanvasNextCreateMeetIndex').prop('checked', false);
            } else {
                container.slideDown(200);
                icon.removeClass('feather-plus').addClass('feather-minus');
                text.text('Remove Next Activity');
            }
        });

        // Open and populate Offcanvas drawer for Deal Followup / Schedule Activity
        $(document).on('click', '.btn-open-deal-followup-offcanvas', function() {
            var dealId = $(this).attr('data-deal-id');
            var dealTitle = $(this).attr('data-deal-title') || 'Deal';
            var dealStage = $(this).attr('data-deal-stage');

            $('#dealFollowupOffcanvasTitle').text('Edit Followup for ' + dealTitle);
            $('#dealFollowupForm').attr('action', '/crm/deals/' + dealId + '/followups');
            $('#dealOffcanvasNotes, #dealOffcanvasScheduleNotes, #dealOffcanvasNextFollowupDateIndex, #dealOffcanvasNextTitleIndex, #dealOffcanvasNextGuestEmailsIndex').val('');
            $('#dealOffcanvasNextSyncGoogleIndex, #dealOffcanvasNextCreateMeetIndex').prop('checked', false);

            $('#containerDealNextScheduleFieldsIndex').hide();
            $('#iconToggleDealNextScheduleIndex').removeClass('feather-minus').addClass('feather-plus');
            $('#textToggleDealNextScheduleIndex').text('Schedule Next Activity');

            if (dealStage) {
                $('#dealOffcanvasStage').val(dealStage);
            }

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
                $('#dealOffcanvasTagUser').val(null).trigger('change');
            }

            switchDealOffcanvasMode('log_note');
        });

        // ==========================================
        // Deal Quick Assignment & Bulk Assign Logic
        // ==========================================
        var assignOffcanvasEl = document.getElementById('assignDealOffcanvas');
        var assignBsOffcanvas = assignOffcanvasEl ? new bootstrap.Offcanvas(assignOffcanvasEl) : null;
        var transAssignDealOwner = @json(__('crm.assign_deal_owner'));
        var transBulkAssignDeals = @json(__('crm.bulk_assign_deals'));
        var transSelectSalesRep = @json(__('crm.select_sales_rep'));
        var transDealsSelected = @json(__('crm.deals_selected'));
        var transDealSelected = @json(__('crm.deal_selected'));
        var transUnassigned = @json(__('crm.unassigned'));
        var transChangeOwner = @json(__('crm.change_owner'));
        var transSelectedActions = @json(__('crm.selected_actions'));

        window.openSingleAssignDrawer = function(dealId, dealTitle, currentOwnerId, currentOwnerName) {
            $('#assignModeInput').val('single');
            $('#assignSingleDealId').val(dealId);
            $('#assignBulkDealIdsContainer').empty();
            
            $('#assignDealOffcanvasTitle').text(transAssignDealOwner);
            $('#assignDealOffcanvasSubtitle').text(transSelectSalesRep);
            $('#assignTargetTypeLabel').text(@json(__('crm.project_deal_title')));
            $('#assignTargetNameDisplay').text(dealTitle || ('Deal #' + dealId));

            if (currentOwnerName && currentOwnerName !== 'Unassigned' && currentOwnerName !== transUnassigned && currentOwnerName.trim() !== '') {
                $('#assignCurrentOwnerBadge').text(currentOwnerName).removeClass('bg-soft-secondary text-secondary').addClass('bg-soft-info text-info');
            } else {
                $('#assignCurrentOwnerBadge').text(transUnassigned).removeClass('bg-soft-info text-info').addClass('bg-soft-secondary text-secondary');
            }

            $('#assignDealOwnerSelect').val(currentOwnerId || '');
            $('#assignNoteInput').val('');

            if (assignBsOffcanvas) {
                assignBsOffcanvas.show();
            }
        };

        window.openBulkAssignDrawer = function() {
            var selectedCheckboxes = $('.deal-select-checkbox:checked');
            var count = selectedCheckboxes.length;
            if (count === 0) {
                alert('Please select at least one deal from the table checkbox.');
                return;
            }

            $('#assignModeInput').val('bulk');
            $('#assignSingleDealId').val('');
            var container = $('#assignBulkDealIdsContainer').empty();

            var dealNames = [];
            selectedCheckboxes.each(function() {
                var did = $(this).val();
                var dname = $(this).attr('data-deal-name');
                container.append('<input type="hidden" name="deal_ids[]" value="' + did + '">');
                if (dealNames.length < 3 && dname) {
                    dealNames.push(dname);
                }
            });

            $('#assignDealOffcanvasTitle').text(transBulkAssignDeals);
            $('#assignDealOffcanvasSubtitle').text(transSelectSalesRep);
            $('#assignTargetTypeLabel').text(count + ' ' + (count === 1 ? transDealSelected : transDealsSelected));
            $('#assignTargetNameDisplay').text(dealNames.join(', ') + (count > 3 ? ' and ' + (count - 3) + ' more...' : ''));
            $('#assignCurrentOwnerBadge').text(count + ' ' + (count === 1 ? transDealSelected : transDealsSelected)).removeClass('bg-soft-info text-info').addClass('bg-soft-primary text-primary');

            $('#assignDealOwnerSelect').val('');
            $('#assignNoteInput').val('');

            if (assignBsOffcanvas) {
                assignBsOffcanvas.show();
            }
        };

        window.clearDealSelections = function() {
            $('.deal-select-checkbox').prop('checked', false);
            $('#selectAllDealsCheckbox').prop('checked', false);
            $('#dealsTable tbody tr').removeClass('deal-row-selected');
            updateToolbarVisibility();
        };

        function updateToolbarVisibility() {
            var selectedCheckboxes = $('.deal-select-checkbox:checked');
            var count = selectedCheckboxes.length;
            var normalToolbar = document.getElementById('normal-toolbar');
            var bulkActionsToolbar = document.getElementById('bulk-actions-toolbar');
            var bulkActionsLabel = document.querySelector('#bulk-actions-toolbar .bulk-actions-label');

            if (count > 0) {
                if (normalToolbar) normalToolbar.classList.add('d-none');
                if (bulkActionsToolbar) bulkActionsToolbar.classList.remove('d-none');
                if (bulkActionsLabel) {
                    bulkActionsLabel.textContent = transSelectedActions + ' (' + count + ')';
                }
            } else {
                if (normalToolbar) normalToolbar.classList.remove('d-none');
                if (bulkActionsToolbar) bulkActionsToolbar.classList.add('d-none');
                if (bulkActionsLabel) {
                    bulkActionsLabel.textContent = transSelectedActions + ' (0)';
                }
            }
        }

        // Checkbox events
        $('#selectAllDealsCheckbox').on('change', function() {
            var isChecked = $(this).is(':checked');
            $('.deal-select-checkbox').prop('checked', isChecked);
            if (isChecked) {
                $('#dealsTable tbody tr').addClass('deal-row-selected');
            } else {
                $('#dealsTable tbody tr').removeClass('deal-row-selected');
            }
            updateToolbarVisibility();
        });

        $(document).on('change', '.deal-select-checkbox', function() {
            var tr = $(this).closest('tr');
            if ($(this).is(':checked')) {
                tr.addClass('deal-row-selected');
            } else {
                tr.removeClass('deal-row-selected');
            }
            
            var totalBoxes = $('.deal-select-checkbox').length;
            var checkedBoxes = $('.deal-select-checkbox:checked').length;
            $('#selectAllDealsCheckbox').prop('checked', totalBoxes > 0 && totalBoxes === checkedBoxes);

            updateToolbarVisibility();
        });

        // Form Submit AJAX Handler
        $('#assignDealForm').on('submit', function(e) {
            e.preventDefault();
            var mode = $('#assignModeInput').val();
            var submitBtn = $('#btnSubmitDealAssign');
            var origBtnHtml = submitBtn.html();

            var ownerId = $('#assignDealOwnerSelect').val();
            var note = $('#assignNoteInput').val();
            var csrfToken = $('meta[name="csrf-token"]').attr('content');

            var postUrl = "{{ route('crm.deals.bulkAssign') }}";
            var payload = {
                deal_owner_id: ownerId || null,
                note: note
            };

            var targetDealIds = [];
            if (mode === 'single') {
                var singleId = parseInt($('#assignSingleDealId').val());
                targetDealIds.push(singleId);
                payload.deal_ids = [singleId];
            } else {
                $('input[name="deal_ids[]"]').each(function() {
                    targetDealIds.push(parseInt($(this).val()));
                });
                payload.deal_ids = targetDealIds;
            }

            submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Saving...');

            fetch(postUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify(payload)
            })
            .then(function(r) {
                return r.json().then(function(data) {
                    return { ok: r.ok, status: r.status, data: data };
                }).catch(function() {
                    return { ok: r.ok, status: r.status, data: { message: r.statusText } };
                });
            })
            .then(function(resObj) {
                submitBtn.prop('disabled', false).html(origBtnHtml);
                var res = resObj.data || {};
                if (resObj.ok && res.success) {
                    var ownerName = res.owner_name || transUnassigned;
                    var ownerEmail = res.owner_email || '—';
                    var ownerInitial = res.owner_initial || 'U';
                    var isAssigned = !!res.owner_id;

                    // Update DOM for each target deal
                    targetDealIds.forEach(function(dealId) {
                        var cell = $('#dealOwnerCell_' + dealId);
                        if (cell.length) {
                            var row = $('#dealRow_' + dealId);
                            var dealName = row.find('.deal-select-checkbox').attr('data-deal-name') || ('Deal #' + dealId);
                            
                            var newHtml = '';
                            if (isAssigned) {
                                newHtml = '<div class="d-flex align-items-center cursor-pointer p-1 rounded" ' +
                                    'onclick="openSingleAssignDrawer(' + dealId + ', \'' + (dealName.replace(/'/g, "\\'")) + '\', \'' + res.owner_id + '\', \'' + (ownerName.replace(/'/g, "\\'")) + '\')" ' +
                                    'title="' + transChangeOwner + '" style="transition: background-color 0.15s ease;">' +
                                    '<div class="rounded-circle me-2 d-flex align-items-center justify-content-center text-white fw-bold shadow-xs owner-avatar-circle" ' +
                                    'style="width: 28px; height: 28px; background-color: #1e40af; font-size: 11px; flex-shrink: 0;" title="' + ownerName + '">' +
                                    ownerInitial +
                                    '</div>' +
                                    '<div>' +
                                    '<span class="d-block fw-semibold text-dark fs-12 owner-name-text" style="line-height: 1.2;">' + ownerName + '</span>' +
                                    '<span class="text-muted fs-10 d-block owner-email-text">' + ownerEmail + '</span>' +
                                    '</div>' +
                                    '</div>';
                            } else {
                                newHtml = '<div class="d-flex align-items-center cursor-pointer p-1 rounded" ' +
                                    'onclick="openSingleAssignDrawer(' + dealId + ', \'' + (dealName.replace(/'/g, "\\'")) + '\', \'\', \'' + transUnassigned + '\')" ' +
                                    'title="' + transAssignDealOwner + '" style="transition: background-color 0.15s ease;">' +
                                    '<div class="rounded-circle me-2 d-flex align-items-center justify-content-center text-white fw-bold shadow-xs owner-avatar-circle" ' +
                                    'style="width: 28px; height: 28px; background-color: #64748b; font-size: 11px; flex-shrink: 0;" title="' + transUnassigned + '">' +
                                    'U' +
                                    '</div>' +
                                    '<div>' +
                                    '<span class="badge bg-soft-warning text-warning border border-warning-subtle fs-10 fw-semibold d-inline-flex align-items-center gap-1 py-0.5 px-2">' +
                                    '<i class="feather-user-plus fs-9"></i> ' + transUnassigned +
                                    '</span>' +
                                    '</div>' +
                                    '</div>';
                            }
                            cell.html(newHtml);
                        }
                    });

                    if (assignBsOffcanvas) {
                        assignBsOffcanvas.hide();
                    }

                    clearDealSelections();

                    // Standard Duralux Toast Notification
                    if (typeof Swal !== 'undefined') {
                        Swal.mixin({
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 3500,
                            timerProgressBar: true,
                            didOpen: function (toast) {
                                toast.addEventListener('mouseenter', Swal.stopTimer);
                                toast.addEventListener('mouseleave', Swal.resumeTimer);
                            }
                        }).fire({
                            icon: 'success',
                            title: res.message || 'Deal assigned successfully!'
                        });
                    } else if (typeof toastr !== 'undefined') {
                        toastr.success(res.message || 'Deal assigned successfully!');
                    }
                } else {
                    var errMsg = res.message;
                    if (res.errors) {
                        errMsg = Object.values(res.errors).flat().join("\n");
                    }
                    if (typeof Swal !== 'undefined') {
                        Swal.mixin({
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 4000,
                            timerProgressBar: true
                        }).fire({
                            icon: 'error',
                            title: errMsg || 'Error updating deal owner.'
                        });
                    } else {
                        alert(errMsg || 'Error updating deal owner.');
                    }
                }
            })
            .catch(function(err) {
                submitBtn.prop('disabled', false).html(origBtnHtml);
                console.error('Assign failed:', err);
                if (typeof Swal !== 'undefined') {
                    Swal.mixin({
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 4000,
                        timerProgressBar: true
                    }).fire({
                        icon: 'error',
                        title: 'An error occurred while assigning deals.'
                    });
                } else {
                    alert('An error occurred while assigning deals.');
                }
            });
        });

        // =========================================================================
        // DEAL AI SOFTPHONE & LIVE 2-WAY WEBRTC CALLING + GEMINI TRANSCRIPTION
        // =========================================================================
        var activeDealCallId = null;
        var activeDealTitle = '';
        var activeDealCompany = '';
        var activeDealDurationSeconds = 0;
        var activeDealCallInterval = null;
        var activeAudioStream = null;
        var activeMediaRecorder = null;
        var audioChunks = [];
        var speechRecognition = null;
        var isSpeechRecording = false;
        var speechFinalTranscript = '';
        var recognitionRestartTimer = null;

        function formatDealCallTimer(seconds) {
            var mins = Math.floor(seconds / 60);
            var secs = seconds % 60;
            return (mins < 10 ? '0' : '') + mins + ':' + (secs < 10 ? '0' : '') + secs;
        }

        // Web Speech Recognition for Real-time Voice to Text
        var SpeechRecognitionClass = window.SpeechRecognition || window.webkitSpeechRecognition;
        if (SpeechRecognitionClass) {
            speechRecognition = new SpeechRecognitionClass();
            speechRecognition.continuous = true;
            speechRecognition.interimResults = true;
            speechRecognition.lang = 'hi-IN'; // Robust support for Hindi & Indian English / Hinglish

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

            speechRecognition.onerror = function(err) {
                console.warn('Speech recognition status:', err.error);
                if (err.error === 'not-allowed') {
                    $('#liveSpeechStatus').html('<span class="text-danger"><i class="feather-mic-off"></i> Microphone blocked in browser</span>');
                }
            };

            speechRecognition.onend = function() {
                if (isSpeechRecording) {
                    clearTimeout(recognitionRestartTimer);
                    recognitionRestartTimer = setTimeout(function() {
                        if (isSpeechRecording && speechRecognition) {
                            try { speechRecognition.start(); } catch(e) {}
                        }
                    }, 200);
                }
            };
        }

        // =========================================================================
        // CRM SOFTPHONE & LIVE TELEPHONE RINGING SYNTHESIZER (Web Audio API)
        // =========================================================================
        var ringAudioCtx = null;
        var isBrowserRinging = false;
        var ringCadenceTimer = null;
        var activeTwilioCallSid = null;
        var isSoftphoneMinimized = false;

        function startBrowserRingingTone() {
            try {
                stopBrowserRingingTone();
                var AudioContextClass = window.AudioContext || window.webkitAudioContext;
                if (!AudioContextClass) return;
                ringAudioCtx = new AudioContextClass();
                isBrowserRinging = true;

                function playDualRingPulse() {
                    if (!isBrowserRinging || !ringAudioCtx) return;
                    try {
                        if (ringAudioCtx.state === 'suspended') {
                            ringAudioCtx.resume();
                        }
                        var now = ringAudioCtx.currentTime;

                        var osc1 = ringAudioCtx.createOscillator();
                        var osc2 = ringAudioCtx.createOscillator();
                        var gain = ringAudioCtx.createGain();

                        osc1.type = 'sine';
                        osc1.frequency.setValueAtTime(440, now);

                        osc2.type = 'sine';
                        osc2.frequency.setValueAtTime(480, now);

                        gain.gain.setValueAtTime(0.08, now);
                        gain.gain.setValueAtTime(0.08, now + 1.8);
                        gain.gain.exponentialRampToValueAtTime(0.0001, now + 2.0);

                        osc1.connect(gain);
                        osc2.connect(gain);
                        gain.connect(ringAudioCtx.destination);

                        osc1.start(now);
                        osc2.start(now);
                        osc1.stop(now + 2.0);
                        osc2.stop(now + 2.0);

                        ringCadenceTimer = setTimeout(function() {
                            if (isBrowserRinging) {
                                playDualRingPulse();
                            }
                        }, 4000);
                    } catch(e) {
                        console.warn('Ringing pulse notice:', e);
                    }
                }

                playDualRingPulse();
            } catch(err) {
                console.warn('Web Audio ringing synthesizer not supported:', err);
            }
        }

        function stopBrowserRingingTone() {
            isBrowserRinging = false;
            if (ringCadenceTimer) {
                clearTimeout(ringCadenceTimer);
                ringCadenceTimer = null;
            }
            if (ringAudioCtx) {
                try {
                    ringAudioCtx.close();
                } catch(e) {}
                ringAudioCtx = null;
            }
        }

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

        // Click-to-Call Trigger for Deals
        $(document).on('click', '.btn-crm-deal-click-to-call', function(e) {
            e.preventDefault();
            var btn = $(this);
            activeDealCallId = btn.attr('data-deal-id');
            activeDealTitle = btn.attr('data-deal-title') || 'Deal';
            activeDealCompany = btn.attr('data-deal-company') || btn.attr('data-contact-name') || '';
            var dealStage = btn.attr('data-deal-stage') || 'Qualification';
            
            var numbers = [];
            try {
                var rawNumbers = btn.attr('data-deal-numbers');
                if (rawNumbers) {
                    numbers = JSON.parse(rawNumbers);
                }
            } catch(err) {
                numbers = [];
            }

            if (!numbers.length && btn.attr('data-deal-phone')) {
                numbers.push({
                    type: 'Primary Phone',
                    label: 'Primary: ' + btn.attr('data-deal-phone'),
                    number: btn.attr('data-deal-phone'),
                    is_primary: true
                });
            }

            // Populate Caller Info Header (Clean Calling Info Only)
            var callerName = btn.attr('data-contact-name') || btn.attr('data-deal-company') || 'Contact Person';
            var companySub = btn.attr('data-deal-company') || 'Calling Line';
            $('#dialerContactName').text(callerName);
            $('#dialerContactSub').text(companySub);
            $('#dialerContactAvatar').text((callerName.trim().charAt(0) || 'C').toUpperCase());

            // Set Phone Number directly in Screen Display
            if (numbers.length > 0 && numbers[0].number) {
                $('#dialerDisplayNumber').val(numbers[0].number);
            } else {
                $('#dialerDisplayNumber').val('');
            }

            // Reset UI to Idle / Ready to Call State
            $('#dialerIdlePanel').show();
            $('#dialerInCallPanel').hide();
            $('#dialerStartAction').show();
            $('#dialerEndAction').hide();
            $('#softphoneStatusPill').text('Ready').removeClass('bg-danger bg-warning').addClass('bg-success');
            $('#dialerLiveTimer').text('00:00');
            $('#dialerCallNotes').val('');
            activeDealDurationSeconds = 0;
            audioChunks = [];
            activeTwilioCallSid = null;

            if (isSoftphoneMinimized) {
                toggleSoftphoneMinimize();
            }
            $('#crmFloatingSoftphoneWidget').fadeIn(250);
        });

        // Dialpad Digit Keys Click
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

        $('#btnDialerClear').on('click', function() {
            $('#dialerDisplayNumber').val('').focus();
        });

        $('#btnToggleDialpadView').on('click', function() {
            var container = $('#dialpadContainer');
            var icon = $('#dialpadToggleIcon');
            if (container.is(':visible')) {
                container.slideUp(200);
                icon.removeClass('feather-chevron-up').addClass('feather-chevron-down');
            } else {
                container.slideDown(200);
                icon.removeClass('feather-chevron-down').addClass('feather-chevron-up');
            }
        });

        $(document).on('click', '.quick-chip-btn', function() {
            var text = $(this).attr('data-text');
            var current = $('#dialerCallNotes').val();
            $('#dialerCallNotes').val((current ? current.trim() + ' ' : '') + text).focus();
        });

        // =========================================================================
        // TWILIO WEBRTC 2-WAY LIVE VOICE DEVICE INITIALIZATION
        // =========================================================================
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
                                console.log('Twilio 2-Way Voice Softphone registered and ready for Deals.');
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
            var initiateUrl = "{{ url('crm/deals') }}/" + activeDealCallId + "/call-ai/initiate-call";

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
                        startTwilioCallStatusPolling(activeDealCallId, activeTwilioCallSid);
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

        // START REAL OUTBOUND CALL
        $('#btnStartRealCall').on('click', function() {
            var targetNumber = $('#dialerDisplayNumber').val().trim();
            if (!targetNumber) {
                alert('Please enter or select a phone number to call.');
                $('#dialerDisplayNumber').focus();
                return;
            }

            if (!activeDealCallId) {
                alert('No active deal selected.');
                return;
            }

            startBrowserRingingTone();

            $('#dialerIdlePanel').hide();
            $('#dialerInCallPanel').fadeIn(250);
            $('#dialerStartAction').hide();
            $('#dialerEndAction').show();
            $('#softphoneStatusPill').text('Calling...').removeClass('bg-success').addClass('bg-danger');
            $('#dialerLiveStatusTag').html('<i class="feather-phone-call me-1"></i> Ringing Mobile...');
            $('#inCallTargetDisplay').text(targetNumber + ' (' + activeDealTitle + ')');
            $('#inCallSubtext').text('Connecting 2-way live audio with customer phone...');

            activeDealDurationSeconds = 0;
            $('#dialerLiveTimer').text('00:00');
            if (activeDealCallInterval) clearInterval(activeDealCallInterval);
            activeDealCallInterval = setInterval(function() {
                activeDealDurationSeconds++;
                $('#dialerLiveTimer').text(formatDealCallTimer(activeDealDurationSeconds));
            }, 1000);

            if (twilioDevice && twilioDevice.state === 'registered') {
                try {
                    twilioDevice.connect({ params: { To: targetNumber, deal_id: activeDealCallId } })
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

            // Audio MediaRecorder for Gemini AI
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

        // Real-time Twilio Call Status Poller
        var activeTwilioPollTimer = null;

        function startTwilioCallStatusPolling(dealId, callSid) {
            if (activeTwilioPollTimer) clearInterval(activeTwilioPollTimer);
            if (!callSid || !dealId) return;

            var pollUrl = "{{ url('crm/deals') }}/" + dealId + "/call-ai/status";
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
                    }
                    else if (st === 'busy' || st === 'no-answer' || st === 'canceled' || st === 'failed') {
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
                        $('#inCallSubtext').html('<span class="text-danger fw-bold">' + reasonText + '</span> • Logged in Deal Interactions.');

                        var confirmUrl = "{{ url('crm/deals') }}/" + dealId + "/call-ai/confirm";
                        fetch(confirmUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrfToken
                            },
                            body: JSON.stringify({
                                summary: 'Not answering',
                                duration_seconds: activeDealDurationSeconds || 0,
                                next_action: 'Retry Call',
                                deal_stage: 'Qualification',
                                sentiment: 'Neutral'
                            })
                        }).then(function() {
                            console.log('Logged Not Answering activity to deal #' + dealId);
                        }).catch(function(e) {
                            console.warn('Auto log error:', e);
                        });

                        setTimeout(function() {
                            if (!$('#dialerStartAction').is(':visible')) {
                                $('#dialerInCallPanel').hide();
                                $('#dialerIdlePanel').fadeIn(200);
                                $('#dialerStartAction').show();
                                $('#dialerEndAction').hide();
                                $('#softphoneStatusPill').text('Ready').removeClass('bg-danger bg-warning').addClass('bg-success');
                            }
                        }, 3500);
                    }
                    else if (st === 'completed') {
                        stopBrowserRingingTone();
                        if (activeTwilioPollTimer) {
                            clearInterval(activeTwilioPollTimer);
                            activeTwilioPollTimer = null;
                        }
                        
                        if (activeDealDurationSeconds >= 3 || (res.duration && res.duration >= 3)) {
                            $('#btnDisconnectAndAnalyze').trigger('click');
                        } else {
                            stopActiveCallMedia();
                            activeTwilioCallSid = null;
                            $('#softphoneStatusPill').text('Call Disconnected').removeClass('bg-success').addClass('bg-warning');
                            $('#dialerLiveStatusTag').html('<i class="feather-phone-off me-1 text-warning"></i> Call Ended');
                            $('#inCallSubtext').text('Call disconnected by recipient • Logged in Interactions.');

                            var confirmUrl = "{{ url('crm/deals') }}/" + dealId + "/call-ai/confirm";
                            fetch(confirmUrl, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': csrfToken
                                },
                                body: JSON.stringify({
                                    summary: 'Not answering',
                                    duration_seconds: activeDealDurationSeconds || 0,
                                    next_action: 'Retry Call',
                                    deal_stage: 'Qualification',
                                    sentiment: 'Neutral'
                                })
                            }).catch(function(){});

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
                try {
                    activeTwilioCall.disconnect();
                } catch(e) {}
                activeTwilioCall = null;
            }
            if (activeTwilioPollTimer) {
                clearInterval(activeTwilioPollTimer);
                activeTwilioPollTimer = null;
            }
            if (activeDealCallInterval) {
                clearInterval(activeDealCallInterval);
                activeDealCallInterval = null;
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

        // DISCONNECT CALL & ANALYZE WITH GEMINI AI
        $('#btnDisconnectAndAnalyze').on('click', function() {
            if (!activeDealCallId) return;

            var currentCallSid = activeTwilioCallSid;
            var csrfToken = $('meta[name="csrf-token"]').attr('content');

            // 1. Forcefully terminate telephony call leg immediately so customer's phone disconnects
            var terminateUrl = "{{ url('crm/deals') }}/" + activeDealCallId + "/call-ai/terminate-call";
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
            btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Gemini AI Transcribing & Processing...');

            var analyzeUrl = "{{ url('crm/deals') }}/" + activeDealCallId + "/call-ai/analyze";

            var formData = new FormData();
            formData.append('call_notes', notes);
            formData.append('duration_seconds', activeDealDurationSeconds || 60);
            formData.append('dialed_number', dialedPhone);
            if (currentCallSid) {
                formData.append('call_sid', currentCallSid);
            }

            if (audioChunks && audioChunks.length > 0) {
                var audioBlob = new Blob(audioChunks, { type: 'audio/webm' });
                formData.append('audio_file', audioBlob, 'deal_call_recording.webm');
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

                    // Populate Gemini AI Confirmation Popup
                    $('#aiModalDealId').val(activeDealCallId);
                    $('#aiModalCallDuration').val(activeDealDurationSeconds);
                    $('#aiModalDialedPhoneVal').val(dialedPhone);
                    $('#aiModalRecordingUrl').val(res.recording_url || '');
                    $('#aiModalDealName').text(activeDealTitle + (activeDealCompany ? ' (' + activeDealCompany + ')' : ''));
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
                    $('#aiModalDealStage').val(res.suggested_deal_stage || 'Qualification');
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

        // APPROVE & SCHEDULE ACTIVITY IN DEAL
        $('#btnApproveAndScheduleDeal').on('click', function(e) {
            e.preventDefault();
            var dealId = $('#aiModalDealId').val();
            if (!dealId) return;

            var payload = {
                summary: $('#aiModalDiscussionSummary').val(),
                transcript: $('#aiModalTranscript').val(),
                next_activity_type: $('#aiModalNextActivityType').val(),
                next_action: $('#aiModalNextActivityTitle').val() || $('#aiModalNextActivityType').val(),
                next_followup_date: $('#aiModalNextFollowupDate').val(),
                deal_stage: $('#aiModalDealStage').val(),
                sentiment: $('#aiModalSentimentBadge').text(),
                followup_message_preview: $('#aiModalFollowupMessage').val(),
                recording_url: $('#aiModalRecordingUrl').val() || null,
                duration_seconds: parseInt($('#aiModalCallDuration').val()) || activeDealDurationSeconds
            };

            var btn = $(this);
            var origHtml = btn.html();
            btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Applying to Deal...');

            var csrfToken = $('meta[name="csrf-token"]').attr('content');
            var confirmUrl = "{{ url('crm/deals') }}/" + dealId + "/call-ai/confirm";

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
                            title: 'Deal Call Saved & Activity Scheduled!',
                            html: '<p class="fs-12 text-muted mb-2">' + (res.message || 'Call logged & next follow-up scheduled to Deal successfully.') + '</p>' +
                                  (payload.next_followup_date ? '<div class="badge bg-soft-primary text-primary fs-11 p-2">📅 Next Follow-up: ' + payload.next_followup_date.replace('T', ' ') + '</div>' : ''),
                            confirmButtonColor: '#4f46e5',
                            confirmButtonText: 'Great, Done!'
                        }).then(function() {
                            window.location.reload();
                        });
                    } else {
                        alert(res.message || 'Deal call and follow-up saved successfully!');
                        window.location.reload();
                    }
                } else {
                    alert(res.message || 'Error saving deal follow-up.');
                }
            })
            .catch(function(err) {
                btn.prop('disabled', false).html(origHtml);
                console.error('Save error:', err);
                alert('An error occurred while saving the activity.');
            });
        });
    });
</script>
@endpush
