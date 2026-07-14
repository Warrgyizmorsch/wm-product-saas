<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Quotation {{ $quotation->quotation_number }}</title>
    <style>
        @page {
            margin: 10mm 15mm 10mm 15mm;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            color: #333;
            font-size: 11px;
            line-height: 1.4;
            margin: 0;
            padding: 0;
        }
        .container {
            width: 100%;
            margin: 0 auto;
        }
        .header {
            margin-bottom: 15px;
        }
        .logo-section {
            float: left;
            width: 50%;
        }
        .title-section {
            float: right;
            width: 50%;
            text-align: right;
        }
        .clear {
            clear: both;
        }
        .logo-avatar {
            background-color: #1e40af;
            color: #ffffff;
            font-size: 20px;
            font-weight: bold;
            width: 38px;
            height: 38px;
            line-height: 38px;
            text-align: center;
            border-radius: 4px;
            display: inline-block;
            margin-right: 8px;
            vertical-align: middle;
        }
        .tenant-name {
            font-size: 16px;
            font-weight: bold;
            color: #1e293b;
            display: inline-block;
            vertical-align: middle;
        }
        .estimate-title {
            font-size: 20px;
            font-weight: bold;
            color: #1e40af;
            margin: 0 0 3px 0;
        }
        .quotation-number {
            font-size: 12px;
            font-weight: bold;
            color: #334155;
        }
        .status-badge {
            font-size: 10px;
            padding: 1px 6px;
            border-radius: 10px;
            font-weight: bold;
            display: inline-block;
            margin-top: 3px;
            background-color: #f1f5f9;
            color: #475569;
        }
        .status-sent { background-color: #e0f2fe; color: #0369a1; }
        .status-accepted { background-color: #dcfce7; color: #15803d; }
        .status-rejected { background-color: #fee2e2; color: #b91c1c; }
        .status-pending { background-color: #fef9c3; color: #a16207; }

        .details-section {
            margin-bottom: 20px;
        }
        .bill-to {
            float: left;
            width: 50%;
        }
        .schedule {
            float: right;
            width: 50%;
            text-align: right;
        }
        .section-label {
            font-size: 9px;
            text-transform: uppercase;
            font-weight: bold;
            color: #64748b;
            margin-bottom: 3px;
            display: block;
        }
        .client-name {
            font-size: 14px;
            font-weight: bold;
            color: #0f172a;
            margin: 0 0 3px 0;
        }
        .client-info {
            color: #475569;
            margin: 1px 0;
            font-size: 11px;
        }
        .table-items {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        .table-items th {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 6px 8px;
            font-size: 10px;
            text-transform: uppercase;
            color: #64748b;
            font-weight: bold;
            text-align: left;
        }
        .table-items td {
            border: 1px solid #e2e8f0;
            padding: 6px 8px;
            vertical-align: top;
            font-size: 11px;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        
        .calc-box {
            border: 1px solid #e2e8f0;
            background-color: #f8fafc;
            border-radius: 4px;
            padding: 10px;
        }
        
        .terms-conditions-container, .terms-conditions-container p, .terms-conditions-container li {
            font-size: 9.5px !important;
            line-height: 1.3 !important;
            margin: 0 0 3px 0 !important;
            color: #475569;
        }

        .client-notes-container {
            font-size: 9.5px !important;
            line-height: 1.3 !important;
            color: #475569;
        }

        .signature-section {
            margin-top: 30px;
        }
        .footer-note {
            float: left;
            width: 60%;
            font-size: 10px;
            color: #64748b;
            margin-top: 25px;
        }
        .signature-box {
            float: right;
            width: 30%;
            text-align: center;
        }
        .signature-line {
            border-top: 1px solid #94a3b8;
            margin-top: 25px;
            padding-top: 4px;
            font-size: 10px;
            color: #64748b;
            text-transform: uppercase;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="logo-section">
                <div class="logo-avatar">
                    {{ strtoupper(substr(tenant() ? tenant()->name : 'E', 0, 1)) }}
                </div>
                <div class="tenant-name">{{ tenant() ? tenant()->name : 'SaaS ERP Workspace' }}</div>
            </div>
            <div class="title-section">
                <h1 class="estimate-title">ESTIMATE</h1>
                <div class="quotation-number">No: {{ $quotation->quotation_number }}</div>
                @php
                    $badgeClass = '';
                    if ($quotation->status === 'Sent' || $quotation->status === 'Quotation Sent') $badgeClass = 'status-sent';
                    elseif ($quotation->status === 'Accepted' || $quotation->status === 'Approved') $badgeClass = 'status-accepted';
                    elseif ($quotation->status === 'Declined' || $quotation->status === 'Rejected') $badgeClass = 'status-rejected';
                    elseif ($quotation->status === 'Pending Approval') $badgeClass = 'status-pending';
                @endphp
                <span class="status-badge {{ $badgeClass }}">{{ $quotation->status }}</span>
            </div>
            <div class="clear"></div>
        </div>

        <hr style="border: 0; border-top: 1px solid #e2e8f0; margin-bottom: 15px;">

        <!-- Details -->
        <div class="details-section">
            <div class="bill-to">
                <span class="section-label">Prepared For</span>
                <h3 class="client-name" style="margin: 0 0 3px 0;">{{ $quotation->customer?->name ?? ($quotation->lead?->company_name ?? '—') }}</h3>
                <div class="client-info"><strong style="color: #64748b;">Email:</strong> {{ $quotation->customer?->email ?: ($quotation->lead?->email ?: '—') }}</div>
                <div class="client-info"><strong style="color: #64748b;">Phone:</strong> {{ $quotation->customer?->phone ?: ($quotation->lead?->phone ?: '—') }}</div>
                
                @if($quotation->lead && ($quotation->lead->address || $quotation->lead->city || $quotation->lead->state || $quotation->lead->country))
                    <div style="margin-top: 10px;">
                        <span class="section-label" style="font-size: 8px;">Billing Address</span>
                        @if($quotation->lead->address)
                            <div class="client-info">{{ $quotation->lead->address }}</div>
                        @endif
                        <div class="client-info">
                            {{ implode(', ', array_filter([$quotation->lead->city, $quotation->lead->state, $quotation->lead->country])) }}
                        </div>
                    </div>
                @endif
            </div>
            <div class="schedule">
                <span class="section-label">Quotation Schedule</span>
                <div class="client-info"><strong>Quotation Date:</strong> {{ $quotation->quotation_date ? $quotation->quotation_date->format('d/m/Y') : '—' }}</div>
                <div class="client-info"><strong>Valid Until:</strong> {{ $quotation->expiry_date ? $quotation->expiry_date->format('d/m/Y') : '—' }}</div>
                @if($quotation->salesPerson)
                    <div class="client-info"><strong>Sales Rep:</strong> {{ $quotation->salesPerson->name }}</div>
                @endif
            </div>
            <div class="clear"></div>
        </div>

        <!-- Items Table -->
        <table class="table-items">
            <thead>
                <tr>
                    <th class="text-center" style="width: 5%;">#</th>
                    <th style="width: 45%;">Description of Service / Product</th>
                    <th class="text-center" style="width: 10%;">Qty</th>
                    <th class="text-right" style="width: 15%;">Unit Price (₹)</th>
                    <th class="text-right" style="width: 10%;">Tax Rate</th>
                    <th class="text-right" style="width: 15%;">Amount (₹)</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($quotation->items as $index => $item)
                    <tr>
                        <td class="text-center" style="color: #64748b;">{{ $index + 1 }}</td>
                        <td>
                            <strong>{{ $item->item_name }}</strong>
                            @if($item->description)
                                <div style="font-size: 10px; color: #64748b; margin-top: 1px;">{{ $item->description }}</div>
                            @endif
                        </td>
                        <td class="text-center">{{ $item->quantity }}</td>
                        <td class="text-right">₹{{ number_format($item->unit_price, 2) }}</td>
                        <td class="text-right">{{ number_format($item->tax_rate, 2) }}%</td>
                        <td class="text-right" style="font-weight: bold;">₹{{ number_format($item->amount, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <!-- Bottom section (Terms & Conditions and Calculations side-by-side using float) -->
        <div style="margin-top: 10px; width: 100%;">
            <!-- Calculations (Right Column) - Listed first in HTML so it anchors on Page 1 -->
            <div style="float: right; width: 42%;">
                <div class="calc-box">
                    <table style="width: 100%; border-collapse: collapse; font-size: 11px;">
                        <tr>
                            <td style="color: #64748b; padding: 4px 0; text-align: left; border: 0;">Subtotal:</td>
                            <td style="text-align: right; font-weight: bold; color: #1e293b; padding: 4px 0; border: 0;">₹{{ number_format($quotation->subtotal, 2) }}</td>
                        </tr>
                        <tr>
                            <td style="color: #64748b; padding: 4px 0; text-align: left; border: 0;">Tax total (GST):</td>
                            <td style="text-align: right; font-weight: bold; color: #1e293b; padding: 4px 0; border: 0;">₹{{ number_format($quotation->tax, 2) }}</td>
                        </tr>
                        @if($quotation->discount > 0)
                            <tr style="color: #b91c1c;">
                                <td style="padding: 4px 0; text-align: left; border: 0;">Discount:</td>
                                <td style="text-align: right; font-weight: bold; padding: 4px 0; border: 0;">-₹{{ number_format($quotation->discount, 2) }}</td>
                            </tr>
                        @endif
                        <tr style="border-top: 1px solid #cbd5e1;">
                            <td style="font-weight: bold; color: #0f172a; padding: 6px 0 0 0; font-size: 11px; text-align: left; border: 0;">Total Payable:</td>
                            <td style="text-align: right; font-weight: bold; color: #1e40af; padding: 6px 0 0 0; font-size: 11px; border: 0;">₹{{ number_format($quotation->total_amount, 2) }}</td>
                        </tr>
                    </table>
                </div>
            </div>

            <!-- Terms & Conditions (Left Column) - Listed second so it flows naturally -->
            <div style="float: left; width: 53%;">
                @if($quotation->terms_conditions)
                    <h4 style="font-size: 10px; text-transform: uppercase; color: #0f172a; margin: 0 0 4px 0;">Terms & Conditions</h4>
                    <div class="terms-conditions-container">{!! $quotation->terms_conditions !!}</div>
                @endif

                @if($quotation->notes)
                    <h4 style="font-size: 10px; text-transform: uppercase; color: #0f172a; margin: 8px 0 2px 0;">Client Notes</h4>
                    <p class="client-notes-container" style="margin: 0; white-space: pre-line;">{{ $quotation->notes }}</p>
                @endif
            </div>
            <div class="clear"></div>
        </div>

        <!-- Signature -->
        <div class="signature-section">
            <div class="clear"></div>
            <div class="footer-note">
                For any questions concerning this quotation, contact sales office.
            </div>
            <div class="signature-box">
                <div class="signature-line">Authorized Signature</div>
            </div>
            <div class="clear"></div>
        </div>
    </div>
</body>
</html>
