@extends('layouts.duralux')

@section('title', __('crm.deals') . ' | SaaS ERP')
@section('page-title', __('crm.deals_pipeline'))
@section('breadcrumb', __('crm.deals'))

@section('page-actions')
    <x-ui.button href="{{ route('crm.deals.create') }}" variant="primary" icon="feather-plus">
        {{ __('crm.new_deal') }}
    </x-ui.button>
@endsection

@push('styles')
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
        background-color: #f8fafc !important;
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
                    <x-ui.filter :label="__('crm.filter_options')" offset="0, 5">
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
                    <tr style="background-color: #e8ecf1 !important;">
                        <th style="width: 35px; background-color: #e8ecf1 !important;" class="text-center">
                            <input type="checkbox" class="form-check-input">
                        </th>
                        <th style="width: 12%; background-color: #e8ecf1 !important;">{{ __('crm.deal_no') }}</th>
                        <th style="width: 20%; background-color: #e8ecf1 !important;">{{ __('crm.project_deal_title') }}</th>
                        <th style="width: 18%; background-color: #e8ecf1 !important;">{{ __('crm.account_client') }}</th>
                        <th style="width: 15%; background-color: #e8ecf1 !important;">{{ __('crm.phone_email') }}</th>
                        <th style="width: 12%; background-color: #e8ecf1 !important;" class="text-end pe-3">{{ __('crm.est_value') }} ({{ active_currency_symbol() }})</th>
                        <th style="width: 13%; background-color: #e8ecf1 !important;">{{ __('crm.closing_date_status') }}</th>
                        <th style="width: 12%; background-color: #e8ecf1 !important;">{{ __('crm.stage') }}</th>
                        <th style="width: 10%; background-color: #e8ecf1 !important;">{{ __('crm.health_percent') }}</th>
                        <th style="width: 4%; background-color: #e8ecf1 !important;" class="text-end pe-3">{{ __('crm.actions') }}</th>
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
                        <tr class="table-deal-row">
                            <td class="text-center">
                                <input type="checkbox" class="form-check-input">
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
                            <td colspan="9" class="text-center py-5 text-muted">
                                <i class="feather-folder fs-1 text-muted d-block mb-2"></i>
                                {{ __('crm.no_deals_found') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.odoo-form-ui>
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
                                        <div class="form-check form-switch mb-0 p-2 border rounded-2 bg-light d-flex align-items-center justify-content-between" style="min-height: 38px;">
                                            <label class="form-check-label fw-bold fs-11 text-dark mb-0 pe-1" for="dealOffcanvasNextSyncGoogleIndex" style="cursor: pointer;">
                                                <i class="feather-calendar text-danger me-1"></i> {{ __('crm.google_calendar') }}
                                            </label>
                                            <input type="hidden" name="next_sync_google_calendar" value="0">
                                            <input class="form-check-input ms-0 mt-0" type="checkbox" name="next_sync_google_calendar" value="1" id="dealOffcanvasNextSyncGoogleIndex" style="cursor: pointer;">
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="form-check form-switch mb-0 p-2 border rounded-2 bg-light d-flex align-items-center justify-content-between" style="min-height: 38px;">
                                            <label class="form-check-label fw-bold fs-11 text-dark mb-0 pe-1" for="dealOffcanvasNextCreateMeetIndex" style="cursor: pointer;">
                                                <i class="feather-video text-primary me-1"></i> {{ __('crm.google_meet_video') }}
                                            </label>
                                            <input type="hidden" name="next_create_meet_link" value="0">
                                            <input class="form-check-input ms-0 mt-0" type="checkbox" name="next_create_meet_link" value="1" id="dealOffcanvasNextCreateMeetIndex" style="cursor: pointer;">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <x-ui.modal-form-ui type="input" name="next_guest_emails" id="dealOffcanvasNextGuestEmailsIndex" :label="__('crm.guest_attendee_emails')" :placeholder="__('crm.guest_emails_placeholder')" />

                            <div class="mt-3">
                                <label class="form-label fw-bold text-dark fs-12 mb-1">{{ __('crm.tag_assign_persons') }}</label>
                                <select name="tagged_user_ids[]" id="dealOffcanvasTagUser" class="form-select form-select-sm shadow-2xs" multiple data-placeholder="{{ __('crm.select_persons_to_tag') }}">
                                    @foreach((\App\Models\User::orderBy('name')->get()) as $u)
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
    });
</script>
@endpush
