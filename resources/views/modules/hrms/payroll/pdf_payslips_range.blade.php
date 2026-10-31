<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payslips - {{ $employee->full_name }} ({{ $fromMonth }} to {{ $toMonth }})</title>
    <style>
        @page {
            margin: 12px 18px;
            size: a4 portrait;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            color: #333333;
            font-size: 11px;
            line-height: 1.4;
            margin: 0;
            padding: 0;
        }
        .page-break {
            page-break-after: always;
        }
        .payslip-page {
            width: 100%;
        }
        .container {
            width: 100%;
            padding: 12px 14px;
            box-sizing: border-box;
        }
        .header {
            border-bottom: 2px solid #1c3faa;
            padding-bottom: 10px;
            margin-bottom: 14px;
        }
        .company-name {
            font-size: 15px;
            font-weight: bold;
            color: #1c3faa;
            text-transform: uppercase;
        }
        .company-details {
            font-size: 9px;
            color: #666666;
            margin-top: 3px;
        }
        .slip-title {
            font-size: 13px;
            font-weight: bold;
            text-align: right;
            color: #333333;
            text-transform: uppercase;
            margin-top: -28px;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }
        .info-table td {
            padding: 5px 8px;
            border: 1px solid #e2e8f0;
            vertical-align: top;
        }
        .info-label {
            font-size: 9px;
            text-transform: uppercase;
            color: #64748b;
            font-weight: bold;
        }
        .info-value {
            font-size: 10px;
            color: #1e293b;
            font-weight: 600;
        }
        .section-title {
            font-size: 11px;
            font-weight: bold;
            color: #1c3faa;
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 4px;
            margin-bottom: 6px;
            text-transform: uppercase;
        }
        .breakdown-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }
        .breakdown-table th {
            background-color: #f1f5f9;
            color: #334155;
            font-size: 9.5px;
            font-weight: bold;
            text-transform: uppercase;
            padding: 6px 8px;
            border: 1px solid #cbd5e1;
            text-align: left;
        }
        .breakdown-table td {
            padding: 6px 8px;
            border: 1px solid #cbd5e1;
            vertical-align: top;
            font-size: 9.5px;
        }
        .amount-col {
            text-align: right;
        }
        .total-row {
            font-weight: bold;
            background-color: #f8fafc;
        }
        .net-pay-box {
            background-color: #f0fdf6;
            border: 1px solid #bbf7d0;
            border-radius: 6px;
            padding: 10px 14px;
            margin-bottom: 24px;
        }
        .net-pay-title {
            font-size: 10.5px;
            font-weight: bold;
            color: #166534;
        }
        .net-pay-amount {
            font-size: 15px;
            font-weight: bold;
            color: #16a34a;
            text-align: right;
            margin-top: -16px;
        }
        .words {
            font-size: 9.5px;
            font-style: italic;
            color: #475569;
            margin-top: 6px;
        }
        .signature-section {
            margin-top: 30px;
            width: 100%;
        }
        .signature-box {
            width: 45%;
            display: inline-block;
            text-align: center;
        }
        .signature-line {
            width: 140px;
            border-bottom: 1px solid #475569;
            margin: 0 auto 5px auto;
        }
        .footer {
            margin-top: 30px;
            border-top: 1px dashed #cbd5e1;
            padding-top: 10px;
            text-align: center;
            font-size: 8.5px;
            color: #64748b;
        }
    </style>
</head>
<body>
    @foreach($payslips as $index => $slip)
        @php
            $emp = $slip['employee'];
            $comp = $slip['company'];
            $run = $slip['run'];
            $calc = $slip['calc'];
            $details = $slip['details'];
            $netPayoutInWords = $slip['netPayoutInWords'];
            $payroll_month_formatted = $slip['payroll_month_formatted'];
            $earningItems = array_filter($details, fn($i) => ($i['type'] ?? '') === 'earning');
            $deductionItems = array_filter($details, fn($i) => ($i['type'] ?? '') === 'deduction');
            $maxRows = max(count($earningItems), count($deductionItems), 3);
        @endphp
        <div class="payslip-page">
            <div class="container">
                <!-- Header -->
                <div class="header">
                    <div class="company-name">{{ $comp->company_name ?? $comp->legal_name ?? config('app.name', 'ERP') }}</div>
                    <div class="company-details">
                        @php
                            $companyAddr = array_filter([$comp->address ?? null, $comp->city ?? null, $comp->state ?? null, $comp->postal_code ?? null]);
                        @endphp
                        @if(!empty($companyAddr))
                            {{ implode(', ', $companyAddr) }}<br>
                        @endif
                        @if(!empty($comp->email) || !empty($comp->phone))
                            @if(!empty($comp->email))Email: {{ $comp->email }}@endif
                            @if(!empty($comp->email) && !empty($comp->phone)) | @endif
                            @if(!empty($comp->phone))Tel: {{ $comp->phone }}@endif
                        @endif
                    </div>
                    <div class="slip-title">
                        Salary Slip<br>
                        <span style="font-size:9px; font-weight:normal; color:#666666;">For the Month of {{ $payroll_month_formatted }}</span>
                    </div>
                </div>

                <!-- Employee Info -->
                <table class="info-table">
                    <tr>
                        <td width="25%">
                            <div class="info-label">Employee Name</div>
                            <div class="info-value">{{ $emp->full_name }}</div>
                        </td>
                        <td width="25%">
                            <div class="info-label">Employee ID</div>
                            <div class="info-value">{{ $emp->employee_id }}</div>
                        </td>
                        <td width="25%">
                            <div class="info-label">Designation</div>
                            <div class="info-value">{{ $emp->job_title ?? $emp->designation?->name ?? '—' }}</div>
                        </td>
                        <td width="25%">
                            <div class="info-label">Department</div>
                            <div class="info-value">{{ $emp->department?->name ?? '—' }}</div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="info-label">Bank Name</div>
                            <div class="info-value">{{ $emp->bank_name ?? '—' }}</div>
                        </td>
                        <td>
                            <div class="info-label">Account Number</div>
                            <div class="info-value">{{ $emp->account_number ?? '—' }}</div>
                        </td>
                        <td>
                            <div class="info-label">PAN Number</div>
                            <div class="info-value">{{ $emp->pan_card_number ?? $emp->pan_number ?? '—' }}</div>
                        </td>
                        <td>
                            <div class="info-label">UAN / PF Number</div>
                            <div class="info-value">{{ $emp->uan_number ?? '—' }}</div>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="info-label">Total Cycle Days</div>
                            <div class="info-value">{{ $calc['total_days'] ?? 30 }} Days</div>
                        </td>
                        <td>
                            <div class="info-label">Paid Days</div>
                            <div class="info-value">{{ $calc['paid_days'] ?? (($calc['total_days'] ?? 30) - ($calc['lop_days'] ?? 0)) }} Days</div>
                        </td>
                        <td>
                            <div class="info-label">Loss of Pay (LOP)</div>
                            <div class="info-value" style="{{ ($calc['lop_days'] ?? 0) > 0 ? 'color:#ef4444;' : '' }}">{{ $calc['lop_days'] ?? 0 }} Days</div>
                        </td>
                        <td>
                            <div class="info-label">Salary Mode</div>
                            <div class="info-value">{{ !empty($emp->account_number) ? 'Bank Transfer' : 'Cash / Cheque' }}</div>
                        </td>
                    </tr>
                </table>

                <!-- Earnings and Deductions Grid -->
                <table style="width: 100%; border-spacing: 0; margin-bottom: 16px;">
                    <tr>
                        <!-- Earnings Column -->
                        <td width="48%" style="vertical-align: top; padding: 0 8px 0 0;">
                            <div class="section-title">Earnings</div>
                            <table class="breakdown-table">
                                <thead>
                                    <tr>
                                        <th>Component Name</th>
                                        <th class="amount-col" width="90">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php $grossEarning = 0; @endphp
                                    @foreach($earningItems as $item)
                                        @php $grossEarning += ($item['calculated_value'] ?? 0); @endphp
                                        <tr>
                                            <td>{{ $item['name'] }}</td>
                                            <td class="amount-col">&#8377;{{ number_format($item['calculated_value'] ?? 0, 2) }}</td>
                                        </tr>
                                    @endforeach

                                    <!-- Fill empty rows to align heights -->
                                    @for ($k = 0; $k < ($maxRows - count($earningItems)); $k++)
                                        <tr>
                                            <td>&nbsp;</td>
                                            <td class="amount-col">&nbsp;</td>
                                        </tr>
                                    @endfor

                                    <tr class="total-row">
                                        <td>Gross Earnings</td>
                                        <td class="amount-col">&#8377;{{ number_format($calc['total_earnings'] ?? $grossEarning, 2) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </td>

                        <!-- Deductions Column -->
                        <td width="48%" style="vertical-align: top; padding: 0 0 0 8px;">
                            <div class="section-title">Deductions</div>
                            <table class="breakdown-table">
                                <thead>
                                    <tr>
                                        <th>Component Name</th>
                                        <th class="amount-col" width="90">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php $totalDeduction = 0; @endphp
                                    @foreach($deductionItems as $item)
                                        @php $totalDeduction += ($item['calculated_value'] ?? 0); @endphp
                                        <tr>
                                            <td>{{ $item['name'] }}</td>
                                            <td class="amount-col">&#8377;{{ number_format($item['calculated_value'] ?? 0, 2) }}</td>
                                        </tr>
                                    @endforeach

                                    <!-- Fill empty rows to align heights -->
                                    @for ($k = 0; $k < ($maxRows - count($deductionItems)); $k++)
                                        <tr>
                                            <td>&nbsp;</td>
                                            <td class="amount-col">&nbsp;</td>
                                        </tr>
                                    @endfor

                                    <tr class="total-row">
                                        <td>Total Deductions</td>
                                        <td class="amount-col">&#8377;{{ number_format($calc['total_deductions'] ?? $totalDeduction, 2) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </td>
                    </tr>
                </table>

                <!-- Net Salary Box -->
                <div class="net-pay-box">
                    <div class="net-pay-title">NET SALARY PAYOUT</div>
                    <div class="net-pay-amount">&#8377;{{ number_format($calc['net_payout'] ?? 0, 2) }}</div>
                    <div class="words"><strong>Net Payable in Words:</strong> {{ $netPayoutInWords }}</div>
                </div>

                <!-- Signatures -->
                <div class="signature-section">
                    <div class="signature-box" style="float: left;">
                        <div class="signature-line"></div>
                        <span style="font-size: 8.5px; font-weight: bold; color: #475569;">Employee Signature</span>
                    </div>
                    <div class="signature-box" style="float: right;">
                        <div class="signature-line"></div>
                        <span style="font-size: 8.5px; font-weight: bold; color: #475569;">Authorized Signatory</span>
                    </div>
                    <div style="clear: both;"></div>
                </div>

                <!-- Disclaimer -->
                <div class="footer">
                    Page {{ $index + 1 }} of {{ count($payslips) }} &bull; Computer-generated document for month: {{ $payroll_month_formatted }}.
                </div>
            </div>
        </div>
        @if(!$loop->last)
            <div style="page-break-after: always; clear: both; height: 0; line-height: 0; font-size: 0;"></div>
        @endif
    @endforeach
</body>
</html>

