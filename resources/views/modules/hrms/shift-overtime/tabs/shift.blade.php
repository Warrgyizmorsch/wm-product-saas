<div class="tab-pane fade @if($activeTab === 'shift') show active @endif" id="shift" role="tabpanel" aria-labelledby="shift-tab">
    {{-- Shift Change Requests Table --}}
    <div>
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-3">
            <div>
                <h5 class="fw-bold text-dark mb-0"><i class="feather-git-pull-request me-2 text-primary"></i> {{ __('hrms.shift_change.title') }}</h5>
                <p class="text-muted fs-12 mb-0">{{ __('hrms.shift_change.title') }}</p>
            </div>
    
    <div class="d-flex align-items-center gap-2">
        <form method="GET" action="javascript:void(0);" id="shiftFilterForm" class="d-flex align-items-center gap-2 m-0 flex-wrap">
            <input type="hidden" name="sort" id="shift_sort_input" value="{{ $shiftSort ?? 'newest' }}">
            <!-- Search Input -->
            <div class="d-flex align-items-center border rounded px-3 py-1" style="background-color: #f1f5f9; min-width: 220px; height: 38px;">
                <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                <input type="text" name="search" id="shift_search" class="form-control border-0 bg-transparent p-0 fs-13" placeholder="{{ __('hrms.shift_change.search_employee') }}" value="{{ $shiftSearch ?? '' }}" style="box-shadow: none; height: 32px;">
            </div>

            <!-- Sort Dropdown -->
            <x-ui.sort-dropdown label="{{ __('hrms.common.sort') }}">
                <a class="dropdown-item py-2 d-flex align-items-center {{ ($shiftSort ?? 'newest') === 'newest' ? 'active' : '' }}" href="#" onclick="setShiftSort('newest', this); event.preventDefault();">
                    <span>{{ __('hrms.shift_change.sort_newest') }}</span>
                </a>
                <a class="dropdown-item py-2 d-flex align-items-center {{ ($shiftSort ?? '') === 'oldest' ? 'active' : '' }}" href="#" onclick="setShiftSort('oldest', this); event.preventDefault();">
                    <span>{{ __('hrms.shift_change.sort_oldest') }}</span>
                </a>
            </x-ui.sort-dropdown>

            <!-- Filter Dropdown -->
            <x-ui.filter label="{{ __('hrms.common.filter') }}" offset="0, 5">
                <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> {{ __('hrms.common.filter_options') }}</h6>
                
                @if($isAdmin)
                    <div class="mb-3" style="min-width: 250px;">
                        <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.shift_change.employee') }}</label>
                        <x-ui.odoo-form-ui type="select" name="employee_id" id="filter_shift_employee_id">
                            <option value="" {{ ($shiftEmployeeId ?? '') === '' ? 'selected' : '' }}>{{ __('hrms.common.all_employees') }}</option>
                            @foreach(($employees ?? []) as $emp)
                                <option value="{{ $emp->id }}" {{ ($shiftEmployeeId ?? '') == $emp->id ? 'selected' : '' }}>{{ $emp->full_name }} ({{ $emp->employee_id }})</option>
                            @endforeach
                        </x-ui.odoo-form-ui>
                    </div>
                @endif

                <div class="mb-3" style="min-width: 250px;">
                    <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">{{ __('hrms.shift_change.status') }}</label>
                    <x-ui.odoo-form-ui type="select" name="status" id="filter_shift_status">
                        <option value="" {{ ($shiftStatus ?? '') === '' ? 'selected' : '' }}>{{ __('hrms.shift_change.all_statuses') }}</option>
                        <option value="pending" {{ ($shiftStatus ?? '') === 'pending' ? 'selected' : '' }}>{{ __('hrms.shift_change.pending') }}</option>
                        <option value="cancellation_requested" {{ ($shiftStatus ?? '') === 'cancellation_requested' ? 'selected' : '' }}>{{ __('hrms.shift_change.cancellation_requested') }}</option>
                        <option value="approved" {{ ($shiftStatus ?? '') === 'approved' ? 'selected' : '' }}>{{ __('hrms.shift_change.approved') }}</option>
                        <option value="rejected" {{ ($shiftStatus ?? '') === 'rejected' ? 'selected' : '' }}>{{ __('hrms.shift_change.rejected') }}</option>
                        <option value="cancelled" {{ ($shiftStatus ?? '') === 'cancelled' ? 'selected' : '' }}>{{ __('hrms.shift_change.cancelled') }}</option>
                    </x-ui.odoo-form-ui>
                </div>

                <div class="dropdown-divider my-3"></div>
                <div class="d-flex gap-2">
                    <x-ui.button variant="primary" size="sm" type="submit" class="flex-grow-1">{{ __('hrms.common.apply') }}</x-ui.button>
                    <x-ui.button variant="light" size="sm" type="button" class="border flex-grow-1" onclick="resetShiftFilters()">{{ __('hrms.common.reset') }}</x-ui.button>
                </div>
            </x-ui.filter>
        </form>
    </div>
</div>
<div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 w-100" id="shiftTable">
            <thead class="table-light">
                <tr>
                    <th class="fs-12 text-uppercase text-muted fw-semibold ps-3" style="width: 20%;">{{ __('hrms.shift_change.employee') }}</th>
                    <th class="fs-12 text-uppercase text-muted fw-semibold" style="width: 11%;">{{ __('hrms.shift_change.type') }}</th>
                    <th class="fs-12 text-uppercase text-muted fw-semibold" style="width: 14%;">{{ __('hrms.shift_change.effective_period') }}</th>
                    <th class="fs-12 text-uppercase text-muted fw-semibold" style="width: 18%;">{{ __('hrms.shift_change.current_shift') }}</th>
                    <th class="fs-12 text-uppercase text-muted fw-semibold" style="width: 18%;">{{ __('hrms.shift_change.requested_shift') }}</th>
                    <th class="fs-12 text-uppercase text-muted fw-semibold text-center" style="width: 9%;">{{ __('hrms.shift_change.status') }}</th>
                    <th class="fs-12 text-uppercase text-muted fw-semibold text-end pe-3" style="width: 10%;">{{ __('hrms.shift_change.actions') }}</th>
                </tr>
            </thead>
            <tbody id="shiftTableBody">
                @forelse($shiftRequests as $req)
                    @php
                        $empName = $req->employee ? $req->employee->full_name : 'Unknown Employee';
                        $empCode = $req->employee ? $req->employee->employee_id : '-';
                        $empInitials = $req->employee ? substr($req->employee->full_name, 0, 2) : 'UE';
                    @endphp
                    <tr class="shift-row" data-employee="{{ strtolower($empName) }}" data-employee-id="{{ $req->employee_id }}" data-status="{{ $req->status }}" data-created-at="{{ $req->created_at ? $req->created_at->timestamp : 0 }}">
                        <td class="ps-3">
                            <div class="d-flex align-items-center gap-2">
                                <div class="avatar bg-soft-primary text-primary rounded-circle d-flex align-items-center justify-content-center flex-shrink-0" style="width: 32px; height: 32px; font-weight: bold; background-color: rgba(13, 110, 253, 0.1);">
                                    {{ $empInitials }}
                                </div>
                                <div>
                                    <div class="fw-semibold text-dark">{{ $empName }}</div>
                                    <div class="text-muted fs-11">{{ $empCode }}</div>
                                </div>
                            </div>
                        </td>
                        <td>
                            @php
                                $typeVariant = match($req->type) {
                                    'permanent' => 'success',
                                    'recurring' => 'primary',
                                    default => 'secondary'
                                };
                            @endphp
                            <x-ui.badge :variant="$typeVariant" soft class="text-uppercase fs-10">
                                {{ __('hrms.shift_change.type_' . $req->type) }}
                            </x-ui.badge>
                            @if($req->type === 'recurring' && $req->recurring_days)
                                @php
                                    $recurringDays = $req->recurring_days;
                                    if (is_string($recurringDays)) {
                                        $recurringDays = json_decode($recurringDays, true) ?: explode(',', $recurringDays);
                                    }
                                    $dayNames = [
                                        0 => __('hrms.shift_change.days.sun'),
                                        1 => __('hrms.shift_change.days.mon'),
                                        2 => __('hrms.shift_change.days.tue'),
                                        3 => __('hrms.shift_change.days.wed'),
                                        4 => __('hrms.shift_change.days.thu'),
                                        5 => __('hrms.shift_change.days.fri'),
                                        6 => __('hrms.shift_change.days.sat')
                                    ];
                                    $mapped = [];
                                    if (is_array($recurringDays)) {
                                        foreach ($recurringDays as $d) {
                                            $dVal = trim((string)$d);
                                            if ($dVal !== '') {
                                                $dInt = (int)$dVal;
                                                if (isset($dayNames[$dInt])) {
                                                    $mapped[] = $dayNames[$dInt];
                                                }
                                            }
                                        }
                                    }
                                @endphp
                                @if(!empty($mapped))
                                    <div class="fs-10 text-muted mt-1 fw-medium" style="max-width: 120px; line-height: 1.2;">{{ implode(', ', $mapped) }}</div>
                                @endif
                            @endif
                        </td>
                        <td>
                            <div class="fw-medium text-dark">{{ $req->start_date->format('d M Y') }}</div>
                            @if($req->type === 'temporary' && $req->end_date && !$req->start_date->isSameDay($req->end_date))
                                <div class="text-muted fs-11">{{ __('hrms.shift_change.to') }} {{ $req->end_date->format('d M Y') }}</div>
                            @endif
                        </td>
                        <td>
                            @if($req->currentShift)
                                <div class="fw-medium text-dark fs-12">{{ $req->currentShift->name }}</div>
                            @else
                                <span class="badge bg-soft-secondary text-secondary">{{ __('hrms.shift_change.day_off') }}</span>
                            @endif
                        </td>
                        <td>
                            @if($req->requestedShift)
                                <div class="fw-medium text-dark fs-12">{{ $req->requestedShift->name }}</div>
                            @else
                                <span class="badge bg-soft-secondary text-secondary">{{ __('hrms.shift_change.day_off') }}</span>
                            @endif
                        </td>
                        <td class="text-center">
                            @php
                                $shiftStatusLabel = match($req->status) {
                                    'approved' => __('hrms.shift_change.approved'),
                                    'rejected' => __('hrms.shift_change.rejected'),
                                    'cancelled' => __('hrms.shift_change.cancelled'),
                                    'cancellation_requested' => __('hrms.shift_change.cancel_req'),
                                    default => __('hrms.shift_change.pending'),
                                };
                            @endphp
                            <x-ui.status-badge :status="$req->status" :label="$shiftStatusLabel" />
                        </td>
                        <td class="text-end pe-3" style="white-space: nowrap;">
                            <div class="d-flex align-items-center justify-content-end gap-2 flex-nowrap">
                                @if($req->attachment_path)
                                    <x-ui.icon-btn variant="soft-secondary" icon="feather-paperclip" :href="asset('storage/' . $req->attachment_path)" target="_blank" title="{{ __('hrms.shift_change.view_attachment') }}" />
                                @endif

                                @if($isAdmin)
                                    <div class="dropdown d-inline-block position-relative">
                                        <button class="btn btn-sm dropdown-toggle py-1 px-3 d-inline-flex align-items-center justify-content-between text-capitalize fw-semibold shadow-sm btn-status-dropdown text-white" type="button" data-bs-toggle="dropdown" data-bs-boundary="viewport" aria-expanded="false">
                                            <span>{{ $req->status === 'cancellation_requested' ? __('hrms.shift_change.cancel_req') : ($req->status === 'approved' ? __('hrms.shift_change.approved') : ($req->status === 'rejected' ? __('hrms.shift_change.rejected') : ($req->status === 'cancelled' ? __('hrms.shift_change.cancelled') : __('hrms.shift_change.pending')))) }}</span>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end status-dropdown-menu shadow" style="z-index: 1060;">
                                            @if($req->status === 'cancellation_requested')
                                                <li>
                                                    <form action="{{ route('hrms.shift-change.approve-cancellation', $req->id) }}" method="POST">
                                                        @csrf
                                                        <button type="submit" class="dropdown-item text-success fw-medium">
                                                            {{ __('hrms.shift_change.approve_cancel') }}
                                                        </button>
                                                    </form>
                                                </li>
                                                <li>
                                                    <form action="{{ route('hrms.shift-change.deny-cancellation', $req->id) }}" method="POST">
                                                        @csrf
                                                        <button type="submit" class="dropdown-item text-danger fw-medium">
                                                            {{ __('hrms.shift_change.deny_cancel') }}
                                                        </button>
                                                    </form>
                                                </li>
                                            @elseif($req->status === 'cancelled')
                                                <li>
                                                    <span class="dropdown-item text-muted disabled">{{ __('hrms.shift_change.cancelled') }}</span>
                                                </li>
                                            @else
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
                                            @endif
                                        </ul>
                                    </div>

                                    <form action="{{ route('hrms.shift-change.destroy', $req->id) }}" method="POST" onsubmit="return confirmFormSubmit(event, '{{ __('hrms.shift_change.confirm_delete') }}', { title: '{{ __('hrms.shift_change.delete_title') }}', variant: 'danger', confirmButtonText: '{{ __('hrms.shift_change.delete_btn') }}' });" class="d-inline m-0">
                                        @csrf
                                        @method('DELETE')
                                        <x-ui.icon-btn variant="soft-danger" icon="feather-trash-2" type="submit" title="{{ __('hrms.shift_change.delete_application') }}" />
                                    </form>
                                @else
                                    @if($req->canWithdraw())
                                        <form action="{{ route('hrms.shift-change.withdraw', $req->id) }}" method="POST" onsubmit="return confirmFormSubmit(event, '{{ __('hrms.shift_change.confirm_withdraw') }}', { title: '{{ __('hrms.shift_change.withdraw_request') }}', variant: 'warning', confirmButtonText: '{{ __('hrms.shift_change.withdraw_btn') }}' });" class="d-inline m-0">
                                            @csrf
                                            <x-ui.icon-btn variant="soft-danger" icon="feather-trash-2" type="submit" title="{{ __('hrms.shift_change.withdraw_request') }}" />
                                        </form>
                                    @elseif($req->canRequestCancellation())
                                        <x-ui.icon-btn variant="soft-warning" icon="feather-x-circle" title="{{ __('hrms.shift_change.cancel_request_title') }}" onclick="openShiftCancellationModal({{ $req->id }}, '{{ route('hrms.shift-change.request-cancellation', $req->id) }}')" />
                                    @else
                                        <x-ui.icon-btn variant="transparent-dark" icon="feather-trash-2" disabled />
                                    @endif
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr id="empty_initial_shift_row">
                        <td colspan="7" class="text-center py-4 text-muted fs-13">{{ __('hrms.shift_change.no_requests_found') }}</td>
                    </tr>
                @endforelse
                <tr id="no_matching_shift_row" class="d-none">
                    <td colspan="7" class="text-center py-5 text-muted">
                        <i class="feather-folder fs-3 d-block mb-3 text-secondary"></i>
                        {{ __('hrms.shift_change.no_matching_requests') }}
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    <div id="shift_pagination_container">
        @if($shiftRequests instanceof \Illuminate\Pagination\LengthAwarePaginator && $shiftRequests->hasPages())
            <x-ui.pagination
                class="px-0 py-0"
                :current-page="$shiftRequests->currentPage()"
                :total-pages="$shiftRequests->lastPage()"
                :total-results="$shiftRequests->total()"
                :per-page="$shiftRequests->perPage()"
                page-param="shift_page"
            />
        @endif
    </div>
</div>
</div>

{{-- Decision Form for Shift Change --}}
<form id="shiftDecisionForm" action="" method="POST" style="display: none;">
    @csrf
    <input type="hidden" name="action" id="shiftDecisionAction" value="">
    <input type="hidden" name="rejection_reason" id="shiftDecisionReason" value="">
</form>
</div>

{{-- Shift Change Cancellation Request Modal --}}
<div class="modal fade" id="shiftCancellationModal" tabindex="-1" aria-labelledby="shiftCancellationModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom py-3">
                <h5 class="modal-title fw-bold text-dark" id="shiftCancellationModalLabel">
                    <i class="feather-x-circle text-warning me-2"></i>{{ __('hrms.shift_change.cancel_shift_change_request') }}
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="shiftCancellationForm" method="POST" action="">
                @csrf
                <div class="modal-body p-4">
                    <p class="text-muted fs-13 mb-3">
                        {{ __('hrms.shift_change.cancellation_reason_help') }}
                    </p>
                    <div class="mb-3">
                        <x-ui.odoo-form-ui type="textarea" label="{{ __('hrms.shift_change.reason_comments') }}" name="cancellation_reason" id="shift_cancellation_reason" :required="true" rows="3" placeholder="{{ __('hrms.shift_change.reason_placeholder') }}" maxlength="1000" />
                    </div>
                </div>
                <div class="modal-footer border-top py-3 d-flex justify-content-end gap-2">
                    <x-ui.button variant="light" data-bs-dismiss="modal">{{ __('hrms.shift_change.close') }}</x-ui.button>
                    <x-ui.button variant="warning" type="submit" icon="feather-send">
                        {{ __('hrms.shift_change.submit_cancellation') }}
                    </x-ui.button>
                </div>
            </form>
        </div>
    </div>
</div>

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
                                @foreach(($employees ?? []) as $emp)
                                    <option value="{{ $emp->id }}" data-shift-id="{{ $emp->shift_id }}" {{ ($employee && $employee->id === $emp->id) ? 'selected' : '' }}>
                                        {{ $emp->full_name }} ({{ $emp->employee_id }})
                                    </option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>
                    @else
                        <input type="hidden" name="employee_id" id="shift_employee_id" value="{{ $employee ? $employee->id : '' }}" data-shift-id="{{ $employee ? $employee->shift_id : '' }}">
                    @endif

                    <div class="mb-3">
                        <x-ui.odoo-form-ui type="select" label="{{ __('hrms.shift_change.change_type') }}" name="type" id="shift_change_type" :required="true">
                            <option value="temporary">{{ __('hrms.shift_change.type_temporary') }} ({{ __('hrms.shift_change.specific_dates') }})</option>
                            <option value="permanent">{{ __('hrms.shift_change.type_permanent') }} ({{ __('hrms.shift_change.new_default_shift') }})</option>
                            <option value="recurring">{{ __('hrms.shift_change.type_recurring') }} ({{ __('hrms.shift_change.specific_weekdays') }})</option>
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
                                <label class="form-check-label fs-12 text-muted" for="day_mon">{{ __('hrms.shift_change.days.mon') }}</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" name="recurring_days[]" value="2" id="day_tue">
                                <label class="form-check-label fs-12 text-muted" for="day_tue">{{ __('hrms.shift_change.days.tue') }}</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" name="recurring_days[]" value="3" id="day_wed">
                                <label class="form-check-label fs-12 text-muted" for="day_wed">{{ __('hrms.shift_change.days.wed') }}</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" name="recurring_days[]" value="4" id="day_thu">
                                <label class="form-check-label fs-12 text-muted" for="day_thu">{{ __('hrms.shift_change.days.thu') }}</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" name="recurring_days[]" value="5" id="day_fri">
                                <label class="form-check-label fs-12 text-muted" for="day_fri">{{ __('hrms.shift_change.days.fri') }}</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" name="recurring_days[]" value="6" id="day_sat">
                                <label class="form-check-label fs-12 text-muted" for="day_sat">{{ __('hrms.shift_change.days.sat') }}</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" name="recurring_days[]" value="0" id="day_sun">
                                <label class="form-check-label fs-12 text-muted" for="day_sun">{{ __('hrms.shift_change.days.sun') }}</label>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <x-ui.odoo-form-ui type="select" label="{{ __('hrms.shift_change.requested_shift') }}" name="requested_shift_id" id="shift_requested_shift_id" :required="false">
                            <option value="">{{ __('hrms.shift_change.select_requested_shift') }} ({{ __('hrms.shift_change.day_off') }})</option>
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
                <div class="modal-footer border-top py-3 d-flex justify-content-end gap-2">
                    <x-ui.button variant="light" data-bs-dismiss="modal">{{ __('hrms.shift_change.close') }}</x-ui.button>
                    <x-ui.button variant="primary" type="submit">{{ __('hrms.shift_change.submit_application') }}</x-ui.button>
                </div>
            </form>
        </div>
    </div>
</div>
