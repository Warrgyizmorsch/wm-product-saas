<div class="card border mb-0 h-100 shadow-sm rounded-3">
    <div class="card-body p-3">
        <div class="d-flex align-items-center justify-content-between mb-2">
            <span class="dash-card-icon-avatar bg-soft-success text-success" style="width:36px;height:36px;border-radius:8px;">
                <i class="feather-check-circle fs-16"></i>
            </span>
            <span class="badge bg-soft-success text-success px-2 py-0.5 fs-11 fw-bold rounded">{{ $attendancePercent ?? 0 }}% Rate</span>
        </div>
        <div class="fs-10 text-uppercase fw-bold text-muted tracking-wide">Today's Attendance</div>
        <div class="fs-22 fw-bolder text-dark mb-2">{{ $presentCount ?? 0 }} <span class="fs-12 text-muted font-normal">/ {{ $totalEmployees ?? 0 }} Present</span></div>
        <div class="d-flex align-items-center justify-content-between pt-2 border-top fs-11">
            <div class="text-center">
                <span class="d-block text-uppercase text-muted fs-9 fw-bold">WFH</span>
                <span class="fw-bold text-info">{{ $wfhCount ?? 0 }}</span>
            </div>
            <div class="text-center">
                <span class="d-block text-uppercase text-muted fs-9 fw-bold">Late</span>
                <span class="fw-bold text-warning">{{ $lateCount ?? 0 }}</span>
            </div>
            <div class="text-center">
                <span class="d-block text-uppercase text-muted fs-9 fw-bold">On Leave</span>
                <span class="fw-bold text-danger">{{ $onLeaveCount ?? 0 }}</span>
            </div>
        </div>
    </div>
</div>
