<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>Sales Order - {{ $order->sales_order_number }}</title>
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

        /* ── HEADER SECTION ──────────────────────── */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            padding-bottom: 12px;
            border-bottom: 1px solid #cbd5e1;
            margin-bottom: 14px;
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
            margin-top: 6px;
        }
        .meta-table td {
            padding: 2px 0;
            font-size: 10px;
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

        /* ── CUSTOMER & ORDER REFERENCES ─────────── */
        .info-boxes-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-bottom: 14px;
            page-break-inside: avoid;
        }
        .info-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 10px 12px;
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
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            margin-bottom: 5px;
        }
        .client-name {
            font-size: 12.5px;
            font-weight: bold;
            color: #0f172a;
            margin-bottom: 3px;
        }
        .client-contact {
            font-size: 10px;
            color: #475569;
            margin-bottom: 5px;
        }
        .address-block {
            font-size: 9.5px;
            color: #475569;
            border-top: 1px solid #e2e8f0;
            padding-top: 5px;
            margin-top: 4px;
            line-height: 1.35;
        }

        .ref-row {
            width: 100%;
            padding: 2.5px 0;
            border-bottom: 1px solid #e2e8f0;
            font-size: 10px;
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

        /* ── ITEMS TABLE WITH DISTINCT COLUMN BORDERS ── */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
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
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: 1px solid #cbd5e1;
            padding: 7px 8px;
        }
        .items-table td {
            padding: 7px 8px;
            border: 1px solid #e2e8f0;
            font-size: 10px;
            color: #1e293b;
            vertical-align: top;
        }
        .item-title {
            font-weight: bold;
            color: #0f172a;
            font-size: 10.5px;
        }
        .item-desc {
            font-size: 8.5px;
            color: #64748b;
            margin-top: 2px;
        }

        /* ── SUMMARY / CALCULATIONS SECTION ──────── */
        .calc-wrapper {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
            margin-bottom: 14px;
            page-break-inside: avoid;
        }
        .calc-notes-col {
            width: 50%;
            vertical-align: top;
            padding-right: 15px;
        }
        .calc-summary-col {
            width: 50%;
            vertical-align: top;
        }

        .summary-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 8px 12px;
        }
        .summary-line {
            width: 100%;
            padding: 3px 0;
            font-size: 10px;
            border-bottom: 1px solid #f1f5f9;
        }
        .summary-lbl {
            color: #64748b;
            display: inline-block;
            width: 48%;
        }
        .summary-num {
            color: #0f172a;
            font-weight: bold;
            display: inline-block;
            width: 50%;
            text-align: right;
        }
        .total-payable-box {
            background-color: #f1f5f9;
            border-radius: 4px;
            padding: 6px 8px;
            margin-top: 6px;
        }
        .total-payable-lbl {
            font-weight: bold;
            color: #0f172a;
            font-size: 11px;
            display: inline-block;
            width: 46%;
        }
        .total-payable-num {
            font-weight: bold;
            color: #1e40af;
            font-size: 12px;
            display: inline-block;
            width: 52%;
            text-align: right;
        }

        /* ── TERMS & CONDITIONS SECTION ──────────── */
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

        /* ── SIGNATURE SECTION ───────────────────── */
        .signature-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 25px;
            padding-top: 10px;
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

    <!-- 0. PERSISTENT FOOTER ON EVERY PAGE (REFRENS STYLE) -->
    <footer class="doc-page-footer">
        <div class="footer-dashed-line"></div>
        <table class="footer-meta-table">
            <tr>
                <td style="text-align: left; width: 75%;">
                    Sales Order #: <strong style="color: #0f172a;">{{ $order->sales_order_number }}</strong> &nbsp;|&nbsp;
                    Date: {{ $order->order_date ? date('d-M-Y', strtotime($order->order_date)) : '—' }} &nbsp;|&nbsp;
                    Customer: <strong style="color: #0f172a;">{{ $order->customer?->name ?? '—' }}</strong>
                </td>
                <td style="text-align: right; width: 25%;">
                    <!-- Space reserved for Page X of Y rendered by canvas script -->
                </td>
            </tr>
        </table>
    </footer>

    <!-- 1. HEADER SECTION -->
    <table class="header-table">
        <tr>
            <td style="vertical-align: top; width: 58%;">
                @php
                    $sellerOrg = billing_organization_details($order);
                @endphp
                <div class="company-avatar">
                    {{ $sellerOrg['avatar_char'] }}
                </div>
                <div class="company-info">
                    <div class="company-name">{{ $sellerOrg['name'] }}</div>
                    @if(!empty($sellerOrg['unit_subtext']))
                        <div class="company-sub">{{ $sellerOrg['unit_subtext'] }}</div>
                    @endif
                </div>
                <div class="company-address">
                    @if(!empty($sellerOrg['address']))
                        {{ $sellerOrg['address'] }}<br>
                    @endif
                    @if(!empty($sellerOrg['gstin']))
                        <strong>GSTIN:</strong> {{ $sellerOrg['gstin'] }}
                    @endif
                    @if(!empty($sellerOrg['state_code']) && !empty($sellerOrg['state_name']))
                        @if(!empty($sellerOrg['gstin'])) &nbsp;|&nbsp; @endif
                        <strong>State Code:</strong> {{ $sellerOrg['state_code'] }} ({{ $sellerOrg['state_name'] }})
                    @endif
                    @if(!empty($sellerOrg['gstin']) || !empty($sellerOrg['state_code']))
                        <br>
                    @endif
                    @if(!empty($sellerOrg['email']))
                        <strong>Email:</strong> {{ $sellerOrg['email'] }}
                    @endif
                    @if(!empty($sellerOrg['email']) && !empty($sellerOrg['phone']))
                        &nbsp;|&nbsp;
                    @endif
                    @if(!empty($sellerOrg['phone']))
                        <strong>Phone:</strong> {{ $sellerOrg['phone'] }}
                    @endif
                </div>
            </td>
            <td style="vertical-align: top; width: 42%; text-align: right;">
                <div class="doc-title">SALES ORDER</div>
                <div class="doc-number"># {{ $order->sales_order_number }}</div>
                
                <table class="meta-table">
                    <tr>
                        <td class="meta-label">Order Date:</td>
                        <td class="meta-value">{{ $order->order_date ? date('d-M-Y', strtotime($order->order_date)) : '—' }}</td>
                    </tr>
                    @if($order->shipment_date)
                        <tr>
                            <td class="meta-label">Est. Shipment:</td>
                            <td class="meta-value">{{ date('d-M-Y', strtotime($order->shipment_date)) }}</td>
                        </tr>
                    @endif
                    @if($order->salesPerson)
                        <tr>
                            <td class="meta-label">Sales Rep:</td>
                            <td class="meta-value">{{ $order->salesPerson->name }}</td>
                        </tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    <!-- 2. CUSTOMER INFO & ORDER REFERENCES -->
    <table class="info-boxes-table">
        <tr>
            <!-- Left Box: Customer Info -->
            <td class="info-box">
                <div class="box-title">BILLED TO (CUSTOMER):</div>
                <div class="client-name">{{ $order->customer?->name ?? '—' }}</div>
                @if($order->customer?->email || $order->customer?->phone)
                    <div class="client-contact">
                        @if($order->customer?->email)
                            {{ $order->customer->email }}
                        @endif
                        @if($order->customer?->email && $order->customer?->phone)
                            |
                        @endif
                        @if($order->customer?->phone)
                            {{ $order->customer->phone }}
                        @endif
                    </div>
                @endif

                @if ($order->billing_address)
                    <div class="address-block">
                        <strong>Billing Address:</strong><br>{{ $order->billing_address }}
                    </div>
                @endif

                @if ($order->shipping_address && $order->shipping_address !== $order->billing_address)
                    <div class="address-block">
                        <strong>Shipping Address:</strong><br>{{ $order->shipping_address }}
                    </div>
                @endif
            </td>

            <!-- Gap -->
            <td class="info-box-gap"></td>

            <!-- Right Box: Order References -->
            <td class="info-box">
                <div class="box-title">ORDER REFERENCES & SCHEDULE:</div>
                
                @if ($order->quotation)
                    <div class="ref-row">
                        <span class="ref-label">Quotation Ref:</span>
                        <span class="ref-val" style="color: #2563eb;">{{ $order->quotation->quotation_number }}</span>
                    </div>
                @endif

                <div class="ref-row">
                    <span class="ref-label">Pricing Mode:</span>
                    <span class="ref-val">{{ $order->effective_tax_mode === 'inclusive' ? 'Tax Inclusive' : 'Tax Exclusive' }}</span>
                </div>

                <div class="ref-row">
                    <span class="ref-label">Discount Option:</span>
                    <span class="ref-val">
                        @if($order->discount_type === 'item_wise')
                            Line / Item Level
                        @elseif($order->discount_type === 'without_discount')
                            Without Discount
                        @else
                            Order Level
                        @endif
                    </span>
                </div>

                <div class="ref-row">
                    <span class="ref-label">GST Tax Type:</span>
                    <span class="ref-val" style="color: #0f766e;">
                        @if(($order->gst_type ?? 'cgst_sgst') === 'cgst_sgst')
                            Intra-State (CGST + SGST)
                        @else
                            Inter-State (IGST)
                        @endif
                    </span>
                </div>

                <div class="ref-row">
                    <span class="ref-label">Payment Terms:</span>
                    <span class="ref-val">{{ \App\Domains\Platform\Models\PaymentTerm::getLabel($order->payment_terms) ?: 'Immediate Payment' }}</span>
                </div>

                <div class="ref-row">
                    <span class="ref-label">Status:</span>
                    <span class="ref-val">{{ $order->status }}</span>
                </div>
            </td>
        </tr>
    </table>

    <!-- 3. ORDER LINES TABLE WITH COLUMN DIVIDERS -->
    @php
        $rawSymbol = active_currency_symbol();
        $currencySymbol = ($rawSymbol === '₹' || active_currency() === 'INR') ? '&#8377;' : $rawSymbol;
        $hasItemDiscount = $order->discount_type === 'item_wise' || $order->items->sum('discount') > 0;
    @endphp
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 5%; text-align: center;">#</th>
                <th style="{{ $hasItemDiscount ? 'width: 38%;' : 'width: 44%;' }}">Item & Description</th>
                <th style="width: 12%; text-align: left;">Warehouse</th>
                <th style="width: 8%; text-align: right;">Qty</th>
                <th style="width: 13%; text-align: right;">Rate ({!! $currencySymbol !!})</th>
                @if($hasItemDiscount)
                    <th style="width: 11%; text-align: right;">Discount ({!! $currencySymbol !!})</th>
                @endif
                <th style="width: 8%; text-align: right;">Tax %</th>
                <th style="width: 14%; text-align: right;">Amount ({!! $currencySymbol !!})</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($order->items as $idx => $item)
                <tr>
                    <td style="text-align: center; color: #64748b;">{{ $idx + 1 }}</td>
                    <td>
                        <div class="item-title">{{ $item->item_name }}</div>
                        @if($item->product?->sku)
                            <div class="item-desc"><strong>SKU:</strong> {{ $item->product->sku }}</div>
                        @endif
                        @if($item->description)
                            <div class="item-desc">{{ $item->description }}</div>
                        @endif
                    </td>
                    <td style="text-align: left; color: #475569; font-size: 9.5px;">
                        {{ $item->warehouse?->name ?: '—' }}
                    </td>
                    <td style="text-align: right; font-weight: 600;">{{ $item->quantity }}</td>
                    <td style="text-align: right;">{!! $currencySymbol !!}{{ number_format((float) convert_from_base($item->unit_price), 2) }}</td>
                    @if($hasItemDiscount)
                        <td style="text-align: right; color: #dc2626;">{{ ($item->discount ?? 0) > 0 ? '-' . number_format((float) convert_from_base($item->discount), 2) : '—' }}</td>
                    @endif
                    <td style="text-align: right;">{{ number_format((float)$item->tax_rate, 2) }}%</td>
                    <td style="text-align: right; font-weight: bold; color: #0f172a;">{!! $currencySymbol !!}{{ number_format((float) convert_from_base($item->amount ?: ($item->quantity * $item->unit_price)), 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <!-- 4. CALCULATIONS SECTION -->
    <table class="calc-wrapper">
        <tr>
            <!-- Left: Tax Treatment Summary & Notes -->
            <td class="calc-notes-col">
                <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px 12px; margin-bottom: 8px;">
                    <div style="font-size: 8.5px; font-weight: bold; text-transform: uppercase; color: #64748b; letter-spacing: 0.5px; margin-bottom: 4px;">Tax Treatment & Commercials Summary</div>
                    <table style="width: 100%; font-size: 9.5px; border-collapse: collapse;">
                        <tr>
                            <td style="color: #64748b; padding: 2px 0; width: 44%;">Tax Scheme:</td>
                            <td style="font-weight: bold; color: #1e293b; padding: 2px 0;">{{ $order->effective_tax_mode === 'inclusive' ? 'Inclusive of GST' : 'Exclusive of GST (Base Rate)' }}</td>
                        </tr>
                        <tr>
                            <td style="color: #64748b; padding: 2px 0;">Applicable GST:</td>
                            <td style="font-weight: bold; color: #0f766e; padding: 2px 0;">
                                @if(($order->gst_type ?? 'cgst_sgst') === 'cgst_sgst')
                                    CGST + SGST (Intra-State Supply)
                                @else
                                    IGST (Inter-State Supply)
                                @endif
                            </td>
                        </tr>
                        @if($order->freight_terms)
                            <tr>
                                <td style="color: #64748b; padding: 2px 0;">Freight Terms:</td>
                                <td style="font-weight: bold; color: #1e293b; padding: 2px 0;">{{ $order->freight_terms }}</td>
                            </tr>
                        @endif
                    </table>
                </div>

                @if(!empty($order->notes))
                    <div class="section-label">Internal Notes / Remarks:</div>
                    <div class="terms-content" style="font-style: italic; color: #64748b; margin-bottom: 8px;">{{ $order->notes }}</div>
                @endif
            </td>

            <!-- Right: Totals Box -->
            <td class="calc-summary-col">
                <div class="summary-box">
                    <div class="summary-line">
                        <span class="summary-lbl">{{ $order->effective_tax_mode === 'inclusive' ? 'Subtotal (Incl. GST):' : 'Subtotal:' }}</span>
                        <span class="summary-num">{!! $currencySymbol !!}{{ number_format((float) convert_from_base($order->subtotal), 2) }}</span>
                    </div>

                    @if($order->discount > 0)
                        <div class="summary-line" style="color: #dc2626;">
                            <span class="summary-lbl" style="color: #dc2626;">Discount:</span>
                            <span class="summary-num" style="color: #dc2626;">-{!! $currencySymbol !!}{{ number_format((float) convert_from_base($order->discount), 2) }}</span>
                        </div>
                    @endif

                    <div class="summary-line" style="color: #475569;">
                        <span class="summary-lbl">{{ $order->effective_tax_mode === 'inclusive' ? 'Taxable Value (Excl. Tax):' : 'Taxable Amount:' }}</span>
                        <span class="summary-num">{!! $currencySymbol !!}{{ number_format((float) convert_from_base($order->taxable_amount), 2) }}</span>
                    </div>

                    @if($order->tax_type !== 'without_tax' && $order->tax > 0)
                        @if(($order->gst_type ?? 'cgst_sgst') === 'cgst_sgst')
                            <div class="summary-line">
                                <span class="summary-lbl">CGST {{ $order->effective_tax_mode === 'inclusive' ? '(Included)' : '' }}:</span>
                                <span class="summary-num">{{ ($order->effective_tax_mode === 'inclusive' ? '' : '+') }}{!! $currencySymbol !!}{{ number_format((float) convert_from_base($order->tax / 2), 2) }}</span>
                            </div>
                            <div class="summary-line">
                                <span class="summary-lbl">SGST {{ $order->effective_tax_mode === 'inclusive' ? '(Included)' : '' }}:</span>
                                <span class="summary-num">{{ ($order->effective_tax_mode === 'inclusive' ? '' : '+') }}{!! $currencySymbol !!}{{ number_format((float) convert_from_base($order->tax / 2), 2) }}</span>
                            </div>
                        @else
                            <div class="summary-line">
                                <span class="summary-lbl">IGST {{ $order->effective_tax_mode === 'inclusive' ? '(Included)' : '' }}:</span>
                                <span class="summary-num">{{ ($order->effective_tax_mode === 'inclusive' ? '' : '+') }}{!! $currencySymbol !!}{{ number_format((float) convert_from_base($order->tax), 2) }}</span>
                            </div>
                        @endif

                        <div class="summary-line">
                            <span class="summary-lbl">Total Tax {{ $order->effective_tax_mode === 'inclusive' ? '(Included)' : '' }}:</span>
                            <span class="summary-num">{!! $currencySymbol !!}{{ number_format((float) convert_from_base($order->tax), 2) }}</span>
                        </div>
                    @endif

                    @if($order->shipping_charges > 0)
                        <div class="summary-line">
                            <span class="summary-lbl">Shipping / Freight:</span>
                            <span class="summary-num">+{!! $currencySymbol !!}{{ number_format((float) convert_from_base($order->shipping_charges), 2) }}</span>
                        </div>
                    @endif

                    @if($order->adjustment != 0)
                        <div class="summary-line">
                            <span class="summary-lbl">Adjustment:</span>
                            <span class="summary-num">{{ $order->adjustment > 0 ? '+' : '' }}{!! $currencySymbol !!}{{ number_format((float) convert_from_base($order->adjustment), 2) }}</span>
                        </div>
                    @endif

                    <div class="total-payable-box">
                        <span class="total-payable-lbl">Total Payable:</span>
                        <span class="total-payable-num">{!! $currencySymbol !!}{{ number_format((float) convert_from_base($order->total_amount), 2) }}</span>
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <!-- 5. TERMS & CONDITIONS SECTION -->
    @if($order->terms_conditions)
        <div class="terms-section">
            <div class="section-label">Terms & Conditions:</div>
            <div class="terms-content">
                {!! $order->terms_conditions !!}
            </div>
        </div>
    @endif

    <!-- 6. SIGNATURE BLOCK -->
    <table class="signature-table">
        <tr>
            <td class="sig-note">
                For any queries regarding this Sales Order, fulfillment schedule or billing, please contact us at {{ tenant() ? tenant()->billing_email : 'sales@saaserp.com' }}.<br>
                Thank you for your business!
            </td>
            <td class="sig-space">
                <div class="sig-line">Authorized Signatory</div>
            </td>
        </tr>
    </table>

    <!-- 7. INLINE CANVAS SCRIPT FOR PAGE NUMBER (Page 1 of 2, Page 2 of 2) -->
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
