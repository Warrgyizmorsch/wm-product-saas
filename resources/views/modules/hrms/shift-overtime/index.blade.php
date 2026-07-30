@extends('layouts.duralux')

@section('title', __('hrms.sidebar.shift_roster') . ' | SaaS ERP')
@section('page-title', __('hrms.sidebar.shift_roster'))
@section('breadcrumb', 'HRMS / ' . __('hrms.sidebar.shift_roster'))

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <button type="button" id="btnApplyShift" class="btn btn-primary fw-bold text-uppercase d-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#applyShiftChangeModal" style="height: 38px;">
            <i class="feather-plus"></i> {{ __('hrms.shift_change.apply_shift_change') }}
        </button>
        <button type="button" id="btnApplyOvertime" class="btn btn-primary fw-bold text-uppercase d-flex align-items-center gap-1 d-none" data-bs-toggle="modal" data-bs-target="#applyOvertimeModal" style="height: 38px;">
            <i class="feather-plus"></i> {{ __('hrms.overtime.apply_overtime') }}
        </button>
    </div>
@endsection

@push('styles')
    <style>
        /* Underlined Horizontal Tabs (matching Leave module) */
        #shiftOvertimeTabs .nav-link {
            border: none !important;
            background-color: transparent !important;
            color: #64748b;
            font-weight: 500;
            padding: 12px 20px;
            border-bottom: 2px solid transparent !important;
            transition: all 0.2s ease-in-out;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        #shiftOvertimeTabs .nav-link:hover {
            color: var(--bs-primary);
        }
        #shiftOvertimeTabs .nav-link.active {
            color: var(--bs-primary) !important;
            border-bottom: 2px solid var(--bs-primary) !important;
            font-weight: 600;
        }

        /* High-specificity overrides to force Select2 options and selection text to dark grey, not blue */
        body .select2-container--bootstrap-5 .select2-dropdown .select2-results__options .select2-results__option,
        body .select2-container--bootstrap-5 .select2-dropdown .select2-results__options .select2-results__option *,
        body .select2-container .select2-results__option,
        body .select2-container .select2-results__option *,
        body .select2-results__option,
        body .select2-results__option *,
        .select2-results__option,
        .select2-results__option * {
            color: #1e293b !important;
        }

        body .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered,
        .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
            color: #1e293b !important;
        }

        /* High-specificity overrides for highlighted/hovered items */
        body .select2-container--bootstrap-5 .select2-dropdown .select2-results__options .select2-results__option--highlighted,
        body .select2-container--bootstrap-5 .select2-dropdown .select2-results__options .select2-results__option--highlighted *,
        body .select2-container .select2-results__option--highlighted,
        body .select2-container .select2-results__option--highlighted *,
        body .select2-results__option--highlighted,
        body .select2-results__option--highlighted *,
        .select2-results__option--highlighted,
        .select2-results__option--highlighted * {
            color: #ffffff !important;
            background-color: var(--bs-primary) !important;
        }

        /* Specific label width override for Overtime Policy Configuration modal to fit text on a single line */
        #overtimeSettingsModal .odoo-form-label {
            width: 180px !important;
        }

        /* Action Status Dropdown Styling */
        .btn-status-dropdown {
            background-color: #7c6f6c !important;
            color: #ffffff !important;
            font-size: 13px !important;
            font-weight: 600 !important;
            height: 36px !important;
            border-radius: 8px !important;
            width: 120px !important;
            border: none !important;
            padding: 0 12px !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: space-between !important;
        }
        .btn-status-dropdown:hover,
        .btn-status-dropdown:focus,
        .btn-status-dropdown:active {
            background-color: #6a5e5a !important;
            color: #ffffff !important;
        }
        .btn-status-dropdown::after {
            display: inline-block;
            margin-left: 8px;
            vertical-align: 0.255em;
            content: "";
            border-top: 0.3em solid;
            border-right: 0.3em solid transparent;
            border-bottom: 0;
            border-left: 0.3em solid transparent;
            color: #ffffff !important;
        }

        .status-dropdown-menu {
            min-width: 120px !important;
            width: 120px !important;
            border-radius: 8px !important;
            border: none !important;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1) !important;
            padding: 6px !important;
            background: #ffffff !important;
        }
        .status-dropdown-menu .dropdown-item {
            text-align: center !important;
            font-size: 13px !important;
            font-weight: 500 !important;
            padding: 8px 12px !important;
            border-radius: 6px !important;
            color: #1e293b !important;
            background: transparent !important;
            transition: all 0.2s ease;
        }
        .status-dropdown-menu .dropdown-item:hover {
            background-color: #f8fafc !important;
            color: #1e293b !important;
        }
        .status-dropdown-menu .dropdown-item.active-status {
            background-color: #f1f5f9 !important;
            color: #1e293b !important;
            font-weight: 700 !important;
        }

        /* ERP Pagination styles matching WFH/Leaves theme */
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
    </style>
@endpush

@section('content')
    {{-- Alerts --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
            <i class="feather-check-circle me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
            <i class="feather-alert-circle me-2"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

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

    <div class="row pt-4 px-4">
        {{-- Tabs Header (matching Leave Module styling) --}}
        <div class="col-12 mb-2">
            <ul class="nav gap-2 border-bottom pb-2" id="shiftOvertimeTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link @if($activeTab === 'shift') active @endif" id="tab-shift" data-bs-toggle="tab" data-bs-target="#shift-pane" type="button" role="tab" aria-controls="shift-pane" aria-selected="@if($activeTab === 'shift') true @else false @endif">
                        <i class="feather-git-pull-request me-1"></i> {{ __('hrms.shift_change.title') }}
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link @if($activeTab === 'overtime') active @endif" id="tab-overtime" data-bs-toggle="tab" data-bs-target="#overtime-pane" type="button" role="tab" aria-controls="overtime-pane" aria-selected="@if($activeTab === 'overtime') true @else false @endif">
                        <i class="feather-clock me-1"></i> {{ __('hrms.overtime.title') }}
                    </button>
                </li>
            </ul>
        </div>

        {{-- Tab Content --}}
        <div class="col-12">
            <div class="tab-content" id="shiftOvertimeTabContent">
                
                {{-- 1. SHIFT CHANGE TAB PANE --}}
                <div class="tab-pane fade @if($activeTab === 'shift') show active @endif" id="shift-pane" role="tabpanel" aria-labelledby="shift-pane-tab">
                    {{-- Shift Change Requests Table --}}
                    <div class="card border-0 shadow-sm rounded-3">
                        <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center flex-wrap gap-3">
                            <div>
                                <h5 class="fw-bold text-dark mb-0"><i class="feather-git-pull-request me-2 text-primary"></i> {{ __('hrms.shift_change.title') }}</h5>
                                <p class="text-muted fs-12 mb-0">{{ __('hrms.shift_change.title') }}</p>
                            </div>
                    
                    <div class="d-flex align-items-center gap-2">
                        <form method="GET" action="javascript:void(0);" id="shiftFilterForm" class="d-flex align-items-center gap-2 m-0 flex-wrap">
                            <!-- Search Input -->
                            <div class="d-flex align-items-center border rounded px-3 py-1" style="background-color: #f1f5f9; min-width: 220px; height: 38px;">
                                <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                                <input type="text" name="search" id="shift_search" class="form-control border-0 bg-transparent p-0 fs-13" placeholder="{{ __('hrms.shift_change.search_employee') }}" style="box-shadow: none; height: 32px;">
                            </div>

                            <!-- Sort Dropdown -->
                            <x-ui.sort-dropdown label="{{ __('hrms.assets.filters') }}">
                                <a class="dropdown-item py-2 d-flex align-items-center active" href="#" onclick="setShiftSort('newest', this); event.preventDefault();">
                                    <span>{{ __('hrms.assets.sort_newest') }}</span>
                                </a>
                                <a class="dropdown-item py-2 d-flex align-items-center" href="#" onclick="setShiftSort('oldest', this); event.preventDefault();">
                                    <span>{{ __('hrms.assets.sort_oldest') }}</span>
                                </a>
                            </x-ui.sort-dropdown>

                            <!-- Filter Dropdown -->
                            <x-ui.filter label="{{ __('hrms.assets.filters') }}" offset="0, 5">
                                <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('hrms.assets.filters') }}</h6>
                                
                                @if($isAdmin)
                                    <div class="mb-3" style="min-width: 250px;">
                                        <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.shift_change.employee') }}</label>
                                        <x-ui.odoo-form-ui type="select" name="employee_id" id="filter_shift_employee_id">
                                            <option value="">{{ __('hrms.common.all_employees') }}</option>
                                            @foreach(($employees ?? []) as $emp)
                                                <option value="{{ $emp->id }}">{{ $emp->full_name }} ({{ $emp->employee_id }})</option>
                                            @endforeach
                                        </x-ui.odoo-form-ui>
                                    </div>
                                @endif

                                <div class="mb-3" style="min-width: 250px;">
                                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.shift_change.status') }}</label>
                                    <x-ui.odoo-form-ui type="select" name="status" id="filter_shift_status">
                                        <option value="">{{ __('hrms.shift_change.all_statuses') }}</option>
                                        <option value="pending">{{ __('hrms.shift_change.pending') }}</option>
                                        <option value="approved">{{ __('hrms.shift_change.approved') }}</option>
                                        <option value="rejected">{{ __('hrms.shift_change.rejected') }}</option>
                                    </x-ui.odoo-form-ui>
                                </div>

                                <div class="dropdown-divider my-3"></div>
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary btn-sm flex-grow-1">Apply</button>
                                    <button type="button" class="btn btn-light btn-sm border flex-grow-1" onclick="resetShiftFilters()">Reset</button>
                                </div>
                            </x-ui.filter>
                        </form>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="overflow: visible;">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>{{ __('hrms.shift_change.employee') }}</th>
                                    <th>{{ __('hrms.shift_change.type') }}</th>
                                    <th>{{ __('hrms.shift_change.effective_period') }}</th>
                                    <th>{{ __('hrms.shift_change.current_shift') }}</th>
                                    <th>{{ __('hrms.shift_change.requested_shift') }}</th>
                                    <th>{{ __('hrms.shift_change.status') }}</th>
                                    <th class="text-end">{{ __('hrms.shift_change.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody id="shiftTableBody">
                                @forelse($shiftRequests as $req)
                                    <tr class="shift-row" data-employee="{{ strtolower($req->employee->full_name) }}" data-employee-id="{{ $req->employee_id }}" data-status="{{ $req->status }}" data-created-at="{{ $req->created_at->timestamp }}">
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="avatar bg-soft-primary text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-weight: bold; background-color: rgba(13, 110, 253, 0.1);">
                                                    {{ substr($req->employee->full_name, 0, 2) }}
                                                </div>
                                                <div>
                                                    <div class="fw-semibold text-dark">{{ $req->employee->full_name }}</div>
                                                    <div class="text-muted fs-11">{{ $req->employee->employee_id }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge text-uppercase fs-10" style="background-color: {{ $req->type === 'permanent' ? 'rgba(25, 135, 84, 0.1)' : ($req->type === 'recurring' ? 'rgba(13, 110, 253, 0.1)' : 'rgba(108, 117, 125, 0.1)') }}; color: {{ $req->type === 'permanent' ? '#198754' : ($req->type === 'recurring' ? '#0d6efd' : '#6c757d') }};">
                                                {{ $req->type }}
                                            </span>
                                            @if($req->type === 'recurring' && is_array($req->recurring_days))
                                                @php
                                                    $dayNames = [0 => 'Sun', 1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat'];
                                                    $mapped = array_map(fn($d) => $dayNames[$d] ?? '', $req->recurring_days);
                                                @endphp
                                                <div class="fs-10 text-muted mt-1 fw-medium" style="max-width: 120px; line-height: 1.2;">{{ implode(', ', $mapped) }}</div>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="fw-medium text-dark">{{ $req->start_date->format('d M Y') }}</div>
                                            @if($req->type === 'temporary' && $req->end_date)
                                                <div class="text-muted fs-11">to {{ $req->end_date->format('d M Y') }}</div>
                                            @endif
                                        </td>
                                        <td>
                                            @if($req->currentShift)
                                                <div class="fw-medium text-dark">{{ $req->currentShift->name }}</div>
                                                <div class="text-muted fs-10">{{ substr($req->currentShift->start_time, 0, 5) }} - {{ substr($req->currentShift->end_time, 0, 5) }}</div>
                                            @else
                                                <span class="badge bg-soft-secondary text-secondary">Day Off</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($req->requestedShift)
                                                <div class="fw-medium text-dark">{{ $req->requestedShift->name }}</div>
                                                <div class="text-muted fs-10">{{ substr($req->requestedShift->start_time, 0, 5) }} - {{ substr($req->requestedShift->end_time, 0, 5) }}</div>
                                            @else
                                                <span class="badge bg-soft-secondary text-secondary">Day Off</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge text-uppercase fs-10" style="background-color: {{ $req->status === 'approved' ? 'rgba(25, 135, 84, 0.1)' : ($req->status === 'rejected' ? 'rgba(220, 53, 69, 0.1)' : 'rgba(255, 193, 7, 0.1)') }}; color: {{ $req->status === 'approved' ? '#198754' : ($req->status === 'rejected' ? '#dc3545' : '#ffc107') }};">
                                                {{ $req->status === 'approved' ? __('hrms.shift_change.approved') : ($req->status === 'rejected' ? __('hrms.shift_change.rejected') : __('hrms.shift_change.pending')) }}
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <div class="d-flex align-items-center justify-content-end gap-2">
                                                @if($isAdmin)
                                                    <div class="dropdown {{ ($loop->last || ($loop->count > 1 && $loop->iteration >= $loop->count - 1)) ? 'dropup' : '' }} d-inline-block position-relative">
                                                        <button class="btn btn-sm dropdown-toggle py-1 px-3 d-inline-flex align-items-center justify-content-between text-capitalize fw-semibold shadow-sm btn-status-dropdown text-white" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false">
                                                            <span>{{ $req->status === 'approved' ? __('hrms.shift_change.approved') : ($req->status === 'rejected' ? __('hrms.shift_change.rejected') : __('hrms.shift_change.pending')) }}</span>
                                                        </button>
                                                        <ul class="dropdown-menu dropdown-menu-end status-dropdown-menu">
                                                            <li>
                                                                <button type="button" class="dropdown-item {{ $req->status === 'approved' ? 'active-status' : '' }}" onclick="handleShiftDecision('approve', {{ $req->id }})">
                                                                    {{ __('hrms.shift_change.approved') }}
                                                                </button>
                                                            </li>
                                                            <li>
                                                                <button type="button" class="dropdown-item {{ $req->status === 'rejected' ? 'active-status' : '' }}" onclick="handleShiftDecision('reject', {{ $req->id }})">
                                                                    {{ __('hrms.shift_change.rejected') }}
                                                                </button>
                                                            </li>
                                                        </ul>
                                                    </div>
                                                @endif

                                                <form action="{{ route('hrms.shift-change.destroy', $req->id) }}" method="POST" onsubmit="return confirmFormSubmit(event, 'Are you sure you want to delete this shift change request?', { title: 'Delete Shift Change Request', variant: 'danger', confirmButtonText: 'Delete' });" class="d-inline m-0">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-soft-danger border" 
                                                            title="{{ $req->status === 'approved' ? 'Approved requests cannot be deleted' : 'Delete Request' }}"
                                                            style="border-radius: 8px; width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; padding: 0; @if($req->status === 'approved') opacity: 0.5; cursor: not-allowed; @endif"
                                                            {{ $req->status === 'approved' ? 'disabled' : '' }}>
                                                        <i class="feather-trash-2 fs-14"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr id="empty_initial_shift_row">
                                        <td colspan="7" class="text-center py-4 text-muted fs-13">No shift change requests found.</td>
                                    </tr>
                                @endforelse
                                <tr id="no_matching_shift_row" class="d-none">
                                    <td colspan="7" class="text-center py-5 text-muted">
                                        <i class="feather-folder fs-3 d-block mb-3 text-secondary"></i>
                                        No matching shift change applications found.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="erp-pagination-container" id="shift_pagination_container">
                        <ul class="erp-pagination mb-2" id="shift_pagination_ul">
                            <!-- Dynamically generated pagination links -->
                        </ul>
                        <div class="erp-pagination-info">
                            Showing <span id="shift_showing_start">0</span> to <span id="shift_showing_end">0</span> of <span id="shift_total_count">0</span> entries
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- 2. OVERTIME TAB PANE --}}
        <div class="tab-pane fade @if($activeTab === 'overtime') show active @endif" id="overtime-pane" role="tabpanel" aria-labelledby="overtime-pane-tab">
            {{-- Policy Overview Info Block (Admins) --}}
            @if($isAdmin)
                <div class="card border-0 shadow-sm mb-4 bg-light">
                    <div class="card-body py-3 px-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
                        <div class="d-flex align-items-center gap-2">
                            <i class="feather-info text-primary fs-4"></i>
                            <div>
                                <h6 class="fw-bold mb-0 text-dark">{{ __('hrms.overtime.title') }}</h6>
                                <p class="text-muted fs-11 mb-0">Threshold: <strong>{{ number_format($tenantSettings['auto_overtime_threshold_hours'] ?? 0.0, 1) }} hours</strong> (auto-approved on punch)</p>
                            </div>
                        </div>
                        <button type="button" class="btn btn-primary btn-sm rounded text-uppercase fw-bold" data-bs-toggle="modal" data-bs-target="#overtimeSettingsModal">
                            {{ __('hrms.overtime.modify_policies') }}
                        </button>
                    </div>
                </div>
            @endif

            {{-- Overtime Table --}}
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        <h5 class="fw-bold text-dark mb-0"><i class="feather-clock me-2 text-primary"></i> {{ __('hrms.overtime.title') }}</h5>
                        <p class="text-muted fs-12 mb-0">{{ __('hrms.overtime.title') }}</p>
                    </div>
                    
                    <div class="d-flex align-items-center gap-2">
                        <form method="GET" action="javascript:void(0);" id="overtimeFilterForm" class="d-flex align-items-center gap-2 m-0 flex-wrap">
                            <!-- Search Input -->
                            <div class="d-flex align-items-center border rounded px-3 py-1" style="background-color: #f1f5f9; min-width: 220px; height: 38px;">
                                <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                                <input type="text" name="search" id="overtime_search" class="form-control border-0 bg-transparent p-0 fs-13" placeholder="{{ __('hrms.overtime.search_employee') }}" style="box-shadow: none; height: 32px;">
                            </div>

                            <!-- Sort Dropdown -->
                            <x-ui.sort-dropdown label="{{ __('hrms.assets.filters') }}">
                                <a class="dropdown-item py-2 d-flex align-items-center active" href="#" onclick="setOvertimeSort('newest', this); event.preventDefault();">
                                    <span>{{ __('hrms.assets.sort_newest') }}</span>
                                </a>
                                <a class="dropdown-item py-2 d-flex align-items-center" href="#" onclick="setOvertimeSort('oldest', this); event.preventDefault();">
                                    <span>{{ __('hrms.assets.sort_oldest') }}</span>
                                </a>
                            </x-ui.sort-dropdown>

                            <!-- Filter Dropdown -->
                            <x-ui.filter label="{{ __('hrms.assets.filters') }}" offset="0, 5">
                                <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('hrms.assets.filters') }}</h6>
                                
                                @if($isAdmin)
                                    <div class="mb-3" style="min-width: 250px;">
                                        <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.overtime.employee') }}</label>
                                        <x-ui.odoo-form-ui type="select" name="employee_id" id="filter_overtime_employee_id">
                                            <option value="">{{ __('hrms.common.all_employees') }}</option>
                                            @foreach(($employees ?? []) as $emp)
                                                <option value="{{ $emp->id }}">{{ $emp->full_name }} ({{ $emp->employee_id }})</option>
                                            @endforeach
                                        </x-ui.odoo-form-ui>
                                    </div>
                                @endif

                                <div class="mb-3" style="min-width: 250px;">
                                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.overtime.status') }}</label>
                                    <x-ui.odoo-form-ui type="select" name="status" id="filter_overtime_status">
                                        <option value="">{{ __('hrms.overtime.all_statuses') }}</option>
                                        <option value="pending">{{ __('hrms.overtime.pending') }}</option>
                                        <option value="approved">{{ __('hrms.overtime.approved') }}</option>
                                        <option value="rejected">{{ __('hrms.overtime.rejected') }}</option>
                                    </x-ui.odoo-form-ui>
                                </div>

                                <div class="dropdown-divider my-3"></div>
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary btn-sm flex-grow-1">Apply</button>
                                    <button type="button" class="btn btn-light btn-sm border flex-grow-1" onclick="resetOvertimeFilters()">Reset</button>
                                </div>
                            </x-ui.filter>
                        </form>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="overflow: visible;">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>{{ __('hrms.overtime.employee') }}</th>
                                    <th>{{ __('hrms.overtime.date') }}</th>
                                    <th>{{ __('hrms.overtime.time_frame') }}</th>
                                    <th>{{ __('hrms.overtime.requested_hours') }}</th>
                                    <th>{{ __('hrms.overtime.approved_hours') }}</th>
                                    <th>{{ __('hrms.overtime.comp_type') }}</th>
                                    <th>{{ __('hrms.overtime.status') }}</th>
                                    <th class="text-end">{{ __('hrms.overtime.actions') }}</th>
                                </tr>
                            </thead>
                            <tbody id="overtimeTableBody">
                                @forelse($overtimeRequests as $req)
                                    <tr class="overtime-row" data-employee="{{ strtolower($req->employee->full_name) }}" data-employee-id="{{ $req->employee_id }}" data-status="{{ $req->status }}" data-created-at="{{ $req->created_at->timestamp }}" data-duration="{{ $req->duration_hours }}">
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="avatar bg-soft-primary text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-weight: bold; background-color: rgba(13, 110, 253, 0.1);">
                                                    {{ substr($req->employee->full_name, 0, 2) }}
                                                </div>
                                                <div>
                                                    <div class="fw-semibold text-dark">{{ $req->employee->full_name }}</div>
                                                    <div class="text-muted fs-11">{{ $req->employee->employee_id }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="fw-medium text-dark">{{ $req->date->format('d M Y') }}</div>
                                        </td>
                                        <td>
                                            <div class="fw-semibold text-dark">{{ substr($req->start_time, 0, 5) }} - {{ substr($req->end_time, 0, 5) }}</div>
                                        </td>
                                        <td>
                                            <div class="fw-bold text-dark">{{ number_format($req->duration_hours, 1) }} hrs</div>
                                        </td>
                                        <td>
                                            @if($req->status === 'approved' && $req->approved_duration_hours !== null)
                                                <div class="fw-bold text-success">{{ number_format($req->approved_duration_hours, 1) }} hrs</div>
                                            @else
                                                <span class="text-muted fs-11">-</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge text-uppercase fs-10" style="background-color: {{ $req->compensation_type === 'comp_off' ? 'rgba(13, 110, 253, 0.1)' : 'rgba(25, 135, 84, 0.1)' }}; color: {{ $req->compensation_type === 'comp_off' ? '#0d6efd' : '#198754' }};">
                                                {{ $req->compensation_type === 'comp_off' ? __('hrms.overtime.comp_off') : __('hrms.overtime.payout') }}
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge text-uppercase fs-10" style="background-color: {{ $req->status === 'approved' ? 'rgba(25, 135, 84, 0.1)' : ($req->status === 'rejected' ? 'rgba(220, 53, 69, 0.1)' : 'rgba(255, 193, 7, 0.1)') }}; color: {{ $req->status === 'approved' ? '#198754' : ($req->status === 'rejected' ? '#dc3545' : '#ffc107') }};">
                                                {{ $req->status === 'approved' ? __('hrms.overtime.approved') : ($req->status === 'rejected' ? __('hrms.overtime.rejected') : __('hrms.overtime.pending')) }}
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <div class="d-flex align-items-center justify-content-end gap-2">
                                                @if($isAdmin)
                                                    <div class="dropdown {{ ($loop->last || ($loop->count > 1 && $loop->iteration >= $loop->count - 1)) ? 'dropup' : '' }} d-inline-block position-relative">
                                                        <button class="btn btn-sm dropdown-toggle py-1 px-3 d-inline-flex align-items-center justify-content-between text-capitalize fw-semibold shadow-sm btn-status-dropdown text-white" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false">
                                                            <span>{{ $req->status === 'approved' ? __('hrms.overtime.approved') : ($req->status === 'rejected' ? __('hrms.overtime.rejected') : __('hrms.overtime.pending')) }}</span>
                                                        </button>
                                                        <ul class="dropdown-menu dropdown-menu-end status-dropdown-menu">
                                                            <li>
                                                                <button type="button" class="dropdown-item {{ $req->status === 'approved' ? 'active-status' : '' }}" onclick="handleOvertimeDecision('approve', {{ $req->id }}, {{ $req->duration_hours }})">
                                                                    {{ __('hrms.overtime.approved') }}
                                                                </button>
                                                            </li>
                                                            <li>
                                                                <button type="button" class="dropdown-item {{ $req->status === 'rejected' ? 'active-status' : '' }}" onclick="handleOvertimeDecision('reject', {{ $req->id }}, {{ $req->duration_hours }})">
                                                                    {{ __('hrms.overtime.rejected') }}
                                                                </button>
                                                            </li>
                                                            <li>
                                                                <button type="button" class="dropdown-item {{ $req->status === 'pending' ? 'active-status' : '' }}" onclick="handleOvertimeDecision('pending', {{ $req->id }}, {{ $req->duration_hours }})">
                                                                    {{ __('hrms.overtime.pending') }}
                                                                </button>
                                                            </li>
                                                        </ul>
                                                    </div>
                                                @endif

                                                <form action="{{ route('hrms.overtime.destroy', $req->id) }}" method="POST" onsubmit="return confirmFormSubmit(event, 'Are you sure you want to delete this overtime request?', { title: 'Delete Overtime Request', variant: 'danger', confirmButtonText: 'Delete' });" class="d-inline m-0">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-soft-danger border" 
                                                            title="{{ $req->status === 'approved' ? 'Approved requests cannot be deleted' : 'Delete Request' }}"
                                                            style="border-radius: 8px; width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; padding: 0; @if($req->status === 'approved') opacity: 0.5; cursor: not-allowed; @endif"
                                                            {{ $req->status === 'approved' ? 'disabled' : '' }}>
                                                        <i class="feather-trash-2 fs-14"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr id="empty_initial_overtime_row">
                                        <td colspan="8" class="text-center py-4 text-muted fs-13">No overtime requests found.</td>
                                    </tr>
                                @endforelse
                                <tr id="no_matching_overtime_row" class="d-none">
                                    <td colspan="8" class="text-center py-5 text-muted">
                                        <i class="feather-folder fs-3 d-block mb-3 text-secondary"></i>
                                        No matching overtime requests found.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="erp-pagination-container" id="overtime_pagination_container">
                        <ul class="erp-pagination mb-2" id="overtime_pagination_ul">
                            <!-- Dynamically generated pagination links -->
                        </ul>
                        <div class="erp-pagination-info">
                            Showing <span id="overtime_showing_start">0</span> to <span id="overtime_showing_end">0</span> of <span id="overtime_total_count">0</span> entries
                        </div>
                    </div>
                </div>
            </div>
        </div>

        </div>

        </div>

    </div>

    {{-- ================================================================= --}}
    {{-- MODALS SECTION - RENDERED AT ROOT OF CONTENT TO PREVENT BLUR ISSUE --}}
    {{-- ================================================================= --}}

    {{-- Apply Shift Change Modal --}}
    <div class="modal fade" id="applyShiftChangeModal" tabindex="-1" aria-labelledby="applyShiftChangeModalLabel" aria-hidden="true" data-bs-backdrop="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <form action="{{ route('hrms.shift-change.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-header border-bottom py-3">
                        <h5 class="modal-title fw-bold" id="applyShiftChangeModalLabel">{{ __('hrms.shift_change.apply_for_shift_change') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        @if($isAdmin)
                            <div class="mb-3">
                                <x-ui.odoo-form-ui type="select" label="{{ __('hrms.shift_change.employee') }}" name="employee_id" id="shift_employee_id" :required="true">
                                    <option value="">{{ __('hrms.shift_change.select_employee') }}</option>
                                    @foreach($employees as $emp)
                                        <option value="{{ $emp->id }}" data-shift-id="{{ $emp->shift_id }}">{{ $emp->full_name }} ({{ $emp->employee_id }})</option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </div>
                        @endif

                        <div class="mb-3">
                            <x-ui.odoo-form-ui type="select" label="{{ __('hrms.shift_change.change_type') }}" name="type" id="shift_change_type" :required="true">
                                <option value="temporary">{{ __('hrms.shift_change.one_time') }}</option>
                                <option value="permanent">{{ __('hrms.shift_change.recurring') }}</option>
                                <option value="recurring">{{ __('hrms.shift_change.recurring') }} Weekdays</option>
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="mb-3">
                            <x-ui.odoo-form-ui type="input" inputType="date" label="{{ __('hrms.shift_change.start_date') }}" name="start_date" id="shift_start_date" :required="true" class="odoo-underline-input" value="{{ date('Y-m-d') }}" />
                        </div>

                        <div class="mb-3" id="end_date_container">
                            <x-ui.odoo-form-ui type="input" inputType="date" label="{{ __('hrms.shift_change.end_date') }}" name="end_date" id="shift_end_date" :required="false" class="odoo-underline-input" value="{{ date('Y-m-d') }}" />
                        </div>

                        <div class="mb-3 d-none" id="recurring_days_container">
                            <label class="form-label fw-bold d-block text-dark fs-12 mb-2">{{ __('hrms.shift_change.select_recurring_days') }}</label>
                            <div class="d-flex flex-wrap gap-2">
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" name="recurring_days[]" value="1" id="day_mon">
                                    <label class="form-check-label fs-12 text-muted" for="day_mon">Mon</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" name="recurring_days[]" value="2" id="day_tue">
                                    <label class="form-check-label fs-12 text-muted" for="day_tue">Tue</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" name="recurring_days[]" value="3" id="day_wed">
                                    <label class="form-check-label fs-12 text-muted" for="day_wed">Wed</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" name="recurring_days[]" value="4" id="day_thu">
                                    <label class="form-check-label fs-12 text-muted" for="day_thu">Thu</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" name="recurring_days[]" value="5" id="day_fri">
                                    <label class="form-check-label fs-12 text-muted" for="day_fri">Fri</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" name="recurring_days[]" value="6" id="day_sat">
                                    <label class="form-check-label fs-12 text-muted" for="day_sat">Sat</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="checkbox" name="recurring_days[]" value="0" id="day_sun">
                                    <label class="form-check-label fs-12 text-muted" for="day_sun">Sun</label>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <x-ui.odoo-form-ui type="select" label="{{ __('hrms.shift_change.requested_shift') }}" name="requested_shift_id" id="shift_requested_shift_id" :required="false">
                                <option value="">{{ __('hrms.shift_change.select_requested_shift') }} ({{ __('hrms.shift_change.one_time') }} Off)</option>
                                @foreach($shifts as $sf)
                                    @if(!$isAdmin && isset($employee) && (int)$sf->id === (int)$employee->shift_id)
                                        @continue
                                    @endif
                                    <option value="{{ $sf->id }}">{{ $sf->name }} ({{ substr($sf->start_time, 0, 5) }} - {{ substr($sf->end_time, 0, 5) }})</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="mb-3">
                            <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.shift_change.reason_comments') }}" name="reason" id="shift_reason" :required="true" class="odoo-underline-input" placeholder="{{ __('hrms.shift_change.reason_placeholder') }}" />
                        </div>

                        <div class="mb-3">
                            <x-ui.odoo-form-ui type="file" label="{{ __('hrms.shift_change.attachment_optional') }}" name="attachment" id="shift_attachment" :required="false" helperText="{{ __('hrms.shift_change.attachment_help') }}" />
                        </div>
                    </div>
                    <div class="modal-footer border-top py-3">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('hrms.shift_change.close') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('hrms.shift_change.submit_application') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Apply Overtime Modal --}}
    <div class="modal fade" id="applyOvertimeModal" tabindex="-1" aria-labelledby="applyOvertimeModalLabel" aria-hidden="true" data-bs-backdrop="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <form action="{{ route('hrms.overtime.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-header border-bottom py-3">
                        <h5 class="modal-title fw-bold" id="applyOvertimeModalLabel">{{ __('hrms.overtime.apply_for_overtime') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        @if($isAdmin)
                            <div class="mb-3">
                                <x-ui.odoo-form-ui type="select" label="{{ __('hrms.overtime.employee') }}" name="employee_id" id="ot_employee_id" :required="true">
                                    <option value="">{{ __('hrms.overtime.select_employee') }}</option>
                                    @foreach($employees as $emp)
                                        <option value="{{ $emp->id }}">{{ $emp->full_name }} ({{ $emp->employee_id }})</option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </div>
                        @endif

                        <div class="mb-3">
                            <x-ui.odoo-form-ui type="input" inputType="date" label="{{ __('hrms.overtime.overtime_date') }}" name="date" id="ot_date" :required="true" class="odoo-underline-input" value="{{ date('Y-m-d') }}" />
                        </div>

                        <div class="mb-3">
                            <x-ui.odoo-form-ui type="input" inputType="time" label="Start Time" name="start_time" id="ot_start_time" :required="true" class="odoo-underline-input" value="18:00" />
                        </div>

                        <div class="mb-3">
                            <x-ui.odoo-form-ui type="input" inputType="time" label="End Time" name="end_time" id="ot_end_time" :required="true" class="odoo-underline-input" value="20:00" />
                        </div>

                        <div class="mb-3">
                            <x-ui.odoo-form-ui type="select" label="{{ __('hrms.overtime.compensation') }}" name="compensation_type" id="ot_compensation_type" :required="true">
                                <option value="payout">{{ __('hrms.overtime.payout_desc') }}</option>
                                <option value="comp_off">{{ __('hrms.overtime.comp_off_desc') }}</option>
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="mb-3">
                            <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.overtime.reason_comments') }}" name="reason" id="ot_reason" :required="true" class="odoo-underline-input" placeholder="{{ __('hrms.overtime.reason_placeholder') }}" />
                        </div>

                        <div class="mb-3">
                            <x-ui.odoo-form-ui type="file" label="{{ __('hrms.overtime.attachment_optional') }}" name="attachment" id="ot_attachment" :required="false" helperText="{{ __('hrms.overtime.attachment_help') }}" />
                        </div>
                    </div>
                    <div class="modal-footer border-top py-3">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('hrms.overtime.close') }}</button>
                        <button type="submit" class="btn btn-primary">{{ __('hrms.overtime.submit_application') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>



    {{-- Overtime Settings Modal --}}
    @if($isAdmin)
        <div class="modal fade" id="overtimeSettingsModal" tabindex="-1" aria-labelledby="overtimeSettingsModalLabel" aria-hidden="true" data-bs-backdrop="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow">
                    <form action="{{ route('hrms.overtime.update-settings') }}" method="POST">
                        @csrf
                        <div class="modal-header border-bottom py-3">
                            <h5 class="modal-title fw-bold" id="overtimeSettingsModalLabel">{{ __('hrms.overtime.policy_config') }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body p-4">
                            <div class="mb-3">
                                <x-ui.odoo-form-ui type="input" inputType="number" label="{{ __('hrms.overtime.threshold_hours') }}" name="auto_overtime_threshold_hours" id="ot_policy_threshold" :required="true" class="odoo-underline-input" step="0.5" min="0" value="{{ $tenantSettings['auto_overtime_threshold_hours'] ?? 0.0 }}" />
                                <div class="form-text text-muted">{{ __('hrms.overtime.threshold_help') }}</div>
                            </div>
                            <div class="mb-3 mt-4">
                                <x-ui.odoo-form-ui type="input" inputType="number" label="{{ __('hrms.overtime.min_request_hours') }}" name="min_overtime_request_hours" id="ot_policy_min_request" :required="true" class="odoo-underline-input" step="0.5" min="0.5" value="{{ $tenantSettings['min_overtime_request_hours'] ?? 0.5 }}" />
                                <div class="form-text text-muted">{{ __('hrms.overtime.min_request_help') }}</div>
                            </div>
                        </div>
                        <div class="modal-footer border-top py-3">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('hrms.overtime.close') }}</button>
                            <button type="submit" class="btn btn-primary">{{ __('hrms.common.save_changes') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    {{-- Decision Forms --}}
    <form id="shiftDecisionForm" action="" method="POST" style="display: none;">
        @csrf
        <input type="hidden" name="action" id="shiftDecisionAction" value="">
        <input type="hidden" name="rejection_reason" id="shiftDecisionReason" value="">
    </form>

    <form id="overtimeDecisionForm" action="" method="POST" style="display: none;">
        @csrf
        <input type="hidden" name="action" id="overtimeDecisionAction" value="">
        <input type="hidden" name="rejection_reason" id="overtimeDecisionReason" value="">
        <input type="hidden" name="approved_duration_hours" id="overtimeDecisionApprovedHours" value="">
    </form>

    {{-- Overtime Approve Modal --}}
    <div class="modal fade" id="approveOvertimeTabModal" tabindex="-1" aria-labelledby="approveOvertimeTabModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="max-width: 420px;">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="approveOvertimeTabModalLabel">Approve Overtime</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Approved Hours</label>
                        <input type="number" id="overtimeTabApproveHoursInput" class="form-control" step="0.5" min="0.5" placeholder="e.g. 2.0">
                        <div class="form-text text-muted">Enter the actual hours to approve (can differ from requested hours).</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" id="confirmOvertimeTabApproveBtn">Approve</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Overtime Reject Modal --}}
    <div class="modal fade" id="rejectOvertimeTabModal" tabindex="-1" aria-labelledby="rejectOvertimeTabModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" style="max-width: 420px;">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold" id="rejectOvertimeTabModalLabel">Reject Overtime</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Rejection Reason <span class="text-muted fw-normal">(optional)</span></label>
                        <textarea id="overtimeTabRejectReasonInput" class="form-control" rows="3" placeholder="Enter reason for rejection..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmOvertimeTabRejectBtn">Reject</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        var originalShiftOptions = '';

        $(document).ready(function() {
            // Append modals to body root to prevent Bootstrap z-index/backdrop issues inside tab panes
            $('#applyShiftChangeModal').appendTo('body');
            $('#applyOvertimeModal').appendTo('body');
            $('#overtimeSettingsModal').appendTo('body');
            $('#approveOvertimeTabModal').appendTo('body');
            $('#rejectOvertimeTabModal').appendTo('body');

            // Save the original list of shift options for dynamic rebuilds
            originalShiftOptions = $('#shift_requested_shift_id').html();

            // Initialize select2 inside modals with dropdownParent to fix Bootstrap focus/typing issue
            $('#applyShiftChangeModal select.odoo-select2, #applyOvertimeModal select.odoo-select2').each(function() {
                var $select = $(this);
                if ($select.hasClass('select2-hidden-accessible')) {
                    $select.select2('destroy');
                }
                $select.select2({
                    theme: 'bootstrap-5',
                    dropdownParent: $select.closest('.modal-content'),
                    width: '100%'
                });
            });

            // Set initial button visibility depending on active tab
            syncPageActionButtons('{{ $activeTab }}');

            // Trigger employee change logic on load if pre-selected
            $('#shift_employee_id').trigger('change');

            // Trigger pagination display on load
            updateShiftPagination();
            updateOvertimePagination();
        });

        // Sync apply button state with the selected tab
        function syncPageActionButtons(activeTab) {
            if (activeTab === 'shift' || activeTab === 'shift-pane') {
                $('#btnApplyShift').removeClass('d-none');
                $('#btnApplyOvertime').addClass('d-none');
            } else {
                $('#btnApplyShift').addClass('d-none');
                $('#btnApplyOvertime').removeClass('d-none');
            }
        }

        // Toggle Shift Change options in Apply modal using jQuery (Select2 compatible)
        $(document).on('change', '#shift_change_type', function() {
            const val = $(this).val();
            const $endDateContainer = $('#end_date_container');
            const $recurringContainer = $('#recurring_days_container');

            if (val === 'temporary') {
                $endDateContainer.removeClass('d-none');
                $recurringContainer.addClass('d-none');
            } else if (val === 'permanent') {
                $endDateContainer.addClass('d-none');
                $recurringContainer.addClass('d-none');
            } else if (val === 'recurring') {
                $endDateContainer.addClass('d-none');
                $recurringContainer.removeClass('d-none');
            }
        });

        // Auto-fill end date when start date is selected (matching Leave and WFH)
        $('#shift_start_date').on('change', function() {
            var startDate = $(this).val();
            if (startDate) {
                $('#shift_end_date').val(startDate);
            }
        });

        // Dynamic requested shift filter logic to completely remove selected employee's current shift
        $('#shift_employee_id').on('change', function() {
            var currentShiftId = $(this).find('option:selected').attr('data-shift-id');
            var $reqShiftSelect = $('#shift_requested_shift_id');
            
            // Restore all original options from backup
            if (originalShiftOptions) {
                $reqShiftSelect.html(originalShiftOptions);
            }
            
            if (currentShiftId) {
                // Completely remove option matching the employee's current active shift
                $reqShiftSelect.find('option[value="' + currentShiftId + '"]').remove();
                
                // If it was selected, reset select to default empty selection
                if ($reqShiftSelect.val() === currentShiftId) {
                    $reqShiftSelect.val('');
                }
            }
            
            // Refresh select2 dropdown display state
            $reqShiftSelect.trigger('change.select2');
        });

        // Client-side Shift Change filtering, sorting and pagination
        var currentShiftPage = 1;
        var shiftItemsPerPage = 10;
        var currentShiftSort = 'newest';

        function updateShiftPagination() {
            var searchVal = $('#shift_search').val() ? $('#shift_search').val().toLowerCase().trim() : '';
            var empId = $('#filter_shift_employee_id').val();
            var status = $('#filter_shift_status').val();

            var $visibleRows = $('.shift-row').filter(function() {
                var $row = $(this);
                var rowEmp = $row.attr('data-employee') || '';
                var rowEmpId = $row.attr('data-employee-id') || '';
                var rowStatus = $row.attr('data-status') || '';

                var matchesSearch = !searchVal || rowEmp.indexOf(searchVal) !== -1;
                var matchesEmp = !empId || rowEmpId === empId;
                var matchesStatus = !status || rowStatus === status;

                return matchesSearch && matchesEmp && matchesStatus;
            });

            // Sort logic
            var rowsArray = $visibleRows.get();
            rowsArray.sort(function(a, b) {
                var keyA = parseInt($(a).attr('data-created-at') || 0);
                var keyB = parseInt($(b).attr('data-created-at') || 0);
                return currentShiftSort === 'newest' ? keyB - keyA : keyA - keyB;
            });

            var $tbody = $('#shiftTableBody');
            $.each(rowsArray, function(index, row) {
                $tbody.append(row);
            });

            var totalItems = $visibleRows.length;
            var totalPages = Math.ceil(totalItems / shiftItemsPerPage) || 1;

            if (currentShiftPage > totalPages) {
                currentShiftPage = totalPages;
            }
            if (currentShiftPage < 1) {
                currentShiftPage = 1;
            }

            var startIndex = (currentShiftPage - 1) * shiftItemsPerPage;
            var endIndex = Math.min(startIndex + shiftItemsPerPage, totalItems);

            $('.shift-row').hide();
            $visibleRows.slice(startIndex, endIndex).show();

            // Hide empty initial row if dynamic results are evaluated
            $('#empty_initial_shift_row').hide();

            if (totalPages > 1) {
                $('#shift_pagination_container').show();
            } else {
                $('#shift_pagination_container').hide();
            }

            if (totalItems === 0) {
                $('#no_matching_shift_row').removeClass('d-none');
            } else {
                $('#no_matching_shift_row').addClass('d-none');
            }

            $('#shift_showing_start').text(totalItems === 0 ? 0 : startIndex + 1);
            $('#shift_showing_end').text(endIndex);
            $('#shift_total_count').text(totalItems);

            var paginationHtml = '';
            paginationHtml += `
                <li class="page-item ${currentShiftPage === 1 ? 'disabled' : ''}">
                    <a class="page-link" href="#" data-page="${currentShiftPage - 1}" aria-label="Previous">
                        <i class="feather-chevron-left"></i>
                    </a>
                </li>
            `;
            for (var i = 1; i <= totalPages; i++) {
                paginationHtml += `
                    <li class="page-item ${currentShiftPage === i ? 'active' : ''}">
                        <a class="page-link" href="#" data-page="${i}">${i}</a>
                    </li>
                `;
            }
            paginationHtml += `
                <li class="page-item ${currentShiftPage === totalPages ? 'disabled' : ''}">
                    <a class="page-link" href="#" data-page="${currentShiftPage + 1}" aria-label="Next">
                        <i class="feather-chevron-right"></i>
                    </a>
                </li>
            `;
            $('#shift_pagination_ul').html(paginationHtml);
        }

        $(document).on('click', '#shift_pagination_ul .page-link', function(e) {
            e.preventDefault();
            var page = $(this).data('page');
            if (page && page !== currentShiftPage) {
                currentShiftPage = page;
                updateShiftPagination();
            }
        });

        $('#shift_search').on('input', function() {
            currentShiftPage = 1;
            updateShiftPagination();
        });

        function closeAllFilterDropdowns() {
            $('.erp-filter-dropdown').removeClass('show');
            $('.erp-filter-dropdown .dropdown-menu').removeClass('show');
        }

        $('#shiftFilterForm').on('submit', function(e) {
            e.preventDefault();
            currentShiftPage = 1;
            updateShiftPagination();
            closeAllFilterDropdowns();
        });

        function setShiftSort(value, element) {
            currentShiftSort = value;
            if (element) {
                var menu = element.closest('.dropdown-menu');
                if (menu) {
                    menu.querySelectorAll('.dropdown-item').forEach(function(el) {
                        el.classList.remove('active');
                    });
                }
                element.classList.add('active');
            }
            currentShiftPage = 1;
            updateShiftPagination();
        }

        function resetShiftFilters() {
            $('#shift_search').val('');
            $('#filter_shift_employee_id').val('').trigger('change');
            $('#filter_shift_status').val('').trigger('change');
            currentShiftSort = 'newest';
            $('#shiftFilterForm').find('.dropdown-menu .dropdown-item').removeClass('active').first().addClass('active');
            currentShiftPage = 1;
            updateShiftPagination();
            closeAllFilterDropdowns();
        }

        // Client-side Overtime filtering, sorting and pagination
        var currentOvertimePage = 1;
        var overtimeItemsPerPage = 10;
        var currentOvertimeSort = 'newest';

        function updateOvertimePagination() {
            var searchVal = $('#overtime_search').val() ? $('#overtime_search').val().toLowerCase().trim() : '';
            var empId = $('#filter_overtime_employee_id').val();
            var status = $('#filter_overtime_status').val();

            var $visibleRows = $('.overtime-row').filter(function() {
                var $row = $(this);
                var rowEmp = $row.attr('data-employee') || '';
                var rowEmpId = $row.attr('data-employee-id') || '';
                var rowStatus = $row.attr('data-status') || '';

                var matchesSearch = !searchVal || rowEmp.indexOf(searchVal) !== -1;
                var matchesEmp = !empId || rowEmpId === empId;
                var matchesStatus = !status || rowStatus === status;

                return matchesSearch && matchesEmp && matchesStatus;
            });

            // Sort logic
            var rowsArray = $visibleRows.get();
            rowsArray.sort(function(a, b) {
                var keyA, keyB;
                if (currentOvertimeSort === 'newest' || currentOvertimeSort === 'oldest') {
                    keyA = parseInt($(a).attr('data-created-at') || 0);
                    keyB = parseInt($(b).attr('data-created-at') || 0);
                    return currentOvertimeSort === 'newest' ? keyB - keyA : keyA - keyB;
                } else if (currentOvertimeSort === 'duration_high' || currentOvertimeSort === 'duration_low') {
                    keyA = parseFloat($(a).attr('data-duration') || 0);
                    keyB = parseFloat($(b).attr('data-duration') || 0);
                    return currentOvertimeSort === 'duration_high' ? keyB - keyA : keyA - keyB;
                }
                return 0;
            });

            var $tbody = $('#overtimeTableBody');
            $.each(rowsArray, function(index, row) {
                $tbody.append(row);
            });

            var totalItems = $visibleRows.length;
            var totalPages = Math.ceil(totalItems / overtimeItemsPerPage) || 1;

            if (currentOvertimePage > totalPages) {
                currentOvertimePage = totalPages;
            }
            if (currentOvertimePage < 1) {
                currentOvertimePage = 1;
            }

            var startIndex = (currentOvertimePage - 1) * overtimeItemsPerPage;
            var endIndex = Math.min(startIndex + overtimeItemsPerPage, totalItems);

            $('.overtime-row').hide();
            $visibleRows.slice(startIndex, endIndex).show();

            $('#empty_initial_overtime_row').hide();

            if (totalPages > 1) {
                $('#overtime_pagination_container').show();
            } else {
                $('#overtime_pagination_container').hide();
            }

            if (totalItems === 0) {
                $('#no_matching_overtime_row').removeClass('d-none');
            } else {
                $('#no_matching_overtime_row').addClass('d-none');
            }

            $('#overtime_showing_start').text(totalItems === 0 ? 0 : startIndex + 1);
            $('#overtime_showing_end').text(endIndex);
            $('#overtime_total_count').text(totalItems);

            var paginationHtml = '';
            paginationHtml += `
                <li class="page-item ${currentOvertimePage === 1 ? 'disabled' : ''}">
                    <a class="page-link" href="#" data-page="${currentOvertimePage - 1}" aria-label="Previous">
                        <i class="feather-chevron-left"></i>
                    </a>
                </li>
            `;
            for (var i = 1; i <= totalPages; i++) {
                paginationHtml += `
                    <li class="page-item ${currentOvertimePage === i ? 'active' : ''}">
                        <a class="page-link" href="#" data-page="${i}">${i}</a>
                    </li>
                `;
            }
            paginationHtml += `
                <li class="page-item ${currentOvertimePage === totalPages ? 'disabled' : ''}">
                    <a class="page-link" href="#" data-page="${currentOvertimePage + 1}" aria-label="Next">
                        <i class="feather-chevron-right"></i>
                    </a>
                </li>
            `;
            $('#overtime_pagination_ul').html(paginationHtml);
        }

        $(document).on('click', '#overtime_pagination_ul .page-link', function(e) {
            e.preventDefault();
            var page = $(this).data('page');
            if (page && page !== currentOvertimePage) {
                currentOvertimePage = page;
                updateOvertimePagination();
            }
        });

        $('#overtime_search').on('input', function() {
            currentOvertimePage = 1;
            updateOvertimePagination();
        });

        $('#overtimeFilterForm').on('submit', function(e) {
            e.preventDefault();
            currentOvertimePage = 1;
            updateOvertimePagination();
            closeAllFilterDropdowns();
        });

        function setOvertimeSort(value, element) {
            currentOvertimeSort = value;
            if (element) {
                var menu = element.closest('.dropdown-menu');
                if (menu) {
                    menu.querySelectorAll('.dropdown-item').forEach(function(el) {
                        el.classList.remove('active');
                    });
                }
                element.classList.add('active');
            }
            currentOvertimePage = 1;
            updateOvertimePagination();
        }

        function resetOvertimeFilters() {
            $('#overtime_search').val('');
            $('#filter_overtime_employee_id').val('').trigger('change');
            $('#filter_overtime_status').val('').trigger('change');
            currentOvertimeSort = 'newest';
            $('#overtimeFilterForm').find('.dropdown-menu .dropdown-item').removeClass('active').first().addClass('active');
            currentOvertimePage = 1;
            updateOvertimePagination();
            closeAllFilterDropdowns();
        }

        // Handle Shift Approval/Rejection
        function handleShiftDecision(action, requestId) {
            const form = document.getElementById('shiftDecisionForm');
            form.action = `{{ url('hrms/shift-change') }}/${requestId}/update-status`;
            document.getElementById('shiftDecisionAction').value = action === 'approve' ? 'approved' : 'rejected';

            if (action === 'reject') {
                const reason = prompt('Please enter a rejection reason:');
                if (reason === null) return;
                document.getElementById('shiftDecisionReason').value = reason;
            } else {
                document.getElementById('shiftDecisionReason').value = '';
            }

            form.submit();
        }

        // Handle Overtime Approval/Rejection
        var _pendingOvertimeDecisionId = null;

        function handleOvertimeDecision(action, requestId, requestedHours) {
            _pendingOvertimeDecisionId = requestId;
            const form = document.getElementById('overtimeDecisionForm');
            form.action = `{{ url('hrms/overtime') }}/${requestId}/update-status`;

            if (action === 'approve') {
                document.getElementById('overtimeTabApproveHoursInput').value = requestedHours;
                var modal = new bootstrap.Modal(document.getElementById('approveOvertimeTabModal'));
                modal.show();
            } else if (action === 'reject') {
                document.getElementById('overtimeTabRejectReasonInput').value = '';
                var modal = new bootstrap.Modal(document.getElementById('rejectOvertimeTabModal'));
                modal.show();
            } else if (action === 'pending') {
                document.getElementById('overtimeDecisionAction').value = 'pending';
                document.getElementById('overtimeDecisionReason').value = '';
                document.getElementById('overtimeDecisionApprovedHours').value = '';
                form.submit();
            }
        }

        document.getElementById('confirmOvertimeTabApproveBtn').addEventListener('click', function () {
            const hoursVal = parseFloat(document.getElementById('overtimeTabApproveHoursInput').value);
            if (isNaN(hoursVal) || hoursVal <= 0) {
                alert('Please enter a valid positive number for hours.');
                return;
            }
            const form = document.getElementById('overtimeDecisionForm');
            form.action = `{{ url('hrms/overtime') }}/${_pendingOvertimeDecisionId}/update-status`;
            document.getElementById('overtimeDecisionAction').value = 'approved';
            document.getElementById('overtimeDecisionApprovedHours').value = hoursVal;
            document.getElementById('overtimeDecisionReason').value = '';
            bootstrap.Modal.getInstance(document.getElementById('approveOvertimeTabModal')).hide();
            form.submit();
        });

        document.getElementById('confirmOvertimeTabRejectBtn').addEventListener('click', function () {
            const reason = document.getElementById('overtimeTabRejectReasonInput').value.trim();
            const form = document.getElementById('overtimeDecisionForm');
            form.action = `{{ url('hrms/overtime') }}/${_pendingOvertimeDecisionId}/update-status`;
            document.getElementById('overtimeDecisionAction').value = 'rejected';
            document.getElementById('overtimeDecisionReason').value = reason;
            document.getElementById('overtimeDecisionApprovedHours').value = '';
            bootstrap.Modal.getInstance(document.getElementById('rejectOvertimeTabModal')).hide();
            form.submit();
        });

        // Maintain active tab in query parameters upon tab click and toggle button views
        const tabElList = [].slice.call(document.querySelectorAll('#shiftOvertimeTabs button[data-bs-toggle="tab"]'));
        tabElList.forEach(function (tabEl) {
            tabEl.addEventListener('shown.bs.tab', function (event) {
                const targetId = event.target.getAttribute('data-bs-target');
                const tabName = targetId.replace('#', '').replace('-pane', '');
                
                const url = new URL(window.location.href);
                url.searchParams.set('tab', tabName);
                window.history.replaceState({}, '', url.toString());

                // Sync the buttons visible in the page header
                syncPageActionButtons(tabName);
            });
        });
    </script>
@endpush
