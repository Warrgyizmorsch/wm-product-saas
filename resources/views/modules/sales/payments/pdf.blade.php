<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>Payment Receipt - {{ $payment->payment_number }}</title>
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
            background-color: #0284c7;
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
            color: #0284c7;
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

        /* ── CUSTOMER & PAYMENT SUMMARY CARDS ────── */
        .info-boxes-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 12px 0;
            margin-left: -12px;
            margin-right: -12px;
            margin-bottom: 14px;
            page-break-inside: avoid;
        }
        .info-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 10px 12px;
            vertical-align: top;
            width: 50%;
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
            margin-bottom: 2px;
        }
        .client-sub {
            font-size: 9.5px;
            color: #64748b;
            margin-bottom: 4px;
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

        .summary-row {
            width: 100%;
            padding: 3px 0;
            border-bottom: 1px solid #e2e8f0;
            font-size: 10px;
        }
        .summary-row:last-child {
            border-bottom: none;
        }
        .summary-lbl {
            color: #64748b;
            display: inline-block;
            width: 48%;
        }
        .summary-val {
            color: #0f172a;
            font-weight: bold;
            display: inline-block;
            width: 48%;
            text-align: right;
        }

        /* ── ALLOCATIONS TABLE ───────────────────── */
        .section-header {
            font-size: 11px;
            font-weight: bold;
            color: #0f172a;
            margin-bottom: 6px;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 4px;
        }
        .alloc-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
            page-break-inside: auto;
            border: 1px solid #cbd5e1;
        }
        .alloc-table thead {
            display: table-header-group;
        }
        .alloc-table tr {
            page-break-inside: avoid;
            page-break-after: auto;
        }
        .alloc-table th {
            background-color: #f8fafc;
            color: #475569;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: 1px solid #cbd5e1;
            padding: 7px 8px;
        }
        .alloc-table td {
            padding: 7px 8px;
            border: 1px solid #e2e8f0;
            font-size: 10px;
            color: #1e293b;
            vertical-align: middle;
        }
        .alloc-table tfoot td {
            background-color: #f8fafc;
            font-weight: bold;
            border-top: 1.5px solid #cbd5e1;
            padding: 7px 8px;
        }

        .badge-pill {
            font-size: 8px;
            font-weight: bold;
            padding: 2px 6px;
            border-radius: 4px;
            display: inline-block;
            text-transform: uppercase;
        }
        .badge-invoice {
            background-color: #e0f2fe;
            color: #0369a1;
        }
        .badge-order {
            background-color: #fef3c7;
            color: #b45309;
        }

        /* ── NOTES BOX ───────────────────────────── */
        .notes-section {
            margin-top: 10px;
            padding-top: 8px;
            border-top: 1px solid #e2e8f0;
            page-break-inside: auto;
        }
        .notes-label {
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            margin-bottom: 4px;
        }
        .notes-content {
            font-size: 9px;
            color: #475569;
            line-height: 1.45;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 6px 8px;
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
        .sig-company {
            font-size: 9.5px;
            font-weight: bold;
            color: #0f172a;
            margin-bottom: 22px;
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

        $allocTotal = $payment->allocations->sum('allocated_amount');
        $unallocated = max(0, $payment->amount - $allocTotal);

        $firstAlloc = $payment->allocations->first();
        $billingAddr = $payment->customer?->address ?? $firstAlloc?->invoice?->salesOrder?->billing_address ?? $firstAlloc?->salesOrder?->billing_address;
    @endphp

    <!-- 0. PERSISTENT FOOTER ON EVERY PAGE (REFRENS STYLE) -->
    <footer class="doc-page-footer">
        <div class="footer-dashed-line"></div>
        <table class="footer-meta-table">
            <tr>
                <td style="text-align: left; width: 75%;">
                    Receipt #: <strong style="color: #0f172a;">{{ $payment->payment_number }}</strong> &nbsp;|&nbsp;
                    Date: {{ $payment->payment_date ? date('d-M-Y', strtotime($payment->payment_date)) : '—' }} &nbsp;|&nbsp;
                    Received From: <strong style="color: #0f172a;">{{ $payment->customer?->name ?: '—' }}</strong>
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
                <div class="company-avatar">
                    {{ strtoupper(substr(tenant() ? tenant()->name : 'D', 0, 1)) }}
                </div>
                <div class="company-info">
                    <div class="company-name">{{ tenant() ? tenant()->name : 'Demo Tenant' }}</div>
                    <div class="company-sub">Official Payment Receipt</div>
                </div>
                <div class="company-address">
                    H-1, Industrial Area, Sukher, Udaipur 313001, Rajasthan, India<br>
                    <strong>GSTIN:</strong> 08AAFCS1234E1Z0 &nbsp;|&nbsp; <strong>State Code:</strong> 08 (Rajasthan)<br>
                    <strong>Email:</strong> {{ tenant() ? tenant()->billing_email : 'billing@sasserp.com' }} &nbsp;|&nbsp; <strong>Phone:</strong> +91 294 2440230
                </div>
            </td>
            <td style="vertical-align: top; width: 42%; text-align: right;">
                <div class="doc-title">PAYMENT RECEIPT</div>
                <div class="doc-number"># {{ $payment->payment_number }}</div>
                
                <table class="meta-table">
                    <tr>
                        <td class="meta-label">Payment Date:</td>
                        <td class="meta-value">{{ date('d-M-Y', strtotime($payment->payment_date)) }}</td>
                    </tr>
                    <tr>
                        <td class="meta-label">Payment Method:</td>
                        <td class="meta-value">{{ $payment->payment_method ?: '—' }}</td>
                    </tr>
                    @if($payment->reference_no)
                        <tr>
                            <td class="meta-label">Bank / Ref No:</td>
                            <td class="meta-value">{{ $payment->reference_no }}</td>
                        </tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    <!-- 2. CUSTOMER & PAYMENT SUMMARY -->
    <table class="info-boxes-table">
        <tr>
            <!-- Left Box: Received From (Customer) -->
            <td class="info-box">
                <div class="box-title">RECEIVED FROM (CUSTOMER):</div>
                <div class="client-name">{{ $payment->customer?->name ?: '—' }}</div>
                <div class="client-sub">Valued Customer</div>

                @if($payment->customer?->email || $payment->customer?->phone)
                    <div class="client-contact">
                        @if($payment->customer?->email)
                            {{ $payment->customer->email }}
                        @endif
                        @if($payment->customer?->email && $payment->customer?->phone)
                            |
                        @endif
                        @if($payment->customer?->phone)
                            {{ $payment->customer->phone }}
                        @endif
                    </div>
                @endif

                @if ($billingAddr)
                    <div class="address-block">
                        <strong>Billing Address:</strong><br>{{ $billingAddr }}
                    </div>
                @endif
            </td>

            <!-- Right Box: Payment Summary & Status -->
            <td class="info-box">
                <div class="box-title">PAYMENT SUMMARY &amp; STATUS:</div>

                <div class="summary-row">
                    <span class="summary-lbl">Total Amount Received:</span>
                    <span class="summary-val" style="color: #0284c7; font-size: 11px;">{!! $currencySymbol !!}{{ number_format((float) convert_from_base($payment->amount), 2) }}</span>
                </div>

                <div class="summary-row">
                    <span class="summary-lbl">Allocated Amount:</span>
                    <span class="summary-val">{!! $currencySymbol !!}{{ number_format((float) convert_from_base($allocTotal), 2) }}</span>
                </div>

                @if($unallocated > 0)
                    <div class="summary-row">
                        <span class="summary-lbl" style="color: #b45309;">Unallocated (Advance):</span>
                        <span class="summary-val" style="color: #b45309;">{!! $currencySymbol !!}{{ number_format((float) convert_from_base($unallocated), 2) }}</span>
                    </div>
                @endif

                <div class="summary-row">
                    <span class="summary-lbl">Payment Method:</span>
                    <span class="summary-val">{{ $payment->payment_method ?: '—' }}</span>
                </div>

                @if($payment->reference_no)
                    <div class="summary-row">
                        <span class="summary-lbl">Bank Ref / Cheque No:</span>
                        <span class="summary-val">{{ $payment->reference_no }}</span>
                    </div>
                @endif

                <div class="summary-row">
                    <span class="summary-lbl">Payment Status:</span>
                    <span class="summary-val" style="color: {{ $payment->status === 'Confirmed' ? '#16a34a' : ($payment->status === 'Cancelled' ? '#dc2626' : '#64748b') }};">
                        {{ $payment->status }}
                    </span>
                </div>
            </td>
        </tr>
    </table>

    <!-- 3. INVOICE ALLOCATIONS / ADJUSTMENTS TABLE -->
    <div class="section-header">
        Invoice Allocations / Adjustments ({{ $payment->allocations->count() }} {{ \Illuminate\Support\Str::plural('Record', $payment->allocations->count()) }})
    </div>

    <table class="alloc-table">
        <thead>
            <tr>
                <th style="width: 35%; text-align: left;">Linked Document</th>
                <th style="width: 20%; text-align: left;">Type</th>
                <th style="width: 20%; text-align: left;">Document Date</th>
                <th style="width: 25%; text-align: right;">Allocated Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse($payment->allocations as $alloc)
                <tr>
                    <td style="font-weight: bold; color: #0284c7;">
                        @if($alloc->invoice_id)
                            {{ $alloc->invoice?->invoice_number }}
                        @elseif($alloc->sales_order_id)
                            {{ $alloc->salesOrder?->sales_order_number }}
                        @else
                            Unallocated
                        @endif
                    </td>
                    <td>
                        @if($alloc->invoice_id)
                            <span class="badge-pill badge-invoice">Customer Invoice</span>
                        @elseif($alloc->sales_order_id)
                            <span class="badge-pill badge-order">Sales Order Advance</span>
                        @else
                            —
                        @endif
                    </td>
                    <td style="color: #475569;">
                        {{ $alloc->invoice?->invoice_date ? date('d-M-Y', strtotime($alloc->invoice->invoice_date)) : ($alloc->salesOrder?->order_date ? date('d-M-Y', strtotime($alloc->salesOrder->order_date)) : '—') }}
                    </td>
                    <td style="text-align: right; font-weight: bold; color: #0f172a;">
                        {!! $currencySymbol !!}{{ number_format((float) convert_from_base($alloc->allocated_amount), 2) }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" style="text-align: center; color: #94a3b8; padding: 18px;">
                        No Allocations Linked. This payment is available as advance credit against future invoices.
                    </td>
                </tr>
            @endforelse
        </tbody>
        @if($payment->allocations->count() > 0)
            <tfoot>
                <tr>
                    <td colspan="3" style="text-align: right; color: #64748b;">Total Allocated Amount:</td>
                    <td style="text-align: right; color: #0284c7; font-size: 11px;">
                        {!! $currencySymbol !!}{{ number_format((float) convert_from_base($allocTotal), 2) }}
                    </td>
                </tr>
            </tfoot>
        @endif
    </table>

    <!-- 4. NOTES / REMARKS (IF ANY) -->
    @if($payment->notes)
        <div class="notes-section">
            <div class="notes-label">Receipt Notes / Remarks:</div>
            <div class="notes-content">{!! nl2br(e($payment->notes)) !!}</div>
        </div>
    @endif

    <!-- 5. SIGNATURE SECTION -->
    <table class="signature-table">
        <tr>
            <td class="sig-note">
                This is a computer generated payment receipt and serves as official acknowledgement of payment received.<br>
                Thank you for your business!
            </td>
            <td class="sig-space">
                <div class="sig-company">For {{ tenant() ? tenant()->name : 'Demo Tenant' }}</div>
                <div class="sig-line">Authorized Signatory</div>
            </td>
        </tr>
    </table>

    <!-- 6. INLINE CANVAS SCRIPT FOR PAGE NUMBER -->
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
