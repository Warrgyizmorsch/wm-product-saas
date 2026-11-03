<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>Purchase Return Debit Note - {{ $return->return_number }}</title>
    <style>
        @page {
            margin-top: 16mm;
            margin-bottom: 20mm;
            margin-left: 14mm;
            margin-right: 14mm;
            size: A4 portrait;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 9.5px;
            color: #1e293b;
            background: #ffffff;
            margin: 0;
            padding: 0;
            line-height: 1.4;
        }

        /* ── PERSISTENT FOOTER ON EVERY PAGE (REFRENS STYLE) ── */
        footer.doc-page-footer {
            position: fixed;
            bottom: -15mm;
            left: 0;
            right: 0;
            height: 13mm;
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
            padding-bottom: 10px;
            border-bottom: 1.5px solid #cbd5e1;
            margin-bottom: 12px;
            page-break-inside: avoid;
        }
        .company-avatar {
            display: inline-block;
            width: 38px;
            height: 38px;
            background-color: #0284c7;
            color: #ffffff;
            font-size: 17px;
            font-weight: bold;
            text-align: center;
            line-height: 38px;
            border-radius: 6px;
            vertical-align: middle;
        }
        .company-info {
            display: inline-block;
            vertical-align: middle;
            padding-left: 8px;
        }
        .company-name {
            font-size: 14px;
            font-weight: bold;
            color: #0f172a;
        }
        .company-sub {
            font-size: 9px;
            color: #64748b;
        }
        .company-address {
            font-size: 9px;
            color: #475569;
            margin-top: 4px;
            line-height: 1.35;
        }

        .doc-title {
            font-size: 16px;
            font-weight: 900;
            color: #dc2626;
            text-align: right;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .doc-number {
            font-size: 11.5px;
            font-weight: bold;
            color: #1e293b;
            text-align: right;
            margin-top: 2px;
        }
        .meta-table {
            margin-left: auto;
            margin-top: 5px;
            border-collapse: collapse;
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

        /* ── TWO COLUMN INFO BOXES ───────────────── */
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
            padding: 9px 11px;
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
            margin-bottom: 6px;
        }
        .client-avatar {
            display: inline-block;
            width: 28px;
            height: 28px;
            background-color: #0284c7;
            color: #ffffff;
            font-size: 13px;
            font-weight: bold;
            text-align: center;
            line-height: 28px;
            border-radius: 4px;
            vertical-align: middle;
        }
        .client-name {
            font-size: 12px;
            font-weight: bold;
            color: #0f172a;
            vertical-align: middle;
            display: inline-block;
            padding-left: 6px;
        }
        .client-contact {
            font-size: 9.5px;
            color: #475569;
            margin-top: 5px;
            line-height: 1.4;
        }

        .summary-row {
            width: 100%;
            padding: 2.5px 0;
            border-bottom: 1px solid #e2e8f0;
            font-size: 9.5px;
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

        /* ── ITEMS TABLE ─────────────────────────── */
        .section-header {
            font-size: 10.5px;
            font-weight: bold;
            color: #0f172a;
            margin-bottom: 5px;
            padding-bottom: 3px;
            border-bottom: 1.5px solid #cbd5e1;
            page-break-inside: avoid;
        }
        .alloc-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
            page-break-inside: auto;
            border: 1px solid #cbd5e1;
        }
        .alloc-table thead {
            display: table-header-group;
        }
        .alloc-table tr {
            page-break-inside: avoid;
        }
        .alloc-table th {
            background-color: #f8fafc;
            color: #475569;
            font-size: 8.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: 1px solid #cbd5e1;
            padding: 6px 7px;
            text-align: left;
        }
        .alloc-table td {
            padding: 6px 7px;
            border: 1px solid #e2e8f0;
            font-size: 9.5px;
            color: #1e293b;
            vertical-align: middle;
        }
        .alloc-table tfoot td {
            background-color: #f8fafc;
            font-weight: bold;
            border-top: 1.5px solid #cbd5e1;
            padding: 6px 7px;
        }

        /* ── NOTES & SIGNATURE BLOCK ─────────────── */
        .bottom-section {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            page-break-inside: avoid;
        }
        .notes-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 8px 10px;
            font-size: 9px;
            color: #475569;
            line-height: 1.4;
        }
        .signature-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
            page-break-inside: avoid;
        }
        .sig-line {
            border-top: 1px solid #94a3b8;
            padding-top: 4px;
            font-size: 9px;
            font-weight: bold;
            color: #334155;
            text-align: right;
            width: 170px;
            margin-left: auto;
        }
    </style>
</head>
<body>

    @php
        $buyerOrg = billing_organization_details($return);
        $displayRefundTotal = $return->total_refund_amount > 0
            ? $return->total_refund_amount
            : ($return->total_amount > 0 ? $return->total_amount : $return->items->sum(fn($i) => (float)$i->quantity * (float)$i->unit_price));
    @endphp

    <!-- ── 1. PERSISTENT FOOTER ON EVERY PAGE (REFRENS STYLE) ── -->
    <footer class="doc-page-footer">
        <div class="footer-dashed-line"></div>
        <table class="footer-meta-table">
            <tr>
                <td style="width: 50%; text-align: left;">
                    {{ $buyerOrg['name'] ?? 'ERP' }} &bull; Purchase Debit Note # {{ $return->return_number }}
                </td>
                <td style="width: 50%; text-align: right;">
                    Generated on {{ date('d-M-Y H:i') }} &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
                </td>
            </tr>
        </table>
    </footer>

    <!-- ── 2. HEADER SECTION ── -->
    <table class="header-table">
        <tr>
            <!-- Left: Company Details -->
            <td style="width: 58%; vertical-align: top;">
                <div>
                    <div class="company-avatar">{{ strtoupper(substr($buyerOrg['name'] ?? 'W', 0, 1)) }}</div>
                    <div class="company-info">
                        <div class="company-name">{{ $buyerOrg['name'] ?? 'Warrgyizmorsch Pvt Ltd' }}</div>
                        <div class="company-sub">Official Purchase Debit Note / Return Voucher</div>
                    </div>
                </div>
                <div class="company-address">
                    @if(!empty($buyerOrg['address']))
                        <div>{{ $buyerOrg['address'] }}</div>
                    @endif
                    <div>
                        @if(!empty($buyerOrg['gstin']))
                            <strong>GSTIN:</strong> {{ $buyerOrg['gstin'] }}
                        @endif
                        @if(!empty($buyerOrg['state_code']) || !empty($buyerOrg['state_name']))
                            @if(!empty($buyerOrg['gstin'])) &nbsp;|&nbsp; @endif
                            <strong>State Code:</strong> {{ $buyerOrg['state_code'] ?? '—' }} ({{ $buyerOrg['state_name'] ?? '—' }})
                        @endif
                    </div>
                    <div>
                        @if(!empty($buyerOrg['email']))
                            <strong>Email:</strong> {{ $buyerOrg['email'] }}
                        @endif
                        @if(!empty($buyerOrg['phone']))
                            @if(!empty($buyerOrg['email'])) &nbsp;|&nbsp; @endif
                            <strong>Phone:</strong> {{ $buyerOrg['phone'] }}
                        @endif
                    </div>
                </div>
            </td>

            <!-- Right: Return Note Title & Meta -->
            <td style="width: 42%; vertical-align: top; text-align: right;">
                <div class="doc-title">PURCHASE DEBIT NOTE</div>
                <div class="doc-number"># {{ $return->return_number }}</div>

                <table class="meta-table">
                    <tr>
                        <td class="meta-label">Return Date:</td>
                        <td class="meta-value">{{ $return->return_date ? date('d-M-Y', strtotime($return->return_date)) : '—' }}</td>
                    </tr>
                    <tr>
                        <td class="meta-label">Status:</td>
                        <td class="meta-value" style="color: {{ in_array($return->status, ['Completed', 'Approved']) ? '#16a34a' : '#d97706' }};">
                            {{ $return->status ?: 'Pending' }}
                        </td>
                    </tr>
                    @if($return->purchaseOrder)
                        <tr>
                            <td class="meta-label">Linked PO:</td>
                            <td class="meta-value">{{ $return->purchaseOrder->purchase_order_number ?? $return->purchaseOrder->order_number }}</td>
                        </tr>
                    @endif
                    @if($return->goodsReceiptNote)
                        <tr>
                            <td class="meta-label">Linked GRN:</td>
                            <td class="meta-value">{{ $return->goodsReceiptNote->grn_number }}</td>
                        </tr>
                    @endif
                    @if($return->vendorBill)
                        <tr>
                            <td class="meta-label">Linked Bill:</td>
                            <td class="meta-value">{{ $return->vendorBill->bill_number }}</td>
                        </tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    <!-- ── 3. TWO COLUMN INFO BOXES ── -->
    <table class="info-boxes-table">
        <tr>
            <!-- Left: Supplier / Vendor Details -->
            <td class="info-box">
                <div class="box-title">Return To (Supplier / Vendor):</div>
                <div style="margin-bottom: 5px;">
                    <div class="client-avatar">{{ strtoupper(substr($return->vendor?->name ?: 'V', 0, 1)) }}</div>
                    <div class="client-name">{{ $return->vendor?->name ?: 'Vendor Supplier' }}</div>
                </div>
                <div class="client-contact">
                    <div><strong>Company:</strong> {{ $return->vendor?->company_name ?: ($return->vendor?->name ?: 'Valued Supplier') }}</div>
                    <div><strong>Email:</strong> {{ $return->vendor?->email ?: '—' }} &nbsp;|&nbsp; <strong>Phone:</strong> {{ $return->vendor?->phone ?: '—' }}</div>
                    @if($return->vendor?->gstin)
                        <div><strong>GSTIN:</strong> {{ $return->vendor->gstin }}</div>
                    @endif
                    @if($return->vendor?->address)
                        <div style="margin-top: 3px; border-top: 1px solid #e2e8f0; padding-top: 3px; font-size: 8.5px; color: #64748b;">
                            {{ $return->vendor->address }}
                        </div>
                    @endif
                </div>
            </td>

            <td class="info-box-gap"></td>

            <!-- Right: Return Summary -->
            <td class="info-box">
                <div class="box-title">Debit Note Summary:</div>
                <div class="summary-row">
                    <span class="summary-lbl">Total Refund Value:</span>
                    <span class="summary-val" style="color: #dc2626; font-size: 11.5px;">{{ format_currency($displayRefundTotal) }}</span>
                </div>
                <div class="summary-row">
                    <span class="summary-lbl">Total Returned Qty:</span>
                    <span class="summary-val">{{ (float)$return->items->sum('quantity') }} Units</span>
                </div>
                <div class="summary-row">
                    <span class="summary-lbl">Line Items:</span>
                    <span class="summary-val">{{ $return->items->count() }} Items</span>
                </div>
                <div class="summary-row">
                    <span class="summary-lbl">Inventory Status:</span>
                    <span class="summary-val" style="color: {{ in_array($return->status, ['Completed', 'Approved']) ? '#16a34a' : '#d97706' }};">
                        {{ in_array($return->status, ['Completed', 'Approved']) ? 'Stock Deducted' : 'Awaiting Approval' }}
                    </span>
                </div>
                <div class="summary-row">
                    <span class="summary-lbl">Settlement Mode:</span>
                    <span class="summary-val" style="font-size: 8.5px;">{{ $return->vendor_bill_id ? 'Vendor Bill Adjustment' : 'Debit Balance Credit' }}</span>
                </div>
            </td>
        </tr>
    </table>

    <!-- ── 4. RETURNED ITEMS TABLE ── -->
    <div class="section-header">
        Returned Items &amp; Stock Deduction Details ({{ $return->items->count() }} {{ $return->items->count() === 1 ? 'Item' : 'Items' }})
    </div>

    <table class="alloc-table">
        <thead>
            <tr>
                <th style="width: 5%; text-align: center;">#</th>
                <th style="width: 45%;">Product / Item Description</th>
                <th style="width: 20%;">Warehouse</th>
                <th style="width: 15%; text-align: right;">Returned Qty</th>
                <th style="width: 15%; text-align: right;">Refund Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($return->items as $idx => $item)
                @php
                    $lineTotal = (float)$item->quantity * (float)$item->unit_price;
                @endphp
                <tr>
                    <td style="text-align: center; color: #64748b;">{{ $idx + 1 }}</td>
                    <td>
                        <strong style="color: #0f172a;">{{ $item->product?->name ?: 'Item' }}</strong>
                        @if($item->product?->sku)
                            <div style="font-size: 8px; color: #64748b; margin-top: 1px;">
                                SKU: {{ $item->product->sku }}
                            </div>
                        @endif
                        @if(!empty($item->serial_numbers))
                            <div style="font-size: 7.5px; color: #475569; margin-top: 1px;">
                                S/N: {{ $item->serial_numbers }}
                            </div>
                        @endif
                    </td>
                    <td>
                        {{ $item->warehouse?->name ?: 'Main Warehouse' }}
                    </td>
                    <td style="text-align: right; font-weight: bold;">
                        {{ (float) $item->quantity }}
                    </td>
                    <td style="text-align: right; font-weight: bold; color: #dc2626;">
                        {{ format_currency($lineTotal) }}
                    </td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="3" style="text-align: right; text-transform: uppercase; font-size: 8.5px; color: #64748b;">
                    Total Refund:
                </td>
                <td style="text-align: right; font-weight: bold;">
                    {{ (float)$return->items->sum('quantity') }}
                </td>
                <td style="text-align: right; color: #dc2626; font-size: 10px;">
                    {{ format_currency($displayRefundTotal) }}
                </td>
            </tr>
        </tfoot>
    </table>

    <!-- ── 5. NOTES & SIGNATURE BLOCK ── -->
    <table class="bottom-section">
        <tr>
            <td style="width: 60%; vertical-align: top; padding-right: 15px;">
                @if($return->reason)
                    <div class="notes-box">
                        <strong style="color: #0f172a; display: block; margin-bottom: 2px;">Reason for Return:</strong>
                        {{ $return->reason }}
                    </div>
                @else
                    <div style="font-size: 8.5px; color: #94a3b8; line-height: 1.4;">
                        This document is an official Purchase Return Debit Note issued by {{ $buyerOrg['name'] ?? 'ERP' }}.
                    </div>
                @endif
            </td>
            <td style="width: 40%; vertical-align: bottom; text-align: right;">
                <div class="sig-line">
                    Authorized Signatory<br>
                    <span style="font-size: 7.5px; font-weight: normal; color: #64748b;">{{ $buyerOrg['name'] ?? 'Warrgyizmorsch Pvt Ltd' }}</span>
                </div>
            </td>
        </tr>
    </table>

    <!-- ── 6. INLINE CANVAS SCRIPT FOR EXACT PAGE NUMBERING (Page 1 of 1, etc.) ── -->
    <script type="text/php">
        if (isset($pdf)) {
            $pdf->page_script(function ($pageNumber, $pageCount, $canvas, $fontMetrics) {
                $text = "Page " . $pageNumber . " of " . $pageCount;
                $font = $fontMetrics->getFont("DejaVu Sans", "normal");
                $size = 8;
                $color = array(100/255, 116/255, 139/255);
                $w = $canvas->get_width();
                $h = $canvas->get_height();
                $canvas->text($w - 85, $h - 22, $text, $font, $size, $color);
            });
        }
    </script>

</body>
</html>
