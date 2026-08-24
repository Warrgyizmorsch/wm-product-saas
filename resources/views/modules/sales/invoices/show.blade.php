@extends('layouts.duralux')

@section('title', 'Tax Invoice ' . $invoice->invoice_number . ' | SaaS ERP')
@section('page-title', 'Tax Invoice')
@section('breadcrumb')
    <a href="{{ route('sales.invoices.index') }}">Invoices</a> &gt; {{ $invoice->invoice_number }}
@endsection

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <a href="{{ route('sales.invoices.index') }}" class="action-dropdown-btn" title="Back to Invoices" data-bs-toggle="tooltip">
            <i class="feather feather-arrow-left"></i>
        </a>

        <a href="javascript:void(0)" onclick="window.print()" class="btn btn-sm btn-outline-secondary fw-bold px-3">
            <i class="feather-printer me-1.5"></i>Print
        </a>

        @if ($invoice->status === 'Draft')
            <form action="{{ route('sales.invoices.send', $invoice->id) }}" method="POST" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-sm btn-primary fw-bold px-3">
                    <i class="feather-send me-1.5"></i>Mark as Sent
                </button>
            </form>
        @endif

        @if (in_array($invoice->status, ['Sent', 'Partially Paid', 'Posted', 'Draft']))
            <a href="{{ route('sales.payments.create', ['invoice_id' => $invoice->id, 'customer_id' => $invoice->customer_id ?: $invoice->salesOrder?->customer_id]) }}" class="btn btn-sm btn-success fw-bold px-3">
                <i class="feather-dollar-sign me-1.5"></i>Register Payment
            </a>
        @endif
    </div>
@endsection

@push('styles')
    <style>
        .invoice-sheet {
            max-width: 900px;
            margin: 0 auto;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            padding: 40px;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            position: relative;
            overflow: hidden;
        }

        .invoice-corner-ribbon {
            position: absolute;
            top: 0;
            right: 0;
            width: 80px;
            height: 80px;
            overflow: hidden;
            pointer-events: none;
            z-index: 10;
        }

        .invoice-corner-ribbon .ribbon-inner {
            position: absolute;
            top: 16px;
            right: -24px;
            width: 105px;
            transform: rotate(45deg);
            text-align: center;
            font-size: 9px;
            font-weight: 800;
            letter-spacing: 1px;
            text-transform: uppercase;
            padding: 4px 0;
            color: #ffffff;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.25);
        }

        .ribbon-paid {
            background-color: #16a34a;
        }
        .ribbon-partially-paid {
            background-color: #d97706;
        }
        .ribbon-sent {
            background-color: #16a34a;
        }
        .ribbon-draft {
            background-color: #64748b;
        }
        .ribbon-cancelled {
            background-color: #dc2626;
        }

        .invoice-table th {
            background-color: #f8fafc;
            color: #475569;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-top: 1px solid #e2e8f0;
            border-bottom: 2px solid #cbd5e1;
            padding: 8px 12px;
        }
        .invoice-table td {
            padding: 8px 12px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 12px;
            color: #1e293b;
        }

        .summary-table td {
            padding: 4px 10px;
            font-size: 12px;
        }

        @media print {
            @page {
                size: A4 portrait;
                margin: 6mm 8mm;
            }

            /* Hide top header, sidebar, buttons, and navigation */
            .nxl-navigation,
            .nxl-header,
            .page-header,
            .page-actions,
            .action-dropdown-btn,
            footer,
            .invoice-corner-ribbon,
            .d-print-none,
            .btn {
                display: none !important;
            }

            /* Reset page wrappers for full height & width printing */
            html, body {
                background: #ffffff !important;
                margin: 0 !important;
                padding: 0 !important;
                height: auto !important;
                overflow: visible !important;
                font-size: 11px !important;
            }

            .nxl-container,
            .nxl-content,
            .main-content {
                margin: 0 !important;
                padding: 0 !important;
                width: 100% !important;
                max-width: 100% !important;
            }

            /* Invoice sheet: static layout so browser can split across pages */
            .invoice-sheet {
                position: static !important;
                width: 100% !important;
                max-width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
                border: none !important;
                box-shadow: none !important;
                border-radius: 0 !important;
                background: #ffffff !important;
                overflow: visible !important;
            }

            /* Ensure background colors in cards & headers print cleanly */
            .invoice-sheet .bg-light,
            .invoice-sheet [class*="bg-light"] {
                background-color: #f8fafc !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .invoice-sheet .invoice-table thead th {
                background-color: #f8fafc !important;
                padding: 4px 6px !important;
                font-size: 10px !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .invoice-sheet .invoice-table td {
                padding: 4px 6px !important;
                font-size: 11px !important;
            }

            .invoice-sheet .p-3 {
                padding: 6px 10px !important;
            }

            .invoice-sheet .mb-4,
            .invoice-sheet .mb-3 {
                margin-bottom: 8px !important;
            }

            .invoice-sheet .mt-4,
            .invoice-sheet .mt-5 {
                margin-top: 8px !important;
            }

            .invoice-sheet .pb-4 {
                padding-bottom: 6px !important;
            }

            .invoice-sheet fs-12,
            .invoice-sheet .fs-12,
            .invoice-sheet .fs-13 {
                font-size: 11px !important;
            }

            /* Avoid breaking inside table rows */
            tr {
                page-break-inside: avoid;
                break-inside: avoid;
            }

            /* Strict Page Break Protection for Cards */
            .bank-details-box,
            .gst-summary-box,
            .summary-table-box {
                page-break-inside: avoid !important;
                break-inside: avoid !important;
            }

            /* Terms Box & Lists: Allow natural splitting across pages without card border clipping */
            .terms-box {
                border: none !important;
                background: transparent !important;
                padding: 0 !important;
                margin-top: 6px !important;
                page-break-inside: auto !important;
                break-inside: auto !important;
            }

            .terms-box ol {
                list-style-type: decimal !important;
                padding-left: 20px !important;
                margin-left: 0 !important;
                page-break-inside: auto !important;
                break-inside: auto !important;
            }

            .terms-box ul {
                list-style-type: disc !important;
                padding-left: 20px !important;
                margin-left: 0 !important;
                page-break-inside: auto !important;
                break-inside: auto !important;
            }

            /* Restore list-item display so numbers (1., 2., 3...) show cleanly in print */
            .terms-box ol > li,
            .terms-box ul > li,
            .terms-box li {
                display: list-item !important;
                list-style-position: outside !important;
                page-break-inside: avoid !important;
                break-inside: avoid !important;
                break-inside: avoid-page !important;
                margin-bottom: 4px !important;
            }

            .terms-box p {
                display: block !important;
                page-break-inside: avoid !important;
                break-inside: avoid !important;
                break-inside: avoid-page !important;
                margin-bottom: 4px !important;
            }

            .terms-box li *,
            .terms-box p * {
                page-break-inside: avoid !important;
                break-inside: avoid !important;
            }

            .signature-footer-block {
                page-break-inside: avoid !important;
                break-inside: avoid !important;
                margin-top: 10px !important;
                padding-top: 8px !important;
            }
        }
    </style>
@endpush

@section('content')
    <div class="row text-dark">
        <div class="col-12">

            <!-- Standard ERP Customer Tax Invoice Sheet (Odoo / Zoho Standard) -->
            <div class="invoice-sheet print-area mb-5">
                <!-- Status Corner Ribbon Tag Patti -->
                <div class="invoice-corner-ribbon">
                    @php
                        $ribbonClass = match($invoice->status) {
                            'Paid' => 'ribbon-paid',
                            'Partially Paid' => 'ribbon-partially-paid',
                            'Sent' => 'ribbon-sent',
                            'Cancelled' => 'ribbon-cancelled',
                            default => 'ribbon-draft',
                        };
                    @endphp
                    <div class="ribbon-inner {{ $ribbonClass }}">
                        {{ $invoice->status }}
                    </div>
                </div>

                <!-- 1. Invoice Document Header -->
                <div class="row align-items-start pb-4 border-bottom mb-4">
                    <div class="col-7">
                        <div class="d-flex align-items-center mb-3">
                            <div class="avatar-text bg-primary text-white fs-2 fw-bold me-3 shadow-sm d-flex align-items-center justify-content-center" style="border-radius: 8px; width: 50px; height: 50px; flex-shrink: 0; background-color: #1e40af !important;">
                                {{ strtoupper(substr(tenant() ? tenant()->name : 'ERP', 0, 1)) }}
                            </div>
                            <div>
                                <h4 class="fw-bold text-dark mb-0 fs-17">{{ tenant() ? tenant()->name : 'SaaS ERP Workspace' }}</h4>
                                <span class="fs-11 text-muted">Official Corporate Billing Unit</span>
                            </div>
                        </div>
                        <div class="fs-12 text-secondary leading-relaxed">
                            <div>H-1, Industrial Area, Sukher, Udaipur 313001, Rajasthan, India</div>
                            <div><strong>GSTIN:</strong> 08AAFCS1234E1Z0 &nbsp;|&nbsp; <strong>State Code:</strong> 08 (Rajasthan)</div>
                            <div><strong>Email:</strong> {{ tenant() ? tenant()->billing_email : 'billing@sasserp.com' }} &nbsp;|&nbsp; <strong>Phone:</strong> +91 294 2440230</div>
                        </div>
                    </div>

                    <div class="col-5 text-end">
                        <h2 class="fw-black text-uppercase tracking-wide mb-1" style="color: #1e40af; font-size: 22px; letter-spacing: 1px;">TAX INVOICE</h2>
                        <div class="fs-14 fw-bold text-dark"># {{ $invoice->invoice_number }}</div>
                        
                        <div class="mt-3 fs-12 text-secondary">
                            <div class="d-flex justify-content-end gap-2 mb-1">
                                <span class="text-muted">Invoice Date:</span>
                                <strong class="text-dark">{{ date('d-M-Y', strtotime($invoice->invoice_date)) }}</strong>
                            </div>
                            <div class="d-flex justify-content-end gap-2 mb-1">
                                <span class="text-muted">Due Date:</span>
                                <strong class="text-dark">{{ $invoice->due_date ? date('d-M-Y', strtotime($invoice->due_date)) : '—' }}</strong>
                            </div>
                            <div class="d-flex justify-content-end gap-2">
                                <span class="text-muted">Terms:</span>
                                <strong class="text-dark">{{ $invoice->salesOrder?->payment_terms ?: 'Immediate Payment' }}</strong>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. Address & Order Details Section (2 Box Columns) -->
                <div class="row g-4 mb-4 fs-12 text-dark">
                    <!-- Left: Customer Details -->
                    <div class="col-6">
                        <div class="p-3 bg-light bg-opacity-40 rounded border h-100">
                            <span class="fs-10 fw-bold text-uppercase text-muted d-block mb-2" style="letter-spacing: 0.5px;">Billed To (Customer):</span>
                            <h6 class="fw-bold text-dark mb-1 fs-14">{{ $invoice->customer?->name ?: ($invoice->salesOrder?->customer?->name ?: '—') }}</h6>
                            @if($invoice->customer?->company_name)
                                <div class="text-muted fw-semibold mb-1">{{ $invoice->customer->company_name }}</div>
                            @endif
                            <div class="text-secondary mb-1">
                                <i class="feather-mail me-1 fs-11 text-muted"></i>{{ $invoice->customer?->email ?: ($invoice->salesOrder?->customer?->email ?: '—') }}
                                &nbsp;|&nbsp;
                                <i class="feather-phone me-1 fs-11 text-muted"></i>{{ $invoice->customer?->phone ?: ($invoice->salesOrder?->customer?->phone ?: '—') }}
                            </div>
                            @if ($invoice->salesOrder?->billing_address)
                                <div class="mt-2 pt-2 border-top text-secondary fs-11" style="white-space: pre-wrap;">
                                    <strong>Billing Address:</strong><br>{{ $invoice->salesOrder->billing_address }}
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Right: Order References & Dispatch Info -->
                    <div class="col-6">
                        <div class="p-3 bg-light bg-opacity-40 rounded border h-100">
                            <span class="fs-10 fw-bold text-uppercase text-muted d-block mb-2" style="letter-spacing: 0.5px;">Order & Dispatch References:</span>
                            
                            @if ($invoice->salesOrder)
                                <div class="d-flex justify-content-between mb-2 pb-1 border-bottom">
                                    <span class="text-muted">Sales Order Ref:</span>
                                    <a href="{{ route('sales.orders.show', $invoice->sales_order_id) }}" class="fw-bold text-primary">
                                        {{ $invoice->salesOrder->sales_order_number }}
                                    </a>
                                </div>
                            @endif

                            @if ($invoice->materialRequirement)
                                <div class="d-flex justify-content-between mb-2 pb-1 border-bottom">
                                    <span class="text-muted">Dispatch Ref:</span>
                                    <a href="{{ route('sales.material-requirements.show', $invoice->material_requirement_id) }}" class="fw-bold text-info">
                                        {{ $invoice->materialRequirement->requirement_number }}
                                    </a>
                                </div>
                            @endif

                            <div class="d-flex justify-content-between mb-2 pb-1 border-bottom">
                                <span class="text-muted">Place of Supply:</span>
                                <span class="fw-semibold text-dark">Rajasthan (08)</span>
                            </div>

                            <div class="d-flex justify-content-between mb-2 pb-1 border-bottom">
                                <span class="text-muted">GST Option:</span>
                                <span class="fw-bold text-dark">{{ $invoice->gst_type === 'igst' ? 'IGST (Inter-State)' : 'CGST + SGST (Intra-State)' }}</span>
                            </div>

                            <div class="d-flex justify-content-between">
                                <span class="text-muted">Payment Status:</span>
                                <span class="fw-bold {{ $balanceDue > 0 ? 'text-danger' : 'text-success' }}">{{ $balanceDue > 0 ? 'Balance Outstanding' : 'Fully Paid' }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 3. Line Items Table (Odoo / Zoho Clean Invoice Standard) -->
                <div class="mb-4">
                    <div class="table-responsive">
                        <table class="table invoice-table align-middle w-100 mb-0">
                            <thead>
                                <tr>
                                    <th style="width: 5%;" class="text-center">#</th>
                                    <th style="width: 45%;">Item & Description</th>
                                    <th class="text-end" style="width: 12%;">Qty</th>
                                    <th class="text-end" style="width: 13%;">Rate (₹)</th>
                                    <th class="text-end" style="width: 10%;">Tax %</th>
                                    <th class="text-end" style="width: 15%;">Amount (₹)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($invoice->items as $idx => $item)
                                    <tr>
                                        <td class="text-center text-muted fs-12">{{ $idx + 1 }}</td>
                                        <td>
                                            <strong class="text-dark d-block fs-13">{{ $item->product?->name ?: $item->item_name }}</strong>
                                            @if($item->product?->sku)
                                                <span class="text-muted fs-11">SKU: {{ $item->product->sku }}</span>
                                            @endif
                                            @if($item->description)
                                                <span class="text-muted fs-11 d-block">{{ $item->description }}</span>
                                            @endif
                                        </td>
                                        <td class="text-end fw-semibold">{{ (float)$item->quantity }}</td>
                                        <td class="text-end">₹{{ number_format($item->unit_price, 2) }}</td>
                                        <td class="text-end text-muted">{{ (float)$item->tax_rate }}%</td>
                                        <td class="text-end fw-bold text-dark">
                                            @php
                                                $lineTotal = ($item->total_amount > 0)
                                                    ? $item->total_amount
                                                    : ($item->subtotal > 0 ? $item->subtotal : ($item->quantity * $item->unit_price));
                                            @endphp
                                            ₹{{ number_format($lineTotal, 2) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- 4. Summary & Calculations Row -->
                <div class="row pt-2 fs-13 text-dark mb-3">
                    <!-- Left: GST Tax Rate Summary -->
                    <div class="col-7">
                        @php
                            $taxGroups = $invoice->items->groupBy(fn($item) => (string)(float)$item->tax_rate);
                        @endphp
                        @if ($taxGroups->count() > 0 && $invoice->tax_amount > 0)
                            <div class="card border shadow-none mb-0 gst-summary-box" style="border-radius: 6px; overflow: hidden; border-color: #cbd5e1 !important;">
                                <div class="py-1 px-3 bg-light border-bottom text-muted fw-bold fs-11 text-uppercase d-flex justify-content-between align-items-center">
                                    <span><i class="feather-pie-chart me-1 text-primary"></i>GST Tax Summary</span>
                                    <span class="badge bg-soft-primary text-primary fs-10" style="font-size: 10px;">{{ $invoice->gst_type === 'igst' ? 'IGST' : 'CGST + SGST' }}</span>
                                </div>
                                <div class="table-responsive" style="overflow-x: visible;">
                                    <table class="table table-sm table-bordered align-middle text-center fs-11 mb-0 w-100">
                                        <thead class="bg-light text-secondary fw-bold">
                                            @if($invoice->gst_type === 'igst')
                                                <tr>
                                                    <th class="py-1" style="width: 30%;">Tax Rate</th>
                                                    <th class="py-1 text-end" style="width: 35%;">Total Tax</th>
                                                    <th class="py-1 text-end" style="width: 35%;">IGST Amt</th>
                                                </tr>
                                            @else
                                                <tr>
                                                    <th class="py-1" style="width: 25%;">Tax Rate</th>
                                                    <th class="py-1 text-end" style="width: 25%;">Total Tax</th>
                                                    <th class="py-1 text-end" style="width: 25%;">CGST Amt</th>
                                                    <th class="py-1 text-end" style="width: 25%;">SGST Amt</th>
                                                </tr>
                                            @endif
                                        </thead>
                                        <tbody>
                                            @php
                                                $totCgst = 0;
                                                $totSgst = 0;
                                                $totIgst = 0;
                                                $totTax = 0;
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
                                                        <td class="py-1 fw-bold">GST {{ $rate }}%</td>
                                                        <td class="py-1 text-end fw-bold text-dark">₹{{ number_format($grpTax, 2) }}</td>
                                                        <td class="py-1 text-end">₹{{ number_format($grpIgst, 2) }}</td>
                                                    </tr>
                                                @else
                                                    @php
                                                        $grpCgst = $gItems->sum('cgst_amount') > 0 ? $gItems->sum('cgst_amount') : round($grpTax / 2, 2);
                                                        $grpSgst = $gItems->sum('sgst_amount') > 0 ? $gItems->sum('sgst_amount') : round($grpTax - $grpCgst, 2);
                                                        $totCgst += $grpCgst;
                                                        $totSgst += $grpSgst;
                                                    @endphp
                                                    <tr>
                                                        <td class="py-1 fw-bold">GST {{ $rate }}%</td>
                                                        <td class="py-1 text-end fw-bold text-dark">₹{{ number_format($grpTax, 2) }}</td>
                                                        <td class="py-1 text-end">₹{{ number_format($grpCgst, 2) }}</td>
                                                        <td class="py-1 text-end">₹{{ number_format($grpSgst, 2) }}</td>
                                                    </tr>
                                                @endif
                                            @endforeach
                                        </tbody>
                                        <tfoot class="bg-light fw-bold text-dark">
                                            @if($invoice->gst_type === 'igst')
                                                <tr>
                                                    <td class="py-1">Total</td>
                                                    <td class="py-1 text-end text-primary">₹{{ number_format($totTax, 2) }}</td>
                                                    <td class="py-1 text-end">₹{{ number_format($totIgst, 2) }}</td>
                                                </tr>
                                            @else
                                                <tr>
                                                    <td class="py-1">Total</td>
                                                    <td class="py-1 text-end text-primary">₹{{ number_format($totTax, 2) }}</td>
                                                    <td class="py-1 text-end">₹{{ number_format($totCgst, 2) }}</td>
                                                    <td class="py-1 text-end">₹{{ number_format($totSgst, 2) }}</td>
                                                </tr>
                                            @endif
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        @endif
                    </div>

                    <!-- Right: Subtotal Calculations -->
                    <div class="col-5 summary-table-box">
                        <table class="table table-sm border-0 summary-table w-100 mb-0">
                            <tbody>
                                <tr>
                                    <td class="text-muted fw-semibold">Sub Total:</td>
                                    <td class="text-end fw-bold text-dark">₹{{ number_format($invoice->subtotal, 2) }}</td>
                                </tr>
                                @if ($invoice->discount_amount > 0)
                                    <tr>
                                        <td class="text-muted fw-semibold">Discount:</td>
                                        <td class="text-end fw-bold text-danger">-₹{{ number_format($invoice->discount_amount, 2) }}</td>
                                    </tr>
                                @endif
                                @if ($invoice->gst_type === 'igst' || $invoice->igst_amount > 0)
                                    <tr>
                                        <td class="text-muted fw-semibold">IGST Amount:</td>
                                        <td class="text-end fw-bold text-dark">₹{{ number_format($invoice->igst_amount > 0 ? $invoice->igst_amount : $invoice->tax_amount, 2) }}</td>
                                    </tr>
                                @elseif ($invoice->gst_type === 'cgst_sgst' || ($invoice->cgst_amount > 0 || $invoice->sgst_amount > 0))
                                    <tr>
                                        <td class="text-muted fw-semibold">CGST Amount:</td>
                                        <td class="text-end fw-bold text-dark">₹{{ number_format($invoice->cgst_amount > 0 ? $invoice->cgst_amount : round($invoice->tax_amount / 2, 2), 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted fw-semibold">SGST Amount:</td>
                                        <td class="text-end fw-bold text-dark">₹{{ number_format($invoice->sgst_amount > 0 ? $invoice->sgst_amount : round($invoice->tax_amount / 2, 2), 2) }}</td>
                                    </tr>
                                @else
                                    <tr>
                                        <td class="text-muted fw-semibold">Tax Amount:</td>
                                        <td class="text-end fw-bold text-dark">₹{{ number_format($invoice->tax_amount, 2) }}</td>
                                    </tr>
                                @endif
                                <tr class="border-top border-bottom" style="background-color: #f8fafc;">
                                    <td class="fw-bold fs-14" style="color: #1e40af;">Total Amount:</td>
                                    <td class="text-end fw-black fs-15" style="color: #1e40af;">₹{{ number_format($invoice->total_amount, 2) }}</td>
                                </tr>
                                @if ($adjustedAmount > 0 || $invoice->amount_paid > 0)
                                    <tr class="text-success">
                                        <td class="fw-semibold fs-12">Payments Received:</td>
                                        <td class="text-end fw-bold">-₹{{ number_format(max($invoice->amount_paid, $adjustedAmount), 2) }}</td>
                                    </tr>
                                @endif
                                <tr>
                                    <td class="fw-bold fs-13 text-dark">Balance Due:</td>
                                    <td class="text-end fw-extrabold fs-14 {{ $balanceDue > 0 ? 'text-danger' : 'text-success' }}">₹{{ number_format($balanceDue, 2) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- 4.5 Bank Details & Terms Row -->
                <div class="row fs-13 text-dark mb-4">
                    <div class="col-7">
                        <div class="p-3 bg-light bg-opacity-30 rounded border mb-3 bank-details-box">
                            <h6 class="fw-bold text-dark fs-11 text-uppercase mb-2" style="letter-spacing: 0.5px;">Bank Payment Details:</h6>
                            <div class="row fs-11 text-secondary g-2">
                                <div class="col-6"><strong>Bank Name:</strong> State Bank of India</div>
                                <div class="col-6"><strong>Account Name:</strong> {{ tenant() ? tenant()->name : 'SaaS ERP' }}</div>
                                <div class="col-6"><strong>Account No:</strong> 398402948201</div>
                                <div class="col-6"><strong>IFSC Code:</strong> SBIN0001234</div>
                            </div>
                        </div>

                        @if ($invoice->notes)
                            <div class="p-3 bg-light bg-opacity-30 rounded border terms-box">
                                <h6 class="fw-bold text-dark fs-11 text-uppercase mb-1" style="letter-spacing: 0.5px;">Terms & Conditions / Customer Notes:</h6>
                                <div class="mb-0 text-muted fs-12">{!! $invoice->notes !!}</div>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- 5. Signature Footer Block -->
                <div class="row pt-4 border-top fs-11 text-muted align-items-end mt-4 signature-footer-block">
                    <div class="col-7">
                        <div>Thank you for your business!</div>
                        <div class="mt-1">This is a computer generated invoice and does not require physical signature.</div>
                    </div>
                    <div class="col-5 text-end">
                        <div class="fw-bold text-dark mb-5">For {{ tenant() ? tenant()->name : 'SaaS ERP' }}</div>
                        <div class="border-top d-inline-block pt-1 px-4 text-muted fw-semibold">Authorized Signatory</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
