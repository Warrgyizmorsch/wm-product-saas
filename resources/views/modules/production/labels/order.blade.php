<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Label — Production Order {{ $order->order_number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Arial', sans-serif; background: #fff; }
        .label-wrapper {
            width: 100mm;
            min-height: 60mm;
            border: 2px solid #1a1a2e;
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
            color: #666;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .label-title {
            font-size: 15px;
            font-weight: bold;
            color: #1a1a2e;
            margin-bottom: 3px;
        }
        .label-row {
            font-size: 10px;
            color: #444;
            margin-bottom: 2px;
        }
        .label-row span { font-weight: bold; color: #111; }
        .barcode-section {
            text-align: center;
            margin-top: 8px;
            padding-top: 6px;
            border-top: 1px dashed #ccc;
        }
        .barcode-text {
            font-family: 'Courier New', monospace;
            font-size: 11px;
            font-weight: bold;
            letter-spacing: 2px;
            color: #1a1a2e;
            display: block;
            margin-bottom: 3px;
        }
        .barcode-bars {
            display: inline-block;
            height: 14mm;
            width: 70mm;
            background: repeating-linear-gradient(
                to right,
                #000 0px, #000 2px,
                #fff 2px, #fff 5px,
                #000 5px, #000 7px,
                #fff 7px, #fff 10px,
                #000 10px, #000 11px,
                #fff 11px, #fff 14px
            );
        }
        .status-badge {
            display: inline-block;
            padding: 2px 7px;
            border-radius: 10px;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            background: #e0f2fe;
            color: #0369a1;
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
        <button onclick="window.print()" style="padding:8px 20px;background:#1a1a2e;color:#fff;border:none;border-radius:5px;cursor:pointer;font-size:13px;">🖨 Print Label</button>
    </div>

    <div class="label-wrapper">
        <div class="label-header">
            <span class="label-type">Production Order</span>
            <span class="status-badge">{{ strtoupper($order->status) }}</span>
        </div>

        <div class="label-title">{{ $order->order_number }}</div>

        <div class="label-row">Product: <span>{{ $order->product?->name ?? '—' }}</span></div>
        <div class="label-row">SKU: <span>{{ $order->product?->sku ?? '—' }}</span></div>
        <div class="label-row">Qty Ordered: <span>{{ number_format($order->quantity_ordered, 2) }}</span></div>
        <div class="label-row">Start: <span>{{ $order->start_date?->format('d/m/Y') ?? '—' }}</span></div>
        <div class="label-row">End: <span>{{ $order->end_date?->format('d/m/Y') ?? '—' }}</span></div>

        <div class="barcode-section">
            <span class="barcode-text">{{ $barcode }}</span>
            <div class="barcode-bars"></div>
        </div>
    </div>
</body>
</html>
