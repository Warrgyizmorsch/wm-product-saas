<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Form 16A</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
        h1 { font-size: 16px; text-align: center; margin: 0 0 4px; }
        .sub { text-align: center; font-size: 10px; margin-bottom: 12px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        th, td { border: 1px solid #999; padding: 4px 6px; vertical-align: top; }
        th { background: #f0f0f0; text-align: left; }
        .r { text-align: right; }
        .label { width: 35%; background: #fafafa; }
    </style>
</head>
<body>
    <h1>FORM No. 16A</h1>
    <div class="sub">Certificate under section 203 of the Income-tax Act, 1961 for tax deducted at source<br>
        Financial year {{ $fiscal_year }} &middot; {{ $quarterLabel }}</div>

    <table>
        <tr><th colspan="2">Deductor</th><th colspan="2">Deductee</th></tr>
        <tr>
            <td class="label">Name</td><td>{{ $config?->deductor_name }}</td>
            <td class="label">Name</td><td>{{ $vendor->name }}</td>
        </tr>
        <tr>
            <td class="label">TAN</td><td>{{ $config?->tan }}</td>
            <td class="label">PAN</td><td>{{ $vendor->pan ?: 'PANNOTAVBL' }}</td>
        </tr>
        <tr>
            <td class="label">PAN</td><td>{{ $config?->deductor_pan }}</td>
            <td class="label">Address</td><td>{{ $vendor->billing_address ?: $vendor->address }}</td>
        </tr>
        <tr>
            <td class="label">Address</td><td colspan="3">{{ collect([$config?->address, $config?->city, $config?->state, $config?->pincode])->filter()->implode(', ') }}</td>
        </tr>
    </table>

    <table>
        <tr><th>Date</th><th>Document</th><th>Section</th><th class="r">Amount paid / credited</th><th class="r">Rate</th><th class="r">TDS</th><th>Challan / BSR / date</th></tr>
        @foreach ($deductions as $d)
            <tr>
                <td>{{ $d->deduction_date->format('d/m/Y') }}</td>
                <td>{{ $d->source_number }}</td>
                <td>{{ $d->section?->code }}</td>
                <td class="r">{{ number_format($d->base_amount + $d->catchup_base, 2) }}</td>
                <td class="r">{{ rtrim(rtrim(number_format($d->rate, 3), '0'), '.') }}%</td>
                <td class="r">{{ number_format($d->tds_amount, 2) }}</td>
                <td>@if ($d->challan){{ $d->challan->challan_number }} / {{ $d->challan->bsr_code }} / {{ $d->challan->deposit_date->format('d/m/Y') }}@else Not yet deposited @endif</td>
            </tr>
        @endforeach
        <tr>
            <th colspan="3">Total</th>
            <th class="r">{{ number_format($total_base, 2) }}</th>
            <th></th>
            <th class="r">{{ number_format($total_tds, 2) }}</th>
            <th></th>
        </tr>
    </table>

    <p>Certified that a sum of Rs {{ number_format($total_tds, 2) }} has been deducted and
        {{ $deductions->every(fn ($d) => $d->tds_challan_id) ? 'deposited to the credit of the Central Government' : 'is pending deposit' }}
        as shown above.</p>

    <table style="margin-top: 30px;">
        <tr>
            <td style="border: none; width: 60%;">Generated {{ now()->format('d M Y') }}. Issue the TRACES-generated Form 16A as the statutory certificate.</td>
            <td style="border: none; text-align: center;">
                ____________________________<br>
                {{ $config?->responsible_person_name }}<br>
                {{ $config?->responsible_person_designation }}
            </td>
        </tr>
    </table>
</body>
</html>
