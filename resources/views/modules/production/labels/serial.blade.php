<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Label — Serial {{ $serial->serial_number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Arial', sans-serif; background: #fff; }
        .label-wrapper {
            width: 100mm;
            min-height: 55mm;
            border: 2px solid #7c3aed;
            border-radius: 6px;
            padding: 8px 10px;
            margin: 20px auto;
            page-break-inside: avoid;
        }
        .label-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
            margin-bottom: 6px;
        }
        .label-type {
            font-size: 9px;
            font-weight: bold;
            color: #7c3aed;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .label-title { font-size: 13px; font-weight: bold; color: #7c3aed; margin-bottom: 3px; }
        .label-row { font-size: 10px; color: #444; margin-bottom: 2px; }
        .label-row span { font-weight: bold; color: #111; }
        .barcode-section {
            text-align: center;
            margin-top: 8px;
            padding-top: 6px;
            border-top: 1px dashed #ccc;
        }
        .barcode-text {
            font-family: 'Courier New', monospace;
            font-size: 10px;
            font-weight: bold;
            letter-spacing: 2px;
            color: #7c3aed;
            display: block;
            margin-bottom: 3px;
        }
        .barcode-bars {
            display: inline-block;
            height: 12mm;
            width: 70mm;
            background: repeating-linear-gradient(
                to right,
                #000 0px, #000 1px,
                #fff 1px, #fff 3px,
                #000 3px, #000 5px,
                #fff 5px, #fff 9px,
                #000 9px, #000 10px,
                #fff 10px, #fff 12px,
                #000 12px, #000 14px,
                #fff 14px, #fff 16px
            );
        }
        .status-badge {
            display: inline-block;
            padding: 2px 7px;
            border-radius: 10px;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            background: #ede9fe;
            color: #7c3aed;
        }
        @media print {
            body { margin: 0; }
            .label-wrapper { margin: 0; border-radius: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="text-align:center;padding:12px;font-family:Arial;font-size:13px;">
        <button onclick="window.print()" style="padding:8px 20px;background:#7c3aed;color:#fff;border:none;border-radius:5px;cursor:pointer;font-size:13px;">🖨 Print Serial Label</button>
    </div>

    <div class="label-wrapper">
        <div class="label-header">
            <span class="label-type">Serial Number</span>
            <span class="status-badge">{{ strtoupper($serial->status) }}</span>
        </div>

        <div class="label-title">{{ $serial->serial_number }}</div>

        <div class="label-row">Product: <span>{{ $serial->product?->name ?? '—' }}</span></div>
        <div class="label-row">SKU: <span>{{ $serial->product?->sku ?? '—' }}</span></div>
        @if($serial->productionOrder)
        <div class="label-row">Order: <span>{{ $serial->productionOrder->order_number }}</span></div>
        @endif
        <div class="label-row">Issued: <span>{{ $serial->created_at->format('d/m/Y') }}</span></div>

        <div class="barcode-section">
            <span class="barcode-text">{{ $barcode }}</span>
            <div class="barcode-bars"></div>
        </div>
    </div>
</body>
</html>
