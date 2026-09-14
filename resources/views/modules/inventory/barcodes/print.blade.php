<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Print Barcode Labels - {{ $product->name }}</title>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background: #f4f5f7;
        }
        .printable-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            max-width: 900px;
            margin: 0 auto;
        }
        .barcode-sticker {
            background: white;
            border: 1px dashed #ccc;
            padding: 12px;
            text-align: center;
            border-radius: 6px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .product-title {
            font-weight: bold;
            font-size: 13px;
            margin-bottom: 2px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .product-sku {
            font-size: 11px;
            color: #555;
            margin-bottom: 4px;
        }
        .barcode-svg {
            max-width: 100%;
            height: auto;
        }
        .price-tag {
            font-size: 12px;
            font-weight: bold;
            color: #111;
            margin-top: 4px;
        }
        @media print {
            body {
                background: white;
                padding: 0;
            }
            .no-print {
                display: none !important;
            }
            .printable-grid {
                gap: 10px;
            }
            .barcode-sticker {
                box-shadow: none;
                border: 1px solid #000;
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="no-print" style="max-width: 900px; margin: 0 auto 20px auto; text-align: right;">
        <button onclick="window.print()" style="padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px;">
            🖨️ Print Labels Now
        </button>
    </div>

    <div class="printable-grid">
        @foreach($labels as $index => $lbl)
            <div class="barcode-sticker">
                <div class="product-title">{{ $lbl['product_name'] }}</div>
                <div class="product-sku">
                    @if($lbl['is_serial'])
                        <span style="color: #007bff; font-weight: bold;">SN: {{ $lbl['serial_number'] }}</span>
                    @else
                        SKU: {{ $lbl['sku'] }}
                    @endif
                </div>
                <svg class="barcode-svg" id="barcode-{{ $index }}"></svg>
                <div class="price-tag">Price: ₹{{ number_format($lbl['price'], 2) }}</div>
            </div>
        @endforeach
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const labelsData = @json($labels);
            labelsData.forEach((item, idx) => {
                JsBarcode("#barcode-" + idx, item.barcode_value, {
                    format: "CODE128",
                    width: 1.8,
                    height: 45,
                    displayValue: true,
                    fontSize: 12,
                    margin: 2
                });
            });
        });
    </script>
</body>
</html>
