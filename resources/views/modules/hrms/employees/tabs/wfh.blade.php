@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
@endpush
@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
@endpush
<div class="tab-pane fade {{ $activeTabName === 'wfh' ? 'show active' : '' }}" id="wfh-pane" role="tabpanel" aria-labelledby="wfh-tab">
    @php
        $wfhTotalCount = $empWfhRequests->count();
    @endphp

    <div class="card-custom">
        <!-- Header with actions -->
        <div class="card-custom-header d-flex justify-content-between align-items-center flex-wrap gap-2 py-3 px-4 border-bottom">
            <div class="d-flex align-items-center gap-2">
                <h5 class="card-custom-title mb-0" id="wfhAppsHeaderTitle">
                    <i class="feather-home text-primary me-1.5"></i> {{ __('hrms.wfh.title') }}
                </h5>
                <span class="badge bg-soft-primary text-primary rounded-pill px-2.5 py-1 fs-11 ms-1 fw-bold" id="empWfhRequestsCountBadge">
                    {{ $wfhTotalCount }} {{ $wfhTotalCount === 1 ? __('hrms.wfh.application') : __('hrms.wfh.applications') }}
                </span>
            </div>
            
            <!-- Toolbar matching leaves list toolbar -->
            <div class="d-flex align-items-center gap-2 flex-wrap ms-auto" id="wfhAppsToolbar">
                <!-- Registry Style Search Input -->
                <div class="d-flex align-items-center border rounded px-3 py-1" style="background-color: #f1f5f9; min-width: 180px; max-width: 240px; height: 38px;">
                    <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                    <input type="text" id="empWfhAppSearch" class="form-control border-0 bg-transparent p-0 fs-13" placeholder="{{ __('hrms.wfh.search_wfh_placeholder') }}" style="box-shadow: none; height: 32px;" autocomplete="off">
                </div>

                <!-- Sort Dropdown with Checkmark Icons -->
                <x-ui.sort-dropdown :label="__('hrms.common.sort')">
                    <a class="dropdown-item py-2 d-flex align-items-center emp-wfh-sort-link active" href="#" onclick="event.preventDefault();" data-sort="date_desc">
                        <span>{{ __('hrms.wfh.sort_newest') }}</span>
                        <i class="feather-check text-dark ms-auto wfh-sort-check"></i>
                    </a>
                    <a class="dropdown-item py-2 d-flex align-items-center emp-wfh-sort-link" href="#" onclick="event.preventDefault();" data-sort="date_asc">
                        <span>{{ __('hrms.wfh.sort_oldest') }}</span>
                        <i class="feather-check text-dark ms-auto wfh-sort-check d-none"></i>
                    </a>
                    <a class="dropdown-item py-2 d-flex align-items-center emp-wfh-sort-link" href="#" onclick="event.preventDefault();" data-sort="duration_desc">
                        <span>{{ __('hrms.wfh.sort_duration_desc') }}</span>
                        <i class="feather-check text-dark ms-auto wfh-sort-check d-none"></i>
                    </a>
                    <a class="dropdown-item py-2 d-flex align-items-center emp-wfh-sort-link" href="#" onclick="event.preventDefault();" data-sort="duration_asc">
                        <span>{{ __('hrms.wfh.sort_duration_asc') }}</span>
                        <i class="feather-check text-dark ms-auto wfh-sort-check d-none"></i>
                    </a>
                </x-ui.sort-dropdown>

                <!-- Filter Dropdown -->
                <x-ui.filter :label="__('hrms.common.filter')" offset="0, 5">
                    <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('hrms.wfh.filter_options') }}</h6>
                    <form id="empWfhAppFilterForm" onsubmit="return false;">
                        <div class="mb-3" style="min-width: 220px;">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.wfh.status') }}</label>
                            <x-ui.odoo-form-ui type="select" name="status" id="empWfhAppFilterStatus">
                                <option value="">{{ __('hrms.wfh.all_statuses') }}</option>
                                <option value="pending">{{ __('hrms.wfh.pending') }}</option>
                                <option value="approved">{{ __('hrms.wfh.approved') }}</option>
                                <option value="rejected">{{ __('hrms.wfh.rejected') }}</option>
                                <option value="cancellation_requested">{{ __('hrms.wfh.cancellation_requested') }}</option>
                                <option value="cancelled">{{ __('hrms.wfh.cancelled') }}</option>
                            </x-ui.odoo-form-ui>
                        </div>
                        <div class="dropdown-divider my-3"></div>
                        <div class="d-flex gap-2">
                            <x-ui.button type="button" id="btnEmpWfhAppFilterApply" variant="primary" size="sm" class="flex-grow-1">{{ __('hrms.wfh.accept') }}</x-ui.button>
                            <x-ui.button type="button" id="btnEmpWfhAppFilterReset" variant="light" size="sm" class="border flex-grow-1">{{ __('hrms.wfh.discard') }}</x-ui.button>
                        </div>
                    </form>
                </x-ui.filter>

                <!-- Apply Button -->
                <x-ui.button type="button" variant="primary" class="fw-bold text-uppercase d-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#empApplyWfhModal">
                    <i class="feather-plus"></i> {{ __('hrms.wfh.apply_wfh') }}
                </x-ui.button>

            </div>
        </div>

        <div class="card-body p-0">
            @if($empWfhRequests->isEmpty())
                <div class="p-5 text-center text-muted">
                    <i class="feather-home fs-32 d-block mb-3 text-secondary"></i>
                    <div class="fw-bold mb-1">{{ __('hrms.wfh.no_applications_yet') }}</div>
                </div>
            @else
                <div class="table-responsive" style="overflow: visible;">
                    <table class="table table-hover align-middle mb-0" id="empWfhTable" style="table-layout: fixed; width: 100%;">
                        <thead class="table-light">
                            <tr>
                                <th class="fs-12 text-uppercase text-muted fw-semibold ps-3" style="width: 18%;">{{ __('hrms.wfh.timeline') }}</th>
                                <th class="fs-12 text-uppercase text-muted fw-semibold text-center" style="width: 5%;">{{ __('hrms.wfh.days') }}</th>
                                <th class="fs-12 text-uppercase text-muted fw-semibold" style="width: 46%;">{{ __('hrms.wfh.reason') }}</th>
                                <th class="fs-12 text-uppercase text-muted fw-semibold text-center" style="width: 4%;">{{ __('hrms.wfh.file') }}</th>
                                <th class="fs-12 text-uppercase text-muted fw-semibold" style="width: 12%;">{{ __('hrms.wfh.status') }}</th>
                                <th class="fs-12 text-uppercase text-muted fw-semibold text-end pe-3" style="width: 15%;">{{ __('hrms.wfh.actions') }}</th>

                            </tr>
                        </thead>
                        <tbody>
                            @foreach($empWfhRequests as $req)
                                @php
                                    $sameYear = $req->start_date && $req->end_date && $req->start_date->format('Y') === $req->end_date->format('Y');
                                    $startStr = $req->start_date ? $req->start_date->format($sameYear ? 'd M' : 'd M Y') : '-';
                                    $endStr   = $req->end_date   ? $req->end_date->format('d M Y')   : '-';
                                    $dateRange = ($req->start_date && $req->end_date && $req->start_date->isSameDay($req->end_date))
                                        ? $req->start_date->format('d M Y')
                                        : $startStr . ' – ' . $endStr;

                                    $sessionInfo = '';
                                    if ($req->start_date_type && $req->start_date_type !== 'full_day') {
                                        $sessionInfo = __('hrms.wfh.' . $req->start_date_type);
                                        if ($req->start_date && $req->end_date && !$req->start_date->isSameDay($req->end_date) && $req->end_date_type && $req->end_date_type !== 'full_day') {
                                            $sessionInfo .= ' → ' . __('hrms.wfh.' . $req->end_date_type);
                                        }
                                    }

                                    $statusBadge = match($req->status) {
                                        'approved'                => ['cls' => 'bg-soft-success text-success',  'icon' => 'feather-check-circle', 'lbl' => __('hrms.wfh.approved')],
                                        'pending'                 => ['cls' => 'bg-soft-warning text-warning',  'icon' => 'feather-clock',         'lbl' => __('hrms.wfh.pending')],
                                        'rejected'                => ['cls' => 'bg-soft-danger text-danger',    'icon' => 'feather-x-circle',      'lbl' => __('hrms.wfh.rejected')],
                                        'cancellation_requested'  => ['cls' => 'bg-soft-info text-info',          'icon' => 'feather-rotate-ccw',     'lbl' => __('hrms.wfh.cancellation_requested')],
                                        'cancelled'               => ['cls' => 'bg-soft-secondary text-secondary','icon' => 'feather-slash',          'lbl' => __('hrms.wfh.cancelled')],
                                        default                   => ['cls' => 'bg-light text-secondary',       'icon' => 'feather-circle',        'lbl' => __('hrms.wfh.' . $req->status) ?: ucfirst($req->status)],
                                    };

                                    $isLongReason = (mb_strlen($req->reason ?? '') > 50) || (substr_count($req->reason ?? '', "\n") > 1);
                                    $isLongCancelReason = (mb_strlen($req->cancellation_reason ?? '') > 50) || (substr_count($req->cancellation_reason ?? '', "\n") > 1);
                                @endphp
                                <tr class="emp-wfh-row"
                                    style="cursor:pointer;"
                                    data-req-id="{{ $req->id }}"
                                    data-employee-name="{{ $employee->full_name }}"
                                    data-employee-code="{{ $employee->employee_id }}"
                                    data-date-range="{{ $dateRange }}"
                                    data-start="{{ $req->start_date?->format('d M Y') }}"
                                    data-end="{{ $req->end_date?->format('d M Y') }}"
                                    data-start-type="{{ str_replace('_',' ', $req->start_date_type) }}"
                                    data-end-type="{{ str_replace('_',' ', $req->end_date_type) }}"
                                    data-duration="{{ floatval($req->duration) }}"
                                    data-reason="{{ addslashes($req->reason ?? '') }}"
                                    data-status="{{ strtolower($req->status) }}"
                                    data-status-label="{{ $statusBadge['lbl'] }}"
                                    data-status-cls="{{ $statusBadge['cls'] }}"
                                    data-status-icon="{{ $statusBadge['icon'] }}"
                                    data-created-at="{{ $req->created_at?->timestamp ?: 0 }}"
                                    data-rejection="{{ addslashes($req->rejection_reason ?? '') }}"
                                    data-attachment="{{ $req->attachment_path ? asset('storage/'.$req->attachment_path) : '' }}"
                                    data-update-url="{{ route('hrms.wfh.update-status', $req->id) }}"
                                    data-approve-cancel-url="{{ route('hrms.wfh.approve-cancellation', $req->id) }}"
                                    data-deny-cancel-url="{{ route('hrms.wfh.deny-cancellation', $req->id) }}"
                                    data-cancellation="{{ addslashes($req->cancellation_reason ?? '') }}"
                                >
                                    <td class="ps-3" style="white-space: nowrap;">
                                        <span class="fw-semibold text-dark fs-13">{{ $dateRange }}</span>
                                        @if($sessionInfo)
                                            <div class="text-muted fs-11 mt-0.5">{{ $sessionInfo }}</div>
                                        @endif
                                    </td>
                                    <td class="text-center" style="white-space: nowrap;">
                                        <span class="badge bg-light text-dark fw-bold fs-12">{{ floatval($req->duration) }}</span>
                                    </td>
                                    <td style="white-space: normal; word-break: break-word; overflow-wrap: anywhere;">
                                        <div class="wfh-reason-text-profile fs-13 text-dark" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.4;">
                                            {{ $req->reason }}
                                        </div>
                                        @if($isLongReason)
                                            <a href="#" class="wfh-toggle-reason-btn-profile fs-11 text-primary fw-semibold d-inline-block mt-0.5" onclick="toggleWfhReasonProfileText(this); return false;">{{ __('hrms.common.detail') }}</a>
                                        @endif

                                        @if($req->status === 'rejected' && !empty($req->rejection_reason))
                                            <div class="text-danger fs-11 mt-1">
                                                <i class="feather-alert-circle me-1"></i>{{ __('hrms.wfh.rejection') }}: {{ $req->rejection_reason }}
                                            </div>
                                        @endif

                                        @if(in_array($req->status, ['cancellation_requested', 'cancelled']) && !empty($req->cancellation_reason))
                                            <div class="text-warning fs-11 mt-2">
                                                <span class="fw-semibold"><i class="feather-rotate-ccw me-1"></i>{{ __('hrms.wfh.cancellation') }}:</span>
                                                <div class="wfh-cancel-reason-text-profile mb-0" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; line-height: 1.4; color: inherit;">
                                                    {{ $req->cancellation_reason }}
                                                </div>
                                                @if($isLongCancelReason)
                                                    <a href="#" class="wfh-toggle-cancel-reason-btn-profile fs-10 text-primary fw-semibold d-inline-block mt-0.5" onclick="toggleWfhCancelReasonProfileText(this); return false;">{{ __('hrms.common.detail') }}</a>
                                                @endif
                                            </div>
                                        @endif
                                    </td>
                                    <td class="text-center" style="white-space: nowrap;">
                                        @if($req->attachment_path)
                                            <a href="{{ asset('storage/'.$req->attachment_path) }}" target="_blank" class="text-primary text-decoration-none" onclick="event.stopPropagation();">
                                                <i class="feather-paperclip fs-14"></i>
                                            </a>
                                        @else
                                            <span class="text-muted fs-13">—</span>
                                        @endif
                                    </td>
                                    <td style="white-space: nowrap;">
                                        <span class="badge {{ $statusBadge['cls'] }} rounded-pill px-2.5 py-1 fs-11">
                                            <i class="{{ $statusBadge['icon'] }} me-1"></i>{{ $statusBadge['lbl'] }}
                                        </span>
                                    </td>
                                    <td class="text-end pe-3" style="white-space: nowrap; min-width:180px;">
                                        <div class="d-flex align-items-center justify-content-end gap-2">
                                            @if($req->status === 'cancellation_requested')
                                                {{-- Cancellation dropdown: Accept / Deny --}}
                                                <div class="dropdown {{ ($loop->last || ($loop->count > 1 && $loop->iteration >= $loop->count - 1)) ? 'dropup' : '' }} d-inline-block position-relative" onclick="event.stopPropagation();">
                                                    <button class="btn btn-sm dropdown-toggle py-1 px-3 d-inline-flex align-items-center justify-content-between text-capitalize fw-semibold shadow-sm" 
                                                            type="button" 
                                                            data-bs-toggle="dropdown" 
                                                            data-bs-boundary="viewport"
                                                            aria-expanded="false"
                                                            style="background-color: var(--bs-primary) !important; color: #ffffff !important; font-size: 11.5px; height: 32px; border-radius: 8px; min-width: 130px; border: none;"
                                                            title="{{ __('hrms.wfh.actions') }}">
                                                        <span>{{ __('hrms.wfh.actions') }}</span>
                                                    </button>
                                                    <ul class="dropdown-menu dropdown-menu-end shadow border-0 p-1.5 mt-1 fs-12" style="border-radius: 8px; min-width: 130px; z-index: 1050; background: #ffffff;">
                                                        <li>
                                                            <form method="POST" action="{{ route('hrms.wfh.approve-cancellation', $req->id) }}" onsubmit="return confirmFormSubmit(event, '{{ __('hrms.wfh.confirm_approve_cancellation') }}', { title: 'Approve Cancellation', variant: 'success', confirmButtonText: 'Approve' })" class="m-0">
                                                                @csrf
                                                                <button type="submit" class="dropdown-item rounded py-1.5 px-3 text-dark fw-medium d-flex align-items-center justify-content-between text-success">
                                                                    <span>{{ __('hrms.wfh.accept') }}</span>
                                                                    <i class="feather-check text-success fs-12"></i>
                                                                </button>
                                                            </form>
                                                        </li>
                                                        <li>
                                                            <form method="POST" action="{{ route('hrms.wfh.deny-cancellation', $req->id) }}" onsubmit="return confirmFormSubmit(event, '{{ __('hrms.wfh.confirm_deny_cancellation') }}', { title: 'Deny Cancellation', variant: 'danger', confirmButtonText: 'Deny' })" class="m-0">
                                                                @csrf
                                                                <button type="submit" class="dropdown-item rounded py-1.5 px-3 text-dark fw-medium d-flex align-items-center justify-content-between text-danger">
                                                                    <span>{{ __('hrms.wfh.deny') }}</span>
                                                                    <i class="feather-x text-danger fs-12"></i>
                                                                </button>
                                                            </form>
                                                        </li>
                                                    </ul>
                                                </div>
                                            @elseif(!in_array($req->status, ['cancelled']))
                                                {{-- Normal status dropdown: Approved / Rejected / Pending --}}
                                                <div class="dropdown {{ ($loop->last || ($loop->count > 1 && $loop->iteration >= $loop->count - 1)) ? 'dropup' : '' }} d-inline-block position-relative" onclick="event.stopPropagation();">
                                                    <button class="btn btn-sm dropdown-toggle py-1 px-3 d-inline-flex align-items-center justify-content-between text-capitalize fw-semibold shadow-sm btn-status-dropdown text-white" 
                                                            type="button" 
                                                            data-bs-toggle="dropdown" 
                                                            data-bs-boundary="viewport"
                                                            aria-expanded="false"
                                                            style="background-color: var(--bs-primary) !important; color: #ffffff !important; font-size: 11.5px; height: 32px; border-radius: 8px; min-width: 130px; border: none;"
                                                            title="{{ __('hrms.wfh.change_status') }}">
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
                                                <form method="POST" action="{{ route('hrms.wfh.withdraw', $req->id) }}" onsubmit="return confirmFormSubmit(event, '{{ __('hrms.wfh.confirm_withdraw') }}', { title: 'Withdraw WFH Application', variant: 'warning', confirmButtonText: 'Withdraw' })" class="d-inline-flex" onclick="event.stopPropagation();">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-soft-danger border" 
                                                            title="{{ __('hrms.wfh.withdraw_application') }}"
                                                            style="border-radius: 8px; width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; padding: 0;">
                                                        <i class="feather-trash-2 fs-14"></i>
                                                    </button>
                                                </form>
                                            @elseif($req->canRequestCancellation())
                                                <div class="d-inline-flex">
                                                    <button type="button" class="btn btn-sm btn-soft-danger border" 
                                                            title="{{ __('hrms.wfh.request_wfh_cancellation') }}"
                                                            onclick="event.stopPropagation(); openWfhCancellationModal({{ $req->id }}, '{{ route('hrms.wfh.request-cancellation', $req->id) }}')"
                                                            style="border-radius: 8px; width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; padding: 0;">
                                                        <i class="feather-trash-2 fs-14"></i>
                                                    </button>
                                                </div>
                                            @else
                                                <div class="d-inline-flex">
                                                    <button type="button" class="btn btn-sm btn-light border disabled" 
                                                            style="border-radius: 8px; width: 32px; height: 32px; display: inline-flex; align-items: center; justify-content: center; padding: 0;" disabled onclick="event.stopPropagation();">
                                                        <i class="feather-trash-2 fs-14"></i>
                                                    </button>
                                                </div>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                            <tr id="no_matching_emp_wfh_apps_row" class="d-none">
                                <td colspan="10" class="text-center py-5 text-muted">
                                    <i class="feather-folder fs-3 d-block mb-2 text-secondary"></i>
                                    {{ __('hrms.wfh.no_matching_applications') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- WFH Applications Pagination Container -->
                <div class="erp-pagination-container py-3 px-3 border-top d-none" id="empWfhAppsPaginationContainer">
                    <ul class="erp-pagination mb-2 justify-content-center" id="emp_wfh_apps_pagination_ul">
                        <!-- Dynamically generated pagination links -->
                    </ul>
                    <div class="erp-pagination-info text-center">
                        Showing <span id="emp_wfh_apps_showing_start">0</span> to <span id="emp_wfh_apps_showing_end">0</span> of <strong id="emp_wfh_apps_total_count">0</strong> entries
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

<div class="modal fade" id="empApplyWfhModal" tabindex="-1" aria-labelledby="empApplyWfhModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom py-3">
                <h5 class="modal-title fw-bold text-dark" id="empApplyWfhModalLabel"><i class="feather-home me-2 text-primary"></i> {{ __('hrms.wfh.apply_wfh') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="{{ route('hrms.wfh.store') }}" method="POST" enctype="multipart/form-data" id="empApplyWfhForm">
                @csrf
                <input type="hidden" name="employee_id" value="{{ $employee->id }}">
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

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <x-ui.odoo-form-ui type="input" inputType="date" label="{{ __('hrms.wfh.start_date') }}" name="start_date" id="emp_wfh_start_date" :required="true" class="odoo-underline-input" />
                        </div>
                        <div class="col-md-6 mb-3">
                            <x-ui.odoo-form-ui type="select" label="{{ __('hrms.wfh.start_date_session') }}" name="start_date_type" id="emp_wfh_start_type" :required="true" class="emp-odoo-select2-custom">
                                <option value="full_day">{{ __('hrms.wfh.full_day') }}</option>
                                <option value="first_half">{{ __('hrms.wfh.first_half') }}</option>
                                <option value="second_half">{{ __('hrms.wfh.second_half') }}</option>
                            </x-ui.odoo-form-ui>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <x-ui.odoo-form-ui type="input" inputType="date" label="{{ __('hrms.wfh.end_date') }}" name="end_date" id="emp_wfh_end_date" :required="true" class="odoo-underline-input" />
                        </div>
                        <div class="col-md-6 mb-3">
                            <x-ui.odoo-form-ui type="select" label="{{ __('hrms.wfh.end_date_session') }}" name="end_date_type" id="emp_wfh_end_type" :required="true" class="emp-odoo-select2-custom">
                                <option value="full_day">{{ __('hrms.wfh.full_day') }}</option>
                                <option value="first_half">{{ __('hrms.wfh.first_half') }}</option>
                                <option value="second_half">{{ __('hrms.wfh.second_half') }}</option>
                            </x-ui.odoo-form-ui>
                        </div>
                    </div>

                    <div class="col-12 mb-3">
                        <div id="emp_wfh_calculated_duration_display" class="alert alert-info py-2 fs-12 mb-0 d-flex justify-content-between align-items-center">
                            <div>
                                <i class="feather-info me-1"></i> {{ __('hrms.wfh.estimated_duration') }}: <strong id="emp_wfh_duration_val">0.0</strong> {{ __('hrms.wfh.days_count') }}
                            </div>
                            <div class="fw-semibold text-primary" id="emp_wfh_session_flow_val">
                                ({{ __('hrms.wfh.full_day') }})
                            </div>
                        </div>
                        <input type="hidden" name="duration" id="emp_wfh_duration" value="0.0">
                    </div>

                    <div class="mb-3">
                        <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.wfh.reason_for_wfh') }}" name="reason" :required="true" class="odoo-underline-input" placeholder="{{ __('hrms.wfh.reason_placeholder') }}"></x-ui.odoo-form-ui>
                    </div>

                    <div class="mb-3">
                        <x-ui.odoo-form-ui type="file" label="{{ __('hrms.wfh.attachment_optional') }}" name="attachment" id="emp_wfh_attachment" :required="false" helperText="{{ __('hrms.wfh.attachment_help') }}" />
                    </div>

                    <div class="mb-3">
                        <x-ui.odoo-form-ui type="select" label="{{ __('hrms.wfh.notify_members') }}" name="notified_contacts[]" id="emp_wfh_notified_contacts" :required="false" :multiple="true" class="emp-odoo-select2-custom" placeholder="{{ __('hrms.wfh.notify_members') }}...">
                            @foreach ($allEmployees as $emp)
                                @if ($emp->id !== $employee->id)
                                    <option value="{{ $emp->id }}">{{ $emp->full_name }} ({{ $emp->employee_id }})</option>
                                @endif
                            @endforeach
                        </x-ui.odoo-form-ui>
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
                <div class="modal-footer border-top py-3 d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('hrms.wfh.discard') }}</button>
                    <button type="submit" class="btn btn-primary text-dark">{{ __('hrms.wfh.submit_application_btn') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="wfhCancellationModal" tabindex="-1" aria-labelledby="wfhCancellationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom py-3">
                <h5 class="modal-title fw-bold text-dark" id="wfhCancellationModalLabel">
                    <i class="feather-x-circle text-warning me-2"></i>{{ __('hrms.wfh.request_wfh_cancellation') }}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="wfhCancellationForm" method="POST" action="">
                @csrf
                <div class="modal-body p-4">
                    <p class="text-muted fs-13 mb-3">
                        {{ __('hrms.wfh.wfh_cancel_help') }}
                    </p>
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-dark fs-13">{{ __('hrms.wfh.cancellation_reason') }} <span class="text-danger">*</span></label>
                        <textarea name="cancellation_reason" id="wfh_cancellation_reason" class="form-control fs-13" rows="3" placeholder="{{ __('hrms.wfh.cancellation_reason_placeholder') }}" required maxlength="1000"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top py-3 d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('hrms.wfh.cancel') }}</button>
                    <button type="submit" class="btn btn-warning text-dark fw-semibold">
                        <i class="feather-send me-1"></i>{{ __('hrms.wfh.submit_application_btn') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="rejectWfhModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom py-3">
                <h5 class="modal-title fw-bold text-danger"><i class="feather-x-circle me-2"></i> {{ __('hrms.wfh.reject_wfh_application') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="rejectWfhForm" method="POST" action="">
                @csrf
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-semibold text-secondary fs-13">{{ __('hrms.wfh.rejection_reason') }} <span class="text-danger">*</span></label>
                        <textarea name="rejection_reason" class="form-control" rows="3" placeholder="{{ __('hrms.wfh.rejection_reason_placeholder') }}" required></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top py-3">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">{{ __('hrms.wfh.cancel') }}</button>
                    <button type="submit" class="btn btn-danger">{{ __('hrms.wfh.confirm_rejection') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
const runWfhReqSetup = () => {
    let reqWfhMap = null;
    let reqWfhMarker = null;

    const initWfhReqMapPicker = () => {
        if (typeof L === 'undefined') {
            setTimeout(initWfhReqMapPicker, 100);
            return;
        }

        const mapContainerId = 'wfh_req_map_picker';
        const latInput = $('#wfh_req_latitude');
        const lngInput = $('#wfh_req_longitude');
        
        if (!document.getElementById(mapContainerId)) return;

        let initialLat = parseFloat(latInput.val()) || 28.6139; // Default center
        let initialLng = parseFloat(lngInput.val()) || 77.2090;
        
        if (reqWfhMap) {
            setTimeout(() => {
                reqWfhMap.invalidateSize();
            }, 300);
            return;
        }

        reqWfhMap = L.map(mapContainerId).setView([initialLat, initialLng], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '© OpenStreetMap'
        }).addTo(reqWfhMap);

        reqWfhMarker = L.marker([initialLat, initialLng], { draggable: true }).addTo(reqWfhMap);

        // Update inputs on marker drag
        reqWfhMarker.on('dragend', function(e) {
            const position = reqWfhMarker.getLatLng();
            latInput.val(position.lat.toFixed(8));
            lngInput.val(position.lng.toFixed(8));
        });

        // Update marker and inputs on map click
        reqWfhMap.on('click', function(e) {
            reqWfhMarker.setLatLng(e.latlng);
            latInput.val(e.latlng.lat.toFixed(8));
            lngInput.val(e.latlng.lng.toFixed(8));
        });

        // Update map when inputs change manually
        const updateMapFromInputs = () => {
            const lat = parseFloat(latInput.val());
            const lng = parseFloat(lngInput.val());
            if (!isNaN(lat) && !isNaN(lng)) {
                const latlng = [lat, lng];
                reqWfhMarker.setLatLng(latlng);
                reqWfhMap.setView(latlng, reqWfhMap.getZoom());
            }
        };

        latInput.on('input', updateMapFromInputs);
        lngInput.on('input', updateMapFromInputs);

        // Address Search Geocoding logic
        const searchInput = $('#wfh_req_map_search');

        const performSearch = () => {
            const query = searchInput.val();
            if (!query) return;

            searchInput.prop('disabled', true).attr('placeholder', 'Searching...');
            
            // Use ArcGIS World Geocoding Service (Free public lookup) for high accuracy address/subarea search
            fetch(`https://geocode.arcgis.com/arcgis/rest/services/World/GeocodeServer/findAddressCandidates?f=json&singleLine=${encodeURIComponent(query)}&maxLocations=1`)
                .then(res => res.json())
                .then(data => {
                    if (data && data.candidates && data.candidates.length > 0) {
                        const lat = parseFloat(data.candidates[0].location.y);
                        const lng = parseFloat(data.candidates[0].location.x);
                        
                        latInput.val(lat.toFixed(8));
                        lngInput.val(lng.toFixed(8));

                        if (reqWfhMap && reqWfhMarker) {
                            reqWfhMarker.setLatLng([lat, lng]);
                            reqWfhMap.setView([lat, lng], 15);
                        }
                        searchInput.prop('disabled', false).attr('placeholder', 'Search address or subarea (Press Enter)...');
                    } else {
                        // Fallback to OSM Nominatim
                        fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}`)
                            .then(res2 => res2.json())
                            .then(data2 => {
                                if (data2 && data2.length > 0) {
                                    const lat = parseFloat(data2[0].lat);
                                    const lng = parseFloat(data2[0].lon);
                                    
                                    latInput.val(lat.toFixed(8));
                                    lngInput.val(lng.toFixed(8));

                                    if (reqWfhMap && reqWfhMarker) {
                                        reqWfhMarker.setLatLng([lat, lng]);
                                        reqWfhMap.setView([lat, lng], 15);
                                    }
                                } else {
                                    alert("Location not found. Please try a different query.");
                                }
                                searchInput.prop('disabled', false).attr('placeholder', 'Search address or subarea (Press Enter)...');
                            })
                            .catch(err => {
                                alert("Location not found. Please try a different query.");
                                searchInput.prop('disabled', false).attr('placeholder', 'Search address or subarea (Press Enter)...');
                            });
                    }
                })
                .catch(err => {
                    console.error("Geocoding error:", err);
                    searchInput.prop('disabled', false).attr('placeholder', 'Search address or subarea (Press Enter)...');
                });
        };

        searchInput.on('keypress', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                performSearch();
            }
        });

        setTimeout(() => {
            reqWfhMap.invalidateSize();
        }, 300);
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
        const modalEl = document.getElementById('empApplyWfhModal');
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
                    
                    if (reqWfhMap && reqWfhMarker) {
                        reqWfhMarker.setLatLng([lat, lng]);
                        reqWfhMap.setView([lat, lng], 15);
                    } else {
                        // Auto-open map to show detected location
                        const mapWrap = document.getElementById('wfh_req_map_wrap');
                        if (mapWrap && mapWrap.style.display === 'none') {
                            window.toggleWfhReqMap();
                            setTimeout(() => {
                                if (reqWfhMap && reqWfhMarker) {
                                    reqWfhMarker.setLatLng([lat, lng]);
                                    reqWfhMap.setView([lat, lng], 15);
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
</script>@endpush

@push('styles')
<style>
    .btn-outline-primary {
        border-color: var(--bs-primary) !important;
        color: var(--bs-primary) !important;
        background-color: transparent !important;
        transition: all 0.2s ease-in-out;
    }
    .btn-outline-primary:hover,
    .btn-outline-primary:focus,
    .btn-outline-primary:active,
    .btn-outline-primary.active,
    .btn-outline-primary.show {
        background-color: var(--bs-primary) !important;
        border-color: var(--bs-primary) !important;
        color: #ffffff !important;
    }
</style>
@endpush
