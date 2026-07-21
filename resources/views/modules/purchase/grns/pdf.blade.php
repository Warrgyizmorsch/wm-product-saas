<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Goods Receipt Note - {{ $grn->grn_number }}</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 11px;
            color: #333333;
            margin: 0;
            padding: 15px;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .header-table td {
            vertical-align: top;
        }
        .company-title {
            font-size: 18px;
            font-weight: bold;
            color: #3454d1;
            text-transform: uppercase;
        }
        .doc-title {
            font-size: 16px;
            font-weight: bold;
            text-align: right;
            color: #1e293b;
        }
        .badge-approved {
            background-color: #dcfce7;
            color: #166534;
            padding: 3px 8px;
            font-size: 10px;
            font-weight: bold;
            border-radius: 3px;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
            border: 1px solid #cbd5e1;
        }
        .info-table td {
            padding: 6px 10px;
            border: 1px solid #cbd5e1;
            font-size: 10.5px;
        }
        .info-header {
            background-color: #f8fafc;
            font-weight: bold;
            color: #475569;
            width: 20%;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            margin-bottom: 20px;
        }
        .items-table th {
            background-color: #3454d1;
            color: #ffffff;
            padding: 7px 6px;
            font-size: 10px;
            text-transform: uppercase;
            border: 1px solid #3454d1;
        }
        .items-table td {
            padding: 6px 6px;
            border: 1px solid #cbd5e1;
            font-size: 10px;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .font-mono { font-family: 'Courier New', Courier, monospace; }
        .signature-table {
            width: 100%;
            margin-top: 40px;
            border-collapse: collapse;
        }
        .signature-table td {
            width: 33.33%;
            text-align: center;
            vertical-align: bottom;
            height: 60px;
        }
        .sign-line {
            border-top: 1px solid #94a3b8;
            margin: 0 15px;
            padding-top: 5px;
            font-weight: bold;
            font-size: 10px;
        }
    </style>
</head>
<body>
    @php
        $tenant = tenant();
        $currency = $tenant?->settings['currency'] ?? 'INR';
    @endphp

    <table class="header-table">
        <tr>
            <td>
                <div class="company-title">{{ $tenant?->name ?? 'SaaS ERP Workspace' }}</div>
                <div style="color: #64748b; margin-top: 4px;">{{ $tenant?->settings['address'] ?? 'Main Store & Receiving Yard' }}</div>
            </td>
            <td class="text-right">
                <div class="doc-title">GOODS RECEIPT NOTE</div>
                <div class="font-mono" style="font-size: 14px; font-weight: bold; color: #3454d1; margin-top: 3px;">{{ $grn->grn_number }}</div>
                <div style="margin-top: 4px;"><span class="badge-approved">{{ strtoupper($grn->status) }}</span></div>
            </td>
        </tr>
    </table>

    <table class="info-table">
        <tr>
            <td class="info-header">Purchase Order #:</td>
            <td class="font-mono" style="font-weight: bold;">{{ $grn->purchaseOrder ? $grn->purchaseOrder->purchase_order_number : 'Direct Receipt' }}</td>
            <td class="info-header">Receipt Date:</td>
            <td>{{ $grn->received_date ? $grn->received_date->format('d-M-Y') : '—' }}</td>
        </tr>
        <tr>
            <td class="info-header">Vendor Name:</td>
            <td style="font-weight: bold; color: #1e293b;">{{ $grn->vendor?->name ?? 'N/A' }}</td>
            <td class="info-header">Warehouse:</td>
            <td>{{ $grn->warehouse?->name ?? 'Main Warehouse' }}</td>
        </tr>
        <tr>
            <td class="info-header">Challan / Invoice #:</td>
            <td>{{ $grn->challan_number ?: '—' }}</td>
            <td class="info-header">Challan Date:</td>
            <td>{{ $grn->challan_date ? $grn->challan_date->format('d-M-Y') : '—' }}</td>
        </tr>
        <tr>
            <td class="info-header">Transporter Name:</td>
            <td>{{ $grn->transporter_name ?: '—' }}</td>
            <td class="info-header">Vehicle Number:</td>
            <td>{{ $grn->vehicle_number ?: '—' }}</td>
        </tr>
        <tr>
            <td class="info-header">L.R. Number:</td>
            <td colspan="3">{{ $grn->lr_number ?: '—' }}</td>
        </tr>
    </table>

    @if($grn->notes)
        <div style="margin-bottom: 12px; padding: 6px 10px; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 3px;">
            <strong>Store Remarks:</strong> {{ $grn->notes }}
        </div>
    @endif

    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 4%;">#</th>
                <th style="width: 30%;">Product Description</th>
                <th class="text-center" style="width: 10%;">Ordered</th>
                <th class="text-center" style="width: 10%;">Prev Rec</th>
                <th class="text-center" style="width: 10%;">Received</th>
                <th class="text-center" style="width: 9%;">Rejected</th>
                <th class="text-center" style="width: 10%;">Accepted</th>
                <th class="text-right" style="width: 17%;">Total ({{ $currency }})</th>
            </tr>
        </thead>
        <tbody>
            @php
                $totRec = 0; $totRej = 0; $totAcc = 0; $totAmt = 0;
            @endphp
            @foreach($grn->items as $idx => $item)
                @php
                    $totRec += (float)$item->received_qty;
                    $totRej += (float)$item->rejected_qty;
                    $totAcc += (float)$item->accepted_qty;
                    $totAmt += (float)$item->total_amount;
                @endphp
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td>
                        <strong>{{ $item->product?->name }}</strong>
                        <div style="font-size: 8.5px; color: #64748b;">SKU: {{ $item->product?->sku ?? 'N/A' }} | UOM: {{ $item->product?->uom?->name ?? 'Pcs' }}</div>
                        @if($item->remarks)
                            <div style="font-size: 8.5px; color: #dc2626;">Remarks: {{ $item->remarks }}</div>
                        @endif
                    </td>
                    <td class="text-center font-mono">{{ number_format($item->ordered_qty, 2) }}</td>
                    <td class="text-center font-mono">{{ number_format($item->previous_received_qty, 2) }}</td>
                    <td class="text-center font-mono" style="font-weight: bold; color: #2563eb;">{{ number_format($item->received_qty, 2) }}</td>
                    <td class="text-center font-mono" style="color: #dc2626;">{{ number_format($item->rejected_qty, 2) }}</td>
                    <td class="text-center font-mono" style="font-weight: bold; color: #166534;">{{ number_format($item->accepted_qty, 2) }}</td>
                    <td class="text-right font-mono" style="font-weight: bold;">{{ number_format($item->total_amount, 2) }}</td>
                </tr>
            @endforeach
            <tr style="background-color: #f8fafc; font-weight: bold;">
                <td colspan="4" class="text-right">Total Summary:</td>
                <td class="text-center font-mono" style="color: #2563eb;">{{ number_format($totRec, 2) }}</td>
                <td class="text-center font-mono" style="color: #dc2626;">{{ number_format($totRej, 2) }}</td>
                <td class="text-center font-mono" style="color: #166534;">{{ number_format($totAcc, 2) }}</td>
                <td class="text-right font-mono" style="color: #1e293b;">{{ $currency }} {{ number_format($totAmt, 2) }}</td>
            </tr>
        </tbody>
    </table>

    <table class="signature-table">
        <tr>
            <td>
                <div class="sign-line">Received By (Store Keeper)</div>
            </td>
            <td>
                <div class="sign-line">Quality Inspected By</div>
            </td>
            <td>
                <div class="sign-line">Authorized Signatory</div>
            </td>
        </tr>
    </table>
</body>
</html>
