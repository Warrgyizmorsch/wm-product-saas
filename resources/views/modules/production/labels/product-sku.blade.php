<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Product Label — {{ $product->sku }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Arial', sans-serif; background: #fff; }
        .label-wrapper {
            width: 80mm;
            min-height: 50mm;
            border: 2px solid #0369a1;
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
            color: #0369a1;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .label-title { font-size: 14px; font-weight: bold; color: #0369a1; margin-bottom: 3px; }
        .label-sku {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            font-weight: bold;
            color: #111;
            margin-bottom: 4px;
        }
        .label-row { font-size: 10px; color: #444; margin-bottom: 2px; }
        .label-row span { font-weight: bold; color: #111; }
        .note {
            font-size: 8px;
            color: #999;
            font-style: italic;
            margin-top: 4px;
        }
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
            color: #0369a1;
            display: block;
            margin-bottom: 3px;
        }
        .barcode-bars {
            display: inline-block;
            height: 12mm;
            width: 60mm;
            background: repeating-linear-gradient(
                to right,
                #000 0px, #000 2px,
                #fff 2px, #fff 6px,
                #000 6px, #000 8px,
                #fff 8px, #fff 11px,
                #000 11px, #000 12px,
                #fff 12px, #fff 15px
            );
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
        <button onclick="window.print()" style="padding:8px 20px;background:#0369a1;color:#fff;border:none;border-radius:5px;cursor:pointer;font-size:13px;">🖨 Print Product Label</button>
    </div>

    <div class="label-wrapper">
        <div class="label-header">
            <span class="label-type">Product / SKU</span>
        </div>

        <div class="label-title">{{ $product->name }}</div>
        <div class="label-sku">{{ $product->sku }}</div>

        @if($product->description)
        <div class="label-row">{{ Str::limit($product->description, 60) }}</div>
        @endif

        <div class="note">⚠ This label identifies the product/SKU, not a specific lot or batch.</div>

        <div class="barcode-section">
            <span class="barcode-text">{{ $barcode }}</span>
            <div class="barcode-bars"></div>
        </div>
    </div>
</body>
</html>
