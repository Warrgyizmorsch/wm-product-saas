<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>Sales Quotation - {{ $quotation->quotation_number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #1e293b;
            background: #ffffff;
            padding: 24px 30px;
        }

        /* ── HEADER ─────────────────────────────────── */
        .header-wrap {
            width: 100%;
            padding-bottom: 14px;
            border-bottom: 1px solid #e2e8f0;
            margin-bottom: 16px;
        }
        .company-avatar {
            display: inline-block;
            width: 42px;
            height: 42px;
            background-color: #1e40af;
            color: #ffffff;
            font-size: 20px;
            font-weight: bold;
            text-align: center;
            line-height: 42px;
            border-radius: 6px;
            vertical-align: middle;
        }
        .company-info {
            display: inline-block;
            vertical-align: middle;
            padding-left: 10px;
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
            line-height: 1.5;
        }
        .doc-title {
            font-size: 18px;
            font-weight: bold;
            color: #1e40af;
            text-align: right;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .doc-number {
            font-size: 12px;
            font-weight: bold;
            color: #1e293b;
            text-align: right;
            margin-top: 2px;
        }
        .doc-meta {
            font-size: 11px;
            color: #64748b;
            text-align: right;
            margin-top: 8px;
        }
        .doc-meta table {
            margin-left: auto;
        }
        .doc-meta td {
            padding: 1.5px 0;
            font-size: 11px;
        }
        .meta-label {
            color: #64748b;
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
            margin-bottom: 6px;
        }
        .addr-name {
            font-size: 12.5px;
            font-weight: bold;
            color: #0f172a;
            margin-bottom: 3px;
        }
        .addr-line {
            font-size: 10.5px;
            color: #475569;
            margin-bottom: 2px;
            line-height: 1.4;
        }

        /* ── ITEMS TABLE ────────────────────────────── */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 14px;
            margin-bottom: 14px;
        }
        .items-table th {
            background-color: #f8fafc;
            color: #475569;
            font-size: 9.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-top: 1px solid #cbd5e1;
            border-bottom: 2px solid #94a3b8;
            padding: 7px 8px;
        }
        .items-table td {
            padding: 7px 8px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 10.5px;
            color: #1e293b;
            vertical-align: top;
        }
        .items-table tr:nth-child(even) td {
            background-color: #fafafa;
        }
        .item-name {
            font-weight: bold;
            color: #0f172a;
            font-size: 11px;
        }
        .item-desc {
            font-size: 9.5px;
            color: #64748b;
            margin-top: 2px;
        }

        /* ── SUMMARY SECTION ─────────────────────────── */
        .summary-wrap {
            width: 100%;
            margin-top: 10px;
        }
        .terms-col {
            width: 55%;
            vertical-align: top;
        }
        .summary-col {
            width: 42%;
            vertical-align: top;
        }
        .terms-title {
            font-size: 9.5px;
            font-weight: bold;
            text-transform: uppercase;
            color: #475569;
            margin-bottom: 4px;
            letter-spacing: 0.5px;
        }
        .terms-body {
            font-size: 9.5px;
            color: #475569;
            line-height: 1.4;
            padding-right: 15px;
        }
        .terms-body p {
            margin-bottom: 3px;
        }

        .summary-box {
            border: 1px solid #cbd5e1;
            background-color: #f8fafc;
            border-radius: 4px;
            padding: 10px 12px;
        }
        .summary-table {
            width: 100%;
            border-collapse: collapse;
        }
        .summary-table td {
            padding: 4px 0;
            font-size: 10.5px;
        }
        .summary-label {
            color: #475569;
        }
        .summary-val {
            text-align: right;
            font-weight: bold;
            color: #0f172a;
        }
        .grand-total-row td {
            border-top: 1.5px solid #cbd5e1;
            padding-top: 6px;
            padding-bottom: 2px;
            font-size: 11.5px;
        }
        .grand-total-label {
            font-weight: bold;
            color: #0f172a;
        }
        .grand-total-val {
            text-align: right;
            font-weight: bold;
            color: #1e40af;
        }

        /* ── FOOTER SIGNATURE ────────────────────────── */
        .footer-wrap {
            width: 100%;
            margin-top: 24px;
            padding-top: 12px;
            border-top: 1px solid #e2e8f0;
        }
        .footer-note {
            font-size: 9.5px;
            color: #64748b;
            width: 60%;
            vertical-align: bottom;
        }
        .sig-col {
            width: 38%;
            text-align: right;
            vertical-align: bottom;
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
            color: #64748b;
        }
    </style>
</head>
<body>

    <!-- 1. HEADER SECTION -->
    <div class="header-wrap">
        <table style="width: 100%; border-collapse: collapse;">
            <tr>
                <td style="vertical-align: top; width: 55%;">
                    <div class="company-avatar">
                        {{ strtoupper(substr(tenant() ? tenant()->name : 'E', 0, 1)) }}
                    </div>
                    <div class="company-info">
                        <div class="company-name">{{ tenant() ? tenant()->name : 'SaaS ERP Workspace' }}</div>
                        <div class="company-sub">Official Corporate Sales Unit</div>
                    </div>
                    <div class="company-address">
                        H-1, Industrial Area, Sukher, Udaipur 313001, Rajasthan, India<br>
                        <strong>GSTIN:</strong> 08AAFCS1234E1Z0 &nbsp;|&nbsp; <strong>State:</strong> Rajasthan (08)<br>
                        <strong>Email:</strong> {{ tenant() ? tenant()->billing_email : 'sales@saaserp.com' }}
                    </div>
                </td>
                <td style="vertical-align: top; width: 45%; text-align: right;">
                    <div class="doc-title">SALES QUOTATION</div>
                    <div class="doc-number"># {{ $quotation->quotation_number }}</div>
                    
                    <div class="doc-meta">
                        <table>
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
                            <tr>
                                <td class="meta-label">Status:</td>
                                <td class="meta-value" style="color: #1e40af;">{{ $quotation->status }}</td>
                            </tr>
                        </table>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <!-- 2. ADDRESS / PREPARED FOR BOXES -->
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 14px;">
        <tr>
            <td class="addr-box" style="margin-right: 4%;">
                <div class="addr-label">Prepared For (Client):</div>
                <div class="addr-name">{{ $quotation->prepared_for_name }}</div>
                @if($quotation->prepared_for_email !== '—')
                    <div class="addr-line"><strong>Email:</strong> {{ $quotation->prepared_for_email }}</div>
                @endif
                @if($quotation->prepared_for_phone !== '—')
                    <div class="addr-line"><strong>Phone:</strong> {{ $quotation->prepared_for_phone }}</div>
                @endif
                @if($quotation->prepared_for_address)
                    <div class="addr-line" style="margin-top: 4px;"><strong>Address:</strong> {{ $quotation->prepared_for_address }}</div>
                @endif
            </td>
            <td style="width: 4%;"></td>
            <td class="addr-box">
                <div class="addr-label">References & Revision Info:</div>
                @if ($quotation->crmDeal)
                    <div class="addr-line"><strong>Deal Ref:</strong> {{ $quotation->crmDeal->deal_number }} ({{ $quotation->crmDeal->title }})</div>
                @elseif ($quotation->lead)
                    <div class="addr-line"><strong>Lead Ref:</strong> {{ $quotation->lead->title ?: $quotation->lead->name }}</div>
                @endif
                <div class="addr-line"><strong>Revision Number:</strong> Revision {{ $quotation->revision_number }}</div>
                <div class="addr-line"><strong>Quotation Status:</strong> {{ $quotation->status }}</div>
            </td>
        </tr>
    </table>

    <!-- 3. LINE ITEMS TABLE -->
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
                        <div class="item-name">{{ $item->item_name }}</div>
                        @if($item->description)
                            <div class="item-desc">{{ $item->description }}</div>
                        @endif
                    </td>
                    <td style="text-align: right;">{{ $item->quantity }}</td>
                    <td style="text-align: right;">₹{{ number_format($item->unit_price, 2) }}</td>
                    <td style="text-align: right;">{{ number_format($item->tax_rate, 2) }}%</td>
                    <td style="text-align: right; font-weight: bold;">₹{{ number_format($item->total_price ?: ($item->amount ?: ($item->quantity * $item->unit_price)), 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <!-- 4. CALCULATIONS & TERMS SECTION -->
    <table class="summary-wrap">
        <tr>
            <!-- Left Column: Terms & Notes -->
            <td class="terms-col">
                @if($quotation->terms_conditions)
                    <div class="terms-title">Terms & Conditions:</div>
                    <div class="terms-body">{!! $quotation->terms_conditions !!}</div>
                @endif

                @if($quotation->notes)
                    <div class="terms-title" style="margin-top: 8px;">Internal Notes:</div>
                    <div class="terms-body" style="font-style: italic;">{{ $quotation->notes }}</div>
                @endif
            </td>

            <!-- Right Column: Summary Totals Box -->
            <td class="summary-col">
                <div class="summary-box">
                    <table class="summary-table">
                        <tr>
                            <td class="summary-label">Subtotal:</td>
                            <td class="summary-val">₹{{ number_format($quotation->subtotal, 2) }}</td>
                        </tr>
                        <tr>
                            <td class="summary-label">Tax Amount (GST):</td>
                            <td class="summary-val">₹{{ number_format($quotation->tax, 2) }}</td>
                        </tr>
                        @if($quotation->discount > 0)
                            <tr style="color: #b91c1c;">
                                <td class="summary-label" style="color: #b91c1c;">Discount:</td>
                                <td class="summary-val" style="color: #b91c1c;">-₹{{ number_format($quotation->discount, 2) }}</td>
                            </tr>
                        @endif
                        <tr class="grand-total-row">
                            <td class="grand-total-label">Total Payable:</td>
                            <td class="grand-total-val">₹{{ number_format($quotation->total_amount, 2) }}</td>
                        </tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    <!-- 5. SIGNATURE & FOOTER -->
    <table class="footer-wrap">
        <tr>
            <td class="footer-note">
                This sales quotation is valid until {{ $quotation->expiry_date ? date('d-M-Y', strtotime($quotation->expiry_date)) : 'the expiry date' }}.<br>
                For any queries, please contact sales office at {{ tenant() ? tenant()->billing_email : 'sales@saaserp.com' }}.
            </td>
            <td class="sig-col">
                <div class="sig-line">Authorized Signature</div>
            </td>
        </tr>
    </table>

</body>
</html>
