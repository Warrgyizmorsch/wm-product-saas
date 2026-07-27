@extends('layouts.duralux')

@section('title', 'WFH Applications | SaaS ERP')
@section('page-title', 'WFH Applications')
@section('breadcrumb', 'HRMS / WFH Applications')

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <button type="button" class="btn btn-primary fw-bold text-uppercase d-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#applyWfhModal" style="height: 38px;">
            <i class="feather-plus"></i> Apply WFH
        </button>
    </div>
@endsection

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

    {{-- Summary Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body d-flex align-items-center justify-content-between p-3">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Total Requests</div>
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
                        <div class="text-muted small fw-semibold text-uppercase">Pending Approval</div>
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
                        <div class="text-muted small fw-semibold text-uppercase">Approved</div>
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
                        <div class="text-muted small fw-semibold text-uppercase">Rejected</div>
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
    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white border-0 pt-4 px-4 d-flex align-items-center justify-content-between">
            <div>
                <h5 class="fw-bold text-dark mb-0"><i class="feather-home me-2 text-primary"></i> Work From Home Applications</h5>
                <p class="text-muted fs-12 mb-0">Review and manage work from home applications</p>
            </div>
            
            <div class="d-flex align-items-center gap-2">
                <form method="GET" action="{{ route('hrms.wfh.index') }}" class="d-flex align-items-center gap-2 m-0" id="wfhFilterForm">
                    <!-- Search Input -->
                    <div class="d-flex align-items-center border rounded px-3 py-1" style="background-color: #f1f5f9; min-width: 220px; max-width: 280px; height: 38px;">
                        <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                        <input type="text" name="search" id="wfh_search" class="form-control border-0 bg-transparent p-0 fs-13" placeholder="Search employee..." value="{{ request('search') }}" style="box-shadow: none; height: 32px;">
                    </div>

                    <!-- Sort Dropdown -->
                    <x-ui.sort-dropdown label="Sort">
                        <a class="dropdown-item py-2 d-flex align-items-center {{ request('sort', 'newest') == 'newest' ? 'active' : '' }}" href="#" onclick="setWfhSort('newest'); event.preventDefault();">
                            <span>Newest First</span>
                            <i class="feather-check text-dark ms-auto sort-check {{ request('sort', 'newest') == 'newest' ? '' : 'd-none' }}"></i>
                        </a>
                        <a class="dropdown-item py-2 d-flex align-items-center {{ request('sort') == 'oldest' ? 'active' : '' }}" href="#" onclick="setWfhSort('oldest'); event.preventDefault();">
                            <span>Oldest First</span>
                            <i class="feather-check text-dark ms-auto sort-check {{ request('sort') == 'oldest' ? '' : 'd-none' }}"></i>
                        </a>
                        <a class="dropdown-item py-2 d-flex align-items-center {{ request('sort') == 'duration_high' ? 'active' : '' }}" href="#" onclick="setWfhSort('duration_high'); event.preventDefault();">
                            <span>Duration: High to Low</span>
                            <i class="feather-check text-dark ms-auto sort-check {{ request('sort') == 'duration_high' ? '' : 'd-none' }}"></i>
                        </a>
                        <a class="dropdown-item py-2 d-flex align-items-center {{ request('sort') == 'duration_low' ? 'active' : '' }}" href="#" onclick="setWfhSort('duration_low'); event.preventDefault();">
                            <span>Duration: Low to High</span>
                            <i class="feather-check text-dark ms-auto sort-check {{ request('sort') == 'duration_low' ? '' : 'd-none' }}"></i>
                        </a>
                    </x-ui.sort-dropdown>

                    <!-- Filter Dropdown -->
                    <x-ui.filter label="Filter" offset="0, 5">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Filter Options</h6>
                        
                        @if($isAdmin)
                            <div class="mb-3" style="min-width: 250px;">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Employee</label>
                                <x-ui.odoo-form-ui type="select" name="employee_id" id="filter_wfh_employee_id">
                                    <option value="">All Employees</option>
                                    @foreach(($employees ?? []) as $emp)
                                        <option value="{{ $emp->id }}" {{ request('employee_id') == $emp->id ? 'selected' : '' }}>
                                            {{ $emp->full_name }} ({{ $emp->employee_id }})
                                        </option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </div>
                        @endif

                        <div class="mb-3" style="min-width: 250px;">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Status</label>
                            <x-ui.odoo-form-ui type="select" name="status" id="filter_wfh_status">
                                <option value="">All Statuses</option>
                                <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                                <option value="approved" {{ request('status') == 'approved' ? 'selected' : '' }}>Approved</option>
                                <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>Rejected</option>
                            </x-ui.odoo-form-ui>
                        </div>

                        <input type="hidden" name="sort" id="wfh_sort_input" value="{{ request('sort', 'newest') }}">

                        <div class="dropdown-divider my-3"></div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary btn-sm flex-grow-1">Apply</button>
                            <a href="{{ route('hrms.wfh.index') }}" class="btn btn-light btn-sm border flex-grow-1 text-center">Reset</a>
                        </div>
                    </x-ui.filter>
                </form>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive" style="min-height: 260px; overflow: visible;">
                <table class="table table-hover align-middle mb-0" id="wfhTable">
                    <thead class="table-light">
                        <tr>
                            @if($isAdmin)
                                <th class="fs-12 text-uppercase text-muted fw-semibold ps-3" style="min-width:160px;">Employee</th>
                            @endif
                            <th class="fs-12 text-uppercase text-muted fw-semibold {{ !$isAdmin ? 'ps-3' : '' }}" style="min-width:170px;">Timeline</th>
                            <th class="fs-12 text-uppercase text-muted fw-semibold text-center" style="width:70px;">Days</th>
                            <th class="fs-12 text-uppercase text-muted fw-semibold" style="min-width:240px;">Reason</th>
                            <th class="fs-12 text-uppercase text-muted fw-semibold text-center" style="width:65px;">File</th>
                            <th class="fs-12 text-uppercase text-muted fw-semibold text-end pe-3" style="min-width:130px;">Status</th>
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
                                    'approved' => ['cls' => 'bg-soft-success text-success', 'icon' => 'feather-check-circle', 'lbl' => 'Approved'],
                                    'pending'  => ['cls' => 'bg-soft-warning text-warning', 'icon' => 'feather-clock', 'lbl' => 'Pending'],
                                    'rejected' => ['cls' => 'bg-soft-danger text-danger',   'icon' => 'feather-x-circle', 'lbl' => 'Rejected'],
                                    default    => ['cls' => 'bg-light text-secondary',      'icon' => 'feather-circle', 'lbl' => ucfirst($req->status)],
                                };

                                $startType = $req->start_date_type ?? 'full_day';
                                $endType   = $req->end_date_type ?? 'full_day';

                                $startTypeLabel = ucwords(str_replace('_', ' ', $startType));
                                $endTypeLabel   = ucwords(str_replace('_', ' ', $endType));

                                $sessionInfo = ($startType === $endType || ($req->start_date && $req->end_date && $req->start_date->isSameDay($req->end_date)))
                                    ? $startTypeLabel
                                    : ($startTypeLabel . ' → ' . $endTypeLabel);

                                $isLongReason = (mb_strlen($req->reason ?? '') > 70) || (substr_count($req->reason ?? '', "\n") > 1);
                            @endphp
                            <tr>
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
                                <td class="text-end pe-3">
                                    @if($isAdmin)
                                        <div class="dropdown {{ ($loop->last || ($loop->count > 1 && $loop->iteration >= $loop->count - 1)) ? 'dropup' : '' }} d-inline-block position-relative">
                                            <button class="btn btn-sm {{ $statusBadge['cls'] }} rounded-pill px-3 py-1 fs-11 dropdown-toggle d-inline-flex align-items-center gap-1 border-0" type="button" data-bs-toggle="dropdown" data-bs-display="static" aria-expanded="false">
                                                <i class="{{ $statusBadge['icon'] }}"></i> {{ $statusBadge['lbl'] }}
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end shadow border-0 p-1.5 mt-1 fs-12" style="min-width: 130px; z-index: 1050; background: #ffffff;">
                                                <li>
                                                    <a class="dropdown-item rounded py-1.5 px-3 text-dark fw-medium d-flex align-items-center justify-content-between {{ $req->status === 'approved' ? 'bg-light text-primary fw-bold' : '' }}"
                                                       href="#"
                                                       onclick="submitWfhStatusDirect('{{ route('hrms.wfh.update-status', $req->id) }}', 'approved'); return false;">
                                                        <span>Approved</span>
                                                        @if($req->status === 'approved') <i class="feather-check text-primary"></i> @endif
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item rounded py-1.5 px-3 text-dark fw-medium d-flex align-items-center justify-content-between {{ $req->status === 'rejected' ? 'bg-light text-danger fw-bold' : '' }}"
                                                       href="#"
                                                       data-action="{{ route('hrms.wfh.reject', $req->id) }}"
                                                       onclick="openWfhRejectModal(this); return false;">
                                                        <span>Rejected</span>
                                                        @if($req->status === 'rejected') <i class="feather-check text-danger"></i> @endif
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item rounded py-1.5 px-3 text-dark fw-medium d-flex align-items-center justify-content-between {{ $req->status === 'pending' ? 'bg-light text-warning fw-bold' : '' }}"
                                                       href="#"
                                                       onclick="submitWfhStatusDirect('{{ route('hrms.wfh.update-status', $req->id) }}', 'pending'); return false;">
                                                        <span>Pending</span>
                                                        @if($req->status === 'pending') <i class="feather-check text-warning"></i> @endif
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                    @else
                                        <span class="badge {{ $statusBadge['cls'] }} rounded-pill px-2.5 py-1 fs-11">
                                            <i class="{{ $statusBadge['icon'] }} me-1"></i>{{ $statusBadge['lbl'] }}
                                        </span>
                                    @endif
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
                    </tbody>
                </table>
            </div>
        </div>
    </div>

{{-- Apply WFH Modal --}}
<div class="modal fade" id="applyWfhModal" tabindex="-1" aria-labelledby="applyWfhModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom py-3">
                <h5 class="modal-title fw-bold text-dark" id="applyWfhModalLabel"><i class="feather-home me-2 text-primary"></i> Apply Work From Home</h5>
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
                                <x-ui.odoo-form-ui type="select" label="Employee" name="employee_id" id="wfh_employee_select" :required="true" class="odoo-select2-custom">
                                    <option value="">-- Select Employee --</option>
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
                            <x-ui.odoo-form-ui type="input" inputType="date" label="Start Date" name="start_date" id="wfh_start_date" :required="true" value="{{ old('start_date') }}" class="odoo-underline-input" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="select" label="Start Date Session" name="start_date_type" id="wfh_start_type" :required="true" class="odoo-select2-custom">
                                <option value="full_day" {{ old('start_date_type') == 'full_day' ? 'selected' : '' }}>Full Day</option>
                                <option value="first_half" {{ old('start_date_type') == 'first_half' ? 'selected' : '' }}>First Half</option>
                                <option value="second_half" {{ old('start_date_type') == 'second_half' ? 'selected' : '' }}>Second Half</option>
                            </x-ui.odoo-form-ui>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input" inputType="date" label="End Date" name="end_date" id="wfh_end_date" :required="true" value="{{ old('end_date') }}" class="odoo-underline-input" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="select" label="End Date Session" name="end_date_type" id="wfh_end_type" :required="true" class="odoo-select2-custom">
                                <option value="full_day" {{ old('end_date_type') == 'full_day' ? 'selected' : '' }}>Full Day</option>
                                <option value="first_half" {{ old('end_date_type') == 'first_half' ? 'selected' : '' }}>First Half</option>
                                <option value="second_half" {{ old('end_date_type') == 'second_half' ? 'selected' : '' }}>Second Half</option>
                            </x-ui.odoo-form-ui>
                        </div>
                    </div>

                    <div class="col-12 mb-3">
                        <div id="wfh_calculated_duration_display" class="alert alert-info py-2 fs-12 mb-0 d-flex justify-content-between align-items-center">
                            <div>
                                <i class="feather-info me-1"></i> Estimated Duration: <strong id="wfh_duration_val">0.0</strong> Day(s)
                            </div>
                            <div class="fw-semibold text-primary" id="wfh_session_flow_val">
                                (Full Day)
                            </div>
                        </div>
                        <input type="hidden" name="duration" id="wfh_duration" value="{{ old('duration', '0.0') }}">
                    </div>

                    <div class="row mb-3">
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="textarea" label="Reason for WFH" name="reason" :required="true" class="odoo-underline-input" placeholder="Specify your reason for working from home...">{{ old('reason') }}</x-ui.odoo-form-ui>
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-12">
                            <x-ui.odoo-form-ui type="file" label="Attachment (Optional)" name="attachment" id="wfh_attachment" :required="false" helperText="Allowed formats: PDF, JPG, PNG, DOC (Max 5MB)" />
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top py-3">
                    <button type="button" class="btn btn-light border px-4 text-uppercase fw-bold" data-bs-dismiss="modal" style="font-size: 11px;">Discard</button>
                    <button type="submit" class="btn btn-primary px-4 text-uppercase fw-bold" style="font-size: 11px;">Submit Application</button>
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
                <h5 class="modal-title fw-bold text-danger"><i class="feather-x-circle me-2"></i> Reject WFH Application</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="rejectWfhForm" method="POST" action="">
                @csrf
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-secondary fs-13">Rejection Reason <span class="text-danger">*</span></label>
                        <textarea name="rejection_reason" class="form-control" rows="3" placeholder="Please provide reason for rejecting this WFH application..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top py-3">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Confirm Rejection</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
function setWfhSort(sortValue) {
    var sortInput = document.getElementById('wfh_sort_input');
    if (sortInput) {
        sortInput.value = sortValue;
    }
    var form = document.getElementById('wfhFilterForm');
    if (form) {
        form.submit();
    }
}

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

        const start = new Date(startDateInput.value);
        const end   = new Date(endDateInput.value);

        if (end < start) {
            durationInput.value = '0.0';
            const displayEl = document.getElementById('wfh_duration_val');
            if (displayEl) displayEl.textContent = '0.0';
            const flowEl = document.getElementById('wfh_session_flow_val');
            if (flowEl) flowEl.textContent = '(Invalid Date Range)';
            return;
        }

        let days = 0;
        let current = new Date(start);

        while (current <= end) {
            // Exclude Sundays
            if (current.getDay() !== 0) {
                if (current.getTime() === start.getTime()) {
                    days += (startTypeInput.value === 'full_day') ? 1.0 : 0.5;
                } else if (current.getTime() === end.getTime()) {
                    days += (endTypeInput.value === 'full_day') ? 1.0 : 0.5;
                } else {
                    days += 1.0;
                }
            }
            current.setDate(current.getDate() + 1);
        }

        const formatted = days.toFixed(1);
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
        startDateInput.addEventListener('change', function () {
            if (this.value) {
                endDateInput.value = this.value;
            }
            calculateDuration();
        });
        endDateInput.addEventListener('change', calculateDuration);
        startTypeInput.addEventListener('change', calculateDuration);
        endTypeInput.addEventListener('change', calculateDuration);
        calculateDuration();
    }
});
</script>
@endpush
@endsection
