<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>Tax Invoice - {{ $invoice->invoice_number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #1e293b;
            background: #ffffff;
            padding: 32px 36px;
        }

        /* ── HEADER ─────────────────────────────────── */
        .header-wrap {
            width: 100%;
            padding-bottom: 16px;
            border-bottom: 1px solid #e2e8f0;
            margin-bottom: 18px;
        }
        .company-avatar {
            display: inline-block;
            width: 44px;
            height: 44px;
            background-color: #1e40af;
            color: #ffffff;
            font-size: 22px;
            font-weight: bold;
            text-align: center;
            line-height: 44px;
            border-radius: 6px;
            vertical-align: middle;
        }
        .company-info {
            display: inline-block;
            vertical-align: middle;
            padding-left: 10px;
        }
        .company-name {
            font-size: 16px;
            font-weight: bold;
            color: #0f172a;
        }
        .company-sub {
            font-size: 10px;
            color: #64748b;
        }
        .company-address {
            font-size: 10.5px;
            color: #475569;
            margin-top: 8px;
            line-height: 1.7;
        }
        .invoice-title {
            font-size: 20px;
            font-weight: bold;
            color: #1e40af;
            text-align: right;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .invoice-number {
            font-size: 13px;
            font-weight: bold;
            color: #1e293b;
            text-align: right;
            margin-top: 3px;
        }
        .invoice-meta {
            font-size: 12px;
            color: #64748b;
            text-align: right;
            margin-top: 10px;
        }
        .invoice-meta table {
            margin-left: auto;
        }
        .invoice-meta td {
            padding: 1.5px 0;
            font-size: 12px;
        }
        .meta-label {
            color: #94a3b8;
            text-align: right;
            padding-right: 6px;
            white-space: nowrap;
        }
        .meta-value {
            color: #1e293b;
            font-weight: bold;
            text-align: right;
            white-space: nowrap;
        }

        /* ── ADDRESS BOXES ───────────────────────────── */
        .addr-box {
            border: 1px solid #e2e8f0;
            padding: 10px 12px;
            background-color: #f8fafc;
            vertical-align: top;
            width: 48%;
        }
        .addr-label {
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 4px;
            margin-bottom: 7px;
        }
        .addr-name {
            font-size: 13px;
            font-weight: bold;
            color: #0f172a;
            margin-bottom: 3px;
        }
        .addr-line {
            font-size: 10.5px;
            color: #475569;
            margin-bottom: 2px;
        }
        .ref-row {
            font-size: 10.5px;
            color: #475569;
            padding: 4px 0;
            border-bottom: 1px solid #f1f5f9;
        }
        .ref-row:last-child {
            border-bottom: none;
        }

        /* ── ITEMS TABLE ─────────────────────────────── */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }
        .items-table thead th {
            background-color: #f8fafc;
            color: #475569;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            padding: 9px 10px;
            border-top: 1px solid #e2e8f0;
            border-bottom: 1.5px solid #cbd5e1;
        }
        .items-table tbody td {
            padding: 10px;
            font-size: 11px;
            color: #1e293b;
            border-bottom: 1px solid #f1f5f9;
        }
        .item-name  { font-weight: bold; display: block; color: #0f172a; }
        .item-sku   { font-size: 9px; color: #94a3b8; display: block; margin-top: 2px; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }

        /* ── BANK BOX ────────────────────────────────── */
        .bank-box {
            border: 1px solid #e2e8f0;
            padding: 10px 12px;
            background-color: #f8fafc;
            vertical-align: top;
            width: 55%;
        }
        .bank-label {
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #1e293b;
            margin-bottom: 6px;
        }
        .bank-grid {
            width: 100%;
        }
        .bank-grid td {
            font-size: 10.5px;
            color: #475569;
            padding: 2px 0;
            width: 50%;
        }
        .notes-box {
            border: 1px solid #e2e8f0;
            padding: 8px 10px;
            background-color: #f8fafc;
            margin-top: 8px;
            font-size: 10px;
            color: #475569;
        }

        /* ── SUMMARY TABLE ───────────────────────────── */
        .summary-table {
            width: 100%;
            border-collapse: collapse;
        }
        .summary-table td {
            padding: 6px 8px;
            font-size: 11.5px;
            color: #64748b;
            border-bottom: 1px solid #f1f5f9;
        }
        .summary-table td.amount {
            text-align: right;
            font-weight: bold;
            color: #1e293b;
        }
        .summary-table .grand-total td {
            background-color: #f8fafc;
            font-size: 13px;
            font-weight: bold;
            color: #1e40af;
            border-top: 1px solid #e2e8f0;
            border-bottom: 1px solid #e2e8f0;
            padding: 8px;
        }
        .summary-table .bal-row td {
            font-size: 12px;
            font-weight: bold;
            color: #1e293b;
        }

        /* ── FOOTER ──────────────────────────────────── */
        .footer-wrap {
            width: 100%;
            border-top: 1px solid #e2e8f0;
            margin-top: 24px;
            padding-top: 14px;
        }
        .footer-thankyou {
            font-size: 10px;
            color: #64748b;
            line-height: 1.7;
        }
        .footer-sig-name {
            font-size: 11px;
            font-weight: bold;
            color: #0f172a;
            text-align: right;
            margin-bottom: 36px;
        }
        .footer-sig-line {
            font-size: 10px;
            color: #64748b;
            border-top: 1px solid #cbd5e1;
            padding-top: 4px;
            text-align: right;
            display: inline-block;
            width: 160px;
        }
        .page-footer {
            font-size: 9px;
            color: #94a3b8;
            text-align: center;
            margin-top: 12px;
            border-top: 1px solid #f1f5f9;
            padding-top: 6px;
        }
    </style>
</head>
<body>

    <!-- ═══ 1. HEADER ═══ -->
    <table class="header-wrap" cellpadding="0" cellspacing="0">
        <tr>
            <!-- LEFT: Company -->
            <td style="width: 55%; vertical-align: top;">
                <table cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="vertical-align: middle;">
                            <div class="company-avatar">{{ strtoupper(substr(tenant() ? tenant()->name : 'E', 0, 1)) }}</div>
                        </td>
                        <td style="vertical-align: middle; padding-left: 10px;">
                            <div class="company-name">{{ tenant() ? tenant()->name : 'SaaS ERP Workspace' }}</div>
                            <div class="company-sub">Official Corporate Billing Unit</div>
                        </td>
                    </tr>
                </table>
                <div class="company-address">
                    H-1, Industrial Area, Sukher, Udaipur 313001, Rajasthan, India<br>
                    <strong>GSTIN:</strong> 08AAFCS1234E1Z0 &nbsp;|&nbsp; <strong>State Code:</strong> 08 (Rajasthan)<br>
                    <strong>Email:</strong> {{ tenant() ? tenant()->billing_email : 'billing@sasserp.com' }} &nbsp;|&nbsp; <strong>Phone:</strong> +91 294 2440230
                </div>
            </td>
            <!-- RIGHT: TAX INVOICE -->
            <td style="width: 45%; vertical-align: top;">
                <div class="invoice-title">TAX INVOICE</div>
                <div class="invoice-number"># {{ $invoice->invoice_number }}</div>
                <div class="invoice-meta">
                    <table cellpadding="0" cellspacing="0">
                        <tr>
                            <td class="meta-label">Invoice Date:</td>
                            <td class="meta-value">{{ date('d-M-Y', strtotime($invoice->invoice_date)) }}</td>
                        </tr>
                        <tr>
                            <td class="meta-label">Due Date:</td>
                            <td class="meta-value">{{ $invoice->due_date ? date('d-M-Y', strtotime($invoice->due_date)) : '—' }}</td>
                        </tr>
                        <tr>
                            <td class="meta-label">Terms:</td>
                            <td class="meta-value">{{ $invoice->salesOrder?->payment_terms ?: 'Immediate Payment' }}</td>
                        </tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    <!-- ═══ 2. ADDRESS CARDS ═══ -->
    <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 18px;">
        <tr>
            <!-- Billed To -->
            <td class="addr-box">
                <div class="addr-label">Billed To (Customer):</div>
                <div class="addr-name">{{ $invoice->customer?->name ?: ($invoice->salesOrder?->customer?->name ?: '—') }}</div>
                @if($invoice->customer?->company_name)
                    <div class="addr-line" style="color:#64748b; font-weight: 600;">{{ $invoice->customer->company_name }}</div>
                @endif
                <div class="addr-line">{{ $invoice->customer?->email ?: ($invoice->salesOrder?->customer?->email ?: '') }} &nbsp;|&nbsp; {{ $invoice->customer?->phone ?: ($invoice->salesOrder?->customer?->phone ?: '') }}</div>
                @if($invoice->salesOrder?->billing_address)
                    <div class="addr-line" style="margin-top:5px; border-top:1px solid #e2e8f0; padding-top:5px;">
                        <strong>Billing Address:</strong><br>{{ $invoice->salesOrder->billing_address }}
                    </div>
                @endif
            </td>
            <td style="width:4%;"></td>
            <!-- Order References -->
            <td class="addr-box">
                <div class="addr-label">Order &amp; Dispatch References:</div>
                @if($invoice->salesOrder)
                    <div class="ref-row">
                        <span style="color:#64748b;">Sales Order Ref:</span>
                        <span style="float:right; font-weight:bold; color:#1e40af;">{{ $invoice->salesOrder->sales_order_number }}</span>
                    </div>
                @endif
                @if($invoice->materialRequirement)
                    <div class="ref-row">
                        <span style="color:#64748b;">Dispatch Ref:</span>
                        <span style="float:right; font-weight:bold;">{{ $invoice->materialRequirement->requirement_number }}</span>
                    </div>
                @endif
                <div class="ref-row">
                    <span style="color:#64748b;">Place of Supply:</span>
                    <span style="float:right; font-weight:600;">Rajasthan (08)</span>
                </div>
                <div class="ref-row">
                    <span style="color:#64748b;">GST Option:</span>
                    <span style="float:right; font-weight:bold;">{{ $invoice->gst_type === 'igst' ? 'IGST (Inter-State)' : 'CGST + SGST (Intra-State)' }}</span>
                </div>
                <div class="ref-row">
                    <span style="color:#64748b;">Payment Status:</span>
                    <span style="float:right; font-weight:bold; color:{{ $balanceDue > 0 ? '#dc2626' : '#16a34a' }};">{{ $balanceDue > 0 ? 'Balance Outstanding' : 'Fully Paid' }}</span>
                </div>
            </td>
        </tr>
    </table>

    <!-- ═══ 3. ITEMS TABLE ═══ -->
    <table class="items-table" cellpadding="0" cellspacing="0">
        <thead>
            <tr>
                <th class="text-center" style="width:5%;">#</th>
                <th style="width:44%;">Item &amp; Description</th>
                <th class="text-right" style="width:11%;">Qty</th>
                <th class="text-right" style="width:14%;">Rate (&#8377;)</th>
                <th class="text-right" style="width:10%;">Tax %</th>
                <th class="text-right" style="width:16%;">Amount (&#8377;)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->items as $idx => $item)
                @php
                    $lineTotal = ($item->total_amount > 0)
                        ? $item->total_amount
                        : ($item->subtotal > 0 ? $item->subtotal : ($item->quantity * $item->unit_price));
                @endphp
                <tr>
                    <td class="text-center" style="color:#94a3b8;">{{ $idx + 1 }}</td>
                    <td>
                        <span class="item-name">{{ $item->product?->name ?: $item->item_name }}</span>
                        @if($item->product?->sku)
                            <span class="item-sku">SKU: {{ $item->product->sku }}</span>
                        @endif
                        @if($item->description)
                            <span class="item-sku">{{ $item->description }}</span>
                        @endif
                    </td>
                    <td class="text-right" style="font-weight:600;">{{ (float)$item->quantity }}</td>
                    <td class="text-right">&#8377;{{ number_format($item->unit_price, 2) }}</td>
                    <td class="text-right" style="color:#64748b;">{{ (float)$item->tax_rate }}%</td>
                    <td class="text-right" style="font-weight:bold;">&#8377;{{ number_format($lineTotal, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <!-- ═══ 4. BANK + SUMMARY ═══ -->
    <table width="100%" cellpadding="0" cellspacing="0" style="margin-top:6px;">
        <tr>
            <!-- Left: GST Summary, Bank Details, Notes -->
            <td class="bank-box">
                @php
                    $taxGroups = $invoice->items->groupBy(fn($item) => (string)(float)$item->tax_rate);
                @endphp
                @if ($taxGroups->count() > 0 && $invoice->tax_amount > 0)
                    <div style="margin-bottom: 8px;">
                        <div style="font-size: 8.5px; font-weight: bold; text-transform: uppercase; color: #1e40af; margin-bottom: 3px; letter-spacing: 0.5px;">
                            GST Tax Summary
                        </div>
                        <table class="items-table" cellpadding="0" cellspacing="0" style="margin-bottom: 6px; font-size: 8.5px; width: 100%;">
                            <thead>
                                @if($invoice->gst_type === 'igst')
                                    <tr>
                                        <th style="width: 30%;">Tax Rate</th>
                                        <th class="text-right" style="width: 35%;">Total Tax</th>
                                        <th class="text-right" style="width: 35%;">IGST Amt</th>
                                    </tr>
                                @else
                                    <tr>
                                        <th style="width: 25%;">Tax Rate</th>
                                        <th class="text-right" style="width: 25%;">Total Tax</th>
                                        <th class="text-right" style="width: 25%;">CGST Amt</th>
                                        <th class="text-right" style="width: 25%;">SGST Amt</th>
                                    </tr>
                                @endif
                            </thead>
                            <tbody>
                                @php
                                    $totCgst = 0; $totSgst = 0; $totIgst = 0; $totTax = 0;
                                @endphp
                                @foreach($taxGroups as $rateStr => $gItems)
                                    @php
                                        $rate = floatval($rateStr);
                                        $grpTax = $gItems->sum('tax_amount');
                                        $totTax += $grpTax;
                                    @endphp
                                    @if($invoice->gst_type === 'igst')
                                        @php
                                            $grpIgst = $gItems->sum('igst_amount') > 0 ? $gItems->sum('igst_amount') : $grpTax;
                                            $totIgst += $grpIgst;
                                        @endphp
                                        <tr>
                                            <td style="font-weight: bold;">GST {{ $rate }}%</td>
                                            <td class="text-right" style="font-weight: bold;">&#8377;{{ number_format($grpTax, 2) }}</td>
                                            <td class="text-right">&#8377;{{ number_format($grpIgst, 2) }}</td>
                                        </tr>
                                    @else
                                        @php
                                            $grpCgst = $gItems->sum('cgst_amount') > 0 ? $gItems->sum('cgst_amount') : round($grpTax / 2, 2);
                                            $grpSgst = $gItems->sum('sgst_amount') > 0 ? $gItems->sum('sgst_amount') : round($grpTax - $grpCgst, 2);
                                            $totCgst += $grpCgst; $totSgst += $grpSgst;
                                        @endphp
                                        <tr>
                                            <td style="font-weight: bold;">GST {{ $rate }}%</td>
                                            <td class="text-right" style="font-weight: bold;">&#8377;{{ number_format($grpTax, 2) }}</td>
                                            <td class="text-right">&#8377;{{ number_format($grpCgst, 2) }}</td>
                                            <td class="text-right">&#8377;{{ number_format($grpSgst, 2) }}</td>
                                        </tr>
                                    @endif
                                @endforeach
                                <tr style="background-color: #f8fafc; font-weight: bold;">
                                    @if($invoice->gst_type === 'igst')
                                        <td>Total</td>
                                        <td class="text-right" style="color: #1e40af;">&#8377;{{ number_format($totTax, 2) }}</td>
                                        <td class="text-right">&#8377;{{ number_format($totIgst, 2) }}</td>
                                    @else
                                        <td>Total</td>
                                        <td class="text-right" style="color: #1e40af;">&#8377;{{ number_format($totTax, 2) }}</td>
                                        <td class="text-right">&#8377;{{ number_format($totCgst, 2) }}</td>
                                        <td class="text-right">&#8377;{{ number_format($totSgst, 2) }}</td>
                                    @endif
                                </tr>
                            </tbody>
                        </table>
                    </div>
                @endif

                <div class="bank-label">Bank Payment Details:</div>
                <table class="bank-grid" cellpadding="0" cellspacing="0">
                    <tr>
                        <td><strong>Bank Name:</strong> State Bank of India</td>
                        <td><strong>Account Name:</strong> {{ tenant() ? tenant()->name : 'SaaS ERP' }}</td>
                    </tr>
                    <tr>
                        <td><strong>Account No:</strong> 398402948201</td>
                        <td><strong>IFSC Code:</strong> SBIN0001234</td>
                    </tr>
                </table>
                @if($invoice->notes)
                    <div class="notes-box">
                        <strong style="font-size:9px; text-transform:uppercase; letter-spacing:0.4px;">Terms & Conditions / Customer Notes:</strong><br>
                        {!! $invoice->notes !!}
                    </div>
                @endif
            </td>
            <td style="width:3%; vertical-align:top;"></td>
            <!-- Right: Subtotal Calculations -->
            <td style="width:45%; vertical-align:top;">
                @php
                    $grossSubtotal = 0;
                    $totalItemDiscount = 0;
                    foreach($invoice->items as $it) {
                        $grossSubtotal += ($it->quantity * $it->unit_price);
                        $totalItemDiscount += $it->discount;
                    }
                    $effectiveDiscount = ($invoice->discount_type === 'order_wise') ? (float)$invoice->discount_amount : $totalItemDiscount;
                    $taxableBase = max(0, $grossSubtotal - $effectiveDiscount);
                    $gstTaxAmount = (float)$invoice->tax_amount;
                    $itemsTotalInclGst = $taxableBase + $gstTaxAmount;
                    $freightAmount = ($invoice->freight_terms === 'To Be Billed') ? (float)($invoice->freight_amount ?: 0) : 0;
                    $adjustment = (float)($invoice->adjustment ?? 0);
                    $grandTotal = (float)$invoice->total_amount;
                    $gstType = $invoice->gst_type ?? 'cgst_sgst';
                @endphp
                <table class="summary-table" cellpadding="0" cellspacing="0">
                    <tr>
                        <td>Subtotal (Excl. Tax):</td>
                        <td class="amount">&#8377;{{ number_format($grossSubtotal, 2) }}</td>
                    </tr>
                    @if($invoice->discount_type !== 'without_discount' && $effectiveDiscount > 0)
                        <tr>
                            <td style="color:#dc2626;">Less: Item Discounts:</td>
                            <td class="amount" style="color:#dc2626;">-&#8377;{{ number_format($effectiveDiscount, 2) }}</td>
                        </tr>
                    @endif
                    <tr>
                        <td>Items Taxable Value:</td>
                        <td class="amount">&#8377;{{ number_format($taxableBase, 2) }}</td>
                    </tr>
                    @if($invoice->tax_type !== 'without_tax' && $gstTaxAmount > 0)
                        @if($gstType === 'cgst_sgst')
                            <tr>
                                <td style="color:#64748b; font-size:10px;">Add: CGST (Central Tax):</td>
                                <td class="amount" style="color:#64748b; font-size:10px;">+&#8377;{{ number_format($gstTaxAmount / 2, 2) }}</td>
                            </tr>
                            <tr>
                                <td style="color:#64748b; font-size:10px;">Add: SGST (State Tax):</td>
                                <td class="amount" style="color:#64748b; font-size:10px;">+&#8377;{{ number_format($gstTaxAmount / 2, 2) }}</td>
                            </tr>
                        @else
                            <tr>
                                <td style="color:#64748b; font-size:10px;">Add: IGST (Integrated Tax):</td>
                                <td class="amount" style="color:#64748b; font-size:10px;">+&#8377;{{ number_format($gstTaxAmount, 2) }}</td>
                            </tr>
                        @endif
                    @endif
                    <tr style="background-color:#f1f5f9; font-weight:bold;">
                        <td>Billed Items Total (Incl. GST):</td>
                        <td class="amount">&#8377;{{ number_format($itemsTotalInclGst, 2) }}</td>
                    </tr>
                    @if($freightAmount > 0)
                        <tr>
                            <td>Freight Charges:</td>
                            <td class="amount" style="color:#1e40af;">&#8377;{{ number_format($freightAmount, 2) }}</td>
                        </tr>
                    @endif
                    @if($adjustment != 0)
                        <tr>
                            <td>Adjustment:</td>
                            <td class="amount">&#8377;{{ number_format($adjustment, 2) }}</td>
                        </tr>
                    @endif
                    <tr class="grand-total">
                        <td style="font-weight:bold; color:#1e40af;">Grand Total:</td>
                        <td class="amount" style="color:#1e40af; font-size:13px; font-weight:bold;">&#8377;{{ number_format($grandTotal, 2) }}</td>
                    </tr>
                    @if($adjustedAmount > 0 || $invoice->amount_paid > 0)
                        <tr>
                            <td style="color:#16a34a; font-weight:600; font-size:11px;">Payments Received:</td>
                            <td class="amount" style="color:#16a34a;">-&#8377;{{ number_format(max($invoice->amount_paid, $adjustedAmount), 2) }}</td>
                        </tr>
                    @endif
                    <tr class="bal-row">
                        <td>Balance Due:</td>
                        <td class="amount" style="font-size:13px; color:{{ $balanceDue > 0 ? '#dc2626' : '#16a34a' }};">&#8377;{{ number_format($balanceDue, 2) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- ═══ 5. SIGNATURE ═══ -->
    <table class="footer-wrap" cellpadding="0" cellspacing="0">
        <tr>
            <td style="width:60%; vertical-align:bottom;">
                <div class="footer-thankyou">
                    Thank you for your business!<br>
                    This is a computer generated invoice and does not require physical signature.
                </div>
            </td>
            <td style="width:40%; vertical-align:bottom; text-align:right;">
                <div class="footer-sig-name">For {{ tenant() ? tenant()->name : 'SaaS ERP' }}</div>
                <div class="footer-sig-line">Authorized Signatory</div>
            </td>
        </tr>
    </table>

    <!-- ═══ PAGE FOOTER ═══ -->
    <div class="page-footer">
        {{ $invoice->invoice_number }} &nbsp;|&nbsp; Generated: {{ date('d M Y, h:i A') }} &nbsp;|&nbsp; {{ tenant() ? tenant()->name : 'SaaS ERP' }}
    </div>

</body>
</html>
