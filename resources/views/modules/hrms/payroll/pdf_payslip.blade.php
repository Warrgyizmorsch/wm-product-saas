<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payslip - {{ $employee->full_name }}</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            color: #333333;
            font-size: 11px;
            line-height: 1.5;
            margin: 0;
            padding: 0;
        }
        .container {
            width: 100%;
            padding: 20px;
        }
        .header {
            border-bottom: 2px solid #1c3faa;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }
        .company-name {
            font-size: 16px;
            font-weight: bold;
            color: #1c3faa;
            text-transform: uppercase;
        }
        .company-details {
            font-size: 10px;
            color: #666666;
            margin-top: 4px;
        }
        .slip-title {
            font-size: 13px;
            font-weight: bold;
            text-align: right;
            color: #333333;
            text-transform: uppercase;
            margin-top: -30px;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .info-table td {
            padding: 6px 8px;
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
            margin-bottom: 8px;
            text-transform: uppercase;
        }
        .breakdown-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .breakdown-table th {
            background-color: #f1f5f9;
            color: #334155;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            padding: 8px;
            border: 1px solid #cbd5e1;
            text-align: left;
        }
        .breakdown-table td {
            padding: 8px;
            border: 1px solid #cbd5e1;
            vertical-align: top;
            font-size: 10px;
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
            padding: 12px 16px;
            margin-bottom: 30px;
        }
        .net-pay-title {
            font-size: 11px;
            font-weight: bold;
            color: #166534;
        }
        .net-pay-amount {
            font-size: 16px;
            font-weight: bold;
            color: #16a34a;
            text-align: right;
            margin-top: -18px;
        }
        .words {
            font-size: 10px;
            font-style: italic;
            color: #475569;
            margin-top: 8px;
        }
        .footer {
            margin-top: 50px;
            border-top: 1px dashed #cbd5e1;
            padding-top: 12px;
            text-align: center;
            font-size: 9px;
            color: #64748b;
        }
        .signature-section {
            margin-top: 40px;
            width: 100%;
        }
        .signature-box {
            width: 45%;
            display: inline-block;
            text-align: center;
        }
        .signature-line {
            width: 150px;
            border-bottom: 1px solid #475569;
            margin: 0 auto 6px auto;
        }
    </style>
</head>
<body>
    <div class="container">
        
        <!-- Header -->
        <div class="header">
            <div class="company-name">{{ $company->company_name ?? 'WARRGYIZMORSCH SAAS' }}</div>
            <div class="company-details">
                {{ $company->address ?? 'Corporate Head Office, Tech Park Area' }}<br>
                Email: {{ $company->email ?? 'finance@warrgyizmorsch.com' }} | Tel: {{ $company->phone ?? '+91 1800-PAYROLL' }}
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
                    <div class="info-value">{{ $employee->full_name }}</div>
                </td>
                <td width="25%">
                    <div class="info-label">Employee ID</div>
                    <div class="info-value">{{ $employee->employee_id }}</div>
                </td>
                <td width="25%">
                    <div class="info-label">Designation</div>
                    <div class="info-value">{{ $employee->job_title }}</div>
                </td>
                <td width="25%">
                    <div class="info-label">Department</div>
                    <div class="info-value">{{ $employee->department->name ?? 'HR & General Admin' }}</div>
                </td>
            </tr>
            <tr>
                <td>
                    <div class="info-label">Bank Name</div>
                    <div class="info-value">{{ $employee->bank_name ?? 'N/A' }}</div>
                </td>
                <td>
                    <div class="info-label">Account Number</div>
                    <div class="info-value">{{ $employee->account_number ?? 'N/A' }}</div>
                </td>
                <td>
                    <div class="info-label">PAN Number</div>
                    <div class="info-value">{{ $employee->pan_number ?? '—' }}</div>
                </td>
                <td>
                    <div class="info-label">UAN / PF Number</div>
                    <div class="info-value">{{ $employee->uan_number ?? '—' }}</div>
                </td>
            </tr>
            <tr>
                <td>
                    <div class="info-label">Total Cycle Days</div>
                    <div class="info-value">{{ $calc['total_days'] ?? 30 }} Days</div>
                </td>
                <td>
                    <div class="info-label">Paid Days</div>
                    <div class="info-value">{{ ($calc['total_days'] ?? 30) - ($calc['lop_days'] ?? 0) }} Days</div>
                </td>
                <td>
                    <div class="info-label">Loss of Pay (LOP)</div>
                    <div class="info-value" style="{{ ($calc['lop_days'] ?? 0) > 0 ? 'color:#ef4444;' : '' }}">{{ $calc['lop_days'] ?? 0 }} Days</div>
                </td>
                <td>
                    <div class="info-label">Salary Mode</div>
                    <div class="info-value">Bank Transfer</div>
                </td>
            </tr>
        </table>

        <!-- Earnings and Deductions Grid -->
        <table style="width: 100%; border-spacing: 0; margin-bottom: 20px;">
            <tr>
                <!-- Earnings Column -->
                <td width="48%" style="vertical-align: top; padding: 0 10px 0 0;">
                    <div class="section-title">Earnings</div>
                    <table class="breakdown-table">
                        <thead>
                            <tr>
                                <th>Component Name</th>
                                <th class="amount-col" width="100">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $grossEarning = 0; @endphp
                            @foreach($details as $item)
                                @if(($item['type'] ?? '') === 'earning')
                                    @php $grossEarning += ($item['calculated_value'] ?? 0); @endphp
                                    <tr>
                                        <td>{{ $item['name'] }}</td>
                                        <td class="amount-col">&#8377;{{ number_format($item['calculated_value'] ?? 0, 2) }}</td>
                                    </tr>
                                @endif
                            @endforeach

                            @if(($calc['adhoc_earnings'] ?? 0) > 0)
                                @php $grossEarning += $calc['adhoc_earnings']; @endphp
                                <tr>
                                    <td>Ad-hoc Earnings (Bonus)</td>
                                    <td class="amount-col">&#8377;{{ number_format($calc['adhoc_earnings'], 2) }}</td>
                                </tr>
                            @endif

                            @if(($calc['retro_lop_reversals'] ?? 0) > 0)
                                @php $grossEarning += $calc['retro_lop_reversals']; @endphp
                                <tr>
                                    <td>Retroactive LOP Refunds</td>
                                    <td class="amount-col">&#8377;{{ number_format($calc['retro_lop_reversals'], 2) }}</td>
                                </tr>
                            @endif

                            <!-- Fill empty rows to align heights -->
                            @for ($k = 0; $k < max(0, 5 - count($details)); $k++)
                                <tr>
                                    <td>&nbsp;</td>
                                    <td class="amount-col">&nbsp;</td>
                                </tr>
                            @endfor

                            <tr class="total-row">
                                <td>Gross Earnings</td>
                                <td class="amount-col">&#8377;{{ number_format($grossEarning, 2) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </td>

                <!-- Deductions Column -->
                <td width="48%" style="vertical-align: top; padding: 0 0 0 10px;">
                    <div class="section-title">Deductions</div>
                    <table class="breakdown-table">
                        <thead>
                            <tr>
                                <th>Component Name</th>
                                <th class="amount-col" width="100">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php $totalDeduction = 0; @endphp
                            @foreach($details as $item)
                                @if(($item['type'] ?? '') === 'deduction')
                                    @php $totalDeduction += ($item['calculated_value'] ?? 0); @endphp
                                    <tr>
                                        <td>{{ $item['name'] }}</td>
                                        <td class="amount-col">&#8377;{{ number_format($item['calculated_value'] ?? 0, 2) }}</td>
                                    </tr>
                                @endif
                            @endforeach

                            @if(($calc['adhoc_deductions'] ?? 0) > 0)
                                @php $totalDeduction += $calc['adhoc_deductions']; @endphp
                                <tr>
                                    <td>Ad-hoc Deductions</td>
                                    <td class="amount-col">&#8377;{{ number_format($calc['adhoc_deductions'], 2) }}</td>
                                </tr>
                            @endif

                            @if(($calc['lop_deduction'] ?? 0) > 0)
                                <tr>
                                    <td>Loss of Pay Deduction</td>
                                    <td class="amount-col" style="color: #64748b; font-style: italic;">Spliced</td>
                                </tr>
                            @endif

                            <!-- Fill empty rows to align heights -->
                            @for ($k = 0; $k < max(0, 5 - count($details)); $k++)
                                <tr>
                                    <td>&nbsp;</td>
                                    <td class="amount-col">&nbsp;</td>
                                </tr>
                            @endfor

                            <tr class="total-row">
                                <td>Total Deductions</td>
                                <td class="amount-col">&#8377;{{ number_format($totalDeduction, 2) }}</td>
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
                <span style="font-size: 9px; font-weight: bold; color: #475569;">Employee Signature</span>
            </div>
            <div class="signature-box" style="float: right;">
                <div class="signature-line"></div>
                <span style="font-size: 9px; font-weight: bold; color: #475569;">Authorized Signatory</span>
            </div>
            <div style="clear: both;"></div>
        </div>

        <!-- Disclaimer -->
        <div class="footer">
            This is a computer-generated document and does not require a physical signature or company stamp.
        </div>

    </div>
</body>
</html>
