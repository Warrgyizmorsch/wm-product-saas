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
    .odoo-field-group {
        margin-bottom: 8px;
    }
    .odoo-field-label {
        font-size: 11.5px;
        font-weight: 700;
        color: #475569;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        margin-bottom: 2px;
        display: block;
    }
    .odoo-field-control {
        border: none !important;
        border-bottom: 1.5px solid #cbd5e1 !important;
        border-radius: 0 !important;
        padding: 5px 2px !important;
        background-color: transparent !important;
        font-size: 13.5px !important;
        color: #1e293b !important;
        width: 100% !important;
        outline: none !important;
        box-shadow: none !important;
        transition: border-color 0.2s ease-in-out, box-shadow 0.2s ease-in-out !important;
    }
    .odoo-field-control:focus {
        border-bottom-color: var(--bs-primary) !important;
        box-shadow: 0 1px 0 0 var(--bs-primary) !important;
    }
    .odoo-field-control.is-invalid,
    input.odoo-field-control.is-invalid,
    select.odoo-field-control.is-invalid,
    textarea.odoo-field-control.is-invalid {
        border-bottom-color: #dc3545 !important;
        box-shadow: 0 1px 0 0 #dc3545 !important;
    }
    .invalid-feedback.dynamic-error-feedback {
        display: block !important;
        font-size: 11.5px !important;
        font-weight: 500 !important;
        color: #dc3545 !important;
        margin-top: 3px !important;
    }
    .visitor-type-card {
        cursor: pointer;
        border: 1.5px solid #e2e8f0;
        border-radius: 8px;
        padding: 8px 12px;
        transition: all 0.2s ease;
        background: #ffffff;
    }
    .visitor-type-card:hover {
        border-color: var(--bs-primary);
        background: color-mix(in srgb, var(--bs-primary) 4%, transparent);
    }
    .visitor-type-card.active {
        border-color: var(--bs-primary);
        background: color-mix(in srgb, var(--bs-primary) 8%, transparent);
        font-weight: 700;
        color: var(--bs-primary);
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
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm rounded-3 mb-3 d-flex align-items-center" role="alert">
            <i class="feather-alert-octagon fs-18 text-danger me-2"></i>
            <div>{{ session('error') }}</div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- 1. Stats Widgets (Standard x-ui.stat-widget) -->
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
                title="{{ __('visitor.overstay_alerts') }}" 
                value="{{ $stats['overstayed_count'] ?? 0 }}" 
                icon="feather-alert-triangle" 
                color="{{ ($stats['overstayed_count'] ?? 0) > 0 ? 'danger' : 'secondary' }}" 
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
                title="{{ __('visitor.fee_charge') }}" 
                value="{{ format_currency($stats['today_fees'] ?? 0) }}" 
                icon="feather-credit-card" 
                color="warning" 
                variant="compact" />
        </div>
    </div>

    <!-- 2. Main Panel: Table, Search & Filter Toolbar -->
    <div class="erp-single-panel">
        
        {{-- Header: Title & Actions --}}
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <h5 class="fw-bold text-dark mb-0">{{ __('visitor.visitor_passes') }}</h5>
            
            <div class="d-flex align-items-center flex-wrap gap-2">
                <!-- Outside Search Box -->
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

                <!-- Filter Component -->
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
                                <option value="Overstayed" {{ request('status') === 'Overstayed' ? 'selected' : '' }}>{{ __('visitor.statuses.Overstayed') }}</option>
                                <option value="Waiting Approval" {{ request('status') === 'Waiting Approval' ? 'selected' : '' }}>{{ __('visitor.statuses.Waiting Approval') }}</option>
                                <option value="Arrived" {{ request('status') === 'Arrived' ? 'selected' : '' }}>{{ __('visitor.statuses.Arrived') }}</option>
                                <option value="Expected" {{ request('status') === 'Expected' ? 'selected' : '' }}>{{ __('visitor.statuses.Expected') }}</option>
                                <option value="Meeting in Progress" {{ request('status') === 'Meeting in Progress' ? 'selected' : '' }}>{{ __('visitor.statuses.Meeting in Progress') }}</option>
                                <option value="Checked-Out" {{ request('status') === 'Checked-Out' ? 'selected' : '' }}>{{ __('visitor.statuses.Checked-Out') }}</option>
                                <option value="Denied" {{ request('status') === 'Denied' ? 'selected' : '' }}>{{ __('visitor.statuses.Denied') }} / {{ __('visitor.statuses.Rejected') }}</option>
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('visitor.visitor_type') }}</label>
                            <x-ui.odoo-form-ui type="select" name="visitor_type">
                                <option value="">— All Visitor Types —</option>
                                <option value="Client" {{ request('visitor_type') === 'Client' ? 'selected' : '' }}>{{ __('visitor.visitor_types.Client') }}</option>
                                <option value="Vendor" {{ request('visitor_type') === 'Vendor' ? 'selected' : '' }}>{{ __('visitor.visitor_types.Vendor') }}</option>
                                <option value="Candidate" {{ request('visitor_type') === 'Candidate' ? 'selected' : '' }}>{{ __('visitor.visitor_types.Candidate') }}</option>
                                <option value="Service" {{ request('visitor_type') === 'Service' ? 'selected' : '' }}>{{ __('visitor.visitor_types.Service') }}</option>
                                <option value="Guest" {{ request('visitor_type') === 'Guest' ? 'selected' : '' }}>{{ __('visitor.visitor_types.Guest') }}</option>
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('visitor.purpose_of_visit') }}</label>
                            <x-ui.odoo-form-ui type="select" name="purpose">
                                <option value="">— All Purposes —</option>
                                <option value="Product Inquiry" {{ request('purpose') === 'Product Inquiry' ? 'selected' : '' }}>{{ __('visitor.purposes.Product Inquiry') }}</option>
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

        {{-- 2. Status Tabs --}}
        @php
            $activeStatus = request('status');
            $visitorTabs = [
                [
                    'id' => 'tab-all-passes',
                    'label' => __('visitor.all_passes') . ' (' . ($stats['total_passes'] ?? 0) . ')',
                    'url' => request()->fullUrlWithQuery(['status' => null, 'page' => null]),
                    'active' => empty($activeStatus),
                ],
                [
                    'id' => 'tab-checked-in',
                    'label' => __('visitor.inside_premises') . ' (' . ($stats['active_inside'] ?? 0) . ')',
                    'url' => request()->fullUrlWithQuery(['status' => 'Checked-In', 'page' => null]),
                    'active' => $activeStatus === 'Checked-In',
                ],
                [
                    'id' => 'tab-overstayed',
                    'label' => __('visitor.overstayed') . ' (' . ($stats['overstayed_count'] ?? 0) . ')',
                    'url' => request()->fullUrlWithQuery(['status' => 'Overstayed', 'page' => null]),
                    'active' => $activeStatus === 'Overstayed',
                ],
                [
                    'id' => 'tab-waiting-approval',
                    'label' => __('visitor.waiting_approval') . ' (' . ($stats['waiting_approval'] ?? 0) . ')',
                    'url' => request()->fullUrlWithQuery(['status' => 'Waiting Approval', 'page' => null]),
                    'active' => $activeStatus === 'Waiting Approval',
                ],
                [
                    'id' => 'tab-expected',
                    'label' => __('visitor.expected') . ' (' . ($stats['today_expected'] ?? 0) . ')',
                    'url' => request()->fullUrlWithQuery(['status' => 'Expected', 'page' => null]),
                    'active' => $activeStatus === 'Expected',
                ],
                [
                    'id' => 'tab-checked-out',
                    'label' => __('visitor.checked_out') . ' (' . ($stats['total_checked_out'] ?? 0) . ')',
                    'url' => request()->fullUrlWithQuery(['status' => 'Checked-Out', 'page' => null]),
                    'active' => $activeStatus === 'Checked-Out',
                ],
                [
                    'id' => 'tab-denied',
                    'label' => __('visitor.denied') . ' (' . ($stats['denied_count'] ?? 0) . ')',
                    'url' => request()->fullUrlWithQuery(['status' => 'Denied', 'page' => null]),
                    'active' => $activeStatus === 'Denied',
                ],
            ];
        @endphp
        <x-ui.horizontal-tabs id="visitorStatusTabs" class="mb-3" :tabs="$visitorTabs" />

        <!-- 3. Visitor Passes Table -->
        <div class="table-responsive">
            <x-ui.odoo-form-ui type="table" id="visitorPassesTable" class="mb-0">
                <thead>
                    <tr style="background-color: #e8ecf1 !important;">
                        <th style="width: 30px; background-color: #e8ecf1 !important;" class="text-center">
                            <input type="checkbox" class="form-check-input">
                        </th>
                        <th style="width: 14%; background-color: #e8ecf1 !important;">{{ __('visitor.pass_number') }}</th>
                        <th style="width: 18%; background-color: #e8ecf1 !important;">{{ __('visitor.visitor_name') }}</th>
                        <th style="width: 14%; background-color: #e8ecf1 !important;">{{ __('visitor.company') }}</th>
                        <th style="width: 12%; background-color: #e8ecf1 !important;">{{ __('visitor.vehicle_details') }}</th>
                        <th style="width: 14%; background-color: #e8ecf1 !important;">{{ __('visitor.select_host') }}</th>
                        <th style="width: 10%; background-color: #e8ecf1 !important;">{{ __('visitor.purpose_of_visit') }}</th>
                        <th style="width: 10%; background-color: #e8ecf1 !important;">{{ __('visitor.check_in_time') }}</th>
                        <th style="width: 8%; background-color: #e8ecf1 !important;">{{ __('visitor.status') }}</th>
                        <th style="width: 5%; background-color: #e8ecf1 !important;" class="text-end pe-3">{{ __('visitor.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($passes as $pass)
                        @php
                            $isOverstay = $pass->isOverstayed();
                        @endphp
                        <tr class="{{ $isOverstay ? 'table-warning' : '' }}">
                            <td class="text-center">
                                <input type="checkbox" class="form-check-input">
                            </td>

                            <!-- Pass Number & Type -->
                            <td>
                                <a href="{{ route('visitor.passes.show', $pass->id) }}" class="fw-bold text-dark font-monospace text-decoration-none">
                                    {{ $pass->pass_number }}
                                </a>
                                <div class="d-flex align-items-center gap-1 mt-0.5">
                                    <span class="badge bg-light text-muted border fs-10 px-1.5 py-0.5">{{ $pass->visitor_type ?? 'Client' }}</span>
                                    <span class="fs-11 text-muted">{{ $pass->gate_number ?? 'Gate 1' }}</span>
                                </div>
                            </td>

                            <!-- Visitor Info -->
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
                                        <div class="d-flex align-items-center flex-wrap gap-1.5">
                                            <span class="fw-bold text-dark fs-13">{{ $pass->visitor?->full_name ?? 'N/A' }}</span>
                                            @if($pass->source_module === 'crm' && $pass->source_reference_id)
                                                <a href="{{ route('crm.leads.show', $pass->source_reference_id) }}" class="badge bg-primary-subtle text-primary border border-primary-subtle fs-10 text-decoration-none" title="{{ __('visitor.linked_lead') }}">
                                                    <i class="feather-trending-up me-0.5"></i> {{ $pass->source_reference_no ?: 'CRM Lead' }}
                                                </a>
                                            @endif
                                            @if($pass->visitor?->is_blacklisted)
                                                <x-ui.status-badge status="blocked" :label="__('visitor.blacklisted')" :dot="true" size="sm" title="{{ $pass->visitor?->blacklist_reason }}" />
                                            @endif
                                            @if($pass->id_verification_status === 'Verified')
                                                <i class="feather-check-circle text-success fs-12" title="ID Verified"></i>
                                            @endif
                                        </div>
                                        <div class="fs-11 text-muted">
                                            <i class="feather-phone me-1 text-muted"></i>{{ $pass->visitor?->phone ?? '—' }}
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- Company -->
                            <td>
                                <div class="fw-medium text-dark">{{ $pass->visitor?->company_name ?: '—' }}</div>
                                @if(!empty($pass->visitor?->designation))
                                    <div class="fs-11 text-muted">{{ $pass->visitor->designation }}</div>
                                @endif
                            </td>

                            <!-- Vehicle & Accompanying -->
                            <td>
                                @if(!empty($pass->vehicle_number))
                                    <div class="fw-semibold font-monospace fs-11 text-dark">
                                        <i class="feather-truck me-1 text-muted"></i>{{ $pass->vehicle_number }}
                                    </div>
                                    @if(!empty($pass->parking_slot))
                                        <div class="fs-10 text-muted">Slot: {{ $pass->parking_slot }}</div>
                                    @endif
                                @else
                                    <span class="text-muted fs-11">Pedestrian</span>
                                @endif
                                @if($pass->accompanying_count > 0)
                                    <span class="badge bg-soft-secondary text-dark fs-10 mt-0.5" title="{{ $pass->accompanying_names }}">
                                        +{{ $pass->accompanying_count }} Guests
                                    </span>
                                @endif
                            </td>

                            <!-- Host -->
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
                                    <span class="badge bg-soft-info text-info ms-1" title="Assets Logged">
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
                                    @elseif($isOverstay)
                                        <span class="badge bg-danger text-white fs-10 px-1 py-0.5 mt-0.5">
                                            <i class="feather-clock me-1"></i>Overdue
                                        </span>
                                    @endif
                                @else
                                    <span class="text-muted fs-12">
                                        {{ $pass->expected_arrival_at ? \Carbon\Carbon::parse($pass->expected_arrival_at)->format('d M, h:i A') : '—' }}
                                    </span>
                                @endif
                            </td>

                            <!-- Status Badge -->
                            <td>
                                @php
                                    $statusKey = match($pass->status) {
                                        'Checked-In'          => 'approved',
                                        'Meeting in Progress' => 'active',
                                        'Checked-Out'         => 'completed',
                                        'Approved'            => 'active',
                                        'Arrived'             => 'in_progress',
                                        'Waiting Approval'    => 'pending_approval',
                                        'Rejected', 'Denied'  => 'rejected',
                                        default               => 'in_progress',
                                    };
                                @endphp
                                <x-ui.status-badge :status="$statusKey" :label="__('visitor.statuses.' . $pass->status, [], null) ?? $pass->status" dot />
                            </td>

                            <!-- Actions Dropdown -->
                            <td class="text-end pe-3">
                                <x-ui.action-dropdown :viewUrl="route('visitor.passes.show', $pass->id)">
                                    <x-slot:extraActions>
                                        @if($pass->status === 'Expected' || $pass->status === 'Approved' || $pass->status === 'Arrived')
                                            <form method="POST" action="{{ route('visitor.passes.check-in', $pass->id) }}" class="d-inline">
                                                @csrf
                                                <button type="submit" class="action-dropdown-btn text-success" title="{{ __('visitor.check_in') }}">
                                                    <i class="feather-log-in"></i>
                                                </button>
                                            </form>
                                        @elseif($pass->status === 'Waiting Approval')
                                            <form method="POST" action="{{ route('visitor.passes.notify-host', $pass->id) }}" class="d-inline">
                                                @csrf
                                                <button type="submit" class="action-dropdown-btn text-primary" title="{{ __('visitor.notify_host') }}">
                                                    <i class="feather-bell"></i>
                                                </button>
                                            </form>
                                        @elseif($pass->status === 'Checked-In' || $pass->status === 'Meeting in Progress')
                                            <button type="button" class="action-dropdown-btn text-danger" onclick="openCheckoutModal({{ $pass->id }}, '{{ $pass->pass_number }}', '{{ $pass->visitor?->full_name }}')" title="{{ __('visitor.check_out') }}">
                                                <i class="feather-log-out"></i>
                                            </button>
                                        @endif
                                    </x-slot:extraActions>

                                    <li>
                                        <a href="{{ route('visitor.passes.show', $pass->id) }}" class="dropdown-item">
                                            <i class="feather-printer me-2 text-primary fs-12"></i> {{ __('visitor.print_badge') }}
                                        </a>
                                    </li>

                                    @if($pass->status === 'Expected')
                                        <li>
                                            <form method="POST" action="{{ route('visitor.passes.mark-arrived', $pass->id) }}">
                                                @csrf
                                                <button type="submit" class="dropdown-item text-primary">
                                                    <i class="feather-map-pin me-2 text-primary fs-12"></i> {{ __('visitor.mark_arrived') }}
                                                </button>
                                            </form>
                                        </li>
                                    @endif

                                    @if($pass->status === 'Checked-In')
                                        <li>
                                            <form method="POST" action="{{ route('visitor.passes.start-meeting', $pass->id) }}">
                                                @csrf
                                                <button type="submit" class="dropdown-item text-info">
                                                    <i class="feather-users me-2 text-info fs-12"></i> {{ __('visitor.start_meeting') }}
                                                </button>
                                            </form>
                                        </li>
                                    @endif

                                    @if($pass->host_user_id)
                                        <li>
                                            <form method="POST" action="{{ route('visitor.passes.notify-host', $pass->id) }}">
                                                @csrf
                                                <button type="submit" class="dropdown-item text-secondary">
                                                    <i class="feather-bell me-2 text-secondary fs-12"></i> {{ __('visitor.notify_host') }}
                                                </button>
                                            </form>
                                        </li>
                                    @endif

                                    @if($pass->status === 'Checked-In' || $pass->status === 'Meeting in Progress')
                                        <li>
                                            <a href="javascript:void(0)" class="dropdown-item text-warning" onclick="openExtendModal({{ $pass->id }}, '{{ $pass->pass_number }}', {{ $pass->expected_duration_minutes ?: 60 }})">
                                                <i class="feather-clock me-2 text-warning fs-12"></i> {{ __('visitor.extend_visit') }}
                                            </a>
                                        </li>
                                        <li>
                                            <a href="javascript:void(0)" class="dropdown-item text-danger" onclick="openCheckoutModal({{ $pass->id }}, '{{ $pass->pass_number }}', '{{ $pass->visitor?->full_name }}')">
                                                <i class="feather-log-out me-2 text-danger fs-12"></i> {{ __('visitor.check_out') }}
                                            </a>
                                        </li>
                                    @endif

                                    @if($pass->status !== 'Denied' && $pass->status !== 'Rejected' && $pass->status !== 'Checked-Out')
                                        <li>
                                            <a href="javascript:void(0)" class="dropdown-item text-danger" onclick="openDenyModal({{ $pass->id }}, '{{ $pass->pass_number }}', '{{ $pass->visitor?->full_name }}')">
                                                <i class="feather-slash me-2 text-danger fs-12"></i> {{ __('visitor.deny_entry') }}
                                            </a>
                                        </li>
                                    @endif

                                    <li>
                                        <a href="javascript:void(0)" class="dropdown-item text-dark" onclick="openIncidentModal({{ $pass->id }}, '{{ $pass->pass_number }}')">
                                            <i class="feather-alert-octagon me-2 text-dark fs-12"></i> {{ __('visitor.report_incident') }}
                                        </a>
                                    </li>

                                    @if($pass->source_module === 'crm' && $pass->source_reference_id)
                                        <li>
                                            <a href="{{ route('crm.leads.show', $pass->source_reference_id) }}" class="dropdown-item text-primary">
                                                <i class="feather-trending-up me-2 text-primary fs-12"></i> {{ __('visitor.view_lead') }} ({{ $pass->source_reference_no }})
                                            </a>
                                        </li>
                                    @else
                                        <li>
                                            <a href="javascript:void(0)" class="dropdown-item text-success" onclick="openConvertLeadModalIndex({{ $pass->id }}, '{{ $pass->pass_number }}', '{{ addslashes($pass->visitor?->full_name ?? '') }}', '{{ addslashes($pass->visitor?->company_name ?? '') }}', '{{ $pass->host_user_id }}')">
                                                <i class="feather-user-plus me-2 text-success fs-12"></i> {{ __('visitor.convert_to_lead') }}
                                            </a>
                                        </li>
                                    @endif

                                    @if($pass->visitor)
                                        <li>
                                            <form method="POST" action="{{ route('visitor.visitors.toggle-blacklist', $pass->visitor_id) }}" onsubmit="return confirm('{{ $pass->visitor->is_blacklisted ? 'Remove this visitor from blacklist?' : 'Are you sure you want to BLACKLIST this visitor? All future pass creations will be blocked.' }}')">
                                                @csrf
                                                <button type="submit" class="dropdown-item {{ $pass->visitor->is_blacklisted ? 'text-success' : 'text-danger' }}">
                                                    <i class="{{ $pass->visitor->is_blacklisted ? 'feather-check-circle text-success' : 'feather-slash text-danger' }} me-2 fs-12"></i>
                                                    {{ $pass->visitor->is_blacklisted ? __('visitor.unblock_visitor') : __('visitor.blacklist_visitor') }}
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

        <!-- 4. Pagination -->
        <div class="pt-3">
            <x-ui.pagination
                :currentPage="$passes->currentPage()"
                :totalPages="$passes->lastPage()"
                :totalResults="$passes->total()"
                :perPage="$passes->perPage()" />
        </div>
    </div>

    <!-- 5. Offcanvas Drawer: Create New Visitor Pass -->
    <x-ui.drawer 
        id="newVisitorDrawer" 
        title="<div class='d-flex align-items-center gap-2'><div class='avatar-sm bg-primary-subtle text-primary rounded-3 d-flex align-items-center justify-content-center' style='width: 34px; height: 34px;'><i class='feather-user-plus fs-16'></i></div><div><div class='fw-bold fs-15 text-dark leading-tight'>{{ __('visitor.new_visitor_pass') }}</div><div class='fs-11 text-muted fw-normal'>Register entry & issue gate pass</div></div></div>" 
        scroll 
        style="--bs-offcanvas-width: min(850px, 95vw);">

        <form method="POST" action="{{ route('visitor.store') }}" id="newVisitorDrawerForm" class="p-2 p-md-3" novalidate>
            @csrf

            <!-- Returning Visitor Live Detection Alert -->
            <div id="returningVisitorAlertDrawer" class="alert alert-success border border-success-subtle bg-success-subtle rounded-3 shadow-none mb-3 d-none p-3">
                <div class="d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2.5">
                        <div class="avatar-sm bg-success text-white rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 36px; height: 36px;">
                            <i class="feather-check-circle fs-16"></i>
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

            <!-- Blacklisted Visitor Security Alert Banner -->
            <div id="blacklistedVisitorAlertDrawer" class="alert alert-danger border-2 border-danger bg-danger-subtle rounded-3 shadow-sm mb-3 d-none p-3">
                <div class="d-flex align-items-start justify-content-between mb-2">
                    <div class="d-flex align-items-start gap-2.5">
                        <div class="avatar-sm bg-danger text-white rounded-circle d-flex align-items-center justify-content-center flex-shrink-0 mt-0.5" style="width: 38px; height: 38px;">
                            <i class="feather-slash fs-18"></i>
                        </div>
                        <div>
                            <div class="d-flex align-items-center flex-wrap gap-2">
                                <strong class="text-danger fs-14 fw-bold">{{ __('visitor.blacklist_warning_title') }}</strong>
                                <span class="badge bg-danger text-white px-2 py-0.5 fs-10 fw-bold">{{ __('visitor.entry_prohibited') }}</span>
                            </div>
                            <div class="fs-12 text-danger-emphasis mt-1">
                                <strong>{{ __('visitor.blacklist_reason') }}:</strong> <span id="blacklistedReasonTextDrawer" class="fst-italic">—</span>
                            </div>
                        </div>
                    </div>
                    <span class="badge bg-danger text-white px-2.5 py-1 fs-11 fw-bold text-uppercase">
                        <i class="feather-alert-octagon me-1"></i> Blocked
                    </span>
                </div>

                <!-- Manager Override Authorization -->
                <div class="border-top border-danger-subtle pt-2 mt-2">
                    <x-ui.checkbox 
                        name="allow_blacklisted_override" 
                        id="drawerBlacklistOverrideCheck" 
                        value="1" 
                        :label="__('visitor.blacklist_override')" 
                        onchange="toggleBlacklistOverride(this.checked)" 
                    />
                    <div id="drawerBlacklistOverrideReasonGroup" class="d-none mt-2">
                        <input type="text" name="blacklist_override_reason" id="drawerBlacklistOverrideReasonInput" class="form-control form-control-sm border-danger-subtle fs-12 bg-white" placeholder="Enter mandatory authorization note / executive approval details...">
                    </div>
                </div>
            </div>

            <!-- Main Form Grid -->
            <div class="row g-3 mb-3">
                <!-- Visitor Type Dropdown -->
                <div class="col-md-6">
                    <div class="odoo-field-group">
                        <label class="odoo-field-label" for="drawerVisitorTypeSelect">
                            {{ __('visitor.visitor_type') }} <span class="text-danger">*</span>
                        </label>
                        <select name="visitor_type" id="drawerVisitorTypeSelect" class="odoo-field-control" required>
                            <option value="Client" selected>{{ __('visitor.visitor_types.Client') }}</option>
                            <option value="Vendor">{{ __('visitor.visitor_types.Vendor') }}</option>
                            <option value="Candidate">{{ __('visitor.visitor_types.Candidate') }}</option>
                            <option value="Service">{{ __('visitor.visitor_types.Service') }}</option>
                            <option value="Guest">{{ __('visitor.visitor_types.Guest') }}</option>
                        </select>
                    </div>
                </div>

                <!-- Phone Number -->
                <div class="col-md-6">
                    <div class="odoo-field-group">
                        <label class="odoo-field-label" for="drawerVisitorPhone">
                            {{ __('visitor.phone_number') }} <span class="text-danger">*</span>
                        </label>
                        <div class="position-relative">
                            <input type="tel" 
                                   name="phone" 
                                   id="drawerVisitorPhone" 
                                   class="odoo-field-control" 
                                   required 
                                   placeholder="e.g. +91 9876543210" 
                                   oninput="debounceVisitorLookupDrawer(this.value, 'phone')">
                            <span id="phoneLookupSpinnerDrawer" class="text-primary fs-11 d-none fw-normal position-absolute end-0 top-50 translate-middle-y me-1">
                                <i class="feather-loader icon-spin me-1"></i> Looking up...
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Visitor Full Name -->
                <div class="col-md-6">
                    <div class="odoo-field-group">
                        <label class="odoo-field-label" for="drawerVisitorFullName">
                            {{ __('visitor.visitor_name') }} <span class="text-danger">*</span>
                        </label>
                        <input type="text" 
                               name="full_name" 
                               id="drawerVisitorFullName" 
                               class="odoo-field-control" 
                               required 
                               placeholder="e.g. Rajesh Sharma">
                    </div>
                </div>

                <!-- Email Address -->
                <div class="col-md-6">
                    <div class="odoo-field-group">
                        <label class="odoo-field-label" for="drawerVisitorEmail">
                            {{ __('visitor.email_address') }}
                        </label>
                        <input type="email" 
                               name="email" 
                               id="drawerVisitorEmail" 
                               class="odoo-field-control" 
                               placeholder="e.g. visitor@company.com"
                               oninput="debounceVisitorLookupDrawer(this.value, 'email')">
                    </div>
                </div>

                <!-- Company -->
                <div class="col-md-6">
                    <div class="odoo-field-group">
                        <label class="odoo-field-label" for="drawerVisitorCompany">
                            {{ __('visitor.company') }}
                        </label>
                        <input type="text" 
                               name="company_name" 
                               id="drawerVisitorCompany" 
                               class="odoo-field-control" 
                               placeholder="e.g. Acme Industries Ltd"
                               oninput="debounceVisitorLookupDrawer(this.value, 'company')">
                    </div>
                </div>

                <!-- Host User -->
                <div class="col-md-6">
                    <div class="odoo-field-group">
                        <label class="odoo-field-label" for="drawerHostUserId">
                            {{ __('visitor.select_host') }} <span class="text-danger">*</span>
                        </label>
                        <select name="host_user_id" id="drawerHostUserId" class="odoo-field-control" required>
                            <option value="">— {{ __('visitor.select_host') }} —</option>
                            @foreach($hosts ?? [] as $host)
                                <option value="{{ $host->id }}">{{ $host->name }} ({{ $host->email }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <!-- Purpose of Visit -->
                <div class="col-md-6">
                    <div class="odoo-field-group">
                        <label class="odoo-field-label" for="drawerPurpose">
                            {{ __('visitor.purpose_of_visit') }} <span class="text-danger">*</span>
                        </label>
                        <select name="purpose" id="drawerPurpose" class="odoo-field-control" required onchange="handleDrawerPurposeChange(this.value)">
                            <option value="Meeting">Meeting / Discussion</option>
                            <option value="Product Inquiry">{{ __('visitor.purposes.Product Inquiry') }}</option>
                            <option value="Interview">Job Interview</option>
                            <option value="Vendor">Vendor / Supplier Visit</option>
                            <option value="Delivery">Courier / Delivery</option>
                            <option value="Audit">Audit / Inspection</option>
                            <option value="Personal">Personal Visit</option>
                        </select>
                    </div>
                </div>

                <!-- Expected Arrival & Duration -->
                <div class="col-md-6">
                    <div class="odoo-field-group">
                        <label class="odoo-field-label" for="drawerExpectedArrival">{{ __('visitor.expected_time') }}</label>
                        <input type="datetime-local" name="expected_arrival_at" id="drawerExpectedArrival" class="odoo-field-control" value="{{ now()->format('Y-m-d\TH:i') }}">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="odoo-field-group">
                        <label class="odoo-field-label" for="drawerDuration">{{ __('visitor.expected_duration') }}</label>
                        <input type="number" name="expected_duration_minutes" id="drawerDuration" class="odoo-field-control" value="60" min="15" max="720">
                    </div>
                </div>
            </div>

            <!-- Front Desk Product Inquiry & CRM Section -->
            <div id="drawerProductInquiryBox" class="p-3 mb-3 rounded-3 border shadow-xs transition-all" style="background: linear-gradient(180deg, #f8fafc 0%, #ffffff 100%); border-color: #e2e8f0;">
                <div class="d-flex align-items-center justify-content-between mb-2.5 pb-2 border-bottom">
                    <div class="d-flex align-items-center gap-2">
                        <div class="avatar-xs bg-primary text-white rounded d-flex align-items-center justify-content-center shadow-xs" style="width: 28px; height: 28px;">
                            <i class="feather-shopping-bag fs-13"></i>
                        </div>
                        <div>
                            <strong class="text-dark fs-13 d-block leading-tight">{{ __('visitor.front_desk_inquiry') }}</strong>
                            <span class="text-muted fs-11">Log catalog interests, quantities & CRM leads</span>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-1.5">
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-0.5 fs-10 fw-bold">CRM Integration</span>
                    </div>
                </div>

                <div class="row g-2.5">
                    <!-- Product & Quantity Items Repeater -->
                    <div class="col-12">
                        <div class="d-flex justify-content-between align-items-center mb-1.5">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-0">
                                <i class="feather-package text-primary me-1"></i> {{ __('visitor.product_and_quantity') }}
                            </label>
                            <button type="button" class="btn btn-xs btn-outline-primary fw-semibold px-2 py-0.5 fs-11 shadow-xs" onclick="addProductItemRow('#drawerProductItemsTable')" style="border-radius: 6px;">
                                <i class="feather-plus me-0.5"></i> {{ __('visitor.add_product_row') }}
                            </button>
                        </div>

                        <div class="border rounded-3 bg-white p-2 shadow-sm">
                            <table class="table table-sm table-borderless align-middle mb-0" id="drawerProductItemsTable">
                                <thead>
                                    <tr class="border-bottom text-muted fs-11" style="background-color: #f8fafc;">
                                        <th style="width: 66%; font-weight: 600;" class="py-1 ps-2">{{ __('visitor.interested_products') }}</th>
                                        <th style="width: 24%; font-weight: 600;" class="py-1 text-center">{{ __('visitor.quantity') }}</th>
                                        <th style="width: 10%; font-weight: 600;" class="py-1 text-center"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr class="product-item-row border-bottom border-light">
                                        <td class="py-1.5 ps-1 pe-1">
                                            <div class="d-flex align-items-center gap-2">
                                                <!-- Product Image Preview Thumbnail (Click to Zoom) -->
                                                <div class="product-thumb-wrapper position-relative flex-shrink-0 border rounded-2 bg-light d-flex align-items-center justify-content-center cursor-pointer shadow-xs" 
                                                     style="width: 34px; height: 34px; overflow: hidden; transition: all 0.2s ease;" 
                                                     onclick="openProductImageZoom(this)"
                                                     title="Click to zoom image"
                                                     data-full-image=""
                                                     data-product-name=""
                                                     data-product-sku="">
                                                    <img src="/assets/images/icons/1.png" 
                                                         class="product-row-thumb w-100 h-100 object-fit-cover d-none" 
                                                         alt="Product"
                                                         onerror="this.src='/assets/images/icons/1.png';">
                                                    <i class="feather-box product-placeholder-icon text-muted fs-14"></i>
                                                    <div class="product-zoom-hint position-absolute inset-0 bg-dark bg-opacity-40 d-flex align-items-center justify-content-center opacity-0 hover-opacity-100 transition-all rounded-2" style="width:100%; height:100%; top:0; left:0;">
                                                        <i class="feather-maximize-2 text-white" style="font-size: 10px;"></i>
                                                    </div>
                                                </div>
                                                <!-- Product Select -->
                                                <div class="flex-grow-1" style="min-width: 0;">
                                                    <select name="product_items[0][product_id]" class="form-select form-select-sm fs-12 product-item-select" onchange="handleProductSelectionChange(this)">
                                                        <option value="" data-image="">— {{ __('visitor.select_products') }} —</option>
                                                        @foreach($products ?? [] as $prod)
                                                            <option value="{{ $prod->id }}" 
                                                                    data-image="{{ $prod->main_image_url ?: ($prod->image_path ? asset('storage/'.$prod->image_path) : '') }}"
                                                                    data-name="{{ $prod->name }}"
                                                                    data-sku="{{ $prod->sku ?? '' }}">
                                                                {{ $prod->name }} {{ $prod->sku ? "({$prod->sku})" : '' }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-1.5 px-1 text-center">
                                            <input type="number" name="product_items[0][quantity]" class="form-control form-control-sm text-center fw-bold fs-12" value="1" min="1" step="1">
                                        </td>
                                        <td class="py-1.5 ps-1 pe-2 text-center">
                                            <button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="removeProductItemRow(this)" title="Remove">
                                                <i class="feather-trash-2 fs-13"></i>
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1" for="drawerInquiryNotes">
                            <i class="feather-file-text text-primary me-1"></i> {{ __('visitor.inquiry_notes') }}
                        </label>
                        <textarea name="inquiry_notes" id="drawerInquiryNotes" class="form-control fs-12 bg-white" rows="2" placeholder="e.g. Inquired about bulk order pricing, specific custom dimensions, delivery timeframe..."></textarea>
                    </div>

                    <div class="col-12 pt-1">
                        <div class="form-check form-switch mb-0 p-2 rounded-2 bg-white border">
                            <input class="form-check-input ms-0 me-2" type="checkbox" name="create_crm_lead" id="drawerCreateCrmLead" value="1">
                            <label class="form-check-label fs-12 fw-semibold text-dark cursor-pointer" for="drawerCreateCrmLead">
                                <i class="feather-trending-up text-success me-1"></i> {{ __('visitor.auto_create_lead') }}
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Live Photo & Badge Capture -->
            <div class="mb-3 pb-3 border-bottom">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <label class="odoo-field-label mb-0">
                        <i class="feather-camera text-primary me-1"></i> {{ __('visitor.live_camera_capture') }}
                    </label>
                    <span class="badge bg-light text-muted border fs-10">Optional Badge Photo</span>
                </div>

                <input type="hidden" name="photo_url" id="drawerVisitorPhotoData">
                
                <div class="d-flex align-items-center flex-wrap gap-3 mt-2">
                    <div id="drawerPhotoPreviewContainer" class="d-none">
                        <img id="drawerPhotoPreview" src="" alt="Captured Photo" width="54" height="54" class="rounded-circle border border-2 border-success object-fit-cover shadow-sm">
                    </div>

                    <div id="drawerWebcamContainer" class="d-none">
                        <video id="drawerWebcamVideo" width="180" height="135" autoplay playsinline class="rounded-3 border border-2 border-primary shadow-sm"></video>
                        <canvas id="drawerWebcamCanvas" width="180" height="135" class="d-none"></canvas>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-outline-primary fw-semibold px-3" id="btnStartCameraDrawer" onclick="startWebcamDrawer()">
                            <i class="feather-camera me-1"></i> Open Webcam
                        </button>
                        <button type="button" class="btn btn-sm btn-success d-none fw-semibold px-3" id="btnCapturePhotoDrawer" onclick="captureWebcamPhotoDrawer()">
                            <i class="feather-check me-1"></i> {{ __('visitor.capture_photo') }}
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger d-none fw-semibold px-3" id="btnRetakePhotoDrawer" onclick="retakeWebcamPhotoDrawer()">
                            <i class="feather-refresh-cw me-1"></i> Retake
                        </button>
                    </div>
                </div>
            </div>

            <!-- Entry Workflow & Pass Type -->
            <div class="mb-3 pb-3 border-bottom">
                <label class="odoo-field-label mb-2">Entry Workflow</label>
                <div class="d-flex flex-wrap gap-4 align-items-center">
                    <x-ui.radio 
                        name="drawer_action_type" 
                        id="drawerActionCheckIn" 
                        value="checkin" 
                        label="Instant Check-in" 
                        :checked="true" 
                        onchange="toggleDrawerActionType(this.value)" 
                    />
                    <x-ui.radio 
                        name="drawer_action_type" 
                        id="drawerActionApproval" 
                        value="approval" 
                        label="Host Approval" 
                        onchange="toggleDrawerActionType(this.value)" 
                    />
                    <x-ui.radio 
                        name="drawer_action_type" 
                        id="drawerActionExpected" 
                        value="expected" 
                        label="Pre-Register" 
                        onchange="toggleDrawerActionType(this.value)" 
                    />
                </div>
                <input type="hidden" name="check_in_now" id="drawerCheckInNowHidden" value="1">
                <input type="hidden" name="status" id="drawerStatusHidden" value="Checked-In">
            </div>

            <!-- Collapsible Extra Details -->
            <div class="my-2">
                <button type="button" 
                        class="btn btn-outline-primary btn-sm px-3 py-1.5 rounded-2 d-inline-flex align-items-center gap-1.5 fw-semibold shadow-none" 
                        id="toggleAdditionalVisitorFieldsBtn" 
                        onclick="toggleAdditionalVisitorFields()">
                    <i class="feather-plus-circle fs-13" id="toggleAdditionalFieldsIcon"></i>
                    <span id="toggleAdditionalFieldsText">+ Additional Details (Vehicle, ID Proof, Assets & Safety)</span>
                </button>
            </div>

            <!-- Collapsible Secondary Fields Container -->
            <div id="additionalVisitorFieldsContainer" class="d-none pt-3">
                <div class="row g-3">
                    <!-- Designation -->
                    <div class="col-md-6">
                        <div class="odoo-field-group">
                            <label class="odoo-field-label" for="drawerVisitorDesignation">{{ __('visitor.designation') }}</label>
                            <input type="text" name="designation" id="drawerVisitorDesignation" class="odoo-field-control" placeholder="e.g. Senior Consultant / Delivery Rep">
                        </div>
                    </div>

                    <!-- Gate Number -->
                    <div class="col-md-6">
                        <div class="odoo-field-group">
                            <label class="odoo-field-label" for="drawerGateNumber">{{ __('visitor.gate_number') }}</label>
                            <input type="text" name="gate_number" id="drawerGateNumber" class="odoo-field-control" value="Main Gate 1">
                        </div>
                    </div>

                    <!-- Vehicle Type & Number -->
                    <div class="col-md-4">
                        <div class="odoo-field-group">
                            <label class="odoo-field-label">{{ __('visitor.vehicle_type') }}</label>
                            <select name="vehicle_type" class="odoo-field-control">
                                <option value="none">{{ __('visitor.vehicle_types.none') }}</option>
                                <option value="2_wheeler">{{ __('visitor.vehicle_types.2_wheeler') }}</option>
                                <option value="4_wheeler">{{ __('visitor.vehicle_types.4_wheeler') }}</option>
                                <option value="truck">{{ __('visitor.vehicle_types.truck') }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="odoo-field-group">
                            <label class="odoo-field-label">{{ __('visitor.vehicle_number') }}</label>
                            <input type="text" name="vehicle_number" class="odoo-field-control" placeholder="e.g. MH-12-AB-1234">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="odoo-field-group">
                            <label class="odoo-field-label">{{ __('visitor.parking_slot') }}</label>
                            <input type="text" name="parking_slot" class="odoo-field-control" placeholder="e.g. Bay P-04">
                        </div>
                    </div>

                    <!-- Accompanying Persons -->
                    <div class="col-md-4">
                        <div class="odoo-field-group">
                            <label class="odoo-field-label">{{ __('visitor.accompanying_count') }}</label>
                            <input type="number" name="accompanying_count" class="odoo-field-control" value="0" min="0" max="50">
                        </div>
                    </div>
                    <div class="col-md-8">
                        <div class="odoo-field-group">
                            <label class="odoo-field-label">{{ __('visitor.accompanying_names') }}</label>
                            <input type="text" name="accompanying_names" class="odoo-field-control" placeholder="Comma separated guest names...">
                        </div>
                    </div>

                    <!-- ID Proof & Verification -->
                    <div class="col-md-4">
                        <div class="odoo-field-group">
                            <label class="odoo-field-label">{{ __('visitor.id_proof') }}</label>
                            <select name="id_proof_type" id="drawerVisitorIdType" class="odoo-field-control">
                                <option value="">— Select ID Proof —</option>
                                <option value="National ID">Aadhaar / National ID</option>
                                <option value="Driving License">Driving License</option>
                                <option value="Passport">Passport</option>
                                <option value="Company ID">Company ID Card</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="odoo-field-group">
                            <label class="odoo-field-label">{{ __('visitor.id_proof_number') }}</label>
                            <input type="text" name="id_proof_number" id="drawerVisitorIdNumber" class="odoo-field-control" placeholder="e.g. DL-987456">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="odoo-field-group">
                            <label class="odoo-field-label">{{ __('visitor.badge_number') }}</label>
                            <input type="text" name="badge_number" class="odoo-field-control" placeholder="e.g. RFID-084">
                        </div>
                    </div>

                    <!-- Belongings / Equipment -->
                    <div class="col-md-4">
                        <div class="odoo-field-group">
                            <label class="odoo-field-label">{{ __('visitor.item_type') }}</label>
                            <input type="text" name="item_type" class="odoo-field-control" placeholder="e.g. Dell Latitude Laptop">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="odoo-field-group">
                            <label class="odoo-field-label">{{ __('visitor.serial_number') }}</label>
                            <input type="text" name="serial_number" class="odoo-field-control" placeholder="e.g. CN-0G541298">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="odoo-field-group">
                            <label class="odoo-field-label">{{ __('visitor.gate_pass_number') }}</label>
                            <input type="text" name="belonging_gate_pass" class="odoo-field-control" placeholder="e.g. GP-9941">
                        </div>
                    </div>

                    <!-- Compliance Checkboxes -->
                    <div class="col-md-12">
                        <div class="d-flex flex-wrap gap-4 py-2 border-top border-bottom">
                            <x-ui.checkbox 
                                name="nda_safety_acknowledged" 
                                id="drawerNdaCheck" 
                                value="1" 
                                :checked="true" 
                                :label="__('visitor.nda_safety_acknowledged')" 
                            />
                            <x-ui.checkbox 
                                name="restricted_area_access" 
                                id="drawerRestrictedCheck" 
                                value="1" 
                                :label="__('visitor.restricted_area_access')" 
                            />
                        </div>
                    </div>

                    <!-- Notes -->
                    <div class="col-md-12">
                        <div class="odoo-field-group">
                            <label class="odoo-field-label">Security Notes / Remarks</label>
                            <textarea name="notes" rows="2" class="form-control fs-13" placeholder="Any vehicle parking bay, cargo details or security remarks..."></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Drawer Bottom Sticky Action Bar -->
            <div class="d-flex align-items-center justify-content-between gap-2 pt-3 border-top mt-4 bg-white sticky-bottom pb-2">
                <button type="button" class="btn btn-light border px-3 fw-semibold" data-bs-dismiss="offcanvas">
                    <i class="feather-x me-1"></i> Cancel
                </button>
                <div class="d-flex align-items-center gap-2">
                    <button type="submit" id="drawerSubmitBtn" class="btn btn-primary px-4 fw-bold">
                        <i class="feather-check me-1"></i> {{ __('visitor.new_visitor_pass') }}
                    </button>
                </div>
            </div>
        </form>
    </x-ui.drawer>

    <!-- Modal 1: Check Out Modal (With Badge Return & Material Exit Verification) -->
    <x-ui.modal 
        id="visitorCheckoutModal" 
        title="<i class='feather-log-out text-danger me-2'></i> {{ __('visitor.check_out') }} - <span id='checkoutPassNumber'></span>" 
        centered 
        :showFooter="false">
        <form id="visitorCheckoutForm" method="POST" action="">
            @csrf
            <p class="fs-13 text-dark mb-3">Checking out visitor: <strong id="checkoutVisitorName"></strong></p>
            
            <x-ui.checkbox 
                name="badge_returned" 
                id="modalBadgeReturnedCheck" 
                value="1" 
                :checked="true" 
                :label="__('visitor.badge_returned_notice')" 
                class="mb-3"
            />

            <div class="mb-3">
                <label class="form-label fw-bold fs-12 text-dark">{{ __('visitor.material_gate_pass') }}</label>
                <input type="text" name="gate_pass_reference" class="form-control fs-13" placeholder="e.g. Exit Gate Pass # or DC Reference">
            </div>

            <div class="d-flex justify-content-end gap-2 pt-3 border-top mt-4">
                <button type="button" class="btn btn-light border px-3" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger px-3 fw-semibold">
                    <i class="feather-log-out me-1"></i> Confirm Check-Out
                </button>
            </div>
        </form>
    </x-ui.modal>

    <!-- Modal 2: Extend Visit Modal -->
    <x-ui.modal 
        id="extendVisitModal" 
        title="<i class='feather-clock text-warning me-2'></i> {{ __('visitor.extend_visit') }} - <span id='extendPassNumber'></span>" 
        centered 
        :showFooter="false">
        <form id="extendVisitForm" method="POST" action="">
            @csrf
            <div class="mb-3">
                <label class="form-label fw-bold fs-12 text-dark">{{ __('visitor.extend_minutes') }} <span class="text-danger">*</span></label>
                <select name="extend_minutes" class="form-select fs-13" required>
                    <option value="30">+30 Minutes</option>
                    <option value="60" selected>+60 Minutes (1 Hour)</option>
                    <option value="120">+120 Minutes (2 Hours)</option>
                    <option value="240">+240 Minutes (4 Hours)</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label fw-bold fs-12 text-dark">Reason / Remarks</label>
                <textarea name="extend_notes" class="form-control fs-13" rows="2" placeholder="Meeting prolonged, additional discussion..."></textarea>
            </div>

            <div class="d-flex justify-content-end gap-2 pt-3 border-top mt-4">
                <button type="button" class="btn btn-light border px-3" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-warning px-3 fw-semibold">
                    <i class="feather-clock me-1"></i> Extend Stay
                </button>
            </div>
        </form>
    </x-ui.modal>

    <!-- Modal 3: Deny Entry Modal -->
    <x-ui.modal 
        id="denyEntryModal" 
        title="<i class='feather-slash text-danger me-2'></i> {{ __('visitor.deny_entry') }} - <span id='denyPassNumber'></span>" 
        centered 
        :showFooter="false">
        <form id="denyEntryForm" method="POST" action="">
            @csrf
            <p class="fs-13 text-dark mb-2">Denying entry for: <strong id="denyVisitorName"></strong></p>
            <div class="mb-3">
                <label class="form-label fw-bold fs-12 text-dark">{{ __('visitor.rejection_reason') }} <span class="text-danger">*</span></label>
                <textarea name="denied_reason" class="form-control fs-13" rows="3" required placeholder="Security concern, invalid authorization, restricted area..."></textarea>
            </div>
            <x-ui.checkbox 
                name="blacklist_visitor" 
                id="modalBlacklistCheck" 
                value="1" 
                :label="__('visitor.blacklist_visitor')" 
            />

            <div class="d-flex justify-content-end gap-2 pt-3 border-top mt-4">
                <button type="button" class="btn btn-light border px-3" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger px-3 fw-semibold">
                    <i class="feather-slash me-1"></i> Confirm Denial
                </button>
            </div>
        </form>
    </x-ui.modal>

    <!-- Modal 4: Report Incident Modal -->
    <x-ui.modal 
        id="reportIncidentModal" 
        title="<i class='feather-alert-octagon text-danger me-2'></i> {{ __('visitor.report_incident') }} - <span id='incidentPassNumber'></span>" 
        centered 
        :showFooter="false">
        <form id="reportIncidentForm" method="POST" action="">
            @csrf
            <div class="mb-3">
                <label class="form-label fw-bold fs-12 text-dark">{{ __('visitor.incident_details') }} <span class="text-danger">*</span></label>
                <textarea name="incident_details" class="form-control fs-13" rows="4" required placeholder="Detailed notes about unauthorized access, misconduct, damage, or security alert..."></textarea>
            </div>

            <div class="d-flex justify-content-end gap-2 pt-3 border-top mt-4">
                <button type="button" class="btn btn-light border px-3" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-dark px-3 fw-semibold">
                    <i class="feather-alert-circle me-1"></i> Submit Incident
                </button>
            </div>
        </form>
    </x-ui.modal>

    <!-- Modal 5: Convert Walk-in Visitor to CRM Lead -->
    <x-ui.modal 
        id="convertLeadModalIndex" 
        title="<i class='feather-user-plus text-success me-2'></i> {{ __('visitor.convert_lead_modal_title') }}" 
        centered 
        :showFooter="false">
        <form id="convertLeadFormIndex" method="POST" action="">
            @csrf
            <div class="mb-3 p-3 bg-light rounded-3 border">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <span class="fs-11 text-muted text-uppercase fw-bold">Visitor Gate Pass</span>
                    <span id="convertLeadPassNumber" class="badge bg-white text-dark border font-monospace fs-11"></span>
                </div>
                <div class="fs-14 text-dark fw-bold" id="convertLeadVisitorName"></div>
                <div class="fs-12 text-muted" id="convertLeadCompanyName"></div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold fs-12 text-dark">{{ __('visitor.lead_owner') }}</label>
                <select name="lead_owner_id" id="convertLeadOwnerSelect" class="form-select fs-13">
                    @foreach($hosts ?? [] as $host)
                        <option value="{{ $host->id }}" {{ $host->id == auth()->id() ? 'selected' : '' }}>{{ $host->name }} ({{ $host->email }})</option>
                    @endforeach
                </select>
            </div>

            <div class="row g-2 mb-3">
                <div class="col-6">
                    <label class="form-label fw-bold fs-12 text-dark">{{ __('visitor.lead_type') }}</label>
                    <select name="lead_type" class="form-select fs-13">
                        <option value="hot">🔥 Hot Lead</option>
                        <option value="warm" selected>⚡ Warm Lead</option>
                        <option value="cold">❄️ Cold Lead</option>
                    </select>
                </div>
                <div class="col-6">
                    <label class="form-label fw-bold fs-12 text-dark">Priority</label>
                    <select name="priority" class="form-select fs-13">
                        <option value="Urgent">Urgent</option>
                        <option value="High">High</option>
                        <option value="Medium" selected>Medium</option>
                        <option value="Low">Low</option>
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <label class="form-label fw-bold fs-12 text-dark mb-0">
                        <i class="feather-package text-primary me-1"></i> {{ __('visitor.product_and_quantity') }}
                    </label>
                    <button type="button" class="btn btn-xs btn-outline-primary fw-semibold px-2 py-0.5 fs-11" onclick="addProductItemRow('#modalProductItemsTableIndex')" style="border-radius: 6px;">
                        <i class="feather-plus me-0.5"></i> {{ __('visitor.add_product_row') }}
                    </button>
                </div>

                <div class="border rounded-3 bg-white p-2 shadow-sm">
                    <table class="table table-sm table-borderless align-middle mb-0" id="modalProductItemsTableIndex">
                        <thead>
                            <tr class="border-bottom text-muted fs-11" style="background-color: #f8fafc;">
                                <th style="width: 66%; font-weight: 600;" class="py-1 ps-2">{{ __('visitor.interested_products') }}</th>
                                <th style="width: 24%; font-weight: 600;" class="py-1 text-center">{{ __('visitor.quantity') }}</th>
                                <th style="width: 10%; font-weight: 600;" class="py-1 text-center"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr class="product-item-row border-bottom border-light">
                                <td class="py-1.5 ps-1 pe-1">
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="product-thumb-wrapper position-relative flex-shrink-0 border rounded-2 bg-light d-flex align-items-center justify-content-center cursor-pointer shadow-xs" 
                                             style="width: 34px; height: 34px; overflow: hidden; transition: all 0.2s ease;" 
                                             onclick="openProductImageZoom(this)"
                                             title="Click to zoom image"
                                             data-full-image=""
                                             data-product-name=""
                                             data-product-sku="">
                                            <img src="/assets/images/icons/1.png" 
                                                 class="product-row-thumb w-100 h-100 object-fit-cover d-none" 
                                                 alt="Product"
                                                 onerror="this.src='/assets/images/icons/1.png';">
                                            <i class="feather-box product-placeholder-icon text-muted fs-14"></i>
                                            <div class="product-zoom-hint position-absolute inset-0 bg-dark bg-opacity-40 d-flex align-items-center justify-content-center opacity-0 hover-opacity-100 transition-all rounded-2" style="width:100%; height:100%; top:0; left:0;">
                                                <i class="feather-maximize-2 text-white" style="font-size: 10px;"></i>
                                            </div>
                                        </div>
                                        <div class="flex-grow-1" style="min-width: 0;">
                                            <select name="product_items[0][product_id]" class="form-select form-select-sm fs-12 product-item-select" onchange="handleProductSelectionChange(this)">
                                                <option value="" data-image="">— {{ __('visitor.select_products') }} —</option>
                                                @foreach($products ?? [] as $prod)
                                                    <option value="{{ $prod->id }}" 
                                                            data-image="{{ $prod->main_image_url ?: ($prod->image_path ? asset('storage/'.$prod->image_path) : '') }}"
                                                            data-name="{{ $prod->name }}"
                                                            data-sku="{{ $prod->sku ?? '' }}">
                                                        {{ $prod->name }} {{ $prod->sku ? "({$prod->sku})" : '' }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-1.5 px-1 text-center">
                                    <input type="number" name="product_items[0][quantity]" class="form-control form-control-sm text-center fw-bold fs-12" value="1" min="1" step="1">
                                </td>
                                <td class="py-1.5 ps-1 pe-2 text-center">
                                    <button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="removeProductItemRow(this)" title="Remove">
                                        <i class="feather-trash-2 fs-13"></i>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold fs-12 text-dark">{{ __('visitor.inquiry_notes') }}</label>
                <textarea name="requirement" id="convertLeadRequirement" rows="3" class="form-control fs-13" placeholder="Enter specific customer requirements, requested quantities, or pricing discussion..."></textarea>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold fs-12 text-dark">Next Follow-up Date</label>
                <input type="date" name="next_followup_date" class="form-control fs-13" value="{{ now()->addDays(2)->format('Y-m-d') }}">
            </div>

            <div class="d-flex justify-content-end gap-2 pt-3 border-top mt-4">
                <button type="button" class="btn btn-light border px-3" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success px-3 fw-bold">
                    <i class="feather-check-circle me-1"></i> Create CRM Lead
                </button>
            </div>
        </form>
    </x-ui.modal>

    <!-- Product Image Zoom / Lightbox Modal -->
    <div class="modal fade" id="productImageZoomModal" tabindex="-1" aria-hidden="true" style="z-index: 1080;">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow-lg overflow-hidden rounded-4">
                <div class="modal-header border-bottom py-2.5 px-3 bg-light">
                    <div class="d-flex align-items-center gap-2">
                        <div class="avatar-xs bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width:28px; height:28px;">
                            <i class="feather-image fs-13"></i>
                        </div>
                        <div>
                            <h6 class="modal-title fs-14 fw-bold text-dark mb-0" id="zoomModalProductName">Product Image Preview</h6>
                            <small class="text-muted fs-11 font-monospace" id="zoomModalProductSku"></small>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-3 text-center bg-dark bg-opacity-10 d-flex align-items-center justify-content-center" style="min-height: 380px; max-height: 75vh;">
                    <img id="zoomModalImage" src="" alt="Product Large Preview" class="img-fluid rounded-3 shadow-sm object-fit-contain" style="max-height: 70vh; max-width: 100%; transition: transform 0.2s ease;">
                </div>
                <div class="modal-footer py-2 px-3 bg-light border-top d-flex justify-content-between align-items-center">
                    <span class="fs-11 text-muted"><i class="feather-info me-1"></i> High-resolution product catalog asset</span>
                    <button type="button" class="btn btn-sm btn-secondary px-3" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

@endsection

@push('scripts')
<script>
    function setVisitorType(type, el) {
        document.querySelectorAll('.visitor-type-card').forEach(c => c.classList.remove('active'));
        if (el) el.classList.add('active');
        const input = document.getElementById('drawerVisitorTypeInput');
        if (input) input.value = type;
    }

    function toggleAdditionalVisitorFields() {
        const container = document.getElementById('additionalVisitorFieldsContainer');
        const textSpan = document.getElementById('toggleAdditionalFieldsText');
        const icon = document.getElementById('toggleAdditionalFieldsIcon');
        const btn = document.getElementById('toggleAdditionalVisitorFieldsBtn');
        
        if (!container) return;

        if (container.classList.contains('d-none')) {
            container.classList.remove('d-none');
            if (textSpan) textSpan.textContent = '- Hide Additional Details';
            if (icon) icon.className = 'feather-minus-circle fs-13';
        } else {
            container.classList.add('d-none');
            if (textSpan) textSpan.textContent = '+ Additional Details (Vehicle, ID Proof, Assets & Safety)';
            if (icon) icon.className = 'feather-plus-circle fs-13';
        }
    }

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

    function toggleBlacklistOverride(isAllowed) {
        const submitBtn = document.getElementById('drawerSubmitBtn');
        const reasonGroup = document.getElementById('drawerBlacklistOverrideReasonGroup');
        const reasonInput = document.getElementById('drawerBlacklistOverrideReasonInput');
        const blacklistAlert = document.getElementById('blacklistedVisitorAlertDrawer');

        if (!blacklistAlert || blacklistAlert.classList.contains('d-none')) {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.className = 'btn btn-primary px-4 fw-bold';
                submitBtn.innerHTML = '<i class="feather-check me-1"></i> {{ __("visitor.new_visitor_pass") }}';
            }
            return;
        }

        if (isAllowed) {
            if (reasonGroup) reasonGroup.classList.remove('d-none');
            if (reasonInput) reasonInput.required = true;
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.className = 'btn btn-warning text-dark px-4 fw-bold';
                submitBtn.innerHTML = '<i class="feather-shield text-dark me-1"></i> Issue Pass (Override Authorized)';
            }
        } else {
            if (reasonGroup) reasonGroup.classList.add('d-none');
            if (reasonInput) {
                reasonInput.required = false;
                reasonInput.value = '';
            }
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.className = 'btn btn-danger px-4 fw-bold disabled';
                submitBtn.innerHTML = '<i class="feather-slash me-1"></i> {{ __("visitor.entry_prohibited") }}';
            }
        }
    }

    let drawerLookupTimer = null;

    function debounceVisitorLookupDrawer(val, fieldType) {
        clearTimeout(drawerLookupTimer);
        const spinner = document.getElementById('phoneLookupSpinnerDrawer');
        const alertBox = document.getElementById('returningVisitorAlertDrawer');
        const blacklistAlert = document.getElementById('blacklistedVisitorAlertDrawer');

        if (!val || val.length < 4) {
            if (alertBox) alertBox.classList.add('d-none');
            if (blacklistAlert) blacklistAlert.classList.add('d-none');
            toggleBlacklistOverride(true);
            return;
        }

        if (spinner) spinner.classList.remove('d-none');

        drawerLookupTimer = setTimeout(function() {
            let queryParams = {};
            queryParams[fieldType] = val;
            const url = '{{ route("visitor.lookup") }}?' + new URLSearchParams(queryParams).toString();

            fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(r => r.json())
                .then(res => {
                    if (spinner) spinner.classList.add('d-none');
                    if (res.found && res.visitor) {
                        const v = res.visitor;
                        if (document.getElementById('drawerVisitorFullName') && !document.getElementById('drawerVisitorFullName').value) {
                            document.getElementById('drawerVisitorFullName').value = v.full_name || '';
                        }
                        if (document.getElementById('drawerVisitorEmail') && !document.getElementById('drawerVisitorEmail').value) {
                            document.getElementById('drawerVisitorEmail').value = v.email || '';
                        }
                        if (document.getElementById('drawerVisitorCompany') && !document.getElementById('drawerVisitorCompany').value) {
                            document.getElementById('drawerVisitorCompany').value = v.company_name || '';
                        }
                        if (document.getElementById('drawerVisitorDesignation') && !document.getElementById('drawerVisitorDesignation').value) {
                            document.getElementById('drawerVisitorDesignation').value = v.designation || '';
                        }
                        if (document.getElementById('drawerVisitorTypeSelect') && v.visitor_type) {
                            document.getElementById('drawerVisitorTypeSelect').value = v.visitor_type;
                        }
                        if (v.photo_url) {
                            const photoData = document.getElementById('drawerVisitorPhotoData');
                            const photoPreview = document.getElementById('drawerPhotoPreview');
                            const photoPreviewContainer = document.getElementById('drawerPhotoPreviewContainer');
                            if (photoData) photoData.value = v.photo_url;
                            if (photoPreview) photoPreview.src = v.photo_url;
                            if (photoPreviewContainer) photoPreviewContainer.classList.remove('d-none');
                        }

                        if (v.is_blacklisted) {
                            if (alertBox) alertBox.classList.add('d-none');
                            if (blacklistAlert) {
                                blacklistAlert.classList.remove('d-none');
                                const reasonTxt = document.getElementById('blacklistedReasonTextDrawer');
                                if (reasonTxt) reasonTxt.textContent = v.blacklist_reason || 'Security Restriction';
                            }
                            const overrideCheck = document.getElementById('drawerBlacklistOverrideCheck');
                            if (overrideCheck) overrideCheck.checked = false;
                            toggleBlacklistOverride(false);
                        } else {
                            if (blacklistAlert) blacklistAlert.classList.add('d-none');
                            if (alertBox) {
                                alertBox.classList.remove('d-none');
                                const txt = document.getElementById('returningVisitorVisitsTextDrawer');
                                if (txt) txt.textContent = (v.total_visits || 1) + ' Visits';
                            }
                            toggleBlacklistOverride(true);
                        }
                    } else {
                        if (blacklistAlert) blacklistAlert.classList.add('d-none');
                        if (alertBox) alertBox.classList.add('d-none');
                        toggleBlacklistOverride(true);
                    }
                })
                .catch(() => {
                    if (spinner) spinner.classList.add('d-none');
                });
        }, 350);
    }

    // Modal Helpers
    function openCheckoutModal(id, passNo, name) {
        document.getElementById('visitorCheckoutForm').action = '/visitor/passes/' + id + '/check-out';
        document.getElementById('checkoutPassNumber').textContent = passNo;
        document.getElementById('checkoutVisitorName').textContent = name;
        new bootstrap.Modal(document.getElementById('visitorCheckoutModal')).show();
    }

    function openExtendModal(id, passNo, currentDuration) {
        document.getElementById('extendVisitForm').action = '/visitor/passes/' + id + '/extend';
        document.getElementById('extendPassNumber').textContent = passNo;
        new bootstrap.Modal(document.getElementById('extendVisitModal')).show();
    }

    function openDenyModal(id, passNo, name) {
        document.getElementById('denyEntryForm').action = '/visitor/passes/' + id + '/deny-entry';
        document.getElementById('denyPassNumber').textContent = passNo;
        document.getElementById('denyVisitorName').textContent = name;
        new bootstrap.Modal(document.getElementById('denyEntryModal')).show();
    }

    function openIncidentModal(id, passNo) {
        document.getElementById('reportIncidentForm').action = '/visitor/passes/' + id + '/report-incident';
        document.getElementById('incidentPassNumber').textContent = passNo;
        new bootstrap.Modal(document.getElementById('reportIncidentModal')).show();
    }

    const productOptionsHtml = `@foreach($products ?? [] as $prod)<option value="{{ $prod->id }}" data-image="{{ $prod->main_image_url ?: ($prod->image_path ? asset('storage/'.$prod->image_path) : '') }}" data-name="{{ addslashes($prod->name) }}" data-sku="{{ addslashes($prod->sku ?? '') }}">{{ addslashes($prod->name) }} {{ $prod->sku ? "(".addslashes($prod->sku).")" : "" }}</option>@endforeach`;

    function addProductItemRow(tableId) {
        const tbody = document.querySelector(tableId + ' tbody');
        if (!tbody) return;
        const rowIndex = tbody.querySelectorAll('tr').length;
        const tr = document.createElement('tr');
        tr.className = 'product-item-row border-bottom border-light';
        tr.innerHTML = `
            <td class="py-1.5 ps-1 pe-1">
                <div class="d-flex align-items-center gap-2">
                    <div class="product-thumb-wrapper position-relative flex-shrink-0 border rounded-2 bg-light d-flex align-items-center justify-content-center cursor-pointer shadow-xs" 
                         style="width: 34px; height: 34px; overflow: hidden; transition: all 0.2s ease;" 
                         onclick="openProductImageZoom(this)"
                         title="Click to zoom image"
                         data-full-image=""
                         data-product-name=""
                         data-product-sku="">
                        <img src="/assets/images/icons/1.png" 
                             class="product-row-thumb w-100 h-100 object-fit-cover d-none" 
                             alt="Product"
                             onerror="this.src='/assets/images/icons/1.png';">
                        <i class="feather-box product-placeholder-icon text-muted fs-14"></i>
                        <div class="product-zoom-hint position-absolute inset-0 bg-dark bg-opacity-40 d-flex align-items-center justify-content-center opacity-0 hover-opacity-100 transition-all rounded-2" style="width:100%; height:100%; top:0; left:0;">
                            <i class="feather-maximize-2 text-white" style="font-size: 10px;"></i>
                        </div>
                    </div>
                    <div class="flex-grow-1" style="min-width: 0;">
                        <select name="product_items[${rowIndex}][product_id]" class="form-select form-select-sm fs-12 product-item-select" onchange="handleProductSelectionChange(this)">
                            <option value="" data-image="">— {{ __('visitor.select_products') }} —</option>
                            ${productOptionsHtml}
                        </select>
                    </div>
                </div>
            </td>
            <td class="py-1.5 px-1 text-center">
                <input type="number" name="product_items[${rowIndex}][quantity]" class="form-control form-control-sm text-center fw-bold fs-12" value="1" min="1" step="1">
            </td>
            <td class="py-1.5 ps-1 pe-2 text-center">
                <button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="removeProductItemRow(this)" title="Remove">
                    <i class="feather-trash-2 fs-13"></i>
                </button>
            </td>
        `;
        tbody.appendChild(tr);
        if (window.erpSearchableSelect && window.erpSearchableSelect.enhance) {
            window.erpSearchableSelect.enhance(tr);
        }
    }

    function handleProductSelectionChange(selectEl) {
        const selectedOption = selectEl.options[selectEl.selectedIndex];
        const imageUrl = selectedOption ? selectedOption.getAttribute('data-image') : '';
        const productName = selectedOption ? (selectedOption.getAttribute('data-name') || selectedOption.text) : '';
        const productSku = selectedOption ? (selectedOption.getAttribute('data-sku') || '') : '';
        
        const row = selectEl.closest('tr');
        if (!row) return;
        
        const thumbImg = row.querySelector('.product-row-thumb');
        const placeholderIcon = row.querySelector('.product-placeholder-icon');
        const thumbWrapper = row.querySelector('.product-thumb-wrapper');
        
        if (thumbImg && thumbWrapper) {
            if (imageUrl && imageUrl.trim() !== '') {
                thumbImg.src = imageUrl;
                thumbImg.classList.remove('d-none');
                if (placeholderIcon) placeholderIcon.classList.add('d-none');
                thumbWrapper.setAttribute('data-full-image', imageUrl);
                thumbWrapper.setAttribute('data-product-name', productName);
                thumbWrapper.setAttribute('data-product-sku', productSku);
                thumbWrapper.classList.add('border-primary');
                thumbWrapper.style.cursor = 'zoom-in';
            } else {
                thumbImg.classList.add('d-none');
                if (placeholderIcon) placeholderIcon.classList.remove('d-none');
                thumbWrapper.removeAttribute('data-full-image');
                thumbWrapper.removeAttribute('data-product-name');
                thumbWrapper.removeAttribute('data-product-sku');
                thumbWrapper.classList.remove('border-primary');
                thumbWrapper.style.cursor = 'default';
            }
        }
    }

    function openProductImageZoom(wrapperEl) {
        const fullImage = wrapperEl.getAttribute('data-full-image');
        if (!fullImage) return;
        
        const name = wrapperEl.getAttribute('data-product-name') || 'Product Image Preview';
        const sku = wrapperEl.getAttribute('data-product-sku') || '';
        
        document.getElementById('zoomModalProductName').textContent = name;
        document.getElementById('zoomModalProductSku').textContent = sku ? 'SKU: ' + sku : '';
        document.getElementById('zoomModalImage').src = fullImage;
        
        const zoomModal = new bootstrap.Modal(document.getElementById('productImageZoomModal'));
        zoomModal.show();
    }

    function removeProductItemRow(btn) {
        const row = btn.closest('tr');
        const tbody = row.parentElement;
        if (tbody.querySelectorAll('tr').length > 1) {
            row.remove();
        } else {
            const select = row.querySelector('select');
            const qty = row.querySelector('input[type="number"]');
            if (select) {
                select.value = '';
                handleProductSelectionChange(select);
            }
            if (qty) qty.value = 1;
        }
    }

    function openConvertLeadModalIndex(id, passNo, name, company, hostId, notes) {
        document.getElementById('convertLeadFormIndex').action = '/visitor/passes/' + id + '/convert-lead';
        document.getElementById('convertLeadPassNumber').textContent = passNo;
        document.getElementById('convertLeadVisitorName').textContent = name || 'Walk-in Visitor';
        document.getElementById('convertLeadCompanyName').textContent = company ? 'Company: ' + company : 'Individual Walk-in';
        if (hostId) {
            const hostSelect = document.getElementById('convertLeadOwnerSelect');
            if (hostSelect) hostSelect.value = hostId;
        }
        if (notes) {
            const reqField = document.getElementById('convertLeadRequirement');
            if (reqField && !reqField.value) reqField.value = notes;
        }
        new bootstrap.Modal(document.getElementById('convertLeadModalIndex')).show();
    }

    function handleDrawerPurposeChange(purpose) {
        const productBox = document.getElementById('drawerProductInquiryBox');
        const leadCheck = document.getElementById('drawerCreateCrmLead');
        if (purpose === 'Product Inquiry') {
            if (productBox) {
                productBox.classList.add('border-primary', 'shadow-sm');
                productBox.style.backgroundColor = '#f0fdf4';
            }
            if (leadCheck) {
                leadCheck.checked = true;
            }
        } else {
            if (productBox) {
                productBox.classList.remove('border-primary', 'shadow-sm');
                productBox.style.backgroundColor = '#f8fafc';
            }
        }
    }

    // Common Component Client-Side Validation Error Handlers
    function showFieldError(field, message) {
        if (!field) return;
        field.classList.add('is-invalid');
        const group = field.closest('.odoo-field-group') || field.parentElement;
        let errEl = group.querySelector('.invalid-feedback.dynamic-error-feedback');
        if (!errEl) {
            errEl = document.createElement('div');
            errEl.className = 'invalid-feedback dynamic-error-feedback d-block fs-11 mt-1 text-danger';
            group.appendChild(errEl);
        }
        errEl.innerHTML = '<i class="feather-alert-circle me-1"></i> ' + message;
    }

    function clearFieldError(field) {
        if (!field) return;
        field.classList.remove('is-invalid');
        const group = field.closest('.odoo-field-group') || field.parentElement;
        const errEl = group.querySelector('.invalid-feedback.dynamic-error-feedback');
        if (errEl) {
            errEl.remove();
        }
    }

    $(document).ready(function() {
        // Real-time error clearance on typing / selecting
        $(document).on('input change', '#newVisitorDrawerForm input, #newVisitorDrawerForm select, #newVisitorDrawerForm textarea', function() {
            if (this.value && this.value.trim() !== '') {
                clearFieldError(this);
            }
        });

        // Intercept form submit and validate with common theme error component
        const drawerForm = document.getElementById('newVisitorDrawerForm');
        if (drawerForm) {
            drawerForm.addEventListener('submit', function(e) {
                let hasErrors = false;
                let firstErrField = null;

                // Remove existing dynamic feedback
                drawerForm.querySelectorAll('.invalid-feedback.dynamic-error-feedback').forEach(el => el.remove());
                drawerForm.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));

                // Validate Phone Number
                const phoneInput = document.getElementById('drawerVisitorPhone');
                if (phoneInput && !phoneInput.value.trim()) {
                    hasErrors = true;
                    showFieldError(phoneInput, '{{ __("visitor.phone_number") }} is required.');
                    if (!firstErrField) firstErrField = phoneInput;
                }

                // Validate Visitor Name
                const nameInput = document.getElementById('drawerVisitorFullName');
                if (nameInput && !nameInput.value.trim()) {
                    hasErrors = true;
                    showFieldError(nameInput, '{{ __("visitor.visitor_name") }} is required.');
                    if (!firstErrField) firstErrField = nameInput;
                }

                // Validate Visitor Type
                const typeSelect = document.getElementById('drawerVisitorTypeSelect');
                if (typeSelect && !typeSelect.value.trim()) {
                    hasErrors = true;
                    showFieldError(typeSelect, '{{ __("visitor.visitor_type") }} is required.');
                    if (!firstErrField) firstErrField = typeSelect;
                }

                // Validate Host
                const hostSelect = document.getElementById('drawerHostUserId');
                if (hostSelect && !hostSelect.value.trim()) {
                    hasErrors = true;
                    showFieldError(hostSelect, '{{ __("visitor.select_host") }} is required.');
                    if (!firstErrField) firstErrField = hostSelect;
                }

                // Validate Purpose
                const purposeSelect = document.getElementById('drawerPurpose');
                if (purposeSelect && !purposeSelect.value.trim()) {
                    hasErrors = true;
                    showFieldError(purposeSelect, '{{ __("visitor.purpose_of_visit") }} is required.');
                    if (!firstErrField) firstErrField = purposeSelect;
                }

                // Validate Blacklist Override Reason if blacklist banner is active
                const blacklistAlert = document.getElementById('blacklistedVisitorAlertDrawer');
                const overrideCheck = document.getElementById('drawerBlacklistOverrideCheck');
                const overrideReason = document.getElementById('drawerBlacklistOverrideReasonInput');
                if (blacklistAlert && !blacklistAlert.classList.contains('d-none')) {
                    if (!overrideCheck || !overrideCheck.checked) {
                        hasErrors = true;
                        if (!firstErrField) firstErrField = overrideCheck;
                    } else if (overrideReason && !overrideReason.value.trim()) {
                        hasErrors = true;
                        showFieldError(overrideReason, 'Authorization override reason is required.');
                        if (!firstErrField) firstErrField = overrideReason;
                    }
                }

                if (hasErrors) {
                    e.preventDefault();
                    e.stopPropagation();
                    if (firstErrField) {
                        firstErrField.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        firstErrField.focus();
                    }
                    return false;
                }
            });
        }
    });
</script>
@endpush
