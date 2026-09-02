<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Full & Final Settlement Statement - {{ $exit->employee->full_name }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
            padding: 40px 20px;
        }
        .statement-container {
            max-width: 850px;
            margin: auto;
            background: #ffffff;
            padding: 50px 45px;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
        }
        .statement-header {
            border-bottom: 2px solid #0284c7;
            padding-bottom: 20px;
            margin-bottom: 25px;
        }
        .table-custom th {
            background-color: #f1f5f9 !important;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        @media print {
            body {
                background: #ffffff;
                padding: 0;
            }
            .statement-container {
                box-shadow: none;
                border: none;
                padding: 10px 0;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="no-print text-center mb-4">
        <button onclick="window.print()" class="btn btn-primary px-4 py-2 fw-bold shadow-sm">
            🖨️ Print / Save Statement
        </button>
        <button onclick="window.close()" class="btn btn-outline-secondary px-3 py-2 ms-2">
            Close
        </button>
    </div>

    <div class="statement-container">
        <!-- Header -->
        <div class="statement-header d-flex justify-content-between align-items-center">
            <div>
                <h3 class="fw-bold text-primary mb-0">{{ $exit->employee->company->company_name ?? 'Warrgyizmorsch Pvt Ltd' }}</h3>
                <p class="text-muted fs-13 mb-0">{{ $exit->employee->company->legal_name ?? 'Corporate Payroll & Settlement Division' }}</p>
            </div>
            <div class="text-end text-muted fs-13">
                <div><strong>Statement Date:</strong> {{ date('d M, Y') }}</div>
                <div><strong>Status:</strong> <span class="badge bg-{{ ($settlement->status ?? '') === 'paid' ? 'success' : 'warning' }} text-uppercase">{{ $settlement->status ?? 'Draft' }}</span></div>
            </div>
        </div>

        <h4 class="text-center fw-bold text-dark text-uppercase mb-4">FULL & FINAL (F&F) SETTLEMENT STATEMENT</h4>

        <!-- Employee Info Box -->
        <div class="p-3 bg-light rounded-3 border mb-4">
            <div class="row g-3 fs-13">
                <div class="col-md-3">
                    <span class="text-muted d-block">Employee Name:</span>
                    <strong>{{ $exit->employee->full_name }}</strong>
                </div>
                <div class="col-md-3">
                    <span class="text-muted d-block">Employee ID:</span>
                    <strong>{{ $exit->employee->employee_id }}</strong>
                </div>
                <div class="col-md-3">
                    <span class="text-muted d-block">Designation:</span>
                    <strong>{{ $exit->employee->designation->name ?? 'N/A' }}</strong>
                </div>
                <div class="col-md-3">
                    <span class="text-muted d-block">Department:</span>
                    <strong>{{ $exit->employee->department->name ?? 'N/A' }}</strong>
                </div>
                <div class="col-md-3">
                    <span class="text-muted d-block">Date of Joining:</span>
                    <strong>{{ $exit->employee->date_of_joining ? \Carbon\Carbon::parse($exit->employee->date_of_joining)->format('d M, Y') : 'N/A' }}</strong>
                </div>
                <div class="col-md-3">
                    <span class="text-muted d-block">Last Working Day (LWD):</span>
                    <strong class="text-danger">{{ $exit->effective_lwd ? \Carbon\Carbon::parse($exit->effective_lwd)->format('d M, Y') : 'N/A' }}</strong>
                </div>
                <div class="col-md-3">
                    <span class="text-muted d-block">Separation Type:</span>
                    <strong>{{ ucfirst(str_replace('_', ' ', $exit->separation_type)) }}</strong>
                </div>
                <div class="col-md-3">
                    <span class="text-muted d-block">Monthly CTC / Gross:</span>
                    <strong>${{ number_format($exit->employee->current_salary, 2) }}</strong>
                </div>
            </div>
        </div>

        <!-- Breakdown Tables -->
        <div class="row g-4 mb-4">
            <!-- Earnings -->
            <div class="col-md-6">
                <div class="card border h-100 shadow-none">
                    <div class="card-header bg-success bg-opacity-10 border-bottom py-2">
                        <h6 class="fw-bold text-success mb-0">A. Earnings & Payable Dues</h6>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-sm mb-0 fs-13">
                            <tbody>
                                <tr>
                                    <td class="ps-3 py-2">Pro-Rata Unpaid Salary ({{ $settlement->unpaid_salary_days ?? 0 }} days)</td>
                                    <td class="text-end pe-3 py-2 fw-semibold">${{ number_format($settlement->unpaid_salary_amount ?? 0, 2) }}</td>
                                </tr>
                                <tr>
                                    <td class="ps-3 py-2">Earned Leave Encashment ({{ $settlement->leave_encashment_days ?? 0 }} days)</td>
                                    <td class="text-end pe-3 py-2 fw-semibold">${{ number_format($settlement->leave_encashment_amount ?? 0, 2) }}</td>
                                </tr>
                                <tr>
                                    <td class="ps-3 py-2">Gratuity Benefit (Tenure $\ge$ 5 Yrs)</td>
                                    <td class="text-end pe-3 py-2 fw-semibold">${{ number_format($settlement->gratuity_amount ?? 0, 2) }}</td>
                                </tr>
                                <tr>
                                    <td class="ps-3 py-2">Approved Travel Expense Reimbursements</td>
                                    <td class="text-end pe-3 py-2 fw-semibold">${{ number_format($settlement->other_earnings ?? 0, 2) }}</td>
                                </tr>
                                <tr>
                                    <td class="ps-3 py-2">Bonus / Arrears / Other Benefits</td>
                                    <td class="text-end pe-3 py-2 fw-semibold">${{ number_format($settlement->bonus_amount ?? 0, 2) }}</td>
                                </tr>
                            </tbody>
                            <tfoot class="table-light border-top">
                                <tr class="fw-bold text-success">
                                    <td class="ps-3 py-2">Total Gross Earnings (A)</td>
                                    <td class="text-end pe-3 py-2">${{ number_format($settlement->total_earnings ?? 0, 2) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Deductions -->
            <div class="col-md-6">
                <div class="card border h-100 shadow-none">
                    <div class="card-header bg-danger bg-opacity-10 border-bottom py-2">
                        <h6 class="fw-bold text-danger mb-0">B. Recoveries & Deductions</h6>
                    </div>
                    <div class="card-body p-0">
                        <table class="table table-sm mb-0 fs-13">
                            <tbody>
                                <tr>
                                    <td class="ps-3 py-2">Notice Shortfall Recovery</td>
                                    <td class="text-end pe-3 py-2 fw-semibold text-danger">${{ number_format($settlement->notice_shortfall_recovery ?? 0, 2) }}</td>
                                </tr>
                                <tr>
                                    <td class="ps-3 py-2">Outstanding Cash Advances</td>
                                    <td class="text-end pe-3 py-2 fw-semibold text-danger">${{ number_format($settlement->unsettled_advances_recovery ?? 0, 2) }}</td>
                                </tr>
                                <tr>
                                    <td class="ps-3 py-2">Asset Damage / Lost Hardware Fees</td>
                                    <td class="text-end pe-3 py-2 fw-semibold text-danger">${{ number_format($settlement->asset_damage_recovery ?? 0, 2) }}</td>
                                </tr>
                                <tr>
                                    <td class="ps-3 py-2">Tax / Statutory Deductions / Other</td>
                                    <td class="text-end pe-3 py-2 fw-semibold text-danger">${{ number_format($settlement->other_deductions ?? 0, 2) }}</td>
                                </tr>
                            </tbody>
                            <tfoot class="table-light border-top">
                                <tr class="fw-bold text-danger">
                                    <td class="ps-3 py-2">Total Deductions (B)</td>
                                    <td class="text-end pe-3 py-2">${{ number_format($settlement->total_deductions ?? 0, 2) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Net Summary Box -->
        <div class="p-3 rounded-3 border mb-4" style="background-color: #f0fdf4; border-color: #86efac !important;">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div>
                    <h5 class="fw-bold text-dark mb-0">NET SETTLEMENT PAYABLE (A - B)</h5>
                    <span class="text-muted fs-12">Channel: {{ ucfirst(str_replace('_', ' ', $settlement->settlement_channel ?? 'monthly_payroll')) }} &bull; Mode: {{ $settlement->payment_method ?? 'Bank Transfer' }}</span>
                </div>
                <div>
                    <h3 class="fw-bold text-success mb-0">${{ number_format($settlement->net_payable_amount ?? 0, 2) }}</h3>
                </div>
            </div>
        </div>

        @if($settlement && $settlement->paid_at)
            <div class="alert alert-success py-2 fs-12 mb-4">
                <strong>Settlement Cleared:</strong> Disbursed on {{ \Carbon\Carbon::parse($settlement->paid_at)->format('d M, Y H:i') }} (Ref: {{ $settlement->payment_reference }}).
            </div>
        @endif

        <!-- Signatures -->
        <div class="d-flex justify-content-between align-items-end pt-5 fs-13">
            <div class="text-center" style="width: 200px;">
                <div class="border-bottom mb-2 pb-4"></div>
                <div class="fw-bold text-dark">Employee Signature</div>
                <div class="text-muted fs-11">Acknowledged & Accepted</div>
            </div>
            <div class="text-center" style="width: 200px;">
                <div class="border-bottom mb-2 pb-4"></div>
                <div class="fw-bold text-dark">Finance / Accounts</div>
                <div class="text-muted fs-11">Verified & Prepared By</div>
            </div>
            <div class="text-center" style="width: 200px;">
                <div class="border-bottom mb-2 pb-4"></div>
                <div class="fw-bold text-dark">HR Management</div>
                <div class="text-muted fs-11">Authorized Signatory</div>
            </div>
        </div>
    </div>
</body>
</html>
