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
                                    <th class="pe-4">Status</th>
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
                                        <td class="pe-4">
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
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted fs-13">No attendance logs found.</td>
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
</script>
