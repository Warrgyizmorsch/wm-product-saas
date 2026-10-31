<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <title>Credit Note - {{ $return->return_number }}</title>
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

        /* ── PERSISTENT FOOTER ON EVERY PAGE ── */
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
            font-weight: bold;
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
        .doc-meta {
            text-align: right;
            font-size: 9.5px;
            color: #475569;
            margin-top: 6px;
        }
        .doc-meta td {
            padding: 1.5px 0 1.5px 10px;
        }

        /* ── TWO-COLUMN DETAILS SECTION ──────────── */
        .cards-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
            page-break-inside: avoid;
        }
        .card-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 10px 12px;
            vertical-align: top;
        }
        .card-heading {
            font-size: 8.5px;
            font-weight: bold;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 3px;
        }
        .client-avatar {
            display: inline-block;
            width: 28px;
            height: 28px;
            background-color: #1e40af;
            color: #ffffff;
            font-size: 13px;
            font-weight: bold;
            text-align: center;
            line-height: 28px;
            border-radius: 4px;
            vertical-align: middle;
            margin-right: 6px;
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
        .reason-box {
            font-size: 9.5px;
            color: #475569;
            border-top: 1px solid #e2e8f0;
            padding-top: 5px;
            margin-top: 5px;
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

        /* ── ITEMS TABLE ─────────────────────────── */
        .section-header {
            font-size: 11px;
            font-weight: bold;
            color: #0f172a;
            margin-bottom: 6px;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 4px;
        }
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
            vertical-align: middle;
        }
        .items-table tfoot td {
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
        .badge-completed {
            background-color: #dcfce7;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }
        .badge-approved {
            background-color: #e0f2fe;
            color: #0369a1;
            border: 1px solid #bae6fd;
        }
        .badge-cancelled {
            background-color: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }
        .badge-pending {
            background-color: #fef3c7;
            color: #b45309;
            border: 1px solid #fde68a;
        }

        /* ── SIGNATURE SECTION ───────────────────── */
        .bottom-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
            page-break-inside: avoid;
        }
        .sign-line {
            border-top: 1px solid #94a3b8;
            display: inline-block;
            width: 180px;
            padding-top: 4px;
            font-size: 9.5px;
            color: #475569;
            font-weight: bold;
            text-align: center;
        }
    </style>
</head>
<body>

    @php
        $tenantName = tenant() ? tenant()->name : 'Demo Tenant';
        $companyInitial = strtoupper(substr($tenantName, 0, 1));
        $customerName = $return->customer?->name ?: ($return->salesOrder?->customer?->name ?: 'Customer');
        $customerInitial = strtoupper(substr($customerName, 0, 1));
        
        $rawSymbol = active_currency_symbol();
        $currencyCode = active_currency();
        $currencySymbol = match($currencyCode) {
            'INR' => '&#8377;',
            'EUR' => '&euro;',
            'GBP' => '&pound;',
            default => ($rawSymbol === '₹' ? '&#8377;' : $rawSymbol),
        };

        $displayRefundTotal = $return->total_refund_amount > 0
            ? $return->total_refund_amount
            : ($return->total_amount > 0
                ? $return->total_amount
                : $return->items->sum(fn($i) => (float)$i->quantity * (float)$i->unit_price));
    @endphp

    <!-- ── PERSISTENT DOCUMENT FOOTER ── -->
    <footer class="doc-page-footer">
        <div class="footer-dashed-line"></div>
        <table class="footer-meta-table">
            <tr>
                <td style="text-align: left; width: 70%;">
                    Credit Note #: <strong>{{ $return->return_number }}</strong> &nbsp;|&nbsp; 
                    Date: <strong>{{ date('d-M-Y', strtotime($return->return_date)) }}</strong> &nbsp;|&nbsp; 
                    Customer: <strong>{{ $customerName }}</strong>
                </td>
                <td style="text-align: right; width: 30%; font-weight: bold; color: #475569;">
                    {{-- Page number filled dynamically by canvas script --}}
                </td>
            </tr>
        </table>
    </footer>

    <!-- ── 1. HEADER SECTION ── -->
    <table class="header-table">
        <tr>
            <td style="width: 58%; vertical-align: top;">
                @php
                    $sellerOrg = billing_organization_details($return);
                @endphp
                <table style="border-collapse: collapse;">
                    <tr>
                        <td style="vertical-align: top; width: 44px;">
                            <div class="company-avatar">{{ $sellerOrg['avatar_char'] }}</div>
                        </td>
                        <td style="vertical-align: top; padding-left: 8px;">
                            <div class="company-name">{{ $sellerOrg['name'] }}</div>
                            <div class="company-sub">{{ $sellerOrg['unit_subtext'] }}</div>
                        </td>
                    </tr>
                </table>
                <div class="company-address">
                    {{ $sellerOrg['address'] }}<br>
                    <strong>GSTIN:</strong> {{ $sellerOrg['gstin'] }} &nbsp;|&nbsp; <strong>State Code:</strong> {{ $sellerOrg['state_code'] }} ({{ $sellerOrg['state_name'] }})<br>
                    @if($sellerOrg['email'])
                        <strong>Email:</strong> {{ $sellerOrg['email'] }}
                    @endif
                    @if($sellerOrg['email'] && $sellerOrg['phone'])
                        &nbsp;|&nbsp;
                    @endif
                    @if($sellerOrg['phone'])
                        <strong>Phone:</strong> {{ $sellerOrg['phone'] }}
                    @endif
                </div>
            </td>
            <td style="width: 42%; vertical-align: top; text-align: right;">
                <div class="doc-title">CREDIT NOTE</div>
                <div class="doc-number"># {{ $return->return_number }}</div>

                <table class="doc-meta" style="margin-left: auto;">
                    <tr>
                        <td style="color: #64748b;">Return Date:</td>
                        <td style="font-weight: bold; color: #0f172a;">{{ date('d-M-Y', strtotime($return->return_date)) }}</td>
                    </tr>
                    @if ($return->salesOrder)
                        <tr>
                            <td style="color: #64748b;">Sales Order:</td>
                            <td style="font-weight: bold; color: #1e40af;">{{ $return->salesOrder->sales_order_number }}</td>
                        </tr>
                    @endif
                    <tr>
                        <td style="color: #64748b;">Status:</td>
                        <td>
                            @php
                                $badgeStyle = match($return->status) {
                                    'Completed' => 'badge-completed',
                                    'Approved'  => 'badge-approved',
                                    'Cancelled' => 'badge-cancelled',
                                    default     => 'badge-pending',
                                };
                            @endphp
                            <span class="badge-pill {{ $badgeStyle }}">{{ $return->status }}</span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- ── 2. TWO-COLUMN CARDS SECTION ── -->
    <table class="cards-table">
        <tr>
            <!-- Left Box: Customer Info -->
            <td class="card-box" style="width: 48%;">
                <div class="card-heading">CUSTOMER:</div>
                <table style="border-collapse: collapse; margin-bottom: 6px;">
                    <tr>
                        <td style="vertical-align: top; width: 34px;">
                            <div class="client-avatar">{{ $customerInitial }}</div>
                        </td>
                        <td style="vertical-align: top;">
                            <div class="client-name">{{ $customerName }}</div>
                            <div class="client-sub">Client Account</div>
                        </td>
                    </tr>
                </table>
                <div class="client-contact">
                    <strong>Email:</strong> {{ $return->customer?->email ?: ($return->salesOrder?->customer?->email ?: '—') }}<br>
                    <strong>Phone:</strong> {{ $return->customer?->phone ?: ($return->salesOrder?->customer?->phone ?: '—') }}
                </div>
                @if ($return->reason)
                    <div class="reason-box">
                        <strong style="color: #0f172a;">Reason for Return:</strong><br>
                        <span style="color: #64748b;">{{ $return->reason }}</span>
                    </div>
                @endif
            </td>

            <td style="width: 4%;"></td>

            <!-- Right Box: Return Summary -->
            <td class="card-box" style="width: 48%;">
                <div class="card-heading">RETURN DETAILS:</div>
                
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="padding: 4px 0; color: #64748b; font-size: 10px; border-bottom: 1px solid #e2e8f0;">Refund Amount:</td>
                        <td style="padding: 4px 0; font-weight: bold; font-size: 13px; color: #dc2626; text-align: right; border-bottom: 1px solid #e2e8f0;">
                            {!! $currencySymbol !!}{{ number_format((float) convert_from_base($displayRefundTotal), 2) }}
                        </td>
                    </tr>
                    @if ($return->salesOrder)
                        <tr>
                            <td style="padding: 4px 0; color: #64748b; font-size: 10px; border-bottom: 1px solid #e2e8f0;">Sales Order:</td>
                            <td style="padding: 4px 0; font-weight: bold; color: #1e40af; text-align: right; border-bottom: 1px solid #e2e8f0;">
                                {{ $return->salesOrder->sales_order_number }}
                            </td>
                        </tr>
                    @endif
                    <tr>
                        <td style="padding: 4px 0; color: #64748b; font-size: 10px; border-bottom: 1px solid #e2e8f0;">Return Date:</td>
                        <td style="padding: 4px 0; font-weight: bold; color: #0f172a; text-align: right; border-bottom: 1px solid #e2e8f0;">
                            {{ date('d-M-Y', strtotime($return->return_date)) }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 4px 0; color: #64748b; font-size: 10px;">Status:</td>
                        <td style="padding: 4px 0; font-weight: bold; text-align: right; color: {{ $return->status === 'Completed' ? '#15803d' : '#b45309' }};">
                            {{ $return->status === 'Completed' ? 'Restocked to Warehouse' : ($return->status ?? 'Draft') }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- ── 3. ITEMS TO RETURN TABLE ── -->
    <div class="section-header">Items to Return</div>
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 5%; text-align: center;">#</th>
                <th style="width: 45%; text-align: left;">Product Details & Serials</th>
                <th style="width: 25%; text-align: left;">Restock Warehouse</th>
                <th style="width: 10%; text-align: right;">Return Qty</th>
                <th style="width: 15%; text-align: right;">Refund Unit Price</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($return->items as $idx => $item)
                <tr>
                    <td style="text-align: center; color: #64748b;">{{ $idx + 1 }}</td>
                    <td>
                        <strong style="color: #0f172a;">{{ $item->product?->name ?: 'Item #' . ($idx + 1) }}</strong>
                        @if ($item->product?->sku)
                            <div style="font-size: 8.5px; color: #64748b; margin-top: 1px;">SKU: {{ $item->product->sku }}</div>
                        @endif
                        @if (!empty($item->serial_numbers))
                            <div style="margin-top: 3px; font-size: 8.5px; color: #0284c7;">
                                Returned Serials: {{ $item->serial_numbers }}
                            </div>
                        @endif
                    </td>
                    <td>
                        <span style="font-weight: bold; color: #334155;">{{ $item->warehouse?->name ?: 'Main Warehouse' }}</span>
                    </td>
                    <td style="text-align: right; font-weight: bold; color: #dc2626;">
                        {{ (int)$item->quantity }}
                    </td>
                    <td style="text-align: right; font-weight: bold; color: #0f172a;">
                        {!! $currencySymbol !!}{{ number_format((float) convert_from_base($item->unit_price), 2) }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" style="text-align: center; color: #64748b; padding: 12px;">No return items recorded.</td>
                </tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4" style="text-align: right; color: #475569; font-size: 10px;">Refund Amount:</td>
                <td style="text-align: right; font-size: 11px; font-weight: bold; color: #dc2626;">
                    {!! $currencySymbol !!}{{ number_format((float) convert_from_base($displayRefundTotal), 2) }}
                </td>
            </tr>
        </tfoot>
    </table>

    <!-- ── 4. SIGNATURE & VERIFICATION BLOCK ── -->
    <table class="bottom-table">
        <tr>
            <td style="width: 60%; vertical-align: top; font-size: 9px; color: #64748b;">
                <div>This is an official Credit Voucher / Sales Return document.</div>
                <div style="margin-top: 3px;">
                    Generated: {{ date('d M Y, h:i A') }} &nbsp;|&nbsp; {{ $tenantName }}
                </div>
            </td>
            <td style="width: 40%; vertical-align: top; text-align: right;">
                <div style="font-size: 10px; font-weight: bold; color: #0f172a; margin-bottom: 40px;">
                    For {{ $tenantName }}
                </div>
                <div class="sign-line">Authorized Signatory</div>
            </td>
        </tr>
    </table>

    <!-- ── DYNAMIC PAGE NUMBER SCRIPT (DOMPDF) ── -->
    <script type="text/php">
        if (isset($pdf)) {
            $font = $fontMetrics->get_font("DejaVu Sans", "normal");
            $size = 8.5;
            $color = array(0.39, 0.45, 0.55); // #64748b
            
            // X and Y coordinates for bottom right
            // A4 page width = 595.28 points, height = 841.89 points
            // Margins: right = 15mm (~42.5pt), bottom = 22mm (~62.3pt)
            $x = 510;
            $y = 818;
            $text = "Page {PAGE_NUM} of {PAGE_COUNT}";
            $pdf->page_text($x, $y, $text, $font, $size, $color);
        }
    </script>
</body>
</html>
