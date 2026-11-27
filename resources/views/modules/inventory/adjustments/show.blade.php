@extends('layouts.duralux')

@section('title', __('inventory.stock_adjustments') . ' #' . $adjustment->adjustment_number . ' | SaaS ERP')
@section('page-title', __('inventory.stock_adjustments'))
@section('breadcrumb', __('inventory.stock_adjustments'))

@section('content')
<div class="erp-single-panel text-dark">
    <x-ui.odoo-form-ui type="sheet">

        <!-- Top Header & Actions Bar -->
        <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom flex-wrap gap-3">
            <div>
                <span class="fs-11 text-muted text-uppercase fw-bold d-block mb-1 letter-spacing-1">
                    <i class="feather-sliders text-primary me-1"></i>{{ __('inventory.stock_adjustment_document') }}
                </span>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <h3 class="fw-bold text-dark mb-0">{{ $adjustment->adjustment_number }}</h3>
                    <x-ui.status-badge :status="$adjustment->status" size="md" />
                </div>
                <small class="text-muted fs-12">
                    <i class="feather-clock me-1"></i>{{ __('inventory.created_on') }} {{ \Carbon\Carbon::parse($adjustment->created_at)->format('d M Y, h:i A') }}
                </small>
            </div>

            <!-- Action Buttons -->
            <div class="d-flex gap-2 flex-wrap align-items-center d-print-none">
                @if($adjustment->status === 'Draft')
                    <form id="approveAdjustmentForm" action="{{ route('inventory.adjustments.approve', $adjustment->id) }}" method="POST">
                        @csrf
                        <x-ui.button type="button" variant="success" icon="feather-check-circle" class="text-white" onclick="handleApproveAdjustment()">
                            {{ __('inventory.approve_apply') }}
                        </x-ui.button>
                    </form>
                    <form id="cancelAdjustmentForm" action="{{ route('inventory.adjustments.cancel', $adjustment->id) }}" method="POST">
                        @csrf
                        <x-ui.button type="button" variant="danger" icon="feather-x-circle" onclick="handleCancelAdjustment()">
                            {{ __('inventory.cancel_adjustment') }}
                        </x-ui.button>
                    </form>
                @endif

                <x-ui.button href="{{ route('inventory.adjustments.index') }}" variant="light" icon="feather-arrow-left">
                    {{ __('inventory.back_to_list') }}
                </x-ui.button>
            </div>
        </div>

        <!-- Metadata Summary Grid Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-3 col-6">
                <div class="p-3 bg-light rounded border h-100">
                    <span class="text-muted d-block fs-11 fw-bold text-uppercase mb-1" style="letter-spacing:0.5px;">{{ __('inventory.warehouse') }}</span>
                    <span class="fw-bold text-dark fs-14 d-flex align-items-center">
                        <i class="feather-map-pin text-muted me-1 fs-13"></i>{{ $adjustment->warehouse->name ?? 'N/A' }}
                    </span>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="p-3 bg-light rounded border h-100">
                    <span class="text-muted d-block fs-11 fw-bold text-uppercase mb-1" style="letter-spacing:0.5px;">{{ __('inventory.reason') }}</span>
                    <div class="mt-1">
                        <span class="badge bg-light text-dark border font-monospace fs-11">{{ $adjustment->reason }}</span>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="p-3 bg-light rounded border h-100">
                    <span class="text-muted d-block fs-11 fw-bold text-uppercase mb-1" style="letter-spacing:0.5px;">{{ __('inventory.adjustment_date') }}</span>
                    <span class="fw-bold text-dark fs-14 d-flex align-items-center">
                        <i class="feather-calendar text-muted me-1 fs-13"></i>{{ $adjustment->adjustment_date ? \Carbon\Carbon::parse($adjustment->adjustment_date)->format('d M Y') : 'N/A' }}
                    </span>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="p-3 bg-light rounded border h-100">
                    <span class="text-muted d-block fs-11 fw-bold text-uppercase mb-1" style="letter-spacing:0.5px;">{{ __('inventory.current_status') }}</span>
                    <div class="mt-1">
                        <x-ui.status-badge :status="$adjustment->status" size="sm" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Adjusted Items Section -->
        <div class="mb-4">
            <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                <h5 class="fw-bold text-dark mb-0 d-flex align-items-center">
                    <i class="feather-layers text-primary me-2"></i>{{ __('inventory.adjusted_line_items') }}
                </h5>
                <span class="badge bg-light text-muted border px-2.5 py-1 fs-12">
                    {{ count($adjustment->items) }} {{ count($adjustment->items) === 1 ? 'Item' : 'Items' }}
                </span>
            </div>
            
            <div class="table-responsive rounded border">
                <table class="table table-hover align-middle mb-0" id="adjustedItemsTable">
                    <thead class="table-light bg-light">
                        <tr>
                            <th style="width: 4%;" class="text-center">#</th>
                            <th style="width: 25%;">{{ __('inventory.product_name') }}</th>
                            <th style="width: 11%;" class="text-center">{{ __('inventory.type') }}</th>
                            <th style="width: 10%;" class="text-end">{{ __('inventory.quantity') }}</th>
                            <th style="width: 11%;" class="text-end">{{ __('inventory.unit_cost') }}</th>
                            <th style="width: 12%;" class="text-end">{{ __('inventory.total_amount') }}</th>
                            <th style="width: 27%; min-width: 260px;">Tracking (Batch / Serial Numbers)</th>
                        </tr>
                    </thead>
                    <tbody class="text-dark">
                        @foreach($adjustment->items as $index => $item)
                            <tr>
                                <td class="text-center fw-bold text-muted">{{ $index + 1 }}</td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="avatar-xs rounded bg-soft-primary text-primary d-flex align-items-center justify-content-center fw-bold flex-shrink-0" style="width: 28px; height: 28px;">
                                            <i class="feather-box fs-13"></i>
                                        </div>
                                        <div>
                                            @if($item->product)
                                                <a href="{{ route('inventory.products.show', $item->product_id) }}" class="fw-bold text-dark text-decoration-none d-block fs-13">
                                                    {{ $item->product->name }}
                                                </a>
                                                @if($item->product->sku)
                                                    <span class="fs-11 text-muted font-monospace">SKU: {{ $item->product->sku }}</span>
                                                @endif
                                            @else
                                                <strong class="text-dark">N/A</strong>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="text-center">
                                    @if($item->type === 'Addition')
                                        <span class="badge bg-soft-success text-success border border-success-subtle px-2 py-0.5 fs-11 fw-bold">
                                            <i class="feather-plus me-0.5"></i>{{ __('inventory.addition') }}
                                        </span>
                                    @else
                                        <span class="badge bg-soft-danger text-danger border border-danger-subtle px-2 py-0.5 fs-11 fw-bold">
                                            <i class="feather-minus me-0.5"></i>{{ __('inventory.deduction') }}
                                        </span>
                                    @endif
                                </td>
                                <td class="text-end font-monospace fw-bold fs-13 text-dark">
                                    {{ number_format($item->quantity, 2) }}
                                    @if($item->product && $item->product->uom)
                                        <span class="fs-11 fw-normal text-muted ms-0.5">{{ $item->product->uom->code ?? $item->product->uom->name }}</span>
                                    @endif
                                </td>
                                <td class="text-end font-monospace fw-semibold fs-12 text-muted">
                                    {!! format_currency($item->unit_cost) !!}
                                </td>
                                <td class="text-end font-monospace fw-bold fs-13 text-dark">
                                    {!! format_currency($item->total_amount) !!}
                                </td>
                                <td>
                                    @if($item->batch)
                                        <div class="mb-1.5 d-flex align-items-center gap-1.5 flex-wrap">
                                            <span class="badge bg-soft-info text-info border border-info-subtle px-2 py-0.5 fs-11 fw-semibold">
                                                <i class="feather-layers me-1"></i>Batch: {{ $item->batch->batch_number }}
                                            </span>
                                            @if($item->batch->expiry_date)
                                                <span class="badge bg-light text-muted border px-2 py-0.5 fs-11">
                                                    <i class="feather-calendar me-1"></i>Exp: {{ $item->batch->expiry_date->format('d M Y') }}
                                                </span>
                                            @endif
                                        </div>
                                    @endif

                                    @if(!empty($item->serial_numbers))
                                        <div class="tracking-serials-box border rounded-3 p-2 bg-light-subtle shadow-2xs">
                                            <div class="d-flex align-items-center justify-content-between mb-1">
                                                <span class="badge bg-soft-primary text-primary border border-primary-subtle px-2 py-0.5 fs-10 fw-semibold">
                                                    <i class="feather-check-circle me-1"></i>{{ count($item->serial_numbers) }} Serials
                                                </span>
                                                <button type="button" class="btn btn-xs btn-outline-secondary py-0 px-2 fs-10 copy-serials-btn"
                                                    onclick="copySerialsToClipboard(this, '{{ addslashes(implode(', ', $item->serial_numbers)) }}')"
                                                    title="Copy all serials">
                                                    <i class="feather-copy me-1"></i>Copy All
                                                </button>
                                            </div>
                                            <div class="tracking-serials-scroll" style="max-height: 85px; overflow-y: auto;">
                                                <div class="d-flex flex-wrap gap-1">
                                                    @foreach($item->serial_numbers as $sn)
                                                        <span class="badge tracking-serial-badge font-monospace fs-11 py-0.5 px-1.5 border">
                                                            <i class="feather-hash text-muted me-0.5"></i>{{ $sn }}
                                                        </span>
                                                    @endforeach
                                                </div>
                                            </div>
                                        </div>
                                    @elseif(!$item->batch)
                                        <span class="badge bg-light text-muted border px-2 py-0.5 fs-11">
                                            <i class="feather-minus me-1"></i>Standard (No tracking)
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Notes Section -->
        @if($adjustment->notes)
            <div class="p-3 bg-light rounded border mt-4">
                <h6 class="fw-bold text-dark mb-1">
                    <i class="feather-file-text text-primary me-1"></i>{{ __('inventory.notes_remarks') }}
                </h6>
                <p class="mb-0 text-muted fs-13">{{ $adjustment->notes }}</p>
            </div>
        @endif

    </x-ui.odoo-form-ui>
</div>
@endsection

@push('styles')
    <style>
        #adjustedItemsTable td {
            vertical-align: middle !important;
            padding: 10px 12px !important;
        }
        #adjustedItemsTable th {
            padding: 10px 12px !important;
            font-size: 11px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .tracking-serials-box {
            background-color: #f8fafc;
            border-color: #e2e8f0 !important;
        }
        .tracking-serial-badge {
            background-color: #ffffff !important;
            color: #1e293b !important;
            border-color: #cbd5e1 !important;
            font-weight: 500;
        }
        .tracking-serial-badge:hover {
            border-color: #3b82f6 !important;
            color: #1d4ed8 !important;
            background-color: #eff6ff !important;
        }
        .tracking-serials-scroll::-webkit-scrollbar {
            width: 4px;
        }
        .tracking-serials-scroll::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }

        /* ================= DARK MODE STYLING ================= */
        html.app-skin-dark .erp-single-panel {
            background-color: #121a2d !important;
            color: #f1f5f9 !important;
        }
        html.app-skin-dark .erp-single-panel .bg-light {
            background-color: #162038 !important;
            border-color: #283c50 !important;
        }
        html.app-skin-dark .erp-single-panel .border {
            border-color: #283c50 !important;
        }
        html.app-skin-dark .erp-single-panel .text-dark {
            color: #f1f5f9 !important;
        }
        html.app-skin-dark #adjustedItemsTable thead,
        html.app-skin-dark #adjustedItemsTable th {
            background-color: #162038 !important;
            color: #94a3b8 !important;
            border-color: #283c50 !important;
        }
        html.app-skin-dark #adjustedItemsTable td {
            border-color: #283c50 !important;
            color: #cbd5e1 !important;
        }
        html.app-skin-dark #adjustedItemsTable tbody tr:hover {
            background-color: #1a263d !important;
        }
        html.app-skin-dark .tracking-serials-box {
            background-color: #121a2d !important;
            border-color: #283c50 !important;
        }
        html.app-skin-dark .tracking-serial-badge {
            background-color: #162038 !important;
            color: #e2e8f0 !important;
            border-color: #283c50 !important;
        }
    </style>
@endpush

@push('scripts')
    <script>
        function handleApproveAdjustment() {
            if (typeof confirmAction === 'function') {
                confirmAction({
                    title: 'Approve & Apply Adjustment',
                    message: 'Are you sure you want to approve adjustment #{{ $adjustment->adjustment_number }}? Inventory balances, batches, and serial numbers will be permanently updated.',
                    confirmButtonText: 'Yes, Approve & Apply',
                    cancelButtonText: 'Cancel',
                    variant: 'success',
                    onConfirm: function() {
                        document.getElementById('approveAdjustmentForm').submit();
                    }
                });
            } else {
                document.getElementById('approveAdjustmentForm').submit();
            }
        }

        function handleCancelAdjustment() {
            if (typeof confirmAction === 'function') {
                confirmAction({
                    title: 'Cancel Stock Adjustment',
                    message: 'Are you sure you want to cancel adjustment #{{ $adjustment->adjustment_number }}?',
                    confirmButtonText: 'Yes, Cancel Adjustment',
                    cancelButtonText: 'Keep Draft',
                    variant: 'warning',
                    onConfirm: function() {
                        document.getElementById('cancelAdjustmentForm').submit();
                    }
                });
            } else {
                document.getElementById('cancelAdjustmentForm').submit();
            }
        }

        function copySerialsToClipboard(btn, text) {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(function() {
                    showCopySuccess(btn);
                }).catch(function() {
                    fallbackCopy(btn, text);
                });
            } else {
                fallbackCopy(btn, text);
            }
        }

        function fallbackCopy(btn, text) {
            var temp = document.createElement("textarea");
            temp.value = text;
            document.body.appendChild(temp);
            temp.select();
            document.execCommand("copy");
            document.body.removeChild(temp);
            showCopySuccess(btn);
        }

        function showCopySuccess(btn) {
            var originalHtml = btn.innerHTML;
            btn.classList.remove('btn-outline-secondary');
            btn.classList.add('btn-success', 'text-white');
            btn.innerHTML = '<i class="feather-check me-1"></i>Copied!';
            setTimeout(function() {
                btn.classList.remove('btn-success', 'text-white');
                btn.classList.add('btn-outline-secondary');
                btn.innerHTML = originalHtml;
            }, 2000);
        }
    </script>
@endpush
