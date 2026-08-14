<div class="tab-pane fade @if($activeTab === 'overtime') show active @endif" id="overtime-pane" role="tabpanel" aria-labelledby="overtime-pane-tab">


    {{-- Overtime Table --}}
    <div>
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-3">
            <div>
                <h5 class="fw-bold text-dark mb-0"><i class="feather-clock me-2 text-primary"></i> {{ __('hrms.overtime.title') }}</h5>
                <p class="text-muted fs-12 mb-0">{{ __('hrms.overtime.title') }}</p>
            </div>
            
            <div class="d-flex align-items-center gap-2">
                <form method="GET" action="javascript:void(0);" id="overtimeFilterForm" class="d-flex align-items-center gap-2 m-0 flex-wrap">
                    <input type="hidden" name="sort" id="overtime_sort_input" value="{{ $overtimeSort ?? 'newest' }}">
                    <!-- Search Input -->
                    <div class="d-flex align-items-center border rounded px-3 py-1" style="background-color: #f1f5f9; min-width: 220px; height: 38px;">
                        <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                        <input type="text" name="search" id="overtime_search" class="form-control border-0 bg-transparent p-0 fs-13" placeholder="{{ __('hrms.overtime.search_employee') }}" value="{{ $overtimeSearch ?? '' }}" style="box-shadow: none; height: 32px;">
                    </div>

                    <!-- Sort Dropdown -->
                    <x-ui.sort-dropdown label="{{ __('hrms.common.sort') }}">
                        <a class="dropdown-item py-2 d-flex align-items-center {{ ($overtimeSort ?? 'newest') === 'newest' ? 'active' : '' }}" href="#" onclick="setOvertimeSort('newest', this); event.preventDefault();">
                            <span>{{ __('hrms.overtime.sort_newest') }}</span>
                        </a>
                        <a class="dropdown-item py-2 d-flex align-items-center {{ ($overtimeSort ?? '') === 'oldest' ? 'active' : '' }}" href="#" onclick="setOvertimeSort('oldest', this); event.preventDefault();">
                            <span>{{ __('hrms.overtime.sort_oldest') }}</span>
                        </a>
                    </x-ui.sort-dropdown>

                    <!-- Filter Dropdown -->
                    <x-ui.filter label="{{ __('hrms.common.filter') }}" offset="0, 5">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('hrms.common.filter_options') }}</h6>
                        
                        @if($isAdmin)
                            <div class="mb-3" style="min-width: 250px;">
                                <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.overtime.employee') }}</label>
                                <x-ui.odoo-form-ui type="select" name="employee_id" id="filter_overtime_employee_id">
                                    <option value="" {{ ($overtimeEmployeeId ?? '') === '' ? 'selected' : '' }}>{{ __('hrms.common.all_employees') }}</option>
                                    @foreach(($employees ?? []) as $emp)
                                        <option value="{{ $emp->id }}" {{ ($overtimeEmployeeId ?? '') == $emp->id ? 'selected' : '' }}>{{ $emp->full_name }} ({{ $emp->employee_id }})</option>
                                    @endforeach
                                </x-ui.odoo-form-ui>
                            </div>
                        @endif

                        <div class="mb-3" style="min-width: 250px;">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.overtime.status') }}</label>
                            <x-ui.odoo-form-ui type="select" name="status" id="filter_overtime_status">
                                <option value="" {{ ($overtimeStatus ?? '') === '' ? 'selected' : '' }}>{{ __('hrms.overtime.all_statuses') }}</option>
                                <option value="pending" {{ ($overtimeStatus ?? '') === 'pending' ? 'selected' : '' }}>{{ __('hrms.overtime.pending') }}</option>
                                <option value="approved" {{ ($overtimeStatus ?? '') === 'approved' ? 'selected' : '' }}>{{ __('hrms.overtime.approved') }}</option>
                                <option value="rejected" {{ ($overtimeStatus ?? '') === 'rejected' ? 'selected' : '' }}>{{ __('hrms.overtime.rejected') }}</option>
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
        <div>
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
            <div id="overtime_pagination_container">
                @if($overtimeRequests instanceof \Illuminate\Pagination\LengthAwarePaginator && $overtimeRequests->hasPages())
                    <x-ui.pagination
                        class="px-0 py-0"
                        :current-page="$overtimeRequests->currentPage()"
                        :total-pages="$overtimeRequests->lastPage()"
                        :total-results="$overtimeRequests->total()"
                        :per-page="$overtimeRequests->perPage()"
                        page-param="overtime_page"
                    />
                @endif
            </div>
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
                        <x-ui.odoo-form-ui type="input" inputType="time" label="{{ __('hrms.overtime.start_time') }}" name="start_time" id="ot_start_time" :required="true" class="odoo-underline-input" value="18:00" />
                    </div>

                    <div class="mb-3">
                        <x-ui.odoo-form-ui type="input" inputType="time" label="{{ __('hrms.overtime.end_time') }}" name="end_time" id="ot_end_time" :required="true" class="odoo-underline-input" value="20:00" />
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
