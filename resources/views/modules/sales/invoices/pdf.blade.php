<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>Tax Invoice - {{ $invoice->invoice_number }}</title>
    <style>
        @page {
            margin-top: 18mm;
            margin-bottom: 22mm;
            margin-left: 15mm;
            margin-right: 15mm;
            size: A4 portrait;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 10px;
            color: #1e293b;
            background: #ffffff;
            margin: 0;
            padding: 0;
            line-height: 1.4;
        }

        /* ── UTILITY ─────────────────────────────────── */
        .text-left   { text-align: left; }
        .text-center { text-align: center; }
        .text-right  { text-align: right; }
        .fw-bold     { font-weight: bold; }
        .text-muted  { color: #64748b; }
        .text-dark   { color: #0f172a; }
        .text-primary{ color: #1e40af; }
        .text-success{ color: #16a34a; }
        .text-danger { color: #dc2626; }
        .font-mono   { font-family: monospace; }

        /* ── PERSISTENT FOOTER ON EVERY PAGE (REFRENS STYLE) ── */
        footer.doc-page-footer {
            position: fixed;
            bottom: -16mm;
            left: 0;
            right: 0;
            height: 14mm;
        }
        .footer-dashed-line {
            border-top: 1.2px dashed #94a3b8;
            margin-bottom: 5px;
            width: 100%;
        }
        .footer-meta-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8.5px;
            color: #64748b;
        }

        /* ── 1. E-INVOICE / E-WAY BILL STRIP ─────────── */
        .einvoice-banner {
            width: 100%;
            border: 1px solid #cbd5e1;
            border-left: 4px solid #16a34a;
            background-color: #f8fafc;
            border-radius: 6px;
            padding: 6px 8px;
            margin-bottom: 10px;
            page-break-inside: avoid;
        }
        .badge-einvoice {
            background-color: #16a34a;
            color: #ffffff;
            font-size: 8px;
            font-weight: bold;
            padding: 2px 5px;
            border-radius: 3px;
            display: inline-block;
            text-transform: uppercase;
        }
        .badge-ewaybill {
            background-color: #0284c7;
            color: #ffffff;
            font-size: 8px;
            font-weight: bold;
            padding: 2px 5px;
            border-radius: 3px;
            display: inline-block;
            text-transform: uppercase;
        }
        .qr-box {
            background-color: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            padding: 2px;
            display: inline-block;
        }

        /* ── 2. HEADER SECTION ──────────────────────── */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            padding-bottom: 10px;
            border-bottom: 1px solid #cbd5e1;
            margin-bottom: 12px;
            page-break-inside: avoid;
        }
        .company-avatar {
            display: inline-block;
            width: 40px;
            height: 40px;
            background-color: #1e40af;
            color: #ffffff;
            font-size: 18px;
            font-weight: bold;
            text-align: center;
            line-height: 40px;
            border-radius: 6px;
            vertical-align: middle;
        }
        .company-info {
            display: inline-block;
            vertical-align: middle;
            padding-left: 8px;
        }
        .company-name {
            font-size: 15px;
            font-weight: bold;
            color: #0f172a;
        }
        .company-sub {
            font-size: 9.5px;
            color: #64748b;
        }
        .company-address {
            font-size: 9.5px;
            color: #475569;
            margin-top: 5px;
            line-height: 1.35;
        }

        .doc-title {
            font-size: 18px;
            font-weight: 900;
            color: #1e40af;
            text-align: right;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .doc-number {
            font-size: 12px;
            font-weight: bold;
            color: #1e293b;
            text-align: right;
            margin-top: 2px;
        }
        .meta-table {
            margin-left: auto;
            margin-top: 5px;
        }
        .meta-table td {
            padding: 1.5px 0;
            font-size: 9.5px;
        }
        .meta-label {
            color: #64748b;
            text-align: right;
            padding-right: 6px;
        }
        .meta-value {
            color: #1e293b;
            font-weight: bold;
            text-align: right;
        }

        /* ── 3. ADDRESS & REFERENCES CARDS ───────────── */
        .info-boxes-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 12px 0;
            margin-left: -12px;
            margin-right: -12px;
            margin-bottom: 12px;
            page-break-inside: avoid;
        }
        .info-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 8px 10px;
            vertical-align: top;
            width: 50%;
        }
        .box-title {
            font-size: 8.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            margin-bottom: 4px;
        }
        .client-name {
            font-size: 12px;
            font-weight: bold;
            color: #0f172a;
            margin-bottom: 2px;
        }
        .client-contact {
            font-size: 9.5px;
            color: #475569;
            margin-bottom: 4px;
        }
        .address-block {
            font-size: 9px;
            color: #475569;
            border-top: 1px solid #e2e8f0;
            padding-top: 4px;
            margin-top: 3px;
            line-height: 1.35;
        }

        .ref-row {
            width: 100%;
            padding: 2px 0;
            border-bottom: 1px solid #e2e8f0;
            font-size: 9.5px;
        }
        .ref-row:last-child {
            border-bottom: none;
        }
        .ref-label {
            color: #64748b;
            display: inline-block;
            width: 44%;
        }
        .ref-val {
            color: #0f172a;
            font-weight: bold;
            display: inline-block;
            width: 52%;
            text-align: right;
        }

        /* ── 4. ITEMS TABLE ──────────────────────────── */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
            page-break-inside: auto;
            border: 1px solid #cbd5e1;
        }
        .items-table thead {
            display: table-header-group;
        }
        .items-table tr {
            page-break-inside: avoid;
            page-break-after: auto;
        }
        .items-table th {
            background-color: #f8fafc;
            color: #475569;
            font-size: 8.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            border: 1px solid #cbd5e1;
            padding: 6px 7px;
        }
        .items-table td {
            padding: 6px 7px;
            border: 1px solid #e2e8f0;
            font-size: 9.5px;
            color: #1e293b;
            vertical-align: top;
        }
        .item-title {
            font-weight: bold;
            color: #0f172a;
            font-size: 10px;
        }
        .item-desc {
            font-size: 8px;
            color: #64748b;
            margin-top: 2px;
        }

        /* ── 5. SUMMARY / CALCULATIONS SECTION ──────── */
        .calc-wrapper {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
            margin-bottom: 12px;
            page-break-inside: avoid;
        }
        .calc-notes-col {
            width: 50%;
            vertical-align: top;
            padding-right: 14px;
        }
        .calc-summary-col {
            width: 50%;
            vertical-align: top;
        }

        .summary-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 8px 10px;
        }
        .summary-line {
            width: 100%;
            padding: 2.5px 0;
            font-size: 9.5px;
            border-bottom: 1px solid #f1f5f9;
        }
        .summary-lbl {
            color: #64748b;
            display: inline-block;
            width: 50%;
        }
        .summary-num {
            color: #0f172a;
            font-weight: bold;
            display: inline-block;
            width: 48%;
            text-align: right;
        }
        .total-payable-box {
            background-color: #f1f5f9;
            border-radius: 4px;
            padding: 5px 8px;
            margin-top: 5px;
        }
        .total-payable-lbl {
            font-weight: bold;
            color: #0f172a;
            font-size: 10.5px;
            display: inline-block;
            width: 48%;
        }
        .total-payable-num {
            font-weight: bold;
            color: #1e40af;
            font-size: 12px;
            display: inline-block;
            width: 50%;
            text-align: right;
        }

        /* ── 6. FULL-WIDTH TERMS & CONDITIONS SECTION ── */
        .terms-section {
            margin-top: 10px;
            padding-top: 8px;
            border-top: 1px solid #e2e8f0;
            page-break-inside: auto;
        }
        .section-label {
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            margin-bottom: 5px;
        }
        .terms-content {
            font-size: 9px;
            color: #475569;
            line-height: 1.45;
        }
        .terms-content p {
            margin: 0 0 3px 0;
            padding-top: 3px;
            padding-bottom: 1px;
            page-break-inside: avoid;
        }

        /* ── 7. GST TAX SUMMARY TABLE ────────────────── */
        .tax-summary-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
            margin-bottom: 10px;
            page-break-inside: avoid;
            border: 1px solid #cbd5e1;
        }
        .tax-summary-table th {
            background-color: #f8fafc;
            color: #475569;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
            padding: 5px 6px;
            border: 1px solid #cbd5e1;
        }
        .tax-summary-table td {
            padding: 4px 6px;
            font-size: 8.5px;
            color: #1e293b;
            border: 1px solid #e2e8f0;
        }
        .tax-summary-table tfoot td {
            background-color: #f1f5f9;
            font-weight: bold;
            border-top: 1.5px solid #cbd5e1;
        }

        /* ── 8. SIGNATURE SECTION ────────────────────── */
        .signature-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            padding-top: 8px;
            border-top: 1px solid #e2e8f0;
            page-break-inside: avoid;
        }
        .sig-note {
            font-size: 9px;
            color: #64748b;
            vertical-align: bottom;
            width: 60%;
            line-height: 1.4;
        }
        .sig-space {
            text-align: right;
            vertical-align: bottom;
            width: 40%;
        }
        .sig-line {
            display: inline-block;
            border-top: 1px solid #94a3b8;
            width: 160px;
            text-align: center;
            padding-top: 4px;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
        }
    </style>
</head>
<body>

    @php
        $rawSymbol = active_currency_symbol();
        $currencySymbol = ($rawSymbol === '₹' || active_currency() === 'INR') ? '&#8377;' : $rawSymbol;

        $hasEinvoice = !empty($invoice->irn) || $invoice->einvoice_status === 'Generated' || $invoice->einvoice_status === 'generated';
        $hasEwayBill = !empty($invoice->eway_bill_no) || $invoice->eway_bill_status === 'Generated' || $invoice->eway_bill_status === 'generated';
        
        $qrBase64 = null;
        if ($hasEinvoice) {
            $qrPayload = $invoice->signed_qrcode ?: $invoice->irn;
            $cacheKey = 'einvoice_qr_b64_' . $invoice->id . '_' . md5($qrPayload);
            $qrBase64 = \Illuminate\Support\Facades\Cache::remember($cacheKey, 86400, function() use ($qrPayload) {
                try {
                    $url = 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=' . urlencode($qrPayload);
                    $ctx = stream_context_create(['http' => ['timeout' => 3]]);
                    $content = @file_get_contents($url, false, $ctx);
                    if ($content) {
                        return 'data:image/png;base64,' . base64_encode($content);
                    }
                } catch (\Throwable $e) {}
                return null;
            });
            if (!$qrBase64) {
                $qrBase64 = 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=' . urlencode($qrPayload);
            }
        }
    @endphp

    <!-- 0. PERSISTENT FOOTER ON EVERY PAGE (REFRENS STYLE) -->
    <footer class="doc-page-footer">
        <div class="footer-dashed-line"></div>
        <table class="footer-meta-table">
            <tr>
                <td style="text-align: left; width: 75%;">
                    Invoice #: <strong style="color: #0f172a;">{{ $invoice->invoice_number }}</strong> &nbsp;|&nbsp;
                    Date: {{ $invoice->invoice_date ? date('d-M-Y', strtotime($invoice->invoice_date)) : '—' }} &nbsp;|&nbsp;
                    Billed To: <strong style="color: #0f172a;">{{ $invoice->customer?->name ?: ($invoice->salesOrder?->customer?->name ?: 'Customer') }}</strong>
                </td>
                <td style="text-align: right; width: 25%;">
                    <!-- Space reserved for Page X of Y rendered by canvas script -->
                </td>
            </tr>
        </table>
    </footer>

    <!-- ═══ 1. GST E-INVOICE & E-WAY BILL BANNER STRIP ═══ -->
    @if($hasEinvoice || $hasEwayBill)
        <table class="einvoice-banner" cellpadding="0" cellspacing="0">
            <tr>
                @if($hasEinvoice && $qrBase64)
                    <td style="width: 68px; vertical-align: middle; text-align: center; padding-right: 8px;">
                        <div class="qr-box">
                            <img src="{{ $qrBase64 }}" alt="QR Code" style="width: 56px; height: 56px; display: block;">
                        </div>
                    </td>
                @endif
                <td style="vertical-align: middle;">
                    <table width="100%" cellpadding="0" cellspacing="0">
                        <tr>
                            <!-- Left: E-Invoice Details -->
                            <td style="vertical-align: top; padding-right: 8px;">
                                <div style="margin-bottom: 2px;">
                                    <span class="badge-einvoice">GST E-INVOICE</span>
                                    <span style="font-size: 8px; color: #64748b; margin-left: 4px;">Govt. E-Invoice Portal (NIC)</span>
                                </div>
                                <div style="font-size: 8.5px; color: #0f172a; margin-bottom: 2px;">
                                    <strong>Ack No:</strong> <span class="font-mono text-primary fw-bold">{{ $invoice->ack_no }}</span>
                                    &nbsp;|&nbsp;
                                    <strong>Ack Date:</strong> <span class="text-muted">{{ $invoice->ack_date ? date('d-M-Y H:i', strtotime($invoice->ack_date)) : '—' }}</span>
                                </div>
                                <div style="font-size: 7.5px; color: #475569; font-family: monospace; word-break: break-all; line-height: 1.2;">
                                    <strong style="color: #0f172a;">IRN:</strong> {{ $invoice->irn }}
                                </div>
                            </td>

                            <!-- Right: E-Way Bill Details -->
                            @if($hasEwayBill)
                                <td style="width: 175px; vertical-align: middle; text-align: right; border-left: 1px solid #cbd5e1; padding-left: 8px;">
                                    <span class="badge-ewaybill">E-WAY BILL ACTIVE</span>
                                    <div class="font-mono fw-bold text-dark" style="font-size: 10.5px; margin-top: 1px;">{{ $invoice->eway_bill_no }}</div>
                                    @if($invoice->transporter_name)
                                        <div style="font-size: 8px; color: #475569;">
                                            Transporter: <strong>{{ $invoice->transporter_name }}</strong>
                                        </div>
                                    @endif
                                    <div style="font-size: 7.5px; color: #64748b;">
                                        Valid Till: <strong style="color: #0f172a;">{{ $invoice->eway_bill_valid_till ? date('d-M-Y H:i', strtotime($invoice->eway_bill_valid_till)) : '—' }}</strong>
                                    </div>
                                </td>
                            @endif
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    @endif

    <!-- ═══ 2. HEADER SECTION ═══ -->
    <table class="header-table">
        <tr>
            <td style="vertical-align: top; width: 58%;">
                <div class="company-avatar">
                    {{ strtoupper(substr(tenant() ? tenant()->name : 'D', 0, 1)) }}
                </div>
                <div class="company-info">
                    <div class="company-name">{{ tenant() ? tenant()->name : 'Demo Tenant' }}</div>
                    <div class="company-sub">Official Corporate Billing Unit</div>
                </div>
                <div class="company-address">
                    H-1, Industrial Area, Sukher, Udaipur 313001, Rajasthan, India<br>
                    <strong>GSTIN:</strong> 08AAFCS1234E1Z0 &nbsp;|&nbsp; <strong>State Code:</strong> 08 (Rajasthan)<br>
                    <strong>Email:</strong> {{ tenant() ? tenant()->billing_email : 'billing@saaserp.com' }} &nbsp;|&nbsp; <strong>Phone:</strong> +91 294 2440230
                </div>
            </td>
            <td style="vertical-align: top; width: 42%; text-align: right;">
                <div class="doc-title">TAX INVOICE</div>
                <div class="doc-number"># {{ $invoice->invoice_number }}</div>
                
                <table class="meta-table">
                    <tr>
                        <td class="meta-label">Invoice Date:</td>
                        <td class="meta-value">{{ $invoice->invoice_date ? date('d-M-Y', strtotime($invoice->invoice_date)) : '—' }}</td>
                    </tr>
                    <tr>
                        <td class="meta-label">Due Date:</td>
                        <td class="meta-value">{{ $invoice->due_date ? date('d-M-Y', strtotime($invoice->due_date)) : '—' }}</td>
                    </tr>
                    <tr>
                        <td class="meta-label">Payment Terms:</td>
                        <td class="meta-value">{{ \App\Domains\Platform\Models\PaymentTerm::getLabel($invoice->payment_terms ?: $invoice->salesOrder?->payment_terms) ?: 'Immediate Payment' }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- ═══ 3. ADDRESS & REFERENCES CARDS ═══ -->
    <table class="info-boxes-table">
        <tr>
            <!-- Left Card: Customer Info -->
            <td class="info-box">
                <div class="box-title">BILLED TO (CUSTOMER):</div>
                <div class="client-name">{{ $invoice->customer?->name ?: ($invoice->salesOrder?->customer?->name ?: 'Customer') }}</div>
                @if($invoice->customer?->email || $invoice->customer?->phone || $invoice->salesOrder?->customer?->phone)
                    <div class="client-contact">
                        {{ $invoice->customer?->email ?: ($invoice->salesOrder?->customer?->email ?: '') }}
                        @if(($invoice->customer?->email || $invoice->salesOrder?->customer?->email) && ($invoice->customer?->phone || $invoice->salesOrder?->customer?->phone))
                            |
                        @endif
                        {{ $invoice->customer?->phone ?: ($invoice->salesOrder?->customer?->phone ?: '') }}
                    </div>
                @endif
                @if($invoice->salesOrder?->billing_address)
                    <div class="address-block">
                        <strong>Billing Address:</strong><br>{{ $invoice->salesOrder->billing_address }}
                    </div>
                @endif
                @if($invoice->salesOrder?->shipping_address && $invoice->salesOrder?->shipping_address !== $invoice->salesOrder?->billing_address)
                    <div class="address-block">
                        <strong>Shipping Address:</strong><br>{{ $invoice->salesOrder->shipping_address }}
                    </div>
                @endif
            </td>

            <!-- Right Card: References -->
            <td class="info-box">
                <div class="box-title">ORDER &amp; DISPATCH REFERENCES:</div>
                
                @if($invoice->salesOrder)
                    <div class="ref-row">
                        <span class="ref-label">Sales Order Ref:</span>
                        <span class="ref-val" style="color: #2563eb;">{{ $invoice->salesOrder->sales_order_number }}</span>
                    </div>
                @endif

                @if($invoice->materialRequirement)
                    <div class="ref-row">
                        <span class="ref-label">Dispatch Ref:</span>
                        <span class="ref-val" style="color: #0284c7;">{{ $invoice->materialRequirement->requirement_number }}</span>
                    </div>
                @endif

                <div class="ref-row">
                    <span class="ref-label">Place of Supply:</span>
                    @php $supply = app(\App\Domains\Accounting\Services\Gst\Gstr1ReturnService::class)->supplyFor($invoice->customer ?? $invoice->salesOrder?->customer); @endphp
                    <span class="ref-val">{{ $supply['pos'] ? \App\Domains\Accounting\Support\GstStates::name($supply['pos']) . ' (' . $supply['pos'] . ')' : '—' }}</span>
                </div>

                <div class="ref-row">
                    <span class="ref-label">GST Tax Type:</span>
                    <span class="ref-val" style="color: #0f766e;">
                        {{ ($invoice->gst_type ?? 'cgst_sgst') === 'igst' ? 'Inter-State GST (IGST)' : 'Intra-State GST (CGST + SGST)' }}
                    </span>
                </div>

                <div class="ref-row">
                    <span class="ref-label">Pricing Mode:</span>
                    <span class="ref-val">{{ ($invoice->effective_tax_mode ?? 'exclusive') === 'inclusive' ? 'Tax Inclusive' : 'Tax Exclusive' }}</span>
                </div>

                <div class="ref-row">
                    <span class="ref-label">Payment Status:</span>
                    <span class="ref-val" style="color: {{ $balanceDue > 0 ? '#dc2626' : '#16a34a' }};">
                        {{ $balanceDue > 0 ? 'Balance Outstanding' : 'Fully Paid' }}
                    </span>
                </div>
            </td>
        </tr>
    </table>

    <!-- ═══ 4. ITEMS TABLE ═══ -->
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 5%; text-align: center;">#</th>
                <th style="width: 45%;">Item &amp; Description</th>
                <th style="width: 8%; text-align: right;">Qty</th>
                <th style="width: 14%; text-align: right;">Rate ({!! $currencySymbol !!})</th>
                <th style="width: 10%; text-align: right;">Tax %</th>
                <th style="width: 18%; text-align: right;">Amount ({!! $currencySymbol !!})</th>
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
                    <td style="text-align: center; color: #64748b;">{{ $idx + 1 }}</td>
                    <td>
                        <div class="item-title">{{ $item->product?->name ?: $item->item_name }}</div>
                        @if($item->product?->sku)
                            <div class="item-desc"><strong>SKU:</strong> {{ $item->product->sku }}</div>
                        @endif
                        @if($item->description)
                            <div class="item-desc">{{ $item->description }}</div>
                        @endif
                    </td>
                    <td style="text-align: right; font-weight: 600;">{{ (float)$item->quantity }}</td>
                    <td style="text-align: right;">{!! $currencySymbol !!}{{ number_format((float) convert_from_base($item->unit_price), 2) }}</td>
                    <td style="text-align: right; color: #64748b;">{{ (float)$item->tax_rate }}%</td>
                    <td style="text-align: right; font-weight: bold; color: #0f172a;">{!! $currencySymbol !!}{{ number_format((float) convert_from_base($lineTotal), 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @php
        $taxMode = $invoice->effective_tax_mode ?? 'exclusive';
        $grossSubtotal = 0;
        $totalItemDiscount = 0;
        $itemsTaxAmount = 0;
        $maxItemTaxRate = 0;
        foreach($invoice->items as $it) {
            $grossSubtotal += ($it->quantity * $it->unit_price);
            $totalItemDiscount += $it->discount;
            if ($invoice->tax_type === 'item_wise_tax') {
                $lineNet = max(0, ($it->quantity * $it->unit_price) - $it->discount);
                if ($taxMode === 'inclusive') {
                    $lineBase = ($it->tax_rate > 0) ? round($lineNet / (1 + ($it->tax_rate / 100)), 4) : $lineNet;
                    $lineTax = $lineNet - $lineBase;
                } else {
                    $lineTax = ($lineNet * ($it->tax_rate / 100));
                }
                $itemsTaxAmount += $lineTax;
                if ($it->tax_rate > $maxItemTaxRate) $maxItemTaxRate = $it->tax_rate;
            }
        }
        $effectiveDiscount = ($invoice->discount_type === 'order_wise') ? (float)$invoice->discount_amount : $totalItemDiscount;
        $itemsNet = max(0, $grossSubtotal - $effectiveDiscount);

        if ($invoice->tax_type === 'order_wise_tax') {
            $orderTaxRate = (float)($invoice->order_tax_rate ?: 18);
            if ($taxMode === 'inclusive') {
                $itemsTaxAmount = ($orderTaxRate > 0) ? ($itemsNet - ($itemsNet / (1 + ($orderTaxRate / 100)))) : 0;
            } else {
                $itemsTaxAmount = $itemsNet * ($orderTaxRate / 100);
            }
        } elseif ($invoice->tax_type === 'without_tax') {
            $itemsTaxAmount = 0;
        }

        if ($taxMode === 'inclusive') {
            $taxableBase = max(0, $itemsNet - $itemsTaxAmount);
            $itemsTotalInclGst = $itemsNet;
        } else {
            $taxableBase = $itemsNet;
            $itemsTotalInclGst = $taxableBase + $itemsTaxAmount;
        }

        $freightAmount = ($invoice->freight_terms === 'To Be Billed') ? (float)($invoice->freight_amount ?: 0) : 0;

        $freightTaxRateOpt = $invoice->freight_tax_rate ?? 'highest';
        $freightTaxRatePercent = 18;
        if ($freightTaxRateOpt === 'highest') {
            if ($invoice->tax_type === 'order_wise_tax') {
                $freightTaxRatePercent = $invoice->order_tax_rate > 0 ? $invoice->order_tax_rate : 18;
            } else {
                $freightTaxRatePercent = ($maxItemTaxRate > 0) ? $maxItemTaxRate : 18;
            }
        } else {
            $freightTaxRatePercent = floatval($freightTaxRateOpt);
        }

        $freightTax = ($freightAmount > 0 && $invoice->tax_type !== 'without_tax') ? round($freightAmount * ($freightTaxRatePercent / 100), 2) : 0;
        $totalInvoiceTaxAmount = $itemsTaxAmount + $freightTax;
        $adjustment = (float)($invoice->adjustment ?? 0);
        $grandTotal = (float)($invoice->total_amount ?: ($taxMode === 'inclusive' ? ($itemsTotalInclGst + $freightAmount + $adjustment) : (($taxableBase + $freightAmount) + $totalInvoiceTaxAmount + $adjustment)));
        $gstType = $invoice->gst_type ?? 'cgst_sgst';
        $taxGroups = $invoice->items->groupBy(fn($item) => (string)(float)$item->tax_rate);
        $hasFreightTax = ($freightAmount > 0 && $freightTax > 0);
        $totalPaid = $invoice->amount_paid ?: 0;
        $balDue = max(0, $grandTotal - $totalPaid);
    @endphp

    <!-- ═══ 5. SPLIT SUMMARY & BANK/COMMERCIALS SECTION ═══ -->
    <table class="calc-wrapper">
        <tr>
            <!-- LEFT COLUMN: Bank Details & Commercials -->
            <td class="calc-notes-col">
                <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 8px 10px; margin-bottom: 8px;">
                    <div style="font-size: 8px; font-weight: bold; text-transform: uppercase; color: #1e40af; letter-spacing: 0.4px; margin-bottom: 4px;">Bank Payment Details</div>
                    <table width="100%" cellpadding="0" cellspacing="0" style="font-size: 9px; color: #475569;">
                        <tr>
                            <td style="width: 50%; padding-bottom: 2px;"><strong>Bank Name:</strong> State Bank of India</td>
                            <td style="width: 50%; padding-bottom: 2px;"><strong>Account Name:</strong> {{ tenant() ? tenant()->name : 'Demo Tenant' }}</td>
                        </tr>
                        <tr>
                            <td><strong>Account No:</strong> 398402948201</td>
                            <td><strong>IFSC Code:</strong> SBIN0001234</td>
                        </tr>
                    </table>
                </div>

                <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 8px 10px;">
                    <div style="font-size: 8px; font-weight: bold; text-transform: uppercase; color: #64748b; letter-spacing: 0.4px; margin-bottom: 4px;">Tax Treatment &amp; Commercials</div>
                    <table style="width: 100%; font-size: 9px; border-collapse: collapse;">
                        <tr>
                            <td style="color: #64748b; padding: 1.5px 0; width: 44%;">Tax Scheme:</td>
                            <td style="font-weight: bold; color: #1e293b; padding: 1.5px 0;">{{ $taxMode === 'inclusive' ? 'Inclusive of GST' : 'Exclusive of GST (Base Rate)' }}</td>
                        </tr>
                        <tr>
                            <td style="color: #64748b; padding: 1.5px 0;">Applicable GST:</td>
                            <td style="font-weight: bold; color: #0f766e; padding: 1.5px 0;">
                                {{ $gstType === 'igst' ? 'IGST (Inter-State Supply)' : 'CGST + SGST (Intra-State Supply)' }}
                            </td>
                        </tr>
                    </table>
                </div>
            </td>

            <!-- RIGHT COLUMN: Subtotal Calculations Card -->
            <td class="calc-summary-col">
                <div class="summary-box">
                    <div class="summary-line">
                        <span class="summary-lbl">{{ $taxMode === 'inclusive' ? 'Subtotal (Incl. GST):' : 'Subtotal (Excl. Tax):' }}</span>
                        <span class="summary-num">{!! $currencySymbol !!}{{ number_format((float) convert_from_base($grossSubtotal), 2) }}</span>
                    </div>

                    @if($invoice->discount_type !== 'without_discount' && $effectiveDiscount > 0)
                        <div class="summary-line" style="color: #dc2626;">
                            <span class="summary-lbl" style="color: #dc2626;">Less: Discounts:</span>
                            <span class="summary-num" style="color: #dc2626;">-{!! $currencySymbol !!}{{ number_format((float) convert_from_base($effectiveDiscount), 2) }}</span>
                        </div>
                    @endif

                    <div class="summary-line" style="color: #475569;">
                        <span class="summary-lbl">{{ $taxMode === 'inclusive' ? 'Taxable Value (Excl. Tax):' : 'Items Taxable Value:' }}</span>
                        <span class="summary-num">{!! $currencySymbol !!}{{ number_format((float) convert_from_base($taxableBase), 2) }}</span>
                    </div>

                    @if($freightAmount > 0)
                        <div class="summary-line">
                            <span class="summary-lbl">Freight Charges:</span>
                            <span class="summary-num">+{!! $currencySymbol !!}{{ number_format((float) convert_from_base($freightAmount), 2) }}</span>
                        </div>
                    @endif

                    @if($invoice->tax_type !== 'without_tax' && $totalInvoiceTaxAmount > 0)
                        @if($gstType === 'cgst_sgst')
                            <div class="summary-line">
                                <span class="summary-lbl">CGST (Central Tax) {{ $taxMode === 'inclusive' ? '(Included)' : '' }}:</span>
                                <span class="summary-num">{{ ($taxMode === 'inclusive' ? '' : '+') }}{!! $currencySymbol !!}{{ number_format((float) convert_from_base($totalInvoiceTaxAmount / 2), 2) }}</span>
                            </div>
                            <div class="summary-line">
                                <span class="summary-lbl">SGST (State Tax) {{ $taxMode === 'inclusive' ? '(Included)' : '' }}:</span>
                                <span class="summary-num">{{ ($taxMode === 'inclusive' ? '' : '+') }}{!! $currencySymbol !!}{{ number_format((float) convert_from_base($totalInvoiceTaxAmount / 2), 2) }}</span>
                            </div>
                        @else
                            <div class="summary-line">
                                <span class="summary-lbl">IGST (Integrated Tax) {{ $taxMode === 'inclusive' ? '(Included)' : '' }}:</span>
                                <span class="summary-num">{{ ($taxMode === 'inclusive' ? '' : '+') }}{!! $currencySymbol !!}{{ number_format((float) convert_from_base($totalInvoiceTaxAmount), 2) }}</span>
                            </div>
                        @endif
                    @endif

                    @if($adjustment != 0)
                        <div class="summary-line">
                            <span class="summary-lbl">Adjustment:</span>
                            <span class="summary-num">{{ $adjustment > 0 ? '+' : '' }}{!! $currencySymbol !!}{{ number_format((float) convert_from_base($adjustment), 2) }}</span>
                        </div>
                    @endif

                    <div class="total-payable-box">
                        <span class="total-payable-lbl">Grand Total:</span>
                        <span class="total-payable-num">{!! $currencySymbol !!}{{ number_format((float) convert_from_base($grandTotal), 2) }}</span>
                    </div>

                    @if($totalPaid > 0)
                        <div class="summary-line" style="color: #16a34a; font-weight: 600; margin-top: 4px;">
                            <span class="summary-lbl" style="color: #16a34a;">Payments Received:</span>
                            <span class="summary-num" style="color: #16a34a;">-{!! $currencySymbol !!}{{ number_format((float) convert_from_base($totalPaid), 2) }}</span>
                        </div>
                    @endif

                    <div class="summary-line" style="border-top: 1px dashed #cbd5e1; margin-top: 3px; padding-top: 3px;">
                        <span class="summary-lbl" style="font-weight: bold; color: {{ $balDue > 0 ? '#dc2626' : '#16a34a' }};">Balance Due:</span>
                        <span class="summary-num" style="font-size: 11px; color: {{ $balDue > 0 ? '#dc2626' : '#16a34a' }};">{!! $currencySymbol !!}{{ number_format((float) convert_from_base($balDue), 2) }}</span>
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <!-- ═══ 6. FULL-WIDTH TERMS & CONDITIONS SECTION (EXACTLY MATCHING QUOTATION PDF) ═══ -->
    @if($invoice->notes)
        <div class="terms-section">
            <div class="section-label">Terms &amp; Conditions / Notes:</div>
            <div class="terms-content">
                {!! $invoice->notes !!}
            </div>
        </div>
    @endif

    <!-- ═══ 7. FULL-WIDTH GST TAX SUMMARY (A4 Standard) ═══ -->
    @if (($taxGroups->count() > 0 && $invoice->tax_amount > 0) || $hasFreightTax)
        <div style="margin-top: 10px; margin-bottom: 8px; page-break-inside: avoid;">
            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 3px;">
                <tr>
                    <td style="font-size: 8px; font-weight: bold; text-transform: uppercase; color: #1e40af; letter-spacing: 0.4px;">
                        GST TAX SUMMARY / TAX BREAKDOWN
                    </td>
                    <td style="text-align: right; font-size: 7.5px; font-weight: bold; color: #64748b;">
                        {{ $gstType === 'igst' ? 'INTER-STATE (IGST)' : 'INTRA-STATE (CGST + SGST)' }}
                    </td>
                </tr>
            </table>
            <table class="tax-summary-table" cellpadding="0" cellspacing="0">
                <thead>
                    @if($gstType === 'igst')
                        <tr>
                            <th style="width: 38%; text-align: left;">Tax Rate / Description</th>
                            <th class="text-right" style="width: 24%;">Taxable Value ({!! $currencySymbol !!})</th>
                            <th class="text-right" style="width: 19%;">Total Tax ({!! $currencySymbol !!})</th>
                            <th class="text-right" style="width: 19%;">IGST Amt ({!! $currencySymbol !!})</th>
                        </tr>
                    @else
                        <tr>
                            <th style="width: 32%; text-align: left;">Tax Rate / Description</th>
                            <th class="text-right" style="width: 20%;">Taxable Value ({!! $currencySymbol !!})</th>
                            <th class="text-right" style="width: 16%;">Total Tax ({!! $currencySymbol !!})</th>
                            <th class="text-right" style="width: 16%;">CGST ({!! $currencySymbol !!})</th>
                            <th class="text-right" style="width: 16%;">SGST ({!! $currencySymbol !!})</th>
                        </tr>
                    @endif
                </thead>
                <tbody>
                    @php
                        $totTaxable = 0; $totCgst = 0; $totSgst = 0; $totIgst = 0; $totTax = 0;
                    @endphp
                    @if($invoice->tax_type === 'order_wise_tax')
                        @php
                            $orderTaxRate = $invoice->order_tax_rate ?: 18;
                            $totTaxable += $taxableBase;
                            $totTax += $itemsTaxAmount;
                        @endphp
                        @if($gstType === 'igst')
                            @php $totIgst += $itemsTaxAmount; @endphp
                            <tr>
                                <td style="font-weight: bold; text-align: left;">GST {{ $orderTaxRate }}% (Order Level)</td>
                                <td class="text-right">{!! $currencySymbol !!}{{ number_format((float) convert_from_base($taxableBase), 2) }}</td>
                                <td class="text-right" style="font-weight: bold; color: #0f172a;">{!! $currencySymbol !!}{{ number_format((float) convert_from_base($itemsTaxAmount), 2) }}</td>
                                <td class="text-right">{!! $currencySymbol !!}{{ number_format((float) convert_from_base($itemsTaxAmount), 2) }}</td>
                            </tr>
                        @else
                            @php
                                $oCgst = round($itemsTaxAmount / 2, 2);
                                $oSgst = round($itemsTaxAmount - $oCgst, 2);
                                $totCgst += $oCgst; $totSgst += $oSgst;
                            @endphp
                            <tr>
                                <td style="font-weight: bold; text-align: left;">GST {{ $orderTaxRate }}% (Order Level)</td>
                                <td class="text-right">{!! $currencySymbol !!}{{ number_format((float) convert_from_base($taxableBase), 2) }}</td>
                                <td class="text-right" style="font-weight: bold; color: #0f172a;">{!! $currencySymbol !!}{{ number_format((float) convert_from_base($itemsTaxAmount), 2) }}</td>
                                <td class="text-right">{!! $currencySymbol !!}{{ number_format((float) convert_from_base($oCgst), 2) }}</td>
                                <td class="text-right">{!! $currencySymbol !!}{{ number_format((float) convert_from_base($oSgst), 2) }}</td>
                            </tr>
                        @endif
                    @else
                        @foreach($taxGroups as $rateStr => $gItems)
                            @php
                                $rate = floatval($rateStr);
                                $grpTaxable = $gItems->sum(function($it) use ($taxMode) {
                                    $lineNet = max(0, ($it->quantity * $it->unit_price) - $it->discount);
                                    return $taxMode === 'inclusive' ? max(0, $lineNet - $it->tax_amount) : $lineNet;
                                });
                                $grpTax = $gItems->sum('tax_amount');
                                $totTaxable += $grpTaxable;
                                $totTax += $grpTax;
                            @endphp
                            @if($gstType === 'igst')
                                @php
                                    $grpIgst = $gItems->sum('igst_amount') > 0 ? $gItems->sum('igst_amount') : $grpTax;
                                    $totIgst += $grpIgst;
                                @endphp
                                <tr>
                                    <td style="font-weight: bold; text-align: left;">GST {{ $rate }}%</td>
                                    <td class="text-right">{!! $currencySymbol !!}{{ number_format((float) convert_from_base($grpTaxable), 2) }}</td>
                                    <td class="text-right" style="font-weight: bold; color: #0f172a;">{!! $currencySymbol !!}{{ number_format((float) convert_from_base($grpTax), 2) }}</td>
                                    <td class="text-right">{!! $currencySymbol !!}{{ number_format((float) convert_from_base($grpIgst), 2) }}</td>
                                </tr>
                            @else
                                @php
                                    $grpCgst = $gItems->sum('cgst_amount') > 0 ? $gItems->sum('cgst_amount') : round($grpTax / 2, 2);
                                    $grpSgst = $gItems->sum('sgst_amount') > 0 ? $gItems->sum('sgst_amount') : round($grpTax - $grpCgst, 2);
                                    $totCgst += $grpCgst; $totSgst += $grpSgst;
                                @endphp
                                <tr>
                                    <td style="font-weight: bold; text-align: left;">GST {{ $rate }}%</td>
                                    <td class="text-right">{!! $currencySymbol !!}{{ number_format((float) convert_from_base($grpTaxable), 2) }}</td>
                                    <td class="text-right" style="font-weight: bold; color: #0f172a;">{!! $currencySymbol !!}{{ number_format((float) convert_from_base($grpTax), 2) }}</td>
                                    <td class="text-right">{!! $currencySymbol !!}{{ number_format((float) convert_from_base($grpCgst), 2) }}</td>
                                    <td class="text-right">{!! $currencySymbol !!}{{ number_format((float) convert_from_base($grpSgst), 2) }}</td>
                                </tr>
                            @endif
                        @endforeach
                    @endif

                    @if($hasFreightTax)
                        @php
                            $totTaxable += $freightAmount;
                            $totTax += $freightTax;
                        @endphp
                        @if($gstType === 'igst')
                            @php $totIgst += $freightTax; @endphp
                            <tr style="background-color: #f1f5f9;">
                                <td style="font-weight: bold; color: #1e40af; text-align: left;">Freight / Shipping ({{ $freightTaxRatePercent }}%)</td>
                                <td class="text-right" style="color: #1e40af;">{!! $currencySymbol !!}{{ number_format((float) convert_from_base($freightAmount), 2) }}</td>
                                <td class="text-right" style="font-weight: bold; color: #1e40af;">{!! $currencySymbol !!}{{ number_format((float) convert_from_base($freightTax), 2) }}</td>
                                <td class="text-right" style="color: #1e40af;">{!! $currencySymbol !!}{{ number_format((float) convert_from_base($freightTax), 2) }}</td>
                            </tr>
                        @else
                            @php
                                $fCgst = round($freightTax / 2, 2);
                                $fSgst = round($freightTax - $fCgst, 2);
                                $totCgst += $fCgst; $totSgst += $fSgst;
                            @endphp
                            <tr style="background-color: #f1f5f9;">
                                <td style="font-weight: bold; color: #1e40af; text-align: left;">Freight / Shipping ({{ $freightTaxRatePercent }}%)</td>
                                <td class="text-right" style="color: #1e40af;">{!! $currencySymbol !!}{{ number_format((float) convert_from_base($freightAmount), 2) }}</td>
                                <td class="text-right" style="font-weight: bold; color: #1e40af;">{!! $currencySymbol !!}{{ number_format((float) convert_from_base($freightTax), 2) }}</td>
                                <td class="text-right" style="color: #1e40af;">{!! $currencySymbol !!}{{ number_format((float) convert_from_base($fCgst), 2) }}</td>
                                <td class="text-right" style="color: #1e40af;">{!! $currencySymbol !!}{{ number_format((float) convert_from_base($fSgst), 2) }}</td>
                            </tr>
                        @endif
                    @endif
                </tbody>
                <tfoot>
                    <tr style="background-color: #f8fafc; font-weight: bold;">
                        <td style="text-align: left;">Total</td>
                        <td class="text-right">{!! $currencySymbol !!}{{ number_format((float) convert_from_base($totTaxable), 2) }}</td>
                        <td class="text-right" style="color: #1e40af;">{!! $currencySymbol !!}{{ number_format((float) convert_from_base($totTax), 2) }}</td>
                        @if($gstType === 'igst')
                            <td class="text-right">{!! $currencySymbol !!}{{ number_format((float) convert_from_base($totIgst), 2) }}</td>
                        @else
                            <td class="text-right">{!! $currencySymbol !!}{{ number_format((float) convert_from_base($totCgst), 2) }}</td>
                            <td class="text-right">{!! $currencySymbol !!}{{ number_format((float) convert_from_base($totSgst), 2) }}</td>
                        @endif
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif

    <!-- ═══ 8. SIGNATURE SECTION ═══ -->
    <table class="signature-table">
        <tr>
            <td class="sig-note">
                Thank you for your business!<br>
                This is a computer generated tax invoice and does not require physical signature.
            </td>
            <td class="sig-space">
                <div class="sig-line">Authorized Signatory</div>
            </td>
        </tr>
    </table>

    <!-- ═══ 9. INLINE CANVAS SCRIPT FOR PAGE NUMBER ═══ -->
    <script type="text/php">
        if (isset($pdf)) {
            $pdf->page_script(function ($pageNumber, $pageCount, $canvas, $fontMetrics) {
                $text = "Page " . $pageNumber . " of " . $pageCount;
                $font = $fontMetrics->getFont("Helvetica", "normal");
                $size = 8.5;
                $color = array(100/255, 116/255, 139/255);
                $w = $canvas->get_width();
                $h = $canvas->get_height();
                $canvas->text($w - 95, $h - 24, $text, $font, $size, $color);
            });
        }
    </script>

</body>
</html>
