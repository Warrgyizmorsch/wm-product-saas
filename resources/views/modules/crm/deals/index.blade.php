@extends('layouts.duralux')

@section('title', 'Deals | SaaS ERP')
@section('page-title', 'Deals Pipeline')
@section('breadcrumb', 'Deals')

@section('page-actions')
    <x-ui.button href="{{ route('crm.deals.create') }}" variant="primary" icon="feather-plus">
        Add New Deal
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
            <h5 class="fw-bold text-dark mb-0">Deals Listing</h5>
            <div class="d-flex align-items-center flex-wrap gap-2">
                <!-- Outside Search Box (HRMS Style) -->
                <form method="GET" action="{{ route('crm.deals.index') }}" class="d-flex align-items-center bg-light border rounded px-2.5 py-0.5 me-1" style="height: 34px; min-width: 240px;">
                    @foreach(request()->except(['search', 'page']) as $k => $v)
                        @if(is_scalar($v) && $v !== '')
                            <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                        @endif
                    @endforeach
                    <i class="feather-search text-muted me-2" style="font-size: 13px;"></i>
                    <input type="text" name="search" class="form-control border-0 bg-transparent p-0 fs-12 text-dark" placeholder="Search by deal #, title, company..." value="{{ request('search') }}" style="box-shadow: none; outline: none;">
                    @if(request('search'))
                        <a href="{{ route('crm.deals.index', request()->except(['search', 'page'])) }}" class="text-muted text-decoration-none ms-1" title="Clear Search">
                            <i class="feather-x fs-12"></i>
                        </a>
                    @endif
                </form>

                <x-ui.view-switcher />

                <x-ui.sort-dropdown label="Sort">
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'id', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'id' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>Latest Deals</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'title', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'title' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>Title (A - Z)</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'estimated_value', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'estimated_value' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>Highest Deal Value</span>
                    </a>
                </x-ui.sort-dropdown>

                <form method="GET" action="{{ route('crm.deals.index') }}" class="d-inline">
                    <x-ui.filter label="Filter" offset="0, 5">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Filter Options</h6>
                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Keywords</label>
                            <x-ui.odoo-form-ui type="input" name="search" placeholder="Search title or company..." value="{{ request('search') }}" />
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Pipeline Stage</label>
                            <x-ui.odoo-form-ui type="select" name="stage">
                                <option value="">All Stages</option>
                                @foreach($dealStatuses as $st)
                                    <option value="{{ $st->name }}" {{ request('stage') === $st->name ? 'selected' : '' }}>{{ $st->name }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Created From</label>
                                <x-ui.odoo-form-ui type="input" inputType="date" name="date_from" value="{{ request('date_from') }}" />
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Created To</label>
                                <x-ui.odoo-form-ui type="input" inputType="date" name="date_to" value="{{ request('date_to') }}" />
                            </div>
                        </div>
                        <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                            <a href="{{ route('crm.deals.index') }}" class="btn btn-xs btn-light border">Reset</a>
                            <button type="submit" class="btn btn-xs btn-primary">Apply Filters</button>
                        </div>
                    </x-ui.filter>
                </form>
            </div>
        </div>

        {{-- 2. Stage Filter Tabs --}}
        <div class="mb-2" style="border-bottom: 2px solid #e2e8f0;">
            <div class="crm-status-tabs-wrapper">
                <a href="{{ request()->fullUrlWithQuery(['stage' => null, 'page' => null]) }}" class="crm-status-tab {{ empty($stage) ? 'active' : '' }}">
                    ALL ({{ $stageCounts['all'] ?? 0 }})
                </a>
                @foreach($dealStatuses as $st)
                    @php
                        $tabClass = match(strtolower($st->name)) {
                            'won', 'closed won' => 'crm-status-tab--won',
                            'lost', 'closed lost' => 'crm-status-tab--lost',
                            default => '',
                        };
                    @endphp
                    <a href="{{ request()->fullUrlWithQuery(['stage' => $st->name, 'page' => null]) }}" class="crm-status-tab {{ $tabClass }} {{ $stage === $st->name ? 'active' : '' }}">
                        {{ strtoupper($st->name) }} ({{ $stageCounts[$st->name] ?? 0 }})
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
                        <th style="width: 12%; background-color: #e8ecf1 !important;">DEAL #</th>
                        <th style="width: 20%; background-color: #e8ecf1 !important;">PROJECT / DEAL TITLE</th>
                        <th style="width: 18%; background-color: #e8ecf1 !important;">COMPANY / CONTACT</th>
                        <th style="width: 15%; background-color: #e8ecf1 !important;">PHONE / EMAIL</th>
                        <th style="width: 12%; background-color: #e8ecf1 !important;" class="text-end pe-3">EST. VALUE (₹)</th>
                        <th style="width: 13%; background-color: #e8ecf1 !important;">CLOSING DATE & STATUS</th>
                        <th style="width: 12%; background-color: #e8ecf1 !important;">STAGE</th>
                        <th style="width: 4%; background-color: #e8ecf1 !important;" class="text-end pe-3">ACTIONS</th>
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

                            $contactName = $deal->contact ? $deal->contact->name : ($deal->account ? $deal->account->primaryContact?->name : null);
                            $phone = $deal->contact?->phone ?: ($deal->account?->phone ?: null);
                            $email = $deal->contact?->email ?: ($deal->account?->email ?: null);
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
                                    <i class="feather-clock me-1 text-primary"></i>Created: {{ $deal->created_at ? $deal->created_at->format('d/m/Y') : 'N/A' }}
                                </div>
                            </td>
                            <td>
                                <a href="{{ route('crm.deals.show', $deal) }}" class="fw-bold text-dark text-decoration-none hover-primary d-block" style="line-height: 1.3;">
                                    {{ $deal->title }}
                                </a>
                                @if($deal->lead_source)
                                    <div class="text-muted fs-11 mt-0.5"><i class="feather-globe me-1 text-primary"></i>{{ $deal->lead_source }}</div>
                                @endif
                            </td>
                            <td>
                                @if($deal->account)
                                    <a href="{{ route('crm.accounts.show', $deal->account) }}" class="fw-bold text-dark text-decoration-none d-block">
                                        <i class="feather-briefcase me-1 text-primary"></i>{{ $deal->account->name }}
                                    </a>
                                @else
                                    <span class="fw-semibold text-dark">—</span>
                                @endif

                                @if($contactName)
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
                                ₹{{ number_format($deal->actual_value ?: $deal->estimated_value, 2) }}
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
                                        <i class="feather-alert-triangle me-1"></i>Overdue Followup
                                    </span>
                                @elseif($isClosingToday)
                                    <span class="badge bg-warning text-dark px-2 py-0.5 mt-1 fs-10 fw-bold">
                                        <i class="feather-clock me-1"></i>Closing Today
                                    </span>
                                @elseif($normalizedStage === 'Won')
                                    <span class="badge bg-soft-success text-success px-2 py-0.5 mt-1 fs-10 fw-bold">
                                        <i class="feather-check-circle me-1"></i>Deal Closed
                                    </span>
                                @elseif($normalizedStage === 'Lost')
                                    <span class="badge bg-soft-danger text-danger px-2 py-0.5 mt-1 fs-10 fw-bold">
                                        <i class="feather-x-circle me-1"></i>Deal Lost
                                    </span>
                                @else
                                    <span class="badge bg-soft-info text-info px-2 py-0.5 mt-1 fs-10 fw-semibold">
                                        Followup Active
                                    </span>
                                @endif
                            </td>
                            <td>
                                @if ($normalizedStage === 'Won')
                                    <span class="badge bg-soft-success text-success px-2.5 py-1 fs-11 fw-bold">
                                        <i class="feather-check-circle me-1"></i>Won
                                    </span>
                                @elseif ($normalizedStage === 'Lost')
                                    <span class="badge bg-soft-danger text-danger px-2.5 py-1 fs-11 fw-bold">
                                        <i class="feather-x-circle me-1"></i>Lost
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
                                                    @endphp
                                                    <option value="{{ $stOption->name }}" data-bg="{{ $bgClass }}" {{ $normalizedStage === $stOption->name ? 'selected' : '' }}>
                                                        {{ $stOption->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </form>
                                    </div>
                                @endif
                            </td>
                            <td class="text-end pe-3">
                                <x-ui.action-dropdown :viewUrl="route('crm.deals.show', $deal)">
                                    <x-slot:extraActions>
                                        <button type="button" 
                                                class="action-dropdown-btn btn-open-deal-followup-offcanvas" 
                                                title="Schedule Activity / Log Followup" 
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
                                            <i class="feather-calendar me-2 text-primary fs-12"></i>Log & Schedule Followup
                                        </a>
                                    </li>
                                    <li>
                                        <a href="{{ route('crm.deals.edit', $deal) }}" class="dropdown-item">
                                            <i class="feather-edit me-2 text-muted fs-12"></i>Edit Deal
                                        </a>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <form action="{{ route('crm.deals.destroy', $deal->id) }}" method="POST" id="deleteDealForm_{{ $deal->id }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="button" class="dropdown-item text-danger fw-semibold" onclick="confirmAction({ title: 'Delete Deal', message: 'Are you sure you want to delete deal &quot;{{ addslashes($deal->title) }}&quot; permanently?', variant: 'danger', confirmText: 'Delete Deal', onConfirm: function() { document.getElementById('deleteDealForm_{{ $deal->id }}').submit(); } })">
                                                <i class="feather-trash-2 me-2 text-danger fs-12"></i>Delete Deal
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
                                No deals found matching your criteria.
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
                    <h5 class="offcanvas-title fw-bold text-dark fs-14 mb-0" id="dealFollowupOffcanvasTitle">Edit Followup</h5>
                    <span class="text-muted fs-11">Log interaction & next followup or schedule activity</span>
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
                        LOG DISCUSSION & NEXT
                    </button>
                    <button type="button" class="btn btn-sm flex-fill fw-bold text-center border-0 deal-offcanvas-mode-btn" data-mode="schedule" style="font-size: 12px; padding: 8px 6px; color: #64748b; background-color: transparent; border-radius: 6px; transition: all 0.2s ease;">
                        DIRECT SCHEDULE ACTIVITY
                    </button>
                </div>

                <!-- Past Interaction Section (Tab 1: Log Activity) -->
                <div id="dealSectionPastInteraction">
                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark fs-12 mb-1">Follow Up / Interaction Type</label>
                        <select name="type" id="dealOffcanvasFollowupType" class="form-select form-select-sm shadow-2xs">
                            <option value="Call">Call</option>
                            <option value="Email">Email</option>
                            <option value="Meeting">Meeting</option>
                            <option value="Demo">Demo</option>
                            <option value="WhatsApp">WhatsApp</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark fs-12 mb-1">Follow Up Status / Outcome</label>
                        <select name="status" id="dealOffcanvasFollowupStatus" class="form-select form-select-sm shadow-2xs">
                            <option value="Connected">Connected</option>
                            <option value="Not Connected">Not Connected</option>
                            <option value="Not Answering">Not Answering</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark fs-12 mb-1">Discussion Notes / Summary</label>
                        <textarea name="notes" id="dealOffcanvasNotes" rows="3" class="form-control form-control-sm shadow-2xs" placeholder="Write discussion notes..."></textarea>
                    </div>

                    <!-- Next Follow-up Section inside Log Mode -->
                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark fs-12 mb-1">Next Activity Type (Optional)</label>
                        <select name="next_activity_type" id="dealOffcanvasNextActivityType" class="form-select form-select-sm shadow-2xs">
                            <option value="Call">Call</option>
                            <option value="Meeting">Meeting</option>
                            <option value="Demo">Demo</option>
                            <option value="Email">Email</option>
                            <option value="WhatsApp">WhatsApp</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark fs-12 mb-1">Next Follow-up Date & Time (Optional)</label>
                        <input type="datetime-local" name="next_followup_date" id="dealOffcanvasNextFollowupDate" class="form-control form-control-sm shadow-2xs">
                    </div>
                </div>

                <!-- Direct Schedule Section (Tab 2: Schedule Activity) -->
                <div id="dealSectionDirectSchedule" style="display: none;">
                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark fs-12 mb-1">Activity Type <span class="text-danger">*</span></label>
                        <select name="schedule_type" id="dealOffcanvasScheduleType" class="form-select form-select-sm shadow-2xs" onchange="$('#dealOffcanvasFollowupType').val(this.value)">
                            <option value="Call">Call</option>
                            <option value="Meeting">Meeting</option>
                            <option value="Demo">Demo</option>
                            <option value="Email">Email</option>
                            <option value="WhatsApp">WhatsApp</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark fs-12 mb-1">Due Date & Time <span class="text-danger">*</span></label>
                        <input type="datetime-local" name="followup_date" id="dealOffcanvasFollowupDate" class="form-control form-control-sm shadow-2xs">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark fs-12 mb-1">Description / Plan</label>
                        <textarea name="schedule_notes" id="dealOffcanvasScheduleNotes" rows="3" class="form-control form-control-sm shadow-2xs" placeholder="Agenda / plan for upcoming activity..." oninput="$('#dealOffcanvasNotes').val(this.value)"></textarea>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold text-dark fs-12 mb-1">Deal Stage</label>
                    <select name="stage" id="dealOffcanvasStage" class="form-select form-select-sm shadow-2xs">
                        @foreach($dealStatuses as $stg)
                            <option value="{{ $stg->name }}">{{ $stg->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-bold text-dark fs-12 mb-1">Tag / Assign Persons</label>
                    <select name="tagged_user_ids[]" id="dealOffcanvasTagUser" class="form-select form-select-sm shadow-2xs" multiple data-placeholder="Select persons to tag...">
                        @foreach((\App\Models\User::orderBy('name')->get()) as $u)
                            <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                        @endforeach
                    </select>
                </div>

                <div class="d-flex align-items-center justify-content-end gap-2 border-top pt-3">
                    <button type="button" class="btn btn-light border px-4 py-2 fs-13 fw-bold text-uppercase" data-bs-dismiss="offcanvas">CLOSE</button>
                    <button type="submit" class="btn btn-primary px-4 py-2 fs-13 fw-bold text-uppercase shadow-sm">UPDATE DETAILS</button>
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
                $('#dealSectionPastInteraction').show();
                $('#dealSectionDirectSchedule').hide();
                $('#dealOffcanvasFollowupDate').removeAttr('required');
            } else if (mode === 'schedule') {
                $('#dealSectionPastInteraction').hide();
                $('#dealSectionDirectSchedule').show();
                $('#dealOffcanvasFollowupDate').attr('required', 'required');
            }
        }

        $(document).on('click', '.deal-offcanvas-mode-btn', function() {
            switchDealOffcanvasMode($(this).attr('data-mode'));
        });

        // Open and populate Offcanvas drawer for Deal Followup / Schedule Activity
        $(document).on('click', '.btn-open-deal-followup-offcanvas', function() {
            var dealId = $(this).attr('data-deal-id');
            var dealTitle = $(this).attr('data-deal-title') || 'Deal';
            var dealStage = $(this).attr('data-deal-stage');

            $('#dealFollowupOffcanvasTitle').text('Edit Followup for ' + dealTitle);
            $('#dealFollowupForm').attr('action', '/crm/deals/' + dealId + '/followups');
            $('#dealOffcanvasNotes, #dealOffcanvasScheduleNotes').val('');

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
