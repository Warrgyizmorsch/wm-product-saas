<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Label — Production Lot {{ $batch->batch_number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Arial', sans-serif; background: #fff; }
        .label-wrapper {
            width: 100mm;
            min-height: 60mm;
            border: 2px solid #166534;
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
            color: #166534;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .label-title {
            font-size: 15px;
            font-weight: bold;
            color: #166534;
            margin-bottom: 3px;
        }
        .label-row { font-size: 10px; color: #444; margin-bottom: 2px; }
        .label-row span { font-weight: bold; color: #111; }
        .expiry-warning { color: #dc2626 !important; font-weight: bold; }
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
            color: #166534;
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
                #fff 2px, #fff 4px,
                #000 4px, #000 8px,
                #fff 8px, #fff 11px,
                #000 11px, #000 13px,
                #fff 13px, #fff 16px
            );
        }
        .status-badge {
            display: inline-block;
            padding: 2px 7px;
            border-radius: 10px;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            background: #dcfce7;
            color: #166534;
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
        <button onclick="window.print()" style="padding:8px 20px;background:#166534;color:#fff;border:none;border-radius:5px;cursor:pointer;font-size:13px;">🖨 Print Lot Label</button>
    </div>

    <div class="label-wrapper">
        <div class="label-header">
            <span class="label-type">Production Lot / Batch</span>
            <span class="status-badge">{{ strtoupper($batch->status) }}</span>
        </div>

        <div class="label-title">{{ $batch->batch_number }}</div>

        <div class="label-row">Product: <span>{{ $batch->product?->name ?? '—' }}</span></div>
        <div class="label-row">SKU: <span>{{ $batch->product?->sku ?? '—' }}</span></div>
        <div class="label-row">Planned Qty: <span>{{ number_format($batch->planned_quantity, 2) }}</span></div>
        <div class="label-row">Actual Qty: <span>{{ number_format($batch->actual_quantity, 2) }}</span></div>
        @if($batch->manufactured_at)
        <div class="label-row">Manufactured: <span>{{ $batch->manufactured_at->format('d/m/Y') }}</span></div>
        @endif
        @if($batch->expiry_date)
        <div class="label-row">Expiry: <span class="{{ $batch->expiry_date->isPast() ? 'expiry-warning' : '' }}">{{ $batch->expiry_date->format('d/m/Y') }}</span></div>
        @endif

        <div class="barcode-section">
            <span class="barcode-text">{{ $barcode }}</span>
            <div class="barcode-bars"></div>
        </div>
    </div>
</body>
</html>
