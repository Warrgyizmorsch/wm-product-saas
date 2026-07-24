<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Purchase Order {{ $order->purchase_order_number }}</title>
    <style>
        @page {
            margin: 10mm 15mm 10mm 15mm;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            color: #333;
            font-size: 11px;
            line-height: 1.4;
            margin: 0;
            padding: 0;
        }
        .container {
            width: 100%;
            margin: 0 auto;
        }
        .header {
            margin-bottom: 15px;
        }
        .logo-section {
            float: left;
            width: 50%;
        }
        .title-section {
            float: right;
            width: 50%;
            text-align: right;
        }
        .clear {
            clear: both;
        }
        .logo-avatar {
            background-color: #1e40af;
            color: #ffffff;
            font-size: 20px;
            font-weight: bold;
            width: 38px;
            height: 38px;
            line-height: 38px;
            text-align: center;
            border-radius: 4px;
            display: inline-block;
            margin-right: 8px;
            vertical-align: middle;
        }
        .tenant-name {
            font-size: 16px;
            font-weight: bold;
            color: #1e293b;
            display: inline-block;
            vertical-align: middle;
        }
        .order-title {
            font-size: 20px;
            font-weight: bold;
            color: #1e40af;
            margin: 0 0 3px 0;
        }
        .order-number {
            font-size: 12px;
            font-weight: bold;
            color: #334155;
        }
        .status-badge {
            font-size: 10px;
            padding: 1px 6px;
            border-radius: 10px;
            font-weight: bold;
            display: inline-block;
            margin-top: 3px;
            background-color: #f1f5f9;
            color: #475569;
        }
        .status-approved { background-color: #dcfce7; color: #15803d; }
        .status-cancelled { background-color: #fee2e2; color: #b91c1c; }
        .status-draft { background-color: #f1f5f9; color: #475569; }

        .details-section {
            margin-bottom: 15px;
        }
        .supplier-info {
            float: left;
            width: 33%;
        }
        .schedule {
            float: left;
            width: 34%;
            text-align: center;
        }
        .reference {
            float: right;
            width: 33%;
            text-align: right;
        }
        .section-label {
            font-size: 9px;
            text-transform: uppercase;
            font-weight: bold;
            color: #64748b;
            margin-bottom: 3px;
            display: block;
        }
        .client-name {
            font-size: 13px;
            font-weight: bold;
            color: #0f172a;
            margin: 0 0 3px 0;
        }
        .client-info {
            color: #475569;
            margin: 1px 0;
            font-size: 10.5px;
        }
        
        .addresses-section {
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 10px 12px;
            background-color: #ffffff;
            margin-bottom: 15px;
        }
        .address-box {
            float: left;
            width: 100%;
        }
        .address-label {
            font-size: 9px;
            font-weight: bold;
            color: #475569;
            text-transform: uppercase;
            margin-bottom: 4px;
        }
        .address-text {
            color: #475569;
            font-size: 11px;
            line-height: 1.4;
            margin: 0;
        }

        .table-items {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        .table-items th {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 6px 8px;
            font-size: 10px;
            text-transform: uppercase;
            color: #64748b;
            font-weight: bold;
            text-align: left;
        }
        .table-items td {
            border: 1px solid #e2e8f0;
            padding: 6px 8px;
            vertical-align: middle;
            font-size: 11px;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        
        .calc-box {
            border: 1px solid #e2e8f0;
            background-color: #ffffff;
            border-radius: 4px;
            padding: 10px;
        }
        
        .terms-conditions-container, .terms-conditions-container p, .terms-conditions-container li {
            font-size: 9.5px !important;
            line-height: 1.3 !important;
            margin: 0 0 3px 0 !important;
            color: #475569;
        }

        .signature-section {
            margin-top: 30px;
        }
        .footer-note {
            float: left;
            width: 60%;
            font-size: 10px;
            color: #64748b;
            margin-top: 25px;
        }
        .signature-box {
            float: right;
            width: 30%;
            text-align: center;
        }
        .signature-line {
            border-top: 1px solid #94a3b8;
            margin-top: 25px;
            padding-top: 4px;
            font-size: 10px;
            color: #64748b;
            text-transform: uppercase;
        }
    </style>
</head>
<body>
    @php
        $currency = tenant()?->settings['currency'] ?? 'INR';
        $currencySymbol = ($currency === 'INR') ? '₹' : $currency . ' ';
    @endphp
    
    <div class="container">
        <!-- Header Table -->
        <table style="width: 100%; border-collapse: collapse; margin-bottom: 15px; font-family: 'DejaVu Sans', sans-serif;">
            <tr>
                <!-- Column 1: Logo -->
                <td style="vertical-align: top; border: 0; padding: 0; width: 55px;">
                    <table style="width: 55px; height: 55px; border-collapse: collapse; border: 0; background-color: #1e40af; border-radius: 6px;">
                        <tr>
                            <td style="vertical-align: middle; text-align: center; color: #ffffff; font-weight: bold; font-size: 26px; border: 0; padding: 0; height: 55px; width: 55px;">
                                {{ strtoupper(substr(tenant() ? tenant()->name : 'E', 0, 1)) }}
                            </td>
                        </tr>
                    </table>
                </td>
                
                <!-- Column 2: Company Details -->
                <td style="vertical-align: top; border: 0; padding: 0 0 0 12px;">
                    <div style="font-size: 15px; font-weight: bold; color: #1e293b; margin: 0 0 2px 0; line-height: 1.2;">
                        {{ tenant() ? tenant()->name : 'SaaS ERP Workspace' }}
                    </div>
                    <div style="font-size: 9px; color: #64748b; margin-bottom: 1px; line-height: 1.3;">
                        H-1, Industrial Area, Sukher, Udaipur 313001, Rajasthan, India
                    </div>
                    <div style="font-size: 8.5px; color: #64748b; margin-bottom: 1px; line-height: 1.3;">
                        Tel: +91 294 2440230 | GSTIN: 08AAFCS1234E1Z0
                    </div>
                    <div style="font-size: 8.5px; color: #64748b; line-height: 1.3;">
                        Email: {{ (tenant() && tenant()->billing_email) ? tenant()->billing_email : 'info@sasserp.com' }} | Web: www.sasserp.com
                    </div>
                </td>
                
                <!-- Column 3: PO details -->
                <td style="width: 35%; vertical-align: top; text-align: right; border: 0; padding: 0;">
                    <h1 style="font-size: 18px; font-weight: bold; color: #1e40af; margin: 0 0 3px 0; text-transform: uppercase; line-height: 1.1;">PURCHASE ORDER</h1>
                    <div style="font-size: 11px; font-weight: bold; color: #334155; margin-bottom: 2px;">No: {{ $order->purchase_order_number }}</div>
                    <div style="font-size: 9.5px; color: #64748b; font-weight: bold;">Date: {{ $order->date ? $order->date->format('d F Y') : '—' }}</div>
                </td>
            </tr>
        </table>

        <hr style="border: 0; border-top: 1px solid #e2e8f0; margin-bottom: 15px; margin-top: 5px;">

        <!-- Supplier & Delivery Details section (Table-based layout) -->
        <table style="width: 100%; border-collapse: separate; border-spacing: 0; margin-bottom: 15px; border: 0;">
            <tr>
                <!-- Left: Supplier Info Card -->
                <td style="width: 48%; vertical-align: top; border: 1px solid #cbd5e1; border-radius: 6px; background-color: #f8fafc; padding: 10px;">
                    <span class="section-label" style="border-bottom: 1px solid #cbd5e1; padding-bottom: 3px; margin-bottom: 6px; display: block; color: #1e40af; font-weight: bold; font-size: 8.5px; text-transform: uppercase;">Supplier Details</span>
                    <h3 class="client-name" style="margin: 0 0 4px 0; font-size: 11px; font-weight: bold; color: #0f172a;">{{ $order->vendor->name ?? '—' }}</h3>
                    @if($order->vendor?->code)
                        <div class="client-info" style="margin-bottom: 2px; font-size: 9.5px; color: #475569;"><strong style="color: #64748b;">Code:</strong> {{ $order->vendor->code }}</div>
                    @endif
                    @if($order->supplier_quotation_number)
                        <div class="client-info" style="margin-bottom: 2px; font-size: 9.5px; color: #475569;"><strong style="color: #64748b;">Quote No:</strong> <span style="color: #1e40af; font-weight: bold;">{{ $order->supplier_quotation_number }}</span></div>
                    @endif
                    @if($order->reference)
                        <div class="client-info" style="margin-bottom: 2px; font-size: 9.5px; color: #475569;"><strong style="color: #64748b;">Ref:</strong> {{ $order->reference }}</div>
                    @endif
                    <div class="client-info" style="margin-bottom: 2px; font-size: 9.5px; color: #475569;"><strong style="color: #64748b;">Email:</strong> {{ $order->vendor->email ?: '—' }}</div>
                    <div class="client-info" style="margin-bottom: 2px; font-size: 9.5px; color: #475569;"><strong style="color: #64748b;">Phone:</strong> {{ $order->vendor->phone ?: '—' }}</div>
                    <div class="client-info" style="margin-top: 4px; font-size: 9.5px; color: #475569; line-height: 1.3;"><strong style="color: #64748b; display: block; margin-bottom: 1px;">Address:</strong> {{ $order->vendor->address ?: '—' }}</div>
                </td>
                
                <!-- Spacer Column -->
                <td style="width: 4%; border: 0; padding: 0;"></td>
                
                <!-- Right: Delivery Details Card -->
                <td style="width: 48%; vertical-align: top; border: 1px solid #cbd5e1; border-radius: 6px; background-color: #f8fafc; padding: 10px;">
                    <span class="section-label" style="border-bottom: 1px solid #cbd5e1; padding-bottom: 3px; margin-bottom: 6px; display: block; color: #1e40af; font-weight: bold; font-size: 8.5px; text-transform: uppercase;">Delivery Details</span>
                    <div class="client-info" style="margin-bottom: 4px; font-size: 9.5px; color: #475569;"><strong style="color: #64748b;">Delivery Location:</strong> {{ $order->location ?: '—' }}</div>
                    @if($order->warehouse?->address)
                        <div class="client-info" style="margin-bottom: 4px; font-size: 9.5px; color: #475569; line-height: 1.3;"><strong style="color: #64748b; display: block; margin-bottom: 1px;">Delivery Address:</strong> {{ $order->warehouse->address }}</div>
                    @endif
                    <div class="client-info" style="font-size: 9.5px; color: #475569;"><strong style="color: #64748b;">Delivery Date:</strong> <span style="color: #b91c1c; font-weight: bold;">{{ $order->delivery_date ? $order->delivery_date->format('d M Y') : '—' }}</span></div>
                </td>
            </tr>
        </table>

        <!-- Items Table -->
        <table class="table-items">
            <thead>
                <tr>
                    <th class="text-center" style="width: 5%;">#</th>
                    <th style="width: 45%;">Product Description</th>
                    <th class="text-center" style="width: 10%;">Qty</th>
                    <th class="text-right" style="width: 13%;">Rate</th>
                    <th class="text-right" style="width: 13%;">Amount</th>
                    
                    @if($order->discount_type === 'item_wise')
                        <th class="text-right" style="width: 10%;">Disc Amt</th>
                    @endif

                    @if($order->tax_type === 'item_wise_tax')
                        <th class="text-right" style="width: 10%;">Tax Amt</th>
                    @endif

                    <th class="text-right" style="width: 15%;">Total ({{ $currencySymbol }})</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $groupedItems = $order->items->groupBy('product_id')->map(function($items) {
                        $first = $items->first();
                        return (object) [
                            'id' => $first->id,
                            'product' => $first->product,
                            'product_id' => $first->product_id,
                            'quantity' => $items->sum('quantity'),
                            'rate' => $first->rate,
                            'amount' => $items->sum('amount'),
                            'discount_percent' => $first->discount_percent,
                            'discount_amount' => $items->sum('discount_amount'),
                            'tax_percent' => $first->tax_percent,
                            'cgst_percent' => $first->cgst_percent,
                            'sgst_percent' => $first->sgst_percent,
                            'igst_percent' => $first->igst_percent,
                            'tax_amount' => $items->sum('tax_amount'),
                            'total_amount' => $items->sum('total_amount'),
                        ];
                    })->values();
                @endphp
                @foreach ($groupedItems as $index => $item)
                    <tr>
                        <td class="text-center" style="color: #64748b;">{{ $index + 1 }}</td>
                        <td>
                            <strong>{{ $item->product->name ?? '—' }}</strong>
                            @if($item->product?->sku)
                                <div style="font-size: 9px; color: #64748b; margin-top: 1px;">SKU: {{ $item->product->sku }}</div>
                            @endif
                        </td>
                        <td class="text-center">{{ (float)$item->quantity }}</td>
                        <td class="text-right">{{ $currencySymbol }}{{ number_format($item->rate, 2) }}</td>
                        <td class="text-right">{{ $currencySymbol }}{{ number_format($item->amount, 2) }}</td>
                        
                        @if($order->discount_type === 'item_wise')
                            <td class="text-right" style="color: #b91c1c;">
                                -{{ $currencySymbol }}{{ number_format($item->discount_amount, 2) }}
                            </td>
                        @endif

                        @if($order->tax_type === 'item_wise_tax')
                            <td class="text-right" style="color: #64748b;">
                                +{{ $currencySymbol }}{{ number_format($item->tax_amount, 2) }}
                            </td>
                        @endif

                        <td class="text-right" style="font-weight: bold;">{{ $currencySymbol }}{{ number_format($item->total_amount, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <!-- Bottom section -->
        <div style="margin-top: 10px; width: 100%;">
            <!-- Calculations Box (floated right) -->
            <div style="float: right; width: 45%;">
                <div class="calc-box">
                    <table style="width: 100%; border-collapse: collapse; font-size: 11px;">
                        <tr>
                            <td style="color: #64748b; padding: 4px 0; text-align: left; border: 0;">Subtotal:</td>
                            <td style="text-align: right; font-weight: bold; color: #1e293b; padding: 4px 0; border: 0;">{{ $currencySymbol }}{{ number_format($order->subtotal, 2) }}</td>
                        </tr>
                        @if($order->discount_type !== 'without_discount' && $order->discount_amount > 0)
                            <tr style="color: #b91c1c;">
                                <td style="padding: 4px 0; text-align: left; border: 0;">Discount:</td>
                                <td style="text-align: right; font-weight: bold; padding: 4px 0; border: 0;">-{{ $currencySymbol }}{{ number_format($order->discount_amount, 2) }}</td>
                            </tr>
                            <tr>
                                <td style="color: #64748b; padding: 4px 0; text-align: left; border: 0;">Gross Total (Before Tax):</td>
                                <td style="text-align: right; font-weight: bold; color: #1e293b; padding: 4px 0; border: 0;">{{ $currencySymbol }}{{ number_format($order->subtotal - $order->discount_amount, 2) }}</td>
                            </tr>
                        @endif
                        @if($order->tax_type !== 'without_tax' && $order->tax_amount > 0)
                            @php
                                $grossTotal = $order->subtotal - $order->discount_amount;
                                $taxPercent = $grossTotal > 0 ? ($order->tax_amount / $grossTotal) * 100 : 0;
                                $taxPercentRounded = round($taxPercent, 2);
                            @endphp
                            @if($order->gst_type === 'igst')
                                <tr>
                                    <td style="color: #64748b; padding: 4px 0; text-align: left; border: 0;">IGST ({{ $taxPercentRounded }}%):</td>
                                    <td style="text-align: right; font-weight: bold; color: #1e293b; padding: 4px 0; border: 0;">+{{ $currencySymbol }}{{ number_format($order->tax_amount, 2) }}</td>
                                </tr>
                            @else
                                @php
                                    $halfTaxPercent = round($taxPercent / 2, 2);
                                    $halfTaxAmount = $order->tax_amount / 2;
                                @endphp
                                <tr>
                                    <td style="color: #64748b; padding: 4px 0; text-align: left; border: 0;">CGST ({{ $halfTaxPercent }}%):</td>
                                    <td style="text-align: right; font-weight: bold; color: #1e293b; padding: 4px 0; border: 0;">+{{ $currencySymbol }}{{ number_format($halfTaxAmount, 2) }}</td>
                                </tr>
                                <tr>
                                    <td style="color: #64748b; padding: 4px 0; text-align: left; border: 0;">SGST ({{ $halfTaxPercent }}%):</td>
                                    <td style="text-align: right; font-weight: bold; color: #1e293b; padding: 4px 0; border: 0;">+{{ $currencySymbol }}{{ number_format($halfTaxAmount, 2) }}</td>
                                </tr>
                            @endif
                        @endif
                        <tr style="border-top: 1px solid #cbd5e1;">
                            <td style="font-weight: bold; color: #0f172a; padding: 6px 0 0 0; font-size: 11px; text-align: left; border: 0;">Total Amount:</td>
                            <td style="text-align: right; font-weight: bold; color: #1e40af; padding: 6px 0 0 0; font-size: 11px; border: 0;">{{ $currencySymbol }}{{ number_format($order->grand_total, 2) }}</td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Terms & Conditions / Notes (floated left) -->
            <div style="float: left; width: 50%;">
                @if($order->notes)
                    <div style="margin-bottom: 12px;">
                        <span class="section-label">Terms & Conditions</span>
                        <div class="terms-conditions-container" style="white-space: pre-line;">{!! $order->notes !!}</div>
                    </div>
                @endif
            </div>
            <div class="clear"></div>
        </div>

        <!-- Signature section -->
        <div class="signature-section">
            <div class="footer-note">
                For queries regarding order fulfillment or billing details, please contact the purchase department.
            </div>
            <div class="signature-box">
                <div class="signature-line">Authorized Signatory</div>
            </div>
            <div class="clear"></div>
        </div>
    </div>
</body>
</html>
