@extends('layouts.duralux')

@section('title', __('purchase.purchase_orders') . " {$order->purchase_order_number} | SaaS ERP")
@section('page-title', __('purchase.purchase_order_details'))
@section('breadcrumb')
    <a href="{{ route('purchase.orders.index') }}">{{ __('purchase.purchase_orders') }}</a> &gt; {{ $order->purchase_order_number }}
@endsection

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <!-- Back Button -->
        <a href="{{ route('purchase.orders.index') }}" class="action-dropdown-btn" title="{{ __('purchase.back') }}" data-bs-toggle="tooltip">
            <i class="feather feather-arrow-left"></i>
        </a>

        <!-- Download PDF Icon Button -->
        <a href="{{ route('purchase.orders.download', $order->id) }}" class="action-dropdown-btn" title="{{ __('purchase.download_pdf') }}" data-bs-toggle="tooltip">
            <i class="feather feather-download"></i>
        </a>

        @if (in_array($order->status, ['Approved', 'Partially Received', 'Received', 'Completed']))
            @php
                $latestGrn = \App\Domains\Purchase\Models\GoodsReceiptNote::where('purchase_order_id', $order->id)->latest()->first();
            @endphp
            <x-ui.button href="{{ route('purchase.returns.create', $latestGrn ? ['goods_receipt_note_id' => $latestGrn->id, 'mode' => 'grn'] : ['purchase_order_id' => $order->id, 'mode' => 'grn']) }}" variant="primary" size="sm" class="fw-bold px-3 me-1" icon="feather-corner-up-left">
                Create Return
            </x-ui.button>
        @endif

        @if($order->status === 'Draft')
            <!-- Action Dropdown -->
            <x-ui.action-dropdown id="poDetailsActions-{{ $order->id }}">
                @if($order->status === 'Draft')
                    <li>
                        <button type="button" class="dropdown-item py-2 text-warning fw-semibold" onclick="openRemindModal('{{ route('purchase.orders.remind', $order->id) }}', '{{ $order->purchase_order_number }}')">
                            <i class="feather-bell me-1.5 text-warning"></i> Send Quick Reminder
                        </button>
                    </li>
                @endif
                <li>
                    <a class="dropdown-item py-2" href="{{ route('purchase.orders.edit', $order->id) }}">
                        <i class="feather-edit me-1.5 text-muted"></i> {{ __('purchase.edit') }}
                    </a>
                </li>
                <li>
                    <form action="{{ route('purchase.orders.destroy', $order->id) }}" method="POST" class="d-inline" id="deletePoShowForm">
                        @csrf
                        @method('DELETE')
                        <button type="button" class="dropdown-item py-2 text-danger" onclick="confirmAction({ title: 'Delete Purchase Order', message: '{{ __('purchase.confirm_delete_po') }}', variant: 'danger', confirmText: 'Delete' }, function() { document.getElementById('deletePoShowForm').submit(); })">
                            <i class="feather-trash-2 me-1.5"></i> {{ __('purchase.delete') }}
                        </button>
                    </form>
                </li>
            </x-ui.action-dropdown>
        @endif
    </div>
@endsection

@push('styles')
    <style>
        .po-lines-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }
        .po-lines-table th {
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
        .po-lines-table td {
            padding: 12px 14px;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: middle;
            font-size: 13px;
        }
        .po-lines-table tr:hover td {
            background-color: #f8fafc;
        }
        .summary-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 16px 20px;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
            font-size: 13px;
        }
        @media print {
            body * { visibility: hidden !important; }
            #printablePoContent, #printablePoContent * { visibility: visible !important; }
            #printablePoContent {
                position: absolute !important;
                left: 0 !important;
                top: 0 !important;
                width: 100% !important;
                padding: 0 !important;
            }
            .d-print-none { display: none !important; }
        }
    </style>
@endpush

@section('content')
    @php
        $currency = tenant()?->settings['currency'] ?? 'INR';
        $currencySymbol = active_currency_symbol();
    @endphp

    <div class="erp-single-panel text-dark">
        <!-- Toast Notifications -->

        <x-ui.odoo-form-ui type="sheet" class="p-0" id="printablePoContent">
            <!-- Header bar -->
            @if($order->is_subcontract)
                @php
                    $firstItemWithMo = $order->items ? $order->items->first(fn($i) => !empty($i->production_order_operation_id)) : null;
                    $linkedMoOp = $firstItemWithMo?->productionOrderOperation;
                    $linkedMo = $linkedMoOp?->order;
                @endphp
                <div class="bg-soft-primary border-bottom border-primary px-4 py-2 d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge bg-primary text-white font-monospace fs-11">Subcontract Service PO</span>
                        <span class="fs-12 text-dark">
                            Auto-generated for Production Subcontracting Execution
                            @if($linkedMo)
                                — <strong>MO:</strong> <a href="{{ route('production.orders.show', $linkedMo->id) }}" class="fw-bold text-primary">{{ $linkedMo->order_number }}</a>
                                @if($linkedMoOp)
                                    | <strong>Op:</strong> {{ $linkedMoOp->operation_number }} ({{ $linkedMoOp->name }})
                                @endif
                            @endif
                        </span>
                    </div>
                    <span class="badge bg-soft-info text-info border font-monospace fs-10">Service Receipt — No Stock Increase</span>
                </div>
            @endif
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 px-4 pt-4 pb-3 border-bottom bg-white">
                <div>
                    <span class="fs-11 text-muted text-uppercase fw-bold d-block mb-1 letter-spacing-1">
                        <i class="feather-shopping-bag text-primary me-1"></i>Purchase Order
                    </span>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <h3 class="fw-bold text-dark mb-0 font-monospace">{{ $order->purchase_order_number }}</h3>
                        @php
                            $statusVariant = 'warning';
                            if ($order->status === 'Completed') $statusVariant = 'success';
                            elseif ($order->status === 'Approved') $statusVariant = 'primary';
                            elseif ($order->status === 'Partially Received') $statusVariant = 'info';
                            elseif (in_array($order->status, ['Cancelled', 'Rejected'])) $statusVariant = 'danger';
                        @endphp
                        <x-ui.badge :soft="true" :variant="$statusVariant" class="px-2.5 py-1 fs-11 fw-bold">
                            {{ $order->status }}
                        </x-ui.badge>
                        @if($order->is_subcontract)
                            <x-ui.badge :soft="true" variant="warning" class="px-2.5 py-1 fs-11 fw-bold">
                                <i class="feather-truck me-1"></i>Subcontract PO
                            </x-ui.badge>
                        @endif
                        @if($order->reminder_count > 0)
                            @php
                                $remData = $order->reminders->map(fn($r) => [
                                    'user' => $r->user->name ?? 'User',
                                    'time' => $r->created_at->format('d M Y h:i A'),
                                    'note' => $r->note
                                ]);
                            @endphp
                            <button type="button" class="btn btn-xs btn-soft-danger border border-danger-subtle px-2.5 py-1 fs-11 fw-bold"
                                    onclick="showReminderHistoryModal('{{ $order->purchase_order_number }}', {{ json_encode($remData) }})">
                                <i class="feather-bell me-1"></i>Reminded ({{ $order->reminder_count }})
                            </button>
                        @endif
                    </div>
                    <span class="fs-13 text-muted">
                        Supplier:&nbsp;<strong class="text-dark">{{ $order->vendor->name ?? '—' }}</strong>
                        &nbsp;·&nbsp;Order Date:&nbsp;<strong class="text-dark">{{ $order->date ? $order->date->format('d-m-Y') : '—' }}</strong>
                        @if($order->delivery_date)
                            &nbsp;·&nbsp;Expected Delivery Date:&nbsp;<strong class="text-info">{{ $order->delivery_date->format('d-m-Y') }}</strong>
                        @endif
                        @if($order->completed_at)
                            &nbsp;·&nbsp;Completion Date:&nbsp;<strong class="text-success">{{ $order->completed_at->format('d-m-Y') }}</strong>
                        @endif
                    </span>
                </div>

                <!-- Grand Total Banner -->
                <div class="text-end bg-light p-3 rounded-3 border min-w-180">
                    <span class="fs-11 text-muted text-uppercase fw-bold d-block mb-1 letter-spacing-1">Grand Total</span>
                    <h3 class="fw-bold text-primary mb-0 font-monospace">{{ $currencySymbol }}{{ number_format($order->grand_total, 2) }}</h3>
                </div>
            </div>

            @if($order->is_subcontract)
                <div class="alert alert-info border-0 border-start border-4 border-info m-4 mb-0 rounded-3 shadow-sm bg-soft-info">
                    <div class="d-flex align-items-top justify-content-between flex-wrap gap-2">
                        <div class="d-flex align-items-top">
                            <i class="feather-info fs-18 text-info me-3 mt-0.5"></i>
                            <div>
                                <h6 class="fw-bold text-info mb-1">Subcontract Service Order</h6>
                                <p class="fs-12 text-dark mb-0">
                                    This Purchase Order purchases vendor processing/service. Any company-owned material or WIP sent to this vendor is managed separately through <strong>Stock Transfer</strong>.
                                </p>
                            </div>
                        </div>
                        @if($order->production_order_id && \Illuminate\Support\Facades\Route::has('inventory.transfers.index'))
                            <a href="{{ route('inventory.transfers.index', ['search' => 'MO-' . $order->production_order_id]) }}" class="btn btn-xs btn-outline-primary fw-bold shadow-sm align-self-center">
                                <i class="feather-arrow-right-circle me-1"></i>View Material Transfers
                            </a>
                        @endif
                    </div>
                </div>
            @endif

            @if (in_array($order->status, ['Cancelled', 'Rejected']) && !empty($order->rejection_reason))
                <div class="alert alert-danger border-0 border-start border-4 border-danger m-4 mb-0 rounded-3 shadow-sm bg-soft-danger">
                    <div class="d-flex align-items-top">
                        <i class="feather-x-circle fs-18 text-danger me-3 mt-0.5"></i>
                        <div>
                            <h6 class="fw-bold text-danger mb-1">Purchase Order Cancelled / Rejected</h6>
                            <p class="fs-13 text-dark mb-0"><strong>Rejection Reason:</strong> {{ $order->rejection_reason }}</p>
                        </div>
                    </div>
                </div>
            @endif

            <!-- 3-Column Info Cards Section -->
            <div class="px-4 py-4 border-bottom bg-light-50">
                <div class="row g-4 fs-13 text-dark">
                    <!-- Column 1: Vendor Details -->
                    <div class="col-md-4 border-end-md">
                        <h6 class="fw-bold text-dark text-uppercase fs-11 letter-spacing-1 mb-3">
                            <i class="feather-truck text-primary me-1.5"></i>{{ __('purchase.supplier_information') }}
                        </h6>
                        <table class="table table-borderless table-sm mb-0">
                            <tbody>
                                <tr>
                                    <td class="text-muted ps-0" style="width: 110px;">{{ __('purchase.vendor') }}:</td>
                                    <td class="fw-bold text-dark fs-14">{{ $order->vendor->name ?? '—' }}</td>
                                </tr>
                                @if($order->vendor?->code)
                                    <tr>
                                        <td class="text-muted ps-0">{{ __('purchase.supplier_code') }}:</td>
                                        <td class="fw-semibold text-secondary font-monospace">{{ $order->vendor->code }}</td>
                                    </tr>
                                @endif
                                @if($order->supplier_quotation_number)
                                    <tr>
                                        <td class="text-muted ps-0">{{ __('purchase.quote_ref') }}:</td>
                                        <td class="fw-bold text-primary font-monospace">{{ $order->supplier_quotation_number }}</td>
                                    </tr>
                                @endif
                                <tr>
                                    <td class="text-muted ps-0">{{ __('purchase.address') }}:</td>
                                    <td class="text-dark" style="line-height: 1.4;">{{ $order->vendor->address ?? '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Column 2: Dates & Warehouse -->
                    <div class="col-md-4 border-end-md">
                        <h6 class="fw-bold text-dark text-uppercase fs-11 letter-spacing-1 mb-3">
                            <i class="feather-calendar text-primary me-1.5"></i>{{ __('purchase.dates_calc_options') }}
                        </h6>
                        <table class="table table-borderless table-sm mb-0">
                            <tbody>
                                <tr>
                                    <td class="text-muted ps-0" style="width: 120px;">{{ __('purchase.order_date') }}:</td>
                                    <td class="fw-semibold text-dark">{{ $order->date ? $order->date->format('d-m-Y') : '—' }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted ps-0">{{ __('purchase.expected_delivery_date_label') }}:</td>
                                    <td class="fw-semibold text-info">{{ $order->delivery_date ? $order->delivery_date->format('d-m-Y') : '—' }}</td>
                                </tr>
                                @if($order->completed_at)
                                    <tr>
                                        <td class="text-muted ps-0">{{ __('purchase.completion_date_label') }}:</td>
                                        <td class="fw-bold text-success">{{ $order->completed_at->format('d-m-Y H:i') }}</td>
                                    </tr>
                                @endif
                                <tr>
                                    <td class="text-muted ps-0">{{ __('purchase.location') }}:</td>
                                    <td class="fw-semibold text-dark">{{ $order->location ?: __('purchase.main_warehouse') }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted ps-0">{{ __('purchase.discount_option_label') }}:</td>
                                    <td class="fw-semibold text-dark text-capitalize">{{ str_replace('_', ' ', $order->discount_type) }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted ps-0">{{ __('purchase.tax_option_label') }}:</td>
                                    <td class="fw-semibold text-dark text-capitalize">{{ str_replace('_', ' ', $order->tax_type) }}</td>
                                </tr>
                                @if($order->freight_terms)
                                    <tr>
                                        <td class="text-muted ps-0">{{ __('purchase.freight_terms') }}:</td>
                                        <td class="fw-semibold text-dark">
                                            @if($order->freight_terms === 'to_pay') {{ __('purchase.freight_to_pay') }}
                                            @elseif($order->freight_terms === 'to_be_billed') {{ __('purchase.freight_to_be_billed') }}
                                            @elseif($order->freight_terms === 'prepaid') {{ __('purchase.freight_prepaid') }}
                                            @elseif($order->freight_terms === 'customer_pickup') {{ __('purchase.freight_customer_pickup') }}
                                            @else {{ str_replace('_', ' ', $order->freight_terms) }}
                                            @endif
                                        </td>
                                    </tr>
                                @endif
                                @if($order->freight_amount > 0)
                                    <tr>
                                        <td class="text-muted ps-0">{{ __('purchase.freight_amount') }}:</td>
                                        <td class="fw-bold text-primary">{{ format_currency($order->freight_amount) }}</td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>

                    <!-- Column 3: Traceability -->
                    <div class="col-md-4">
                        <div class="d-flex align-items-center justify-content-between mb-3">
                            <h6 class="fw-bold text-dark text-uppercase fs-11 letter-spacing-1 mb-0">
                                <i class="feather-link text-primary me-1.5"></i>{{ __('purchase.traceability_audit') }}
                            </h6>
                        </div>
                        <table class="table table-borderless table-sm mb-0">
                            <tbody>
                                <tr>
                                    <td class="text-muted ps-0" style="width: 130px;">{{ __('purchase.source_requisition_label') }}:</td>
                                    <td class="fw-bold">
                                        @if($order->requisition)
                                            <a href="{{ route('purchase.requisitions.show', $order->purchase_requisition_id) }}" class="text-primary hover-underline font-monospace">
                                                <i class="feather-file-text me-1"></i>{{ $order->requisition->requisition_number }}
                                            </a>
                                        @else
                                            <span class="text-muted">{{ __('purchase.direct_creation') }}</span>
                                        @endif
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-muted ps-0">{{ __('purchase.reference_note_label') }}:</td>
                                    <td class="fw-semibold text-dark">{{ $order->reference ?: '—' }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted ps-0">{{ __('purchase.created_by_label') }}:</td>
                                    <td class="fw-semibold text-dark">{{ $order->creator->name ?? __('purchase.system') }}</td>
                                </tr>
                                <tr>
                                    <td class="text-muted ps-0">{{ __('purchase.gst_treatment_label') }}:</td>
                                    <td class="fw-semibold text-dark text-uppercase">{{ $order->gst_type === 'igst' ? 'IGST (Inter-State)' : 'CGST + SGST (Intra-State)' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Line Items Table Section -->
            <div class="px-4 py-4 border-bottom">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h5 class="fw-bold text-dark mb-0">
                        <i class="feather-list text-primary me-2"></i>{{ __('purchase.po_line_items_heading') }}
                    </h5>
                    <span class="badge bg-light text-dark border px-2.5 py-1 font-monospace fs-12">
                        {{ $order->items->count() }} Line(s)
                    </span>
                </div>

                <div class="table-responsive rounded-3 border">
                    <table class="po-lines-table">
                        <thead>
                            <tr>
                                <th style="width: 4%;">#</th>
                                <th style="width: 28%;">{{ __('purchase.product_name') }} & SKU</th>
                                <th class="text-end" style="width: 8%;">{{ __('purchase.qty') }}</th>
                                <th class="text-end" style="width: 12%;">{{ __('purchase.rate') }} ({{ active_currency_symbol() }})</th>
                                <th class="text-end" style="width: 12%;">{{ __('purchase.gross_amount') }} ({{ active_currency_symbol() }})</th>

                                @if($order->discount_type === 'item_wise')
                                    <th class="text-end text-danger" style="width: 10%;">{{ __('purchase.disc_percent') }}</th>
                                @endif

                                <th class="text-end text-dark" style="width: 13%;">{{ __('purchase.taxable_amt') }} ({{ active_currency_symbol() }})</th>

                                @if($order->tax_type === 'item_wise_tax')
                                    <th class="text-end text-muted" style="width: 10%;">{{ __('purchase.tax_rate') }}</th>
                                @endif

                                <th class="text-end" style="width: 13%;">{{ __('purchase.total_amount') }} ({{ active_currency_symbol() }})</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                // Group by product_id + line_type, not product_id alone — otherwise a
                                // split purchase of the same product (e.g. 8 units to Stock, 2 to Asset)
                                // would incorrectly merge into a single line.
                                $groupedItems = $order->items->groupBy(fn ($item) => $item->product_id . '|' . $item->line_type)->map(function($items) {
                                    $first = $items->first();
                                    $qty = $items->sum('quantity');
                                    $rate = $first->rate;
                                    $grossAmt = $qty * $rate;
                                    $discAmt = $items->sum('discount_amount');
                                    $taxableAmt = max(0, $grossAmt - $discAmt);
                                    $taxAmt = $items->sum('tax_amount');
                                    $totalAmt = $items->sum('total_amount');
                                    return (object) [
                                        'id' => $first->id,
                                        'product' => $first->product,
                                        'product_id' => $first->product_id,
                                        'description' => $first->description,
                                        'line_type' => $first->line_type,
                                        'asset_category' => $first->assetCategory ?? null,
                                        'chart_of_account' => $first->chartOfAccount ?? null,
                                        'quantity' => $qty,
                                        'rate' => $rate,
                                        'gross_amount' => $grossAmt,
                                        'discount_percent' => $first->discount_percent,
                                        'discount_amount' => $discAmt,
                                        'taxable_amount' => $taxableAmt,
                                        'tax_percent' => $first->tax_percent,
                                        'cgst_percent' => $first->cgst_percent,
                                        'sgst_percent' => $first->sgst_percent,
                                        'igst_percent' => $first->igst_percent,
                                        'tax_amount' => $taxAmt,
                                        'total_amount' => $totalAmt,
                                    ];
                                })->values();
                            @endphp

                            @foreach($groupedItems as $index => $item)
                                <tr>
                                    <td class="text-muted fs-12 font-monospace">{{ $index + 1 }}</td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <div class="avatar-sm bg-soft-primary text-primary rounded-2 d-flex align-items-center justify-content-center fw-bold fs-13" style="width:30px; height:30px; flex-shrink:0;">
                                                <i class="feather-{{ $item->line_type === 'asset' ? 'archive' : ($item->line_type === 'expense' ? 'file-text' : 'box') }}"></i>
                                            </div>
                                            <div>
                                                @if($item->product_id)
                                                    <a href="{{ route('inventory.products.show', $item->product_id) }}" class="fw-bold text-dark text-decoration-none hover-underline">
                                                        {{ $item->product->name ?? '—' }}
                                                    </a>
                                                    <div class="text-muted fs-11 font-monospace">SKU: {{ $item->product->sku ?: '—' }}</div>
                                                @elseif($item->line_type === 'asset')
                                                    <span class="fw-bold text-dark">{{ $item->description ?: ($item->asset_category->name ?? 'Fixed Asset') }}</span>
                                                    <div class="text-muted fs-11">{{ $item->asset_category->name ?? 'Asset purchase' }} — no stock item</div>
                                                @elseif($item->line_type === 'expense')
                                                    <span class="fw-bold text-dark">{{ $item->description ?: ($item->chart_of_account ? $item->chart_of_account->code . ' - ' . $item->chart_of_account->name : 'Expense') }}</span>
                                                    <div class="text-muted fs-11">{{ $item->chart_of_account ? $item->chart_of_account->code . ' - ' . $item->chart_of_account->name : 'Direct expense' }} — no stock item</div>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-end fw-bold text-dark font-monospace fs-14">
                                        {{ (float)$item->quantity }}
                                    </td>
                                    <td class="text-end font-monospace text-muted">
                                        {{ $currencySymbol }}{{ number_format($item->rate, 2) }}
                                    </td>
                                    <td class="text-end font-monospace fw-semibold text-dark">
                                        {{ $currencySymbol }}{{ number_format($item->gross_amount, 2) }}
                                    </td>

                                    @if($order->discount_type === 'item_wise')
                                        <td class="text-end font-monospace text-danger">
                                            {{ (float)$item->discount_percent }}%
                                        </td>
                                    @endif

                                    <td class="text-end font-monospace fw-semibold text-dark">
                                        {{ $currencySymbol }}{{ number_format($item->taxable_amount, 2) }}
                                    </td>

                                    @if($order->tax_type === 'item_wise_tax')
                                        <td class="text-end font-monospace text-muted">
                                            {{ (float)$item->tax_percent }}%
                                        </td>
                                    @endif

                                    <td class="text-end font-monospace fw-bold text-dark fs-14">
                                        {{ $currencySymbol }}{{ number_format($item->total_amount, 2) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Financial Summary & Terms Section -->
            <div class="px-4 py-4 border-bottom bg-white">
                <div class="row g-4 text-dark fs-13">
                    <!-- Terms & Notes -->
                    <div class="col-md-7">
                        <h6 class="fw-bold text-dark text-uppercase fs-11 letter-spacing-1 mb-3">
                            <i class="feather-file-text me-1.5 text-primary"></i>{{ __('purchase.terms_and_notes') }}
                        </h6>
                        <div class="bg-light p-3 border rounded-3 text-muted" style="min-height: 90px; white-space: pre-line; line-height: 1.5;">
                            {!! $order->notes ? e($order->notes) : '<span class="italic"><i class="feather-info me-1"></i>' . __('purchase.no_additional_terms_specified') . '</span>' !!}
                        </div>
                    </div>

                    <!-- Financial Calculations Box -->
                    <div class="col-md-5">
                        <div class="summary-box">
                            <h6 class="fw-bold text-dark text-uppercase fs-11 letter-spacing-1 mb-3 border-bottom pb-2">
                                {{ __('purchase.financial_summary') }}
                            </h6>

                            <!-- Subtotal -->
                            <div class="summary-row">
                                <span class="text-muted fw-semibold">{{ __('purchase.subtotal') }}:</span>
                                <span class="fw-bold text-dark font-monospace">{{ format_currency($order->subtotal) }}</span>
                            </div>

                            <!-- Discount Row (ONLY if discount > 0) -->
                            @if(($order->discount_type !== 'without_discount' || $order->discount_amount > 0) && $order->discount_amount > 0)
                                <div class="summary-row text-danger">
                                    <span class="fw-semibold">{{ __('purchase.discount') }} ({{ str_replace('_', ' ', $order->discount_type) }}):</span>
                                    <span class="fw-bold font-monospace">-{{ format_currency($order->discount_amount) }}</span>
                                </div>

                                <div class="summary-row">
                                    <span class="text-muted fw-semibold">{{ __('purchase.gross_total_before_tax') }}:</span>
                                    <span class="fw-bold text-dark font-monospace">{{ format_currency(max(0, $order->subtotal - $order->discount_amount)) }}</span>
                                </div>
                            @endif

                            <!-- Tax Breakdown (ONLY if tax > 0) -->
                            @if(($order->tax_type !== 'without_tax' || $order->tax_amount > 0) && $order->tax_amount > 0)
                                @php
                                    $grossTotal = max(0, $order->subtotal - $order->discount_amount);
                                    $effectiveTaxRate = $grossTotal > 0 ? ($order->tax_amount / $grossTotal) * 100 : 0;
                                @endphp
                                @if($order->gst_type === 'cgst_sgst' || ($order->cgst_amount > 0 || $order->sgst_amount > 0))
                                    @php
                                        $cgst = $order->cgst_amount > 0 ? $order->cgst_amount : round($order->tax_amount / 2, 2);
                                        $sgst = $order->sgst_amount > 0 ? $order->sgst_amount : round($order->tax_amount - $cgst, 2);
                                        $halfRate = round($effectiveTaxRate / 2, 2);
                                    @endphp
                                    <div class="summary-row">
                                        <span class="text-muted fw-semibold">CGST ({{ $halfRate }}%):</span>
                                        <span class="fw-semibold text-dark font-monospace">+{{ format_currency($cgst) }}</span>
                                    </div>
                                    <div class="summary-row">
                                        <span class="text-muted fw-semibold">SGST ({{ $halfRate }}%):</span>
                                        <span class="fw-semibold text-dark font-monospace">+{{ format_currency($sgst) }}</span>
                                    </div>
                                @elseif($order->gst_type === 'igst' || $order->igst_amount > 0)
                                    <div class="summary-row">
                                        <span class="text-muted fw-semibold">IGST ({{ round($effectiveTaxRate, 2) }}%):</span>
                                        <span class="fw-semibold text-dark font-monospace">+{{ format_currency($order->tax_amount) }}</span>
                                    </div>
                                @else
                                    <div class="summary-row">
                                        <span class="text-muted fw-semibold">{{ __('purchase.taxes') }} ({{ round($effectiveTaxRate, 2) }}%):</span>
                                        <span class="fw-semibold text-dark font-monospace">+{{ format_currency($order->tax_amount) }}</span>
                                    </div>
                                @endif
                            @endif

                            <hr class="my-2">
                            <div class="d-flex justify-content-between align-items-center pt-1">
                                <span class="fs-14 fw-bold text-dark">{{ __('purchase.grand_total') }}:</span>
                                <span class="fs-16 fw-bold text-primary font-monospace">{{ format_currency($order->grand_total) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Signature Footer -->
                <div class="row mt-4 pt-3 text-dark">
                    <div class="col-6 text-start">
                        <p class="fs-11 text-muted mb-0">
                            {{ __('purchase.po_queries_fulfillment') }}
                        </p>
                    </div>
                    <div class="col-6 text-end">
                        <div class="d-inline-block text-center" style="width: 180px;">
                            <hr class="mb-1 mt-2">
                            <span class="fs-11 text-muted text-uppercase fw-bold letter-spacing-1">{{ __('purchase.authorized_signature') }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Advance Payments & Accounting Section (if Approved) -->
            @if($order->status === 'Approved')
                <div class="px-4 py-4 bg-light-50 d-print-none">
                    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                        <div>
                            <h6 class="fw-bold text-dark mb-0">
                                <i class="feather-credit-card text-success me-1.5"></i>{{ __('purchase.vendor_advance_payments_accounting') }}
                            </h6>
                            <small class="text-muted fs-12">{{ __('purchase.record_advance_payments_help') }}</small>
                        </div>
                        @if($order->balance_due > 0)
                            <button type="button" class="btn btn-sm btn-success fw-bold px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#advancePaymentModal">
                                <i class="feather-plus-circle me-1.5"></i>{{ __('purchase.register_advance_payment') }}
                            </button>
                        @endif
                    </div>

                    <div class="row g-3 mb-3 text-dark">
                        <div class="col-md-4">
                            <div class="bg-white p-3 border rounded-3">
                                <span class="fs-11 text-uppercase text-muted fw-bold d-block mb-1">{{ __('purchase.total_po_amount') }}</span>
                                <h4 class="fw-bold text-dark mb-0 font-monospace">{{ format_currency($order->grand_total) }}</h4>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="bg-white p-3 border rounded-3">
                                <span class="fs-11 text-uppercase text-success fw-bold d-block mb-1">{{ __('purchase.advance_paid_posted') }}</span>
                                <h4 class="fw-bold text-success mb-0 font-monospace">{{ format_currency($order->total_advance_paid) }}</h4>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="bg-white p-3 border rounded-3">
                                <span class="fs-11 text-uppercase text-primary fw-bold d-block mb-1">{{ __('purchase.balance_due') }}</span>
                                <h4 class="fw-bold text-primary mb-0 font-monospace">{{ format_currency($order->balance_due) }}</h4>
                            </div>
                        </div>
                    </div>

                    @if($order->advancePayments->count() > 0)
                        <div class="table-responsive rounded-3 border bg-white">
                            <table class="table table-sm align-middle fs-13 text-dark mb-0">
                                <thead class="table-light fs-11 text-uppercase text-muted fw-semibold">
                                    <tr>
                                        <th class="ps-3">{{ __('purchase.payment_no') }}</th>
                                        <th>{{ __('purchase.payment_date') }}</th>
                                        <th>{{ __('purchase.method') }}</th>
                                        <th>{{ __('purchase.reference_no') }}</th>
                                        <th class="text-end pe-3">{{ __('purchase.amount') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($order->advancePayments as $adv)
                                        <tr>
                                            <td class="ps-3 fw-bold text-primary font-monospace">{{ $adv->payment_number }}</td>
                                            <td>{{ $adv->payment_date ? $adv->payment_date->format('d-M-Y') : '—' }}</td>
                                            <td><span class="badge bg-soft-info text-info fs-11 fw-semibold">{{ $adv->payment_method }}</span></td>
                                            <td class="font-monospace">{{ $adv->reference_number ?: __('purchase.not_applicable') }}</td>
                                            <td class="text-end pe-3 font-monospace fw-bold text-success">{{ format_currency($adv->amount) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-3 text-muted fs-12 bg-white border rounded-3">
                            <i class="feather-info me-1"></i>{{ __('purchase.no_advance_payments_registered') }}
                        </div>
                    @endif
                </div>
            @endif
        </x-ui.odoo-form-ui>
    </div>

    <!-- Reject PO Modal -->
    @if($order->status === 'Draft')
        <div class="modal fade" id="rejectPoModal" tabindex="-1" aria-labelledby="rejectPoModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow">
                    <form action="{{ route('purchase.orders.reject', $order->id) }}" method="POST">
                        @csrf
                        <div class="modal-header border-bottom bg-light">
                            <h5 class="modal-title fw-bold text-danger" id="rejectPoModalLabel">
                                <i class="feather-x-circle me-1.5"></i>{{ __('purchase.reject_purchase_order') }}
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body p-4">
                            <p class="fs-13 text-muted mb-3">
                                {{ __('purchase.reject_po_help_text') }} <strong class="text-dark">{{ $order->purchase_order_number }}</strong>.
                            </p>
                            <div class="mb-3">
                                <label for="rejection_reason" class="form-label fs-12 fw-bold text-dark">{{ __('purchase.rejection_reason_remarks') }} <span class="text-danger">*</span></label>
                                <textarea name="rejection_reason" id="rejection_reason" rows="3" class="form-control fs-13" placeholder="{{ __('purchase.reject_po_reason_placeholder') }}" required></textarea>
                            </div>
                        </div>
                        <div class="modal-footer border-top bg-light">
                            <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">{{ __('ui.cancel') ?? 'Cancel' }}</button>
                            <button type="submit" class="btn btn-sm btn-danger fw-bold px-3">
                                <i class="feather-x-circle me-1"></i>{{ __('purchase.confirm_rejection') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <!-- Register Advance Payment Modal -->
    @if($order->status === 'Approved')
        <x-ui.modal id="advancePaymentModal" :title="__('purchase.register_vendor_advance_payment')" size="lg">
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
                            <x-ui.odoo-form-ui type="input" inputType="number" :label="__('purchase.advance_amount')" name="amount" id="advance_amount" value="{{ min($order->balance_due, $order->grand_total) }}" step="0.01" min="0.01" max="{{ $order->balance_due }}" required="true" :placeholder="__('purchase.enter_amount_placeholder')" />
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
                    <button type="button" class="btn btn-sm btn-light border" data-bs-dismiss="modal">{{ __('ui.cancel') ?? 'Cancel' }}</button>
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

    <!-- View Approval Reminders Modal -->
    <div class="modal fade" id="viewRemindersModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-3">
                <div class="modal-header bg-soft-warning border-bottom py-3">
                    <h6 class="modal-title fw-bold text-dark fs-14">
                        <i class="feather-bell text-warning me-1.5 fs-15"></i> {{ __('purchase.approval_reminders_log') }} — <span id="reminderModalDocNumber" class="text-primary font-monospace"></span>
                    </h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-3">
                    <div id="reminderModalList" class="d-flex flex-column gap-2">
                        <!-- Populated dynamically via JS -->
                    </div>
                </div>
                <div class="modal-footer bg-light py-2 px-3 border-top">
                    <button type="button" class="btn btn-sm btn-secondary fw-semibold px-3" data-bs-dismiss="modal">{{ __('purchase.close') }}</button>
                </div>
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

        function showReminderHistoryModal(docNumber, reminders) {
            document.getElementById('reminderModalDocNumber').innerText = docNumber || '';
            const container = document.getElementById('reminderModalList');
            container.innerHTML = '';

            if (!reminders || reminders.length === 0) {
                container.innerHTML = '<div class="text-muted fs-12 text-center py-3">{{ __('purchase.no_reminder_messages') }}</div>';
            } else {
                reminders.forEach(r => {
                    const item = document.createElement('div');
                    item.className = 'border rounded-3 p-3 bg-light-50 shadow-2xs';
                    item.innerHTML = `
                        <div class="d-flex align-items-center justify-content-between mb-1">
                            <span class="fw-bold text-dark fs-12"><i class="feather-user text-primary me-1"></i>${r.user}</span>
                            <span class="text-muted fs-10 font-monospace">${r.time}</span>
                        </div>
                        ${r.note ? `<div class="text-dark fst-italic fs-12 bg-white p-2 rounded border border-warning-subtle mt-1"><i class="feather-message-square me-1 text-warning"></i>"${r.note}"</div>` : '<div class="text-muted fs-11 fst-italic mt-1">{{ __('purchase.no_note_provided') }}</div>'}
                    `;
                    container.appendChild(item);
                });
            }

            var modal = new bootstrap.Modal(document.getElementById('viewRemindersModal'));
            modal.show();
        }
    </script>
    @endpush
@endsection
