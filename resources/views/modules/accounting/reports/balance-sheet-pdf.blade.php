<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Balance Sheet {{ $period->name }}</title>
    <style>
        @page {
            margin: 12mm 15mm;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            color: #222;
            font-size: 11px;
            line-height: 1.4;
            margin: 0;
            padding: 0;
        }
        .header {
            text-align: center;
            margin-bottom: 14px;
        }
        .tenant-name {
            font-size: 16px;
            font-weight: bold;
            color: #1e293b;
        }
        .report-title {
            font-size: 13px;
            font-weight: bold;
            margin-top: 2px;
        }
        .report-subtitle {
            font-size: 10px;
            color: #666;
        }
        table.bs-table {
            width: 100%;
            border-collapse: collapse;
        }
        table.bs-table td, table.bs-table th {
            padding: 4px 6px;
            vertical-align: top;
        }
        table.bs-table th {
            background-color: #f1f3f5;
            text-transform: uppercase;
            font-size: 9px;
            border-bottom: 1px solid #ccc;
        }
        .col-half {
            width: 50%;
        }
        .group-row td {
            font-weight: bold;
            background-color: #f8f9fa;
            padding-top: 6px;
        }
        .total-row td {
            font-weight: bold;
            border-top: 1px solid #333;
        }
        .grand-total-row td {
            font-weight: bold;
            border-top: 2px solid #222;
            background-color: #f1f3f5;
        }
        .amount {
            text-align: right;
        }
        .code {
            color: #666;
            width: 40px;
        }
        .status {
            text-align: center;
            margin-bottom: 8px;
            font-size: 10px;
            font-weight: bold;
        }
        .status.balanced { color: #1a7f37; }
        .status.unbalanced { color: #c0392b; }
    </style>
</head>
<body>
    <div class="header">
        <div class="tenant-name">{{ tenant()?->name }}</div>
        <div class="report-title">Balance Sheet</div>
        <div class="report-subtitle">as at {{ \Illuminate\Support\Carbon::parse($period->end_date)->format('d M Y') }} (end of {{ $period->name }})</div>
    </div>

    <div class="status {{ $isBalanced ? 'balanced' : 'unbalanced' }}">
        {{ $isBalanced ? 'BALANCED' : 'OUT OF BALANCE' }}
    </div>

    <table class="bs-table">
        <tr>
            <td class="col-half" style="padding: 0;">
                <table class="bs-table">
                    <tr><th colspan="2">Liabilities</th></tr>

                    <tr class="group-row"><td>Capital Account</td><td class="amount">{{ number_format($totals['equity'], 2) }}</td></tr>
                    @foreach ($sections['equity'] as $row)
                        <tr><td class="code">{{ $row['account']->code }}</td><td>{{ $row['account']->name }}<span class="amount" style="float:right">{{ number_format($row['balance'], 2) }}</span></td></tr>
                    @endforeach
                    <tr><td>Net Profit for the Period</td><td class="amount">{{ number_format($netIncome, 2) }}</td></tr>

                    @if ($sections['liability']['non_current']->isNotEmpty())
                        <tr class="group-row"><td>Non-Current Liabilities</td><td class="amount">{{ number_format($totals['liability_non_current'], 2) }}</td></tr>
                        @foreach ($sections['liability']['non_current'] as $row)
                            <tr><td class="code">{{ $row['account']->code }}</td><td>{{ $row['account']->name }}<span class="amount" style="float:right">{{ number_format($row['balance'], 2) }}</span></td></tr>
                        @endforeach
                    @endif

                    <tr class="group-row"><td>Current Liabilities</td><td class="amount">{{ number_format($totals['liability_current'], 2) }}</td></tr>
                    @forelse ($sections['liability']['current'] as $row)
                        <tr><td class="code">{{ $row['account']->code }}</td><td>{{ $row['account']->name }}<span class="amount" style="float:right">{{ number_format($row['balance'], 2) }}</span></td></tr>
                    @empty
                        <tr><td colspan="2" style="color:#999">No accounts.</td></tr>
                    @endforelse

                    <tr class="grand-total-row"><td>Total</td><td class="amount">{{ number_format($totals['liability'] + $totals['equity'], 2) }}</td></tr>
                </table>
            </td>
            <td class="col-half" style="padding: 0;">
                <table class="bs-table">
                    <tr><th colspan="2">Assets</th></tr>

                    <tr class="group-row"><td>Fixed Assets</td><td class="amount">{{ number_format($totals['asset_non_current'], 2) }}</td></tr>
                    @forelse ($sections['asset']['non_current'] as $row)
                        <tr><td class="code">{{ $row['account']->code }}</td><td>{{ $row['account']->name }}<span class="amount" style="float:right">{{ number_format($row['balance'], 2) }}</span></td></tr>
                    @empty
                        <tr><td colspan="2" style="color:#999">No accounts.</td></tr>
                    @endforelse

                    <tr class="group-row"><td>Current Assets</td><td class="amount">{{ number_format($totals['asset_current'], 2) }}</td></tr>
                    @forelse ($sections['asset']['current'] as $row)
                        <tr><td class="code">{{ $row['account']->code }}</td><td>{{ $row['account']->name }}<span class="amount" style="float:right">{{ number_format($row['balance'], 2) }}</span></td></tr>
                    @empty
                        <tr><td colspan="2" style="color:#999">No accounts.</td></tr>
                    @endforelse

                    <tr class="grand-total-row"><td>Total</td><td class="amount">{{ number_format($totals['asset'], 2) }}</td></tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
