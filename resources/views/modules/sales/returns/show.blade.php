@extends('layouts.duralux')

@section('title', 'Sales Return | ' . $return->return_number)
@section('page-title', 'Sales Return ' . $return->return_number)
@section('breadcrumb', 'Sales / Returns / Details')

@section('page-actions')
    <a href="{{ route('sales.returns.index') }}" class="action-dropdown-btn" title="Back to Returns" data-bs-toggle="tooltip">
        <i class="feather feather-arrow-left"></i>
    </a>

    <a href="javascript:void(0)" onclick="window.print()" class="btn btn-sm btn-outline-secondary fw-bold px-3 d-print-none">
        <i class="feather-printer me-1.5"></i>Print
    </a>

    @if (in_array($return->status, ['Pending', 'Draft']))
        <form action="{{ route('sales.returns.approve', $return->id) }}" method="POST" id="approveReturnForm" class="d-inline d-print-none">
            @csrf
            <button type="button" class="btn btn-sm btn-success py-1.5 px-3 fw-bold" onclick="confirmAction({ title: 'Approve Sales Return', message: 'Approve sales return {{ $return->return_number }}? This will restore returned stock to inventory.', variant: 'success', confirmText: 'Approve & Restock' }, function() { document.getElementById('approveReturnForm').submit(); })">
                <i class="feather-check-circle me-1"></i>Approve & Restock Inventory
            </button>
        </form>
    @endif
@endsection

@push('styles')
    <style>
        /* ── Return Sheet Document (Odoo / Zoho Standard) ── */
        .return-sheet {
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

        /* ── Status Corner Ribbon Tag Patti ──────────────── */
        .return-corner-ribbon {
            position: absolute;
            top: 0;
            right: 0;
            width: 85px;
            height: 85px;
            overflow: hidden;
            pointer-events: none;
            z-index: 10;
        }

        .return-corner-ribbon .ribbon-inner {
            position: absolute;
            top: 18px;
            right: -25px;
            width: 110px;
            transform: rotate(45deg);
            text-align: center;
            font-size: 9.5px;
            font-weight: 800;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            padding: 4px 0;
            color: #ffffff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .ribbon-completed { background-color: #16a34a; }
        .ribbon-approved  { background-color: #0284c7; }
        .ribbon-draft     { background-color: #64748b; }
        .ribbon-cancelled { background-color: #dc2626; }

        /* ── Return Table Styling ────────────────────────── */
        .return-table {
            width: 100%;
            border-collapse: collapse;
        }

        .return-table thead th {
            background-color: #f8fafc;
            color: #475569;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 10px 14px;
            border-top: 1px solid #e2e8f0;
            border-bottom: 2px solid #cbd5e1;
        }

        .return-table tbody td {
            padding: 12px 14px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 13px;
            color: #1e293b;
        }

        .return-table tfoot td {
            padding: 10px 14px;
            background-color: #f8fafc;
            border-top: 1.5px solid #cbd5e1;
            font-size: 13px;
            font-weight: 700;
        }

        /* ── Print Styles ───────────────────────────────── */
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

            .return-sheet,
            .return-sheet * {
                visibility: visible;
            }

            html, body {
                margin: 0 !important;
                padding: 0 !important;
                background: #fff !important;
            }

            .nxl-container,
            .nxl-content,
            .main-content,
            .page-header,
            .row, .col-12 {
                margin: 0 !important;
                padding: 0 !important;
            }

            .return-sheet {
                position: fixed;
                top: 0;
                left: 0;
                width: 100% !important;
                max-width: 100% !important;
                margin: 0 !important;
                padding: 36px 44px !important;
                border: none !important;
                box-shadow: none !important;
                border-radius: 0 !important;
                background: #ffffff !important;
            }

            .return-sheet .bg-light,
            .return-sheet [class*="bg-light"],
            .return-sheet [class*="bg-opacity"] {
                background-color: #ffffff !important;
                background: #ffffff !important;
            }

            .return-sheet .rounded.border,
            .return-sheet .border {
                border-color: #e2e8f0 !important;
            }

            .d-print-none,
            .return-corner-ribbon {
                display: none !important;
                visibility: hidden !important;
            }
        }
    </style>
@endpush

@section('content')
    <div class="row text-dark">
        <div class="col-12">

            @if ($errors->any())
                <x-ui.toast :auto="true" type="error" title="{{ $errors->first() }}" />
            @endif

            <!-- Standard ERP Sales Return / Credit Note Sheet -->
            <div class="return-sheet print-area mb-5">
                <!-- Status Corner Ribbon Tag Patti -->
                <div class="return-corner-ribbon">
                    @php
                        $ribbonClass = match($return->status) {
                            'Completed' => 'ribbon-completed',
                            'Approved'  => 'ribbon-approved',
                            'Cancelled' => 'ribbon-cancelled',
                            default     => 'ribbon-draft',
                        };
                    @endphp
                    <div class="ribbon-inner {{ $ribbonClass }}">
                        {{ $return->status }}
                    </div>
                </div>

                <!-- 1. Document Header -->
                <div class="row align-items-start pb-4 border-bottom mb-4">
                    <div class="col-7">
                        <div class="d-flex align-items-center mb-3">
                            <div class="avatar-text text-white fs-2 fw-bold me-3 shadow-sm d-flex align-items-center justify-content-center" style="border-radius: 8px; width: 50px; height: 50px; flex-shrink: 0; background-color: #1e40af !important;">
                                {{ strtoupper(substr(tenant() ? tenant()->name : 'R', 0, 1)) }}
                            </div>
                            <div>
                                <h4 class="fw-bold text-dark mb-0 fs-17">{{ tenant() ? tenant()->name : 'SaaS ERP Workspace' }}</h4>
                                <span class="fs-11 text-muted">Sales Return / Credit Voucher Unit</span>
                            </div>
                        </div>
                        <div class="fs-12 text-secondary leading-relaxed">
                            <div>H-1, Industrial Area, Sukher, Udaipur 313001, Rajasthan, India</div>
                            <div><strong>GSTIN:</strong> 08AAFCS1234E1Z0 &nbsp;|&nbsp; <strong>State Code:</strong> 08 (Rajasthan)</div>
                            <div><strong>Email:</strong> {{ tenant() ? tenant()->billing_email : 'billing@sasserp.com' }} &nbsp;|&nbsp; <strong>Phone:</strong> +91 294 2440230</div>
                        </div>
                    </div>

                    <div class="col-5 text-end">
                        <h2 class="fw-black text-uppercase tracking-wide mb-1" style="color: #1e40af; font-size: 22px; letter-spacing: 1px;">CREDIT NOTE</h2>
                        <div class="fs-14 fw-bold text-dark"># {{ $return->return_number }}</div>
                        
                        <div class="mt-3 fs-12 text-secondary">
                            <div class="d-flex justify-content-end gap-2 mb-1">
                                <span class="text-muted">Return Date:</span>
                                <strong class="text-dark">{{ date('d-M-Y', strtotime($return->return_date)) }}</strong>
                            </div>
                            @if ($return->salesOrder)
                                <div class="d-flex justify-content-end gap-2 mb-1">
                                    <span class="text-muted">Origin Order:</span>
                                    <a href="{{ route('sales.orders.show', $return->sales_order_id) }}" class="fw-bold text-primary">
                                        {{ $return->salesOrder->sales_order_number }}
                                    </a>
                                </div>
                            @endif
                            <div class="d-flex justify-content-end gap-2">
                                <span class="text-muted">Status:</span>
                                <strong class="{{ $return->status === 'Completed' ? 'text-success' : ($return->status === 'Cancelled' ? 'text-danger' : 'text-primary') }}">
                                    {{ $return->status }}
                                </strong>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. Address & Return Information (2 Box Columns) -->
                <div class="row g-4 mb-4 fs-12 text-dark">
                    <!-- Left: Customer Details -->
                    <div class="col-6">
                        <div class="p-3 bg-light bg-opacity-40 rounded border h-100 d-flex flex-column justify-content-between">
                            <div>
                                <span class="fs-10 fw-bold text-uppercase text-muted d-block mb-3" style="letter-spacing: 0.5px;">Customer / Client:</span>
                                
                                <div class="d-flex align-items-center mb-3 pb-2 border-bottom">
                                    <div class="avatar-text bg-primary text-white fw-bold me-3 d-flex align-items-center justify-content-center rounded-3 shadow-sm" style="width: 42px; height: 42px; font-size: 16px; flex-shrink: 0; background-color: #1e40af !important;">
                                        {{ strtoupper(substr($return->customer?->name ?: ($return->salesOrder?->customer?->name ?: 'C'), 0, 1)) }}
                                    </div>
                                    <div>
                                        <h6 class="fw-bold text-dark mb-0 fs-14">{{ $return->customer?->name ?: ($return->salesOrder?->customer?->name ?: '—') }}</h6>
                                        <span class="fs-11 text-muted">Client Account</span>
                                    </div>
                                </div>

                                <div class="fs-12 text-secondary mb-2">
                                    <div class="d-flex align-items-center mb-1.5">
                                        <i class="feather-mail me-2 text-muted fs-12" style="width: 16px;"></i>
                                        <span class="text-muted me-1">Email:</span>
                                        <strong class="text-dark">{{ $return->customer?->email ?: ($return->salesOrder?->customer?->email ?: '—') }}</strong>
                                    </div>
                                    <div class="d-flex align-items-center mb-1.5">
                                        <i class="feather-phone me-2 text-muted fs-12" style="width: 16px;"></i>
                                        <span class="text-muted me-1">Phone:</span>
                                        <strong class="text-dark">{{ $return->customer?->phone ?: ($return->salesOrder?->customer?->phone ?: '—') }}</strong>
                                    </div>
                                </div>

                                @if ($return->reason)
                                    <div class="mt-2 pt-2 border-top text-secondary fs-11">
                                        <strong class="text-dark d-block mb-1"><i class="feather-alert-circle me-1 fs-11 text-muted"></i>Return Reason:</strong>
                                        <span style="white-space: pre-wrap;" class="text-muted leading-relaxed">{{ $return->reason }}</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Right: Return & Refund Summary -->
                    <div class="col-6">
                        <div class="p-3 bg-light bg-opacity-40 rounded border h-100">
                            <span class="fs-10 fw-bold text-uppercase text-muted d-block mb-2" style="letter-spacing: 0.5px;">Return & Refund Summary:</span>

                            @php
                                $displayRefundTotal = $return->total_refund_amount > 0
                                    ? $return->total_refund_amount
                                    : ($return->total_amount > 0
                                        ? $return->total_amount
                                        : $return->items->sum(fn($i) => (float)$i->quantity * (float)$i->unit_price));
                            @endphp

                            <div class="d-flex justify-content-between mb-2 pb-1 border-bottom">
                                <span class="text-muted">Total Refund Amount:</span>
                                <strong class="fw-black text-danger fs-15">₹{{ number_format($displayRefundTotal, 2) }}</strong>
                            </div>

                            @if ($return->salesOrder)
                                <div class="d-flex justify-content-between mb-2 pb-1 border-bottom">
                                    <span class="text-muted">Originating Order:</span>
                                    <a href="{{ route('sales.orders.show', $return->sales_order_id) }}" class="fw-bold text-primary">
                                        {{ $return->salesOrder->sales_order_number }}
                                    </a>
                                </div>
                            @endif

                            <div class="d-flex justify-content-between mb-2 pb-1 border-bottom">
                                <span class="text-muted">Return Date:</span>
                                <span class="fw-semibold text-dark">{{ date('d-M-Y', strtotime($return->return_date)) }}</span>
                            </div>

                            <div class="d-flex justify-content-between">
                                <span class="text-muted">Inventory Action:</span>
                                <span class="fw-bold {{ $return->status === 'Completed' ? 'text-success' : 'text-warning' }}">
                                    {{ $return->status === 'Completed' ? 'Restocked to Warehouse' : 'Pending Restock' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 3. Line Items Table -->
                <div class="mb-4">
                    <h6 class="fw-bold text-dark fs-13 mb-3"><i class="feather-rotate-ccw me-1 text-danger"></i> Returned Line Items</h6>
                    <div class="table-responsive">
                        <table class="table return-table align-middle w-100 mb-0">
                            <thead>
                                <tr>
                                    <th style="width: 5%;" class="text-center">#</th>
                                    <th style="width: 45%;">Product Details</th>
                                    <th style="width: 25%;">Restock Warehouse</th>
                                    <th class="text-end" style="width: 10%;">Qty</th>
                                    <th class="text-end" style="width: 15%;">Refund Price (₹)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($return->items as $idx => $item)
                                    <tr>
                                        <td class="text-center text-muted fs-12">{{ $idx + 1 }}</td>
                                        <td>
                                            <strong class="text-dark d-block fs-13">{{ $item->product?->name ?: 'Item #' . ($idx + 1) }}</strong>
                                            @if($item->product?->sku)
                                                <span class="text-muted fs-11 me-2">SKU: {{ $item->product->sku }}</span>
                                            @endif
                                            @if(!empty($item->serial_numbers))
                                                <div class="mt-1">
                                                    <span class="badge bg-soft-info text-info font-monospace fs-11 p-1">
                                                        <i class="feather-hash me-1"></i>Returned Serials: {{ $item->serial_numbers }}
                                                    </span>
                                                </div>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="fw-semibold text-dark fs-12">
                                                <i class="feather-package me-1 text-muted fs-11"></i>{{ $item->warehouse?->name ?: 'Main Warehouse' }}
                                            </span>
                                        </td>
                                        <td class="text-end fw-bold text-danger">{{ (int)$item->quantity }}</td>
                                        <td class="text-end fw-bold text-dark">
                                            ₹{{ number_format($item->unit_price, 2) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="4" class="text-end text-muted fw-semibold">Total Credit Refund:</td>
                                    <td class="text-end fw-black text-danger fs-14">₹{{ number_format($displayRefundTotal, 2) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                <!-- 4. Signature Footer Block -->
                <div class="row pt-4 border-top fs-11 text-muted align-items-end mt-4">
                    <div class="col-7">
                        <div>This is an official Credit Voucher / Sales Return document.</div>
                        <div class="mt-1">Generated: {{ date('d M Y, h:i A') }} &nbsp;|&nbsp; {{ tenant() ? tenant()->name : 'SaaS ERP' }}</div>
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
