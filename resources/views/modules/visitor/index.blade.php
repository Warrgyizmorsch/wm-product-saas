@extends('layouts.duralux')

@section('title', __('visitor.gate_desk') . ' | SaaS ERP')
@section('page-title', __('visitor.gate_desk'))
@section('breadcrumb', __('visitor.visitor_management') . ' > ' . __('visitor.gate_desk'))

@push('styles')
<style>
    .visitor-avatar {
        width: 36px;
        height: 36px;
        object-fit: cover;
        border-radius: 50%;
        border: 2px solid #e2e8f0;
    }
    .visitor-avatar-placeholder {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 13px;
        background-color: #f1f5f9;
        color: #475569;
        border: 1px solid #cbd5e1;
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
</style>
@endpush

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <x-ui.import-export-dropdown 
            type="visitor-passes" 
            exportRoute="{{ route('visitor.export') }}"
            downloadTemplateRoute="{{ route('visitor.sample-template') }}"
            importModalTarget="#importVisitorModal" />

        <x-ui.button variant="primary" icon="feather-plus" data-bs-toggle="offcanvas" data-bs-target="#newVisitorDrawer">
            {{ __('visitor.new_visitor_pass') }}
        </x-ui.button>
    </div>
@endsection

@section('content')

    <!-- Flash Notifications -->
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm rounded-3 mb-3 d-flex align-items-center" role="alert">
            <i class="feather-check-circle fs-18 text-success me-2"></i>
            <div>{{ session('success') }}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- 1. Stats Widgets (Common x-ui.stat-widget) -->
    <div class="row g-3 mb-3">
        <div class="col-sm-6 col-xl-3">
            <x-ui.stat-widget 
                title="{{ __('visitor.live_headcount') }}" 
                value="{{ $stats['active_inside'] ?? 0 }}" 
                icon="feather-user-check" 
                color="success" 
                variant="compact" />
        </div>
        <div class="col-sm-6 col-xl-3">
            <x-ui.stat-widget 
                title="{{ __('visitor.today_expected') }}" 
                value="{{ $stats['today_expected'] ?? 0 }}" 
                icon="feather-calendar" 
                color="primary" 
                variant="compact" />
        </div>
        <div class="col-sm-6 col-xl-3">
            <x-ui.stat-widget 
                title="{{ __('visitor.today_checked_in') }}" 
                value="{{ $stats['today_checked_in'] ?? 0 }}" 
                icon="feather-log-in" 
                color="info" 
                variant="compact" />
        </div>
        <div class="col-sm-6 col-xl-3">
            <x-ui.stat-widget 
                title="{{ __('visitor.fee_charge') }}" 
                value="{{ format_currency($stats['today_fees'] ?? 0) }}" 
                icon="feather-credit-card" 
                color="warning" 
                variant="compact" />
        </div>
    </div>

    <!-- 2. Main Panel: Table, Search & Common Filter Toolbar -->
    <div class="erp-single-panel">
        
        {{-- Header: Title & Actions --}}
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <h5 class="fw-bold text-dark mb-0">{{ __('visitor.visitor_passes') }}</h5>
            
            <div class="d-flex align-items-center flex-wrap gap-2">
                <!-- Outside Search Box (CRM/HRMS Common Style) -->
                <form method="GET" action="{{ route('visitor.index') }}" class="d-flex align-items-center bg-light border rounded px-2.5 py-0.5 me-1" style="height: 34px; min-width: 250px;">
                    @foreach(request()->except(['search', 'page']) as $k => $v)
                        @if(is_scalar($v) && $v !== '')
                            <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                        @endif
                    @endforeach
                    <i class="feather-search text-muted me-2" style="font-size: 13px;"></i>
                    <input type="text" name="search" class="form-control border-0 bg-transparent p-0 fs-12 text-dark" placeholder="{{ __('visitor.search_placeholder') }}" value="{{ request('search') }}" style="box-shadow: none; outline: none;">
                    @if(request('search'))
                        <a href="{{ route('visitor.index', request()->except(['search', 'page'])) }}" class="text-muted text-decoration-none ms-1" title="Clear Search">
                            <i class="feather-x fs-12"></i>
                        </a>
                    @endif
                </form>

                <!-- Common Filter Component (Lead Module Style) -->
                <form method="GET" action="{{ route('visitor.index') }}" class="d-inline">
                    @if(request('search'))
                        <input type="hidden" name="search" value="{{ request('search') }}">
                    @endif
                    <x-ui.filter :label="__('crm.filter') ?? 'Filter'" offset="0, 5">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('crm.filter_options') ?? 'Filter Options' }}</h6>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('visitor.status') }}</label>
                            <x-ui.odoo-form-ui type="select" name="status">
                                <option value="">— All Statuses —</option>
                                <option value="Checked-In" {{ request('status') === 'Checked-In' ? 'selected' : '' }}>{{ __('visitor.statuses.Checked-In') }}</option>
                                <option value="Expected" {{ request('status') === 'Expected' ? 'selected' : '' }}>{{ __('visitor.statuses.Expected') }}</option>
                                <option value="Checked-Out" {{ request('status') === 'Checked-Out' ? 'selected' : '' }}>{{ __('visitor.statuses.Checked-Out') }}</option>
                                <option value="Waiting Approval" {{ request('status') === 'Waiting Approval' ? 'selected' : '' }}>{{ __('visitor.statuses.Waiting Approval') }}</option>
                                <option value="Approved" {{ request('status') === 'Approved' ? 'selected' : '' }}>{{ __('visitor.statuses.Approved') }}</option>
                                <option value="Rejected" {{ request('status') === 'Rejected' ? 'selected' : '' }}>{{ __('visitor.statuses.Rejected') }}</option>
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('visitor.purpose_of_visit') }}</label>
                            <x-ui.odoo-form-ui type="select" name="purpose">
                                <option value="">— All Purposes —</option>
                                <option value="Meeting" {{ request('purpose') === 'Meeting' ? 'selected' : '' }}>{{ __('visitor.purposes.Meeting') }}</option>
                                <option value="Interview" {{ request('purpose') === 'Interview' ? 'selected' : '' }}>{{ __('visitor.purposes.Interview') }}</option>
                                <option value="Vendor" {{ request('purpose') === 'Vendor' ? 'selected' : '' }}>{{ __('visitor.purposes.Vendor') }}</option>
                                <option value="Delivery" {{ request('purpose') === 'Delivery' ? 'selected' : '' }}>{{ __('visitor.purposes.Delivery') }}</option>
                                <option value="Audit" {{ request('purpose') === 'Audit' ? 'selected' : '' }}>{{ __('visitor.purposes.Audit') }}</option>
                                <option value="Personal" {{ request('purpose') === 'Personal' ? 'selected' : '' }}>{{ __('visitor.purposes.Personal') }}</option>
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('visitor.select_host') }}</label>
                            <x-ui.odoo-form-ui type="select" name="host_user_id">
                                <option value="">— All Hosts —</option>
                                @foreach($hosts ?? [] as $h)
                                    <option value="{{ $h->id }}" {{ (string)request('host_user_id') === (string)$h->id ? 'selected' : '' }}>{{ $h->name }} ({{ $h->email }})</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('crm.date_from') ?? 'Date From' }}</label>
                                <x-ui.odoo-form-ui type="input" inputType="date" name="date_from" value="{{ request('date_from') }}" />
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('crm.date_to') ?? 'Date To' }}</label>
                                <x-ui.odoo-form-ui type="input" inputType="date" name="date_to" value="{{ request('date_to') }}" />
                            </div>
                        </div>

                        <div class="d-flex gap-2 justify-content-end mt-4">
                            <a href="{{ route('visitor.index') }}" class="btn btn-sm btn-light border">{{ __('crm.reset') ?? 'Reset' }}</a>
                            <button type="submit" class="btn btn-sm btn-primary">{{ __('crm.apply_filters') ?? 'Apply Filters' }}</button>
                        </div>
                    </x-ui.filter>
                </form>
            </div>
        </div>

        {{-- 2. Status Tabs (Using Common Horizontal Tabs Component) --}}
        @php
            $activeStatus = request('status');
            $visitorTabs = [
                [
                    'id' => 'tab-all-passes',
                    'label' => 'All Passes (' . ($stats['total_passes'] ?? 0) . ')',
                    'url' => request()->fullUrlWithQuery(['status' => null, 'page' => null]),
                    'active' => empty($activeStatus),
                ],
                [
                    'id' => 'tab-checked-in',
                    'label' => 'Inside Premises (' . ($stats['active_inside'] ?? 0) . ')',
                    'url' => request()->fullUrlWithQuery(['status' => 'Checked-In', 'page' => null]),
                    'active' => $activeStatus === 'Checked-In',
                ],
                [
                    'id' => 'tab-waiting-approval',
                    'label' => 'Waiting Approval (' . ($stats['waiting_approval'] ?? 0) . ')',
                    'url' => request()->fullUrlWithQuery(['status' => 'Waiting Approval', 'page' => null]),
                    'active' => $activeStatus === 'Waiting Approval',
                ],
                [
                    'id' => 'tab-expected',
                    'label' => 'Expected (' . ($stats['today_expected'] ?? 0) . ')',
                    'url' => request()->fullUrlWithQuery(['status' => 'Expected', 'page' => null]),
                    'active' => $activeStatus === 'Expected',
                ],
                [
                    'id' => 'tab-checked-out',
                    'label' => 'Checked-Out (' . ($stats['total_checked_out'] ?? 0) . ')',
                    'url' => request()->fullUrlWithQuery(['status' => 'Checked-Out', 'page' => null]),
                    'active' => $activeStatus === 'Checked-Out',
                ],
            ];
        @endphp
        <x-ui.horizontal-tabs id="visitorStatusTabs" class="mb-3" :tabs="$visitorTabs" />

        <!-- 3. Visitor Passes Table (Using Common x-ui.odoo-form-ui table) -->
        <div class="table-responsive">
            <x-ui.odoo-form-ui type="table" id="visitorPassesTable" class="mb-0">
                <thead>
                    <tr style="background-color: #e8ecf1 !important;">
                        <th style="width: 35px; background-color: #e8ecf1 !important;" class="text-center">
                            <input type="checkbox" class="form-check-input">
                        </th>
                        <th style="width: 14%; background-color: #e8ecf1 !important;">{{ __('visitor.pass_number') }}</th>
                        <th style="width: 20%; background-color: #e8ecf1 !important;">{{ __('visitor.visitor_name') }}</th>
                        <th style="width: 15%; background-color: #e8ecf1 !important;">{{ __('visitor.company') }}</th>
                        <th style="width: 15%; background-color: #e8ecf1 !important;">{{ __('visitor.select_host') }}</th>
                        <th style="width: 12%; background-color: #e8ecf1 !important;">{{ __('visitor.purpose_of_visit') }}</th>
                        <th style="width: 12%; background-color: #e8ecf1 !important;">{{ __('visitor.check_in_time') }}</th>
                        <th style="width: 10%; background-color: #e8ecf1 !important;" class="text-end pe-3">{{ __('visitor.fee_charge') }}</th>
                        <th style="width: 10%; background-color: #e8ecf1 !important;">{{ __('visitor.status') }}</th>
                        <th style="width: 5%; background-color: #e8ecf1 !important;" class="text-end pe-3">{{ __('visitor.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($passes as $pass)
                        <tr>
                            <td class="text-center">
                                <input type="checkbox" class="form-check-input">
                            </td>

                            <!-- Pass Number & Entry details -->
                            <td>
                                <a href="{{ route('visitor.passes.show', $pass->id) }}" class="fw-bold text-dark font-monospace text-decoration-none">
                                    {{ $pass->pass_number }}
                                </a>
                                <div class="fs-11 text-muted">{{ $pass->gate_number ?? 'Gate 1' }} • {{ $pass->entry_type }}</div>
                            </td>

                            <!-- Visitor Info & Avatar -->
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    @if(!empty($pass->visitor?->photo_url))
                                        <img src="{{ $pass->visitor->photo_url }}" class="visitor-avatar" alt="Photo">
                                    @else
                                        <div class="visitor-avatar-placeholder">
                                            {{ strtoupper(substr($pass->visitor?->full_name ?? 'V', 0, 1)) }}
                                        </div>
                                    @endif
                                    <div>
                                        <div class="fw-bold text-dark fs-13">{{ $pass->visitor?->full_name ?? 'N/A' }}</div>
                                        <div class="fs-11 text-muted">
                                            <i class="feather-phone me-1 text-muted"></i>{{ $pass->visitor?->phone ?? '—' }}
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- Company / Organization -->
                            <td>
                                <div class="fw-medium text-dark">{{ $pass->visitor?->company_name ?: '—' }}</div>
                                @if(!empty($pass->visitor?->designation))
                                    <div class="fs-11 text-muted">{{ $pass->visitor->designation }}</div>
                                @endif
                            </td>

                            <!-- Host (Employee) -->
                            <td>
                                <div class="fw-semibold text-primary">
                                    <i class="feather-user me-1 text-muted"></i>{{ $pass->host?->name ?: 'Direct Reception' }}
                                </div>
                                <div class="fs-10 text-muted">{{ $pass->host?->email ?? '' }}</div>
                            </td>

                            <!-- Purpose & Belongings -->
                            <td>
                                <span class="badge bg-light text-dark border fw-medium px-2 py-1">
                                    {{ __('visitor.purposes.' . $pass->purpose, [], null) ?? $pass->purpose }}
                                </span>
                                @if($pass->belongings && $pass->belongings->count() > 0)
                                    <span class="badge bg-soft-info text-info ms-1" title="Belongings Recorded">
                                        <i class="feather-briefcase me-1"></i>{{ $pass->belongings->count() }}
                                    </span>
                                @endif
                            </td>

                            <!-- Timestamps -->
                            <td>
                                @if($pass->check_in_at)
                                    <div class="text-success fw-semibold fs-12">
                                        <i class="feather-log-in me-1"></i>{{ \Carbon\Carbon::parse($pass->check_in_at)->format('h:i A') }}
                                    </div>
                                    @if($pass->check_out_at)
                                        <div class="text-danger fw-semibold fs-11 mt-0.5">
                                            <i class="feather-log-out me-1"></i>{{ \Carbon\Carbon::parse($pass->check_out_at)->format('h:i A') }}
                                        </div>
                                    @endif
                                @else
                                    <span class="text-muted fs-12">
                                        {{ $pass->expected_arrival_at ? \Carbon\Carbon::parse($pass->expected_arrival_at)->format('d M, h:i A') : '—' }}
                                    </span>
                                @endif
                            </td>

                            <!-- Fee (Formatted Currency) -->
                            <td class="text-end pe-3">
                                <span class="fw-bold text-dark fs-12">{{ format_currency($pass->fee_amount ?? 0) }}</span>
                            </td>

                            <!-- Status Badge Component -->
                            <td>
                                @php
                                    $statusKey = match($pass->status) {
                                        'Checked-In'       => 'approved',
                                        'Checked-Out'      => 'completed',
                                        'Approved'         => 'active',
                                        'Waiting Approval' => 'pending_approval',
                                        'Rejected'         => 'rejected',
                                        default            => 'in_progress',
                                    };
                                @endphp
                                <x-ui.status-badge :status="$statusKey" :label="__('visitor.statuses.' . $pass->status, [], null) ?? $pass->status" dot />
                            </td>

                            <!-- Actions (Using Common x-ui.action-dropdown) -->
                            <td class="text-end pe-3">
                                <x-ui.action-dropdown :viewUrl="route('visitor.passes.show', $pass->id)">
                                    <x-slot:extraActions>
                                        @if($pass->status === 'Expected' || $pass->status === 'Approved')
                                            <form method="POST" action="{{ route('visitor.passes.check-in', $pass->id) }}" class="d-inline">
                                                @csrf
                                                <button type="submit" class="action-dropdown-btn text-success" title="{{ __('visitor.check_in') }}">
                                                    <i class="feather-log-in"></i>
                                                </button>
                                            </form>
                                        @elseif($pass->status === 'Waiting Approval')
                                            <span class="action-dropdown-btn text-warning" title="Waiting Host Approval" style="cursor: not-allowed; opacity: 0.75;">
                                                <i class="feather-clock"></i>
                                            </span>
                                        @elseif($pass->status === 'Checked-In')
                                            <form method="POST" action="{{ route('visitor.passes.check-out', $pass->id) }}" class="d-inline">
                                                @csrf
                                                <button type="submit" class="action-dropdown-btn text-danger" title="{{ __('visitor.check_out') }}">
                                                    <i class="feather-log-out"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </x-slot:extraActions>

                                    <li>
                                        <a href="{{ route('visitor.passes.show', $pass->id) }}" class="dropdown-item">
                                            <i class="feather-printer me-2 text-primary fs-12"></i> {{ __('visitor.print_badge') }}
                                        </a>
                                    </li>
                                    @if($pass->status === 'Expected' || $pass->status === 'Approved')
                                        <li>
                                            <form method="POST" action="{{ route('visitor.passes.check-in', $pass->id) }}">
                                                @csrf
                                                <button type="submit" class="dropdown-item text-success">
                                                    <i class="feather-log-in me-2 text-success fs-12"></i> {{ __('visitor.check_in') }}
                                                </button>
                                            </form>
                                        </li>
                                    @endif
                                    @if($pass->status === 'Checked-In')
                                        <li>
                                            <form method="POST" action="{{ route('visitor.passes.check-out', $pass->id) }}">
                                                @csrf
                                                <button type="submit" class="dropdown-item text-danger">
                                                    <i class="feather-log-out me-2 text-danger fs-12"></i> {{ __('visitor.check_out') }}
                                                </button>
                                            </form>
                                        </li>
                                    @endif
                                </x-ui.action-dropdown>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center py-5 text-muted">
                                <i class="feather-users fs-1 d-block mb-3 text-light"></i>
                                <div class="fs-14 fw-medium">{{ __('visitor.no_visitors_found') }}</div>
                                <x-ui.button variant="primary" data-bs-toggle="offcanvas" data-bs-target="#newVisitorDrawer" icon="feather-plus" class="mt-3">
                                    {{ __('visitor.new_visitor_pass') }}
                                </x-ui.button>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.odoo-form-ui>
        </div>

        <!-- 4. Pagination (Lead Module Standard Style) -->
        <div class="pt-3">
            <x-ui.pagination
                :currentPage="$passes->currentPage()"
                :totalPages="$passes->lastPage()"
                :totalResults="$passes->total()"
                :perPage="$passes->perPage()" />
        </div>
    </div>

    <!-- 5. Offcanvas Drawer: Create New Visitor Pass (Using Common x-ui.drawer & x-ui.odoo-form-ui) -->
    <!-- 5. Offcanvas Drawer: Create New Visitor Pass -->
    <x-ui.drawer 
        id="newVisitorDrawer" 
        title="<div class='d-flex align-items-center gap-2'><div class='avatar-sm bg-primary-subtle text-primary rounded-3 d-flex align-items-center justify-content-center' style='width: 34px; height: 34px;'><i class='feather-user-plus fs-16'></i></div><div><div class='fw-bold fs-15 text-dark leading-tight'>{{ __('visitor.new_visitor_pass') }}</div><div class='fs-11 text-muted fw-normal'>Register entry & issue gate pass</div></div></div>" 
        scroll 
        style="--bs-offcanvas-width: min(780px, 95vw);">

        <form method="POST" action="{{ route('visitor.store') }}" id="newVisitorDrawerForm" class="p-1">
            @csrf

            <!-- Returning Visitor Live Detection Card -->
            <div id="returningVisitorAlertDrawer" class="card border border-success-subtle bg-success-subtle rounded-3 shadow-none mb-3 d-none">
                <div class="card-body p-3 d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2.5">
                        <div class="avatar-sm bg-success text-white rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 38px; height: 38px;">
                            <i class="feather-check-circle fs-18"></i>
                        </div>
                        <div>
                            <div class="d-flex align-items-center gap-2">
                                <strong class="text-success-emphasis fs-13">{{ __('visitor.returning_visitor_found') }}</strong>
                                <span class="badge bg-success text-white px-2 py-0.5 fs-10 fw-bold" id="returningVisitorVisitsTextDrawer">Visited</span>
                            </div>
                            <div class="fs-11 text-muted">{{ __('visitor.auto_filled_notice') }}</div>
                        </div>
                    </div>
                    <span class="badge bg-white text-success border border-success-subtle px-2.5 py-1 fs-11 fw-semibold">
                        <i class="feather-shield text-success me-1"></i> Verified Record
                    </span>
                </div>
            </div>

            <!-- Card 1: Visitor Information & Smart Lookup -->
            <div class="card border border-slate-200 rounded-3 shadow-none mb-3 bg-white">
                <div class="card-header bg-slate-50 border-bottom py-2.5 px-3 d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                        <span class="avatar-xs bg-primary-subtle text-primary rounded-2 d-flex align-items-center justify-content-center" style="width: 24px; height: 24px;">
                            <i class="feather-user fs-12"></i>
                        </span>
                        <h6 class="fw-bold text-dark fs-12 mb-0 text-uppercase tracking-wider">{{ __('visitor.visitor_details') }}</h6>
                    </div>
                    <span class="badge bg-light text-muted border fs-10 fw-semibold">Step 1</span>
                </div>
                <div class="card-body p-3">
                    <div class="row g-2">
                        <!-- Phone Number (with fast auto-lookup) -->
                        <div class="col-md-6">
                            <div class="position-relative">
                                <span id="phoneLookupSpinnerDrawer" class="text-primary fs-11 d-none fw-normal position-absolute end-0 top-0 mt-1 me-1">
                                    <i class="feather-loader icon-spin me-1"></i> Looking up...
                                </span>
                                <x-ui.modal-form-ui 
                                    type="input" 
                                    inputType="tel" 
                                    name="phone" 
                                    id="drawerVisitorPhone" 
                                    :label="__('visitor.phone_number')" 
                                    :required="true" 
                                    placeholder="e.g. +91 9876543210" 
                                    oninput="debounceVisitorLookupDrawer(this.value)" 
                                    helperText="Type phone for instant repeat visitor lookup" />
                            </div>
                        </div>

                        <!-- Visitor Full Name -->
                        <div class="col-md-6">
                            <x-ui.modal-form-ui 
                                type="input" 
                                name="full_name" 
                                id="drawerVisitorFullName" 
                                :label="__('visitor.visitor_name')" 
                                :required="true" 
                                placeholder="e.g. Rajesh Sharma" />
                        </div>

                        <!-- Email Address -->
                        <div class="col-md-6">
                            <x-ui.modal-form-ui 
                                type="input" 
                                inputType="email" 
                                name="email" 
                                id="drawerVisitorEmail" 
                                :label="__('visitor.email_address')" 
                                placeholder="e.g. visitor@company.com" />
                        </div>

                        <!-- Company / Organization -->
                        <div class="col-md-6">
                            <x-ui.modal-form-ui 
                                type="input" 
                                name="company_name" 
                                id="drawerVisitorCompany" 
                                :label="__('visitor.company')" 
                                placeholder="e.g. Tata Consultancy Services" />
                        </div>

                        <!-- Designation -->
                        <div class="col-md-6">
                            <x-ui.modal-form-ui 
                                type="input" 
                                name="designation" 
                                id="drawerVisitorDesignation" 
                                :label="__('visitor.designation')" 
                                placeholder="e.g. Senior Consultant / Vendor Rep" />
                        </div>

                        <!-- ID Proof Type & Number -->
                        <div class="col-md-3">
                            <x-ui.modal-form-ui 
                                type="select" 
                                name="id_proof_type" 
                                id="drawerVisitorIdType" 
                                :label="__('visitor.id_proof')" 
                                :searchable="false">
                                <option value="">— Type —</option>
                                <option value="National ID">Aadhaar / National ID</option>
                                <option value="Driving License">Driving License</option>
                                <option value="Passport">Passport</option>
                                <option value="Company ID">Company ID Card</option>
                                <option value="Other">Other</option>
                            </x-ui.modal-form-ui>
                        </div>
                        <div class="col-md-3">
                            <x-ui.modal-form-ui 
                                type="input" 
                                name="id_proof_number" 
                                id="drawerVisitorIdNumber" 
                                :label="__('visitor.id_proof_number')" 
                                placeholder="e.g. DL-987456" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card 2: Host & Visit Purpose Details -->
            <div class="card border border-slate-200 rounded-3 shadow-none mb-3 bg-white">
                <div class="card-header bg-slate-50 border-bottom py-2.5 px-3 d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                        <span class="avatar-xs bg-info-subtle text-info rounded-2 d-flex align-items-center justify-content-center" style="width: 24px; height: 24px;">
                            <i class="feather-compass fs-12"></i>
                        </span>
                        <h6 class="fw-bold text-dark fs-12 mb-0 text-uppercase tracking-wider">{{ __('visitor.pass_information') }}</h6>
                    </div>
                    <span class="badge bg-light text-muted border fs-10 fw-semibold">Step 2</span>
                </div>
                <div class="card-body p-3">
                    <div class="row g-2">
                        <!-- Host Selection -->
                        <div class="col-md-6">
                            <x-ui.modal-form-ui 
                                type="select" 
                                name="host_user_id" 
                                id="drawerHostUserId" 
                                :label="__('visitor.select_host')" 
                                :required="true" 
                                :searchable="false">
                                <option value="">— {{ __('visitor.select_host') }} —</option>
                                @foreach($hosts ?? [] as $host)
                                    <option value="{{ $host->id }}">{{ $host->name }} ({{ $host->email }})</option>
                                @endforeach
                            </x-ui.modal-form-ui>
                        </div>

                        <!-- Purpose of Visit -->
                        <div class="col-md-6">
                            <x-ui.modal-form-ui 
                                type="select" 
                                name="purpose" 
                                id="drawerPurpose" 
                                :label="__('visitor.purpose_of_visit')" 
                                :required="true" 
                                :searchable="false">
                                <option value="Meeting">Meeting / Discussion</option>
                                <option value="Interview">Job Interview</option>
                                <option value="Vendor">Vendor / Supplier Visit</option>
                                <option value="Delivery">Courier / Delivery</option>
                                <option value="Audit">Audit / Inspection</option>
                                <option value="Personal">Personal Visit</option>
                            </x-ui.modal-form-ui>
                        </div>

                        <!-- Gate Number -->
                        <div class="col-md-4">
                            <x-ui.modal-form-ui 
                                type="input" 
                                name="gate_number" 
                                id="drawerGateNumber" 
                                :label="__('visitor.gate_number')" 
                                value="Main Gate 1" />
                        </div>

                        <!-- Pass Fee / Charge -->
                        <div class="col-md-4">
                            <x-ui.modal-form-ui 
                                type="input" 
                                inputType="number" 
                                name="fee_amount" 
                                id="drawerFeeAmount" 
                                :label="__('visitor.fee_charge') . ' (' . active_currency_symbol() . ')'" 
                                value="0.00" 
                                step="0.01" />
                        </div>

                        <!-- Expected Arrival Time -->
                        <div class="col-md-4">
                            <x-ui.modal-form-ui 
                                type="input" 
                                inputType="datetime-local" 
                                name="expected_arrival_at" 
                                id="drawerExpectedArrival" 
                                :label="__('visitor.expected_time')" 
                                value="{{ now()->format('Y-m-d\TH:i') }}" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card 3: Belongings & Equipment -->
            <div class="card border border-slate-200 rounded-3 shadow-none mb-3 bg-white">
                <div class="card-header bg-slate-50 border-bottom py-2.5 px-3 d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                        <span class="avatar-xs bg-warning-subtle text-warning rounded-2 d-flex align-items-center justify-content-center" style="width: 24px; height: 24px;">
                            <i class="feather-package fs-12"></i>
                        </span>
                        <h6 class="fw-bold text-dark fs-12 mb-0 text-uppercase tracking-wider">{{ __('visitor.belongings') }}</h6>
                    </div>
                    <span class="badge bg-light text-muted border fs-10 fw-normal">Optional</span>
                </div>
                <div class="card-body p-3">
                    <div class="row g-2">
                        <div class="col-md-6">
                            <x-ui.modal-form-ui 
                                type="input" 
                                name="item_type" 
                                id="drawerItemType" 
                                :label="__('visitor.item_type')" 
                                placeholder="e.g. Dell Latitude 7420 Laptop" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.modal-form-ui 
                                type="input" 
                                name="serial_number" 
                                id="drawerSerialNumber" 
                                :label="__('visitor.serial_number')" 
                                placeholder="e.g. CN-0G541298" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card 4: Live Photo Studio -->
            <div class="card border border-slate-200 rounded-3 shadow-none mb-3 bg-white">
                <div class="card-header bg-slate-50 border-bottom py-2.5 px-3 d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                        <span class="avatar-xs bg-success-subtle text-success rounded-2 d-flex align-items-center justify-content-center" style="width: 24px; height: 24px;">
                            <i class="feather-camera fs-12"></i>
                        </span>
                        <h6 class="fw-bold text-dark fs-12 mb-0 text-uppercase tracking-wider">{{ __('visitor.live_camera_capture') }}</h6>
                    </div>
                    <span class="badge bg-light text-muted border fs-10 fw-normal">Badge Photo</span>
                </div>
                <div class="card-body p-3 text-center">
                    <input type="hidden" name="photo_url" id="drawerVisitorPhotoData">
                    
                    <div id="drawerWebcamContainer" class="d-none mb-2.5">
                        <video id="drawerWebcamVideo" width="220" height="165" autoplay playsinline class="rounded-3 border border-2 border-primary shadow-sm"></video>
                        <canvas id="drawerWebcamCanvas" width="220" height="165" class="d-none"></canvas>
                    </div>

                    <div id="drawerPhotoPreviewContainer" class="d-none mb-2.5">
                        <img id="drawerPhotoPreview" src="" alt="Captured Photo" width="100" height="100" class="rounded-circle border border-3 border-success object-fit-cover shadow-sm">
                    </div>

                    <div class="d-flex justify-content-center gap-2">
                        <button type="button" class="btn btn-sm btn-outline-primary fw-semibold px-3" id="btnStartCameraDrawer" onclick="startWebcamDrawer()">
                            <i class="feather-camera me-1"></i> Open Webcam
                        </button>
                        <button type="button" class="btn btn-sm btn-success d-none fw-semibold px-3" id="btnCapturePhotoDrawer" onclick="captureWebcamPhotoDrawer()">
                            <i class="feather-check me-1"></i> {{ __('visitor.capture_photo') }}
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger d-none fw-semibold px-3" id="btnRetakePhotoDrawer" onclick="retakeWebcamPhotoDrawer()">
                            <i class="feather-refresh-cw me-1"></i> {{ __('visitor.retake_photo') }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Card 5: Entry Workflow & Pass Type (Interactive Cards) -->
            <div class="card border border-slate-200 rounded-3 shadow-none mb-3 bg-white">
                <div class="card-header bg-slate-50 border-bottom py-2.5 px-3 d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                        <span class="avatar-xs bg-primary-subtle text-primary rounded-2 d-flex align-items-center justify-content-center" style="width: 24px; height: 24px;">
                            <i class="feather-shield fs-12"></i>
                        </span>
                        <h6 class="fw-bold text-dark fs-12 mb-0 text-uppercase tracking-wider">Entry Workflow & Pass Type</h6>
                    </div>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-10 fw-bold">Select Action</span>
                </div>
                <div class="card-body p-3">
                    <div class="row g-2">
                        <div class="col-md-4">
                            <label class="workflow-radio-card d-block p-2.5 border rounded-3 cursor-pointer h-100 position-relative transition-all" for="drawerActionCheckIn" style="border: 1.5px solid #cbd5e1; background: #f8fafc;">
                                <div class="form-check mb-0">
                                    <input class="form-check-input" type="radio" name="drawer_action_type" id="drawerActionCheckIn" value="checkin" checked onchange="toggleDrawerActionType(this.value)">
                                    <label class="form-check-label fw-bold fs-12 text-dark d-block cursor-pointer" for="drawerActionCheckIn">
                                        <i class="feather-check-circle text-success me-1"></i> Instant Check-in
                                    </label>
                                </div>
                                <div class="fs-11 text-muted mt-1 ps-4">Visitor enters immediately; pass status: <strong>Checked-In</strong>.</div>
                            </label>
                        </div>
                        <div class="col-md-4">
                            <label class="workflow-radio-card d-block p-2.5 border rounded-3 cursor-pointer h-100 position-relative transition-all" for="drawerActionApproval" style="border: 1.5px solid #cbd5e1; background: #f8fafc;">
                                <div class="form-check mb-0">
                                    <input class="form-check-input" type="radio" name="drawer_action_type" id="drawerActionApproval" value="approval" onchange="toggleDrawerActionType(this.value)">
                                    <label class="form-check-label fw-bold fs-12 text-dark d-block cursor-pointer" for="drawerActionApproval">
                                        <i class="feather-clock text-warning me-1"></i> Host Approval
                                    </label>
                                </div>
                                <div class="fs-11 text-muted mt-1 ps-4">Requires host employee consent before gate entry.</div>
                            </label>
                        </div>
                        <div class="col-md-4">
                            <label class="workflow-radio-card d-block p-2.5 border rounded-3 cursor-pointer h-100 position-relative transition-all" for="drawerActionExpected" style="border: 1.5px solid #cbd5e1; background: #f8fafc;">
                                <div class="form-check mb-0">
                                    <input class="form-check-input" type="radio" name="drawer_action_type" id="drawerActionExpected" value="expected" onchange="toggleDrawerActionType(this.value)">
                                    <label class="form-check-label fw-bold fs-12 text-dark d-block cursor-pointer" for="drawerActionExpected">
                                        <i class="feather-calendar text-primary me-1"></i> Pre-Register
                                    </label>
                                </div>
                                <div class="fs-11 text-muted mt-1 ps-4">Pre-schedule guest for future arrival date/time.</div>
                            </label>
                        </div>
                    </div>
                    <input type="hidden" name="check_in_now" id="drawerCheckInNowHidden" value="1">
                    <input type="hidden" name="status" id="drawerStatusHidden" value="Checked-In">
                </div>
            </div>

            <!-- Card 6: Notes & Remarks -->
            <div class="card border border-slate-200 rounded-3 shadow-none mb-3 bg-white">
                <div class="card-body p-3">
                    <x-ui.modal-form-ui 
                        type="textarea" 
                        name="notes" 
                        id="drawerNotes" 
                        label="Security Notes / Remarks" 
                        :rows="2" 
                        placeholder="Any vehicle number, parking bay, or luggage details..." />
                </div>
            </div>

            <!-- Drawer Bottom Sticky Action Bar -->
            <div class="d-flex align-items-center justify-content-between gap-2 pt-3 border-top mt-3 bg-white sticky-bottom pb-2">
                <button type="button" class="btn btn-light border px-3 fw-semibold" data-bs-dismiss="offcanvas">
                    <i class="feather-x me-1"></i> Cancel
                </button>
                <div class="d-flex align-items-center gap-2">
                    <x-ui.button type="submit" variant="primary" icon="feather-check" class="px-4 fw-bold">
                        {{ __('visitor.new_visitor_pass') }}
                    </x-ui.button>
                </div>
            </div>
        </form>
    </x-ui.drawer>

    <!-- Smart Visual Import Modal (3-Step Column Mapping & Dry Run Importer) -->
    @include('modules.visitor.partials.smart-import-modal')

@endsection

@push('scripts')
<script>
    let drawerVideoStream = null;

    function startWebcamDrawer() {
        const video = document.getElementById('drawerWebcamVideo');
        const webcamContainer = document.getElementById('drawerWebcamContainer');
        const btnStart = document.getElementById('btnStartCameraDrawer');
        const btnCapture = document.getElementById('btnCapturePhotoDrawer');
        const photoPreviewContainer = document.getElementById('drawerPhotoPreviewContainer');

        if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
            navigator.mediaDevices.getUserMedia({ video: { width: 320, height: 240 } })
                .then(function(stream) {
                    drawerVideoStream = stream;
                    video.srcObject = stream;
                    video.play();
                    webcamContainer.classList.remove('d-none');
                    btnStart.classList.add('d-none');
                    btnCapture.classList.remove('d-none');
                    photoPreviewContainer.classList.add('d-none');
                })
                .catch(function(err) {
                    alert('Camera access error: ' + err.message);
                });
        } else {
            alert('Camera not supported in this browser.');
        }
    }

    function captureWebcamPhotoDrawer() {
        const video = document.getElementById('drawerWebcamVideo');
        const canvas = document.getElementById('drawerWebcamCanvas');
        const photoInput = document.getElementById('drawerVisitorPhotoData');
        const photoPreview = document.getElementById('drawerPhotoPreview');
        const webcamContainer = document.getElementById('drawerWebcamContainer');
        const photoPreviewContainer = document.getElementById('drawerPhotoPreviewContainer');
        const btnCapture = document.getElementById('btnCapturePhotoDrawer');
        const btnRetake = document.getElementById('btnRetakePhotoDrawer');

        const ctx = canvas.getContext('2d');
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
        const dataUrl = canvas.toDataURL('image/jpeg', 0.85);

        photoInput.value = dataUrl;
        photoPreview.src = dataUrl;

        if (drawerVideoStream) {
            drawerVideoStream.getTracks().forEach(track => track.stop());
            drawerVideoStream = null;
        }

        webcamContainer.classList.add('d-none');
        photoPreviewContainer.classList.remove('d-none');
        btnCapture.classList.add('d-none');
        btnRetake.classList.remove('d-none');
    }

    function retakeWebcamPhotoDrawer() {
        const btnRetake = document.getElementById('btnRetakePhotoDrawer');
        btnRetake.classList.add('d-none');
        document.getElementById('drawerVisitorPhotoData').value = '';
        startWebcamDrawer();
    }

    function toggleDrawerActionType(val) {
        const checkInNow = document.getElementById('drawerCheckInNowHidden');
        const status = document.getElementById('drawerStatusHidden');
        if (val === 'checkin') {
            checkInNow.value = '1';
            status.value = 'Checked-In';
        } else if (val === 'approval') {
            checkInNow.value = '0';
            status.value = 'Waiting Approval';
        } else {
            checkInNow.value = '0';
            status.value = 'Expected';
        }
    }

    let drawerLookupTimer = null;
    let isVisitorAutoFilled = false;

    function clearAutoFilledVisitorFields() {
        if (!isVisitorAutoFilled) return;

        const fullName = document.getElementById('drawerVisitorFullName');
        if (fullName) fullName.value = '';

        const email = document.getElementById('drawerVisitorEmail');
        if (email) email.value = '';

        const company = document.getElementById('drawerVisitorCompany');
        if (company) company.value = '';

        const designation = document.getElementById('drawerVisitorDesignation');
        if (designation) designation.value = '';

        const idType = document.getElementById('drawerVisitorIdType');
        if (idType) idType.value = '';

        const idNum = document.getElementById('drawerVisitorIdNumber');
        if (idNum) idNum.value = '';

        const photoData = document.getElementById('drawerVisitorPhotoData');
        if (photoData) photoData.value = '';

        const photoPreview = document.getElementById('drawerPhotoPreview');
        if (photoPreview) photoPreview.src = '';

        const photoPreviewContainer = document.getElementById('drawerPhotoPreviewContainer');
        if (photoPreviewContainer) photoPreviewContainer.classList.add('d-none');

        const btnStartCamera = document.getElementById('btnStartCameraDrawer');
        if (btnStartCamera) btnStartCamera.classList.remove('d-none');

        const btnRetake = document.getElementById('btnRetakePhotoDrawer');
        if (btnRetake) btnRetake.classList.add('d-none');

        const alertBox = document.getElementById('returningVisitorAlertDrawer');
        if (alertBox) alertBox.classList.add('d-none');

        isVisitorAutoFilled = false;
    }

    function debounceVisitorLookupDrawer(phone) {
        clearTimeout(drawerLookupTimer);
        const spinner = document.getElementById('phoneLookupSpinnerDrawer');
        const alertBox = document.getElementById('returningVisitorAlertDrawer');

        const trimmed = (phone || '').trim();

        if (trimmed.length < 5) {
            if (alertBox) alertBox.classList.add('d-none');
            if (spinner) spinner.classList.add('d-none');
            clearAutoFilledVisitorFields();
            return;
        }

        if (spinner) spinner.classList.remove('d-none');

        drawerLookupTimer = setTimeout(() => {
            fetch(`/visitor/lookup?phone=${encodeURIComponent(trimmed)}`)
                .then(res => res.json())
                .then(data => {
                    if (spinner) spinner.classList.add('d-none');
                    if (data.found && data.visitor) {
                        const v = data.visitor;
                        isVisitorAutoFilled = true;

                        if (alertBox) {
                            const visitsText = document.getElementById('returningVisitorVisitsTextDrawer');
                            if (visitsText) visitsText.textContent = `(Visits: ${v.total_visits || 1})`;
                            alertBox.classList.remove('d-none');
                        }

                        // Auto-fill form fields
                        const fullName = document.getElementById('drawerVisitorFullName');
                        if (fullName) fullName.value = v.full_name || '';

                        const email = document.getElementById('drawerVisitorEmail');
                        if (email) email.value = v.email || '';

                        const company = document.getElementById('drawerVisitorCompany');
                        if (company) company.value = v.company_name || '';

                        const designation = document.getElementById('drawerVisitorDesignation');
                        if (designation) designation.value = v.designation || '';

                        const idType = document.getElementById('drawerVisitorIdType');
                        if (idType) idType.value = v.id_proof_type || '';

                        const idNum = document.getElementById('drawerVisitorIdNumber');
                        if (idNum) idNum.value = v.id_proof_number || '';

                        if (v.photo_url) {
                            const photoData = document.getElementById('drawerVisitorPhotoData');
                            if (photoData) photoData.value = v.photo_url;

                            const photoPreview = document.getElementById('drawerPhotoPreview');
                            if (photoPreview) photoPreview.src = v.photo_url;

                            const photoPreviewContainer = document.getElementById('drawerPhotoPreviewContainer');
                            if (photoPreviewContainer) photoPreviewContainer.classList.remove('d-none');

                            const webcamContainer = document.getElementById('drawerWebcamContainer');
                            if (webcamContainer) webcamContainer.classList.add('d-none');

                            const btnRetake = document.getElementById('btnRetakePhotoDrawer');
                            if (btnRetake) btnRetake.classList.remove('d-none');

                            const btnStartCamera = document.getElementById('btnStartCameraDrawer');
                            if (btnStartCamera) btnStartCamera.classList.add('d-none');
                        }
                    } else {
                        // Phone changed and no matching visitor found -> clear auto-filled info
                        clearAutoFilledVisitorFields();
                    }
                })
                .catch(() => {
                    if (spinner) spinner.classList.add('d-none');
                });
        }, 300);
    }

    const drawerEl = document.getElementById('newVisitorDrawer');
    if (drawerEl) {
        drawerEl.addEventListener('hide.bs.offcanvas', function () {
            if (drawerVideoStream) {
                drawerVideoStream.getTracks().forEach(track => track.stop());
                drawerVideoStream = null;
            }
        });
    }
</script>
@endpush

