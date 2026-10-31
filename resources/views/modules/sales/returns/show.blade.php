@extends('layouts.duralux')

@section('title', __('crm.sales_returns') . ' | ' . $return->return_number)
@section('page-title', __('crm.sales_returns') . ' ' . $return->return_number)
@section('breadcrumb', __('crm.sales') . ' / ' . __('crm.sales_returns') . ' / ' . __('crm.view_record'))

@section('page-actions')
    <a href="{{ route('sales.returns.index') }}" class="action-dropdown-btn" title="{{ __('crm.view_return_details') }}" data-bs-toggle="tooltip">
        <i class="feather feather-arrow-left"></i>
    </a>

    <a href="{{ route('sales.returns.download', $return->id) }}" class="btn btn-sm btn-primary fw-bold px-3">
        <i class="feather-download me-1.5"></i>{{ __('crm.download_pdf') }}
    </a>

    @if (in_array($return->status, ['Pending', 'Draft']))
        <form action="{{ route('sales.returns.approve', $return->id) }}" method="POST" id="approveReturnForm" class="d-inline d-print-none">
            @csrf
            <button type="button" class="btn btn-sm btn-success py-1.5 px-3 fw-bold" onclick="confirmAction({ title: 'Approve Sales Return', message: 'Approve sales return {{ $return->return_number }}? This will restore returned stock to inventory.', variant: 'success', confirmText: 'Approve & Restock' }, function() { document.getElementById('approveReturnForm').submit(); })">
                <i class="feather-check-circle me-1"></i>{{ __('crm.approve_restock_inventory') }}
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

        .return-avatar-box {
            background-color: #1e40af !important;
        }

        .return-main-title {
            color: #1e40af;
        }

        /* ── Light Mode Explicit Styles ── */
        html:not(.app-skin-dark) .return-sheet {
            background: #ffffff !important;
            border-color: #e2e8f0 !important;
            color: #1e293b !important;
        }
        html:not(.app-skin-dark) .return-sheet .text-dark {
            color: #0f172a !important;
        }
        html:not(.app-skin-dark) .return-sheet .text-muted {
            color: #64748b !important;
        }
        html:not(.app-skin-dark) .return-sheet .text-secondary {
            color: #475569 !important;
        }
        html:not(.app-skin-dark) .return-sheet .bg-light,
        html:not(.app-skin-dark) .return-sheet [class*="bg-light"] {
            background-color: #f8fafc !important;
            border-color: #e2e8f0 !important;
        }

        /* ── Dark Mode Support ── */
        html.app-skin-dark .return-sheet {
            background-color: #0f172a !important;
            border-color: #1e293b !important;
            color: #e2e8f0 !important;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.45) !important;
        }

        html.app-skin-dark .return-sheet .return-main-title {
            color: #60a5fa !important;
        }

        html.app-skin-dark .return-sheet .return-avatar-box {
            background-color: #2563eb !important;
        }

        html.app-skin-dark .return-sheet .text-dark,
        html.app-skin-dark .return-sheet h2,
        html.app-skin-dark .return-sheet h4,
        html.app-skin-dark .return-sheet h6,
        html.app-skin-dark .return-sheet strong {
            color: #f8fafc !important;
        }

        html.app-skin-dark .return-sheet .text-muted {
            color: #94a3b8 !important;
        }

        html.app-skin-dark .return-sheet .text-secondary {
            color: #cbd5e1 !important;
        }

        html.app-skin-dark .return-sheet .border,
        html.app-skin-dark .return-sheet .border-bottom,
        html.app-skin-dark .return-sheet .border-top {
            border-color: #1e293b !important;
        }

        html.app-skin-dark .return-sheet .bg-light,
        html.app-skin-dark .return-sheet [class*="bg-light"],
        html.app-skin-dark .return-sheet [class*="bg-opacity"] {
            background-color: #1e293b !important;
            border-color: #334155 !important;
        }

        html.app-skin-dark .return-sheet .return-table thead th {
            background-color: #0b1329 !important;
            color: #94a3b8 !important;
            border-top-color: #1e293b !important;
            border-bottom-color: #334155 !important;
        }

        html.app-skin-dark .return-sheet .return-table tbody td {
            border-bottom-color: #1e293b !important;
            color: #e2e8f0 !important;
        }

        html.app-skin-dark .return-sheet .return-table tbody tr:hover {
            background-color: #1e293b !important;
        }

        html.app-skin-dark .return-sheet .return-table tfoot td {
            background-color: #0b1329 !important;
            border-top-color: #334155 !important;
            color: #f8fafc !important;
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
                color: #000000 !important;
            }

            .return-sheet .return-main-title {
                color: #1e40af !important;
            }

            .return-sheet .return-avatar-box {
                background-color: #1e40af !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .return-sheet .text-dark,
            .return-sheet h2,
            .return-sheet h4,
            .return-sheet h6,
            .return-sheet strong {
                color: #000000 !important;
            }

            .return-sheet .text-muted {
                color: #555555 !important;
            }

            .return-sheet .text-secondary {
                color: #333333 !important;
            }

            .return-sheet .bg-light,
            .return-sheet [class*="bg-light"],
            .return-sheet [class*="bg-opacity"] {
                background-color: #ffffff !important;
                background: #ffffff !important;
                border-color: #cccccc !important;
            }

            .return-sheet .rounded.border,
            .return-sheet .border,
            .return-sheet .border-top,
            .return-sheet .border-bottom {
                border-color: #cccccc !important;
            }

            .return-sheet .return-table thead th,
            .return-sheet .return-table tfoot td {
                background-color: #f1f5f9 !important;
                color: #000000 !important;
                border-color: #cccccc !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .return-sheet .return-table tbody td {
                color: #000000 !important;
                border-bottom-color: #e2e8f0 !important;
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
                        @php
                            $sellerOrg = billing_organization_details($return);
                        @endphp
                        <div class="d-flex align-items-center mb-3">
                            <div class="avatar-text return-avatar-box text-white fs-2 fw-bold me-3 shadow-sm d-flex align-items-center justify-content-center" style="border-radius: 8px; width: 50px; height: 50px; flex-shrink: 0;">
                                {{ $sellerOrg['avatar_char'] }}
                            </div>
                            <div>
                                <h4 class="fw-bold text-dark mb-0 fs-17">{{ $sellerOrg['name'] }}</h4>
                                <span class="fs-11 text-muted">{{ $sellerOrg['unit_subtext'] }}</span>
                            </div>
                        </div>
                        <div class="fs-12 text-secondary leading-relaxed">
                            <div>{{ $sellerOrg['address'] }}</div>
                            <div><strong>GSTIN:</strong> {{ $sellerOrg['gstin'] }} &nbsp;|&nbsp; <strong>State Code:</strong> {{ $sellerOrg['state_code'] }} ({{ $sellerOrg['state_name'] }})</div>
                            <div>
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
                        </div>
                    </div>

                    <div class="col-5 text-end">
                        <h2 class="return-main-title fw-black text-uppercase tracking-wide mb-1" style="font-size: 22px; letter-spacing: 1px;">{{ __('crm.credit_note') }}</h2>
                        <div class="fs-14 fw-bold text-dark"># {{ $return->return_number }}</div>
                        
                        <div class="mt-3 fs-12 text-secondary">
                            <div class="d-flex justify-content-end gap-2 mb-1">
                                <span class="text-muted">{{ __('crm.return_date') }}:</span>
                                <strong class="text-dark">{{ date('d-M-Y', strtotime($return->return_date)) }}</strong>
                            </div>
                            @if ($return->salesOrder)
                                <div class="d-flex justify-content-end gap-2 mb-1">
                                    <span class="text-muted">{{ __('crm.sales_order_ref') }}:</span>
                                    <a href="{{ route('sales.orders.show', $return->sales_order_id) }}" class="fw-bold text-primary">
                                        {{ $return->salesOrder->sales_order_number }}
                                    </a>
                                </div>
                            @endif
                            <div class="d-flex justify-content-end gap-2">
                                <span class="text-muted">{{ __('crm.status') }}:</span>
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
                                <span class="fs-10 fw-bold text-uppercase text-muted d-block mb-3" style="letter-spacing: 0.5px;">{{ __('crm.customer') }}:</span>
                                
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
                                        <strong class="text-dark d-block mb-1"><i class="feather-alert-circle me-1 fs-11 text-muted"></i>{{ __('crm.reason_for_return') }}:</strong>
                                        <span style="white-space: pre-wrap;" class="text-muted leading-relaxed">{{ $return->reason }}</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Right: Return & Refund Summary -->
                    <div class="col-6">
                        <div class="p-3 bg-light bg-opacity-40 rounded border h-100">
                            <span class="fs-10 fw-bold text-uppercase text-muted d-block mb-2" style="letter-spacing: 0.5px;">{{ __('crm.return_details') }}:</span>

                            @php
                                $displayRefundTotal = $return->total_refund_amount > 0
                                    ? $return->total_refund_amount
                                    : ($return->total_amount > 0
                                        ? $return->total_amount
                                        : $return->items->sum(fn($i) => (float)$i->quantity * (float)$i->unit_price));
                            @endphp

                            <div class="d-flex justify-content-between mb-2 pb-1 border-bottom">
                                <span class="text-muted">{{ __('crm.refund_amount') }}:</span>
                                <strong class="fw-black text-danger fs-15">{{ format_currency($displayRefundTotal) }}</strong>
                            </div>

                            @if ($return->salesOrder)
                                <div class="d-flex justify-content-between mb-2 pb-1 border-bottom">
                                    <span class="text-muted">{{ __('crm.sales_order_ref') }}:</span>
                                    <a href="{{ route('sales.orders.show', $return->sales_order_id) }}" class="fw-bold text-primary">
                                        {{ $return->salesOrder->sales_order_number }}
                                    </a>
                                </div>
                            @endif

                            <div class="d-flex justify-content-between mb-2 pb-1 border-bottom">
                                <span class="text-muted">{{ __('crm.return_date') }}:</span>
                                <span class="fw-semibold text-dark">{{ date('d-M-Y', strtotime($return->return_date)) }}</span>
                            </div>

                            <div class="d-flex justify-content-between">
                                <span class="text-muted">{{ __('crm.status') }}:</span>
                                <span class="fw-bold {{ $return->status === 'Completed' ? 'text-success' : 'text-warning' }}">
                                    {{ $return->status === 'Completed' ? 'Restocked to Warehouse' : 'Pending Restock' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 3. Line Items Table -->
                <div class="mb-4">
                    <h6 class="fw-bold text-dark fs-13 mb-3"><i class="feather-rotate-ccw me-1 text-danger"></i> {{ __('crm.items_to_return') }}</h6>
                    <div class="table-responsive">
                        <table class="table return-table align-middle w-100 mb-0">
                            <thead>
                                <tr>
                                    <th style="width: 5%;" class="text-center">#</th>
                                    <th style="width: 45%;">{{ __('crm.product_details_serials') }}</th>
                                    <th style="width: 25%;">{{ __('crm.restock_warehouse') }}</th>
                                    <th class="text-end" style="width: 10%;">{{ __('crm.return_qty') }}</th>
                                    <th class="text-end" style="width: 15%;">{{ __('crm.refund_unit_price') }}</th>
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
                                            {{ format_currency($item->unit_price) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="4" class="text-end text-muted fw-semibold">{{ __('crm.refund_amount') }}:</td>
                                    <td class="text-end fw-black text-danger fs-14">{{ format_currency($displayRefundTotal) }}</td>
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

