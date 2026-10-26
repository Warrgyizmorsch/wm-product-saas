<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Form 16</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
        h1 { font-size: 16px; text-align: center; margin: 0 0 4px; }
        h2 { font-size: 12px; margin: 14px 0 6px; }
        .sub { text-align: center; font-size: 10px; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        th, td { border: 1px solid #999; padding: 4px 6px; vertical-align: top; }
        th { background: #f0f0f0; text-align: left; }
        .r { text-align: right; }
        .label { width: 35%; background: #fafafa; }
        .note { font-size: 9px; color: #555; }
    </style>
</head>
<body>
    <h1>FORM No. 16</h1>
    <div class="sub">Certificate under section 203 of the Income-tax Act, 1961 for tax deducted at source on salary<br>
        Financial year {{ $fiscal_year }} &middot; Assessment year {{ (int) substr($fiscal_year, 0, 4) + 1 }}-{{ substr((string) ((int) substr($fiscal_year, 0, 4) + 2), -2) }}</div>

    <h2>PART A</h2>
    <table>
        <tr><th colspan="2">Employer (deductor)</th><th colspan="2">Employee</th></tr>
        <tr>
            <td class="label">Name</td><td>{{ $config?->deductor_name }}</td>
            <td class="label">Name</td><td>{{ $employee->full_name }}</td>
        </tr>
        <tr>
            <td class="label">TAN</td><td>{{ $config?->tan }}</td>
            <td class="label">PAN</td><td>{{ $employee->pan_card_number ?: ($pan ?: 'PANNOTAVBL') }}</td>
        </tr>
        <tr>
            <td class="label">PAN</td><td>{{ $config?->deductor_pan }}</td>
            <td class="label">Employee ID</td><td>{{ $employee->employee_id }}</td>
        </tr>
        <tr>
            <td class="label">Address</td><td colspan="3">{{ collect([$config?->address, $config?->city, $config?->state, $config?->pincode])->filter()->implode(', ') }}</td>
        </tr>
    </table>

    <table>
        <tr><th>Quarter</th><th class="r">Amount paid / credited</th><th class="r">Tax deducted</th><th class="r">Tax deposited</th></tr>
        @foreach ($quarters as $q)
            <tr>
                <td>{{ $q['label'] }}</td>
                <td class="r">{{ number_format($q['salary'], 2) }}</td>
                <td class="r">{{ number_format($q['tds'], 2) }}</td>
                <td class="r">{{ number_format($q['deposited'], 2) }}</td>
            </tr>
        @endforeach
        <tr>
            <th>Total</th>
            <th class="r">{{ number_format($total_salary, 2) }}</th>
            <th class="r">{{ number_format($total_tds, 2) }}</th>
            <th class="r">{{ number_format(collect($quarters)->sum('deposited'), 2) }}</th>
        </tr>
    </table>

    @if ($challans->isNotEmpty())
        <table>
            <tr><th>Challan no.</th><th>BSR code</th><th>Date deposited</th><th class="r">Amount (whole challan)</th></tr>
            @foreach ($challans as $challan)
                <tr>
                    <td>{{ $challan->challan_number }}</td>
                    <td>{{ $challan->bsr_code }}</td>
                    <td>{{ $challan->deposit_date->format('d/m/Y') }}</td>
                    <td class="r">{{ number_format($challan->tax_amount, 2) }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    <h2>PART B (salary summary)</h2>
    <table>
        <tr><th>Month</th><th class="r">Gross salary</th><th class="r">TDS</th></tr>
        @foreach ($months as $row)
            <tr>
                <td>{{ $row->deduction_date->format('M Y') }}</td>
                <td class="r">{{ number_format($row->base_amount, 2) }}</td>
                <td class="r">{{ number_format($row->tds_amount, 2) }}</td>
            </tr>
        @endforeach
        <tr><th>Gross salary for the year</th><th class="r">{{ number_format($total_salary, 2) }}</th><th class="r">{{ number_format($total_tds, 2) }}</th></tr>
    </table>
    <p class="note">Part B here lists the salary paid through payroll. Exemptions, Chapter VI-A deductions, the tax regime and the tax computation are not yet worked out by the ERP, so add them before issuing the statutory Form 16 (Part A from TRACES).</p>

    <table style="margin-top: 26px;">
        <tr>
            <td style="border: none; width: 60%;">Generated {{ now()->format('d M Y') }}.</td>
            <td style="border: none; text-align: center;">
                ____________________________<br>
                {{ $config?->responsible_person_name }}<br>
                {{ $config?->responsible_person_designation }}
            </td>
        </tr>
    </table>
</body>
</html>
