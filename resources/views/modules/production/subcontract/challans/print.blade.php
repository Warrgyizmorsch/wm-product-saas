<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Subcontract Delivery Challan {{ $challan->challan_number }}</title>
    <style>
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 12px; color: #333; margin: 0; padding: 20px; }
        .challan-header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #222; padding-bottom: 15px; margin-bottom: 20px; }
        .company-title { font-size: 20px; font-weight: bold; text-transform: uppercase; color: #1a365d; }
        .challan-title { font-size: 16px; font-weight: bold; text-align: right; text-transform: uppercase; letter-spacing: 1px; }
        .meta-grid { display: flex; justify-content: space-between; margin-bottom: 20px; }
        .meta-box { width: 48%; border: 1px solid #ddd; padding: 12px; border-radius: 4px; box-sizing: border-box; }
        .meta-box h4 { margin: 0 0 8px 0; font-size: 11px; text-transform: uppercase; color: #666; border-bottom: 1px solid #eee; padding-bottom: 4px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 30px; }
        table th { background-color: #f1f5f9; border: 1px solid #cbd5e1; padding: 8px; font-size: 11px; text-transform: uppercase; text-align: left; }
        table td { border: 1px solid #cbd5e1; padding: 8px; font-size: 12px; }
        .signatures { display: flex; justify-content: space-between; margin-top: 50px; padding-top: 20px; }
        .sig-box { text-align: center; width: 30%; border-top: 1px dashed #666; padding-top: 8px; font-weight: bold; font-size: 11px; text-transform: uppercase; }
        @media print {
            body { padding: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 20px; text-align: right;">
        <button onclick="window.print()" style="padding: 8px 16px; background: #2563eb; color: #fff; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">Print Gate Pass (PDF)</button>
    </div>

    <div class="challan-header">
        <div>
            <div class="company-title">{{ tenant()?->name ?? 'SaaS ERP Manufacturing' }}</div>
            <div style="font-size: 11px; color: #555; margin-top: 4px;">Main Works & Warehouse Division</div>
        </div>
        <div>
            <div class="challan-title">Subcontract Delivery Challan</div>
            <div style="font-size: 14px; font-weight: bold; font-family: monospace; text-align: right; color: #2563eb; margin-top: 4px;">#{{ $challan->challan_number }}</div>
            <div style="font-size: 11px; text-align: right; color: #666;">Date: {{ $challan->challan_date->format('d/m/Y') }}</div>
        </div>
    </div>

    <div class="meta-grid">
        <div class="meta-box">
            <h4>Consignee / Subcontractor Details</h4>
            <div style="font-weight: bold; font-size: 13px;">{{ $challan->vendor?->name }}</div>
            <div>{{ $challan->vendor?->address ?: 'Vendor Address N/A' }}</div>
            <div style="margin-top: 4px; font-size: 11px; font-family: monospace;">GSTIN: {{ $challan->vendor?->gst_number ?: 'N/A' }}</div>
        </div>
        <div class="meta-box">
            <h4>Order & Logistics Details</h4>
            @if($challan->productionOrder)
                <div>MO #: <strong>{{ $challan->productionOrder->order_number }}</strong></div>
                <div>Product: {{ $challan->productionOrder->product?->name }}</div>
            @endif
            @if($challan->operation)
                <div>Op #: <strong>Op {{ $challan->operation->operation_number }} — {{ $challan->operation->name }}</strong></div>
            @endif
            <div style="margin-top: 4px;">Vehicle #: <strong>{{ $challan->vehicle_number ?: 'N/A' }}</strong></div>
            <div>LR / E-Way Bill #: {{ $challan->lr_number ?: 'N/A' }}</div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 55%;">Material Description / Item Specification</th>
                <th style="width: 20%; text-align: right;">Quantity Dispatched</th>
                <th style="width: 20%;">UOM</th>
            </tr>
        </thead>
        <tbody>
            @foreach($challan->items as $idx => $item)
                <tr>
                    <td>{{ $idx + 1 }}</td>
                    <td>
                        <strong>{{ $item->product?->name }}</strong>
                        <div style="font-size: 10px; color: #666; font-family: monospace;">SKU: {{ $item->product?->sku }}</div>
                    </td>
                    <td style="text-align: right; font-weight: bold; font-family: monospace;">{{ number_format($item->quantity, 2) }}</td>
                    <td style="text-transform: uppercase;">{{ $item->unit_of_measure }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if($challan->notes)
        <div style="border: 1px solid #ddd; padding: 8px; margin-bottom: 20px; font-size: 11px; background-color: #fafafa;">
            <strong>Remarks / Special Instructions:</strong> {{ $challan->notes }}
        </div>
    @endif

    <div class="signatures">
        <div class="sig-box">Prepared By</div>
        <div class="sig-box">Security / Gate Officer</div>
        <div class="sig-box">Subcontractor Receipt Stamp</div>
    </div>
</body>
</html>
