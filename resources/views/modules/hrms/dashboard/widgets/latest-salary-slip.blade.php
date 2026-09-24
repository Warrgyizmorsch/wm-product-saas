<div class="card border-0 mb-0 h-100 shadow-sm" style="border-radius: 16px; background: #ffffff;">
    <div class="card-body p-4 d-flex flex-column justify-content-between">
        <div>
            <!-- Header: My Latest Salary Slip -->
            <div class="d-flex align-items-center justify-content-between mb-3">
                <div class="d-flex align-items-center gap-2.5" style="gap: 10px;">
                    <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width: 32px; height: 32px; background: #eef2ff; border-radius: 8px; color: #4f46e5;">
                        <i class="feather-file-text fs-14"></i>
                    </div>
                    <h6 class="fw-bold mb-0 text-dark" style="font-size: 16px; color: #1e293b !important; font-weight: 700;">My Latest Salary Slip</h6>
                </div>
                <span class="badge px-2.5 py-1 fs-11 fw-bold rounded-2" style="background: #dcfce7; color: #15803d;">Available</span>
            </div>

            <p class="fs-13 text-muted mb-4" style="color: #64748b !important; line-height: 1.5;">Download your official salary slip for the recent payroll disbursement period.</p>
        </div>

        <a href="{{ \Illuminate\Support\Facades\Route::has('hrms.payroll.mySalary') ? route('hrms.payroll.mySalary') : url('/hrms/payroll/my-salary') }}" class="btn text-white fw-bold py-2.5 w-100 text-uppercase fs-12 d-inline-flex align-items-center justify-content-center gap-1.5 shadow-2xs" style="background: #4a3838 !important; color: #ffffff !important; border: none !important; border-radius: 10px !important; font-weight: 700 !important; font-size: 12px !important; letter-spacing: 0.03em !important;">
            <i class="feather-download fs-13"></i> VIEW & DOWNLOAD PAYSLIP (PDF)
        </a>
    </div>
</div>
