<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>Tax Invoice - {{ $invoice->invoice_number }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            line-height: 1.38;
            color: #1e293b;
            background: #ffffff;
            padding: 28px 32px;
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

        /* ── 1. E-INVOICE / E-WAY BILL STRIP ─────────── */
        .einvoice-banner {
            width: 100%;
            border: 1px solid #cbd5e1;
            border-left: 4px solid #16a34a;
            background-color: #f8fafc;
            border-radius: 6px;
            padding: 8px 10px;
            margin-bottom: 10px;
        }

        .badge-einvoice {
            background-color: #16a34a;
            color: #ffffff;
            font-size: 8px;
            font-weight: bold;
            padding: 2px 6px;
            border-radius: 3px;
            display: inline-block;
            text-transform: uppercase;
        }

        .badge-ewaybill {
            background-color: #0284c7;
            color: #ffffff;
            font-size: 8px;
            font-weight: bold;
            padding: 2px 6px;
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

        /* ── 2. HEADER ───────────────────────────────── */
        .header-table {
            width: 100%;
            border-bottom: 1.5px solid #e2e8f0;
            padding-bottom: 10px;
            margin-bottom: 10px;
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

        .company-title-wrap {
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
            font-size: 9px;
            color: #64748b;
        }

        .company-details {
            font-size: 9.5px;
            color: #475569;
            margin-top: 4px;
            line-height: 1.45;
        }

        .doc-title {
            font-size: 20px;
            font-weight: bold;
            color: #1e40af;
            text-align: right;
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }

        .doc-number {
            font-size: 12.5px;
            font-weight: bold;
            color: #1e293b;
            text-align: right;
            margin-top: 2px;
        }

        .doc-meta-table {
            margin-left: auto;
            margin-top: 6px;
        }

        .doc-meta-table td {
            font-size: 9.5px;
            padding: 1px 0;
            text-align: right;
        }

        .meta-lbl {
            color: #64748b;
            padding-right: 6px;
            white-space: nowrap;
        }

        .meta-val {
            color: #0f172a;
            font-weight: bold;
            white-space: nowrap;
        }

        /* ── 3. ADDRESS & REFERENCE CARDS ───────────── */
        .cards-table {
            width: 100%;
            margin-bottom: 10px;
        }

        .card-box {
            border: 1px solid #cbd5e1;
            background-color: #f8fafc;
            border-radius: 5px;
            padding: 8px 10px;
            vertical-align: top;
        }

        .card-header-lbl {
            font-size: 8.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 3px;
            margin-bottom: 5px;
        }

        .card-party-name {
            font-size: 12.5px;
            font-weight: bold;
            color: #0f172a;
            margin-bottom: 2px;
        }

        .card-line {
            font-size: 9.5px;
            color: #475569;
            line-height: 1.35;
        }

        .ref-row {
            font-size: 9.5px;
            color: #475569;
            padding: 2px 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .ref-row:last-child {
            border-bottom: none;
        }

        /* ── 4. ITEMS TABLE ──────────────────────────── */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

        .items-table thead th {
            background-color: #f1f5f9;
            color: #334155;
            font-size: 8.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            padding: 6px 7px;
            border-top: 1px solid #e2e8f0;
            border-bottom: 1.5px solid #cbd5e1;
        }

        .items-table tbody td {
            padding: 6px 7px;
            font-size: 9.5px;
            color: #1e293b;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
        }

        .item-name {
            font-weight: bold;
            color: #0f172a;
            font-size: 10px;
            display: block;
        }

        .item-sub {
            font-size: 8px;
            color: #64748b;
            display: block;
            margin-top: 1px;
        }

        /* ── 5. SPLIT SUMMARY SECTION ────────────────── */
        .bottom-split-table {
            width: 100%;
            margin-top: 2px;
        }

        .tax-summary-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6px;
        }

        .tax-summary-table th {
            background-color: #f1f5f9;
            color: #334155;
            font-size: 7.5px;
            font-weight: bold;
            text-transform: uppercase;
            padding: 3px 5px;
            border-top: 1px solid #e2e8f0;
            border-bottom: 1.5px solid #cbd5e1;
        }

        .tax-summary-table td {
            padding: 3px 5px;
            font-size: 8px;
            color: #334155;
            border-bottom: 1px solid #f1f5f9;
        }

        .bank-info-box {
            border: 1px solid #cbd5e1;
            background-color: #f8fafc;
            border-radius: 4px;
            padding: 6px 8px;
            margin-bottom: 6px;
        }

        .bank-info-title {
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            color: #1e40af;
            margin-bottom: 3px;
        }

        .notes-box {
            border: 1px solid #cbd5e1;
            background-color: #f8fafc;
            border-radius: 4px;
            padding: 6px 8px;
            font-size: 8px;
            color: #475569;
        }

        /* ── SUMMARY CALCULATION TABLE ───────────────── */
        .summary-card {
            border: 1px solid #cbd5e1;
            background-color: #f8fafc;
            border-radius: 5px;
            padding: 6px 8px;
        }

        .summary-table {
            width: 100%;
            border-collapse: collapse;
        }

        .summary-table td {
            padding: 3.5px 4px;
            font-size: 9.5px;
            color: #64748b;
            border-bottom: 1px solid #f1f5f9;
        }

        .summary-table td.amt {
            text-align: right;
            font-weight: bold;
            color: #0f172a;
        }

        .summary-table .billed-items-total td {
            background-color: #ffffff;
            color: #0f172a;
            font-weight: bold;
            border-top: 1px solid #e2e8f0;
            border-bottom: 1px solid #e2e8f0;
            padding: 4px;
        }

        .summary-table .grand-total-row td {
            border-top: 1.5px solid #cbd5e1;
            padding: 6px 4px;
        }

        .summary-table .grand-total-row td.lbl {
            font-size: 11px;
            font-weight: bold;
            color: #0f172a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .summary-table .grand-total-row td.amt {
            font-size: 13.5px;
            font-weight: bold;
            color: #1e40af;
        }

        .summary-table .bal-row td {
            font-size: 10px;
            font-weight: bold;
            padding: 4px;
        }

        /* ── 6. FOOTER & SIGNATURE ───────────────────── */
        .footer-table {
            width: 100%;
            border-top: 1px solid #e2e8f0;
            margin-top: 12px;
            padding-top: 8px;
        }

        .footer-disclaimer {
            font-size: 8px;
            color: #64748b;
            line-height: 1.4;
        }

        .sig-box {
            text-align: right;
        }

        .sig-company {
            font-size: 9.5px;
            font-weight: bold;
            color: #0f172a;
            margin-bottom: 22px;
        }

        .sig-line {
            font-size: 8.5px;
            color: #64748b;
            font-weight: 600;
            border-top: 1px solid #94a3b8;
            display: inline-block;
            padding-top: 2px;
            width: 140px;
            text-align: center;
        }
    </style>
</head>
<body>

    @php
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

    <!-- ═══ 1. GST E-INVOICE & E-WAY BILL BANNER STRIP ═══ -->
    @if($hasEinvoice || $hasEwayBill)
        <table class="einvoice-banner" cellpadding="0" cellspacing="0">
            <tr>
                @if($hasEinvoice && $qrBase64)
                    <td style="width: 72px; vertical-align: middle; text-align: center; padding-right: 8px;">
                        <div class="qr-box">
                            <img src="{{ $qrBase64 }}" alt="QR Code" style="width: 62px; height: 62px; display: block;">
                        </div>
                    </td>
                @endif
                <td style="vertical-align: middle;">
                    <table width="100%" cellpadding="0" cellspacing="0">
                        <tr>
                            <!-- Left: E-Invoice Details -->
                            <td style="vertical-align: top; padding-right: 10px;">
                                <div style="margin-bottom: 2px;">
                                    <span class="badge-einvoice">GST E-INVOICE</span>
                                    <span style="font-size: 8px; color: #64748b; margin-left: 4px;">Govt. E-Invoice Portal (NIC)</span>
                                </div>
                                <div style="font-size: 9px; color: #0f172a; margin-bottom: 2px;">
                                    <strong>Ack No:</strong> <span class="font-mono text-primary fw-bold" style="font-size: 9.5px;">{{ $invoice->ack_no }}</span>
                                    &nbsp;|&nbsp;
                                    <strong>Ack Date:</strong> <span class="text-muted">{{ $invoice->ack_date ? date('d-M-Y H:i', strtotime($invoice->ack_date)) : '—' }}</span>
                                </div>
                                <div style="font-size: 8px; color: #475569; font-family: monospace; word-break: break-all; line-height: 1.25;">
                                    <strong style="color: #0f172a;">IRN:</strong> {{ $invoice->irn }}
                                </div>
                            </td>

                            <!-- Right: E-Way Bill Details -->
                            @if($hasEwayBill)
                                <td style="width: 175px; vertical-align: middle; text-align: right; border-left: 1px solid #cbd5e1; padding-left: 10px;">
                                    <span class="badge-ewaybill">E-WAY BILL ACTIVE</span>
                                    <div class="font-mono fw-bold text-dark" style="font-size: 11px; margin-top: 1px;">{{ $invoice->eway_bill_no }}</div>
                                    @if($invoice->transporter_name)
                                        <div style="font-size: 8px; color: #475569; white-space: nowrap; overflow: hidden;">
                                            Transporter: <strong>{{ $invoice->transporter_name }}</strong> @if($invoice->transporter_id)<span class="font-mono">({{ $invoice->transporter_id }})</span>@endif
                                        </div>
                                    @endif
                                    @if($invoice->vehicle_no || $invoice->transport_mode)
                                        <div style="font-size: 8px; color: #475569;">
                                            Vehicle/Mode: <strong>{{ $invoice->vehicle_no ?: ($invoice->transport_doc_no ?: '—') }}</strong> ({{ ucfirst($invoice->transport_mode ?: 'Road') }})
                                        </div>
                                    @endif
                                    <div style="font-size: 8px; color: #64748b;">
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

    <!-- ═══ 2. HEADER ═══ -->
    <table class="header-table" cellpadding="0" cellspacing="0">
        <tr>
            <!-- LEFT: Company Details -->
            <td style="width: 58%; vertical-align: top;">
                <table cellpadding="0" cellspacing="0">
                    <tr>
                        <td style="vertical-align: middle;">
                            <div class="company-avatar">{{ strtoupper(substr(tenant() ? tenant()->name : 'D', 0, 1)) }}</div>
                        </td>
                        <td style="vertical-align: middle; padding-left: 8px;">
                            <div class="company-name">{{ tenant() ? tenant()->name : 'Demo Tenant' }}</div>
                            <div class="company-sub">Official Corporate Billing Unit</div>
                        </td>
                    </tr>
                </table>
                <div class="company-details">
                    H-1, Industrial Area, Sukher, Udaipur 313001, Rajasthan, India<br>
                    <strong>GSTIN:</strong> 08AAFCS1234E1Z0 &nbsp;|&nbsp; <strong>State Code:</strong> 08 (Rajasthan)<br>
                    <strong>Email:</strong> {{ tenant() ? tenant()->billing_email : 'billing@saaserp.com' }} &nbsp;|&nbsp; <strong>Phone:</strong> +91 294 2440230
                </div>
            </td>

            <!-- RIGHT: Invoice Title & Meta -->
            <td style="width: 42%; vertical-align: top;">
                <div class="doc-title">TAX INVOICE</div>
                <div class="doc-number"># {{ $invoice->invoice_number }}</div>
                <table class="doc-meta-table" cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="meta-lbl">Invoice Date:</td>
                        <td class="meta-val">{{ date('d-M-Y', strtotime($invoice->invoice_date)) }}</td>
                    </tr>
                    <tr>
                        <td class="meta-lbl">Due Date:</td>
                        <td class="meta-val">{{ $invoice->due_date ? date('d-M-Y', strtotime($invoice->due_date)) : '—' }}</td>
                    </tr>
                    <tr>
                        <td class="meta-lbl">Payment Terms:</td>
                        <td class="meta-val">{{ \App\Domains\Platform\Models\PaymentTerm::getLabel($invoice->payment_terms ?: $invoice->salesOrder?->payment_terms) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- ═══ 3. ADDRESS & ORDER CARDS ═══ -->
    <table class="cards-table" cellpadding="0" cellspacing="0">
        <tr>
            <!-- Left Card: Billed To Customer -->
            <td class="card-box" style="width: 49%;">
                <div class="card-header-lbl">BILLED TO (CUSTOMER):</div>
                <div class="card-party-name">{{ $invoice->customer?->name ?: ($invoice->salesOrder?->customer?->name ?: 'Customer') }}</div>
                @if($invoice->customer?->company_name)
                    <div class="card-line" style="font-weight: 600; color: #475569;">{{ $invoice->customer->company_name }}</div>
                @endif
                <div class="card-line">
                    {{ $invoice->customer?->email ?: ($invoice->salesOrder?->customer?->email ?: '') }}
                    @if($invoice->customer?->phone || $invoice->salesOrder?->customer?->phone)
                        &nbsp;|&nbsp; {{ $invoice->customer?->phone ?: $invoice->salesOrder?->customer?->phone }}
                    @endif
                </div>
                @if($invoice->salesOrder?->billing_address)
                    <div class="card-line" style="margin-top: 4px; padding-top: 3px; border-top: 1px solid #e2e8f0;">
                        <strong style="color: #64748b;">Billing Address:</strong><br>{{ $invoice->salesOrder->billing_address }}
                    </div>
                @endif
            </td>

            <td style="width: 2%;"></td>

            <!-- Right Card: Order & Dispatch References -->
            <td class="card-box" style="width: 49%;">
                <div class="card-header-lbl">ORDER &amp; DISPATCH REFERENCES:</div>
                @if($invoice->salesOrder)
                    <div class="ref-row">
                        <span style="color: #64748b;">Sales Order Reference:</span>
                        <span style="float: right; font-weight: bold; color: #1e40af;">{{ $invoice->salesOrder->sales_order_number }}</span>
                    </div>
                @endif
                @if($invoice->materialRequirement)
                    <div class="ref-row">
                        <span style="color: #64748b;">Dispatch Ref:</span>
                        <span style="float: right; font-weight: bold; color: #0284c7;">{{ $invoice->materialRequirement->requirement_number }}</span>
                    </div>
                @endif
                @if($hasEwayBill)
                    <div class="ref-row">
                        <span style="color: #64748b;">E-Way Bill No:</span>
                        <span style="float: right; font-weight: bold; color: #0f172a; font-family: monospace;">{{ $invoice->eway_bill_no }} @if($invoice->vehicle_no)({{ $invoice->vehicle_no }})@endif</span>
                    </div>
                    @if($invoice->transporter_name)
                        <div class="ref-row">
                            <span style="color: #64748b;">Transporter:</span>
                            <span style="float: right; font-weight: 600; color: #0f172a;">{{ $invoice->transporter_name }} @if($invoice->transporter_id)({{ $invoice->transporter_id }})@endif</span>
                        </div>
                    @endif
                @endif
                <div class="ref-row">
                    <span style="color: #64748b;">Place of Supply:</span>
                    <span style="float: right; font-weight: 600; color: #0f172a;">Rajasthan (08)</span>
                </div>
                <div class="ref-row">
                    <span style="color: #64748b;">GST Option:</span>
                    <span style="float: right; font-weight: bold; color: #0f172a;">{{ $invoice->gst_type === 'igst' ? 'Inter-State GST (IGST)' : 'Intra-State GST (CGST + SGST)' }}</span>
                </div>
                <div class="ref-row">
                    <span style="color: #64748b;">Payment Status:</span>
                    <span style="float: right; font-weight: bold; color: {{ $balanceDue > 0 ? '#dc2626' : '#16a34a' }};">
                        {{ $balanceDue > 0 ? 'Balance Outstanding' : 'Fully Paid' }}
                    </span>
                </div>
            </td>
        </tr>
    </table>

    <!-- ═══ 4. ITEMS TABLE ═══ -->
    <table class="items-table" cellpadding="0" cellspacing="0">
        <thead>
            <tr>
                <th class="text-center" style="width: 4%;">#</th>
                <th style="width: 44%;">ITEM &amp; DESCRIPTION</th>
                <th class="text-right" style="width: 10%;">QTY</th>
                <th class="text-right" style="width: 14%;">RATE (&#8377;)</th>
                <th class="text-right" style="width: 10%;">TAX %</th>
                <th class="text-right" style="width: 18%;">AMOUNT (&#8377;)</th>
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
                    <td class="text-center" style="color: #94a3b8;">{{ $idx + 1 }}</td>
                    <td>
                        <span class="item-name">{{ $item->product?->name ?: $item->item_name }}</span>
                        @if($item->product?->sku)
                            <span class="item-sub">SKU: {{ $item->product->sku }}</span>
                        @endif
                        @if($item->description)
                            <span class="item-sub">{{ $item->description }}</span>
                        @endif
                    </td>
                    <td class="text-right" style="font-weight: 600;">{{ (float)$item->quantity }}</td>
                    <td class="text-right">&#8377;{{ number_format($item->unit_price, 2) }}</td>
                    <td class="text-right" style="color: #64748b;">{{ (float)$item->tax_rate }}%</td>
                    <td class="text-right" style="font-weight: bold; color: #0f172a;">&#8377;{{ number_format($lineTotal, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <!-- ═══ 5. SPLIT SUMMARY SECTION ═══ -->
    <table class="bottom-split-table" cellpadding="0" cellspacing="0">
        <tr>
            <!-- LEFT COLUMN: GST Breakdown, Bank Details & Notes -->
            <td style="width: 53%; vertical-align: top; padding-right: 10px;">
                @php
                    $taxGroups = $invoice->items->groupBy(fn($item) => (string)(float)$item->tax_rate);
                @endphp
                @if ($taxGroups->count() > 0 && $invoice->tax_amount > 0)
                    <div style="font-size: 8px; font-weight: bold; text-transform: uppercase; color: #1e40af; margin-bottom: 3px; letter-spacing: 0.4px;">
                        GST TAX SUMMARY
                    </div>
                    <table class="tax-summary-table" cellpadding="0" cellspacing="0">
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
                                    <th class="text-right" style="width: 25%;">CGST</th>
                                    <th class="text-right" style="width: 25%;">SGST</th>
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
                                <td>Total</td>
                                <td class="text-right" style="color: #1e40af;">&#8377;{{ number_format($totTax, 2) }}</td>
                                @if($invoice->gst_type === 'igst')
                                    <td class="text-right">&#8377;{{ number_format($totIgst, 2) }}</td>
                                @else
                                    <td class="text-right">&#8377;{{ number_format($totCgst, 2) }}</td>
                                    <td class="text-right">&#8377;{{ number_format($totSgst, 2) }}</td>
                                @endif
                            </tr>
                        </tbody>
                    </table>
                @endif

                <div class="bank-info-box">
                    <div class="bank-info-title">Bank Payment Details:</div>
                    <table width="100%" cellpadding="0" cellspacing="0" style="font-size: 8.5px; color: #475569;">
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

                @if($invoice->notes)
                    <div class="notes-box">
                        <strong style="font-size: 7.5px; text-transform: uppercase; color: #1e40af; letter-spacing: 0.3px;">Terms &amp; Conditions / Notes:</strong><br>
                        {!! $invoice->notes !!}
                    </div>
                @endif
            </td>

            <!-- RIGHT COLUMN: Subtotal Calculations Card -->
            <td style="width: 47%; vertical-align: top;">
                @php
                    $grossSubtotal = 0;
                    $totalItemDiscount = 0;
                    $itemsTaxAmount = 0;
                    $maxItemTaxRate = 0;
                    foreach($invoice->items as $it) {
                        $grossSubtotal += ($it->quantity * $it->unit_price);
                        $totalItemDiscount += $it->discount;
                        if ($invoice->tax_type === 'item_wise_tax') {
                            $lineTaxable = max(0, ($it->quantity * $it->unit_price) - $it->discount);
                            $itemsTaxAmount += ($lineTaxable * ($it->tax_rate / 100));
                            if ($it->tax_rate > $maxItemTaxRate) $maxItemTaxRate = $it->tax_rate;
                        }
                    }
                    $effectiveDiscount = ($invoice->discount_type === 'order_wise') ? (float)$invoice->discount_amount : $totalItemDiscount;
                    $taxableBase = max(0, $grossSubtotal - $effectiveDiscount);

                    if ($invoice->tax_type === 'order_wise_tax') {
                        $itemsTaxAmount = $taxableBase * (($invoice->order_tax_rate ?: 18) / 100);
                    } elseif ($invoice->tax_type === 'without_tax') {
                        $itemsTaxAmount = 0;
                    }

                    $itemsTotalInclGst = $taxableBase + $itemsTaxAmount;
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
                    $totalFreightInclGst = $freightAmount + $freightTax;
                    $totalInvoiceTaxAmount = $itemsTaxAmount + $freightTax;
                    $adjustment = (float)($invoice->adjustment ?? 0);
                    $grandTotal = (float)$invoice->total_amount;
                    $gstType = $invoice->gst_type ?? 'cgst_sgst';
                @endphp

                <div class="summary-card">
                    <table class="summary-table" cellpadding="0" cellspacing="0">
                        <tr>
                            <td>Subtotal (Excl. Tax):</td>
                            <td class="amt">&#8377;{{ number_format($grossSubtotal, 2) }}</td>
                        </tr>

                        @if($invoice->discount_type !== 'without_discount' && $effectiveDiscount > 0)
                            <tr>
                                <td style="color: #dc2626;">Less: Item Discounts:</td>
                                <td class="amt" style="color: #dc2626;">-&#8377;{{ number_format($effectiveDiscount, 2) }}</td>
                            </tr>
                        @endif

                        <tr>
                            <td>Items Taxable Value:</td>
                            <td class="amt">&#8377;{{ number_format($taxableBase, 2) }}</td>
                        </tr>

                        @if($invoice->tax_type !== 'without_tax' && $itemsTaxAmount > 0)
                            <tr>
                                <td style="font-size: 8.5px;">Add: Items GST Tax:</td>
                                <td class="amt" style="font-size: 8.5px; color: #64748b;">+&#8377;{{ number_format($itemsTaxAmount, 2) }}</td>
                            </tr>
                        @endif

                        <tr class="billed-items-total">
                            <td>Billed Items Total (Incl. GST):</td>
                            <td class="amt">&#8377;{{ number_format($itemsTotalInclGst, 2) }}</td>
                        </tr>

                        @if($freightAmount > 0)
                            <tr>
                                <td>Freight Charges:</td>
                                <td class="amt" style="color: #1e40af;">&#8377;{{ number_format($freightAmount, 2) }}</td>
                            </tr>
                            @if($freightTax > 0)
                                <tr>
                                    <td style="font-size: 8.5px;">Add: Freight GST Tax:</td>
                                    <td class="amt" style="font-size: 8.5px; color: #64748b;">+&#8377;{{ number_format($freightTax, 2) }}</td>
                                </tr>
                            @endif
                        @endif

                        @if($invoice->tax_type !== 'without_tax' && $totalInvoiceTaxAmount > 0)
                            @if($gstType === 'cgst_sgst')
                                <tr>
                                    <td style="font-size: 8.5px; color: #64748b;">CGST (Central Tax):</td>
                                    <td class="amt" style="font-size: 8.5px; color: #64748b;">+&#8377;{{ number_format($totalInvoiceTaxAmount / 2, 2) }}</td>
                                </tr>
                                <tr>
                                    <td style="font-size: 8.5px; color: #64748b;">SGST (State Tax):</td>
                                    <td class="amt" style="font-size: 8.5px; color: #64748b;">+&#8377;{{ number_format($totalInvoiceTaxAmount / 2, 2) }}</td>
                                </tr>
                            @else
                                <tr>
                                    <td style="font-size: 8.5px; color: #64748b;">IGST (Integrated Tax):</td>
                                    <td class="amt" style="font-size: 8.5px; color: #64748b;">+&#8377;{{ number_format($totalInvoiceTaxAmount, 2) }}</td>
                                </tr>
                            @endif
                        @endif

                        @if($adjustment != 0)
                            <tr>
                                <td>Adjustment:</td>
                                <td class="amt">&#8377;{{ number_format($adjustment, 2) }}</td>
                            </tr>
                        @endif

                        <tr class="grand-total-row">
                            <td class="lbl">Grand Total:</td>
                            <td class="amt">&#8377;{{ number_format($grandTotal, 2) }}</td>
                        </tr>

                        @php
                            $totalPaid = $invoice->amount_paid ?: 0;
                            $balDue = max(0, $grandTotal - $totalPaid);
                        @endphp
                        @if($totalPaid > 0)
                            <tr>
                                <td style="color: #16a34a; font-weight: 600;">Payments Received:</td>
                                <td class="amt" style="color: #16a34a;">-&#8377;{{ number_format($totalPaid, 2) }}</td>
                            </tr>
                        @endif

                        <tr class="bal-row">
                            <td style="color: {{ $balDue > 0 ? '#dc2626' : '#16a34a' }};">Balance Due:</td>
                            <td class="amt" style="color: {{ $balDue > 0 ? '#dc2626' : '#16a34a' }}; font-size: 11.5px;">&#8377;{{ number_format($balDue, 2) }}</td>
                        </tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    <!-- ═══ 6. SIGNATURE & FOOTER ═══ -->
    <table class="footer-table" cellpadding="0" cellspacing="0">
        <tr>
            <td style="width: 60%; vertical-align: bottom;">
                <div class="footer-disclaimer">
                    Thank you for your business!<br>
                    This is a computer generated invoice and does not require physical signature.
                </div>
            </td>
            <td style="width: 40%; vertical-align: bottom;" class="sig-box">
                <div class="sig-company">For {{ tenant() ? tenant()->name : 'Demo Tenant' }}</div>
                <div class="sig-line">Authorized Signatory</div>
            </td>
        </tr>
    </table>

</body>
</html>
