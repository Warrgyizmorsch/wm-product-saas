<div class="card border mb-0 h-100 shadow-sm rounded-3">
    <div class="card-body p-3">
        <div class="d-flex align-items-center justify-content-between mb-2">
            <span class="dash-card-icon-avatar bg-soft-warning text-warning" style="width:36px;height:36px;border-radius:8px;">
                <i class="feather-inbox fs-16"></i>
            </span>
            <span class="badge bg-soft-success text-success px-2 py-0.5 fs-11 fw-bold rounded">
                {{ ($totalPendingApprovals ?? 0) === 0 ? 'All Cleared' : ($totalPendingApprovals . ' Pending') }}
            </span>
        </div>
        <div class="fs-10 text-uppercase fw-bold text-muted tracking-wide">Pending Approvals</div>
        <div class="fs-22 fw-bolder text-dark mb-2">{{ $totalPendingApprovals ?? 0 }}</div>
        <div class="d-flex align-items-center justify-content-between pt-2 border-top fs-11">
            <div class="text-center">
                <span class="d-block text-uppercase text-muted fs-9 fw-bold">Leaves</span>
                <span class="fw-bold text-primary">{{ isset($pendingLeaves) ? count($pendingLeaves) : 0 }}</span>
            </div>
            <div class="text-center">
                <span class="d-block text-uppercase text-muted fs-9 fw-bold">WFH</span>
                <span class="fw-bold text-info">{{ isset($pendingWfh) ? count($pendingWfh) : 0 }}</span>
            </div>
            <div class="text-center">
                <span class="d-block text-uppercase text-muted fs-9 fw-bold">Punches</span>
                <span class="fw-bold text-warning">{{ isset($pendingCorrections) ? count($pendingCorrections) : 0 }}</span>
            </div>
        </div>
    </div>
</div>
