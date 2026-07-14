<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Sales Order {{ $order->sales_order_number }}</title>
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
        .status-confirmed { background-color: #e0f2fe; color: #0369a1; }
        .status-shipped { background-color: #dcfce7; color: #15803d; }
        .status-cancelled { background-color: #fee2e2; color: #b91c1c; }
        .status-draft { background-color: #f1f5f9; color: #475569; }

        .details-section {
            margin-bottom: 15px;
        }
        .bill-to {
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
            width: 50%;
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

        .client-notes-container {
            font-size: 9.5px !important;
            line-height: 1.3 !important;
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
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="logo-section">
                <div class="logo-avatar">
                    {{ strtoupper(substr(tenant() ? tenant()->name : 'E', 0, 1)) }}
                </div>
                <div class="tenant-name">{{ tenant() ? tenant()->name : 'SaaS ERP Workspace' }}</div>
            </div>
            <div class="title-section">
                <h1 class="order-title">SALES ORDER</h1>
                <div class="order-number">No: {{ $order->sales_order_number }}</div>
                @php
                    $badgeClass = 'status-draft';
                    if ($order->status === 'Confirmed') $badgeClass = 'status-confirmed';
                    elseif ($order->status === 'Shipped' || $order->status === 'Partially Shipped') $badgeClass = 'status-shipped';
                    elseif ($order->status === 'Cancelled') $badgeClass = 'status-cancelled';
                @endphp
                <span class="status-badge {{ $badgeClass }}">{{ $order->status }}</span>
            </div>
            <div class="clear"></div>
        </div>

        <hr style="border: 0; border-top: 1px solid #e2e8f0; margin-bottom: 15px;">

        <!-- Details -->
        <div class="details-section">
            <div class="bill-to">
                <span class="section-label">Customer Info</span>
                <h3 class="client-name" style="margin: 0 0 3px 0;">{{ $order->customer?->name ?? '—' }}</h3>
                <div class="client-info"><strong style="color: #64748b;">Email:</strong> {{ $order->customer?->email ?: '—' }}</div>
                <div class="client-info"><strong style="color: #64748b;">Phone:</strong> {{ $order->customer?->phone ?: '—' }}</div>
            </div>
            <div class="schedule">
                <span class="section-label" style="text-align: center;">Order Schedule</span>
                <div class="client-info" style="text-align: center;"><strong>Order Date:</strong> {{ $order->order_date ? $order->order_date->format('d/m/Y') : '—' }}</div>
                <div class="client-info" style="text-align: center;"><strong>Est. Shipment:</strong> {{ $order->shipment_date ? $order->shipment_date->format('d/m/Y') : 'Not Scheduled' }}</div>
                <div class="client-info" style="text-align: center;"><strong>Payment Terms:</strong> {{ $order->payment_terms ?: 'Due on Receipt' }}</div>
            </div>
            <div class="reference">
                <span class="section-label">Reference Details</span>
                @if($order->quotation)
                    <div class="client-info"><strong>Quotation Ref:</strong> {{ $order->quotation->quotation_number }}</div>
                @endif
                @if($order->salesPerson)
                    <div class="client-info"><strong>Sales Rep:</strong> {{ $order->salesPerson->name }}</div>
                @endif
            </div>
            <div class="clear"></div>
        </div>

        <!-- Addresses Box with 6px Border Radius Curve -->
        <div class="addresses-section">
            <div class="address-box" style="border-right: 1px solid #e2e8f0; width: 48%; padding-right: 2%;">
                <div class="address-label">Billing Address</div>
                <p class="address-text">{{ $order->billing_address ?: 'No billing address provided.' }}</p>
            </div>
            <div class="address-box" style="width: 48%; padding-left: 2%;">
                <div class="address-label">Shipping Address</div>
                <p class="address-text">{{ $order->shipping_address ?: 'No shipping address provided.' }}</p>
            </div>
            <div class="clear"></div>
        </div>

        <!-- Items Table -->
        <table class="table-items">
            <thead>
                <tr>
                    <th class="text-center" style="width: 5%;">#</th>
                    <th style="width: 40%;">Description of Product</th>
                    <th style="width: 15%;">Warehouse</th>
                    <th class="text-center" style="width: 8%;">Qty</th>
                    <th class="text-right" style="width: 12%;">Unit Price</th>
                    <th class="text-right" style="width: 10%;">Tax Rate</th>
                    <th class="text-right" style="width: 10%;">Discount</th>
                    <th class="text-right" style="width: 15%;">Amount (₹)</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($order->items as $index => $item)
                    <tr>
                        <td class="text-center" style="color: #64748b;">{{ $index + 1 }}</td>
                        <td>
                            <strong>{{ $item->item_name }}</strong>
                            @if($item->product?->sku)
                                <div style="font-size: 9px; color: #64748b; margin-top: 1px;">SKU: {{ $item->product->sku }}</div>
                            @endif
                            @if($item->description)
                                <div style="font-size: 9px; color: #64748b; margin-top: 1px;">{{ $item->description }}</div>
                            @endif
                        </td>
                        <td>
                            {{ $item->warehouse?->name ?: '—' }}
                        </td>
                        <td class="text-center">{{ $item->quantity }}</td>
                        <td class="text-right">₹{{ number_format($item->unit_price, 2) }}</td>
                        <td class="text-right">{{ number_format($item->tax_rate, 2) }}%</td>
                        <td class="text-right">
                            @if($item->discount > 0)
                                ₹{{ number_format($item->discount, 2) }}
                            @else
                                —
                            @endif
                        </td>
                        <td class="text-right" style="font-weight: bold;">₹{{ number_format($item->amount, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <!-- Bottom section -->
        <div style="margin-top: 10px; width: 100%;">
            <!-- Calculations Box (floated right) -->
            <div style="float: right; width: 42%;">
                <div class="calc-box">
                    <table style="width: 100%; border-collapse: collapse; font-size: 11px;">
                        <tr>
                            <td style="color: #64748b; padding: 4px 0; text-align: left; border: 0;">Subtotal:</td>
                            <td style="text-align: right; font-weight: bold; color: #1e293b; padding: 4px 0; border: 0;">₹{{ number_format($order->subtotal, 2) }}</td>
                        </tr>
                        <tr>
                            <td style="color: #64748b; padding: 4px 0; text-align: left; border: 0;">Tax total (GST):</td>
                            <td style="text-align: right; font-weight: bold; color: #1e293b; padding: 4px 0; border: 0;">₹{{ number_format($order->tax, 2) }}</td>
                        </tr>
                        @if($order->discount > 0)
                            <tr style="color: #b91c1c;">
                                <td style="padding: 4px 0; text-align: left; border: 0;">Discount:</td>
                                <td style="text-align: right; font-weight: bold; padding: 4px 0; border: 0;">-₹{{ number_format($order->discount, 2) }}</td>
                            </tr>
                        @endif
                        @if($order->shipping_charges > 0)
                            <tr>
                                <td style="color: #64748b; padding: 4px 0; text-align: left; border: 0;">Shipping Charges:</td>
                                <td style="text-align: right; font-weight: bold; color: #1e293b; padding: 4px 0; border: 0;">₹{{ number_format($order->shipping_charges, 2) }}</td>
                            </tr>
                        @endif
                        @if($order->adjustment != 0)
                            <tr>
                                <td style="color: #64748b; padding: 4px 0; text-align: left; border: 0;">Adjustment:</td>
                                <td style="text-align: right; font-weight: bold; color: #1e293b; padding: 4px 0; border: 0;">₹{{ number_format($order->adjustment, 2) }}</td>
                            </tr>
                        @endif
                        <tr style="border-top: 1px solid #cbd5e1;">
                            <td style="font-weight: bold; color: #0f172a; padding: 6px 0 0 0; font-size: 11px; text-align: left; border: 0;">Total Payable:</td>
                            <td style="text-align: right; font-weight: bold; color: #1e40af; padding: 6px 0 0 0; font-size: 11px; border: 0;">₹{{ number_format($order->total_amount, 2) }}</td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Terms & Conditions / Remarks (floated left) -->
            <div style="float: left; width: 54%;">
                @if($order->terms_conditions)
                    <div style="margin-bottom: 12px;">
                        <span class="section-label">Terms & Conditions</span>
                        <div class="terms-conditions-container">{!! $order->terms_conditions !!}</div>
                    </div>
                @endif

                @if($order->notes)
                    <div>
                        <span class="section-label">Remarks / Internal Notes</span>
                        <div class="client-notes-container" style="white-space: pre-line;">{{ $order->notes }}</div>
                    </div>
                @endif
            </div>
            <div class="clear"></div>
        </div>

        <!-- Signature section -->
        <div class="signature-section">
            <div class="footer-note">
                For queries regarding order fulfillment or billing details, please contact us.
            </div>
            <div class="signature-box">
                <div class="signature-line">Authorized Signatory</div>
            </div>
            <div class="clear"></div>
        </div>
    </div>
</body>
</html>
