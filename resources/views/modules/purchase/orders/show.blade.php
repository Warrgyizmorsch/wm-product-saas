@extends('layouts.duralux')

@section('title', 'Purchase Order ' . $order->purchase_order_number . ' | SaaS ERP')
@section('page-title', __('purchase.purchase_orders') . ' — ' . $order->purchase_order_number)
@section('breadcrumb')
    <a href="{{ route('purchase.orders.index') }}">{{ __('purchase.purchase_orders') }}</a> &gt; {{ $order->purchase_order_number }}
@endsection

@section('page-actions')
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <!-- Back Button -->
        <a href="{{ route('purchase.orders.index') }}" class="btn btn-sm btn-light border d-inline-flex align-items-center justify-content-center px-2.5" title="{{ __('purchase.back') }}" data-bs-toggle="tooltip">
            <i class="feather feather-arrow-left"></i>
        </a>


        <!-- Advance Payment Button (If Approved & has balance due) -->
        @if (in_array($order->status, ['Approved', 'Partially Received']))
            <button type="button" class="btn btn-sm btn-success fw-bold px-3 d-inline-flex align-items-center" data-bs-toggle="modal" data-bs-target="#advancePaymentModal">
                <i class="feather-dollar-sign me-1.5"></i>Advance Payment
            </button>
        @endif

        <!-- Create Return (If Goods Received) -->
        @if (in_array($order->status, ['Approved', 'Partially Received', 'Received', 'Completed']))
            @php
                $latestGrn = \App\Domains\Purchase\Models\GoodsReceiptNote::where('purchase_order_id', $order->id)->latest()->first();
            @endphp
            <a href="{{ route('purchase.returns.create', $latestGrn ? ['goods_receipt_note_id' => $latestGrn->id, 'mode' => 'grn'] : ['purchase_order_id' => $order->id, 'mode' => 'grn']) }}" class="btn btn-sm btn-light border fw-bold px-3 d-inline-flex align-items-center">
                <i class="feather-corner-up-left me-1.5"></i>Create Return
            </a>
        @endif

        <!-- Direct PDF Download Button -->
        <a href="{{ route('purchase.orders.download', $order->id) }}" class="btn btn-sm btn-outline-danger fw-bold px-3 d-inline-flex align-items-center" title="Download Official Purchase Order PDF">
            <i class="feather-download me-1.5"></i>PDF
        </a>

        @php
            $vendorPhone = $order->vendor?->phone ?: '';
            $vendorEmail = $order->vendor?->email ?: '';
            $vendorName = $order->vendor?->name ?: 'Vendor';
            $poGrandTotal = format_currency($order->grand_total);
        @endphp

        <!-- MORE Dropdown Button -->
        <div class="dropdown d-inline-flex align-items-center">
            <button class="btn btn-sm btn-light border fw-bold px-3 dropdown-toggle d-inline-flex align-items-center" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="More PO Actions">
                <i class="feather-more-horizontal me-1"></i>MORE
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow border p-1.5" style="min-width: 250px;">
                <li>
                    <button type="button" 
                            class="dropdown-item py-2 px-3 pe-4 fs-12 fw-medium btn-open-po-wa-modal d-flex align-items-center text-nowrap"
                            data-po-number="{{ $order->purchase_order_number }}"
                            data-vendor-name="{{ $vendorName }}"
                            data-vendor-phone="{{ $vendorPhone }}"
                            data-total-amount="{{ $poGrandTotal }}">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" class="me-2.5"><path fill-rule="evenodd" clip-rule="evenodd" d="M12 2C6.477 2 2 6.477 2 12c0 2.15.68 4.14 1.838 5.776L2.5 21.5l3.876-1.303A9.957 9.957 0 0012 22c5.523 0 10-4.477 10-10S17.523 2 12 2zm0 18a7.96 7.96 0 01-4.086-1.125l-.293-.174-2.295.771.785-2.238-.191-.304A7.96 7.96 0 014 12c0-4.418 3.582-8 8-8s8 3.582 8 8-3.582 8-8 8z" fill="#25D366"/><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.447-.521.149-.174.198-.298.298-.497.099-.198.05-.372-.025-.521-.075-.149-.669-1.612-.916-2.206-.242-.579-.487-.501-.669-.51l-.57-.01c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.095 3.2 5.076 4.487.709.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.695.248-1.29.173-1.414-.074-.124-.272-.198-.57-.347z" fill="#25D366"/></svg>
                        Send via WhatsApp
                    </button>
                </li>
                <li>
                    <button type="button" 
                            class="dropdown-item py-2 px-3 pe-4 fs-12 fw-medium btn-open-po-email-modal d-flex align-items-center text-nowrap"
                            data-po-number="{{ $order->purchase_order_number }}"
                            data-vendor-name="{{ $vendorName }}"
                            data-vendor-email="{{ $vendorEmail }}"
                            data-total-amount="{{ $poGrandTotal }}">
                        <i class="feather-mail me-2.5 text-primary fs-14"></i>
                        Send via Email
                    </button>
                </li>
                <li>
                    <a href="{{ route('purchase.orders.download', $order->id) }}" class="dropdown-item py-2 px-3 pe-4 fs-12 fw-medium d-flex align-items-center text-nowrap">
                        <i class="feather-download me-2.5 text-danger fs-14"></i>
                        Download PDF Document
                    </a>
                </li>
                <li><hr class="dropdown-divider my-1"></li>
                <li>
                    <a href="javascript:void(0)" onclick="window.print()" class="dropdown-item py-2 px-3 pe-4 fs-12 fw-medium d-flex align-items-center text-nowrap">
                        <i class="feather-printer me-2.5 text-secondary fs-14"></i>
                        Print Purchase Order
                    </a>
                </li>

                @if ($order->status === 'Draft')
                    <li><hr class="dropdown-divider my-1"></li>
                    <li>
                        <button type="button" class="dropdown-item py-2 px-3 pe-4 fs-12 fw-medium text-warning d-flex align-items-center text-nowrap" onclick="openRemindModal('{{ route('purchase.orders.remind', $order->id) }}', '{{ $order->purchase_order_number }}')">
                            <i class="feather-bell me-2.5 text-warning fs-14"></i>
                            Send Quick Reminder
                        </button>
                    </li>
                    <li>
                        <a href="{{ route('purchase.orders.edit', $order->id) }}" class="dropdown-item py-2 px-3 pe-4 fs-12 fw-medium d-flex align-items-center text-nowrap">
                            <i class="feather-edit me-2.5 text-muted fs-14"></i>
                            {{ __('purchase.edit') }}
                        </a>
                    </li>
                    <li>
                        <form action="{{ route('purchase.orders.destroy', $order->id) }}" method="POST" id="deletePoShowForm" class="m-0">
                            @csrf
                            @method('DELETE')
                            <button type="button" class="dropdown-item py-2 px-3 pe-4 fs-12 fw-medium text-danger d-flex align-items-center text-nowrap" onclick="confirmAction({ title: 'Delete Purchase Order', message: '{{ __('purchase.confirm_delete_po') }}', variant: 'danger', confirmText: 'Delete' }, function() { document.getElementById('deletePoShowForm').submit(); })">
                                <i class="feather-trash-2 me-2.5 text-danger fs-14"></i>
                                {{ __('purchase.delete') }}
                            </button>
                        </form>
                    </li>
                @endif
            </ul>
        </div>
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

        .ribbon-paid { background-color: #16a34a; }
        .ribbon-partially-paid { background-color: #d97706; }
        .ribbon-posted { background-color: #2563eb; }
        .ribbon-draft { background-color: #64748b; }
        .ribbon-cancelled { background-color: #dc2626; }

        .po-table th {
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
        .po-table td {
            padding: 9px 12px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 12px;
            color: #1e293b;
        }

        .summary-table td {
            padding: 4px 10px;
            font-size: 12px;
        }

        .po-main-title {
            color: #1e40af;
        }

        .po-avatar-box {
            background-color: #1e40af !important;
        }

        .po-rich-text {
            line-height: 1.6;
        }
        .po-rich-text ol,
        .po-rich-text ul {
            padding-left: 20px;
            margin-top: 4px;
            margin-bottom: 4px;
        }
        .po-rich-text ol {
            list-style-type: decimal !important;
        }
        .po-rich-text ul {
            list-style-type: disc !important;
        }
        .po-rich-text li {
            margin-bottom: 5px;
            color: #334155;
            padding-left: 3px;
        }
        .po-rich-text p {
            margin-bottom: 6px;
        }

        /* Dark mode support */
        html.app-skin-dark .invoice-sheet {
            background-color: #0f172a !important;
            border-color: #1e293b !important;
            color: #e2e8f0 !important;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.45) !important;
        }

        html.app-skin-dark .invoice-sheet .po-main-title {
            color: #60a5fa !important;
        }

        html.app-skin-dark .invoice-sheet .po-avatar-box {
            background-color: #2563eb !important;
        }

        html.app-skin-dark .invoice-sheet .text-dark,
        html.app-skin-dark .invoice-sheet h2,
        html.app-skin-dark .invoice-sheet h4,
        html.app-skin-dark .invoice-sheet h6,
        html.app-skin-dark .invoice-sheet strong {
            color: #f8fafc !important;
        }

        html.app-skin-dark .invoice-sheet .text-muted {
            color: #94a3b8 !important;
        }

        html.app-skin-dark .invoice-sheet .text-secondary {
            color: #cbd5e1 !important;
        }

        html.app-skin-dark .invoice-sheet .border,
        html.app-skin-dark .invoice-sheet .border-bottom,
        html.app-skin-dark .invoice-sheet .border-top {
            border-color: #1e293b !important;
        }

        html.app-skin-dark .invoice-sheet .bg-light {
            background-color: #1e293b !important;
            color: #e2e8f0 !important;
        }

        html.app-skin-dark .invoice-sheet .po-table th {
            background-color: #1e293b !important;
            color: #94a3b8 !important;
            border-color: #334155 !important;
        }

        html.app-skin-dark .invoice-sheet .po-table td {
            border-color: #1e293b !important;
            color: #e2e8f0 !important;
        }

        @media print {
            @page {
                size: A4 portrait;
                margin: 0 !important;
            }

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

            html, body, main, .nxl-container, .nxl-content, .main-content, .row, .col-12 {
                background: #ffffff !important;
                margin: 0 !important;
                padding: 0 !important;
                height: auto !important;
                overflow: visible !important;
                font-size: 10px !important;
            }

            .invoice-sheet {
                position: static !important;
                width: 100% !important;
                max-width: 100% !important;
                margin: 0 !important;
                padding: 4mm 8mm !important;
                border: none !important;
                box-shadow: none !important;
                background: #ffffff !important;
            }
        }
    </style>
@endpush

@section('content')
    <div class="row text-dark">
        <div class="col-12">

            <!-- Subcontract Alert Banner (If Applicable) -->
            @if($order->is_subcontract)
                @php
                    $firstItemWithMo = $order->items ? $order->items->first(fn($i) => !empty($i->production_order_operation_id)) : null;
                    $linkedMoOp = $firstItemWithMo?->productionOrderOperation;
                    $linkedMo = $linkedMoOp?->order;
                @endphp
                <div class="alert alert-soft-primary border border-primary-subtle d-flex align-items-center justify-content-between p-3 mb-4 rounded-3 shadow-sm d-print-none" style="max-width: 900px; margin: 0 auto 1.5rem auto;">
                    <div class="d-flex align-items-center">
                        <div class="avatar avatar-md bg-primary text-white rounded-circle me-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 40px; height: 40px;">
                            <i class="feather-truck fs-16"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold text-primary mb-0.5 fs-14">Subcontract Service Purchase Order</h6>
                            <p class="text-muted fs-12 mb-0">
                                Purchases vendor service.
                                @if($linkedMo)
                                    Linked Manufacturing Order: <a href="{{ route('production.orders.show', $linkedMo->id) }}" class="fw-bold text-primary">{{ $linkedMo->order_number }}</a>
                                    @if($linkedMoOp) | Op: {{ $linkedMoOp->operation_number }} ({{ $linkedMoOp->name }}) @endif
                                @endif
                            </p>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Cancelled / Rejected Alert Banner -->
            @if (in_array($order->status, ['Cancelled', 'Rejected']))
                <div class="alert alert-soft-danger border border-danger-subtle d-flex align-items-center justify-content-between p-3 mb-4 rounded-3 shadow-sm d-print-none" style="max-width: 900px; margin: 0 auto 1.5rem auto;">
                    <div class="d-flex align-items-center">
                        <div class="avatar avatar-md bg-danger text-white rounded-circle me-3 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 40px; height: 40px;">
                            <i class="feather-slash fs-16"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold text-danger mb-0.5 fs-14">Purchase Order Cancelled / Rejected</h6>
                            <p class="text-muted fs-12 mb-0">
                                Reason: <strong>{{ $order->rejection_reason ?: 'No rejection reason specified.' }}</strong>
                            </p>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Standard ERP Purchase Order Sheet (Matching Sales Invoice) -->
            <div class="invoice-sheet print-area mb-5">
                <!-- Status Corner Ribbon Tag Patti -->
                <div class="invoice-corner-ribbon">
                    @php
                        $ribbonClass = match($order->status) {
                            'Approved' => 'ribbon-posted',
                            'Completed' => 'ribbon-paid',
                            'Received' => 'ribbon-paid',
                            'Partially Received' => 'ribbon-partially-paid',
                            'Cancelled', 'Rejected' => 'ribbon-cancelled',
                            default => 'ribbon-draft',
                        };
                    @endphp
                    <div class="ribbon-inner {{ $ribbonClass }}">
                        {{ $order->status }}
                    </div>
                </div>

                <!-- 1. Document Header -->
                <div class="row align-items-start pb-4 border-bottom mb-4">
                    <div class="col-7">
                        @php
                            $buyerOrg = billing_organization_details($order);
                            $isItemWiseTax = ($order->tax_type === 'item_wise_tax');
                            $hasTax = ($order->tax_type !== 'without_tax') && (((float)($order->tax_amount ?? 0) > 0) || $order->items->contains(function($item) {
                                return (float)($item->tax_amount ?? 0) > 0 || (float)($item->tax_percent ?? 0) > 0;
                            }));
                            $hasDiscount = ((float)($order->discount_amount ?? 0) > 0) || $order->items->contains(function($item) {
                                return (float)($item->discount_amount ?? 0) > 0;
                            });
                        @endphp
                        <div class="d-flex align-items-center mb-3">
                            <div class="avatar-text po-avatar-box text-white fs-2 fw-bold me-3 shadow-sm d-flex align-items-center justify-content-center" style="border-radius: 8px; width: 50px; height: 50px; flex-shrink: 0;">
                                {{ $buyerOrg['avatar_char'] ?? strtoupper(substr($buyerOrg['name'] ?? 'P', 0, 1)) }}
                            </div>
                            <div>
                                <h4 class="fw-bold text-dark mb-0 fs-17">{{ $buyerOrg['name'] }}</h4>
                                <span class="fs-11 text-muted">{{ $buyerOrg['unit_subtext'] }}</span>
                            </div>
                        </div>
                        <div class="fs-12 text-secondary leading-relaxed">
                            <div>{{ $buyerOrg['address'] }}</div>
                            <div>
                                @if(!empty($buyerOrg['gstin']))
                                    <strong>GSTIN:</strong> {{ $buyerOrg['gstin'] }}
                                @endif
                                @if(!empty($buyerOrg['state_code']) && !empty($buyerOrg['state_name']))
                                    @if(!empty($buyerOrg['gstin'])) &nbsp;|&nbsp; @endif
                                    <strong>State Code:</strong> {{ $buyerOrg['state_code'] }} ({{ $buyerOrg['state_name'] }})
                                @endif
                            </div>
                            <div>
                                @if(!empty($buyerOrg['email']))
                                    <strong>Email:</strong> {{ $buyerOrg['email'] }}
                                @endif
                                @if(!empty($buyerOrg['email']) && !empty($buyerOrg['phone']))
                                    &nbsp;|&nbsp;
                                @endif
                                @if(!empty($buyerOrg['phone']))
                                    <strong>Phone:</strong> {{ $buyerOrg['phone'] }}
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="col-5 text-end">
                        <h2 class="po-main-title fw-black text-uppercase tracking-wide mb-1" style="font-size: 22px; letter-spacing: 1px;">PURCHASE ORDER</h2>
                        <div class="fs-14 fw-bold text-dark"># {{ $order->purchase_order_number }}</div>

                        <div class="mt-3 fs-12 text-secondary">
                            <div class="d-flex justify-content-end gap-2 mb-1">
                                <span class="text-muted">PO Date:</span>
                                <strong class="text-dark">{{ $order->date ? $order->date->format('d-M-Y') : '—' }}</strong>
                            </div>
                            @if($order->delivery_date)
                                <div class="d-flex justify-content-end gap-2 mb-1">
                                    <span class="text-muted">Expected Delivery:</span>
                                    <strong class="text-primary">{{ $order->delivery_date->format('d-M-Y') }}</strong>
                                </div>
                            @endif
                            @if($order->payment_terms)
                                <div class="d-flex justify-content-end gap-2">
                                    <span class="text-muted">Payment Terms:</span>
                                    <strong class="text-dark">{{ $order->payment_terms }}</strong>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- 2. Address & References Section (Standard 2-Box ERP Layout) -->
                <div class="row g-4 mb-4 fs-12 text-dark">
                    <!-- Box 1 (Left 50%): Supplier Details (Vendor) -->
                    <div class="col-6">
                        <div class="p-3 bg-light bg-opacity-40 rounded border h-100">
                            <span class="fs-10 fw-bold text-uppercase text-muted d-block mb-2" style="letter-spacing: 0.5px;">SUPPLIER DETAILS (VENDOR)</span>
                            <h6 class="fw-bold text-dark mb-1 fs-14">{{ $order->vendor->name ?? '—' }}</h6>
                            @if($order->vendor?->code)
                                <div class="text-muted fw-semibold mb-1 fs-11">Code: <span class="font-monospace text-dark">{{ $order->vendor->code }}</span></div>
                            @endif
                            @if($order->vendor?->gstin || $order->vendor?->tax_number)
                                <div class="text-dark mb-1 fs-11">
                                    <strong>GSTIN / Tax ID:</strong> <span class="font-monospace fw-bold text-primary">{{ $order->vendor?->gstin ?: $order->vendor?->tax_number }}</span>
                                </div>
                            @endif
                            @if($order->vendor?->email || $order->vendor?->phone)
                                <div class="text-secondary mb-2 fs-11">
                                    @if($order->vendor?->email)
                                        <span class="me-3"><i class="feather-mail me-1 fs-11 text-muted"></i>{{ $order->vendor->email }}</span>
                                    @endif
                                    @if($order->vendor?->phone)
                                        <span><i class="feather-phone me-1 fs-11 text-muted"></i>{{ $order->vendor->phone }}</span>
                                    @endif
                                </div>
                            @endif

                            <div class="mt-2 pt-2 border-top text-secondary fs-11">
                                <strong class="text-dark d-block mb-1">Supplier Registered Address:</strong>
                                <div style="white-space: pre-wrap;">{{ $order->vendor?->address ?: ($order->vendor?->billing_address ?: '—') }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Box 2 (Right 50%): Delivery & Order References (Ship To) -->
                    <div class="col-6">
                        <div class="p-3 bg-light bg-opacity-40 rounded border h-100">
                            <span class="fs-10 fw-bold text-uppercase text-muted d-block mb-2" style="letter-spacing: 0.5px;">ORDER &amp; DELIVERY REFERENCES</span>
                            
                            <h6 class="fw-bold text-primary mb-1 fs-14">{{ $order->warehouse?->name ?: ($order->location ?: 'Company Central Warehouse') }}</h6>
                            <div class="text-secondary mb-2 fs-11">
                                <strong class="text-dark d-block mb-1">Delivery Address:</strong>
                                <div style="white-space: pre-wrap;">{{ $order->warehouse?->address ?: ($order->location ?: ($buyerOrg['address'] ?? '—')) }}</div>
                            </div>

                            <div class="mt-2 pt-2 border-top fs-11">
                                @if ($order->requisition)
                                    <div class="d-flex justify-content-between mb-1">
                                        <span class="text-muted">Requisition Ref:</span>
                                        <a href="{{ route('purchase.requisitions.show', $order->purchase_requisition_id) }}" class="fw-bold text-primary font-monospace">
                                            {{ $order->requisition->requisition_number }}
                                        </a>
                                    </div>
                                @endif
                                @if($order->supplier_quotation_number)
                                    <div class="d-flex justify-content-between mb-1">
                                        <span class="text-muted">Supplier Quote No:</span>
                                        <span class="fw-semibold text-dark">{{ $order->supplier_quotation_number }}</span>
                                    </div>
                                @endif
                                @if($order->reference)
                                    <div class="d-flex justify-content-between mb-1">
                                        <span class="text-muted">Internal Ref:</span>
                                        <span class="fw-semibold text-dark">{{ $order->reference }}</span>
                                    </div>
                                @endif
                                @if($order->freight_terms)
                                    <div class="d-flex justify-content-between mb-1">
                                        <span class="text-muted">Freight Terms:</span>
                                        <span class="fw-semibold text-dark">{{ ucwords(str_replace('_', ' ', $order->freight_terms)) }}</span>
                                    </div>
                                @endif
                                @if($hasTax)
                                    <div class="d-flex justify-content-between">
                                        <span class="text-muted">GST Treatment:</span>
                                        <span class="fw-semibold text-dark">{{ $order->gst_type === 'igst' ? 'IGST (Inter-State)' : 'CGST + SGST (Intra-State)' }}</span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 3. Line Items Table -->
                <div class="table-responsive mb-4">
                    <table class="table po-table mb-0">
                        <thead>
                            <tr>
                                <th style="width: 4%;">#</th>
                                <th style="width: {{ ($hasDiscount && $hasTax && $isItemWiseTax) ? '40%' : (($hasDiscount || ($hasTax && $isItemWiseTax)) ? '48%' : '56%') }};">Item Description &amp; Details</th>
                                <th style="width: 10%;" class="text-center">Qty</th>
                                <th style="width: 12%;" class="text-end">Rate</th>
                                @if($hasDiscount)
                                    <th style="width: 10%;" class="text-end">Discount</th>
                                @endif
                                @if($hasTax && $isItemWiseTax)
                                    <th style="width: 10%;" class="text-end">Tax</th>
                                @endif
                                <th style="width: 14%;" class="text-end">Total Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($order->items as $index => $item)
                                @php
                                    $itemQty = (float) $item->quantity;
                                    $itemRate = (float) $item->rate;
                                    $itemGross = (float) ($item->amount ?: ($itemQty * $itemRate));
                                    $itemDiscAmt = (float) ($item->discount_amount ?: 0);
                                    $itemTaxAmt = $isItemWiseTax ? (float) ($item->tax_amount ?: 0) : 0;
                                    $itemTotal = (float) ($item->total_amount ?: (max(0, $itemGross - $itemDiscAmt) + $itemTaxAmt));
                                    if (!$isItemWiseTax) {
                                        $itemTotal = (float) max(0, $itemGross - $itemDiscAmt);
                                    }
                                    $hsnCode = $item->product?->hsn_sac ?: ($item->product?->hsn_code ?: ($item->product?->hsn ?: null));
                                @endphp
                                <tr>
                                    <td class="text-muted">{{ $index + 1 }}</td>
                                    <td>
                                        <div class="fw-bold text-dark fs-13">
                                            {{ $item->product?->name ?: ($item->description ?: 'Purchased Item #' . ($index + 1)) }}
                                        </div>
                                        <div class="d-flex align-items-center flex-wrap gap-1 mt-1">
                                            @if($item->product?->sku)
                                                <span class="badge bg-light text-muted border font-monospace fs-10">SKU: {{ $item->product->sku }}</span>
                                            @endif
                                            @if(!empty($hsnCode) && $hsnCode !== '—')
                                                <span class="badge bg-light text-muted border font-monospace fs-10">HSN: {{ $hsnCode }}</span>
                                            @endif
                                            @if($item->line_type && $item->line_type !== 'stock')
                                                <span class="badge bg-soft-info text-info border font-monospace fs-10 text-uppercase">{{ $item->line_type }}</span>
                                            @endif
                                        </div>
                                        @if($item->description && $item->product && $item->description !== $item->product->name)
                                            <div class="text-muted fs-11 mt-1">{{ $item->description }}</div>
                                        @endif
                                    </td>
                                    <td class="text-center fw-bold font-monospace">{{ rtrim(rtrim(number_format($itemQty, 4), '0'), '.') }}</td>
                                    <td class="text-end font-monospace">{{ format_currency($itemRate) }}</td>
                                    
                                    @if($hasDiscount)
                                        <td class="text-end font-monospace text-danger">
                                            @if($itemDiscAmt > 0)
                                                <div>-{{ format_currency($itemDiscAmt) }}</div>
                                                @if((float)$item->discount_percent > 0)
                                                    <div class="fs-10 text-muted">({{ (float)$item->discount_percent }}%)</div>
                                                @endif
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                    @endif

                                    @if($hasTax && $isItemWiseTax)
                                        <td class="text-end font-monospace text-secondary">
                                            @if($itemTaxAmt > 0)
                                                +{{ format_currency($itemTaxAmt) }}
                                                @if($item->tax_percent > 0)
                                                    <div class="fs-10 text-muted">({{ (float)$item->tax_percent }}%)</div>
                                                @endif
                                            @else
                                                —
                                            @endif
                                        </td>
                                    @endif

                                    <td class="text-end fw-bold font-monospace text-dark">{{ format_currency($itemTotal) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- 4. Summary & Terms Section -->
                <div class="row g-4 mb-4">
                    <div class="col-7">
                        @if($order->notes)
                            <div class="p-3 bg-light bg-opacity-40 rounded border mb-3">
                                <span class="fs-10 fw-bold text-uppercase text-muted d-block mb-1">Instructions &amp; Remarks:</span>
                                <div class="fs-12 text-secondary po-rich-text">
                                    @if(strip_tags($order->notes) !== $order->notes)
                                        {!! $order->notes !!}
                                    @else
                                        <div style="white-space: pre-wrap;">{{ $order->notes }}</div>
                                    @endif
                                </div>
                            </div>
                        @endif

                        @if($order->terms_conditions)
                            <div class="p-3 bg-light bg-opacity-40 rounded border mb-3">
                                <span class="fs-10 fw-bold text-uppercase text-muted d-block mb-1">Terms &amp; Conditions:</span>
                                <div class="fs-12 text-secondary po-rich-text">
                                    @if(strip_tags($order->terms_conditions) !== $order->terms_conditions)
                                        {!! $order->terms_conditions !!}
                                    @else
                                        <div style="white-space: pre-wrap;">{{ $order->terms_conditions }}</div>
                                    @endif
                                </div>
                            </div>
                        @endif

                        <div class="text-muted fs-11">
                            • Please quote Purchase Order number <strong>{{ $order->purchase_order_number }}</strong> on all delivery challans and invoices.<br>
                            • Material will be inspected upon receipt at the delivery location before final acceptance.
                        </div>
                    </div>

                    <div class="col-5">
                        <div class="p-3 bg-light bg-opacity-40 rounded border">
                            <table class="table table-borderless table-sm summary-table mb-0">
                                <tbody>
                                    <tr>
                                        <td class="text-muted ps-0">Subtotal:</td>
                                        <td class="text-end fw-bold font-monospace text-dark">{{ format_currency($order->subtotal) }}</td>
                                    </tr>

                                    @if($order->discount_amount > 0)
                                        <tr>
                                            <td class="text-danger ps-0">Discount:</td>
                                            <td class="text-end fw-bold font-monospace text-danger">-{{ format_currency($order->discount_amount) }}</td>
                                        </tr>
                                    @endif

                                    @if($order->tax_amount > 0)
                                        @if($order->gst_type === 'igst')
                                            <tr>
                                                <td class="text-muted ps-0">IGST:</td>
                                                <td class="text-end fw-bold font-monospace text-dark">+{{ format_currency($order->igst_amount ?: $order->tax_amount) }}</td>
                                            </tr>
                                        @else
                                            <tr>
                                                <td class="text-muted ps-0">CGST:</td>
                                                <td class="text-end fw-bold font-monospace text-dark">+{{ format_currency($order->cgst_amount ?: ($order->tax_amount / 2)) }}</td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted ps-0">SGST:</td>
                                                <td class="text-end fw-bold font-monospace text-dark">+{{ format_currency($order->sgst_amount ?: ($order->tax_amount / 2)) }}</td>
                                            </tr>
                                        @endif
                                    @endif

                                    @if($order->freight_amount > 0)
                                        <tr>
                                            <td class="text-muted ps-0">Freight Charges:</td>
                                            <td class="text-end fw-bold font-monospace text-dark">+{{ format_currency($order->freight_amount) }}</td>
                                        </tr>
                                    @endif

                                    <tr class="border-top">
                                        <td class="fw-bold text-dark fs-14 ps-0 pt-2">Grand Total:</td>
                                        <td class="text-end fw-bold font-monospace text-primary fs-16 pt-2">{{ format_currency($order->grand_total) }}</td>
                                    </tr>

                                    @if($order->advance_paid > 0)
                                        <tr>
                                            <td class="text-success ps-0">Advance Paid:</td>
                                            <td class="text-end fw-bold font-monospace text-success">-{{ format_currency($order->advance_paid) }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted ps-0 fw-bold">Balance Due:</td>
                                            <td class="text-end fw-bold font-monospace text-dark">{{ format_currency($order->balance_due) }}</td>
                                        </tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- 5. Signatory Block -->
                <div class="row pt-4 border-top mt-4 align-items-end">
                    <div class="col-7 fs-11 text-muted">
                        <div>Computer generated Purchase Order issued by <strong>{{ $buyerOrg['name'] }}</strong>.</div>
                        <div>Created by: <span class="fw-semibold text-dark">{{ $order->creator?->name ?? 'Purchase Department' }}</span></div>
                    </div>
                    <div class="col-5 text-end">
                        <div class="fs-11 fw-bold text-dark mb-4">For {{ $buyerOrg['name'] }}</div>
                        <div class="d-inline-block border-top border-secondary pt-1 px-4 fs-11 text-muted fw-bold">
                            Authorized Signatory
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Send WhatsApp Modal -->
    <div class="modal fade" id="sendPoWhatsAppModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-3">
                <div class="modal-header bg-soft-success border-bottom py-3">
                    <h6 class="modal-title fw-bold text-success fs-14">
                        <i class="feather-message-circle me-1.5 fs-15"></i> Send Purchase Order via WhatsApp
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark fs-12 mb-1">Supplier / Vendor Phone (with Country Code):</label>
                        <input type="text" id="waVendorPhone" class="form-control form-control-sm" placeholder="e.g. 919876543210" value="{{ $vendorPhone }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark fs-12 mb-1">Message Preview:</label>
                        <textarea id="waMessageText" class="form-control form-control-sm font-monospace fs-12" rows="6"></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 px-3 border-top d-flex justify-content-between">
                    <a href="{{ route('purchase.orders.download', $order->id) }}" class="btn btn-sm btn-outline-danger fw-semibold">
                        <i class="feather-download me-1"></i>Download PDF to attach
                    </a>
                    <button type="button" class="btn btn-sm btn-success fw-bold px-3 shadow-sm" onclick="sendPoOnWhatsApp()">
                        <i class="feather-send me-1"></i>Open in WhatsApp
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Send Email Modal -->
    <div class="modal fade" id="sendPoEmailModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-3">
                <div class="modal-header bg-soft-primary border-bottom py-3">
                    <h6 class="modal-title fw-bold text-primary fs-14">
                        <i class="feather-mail me-1.5 fs-15"></i> Send Purchase Order via Email
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark fs-12 mb-1">Recipient Email:</label>
                        <input type="email" id="emailVendorRecipient" class="form-control form-control-sm" placeholder="supplier@example.com" value="{{ $vendorEmail }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark fs-12 mb-1">Subject:</label>
                        <input type="text" id="emailSubject" class="form-control form-control-sm" value="Purchase Order {{ $order->purchase_order_number }} from {{ $buyerOrg['name'] }}">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark fs-12 mb-1">Email Body:</label>
                        <textarea id="emailBodyText" class="form-control form-control-sm fs-12" rows="5"></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 px-3 border-top d-flex justify-content-between">
                    <a href="{{ route('purchase.orders.download', $order->id) }}" class="btn btn-sm btn-outline-danger fw-semibold">
                        <i class="feather-download me-1"></i>Download PDF
                    </a>
                    <button type="button" class="btn btn-sm btn-primary fw-bold px-3" onclick="sendPoOnEmail()">
                        <i class="feather-send me-1"></i>Compose Email
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Register Advance Payment Modal -->
    @if(in_array($order->status, ['Approved', 'Partially Received']))
        @php
            $activeBalanceDue = round(convert_from_base((float)($order->balance_due ?: $order->grand_total)), 2);
            $activeGrandTotal = round(convert_from_base((float)$order->grand_total), 2);
            $defaultAdvanceAmount = min($activeBalanceDue, $activeGrandTotal);
            $currSymbol = active_currency_symbol();
        @endphp
        <x-ui.modal id="advancePaymentModal" :title="__('purchase.register_vendor_advance_payment')" size="lg" :showFooter="false">
            <form action="{{ route('purchase.orders.advance-payments.store') }}" method="POST" class="odoo-sheet">
                @csrf
                <input type="hidden" name="purchase_order_id" value="{{ $order->id }}">
                <input type="hidden" name="vendor_id" value="{{ $order->vendor_id }}">

                <div class="p-3">
                    <div class="alert alert-info py-2 px-3 fs-12 mb-3">
                        <i class="feather-info me-1"></i>
                        {{ __('purchase.advance_payment_journal_help') }}
                    </div>

                    <div class="row g-2">
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input" :label="__('purchase.vendor')" name="vendor_display" value="{{ $order->vendor?->name }}" readonly="true" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input" :label="__('purchase.po_number')" name="po_display" value="{{ $order->purchase_order_number }}" readonly="true" />
                        </div>
                    </div>

                    <div class="row g-2">
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input" inputType="number" :label="__('purchase.advance_amount') . ' (' . $currSymbol . ')'" name="amount" id="advance_amount" value="{{ number_format($defaultAdvanceAmount, 2, '.', '') }}" step="0.01" min="0.01" max="{{ number_format($activeBalanceDue, 2, '.', '') }}" required="true" :placeholder="__('purchase.enter_amount_placeholder')" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="select" :label="__('purchase.payment_method')" name="payment_method" id="payment_method" required="true">
                                <option value="Bank Transfer" selected>{{ __('purchase.bank_transfer') }}</option>
                                <option value="Cheque">{{ __('purchase.cheque') }}</option>
                                <option value="Cash">{{ __('purchase.cash') }}</option>
                                <option value="UPI">{{ __('purchase.upi') }}</option>
                            </x-ui.odoo-form-ui>
                        </div>
                    </div>

                    <div class="row g-2">
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input" inputType="date" :label="__('purchase.payment_date')" name="payment_date" id="payment_date" value="{{ date('Y-m-d') }}" required="true" />
                        </div>
                        <div class="col-md-6">
                            <x-ui.odoo-form-ui type="input" :label="__('purchase.ref_transaction_no')" name="reference_number" id="reference_number" placeholder="e.g. UTR123456789" />
                        </div>
                    </div>

                    <x-ui.odoo-form-ui type="textarea" :label="__('purchase.payment_notes_remarks')" name="notes" :placeholder="__('purchase.enter_payment_notes_placeholder')" rows="2" />
                </div>

                <div class="modal-footer border-top px-3 py-2 bg-light d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">{{ __('purchase.cancel') }}</button>
                    <button type="submit" class="btn btn-sm btn-primary fw-semibold">
                        <i class="feather-check me-1"></i>{{ __('purchase.post_advance_payment') }}
                    </button>
                </div>
            </form>
        </x-ui.modal>
    @endif

    <!-- Send Quick Reminder Modal -->
    <div class="modal fade" id="remindModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-3">
                <div class="modal-header bg-soft-warning border-bottom py-3">
                    <h6 class="modal-title fw-bold text-dark fs-14">
                        <i class="feather-bell text-warning me-1.5 fs-15"></i> {{ __('purchase.send_quick_approval_reminder') }}
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="remindForm" method="POST" action="">
                    @csrf
                    <div class="modal-body p-4">
                        <div class="alert alert-warning border border-warning-subtle py-2 px-3 fs-12 mb-3">
                            <i class="feather-info me-1"></i>
                            {{ __('purchase.sending_reminder_for_doc') }} <strong id="remindDocNumberText" class="text-dark"></strong>.
                        </div>
                        <div class="mb-3 text-start">
                            <label class="form-label fw-bold text-dark fs-12 mb-1">{{ __('purchase.optional_note_for_approver') }}</label>
                            <textarea name="note" class="form-control form-control-sm shadow-2xs" rows="3" placeholder="{{ __('purchase.reminder_note_placeholder') }}"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer bg-light py-2 px-3 border-top">
                        <button type="button" class="btn btn-sm btn-light border fw-semibold" data-bs-dismiss="modal">{{ __('ui.cancel') ?? 'Cancel' }}</button>
                        <button type="submit" class="btn btn-sm btn-warning fw-bold px-3 shadow-2xs text-white" style="background-color: #f59e0b; border-color: #d97706;">
                            <i class="feather-send me-1"></i> {{ __('purchase.send_reminder') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function openRemindModal(actionUrl, docNumber) {
            document.getElementById('remindForm').action = actionUrl;
            document.getElementById('remindDocNumberText').innerText = docNumber || '';
            var modal = new bootstrap.Modal(document.getElementById('remindModal'));
            modal.show();
        }

        // WhatsApp Modal Trigger
        $(document).on('click', '.btn-open-po-wa-modal', function() {
            const poNum = $(this).data('po-number');
            const vendorName = $(this).data('vendor-name');
            const phone = $(this).data('vendor-phone') || '';
            const total = $(this).data('total-amount') || '';
            const company = "{{ $buyerOrg['name'] }}";

            $('#waVendorPhone').val(phone);
            const msg = `Dear ${vendorName},\n\nPlease find Purchase Order *${poNum}* from *${company}*.\n💰 *Order Value:* ${total}\n\nPlease review and confirm receipt.\nThank you!`;
            $('#waMessageText').val(msg);

            new bootstrap.Modal(document.getElementById('sendPoWhatsAppModal')).show();
        });

        function sendPoOnWhatsApp() {
            let phone = $('#waVendorPhone').val().replace(/[^0-9]/g, '');
            const msg = encodeURIComponent($('#waMessageText').val());
            if (!phone) {
                alert('Please enter a valid vendor phone number.');
                return;
            }
            window.open(`https://api.whatsapp.com/send?phone=${phone}&text=${msg}`, '_blank');
        }

        // Email Modal Trigger
        $(document).on('click', '.btn-open-po-email-modal', function() {
            const poNum = $(this).data('po-number');
            const vendorName = $(this).data('vendor-name');
            const email = $(this).data('vendor-email') || '';
            const total = $(this).data('total-amount') || '';
            const company = "{{ $buyerOrg['name'] }}";

            $('#emailVendorRecipient').val(email);
            const body = `Dear ${vendorName},\n\nPlease find attached Purchase Order ${poNum} amounting to ${total}.\n\nKindly acknowledge this order and inform us of the expected delivery schedule.\n\nBest Regards,\nPurchase Department\n${company}`;
            $('#emailBodyText').val(body);

            new bootstrap.Modal(document.getElementById('sendPoEmailModal')).show();
        });

        function sendPoOnEmail() {
            const email = $('#emailVendorRecipient').val().trim();
            const subject = encodeURIComponent($('#emailSubject').val());
            const body = encodeURIComponent($('#emailBodyText').val());
            if (!email) {
                alert('Please enter a valid recipient email.');
                return;
            }
            window.location.href = `mailto:${email}?subject=${subject}&body=${body}`;
        }
    </script>
    @endpush
@endsection
