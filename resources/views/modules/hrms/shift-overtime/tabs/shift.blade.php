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
                        <option value="approved" {{ ($shiftStatus ?? '') === 'approved' ? 'selected' : '' }}>{{ __('hrms.shift_change.approved') }}</option>
                        <option value="rejected" {{ ($shiftStatus ?? '') === 'rejected' ? 'selected' : '' }}>{{ __('hrms.shift_change.rejected') }}</option>
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
                                {{ __('hrms.shift_change.type_' . $req->type) }}
                            </span>
                            @if($req->type === 'recurring' && $req->recurring_days)
                                @php
                                    $recurringDays = $req->recurring_days;
                                    if (is_string($recurringDays)) {
                                        $recurringDays = json_decode($recurringDays, true) ?: explode(',', $recurringDays);
                                    }
                                    $dayNames = [
                                        0 => 'Sun',
                                        1 => 'Mon',
                                        2 => 'Tue',
                                        3 => 'Wed',
                                        4 => 'Thu',
                                        5 => 'Fri',
                                        6 => 'Sat'
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
