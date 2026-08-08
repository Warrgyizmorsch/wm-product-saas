<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<div class="tab-pane fade {{ $activeTabName === 'attendance' ? 'show active' : '' }}" id="attendance-pane" role="tabpanel" aria-labelledby="attendance-tab">
    
    <div class="row g-4">
        <!-- 1. Attendance Action Widget -->
        <div class="col-xl-4 col-lg-5">
            <div class="card-custom mb-4">
                <div class="card-custom-header">
                    <h5 class="card-custom-title"><i class="feather-clock text-primary"></i> Daily Attendance Console</h5>
                </div>
                <div class="card-body p-4 text-center">
                    
                    <!-- Dynamic Timer -->
                    <div class="my-3">
                        <div class="d-inline-block px-4 py-2 bg-light border rounded-pill mb-2">
                            <span id="attendance-clock" class="fs-28 fw-bold text-dark" style="font-family: 'Outfit', sans-serif; letter-spacing: 1px;">00:00:00</span>
                        </div>
                        <div id="attendance-date" class="fs-12 text-muted fw-semibold text-uppercase tracking-wider">...</div>
                    </div>

                    <!-- Current Status Badge -->
                    <div class="mb-4">
                        @if(!$todayAttendance)
                            <span class="badge bg-soft-secondary text-secondary px-3 py-2 fs-11 fw-bold text-uppercase tracking-wider">Checked Out</span>
                        @elseif($todayAttendance->isWorking() && !$todayAttendance->isOnBreak())
                            <span class="badge bg-soft-success text-success px-3 py-2 fs-11 fw-bold text-uppercase tracking-wider">Working &bull; Active</span>
                        @elseif($todayAttendance->isOnBreak())
                            <span class="badge bg-soft-warning text-warning px-3 py-2 fs-11 fw-bold text-uppercase tracking-wider text-dark">On Break</span>
                        @else
                            <span class="badge bg-soft-danger text-danger px-3 py-2 fs-11 fw-bold text-uppercase tracking-wider">Shift Completed</span>
                        @endif
                    </div>

                    @php
                        $rule = \App\Domains\HRMS\Models\AttendanceRule::where(function ($q) use ($employee) {
                                if ($employee) {
                                    $q->where('company_id', $employee->company_id)
                                      ->orWhereNull('company_id');
                                }
                            })
                            ->where('status', true)
                            ->orderByRaw('company_id IS NULL ASC')
                            ->first();
                        
                        $locationType = !$todayAttendance ? ($employee->office ?: 'office') : $todayAttendance->location_type;
                        
                        $officeWebDisabled = false;
                        if ($locationType === 'office') {
                            $officeWebDisabled = $rule ? !(bool)$rule->office_web : false;
                        }

                        $selfieRequired = false;
                        if ($locationType === 'wfh') {
                            $selfieRequired = $rule ? (bool)$rule->wfh_selfie : false;
                        } elseif ($locationType === 'onsite' || $locationType === 'site') {
                            $selfieRequired = $rule ? (bool)$rule->site_selfie : false;
                        } else {
                            $selfieRequired = false; // Office does not define a selfie rule
                        }

                        $trackingEnabled = false;
                        $trackingIntervalMinutes = 15;
                        if ($locationType === 'wfh') {
                            $trackingEnabled = $rule ? (bool)$rule->wfh_tracking : false;
                            $trackingIntervalMinutes = $rule ? (int)$rule->wfh_tracking_minutes : 15;
                        } elseif ($locationType === 'onsite' || $locationType === 'site') {
                            $trackingEnabled = $rule ? (bool)$rule->site_tracking : false;
                            $trackingIntervalMinutes = $rule ? (int)$rule->site_tracking_minutes : 15;
                        } else {
                            $trackingEnabled = $rule ? (bool)$rule->office_tracking : false;
                            $trackingIntervalMinutes = $rule ? (int)$rule->office_tracking_minutes : 15;
                        }
                    @endphp



                    <!-- Actions Form Console -->
                    <div class="bg-light border border-dashed p-4 rounded-4 mx-auto text-center" style="max-width: 320px;">
                        @if(!$todayAttendance)
                            @if($officeWebDisabled)
                                <div class="avatar-md bg-soft-warning text-warning rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3" style="width: 48px; height: 48px;">
                                    <i class="feather-alert-octagon fs-22"></i>
                                </div>
                                <h6 class="fw-bold text-dark mb-2 fs-14">Web Clock-In Disabled</h6>
                                <p class="fs-12 text-muted mb-0">Please use the biometric device at the office to mark your attendance.</p>
                            @else
                                <div class="avatar-md bg-soft-primary text-primary rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3" style="width: 48px; height: 48px;">
                                    <i class="feather-log-in fs-22"></i>
                                </div>
                                <h6 class="fw-bold text-dark mb-2 fs-14">Shift Not Started</h6>
                                <form action="{{ route('hrms.attendance.check-in') }}" method="POST" id="check-in-form">
                                    @csrf
                                    <input type="hidden" name="employee_id" value="{{ $employee->id }}">
                                    <input type="hidden" name="latitude" id="check-in-lat">
                                    <input type="hidden" name="longitude" id="check-in-lng">
                                    <input type="hidden" name="selfie" id="check-in-selfie">
                                    <button type="submit" class="btn btn-primary w-100 py-2 fs-13 fw-bold d-flex align-items-center justify-content-center gap-2">
                                        Check In Now
                                    </button>
                                </form>
                            @endif
                        @elseif($todayAttendance->isWorking())
                            <div class="avatar-md bg-soft-success text-success rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3" style="width: 48px; height: 48px;">
                                <i class="feather-activity fs-22"></i>
                            </div>
                            <h6 class="fw-bold text-dark mb-1 fs-14">Shift Active</h6>
                            <p class="fs-12 text-muted mb-3">Checked in at: <strong class="text-dark">{{ \Carbon\Carbon::parse($todayAttendance->check_in)->format('h:i A') }}</strong> ({{ ucfirst($todayAttendance->location_type) }})</p>
                            
                            <div class="d-flex flex-column gap-2">
                                @if($todayAttendance->isOnBreak())
                                    <!-- End Break Button -->
                                    <form action="{{ route('hrms.attendance.break-out', $todayAttendance->id) }}" method="POST" id="break-out-form">
                                        @csrf
                                        <button type="submit" class="btn btn-success w-100 py-2 fs-13 fw-bold d-flex align-items-center justify-content-center gap-2">
                                            End Break
                                        </button>
                                    </form>
                                @else
                                    <!-- Start Break Button -->
                                    <form action="{{ route('hrms.attendance.break-in', $todayAttendance->id) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="btn btn-warning w-100 py-2 fs-13 fw-bold text-dark d-flex align-items-center justify-content-center gap-2">
                                            Start Break
                                        </button>
                                    </form>
                                @endif

                                <!-- Check Out Button -->
                                @if($officeWebDisabled)
                                    <div class="alert alert-warning border-0 p-2.5 rounded-3 text-center fs-11 mb-0 mt-1">
                                        <i class="feather-alert-octagon fs-14 mb-0.5 d-block text-warning"></i>
                                        Web check-out is disabled. Please check out via biometric device.
                                    </div>
                                @else
                                    <form action="{{ route('hrms.attendance.check-out', $todayAttendance->id) }}" method="POST" class="mt-1" id="check-out-form">
                                        @csrf
                                        <input type="hidden" name="latitude" id="check-out-lat">
                                        <input type="hidden" name="longitude" id="check-out-lng">
                                        <input type="hidden" name="selfie" id="check-out-selfie">
                                        <button type="submit" class="btn btn-danger w-100 py-2 fs-13 fw-bold d-flex align-items-center justify-content-center gap-2">
                                            Check Out
                                        </button>
                                    </form>
                                @endif
                            </div>
                        @else
                            <div class="avatar-md bg-soft-success text-success rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3" style="width: 48px; height: 48px;">
                                <i class="feather-check-circle fs-22"></i>
                            </div>
                            <h6 class="fw-bold text-dark mb-1 fs-14">Shift Completed Today!</h6>
                            <span class="fs-12 text-muted">Checked out at: <strong class="text-dark">{{ \Carbon\Carbon::parse($todayAttendance->check_out)->format('h:i A') }}</strong></span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. Attendance History Logs -->
        <div class="col-xl-8 col-lg-7">
            <div class="card border-0 shadow-sm rounded-4">
                <div class="card-header bg-transparent border-0 pt-4 px-4 d-flex align-items-center justify-content-between">
                    <div>
                        <h5 class="fw-bold text-dark mb-0">Attendance Log History</h5>
                        <p class="text-muted fs-12 mb-0">Recent logs of check-ins, outs, and breaks</p>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" style="min-width: 600px;">
                            <thead class="bg-light text-uppercase fs-10 tracking-wider">
                                <tr>
                                    <th class="ps-4">Date & Location</th>
                                    <th>Check In</th>
                                    <th>Check Out</th>
                                    <th>Breaks</th>
                                    <th>Work Hours</th>
                                    <th>Status</th>
                                    <th class="pe-4 text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($empAttendances as $log)
                                    <tr>
                                        <td class="ps-4 text-nowrap">
                                            <span class="fw-semibold text-dark d-block mb-1">{{ $log->date->format('M d, Y') }}</span>
                                            @if($log->location_type === 'office')
                                                <span class="badge bg-soft-primary text-primary border-0 fs-10 rounded-pill">Office</span>
                                            @elseif($log->location_type === 'wfh')
                                                <span class="badge bg-soft-success text-success border-0 fs-10 rounded-pill">WFH</span>
                                            @elseif($log->location_type === 'onsite')
                                                <span class="badge bg-soft-warning text-warning border-0 fs-10 rounded-pill">On-Site</span>
                                            @else
                                                <span class="badge bg-soft-dark text-slate border-0 fs-10 rounded-pill">{{ $log->formatted_location_type }}</span>
                                            @endif
                                        </td>
                                        <td class="text-muted fs-13">
                                            {{ ($log->check_in && !in_array($log->status, ['absent', 'on_leave'])) ? \Carbon\Carbon::parse($log->check_in)->format('h:i A') : '-' }}
                                        </td>
                                        <td class="text-muted fs-13">
                                            {{ ($log->check_out && !in_array($log->status, ['absent', 'on_leave'])) ? \Carbon\Carbon::parse($log->check_out)->format('h:i A') : '-' }}
                                        </td>
                                        <td class="fs-13">
                                            @if($log->breaks->isNotEmpty())
                                                @foreach($log->breaks as $index => $brk)
                                                    <div class="fs-11 text-muted" style="line-height: 1.4;">
                                                        {{ \Carbon\Carbon::parse($brk->break_in)->format('h:i A') }} - 
                                                        {{ $brk->break_out ? \Carbon\Carbon::parse($brk->break_out)->format('h:i A') : 'Active' }}
                                                        ({{ $brk->duration_minutes !== null ? $brk->duration_minutes . 'm' : 'Active' }})
                                                    </div>
                                                @endforeach
                                                @if($log->total_break_hours > 0)
                                                    <div class="fw-bold mt-1 text-dark" style="font-size: 11px;">Total: {{ $log->formatted_break_hours }}</div>
                                                @endif
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td class="fw-semibold text-dark fs-13">
                                            @if(!$log->check_in || in_array($log->status, ['absent', 'on_leave']))
                                                -
                                            @elseif($log->check_out)
                                                {{ $log->formatted_work_hours }}
                                            @else
                                                <span class="text-success fw-normal">In progress</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if($log->status === 'present')
                                                <span class="badge bg-soft-success text-success">Present</span>
                                            @elseif($log->status === 'late')
                                                <span class="badge bg-soft-warning text-warning">Late</span>
                                            @elseif($log->status === 'half_day')
                                                <span class="badge bg-soft-danger text-danger">Half Day</span>
                                            @elseif($log->status === 'under_hours')
                                                <span class="badge bg-soft-secondary text-slate">Under Hours</span>
                                            @else
                                                <span class="badge bg-soft-primary text-primary">{{ ucfirst($log->status) }}</span>
                                            @endif
                                        </td>
                                        <td class="pe-4 text-end">
                                            <x-ui.icon-btn 
                                                variant="soft-primary" 
                                                icon="feather-eye" 
                                                title="View Details" 
                                                data-check-in-selfie="{{ $log->check_in_selfie_path ? asset('storage/' . $log->check_in_selfie_path) : '' }}" 
                                                data-check-out-selfie="{{ $log->check_out_selfie_path ? asset('storage/' . $log->check_out_selfie_path) : '' }}"
                                                data-check-in-lat="{{ $log->check_in_latitude }}"
                                                data-check-in-lng="{{ $log->check_in_longitude }}"
                                                data-check-out-lat="{{ $log->check_out_latitude }}"
                                                data-check-out-lng="{{ $log->check_out_longitude }}"
                                                data-date="{{ $log->date->format('M d, Y') }}"
                                                data-location-type="{{ $log->location_type }}"
                                                data-check-in-time="{{ ($log->check_in && !in_array($log->status, ['absent', 'on_leave'])) ? \Carbon\Carbon::parse($log->check_in)->format('h:i A') : '-' }}"
                                                data-check-out-time="{{ ($log->check_out && !in_array($log->status, ['absent', 'on_leave'])) ? \Carbon\Carbon::parse($log->check_out)->format('h:i A') : '-' }}"
                                                data-status="{{ ucfirst($log->status) }}"
                                                data-location-logs="{{ json_encode($log->locationLogs->map(fn($l) => ['lat' => (float)$l->latitude, 'lng' => (float)$l->longitude, 'time' => $l->created_at ? $l->created_at->format('h:i A') : ''])) }}"
                                                onclick="viewAttendanceDetailDrawer(this)"
                                            />
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center py-4 text-muted fs-13">No attendance logs found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Webcam Selfie Modal -->
<div class="modal fade" id="selfieCaptureModal" tabindex="-1" aria-labelledby="selfieCaptureModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 380px;">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-bottom bg-light">
                <h5 class="modal-title fw-bold text-dark fs-14" id="selfieCaptureModalLabel">
                    <i class="feather-camera text-primary me-1.5 fs-15"></i>Verify Attendance Identity
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" id="btn-close-selfie-modal"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <div class="position-relative mx-auto rounded-3 overflow-hidden bg-dark mb-3 border shadow-sm" style="width: 320px; height: 240px;">
                    <video id="modal-webcam-video" width="320" height="240" autoplay playsinline style="object-fit: cover; transform: scaleX(-1);"></video>
                    <canvas id="modal-webcam-canvas" width="320" height="240" class="d-none"></canvas>
                    <img id="modal-selfie-preview-img" width="320" height="240" class="d-none" style="object-fit: cover; transform: scaleX(-1);">
                </div>
                <p class="text-muted fs-12 mb-0" id="selfie-instructions-text">Please align your face and capture a selfie before clocking.</p>
            </div>
            <div class="modal-footer border-top bg-light d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-sm btn-light border px-3" data-bs-dismiss="modal" id="btn-modal-cancel">Cancel</button>
                <button type="button" class="btn btn-sm btn-primary fw-bold px-3 d-flex align-items-center gap-1" id="btn-modal-snap">
                    <i class="feather-camera fs-14"></i> Snap Selfie
                </button>
                <button type="button" class="btn btn-sm btn-warning fw-bold text-dark px-3 d-flex align-items-center gap-1 d-none" id="btn-modal-retake">
                    <i class="feather-refresh-cw fs-14"></i> Retake
                </button>
                <button type="button" class="btn btn-sm btn-success fw-bold text-white px-3 d-flex align-items-center gap-1 d-none" id="btn-modal-confirm">
                    <i class="feather-check-circle fs-14"></i> Confirm & Proceed
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Attendance Action Confirmation & Alert Modal -->
<div class="modal fade" id="attendanceConfirmModal" tabindex="-1" aria-labelledby="attendanceConfirmModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 380px;">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px; overflow: hidden;">
            <div class="modal-header bg-light border-0 py-3 px-4">
                <h6 class="modal-title fw-bold text-dark d-flex align-items-center gap-2" id="attendanceConfirmModalLabel">
                    <i class="feather-alert-circle text-primary fs-16"></i> Action Alert
                </h6>
                <button type="button" class="btn-close fs-10" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <div class="avatar-md bg-soft-primary text-primary rounded-circle d-flex align-items-center justify-content-center mx-auto mb-3" style="width: 56px; height: 56px;">
                    <i id="confirm-modal-icon" class="feather-clock fs-26"></i>
                </div>
                <h6 class="fw-bold text-dark mb-2" id="confirm-modal-title">Are you sure?</h6>
                <p class="text-muted fs-12 mb-0" style="line-height: 1.5;" id="confirm-modal-message">Do you want to proceed with this attendance action?</p>
            </div>
            <div class="modal-footer border-0 bg-light py-3 px-4 d-flex justify-content-end gap-2">
                <button type="button" class="btn btn-sm btn-light border px-3" id="confirm-modal-cancel-btn" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-sm btn-primary fw-bold px-3" id="confirm-modal-submit-btn">Yes, Proceed</button>
            </div>
        </div>
    </div>
</div>

<!-- Attendance Record Details Drawer -->
<x-ui.drawer id="attendanceRecordDetailDrawer" title="Attendance Session Details" position="end" style="width: 480px; max-width: 95vw;">
    <div class="px-1">
        <div class="d-flex align-items-center justify-content-between mb-3 pb-3 border-bottom">
            <div>
                <span class="text-muted fs-11 d-block text-uppercase">Work Mode</span>
                <span class="badge bg-soft-primary text-primary px-3 py-1 fs-11 rounded-pill fw-bold" id="detail-drawer-location">OFFICE</span>
            </div>
            <div class="text-end">
                <span class="text-muted fs-11 d-block text-uppercase">Status</span>
                <span class="badge bg-soft-success text-success px-3 py-1 fs-11 rounded-pill fw-bold" id="detail-drawer-status">Present</span>
            </div>
        </div>

        <!-- Date Info Card -->
        <div class="bg-light border rounded-3 p-3 mb-4 d-flex align-items-center justify-content-between">
            <div>
                <span class="text-muted fs-11 d-block text-uppercase">Date</span>
                <h6 class="fw-bold text-dark mb-0 fs-13" id="detail-drawer-date">Aug 07, 2026</h6>
            </div>
            <div class="avatar-sm bg-soft-primary text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                <i class="feather-calendar fs-16"></i>
            </div>
        </div>

        <!-- Single Location Map -->
        <div class="mb-4">
            <span class="text-muted fs-11 text-uppercase fw-semibold d-block mb-1">Session Location Map</span>
            <div class="position-relative w-100" id="detail-drawer-map-wrap" style="display: none;">
                <input type="text" id="detail_drawer_map_search" class="form-control position-absolute" style="top: 10px; right: 10px; width: 240px; z-index: 1000; box-shadow: 0 2px 8px rgba(0,0,0,0.15) !important; font-size: 11px; border: none !important; border-radius: 6px !important; padding: 6px 12px !important; height: 34px !important; background-color: #fff !important; outline: none !important;" placeholder="Search address or subarea (Press Enter)...">
                <div id="detail-drawer-map" style="height: 250px; width: 100%; border-radius: 8px; border: 1px solid #e2e8f0; background: #f8fafc; z-index: 1;"></div>
            </div>
            <div id="detail-drawer-map-none" class="alert alert-light border text-center fs-12 py-4 mb-0">
                <i class="feather-map-pin text-muted fs-20 d-block mb-1"></i> No location coordinates captured for check-in or check-out.
            </div>
        </div>

        <!-- Check-In & Check-Out Info Grid (Stacked/Comparison) -->
        <div class="row g-3">
            <!-- Check In Info -->
            <div class="col-6">
                <div class="card border rounded-3 p-3 h-100 bg-white shadow-sm">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <div class="avatar-sm bg-soft-success text-success rounded-circle d-flex align-items-center justify-content-center" style="width: 22px; height: 22px;">
                            <i class="feather-log-in fs-11"></i>
                        </div>
                        <span class="fw-bold text-dark fs-12">Check In</span>
                    </div>
                    <div class="mb-2">
                        <span class="text-muted fs-10 d-block">TIME</span>
                        <span class="fw-bold text-dark fs-12" id="detail-drawer-checkin-time">-</span>
                    </div>
                    <div>
                        <span class="text-muted fs-10 d-block mb-1">SELFIE</span>
                        <div class="bg-light border border-dashed rounded p-2 d-flex align-items-center justify-content-center" style="height: 100px;">
                            <img id="detail-drawer-checkin-selfie" src="" class="rounded border shadow-sm" style="max-height: 85px; max-width: 100%; object-fit: cover; display: none; transform: scaleX(-1);">
                            <span id="detail-drawer-checkin-selfie-none" class="text-muted fs-10 text-center"><i class="feather-image d-block mb-0.5 fs-12"></i> None</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Check Out Info -->
            <div class="col-6">
                <div class="card border rounded-3 p-3 h-100 bg-white shadow-sm">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <div class="avatar-sm bg-soft-danger text-danger rounded-circle d-flex align-items-center justify-content-center" style="width: 22px; height: 22px;">
                            <i class="feather-log-out fs-11"></i>
                        </div>
                        <span class="fw-bold text-dark fs-12">Check Out</span>
                    </div>
                    <div class="mb-2">
                        <span class="text-muted fs-10 d-block">TIME</span>
                        <span class="fw-bold text-dark fs-12" id="detail-drawer-checkout-time">-</span>
                    </div>
                    <div>
                        <span class="text-muted fs-10 d-block mb-1">SELFIE</span>
                        <div class="bg-light border border-dashed rounded p-2 d-flex align-items-center justify-content-center" style="height: 100px;">
                            <img id="detail-drawer-checkout-selfie" src="" class="rounded border shadow-sm" style="max-height: 85px; max-width: 100%; object-fit: cover; display: none; transform: scaleX(-1);">
                            <span id="detail-drawer-checkout-selfie-none" class="text-muted fs-10 text-center"><i class="feather-image d-block mb-0.5 fs-12"></i> None</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-ui.drawer>

<script>
    // Digital Clock Update for UI Widget - tracks shift duration
    function startAttendanceClock() {
        const clockEl = document.getElementById('attendance-clock');
        const dateEl = document.getElementById('attendance-date');
        
        if (!clockEl || !dateEl) return;
        
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        dateEl.textContent = new Date().toLocaleDateString('en-US', options);
        
        @if(!$todayAttendance)
            // Case 1: Not checked in yet
            clockEl.textContent = "00:00:00";
        @elseif($todayAttendance && !$todayAttendance->check_out)
            // Case 2: Checked in, timer running
            const checkInTime = new Date("{{ $todayAttendance->check_in->toIso8601String() }}");
            
            const updateTimer = () => {
                const now = new Date();
                let diffMs = now - checkInTime;
                if (diffMs < 0) diffMs = 0;
                
                const totalSecs = Math.floor(diffMs / 1000);
                const hrs = String(Math.floor(totalSecs / 3600)).padStart(2, '0');
                const mins = String(Math.floor((totalSecs % 3600) / 60)).padStart(2, '0');
                const secs = String(totalSecs % 60).padStart(2, '0');
                
                clockEl.textContent = `${hrs}:${mins}:${secs}`;
            };
            
            updateTimer();
            setInterval(updateTimer, 1000);
        @else
            // Case 3: Checked out, show static total duration
            const checkInTime = new Date("{{ $todayAttendance->check_in->toIso8601String() }}");
            const checkOutTime = new Date("{{ $todayAttendance->check_out->toIso8601String() }}");
            
            let diffMs = checkOutTime - checkInTime;
            if (diffMs < 0) diffMs = 0;
            
            const totalSecs = Math.floor(diffMs / 1000);
            const hrs = String(Math.floor(totalSecs / 3600)).padStart(2, '0');
            const mins = String(Math.floor((totalSecs % 3600) / 60)).padStart(2, '0');
            const secs = String(totalSecs % 60).padStart(2, '0');
            
            clockEl.textContent = `${hrs}:${mins}:${secs}`;
        @endif
    }
    
    document.addEventListener('DOMContentLoaded', function() {
        startAttendanceClock();

        const selfieRequired = {{ $selfieRequired ? 'true' : 'false' }};
        let webcamStream = null;
        let snappedSelfieData = null;
        let targetForm = null;

        // Initialize Bootstrap Modal instances
        const selfieModalEl = document.getElementById('selfieCaptureModal');
        document.body.appendChild(selfieModalEl);
        const bootstrapModal = new bootstrap.Modal(selfieModalEl);

        const confirmModalEl = document.getElementById('attendanceConfirmModal');
        document.body.appendChild(confirmModalEl);
        const confirmModal = new bootstrap.Modal(confirmModalEl);

        // Helper to show styled action/alert modals instead of standard browser alerts
        const showConfirmModal = (title, message, iconClass, isAlertOnly, onConfirmCallback) => {
            if (typeof Swal !== 'undefined') {
                const primaryColor = getComputedStyle(document.documentElement).getPropertyValue('--bs-primary').trim() || '#3085d6';
                if (isAlertOnly) {
                    Swal.fire({
                        title: title,
                        text: message,
                        icon: iconClass === 'alert-triangle' || iconClass === 'alert-circle' ? 'warning' : (iconClass === 'map-pin' ? 'error' : 'info'),
                        confirmButtonText: 'OK',
                        customClass: {
                            confirmButton: 'btn btn-sm btn-primary fw-bold px-4 py-2'
                        },
                        buttonsStyling: false
                    });
                } else {
                    Swal.fire({
                        title: title,
                        text: message,
                        icon: iconClass === 'log-in' || iconClass === 'log-out' ? 'question' : 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Yes, Proceed',
                        cancelButtonText: 'Cancel',
                        customClass: {
                            confirmButton: 'btn btn-sm btn-primary fw-bold px-3 py-2 me-2',
                            cancelButton: 'btn btn-sm btn-light border fw-bold px-3 py-2'
                        },
                        buttonsStyling: false
                    }).then((result) => {
                        if (result.isConfirmed && onConfirmCallback) {
                            setTimeout(onConfirmCallback, 350);
                        }
                    });
                }
                return;
            }

            // Fallback to Bootstrap Modal if Swal is not loaded
            document.getElementById('confirm-modal-title').textContent = title;
            document.getElementById('confirm-modal-message').textContent = message;
            
            const iconEl = document.getElementById('confirm-modal-icon');
            iconEl.className = `feather-${iconClass} fs-26`;
            
            const submitBtn = document.getElementById('confirm-modal-submit-btn');
            const cancelBtn = document.getElementById('confirm-modal-cancel-btn');
            
            if (isAlertOnly) {
                submitBtn.style.display = 'none';
                cancelBtn.textContent = 'OK';
                cancelBtn.className = 'btn btn-sm btn-primary px-3';
            } else {
                submitBtn.style.display = 'inline-block';
                submitBtn.className = 'btn btn-sm btn-primary fw-bold px-3';
                cancelBtn.textContent = 'Cancel';
                cancelBtn.className = 'btn btn-sm btn-light border px-3';
            }
            
            const newSubmitBtn = submitBtn.cloneNode(true);
            submitBtn.parentNode.replaceChild(newSubmitBtn, submitBtn);
            
            if (!isAlertOnly && onConfirmCallback) {
                newSubmitBtn.addEventListener('click', function() {
                    confirmModal.hide();
                    onConfirmCallback();
                });
            }
            
            if (confirmModalEl.classList.contains('show') || confirmModalEl.classList.contains('collapsing')) {
                confirmModal.hide();
                setTimeout(() => {
                    confirmModal.show();
                }, 350);
            } else {
                confirmModal.show();
            }
        };

        const video = document.getElementById('modal-webcam-video');
        const canvas = document.getElementById('modal-webcam-canvas');
        const previewImg = document.getElementById('modal-selfie-preview-img');
        
        const btnSnap = document.getElementById('btn-modal-snap');
        const btnRetake = document.getElementById('btn-modal-retake');
        const btnConfirm = document.getElementById('btn-modal-confirm');

        // Function to start camera
        const startWebcam = () => {
            snappedSelfieData = null;
            video.classList.remove('d-none');
            previewImg.classList.add('d-none');
            btnSnap.classList.remove('d-none');
            btnRetake.classList.add('d-none');
            btnConfirm.classList.add('d-none');

            navigator.mediaDevices.getUserMedia({ video: { width: 320, height: 240, facingMode: 'user' } })
                .then(function(stream) {
                    webcamStream = stream;
                    video.srcObject = stream;
                    video.play();
                })
                .catch(function(err) {
                    console.error("Camera access error:", err);
                    showConfirmModal("Camera Access Error", "Webcam access is required to clock in/out. Please grant camera permissions.", "camera", true);
                    bootstrapModal.hide();
                });
        };

        // Function to stop camera tracks
        const stopWebcam = () => {
            if (webcamStream) {
                webcamStream.getTracks().forEach(track => track.stop());
                webcamStream = null;
            }
        };

        // Modal Lifecycle events
        selfieModalEl.addEventListener('shown.bs.modal', function () {
            startWebcam();
        });

        selfieModalEl.addEventListener('hidden.bs.modal', function () {
            stopWebcam();
        });

        // Snap Photo Button Click
        btnSnap.addEventListener('click', function() {
            if (!webcamStream) {
                showConfirmModal("Camera Inactive", "Camera is not active.", "alert-triangle", true);
                return;
            }
            const context = canvas.getContext('2d');
            // Draw mirrored image
            context.translate(320, 0);
            context.scale(-1, 1);
            context.drawImage(video, 0, 0, 320, 240);
            context.setTransform(1, 0, 0, 1, 0, 0);

            snappedSelfieData = canvas.toDataURL('image/jpeg');
            
            // Show preview & stop camera immediately to turn off the hardware light
            previewImg.src = snappedSelfieData;
            video.classList.add('d-none');
            previewImg.classList.remove('d-none');
            
            btnSnap.classList.add('d-none');
            btnRetake.classList.remove('d-none');
            btnConfirm.classList.remove('d-none');

            stopWebcam();
        });

        // Retake Button Click
        btnRetake.addEventListener('click', function() {
            startWebcam();
        });

        // Confirm Button Click
        btnConfirm.addEventListener('click', function() {
            if (!snappedSelfieData) {
                showConfirmModal("Selfie Required", "Please snap a selfie first.", "camera", true);
                return;
            }

            if (targetForm) {
                const selfieInput = targetForm.querySelector('input[name="selfie"]');
                if (selfieInput) {
                    selfieInput.value = snappedSelfieData;
                }
                bootstrapModal.hide();
                collectLocationAndSubmit(targetForm);
            }
        });

        // Collect coordinates and submit form
        const collectLocationAndSubmit = (form) => {
            const latField = form.querySelector('input[name="latitude"]');
            const lngField = form.querySelector('input[name="longitude"]');
            const btnSubmit = form.querySelector('button[type="submit"]');
            const originalHtml = btnSubmit.innerHTML;

            btnSubmit.disabled = true;
            btnSubmit.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Fetching location...';

            if (!navigator.geolocation) {
                showConfirmModal("Location Error", "Geolocation is not supported by your browser.", "alert-triangle", true);
                btnSubmit.disabled = false;
                btnSubmit.innerHTML = originalHtml;
                return;
            }

            navigator.geolocation.getCurrentPosition(
                function(position) {
                    latField.value = position.coords.latitude.toFixed(8);
                    lngField.value = position.coords.longitude.toFixed(8);
                    btnSubmit.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...';
                    form.submit();
                },
                function(error) {
                    let errorMsg = 'Unable to verify location. Please ensure location services are enabled.';
                    if (error.code === error.PERMISSION_DENIED) {
                        errorMsg = 'Location access is required to check in/out. Please allow browser location permissions.';
                    }
                    showConfirmModal("Location Verification Failed", errorMsg, "map-pin", true);
                    btnSubmit.disabled = false;
                    btnSubmit.innerHTML = originalHtml;
                },
                { enableHighAccuracy: true, timeout: 10000 }
            );
        };

        // Form Submit Interceptors
        const checkInForm = document.getElementById('check-in-form');
        if (checkInForm) {
            checkInForm.addEventListener('submit', function(e) {
                if (!selfieRequired) {
                    if (document.getElementById('check-in-lat').value && document.getElementById('check-in-lng').value) {
                        return true;
                    }
                    e.preventDefault();
                    collectLocationAndSubmit(checkInForm);
                    return;
                }

                const selfieInput = document.getElementById('check-in-selfie');
                if (selfieInput && selfieInput.value) {
                    return true;
                }

                e.preventDefault();
                targetForm = checkInForm;
                bootstrapModal.show();
            });
        }

        const checkOutForm = document.getElementById('check-out-form');
        if (checkOutForm) {
            checkOutForm.addEventListener('submit', function(e) {
                if (!selfieRequired) {
                    if (document.getElementById('check-out-lat').value && document.getElementById('check-out-lng').value) {
                        return true;
                    }
                    e.preventDefault();
                    collectLocationAndSubmit(checkOutForm);
                    return;
                }

                const selfieInput = document.getElementById('check-out-selfie');
                if (selfieInput && selfieInput.value) {
                    return true;
                }

                e.preventDefault();
                targetForm = checkOutForm;
                bootstrapModal.show();
            });
        }

        // Background coordinates tracking
        const activeAttendanceId = {{ $todayAttendance ? $todayAttendance->id : 'null' }};
        const currentEmployeeId = {{ $employee->id }};
        const isWorking = {{ ($todayAttendance && $todayAttendance->isWorking()) ? 'true' : 'false' }};
        const trackingEnabled = {{ $trackingEnabled ? 'true' : 'false' }};
        const trackLocationRoute = "{{ route('hrms.attendance.track-location') }}";
        const csrfToken = "{{ csrf_token() }}";

        let currentIsOnBreak = {{ ($todayAttendance && $todayAttendance->isOnBreak()) ? 'true' : 'false' }};
        const breakInRoute = "{{ $todayAttendance ? route('hrms.attendance.break-in', $todayAttendance->id) : '' }}";
        const breakOutRoute = "{{ $todayAttendance ? route('hrms.attendance.break-out', $todayAttendance->id) : '' }}";

        // Intercept manual Break Out form submission to verify location is active
        const breakOutForm = document.getElementById('break-out-form');
        if (breakOutForm) {
            breakOutForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const btnSubmit = breakOutForm.querySelector('button[type="submit"]');
                const originalHtml = btnSubmit.innerHTML;

                btnSubmit.disabled = true;
                btnSubmit.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Verifying location...';

                if (!navigator.geolocation) {
                    showConfirmModal("Location Error", "Geolocation is not supported by your browser.", "alert-triangle", true);
                    btnSubmit.disabled = false;
                    btnSubmit.innerHTML = originalHtml;
                    return;
                }

                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        breakOutForm.submit();
                    },
                    function(error) {
                        let errorMsg = 'Cannot end break. Your location services are disabled or blocked. Please enable location services to end break.';
                        if (error.code === error.PERMISSION_DENIED) {
                            errorMsg = 'Cannot end break. Location permission has been revoked. Please allow location permissions to end break.';
                        }
                        showConfirmModal("Location Required", errorMsg, "map-pin", true);
                        btnSubmit.disabled = false;
                        btnSubmit.innerHTML = originalHtml;
                    },
                    { enableHighAccuracy: true, timeout: 10000 }
                );
            });
        }

        if (isWorking && trackingEnabled) {
            const trackingIntervalMinutes = {{ $trackingIntervalMinutes }};
            const trackingIntervalMs = trackingIntervalMinutes * 60 * 1000;

            const sendTrackRequest = () => {
                if (!navigator.geolocation) return;

                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        const payload = {
                            employee_id: currentEmployeeId,
                            latitude: position.coords.latitude,
                            longitude: position.coords.longitude
                        };

                        // Check if we were on an auto location break, and now location is back ON!
                        if (currentIsOnBreak && sessionStorage.getItem('auto_location_break') === 'true') {
                            fetch(breakOutRoute, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': csrfToken,
                                    'Accept': 'application/json'
                                }
                            })
                            .then(res => res.json())
                            .then(data => {
                                currentIsOnBreak = false;
                                sessionStorage.removeItem('auto_location_break');
                                
                                showConfirmModal(
                                    "Shift Resumed",
                                    "Location services restored. Your automatic break has ended, and your shift has resumed.",
                                    "check-circle",
                                    true
                                );
                                
                                setTimeout(() => {
                                    window.location.reload();
                                }, 3000);
                            })
                            .catch(err => console.error("Auto break-out request failed:", err));
                            
                            return;
                        }

                        fetch(trackLocationRoute, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken
                            },
                            body: JSON.stringify(payload)
                        })
                        .then(res => res.json())
                        .then(data => {
                            const alertBoxId = 'geofence-alert-box';
                            let alertBox = document.getElementById(alertBoxId);

                            if (data.status === 'out_of_bounds') {
                                if (!alertBox) {
                                    alertBox = document.createElement('div');
                                    alertBox.id = alertBoxId;
                                    alertBox.className = 'alert alert-danger shadow-lg border-0 d-flex align-items-center justify-content-between p-3 position-fixed start-50 translate-middle-x';
                                    alertBox.style.cssText = 'top: 20px; z-index: 1060; width: 90%; max-width: 500px; border-radius: 12px; margin: 0;';
                                    alertBox.innerHTML = `
                                        <div class="d-flex align-items-center gap-2">
                                            <i class="feather-alert-triangle fs-18 text-white"></i>
                                            <span class="fs-12 text-white">
                                                <strong>Geofence Warning!</strong> You are currently outside your designated geofence radius (Distance: <strong id="geofence-distance-val">${data.distance}</strong>m, allowed: ${data.radius}m).
                                            </span>
                                        </div>
                                    `;
                                    document.body.appendChild(alertBox);
                                } else {
                                    const distanceVal = document.getElementById('geofence-distance-val');
                                    if (distanceVal) distanceVal.textContent = data.distance;
                                }
                            } else {
                                if (alertBox) {
                                    alertBox.remove();
                                }
                            }
                        })
                        .catch(err => console.error("Tracking request failed:", err));
                    },
                    function(error) {
                        console.error("Tracking location capture error:", error);
                        
                        // Automatically trigger break-in if location is disabled/blocked during shift
                        if (!currentIsOnBreak) {
                            fetch(breakInRoute, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': csrfToken,
                                    'Accept': 'application/json'
                                }
                            })
                            .then(res => res.json())
                            .then(data => {
                                currentIsOnBreak = true;
                                sessionStorage.setItem('auto_location_break', 'true');
                                
                                showConfirmModal(
                                    "Auto Break Triggered",
                                    "Your location services are disabled or blocked. As location is required, you have been automatically put on Break. Enable location services to resume shift.",
                                    "clock",
                                    true
                                );
                                
                                setTimeout(() => {
                                    window.location.reload();
                                }, 3000);
                            })
                            .catch(err => console.error("Auto break-in request failed:", err));
                        } else {
                            let errorMsg = 'Your location services are disabled or blocked. Please turn on location services to resume your shift.';
                            if (error.code === error.PERMISSION_DENIED) {
                                errorMsg = 'Location permission has been revoked. Please allow location permissions in your browser settings to resume your shift.';
                            }
                            showConfirmModal("Location Services Disabled", errorMsg, "alert-triangle", true);
                        }
                    },
                    { enableHighAccuracy: true, timeout: 10000 }
                );
            };

            // Run immediately on load, and then set interval
            sendTrackRequest();
            setInterval(sendTrackRequest, trackingIntervalMs);
        }
    });

    // Map instance for detail drawer
    let detailDrawerMapObj = null;
    let detailDrawerMarkersGroup = null;

    @php
        $officeLat = $rule ? $rule->office_latitude  : null;
        $officeLng = $rule ? $rule->office_longitude : null;
        $officeRad = ($rule && $rule->office_radius) ? (int)$rule->office_radius : 200;
        $wfhLat    = $employee->wfh_latitude  ?? null;
        $wfhLng    = $employee->wfh_longitude ?? null;
        $wfhRad    = ($rule && $rule->wfh_tracking_meters) ? (int)$rule->wfh_tracking_meters : 200;
    @endphp

    function viewAttendanceDetailDrawer(btn) {
        const searchInput = document.getElementById('detail_drawer_map_search');
        if (searchInput) {
            searchInput.value = '';
            searchInput.disabled = false;
            searchInput.placeholder = 'Search address or subarea (Press Enter)...';
        }

        const date = btn.getAttribute('data-date');
        const status = btn.getAttribute('data-status');
        const locationType = btn.getAttribute('data-location-type') || 'office';
        
        const checkinTime = btn.getAttribute('data-check-in-time');
        const checkoutTime = btn.getAttribute('data-check-out-time');
        
        const checkinSelfie = btn.getAttribute('data-check-in-selfie');
        const checkoutSelfie = btn.getAttribute('data-check-out-selfie');
        
        const checkinLat = btn.getAttribute('data-check-in-lat');
        const checkinLng = btn.getAttribute('data-check-in-lng');
        const checkoutLat = btn.getAttribute('data-check-out-lat');
        const checkoutLng = btn.getAttribute('data-check-out-lng');

        // Populate header & status details
        document.getElementById('detail-drawer-date').textContent = date;
        document.getElementById('detail-drawer-status').textContent = status;
        document.getElementById('detail-drawer-location').textContent = locationType.toUpperCase();
        
        // Update badge color styles dynamically based on status
        const statusBadge = document.getElementById('detail-drawer-status');
        statusBadge.className = 'badge px-3 py-1 fs-11 rounded-pill fw-bold';
        if (status.toLowerCase() === 'present') {
            statusBadge.classList.add('bg-soft-success', 'text-success');
        } else if (status.toLowerCase() === 'late') {
            statusBadge.classList.add('bg-soft-warning', 'text-warning');
        } else if (status.toLowerCase() === 'half day' || status.toLowerCase() === 'half_day') {
            statusBadge.classList.add('bg-soft-danger', 'text-danger');
        } else {
            statusBadge.classList.add('bg-soft-primary', 'text-primary');
        }

        // Check In details
        document.getElementById('detail-drawer-checkin-time').textContent = checkinTime;
        const imgCheckin = document.getElementById('detail-drawer-checkin-selfie');
        const noneCheckin = document.getElementById('detail-drawer-checkin-selfie-none');
        if (checkinSelfie) {
            imgCheckin.src = checkinSelfie;
            imgCheckin.style.display = 'block';
            noneCheckin.style.display = 'none';
        } else {
            imgCheckin.src = '';
            imgCheckin.style.display = 'none';
            noneCheckin.style.display = 'block';
        }

        // Check Out details
        document.getElementById('detail-drawer-checkout-time').textContent = checkoutTime;
        const imgCheckout = document.getElementById('detail-drawer-checkout-selfie');
        const noneCheckout = document.getElementById('detail-drawer-checkout-selfie-none');
        if (checkoutSelfie) {
            imgCheckout.src = checkoutSelfie;
            imgCheckout.style.display = 'block';
            noneCheckout.style.display = 'none';
        } else {
            imgCheckout.src = '';
            imgCheckout.style.display = 'none';
            noneCheckout.style.display = 'block';
        }

        // Parse intermediate 15-minute tracking location logs
        const locationLogsStr = btn.getAttribute('data-location-logs') || '[]';
        let locationLogs = [];
        try {
            locationLogs = JSON.parse(locationLogsStr);
        } catch(e) {
            console.error("Failed to parse location logs:", e);
        }

        // Show/hide map div based on coordinates presence
        const mapWrap = document.getElementById('detail-drawer-map-wrap');
        const mapNone = document.getElementById('detail-drawer-map-none');
        
        const hasCheckinCoords = checkinLat && checkinLng && parseFloat(checkinLat) !== 0 && parseFloat(checkinLng) !== 0;
        const hasCheckoutCoords = checkoutLat && checkoutLng && parseFloat(checkoutLat) !== 0 && parseFloat(checkoutLng) !== 0;
        const hasLocationLogs = locationLogs && locationLogs.length > 0;
        // Geofence circles from server config (always true if coords are set in rules/employee)
        const hasGeofenceCoords = {{ ($officeLat && $officeLng) || ($wfhLat && $wfhLng) ? 'true' : 'false' }};

        if (hasCheckinCoords || hasCheckoutCoords || hasLocationLogs || hasGeofenceCoords) {
            mapWrap.style.display = 'block';
            mapNone.style.display = 'none';
        } else {
            mapWrap.style.display = 'none';
            mapNone.style.display = 'block';
        }

        // Show Drawer
        const drawerEl = document.getElementById('attendanceRecordDetailDrawer');
        const bootstrapDrawer = bootstrap.Offcanvas.getOrCreateInstance(drawerEl);
        bootstrapDrawer.show();

        // Render Leaflet map
        setTimeout(() => {
            if (hasCheckinCoords || hasCheckoutCoords || hasLocationLogs || hasGeofenceCoords) {
                // Initialize map if not yet initialized
                if (!detailDrawerMapObj) {
                    @if($officeLat && $officeLng)
                    detailDrawerMapObj = L.map('detail-drawer-map').setView([{{ (float)$officeLat }}, {{ (float)$officeLng }}], 13);
                    @elseif($wfhLat && $wfhLng)
                    detailDrawerMapObj = L.map('detail-drawer-map').setView([{{ (float)$wfhLat }}, {{ (float)$wfhLng }}], 13);
                    @else
                    detailDrawerMapObj = L.map('detail-drawer-map').setView([20.5937, 78.9629], 5);
                    @endif
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        maxZoom: 19,
                        attribution: '© OpenStreetMap'
                    }).addTo(detailDrawerMapObj);
                    detailDrawerMarkersGroup = L.featureGroup().addTo(detailDrawerMapObj);

                    // Geocoding search logic for details drawer map
                    const searchInput = document.getElementById('detail_drawer_map_search');
                    if (searchInput) {
                        const performDetailSearch = () => {
                            const query = searchInput.value;
                            if (!query) return;

                            searchInput.disabled = true;
                            searchInput.placeholder = 'Searching...';

                            // ArcGIS World Geocoding (primary — high accuracy for streets, subareas, landmarks)
                            fetch(`https://geocode.arcgis.com/arcgis/rest/services/World/GeocodeServer/findAddressCandidates?f=json&singleLine=${encodeURIComponent(query)}&maxLocations=1`)
                                .then(res => res.json())
                                .then(data => {
                                    if (data && data.candidates && data.candidates.length > 0) {
                                        const lat = parseFloat(data.candidates[0].location.y);
                                        const lng = parseFloat(data.candidates[0].location.x);
                                        if (detailDrawerMapObj) {
                                            detailDrawerMapObj.setView([lat, lng], 15);
                                        }
                                        searchInput.disabled = false;
                                        searchInput.placeholder = 'Search address or subarea (Press Enter)...';
                                    } else {
                                        // Fallback to OSM Nominatim
                                        fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}`)
                                            .then(res2 => res2.json())
                                            .then(data2 => {
                                                if (data2 && data2.length > 0) {
                                                    const lat = parseFloat(data2[0].lat);
                                                    const lng = parseFloat(data2[0].lon);
                                                    if (detailDrawerMapObj) {
                                                        detailDrawerMapObj.setView([lat, lng], 15);
                                                    }
                                                } else {
                                                    alert("Location not found. Please try a different query.");
                                                }
                                                searchInput.disabled = false;
                                                searchInput.placeholder = 'Search address or subarea (Press Enter)...';
                                            })
                                            .catch(() => {
                                                alert("Location not found. Please try a different query.");
                                                searchInput.disabled = false;
                                                searchInput.placeholder = 'Search address or subarea (Press Enter)...';
                                            });
                                    }
                                })
                                .catch(err => {
                                    console.error("Geocoding error:", err);
                                    searchInput.disabled = false;
                                    searchInput.placeholder = 'Search address or subarea (Press Enter)...';
                                });
                        };

                        searchInput.addEventListener('keypress', function(e) {
                            if (e.key === 'Enter') {
                                e.preventDefault();
                                performDetailSearch();
                            }
                        });
                    }
                } else {
                    // Clear existing markers
                    detailDrawerMarkersGroup.clearLayers();
                }

                const pathLatLngs = [];

                // Add Check-In Marker (Green icon style)
                if (hasCheckinCoords) {
                    const checkinLatVal = parseFloat(checkinLat);
                    const checkinLngVal = parseFloat(checkinLng);
                    const checkinLatLng = [checkinLatVal, checkinLngVal];
                    pathLatLngs.push(checkinLatLng);
                    
                    const checkinIcon = L.icon({
                        iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-green.png',
                        shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/images/marker-shadow.png',
                        iconSize: [25, 41],
                        iconAnchor: [12, 41],
                        popupAnchor: [1, -34],
                        shadowSize: [41, 41]
                    });

                    L.marker(checkinLatLng, { icon: checkinIcon })
                        .addTo(detailDrawerMarkersGroup)
                        .bindPopup(`<b>Check In Point</b><br>Time: ${checkinTime}<br>Lat: ${checkinLatVal.toFixed(6)}<br>Lng: ${checkinLngVal.toFixed(6)}`);
                }

                // Add intermediate 15-minute tracking location logs as circle markers
                if (hasLocationLogs) {
                    locationLogs.forEach(log => {
                        if (log.lat && log.lng) {
                            const latVal = parseFloat(log.lat);
                            const lngVal = parseFloat(log.lng);
                            const logLatLng = [latVal, lngVal];
                            pathLatLngs.push(logLatLng);

                            L.circleMarker(logLatLng, {
                                radius: 6,
                                fillColor: '#3b82f6',
                                color: '#ffffff',
                                weight: 2,
                                opacity: 1,
                                fillOpacity: 0.8
                            }).addTo(detailDrawerMarkersGroup)
                              .bindPopup(`<b>Location Log (15m Tracking)</b><br>Time: ${log.time}<br>Lat: ${latVal.toFixed(6)}<br>Lng: ${lngVal.toFixed(6)}`);
                        }
                    });
                }

                // Add Check-Out Marker (Red icon style)
                if (hasCheckoutCoords) {
                    const checkoutLatVal = parseFloat(checkoutLat);
                    const checkoutLngVal = parseFloat(checkoutLng);
                    const checkoutLatLng = [checkoutLatVal, checkoutLngVal];
                    pathLatLngs.push(checkoutLatLng);

                    const checkoutIcon = L.icon({
                        iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png',
                        shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/images/marker-shadow.png',
                        iconSize: [25, 41],
                        iconAnchor: [12, 41],
                        popupAnchor: [1, -34],
                        shadowSize: [41, 41]
                    });

                    L.marker(checkoutLatLng, { icon: checkoutIcon })
                        .addTo(detailDrawerMarkersGroup)
                        .bindPopup(`<b>Check Out Point</b><br>Time: ${checkoutTime}<br>Lat: ${checkoutLatVal.toFixed(6)}<br>Lng: ${checkoutLngVal.toFixed(6)}`);
                }

                // Add Polyline connecting all path points if we have 2 or more coordinates
                if (pathLatLngs.length >= 2) {
                    L.polyline(pathLatLngs, {
                        color: '#4f46e5', // Premium Indigo path color
                        weight: 4,
                        opacity: 0.8,
                        dashArray: '8, 8', // Dashed line to show direction/flow
                        lineJoin: 'round'
                    }).addTo(detailDrawerMarkersGroup);
                }

                // Draw geofence threshold radius circles
                @if($officeLat && $officeLng)
                const officeGeofenceLat = {{ (float)$officeLat }};
                const officeGeofenceLng = {{ (float)$officeLng }};
                const officeGeofenceRadius = {{ $officeRad }};
                L.circle([officeGeofenceLat, officeGeofenceLng], {
                    radius: officeGeofenceRadius,
                    color: '#4f46e5',
                    weight: 2,
                    fillColor: '#4f46e5',
                    fillOpacity: 0.08,
                    dashArray: '6, 4'
                }).addTo(detailDrawerMarkersGroup)
                  .bindPopup(`<b>Office Geofence</b><br>Lat: ${officeGeofenceLat.toFixed(6)}<br>Lng: ${officeGeofenceLng.toFixed(6)}<br>Radius: ${officeGeofenceRadius}m`);
                @endif

                @if($wfhLat && $wfhLng)
                const wfhGeofenceLat = {{ (float)$wfhLat }};
                const wfhGeofenceLng = {{ (float)$wfhLng }};
                const wfhGeofenceRadius = {{ $wfhRad }};
                L.circle([wfhGeofenceLat, wfhGeofenceLng], {
                    radius: wfhGeofenceRadius,
                    color: '#10b981',
                    weight: 2,
                    fillColor: '#10b981',
                    fillOpacity: 0.08,
                    dashArray: '6, 4'
                }).addTo(detailDrawerMarkersGroup)
                  .bindPopup(`<b>WFH Geofence</b><br>Lat: ${wfhGeofenceLat.toFixed(6)}<br>Lng: ${wfhGeofenceLng.toFixed(6)}<br>Radius: ${wfhGeofenceRadius}m`);
                @endif

                // Set View/Bounds
                if (pathLatLngs.length > 0) {
                    const bounds = detailDrawerMarkersGroup.getBounds();
                    if (pathLatLngs.length >= 2) {
                        detailDrawerMapObj.fitBounds(bounds, { padding: [30, 30] });
                    } else {
                        detailDrawerMapObj.setView(pathLatLngs[0], 15);
                    }
                } else if (detailDrawerMarkersGroup.getLayers().length > 0) {
                    // Only geofence circles — center map on available geofence bounds
                    try {
                        detailDrawerMapObj.fitBounds(detailDrawerMarkersGroup.getBounds(), { padding: [20, 20] });
                    } catch(e) {
                        @if($officeLat && $officeLng)
                        detailDrawerMapObj.setView([{{ (float)$officeLat }}, {{ (float)$officeLng }}], 15);
                        @elseif($wfhLat && $wfhLng)
                        detailDrawerMapObj.setView([{{ (float)$wfhLat }}, {{ (float)$wfhLng }}], 15);
                        @endif
                    }
                }
                
                detailDrawerMapObj.invalidateSize();
            }
        }, 300);
    }

    // Attach invalidation listener on drawer open
    document.addEventListener('DOMContentLoaded', function() {
        const drawerEl = document.getElementById('attendanceRecordDetailDrawer');
        if (drawerEl) {
            document.body.appendChild(drawerEl);
            drawerEl.addEventListener('shown.bs.offcanvas', function() {
                if (detailDrawerMapObj) {
                    detailDrawerMapObj.invalidateSize();
                }
            });
        }
    });
</script>
