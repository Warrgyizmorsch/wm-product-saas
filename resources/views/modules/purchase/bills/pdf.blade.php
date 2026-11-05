<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Vendor Bill - {{ $bill->bill_number }}</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 11px;
            color: #1e293b;
            margin: 0;
            padding: 20px;
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
            color: #1e40af;
            text-transform: uppercase;
        }
        .doc-title {
            font-size: 18px;
            font-weight: bold;
            text-align: right;
            color: #714B67;
            text-transform: uppercase;
        }
        .badge {
            display: inline-block;
            padding: 3px 8px;
            font-size: 9.5px;
            font-weight: bold;
            border-radius: 3px;
            text-transform: uppercase;
            background-color: #f1f5f9;
            color: #334155;
        }
        .badge-paid { background-color: #dcfce7; color: #166534; }
        .badge-unpaid { background-color: #fee2e2; color: #991b1b; }
        .badge-partially { background-color: #e0f2fe; color: #075985; }
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .info-card {
            width: 48%;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 10px 12px;
            box-sizing: border-box;
            background-color: #f8fafc;
        }
        .info-title {
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            color: #64748b;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 4px;
            margin-bottom: 6px;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            margin-bottom: 20px;
        }
        .items-table th {
            background-color: #1e40af;
            color: #ffffff;
            padding: 7px 8px;
            font-size: 10px;
            text-transform: uppercase;
            border: 1px solid #1e40af;
        }
        .items-table td {
            padding: 6px 8px;
            border: 1px solid #cbd5e1;
            font-size: 10px;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .font-mono { font-family: 'Courier New', Courier, monospace; }
        .totals-table {
            width: 40%;
            margin-left: auto;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .totals-table td {
            padding: 4px 8px;
            font-size: 10.5px;
        }
        .totals-table .grand-total {
            font-size: 13px;
            font-weight: bold;
            color: #1e40af;
            border-top: 2px solid #1e40af;
            border-bottom: 2px solid #1e40af;
        }
        .signature-table {
            width: 100%;
            margin-top: 40px;
            border-collapse: collapse;
        }
        .signature-table td {
            width: 50%;
            text-align: center;
            vertical-align: bottom;
            height: 60px;
        }
        .sign-line {
            border-top: 1px solid #94a3b8;
            margin: 0 30px;
            padding-top: 5px;
            font-weight: bold;
            font-size: 10px;
        }
    </style>
</head>
<body>
    @php
        $org = billing_organization_details($bill);
        $badgeClass = match(strtolower($bill->status)) {
            'paid' => 'badge-paid',
            'unpaid' => 'badge-unpaid',
            'partially paid' => 'badge-partially',
            default => 'badge',
        };
    @endphp

    {{-- 1. Document Header --}}
    <table class="header-table">
        <tr>
            <td style="width: 58%;">
                <div class="company-title">{{ $org['name'] }}</div>
                @if(!empty($org['address']))
                    <div style="color: #64748b; font-size: 10px; margin-top: 3px; line-height: 1.4;">{{ $org['address'] }}</div>
                @endif
                <div style="color: #64748b; font-size: 9.5px; margin-top: 2px;">
                    @if(!empty($org['gstin'])) <strong>GSTIN:</strong> {{ $org['gstin'] }} @endif
                    @if(!empty($org['state_code'])) &nbsp;|&nbsp; <strong>State Code:</strong> {{ $org['state_code'] }} ({{ $org['state_name'] ?? '' }}) @endif
                    @if(!empty($org['phone'])) &nbsp;|&nbsp; <strong>Phone:</strong> {{ $org['phone'] }} @endif
                    @if(!empty($org['email'])) &nbsp;|&nbsp; <strong>Email:</strong> {{ $org['email'] }} @endif
                </div>
            </td>
            <td style="width: 42%; text-align: right;">
                <div class="doc-title">VENDOR BILL</div>
                <div class="font-mono" style="font-size: 14px; font-weight: bold; color: #1e293b; margin-top: 2px;"># {{ $bill->bill_number }}</div>
                <div style="margin-top: 4px;"><span class="badge {{ $badgeClass }}">{{ strtoupper($bill->status) }}</span></div>
                <div style="color: #64748b; font-size: 10px; margin-top: 4px;">
                    Date: <strong>{{ $bill->bill_date ? $bill->bill_date->format('d-M-Y') : '—' }}</strong>
                    @if($bill->due_date)
                        &nbsp;|&nbsp; Due: <strong>{{ $bill->due_date->format('d-M-Y') }}</strong>
                    @endif
                </div>
            </td>
        </tr>
    </table>

    {{-- 2. Vendor / Supplier Info & References --}}
    <table class="info-table">
        <tr>
            <td class="info-card" style="vertical-align: top;">
                <div class="info-title">Vendor / Supplier Details</div>
                <div style="font-size: 12px; font-weight: bold; color: #1e293b;">{{ $bill->vendor?->name ?? 'Supplier' }}</div>
                @if($bill->vendor?->address)
                    <div style="color: #475569; font-size: 10px; margin-top: 2px;">{{ $bill->vendor->address }}</div>
                @endif
                <div style="color: #475569; font-size: 9.5px; margin-top: 3px;">
                    @if($bill->vendor?->gst_number)
                        <strong>GSTIN:</strong> {{ $bill->vendor->gst_number }}
                    @endif
                    @if($bill->vendor?->phone)
                        &nbsp;|&nbsp; <strong>Phone:</strong> {{ $bill->vendor->phone }}
                    @endif
                </div>
            </td>
            <td style="width: 4%;"></td>
            <td class="info-card" style="vertical-align: top;">
                <div class="info-title">Bill & Order Reference</div>
                @if($bill->vendor_invoice_number)
                    <div><strong>Vendor Inv #:</strong> <span class="font-mono">{{ $bill->vendor_invoice_number }}</span></div>
                @endif
                @if($bill->purchaseOrder)
                    <div><strong>PO #:</strong> <span class="font-mono">{{ $bill->purchaseOrder->purchase_order_number }}</span></div>
                @endif
                @if($bill->goodsReceiptNote)
                    <div><strong>GRN #:</strong> <span class="font-mono">{{ $bill->goodsReceiptNote->grn_number }}</span></div>
                @endif
                @if($bill->freight_terms)
                    <div><strong>Freight Terms:</strong> {{ ucwords(str_replace('_', ' ', $bill->freight_terms)) }}</div>
                @endif
            </td>
        </tr>
    </table>

    {{-- 3. Items Table --}}
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 45%;">Item / Description</th>
                <th class="text-center" style="width: 10%;">Qty</th>
                <th class="text-right" style="width: 12%;">Rate</th>
                <th class="text-right" style="width: 13%;">Tax</th>
                <th class="text-right" style="width: 15%;">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($bill->items as $idx => $item)
                <tr>
                    <td class="text-center">{{ $idx + 1 }}</td>
                    <td>
                        <strong>{{ $item->product?->name ?: ($item->description ?: 'Billed Item') }}</strong>
                        @if($item->product?->sku)
                            <div style="font-size: 9px; color: #64748b;" class="font-mono">SKU: {{ $item->product->sku }}</div>
                        @endif
                    </td>
                    <td class="text-center font-mono">{{ number_format($item->quantity, 2) }}</td>
                    <td class="text-right font-mono">{{ number_format($item->unit_rate, 2) }}</td>
                    <td class="text-right font-mono">{{ number_format($item->tax_amount, 2) }}</td>
                    <td class="text-right font-mono" style="font-weight: bold;">{{ number_format($item->total_amount, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- 4. Totals --}}
    <table class="totals-table">
        <tr>
            <td class="text-right" style="color: #64748b;">Subtotal:</td>
            <td class="text-right font-mono">{{ number_format($bill->subtotal, 2) }}</td>
        </tr>
        @if($bill->discount_amount > 0)
        <tr>
            <td class="text-right" style="color: #64748b;">Discount:</td>
            <td class="text-right font-mono">-{{ number_format($bill->discount_amount, 2) }}</td>
        </tr>
        @endif
        @if($bill->tax_amount > 0)
        <tr>
            <td class="text-right" style="color: #64748b;">Tax Amount:</td>
            <td class="text-right font-mono">{{ number_format($bill->tax_amount, 2) }}</td>
        </tr>
        @endif
        @if($bill->freight_amount > 0)
        <tr>
            <td class="text-right" style="color: #64748b;">Freight Charges:</td>
            <td class="text-right font-mono">{{ number_format($bill->freight_amount, 2) }}</td>
        </tr>
        @endif
        <tr>
            <td class="text-right grand-total">Grand Total:</td>
            <td class="text-right font-mono grand-total">{{ number_format($bill->grand_total, 2) }}</td>
        </tr>
        @if($bill->paid_amount > 0)
        <tr>
            <td class="text-right" style="color: #166534; font-weight: bold;">Paid Amount:</td>
            <td class="text-right font-mono" style="color: #166534; font-weight: bold;">{{ number_format($bill->paid_amount, 2) }}</td>
        </tr>
        <tr>
            <td class="text-right" style="color: #991b1b; font-weight: bold;">Due Amount:</td>
            <td class="text-right font-mono" style="color: #991b1b; font-weight: bold;">{{ number_format($bill->due_amount, 2) }}</td>
        </tr>
        @endif
    </table>

    {{-- 5. Signatures --}}
    <table class="signature-table">
        <tr>
            <td>
                <div class="sign-line">Prepared / Verified By</div>
            </td>
            <td>
                <div class="sign-line">Authorized Signatory</div>
            </td>
        </tr>
    </table>
</body>
</html>
