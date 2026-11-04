<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>Purchase Order - {{ $order->purchase_order_number }}</title>
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

        /* ── 1. HEADER SECTION ──────────────────────── */
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

        /* ── 2. ADDRESS & REFERENCES CARDS ───────────── */
        .info-boxes-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-bottom: 12px;
            page-break-inside: avoid;
        }
        .info-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 8px 10px;
            vertical-align: top;
            width: 48.5%;
        }
        .info-box-gap {
            width: 3%;
            border: none;
            background: transparent;
            padding: 0;
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
            padding: 2.5px 0;
            border-bottom: 1px solid #e2e8f0;
            font-size: 9px;
            display: table;
        }
        .ref-row:last-child {
            border-bottom: none;
        }
        .ref-label {
            color: #64748b;
            display: table-cell;
            width: 38%;
            vertical-align: top;
            padding-right: 4px;
        }
        .ref-val {
            color: #0f172a;
            font-weight: bold;
            display: table-cell;
            width: 62%;
            text-align: right;
            vertical-align: top;
        }

        /* ── 3. ITEMS TABLE ──────────────────────────── */
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

        /* ── 4. SUMMARY / CALCULATIONS SECTION ──────── */
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

        /* ── 5. FULL-WIDTH TERMS & CONDITIONS SECTION ── */
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
        .terms-content ol,
        .terms-content ul {
            margin: 3px 0;
            padding-left: 20px;
        }
        .terms-content li {
            margin-bottom: 3px;
        }
        .terms-content p {
            margin: 0 0 3px 0;
            padding-top: 1px;
            padding-bottom: 1px;
            page-break-inside: avoid;
        }

        /* ── 6. GST TAX SUMMARY TABLE ────────────────── */
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

        /* ── 7. SIGNATURE SECTION ────────────────────── */
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
        $buyerOrg = billing_organization_details($order);
        $hasTax = ((float)($order->tax_amount ?? 0) > 0) || $order->items->contains(function($item) {
            return (float)($item->tax_amount ?? 0) > 0 || (float)($item->tax_percent ?? 0) > 0;
        });
        $hasDiscount = ((float)($order->discount_amount ?? 0) > 0) || $order->items->contains(function($item) {
            return (float)($item->discount_amount ?? 0) > 0;
        });
    @endphp

    <!-- ═══ PERSISTENT FOOTER ON EVERY PAGE (REFRENS STYLE) ═══ -->
    <footer class="doc-page-footer">
        <div class="footer-dashed-line"></div>
        <table class="footer-meta-table">
            <tr>
                <td style="text-align: left; width: 75%;">
                    PO #: <strong>{{ $order->purchase_order_number }}</strong>
                    &nbsp;|&nbsp; Date: <strong>{{ $order->date ? $order->date->format('d-M-Y') : '—' }}</strong>
                    &nbsp;|&nbsp; Supplier: <strong>{{ $order->vendor?->name ?? '—' }}</strong>
                </td>
                <td style="text-align: right; width: 25%;">
                    Page <span class="page-number"></span>
                </td>
            </tr>
        </table>
    </footer>

    <!-- ═══ 1. HEADER SECTION ═══ -->
    <table class="header-table">
        <tr>
            <td style="vertical-align: top; width: 58%;">
                <div class="company-avatar">
                    {{ $buyerOrg['avatar_char'] ?? strtoupper(substr($buyerOrg['name'] ?? 'P', 0, 1)) }}
                </div>
                <div class="company-info">
                    <div class="company-name">{{ $buyerOrg['name'] }}</div>
                    @if(!empty($buyerOrg['unit_subtext']))
                        <div class="company-sub">{{ $buyerOrg['unit_subtext'] }}</div>
                    @endif
                </div>
                <div class="company-address">
                    @if(!empty($buyerOrg['address']))
                        {{ $buyerOrg['address'] }}<br>
                    @endif
                    @if(!empty($buyerOrg['gstin']))
                        <strong>GSTIN:</strong> {{ $buyerOrg['gstin'] }}
                    @endif
                    @if(!empty($buyerOrg['state_code']) && !empty($buyerOrg['state_name']))
                        @if(!empty($buyerOrg['gstin'])) &nbsp;|&nbsp; @endif
                        <strong>State Code:</strong> {{ $buyerOrg['state_code'] }} ({{ $buyerOrg['state_name'] }})
                    @endif
                    @if(!empty($buyerOrg['gstin']) || !empty($buyerOrg['state_code']))
                        <br>
                    @endif
                    @if(!empty($buyerOrg['email']))
                        <strong>Email:</strong> {{ $buyerOrg['email'] }}
                    @endif
                    @if(!empty($buyerOrg['email']) && !empty($buyerOrg['phone']))
                        &nbsp;|&nbsp;
                    @endif
                    @if(!empty($buyerOrg['phone']))
                        <strong>Phone:</strong> {{ $buyerOrg['phone'] }}
                    @endif
                </div>
            </td>
            <td style="vertical-align: top; width: 42%; text-align: right;">
                <div class="doc-title">PURCHASE ORDER</div>
                <div class="doc-number"># {{ $order->purchase_order_number }}</div>

                <table class="meta-table">
                    <tr>
                        <td class="meta-label">PO Date:</td>
                        <td class="meta-value">{{ $order->date ? $order->date->format('d-M-Y') : '—' }}</td>
                    </tr>
                    @if($order->delivery_date)
                        <tr>
                            <td class="meta-label">Delivery Date:</td>
                            <td class="meta-value">{{ $order->delivery_date->format('d-M-Y') }}</td>
                        </tr>
                    @endif
                    <tr>
                        <td class="meta-label">Payment Terms:</td>
                        <td class="meta-value">{{ $order->payment_terms ?: 'As Agreed' }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- ═══ 2. ADDRESS & REFERENCES CARDS (2-BOX FORMAT) ═══ -->
    <table class="info-boxes-table">
        <tr>
            <!-- Left Box: Supplier Details (Vendor) -->
            <td class="info-box">
                <div class="box-title">SUPPLIER DETAILS (VENDOR):</div>
                <div class="client-name">{{ $order->vendor?->name ?? '—' }}</div>
                @if($order->vendor?->code)
                    <div style="font-size: 8.5px; color: #475569; margin-bottom: 2px;">
                        Supplier Code: <strong>{{ $order->vendor->code }}</strong>
                    </div>
                @endif
                @if($order->vendor?->gstin || $order->vendor?->tax_number)
                    <div style="font-size: 8.5px; margin-bottom: 3px;">
                        <strong>GSTIN:</strong> {{ $order->vendor?->gstin ?: $order->vendor?->tax_number }}
                    </div>
                @endif
                @if($order->vendor?->email || $order->vendor?->phone)
                    <div class="client-contact">
                        {{ $order->vendor?->email ?: '' }}
                        @if($order->vendor?->email && $order->vendor?->phone) | @endif
                        {{ $order->vendor?->phone ?: '' }}
                    </div>
                @endif

                <div class="address-block">
                    <strong style="color: #0f172a; display: block; margin-bottom: 2px;">Supplier Address:</strong>
                    {{ $order->vendor?->address ?: ($order->vendor?->billing_address ?: '—') }}
                </div>
            </td>

            <!-- Gap -->
            <td class="info-box-gap"></td>

            <!-- Right Box: Order & Delivery References -->
            <td class="info-box">
                <div class="box-title">ORDER &amp; DELIVERY REFERENCES:</div>
                
                <div class="ref-row">
                    <span class="ref-label">Delivery Location:</span>
                    <span class="ref-val" style="color: #1e40af;">{{ $order->warehouse?->name ?: ($order->location ?: 'Main Warehouse') }}</span>
                </div>

                @php
                    $deliveryAddrText = $order->warehouse?->address ?: ($order->location ?: ($buyerOrg['address'] ?? null));
                @endphp
                @if($deliveryAddrText)
                    <div class="ref-row">
                        <span class="ref-label">Delivery Address:</span>
                        <span class="ref-val" style="font-weight: normal; color: #475569; font-size: 8.5px;">{{ $deliveryAddrText }}</span>
                    </div>
                @endif

                @if($hasTax)
                    <div class="ref-row">
                        <span class="ref-label">GST Tax Type:</span>
                        <span class="ref-val">{{ $order->gst_type === 'igst' ? 'Inter-State GST (IGST)' : 'Intra-State GST (CGST + SGST)' }}</span>
                    </div>
                @endif

                @if($order->freight_terms)
                    <div class="ref-row">
                        <span class="ref-label">Freight Terms:</span>
                        <span class="ref-val">{{ ucwords(str_replace('_', ' ', $order->freight_terms)) }}</span>
                    </div>
                @endif

                @if($order->requisition)
                    <div class="ref-row">
                        <span class="ref-label">Requisition Ref:</span>
                        <span class="ref-val" style="color: #2563eb;">{{ $order->requisition->requisition_number }}</span>
                    </div>
                @endif

                @if($order->supplier_quotation_number)
                    <div class="ref-row">
                        <span class="ref-label">Supplier Quote No:</span>
                        <span class="ref-val">{{ $order->supplier_quotation_number }}</span>
                    </div>
                @endif

                @if($order->reference)
                    <div class="ref-row">
                        <span class="ref-label">Internal Ref:</span>
                        <span class="ref-val">{{ $order->reference }}</span>
                    </div>
                @endif

                <div class="ref-row">
                    <span class="ref-label">Payment Terms:</span>
                    <span class="ref-val">{{ $order->payment_terms ?: 'As Agreed' }}</span>
                </div>
            </td>
        </tr>
    </table>

    <!-- ═══ 3. ITEMS TABLE ═══ -->
    <table class="items-table">
        <thead>
            @php
                if ($hasDiscount && $hasTax) {
                    $itemDescWidth = '38%';
                    $qtyWidth = '8%';
                    $rateWidth = '14%';
                    $discWidth = '12%';
                    $taxWidth = '10%';
                    $amountWidth = '14%';
                } elseif ($hasDiscount) {
                    $itemDescWidth = '46%';
                    $qtyWidth = '10%';
                    $rateWidth = '14%';
                    $discWidth = '14%';
                    $amountWidth = '16%';
                } elseif ($hasTax) {
                    $itemDescWidth = '46%';
                    $qtyWidth = '10%';
                    $rateWidth = '14%';
                    $taxWidth = '10%';
                    $amountWidth = '16%';
                } else {
                    $itemDescWidth = '50%';
                    $qtyWidth = '12%';
                    $rateWidth = '16%';
                    $amountWidth = '18%';
                }
            @endphp
            <tr>
                <th class="text-center" style="width: 4%;">#</th>
                <th class="text-left" style="width: {{ $itemDescWidth }};">ITEM &amp; DESCRIPTION</th>
                <th class="text-center" style="width: {{ $qtyWidth }};">QTY</th>
                <th class="text-right" style="width: {{ $rateWidth }};">RATE ({!! $currencySymbol !!})</th>
                @if($hasDiscount)
                    <th class="text-right" style="width: {{ $discWidth }};">DISCOUNT ({!! $currencySymbol !!})</th>
                @endif
                @if($hasTax)
                    <th class="text-center" style="width: {{ $taxWidth }};">TAX %</th>
                @endif
                <th class="text-right" style="width: {{ $amountWidth }};">AMOUNT ({!! $currencySymbol !!})</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($order->items as $index => $item)
                @php
                    $itemQty = (float) $item->quantity;
                    $itemRate = (float) $item->rate;
                    $itemGross = (float) ($item->amount ?: ($itemQty * $itemRate));
                    $itemDiscAmt = (float) ($item->discount_amount ?: 0);
                    $itemTaxAmt = (float) ($item->tax_amount ?: 0);
                    $itemTotal = (float) ($item->total_amount ?: (max(0, $itemGross - $itemDiscAmt) + $itemTaxAmt));
                    $taxPct = (float) ($item->tax_percent ?: (($item->cgst_percent ?? 0) + ($item->sgst_percent ?? 0) + ($item->igst_percent ?? 0)));
                    $hsnCode = $item->product?->hsn_sac ?: ($item->product?->hsn_code ?: ($item->product?->hsn ?: null));
                @endphp
                <tr>
                    <td class="text-center text-muted">{{ $index + 1 }}</td>
                    <td class="text-left">
                        <div class="item-title">
                            {{ $item->product?->name ?: ($item->description ?: 'Item #' . ($index + 1)) }}
                        </div>
                        @if($item->product?->sku)
                            <div class="item-desc">SKU: {{ $item->product->sku }}</div>
                        @endif
                        @if(!empty($hsnCode) && $hsnCode !== '—')
                            <div class="item-desc">HSN: {{ $hsnCode }}</div>
                        @endif
                        @if($item->description && $item->product && $item->description !== $item->product->name)
                            <div class="item-desc">{{ $item->description }}</div>
                        @endif
                        @if($item->line_type && $item->line_type !== 'stock')
                            <div class="item-desc" style="color: #1e40af; font-weight: bold; text-transform: uppercase;">Type: {{ $item->line_type }}</div>
                        @endif
                    </td>
                    <td class="text-center fw-bold">{{ rtrim(rtrim(number_format($itemQty, 4), '0'), '.') }}</td>
                    <td class="text-right">{!! $currencySymbol !!}{{ number_format($itemRate, 2) }}</td>
                    @if($hasDiscount)
                        <td class="text-right" style="color: #dc2626;">
                            @if($itemDiscAmt > 0)
                                -{!! $currencySymbol !!}{{ number_format($itemDiscAmt, 2) }}
                                @if((float)$item->discount_percent > 0)
                                    <div class="item-desc" style="color: #64748b;">({{ rtrim(rtrim(number_format((float)$item->discount_percent, 2), '0'), '.') }}%)</div>
                                @endif
                            @else
                                <span style="color: #94a3b8;">—</span>
                            @endif
                        </td>
                    @endif
                    @if($hasTax)
                        <td class="text-center text-muted">
                            {{ $taxPct > 0 ? (rtrim(rtrim(number_format($taxPct, 2), '0'), '.') . '%') : '0%' }}
                        </td>
                    @endif
                    <td class="text-right fw-bold text-dark">
                        {!! $currencySymbol !!}{{ number_format($itemTotal, 2) }}
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <!-- ═══ 4. SUMMARY / CALCULATIONS SECTION ═══ -->
    <table class="calc-wrapper">
        <tr>
            <!-- Left Column: Empty space as requested -->
            <td class="calc-notes-col"></td>

            <!-- Right Column: Calculations Summary -->
            <td class="calc-summary-col">
                <div class="summary-box">
                    <div class="summary-line">
                        <span class="summary-lbl">{{ $hasTax ? 'Subtotal (Excl. Tax):' : 'Subtotal:' }}</span>
                        <span class="summary-num">{!! $currencySymbol !!}{{ number_format((float)$order->subtotal, 2) }}</span>
                    </div>

                    @if($order->discount_amount > 0)
                        <div class="summary-line" style="color: #dc2626;">
                            <span class="summary-lbl" style="color: #dc2626;">Discount:</span>
                            <span class="summary-num" style="color: #dc2626;">-{!! $currencySymbol !!}{{ number_format((float)$order->discount_amount, 2) }}</span>
                        </div>
                    @endif

                    @if($order->tax_amount > 0)
                        @if($order->gst_type === 'igst')
                            <div class="summary-line">
                                <span class="summary-lbl">IGST (Integrated Tax):</span>
                                <span class="summary-num">+{!! $currencySymbol !!}{{ number_format((float)($order->igst_amount ?: $order->tax_amount), 2) }}</span>
                            </div>
                        @else
                            <div class="summary-line">
                                <span class="summary-lbl">CGST (Central Tax):</span>
                                <span class="summary-num">+{!! $currencySymbol !!}{{ number_format((float)($order->cgst_amount ?: ($order->tax_amount / 2)), 2) }}</span>
                            </div>
                            <div class="summary-line">
                                <span class="summary-lbl">SGST (State Tax):</span>
                                <span class="summary-num">+{!! $currencySymbol !!}{{ number_format((float)($order->sgst_amount ?: ($order->tax_amount / 2)), 2) }}</span>
                            </div>
                        @endif
                    @endif

                    @if($order->freight_amount > 0)
                        <div class="summary-line">
                            <span class="summary-lbl">Freight Charges:</span>
                            <span class="summary-num">+{!! $currencySymbol !!}{{ number_format((float)$order->freight_amount, 2) }}</span>
                        </div>
                    @endif

                    <div class="total-payable-box">
                        <span class="total-payable-lbl">Grand Total:</span>
                        <span class="total-payable-num">{!! $currencySymbol !!}{{ number_format((float)$order->grand_total, 2) }}</span>
                    </div>

                    @if($order->advance_paid > 0)
                        <div class="summary-line" style="margin-top: 4px; color: #16a34a;">
                            <span class="summary-lbl" style="color: #16a34a;">Advance Paid:</span>
                            <span class="summary-num" style="color: #16a34a;">-{!! $currencySymbol !!}{{ number_format((float)$order->advance_paid, 2) }}</span>
                        </div>
                        <div class="summary-line">
                            <span class="summary-lbl"><strong>Balance Due:</strong></span>
                            <span class="summary-num"><strong>{!! $currencySymbol !!}{{ number_format((float)$order->balance_due, 2) }}</strong></span>
                        </div>
                    @endif
                </div>
            </td>
        </tr>
    </table>

    <!-- ═══ 5. FULL-WIDTH TERMS & CONDITIONS / NOTES SECTION ═══ -->
    @if($order->notes || $order->terms_conditions)
        <div class="terms-section">
            <div class="section-label">TERMS &amp; CONDITIONS / NOTES:</div>
            <div class="terms-content">
                @if($order->notes)
                    @if(strip_tags($order->notes) !== $order->notes)
                        {!! $order->notes !!}
                    @else
                        <p style="white-space: pre-line;">{{ $order->notes }}</p>
                    @endif
                @endif

                @if($order->terms_conditions)
                    @if(strip_tags($order->terms_conditions) !== $order->terms_conditions)
                        {!! $order->terms_conditions !!}
                    @else
                        <p style="white-space: pre-line;">{{ $order->terms_conditions }}</p>
                    @endif
                @endif
            </div>
        </div>
    @endif

    <!-- ═══ 6. GST TAX SUMMARY / BREAKDOWN ═══ -->
    @if($order->tax_amount > 0)
        @php
            $subtotalVal = (float)$order->subtotal - (float)$order->discount_amount;
            $taxVal = (float)$order->tax_amount;
            $isIgst = ($order->gst_type === 'igst');
        @endphp
        <div style="margin-top: 14px; page-break-inside: avoid;">
            <table style="width: 100%; border-collapse: collapse; margin-bottom: 3px;">
                <tr>
                    <td style="font-size: 9px; font-weight: bold; color: #1e40af; text-transform: uppercase; letter-spacing: 0.5px;">
                        GST TAX SUMMARY / TAX BREAKDOWN
                    </td>
                    <td style="font-size: 8.5px; font-weight: bold; color: #64748b; text-align: right; text-transform: uppercase;">
                        {{ $isIgst ? 'INTER-STATE (IGST)' : 'INTRA-STATE (CGST + SGST)' }}
                    </td>
                </tr>
            </table>

            <table class="tax-summary-table">
                <thead>
                    <tr>
                        <th class="text-left">TAX RATE / DESCRIPTION</th>
                        <th class="text-right">TAXABLE VALUE ({!! $currencySymbol !!})</th>
                        <th class="text-right">TOTAL TAX ({!! $currencySymbol !!})</th>
                        @if($isIgst)
                            <th class="text-right">IGST ({!! $currencySymbol !!})</th>
                        @else
                            <th class="text-right">CGST ({!! $currencySymbol !!})</th>
                            <th class="text-right">SGST ({!! $currencySymbol !!})</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="text-left fw-bold">GST Summary</td>
                        <td class="text-right">{!! $currencySymbol !!}{{ number_format($subtotalVal, 2) }}</td>
                        <td class="text-right fw-bold">{!! $currencySymbol !!}{{ number_format($taxVal, 2) }}</td>
                        @if($isIgst)
                            <td class="text-right">{!! $currencySymbol !!}{{ number_format($taxVal, 2) }}</td>
                        @else
                            <td class="text-right">{!! $currencySymbol !!}{{ number_format($taxVal / 2, 2) }}</td>
                            <td class="text-right">{!! $currencySymbol !!}{{ number_format($taxVal / 2, 2) }}</td>
                        @endif
                    </tr>
                </tbody>
                <tfoot>
                    <tr>
                        <td class="text-left">Total</td>
                        <td class="text-right">{!! $currencySymbol !!}{{ number_format($subtotalVal, 2) }}</td>
                        <td class="text-right">{!! $currencySymbol !!}{{ number_format($taxVal, 2) }}</td>
                        @if($isIgst)
                            <td class="text-right">{!! $currencySymbol !!}{{ number_format($taxVal, 2) }}</td>
                        @else
                            <td class="text-right">{!! $currencySymbol !!}{{ number_format($taxVal / 2, 2) }}</td>
                            <td class="text-right">{!! $currencySymbol !!}{{ number_format($taxVal / 2, 2) }}</td>
                        @endif
                    </tr>
                </tfoot>
            </table>
        </div>
    @endif

    <!-- ═══ 7. SIGNATURE SECTION ═══ -->
    <table class="signature-table">
        <tr>
            <td class="sig-note">
                <div style="font-weight: bold; color: #0f172a; margin-bottom: 2px;">Thank you for your business!</div>
                <div>This is a computer generated purchase order and does not require physical signature.</div>
            </td>
            <td class="sig-space">
                <div class="sig-line">
                    AUTHORIZED SIGNATORY
                </div>
            </td>
        </tr>
    </table>

</body>
</html>
