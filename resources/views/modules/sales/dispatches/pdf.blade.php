<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Challan - {{ $dispatch->dispatch_number }}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/feather-icons/dist/feather.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            font-size: 12px;
            color: #1e293b;
            background-color: #f1f5f9;
            line-height: 1.5;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        /* ── Screen Action Bar ── */
        .action-bar {
            background-color: #0f172a;
            color: #ffffff;
            padding: 12px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .action-title {
            font-size: 14px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
            letter-spacing: 0.3px;
        }
        .btn-group {
            display: flex;
            gap: 10px;
        }
        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 16px;
            font-size: 12px;
            font-weight: 600;
            border-radius: 6px;
            text-decoration: none;
            cursor: pointer;
            border: none;
            transition: all 0.2s ease;
        }
        .btn-print {
            background-color: #714B67;
            color: #ffffff;
        }
        .btn-print:hover {
            background-color: #583950;
        }
        .btn-back {
            background-color: #334155;
            color: #f8fafc;
        }
        .btn-back:hover {
            background-color: #475569;
        }

        /* ── A4 Sheet Wrapper ── */
        .page-wrapper {
            padding: 30px 15px;
            display: flex;
            justify-content: center;
        }

        .a4-card {
            background-color: #ffffff;
            width: 100%;
            max-width: 820px;
            min-height: 1050px;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            position: relative;
        }

        /* ── Document Header ── */
        .doc-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding-bottom: 20px;
            border-bottom: 2px solid #714B67;
            margin-bottom: 24px;
        }

        .company-brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .company-logo-avatar {
            width: 46px;
            height: 46px;
            background-color: #714B67;
            color: #ffffff;
            font-size: 20px;
            font-weight: 800;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-transform: uppercase;
        }
        .company-name {
            font-size: 18px;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.3px;
        }
        .company-sub {
            font-size: 11px;
            color: #64748b;
            margin-top: 2px;
        }

        .challan-title-block {
            text-align: right;
        }
        .doc-type-title {
            font-size: 22px;
            font-weight: 900;
            color: #714B67;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .challan-no {
            font-size: 14px;
            font-weight: 700;
            font-family: monospace;
            color: #1e293b;
            margin-top: 2px;
        }
        .meta-text {
            font-size: 11px;
            color: #64748b;
            margin-top: 4px;
        }

        /* ── Info Cards Grid ── */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 24px;
        }
        .info-card {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 14px 16px;
        }
        .card-label {
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            margin-bottom: 8px;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .card-title {
            font-size: 13px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 4px;
        }
        .card-detail {
            font-size: 11px;
            color: #334155;
            margin-bottom: 3px;
            line-height: 1.4;
        }

        /* ── Items Table ── */
        .table-wrap {
            margin-bottom: 24px;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }
        .items-table th {
            background-color: #714B67;
            color: #ffffff;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 10px 12px;
            text-align: left;
        }
        .items-table th:first-child { border-top-left-radius: 6px; }
        .items-table th:last-child { border-top-right-radius: 6px; text-align: right; }
        .items-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
        }
        .items-table tr:nth-child(even) td {
            background-color: #f8fafc;
        }
        .item-name {
            font-weight: 700;
            color: #0f172a;
            font-size: 12px;
        }
        .item-sku {
            font-family: monospace;
            font-size: 10px;
            color: #64748b;
        }
        .tag-badge {
            display: inline-block;
            font-family: monospace;
            font-size: 10px;
            font-weight: 600;
            padding: 2px 6px;
            border-radius: 4px;
            margin-top: 4px;
        }
        .tag-batch { background-color: #e0f2fe; color: #0369a1; }
        .tag-serial { background-color: #dcfce7; color: #15803d; }

        /* ── Remarks & Notes ── */
        .notes-box {
            background-color: #fffbeb;
            border: 1px solid #fef3c7;
            border-radius: 6px;
            padding: 12px 14px;
            margin-bottom: 40px;
            font-size: 11px;
        }
        .notes-title {
            font-weight: 700;
            color: #92400e;
            margin-bottom: 3px;
        }

        /* ── Signatures ── */
        .signatures-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 20px;
            margin-top: 60px;
        }
        .sig-block {
            text-align: center;
            border-top: 1px dashed #cbd5e1;
            padding-top: 8px;
            font-size: 11px;
            font-weight: 600;
            color: #475569;
        }

        /* ── Print Specific Styles ── */
        @media print {
            .no-print { display: none !important; }
            body { background-color: #ffffff; padding: 0; }
            .page-wrapper { padding: 0; }
            .a4-card {
                box-shadow: none;
                border: none;
                border-radius: 0;
                padding: 20px 24px;
                max-width: 100%;
                width: 100%;
            }
        }
    </style>
</head>
<body>

    <!-- Top Action Bar (No Print) -->
    <div class="action-bar no-print">
        <div class="action-title">
            <span>📦 Delivery Challan Document Preview</span>
            <span style="opacity: 0.5;">|</span>
            <span style="font-family: monospace; font-size: 12px; font-weight: normal; color: #cbd5e1;">{{ $dispatch->dispatch_number }}</span>
        </div>
        <div class="btn-group">
            <a href="{{ route('sales.dispatches.show', $dispatch->id) }}" class="btn-action btn-back">
                <i class="feather-arrow-left"></i> Back to Dispatch Order
            </a>
            <button onclick="window.print()" class="btn-action btn-print">
                <i class="feather-printer"></i> Print Delivery Challan (A4)
            </button>
        </div>
    </div>

    <!-- A4 Paper Container -->
    <div class="page-wrapper">
        <div class="a4-card">
            
            <!-- Header -->
            <div class="doc-header">
                <div class="company-brand">
                    @php
                        $tenant = auth()->user()?->tenant;
                        $companyName = $tenant?->name ?? config('app.name', 'ERP SAAS');
                        $initials = strtoupper(substr($companyName, 0, 2));
                    @endphp
                    <div class="company-logo-avatar">{{ $initials }}</div>
                    <div>
                        <div class="company-name">{{ $companyName }}</div>
                        <div class="company-sub">Authorized Outward Material Delivery Challan</div>
                    </div>
                </div>
                <div class="challan-title-block">
                    <div class="doc-type-title">DELIVERY CHALLAN</div>
                    <div class="challan-no">{{ $dispatch->dispatch_number }}</div>
                    <div class="meta-text">Date: <strong>{{ $dispatch->dispatch_date ? $dispatch->dispatch_date->format('d/m/Y') : date('d/m/Y') }}</strong></div>
                </div>
            </div>

            <!-- Addresses & Transporter Info -->
            <div class="info-grid">
                <!-- Ship To -->
                <div class="info-card">
                    <div class="card-label">📍 Ship To (Consignee Destination)</div>
                    <div class="card-title">{{ $dispatch->customer?->name ?: 'Direct Customer Outward' }}</div>
                    @if($dispatch->shipping_address)
                        <div class="card-detail">{!! nl2br(e($dispatch->shipping_address)) !!}</div>
                    @elseif($dispatch->customer?->address)
                        <div class="card-detail">{!! nl2br(e($dispatch->customer->address)) !!}</div>
                    @endif
                    @if($dispatch->customer?->phone)
                        <div class="card-detail" style="margin-top: 4px; font-weight: 600;">Phone: {{ $dispatch->customer->phone }}</div>
                    @endif
                </div>

                <!-- Logistics & Transporter Details -->
                <div class="info-card">
                    <div class="card-label">🚚 Transporter & Logistics Info</div>
                    <div class="card-detail">Transporter Master: <strong>{{ $dispatch->transporter?->name ?: ($dispatch->carrier ?: 'Self Pickup / Direct') }}</strong></div>
                    @if($dispatch->transporter?->transporter_id)
                        <div class="card-detail">Transporter ID: <span style="font-family: monospace; font-weight: bold;">{{ $dispatch->transporter->transporter_id }}</span></div>
                    @endif
                    @if($dispatch->carrier)
                        <div class="card-detail">Carrier / Courier: <strong>{{ $dispatch->carrier }}</strong></div>
                    @endif
                    @if($dispatch->vehicle_number)
                        <div class="card-detail" style="margin-top: 4px;">Vehicle #: <strong style="font-family: monospace; text-transform: uppercase;">{{ $dispatch->vehicle_number }}</strong></div>
                    @endif
                    @if($dispatch->driver_name)
                        <div class="card-detail">Driver Name: <strong>{{ $dispatch->driver_name }}</strong> {{ $dispatch->driver_phone ? "({$dispatch->driver_phone})" : '' }}</div>
                    @endif
                </div>
            </div>

            <!-- Items Table -->
            <div class="table-wrap">
                <table class="items-table">
                    <thead>
                        <tr>
                            <th style="width: 5%; text-align: center;">#</th>
                            <th style="width: 45%;">Item Description & SKU</th>
                            <th style="width: 25%;">Warehouse Location</th>
                            <th style="width: 15%; text-align: right;">Dispatched Qty</th>
                            <th style="width: 10%; text-align: right;">UOM</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($dispatch->items as $index => $item)
                            <tr>
                                <td style="text-align: center; color: #64748b;">{{ $index + 1 }}</td>
                                <td>
                                    <div class="item-name">{{ $item->product?->name ?: 'Item' }}</div>
                                    @if($item->product?->sku)
                                        <div class="item-sku">SKU: {{ $item->product->sku }}</div>
                                    @endif
                                    @if($item->batch_number)
                                        <div class="tag-badge tag-batch">Batch #: {{ $item->batch_number }}</div>
                                    @endif
                                    @if($item->serial_numbers)
                                        <div class="tag-badge tag-serial">Serials: {{ is_array($item->serial_numbers) ? implode(', ', $item->serial_numbers) : $item->serial_numbers }}</div>
                                    @endif
                                </td>
                                <td>{{ $item->warehouse?->name ?: 'Main Warehouse' }}</td>
                                <td style="text-align: right; font-weight: 800; font-size: 12px; color: #0f172a;">
                                    {{ number_format($item->quantity_dispatched, 2) }}
                                </td>
                                <td style="text-align: right; color: #475569;">
                                    {{ $item->product?->uom?->code ?: 'Pcs' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 20px; color: #94a3b8;">No items listed.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Notes Section -->
            @if($dispatch->notes)
                <div class="notes-box">
                    <div class="notes-title">📝 Special Dispatch Notes & Instructions:</div>
                    <div>{{ $dispatch->notes }}</div>
                </div>
            @endif

            <!-- Signatures -->
            <div class="signatures-grid">
                <div class="sig-block">
                    Prepared By<br>
                    <span style="font-size: 10px; color: #94a3b8;">(Store Executive)</span>
                </div>
                <div class="sig-block">
                    Checked By<br>
                    <span style="font-size: 10px; color: #94a3b8;">(Security / Quality Officer)</span>
                </div>
                <div class="sig-block">
                    Received By<br>
                    <span style="font-size: 10px; color: #94a3b8;">(Customer Stamp & Sign)</span>
                </div>
            </div>

        </div>
    </div>

</body>
</html>
