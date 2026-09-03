<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Full & Final Settlement Statement - {{ $exit->employee->full_name }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/feather-icons/4.29.0/feather.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap');
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #f1f5f9;
            color: #1e293b;
            padding: 30px 15px;
            font-size: 12px;
            line-height: 1.5;
        }

        .document-wrapper {
            max-width: 850px;
            margin: 0 auto;
            background: #ffffff;
            padding: 45px 50px;
            border-radius: 8px;
            box-shadow: 0 4px 25px rgba(0, 0, 0, 0.08);
            border: 1px solid #e2e8f0;
            position: relative;
        }

        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-30deg);
            font-size: 72px;
            font-weight: 800;
            color: rgba(30, 41, 59, 0.03);
            text-transform: uppercase;
            letter-spacing: 6px;
            user-select: none;
            pointer-events: none;
            z-index: 0;
            white-space: nowrap;
        }

        .doc-header {
            border-bottom: 2px solid #1c3faa;
            padding-bottom: 16px;
            margin-bottom: 22px;
            position: relative;
            z-index: 1;
        }

        .company-logo-badge {
            width: 48px;
            height: 48px;
            background: #1c3faa;
            color: #ffffff;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: 800;
            letter-spacing: 0.5px;
        }

        .company-name {
            font-size: 18px;
            font-weight: 800;
            color: #1c3faa;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
        }

        .company-meta {
            font-size: 11px;
            color: #64748b;
            line-height: 1.4;
        }

        .doc-title-bar {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-left: 4px solid #1c3faa;
            padding: 10px 16px;
            border-radius: 4px;
            margin-bottom: 20px;
            position: relative;
            z-index: 1;
        }

        .doc-title {
            font-size: 14px;
            font-weight: 700;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0;
        }

        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            position: relative;
            z-index: 1;
        }

        .info-table td {
            padding: 6px 10px;
            border: 1px solid #e2e8f0;
            vertical-align: middle;
            font-size: 11px;
        }

        .info-label {
            font-size: 10px;
            text-transform: uppercase;
            color: #64748b;
            font-weight: 600;
            letter-spacing: 0.3px;
        }

        .info-value {
            font-size: 11px;
            color: #0f172a;
            font-weight: 600;
        }

        .section-heading {
            font-size: 12px;
            font-weight: 700;
            color: #1c3faa;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1.5px solid #cbd5e1;
            padding-bottom: 4px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .breakdown-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
            font-size: 11px;
            position: relative;
            z-index: 1;
        }

        .breakdown-table th {
            background-color: #f8fafc;
            color: #334155;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 7px 10px;
            border: 1px solid #cbd5e1;
            text-align: left;
        }

        .breakdown-table td {
            padding: 6px 10px;
            border: 1px solid #cbd5e1;
            vertical-align: middle;
        }

        .amount-col {
            text-align: right;
            font-variant-numeric: tabular-nums;
        }

        .total-row {
            font-weight: 700;
            background-color: #f8fafc;
        }

        .net-settlement-box {
            background-color: #f0fdf4;
            border: 1.5px solid #86efac;
            border-radius: 6px;
            padding: 14px 18px;
            margin-bottom: 22px;
            position: relative;
            z-index: 1;
        }

        .net-settlement-title {
            font-size: 12px;
            font-weight: 700;
            color: #166534;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .net-settlement-amount {
            font-size: 18px;
            font-weight: 800;
            color: #15803d;
            text-align: right;
            font-variant-numeric: tabular-nums;
        }

        .words-block {
            font-size: 10px;
            color: #475569;
            font-style: italic;
            margin-top: 4px;
        }

        .clearance-badge {
            font-size: 9px;
            font-weight: 700;
            padding: 2px 6px;
            border-radius: 4px;
            text-transform: uppercase;
        }

        .signature-section {
            margin-top: 40px;
            width: 100%;
            position: relative;
            z-index: 1;
        }

        .signature-box {
            text-align: center;
        }

        .signature-line {
            width: 160px;
            border-bottom: 1px solid #475569;
            margin: 0 auto 6px auto;
        }

        .seal-box {
            width: 85px;
            height: 85px;
            border: 1.5px dashed #94a3b8;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #94a3b8;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
            text-align: center;
            margin: 0 auto;
            letter-spacing: 0.5px;
        }

        .doc-footer {
            margin-top: 35px;
            border-top: 1px dashed #cbd5e1;
            padding-top: 10px;
            text-align: center;
            font-size: 9px;
            color: #64748b;
            position: relative;
            z-index: 1;
        }

        @media print {
            body {
                background: #ffffff;
                padding: 0;
                color: #000000;
            }
            .document-wrapper {
                box-shadow: none;
                border: none;
                padding: 10px 0;
                max-width: 100%;
            }
            .no-print {
                display: none !important;
            }
            .breakdown-table th, .breakdown-table td, .info-table td {
                border-color: #94a3b8 !important;
            }
        }
    </style>
</head>
<body>
    @php
        $company = $exit->employee->company ?: \App\Domains\HRMS\Models\Company::first();
        $companyName = $company->company_name ?? 'Warrgyizmorsch Pvt Ltd';
        $companyInitials = strtoupper(substr($companyName, 0, 2));
        $refNumber = 'FNF/' . ($company->code ?? 'ORG') . '/' . date('Y') . '/' . str_pad((string)$exit->id, 4, '0', STR_PAD_LEFT);
        $issueDate = $settlement && $settlement->paid_at ? \Carbon\Carbon::parse($settlement->paid_at)->format('d M, Y') : date('d M, Y');
        
        // Convert number to words helper
        $amountToWords = function($number) {
            $f = new \NumberFormatter("en", \NumberFormatter::SPELLOUT);
            return ucfirst($f->format((float)$number));
        };
        $netAmount = (float)($settlement->net_payable_amount ?? ($settlement->net_settlement_amount ?? 0));
        $netAmountWords = class_exists('\NumberFormatter') ? $amountToWords($netAmount) : number_format($netAmount, 2);
    @endphp

    <div class="no-print text-center mb-4">
        <button onclick="window.print()" class="btn btn-primary px-4 py-2 fw-bold shadow-sm" style="background-color: #1c3faa; border-color: #1c3faa;">
            🖨️ Print / Save Statement (PDF)
        </button>
        <button onclick="window.close()" class="btn btn-outline-secondary px-3 py-2 ms-2">
            Close
        </button>
    </div>

    <div class="document-wrapper">
        <div class="watermark">{{ ($settlement->status ?? '') === 'paid' ? 'SETTLED' : 'STATEMENT' }}</div>

        <!-- Corporate Letterhead -->
        <div class="doc-header">
            <div class="d-flex justify-content-between align-items-start">
                <div class="d-flex align-items-center gap-3">
                    <div class="company-logo-badge">{{ $companyInitials }}</div>
                    <div>
                        <div class="company-name">{{ $companyName }}</div>
                        <div class="company-meta">
                            {{ $company->legal_name ?? 'Corporate Payroll & Employee Settlement Division' }}<br>
                            {{ $company->address ?? 'Corporate Headquarters' }} | Email: {{ $company->email ?? 'hr@company.com' }}
                        </div>
                    </div>
                </div>
                <div class="text-end">
                    <div class="fw-bold text-dark fs-12 mb-1">REF: {{ $refNumber }}</div>
                    <div class="text-muted fs-11">Date: {{ $issueDate }}</div>
                    <span class="badge {{ ($settlement->status ?? '') === 'paid' ? 'bg-success' : 'bg-warning text-dark' }} px-2 py-1 fs-10 text-uppercase mt-1">
                        {{ $settlement->status ?? 'Draft Settlement' }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Document Title -->
        <div class="doc-title-bar d-flex justify-content-between align-items-center">
            <h4 class="doc-title">Full & Final (F&F) Settlement Statement</h4>
            <span class="text-muted fs-11">Confidential Payroll Document</span>
        </div>

        <!-- Employee Information Grid -->
        <table class="info-table">
            <tr>
                <td width="25%">
                    <div class="info-label">Employee Name</div>
                    <div class="info-value">{{ $exit->employee->full_name }}</div>
                </td>
                <td width="25%">
                    <div class="info-label">Employee Code</div>
                    <div class="info-value">{{ $exit->employee->employee_id }}</div>
                </td>
                <td width="25%">
                    <div class="info-label">Designation</div>
                    <div class="info-value">{{ $exit->employee->designation->name ?? 'N/A' }}</div>
                </td>
                <td width="25%">
                    <div class="info-label">Department</div>
                    <div class="info-value">{{ $exit->employee->department->name ?? 'N/A' }}</div>
                </td>
            </tr>
            <tr>
                <td>
                    <div class="info-label">Date of Joining</div>
                    <div class="info-value">{{ $exit->employee->date_of_joining ? \Carbon\Carbon::parse($exit->employee->date_of_joining)->format('d M, Y') : 'N/A' }}</div>
                </td>
                <td>
                    <div class="info-label">Last Working Day (LWD)</div>
                    <div class="info-value text-danger">{{ $exit->effective_lwd ? \Carbon\Carbon::parse($exit->effective_lwd)->format('d M, Y') : 'N/A' }}</div>
                </td>
                <td>
                    <div class="info-label">Separation Type</div>
                    <div class="info-value">{{ ucfirst(str_replace('_', ' ', $exit->separation_type)) }}</div>
                </td>
                <td>
                    <div class="info-label">Monthly Gross CTC</div>
                    <div class="info-value">${{ number_format($exit->employee->current_salary, 2) }}</div>
                </td>
            </tr>
            <tr>
                <td>
                    <div class="info-label">Bank Name</div>
                    <div class="info-value">{{ $exit->employee->bank_name ?: 'On-Record Bank' }}</div>
                </td>
                <td>
                    <div class="info-label">Bank Account Number</div>
                    <div class="info-value">{{ $exit->employee->account_number ?: 'XXXXXXXX' . substr($exit->employee->employee_id, -3) }}</div>
                </td>
                <td>
                    <div class="info-label">PAN / Tax ID</div>
                    <div class="info-value">{{ $exit->employee->pan_card_number ?: '—' }}</div>
                </td>
                <td>
                    <div class="info-label">Payment Channel</div>
                    <div class="info-value">{{ ucfirst(str_replace('_', ' ', $settlement->settlement_channel ?? 'Direct Bank Transfer')) }}</div>
                </td>
            </tr>
        </table>

        <!-- Earnings & Deductions Breakdown -->
        <div class="row g-3 mb-2">
            <!-- Earnings -->
            <div class="col-6">
                <div class="section-heading">
                    <span>A. Gross Earnings & Receivables</span>
                    <span class="fs-10 text-muted">Amount ($)</span>
                </div>
                <table class="breakdown-table">
                    <thead>
                        <tr>
                            <th>Particulars / Dues</th>
                            <th class="amount-col" width="100">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Unpaid Working Days Salary ({{ $settlement->unpaid_salary_days ?? 0 }} days)</td>
                            <td class="amount-col fw-semibold">${{ number_format($settlement->unpaid_salary_amount ?? 0, 2) }}</td>
                        </tr>
                        <tr>
                            <td>Earned Leave Encashment ({{ $settlement->leave_encashment_days ?? 0 }} days)</td>
                            <td class="amount-col fw-semibold">${{ number_format($settlement->leave_encashment_amount ?? 0, 2) }}</td>
                        </tr>
                        <tr>
                            <td>Gratuity Benefits (Statutory Entitlement)</td>
                            <td class="amount-col fw-semibold">${{ number_format($settlement->gratuity_amount ?? 0, 2) }}</td>
                        </tr>
                        <tr>
                            <td>Approved Travel & Expense Claims</td>
                            <td class="amount-col fw-semibold">${{ number_format($settlement->other_earnings ?? 0, 2) }}</td>
                        </tr>
                        <tr>
                            <td>Bonus, Arrears & Special Allowances</td>
                            <td class="amount-col fw-semibold">${{ number_format($settlement->bonus_amount ?? 0, 2) }}</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="total-row text-success">
                            <td>TOTAL GROSS EARNINGS (A)</td>
                            <td class="amount-col">${{ number_format($settlement->total_earnings ?? 0, 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Deductions -->
            <div class="col-6">
                <div class="section-heading">
                    <span>B. Recoveries & Deductions</span>
                    <span class="fs-10 text-muted">Amount ($)</span>
                </div>
                <table class="breakdown-table">
                    <thead>
                        <tr>
                            <th>Particulars / Liabilities</th>
                            <th class="amount-col" width="100">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Notice Shortfall / Buyout Recovery</td>
                            <td class="amount-col fw-semibold text-danger">${{ number_format($settlement->notice_shortfall_recovery ?? 0, 2) }}</td>
                        </tr>
                        <tr>
                            <td>Unsettled Cash Advances & Imprest</td>
                            <td class="amount-col fw-semibold text-danger">${{ number_format($settlement->unsettled_advances_recovery ?? 0, 2) }}</td>
                        </tr>
                        <tr>
                            <td>Asset Damage / Lost Equipment Fees</td>
                            <td class="amount-col fw-semibold text-danger">${{ number_format($settlement->asset_damage_recovery ?? 0, 2) }}</td>
                        </tr>
                        <tr>
                            <td>TDS / Tax & Statutory Deductions</td>
                            <td class="amount-col fw-semibold text-danger">${{ number_format($settlement->other_deductions ?? 0, 2) }}</td>
                        </tr>
                        <tr>
                            <td>Other Adjustments / Loans</td>
                            <td class="amount-col fw-semibold text-danger">$0.00</td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="total-row text-danger">
                            <td>TOTAL DEDUCTIONS (B)</td>
                            <td class="amount-col">${{ number_format($settlement->total_deductions ?? 0, 2) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Net Settlement Amount Box -->
        <div class="net-settlement-box">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <div class="net-settlement-title">Net Settlement Payable (A - B)</div>
                    <div class="words-block">Amount in words: {{ $netAmountWords }} Dollars Only</div>
                    @if($settlement && $settlement->paid_at)
                        <div class="fs-10 text-success mt-1">
                            <strong>Disbursement Confirmed:</strong> Paid via {{ $settlement->payment_method ?? 'Bank Transfer' }} (Ref: {{ $settlement->payment_reference }}) on {{ \Carbon\Carbon::parse($settlement->paid_at)->format('d M, Y') }}
                        </div>
                    @endif
                </div>
                <div class="net-settlement-amount">
                    ${{ number_format($netAmount, 2) }}
                </div>
            </div>
        </div>

        <!-- Department Clearance Summary Table -->
        <div class="section-heading mt-3">
            <span>Department Clearance & Handover Summary</span>
            <span class="fs-10 text-muted">All Departments Cleared</span>
        </div>
        <table class="breakdown-table mb-4">
            <thead>
                <tr>
                    <th width="22%">Department</th>
                    <th width="38%">Scope & Clearance Description</th>
                    <th width="20%">Verified By</th>
                    <th width="20%" class="text-center">Clearance Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($exit->clearances as $clr)
                    <tr>
                        <td class="fw-bold text-uppercase fs-10 text-dark">{{ $clr->department }}</td>
                        <td>{{ $clr->item_name }} @if($clr->remarks)<span class="text-muted">({{ $clr->remarks }})</span>@endif</td>
                        <td>{{ $clr->clearedByUser->name ?? 'Dept Officer' }}</td>
                        <td class="text-center">
                            @if($clr->status === 'cleared' || $clr->status === 'waived')
                                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 clearance-badge">CLEARED</span>
                            @elseif($clr->status === 'issues_found')
                                <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 clearance-badge">ADJUSTED (${{ number_format($clr->deduction_amount, 2) }})</span>
                            @else
                                <span class="badge bg-warning bg-opacity-10 text-dark border border-warning border-opacity-25 clearance-badge">PENDING</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td class="fw-bold text-uppercase fs-10 text-dark">IT & INFRASTRUCTURE</td>
                        <td>Laptop, Workstation Access, Email & Software Licenses</td>
                        <td>IT Administration</td>
                        <td class="text-center"><span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 clearance-badge">CLEARED</span></td>
                    </tr>
                    <tr>
                        <td class="fw-bold text-uppercase fs-10 text-dark">FINANCE & ACCOUNTS</td>
                        <td>Cash Advances, Travel Claims, Company Credit Card</td>
                        <td>Finance Controller</td>
                        <td class="text-center"><span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 clearance-badge">CLEARED</span></td>
                    </tr>
                    <tr>
                        <td class="fw-bold text-uppercase fs-10 text-dark">HR & OPERATIONS</td>
                        <td>Identity Card, Access Badges, Final Handover Documentation</td>
                        <td>HR Management</td>
                        <td class="text-center"><span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 clearance-badge">CLEARED</span></td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <!-- Signatures & Authorization Section -->
        <div class="signature-section">
            <div class="row text-center fs-11">
                <div class="col-4">
                    <div class="signature-box">
                        <div class="signature-line"></div>
                        <div class="fw-bold text-dark">Employee Signature</div>
                        <div class="text-muted fs-10">Accepted & Received Full Dues</div>
                    </div>
                </div>
                <div class="col-4">
                    <div class="signature-box">
                        <div class="signature-line"></div>
                        <div class="fw-bold text-dark">Finance & Payroll Manager</div>
                        <div class="text-muted fs-10">Verified & Accounts Settled</div>
                    </div>
                </div>
                <div class="col-4">
                    <div class="signature-box">
                        <div class="seal-box mb-2">[ OFFICIAL SEAL ]</div>
                        <div class="fw-bold text-dark">Authorized Signatory</div>
                        <div class="text-muted fs-10">Human Resources Division</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Official Footer -->
        <div class="doc-footer">
            This is a computer-generated Full & Final Settlement document verified under {{ $companyName }} HRMS Enterprise Security System.<br>
            Any queries regarding this statement should be directed to the Corporate Payroll Department within 15 days of issue.
        </div>
    </div>
</body>
</html>
