@extends('layouts.duralux')

@section('title', 'Quotation details ' . $quotation->quotation_number . ' | SaaS ERP')
@section('page-title', 'Sales Quotation')
@section('breadcrumb', 'CRM / Quotations / ' . $quotation->quotation_number)

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <a href="{{ route('crm.quotations.index') }}" class="action-dropdown-btn" title="Back to Quotations" data-bs-toggle="tooltip">
            <i class="feather feather-arrow-left"></i>
        </a>

        <a href="javascript:void(0)" onclick="window.print()" class="btn btn-sm btn-outline-secondary fw-bold px-3">
            <i class="feather-printer me-1.5"></i>Print
        </a>

        @if ($quotation->status === 'Accepted')
            <a href="{{ route('sales.orders.create', ['quotation_id' => $quotation->id]) }}" class="btn btn-sm btn-success fw-bold px-3">
                <i class="feather-shopping-cart me-1.5"></i>Convert to Sales Order
            </a>
        @endif

        <a href="{{ route('crm.quotations.download', $quotation->id) }}" class="btn btn-sm btn-primary fw-bold px-3">
            <i class="feather-download me-1.5"></i>Download PDF
        </a>
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

        .ribbon-accepted, .ribbon-approved {
            background-color: #16a34a;
        }
        .ribbon-sent, .ribbon-quotation-sent {
            background-color: #2563eb;
        }
        .ribbon-draft {
            background-color: #64748b;
        }
        .ribbon-rejected, .ribbon-declined {
            background-color: #dc2626;
        }
        .ribbon-rework {
            background-color: #d97706;
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
            padding: 10px 14px;
        }
        .invoice-table td {
            padding: 12px 14px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 13px;
            color: #1e293b;
        }

        .terms-conditions-content p {
            margin-bottom: 4px !important;
            line-height: 1.4 !important;
        }
        .terms-conditions-content p:last-child {
            margin-bottom: 0 !important;
        }

        @media print {
            @page {
                size: A4 portrait;
                margin: 0;
            }

            body > *,
            .nxl-container,
            .nxl-content,
            .page-header,
            .main-content,
            .nxl-navigation,
            .nxl-header,
            footer,
            .loader-bg {
                visibility: hidden;
            }

            .invoice-sheet,
            .invoice-sheet * {
                visibility: visible;
            }

            html, body {
                margin: 0 !important;
                padding: 0 !important;
                background: #fff !important;
            }
            .invoice-sheet {
                box-shadow: none !important;
                border: 0 !important;
                margin: 0 !important;
                padding: 10mm 15mm !important;
                width: 100% !important;
                max-width: 100% !important;
            }

            .d-print-none,
            .invoice-corner-ribbon {
                display: none !important;
                visibility: hidden !important;
            }
        }
    </style>
@endpush

@section('content')
    <div class="row text-dark">
        <div class="col-12">

            @if (in_array($quotation->status, ['Rejected', 'Declined']))
                <div class="alert alert-danger border-danger border-start border-4 shadow-sm mb-4 d-print-none" role="alert" style="background-color: #fff5f5;">
                    <div class="d-flex align-items-start">
                        <div class="avatar-text avatar-md bg-danger text-white me-3 mt-0.5 rounded-circle flex-shrink-0">
                            <i class="feather-x-circle fs-18"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="alert-heading fw-bold text-danger mb-1"><i class="feather-alert-triangle me-1"></i> Quotation Rejected</h6>
                            <p class="fs-13 text-dark mb-0">
                                <strong>Rejection Reason / Remarks:</strong> 
                                <span class="text-danger fw-semibold">{{ $quotation->rejection_reason ?: 'No specific reason provided.' }}</span>
                            </p>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Standard ERP Customer Quotation Sheet -->
            <div class="invoice-sheet print-area mb-5">
                <!-- Status Corner Ribbon Tag Patti -->
                <div class="invoice-corner-ribbon">
                    @php
                        $statusSlug = strtolower(str_replace(' ', '-', $quotation->status));
                        $ribbonClass = match($quotation->status) {
                            'Accepted', 'Approved' => 'ribbon-accepted',
                            'Sent', 'Quotation Sent' => 'ribbon-sent',
                            'Rejected', 'Declined' => 'ribbon-rejected',
                            'Rework', 'Quotation Rework' => 'ribbon-rework',
                            default => 'ribbon-draft',
                        };
                    @endphp
                    <div class="ribbon-inner {{ $ribbonClass }}">
                        {{ $quotation->status }}
                    </div>
                </div>

                <!-- 1. Document Header -->
                <div class="row align-items-start pb-4 border-bottom mb-4">
                    <div class="col-7">
                        <div class="d-flex align-items-center mb-3">
                            <div class="avatar-text bg-primary text-white fs-2 fw-bold me-3 shadow-sm d-flex align-items-center justify-content-center" style="border-radius: 8px; width: 50px; height: 50px; flex-shrink: 0; background-color: #1e40af !important;">
                                {{ strtoupper(substr(tenant() ? tenant()->name : 'ERP', 0, 1)) }}
                            </div>
                            <div>
                                <h4 class="fw-bold text-dark mb-0 fs-17">{{ tenant() ? tenant()->name : 'SaaS ERP Workspace' }}</h4>
                                <span class="fs-11 text-muted">Official Corporate Sales Unit</span>
                            </div>
                        </div>
                        <div class="fs-12 text-secondary leading-relaxed">
                            <div>H-1, Industrial Area, Sukher, Udaipur 313001, Rajasthan, India</div>
                            <div><strong>GSTIN:</strong> 08AAFCS1234E1Z0 &nbsp;|&nbsp; <strong>State Code:</strong> 08 (Rajasthan)</div>
                            <div><strong>Email:</strong> {{ tenant() ? tenant()->billing_email : 'sales@saaserp.com' }} &nbsp;|&nbsp; <strong>Phone:</strong> +91 294 2440230</div>
                        </div>
                    </div>

                    <div class="col-5 text-end">
                        <h2 class="fw-black text-uppercase tracking-wide mb-1" style="color: #1e40af; font-size: 22px; letter-spacing: 1px;">SALES QUOTATION</h2>
                        <div class="fs-14 fw-bold text-dark"># {{ $quotation->quotation_number }}</div>
                        
                        <div class="mt-3 fs-12 text-secondary">
                            <div class="d-flex justify-content-end gap-2 mb-1">
                                <span class="text-muted">Quotation Date:</span>
                                <strong class="text-dark">{{ $quotation->quotation_date ? date('d-M-Y', strtotime($quotation->quotation_date)) : '—' }}</strong>
                            </div>
                            <div class="d-flex justify-content-end gap-2 mb-1">
                                <span class="text-muted">Valid Until:</span>
                                <strong class="text-dark">{{ $quotation->expiry_date ? date('d-M-Y', strtotime($quotation->expiry_date)) : '—' }}</strong>
                            </div>
                            @if($quotation->salesPerson)
                                <div class="d-flex justify-content-end gap-2">
                                    <span class="text-muted">Sales Rep:</span>
                                    <strong class="text-dark">{{ $quotation->salesPerson->name }}</strong>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- 2. Prepared For & References (2 Box Columns) -->
                <div class="row g-4 mb-4 fs-12 text-dark">
                    <!-- Left: Prepared For -->
                    <div class="col-6">
                        <div class="p-3 bg-light bg-opacity-40 rounded border h-100">
                            <span class="fs-10 fw-bold text-uppercase text-muted d-block mb-2" style="letter-spacing: 0.5px;">Prepared For:</span>
                            <h6 class="fw-bold text-dark mb-1 fs-14">{{ $quotation->prepared_for_name }}</h6>
                            @if($quotation->prepared_for_email !== '—' || $quotation->prepared_for_phone !== '—')
                                <div class="text-secondary mb-1">
                                    @if($quotation->prepared_for_email !== '—')
                                        <span><i class="feather-mail me-1 fs-11 text-muted"></i>{{ $quotation->prepared_for_email }}</span>
                                    @endif
                                    @if($quotation->prepared_for_email !== '—' && $quotation->prepared_for_phone !== '—')
                                        <span class="mx-1 text-muted">|</span>
                                    @endif
                                    @if($quotation->prepared_for_phone !== '—')
                                        <span><i class="feather-phone me-1 fs-11 text-muted"></i>{{ $quotation->prepared_for_phone }}</span>
                                    @endif
                                </div>
                            @endif
                            @if ($quotation->prepared_for_address)
                                <div class="mt-2 pt-2 border-top text-secondary fs-11" style="white-space: pre-wrap;">
                                    <strong>Billing Address:</strong><br>{{ $quotation->prepared_for_address }}
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Right: References & Revision Info -->
                    <div class="col-6">
                        <div class="p-3 bg-light bg-opacity-40 rounded border h-100">
                            <span class="fs-10 fw-bold text-uppercase text-muted d-block mb-2" style="letter-spacing: 0.5px;">Quotation References:</span>
                            
                            @if ($quotation->crmDeal)
                                <div class="d-flex justify-content-between mb-2 pb-1 border-bottom">
                                    <span class="text-muted">CRM Deal Ref:</span>
                                    <a href="{{ route('crm.deals.show', $quotation->crm_deal_id) }}" class="fw-bold text-primary">
                                        {{ $quotation->crmDeal->deal_number }} ({{ $quotation->crmDeal->title }})
                                    </a>
                                </div>
                            @elseif ($quotation->lead)
                                <div class="d-flex justify-content-between mb-2 pb-1 border-bottom">
                                    <span class="text-muted">Lead Ref:</span>
                                    <a href="{{ route('crm.leads.show', $quotation->lead_id) }}" class="fw-bold text-primary">
                                        {{ $quotation->lead->title ?: $quotation->lead->name }}
                                    </a>
                                </div>
                            @endif

                            <div class="d-flex justify-content-between mb-2 pb-1 border-bottom">
                                <span class="text-muted">Revision Count:</span>
                                <span class="fw-semibold text-dark">Revision {{ $quotation->revision_number }}</span>
                            </div>

                            <div class="d-flex justify-content-between">
                                <span class="text-muted">Status:</span>
                                <span class="fw-bold text-dark">{{ $quotation->status }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 3. Order Lines Table -->
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
                                @foreach ($quotation->items as $idx => $item)
                                    <tr>
                                        <td class="text-center text-muted fs-12">{{ $idx + 1 }}</td>
                                        <td>
                                            <strong class="text-dark d-block fs-13">{{ $item->item_name }}</strong>
                                            @if($item->description)
                                                <small class="text-muted d-block fs-11 mt-0.5">{{ $item->description }}</small>
                                            @endif
                                        </td>
                                        <td class="text-end font-monospace">{{ $item->quantity }}</td>
                                        <td class="text-end font-monospace">₹{{ number_format($item->unit_price, 2) }}</td>
                                        <td class="text-end font-monospace">{{ number_format($item->tax_rate, 2) }}%</td>
                                        <td class="text-end font-monospace fw-bold text-dark">₹{{ number_format($item->total_price ?: ($item->amount ?: ($item->quantity * $item->unit_price)), 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- 4. Calculations & Terms Section -->
                <div class="row g-4 border-top pt-3">
                    <!-- Left Column: Terms & Conditions and Notes -->
                    <div class="col-7">
                        @if($quotation->terms_conditions)
                            <div class="mb-3">
                                <span class="fs-10 fw-bold text-uppercase text-muted d-block mb-1" style="letter-spacing: 0.5px;">Terms & Conditions:</span>
                                <div class="text-secondary fs-12 terms-conditions-content">{!! $quotation->terms_conditions !!}</div>
                            </div>
                        @endif

                        @if($quotation->notes)
                            <div class="mb-2">
                                <span class="fs-10 fw-bold text-uppercase text-muted d-block mb-1" style="letter-spacing: 0.5px;">Internal Notes:</span>
                                <div class="text-secondary fs-12 p-2.5 rounded bg-light border" style="white-space: pre-line;">{{ $quotation->notes }}</div>
                            </div>
                        @endif
                    </div>

                    <!-- Right Column: Totals Summary -->
                    <div class="col-5">
                        <div class="p-3 bg-light bg-opacity-50 rounded border">
                            <div class="d-flex justify-content-between py-1 border-bottom fs-12">
                                <span class="text-muted">Subtotal:</span>
                                <span class="fw-bold text-dark">₹{{ number_format($quotation->subtotal, 2) }}</span>
                            </div>
                            <div class="d-flex justify-content-between py-1 border-bottom fs-12">
                                <span class="text-muted">Tax Amount (GST):</span>
                                <span class="fw-bold text-dark">₹{{ number_format($quotation->tax, 2) }}</span>
                            </div>
                            @if($quotation->discount > 0)
                                <div class="d-flex justify-content-between py-1 border-bottom fs-12 text-danger">
                                    <span>Discount:</span>
                                    <span class="fw-bold">-₹{{ number_format($quotation->discount, 2) }}</span>
                                </div>
                            @endif
                            <div class="d-flex justify-content-between py-2 mt-2 fs-15 rounded px-2" style="background-color: #f1f5f9;">
                                <span class="fw-bold text-dark">Total Payable:</span>
                                <span class="fw-extrabold text-primary">₹{{ number_format($quotation->total_amount, 2) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 5. Signature Footer Block -->
                <div class="row align-items-end mt-5 pt-4 border-top">
                    <div class="col-7">
                        <span class="fs-11 text-muted d-block">This sales quotation is valid until {{ $quotation->expiry_date ? date('d-M-Y', strtotime($quotation->expiry_date)) : 'the expiry date' }}.</span>
                        <span class="fs-11 text-muted d-block">For any queries, please contact our support at {{ tenant() ? tenant()->billing_email : 'sales@saaserp.com' }}.</span>
                    </div>
                    <div class="col-5 text-end">
                        <div class="d-inline-block text-center" style="min-width: 180px;">
                            <div class="border-top pt-1 text-uppercase text-muted fs-10 fw-bold" style="letter-spacing: 0.5px;">
                                Authorized Signature
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
@endsection

@if(request()->has('print'))
    @push('scripts')
        <script>
            window.addEventListener('DOMContentLoaded', () => {
                window.print();
            });
        </script>
    @endpush
@endif
