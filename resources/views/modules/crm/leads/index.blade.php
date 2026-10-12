@extends('layouts.duralux')

@section('title', __('crm.crm_leads') . ' | SaaS ERP')
@section('page-title', __('crm.crm_leads'))
@section('breadcrumb', __('crm.crm_leads'))

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
    <style>
        /* Softphone Floating Widget Light & Dark Mode Styles */
        .crm-softphone-dock {
            background: #ffffff;
            border: 1px solid rgba(226, 232, 240, 0.9);
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
            border-bottom: 1px solid #334155 !important;
        }
        [data-bs-theme="dark"] .crm-softphone-body,
        html.app-skin-dark .crm-softphone-body {
            background: #0f172a !important;
        }
        [data-bs-theme="dark"] .crm-header-action-btn,
        html.app-skin-dark .crm-header-action-btn {
            background: #334155 !important;
            color: #cbd5e1 !important;
        }
        [data-bs-theme="dark"] .crm-header-action-btn:hover,
        html.app-skin-dark .crm-header-action-btn:hover {
            background: #475569 !important;
            color: #ffffff !important;
        }
        [data-bs-theme="dark"] .crm-header-close-btn,
        html.app-skin-dark .crm-header-close-btn {
            background: rgba(239, 68, 68, 0.2) !important;
            color: #f87171 !important;
        }
        [data-bs-theme="dark"] .crm-header-close-btn:hover,
        html.app-skin-dark .crm-header-close-btn:hover {
            background: rgba(239, 68, 68, 0.35) !important;
            color: #fca5a5 !important;
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
    </style>
@endpush

@push('scripts')
    <script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/select2-active.min.js') }}"></script>
@endpush

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <x-ui.import-export-dropdown 
            type="leads" 
            :can-import="true" 
            :can-download-template="true" 
            download-template-route="{{ route('crm.leads.downloadSample') }}" 
            import-modal-target="#importLeadsModal" 
            export-route="{{ route('crm.leads.export') }}" />
        <x-ui.button href="{{ route('crm.leads.create') }}" variant="primary" icon="feather-plus">
            {{ __('crm.add_new_call_lead') }}
        </x-ui.button>
    </div>
@endsection

@section('content')

    @php
        $sortBy = request('sort_by', 'call_date');
        $sortOrder = request('sort_order', 'desc');
    @endphp

    <div class="erp-single-panel">

        {{-- 1. Header: Title & Actions --}}
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <h5 class="fw-bold text-dark mb-0">{{ __('crm.leads_listing') }}</h5>
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <!-- Outside Search Box (HRMS Style) -->
                    <form method="GET" action="{{ route('crm.leads.index') }}" class="d-flex align-items-center bg-light border rounded px-2.5 py-0.5 me-1" style="height: 34px; min-width: 240px;">
                        @foreach(request()->except(['search', 'page']) as $k => $v)
                            @if(is_scalar($v) && $v !== '')
                                <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                            @endif
                        @endforeach
                        <i class="feather-search text-muted me-2" style="font-size: 13px;"></i>
                        <input type="text" name="search" class="form-control border-0 bg-transparent p-0 fs-12 text-dark" placeholder="{{ __('crm.search_placeholder_leads') }}" value="{{ request('search') }}" style="box-shadow: none; outline: none;">
                        @if(request('search'))
                            <a href="{{ route('crm.leads.index', request()->except(['search', 'page'])) }}" class="text-muted text-decoration-none ms-1" title="Clear Search">
                                <i class="feather-x fs-12"></i>
                            </a>
                        @endif
                    </form>

                    <x-ui.view-switcher />

                    <!-- Normal Toolbar (Sort, Filter) -->
                    <div id="normal-toolbar" class="d-flex gap-2 align-items-center">
                        <x-ui.sort-dropdown :label="__('crm.sort')">
                            <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'call_date', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'call_date' && $sortOrder === 'desc' ? 'active' : '' }}">
                                <span>{{ __('crm.sort_call_date_latest') }}</span>
                            </a>
                            <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'call_date', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'call_date' && $sortOrder === 'asc' ? 'active' : '' }}">
                                <span>{{ __('crm.sort_call_date_oldest') }}</span>
                            </a>
                            <div class="dropdown-divider"></div>
                            <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'company_name', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'company_name' && $sortOrder === 'asc' ? 'active' : '' }}">
                                <span>{{ __('crm.sort_company_name_az') }}</span>
                            </a>
                            <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'company_name', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'company_name' && $sortOrder === 'desc' ? 'active' : '' }}">
                                <span>{{ __('crm.sort_company_name_za') }}</span>
                            </a>
                            <div class="dropdown-divider"></div>
                            <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'duplicates', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'duplicates' ? 'active' : '' }}">
                                <span class="text-danger fw-semibold"><i class="feather-copy me-1"></i>{{ __('crm.group_duplicates') }}</span>
                            </a>
                            <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'expected_amount', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'expected_amount' && $sortOrder === 'desc' ? 'active' : '' }}">
                                <span>{{ __('crm.sort_expected_amount_desc') }}</span>
                            </a>
                            <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'expected_amount', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'expected_amount' && $sortOrder === 'asc' ? 'active' : '' }}">
                                <span>{{ __('crm.sort_expected_amount_asc') }}</span>
                            </a>
                        </x-ui.sort-dropdown>

                        <form method="GET" action="{{ route('crm.leads.index') }}" class="d-inline">
                            <x-ui.filter :label="__('crm.filter')" offset="0, 5">
                                <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('crm.filter_options') }}</h6>
                                <div class="mb-3">
                                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('crm.search_keywords') }}</label>
                                    <x-ui.odoo-form-ui type="input" name="search" :placeholder="__('crm.search_placeholder_leads')" value="{{ request('search') }}" />
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('crm.priority') }}</label>
                                    <x-ui.odoo-form-ui type="select" name="priority">
                                        <option value="">{{ __('crm.all_priorities') }}</option>
                                        <option value="Low" {{ request('priority') === 'Low' ? 'selected' : '' }}>{{ __('crm.priorities.Low') }}</option>
                                        <option value="Medium" {{ request('priority') === 'Medium' ? 'selected' : '' }}>{{ __('crm.priorities.Medium') }}</option>
                                        <option value="High" {{ request('priority') === 'High' ? 'selected' : '' }}>{{ __('crm.priorities.High') }}</option>
                                        <option value="Urgent" {{ request('priority') === 'Urgent' ? 'selected' : '' }}>{{ __('crm.priorities.Urgent') }}</option>
                                    </x-ui.odoo-form-ui>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('crm.segment') }}</label>
                                    <x-ui.odoo-form-ui type="select" name="segment">
                                        <option value="">{{ __('crm.all_segments') }}</option>
                                        <option value="SME" {{ request('segment') === 'SME' ? 'selected' : '' }}>{{ __('crm.segments.SME') }}</option>
                                        <option value="Mid-Market" {{ request('segment') === 'Mid-Market' ? 'selected' : '' }}>{{ __('crm.segments.Mid-Market') }}</option>
                                        <option value="Enterprise" {{ request('segment') === 'Enterprise' ? 'selected' : '' }}>{{ __('crm.segments.Enterprise') }}</option>
                                    </x-ui.odoo-form-ui>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('crm.status') }}</label>
                                    <x-ui.odoo-form-ui type="select" name="status">
                                        <option value="">{{ __('crm.all_statuses') }}</option>
                                        @foreach($leadStatuses as $ls)
                                            @php
                                                $lsDisplayName = \Illuminate\Support\Facades\Lang::has('crm.statuses.' . $ls->name) ? __('crm.statuses.' . $ls->name) : $ls->name;
                                            @endphp
                                            <option value="{{ $ls->name }}" {{ request('status') === $ls->name ? 'selected' : '' }}>
                                                {{ $lsDisplayName }}
                                            </option>
                                        @endforeach
                                    </x-ui.odoo-form-ui>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('crm.lead_owner') }}</label>
                                    <x-ui.odoo-form-ui type="select" name="lead_owner_id">
                                        <option value="">{{ __('crm.all_lead_owners') }}</option>
                                        @foreach($users as $u)
                                            <option value="{{ $u->id }}" {{ (string)request('lead_owner_id') === (string)$u->id ? 'selected' : '' }}>{{ $u->name }} ({{ $u->email }})</option>
                                        @endforeach
                                    </x-ui.odoo-form-ui>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('crm.quotation_status') }}</label>
                                    <x-ui.odoo-form-ui type="select" name="quotation_status">
                                        <option value="">{{ __('crm.all_leads') }}</option>
                                        <option value="with_quotation" {{ request('quotation_status') === 'with_quotation' ? 'selected' : '' }}>{{ __('crm.with_quotation') }}</option>
                                        <option value="without_quotation" {{ request('quotation_status') === 'without_quotation' ? 'selected' : '' }}>{{ __('crm.without_quotation') }}</option>
                                    </x-ui.odoo-form-ui>
                                </div>
                                <div class="row g-2 mb-3">
                                    <div class="col-6">
                                        <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('crm.date_from') }}</label>
                                        <x-ui.odoo-form-ui type="input" inputType="date" name="date_from" value="{{ request('date_from') ?? request('start_date') }}" />
                                    </div>
                                    <div class="col-6">
                                        <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('crm.date_to') }}</label>
                                        <x-ui.odoo-form-ui type="input" inputType="date" name="date_to" value="{{ request('date_to') ?? request('end_date') }}" />
                                    </div>
                                </div>
                                <div class="d-flex gap-2 justify-content-end mt-4">
                                    <a href="{{ route('crm.leads.index') }}" class="btn btn-sm btn-light border">{{ __('crm.reset') }}</a>
                                    <button type="submit" class="btn btn-sm btn-primary">{{ __('crm.apply_filters') }}</button>
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
                            <button type="button" class="dropdown-item text-secondary" onclick="clearLeadSelections()">
                                <i class="feather-x me-2 text-secondary"></i> {{ __('crm.deselect') }}
                            </button>
                        </x-ui.bulk-actions>
                    </div>
                </div>
            </div>

            {{-- 2. Status Tabs Strip --}}
            @php
                $activeStatus = request('status');
                $isDuplicatesOnly = request('duplicates_only') === '1';
                $isAll = !$activeStatus && !$isDuplicatesOnly;
            @endphp
            <div class="mb-2" style="border-bottom: 2px solid #e2e8f0;">
                <div class="d-flex align-items-center gap-1 overflow-x-auto" style="scrollbar-width: thin;">
                    <a href="{{ request()->fullUrlWithQuery(['status' => null, 'duplicates_only' => null]) }}"
                       class="crm-status-tab {{ $isAll ? 'active' : '' }}">
                        {{ __('crm.tabs.all') }} ({{ $totalLeadsCount ?? $leads->total() }})
                    </a>
                    @foreach($leadStatuses as $ls)
                        @php
                            $statusKey = strtolower($ls->name);
                            $lsDisplayName = \Illuminate\Support\Facades\Lang::has('crm.statuses.' . $ls->name) ? __('crm.statuses.' . $ls->name) : $ls->name;
                            $tabLabel = match($statusKey) {
                                'new' => __('crm.tabs.untouched'),
                                default => mb_strtoupper($lsDisplayName),
                            };
                        @endphp
                        <a href="{{ request()->fullUrlWithQuery(['status' => $ls->name, 'duplicates_only' => null]) }}"
                           class="crm-status-tab {{ $activeStatus === $ls->name ? 'active' : '' }}">
                            {{ $tabLabel }} ({{ $statusCounts[$ls->name] ?? 0 }})
                        </a>
                    @endforeach
                    <a href="{{ request()->fullUrlWithQuery(['duplicates_only' => '1', 'status' => null]) }}"
                       class="crm-status-tab crm-status-tab--duplicates {{ $isDuplicatesOnly ? 'active' : '' }}">
                        <i class="feather-copy me-1 fs-11"></i>{{ __('crm.tabs.duplicates') }} ({{ $duplicatesCount ?? 0 }})
                    </a>
                </div>
            </div>

        {{-- 3. Table --}}
        <div class="table-responsive">
                <x-ui.odoo-form-ui type="table" id="leadsTable" class="mb-0">
                    <thead>
                        <tr>
                            <th style="width: 35px;" class="text-center">
                                <input type="checkbox" class="form-check-input" id="selectAllLeadsCheckbox" title="Select All Leads">
                            </th>
                            <th style="width: 11%;">{{ __('crm.call_date_time') }}</th>
                            <th style="width: 19%;">{{ __('crm.lead_company') }}</th>
                            <th style="width: 14%;">{{ __('crm.lead_owner') }}</th>
                            <th style="width: 17%;">{{ __('crm.phone_email') }}</th>
                            <th style="width: 12%;" class="text-end pe-3">{{ __('crm.value_est_sale') }}</th>
                            <th style="width: 18%;">{{ __('crm.details') }}</th>
                            <th style="width: 9%;">{{ __('crm.status') }}</th>
                            <th style="width: 5%;" class="text-end pe-3">{{ __('crm.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($leads as $lead)
                            <tr id="leadRow_{{ $lead->id }}">
                                <td class="text-center">
                                    <input type="checkbox" class="form-check-input lead-select-checkbox" 
                                           value="{{ $lead->id }}" 
                                           data-lead-name="{{ e($lead->company_name ?: $lead->contact_person ?: ('Lead #'.$lead->id)) }}"
                                           data-is-converted="{{ $lead->crm_deal_id || in_array(strtolower($lead->status ?? ''), ['won']) ? '1' : '0' }}">
                                </td>
                                <td>
                                    @php
                                        $pendingFollowup = $lead->followups ? $lead->followups->where('status', 'Pending')->sortBy('followup_date')->first() : null;
                                        $lastInteraction = $lead->followups ? $lead->followups->whereIn('status', ['Completed', 'Not Answering', 'Not Connected'])->sortByDesc('followup_date')->first() : null;
                                    @endphp
                                    
                                    <div class="d-flex flex-column gap-1">
                                        <!-- Initial Call / Created Date -->
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-text avatar-xs bg-soft-primary text-primary me-1.5 rounded-circle shadow-2xs d-flex align-items-center justify-content-center" style="width: 22px; height: 22px; font-size: 10px;">
                                                <i class="feather-calendar"></i>
                                            </div>
                                            <div>
                                                <span class="d-block fw-semibold text-dark fs-12 leading-tight">{{ $lead->call_date ? $lead->call_date->format('d/m/Y') : 'N/A' }}</span>
                                                <span class="text-muted fs-10">{{ $lead->call_date ? $lead->call_date->format('h:i A') : '' }}</span>
                                            </div>
                                        </div>

                                        <!-- Odoo-Style Next Activity Smart Badge -->
                                        @if ($pendingFollowup && $pendingFollowup->followup_date)
                                            @php
                                                $fDate = \Carbon\Carbon::parse($pendingFollowup->followup_date);
                                                $isOverdue = $fDate->lt(now()->startOfDay());
                                                $isToday = $fDate->isToday();
                                                $isTomorrow = $fDate->isTomorrow();
                                                
                                                $pillClass = $isOverdue ? 'bg-danger-subtle text-danger border-danger-subtle' : 
                                                            ($isToday ? 'bg-warning-subtle text-warning-emphasis border-warning-subtle' : 'bg-success-subtle text-success border-success-subtle');
                                                
                                                $timeLabel = $isOverdue ? ('Overdue (' . $fDate->format('d M') . ')') :
                                                            ($isToday ? ('Today ' . $fDate->format('h:i A')) :
                                                            ($isTomorrow ? ('Tomorrow ' . $fDate->format('h:i A')) : $fDate->format('d M, h:i A')));
                                                
                                                $actIcon = str_contains(strtolower($pendingFollowup->title ?? ''), 'meeting') ? 'feather-video' :
                                                          (str_contains(strtolower($pendingFollowup->title ?? ''), 'demo') ? 'feather-monitor' : 'feather-phone-call');

                                                $statusBadge = $isOverdue 
                                                    ? "<span class='badge bg-danger text-white fs-9 px-1.5 py-0.5 rounded-pill'>Overdue</span>" 
                                                    : ($isToday 
                                                        ? "<span class='badge bg-warning text-dark fs-9 px-1.5 py-0.5 rounded-pill'>Due Today</span>" 
                                                        : "<span class='badge bg-success text-white fs-9 px-1.5 py-0.5 rounded-pill'>Upcoming</span>");

                                                $cleanNote = $pendingFollowup->notes;
                                                $cleanNote = preg_replace('/^Scheduled action:[^\n]+\n/i', '', $cleanNote);
                                                $cleanNote = preg_replace('/^Context from call:\s*/i', '', $cleanNote);
                                                $cleanNote = trim((string)$cleanNote);
                                                           
                                                $tooltipHtml = "<div class='crm-activity-tooltip text-start'>" .
                                                    "<div class='d-flex align-items-center justify-content-between gap-2 pb-1.5 mb-2 border-bottom border-light-subtle'>" .
                                                        "<div class='d-flex align-items-center gap-1.5 overflow-hidden'>" .
                                                            "<span class='avatar-text avatar-xs bg-soft-primary text-primary rounded-circle d-inline-flex align-items-center justify-content-center' style='width: 20px; height: 20px; font-size: 10px;'><i class='{$actIcon}'></i></span>" .
                                                            "<span class='fw-bold text-dark fs-12 text-truncate'>" . e($pendingFollowup->title ?: 'Follow-up Activity') . "</span>" .
                                                        "</div>" .
                                                        "{$statusBadge}" .
                                                    "</div>" .
                                                    "<div class='d-flex align-items-center gap-1.5 fs-11 mb-2'>" .
                                                        "<i class='feather-calendar text-muted fs-11'></i>" .
                                                        "<span class='text-muted'>Scheduled:</span>" .
                                                        "<strong class='text-dark'>" . $fDate->format('d M Y, h:i A') . "</strong>" .
                                                    "</div>" .
                                                    (!empty($cleanNote) ? (
                                                        "<div class='mb-2'>" .
                                                            "<div class='crm-tooltip-note p-2 rounded bg-light border border-light-subtle'>" .
                                                                "<div class='text-muted fs-10 fw-semibold mb-0.5 d-flex align-items-center gap-1'><i class='feather-message-square fs-9 text-primary'></i> Note / Context:</div>" .
                                                                "<div class='text-dark fs-11' style='line-height: 1.4; word-break: break-word;'>" . e(\Illuminate\Support\Str::limit($cleanNote, 160)) . "</div>" .
                                                            "</div>" .
                                                        "</div>"
                                                    ) : "") .
                                                    ($lastInteraction && $lastInteraction->followup_date ? (
                                                        "<div class='pt-1.5 mt-1 border-top border-light-subtle d-flex align-items-center justify-content-between text-muted fs-10'>" .
                                                            "<span><i class='feather-check-circle text-success me-1'></i>Last Touch:</span>" .
                                                            "<strong class='text-dark'>" . \Carbon\Carbon::parse($lastInteraction->followup_date)->diffForHumans() . "</strong>" .
                                                        "</div>"
                                                    ) : "") .
                                                    "</div>";
                                            @endphp
                                            <div class="mt-0.5">
                                                <span class="badge {{ $pillClass }} border font-monospace px-1.5 py-0.5 fs-10 d-inline-flex align-items-center gap-1 cursor-pointer"
                                                      style="letter-spacing: -0.2px; max-width: 100%;"
                                                      data-bs-toggle="tooltip"
                                                      data-bs-html="true"
                                                      data-bs-placement="top"
                                                      data-bs-custom-class="custom-white-tooltip"
                                                      title="{{ $tooltipHtml }}">
                                                    <i class="{{ $actIcon }} fs-10"></i>
                                                    <span class="text-truncate">{{ $timeLabel }}</span>
                                                </span>
                                            </div>
                                        @elseif ($lastInteraction && $lastInteraction->followup_date)
                                            @php
                                                $lDate = \Carbon\Carbon::parse($lastInteraction->followup_date);
                                                $cleanLastNote = $lastInteraction->notes;
                                                $cleanLastNote = preg_replace('/^Scheduled action:[^\n]+\n/i', '', $cleanLastNote);
                                                $cleanLastNote = preg_replace('/^Context from call:\s*/i', '', $cleanLastNote);
                                                $cleanLastNote = trim((string)$cleanLastNote);

                                                $lastTooltip = "<div class='crm-activity-tooltip text-start'>" .
                                                    "<div class='d-flex align-items-center justify-content-between gap-2 pb-1.5 mb-2 border-bottom border-light-subtle'>" .
                                                        "<div class='d-flex align-items-center gap-1.5'>" .
                                                            "<span class='avatar-text avatar-xs bg-soft-success text-success rounded-circle d-inline-flex align-items-center justify-content-center' style='width: 20px; height: 20px; font-size: 10px;'><i class='feather-check-circle'></i></span>" .
                                                            "<span class='fw-bold text-dark fs-12'>Last Interaction</span>" .
                                                        "</div>" .
                                                        "<span class='badge bg-light text-muted border fs-9 px-1.5 py-0.5 rounded-pill'>" . $lDate->diffForHumans() . "</span>" .
                                                    "</div>" .
                                                    "<div class='d-flex align-items-center gap-1.5 fs-11 mb-2'>" .
                                                        "<i class='feather-calendar text-muted fs-11'></i>" .
                                                        "<span class='text-muted'>Date:</span>" .
                                                        "<strong class='text-dark'>" . $lDate->format('d M Y, h:i A') . "</strong>" .
                                                    "</div>" .
                                                    (!empty($cleanLastNote) ? (
                                                        "<div>" .
                                                            "<div class='crm-tooltip-note p-2 rounded bg-light border border-light-subtle'>" .
                                                                "<div class='text-muted fs-10 fw-semibold mb-0.5 d-flex align-items-center gap-1'><i class='feather-file-text fs-9 text-success'></i> Discussion Summary:</div>" .
                                                                "<div class='text-dark fs-11' style='line-height: 1.4; word-break: break-word;'>" . e(\Illuminate\Support\Str::limit($cleanLastNote, 160)) . "</div>" .
                                                            "</div>" .
                                                        "</div>"
                                                    ) : "") .
                                                    "</div>";
                                            @endphp
                                            <div class="mt-0.5">
                                                <span class="badge bg-light text-muted border px-1.5 py-0.5 fs-10 d-inline-flex align-items-center gap-1 cursor-pointer"
                                                      data-bs-toggle="tooltip"
                                                      data-bs-html="true"
                                                      data-bs-placement="top"
                                                      data-bs-custom-class="custom-white-tooltip"
                                                      title="{{ $lastTooltip }}">
                                                    <i class="feather-check fs-9 text-success"></i>
                                                    <span>Last: {{ $lDate->diffForHumans() }}</span>
                                                </span>
                                            </div>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center flex-wrap gap-1">
                                        <span class="fw-bold text-dark">{{ $lead->company_name }}</span>
                                        <span class="badge bg-light text-primary border font-monospace px-1.5 py-0.5 fs-11">{{ $lead->lead_number ?: ('LD-' . str_pad($lead->id, 4, '0', STR_PAD_LEFT)) }}</span>
                                        @if(request('duplicates_only') === '1' || request('sort_by') === 'duplicates')
                                            @if(!empty($lead->is_duplicate))
                                                <span class="badge bg-warning-subtle text-warning border border-warning-subtle font-monospace px-2 py-0.5 fs-11 ms-1 d-inline-flex align-items-center" 
                                                      data-bs-toggle="tooltip" 
                                                      data-bs-placement="top" 
                                                      data-bs-html="true" 
                                                      data-bs-custom-class="custom-white-tooltip" 
                                                      title="<div class='text-start'><div class='fw-bold text-dark fs-12 mb-1'><i class='feather-copy text-warning me-1'></i>Duplicate Lead</div><div class='text-muted fs-11 mb-1'>Duplicate of Lead <strong class='text-dark'>#{{ $lead->duplicate_of_number ?: ('LD-' . str_pad($lead->duplicate_of_id, 4, '0', STR_PAD_LEFT)) }}</strong></div><div class='text-muted fs-11'>Reason: <strong class='text-dark'>{{ $lead->duplicate_reason }}</strong></div></div>">
                                                    <a href="{{ route('crm.leads.show', $lead->duplicate_of_id) }}" class="text-warning text-decoration-none d-inline-flex align-items-center" onclick="event.stopPropagation();">
                                                        <i class="feather-copy me-1 fs-11"></i>Duplicate of #{{ $lead->duplicate_of_number ?: ('LD-' . str_pad($lead->duplicate_of_id, 4, '0', STR_PAD_LEFT)) }}
                                                    </a>
                                                </span>
                                            @elseif(!empty($lead->is_original))
                                                <span class="badge bg-success-subtle text-success border border-success-subtle font-monospace px-2 py-0.5 fs-11 ms-1 d-inline-flex align-items-center" 
                                                      data-bs-toggle="tooltip" 
                                                      data-bs-placement="top" 
                                                      title="Original Lead record. Subsequent matching leads are marked as duplicates of this lead.">
                                                    <i class="feather-check-circle me-1 fs-11"></i>ORIGINAL LEAD
                                                </span>
                                            @endif
                                        @else
                                            @if(!empty($lead->is_duplicate))
                                                <span class="duplicate-indicator ms-1" 
                                                      data-bs-toggle="tooltip" 
                                                      data-bs-placement="top" 
                                                      data-bs-html="true" 
                                                      data-bs-custom-class="custom-white-tooltip" 
                                                      title="<div class='text-start'><div class='fw-bold text-dark fs-12 mb-1'><i class='feather-copy text-warning me-1'></i>Duplicate Lead</div><div class='text-muted fs-11 mb-1'>Duplicate of Lead <strong class='text-dark'>#{{ $lead->duplicate_of_number ?: ('LD-' . str_pad($lead->duplicate_of_id, 4, '0', STR_PAD_LEFT)) }}</strong></div><div class='text-muted fs-11'>Reason: <strong class='text-dark'>{{ $lead->duplicate_reason }}</strong></div></div>">
                                                    <a href="{{ route('crm.leads.show', $lead->duplicate_of_id) }}" class="text-warning text-decoration-none d-inline-flex align-items-center p-1 rounded hover-bg-warning-soft" onclick="event.stopPropagation();">
                                                        <i class="feather-copy fs-13"></i>
                                                    </a>
                                                </span>
                                            @endif
                                        @endif
                                    </div>
                                    <span class="text-muted fs-11"><i class="feather-user me-1 fs-10 text-primary"></i>{{ $lead->contact_person ?: 'N/A' }}</span>
                                </td>
                                <td id="leadOwnerCell_{{ $lead->id }}">
                                    <div class="d-flex align-items-center cursor-pointer p-1 rounded" 
                                         onclick="openSingleAssignDrawer({{ $lead->id }}, '{{ e($lead->company_name ?: $lead->contact_person ?: ('Lead #'.$lead->id)) }}', '{{ $lead->lead_owner_id }}', '{{ e($lead->owner?->name ?: __('crm.unassigned')) }}')"
                                         title="{{ $lead->owner ? __('crm.change_owner') : __('crm.assign_owner') }}"
                                         style="transition: background-color 0.15s ease;">
                                        <div class="rounded-circle me-2 d-flex align-items-center justify-content-center text-white fw-bold shadow-xs owner-avatar-circle" 
                                             style="width: 28px; height: 28px; background-color: {{ $lead->owner ? '#1e40af' : '#64748b' }}; font-size: 11px; flex-shrink: 0;"
                                             title="{{ $lead->owner?->name ?: __('crm.unassigned') }}">
                                            {{ strtoupper(substr($lead->owner?->name ?: 'U', 0, 1)) }}
                                        </div>
                                        <div>
                                            @if($lead->owner)
                                                <span class="d-block fw-semibold text-dark fs-12 owner-name-text" style="line-height: 1.2;">{{ $lead->owner->name }}</span>
                                                <span class="text-muted fs-10 d-block owner-email-text">{{ $lead->owner->email ?: '—' }}</span>
                                            @else
                                                <span class="badge bg-soft-warning text-warning border border-warning-subtle fs-10 fw-semibold d-inline-flex align-items-center gap-1 py-0.5 px-2">
                                                    <i class="feather-user-plus fs-9"></i> {{ __('crm.unassigned') }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    @php
                                        $leadNumbers = [];
                                        if (!empty($lead->phone)) {
                                            $leadNumbers[] = [
                                                'type' => 'Contact Person',
                                                'label' => ($lead->contact_person ? $lead->contact_person . ' (Contact Person)' : 'Contact Person') . ': ' . $lead->phone,
                                                'number' => $lead->phone,
                                                'is_primary' => true,
                                            ];
                                        }
                                        if (!empty($lead->additional_contacts) && is_array($lead->additional_contacts)) {
                                            foreach ($lead->additional_contacts as $idx => $ac) {
                                                if (!empty($ac['phone'])) {
                                                    $leadNumbers[] = [
                                                        'type' => 'Additional Contact',
                                                        'label' => (!empty($ac['name']) ? $ac['name'] . ' (Additional Contact)' : ('Contact #' . ($idx+1))) . ': ' . $ac['phone'],
                                                        'number' => $ac['phone'],
                                                        'is_primary' => false,
                                                    ];
                                                }
                                            }
                                        }
                                        if (!empty($lead->company_phone)) {
                                            $leadNumbers[] = [
                                                'type' => 'Company Phone',
                                                'label' => ($lead->company_name ? $lead->company_name . ' (Company Phone)' : 'Company Phone') . ': ' . $lead->company_phone,
                                                'number' => $lead->company_phone,
                                                'is_primary' => false,
                                            ];
                                        }
                                        $primaryDisplayPhone = $leadNumbers[0]['number'] ?? ($lead->phone ?: '');
                                        $numbersJson = json_encode($leadNumbers);
                                    @endphp

                                    @if ($primaryDisplayPhone)
                                        <div class="d-flex align-items-center gap-1.5 mb-1">
                                            <a href="javascript:void(0)" 
                                               class="text-dark fw-semibold fs-12 text-decoration-none btn-crm-click-to-call hover-primary"
                                               data-lead-id="{{ $lead->id }}"
                                               data-lead-name="{{ e($lead->contact_person ?: $lead->company_name ?: ('Lead #' . $lead->id)) }}"
                                               data-lead-company="{{ e($lead->company_name) }}"
                                               data-lead-phone="{{ e($primaryDisplayPhone) }}"
                                               data-lead-numbers="{{ e($numbersJson) }}"
                                               data-lead-status="{{ e($lead->status ?: 'New') }}">
                                                {{ $primaryDisplayPhone }}
                                            </a>
                                            @if (count($leadNumbers) > 1)
                                                <span class="badge bg-soft-info text-info fs-9" title="{{ count($leadNumbers) }} phone numbers available">+{{ count($leadNumbers) - 1 }}</span>
                                            @endif
                                        </div>
                                    @else
                                        <div class="d-flex align-items-center gap-1.5 mb-1">
                                            <span class="text-muted fs-11">—</span>
                                        </div>
                                    @endif

                                    @if ($lead->email)
                                        <span class="text-muted fs-11 d-block"><i class="feather-mail fs-11 me-1 text-muted"></i>{{ $lead->email }}</span>
                                    @endif
                                    @php
                                        $addlContacts = $lead->additional_contacts ?: [];
                                        $firstAddl = $addlContacts[0] ?? null;
                                    @endphp
                                    @if (!empty($firstAddl))
                                        <small class="text-secondary d-block fs-10 mt-1" title="Additional Contact">
                                            <i class="feather-user-plus me-1 text-primary"></i>
                                            {{ !empty($firstAddl['name']) ? $firstAddl['name'] . ': ' : '' }}
                                            {{ $firstAddl['phone'] ?? ($firstAddl['email'] ?? '') }}
                                            @if(count($addlContacts) > 1)
                                                <span class="badge bg-soft-primary text-primary fs-9 ms-1">+{{ count($addlContacts) - 1 }} more</span>
                                            @endif
                                        </small>
                                    @endif
                                </td>
                                <td class="text-end pe-4">
                                    <span class="fw-bold text-dark d-block mb-1">{{ $lead->expected_amount ? format_currency($lead->expected_amount) : '—' }}</span>
                                    @if($lead->expected_sale_date)
                                        <span class="text-muted fs-11"><i class="feather-calendar me-1 fs-10 text-success"></i>{{ $lead->expected_sale_date->format('d/m/Y') }}</span>
                                    @else
                                        <span class="text-muted fs-11">—</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="d-flex flex-column gap-1 fs-11">
                                        @if($lead->source && !in_array($lead->source, ['Select an Option', 'Select an option', 'Select Option'], true))
                                            <div><span class="text-muted">{{ __('crm.source') }}:</span> <span class="fw-semibold text-dark">{{ \Illuminate\Support\Facades\Lang::has('crm.sources.' . $lead->source) ? __('crm.sources.' . $lead->source) : $lead->source }}</span></div>
                                        @endif
                                        @php
                                            $currentPriority = ($lead->priority && $lead->priority !== 'Select an Option') ? $lead->priority : '';
                                            $starsCount = match($currentPriority) {
                                                'Low' => 1,
                                                'Medium' => 2,
                                                'High' => 3,
                                                'Urgent' => 4,
                                                default => 0,
                                            };
                                            $starLabels = [1 => 'Low', 2 => 'Medium', 3 => 'High', 4 => 'Urgent'];
                                            $badgeClasses = match($currentPriority) {
                                                'Low' => 'bg-soft-success text-success',
                                                'Medium' => 'bg-soft-warning text-warning',
                                                'High' => 'bg-soft-danger text-danger',
                                                'Urgent' => 'bg-danger text-white',
                                                default => 'bg-soft-secondary text-secondary',
                                            };
                                        @endphp
                                        <div class="d-flex align-items-center gap-1.5 my-0.5">
                                            <span class="text-muted fs-11">{{ __('crm.priority') }}:</span>
                                            <div class="star-rating-widget d-inline-flex align-items-center gap-1" id="starRating_{{ $lead->id }}" data-current-stars="{{ $starsCount }}" data-current-priority="{{ $currentPriority }}">
                                                @for($i = 1; $i <= 4; $i++)
                                                    @php $targetPriority = $starLabels[$i]; @endphp
                                                    <i class="feather-star star-icon {{ $i <= $starsCount ? 'active-star' : 'inactive-star' }}"
                                                       data-star="{{ $i }}"
                                                       data-priority="{{ $targetPriority }}"
                                                       data-lead-id="{{ $lead->id }}"
                                                       data-bs-toggle="tooltip"
                                                       data-bs-placement="top"
                                                       title="{{ __('crm.priorities.' . $targetPriority) }}"
                                                       onclick="updateLeadPriority({{ $lead->id }}, '{{ $targetPriority }}', this)"></i>
                                                @endfor
                                            </div>
                                            <span class="badge fs-10 ms-1 priority-badge-{{ $lead->id }} {{ $badgeClasses }}">
                                                {{ $currentPriority ? (\Illuminate\Support\Facades\Lang::has('crm.priorities.' . $currentPriority) ? __('crm.priorities.' . $currentPriority) : $currentPriority) : 'Unset' }}
                                            </span>
                                        </div>
                                        @if($lead->segment && $lead->segment !== 'Select an Option')
                                            <div><span class="text-muted">{{ __('crm.segment') }}:</span> <span class="fw-semibold text-dark">{{ \Illuminate\Support\Facades\Lang::has('crm.segments.' . $lead->segment) ? __('crm.segments.' . $lead->segment) : $lead->segment }}</span></div>
                                        @endif
                                        @if((!$lead->source || $lead->source === 'Select an Option') && (!$lead->priority || $lead->priority === 'Select an Option') && (!$lead->segment || $lead->segment === 'Select an Option'))
                                            <span class="text-muted">—</span>
                                        @endif
                                    </div>
                                </td>

                                <td>
                                    @if ($lead->is_customer || $lead->status === 'Won')
                                        <span class="badge bg-soft-success text-success px-2 py-1 fs-11 fw-bold"><i class="feather-check-circle me-1"></i>Won</span>
                                    @else
                                        <div class="d-flex flex-column gap-1">
                                            <form action="{{ route('crm.leads.updateStatus', $lead->id) }}" method="POST" class="d-inline">
                                                @csrf
                                                @method('PATCH')
                                                <select name="status" class="form-control status-select" data-select2-selector="status" onchange="this.form.submit()" style="width: 150px;">
                                                    @foreach($leadStatuses as $ls)
                                                        @php
                                                            $statusOption = $ls->name;
                                                            $presetBgColors = ['bg-primary', 'bg-info', 'bg-teal', 'bg-success', 'bg-warning', 'bg-danger', 'bg-secondary', 'bg-dark'];
                                                            $bgClass = match(strtolower($statusOption)) {
                                                                'new' => 'bg-primary',
                                                                'qualified' => 'bg-teal',
                                                                'dealing' => 'bg-info',
                                                                'won' => 'bg-success',
                                                                'lost' => 'bg-danger',
                                                                default => (!empty($ls->color) && str_starts_with($ls->color, 'bg-') ? $ls->color : $presetBgColors[abs($ls->id ?? 0) % count($presetBgColors)]),
                                                            };
                                                        @endphp
                                                        <option value="{{ $statusOption }}" data-bg="{{ $bgClass }}" {{ ($lead->status ?: 'New') === $statusOption ? 'selected' : '' }}>
                                                            {{ $statusOption }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </form>
                                        </div>
                                    @endif
                                </td>
                                <td class="text-end pe-4">
                                    <x-ui.action-dropdown :viewUrl="route('crm.leads.show', $lead->id)">
                                        <x-slot:extraActions>
                                            <button type="button" 
                                                    class="action-dropdown-btn btn-crm-click-to-call text-success" 
                                                    title="Open Phone Dialer & AI Assistant"
                                                    data-lead-id="{{ $lead->id }}"
                                                    data-lead-name="{{ e($lead->contact_person ?: $lead->company_name ?: ('Lead #'.$lead->id)) }}"
                                                    data-lead-company="{{ e($lead->company_name) }}"
                                                    data-lead-phone="{{ e($primaryDisplayPhone) }}"
                                                    data-lead-numbers="{{ e($numbersJson) }}"
                                                    data-lead-status="{{ e($lead->status ?: 'New') }}">
                                                <i class="feather-phone-call text-success"></i>
                                            </button>
                                            <button type="button" 
                                                    class="action-dropdown-btn btn-open-followup-offcanvas" 
                                                    title="Schedule Activity / Log Followup" 
                                                    data-bs-toggle="offcanvas" 
                                                    data-bs-target="#leadFollowupOffcanvas"
                                                    data-lead-id="{{ $lead->id }}"
                                                    data-lead-name="{{ e($lead->company_name ?: $lead->contact_person ?: ('Lead #'.$lead->id)) }}"
                                                    data-lead-status="{{ $lead->status ?: 'New' }}"
                                                    data-lead-priority="{{ $lead->priority ?: 'Medium' }}"
                                                    data-next-followup="{{ $lead->next_followup_date ? $lead->next_followup_date->format('Y-m-d\TH:i') : '' }}">
                                                <i class="feather-calendar text-primary"></i>
                                            </button>
                                        </x-slot:extraActions>

                                        {{-- Assign / Change Owner --}}
                                        <li>
                                            <a href="javascript:void(0)" class="dropdown-item" onclick="openSingleAssignDrawer({{ $lead->id }}, '{{ e($lead->company_name ?: $lead->contact_person ?: ('Lead #'.$lead->id)) }}', '{{ $lead->lead_owner_id }}', '{{ e($lead->owner?->name ?: __('crm.unassigned')) }}')">
                                                <i class="feather-user-check me-2 text-primary fs-12"></i>{{ $lead->lead_owner_id ? __('crm.change_owner') : __('crm.assign_owner') }}
                                            </a>
                                        </li>

                                        {{-- Edit --}}
                                        @if (!in_array(strtolower($lead->status ?? ''), ['dealing', 'won']))
                                            <li>
                                                <a href="{{ route('crm.leads.show', ['lead' => $lead->id, 'edit_lead' => 1]) }}" class="dropdown-item">
                                                    <i class="feather-edit me-2 text-muted fs-12"></i>{{ __('crm.edit_lead') }}
                                                </a>
                                            </li>
                                        @endif

                                        {{-- Deal & Account options --}}
                                        @if ($lead->crm_deal_id)
                                            <li>
                                                <a href="{{ route('crm.deals.show', $lead->crm_deal_id) }}" class="dropdown-item text-success fw-semibold">
                                                    <i class="feather-git-branch me-2 text-success fs-12"></i>{{ __('crm.view_deal') }}
                                                </a>
                                            </li>
                                        @elseif (strtolower($lead->status ?: '') === 'qualified')
                                            <li>
                                                <form action="{{ route('crm.leads.qualify', $lead->id) }}" method="POST">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit" class="dropdown-item text-warning fw-bold">
                                                        <i class="feather-user-check me-2 text-warning fs-12"></i>{{ __('crm.convert_to_deal') }}
                                                    </button>
                                                </form>
                                            </li>
                                        @endif

                                        @if ($lead->crm_account_id)
                                            <li>
                                                <a href="{{ route('crm.accounts.show', $lead->crm_account_id) }}" class="dropdown-item text-primary fw-semibold">
                                                    <i class="feather-briefcase me-2 text-primary fs-12"></i>{{ __('crm.view_account') }}
                                                </a>
                                            </li>
                                        @endif

                                        @if(!empty($lead->is_duplicate))
                                            {{-- Qualify Lead (Only if Duplicate) --}}
                                            @if(($lead->status ?: 'New') !== 'Qualified' && ($lead->status ?: 'New') !== 'Won')
                                                <li>
                                                    <form action="{{ route('crm.leads.qualify', $lead->id) }}" method="POST">
                                                        @csrf
                                                        @method('PATCH')
                                                        <button type="submit" class="dropdown-item text-success fw-semibold">
                                                            <i class="feather-check-circle me-2 text-success fs-12"></i>{{ __('crm.qualify_genuine_lead') }}
                                                        </button>
                                                    </form>
                                                </li>
                                            @endif
                                            {{-- Reject & Delete Lead (Only if Duplicate) --}}
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <form action="{{ route('crm.leads.destroy', $lead->id) }}" method="POST" id="deleteLeadForm_{{ $lead->id }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="button" class="dropdown-item text-danger fw-semibold" onclick="confirmAction({ title: '{{ __('crm.reject_delete_lead') }}', message: '{{ __('crm.confirm_reject_delete_lead', ['company' => addslashes($lead->company_name), 'number' => ($lead->lead_number ?: ('LD-' . str_pad($lead->id, 4, '0', STR_PAD_LEFT)))]) }}', variant: 'danger', confirmText: '{{ __('crm.reject_delete_lead') }}', onConfirm: function() { document.getElementById('deleteLeadForm_{{ $lead->id }}').submit(); } })">
                                                        <i class="feather-x-circle me-2 text-danger fs-12"></i>{{ __('crm.reject_delete_lead') }}
                                                    </button>
                                                </form>
                                            </li>
                                        @else
                                            {{-- Regular Delete (If Not Duplicate) --}}
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <form action="{{ route('crm.leads.destroy', $lead->id) }}" method="POST" id="deleteLeadForm_{{ $lead->id }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="button" class="dropdown-item text-danger fw-semibold" onclick="confirmAction({ title: '{{ __('crm.delete_lead') }}', message: '{{ __('crm.confirm_delete_lead') }}', variant: 'danger', confirmText: '{{ __('crm.delete_lead') }}', onConfirm: function() { document.getElementById('deleteLeadForm_{{ $lead->id }}').submit(); } })">
                                                        <i class="feather-trash-2 me-2 text-danger fs-12"></i>{{ __('crm.delete_lead') }}
                                                    </button>
                                                </form>
                                            </li>
                                        @endif
                                    </x-ui.action-dropdown>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-5 text-muted">
                                    <i class="feather-users fs-1 d-block mb-3 text-light"></i>
                                    {{ __('crm.no_leads') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-ui.odoo-form-ui>
            </div>

        {{-- 4. Pagination --}}
        <div class="pt-3">
            <x-ui.pagination
                :currentPage="$leads->currentPage()"
                :totalPages="$leads->lastPage()"
                :totalResults="$leads->total()"
                :perPage="$leads->perPage()" />
        </div>
    </div>

    {{-- Smart Visual Column Mapping Import Modal (IndiaMart / Tally / Excel) --}}
    @include('modules.crm.leads.partials.smart-import-modal')
@endsection

@push('styles')
    <!-- Select2 & SweetAlert2 Theme Styles -->
    <link class="select2-css" rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link class="select2-css" rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/sweetalert2.min.css') }}">
    <style>
        /* Status Tabs */
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
            margin-bottom: -1px;
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
        .crm-status-tab--duplicates {
            color: #64748b;
        }
        .crm-status-tab--duplicates:hover {
            color: var(--bs-primary);
            background-color: color-mix(in srgb, var(--bs-primary) 6%, transparent);
        }
        .crm-status-tab--duplicates.active {
            color: var(--bs-primary);
            border-bottom-color: var(--bs-primary);
            background-color: color-mix(in srgb, var(--bs-primary) 8%, transparent);
        }

        /* Select2 compact for table */
        .select2-container--bootstrap-5 .select2-selection--single {
            padding: 2px 8px;
            height: auto;
            font-size: 11px;
            font-weight: 600;
        }
        .status-select + .select2-container {
            min-width: 125px !important;
            width: 125px !important;
        }

        /* Duplicate indicator icon */
        .duplicate-indicator {
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            padding: 2px;
            border-radius: 4px;
            transition: background-color 0.2s ease;
        }
        .duplicate-indicator:hover {
            background-color: rgba(245, 158, 11, 0.12);
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

        /* Star Rating Widget Styling */
        .star-rating-widget {
            cursor: pointer;
            user-select: none;
        }
        .star-rating-widget .star-icon {
            font-size: 13px;
            color: #cbd5e1;
            fill: transparent;
            transition: transform 0.15s ease, color 0.15s ease, fill 0.15s ease;
        }
        .star-rating-widget .star-icon.active-star {
            color: #f59e0b;
            fill: #f59e0b;
        }
        .star-rating-widget .star-icon.hovered-star {
            color: #f59e0b !important;
            fill: #f59e0b !important;
            transform: scale(1.25);
        }

        /* Spacious Table Alignment & Padding */
        #leadsTable td, #leadsTable th {
            padding: 10px 10px !important;
            vertical-align: middle !important;
        }
        #leadsTable thead th {
            font-size: 11px !important;
            font-weight: 700 !important;
            text-transform: uppercase !important;
            letter-spacing: 0.5px !important;
            color: #475569 !important;
            white-space: nowrap !important;
            border-bottom: 2px solid #cbd5e1 !important;
        }
        #leadsTable tbody tr {
            transition: background-color 0.15s ease;
        }
        #leadsTable tbody tr:hover {
            background-color: #f8fafc;
        }
        #leadsTable tbody tr.lead-row-selected {
            background-color: #f0f7ff !important;
        }
        html.app-skin-dark #leadsTable tbody tr:hover {
            background-color: #162038 !important;
        }
        html.app-skin-dark #leadsTable tbody tr.lead-row-selected {
            background-color: #1e293b !important;
        }
    </style>
@endpush

@push('scripts')
    <!-- Twilio Voice WebRTC SDK for 2-Way Live Calling -->
    <script src="https://cdn.jsdelivr.net/npm/@twilio/voice-sdk@2.11.1/dist/twilio.min.js"></script>
    <!-- Select2 & SweetAlert2 Scripts -->
    <script src="{{ asset('assets/vendors/js/sweetalert2.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/select2-active.min.js') }}"></script>
    <script>
        window.updateLeadPriority = function(leadId, priority, el) {
            var csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
            fetch("{{ url('crm/leads') }}/" + leadId + '/priority', {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({ priority: priority })
            })
            .then(r => r.json())
            .then(function(res) {
                if (res.success) {
                    var widget = document.getElementById('starRating_' + leadId);
                    var starsCount = priority === 'Low' ? 1 : (priority === 'Medium' ? 2 : (priority === 'High' ? 3 : 4));
                    if (widget) {
                        widget.setAttribute('data-current-stars', starsCount);
                        widget.setAttribute('data-current-priority', priority);

                        widget.querySelectorAll('.star-icon').forEach(function(icon) {
                            var s = parseInt(icon.getAttribute('data-star'));
                            if (s <= starsCount) {
                                icon.classList.add('active-star');
                                icon.classList.remove('inactive-star');
                            } else {
                                icon.classList.add('inactive-star');
                                icon.classList.remove('active-star');
                            }
                        });
                    }

                    var badge = document.querySelector('.priority-badge-' + leadId);
                    if (badge) {
                        badge.textContent = priority;
                        badge.className = 'badge fs-10 ms-1 priority-badge-' + leadId;
                        if (priority === 'Low')         badge.classList.add('bg-soft-success', 'text-success');
                        else if (priority === 'Medium') badge.classList.add('bg-soft-warning', 'text-warning');
                        else if (priority === 'High')   badge.classList.add('bg-soft-danger',  'text-danger');
                        else if (priority === 'Urgent') badge.classList.add('bg-danger',        'text-white');
                    }
                }
            })
            .catch(function(err) { console.error('Priority update failed:', err); });
        };

        $(function () {
            // Auto submit status forms when changed in Select2
            $('.status-select').on('change', function() {
                $(this).closest('form').submit();
            });

            // Initialize Bootstrap tooltips for duplicate indicators
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (el) {
                return new bootstrap.Tooltip(el);
            });

            // Star rating hover effect
            $(document).on('mouseenter', '.star-rating-widget .star-icon', function() {
                var hoveredStar = parseInt($(this).attr('data-star'));
                var widget = $(this).closest('.star-rating-widget');
                widget.find('.star-icon').each(function() {
                    var s = parseInt($(this).attr('data-star'));
                    if (s <= hoveredStar) {
                        $(this).addClass('hovered-star');
                    } else {
                        $(this).removeClass('hovered-star');
                    }
                });
            }).on('mouseleave', '.star-rating-widget', function() {
                $(this).find('.star-icon').removeClass('hovered-star');
            });

            // Live Search filter for the Leads table
            $('#tableSearch').on('input', function() {
                var value = $(this).val().toLowerCase().trim();
                var visibleRows = 0;
                var totalRows = 0;

                $('#leadsTable tbody tr').each(function() {
                    if ($(this).hasClass('no-search-results')) {
                        return;
                    }
                    totalRows++;
                    var rowText = $(this).text().toLowerCase();
                    if (rowText.indexOf(value) > -1) {
                        $(this).show();
                        visibleRows++;
                    } else {
                        $(this).hide();
                    }
                });

                $('#leadsTable tbody tr.no-search-results').remove();

                if (visibleRows === 0 && totalRows > 0) {
                    var noResultsText = '{{ __('crm.no_matching_leads', ['query' => '_QUERY_']) }}'.replace('_QUERY_', value);
                    $('#leadsTable tbody').append(
                        '<tr class="no-search-results"><td colspan="9" class="text-center py-4 text-muted"><i class="feather-search fs-3 d-block mb-2 text-light"></i>' + noResultsText + '</td></tr>'
                    );
                }
            });

            // Auto submit form when status is changed from dropdown
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
                activeBtn.addClass('active btn-primary text-white shadow-sm').css({'background-color': 'var(--bs-primary)', 'color': '#ffffff', 'box-shadow': '0 2px 4px rgba(0,0,0,0.15)'});
                
                $('#offcanvasActionMode').val(mode);

                if (mode === 'log_note') {
                    $('#sectionPastInteraction, #sectionLogInteraction').show();
                    $('#sectionDirectSchedule').hide();
                    $('#offcanvasFollowupDate').removeAttr('required');
                } else if (mode === 'schedule') {
                    $('#sectionPastInteraction, #sectionLogInteraction').hide();
                    $('#sectionDirectSchedule').show();
                    $('#offcanvasFollowupDate').attr('required', 'required');
                }
            }

            $(document).on('click', '.offcanvas-mode-btn', function() {
                switchOffcanvasMode($(this).attr('data-mode'));
            });

            window.toggleNextScheduleFields = function(show) {
                var container = $('#containerNextScheduleFields');
                var btn = $('#btnToggleNextSchedule');
                var icon = $('#iconToggleNextSchedule');
                var text = $('#textToggleNextSchedule');

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

            $(document).on('click', '#btnToggleNextSchedule', function() {
                window.toggleNextScheduleFields();
            });

            // Open and populate Offcanvas drawer for Lead Followup / Schedule Activity from Index listing
            $(document).on('click', '.btn-open-followup-offcanvas', function() {
                var leadId = $(this).attr('data-lead-id');
                var leadName = $(this).attr('data-lead-name') || ('Lead #' + leadId);
                var leadStatus = $(this).attr('data-lead-status') || 'New';
                var leadPriority = $(this).attr('data-lead-priority') || 'Medium';
                var nextFollowup = $(this).attr('data-next-followup') || '';

                $('#leadFollowupForm').attr('action', '{{ url("crm/leads") }}/' + leadId + '/followups');
                $('#leadFollowupOffcanvasTitle').text('Edit Followup for ' + leadName);

                $('#offcanvasLeadStatus').val(leadStatus);
                $('#offcanvasLeadPriority').val(leadPriority);
                $('#offcanvasFollowupDate').val(nextFollowup);
                $('#offcanvasNotes, #offcanvasScheduleNotes').val('');

                // Reset direct schedule inputs
                $('#offcanvasEventTitle').val('CRM Followup Call');
                $('#offcanvasScheduleType').val('Call');

                // Next schedule section reset
                $('#offcanvasNextFollowupDate').val('');
                window.toggleNextScheduleFields(false);

                if ($('#offcanvasTagUser').length && $.fn.select2) {
                    if ($('#offcanvasTagUser').hasClass('select2-hidden-accessible')) {
                        $('#offcanvasTagUser').select2('destroy');
                    }
                    $('#offcanvasTagUser').select2({
                        theme: "bootstrap-5",
                        width: "100%",
                        dropdownParent: $('#leadFollowupOffcanvas'),
                        placeholder: "{{ __('crm.select_persons_to_tag') }}"
                    });
                    $('#offcanvasTagUser').val(null).trigger('change');
                }

                switchOffcanvasMode('schedule');
            });

            // ==========================================
            // Lead Quick Assignment & Bulk Assign Logic
            // ==========================================
            var assignOffcanvasEl = document.getElementById('assignLeadOffcanvas');
            var assignBsOffcanvas = assignOffcanvasEl ? new bootstrap.Offcanvas(assignOffcanvasEl) : null;
            var transAssignLeadOwner = @json(__('crm.assign_lead_owner'));
            var transBulkAssignLeads = @json(__('crm.bulk_assign_leads'));
            var transSelectSalesRep = @json(__('crm.select_sales_rep'));
            var transLeadsSelected = @json(__('crm.leads_selected'));
            var transLeadSelected = @json(__('crm.lead_selected'));
            var transUnassigned = @json(__('crm.unassigned'));
            var transChangeOwner = @json(__('crm.change_owner'));
            var transAssignOwner = @json(__('crm.assign_owner'));

            var transSelectedActions = @json(__('crm.selected_actions'));

            window.openSingleAssignDrawer = function(leadId, leadName, currentOwnerId, currentOwnerName) {
                $('#assignModeInput').val('single');
                $('#assignSingleLeadId').val(leadId);
                $('#assignBulkLeadIdsContainer').empty();
                
                $('#assignLeadOffcanvasTitle').text(transAssignLeadOwner);
                $('#assignLeadOffcanvasSubtitle').text(transSelectSalesRep);
                $('#assignTargetTypeLabel').text(@json(__('crm.lead_company')));
                $('#assignTargetNameDisplay').text(leadName || ('Lead #' + leadId));
                $('#assignBulkCountHint').hide();

                if (currentOwnerName && currentOwnerName !== 'Unassigned' && currentOwnerName !== transUnassigned && currentOwnerName.trim() !== '') {
                    $('#assignCurrentOwnerBadge').text(currentOwnerName).removeClass('bg-soft-secondary text-secondary').addClass('bg-soft-info text-info');
                } else {
                    $('#assignCurrentOwnerBadge').text(transUnassigned).removeClass('bg-soft-info text-info').addClass('bg-soft-secondary text-secondary');
                }

                $('#assignLeadOwnerSelect').val(currentOwnerId || '');
                $('#assignNoteInput').val('');

                if (assignBsOffcanvas) {
                    assignBsOffcanvas.show();
                }
            };

            window.openBulkAssignDrawer = function() {
                var selectedCheckboxes = $('.lead-select-checkbox:checked');
                var count = selectedCheckboxes.length;
                if (count === 0) {
                    alert('Please select at least one lead from the table checkbox.');
                    return;
                }

                $('#assignModeInput').val('bulk');
                $('#assignSingleLeadId').val('');
                var container = $('#assignBulkLeadIdsContainer').empty();

                var leadNames = [];
                selectedCheckboxes.each(function() {
                    var lid = $(this).val();
                    var lname = $(this).attr('data-lead-name');
                    container.append('<input type="hidden" name="lead_ids[]" value="' + lid + '">');
                    if (leadNames.length < 3 && lname) {
                        leadNames.push(lname);
                    }
                });

                $('#assignLeadOffcanvasTitle').text(transBulkAssignLeads);
                $('#assignLeadOffcanvasSubtitle').text(transSelectSalesRep);
                $('#assignTargetTypeLabel').text(count + ' ' + (count === 1 ? transLeadSelected : transLeadsSelected));
                $('#assignTargetNameDisplay').text(leadNames.join(', ') + (count > 3 ? ' and ' + (count - 3) + ' more...' : ''));
                $('#assignCurrentOwnerBadge').text(count + ' ' + (count === 1 ? transLeadSelected : transLeadsSelected)).removeClass('bg-soft-info text-info').addClass('bg-soft-primary text-primary');
                $('#assignBulkCountHint').hide();

                $('#assignLeadOwnerSelect').val('');
                $('#assignNoteInput').val('');

                if (assignBsOffcanvas) {
                    assignBsOffcanvas.show();
                }
            };

            window.clearLeadSelections = function() {
                $('.lead-select-checkbox').prop('checked', false);
                $('#selectAllLeadsCheckbox').prop('checked', false);
                $('#leadsTable tbody tr').removeClass('lead-row-selected');
                updateToolbarVisibility();
            };

            function updateToolbarVisibility() {
                var selectedCheckboxes = $('.lead-select-checkbox:checked');
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
            $('#selectAllLeadsCheckbox').on('change', function() {
                var isChecked = $(this).is(':checked');
                $('.lead-select-checkbox').prop('checked', isChecked);
                if (isChecked) {
                    $('#leadsTable tbody tr').addClass('lead-row-selected');
                } else {
                    $('#leadsTable tbody tr').removeClass('lead-row-selected');
                }
                updateToolbarVisibility();
            });

            $(document).on('change', '.lead-select-checkbox', function() {
                var tr = $(this).closest('tr');
                if ($(this).is(':checked')) {
                    tr.addClass('lead-row-selected');
                } else {
                    tr.removeClass('lead-row-selected');
                }
                
                var totalBoxes = $('.lead-select-checkbox').length;
                var checkedBoxes = $('.lead-select-checkbox:checked').length;
                $('#selectAllLeadsCheckbox').prop('checked', totalBoxes > 0 && totalBoxes === checkedBoxes);

                updateToolbarVisibility();
            });

            // Form Submit AJAX Handler
            $('#assignLeadForm').on('submit', function(e) {
                e.preventDefault();
                var form = $(this);
                var mode = $('#assignModeInput').val();
                var submitBtn = $('#btnSubmitLeadAssign');
                var origBtnHtml = submitBtn.html();

                var ownerId = $('#assignLeadOwnerSelect').val();
                var note = $('#assignNoteInput').val();
                var csrfToken = $('meta[name="csrf-token"]').attr('content');

                var postUrl = "{{ route('crm.leads.bulkAssign') }}";
                var payload = {
                    lead_owner_id: ownerId || null,
                    note: note
                };

                var targetLeadIds = [];
                if (mode === 'single') {
                    var singleId = parseInt($('#assignSingleLeadId').val());
                    targetLeadIds.push(singleId);
                    payload.lead_ids = [singleId];
                } else {
                    $('input[name="lead_ids[]"]').each(function() {
                        targetLeadIds.push(parseInt($(this).val()));
                    });
                    payload.lead_ids = targetLeadIds;
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

                        // Update DOM for each target lead
                        targetLeadIds.forEach(function(leadId) {
                            var cell = $('#leadOwnerCell_' + leadId);
                            if (cell.length) {
                                var row = $('#leadRow_' + leadId);
                                var leadName = row.find('.lead-select-checkbox').attr('data-lead-name') || ('Lead #' + leadId);
                                
                                var newHtml = '';
                                if (isAssigned) {
                                    newHtml = '<div class="d-flex align-items-center cursor-pointer p-1 rounded" ' +
                                        'onclick="openSingleAssignDrawer(' + leadId + ', \'' + (leadName.replace(/'/g, "\\'")) + '\', \'' + res.owner_id + '\', \'' + (ownerName.replace(/'/g, "\\'")) + '\')" ' +
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
                                        'onclick="openSingleAssignDrawer(' + leadId + ', \'' + (leadName.replace(/'/g, "\\'")) + '\', \'\', \'' + transUnassigned + '\')" ' +
                                        'title="' + transAssignOwner + '" style="transition: background-color 0.15s ease;">' +
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

                        clearLeadSelections();

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
                                title: res.message || 'Lead assigned successfully!'
                            });
                        } else if (typeof toastr !== 'undefined') {
                            toastr.success(res.message || 'Lead assigned successfully!');
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
                                title: errMsg || 'Error updating lead owner.'
                            });
                        } else {
                            alert(errMsg || 'Error updating lead owner.');
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
                            title: 'An error occurred while assigning leads.'
                        });
                    } else {
                        alert('An error occurred while assigning leads.');
                    }
                });
            });

            // =========================================================================
            // CRM SOFTPHONE DIALER, AUDIO RECORDING & GEMINI AI VOICE ASSISTANT
            // =========================================================================
            var activeCallLeadId = null;
            var activeCallLeadName = '';
            var activeCallLeadCompany = '';
            var activeCallDurationSeconds = 0;
            var activeCallInterval = null;
            var activeAudioStream = null;
            var activeMediaRecorder = null;
            var audioChunks = [];
            var speechRecognition = null;
            var isSpeechRecording = false;

            function formatCallTimer(sec) {
                var m = Math.floor(sec / 60);
                var s = sec % 60;
                return (m < 10 ? '0' + m : m) + ':' + (s < 10 ? '0' + s : s);
            }

            // Speech Recognition Initializer (Real-time Speech-to-Text)
            var speechFinalTranscript = '';
            var recognitionRestartTimer = null;
            var SpeechRecognitionAPI = window.SpeechRecognition || window.webkitSpeechRecognition;
            if (SpeechRecognitionAPI) {
                speechRecognition = new SpeechRecognitionAPI();
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

                            // Standard Dual Frequency Phone Ring Cadence (440Hz + 480Hz)
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

                            // Next ring cycle in 4 seconds (2s ring + 2s silence)
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
                activeCallLeadId = btn.attr('data-lead-id');
                activeCallLeadName = btn.attr('data-lead-name') || 'Customer';
                activeCallLeadCompany = btn.attr('data-lead-company') || '';
                
                var numbers = [];
                try {
                    var rawNumbers = btn.attr('data-lead-numbers');
                    if (rawNumbers) {
                        numbers = JSON.parse(rawNumbers);
                    }
                } catch(err) {
                    numbers = [];
                }

                if (!numbers.length && btn.attr('data-lead-phone')) {
                    numbers.push({
                        type: 'Direct Phone',
                        label: btn.attr('data-lead-phone'),
                        number: btn.attr('data-lead-phone'),
                        is_primary: true
                    });
                }

                // Populate Clean Caller Information (No status badges or clutter)
                $('#dialerContactName').text(activeCallLeadName);
                $('#dialerContactSub').text(activeCallLeadCompany ? activeCallLeadCompany : 'Direct Outbound Call');
                $('#dialerContactAvatar').text((activeCallLeadName.trim().charAt(0) || 'C').toUpperCase());

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
                activeCallDurationSeconds = 0;
                audioChunks = [];
                activeTwilioCallSid = null;

                // Ensure widget is expanded and visible in bottom-right
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

            // Dialpad Backspace & Clear
            $('#btnDialerBackspace').on('click', function() {
                var cur = $('#dialerDisplayNumber').val();
                if (cur.length > 0) {
                    $('#dialerDisplayNumber').val(cur.slice(0, -1));
                }
            });

            $('#btnDialerClear').on('click', function() {
                $('#dialerDisplayNumber').val('').focus();
            });

            // Toggle Dialpad Grid View
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

            // Quick suggestion chips in in-call panel
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

            // Initialize Twilio Device on load
            $(document).ready(function() {
                setTimeout(setupTwilioVoiceDevice, 500);
            });

            // Trigger REST Outbound Call (Fallback / Bridge)
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

            // =========================================================================
            // START REAL OUTBOUND CALL (TWILIO WEBRTC 2-WAY LIVE AUDIO + RECORDING)
            // =========================================================================
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

            // Real-time Twilio Call Status Poller (Detects: answered, busy, rejected, completed)
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

                        // 1. Call Answered & In-Progress -> Stop browser ringing tone immediately!
                        if (st === 'in-progress') {
                            stopBrowserRingingTone();
                            $('#softphoneStatusPill').text('Connected').removeClass('bg-danger bg-warning').addClass('bg-success');
                            $('#dialerLiveStatusTag').html('<i class="feather-phone-call me-1 text-success"></i> Connected • Speaking');
                            $('#inCallSubtext').text('Call connected! Speak directly with customer.');
                        }
                        // 2. Call Cut / Busy / No-Answer / Rejected / Canceled -> Stop ringing and auto-log to interactions
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
                            $('#inCallSubtext').html('<span class="text-danger fw-bold">' + reasonText + '</span> • Logged in Lead Interactions.');

                            // Automatically log the "Not answering" interaction to CRM
                            var confirmUrl = "{{ url('crm/leads') }}/" + leadId + "/call-ai/confirm";
                            fetch(confirmUrl, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': csrfToken
                                },
                                body: JSON.stringify({
                                    summary: 'Not answering',
                                    duration_seconds: activeCallDurationSeconds || 0,
                                    next_action: 'Retry Call',
                                    lead_status: 'Contacted',
                                    sentiment: 'Neutral'
                                })
                            }).then(function() {
                                console.log('Logged Not Answering activity to lead #' + leadId);
                            }).catch(function(e) {
                                console.warn('Auto log error:', e);
                            });

                            // Auto-reset back to dialer idle panel after 3.5 seconds
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
                        // 3. Call Completed (Hung up after conversation)
                        else if (st === 'completed') {
                            stopBrowserRingingTone();
                            if (activeTwilioPollTimer) {
                                clearInterval(activeTwilioPollTimer);
                                activeTwilioPollTimer = null;
                            }
                            
                            // If conversation lasted >= 3 seconds, auto process with Gemini AI
                            if (activeCallDurationSeconds >= 3 || (res.duration && res.duration >= 3)) {
                                $('#btnDisconnectAndAnalyze').trigger('click');
                            } else {
                                // Cut immediately -> Log as Not answering / Short call
                                stopActiveCallMedia();
                                activeTwilioCallSid = null;
                                $('#softphoneStatusPill').text('Call Disconnected').removeClass('bg-success').addClass('bg-warning');
                                $('#dialerLiveStatusTag').html('<i class="feather-phone-off me-1 text-warning"></i> Call Ended');
                                $('#inCallSubtext').text('Call disconnected by recipient • Logged in Interactions.');

                                var confirmUrl = "{{ url('crm/leads') }}/" + leadId + "/call-ai/confirm";
                                fetch(confirmUrl, {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'Accept': 'application/json',
                                        'X-CSRF-TOKEN': csrfToken
                                    },
                                    body: JSON.stringify({
                                        summary: 'Not answering',
                                        duration_seconds: activeCallDurationSeconds || 0,
                                        next_action: 'Retry Call',
                                        lead_status: 'Contacted',
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

            // Disconnect Call & Clean up Media
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

            // =========================================================================
            // DISCONNECT CALL & ANALYZE AUDIO / TRANSCRIPT WITH GEMINI AI
            // =========================================================================
            $('#btnDisconnectAndAnalyze').on('click', function() {
                if (!activeCallLeadId) return;

                var currentCallSid = activeTwilioCallSid;
                var csrfToken = $('meta[name="csrf-token"]').attr('content');

                // 1. Forcefully terminate telephony call leg immediately so customer's phone disconnects
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
                btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Gemini AI Transcribing & Processing...');

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
                        // Hide Softphone Widget
                        $('#crmFloatingSoftphoneWidget').fadeOut(250);

                        // Populate Gemini AI Confirmation Popup
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

                        // Show Review & Confirmation Popup
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

            // =========================================================================
            // APPROVE & SCHEDULE ACTIVITY IN CRM
            // =========================================================================
            $('#btnApproveAndSchedule').on('click', function(e) {
                e.preventDefault();
                var leadId = $('#aiModalLeadId').val();
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
        });
    </script>

    <!-- Offcanvas Drawer: Edit Followup / Schedule Activity -->
    <div class="offcanvas offcanvas-end border-0 shadow-lg" tabindex="-1" id="leadFollowupOffcanvas" aria-labelledby="leadFollowupOffcanvasLabel" style="width: 490px; max-width: 92vw;">
        <div class="offcanvas-header bg-light border-bottom py-3 px-4">
            <div class="d-flex align-items-center gap-2">
                <div class="avatar-text avatar-sm bg-soft-primary text-primary rounded-circle">
                    <i class="feather-calendar"></i>
                </div>
                <div>
                    <h5 class="offcanvas-title fw-bold text-dark fs-14 mb-0" id="leadFollowupOffcanvasTitle">{{ __('crm.edit_followup') }}</h5>
                    <span class="text-muted fs-11">{{ __('crm.log_interaction_next_followup') }}</span>
                </div>
            </div>
            <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        
        <div class="offcanvas-body p-4 bg-white">
            <form action="" method="POST" id="leadFollowupForm" enctype="multipart/form-data">
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

                <!-- Log Interaction Section (Tab 1: Log Interaction) -->
                <div id="sectionPastInteraction">
                    <x-ui.modal-form-ui 
                        type="select" 
                        :label="__('crm.followup_interaction_type')" 
                        name="type" 
                        id="offcanvasFollowupType" 
                    >
                        <option value="Call">{{ __('crm.interaction_types.Call') }}</option>
                        <option value="Email">{{ __('crm.interaction_types.Email') }}</option>
                        <option value="Meeting">{{ __('crm.interaction_types.Meeting') }}</option>
                        <option value="Demo">{{ __('crm.interaction_types.Demo') }}</option>
                        <option value="WhatsApp">{{ __('crm.activity_types.WhatsApp') }}</option>
                    </x-ui.modal-form-ui>

                    <x-ui.modal-form-ui 
                        type="select" 
                        :label="__('crm.followup_status_outcome')" 
                        name="status" 
                        id="offcanvasFollowupStatus" 
                    >
                        <option value="Connected">{{ __('crm.outcomes.Connected') }}</option>
                        <option value="Not Connected">{{ __('crm.outcomes.Not Connected') }}</option>
                        <option value="Not Answering">{{ __('crm.outcomes.Not Answering') }}</option>
                    </x-ui.modal-form-ui>

                    <x-ui.modal-form-ui 
                        type="textarea" 
                        :label="__('crm.notes_summary')" 
                        name="notes" 
                        id="offcanvasNotes" 
                        rows="3" 
                        :placeholder="__('crm.notes_summary_placeholder')" 
                    />

                    <!-- Next Follow-up Section inside Log Mode -->
                    <div class="border-top pt-3 mt-3">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <h6 class="fs-12 fw-bold text-dark mb-0">
                                <i class="feather-calendar text-primary me-1"></i> {{ __('crm.next_activity_schedule') }}
                            </h6>
                            <button type="button" class="btn btn-xs btn-outline-primary fw-bold px-2.5 py-1 rounded-pill d-inline-flex align-items-center gap-1" id="btnToggleNextSchedule">
                                <i class="feather-plus fs-11" id="iconToggleNextSchedule"></i>
                                <span id="textToggleNextSchedule">{{ __('crm.schedule_next_activity_btn') }}</span>
                            </button>
                        </div>
                        
                        <div id="containerNextScheduleFields" class="mt-3 p-3 bg-light rounded-3 border" style="display: none;">
                            <x-ui.modal-form-ui 
                                type="input" 
                                :label="__('crm.next_activity_title')" 
                                name="next_title" 
                                id="offcanvasNextTitle" 
                                :placeholder="__('crm.next_activity_title_placeholder')" 
                            />

                            <div class="row g-2">
                                <div class="col-6">
                                    <x-ui.modal-form-ui 
                                        type="select" 
                                        :label="__('crm.next_activity_type')" 
                                        name="next_activity_type" 
                                        id="offcanvasNextActivityType" 
                                    >
                                        <option value="Call">{{ __('crm.interaction_types.Call') }}</option>
                                        <option value="Meeting">{{ __('crm.interaction_types.Meeting') }}</option>
                                        <option value="Demo">{{ __('crm.interaction_types.Demo') }}</option>
                                        <option value="Email">{{ __('crm.interaction_types.Email') }}</option>
                                        <option value="WhatsApp">{{ __('crm.activity_types.WhatsApp') }}</option>
                                    </x-ui.modal-form-ui>
                                </div>
                                <div class="col-6">
                                    <x-ui.modal-form-ui 
                                        type="select" 
                                        :label="__('crm.duration_minutes')" 
                                        name="next_duration_minutes" 
                                        id="offcanvasNextDuration" 
                                    >
                                        <option value="15">{{ __('crm.duration_options.15') }}</option>
                                        <option value="30" selected>{{ __('crm.duration_options.30') }}</option>
                                        <option value="45">{{ __('crm.duration_options.45') }}</option>
                                        <option value="60">{{ __('crm.duration_options.60') }}</option>
                                        <option value="90">{{ __('crm.duration_options.90') }}</option>
                                        <option value="120">{{ __('crm.duration_options.120') }}</option>
                                    </x-ui.modal-form-ui>
                                </div>
                            </div>

                            <x-ui.modal-form-ui 
                                type="input" 
                                inputType="datetime-local" 
                                :label="__('crm.next_followup_datetime_optional')" 
                                name="next_followup_date" 
                                id="offcanvasNextFollowupDate" 
                            />

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

                            <x-ui.modal-form-ui 
                                type="input" 
                                :label="__('crm.guest_attendee_emails')" 
                                name="next_guest_emails" 
                                id="offcanvasNextGuestEmails" 
                                :placeholder="__('crm.guest_emails_placeholder')" 
                            />

                            <x-ui.modal-form-ui 
                                type="select" 
                                :label="__('crm.tag_assign_persons')" 
                                name="tagged_user_ids[]" 
                                id="offcanvasTagUser" 
                                :multiple="true"
                                :data-placeholder="__('crm.select_persons_to_tag')"
                            >
                                @foreach($users as $u)
                                    <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                                @endforeach
                            </x-ui.modal-form-ui>
                        </div>
                    </div>
                </div>

                <!-- Direct Schedule Section (Tab 2: Schedule Activity) -->
                <div id="sectionDirectSchedule" style="display: none;">
                    <x-ui.modal-form-ui 
                        type="input" 
                        :label="__('crm.event_meeting_title')" 
                        name="title" 
                        id="offcanvasEventTitle" 
                        :placeholder="__('crm.event_title_placeholder')" 
                        value="" 
                    />

                    <x-ui.modal-form-ui 
                        type="select" 
                        :label="__('crm.activity_type')" 
                        name="schedule_type" 
                        id="offcanvasScheduleType" 
                        :required="true"
                        onchange="$('#offcanvasFollowupType').val(this.value)"
                    >
                        <option value="Call">{{ __('crm.interaction_types.Call') }}</option>
                        <option value="Meeting">{{ __('crm.interaction_types.Meeting') }}</option>
                        <option value="Demo">{{ __('crm.interaction_types.Demo') }}</option>
                        <option value="Email">{{ __('crm.interaction_types.Email') }}</option>
                        <option value="WhatsApp">{{ __('crm.activity_types.WhatsApp') }}</option>
                    </x-ui.modal-form-ui>

                    <div class="row g-2">
                        <div class="col-6">
                            <x-ui.modal-form-ui 
                                type="input" 
                                inputType="datetime-local" 
                                :label="__('crm.due_date_time')" 
                                name="followup_date" 
                                id="offcanvasFollowupDate" 
                                :required="true"
                            />
                        </div>
                        <div class="col-6">
                            <x-ui.modal-form-ui 
                                type="select" 
                                :label="__('crm.duration_minutes')" 
                                name="duration_minutes" 
                                id="offcanvasDuration" 
                            >
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

                    <x-ui.modal-form-ui 
                        type="input" 
                        :label="__('crm.guest_attendee_emails')" 
                        name="guest_emails" 
                        id="offcanvasGuestEmails" 
                        :placeholder="__('crm.guest_emails_placeholder')" 
                    />

                    <x-ui.modal-form-ui 
                        type="textarea" 
                        :label="__('crm.description_plan')" 
                        name="schedule_notes" 
                        id="offcanvasScheduleNotes" 
                        rows="3" 
                        :placeholder="__('crm.agenda_plan_placeholder')" 
                        oninput="$('#offcanvasNotes').val(this.value)"
                    />
                </div>

                <div class="d-flex align-items-center justify-content-end gap-2 border-top pt-3">
                    <button type="button" class="btn btn-light border px-4 py-2 fs-13 fw-bold text-uppercase" data-bs-dismiss="offcanvas">{{ __('crm.close') }}</button>
                    <button type="submit" class="btn btn-primary px-4 py-2 fs-13 fw-bold text-uppercase shadow-sm">{{ __('crm.save') }}</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Offcanvas Drawer: Quick Single & Bulk Lead Assignment -->
    <div class="offcanvas offcanvas-end border-0 shadow-lg" tabindex="-1" id="assignLeadOffcanvas" aria-labelledby="assignLeadOffcanvasLabel" style="width: 460px; max-width: 92vw;">
        <div class="offcanvas-header bg-light border-bottom py-3 px-4">
            <div class="d-flex align-items-center gap-2">
                <div class="avatar-text avatar-sm bg-soft-primary text-primary rounded-circle">
                    <i class="feather-user-check"></i>
                </div>
                <div>
                    <h5 class="offcanvas-title fw-bold text-dark fs-14 mb-0" id="assignLeadOffcanvasTitle">{{ __('crm.assign_lead_owner') }}</h5>
                    <span class="text-muted fs-11" id="assignLeadOffcanvasSubtitle">{{ __('crm.select_sales_rep') }}</span>
                </div>
            </div>
            <button type="button" class="btn-close text-reset" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        
        <div class="offcanvas-body p-4 bg-white">
            <form id="assignLeadForm">
                @csrf
                <input type="hidden" name="assign_mode" id="assignModeInput" value="single">
                <input type="hidden" name="single_lead_id" id="assignSingleLeadId" value="">
                <div id="assignBulkLeadIdsContainer"></div>

                <!-- Lead Info / Target Preview Card -->
                <div class="p-3 mb-3 bg-light rounded-3 border" id="assignTargetSummaryCard">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <span class="text-muted fs-11 fw-bold text-uppercase" id="assignTargetTypeLabel">{{ __('crm.lead_company') }}</span>
                        <span class="badge bg-soft-info text-info border border-info-subtle fs-10" id="assignCurrentOwnerBadge">{{ __('crm.unassigned') }}</span>
                    </div>
                    <div class="fw-bold text-dark fs-13" id="assignTargetNameDisplay">Lead Name</div>
                    <div class="text-muted fs-11 mt-1" id="assignBulkCountHint" style="display: none;"></div>
                </div>

                <!-- Assignee Selector -->
                <div class="mb-3">
                    <label class="form-label fw-bold fs-12 text-dark mb-1">
                        {{ __('crm.select_sales_rep') }} <span class="text-danger">*</span>
                    </label>
                    <select name="lead_owner_id" id="assignLeadOwnerSelect" class="form-select form-select-sm fs-12 py-2" required>
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
                    <textarea name="note" id="assignNoteInput" class="form-control fs-12" rows="3" placeholder="e.g. Assigned from Meta ad inquiry, ceramic project..."></textarea>
                </div>

                <!-- Action Buttons -->
                <div class="d-flex align-items-center justify-content-end gap-2 border-top pt-3">
                    <button type="button" class="btn btn-light border px-4 py-2 fs-13 fw-bold text-uppercase" data-bs-dismiss="offcanvas">{{ __('crm.close') }}</button>
                    <button type="submit" class="btn btn-primary px-4 py-2 fs-13 fw-bold text-uppercase shadow-sm d-flex align-items-center gap-1.5" id="btnSubmitLeadAssign">
                        <i class="feather-check"></i>
                        <span>{{ __('crm.confirm_assignment') }}</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Floating Right-Side CRM Mobile Softphone Dialer Widget -->
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
            
            <!-- Caller Display Section (Clean Minimalist Mobile Style) -->
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

    <!-- Modal 2: Gemini AI Call Analysis & Confirmation Popup (Human-in-the-Loop using common component) -->
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
                <label class="form-label fw-semibold fs-11 text-muted mb-1">
                    <i class="feather-send text-success me-1"></i> Customer Follow-up Message Note
                </label>
                <textarea class="form-control fs-11 bg-white" id="aiModalFollowupMessage" rows="2" placeholder="Summary message note for WhatsApp/Email..."></textarea>
            </div>

            <!-- Footer: Actions -->
            <div class="d-flex align-items-center justify-content-between pt-3 border-top mt-3">
                <button type="button" class="btn btn-light border px-3 py-2 fs-12 fw-semibold" data-bs-dismiss="modal">Cancel / Edit Later</button>
                <button type="button" class="btn btn-primary px-4 py-2 fs-12 fw-bold d-flex align-items-center gap-1.5 shadow-sm" id="btnApproveAndSchedule">
                    <i class="feather-check-circle fs-13"></i>
                    <span id="btnApproveText">Approve & Schedule Activity</span>
                </button>
            </div>
        </form>
    </x-ui.modal>
@endpush

