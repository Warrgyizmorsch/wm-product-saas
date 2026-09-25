<div class="card border-0 mb-0 h-100 shadow-sm" style="border-radius: 16px; background: #ffffff; overflow: hidden;">
    <div class="card-body p-3 p-md-3.5 d-flex flex-column justify-content-between h-100">
        <div>
            <!-- Header: My Latest Salary Slip -->
            <div class="d-flex align-items-center justify-content-between mb-2">
                <div class="d-flex align-items-center gap-2">
                    <div class="d-flex align-items-center justify-content-center flex-shrink-0" style="width: 28px; height: 28px; background: #eef2ff; border-radius: 8px; color: #4f46e5;">
                        <i class="feather-file-text fs-13"></i>
                    </div>
                    <h6 class="fw-bold mb-0 text-dark fs-14" style="color: #1e293b !important; font-weight: 700;">My Latest Salary Slip</h6>
                </div>
                <span class="badge px-2 py-0.5 fs-10 fw-bold rounded-2" style="background: #dcfce7; color: #15803d;">Available</span>
            </div>

            <p class="fs-12 text-muted mb-0" style="color: #64748b !important; line-height: 1.4;">Download your official salary slip for the recent payroll disbursement period.</p>
        </div>

        <div class="mt-auto pt-2">
            <a href="{{ \Illuminate\Support\Facades\Route::has('hrms.payroll.mySalary') ? route('hrms.payroll.mySalary') : url('/hrms/payroll/my-salary') }}" class="btn text-white fw-bold py-2 w-100 text-uppercase fs-11 d-inline-flex align-items-center justify-content-center gap-1.5 shadow-2xs" style="background: #4a3838 !important; color: #ffffff !important; border: none !important; border-radius: 10px !important; font-weight: 700 !important; font-size: 11px !important; letter-spacing: 0.03em !important;">
                <i class="feather-download fs-12"></i> VIEW & DOWNLOAD PAYSLIP (PDF)
            </a>
        </div>
    </div>
</div>
