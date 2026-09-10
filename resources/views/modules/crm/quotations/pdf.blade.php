<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>Sales Quotation - {{ $quotation->quotation_number }}</title>
    <style>
        @page {
            margin: 0;
            size: A4 portrait;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 11px;
            color: #1e293b;
            background: #ffffff;
            padding: 30px 35px;
            position: relative;
        }

        /* ── CORNER RIBBON BADGE ───────────────────── */
        .corner-ribbon-container {
            position: absolute;
            top: 0;
            right: 0;
            width: 90px;
            height: 90px;
            overflow: hidden;
            z-index: 10;
        }
        .corner-ribbon {
            position: absolute;
            top: 18px;
            right: -24px;
            width: 115px;
            transform: rotate(45deg);
            -webkit-transform: rotate(45deg);
            text-align: center;
            font-size: 8.5px;
            font-weight: bold;
            letter-spacing: 1px;
            text-transform: uppercase;
            padding: 4px 0;
            color: #ffffff;
        }

        .ribbon-accepted, .ribbon-approved {
            background-color: #16a34a;
        }
        .ribbon-sent, .ribbon-quotation-sent {
            background-color: #2563eb;
        }
        .ribbon-draft {
            background-color: #64748b;
        }
        .ribbon-rejected, .ribbon-declined {
            background-color: #dc2626;
        }
        .ribbon-rework {
            background-color: #d97706;
        }

        /* ── HEADER SECTION ──────────────────────── */
        .header-table {
            width: 100%;
            border-collapse: collapse;
            padding-bottom: 14px;
            border-bottom: 1px solid #e2e8f0;
            margin-bottom: 18px;
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
            font-size: 10px;
            color: #475569;
            margin-top: 6px;
            line-height: 1.4;
        }

        .doc-title {
            font-size: 19px;
            font-weight: 900;
            color: #1e40af;
            text-align: right;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .doc-number {
            font-size: 12.5px;
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
            padding: 1.5px 0;
            font-size: 10.5px;
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

        /* ── PREPARED FOR & REFERENCES ───────────── */
        .info-boxes-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 12px 0;
            margin-left: -12px;
            margin-right: -12px;
            margin-bottom: 18px;
        }
        .info-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 12px 14px;
            vertical-align: top;
            width: 50%;
        }
        .box-title {
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            margin-bottom: 6px;
        }
        .client-name {
            font-size: 13px;
            font-weight: bold;
            color: #0f172a;
            margin-bottom: 3px;
        }
        .client-contact {
            font-size: 10.5px;
            color: #475569;
            margin-bottom: 6px;
        }
        .address-block {
            font-size: 10px;
            color: #475569;
            border-top: 1px solid #e2e8f0;
            padding-top: 6px;
            margin-top: 4px;
            line-height: 1.35;
        }

        .ref-row {
            width: 100%;
            padding: 3px 0;
            border-bottom: 1px solid #e2e8f0;
            font-size: 10.5px;
        }
        .ref-row:last-child {
            border-bottom: none;
        }
        .ref-label {
            color: #64748b;
            display: inline-block;
            width: 45%;
        }
        .ref-val {
            color: #0f172a;
            font-weight: bold;
            display: inline-block;
            width: 50%;
            text-align: right;
        }

        /* ── ITEMS TABLE ─────────────────────────── */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
        }
        .items-table th {
            background-color: #f8fafc;
            color: #475569;
            font-size: 9.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-top: 1px solid #e2e8f0;
            border-bottom: 2px solid #cbd5e1;
            padding: 8px 10px;
        }
        .items-table td {
            padding: 9px 10px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 10.5px;
            color: #1e293b;
            vertical-align: top;
        }
        .item-title {
            font-weight: bold;
            color: #0f172a;
            font-size: 11px;
        }
        .item-desc {
            font-size: 9px;
            color: #64748b;
            margin-top: 2px;
        }

        /* ── SUMMARY SECTION ─────────────────────── */
        .summary-wrapper {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #e2e8f0;
        }
        .terms-column {
            width: 55%;
            vertical-align: top;
            padding-right: 15px;
        }
        .summary-column {
            width: 45%;
            vertical-align: top;
        }
        .section-label {
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            margin-bottom: 4px;
        }
        .terms-content {
            font-size: 10px;
            color: #475569;
            line-height: 1.4;
        }
        .terms-content p {
            margin-bottom: 4px;
        }

        .summary-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 10px 12px;
        }
        .summary-line {
            width: 100%;
            padding: 3px 0;
            font-size: 10.5px;
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
            padding: 6px 8px;
            margin-top: 6px;
        }
        .total-payable-lbl {
            font-weight: bold;
            color: #0f172a;
            font-size: 11.5px;
            display: inline-block;
            width: 48%;
        }
        .total-payable-num {
            font-weight: bold;
            color: #1e40af;
            font-size: 12.5px;
            display: inline-block;
            width: 50%;
            text-align: right;
        }

        /* ── FOOTER SIGNATURE ────────────────────── */
        .footer-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 30px;
            padding-top: 12px;
            border-top: 1px solid #e2e8f0;
        }
        .footer-text {
            font-size: 9.5px;
            color: #64748b;
            vertical-align: bottom;
            width: 60%;
            line-height: 1.35;
        }
        .sig-block {
            text-align: right;
            vertical-align: bottom;
            width: 40%;
        }
        .sig-line {
            display: inline-block;
            border-top: 1px solid #cbd5e1;
            width: 170px;
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

    <!-- Status Corner Ribbon Tag -->
    @php
        $ribbonClass = match($quotation->status) {
            'Accepted', 'Approved' => 'ribbon-accepted',
            'Sent', 'Quotation Sent' => 'ribbon-sent',
            'Rejected', 'Declined' => 'ribbon-rejected',
            'Rework', 'Quotation Rework' => 'ribbon-rework',
            default => 'ribbon-draft',
        };
    @endphp
    <div class="corner-ribbon-container">
        <div class="corner-ribbon {{ $ribbonClass }}">
            {{ $quotation->status }}
        </div>
    </div>

    <!-- 1. HEADER SECTION -->
    <table class="header-table">
        <tr>
            <td style="vertical-align: top; width: 58%;">
                <div class="company-avatar">
                    {{ strtoupper(substr(tenant() ? tenant()->name : 'S', 0, 1)) }}
                </div>
                <div class="company-info">
                    <div class="company-name">{{ tenant() ? tenant()->name : 'Demo Tenant' }}</div>
                    <div class="company-sub">Official Corporate Sales Unit</div>
                </div>
                <div class="company-address">
                    H-1, Industrial Area, Sukher, Udaipur 313001, Rajasthan, India<br>
                    <strong>GSTIN:</strong> 08AAFCS1234E1Z0 &nbsp;|&nbsp; <strong>State Code:</strong> 08 (Rajasthan)<br>
                    <strong>Email:</strong> {{ tenant() ? tenant()->billing_email : 'sales@saaserp.com' }} &nbsp;|&nbsp; <strong>Phone:</strong> +91 294 2440230
                </div>
            </td>
            <td style="vertical-align: top; width: 42%; text-align: right;">
                <div class="doc-title">SALES QUOTATION</div>
                <div class="doc-number"># {{ $quotation->quotation_number }}</div>
                
                <table class="meta-table">
                    <tr>
                        <td class="meta-label">Quotation Date:</td>
                        <td class="meta-value">{{ $quotation->quotation_date ? date('d-M-Y', strtotime($quotation->quotation_date)) : '—' }}</td>
                    </tr>
                    <tr>
                        <td class="meta-label">Valid Until:</td>
                        <td class="meta-value">{{ $quotation->expiry_date ? date('d-M-Y', strtotime($quotation->expiry_date)) : '—' }}</td>
                    </tr>
                    @if($quotation->salesPerson)
                        <tr>
                            <td class="meta-label">Sales Rep:</td>
                            <td class="meta-value">{{ $quotation->salesPerson->name }}</td>
                        </tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    <!-- 2. PREPARED FOR & QUOTATION REFERENCES -->
    <table class="info-boxes-table">
        <tr>
            <!-- Left Box: Prepared For -->
            <td class="info-box">
                <div class="box-title">PREPARED FOR:</div>
                <div class="client-name">{{ $quotation->prepared_for_name }}</div>
                @if($quotation->prepared_for_email !== '—' || $quotation->prepared_for_phone !== '—')
                    <div class="client-contact">
                        @if($quotation->prepared_for_email !== '—')
                            {{ $quotation->prepared_for_email }}
                        @endif
                        @if($quotation->prepared_for_email !== '—' && $quotation->prepared_for_phone !== '—')
                            |
                        @endif
                        @if($quotation->prepared_for_phone !== '—')
                            {{ $quotation->prepared_for_phone }}
                        @endif
                    </div>
                @endif
                @if ($quotation->prepared_for_address)
                    <div class="address-block">
                        <strong>Billing Address:</strong><br>{{ $quotation->prepared_for_address }}
                    </div>
                @endif
            </td>

            <!-- Right Box: Quotation References -->
            <td class="info-box">
                <div class="box-title">QUOTATION REFERENCES:</div>
                
                @if ($quotation->crmDeal)
                    <div class="ref-row">
                        <span class="ref-label">CRM Deal Ref:</span>
                        <span class="ref-val" style="color: #2563eb;">{{ $quotation->crmDeal->deal_number }}</span>
                    </div>
                @elseif ($quotation->lead)
                    <div class="ref-row">
                        <span class="ref-label">Lead Ref:</span>
                        <span class="ref-val" style="color: #2563eb;">{{ $quotation->lead->title ?: $quotation->lead->name }}</span>
                    </div>
                @endif

                <div class="ref-row">
                    <span class="ref-label">Revision Count:</span>
                    <span class="ref-val">Revision {{ $quotation->revision_number }}</span>
                </div>

                <div class="ref-row">
                    <span class="ref-label">Status:</span>
                    <span class="ref-val">{{ $quotation->status }}</span>
                </div>
            </td>
        </tr>
    </table>

    <!-- 3. ORDER LINES TABLE -->
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 5%; text-align: center;">#</th>
                <th style="width: 45%;">Item & Description</th>
                <th style="width: 12%; text-align: right;">Qty</th>
                <th style="width: 13%; text-align: right;">Rate (₹)</th>
                <th style="width: 10%; text-align: right;">Tax %</th>
                <th style="width: 15%; text-align: right;">Amount (₹)</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($quotation->items as $idx => $item)
                <tr>
                    <td style="text-align: center; color: #64748b;">{{ $idx + 1 }}</td>
                    <td>
                        <div class="item-title">{{ $item->item_name }}</div>
                        @if($item->description)
                            <div class="item-desc">{{ $item->description }}</div>
                        @endif
                    </td>
                    <td style="text-align: right;">{{ $item->quantity }}</td>
                    <td style="text-align: right;">₹{{ number_format($item->unit_price, 2) }}</td>
                    <td style="text-align: right;">{{ number_format($item->tax_rate, 2) }}%</td>
                    <td style="text-align: right; font-weight: bold; color: #0f172a;">₹{{ number_format($item->total_price ?: ($item->amount ?: ($item->quantity * $item->unit_price)), 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <!-- 4. CALCULATIONS & TERMS SECTION -->
    <table class="summary-wrapper">
        <tr>
            <!-- Left Column: Terms & Conditions and Internal Notes -->
            <td class="terms-column">
                @if($quotation->terms_conditions)
                    <div class="section-label">Terms & Conditions:</div>
                    <div class="terms-content">{!! $quotation->terms_conditions !!}</div>
                @endif

                @if($quotation->notes)
                    <div class="section-label" style="margin-top: 8px;">Internal Notes:</div>
                    <div class="terms-content" style="font-style: italic;">{{ $quotation->notes }}</div>
                @endif
            </td>

            <!-- Right Column: Totals Summary -->
            <td class="summary-column">
                <div class="summary-box">
                    <div class="summary-line">
                        <span class="summary-lbl">Subtotal:</span>
                        <span class="summary-num">₹{{ number_format($quotation->subtotal, 2) }}</span>
                    </div>
                    <div class="summary-line">
                        <span class="summary-lbl">Tax Amount (GST):</span>
                        <span class="summary-num">₹{{ number_format($quotation->tax, 2) }}</span>
                    </div>
                    @if($quotation->discount > 0)
                        <div class="summary-line" style="color: #dc2626;">
                            <span class="summary-lbl" style="color: #dc2626;">Discount:</span>
                            <span class="summary-num" style="color: #dc2626;">-₹{{ number_format($quotation->discount, 2) }}</span>
                        </div>
                    @endif
                    <div class="total-payable-box">
                        <span class="total-payable-lbl">Total Payable:</span>
                        <span class="total-payable-num">₹{{ number_format($quotation->total_amount, 2) }}</span>
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <!-- 5. SIGNATURE FOOTER BLOCK -->
    <table class="footer-table">
        <tr>
            <td class="footer-text">
                This sales quotation is valid until {{ $quotation->expiry_date ? date('d-M-Y', strtotime($quotation->expiry_date)) : 'the expiry date' }}.<br>
                For any queries, please contact our support at {{ tenant() ? tenant()->billing_email : 'sales@saaserp.com' }}.
            </td>
            <td class="sig-block">
                <div class="sig-line">Authorized Signature</div>
            </td>
        </tr>
    </table>

</body>
</html>
