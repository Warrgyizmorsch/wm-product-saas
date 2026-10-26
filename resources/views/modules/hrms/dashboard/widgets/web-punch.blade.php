<div class="card border-0 shadow-sm rounded-4 mb-0 bg-white h-100 d-flex flex-column" style="border: 1px solid #edf2f7 !important; border-radius: 16px !important; box-shadow: 0 1px 3px rgba(0,0,0,0.03), 0 6px 16px rgba(0,0,0,0.02) !important;">
    <div class="card-body p-3 p-md-3.5 d-flex flex-column justify-content-between h-100 gap-2">
        <!-- Header row: Web Punch Pill + Work Location Pill + Shift details + Top Right Action Pills -->
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 flex-shrink-0">
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <span class="badge px-2.5 py-1 fs-11 fw-semibold rounded-pill d-inline-flex align-items-center" style="background: rgba(var(--bs-primary-rgb, 99, 55, 250), 0.1); color: var(--bs-primary, #6337fa); border: 1px solid rgba(var(--bs-primary-rgb, 99, 55, 250), 0.2); font-weight: 600; gap: 6px;">
                    <i class="feather-clock fs-12" style="color: var(--bs-primary, #6337fa); margin-bottom: 2px; margin-right: 2px;"></i> Web Punch Station
                </span>
                @php
                    $locType = $workLocation ?? ($myTodayAttendance->location_type ?? 'office');
                    $locLabel = $formattedWorkLocation ?? (strtolower($locType) === 'wfh' ? 'WFH' : (in_array(strtolower($locType), ['onsite', 'on-site', 'site']) ? 'On-Site' : 'Office'));
                    $locPillClass = 'bg-soft-primary text-primary';
                    $locIcon = 'feather-briefcase';
                    if (strtolower($locType) === 'wfh') {
                        $locPillClass = 'bg-soft-success text-success';
                        $locIcon = 'feather-home';
                    } elseif (in_array(strtolower($locType), ['onsite', 'on-site', 'site'])) {
                        $locPillClass = 'bg-soft-warning text-warning';
                        $locIcon = 'feather-map-pin';
                    }
                @endphp
                <span class="badge {{ $locPillClass }} px-2.5 py-1 fs-11 fw-bold rounded-pill d-inline-flex align-items-center border-0" style="gap: 5px;">
                    <i class="{{ $locIcon }} fs-11"></i> {{ $locLabel }}
                </span>
                @php
                    $shiftRawName = $myShiftDetails['name'] ?? 'General Day Shift';
                    $cleanShiftName = trim(preg_replace('/\s*\([^)]*\)/', '', $shiftRawName));
                    $shiftTiming = $myShiftDetails['timing'] ?? '09:00 AM - 06:00 PM';
                @endphp
                <span class="fs-12 fw-normal ms-1" style="color: #64748b !important;">
                    • {{ $cleanShiftName }} ({{ $shiftTiming }})
                </span>
            </div>
            <div class="d-flex align-items-center gap-2 ms-auto">
                <a href="{{ \Illuminate\Support\Facades\Route::has('hrms.wfh.index') ? route('hrms.wfh.index') : url('/hrms/wfh') }}" class="btn fw-bold text-uppercase fs-11 px-2.5 py-1 rounded-pill d-inline-flex align-items-center text-nowrap" style="background: #f8fafc; border: 1px solid #e2e8f0; color: #334155; font-size: 11px; font-weight: 700; letter-spacing: 0.03em; gap: 6px;">
                    <i class="feather-home fs-12" style="color: #0d9488; margin-bottom: 2px; margin-right: 2px;"></i> REQUEST WFH
                </a>
                <a href="{{ \Illuminate\Support\Facades\Route::has('hrms.attendance.myAttendance') ? route('hrms.attendance.myAttendance') : url('/hrms/attendance/my-attendance') }}" class="btn fw-bold text-uppercase fs-11 px-2.5 py-1 rounded-pill d-inline-flex align-items-center text-nowrap" style="background: #f8fafc; border: 1px solid #e2e8f0; color: #334155; font-size: 11px; font-weight: 700; letter-spacing: 0.03em; gap: 6px;">
                    <i class="feather-clock fs-12" style="color: #f59e0b; margin-bottom: 2px; margin-right: 2px;"></i> REGULARIZE PUNCH
                </a>
            </div>
        </div>

        @if(!empty($trackingConfig['configuration_error']))
            <div class="alert alert-danger py-2 mb-0" role="alert">
                Live location tracking is enabled, but its interval is missing or invalid in the applicable attendance rule. Please contact HR to complete the rule settings.
            </div>
        @endif

        <!-- Live GPS Warning Banner (Pillar 2: Triggered automatically when device GPS is toggled off mid-shift) -->
        <div id="web_punch_gps_warning" class="alert alert-warning py-2 px-3 mb-1 rounded-3 fs-11 d-none" style="background: #fffbeb; color: #b45309; border: 1px solid #fde68a !important;">
            <div class="d-flex align-items-center justify-content-between gap-2">
                <div class="d-flex align-items-center gap-2">
                    <i class="feather-alert-triangle fs-14 flex-shrink-0 text-warning"></i>
                    <div>
                        <strong class="fw-bold">Tracking Interrupted:</strong> <span id="web_punch_gps_warning_text">Device location is turned off or blocked.</span>
                        <span class="text-muted fs-10 d-block">Company rules require active tracking during shift. Please re-enable GPS to prevent compliance audit flags.</span>
                    </div>
                </div>
                <button type="button" class="btn btn-sm btn-outline-warning border-0 py-0 px-2 fs-11 fw-bold text-nowrap" onclick="window.retryLocationPermission && window.retryLocationPermission()">RETRY GPS</button>
            </div>
        </div>

        <!-- Main Live Clock & Punch Action -->
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 my-auto flex-grow-1 py-1">
            <div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <h1 class="fw-semibold mb-0 live-web-clock" style="font-size: 28px; font-weight: 900; color: #0f172a; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; letter-spacing: -0.02em; line-height: 1.1;">
                        {{ date('h:i:s A') }}
                    </h1>
                    <span class="badge px-2 py-1 fs-11 fw-normal rounded-2 d-inline-flex align-items-center ms-1" style="background: #edf2f7; color: #64748b; font-weight: 500; gap: 5px;">
                        <i class="feather-calendar fs-11 me-1" style="color: #6366f1;"></i> {{ date('l, d F Y') }}
                    </span>
                </div>

                @php
                    $isClockedIn = ($myTodayAttendance ?? null) && $myTodayAttendance->check_in && !$myTodayAttendance->check_out;
                    $isCompleted = ($myTodayAttendance ?? null) && $myTodayAttendance->check_in && $myTodayAttendance->check_out;
                    $activeBreak = ($myTodayAttendance ?? null) && $myTodayAttendance->breaks ? $myTodayAttendance->breaks->whereNull('break_out')->first() : null;
                @endphp

                @if($isClockedIn && !$activeBreak)
                    <div class="mt-1 d-flex align-items-center gap-2">
                        <span class="badge px-2 py-0.5 fs-11 fw-bold rounded-pill d-inline-flex align-items-center" style="background: #dcfce7; color: #16a34a; font-weight: 700; gap: 4px;">
                            <i class="feather-check-circle fs-12 me-1"></i> Clocked In
                        </span>
                        <span class="fs-12 fw-normal" style="color: #64748b;">
                            In at <strong class="fw-bold" style="color: #0f172a;">{{ \Carbon\Carbon::parse($myTodayAttendance->check_in)->format('h:i A') }}</strong>
                        </span>
                    </div>
                @elseif($isClockedIn && $activeBreak)
                    <div class="mt-1 d-flex align-items-center gap-2">
                        <span class="badge px-2 py-0.5 fs-11 fw-bold rounded-pill d-inline-flex align-items-center" style="background: #fef3c7; color: #d97706; font-weight: 700; gap: 4px;">
                            <i class="feather-pause-circle fs-12 me-1"></i> On Break
                        </span>
                        <span class="fs-12 fw-normal" style="color: #64748b;">
                            In at <strong class="fw-bold" style="color: #0f172a;">{{ \Carbon\Carbon::parse($myTodayAttendance->check_in)->format('h:i A') }}</strong>
                        </span>
                    </div>
                @elseif($isCompleted)
                    <div class="mt-1 d-flex align-items-center gap-2">
                        <span class="badge px-2 py-0.5 fs-11 fw-bold rounded-pill d-inline-flex align-items-center" style="background: #f1f5f9; color: #64748b; font-weight: 700; gap: 4px;">
                            <i class="feather-check fs-12 me-1"></i> Shift Completed
                        </span>
                        <span class="fs-12 fw-normal" style="color: #64748b;">
                            In: <strong class="fw-bold" style="color: #0f172a;">{{ \Carbon\Carbon::parse($myTodayAttendance->check_in)->format('h:i A') }}</strong> • Out: <strong class="fw-bold" style="color: #0f172a;">{{ \Carbon\Carbon::parse($myTodayAttendance->check_out)->format('h:i A') }}</strong>
                        </span>
                    </div>
                @else
                    <div class="mt-1 d-flex align-items-center gap-2">
                        <span class="badge px-2 py-0.5 fs-11 fw-bold rounded-pill d-inline-flex align-items-center" style="background: #fee2e2; color: #ef4444; font-weight: 700; gap: 4px;">
                            <i class="feather-alert-circle fs-12 me-1"></i> Not Clocked In
                        </span>
                        <span class="fs-11 text-muted">You have not clocked in yet today.</span>
                    </div>
                @endif
            </div>

            <div class="text-end">
                <form action="{{ \Illuminate\Support\Facades\Route::has('hrms.dashboard.punch') ? route('hrms.dashboard.punch') : url('/hrms/dashboard/punch') }}" method="POST" class="m-0" id="webPunchForm" 
                    data-location-required="{{ !empty($trackingConfig['location_required']) ? '1' : '0' }}" 
                    data-selfie-required="{{ !empty($trackingConfig['selfie_required']) ? '1' : '0' }}"
                    data-is-on-break="{{ $activeBreak ? '1' : '0' }}"
                    data-pause-on-break="{{ !empty($trackingConfig['pause_tracking_on_break']) ? '1' : '0' }}"
                    data-allow-multiple="{{ !empty($trackingConfig['allow_multiple_punches']) ? '1' : '0' }}"
                    data-office-break-geofence="{{ !empty($trackingConfig['office_break_geofence']) ? '1' : '0' }}">
                    @csrf
                    @if($currentEmployee ?? null)
                        <input type="hidden" name="employee_id" value="{{ $currentEmployee->id }}">
                    @endif
                    <input type="hidden" name="latitude" id="web_punch_lat" value="">
                    <input type="hidden" name="longitude" id="web_punch_lng" value="">
                    <input type="hidden" name="location_type" id="web_punch_loc" value="{{ $locType ?? 'office' }}">
                    <input type="hidden" name="selfie" id="web_punch_selfie" value="">

                    <div class="d-flex align-items-center gap-2">
                        @if(!$isClockedIn && !$isCompleted)
                            <input type="hidden" name="action" value="in">
                            <button type="submit" name="action" value="in" {{ !($trackingConfig['punch_allowed'] ?? true) ? 'disabled' : '' }} class="btn text-white fw-bold px-3 py-1.5 rounded-3 text-uppercase d-inline-flex align-items-center" style="background: #10b981; border: none; font-size: 11px; font-weight: 800; letter-spacing: 0.03em; border-radius: 8px; gap: 6px;">
                                <i class="feather-log-in fs-13 me-1"></i> CLOCK-IN
                            </button>
                        @elseif($isClockedIn)
                            @if(!$activeBreak)
                                <button type="submit" name="action" value="break_out" {{ !($trackingConfig['punch_allowed'] ?? true) ? 'disabled' : '' }} class="btn fw-bold px-3 py-1.5 rounded-3 text-uppercase d-inline-flex align-items-center" style="background: #ffffff; border: 1px solid #e2e8f0; color: #1e293b; font-size: 11px; font-weight: 800; letter-spacing: 0.03em; border-radius: 8px; gap: 6px;">
                                    <i class="feather-pause-circle fs-13 me-1" style="color: #f97316;"></i> START BREAK
                                </button>
                            @else
                                <button type="submit" name="action" value="break_in" {{ !($trackingConfig['punch_allowed'] ?? true) ? 'disabled' : '' }} class="btn fw-bold px-3 py-1.5 rounded-3 text-uppercase d-inline-flex align-items-center" style="background: #eef2ff; border: 1px solid #c7d2fe; color: #4338ca; font-size: 11px; font-weight: 800; border-radius: 8px; gap: 6px;">
                                    <i class="feather-play-circle fs-13 me-1" style="color: #6366f1;"></i> RESUME WORK
                                </button>
                            @endif

                            <button type="submit" name="action" value="out" {{ !($trackingConfig['punch_allowed'] ?? true) ? 'disabled' : '' }} class="btn text-white fw-bold px-3 py-1.5 rounded-3 text-uppercase d-inline-flex align-items-center" style="background: #df4e2e; border: none; font-size: 11px; font-weight: 800; letter-spacing: 0.03em; border-radius: 8px; gap: 6px;">
                                <i class="feather-log-out fs-13 me-1"></i> CLOCK-OUT
                            </button>
                        @else
                            @if(!empty($trackingConfig['allow_multiple_punches']))
                                <button type="submit" name="action" value="in" class="btn text-white fw-bold px-3 py-1.5 rounded-3 text-uppercase d-inline-flex align-items-center" style="background: #10b981; border: none; font-size: 11px; font-weight: 800; letter-spacing: 0.03em; border-radius: 8px; gap: 6px;">
                                    <i class="feather-log-in fs-13 me-1"></i> CLOCK-IN (NEXT SHIFT)
                                </button>
                            @else
                                <button type="button" class="btn btn-secondary disabled fw-bold px-3 py-1.5 rounded-3 text-uppercase opacity-75 d-inline-flex align-items-center" style="font-size: 11px; font-weight: 800; border-radius: 8px; gap: 6px;">
                                    <i class="feather-check fs-13 me-1"></i> COMPLETED
                                </button>
                            @endif
                        @endif
                    </div>
                </form>
            </div>
        </div>

        <!-- 7-Day Attendance Log Section -->
        <div class="pt-2 border-top flex-shrink-0 mt-auto" style="border-color: #f1f5f9 !important;">
            <div class="d-flex align-items-center justify-content-between mb-1.5">
                <span class="fs-11 text-uppercase fw-bold" style="color: #475569 !important; letter-spacing: 0.04em; font-weight: 800;">RECENT 7 DAYS ATTENDANCE LOG</span>
                <a href="{{ \Illuminate\Support\Facades\Route::has('hrms.attendance.myAttendance') ? route('hrms.attendance.myAttendance') : url('/hrms/attendance/my-attendance') }}" class="fs-11 fw-bold text-decoration-none" style="color: var(--bs-primary, #6337fa);">Full Attendance Log &rarr;</a>
            </div>
            <div style="display: grid; grid-template-columns: repeat(7, 1fr); gap: 6px;">
                @foreach(($recentPunches ?? []) as $punch)
                    @php
                        $isToday = $punch['is_today'] ?? false;
                        $status = strtolower($punch['status'] ?? 'unmarked');
                        
                        $badgeBg = '#f1f5f9';
                        $badgeColor = '#64748b';

                        if ($status === 'late') {
                            $badgeBg = '#fef3c7';
                            $badgeColor = '#d97706';
                            $statusLabel = 'Late';
                        } elseif ($status === 'present') {
                            $badgeBg = '#dcfce7';
                            $badgeColor = '#16a34a';
                            $statusLabel = 'Present';
                        } elseif ($status === 'absent') {
                            $badgeBg = '#fee2e2';
                            $badgeColor = '#ef4444';
                            $statusLabel = 'Absent';
                        } elseif ($status === 'off') {
                            $badgeBg = '#f1f5f9';
                            $badgeColor = '#64748b';
                            $statusLabel = 'Off';
                        } elseif ($status === 'half_day') {
                            $badgeBg = '#f3e8ff';
                            $badgeColor = '#9333ea';
                            $statusLabel = 'Half Day';
                        } elseif ($status === 'leave' || $status === 'on_leave') {
                            $badgeBg = '#e0f2fe';
                            $badgeColor = '#0284c7';
                            $statusLabel = 'Leave';
                        } else {
                            if ($isToday) {
                                if ($isClockedIn) {
                                    $badgeBg = '#dcfce7';
                                    $badgeColor = '#16a34a';
                                    $statusLabel = 'Present';
                                } else {
                                    $badgeBg = '#fef3c7';
                                    $badgeColor = '#d97706';
                                    $statusLabel = 'Late';
                                }
                            } else {
                                $badgeBg = '#fee2e2';
                                $badgeColor = '#ef4444';
                                $statusLabel = 'Absent';
                            }
                        }
                    @endphp
                    <div class="h-100">
                        <div class="p-1.5 p-md-2 text-center d-flex flex-column justify-content-between h-100" style="background: #ffffff; border: {{ $isToday ? '2px solid var(--bs-primary, #6337fa)' : '1px solid #eaeff5' }}; border-radius: 8px !important;">
                            <div class="text-uppercase mb-0.5" style="color: #475569; font-size: 10px; font-weight: 700; letter-spacing: 0.02em;">
                                {{ $punch['day_name'] ?? '—' }}
                            </div>
                            <div class="my-0.5" style="color: #0f172a; font-size: 16px; font-weight: 800; line-height: 1.1;">
                                {{ $punch['day_num'] ?? '—' }}
                            </div>
                            <div class="mt-0.5">
                                <span class="badge px-1 py-0.5 rounded fw-bold d-inline-block" style="background: {{ $badgeBg }}; color: {{ $badgeColor }}; font-size: 9.5px; font-weight: 700; min-width: 38px;">
                                    {{ $statusLabel }}
                                </span>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>

<!-- Attendance Selfie Capture Modal for Web Punch Station -->
<div class="modal fade" id="webPunchSelfieModal" tabindex="-1" aria-labelledby="webPunchSelfieModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false" style="z-index: 1065 !important;">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 440px;">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 16px; overflow: hidden; background: #ffffff;">
            <div class="modal-header border-bottom py-3 px-3 bg-light d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <span class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary text-white" style="width: 32px; height: 32px;">
                        <i class="feather-camera fs-14"></i>
                    </span>
                    <div>
                        <h6 class="modal-title fw-bold mb-0 text-dark fs-14" id="webPunchSelfieModalLabel">Attendance Selfie</h6>
                        <small class="text-muted fs-11" id="webPunchModalSubtitle">Please capture a photo to confirm your attendance</small>
                    </div>
                </div>
                <button type="button" class="btn-close fs-11" data-bs-dismiss="modal" aria-label="Close" id="webPunchModalCloseBtn"></button>
            </div>
            
            <div class="modal-body p-3">
                <div id="webPunchCameraError" class="alert alert-danger py-2 px-3 fs-12 d-none mb-3 d-flex align-items-center gap-2">
                    <i class="feather-alert-circle fs-15 flex-shrink-0"></i>
                    <span id="webPunchCameraErrorMessage">Camera access is unavailable. Please grant camera permission.</span>
                </div>

                <!-- Camera / Snapshot Viewport -->
                <div class="position-relative rounded-3 overflow-hidden d-flex align-items-center justify-content-center" style="min-height: 280px; max-height: 320px; background: #0b1120;">
                    <!-- Live Camera Feed (Mirrored for natural selfie preview) -->
                    <video id="webPunchModalVideo" autoplay playsinline muted class="w-100 h-100" style="object-fit: cover; max-height: 320px; transform: scaleX(-1);"></video>

                    <!-- Snapshot Preview (Mirrored to match camera view) -->
                    <img id="webPunchModalPreview" class="d-none w-100 h-100" style="object-fit: cover; max-height: 320px; transform: scaleX(-1);" alt="Attendance Selfie Preview">

                    <!-- Loading Spinner Overlay -->
                    <div id="webPunchCameraLoading" class="position-absolute top-50 start-50 translate-middle text-center text-white" style="z-index: 5;">
                        <div class="spinner-border spinner-border-sm text-light mb-1.5" role="status"></div>
                        <div class="fs-11 text-light opacity-75">Starting camera...</div>
                    </div>

                    <!-- Face Guide Overlay (Only during live feed) -->
                    <div id="webPunchFaceGuide" class="position-absolute top-50 start-50 translate-middle rounded-circle border border-2 border-white opacity-25 pointer-events-none d-none" style="width: 170px; height: 210px; border-radius: 50% !important; border-style: dashed !important; z-index: 3;"></div>
                </div>

                <!-- Hidden canvas for rendering snapshot image -->
                <canvas id="webPunchModalCanvas" class="d-none"></canvas>

                <div class="mt-2 text-center">
                    <span class="text-muted fs-11" id="webPunchHelpText">
                        <i class="feather-info me-1"></i> Look straight at the camera and ensure good lighting.
                    </span>
                </div>
            </div>

            <div class="modal-footer border-top py-2.5 px-3 bg-light d-flex align-items-center justify-content-between">
                <button type="button" class="btn btn-sm btn-outline-secondary px-3 py-1.5 rounded-2 fs-12 fw-semibold" data-bs-dismiss="modal" id="webPunchModalCancelBtn">
                    Cancel
                </button>

                <!-- State 1: Active Camera -> Take Snapshot -->
                <button type="button" class="btn btn-sm btn-primary px-3 py-1.5 rounded-2 fs-12 fw-bold d-inline-flex align-items-center gap-1.5 shadow-sm" id="webPunchModalSnapBtn">
                    <i class="feather-camera fs-13"></i> Take Snapshot
                </button>

                <!-- State 2: Snapshot Taken -> Retake & Confirm -->
                <div class="d-flex align-items-center gap-2 d-none" id="webPunchModalConfirmGroup">
                    <button type="button" class="btn btn-sm btn-outline-secondary px-2.5 py-1.5 rounded-2 fs-12 fw-semibold d-inline-flex align-items-center gap-1" id="webPunchModalRetakeBtn">
                        <i class="feather-rotate-ccw fs-12"></i> Retake
                    </button>
                    <button type="button" class="btn btn-sm btn-success px-3 py-1.5 rounded-2 fs-12 fw-bold d-inline-flex align-items-center gap-1.5 shadow-sm" id="webPunchModalConfirmBtn">
                        <i class="feather-check fs-13"></i> <span id="webPunchConfirmBtnText">Confirm & Process</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script data-execute-widget-script>
    (function() {
        function updateLiveClock() {
            const now = new Date();
            let hours = now.getHours();
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');
            const ampm = hours >= 12 ? 'PM' : 'AM';
            hours = hours % 12;
            hours = hours ? String(hours).padStart(2, '0') : '12';
            const timeStr = hours + ':' + minutes + ':' + seconds + ' ' + ampm;
            
            document.querySelectorAll('.live-web-clock').forEach(function(el) {
                el.textContent = timeStr;
            });
        }
        setInterval(updateLiveClock, 1000);

        // Geolocation Capture for Punch Station
        const latInput = document.getElementById('web_punch_lat');
        const lngInput = document.getElementById('web_punch_lng');
        const punchForm = document.getElementById('webPunchForm');
        const selfieInput = document.getElementById('web_punch_selfie');

        // Modal Elements
        const selfieModalEl = document.getElementById('webPunchSelfieModal');
        const modalVideo = document.getElementById('webPunchModalVideo');
        const modalPreview = document.getElementById('webPunchModalPreview');
        const modalCanvas = document.getElementById('webPunchModalCanvas');
        const modalLoading = document.getElementById('webPunchCameraLoading');
        const modalFaceGuide = document.getElementById('webPunchFaceGuide');
        const modalSnapBtn = document.getElementById('webPunchModalSnapBtn');
        const modalConfirmGroup = document.getElementById('webPunchModalConfirmGroup');
        const modalRetakeBtn = document.getElementById('webPunchModalRetakeBtn');
        const modalConfirmBtn = document.getElementById('webPunchModalConfirmBtn');
        const confirmBtnText = document.getElementById('webPunchConfirmBtnText');
        const modalSubtitle = document.getElementById('webPunchModalSubtitle');
        const cameraErrorAlert = document.getElementById('webPunchCameraError');
        const cameraErrorMessage = document.getElementById('webPunchCameraErrorMessage');
        const modalCloseBtn = document.getElementById('webPunchModalCloseBtn');
        const modalCancelBtn = document.getElementById('webPunchModalCancelBtn');

        let selfieStream = null;
        let pendingAction = null;
        let isSubmitting = false;

        function relocatePunchModal() {
            if (selfieModalEl && selfieModalEl.parentElement !== document.body) {
                document.body.appendChild(selfieModalEl);
            }
        }
        relocatePunchModal();

        function getModalInstance() {
            if (!selfieModalEl) return null;
            relocatePunchModal();
            if (window.bootstrap && window.bootstrap.Modal) {
                return window.bootstrap.Modal.getOrCreateInstance(selfieModalEl);
            }
            if (window.jQuery && typeof window.jQuery(selfieModalEl).modal === 'function') {
                return {
                    show: function() { window.jQuery(selfieModalEl).modal('show'); },
                    hide: function() { window.jQuery(selfieModalEl).modal('hide'); }
                };
            }
            return null;
        }

        function stopCameraStream() {
            if (selfieStream) {
                selfieStream.getTracks().forEach(function(track) {
                    try { track.stop(); } catch (err) {}
                });
                selfieStream = null;
            }
            if (modalVideo) {
                modalVideo.srcObject = null;
            }
        }

        async function startCamera() {
            stopCameraStream();
            if (cameraErrorAlert) cameraErrorAlert.classList.add('d-none');
            if (modalLoading) modalLoading.classList.remove('d-none');
            if (modalFaceGuide) modalFaceGuide.classList.add('d-none');
            if (modalVideo) modalVideo.classList.remove('d-none');
            if (modalPreview) {
                modalPreview.classList.add('d-none');
                modalPreview.src = '';
            }
            if (modalSnapBtn) {
                modalSnapBtn.classList.remove('d-none');
                modalSnapBtn.disabled = true;
            }
            if (modalConfirmGroup) modalConfirmGroup.classList.add('d-none');

            try {
                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                    throw new Error('Camera access is unavailable in this browser or context.');
                }
                const stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } },
                    audio: false
                });
                selfieStream = stream;
                if (modalVideo) {
                    modalVideo.srcObject = stream;
                    modalVideo.onloadedmetadata = function() {
                        modalVideo.play().catch(function() {});
                        if (modalLoading) modalLoading.classList.add('d-none');
                        if (modalFaceGuide) modalFaceGuide.classList.remove('d-none');
                        if (modalSnapBtn) modalSnapBtn.disabled = false;
                    };
                }
            } catch (err) {
                console.error('Camera access error:', err);
                if (modalLoading) modalLoading.classList.add('d-none');
                if (cameraErrorAlert && cameraErrorMessage) {
                    cameraErrorMessage.textContent = err.message || 'Unable to open camera. Please grant camera permission.';
                    cameraErrorAlert.classList.remove('d-none');
                }
                if (modalSnapBtn) modalSnapBtn.disabled = true;
            }
        }

        function takeSnapshot() {
            if (!modalVideo || !modalVideo.videoWidth || !modalVideo.videoHeight) {
                window.alert('The camera is not ready yet. Please wait a moment.');
                return false;
            }

            const width = modalVideo.videoWidth;
            const height = modalVideo.videoHeight;
            const scale = Math.min(1, 640 / width);
            const targetW = Math.round(width * scale);
            const targetH = Math.round(height * scale);

            modalCanvas.width = targetW;
            modalCanvas.height = targetH;
            const ctx = modalCanvas.getContext('2d');

            // Mirror horizontally so the saved snapshot matches the mirrored video preview
            ctx.translate(targetW, 0);
            ctx.scale(-1, 1);
            ctx.drawImage(modalVideo, 0, 0, targetW, targetH);

            const base64Data = modalCanvas.toDataURL('image/jpeg', 0.82);

            if (selfieInput) {
                selfieInput.value = base64Data;
            }

            if (modalPreview) {
                modalPreview.src = base64Data;
                modalPreview.classList.remove('d-none');
            }
            if (modalVideo) {
                modalVideo.classList.add('d-none');
            }
            if (modalFaceGuide) {
                modalFaceGuide.classList.add('d-none');
            }

            if (modalSnapBtn) modalSnapBtn.classList.add('d-none');
            if (modalConfirmGroup) modalConfirmGroup.classList.remove('d-none');

            return true;
        }

        function retakeSnapshot() {
            if (selfieInput) {
                selfieInput.value = '';
            }
            if (modalPreview) {
                modalPreview.classList.add('d-none');
                modalPreview.src = '';
            }
            if (modalVideo) {
                modalVideo.classList.remove('d-none');
            }
            if (modalFaceGuide) {
                modalFaceGuide.classList.remove('d-none');
            }
            if (modalConfirmGroup) modalConfirmGroup.classList.add('d-none');
            if (modalSnapBtn) {
                modalSnapBtn.classList.remove('d-none');
                modalSnapBtn.disabled = false;
            }
        }

        if (modalSnapBtn) {
            modalSnapBtn.addEventListener('click', function() {
                takeSnapshot();
            });
        }

        if (modalRetakeBtn) {
            modalRetakeBtn.addEventListener('click', function() {
                retakeSnapshot();
            });
        }

        if (modalConfirmBtn) {
            modalConfirmBtn.addEventListener('click', function() {
                if (!selfieInput || !selfieInput.value) {
                    const ok = takeSnapshot();
                    if (!ok) return;
                }
                modalConfirmBtn.disabled = true;
                modalConfirmBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status"></span> Processing...';
                submitPunchFormWithAction(pendingAction || 'out');
            });
        }

        [modalCloseBtn, modalCancelBtn].forEach(function(btn) {
            if (btn) {
                btn.addEventListener('click', function() {
                    stopCameraStream();
                    if (!isSubmitting) {
                        if (selfieInput) selfieInput.value = '';
                        pendingAction = null;
                    }
                });
            }
        });

        if (selfieModalEl) {
            selfieModalEl.addEventListener('hidden.bs.modal', function() {
                stopCameraStream();
                if (!isSubmitting) {
                    if (selfieInput) selfieInput.value = '';
                    pendingAction = null;
                }
            });
        }

        function fetchCurrentPosition(callback) {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        if (latInput && lngInput) {
                            latInput.value = position.coords.latitude;
                            lngInput.value = position.coords.longitude;
                        }
                        if (typeof callback === 'function') callback(position.coords);
                    },
                    function(err) {
                        console.warn('Geolocation error or permission denied:', err.message);
                        if (typeof callback === 'function') callback(null);
                    },
                    { enableHighAccuracy: true, timeout: 8000, maximumAge: 30000 }
                );
            } else if (typeof callback === 'function') {
                callback(null);
            }
        }

        function submitPunchFormWithAction(action) {
            isSubmitting = true;
            stopCameraStream();
            const modalInst = getModalInstance();
            if (modalInst) {
                try { modalInst.hide(); } catch (err) {}
            }

            let actionField = punchForm.querySelector('input[name="action"]');
            if (!actionField) {
                actionField = document.createElement('input');
                actionField.type = 'hidden';
                actionField.name = 'action';
                punchForm.appendChild(actionField);
            }
            actionField.value = action;

            const locationRequired = punchForm.dataset.locationRequired === '1';
            if (locationRequired && ['in', 'out'].includes(action)) {
                fetchCurrentPosition(function(coords) {
                    if (!coords || !Number.isFinite(coords.latitude) || !Number.isFinite(coords.longitude)) {
                        isSubmitting = false;
                        if (modalConfirmBtn) {
                            modalConfirmBtn.disabled = false;
                            modalConfirmBtn.innerHTML = '<i class="feather-check fs-13"></i> ' + (confirmBtnText ? confirmBtnText.textContent : 'Confirm & Process');
                        }
                        window.alert('Could not get your current location. Check browser location permission and try again.');
                        return;
                    }
                    punchForm.submit();
                });
            } else {
                punchForm.submit();
            }
        }

        // Intercept punchForm submission
        if (punchForm) {
            punchForm.addEventListener('submit', function(e) {
                if (isSubmitting) {
                    return; // Allow direct form submit
                }

                const actionInput = punchForm.querySelector('[name="action"]');
                const action = e.submitter?.value || actionInput?.value || '';
                const locationRequired = punchForm.dataset.locationRequired === '1';
                const selfieRequired = punchForm.dataset.selfieRequired === '1';

                // Break actions don't require selfie, but check if break geofence is enabled (Enhancement 2)
                const breakGeofenceRequired = punchForm.dataset.officeBreakGeofence === '1';
                if (['break_in', 'break_out'].includes(action)) {
                    if (breakGeofenceRequired && (!latInput?.value || !lngInput?.value)) {
                        e.preventDefault();
                        fetchCurrentPosition(function(coords) {
                            if (!coords || !Number.isFinite(coords.latitude) || !Number.isFinite(coords.longitude)) {
                                window.alert('Office break geofencing requires your location. Check browser location permission and try again.');
                                return;
                            }
                            isSubmitting = true;
                            let actionField = punchForm.querySelector('input[name="action"]');
                            if (!actionField) {
                                actionField = document.createElement('input');
                                actionField.type = 'hidden';
                                actionField.name = 'action';
                                punchForm.appendChild(actionField);
                            }
                            actionField.value = action;
                            punchForm.submit();
                        });
                        return;
                    }
                    return;
                }

                // If selfie is required and not yet captured, open modal!
                if (selfieRequired && ['in', 'out'].includes(action) && (!selfieInput || !selfieInput.value)) {
                    e.preventDefault();
                    e.stopPropagation();
                    pendingAction = action;

                    const isClockOut = action === 'out';
                    const actionLabel = isClockOut ? 'Clock-Out' : 'Clock-In';

                    if (modalSubtitle) {
                        modalSubtitle.textContent = `Please take a photo to confirm your ${actionLabel}`;
                    }
                    if (confirmBtnText) {
                        confirmBtnText.textContent = `Confirm & ${actionLabel}`;
                    }

                    const modalInst = getModalInstance();
                    if (modalInst) {
                        modalInst.show();
                        startCamera();
                    } else {
                        window.alert(`Attendance selfie is required for ${actionLabel}.`);
                    }
                    return;
                }

                // If location required
                if (locationRequired && ['in', 'out'].includes(action)) {
                    if (!latInput?.value || !lngInput?.value) {
                        e.preventDefault();
                        fetchCurrentPosition(function(coords) {
                            if (!coords || !Number.isFinite(coords.latitude) || !Number.isFinite(coords.longitude)) {
                                window.alert('Could not get your current location. Check browser location permission and try again.');
                                return;
                            }
                            isSubmitting = true;
                            let actionField = punchForm.querySelector('input[name="action"]');
                            if (!actionField) {
                                actionField = document.createElement('input');
                                actionField.type = 'hidden';
                                actionField.name = 'action';
                                punchForm.appendChild(actionField);
                            }
                            actionField.value = action;
                            punchForm.submit();
                        });
                        return;
                    }
                }
            });
        }

        // Periodic Location Tracking (if enabled in Attendance Rules and employee is clocked in)
        @if(($isClockedIn ?? false) && !empty($trackingConfig['enabled']) && !empty($currentEmployee))
            const trackEmployeeId = {{ (int)$currentEmployee->id }};
            const trackIntervalSec = {{ (int)$trackingConfig['interval_seconds'] }};
            const trackUrl = "{{ \Illuminate\Support\Facades\Route::has('hrms.attendance.track-location') ? route('hrms.attendance.track-location') : url('/hrms/attendance/track-location') }}";
            const csrfToken = "{{ csrf_token() }}";

            const gpsWarningEl = document.getElementById('web_punch_gps_warning');
            const gpsWarningText = document.getElementById('web_punch_gps_warning_text');

            function showGpsWarning(msg) {
                if (gpsWarningEl) {
                    if (gpsWarningText && msg) gpsWarningText.textContent = msg;
                    gpsWarningEl.classList.remove('d-none');
                }
            }

            function hideGpsWarning() {
                if (gpsWarningEl) {
                    gpsWarningEl.classList.add('d-none');
                }
            }

            window.retryLocationPermission = function() {
                sendLocationBreadcrumb();
            };

            function sendLocationBreadcrumb() {
                // Enhancement 1: Break Telemetry Privacy Mode
                if (punchForm && punchForm.dataset.pauseOnBreak === '1' && punchForm.dataset.isOnBreak === '1') {
                    console.debug('Telemetry paused: Employee is currently on break.');
                    return;
                }

                if (!navigator.geolocation) {
                    showGpsWarning('Geolocation is not supported by your browser.');
                    return;
                }

                navigator.geolocation.getCurrentPosition(function(pos) {
                    hideGpsWarning();
                    const lat = pos.coords.latitude;
                    const lng = pos.coords.longitude;
                    if (latInput && lngInput) {
                        latInput.value = lat;
                        lngInput.value = lng;
                    }

                    fetch(trackUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            employee_id: trackEmployeeId,
                            latitude: lat,
                            longitude: lng
                        })
                    }).catch(function(e) {
                        console.debug('Background location track error:', e);
                    });
                }, function(err) {
                    let errReason = 'Device location / GPS is turned off.';
                    if (err.code === 1) {
                        errReason = 'Location permission is denied in browser settings.';
                    } else if (err.code === 2) {
                        errReason = 'Device GPS / location services are turned off.';
                    } else if (err.code === 3) {
                        errReason = 'GPS satellite acquisition timed out.';
                    }
                    showGpsWarning(errReason);
                    console.warn('Background track GPS error (Pillar 2 active):', errReason);
                }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 });
            }

            // Run initial breadcrumb and then periodically
            setTimeout(sendLocationBreadcrumb, 5000);
            setInterval(sendLocationBreadcrumb, trackIntervalSec * 1000);
        @endif
    })();
</script>
