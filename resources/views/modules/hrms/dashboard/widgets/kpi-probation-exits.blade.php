<div class="card border mb-0 h-100 shadow-sm rounded-3">
    <div class="card-body p-3">
        <div class="d-flex align-items-center justify-content-between mb-2">
            <span class="dash-card-icon-avatar bg-soft-info text-info" style="width:36px;height:36px;border-radius:8px;">
                <i class="feather-activity fs-16"></i>
            </span>
            <span class="badge bg-soft-info text-info px-2 py-0.5 fs-11 fw-bold rounded">Lifecycle</span>
        </div>
        <div class="fs-10 text-uppercase fw-bold text-muted tracking-wide">Probation & Exits</div>
        <div class="fs-22 fw-bolder text-dark mb-2">{{ (isset($upcomingProbationEmployees) ? count($upcomingProbationEmployees) : 0) + (isset($activeExits) ? count($activeExits) : 0) }}</div>
        <div class="d-flex align-items-center justify-content-between pt-2 border-top fs-11">
            <div class="text-center">
                <span class="d-block text-uppercase text-muted fs-9 fw-bold">Reviews Due</span>
                <span class="fw-bold text-warning">{{ isset($upcomingProbationEmployees) ? count($upcomingProbationEmployees) : 0 }}</span>
            </div>
            <div class="text-center">
                <span class="d-block text-uppercase text-muted fs-9 fw-bold">Active Exits</span>
                <span class="fw-bold text-danger">{{ isset($activeExits) ? count($activeExits) : 0 }}</span>
            </div>
        </div>
    </div>
</div>
