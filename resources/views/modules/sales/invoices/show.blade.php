@extends('layouts.duralux')

@section('title', __('crm.tax_invoice') . ' ' . $invoice->invoice_number . ' | SaaS ERP')
@section('page-title', __('crm.tax_invoice'))
@section('breadcrumb')
    <a href="{{ route('sales.invoices.index') }}">{{ __('crm.invoices') }}</a> &gt; {{ $invoice->invoice_number }}
@endsection

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <a href="{{ route('sales.invoices.index') }}" class="action-dropdown-btn" title="{{ __('crm.back_to_invoices') }}" data-bs-toggle="tooltip">
            <i class="feather feather-arrow-left"></i>
        </a>

        <a href="javascript:void(0)" onclick="window.print()" class="btn btn-sm btn-outline-secondary fw-bold px-3">
            <i class="feather-printer me-1.5"></i>{{ __('crm.print') }}
        </a>

        @if ($invoice->status === 'Draft')
            <form action="{{ route('sales.invoices.send', $invoice->id) }}" method="POST" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-sm btn-primary fw-bold px-3">
                    <i class="feather-send me-1.5"></i>{{ __('crm.mark_as_sent') }}
                </button>
            </form>
        @endif

        @if (in_array($invoice->status, ['Sent', 'Partially Paid', 'Posted', 'Draft']))
            <a href="{{ route('sales.payments.create', ['invoice_id' => $invoice->id, 'customer_id' => $invoice->customer_id ?: $invoice->salesOrder?->customer_id]) }}" class="btn btn-sm btn-success fw-bold px-3">
                <i class="feather-dollar-sign me-1.5"></i>{{ __('crm.register_payment') }}
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
                margin: 0 !important;
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

            /* Reset page wrappers for full height & width printing and remove top offsets */
            html, body, main, .nxl-container, .nxl-content, .main-content, .row, .col-12 {
                background: #ffffff !important;
                margin: 0 !important;
                padding: 0 !important;
                height: auto !important;
                overflow: visible !important;
                font-size: 10px !important;
            }

            /* Invoice sheet: static layout with minimal top padding */
            .invoice-sheet {
                position: static !important;
                width: 100% !important;
                max-width: 100% !important;
                margin: 0 !important;
                padding: 2mm 6mm 2mm 6mm !important;
                border: none !important;
                box-shadow: none !important;
                border-radius: 0 !important;
                background: #ffffff !important;
                overflow: visible !important;
            }

            .invoice-sheet > div:first-child,
            .invoice-sheet > .row:first-child {
                margin-top: 0 !important;
                padding-top: 0 !important;
            }

            .invoice-sheet .row {
                --bs-gutter-x: 0.75rem !important;
                --bs-gutter-y: 0.35rem !important;
                margin-bottom: 4px !important;
            }

            .invoice-sheet .avatar-text {
                width: 36px !important;
                height: 36px !important;
                font-size: 1rem !important;
            }

            .invoice-sheet h2 {
                font-size: 17px !important;
                margin-bottom: 2px !important;
            }

            .invoice-sheet h4 {
                font-size: 13.5px !important;
                margin-bottom: 2px !important;
            }

            .invoice-sheet h6 {
                font-size: 11px !important;
                margin-bottom: 2px !important;
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
                padding: 3px 5px !important;
                font-size: 9.5px !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .invoice-sheet .invoice-table td {
                padding: 3px 5px !important;
                font-size: 10px !important;
            }

            .invoice-sheet .p-3 {
                padding: 4px 8px !important;
            }

            .invoice-sheet .mb-4,
            .invoice-sheet .mb-3,
            .invoice-sheet .mb-5 {
                margin-bottom: 4px !important;
            }

            .invoice-sheet .mt-3,
            .invoice-sheet .mt-4,
            .invoice-sheet .mt-5 {
                margin-top: 4px !important;
            }

            .invoice-sheet .pb-4 {
                padding-bottom: 4px !important;
            }

            .invoice-sheet .pt-2,
            .invoice-sheet .pt-3,
            .invoice-sheet .pt-4 {
                padding-top: 4px !important;
            }

            .invoice-sheet .fs-12,
            .invoice-sheet .fs-13 {
                font-size: 10.5px !important;
            }

            .invoice-sheet .fs-14 {
                font-size: 11.5px !important;
            }

            .summary-table-box .p-3 {
                padding: 5px 8px !important;
            }

            .summary-table-box .mb-2 {
                margin-bottom: 2px !important;
            }

            .summary-table-box .my-2 {
                margin: 2px 0 !important;
            }

            .summary-table-box .py-1\.5 {
                padding-top: 2px !important;
                padding-bottom: 2px !important;
            }

            .summary-table-box .fs-12 {
                font-size: 10px !important;
            }

            .summary-table-box .fs-13 {
                font-size: 10.5px !important;
            }

            .summary-table-box .fs-16 {
                font-size: 13px !important;
            }

            .gst-summary-box table th,
            .gst-summary-box table td {
                padding: 2px 4px !important;
                font-size: 9.5px !important;
            }

            .bank-details-box {
                padding: 4px 8px !important;
                margin-bottom: 4px !important;
            }

            /* Avoid breaking inside table rows */
            tr {
                page-break-inside: avoid;
                break-inside: avoid;
            }

            /* Page Break Protection for Cards */
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
                margin-top: 4px !important;
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

            /* Restore list-item display so numbers show cleanly in print */
            .terms-box ol > li,
            .terms-box ul > li,
            .terms-box li {
                display: list-item !important;
                list-style-position: outside !important;
                page-break-inside: avoid !important;
                break-inside: avoid !important;
                break-inside: avoid-page !important;
                margin-bottom: 2px !important;
            }

            .terms-box p {
                display: block !important;
                page-break-inside: avoid !important;
                break-inside: avoid !important;
                break-inside: avoid-page !important;
                margin-bottom: 2px !important;
            }

            .terms-box li *,
            .terms-box p * {
                page-break-inside: avoid !important;
                break-inside: avoid !important;
            }

            .signature-footer-block {
                page-break-inside: avoid !important;
                break-inside: avoid !important;
                margin-top: 6px !important;
                padding-top: 4px !important;
            }
        }
    </style>
@endpush

@section('content')
    <div class="row text-dark">
        <div class="col-12">

            <!-- Standard ERP Customer Tax Invoice Sheet -->
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

                        $statusLabel = match($invoice->status) {
                            'Paid' => __('crm.status_paid'),
                            'Partially Paid' => __('crm.status_partially_paid'),
                            'Sent' => __('crm.status_sent'),
                            'Cancelled' => __('crm.status_cancelled'),
                            default => __('crm.status_draft'),
                        };
                    @endphp
                    <div class="ribbon-inner {{ $ribbonClass }}">
                        {{ $statusLabel }}
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
                                <span class="fs-11 text-muted">{{ __('crm.official_corporate_billing_unit') }}</span>
                            </div>
                        </div>
                        <div class="fs-12 text-secondary leading-relaxed">
                            <div>H-1, Industrial Area, Sukher, Udaipur 313001, Rajasthan, India</div>
                            <div><strong>GSTIN:</strong> 08AAFCS1234E1Z0 &nbsp;|&nbsp; <strong>State Code:</strong> 08 (Rajasthan)</div>
                            <div><strong>Email:</strong> {{ tenant() ? tenant()->billing_email : 'billing@sasserp.com' }} &nbsp;|&nbsp; <strong>Phone:</strong> +91 294 2440230</div>
                        </div>
                    </div>

                    <div class="col-5 text-end">
                        <h2 class="fw-black text-uppercase tracking-wide mb-1" style="color: #1e40af; font-size: 22px; letter-spacing: 1px;">{{ __('crm.tax_invoice') }}</h2>
                        <div class="fs-14 fw-bold text-dark"># {{ $invoice->invoice_number }}</div>
                        
                        <div class="mt-3 fs-12 text-secondary">
                            <div class="d-flex justify-content-end gap-2 mb-1">
                                <span class="text-muted">{{ __('crm.invoice_date') }}:</span>
                                <strong class="text-dark">{{ date('d-M-Y', strtotime($invoice->invoice_date)) }}</strong>
                            </div>
                            <div class="d-flex justify-content-end gap-2 mb-1">
                                <span class="text-muted">{{ __('crm.due_date') }}:</span>
                                <strong class="text-dark">{{ $invoice->due_date ? date('d-M-Y', strtotime($invoice->due_date)) : '—' }}</strong>
                            </div>
                            <div class="d-flex justify-content-end gap-2">
                                <span class="text-muted">{{ __('crm.payment_terms') }}:</span>
                                <strong class="text-dark">{{ $invoice->salesOrder?->payment_terms ?: __('crm.immediate_payment') }}</strong>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. Address & Order Details Section -->
                <div class="row g-4 mb-4 fs-12 text-dark">
                    <!-- Left: Customer Details -->
                    <div class="col-6">
                        <div class="p-3 bg-light bg-opacity-40 rounded border h-100">
                            <span class="fs-10 fw-bold text-uppercase text-muted d-block mb-2" style="letter-spacing: 0.5px;">{{ __('crm.billed_to_customer') }}</span>
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
                                    <strong>{{ __('crm.billing_address') }}:</strong><br>{{ $invoice->salesOrder->billing_address }}
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Right: Order References & Dispatch Info -->
                    <div class="col-6">
                        <div class="p-3 bg-light bg-opacity-40 rounded border h-100">
                            <span class="fs-10 fw-bold text-uppercase text-muted d-block mb-2" style="letter-spacing: 0.5px;">{{ __('crm.order_dispatch_references') }}</span>
                            
                            @if ($invoice->salesOrder)
                                <div class="d-flex justify-content-between mb-2 pb-1 border-bottom">
                                    <span class="text-muted">{{ __('crm.sales_order_reference') }}:</span>
                                    <a href="{{ route('sales.orders.show', $invoice->sales_order_id) }}" class="fw-bold text-primary">
                                        {{ $invoice->salesOrder->sales_order_number }}
                                    </a>
                                </div>
                            @endif

                            @if ($invoice->materialRequirement)
                                <div class="d-flex justify-content-between mb-2 pb-1 border-bottom">
                                    <span class="text-muted">{{ __('crm.dispatch_order_reference') }}:</span>
                                    <a href="{{ route('sales.material-requirements.show', $invoice->material_requirement_id) }}" class="fw-bold text-info">
                                        {{ $invoice->materialRequirement->requirement_number }}
                                    </a>
                                </div>
                            @endif

                            <div class="d-flex justify-content-between mb-2 pb-1 border-bottom">
                                <span class="text-muted">{{ __('crm.place_of_supply') }}</span>
                                <span class="fw-semibold text-dark">Rajasthan (08)</span>
                            </div>

                            <div class="d-flex justify-content-between mb-2 pb-1 border-bottom">
                                <span class="text-muted">{{ __('crm.gst_option') }}</span>
                                <span class="fw-bold text-dark">{{ $invoice->gst_type === 'igst' ? __('crm.inter_state_gst') : __('crm.intra_state_gst') }}</span>
                            </div>

                            <div class="d-flex justify-content-between">
                                <span class="text-muted">{{ __('crm.payment_status') }}</span>
                                <span class="fw-bold {{ $balanceDue > 0 ? 'text-danger' : 'text-success' }}">{{ $balanceDue > 0 ? __('crm.balance_outstanding') : __('crm.fully_paid') }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 3. Line Items Table -->
                <div class="mb-4">
                    <div class="table-responsive">
                        <table class="table invoice-table align-middle w-100 mb-0">
                            <thead>
                                <tr>
                                    <th style="width: 5%;" class="text-center">#</th>
                                    <th style="width: 45%;">{{ __('crm.item_and_description') }}</th>
                                    <th class="text-end" style="width: 12%;">{{ __('crm.qty') }}</th>
                                    <th class="text-end" style="width: 13%;">{{ __('crm.rate') }}</th>
                                    <th class="text-end" style="width: 10%;">{{ __('crm.tax_rate') }}</th>
                                    <th class="text-end" style="width: 15%;">{{ __('crm.amount') }}</th>
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
                                        <td class="text-end">{{ format_currency($item->unit_price) }}</td>
                                        <td class="text-end text-muted">{{ (float)$item->tax_rate }}%</td>
                                        <td class="text-end fw-bold text-dark">
                                            @php
                                                $lineTotal = ($item->total_amount > 0)
                                                    ? $item->total_amount
                                                    : ($item->subtotal > 0 ? $item->subtotal : ($item->quantity * $item->unit_price));
                                            @endphp
                                            {{ format_currency($lineTotal) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- 4. Summary & Calculations Row -->
                <div class="row pt-2 fs-13 text-dark mb-4">
                    <!-- Left: GST Tax Rate Summary, Bank Details & Terms -->
                    <div class="col-7">
                        @php
                            $taxGroups = $invoice->items->groupBy(fn($item) => (string)(float)$item->tax_rate);
                        @endphp
                        @if ($taxGroups->count() > 0 && $invoice->tax_amount > 0)
                            <div class="card border shadow-none mb-3 gst-summary-box" style="border-radius: 6px; overflow: hidden; border-color: #cbd5e1 !important;">
                                <div class="py-1 px-3 bg-light border-bottom text-muted fw-bold fs-11 text-uppercase d-flex justify-content-between align-items-center">
                                    <span><i class="feather-pie-chart me-1 text-primary"></i>{{ __('crm.gst_tax_summary') }}</span>
                                    <span class="badge bg-soft-primary text-primary fs-10" style="font-size: 10px;">{{ $invoice->gst_type === 'igst' ? 'IGST' : 'CGST + SGST' }}</span>
                                </div>
                                <div class="table-responsive" style="overflow-x: visible;">
                                    <table class="table table-sm table-bordered align-middle text-center fs-11 mb-0 w-100">
                                        <thead class="bg-light text-secondary fw-bold">
                                            @if($invoice->gst_type === 'igst')
                                                <tr>
                                                    <th class="py-1" style="width: 30%;">{{ __('crm.tax_rate') }}</th>
                                                    <th class="py-1 text-end" style="width: 35%;">{{ __('crm.total_amount') }}</th>
                                                    <th class="py-1 text-end" style="width: 35%;">IGST</th>
                                                </tr>
                                            @else
                                                <tr>
                                                    <th class="py-1" style="width: 25%;">{{ __('crm.tax_rate') }}</th>
                                                    <th class="py-1 text-end" style="width: 25%;">{{ __('crm.total_amount') }}</th>
                                                    <th class="py-1 text-end" style="width: 25%;">CGST</th>
                                                    <th class="py-1 text-end" style="width: 25%;">SGST</th>
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
                                                        <td class="py-1 text-end fw-bold text-dark">{{ format_currency($grpTax) }}</td>
                                                        <td class="py-1 text-end">{{ format_currency($grpIgst) }}</td>
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
                                                        <td class="py-1 text-end fw-bold text-dark">{{ format_currency($grpTax) }}</td>
                                                        <td class="py-1 text-end">{{ format_currency($grpCgst) }}</td>
                                                        <td class="py-1 text-end">{{ format_currency($grpSgst) }}</td>
                                                    </tr>
                                                @endif
                                            @endforeach
                                        </tbody>
                                        <tfoot class="bg-light fw-bold text-dark">
                                            @if($invoice->gst_type === 'igst')
                                                <tr>
                                                    <td class="py-1">Total</td>
                                                    <td class="py-1 text-end text-primary">{{ format_currency($totTax) }}</td>
                                                    <td class="py-1 text-end">{{ format_currency($totIgst) }}</td>
                                                </tr>
                                            @else
                                                <tr>
                                                    <td class="py-1">Total</td>
                                                    <td class="py-1 text-end text-primary">{{ format_currency($totTax) }}</td>
                                                    <td class="py-1 text-end">{{ format_currency($totCgst) }}</td>
                                                    <td class="py-1 text-end">{{ format_currency($totSgst) }}</td>
                                                </tr>
                                            @endif
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        @endif

                        <div class="p-3 bg-light bg-opacity-30 rounded border mb-3 bank-details-box" style="border-color: #cbd5e1 !important;">
                            <h6 class="fw-bold text-dark fs-11 text-uppercase mb-2" style="letter-spacing: 0.5px;">{{ __('crm.bank_payment_details') }}</h6>
                            <div class="row fs-11 text-secondary g-2">
                                <div class="col-6"><strong>{{ __('crm.bank_name') }}</strong> State Bank of India</div>
                                <div class="col-6"><strong>{{ __('crm.account_name') }}</strong> {{ tenant() ? tenant()->name : 'SaaS ERP' }}</div>
                                <div class="col-6"><strong>{{ __('crm.account_no') }}</strong> 398402948201</div>
                                <div class="col-6"><strong>{{ __('crm.ifsc_code') }}</strong> SBIN0001234</div>
                            </div>
                        </div>

                        @if ($invoice->notes)
                            <div class="p-3 bg-light bg-opacity-30 rounded border terms-box" style="border-color: #cbd5e1 !important;">
                                <h6 class="fw-bold text-dark fs-11 text-uppercase mb-1" style="letter-spacing: 0.5px;">{{ __('crm.terms_conditions_notes') }}</h6>
                                <div class="mb-0 text-muted fs-12">{!! $invoice->notes !!}</div>
                            </div>
                        @endif
                    </div>

                    <!-- Right: Subtotal Calculations -->
                    <div class="col-5 summary-table-box ms-auto">
                        <div class="p-3 rounded-3 border bg-light text-dark" style="border-color: #cbd5e1 !important; background-color: #f8fafc !important;">
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
                                $grandTotal = $itemsTotalInclGst + $totalFreightInclGst + $adjustment;
                                $gstType = $invoice->gst_type ?? 'cgst_sgst';
                            @endphp

                            <!-- 1. Subtotal (Excl. Tax) -->
                            <div class="d-flex justify-content-between align-items-center mb-2 fs-12">
                                <span class="text-muted fw-semibold">{{ __('crm.subtotal_excl_tax') }}</span>
                                <span class="fw-bold text-dark">{{ format_currency($grossSubtotal) }}</span>
                            </div>

                            <!-- 2. Less: Item Discounts -->
                            @if($invoice->discount_type !== 'without_discount' && $effectiveDiscount > 0)
                                <div class="d-flex justify-content-between align-items-center mb-2 fs-12 text-danger">
                                    <span class="fw-semibold">{{ __('crm.less_item_discounts') }}</span>
                                    <span class="fw-bold">-{{ format_currency($effectiveDiscount) }}</span>
                                </div>
                            @endif

                            <!-- 3. Items Taxable Value -->
                            <div class="d-flex justify-content-between align-items-center mb-2 fs-12">
                                <span class="text-muted fw-semibold">{{ __('crm.items_taxable_value') }}</span>
                                <span class="fw-bold text-dark">{{ format_currency($taxableBase) }}</span>
                            </div>

                            <!-- 4. Add: Items GST Tax -->
                            @if($invoice->tax_type !== 'without_tax' && $itemsTaxAmount > 0)
                                <div class="d-flex justify-content-between align-items-center mb-2 fs-12">
                                    <span class="text-muted fw-medium">{{ __('crm.add_items_gst_tax') }}</span>
                                    <span class="text-muted font-monospace">+{{ format_currency($itemsTaxAmount) }}</span>
                                </div>
                            @endif

                            <!-- 5. Billed Items Total (Incl. GST) -->
                            <div class="d-flex justify-content-between align-items-center my-2 py-1.5 px-2.5 rounded bg-white border fs-12 fw-bold text-dark" style="border-color: #e2e8f0 !important;">
                                <span>{{ __('crm.billed_items_total') }}</span>
                                <span>{{ format_currency($itemsTotalInclGst) }}</span>
                            </div>

                            <!-- 6. Freight Charges -->
                            @if($freightAmount > 0)
                                <hr class="my-2 border-slate">
                                <div class="d-flex justify-content-between align-items-center mb-2 fs-12">
                                    <span class="text-muted fw-semibold">{{ __('crm.freight_charges') }}</span>
                                    <span class="fw-bold text-primary">{{ format_currency($freightAmount) }}</span>
                                </div>
                                @if($freightTax > 0)
                                    <div class="d-flex justify-content-between align-items-center mb-2 fs-12">
                                        <span class="text-muted fw-semibold">{{ __('crm.add_freight_gst_tax') }}</span>
                                        <span class="text-muted font-monospace">+{{ format_currency($freightTax) }}</span>
                                    </div>
                                @endif
                                <div class="d-flex justify-content-between align-items-center mb-2 fs-12 fw-bold text-primary">
                                    <span>{{ __('crm.total_freight_incl_gst') }}</span>
                                    <span>{{ format_currency($totalFreightInclGst) }}</span>
                                </div>
                            @endif

                            <!-- OVERALL TAX BREAKDOWN -->
                            @if($invoice->tax_type !== 'without_tax' && $totalInvoiceTaxAmount > 0)
                                <hr class="my-2 border-slate">
                                @if($gstType === 'cgst_sgst')
                                    <div class="d-flex justify-content-between align-items-center mb-1.5 fs-12">
                                        <span class="text-muted fw-medium">{{ __('crm.cgst_central_tax') }}</span>
                                        <span class="text-muted font-monospace">+{{ format_currency($totalInvoiceTaxAmount / 2) }}</span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mb-2 fs-12">
                                        <span class="text-muted fw-medium">{{ __('crm.sgst_state_tax') }}</span>
                                        <span class="text-muted font-monospace">+{{ format_currency($totalInvoiceTaxAmount / 2) }}</span>
                                    </div>
                                @else
                                    <div class="d-flex justify-content-between align-items-center mb-2 fs-12">
                                        <span class="text-muted fw-medium">{{ __('crm.igst_integrated_tax') }}</span>
                                        <span class="text-muted font-monospace">+{{ format_currency($totalInvoiceTaxAmount) }}</span>
                                    </div>
                                @endif
                            @endif

                            <!-- 7. Adjustment -->
                            @if($adjustment != 0)
                                <div class="d-flex justify-content-between align-items-center mb-2 fs-12">
                                    <span class="text-muted fw-semibold">{{ __('crm.adjustment') }}</span>
                                    <span class="fw-bold text-dark">{{ format_currency($adjustment) }}</span>
                                </div>
                            @endif

                            <!-- 8. Grand Total -->
                            <div class="d-flex justify-content-between align-items-center pt-2.5 border-top mt-2" style="border-color: #cbd5e1 !important;">
                                <span class="fw-bold text-dark fs-13 text-uppercase" style="letter-spacing: 0.5px;">{{ __('crm.grand_total') }}</span>
                                <span class="fw-bold text-primary fs-16">{{ format_currency($grandTotal) }}</span>
                            </div>

                            <!-- 9. Balance Due (if applicable) -->
                            @php
                                $totalPaid = $invoice->amount_paid ?: 0;
                                $balDue = max(0, $grandTotal - $totalPaid);
                            @endphp
                            @if($totalPaid > 0)
                                <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top text-success fs-12">
                                    <span class="fw-semibold">{{ __('crm.amount_paid') }}:</span>
                                    <span class="fw-bold">-{{ format_currency($totalPaid) }}</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mt-1 pt-1 text-danger fs-13">
                                    <span class="fw-bold">{{ __('crm.balance_due') }}:</span>
                                    <span class="fw-bold">{{ format_currency($balDue) }}</span>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- 5. Signature Footer Block -->
                <div class="row pt-4 border-top fs-11 text-muted align-items-end mt-4 signature-footer-block">
                    <div class="col-7">
                        <div>{{ __('crm.thank_you_for_your_business') }}</div>
                        <div class="mt-1">{{ __('crm.computer_generated_invoice_notice') }}</div>
                    </div>
                    <div class="col-5 text-end">
                        <div class="fw-bold text-dark mb-5">For {{ tenant() ? tenant()->name : 'SaaS ERP' }}</div>
                        <div class="border-top d-inline-block pt-1 px-4 text-muted fw-semibold">{{ __('crm.authorized_signatory') }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
