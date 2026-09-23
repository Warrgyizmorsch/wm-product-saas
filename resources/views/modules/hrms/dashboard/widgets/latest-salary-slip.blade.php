<div class="card border mb-0 h-100 shadow-sm rounded-3">
    <div class="card-header py-3 px-4 d-flex align-items-center justify-content-between border-bottom">
        <div class="d-flex align-items-center gap-2">
            <span class="dash-card-icon-avatar bg-soft-primary text-primary" style="width:28px;height:28px;border-radius:6px;">
                <i class="feather-file-text"></i>
            </span>
            <h6 class="fw-bold mb-0 text-dark fs-14">My Latest Salary Slip</h6>
        </div>
        <span class="badge bg-soft-success text-success px-2 py-0.5 fs-10 fw-bold">Available</span>
    </div>

    <div class="card-body p-3.5 d-flex flex-column justify-content-between">
        <p class="fs-12 text-muted mb-3">Download your official salary slip for the recent payroll disbursement period.</p>
        <a href="{{ \Illuminate\Support\Facades\Route::has('hrms.employees.index') ? route('hrms.employees.index') : '#' }}" class="btn btn-primary fw-bold w-100 py-2 shadow-2xs d-inline-flex align-items-center justify-content-center gap-1.5 fs-12">
            <i class="feather-download fs-13"></i> VIEW & DOWNLOAD PAYSLIP (PDF)
        </a>
    </div>
</div>
