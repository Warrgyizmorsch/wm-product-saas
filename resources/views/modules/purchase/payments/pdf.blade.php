<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>Payment Voucher - {{ $payment->payment_number }}</title>
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

        /* ── PERSISTENT FOOTER ON EVERY PAGE (REFRENS / ERP DASHED STYLE) ── */
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
            font-size: 17px;
            font-weight: 900;
            color: #0284c7;
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

        /* ── ADVANCE CREDIT ADJUSTED BANNER ──────── */
        .advance-banner {
            background-color: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 6px;
            padding: 8px 12px;
            margin-bottom: 12px;
            page-break-inside: avoid;
        }
        .advance-badge {
            background-color: #0284c7;
            color: #ffffff;
            font-size: 8.5px;
            font-weight: bold;
            padding: 2px 6px;
            border-radius: 3px;
            display: inline-block;
            margin-right: 6px;
        }
        .advance-amount {
            color: #0284c7;
            font-weight: bold;
            font-size: 11px;
        }
        .advance-sub {
            font-size: 8.5px;
            color: #475569;
            margin-top: 3px;
            line-height: 1.35;
        }
        .status-badge {
            font-size: 8px;
            font-weight: bold;
            padding: 2px 6px;
            border-radius: 3px;
            display: inline-block;
            text-transform: uppercase;
        }
        .badge-success {
            background-color: #dcfce7;
            color: #15803d;
            border: 0.8px solid #86efac;
        }
        .badge-info {
            background-color: #e0f2fe;
            color: #0369a1;
            border: 0.8px solid #7dd3fc;
        }

        /* ── ALLOCATIONS TABLE ───────────────────── */
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
        $buyerOrg = billing_organization_details($payment);

        // Calculate advance adjusted across linked bills
        $allLinkedAdvanceSettled = 0.0;
        $advanceVouchers = [];

        foreach ($payment->allocations as $alloc) {
            $bill = $alloc->vendorBill ?? $alloc->bill;
            if ($bill) {
                $otherAllocs = $bill->allocations->filter(function($a) use ($payment) {
                    return $a->vendor_payment_id != $payment->id && ($a->vendorPayment?->payment_type === 'Advance' || $a->payment?->payment_type === 'Advance');
                });
                foreach ($otherAllocs as $oa) {
                    $allLinkedAdvanceSettled += (float)$oa->allocated_amount;
                    $vNum = $oa->vendorPayment?->payment_number ?? $oa->payment?->payment_number;
                    if ($vNum && !in_array($vNum, $advanceVouchers)) {
                        $advanceVouchers[] = $vNum;
                    }
                }
            }
        }

        $allocTotal = $payment->allocations->sum('allocated_amount');
        $unallocated = max(0.0, (float)$payment->amount - (float)$allocTotal);
    @endphp

    <!-- ── 1. PERSISTENT FOOTER ON EVERY PAGE (REFRENS STYLE) ── -->
    <footer class="doc-page-footer">
        <div class="footer-dashed-line"></div>
        <table class="footer-meta-table">
            <tr>
                <td style="width: 50%; text-align: left;">
                    {{ $buyerOrg['name'] ?? 'ERP' }} &bull; Payment Voucher # {{ $payment->payment_number }}
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
                        <div class="company-sub">Official Vendor Payment Voucher</div>
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

            <!-- Right: Voucher Title & Meta -->
            <td style="width: 42%; vertical-align: top; text-align: right;">
                <div class="doc-title">
                    {{ $payment->payment_type === 'Advance' ? 'ADVANCE RECEIPT' : 'PAYMENT VOUCHER' }}
                </div>
                <div class="doc-number"># {{ $payment->payment_number }}</div>

                <table class="meta-table">
                    <tr>
                        <td class="meta-label">Payment Date:</td>
                        <td class="meta-value">{{ $payment->payment_date ? (is_string($payment->payment_date) ? date('d-M-Y', strtotime($payment->payment_date)) : $payment->payment_date->format('d-M-Y')) : '—' }}</td>
                    </tr>
                    <tr>
                        <td class="meta-label">Payment Method:</td>
                        <td class="meta-value">{{ $payment->payment_method ?: 'Bank Transfer' }}</td>
                    </tr>
                    @if($payment->reference_number)
                        <tr>
                            <td class="meta-label">Bank Ref / UTR No:</td>
                            <td class="meta-value font-monospace">{{ $payment->reference_number }}</td>
                        </tr>
                    @endif
                    <tr>
                        <td class="meta-label">Payment Status:</td>
                        <td class="meta-value" style="color: #16a34a;">{{ $payment->status ?: 'Posted' }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- ── 3. TWO COLUMN INFO BOXES ── -->
    <table class="info-boxes-table">
        <tr>
            <!-- Left: Supplier / Vendor Details -->
            <td class="info-box">
                <div class="box-title">Paid To (Supplier / Vendor):</div>
                <div style="margin-bottom: 5px;">
                    <div class="client-avatar">{{ strtoupper(substr($payment->vendor?->name ?: 'V', 0, 1)) }}</div>
                    <div class="client-name">{{ $payment->vendor?->name ?: 'Vendor Supplier' }}</div>
                </div>
                <div class="client-contact">
                    <div><strong>Company:</strong> {{ $payment->vendor?->company_name ?: ($payment->vendor?->name ?: 'Valued Supplier') }}</div>
                    <div><strong>Email:</strong> {{ $payment->vendor?->email ?: '—' }} &nbsp;|&nbsp; <strong>Phone:</strong> {{ $payment->vendor?->phone ?: '—' }}</div>
                    @if($payment->vendor?->gstin)
                        <div><strong>GSTIN:</strong> {{ $payment->vendor->gstin }}</div>
                    @endif
                    @if($payment->vendor?->address)
                        <div style="margin-top: 3px; border-top: 1px solid #e2e8f0; padding-top: 3px; font-size: 8.5px; color: #64748b;">
                            {{ $payment->vendor->address }}
                        </div>
                    @endif
                </div>
            </td>

            <td class="info-box-gap"></td>

            <!-- Right: Payment & Outflow Summary -->
            <td class="info-box">
                <div class="box-title">Payment Summary &amp; Status:</div>
                <div class="summary-row">
                    <span class="summary-lbl">Total Amount Paid:</span>
                    <span class="summary-val" style="color: #0284c7; font-size: 11.5px;">{{ format_currency($payment->amount) }}</span>
                </div>
                <div class="summary-row">
                    <span class="summary-lbl">Allocated Amount:</span>
                    <span class="summary-val">{{ format_currency($allocTotal) }}</span>
                </div>
                @if($unallocated > 0.001)
                    <div class="summary-row">
                        <span class="summary-lbl" style="color: #d97706;">Unallocated (Advance):</span>
                        <span class="summary-val" style="color: #d97706;">{{ format_currency($unallocated) }}</span>
                    </div>
                @endif
                <div class="summary-row">
                    <span class="summary-lbl">Paid From Account:</span>
                    <span class="summary-val" style="font-size: 8.5px;">
                        @if($payment->bankAccount)
                            {{ $payment->bankAccount->code }} — {{ $payment->bankAccount->name }}
                        @else
                            {{ $payment->payment_method === 'Cash' ? '1010 — Cash-in-Hand' : '1020 — Bank Account' }}
                        @endif
                    </span>
                </div>
                <div class="summary-row">
                    <span class="summary-lbl">Payment Status:</span>
                    <span class="summary-val" style="color: #16a34a;">{{ $payment->status ?: 'Posted' }}</span>
                </div>
            </td>
        </tr>
    </table>

    <!-- ── 4. ADVANCE CREDIT ADJUSTED BANNER (IF APPLICABLE) ── -->
    @if($allLinkedAdvanceSettled > 0.001)
        <table class="advance-banner" style="width: 100%;">
            <tr>
                <td style="width: 78%; vertical-align: middle;">
                    <div>
                        <span class="advance-badge">Advance Credit Adjusted</span>
                        <strong style="color: #0f172a;">Advance Applied on Linked Bills:</strong>
                        <span class="advance-amount">{{ format_currency($allLinkedAdvanceSettled) }}</span>
                    </div>
                    <div class="advance-sub">
                        Settled via Advance Payment Voucher(s): <strong>{{ implode(', ', $advanceVouchers) }}</strong>. Combined Total Settled: <strong>{{ format_currency($payment->amount + $allLinkedAdvanceSettled) }}</strong> (Bank {{ format_currency($payment->amount) }} + Advance {{ format_currency($allLinkedAdvanceSettled) }}).
                    </div>
                </td>
                <td style="width: 22%; text-align: right; vertical-align: middle;">
                    <span class="status-badge badge-success">&#10003; Fully Adjusted</span>
                </td>
            </tr>
        </table>
    @endif

    <!-- ── 5. BILL ALLOCATIONS & ADJUSTMENTS TABLE ── -->
    <div class="section-header">
        Bill Allocations / Adjustments ({{ $payment->allocations->count() }} {{ $payment->allocations->count() === 1 ? 'Record' : 'Records' }})
    </div>

    <table class="alloc-table">
        <thead>
            <tr>
                <th style="width: 26%;">Linked Document</th>
                <th style="width: 15%;">Type</th>
                <th style="width: 14%;">Document Date</th>
                <th style="width: 15%; text-align: right;">Bill Total</th>
                <th style="width: 15%; text-align: right;">Paid from Bank</th>
                <th style="width: 15%; text-align: right;">Advance Adjusted</th>
            </tr>
        </thead>
        <tbody>
            @php
                $sumBillTotal = 0.0;
                $sumBankAlloc = 0.0;
                $sumAdvAdjusted = 0.0;
            @endphp
            @forelse($payment->allocations as $alloc)
                @php
                    $bill = $alloc->vendorBill ?? $alloc->bill;

                    $advAppliedOnThisBill = 0.0;
                    $advVoucherNums = [];
                    if ($bill) {
                        $advAllocs = $bill->allocations->filter(function($a) use ($payment) {
                            return $a->vendor_payment_id != $payment->id && ($a->vendorPayment?->payment_type === 'Advance' || $a->payment?->payment_type === 'Advance');
                        });
                        $advAppliedOnThisBill = (float)$advAllocs->sum('allocated_amount');
                        foreach ($advAllocs as $aa) {
                            $num = $aa->vendorPayment?->payment_number ?? $aa->payment?->payment_number;
                            if ($num && !in_array($num, $advVoucherNums)) {
                                $advVoucherNums[] = $num;
                            }
                        }
                    }

                    $bTotal = (float)($bill?->grand_total ?: 0);
                    $sumBillTotal += $bTotal;
                    $sumBankAlloc += (float)$alloc->allocated_amount;
                    $sumAdvAdjusted += $advAppliedOnThisBill;
                @endphp
                <tr>
                    <td>
                        @if($bill)
                            <strong style="color: #0284c7;">{{ $bill->bill_number }}</strong>
                            @if($bill->purchaseOrder)
                                <div style="font-size: 8px; color: #64748b; margin-top: 1px;">
                                    PO: {{ $bill->purchaseOrder->purchase_order_number }}
                                </div>
                            @endif
                        @else
                            <span style="color: #94a3b8; font-style: italic;">Unallocated</span>
                        @endif
                    </td>
                    <td>
                        <span class="status-badge badge-info">Vendor Bill</span>
                    </td>
                    <td>
                        {{ $bill?->bill_date ? (is_string($bill->bill_date) ? date('d-M-Y', strtotime($bill->bill_date)) : $bill->bill_date->format('d-M-Y')) : '—' }}
                    </td>
                    <td style="text-align: right; font-weight: 600;">
                        {{ format_currency($bTotal) }}
                    </td>
                    <td style="text-align: right; font-weight: bold; color: #0f172a;">
                        {{ format_currency($alloc->allocated_amount) }}
                    </td>
                    <td style="text-align: right;">
                        @if($advAppliedOnThisBill > 0.001)
                            <strong style="color: #0284c7;">{{ format_currency($advAppliedOnThisBill) }}</strong>
                            @if(count($advVoucherNums) > 0)
                                <div style="font-size: 7.5px; color: #64748b;">({{ implode(', ', $advVoucherNums) }})</div>
                            @endif
                        @else
                            <span style="color: #94a3b8;">—</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="text-align: center; color: #94a3b8; padding: 18px;">
                        No bill allocations linked to this payment voucher.
                    </td>
                </tr>
            @endforelse
        </tbody>
        @if($payment->allocations->count() > 0)
            <tfoot>
                <tr>
                    <td colspan="3" style="text-align: right; text-transform: uppercase; font-size: 8.5px; color: #64748b;">
                        Totals:
                    </td>
                    <td style="text-align: right;">
                        {{ format_currency($sumBillTotal) }}
                    </td>
                    <td style="text-align: right; color: #0f172a;">
                        {{ format_currency($sumBankAlloc) }}
                    </td>
                    <td style="text-align: right; color: #0284c7;">
                        {{ format_currency($sumAdvAdjusted) }}
                    </td>
                </tr>
            </tfoot>
        @endif
    </table>

    <!-- ── 6. NOTES & SIGNATURE BLOCK ── -->
    <table class="bottom-section">
        <tr>
            <td style="width: 60%; vertical-align: top; padding-right: 15px;">
                @if($payment->notes)
                    <div class="notes-box">
                        <strong style="color: #0f172a; display: block; margin-bottom: 2px;">Voucher Remarks / Notes:</strong>
                        {{ $payment->notes }}
                    </div>
                @else
                    <div style="font-size: 8.5px; color: #94a3b8; line-height: 1.4;">
                        This payment voucher is an official electronic settlement voucher generated by {{ $buyerOrg['name'] ?? 'ERP' }}.
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

    <!-- ── 7. INLINE CANVAS SCRIPT FOR EXACT PAGE NUMBERING (Page 1 of 1, etc.) ── -->
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
