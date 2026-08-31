@extends('layouts.duralux')

@section('title', __('hrms.wfh.title') . ' | SaaS ERP')
@section('page-title', __('hrms.wfh.title'))
@section('breadcrumb', 'HRMS / ' . __('hrms.wfh.title'))

@push('styles')
@once
<script src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google_maps.key') }}&libraries=places"></script>
<style>
    .pac-container {
        z-index: 9999 !important;
    }
</style>
@endonce
@endpush

@push('styles')
    <style>
        .erp-pagination-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-top: auto !important;
            padding: 20px 15px 15px 15px !important;
            border-top: 1px solid #f1f5f9;
        }
        .erp-pagination {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 0;
            padding-left: 0;
            list-style: none;
        }
        .erp-pagination .page-item {
            display: inline-block;
        }
        .erp-pagination .page-link {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 50% !important;
            border: 1px solid #cbd5e1;
            background-color: #ffffff;
            color: #475569;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.2s ease-in-out;
            text-decoration: none;
            cursor: pointer;
        }
        .erp-pagination .page-link:hover {
            background-color: rgba(var(--bs-primary-rgb), 0.08);
            border-color: var(--bs-primary);
            color: var(--bs-primary);
        }
        .erp-pagination .page-item.active .page-link {
            background-color: var(--bs-primary) !important;
            border-color: var(--bs-primary) !important;
            color: #ffffff !important;
            box-shadow: 0 4px 10px rgba(var(--bs-primary-rgb), 0.2);
        }
        .erp-pagination .page-item.disabled .page-link {
            background-color: #f8fafc;
            border-color: #e2e8f0;
            color: #94a3b8;
            cursor: not-allowed;
        }
        .erp-pagination-info {
            font-size: 12px;
            color: #64748b;
        }
        .odoo-underline-input {
            border: none !important;
            border-bottom: 2px solid #cbd5e1 !important;
            border-radius: 0 !important;
            padding-left: 0 !important;
            padding-right: 0 !important;
            background: transparent !important;
        }

        /* ── Table Responsive Dropdown Visibility Fix ── */
        .table-responsive {
            position: relative;
        }
        .table-responsive:has(.dropdown.show) {
            overflow: visible !important;
        }

        /* ── Custom Sort Dropdown Chevrons ── */
        .erp-sort-dropdown .dropdown-item.active:not(:has(i))::after {
            content: '';
            display: inline-block;
            width: 12px;
            height: 12px;
            background-repeat: no-repeat;
            background-position: center;
            background-size: contain;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%233b82f6' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='18 15 12 9 6 15'%3E%3C/polyline%3E%3C/svg%3E") !important;
            margin-left: 12px;
        }
        .erp-sort-dropdown .dropdown-item.active[data-sort*="desc"]:not(:has(i))::after,
        .erp-sort-dropdown .dropdown-item.active[data-sort*="high"]:not(:has(i))::after,
        .erp-sort-dropdown .dropdown-item.active[data-sort*="oldest"]:not(:has(i))::after,
        .erp-sort-dropdown .dropdown-item.active[onclick*="desc"]:not(:has(i))::after,
        .erp-sort-dropdown .dropdown-item.active[onclick*="high"]:not(:has(i))::after,
        .erp-sort-dropdown .dropdown-item.active[onclick*="oldest"]:not(:has(i))::after,
        .erp-sort-dropdown .dropdown-item.active[href*="desc"]:not(:has(i))::after,
        .erp-sort-dropdown .dropdown-item.active[href*="high"]:not(:has(i))::after,
        .erp-sort-dropdown .dropdown-item.active[href*="oldest"]:not(:has(i))::after {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%233b82f6' stroke-width='3' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E") !important;
        }
    </style>
@endpush

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <a href="{{ route('hrms.wfh.export') }}"
           id="btnExportHeader"
           class="btn btn-light border fw-bold text-uppercase d-flex align-items-center gap-1"
           style="height: 38px; color: #475569; border-color: #cbd5e1 !important;">
            <i class="feather-download"></i> {{ __('hrms.common.export_excel') }}
        </a>
        <button type="button" class="btn btn-primary fw-bold text-uppercase d-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#applyWfhModal" style="height: 38px;">
            <i class="feather-plus"></i> {{ __('hrms.wfh.apply_wfh') }}
        </button>
    </div>
@endsection

@section('content')
    <div class="erp-single-panel bg-white p-4 shadow-sm rounded border-0 text-dark">
        @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
            <i class="feather-alert-triangle me-2"></i>
            <ul class="mb-0 ps-3">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- Summary Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center justify-content-between p-3">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">{{ __('hrms.wfh.total_requests') }}</div>
                        <h3 class="fw-bold mb-0 text-dark mt-1">{{ number_format($totalRequests) }}</h3>
                    </div>
                    <div class="avatar-lg bg-soft-primary text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background-color: rgba(13, 110, 253, 0.1);">
                        <i class="feather-home fs-4"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center justify-content-between p-3">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">{{ __('hrms.wfh.pending_approval') }}</div>
                        <h3 class="fw-bold mb-0 text-warning mt-1">{{ number_format($pendingRequests) }}</h3>
                    </div>
                    <div class="avatar-lg bg-soft-warning text-warning rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background-color: rgba(255, 193, 7, 0.1);">
                        <i class="feather-clock fs-4"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center justify-content-between p-3">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">{{ __('hrms.wfh.approved') }}</div>
                        <h3 class="fw-bold mb-0 text-success mt-1">{{ number_format($approvedRequests) }}</h3>
                    </div>
                    <div class="avatar-lg bg-soft-success text-success rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background-color: rgba(25, 135, 84, 0.1);">
                        <i class="feather-check-circle fs-4"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center justify-content-between p-3">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">{{ __('hrms.wfh.rejected') }}</div>
                        <h3 class="fw-bold mb-0 text-danger mt-1">{{ number_format($rejectedRequests) }}</h3>
                    </div>
                    <div class="avatar-lg bg-soft-danger text-danger rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; background-color: rgba(220, 53, 69, 0.1);">
                        <i class="feather-x-circle fs-4"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Requests Table --}}
    <div>
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h5 class="fw-bold text-dark mb-0"><i class="feather-home me-2 text-primary"></i> {{ __('hrms.wfh.title') }}</h5>
                <p class="text-muted fs-12 mb-0">{{ __('hrms.wfh.title') }}</p>
            </div>
            
            <div class="d-flex align-items-center gap-2">
                <form method="GET" action="javascript:void(0);" class="d-flex align-items-center gap-2 m-0" id="wfhFilterForm">
                    <!-- Search Input -->
                    <div class="d-flex align-items-center border rounded px-3 py-1" style="background-color: #f1f5f9; min-width: 220px; max-width: 280px; height: 38px;">
                        <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                        <input type="text" name="search" id="wfh_search" class="form-control border-0 bg-transparent p-0 fs-13" placeholder="{{ __('hrms.wfh.search_employee') }}" value="{{ $wfhSearch ?? '' }}" style="box-shadow: none; height: 32px;">
                    </div>

                    <!-- Sort Dropdown -->
                    <x-ui.sort-dropdown label="{{ __('hrms.common.sort') }}">
                        <a class="dropdown-item py-2 d-flex align-items-center {{ ($wfhSort ?? 'newest') === 'newest' ? 'active' : '' }}" href="#" onclick="setWfhSort('newest', this); event.preventDefault();">
                            <span>{{ __('hrms.wfh.sort_newest') }}</span>
                        </a>
                        <a class="dropdown-item py-2 d-flex align-items-center {{ ($wfhSort ?? '') === 'oldest' ? 'active' : '' }}" href="#" onclick="setWfhSort('oldest', this); event.preventDefault();">
                            <span>{{ __('hrms.wfh.sort_oldest') }}</span>
                        </a>
                        <a class="dropdown-item py-2 d-flex align-items-center {{ ($wfhSort ?? '') === 'duration_high' ? 'active' : '' }}" href="#" onclick="setWfhSort('duration_high', this); event.preventDefault();">
                            <span>{{ __('hrms.wfh.sort_duration_high_low') }}</span>
                        </a>
                        <a class="dropdown-item py-2 d-flex align-items-center {{ ($wfhSort ?? '') === 'duration_low' ? 'active' : '' }}" href="#" onclick="setWfhSort('duration_low', this); event.preventDefault();">
                            <span>{{ __('hrms.wfh.sort_duration_low_high') }}</span>
                        </a>
                    </x-ui.sort-dropdown>

                    <!-- Filter Dropdown -->
                    <x-ui.filter label="{{ __('hrms.common.filter') }}" offset="0, 5">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('hrms.common.filter_options') }}</h6>
                        
                        @if($isAdmin)
                            <div class="mb-3" style="min-width: 250px;">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.wfh.employee') }}</label>
                                <x-ui.odoo-form-ui type="select" name="employee_id" id="filter_wfh_employee_id">
                                    <option value="" {{ ($wfhEmployeeId ?? '') === '' ? 'selected' : '' }}>{{ __('hrms.common.all_employees') }}</option>
                                    @foreach(($employees ?? []) as $emp)
                                        <option value="{{ $emp->id }}" {{ ($wfhEmployeeId ?? '') == $emp->id ? 'selected' : '' }}>
                                            {{ $emp->full_name }} ({{ $emp->employee_id }})
                                        </option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </div>
                        @endif

                        <div class="mb-3" style="min-width: 250px;">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.wfh.status') }}</label>
                            <x-ui.odoo-form-ui type="select" name="status" id="filter_wfh_status">
                                <option value="" {{ ($wfhStatus ?? '') === '' ? 'selected' : '' }}>{{ __('hrms.wfh.all_statuses') }}</option>
                                <option value="pending" {{ ($wfhStatus ?? '') === 'pending' ? 'selected' : '' }}>{{ __('hrms.wfh.pending') }}</option>
                                <option value="approved" {{ ($wfhStatus ?? '') === 'approved' ? 'selected' : '' }}>{{ __('hrms.wfh.approved') }}</option>
                                <option value="rejected" {{ ($wfhStatus ?? '') === 'rejected' ? 'selected' : '' }}>{{ __('hrms.wfh.rejected') }}</option>
                            </x-ui.odoo-form-ui>
                        </div>

                        <input type="hidden" name="sort" id="wfh_sort_input" value="{{ $wfhSort ?? 'newest' }}">

                        <div class="dropdown-divider my-3"></div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary btn-sm flex-grow-1">Apply</button>
                            <button type="button" class="btn btn-light btn-sm border flex-grow-1">Reset</button>
                        </div>
                    </x-ui.filter>
                </form>
            </div>
        </div>
        <div>
            <div class="table-responsive" style="overflow: visible;">
                <table class="table table-hover align-middle mb-0" id="wfhTable">
                    <thead class="table-light">
                        <tr>
                            @if($isAdmin)
                                <th class="fs-12 text-uppercase text-muted fw-semibold ps-3" style="min-width:160px;">{{ __('hrms.wfh.employee') }}</th>
                            @endif
                            <th class="fs-12 text-uppercase text-muted fw-semibold {{ !$isAdmin ? 'ps-3' : '' }}" style="min-width:170px;">{{ __('hrms.wfh.start_date') }} – {{ __('hrms.wfh.end_date') }}</th>
                            <th class="fs-12 text-uppercase text-muted fw-semibold text-center" style="width:70px;">{{ __('hrms.wfh.duration') }}</th>
                            <th class="fs-12 text-uppercase text-muted fw-semibold" style="min-width:240px;">{{ __('hrms.wfh.reason') }}</th>
                            <th class="fs-12 text-uppercase text-muted fw-semibold text-center" style="width:65px;">{{ __('hrms.employees.tbl_file') }}</th>
                            <th class="fs-12 text-uppercase text-muted fw-semibold text-end pe-3" style="min-width:130px;">{{ __('hrms.wfh.status') }}</th>
                            <th class="fs-12 text-uppercase text-muted fw-semibold text-end pe-3" style="min-width:110px;">{{ __('hrms.wfh.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($requests as $req)
                            @php
                                $sameYear = $req->start_date && $req->end_date && ($req->start_date->format('Y') === $req->end_date->format('Y'));
                                $startStr = $req->start_date ? $req->start_date->format($sameYear ? 'd M' : 'd M Y') : '';
                                $endStr   = $req->end_date ? $req->end_date->format('d M Y') : '';
                                $dateRange = ($req->start_date && $req->end_date)
                                    ? ($req->start_date->isSameDay($req->end_date) ? $req->start_date->format('d M Y') : $startStr . ' – ' . $endStr)
                                    : '—';

                                $statusBadge = match($req->status) {
                                    'approved'               => ['cls' => 'bg-soft-success text-success', 'icon' => 'feather-check-circle',  'lbl' => __('hrms.wfh.approved')],
                                    'pending'                => ['cls' => 'bg-soft-warning text-warning', 'icon' => 'feather-clock',          'lbl' => __('hrms.wfh.pending')],
                                    'rejected'               => ['cls' => 'bg-soft-danger text-danger',   'icon' => 'feather-x-circle',       'lbl' => __('hrms.wfh.rejected')],
                                    'cancellation_requested' => ['cls' => 'bg-soft-info text-info',       'icon' => 'feather-rotate-ccw',     'lbl' => __('hrms.wfh.cancellation_requested')],
                                    'cancelled'              => ['cls' => 'bg-soft-secondary text-secondary', 'icon' => 'feather-slash',      'lbl' => __('hrms.wfh.cancelled')],
                                    default                  => ['cls' => 'bg-light text-secondary',      'icon' => 'feather-circle',         'lbl' => ucfirst($req->status)],
                                };

                                $startType = $req->start_date_type ?? 'full_day';
                                $endType   = $req->end_date_type ?? 'full_day';

                                $startTypeLabel = ucwords(str_replace('_', ' ', $startType));
                                $endTypeLabel   = ucwords(str_replace('_', ' ', $endType));

                                $sessionInfo = ($startType === $endType || ($req->start_date && $req->end_date && $req->start_date->isSameDay($req->end_date)))
                                    ? $startTypeLabel
                                    : ($startTypeLabel . ' → ' . $endTypeLabel);

                                $isLongReason = (mb_strlen($req->reason ?? '') > 70) || (substr_count($req->reason ?? '', "\n") > 1);
                                $isLongCancelReason = (mb_strlen($req->cancellation_reason ?? '') > 70) || (substr_count($req->cancellation_reason ?? '', "\n") > 1);
                            @endphp
                             <tr class="wfh-row"
                                 data-employee="{{ strtolower($req->employee->full_name ?? '') }} {{ strtolower($req->employee->employee_id ?? '') }}"
                                 data-employee-id="{{ $req->employee_id }}"
                                 data-status="{{ $req->status }}"
                                 data-duration="{{ $req->duration }}"
                                 data-created-at="{{ $req->created_at ? $req->created_at->timestamp : 0 }}"
                             >
                                @if($isAdmin)
                                    <td class="ps-3">
                                        <div class="fw-semibold text-dark fs-13">{{ $req->employee->full_name ?? 'N/A' }}</div>
                                        <div class="text-muted fs-11">
                                            Applied: {{ $req->created_at ? $req->created_at->format('d M Y') : '—' }}
                                        </div>
                                    </td>
                                @endif
                                <td class="{{ !$isAdmin ? 'ps-3' : '' }}">
                                    <div class="fw-semibold text-dark fs-13">{{ $dateRange }}</div>
                                    <div class="text-muted fs-11">{{ $sessionInfo }}</div>
                                </td>
                                <td class="text-center">
                                    <span class="fw-bold fs-13 text-dark">{{ floatval($req->duration) }}</span>
                                </td>
                                <td>
                                    <div class="wfh-reason-wrapper" style="max-width: 320px;">
                                        <div class="wfh-reason-text fs-13 text-dark mb-0" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; word-break: break-word; white-space: normal; line-height: 1.4;">
                                            {{ $req->reason }}
                                        </div>
                                        @if($isLongReason)
                                            <a href="#" class="wfh-toggle-reason-btn fs-11 text-primary fw-semibold d-inline-block mt-0.5" onclick="toggleWfhReasonText(this); return false;">See more</a>
                                        @endif
                                        @if($req->status === 'rejected' && !empty($req->rejection_reason))
                                            <div class="text-danger fs-11 mt-1">
                                                <i class="feather-alert-circle me-1"></i>Rejection: {{ $req->rejection_reason }}
                                            </div>
                                        @endif
                                        @if(in_array($req->status, ['cancellation_requested', 'cancelled']) && !empty($req->cancellation_reason))
                                             <div class="text-warning fs-11 mt-2">
                                                 <span class="fw-semibold"><i class="feather-rotate-ccw me-1"></i>Cancellation:</span>
                                                 <div class="wfh-cancel-reason-text mb-0" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; word-break: break-word; white-space: normal; line-height: 1.4; color: inherit;">
                                                     {{ $req->cancellation_reason }}
                                                 </div>
                                                 @if($isLongCancelReason)
                                                     <a href="#" class="wfh-toggle-cancel-reason-btn fs-10 text-primary fw-semibold d-inline-block mt-0.5" onclick="toggleWfhCancelReasonText(this); return false;">See more</a>
                                                 @endif
                                             </div>
                                         @endif
                                    </div>
                                </td>
                                <td class="text-center">
                                    @if($req->attachment_path)
                                        <a href="{{ asset('storage/' . $req->attachment_path) }}" target="_blank" title="View Attachment" data-bs-toggle="tooltip">
                                            <i class="feather-paperclip text-primary fs-13"></i>
                                        </a>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                {{-- Status column: plain badge only --}}
                                <td class="text-end pe-3">
                                    <span class="badge {{ $statusBadge['cls'] }} rounded-pill px-2.5 py-1 fs-11">
                                        <i class="{{ $statusBadge['icon'] }} me-1"></i>{{ $statusBadge['lbl'] }}
                                    </span>
                                </td>

                                {{-- Actions column: solid styled dropdown + delete/withdraw button --}}
                                <td class="text-end pe-3">
                                    <div class="d-flex align-items-center justify-content-end gap-2">

                                        @if($isAdmin)
                                            @if($req->status === 'cancellation_requested')
                                                {{-- Cancellation dropdown: Accept / Deny --}}
                                                <div class="dropdown {{ ($loop->last || ($loop->count > 1 && $loop->iteration >= $loop->count - 1)) ? 'dropup' : '' }} d-inline-block position-relative">
                                                    <button class="btn btn-sm dropdown-toggle py-1 px-3 d-inline-flex align-items-center justify-content-between text-capitalize fw-semibold shadow-sm" 
                                                            type="button" 
                                                            data-bs-toggle="dropdown" 
                                                            data-bs-display="static"
                                                            aria-expanded="false"
                                                            style="background-color: var(--bs-primary) !important; color: #ffffff !important; font-size: 11.5px; height: 32px; border-radius: 8px; min-width: 130px; border: none;"
                                                            title="{{ __('hrms.wfh.status') }}">
                                                        <span>{{ __('hrms.wfh.status') }}</span>
                                                    </button>
                                                    <ul class="dropdown-menu dropdown-menu-end shadow border-0 p-1.5 mt-1 fs-12" style="border-radius: 8px; min-width: 130px; z-index: 1050; background: #ffffff;">
                                                        <li>
                                                            <form method="POST" action="{{ route('hrms.wfh.approve-cancellation', $req->id) }}" onsubmit="return confirmFormSubmit(event, 'Approve this WFH cancellation?', { title: 'Approve Cancellation', variant: 'success', confirmButtonText: 'Approve' })" class="m-0">
                                                                @csrf
                                                                <button type="submit" class="dropdown-item rounded py-1.5 px-3 text-dark fw-medium d-flex align-items-center justify-content-between text-success">
                                                                    <span>{{ __('hrms.wfh.approved') }}</span>
                                                                    <i class="feather-check text-success fs-12"></i>
                                                                </button>
                                                            </form>
                                                        </li>
                                                        <li>
                                                            <form method="POST" action="{{ route('hrms.wfh.deny-cancellation', $req->id) }}" onsubmit="return confirmFormSubmit(event, 'Deny this cancellation request?', { title: 'Deny Cancellation', variant: 'danger', confirmButtonText: 'Deny' })" class="m-0">
                                                                @csrf
                                                                <button type="submit" class="dropdown-item rounded py-1.5 px-3 text-dark fw-medium d-flex align-items-center justify-content-between text-danger">
                                                                    <span>{{ __('hrms.wfh.rejected') }}</span>
                                                                    <i class="feather-x text-danger fs-12"></i>
                                                                </button>
                                                            </form>
                                                        </li>
                                                    </ul>
                                                </div>

                                            @elseif(!in_array($req->status, ['cancelled']))
                                                {{-- Normal status dropdown: Approved / Rejected / Pending --}}
                                                <div class="dropdown {{ ($loop->last || ($loop->count > 1 && $loop->iteration >= $loop->count - 1)) ? 'dropup' : '' }} d-inline-block position-relative">
                                                    <button class="btn btn-sm dropdown-toggle py-1 px-3 d-inline-flex align-items-center justify-content-between text-capitalize fw-semibold shadow-sm" 
                                                            type="button" 
                                                            data-bs-toggle="dropdown" 
                                                            data-bs-display="static"
                                                            aria-expanded="false"
                                                            style="background-color: var(--bs-primary) !important; color: #ffffff !important; font-size: 11.5px; height: 32px; border-radius: 8px; min-width: 130px; border: none;"
                                                            title="Change Status">
                                                        <span>{{ $statusBadge['lbl'] }}</span>
                                                    </button>
                                                    <ul class="dropdown-menu dropdown-menu-end shadow border-0 p-1.5 mt-1 fs-12" style="border-radius: 8px; min-width: 130px; z-index: 1050; background: #ffffff;">
                                                        <li>
                                                            <a class="dropdown-item rounded py-1.5 px-3 text-dark fw-medium d-flex align-items-center justify-content-between {{ $req->status === 'approved' ? 'bg-light text-primary fw-bold' : '' }}"
                                                               href="#"
                                                               onclick="submitWfhStatusDirect('{{ route('hrms.wfh.update-status', $req->id) }}', 'approved'); return false;"
                                                               style="{{ $req->status === 'approved' ? 'color: var(--bs-primary) !important;' : '' }}">
                                                                <span>{{ __('hrms.wfh.approved') }}</span>
                                                            </a>
                                                        </li>
                                                        <li>
                                                            <a class="dropdown-item rounded py-1.5 px-3 text-dark fw-medium d-flex align-items-center justify-content-between {{ $req->status === 'rejected' ? 'bg-light text-primary fw-bold' : '' }}"
                                                               href="#"
                                                               data-action="{{ route('hrms.wfh.reject', $req->id) }}"
                                                               onclick="openWfhRejectModal(this); return false;"
                                                               style="{{ $req->status === 'rejected' ? 'color: var(--bs-primary) !important;' : '' }}">
                                                                <span>{{ __('hrms.wfh.rejected') }}</span>
                                                            </a>
                                                        </li>
                                                        <li>
                                                            <a class="dropdown-item rounded py-1.5 px-3 text-dark fw-medium d-flex align-items-center justify-content-between {{ $req->status === 'pending' ? 'bg-light text-primary fw-bold' : '' }}"
                                                               href="#"
                                                               onclick="submitWfhStatusDirect('{{ route('hrms.wfh.update-status', $req->id) }}', 'pending'); return false;"
                                                               style="{{ $req->status === 'pending' ? 'color: var(--bs-primary) !important;' : '' }}">
                                                                <span>{{ __('hrms.wfh.pending') }}</span>
                                                            </a>
                                                        </li>
                                                    </ul>
                                                </div>
                                            @endif

                                            {{-- Unified Withdraw / Cancellation Delete button --}}
                                            @if($req->canWithdraw())
                                                <form method="POST" action="{{ route('hrms.wfh.withdraw', $req->id) }}" onsubmit="return confirmFormSubmit(event, 'Withdraw this WFH application?', { title: 'Withdraw WFH Application', variant: 'warning', confirmButtonText: 'Withdraw' })" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-soft-danger border" 
                                                            title="Withdraw Application"
                                                            style="border-radius: 8px; width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; padding: 0;">
                                                        <i class="feather-trash-2 fs-14"></i>
                                                    </button>
                                                </form>
                                            @elseif($req->canRequestCancellation())
                                                <button type="button" class="btn btn-sm btn-soft-danger border" 
                                                        title="Request Cancellation"
                                                        onclick="openWfhCancellationModal({{ $req->id }}, '{{ route('hrms.wfh.request-cancellation', $req->id) }}')"
                                                        style="border-radius: 8px; width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; padding: 0;">
                                                    <i class="feather-trash-2 fs-14"></i>
                                                </button>
                                            @else
                                                <button type="button" class="btn btn-sm btn-light border disabled" 
                                                        style="border-radius: 8px; width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; padding: 0;" disabled>
                                                    <i class="feather-trash-2 fs-14"></i>
                                                </button>
                                            @endif

                                        @else
                                            {{-- Non-admin actions --}}
                                            {{-- Unified Withdraw / Cancellation Delete button --}}
                                            @if($req->canWithdraw())
                                                <form method="POST" action="{{ route('hrms.wfh.withdraw', $req->id) }}" onsubmit="return confirmFormSubmit(event, 'Withdraw this WFH application?', { title: 'Withdraw WFH Application', variant: 'warning', confirmButtonText: 'Withdraw' })" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-soft-danger border" 
                                                            title="Withdraw Application"
                                                            style="border-radius: 8px; width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; padding: 0;">
                                                        <i class="feather-trash-2 fs-14"></i>
                                                    </button>
                                                </form>
                                            @elseif($req->canRequestCancellation())
                                                <button type="button" class="btn btn-sm btn-soft-danger border" 
                                                        title="Request Cancellation"
                                                        onclick="openWfhCancellationModal({{ $req->id }}, '{{ route('hrms.wfh.request-cancellation', $req->id) }}')"
                                                        style="border-radius: 8px; width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; padding: 0;">
                                                    <i class="feather-trash-2 fs-14"></i>
                                                </button>
                                            @else
                                                <button type="button" class="btn btn-sm btn-light border disabled" 
                                                        style="border-radius: 8px; width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; padding: 0;" disabled>
                                                    <i class="feather-trash-2 fs-14"></i>
                                                </button>
                                            @endif
                                        @endif

                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="text-center py-5 text-muted">
                                    <i class="feather-folder fs-3 d-block mb-3 text-secondary"></i>
                                    No WFH applications found.
                                </td>
                            </tr>
                        @endforelse
                        <tr id="no_matching_wfh_row" class="d-none">
                            <td colspan="10" class="text-center py-5 text-muted">
                                <i class="feather-folder fs-3 d-block mb-3 text-secondary"></i>
                                No matching applications found.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div id="wfh_pagination_container">
                @if($requests instanceof \Illuminate\Pagination\LengthAwarePaginator && $requests->hasPages())
                    <x-ui.pagination
                        class="px-0 py-0"
                        :current-page="$requests->currentPage()"
                        :total-pages="$requests->lastPage()"
                        :total-results="$requests->total()"
                        :per-page="$requests->perPage()"
                        page-param="wfh_page"
                    />
                @endif
            </div>
            </div>
    </div>

{{-- WFH Cancellation Request Modal --}}
<div class="modal fade" id="wfhCancellationModal" tabindex="-1" aria-labelledby="wfhCancellationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom py-3">
                <h5 class="modal-title fw-bold text-dark" id="wfhCancellationModalLabel">
                    <i class="feather-x-circle text-warning me-2"></i>{{ __('hrms.wfh.delete_application') }}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="wfhCancellationForm" method="POST" action="">
                @csrf
                <div class="modal-body p-4">
                    <p class="text-muted fs-13 mb-3">
                        {{ __('hrms.wfh.confirm_delete') }}
                    </p>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark fs-13">{{ __('hrms.wfh.reason_comments') }} <span class="text-danger">*</span></label>
                        <textarea name="cancellation_reason" id="wfh_cancellation_reason" class="form-control fs-13" rows="3" placeholder="{{ __('hrms.wfh.reason_placeholder') }}" required maxlength="1000"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top py-3 d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('hrms.wfh.close') }}</button>
                    <button type="submit" class="btn btn-warning text-dark fw-semibold">
                        <i class="feather-send me-1"></i>{{ __('hrms.wfh.submit_application') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Apply WFH Modal --}}
<div class="modal fade" id="applyWfhModal" tabindex="-1" aria-labelledby="applyWfhModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom py-3">
                <h5 class="modal-title fw-bold text-dark" id="applyWfhModalLabel"><i class="feather-home me-2 text-primary"></i> {{ __('hrms.wfh.apply_for_wfh') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('hrms.wfh.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-body p-4">
                    @if ($errors->any())
                        <div class="alert alert-danger mb-3">
                            <ul class="mb-0">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if($isAdmin)
                        <div class="row mb-3">
                            <div class="col-12">
                                <x-ui.odoo-form-ui type="select" label="{{ __('hrms.wfh.employee') }}" name="employee_id" id="wfh_employee_select" :required="true" class="odoo-select2-custom">
                                    <option value="">-- {{ __('hrms.wfh.select_employee') }} --</option>
                                    @foreach($employees as $emp)
                                        <option value="{{ $emp->id }}" {{ (old('employee_id') == $emp->id || ($employee && $employee->id == $emp->id)) ? 'selected' : '' }}>
                                            {{ $emp->full_name }} ({{ $emp->employee_id }})
                                        </option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </div>
                        </div>
                    @endif

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input" inputType="date" label="{{ __('hrms.wfh.start_date') }}" name="start_date" id="wfh_start_date" :required="true" value="{{ old('start_date') }}" class="odoo-underline-input" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="select" label="{{ __('hrms.wfh.start_date') }} Session" name="start_date_type" id="wfh_start_type" :required="true" class="odoo-select2-custom">
                                <option value="full_day" {{ old('start_date_type') == 'full_day' ? 'selected' : '' }}>Full Day</option>
                                <option value="first_half" {{ old('start_date_type') == 'first_half' ? 'selected' : '' }}>First Half</option>
                                <option value="second_half" {{ old('start_date_type') == 'second_half' ? 'selected' : '' }}>Second Half</option>
                            </x-ui.odoo-form-ui>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input" inputType="date" label="{{ __('hrms.wfh.end_date') }}" name="end_date" id="wfh_end_date" :required="true" value="{{ old('end_date') }}" class="odoo-underline-input" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="select" label="{{ __('hrms.wfh.end_date') }} Session" name="end_date_type" id="wfh_end_type" :required="true" class="odoo-select2-custom">
                                <option value="full_day" {{ old('end_date_type') == 'full_day' ? 'selected' : '' }}>Full Day</option>
                                <option value="first_half" {{ old('end_date_type') == 'first_half' ? 'selected' : '' }}>First Half</option>
                                <option value="second_half" {{ old('end_date_type') == 'second_half' ? 'selected' : '' }}>Second Half</option>
                            </x-ui.odoo-form-ui>
                        </div>
                    </div>

                    <div class="col-12 mb-3">
                        <div id="wfh_calculated_duration_display" class="alert alert-info py-2 fs-12 mb-0 d-flex justify-content-between align-items-center">
                            <div>
                                <i class="feather-info me-1"></i> Estimated {{ __('hrms.wfh.duration') }}: <strong id="wfh_duration_val">0.0</strong> Day(s)
                            </div>
                            <div class="fw-semibold text-primary" id="wfh_session_flow_val">
                                (Full Day)
                            </div>
                        </div>
                        <input type="hidden" name="duration" id="wfh_duration" value="{{ old('duration', '0.0') }}">
                    </div>

                    <div class="row mb-3">
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.wfh.reason_comments') }}" name="reason" :required="true" class="odoo-underline-input" placeholder="{{ __('hrms.wfh.reason_placeholder') }}">{{ old('reason') }}</x-ui.odoo-form-ui>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="file" label="{{ __('hrms.wfh.attachment_optional') }}" name="attachment" id="wfh_attachment" :required="false" helperText="{{ __('hrms.wfh.attachment_help') }}" />
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="card bg-light border p-3 rounded-3 shadow-sm">
                            <div class="fw-bold text-dark fs-12 mb-2"><i class="feather-map-pin me-1 text-primary"></i> WFH Target Coordinates</div>
                            <div class="row g-2 align-items-end">
                                <div class="col-md-4">
                                    <x-ui.odoo-form-ui type="input" label="WFH Latitude" name="wfh_latitude" id="wfh_req_latitude" placeholder="e.g. 28.6139" class="odoo-underline-input" />
                                </div>
                                <div class="col-md-4">
                                    <x-ui.odoo-form-ui type="input" label="WFH Longitude" name="wfh_longitude" id="wfh_req_longitude" placeholder="e.g. 77.2090" class="odoo-underline-input" />
                                </div>
                                <div class="col-md-4 d-flex align-items-end">
                                    <div class="d-flex gap-2 w-100">
                                        <button type="button" class="btn btn-primary btn-sm flex-fill" id="btn_detect_wfh_req_loc" style="font-size: 11px;">
                                            <i class="feather-crosshair me-1"></i>Detect
                                        </button>
                                        <button type="button" class="btn btn-light-brand btn-sm flex-fill" id="btn_toggle_wfh_req_map" style="font-size: 11px;" onclick="toggleWfhReqMap()">
                                            <i class="feather-map me-1"></i>Map
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="position-relative mt-3" id="wfh_req_map_wrap" style="display: none;">
                                <input type="text" id="wfh_req_map_search" class="form-control position-absolute" style="top: 10px; right: 10px; width: 240px; z-index: 1000; box-shadow: 0 2px 8px rgba(0,0,0,0.15) !important; font-size: 11px; border: none !important; border-radius: 6px !important; padding: 6px 12px !important; height: 34px !important; background-color: #fff !important; outline: none !important;" placeholder="Search address or subarea (Press Enter)...">
                                <div id="wfh_req_map_picker" style="height: 180px; width: 100%; border-radius: 8px; border: 1px solid #ced4da; z-index: 1;"></div>
                            </div>
                            <small class="form-text text-muted fs-11 mt-1">If WFH geofencing is enabled, checking in will require matching these coordinates.</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top py-3">
                    <button type="button" class="btn btn-light border px-4 text-uppercase fw-bold" data-bs-dismiss="modal" style="font-size: 11px;">{{ __('hrms.wfh.close') }}</button>
                    <button type="submit" class="btn btn-primary px-4 text-uppercase fw-bold" style="font-size: 11px;">{{ __('hrms.wfh.submit_application') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Reject Reason Modal --}}
<div class="modal fade" id="rejectWfhModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom py-3">
                <h5 class="modal-title fw-bold text-danger"><i class="feather-x-circle me-2"></i> {{ __('hrms.wfh.rejected') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="rejectWfhForm" method="POST" action="">
                @csrf
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-secondary fs-13">{{ __('hrms.wfh.reason_comments') }} <span class="text-danger">*</span></label>
                        <textarea name="rejection_reason" class="form-control" rows="3" placeholder="{{ __('hrms.wfh.reason_placeholder') }}" required></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top py-3">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">{{ __('hrms.wfh.close') }}</button>
                    <button type="submit" class="btn btn-danger">{{ __('hrms.wfh.rejected') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>

function submitWfhStatusDirect(url, action) {
    var form = document.createElement('form');
    form.method = 'POST';
    form.action = url;
    
    var csrfInput = document.createElement('input');
    csrfInput.type = 'hidden';
    csrfInput.name = '_token';
    csrfInput.value = '{{ csrf_token() }}';
    form.appendChild(csrfInput);

    var actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = action;
    form.appendChild(actionInput);

    document.body.appendChild(form);
    form.submit();
}

function toggleWfhReasonText(btn) {
    var textEl = btn.previousElementSibling;
    if (textEl.style.display === 'block') {
        textEl.style.display = '-webkit-box';
        textEl.style.webkitLineClamp = '2';
        btn.textContent = 'See more';
    } else {
        textEl.style.display = 'block';
        textEl.style.webkitLineClamp = 'none';
        btn.textContent = 'See less';
    }
}

function toggleWfhCancelReasonText(btn) {
    var textEl = btn.previousElementSibling;
    if (textEl.style.display === 'block') {
        textEl.style.display = '-webkit-box';
        textEl.style.webkitLineClamp = '2';
        btn.textContent = 'See more';
    } else {
        textEl.style.display = 'block';
        textEl.style.webkitLineClamp = 'none';
        btn.textContent = 'See less';
    }
}

function openWfhRejectModal(btn) {
    var actionUrl = btn.getAttribute('data-action');
    var form = document.getElementById('rejectWfhForm');
    if (form && actionUrl) {
        form.action = actionUrl;
        var modalEl = document.getElementById('rejectWfhModal');
        if (modalEl) {
            var modal = new bootstrap.Modal(modalEl);
            modal.show();
        }
    }
}

document.addEventListener('DOMContentLoaded', function () {
    // Ensure modals are appended to document.body to prevent backdrop z-index clipping
    ['applyWfhModal', 'rejectWfhModal'].forEach(function (id) {
        const modalEl = document.getElementById(id);
        if (modalEl && modalEl.parentElement !== document.body) {
            document.body.appendChild(modalEl);
        }
    });

    // Dynamic Duration & Session Calculation
    const startDateInput = document.getElementById('wfh_start_date');
    const endDateInput   = document.getElementById('wfh_end_date');
    const startTypeInput = document.getElementById('wfh_start_type');
    const endTypeInput   = document.getElementById('wfh_end_type');
    const durationInput  = document.getElementById('wfh_duration');

    function formatSessionTitle(str) {
        if (!str) return 'Full Day';
        return str.replace('_', ' ').replace(/\b\w/g, function(l) { return l.toUpperCase(); });
    }

    function calculateDuration() {
        if (!startDateInput.value || !endDateInput.value) {
            durationInput.value = '0.0';
            const displayEl = document.getElementById('wfh_duration_val');
            if (displayEl) displayEl.textContent = '0.0';
            const flowEl = document.getElementById('wfh_session_flow_val');
            if (flowEl) flowEl.textContent = '';
            return;
        }

        const startParts = startDateInput.value.split('-');
        const endParts = endDateInput.value.split('-');
        const start = new Date(parseInt(startParts[0], 10), parseInt(startParts[1], 10) - 1, parseInt(startParts[2], 10));
        const end   = new Date(parseInt(endParts[0], 10), parseInt(endParts[1], 10) - 1, parseInt(endParts[2], 10));

        if (end < start) {
            durationInput.value = '0.0';
            const displayEl = document.getElementById('wfh_duration_val');
            if (displayEl) displayEl.textContent = '0.0';
            const flowEl = document.getElementById('wfh_session_flow_val');
            if (flowEl) flowEl.textContent = '(Invalid Date Range)';
            return;
        }

        let duration = 0;
        let current = new Date(start);

        if (start.getTime() === end.getTime()) {
            // Single day
            if (start.getDay() !== 0) { // Exclude Sunday (0)
                duration = (startTypeInput.value === 'full_day') ? 1.0 : 0.5;
            }
        } else {
            while (current <= end) {
                // Exclude Sundays
                if (current.getDay() !== 0) {
                    var isStart = current.getTime() === start.getTime();
                    var isEnd = current.getTime() === end.getTime();

                    if (isStart) {
                        duration += (startTypeInput.value === 'full_day') ? 1.0 : 0.5;
                    } else if (isEnd) {
                        duration += (endTypeInput.value === 'full_day') ? 1.0 : 0.5;
                    } else {
                        duration += 1.0;
                    }
                }
                current.setDate(current.getDate() + 1);
            }
        }

        const formatted = duration.toFixed(1);
        durationInput.value = formatted;
        const displayEl = document.getElementById('wfh_duration_val');
        if (displayEl) displayEl.textContent = formatted;

        const startLabel = formatSessionTitle(startTypeInput.value);
        const endLabel   = formatSessionTitle(endTypeInput.value);
        const flowEl     = document.getElementById('wfh_session_flow_val');
        if (flowEl) {
            if (startTypeInput.value === endTypeInput.value || startDateInput.value === endDateInput.value) {
                flowEl.textContent = '(' + startLabel + ')';
            } else {
                flowEl.textContent = '(' + startLabel + ' → ' + endLabel + ')';
            }
        }
    }

    if (startDateInput && endDateInput) {
        // Automatically sync End Date when Start Date changes (matching Leave Application form)
        $('#wfh_start_date').on('change', function () {
            if (this.value) {
                endDateInput.value = this.value;
            }
            calculateDuration();
        });
        $('#wfh_end_date, #wfh_start_type, #wfh_end_type').on('change', calculateDuration);
        calculateDuration();
    }

    // Append wfhCancellationModal to body to avoid z-index/backdrop issues
    var wfhCancelEl = document.getElementById('wfhCancellationModal');
    if (wfhCancelEl && wfhCancelEl.parentNode !== document.body) {
        document.body.appendChild(wfhCancelEl);
    }

    // AJAX Loader for server-side search, sort, filter, and pagination
    function loadWfhApplications(page = 1) {
        var search = $('#wfh_search').val() || '';
        var empId = $('#filter_wfh_employee_id').val() || '';
        var status = $('#filter_wfh_status').val() || '';
        var sort = $('#wfh_sort_input').val() || 'newest';

        var url = '{{ route("hrms.wfh.index") }}?' +
                  'wfh_search=' + encodeURIComponent(search) +
                  '&wfh_employee_id=' + encodeURIComponent(empId) +
                  '&wfh_status=' + encodeURIComponent(status) +
                  '&wfh_sort=' + encodeURIComponent(sort) +
                  '&wfh_page=' + page;

        $.ajax({
            url: url,
            type: 'GET',
            success: function(response) {
                var parser = new DOMParser();
                var doc = parser.parseFromString(response, 'text/html');
                
                var oldBody = $('#wfhTable tbody');
                var newBody = $(doc).find('#wfhTable tbody');
                if (oldBody.length && newBody.length) {
                    oldBody.html(newBody.html());
                }
                
                var oldPagination = $('#wfh_pagination_container');
                var newPagination = $(doc).find('#wfh_pagination_container');
                if (oldPagination.length && newPagination.length) {
                    oldPagination.replaceWith(newPagination);
                }

                $('.erp-filter-dropdown .dropdown-menu.show').removeClass('show');
                $('.erp-filter-dropdown.show').removeClass('show');

                history.pushState(null, '', url);
            }
        });
    }

    var wfhSearchTimeout;
    $(document).on('input', '#wfh_search', function() {
        clearTimeout(wfhSearchTimeout);
        wfhSearchTimeout = setTimeout(function() {
            loadWfhApplications(1);
        }, 300);
    });

    $('#wfhFilterForm').on('submit', function(e) {
        e.preventDefault();
        loadWfhApplications(1);
        $(this).closest('.dropdown').find('[data-bs-toggle="dropdown"]').dropdown('toggle');
    });

    $('#wfhFilterForm').find('.btn-light').on('click', function(e) {
        e.preventDefault();
        $('#wfh_search').val('');
        $('#filter_wfh_employee_id').val('').trigger('change');
        $('#filter_wfh_status').val('').trigger('change');
        $('#wfh_sort_input').val('newest');

        var sortDropdown = $('.erp-sort-dropdown');
        if (sortDropdown.length) {
            sortDropdown.find('.dropdown-item').removeClass('active');
            sortDropdown.find('.dropdown-item:first').addClass('active');
        }

        loadWfhApplications(1);
        $(this).closest('.dropdown').find('[data-bs-toggle="dropdown"]').dropdown('toggle');
    });

    $(document).on('click', '#wfh_pagination_container a', function(e) {
        e.preventDefault();
        var url = $(this).attr('href');
        if (!url) return;
        var urlParams = new URLSearchParams(url.substring(url.indexOf('?')));
        var page = urlParams.get('wfh_page') || 1;
        loadWfhApplications(page);
    });

    window.loadWfhApplications = loadWfhApplications;
    window.setWfhSort = function(criteria, element) {
        var input = document.getElementById('wfh_sort_input');
        if (input) {
            input.value = criteria;
        }
        if (element) {
            var menu = element.closest('.dropdown-menu');
            if (menu) {
                menu.querySelectorAll('.dropdown-item').forEach(function(el) {
                    el.classList.remove('active');
                });
            }
            element.classList.add('active');
        }
        if (typeof window.loadWfhApplications === 'function') {
            window.loadWfhApplications(1);
        }
    };
});

// ── WFH Cancellation Modal ─────────────────────────────────────────────────
function openWfhCancellationModal(wfhId, actionUrl) {
    var form = document.getElementById('wfhCancellationForm');
    if (form) {
        form.action = actionUrl;
    }
    document.getElementById('wfh_cancellation_reason').value = '';
    var modal = new bootstrap.Modal(document.getElementById('wfhCancellationModal'));
    modal.show();
}

// ── WFH Target Coordinates & Leaflet Map setup ──────────────────────────────
const runWfhReqSetup = () => {
    let reqWfhMap = null;
    let reqWfhMarker = null;

    const initWfhReqMapPicker = () => {
        if (typeof google === 'undefined' || typeof google.maps === 'undefined') {
            setTimeout(initWfhReqMapPicker, 100);
            return;
        }

        const mapContainerId = 'wfh_req_map_picker';
        const latInput = $('#wfh_req_latitude');
        const lngInput = $('#wfh_req_longitude');
        
        if (!document.getElementById(mapContainerId)) return;

        let initialLat = parseFloat(latInput.val()) || 28.6139; // Default center
        let initialLng = parseFloat(lngInput.val()) || 77.2090;
        const initialPos = { lat: initialLat, lng: initialLng };
        
        if (reqWfhMap) {
            google.maps.event.trigger(reqWfhMap, 'resize');
            reqWfhMap.setCenter(initialPos);
            return;
        }

        reqWfhMap = new google.maps.Map(document.getElementById(mapContainerId), {
            center: initialPos,
            zoom: 13,
            mapTypeControl: false,
            fullscreenControl: false,
            streetViewControl: false
        });

        reqWfhMarker = new google.maps.Marker({
            position: initialPos,
            map: reqWfhMap,
            draggable: true
        });

        // Update inputs on marker drag
        reqWfhMarker.addListener('dragend', function() {
            const position = reqWfhMarker.getPosition();
            latInput.val(position.lat().toFixed(8));
            lngInput.val(position.lng().toFixed(8));
        });

        // Update marker and inputs on map click
        reqWfhMap.addListener('click', function(e) {
            reqWfhMarker.setPosition(e.latLng);
            latInput.val(e.latLng.lat().toFixed(8));
            lngInput.val(e.latLng.lng().toFixed(8));
        });

        // Update map when inputs change manually
        const updateMapFromInputs = () => {
            const lat = parseFloat(latInput.val());
            const lng = parseFloat(lngInput.val());
            if (!isNaN(lat) && !isNaN(lng)) {
                const latlng = { lat: lat, lng: lng };
                reqWfhMarker.setPosition(latlng);
                reqWfhMap.setCenter(latlng);
            }
        };

        latInput.on('input', updateMapFromInputs);
        lngInput.on('input', updateMapFromInputs);

        // Bind Google Places Autocomplete search input
        const searchInputEl = document.getElementById('wfh_req_map_search');
        if (searchInputEl) {
            const autocomplete = new google.maps.places.Autocomplete(searchInputEl);
            autocomplete.bindTo('bounds', reqWfhMap);

            autocomplete.addListener('place_changed', function() {
                const place = autocomplete.getPlace();
                if (!place.geometry || !place.geometry.location) {
                    return;
                }

                reqWfhMap.setCenter(place.geometry.location);
                reqWfhMap.setZoom(15);
                reqWfhMarker.setPosition(place.geometry.location);

                latInput.val(place.geometry.location.lat().toFixed(8));
                lngInput.val(place.geometry.location.lng().toFixed(8));
            });

            // Prevent form submission when pressing enter on search box
            $(searchInputEl).on('keypress', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                }
            });
        }
    };

    const setupWfhRequestCoords = () => {
        if (typeof $ === 'undefined') {
            setTimeout(setupWfhRequestCoords, 100);
            return;
        }

        // Toggle map open/close
        window.toggleWfhReqMap = function() {
            const mapWrap = document.getElementById('wfh_req_map_wrap');
            const toggleBtn = document.getElementById('btn_toggle_wfh_req_map');
            if (!mapWrap) return;
            const isVisible = mapWrap.style.display !== 'none';
            if (isVisible) {
                mapWrap.style.display = 'none';
                if (toggleBtn) {
                    toggleBtn.innerHTML = '<i class="feather-map me-1"></i>Map';
                    toggleBtn.classList.remove('btn-secondary');
                    toggleBtn.classList.add('btn-soft-secondary');
                }
            } else {
                mapWrap.style.display = 'block';
                if (toggleBtn) {
                    toggleBtn.innerHTML = '<i class="feather-x me-1"></i>Hide Map';
                    toggleBtn.classList.remove('btn-soft-secondary');
                    toggleBtn.classList.add('btn-secondary');
                }
                setTimeout(initWfhReqMapPicker, 150);
            }
        };

        // Initialize/invalidate on modal show — only reset search, don't auto-show map
        const modalEl = document.getElementById('applyWfhModal');
        if (modalEl) {
            modalEl.addEventListener('shown.bs.modal', function() {
                const searchInput = $('#wfh_req_map_search');
                if (searchInput.length) {
                    searchInput.val('');
                    searchInput.prop('disabled', false).attr('placeholder', 'Search address or subarea (Press Enter)...');
                }
                // Reset map visibility
                const mapWrap = document.getElementById('wfh_req_map_wrap');
                const toggleBtn = document.getElementById('btn_toggle_wfh_req_map');
                if (mapWrap) mapWrap.style.display = 'none';
                if (toggleBtn) {
                    toggleBtn.innerHTML = '<i class="feather-map me-1"></i>Map';
                    toggleBtn.classList.remove('btn-secondary');
                    toggleBtn.classList.add('btn-soft-secondary');
                }
            });
        }

        $('#btn_detect_wfh_req_loc').on('click', function() {
            const btn = $(this);
            const originalHtml = btn.html();
            
            if (!navigator.geolocation) {
                alert('Geolocation is not supported by your browser.');
                return;
            }

            btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Det.');

            navigator.geolocation.getCurrentPosition(
                function(position) {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;
                    $('#wfh_req_latitude').val(lat.toFixed(8));
                    $('#wfh_req_longitude').val(lng.toFixed(8));
                    
                    const latlng = { lat: lat, lng: lng };
                    if (reqWfhMap && reqWfhMarker) {
                        reqWfhMarker.setPosition(latlng);
                        reqWfhMap.setCenter(latlng);
                        reqWfhMap.setZoom(15);
                    } else {
                        // Auto-open map to show detected location
                        const mapWrap = document.getElementById('wfh_req_map_wrap');
                        if (mapWrap && mapWrap.style.display === 'none') {
                            window.toggleWfhReqMap();
                            setTimeout(() => {
                                if (reqWfhMap && reqWfhMarker) {
                                    reqWfhMarker.setPosition(latlng);
                                    reqWfhMap.setCenter(latlng);
                                    reqWfhMap.setZoom(15);
                                }
                            }, 600);
                        }
                    }
                    
                    btn.prop('disabled', false).html(originalHtml);
                },
                function(error) {
                    let errorMsg = 'Unable to retrieve location.';
                    if (error.code === error.PERMISSION_DENIED) {
                        errorMsg = 'Permission denied. Please allow location access.';
                    }
                    alert(errorMsg);
                    btn.prop('disabled', false).html(originalHtml);
                },
                { enableHighAccuracy: true, timeout: 10000 }
            );
        });
    };

    setupWfhRequestCoords();
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', runWfhReqSetup);
} else {
    runWfhReqSetup();
}
</script>
@endpush
@endsection
