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
                        @endphp
                        <tr class="table-deal-row" id="dealRow_{{ $deal->id }}">
                            <td class="text-center">
                                <input type="checkbox" class="form-check-input deal-select-checkbox" 
                                       value="{{ $deal->id }}" 
                                       data-deal-name="{{ e($deal->title) }}">
                            </td>
                            <td class="font-monospace fw-bold">
                                <a href="{{ route('crm.deals.show', $deal) }}" class="text-primary text-decoration-none hover-underline fs-13">
                                    {{ $deal->deal_number ?: ('DL-' . str_pad($deal->id, 5, '0', STR_PAD_LEFT)) }}
                                </a>
                                <div class="text-muted fs-11 mt-0.5 font-sans fw-normal" title="Deal Creation Date">
                                    <i class="feather-clock me-1 text-primary"></i>{{ __('crm.created') }}: {{ $deal->created_at ? $deal->created_at->format('d/m/Y') : 'N/A' }}
                                </div>
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
                                    <span class="d-block text-dark fw-semibold"><i class="feather-phone fs-11 me-1 text-primary"></i>{{ $phone }}</span>
                                @endif
                                @if ($email)
                                    <span class="text-muted fs-11 d-block text-truncate" style="max-width: 160px;" title="{{ $email }}">
                                        <i class="feather-mail fs-11 me-1 text-muted"></i>{{ $email }}
                                    </span>
                                @endif
                                @if (!$phone && !$email)
                                    <span class="text-muted fs-11">—</span>
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
@endsection

@push('scripts')
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
    });
</script>
@endpush
