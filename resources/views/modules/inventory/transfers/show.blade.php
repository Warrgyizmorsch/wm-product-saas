@extends('layouts.duralux')

@section('title', __('inventory.stock_transfers_details') . ' #' . $transfer->transfer_number . ' | SaaS ERP')
@section('page-title', __('inventory.stock_transfers_details'))
@section('breadcrumb', __('inventory.stock_transfers_details'))

@section('content')
    <div class="erp-single-panel text-dark">
        <x-ui.odoo-form-ui type="sheet">

            @php
                $isSubcontractTransfer = ($transfer->toWarehouse?->type === 'subcontractor') || str_contains($transfer->notes ?? '', 'Subcontract') || str_contains($transfer->notes ?? '', 'MO-');
                $isWipDispatch = $isSubcontractTransfer && (str_contains($transfer->notes ?? '', 'WIP') || str_contains($transfer->notes ?? '', 'Op'));
            @endphp

            <!-- Top Header & Actions Bar -->
            <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom flex-wrap gap-3">
                <div>
                    <span class="fs-11 text-muted text-uppercase fw-bold d-block mb-1 letter-spacing-1">
                        <i class="feather-box text-primary me-1"></i>{{ __('inventory.stock_transfer_document') }}
                    </span>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <h3 class="fw-bold text-dark mb-0">{{ $transfer->transfer_number }}</h3>
                        <x-ui.status-badge :status="$transfer->status" size="md" />
                        @if($isSubcontractTransfer)
                            @if($isWipDispatch)
                                <span class="badge bg-soft-warning text-dark border border-warning px-2.5 py-1 fs-11 fw-bold me-2">
                                    <i class="feather-truck me-1"></i>{{ __('inventory.subcontract_wip_dispatch') }}
                                </span>
                            @else
                                <span class="badge bg-soft-info text-dark border border-info px-2.5 py-1 fs-11 fw-bold me-2">
                                    <i class="feather-box me-1"></i>{{ __('inventory.subcontract_material_dispatch') }}
                                </span>
                            @endif
                        @endif
                    </div>
                    <small class="text-muted fs-12">
                        <i class="feather-clock me-1"></i>{{ __('inventory.created_on') }}
                        {{ \Carbon\Carbon::parse($transfer->created_at)->format('d M Y, h:i A') }}
                    </small>
                </div>

                <!-- Action Buttons -->
                <div class="d-flex gap-2 flex-wrap align-items-center">
                    @if(in_array($transfer->status, ['Draft', 'Pending']))
                        <form id="dispatchTransferForm" action="{{ route('inventory.transfers.dispatch', $transfer->id) }}" method="POST">
                            @csrf
                            <x-ui.button type="button" variant="primary" icon="feather-truck" onclick="handleDispatchTransfer()">
                                {{ __('inventory.dispatch_transfer') }}
                            </x-ui.button>
                        </form>
                        <form id="cancelTransferForm" action="{{ route('inventory.transfers.cancel', $transfer->id) }}" method="POST">
                            @csrf
                            <x-ui.button type="button" variant="danger" icon="feather-x-circle" onclick="handleCancelTransfer()">
                                {{ __('inventory.cancel') }}
                            </x-ui.button>
                        </form>
                    @elseif(in_array($transfer->status, ['In Transit', 'In-Transit']))
                        <form id="receiveTransferForm" action="{{ route('inventory.transfers.receive', $transfer->id) }}" method="POST">
                            @csrf
                            <x-ui.button type="button" variant="success" icon="feather-check-circle" class="text-white" onclick="handleReceiveTransfer()">
                                {{ __('inventory.receive_stock') }}
                            </x-ui.button>
                        </form>
                        <form id="cancelTransferForm" action="{{ route('inventory.transfers.cancel', $transfer->id) }}" method="POST">
                            @csrf
                            <x-ui.button type="button" variant="danger" icon="feather-x-circle" onclick="handleCancelTransfer()">
                                {{ __('inventory.cancel') }}
                            </x-ui.button>
                        </form>
                    @endif

                    <x-ui.button href="{{ route('inventory.transfers.index') }}" variant="light" icon="feather-arrow-left">
                        {{ __('inventory.back_to_list') }}
                    </x-ui.button>
                </div>
            </div>

            @if($isSubcontractTransfer)
                <div class="alert alert-info border-0 border-start border-4 border-info mb-4 rounded-3 shadow-sm bg-soft-info">
                    <div class="d-flex align-items-top">
                        <i class="feather-info fs-18 text-info me-3 mt-0.5"></i>
                        <div>
                            <h6 class="fw-bold text-info mb-1">{{ __('inventory.subcontract_material_dispatch') }}</h6>
                            <p class="fs-12 text-dark mb-0">
                                {{ __('inventory.subcontract_dispatch_info', ['warehouse' => $transfer->toWarehouse?->name ?? 'Vendor Warehouse']) }}
                            </p>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Transfer Metadata Summary Cards -->
            <div class="row g-3 mb-4">
                <div class="col-md-3 col-6">
                    <div class="p-3 bg-light rounded border h-100">
                        <span class="text-muted d-block fs-11 fw-bold text-uppercase mb-1"
                            style="letter-spacing:0.5px;">{{ __('inventory.from_warehouse') }}</span>
                        <span class="fw-bold text-dark fs-14 d-flex align-items-center">
                            <i
                                class="feather-arrow-up-right text-danger me-1 fs-14"></i>{{ $transfer->fromWarehouse->name ?? 'N/A' }}
                        </span>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="p-3 bg-light rounded border h-100">
                        <span class="text-muted d-block fs-11 fw-bold text-uppercase mb-1"
                            style="letter-spacing:0.5px;">{{ __('inventory.to_warehouse') }}</span>
                        <span class="fw-bold text-dark fs-14 d-flex align-items-center">
                            <i
                                class="feather-arrow-down-left text-success me-1 fs-14"></i>{{ $transfer->toWarehouse->name ?? 'N/A' }}
                        </span>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="p-3 bg-light rounded border h-100">
                        <span class="text-muted d-block fs-11 fw-bold text-uppercase mb-1"
                            style="letter-spacing:0.5px;">{{ __('inventory.transfer_date') }}</span>
                        <span class="fw-bold text-dark fs-14 d-flex align-items-center">
                            <i
                                class="feather-calendar text-muted me-1 fs-13"></i>{{ $transfer->transfer_date ? \Carbon\Carbon::parse($transfer->transfer_date)->format('d M Y') : 'N/A' }}
                        </span>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="p-3 bg-light rounded border h-100">
                        <span class="text-muted d-block fs-11 fw-bold text-uppercase mb-1"
                            style="letter-spacing:0.5px;">{{ __('inventory.current_status') }}</span>
                        <div class="mt-1">
                            <x-ui.status-badge :status="$transfer->status" size="sm" />
                        </div>
                    </div>
                </div>
            </div>

        <!-- Transferred Items Section -->
        <div class="mb-4">
            <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
                <h5 class="fw-bold text-dark mb-0 d-flex align-items-center">
                    <i class="feather-layers text-primary me-2"></i>{{ __('inventory.transferred_items') }}
                </h5>
                <span class="badge bg-light text-muted border px-2.5 py-1 fs-12">
                    {{ count($transfer->items) }} {{ count($transfer->items) === 1 ? 'Item' : 'Items' }}
                </span>
            </div>
            
            <div class="table-responsive rounded border">
                <table class="table table-hover align-middle mb-0" id="transferredItemsTable">
                    <thead class="table-light bg-light">
                        <tr>
                            <th style="width: 4%;" class="text-center">#</th>
                            <th style="width: 25%;">{{ __('inventory.variant_details') }}</th>
                            <th style="width: 13%;">{{ __('inventory.sku') }}</th>
                            <th style="width: 12%;" class="text-end">{{ __('inventory.transfer_qty') }}</th>
                            <th style="width: 11%;" class="text-end">{{ __('inventory.received_qty') }}</th>
                            <th style="width: 35%; min-width: 310px;">Tracking (Batch / Serial Numbers)</th>
                        </tr>
                    </thead>
                    <tbody class="text-dark">
                        @foreach($transfer->items as $index => $item)
                            <tr>
                                <td class="text-center fw-bold text-muted">{{ $index + 1 }}</td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="avatar-xs rounded bg-soft-primary text-primary d-flex align-items-center justify-content-center fw-bold flex-shrink-0" style="width: 30px; height: 30px;">
                                            <i class="feather-box fs-14"></i>
                                        </div>
                                        <div>
                                            @if($item->product)
                                                <a href="{{ route('inventory.products.show', $item->product_id) }}"
                                                    class="fw-bold text-dark text-decoration-none d-block fs-13 hover-primary">
                                                    {{ $item->product->name }}
                                                </a>
                                                <span class="text-muted fs-11">
                                                    {{ ucfirst($item->product->type ?? 'Goods') }}
                                                </span>
                                            @else
                                                <strong class="text-dark">N/A</strong>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-muted font-monospace border fs-11 px-2 py-1">
                                        {{ $item->product->sku ?? '—' }}
                                    </span>
                                </td>
                                <td class="text-end font-monospace fw-bold fs-13 text-dark">
                                    {{ number_format($item->quantity, 2) }}
                                    @if($item->product && $item->product->uom)
                                        <span class="fs-11 fw-normal text-muted ms-0.5">{{ $item->product->uom->code ?? $item->product->uom->name }}</span>
                                    @endif
                                </td>
                                <td class="text-end font-monospace">
                                    @if($item->received_quantity > 0)
                                        <span class="badge bg-soft-success text-success border border-success-subtle px-2 py-1 fw-bold fs-11">
                                            {{ number_format($item->received_quantity, 2) }}
                                        </span>
                                    @else
                                        <span class="text-muted fs-12">0.00</span>
                                    @endif
                                </td>
                                <td>
                                    @if($item->batch)
                                        <div class="mb-2 d-flex align-items-center gap-1.5 flex-wrap">
                                            <span class="badge bg-soft-info text-info border border-info-subtle px-2 py-1 fs-11 fw-semibold">
                                                <i class="feather-layers me-1"></i>Batch: {{ $item->batch->batch_number }}
                                            </span>
                                            @if($item->batch->expiry_date)
                                                <span class="badge bg-light text-muted border px-2 py-1 fs-11">
                                                    <i class="feather-calendar me-1"></i>Exp: {{ $item->batch->expiry_date->format('d M Y') }}
                                                </span>
                                            @endif
                                        </div>
                                    @endif

                                    @if(!empty($item->serial_numbers))
                                        <div class="tracking-serials-box border rounded-3 p-2 bg-light-subtle shadow-2xs">
                                            <div class="d-flex align-items-center justify-content-between mb-1.5">
                                                <span class="badge bg-soft-primary text-primary border border-primary-subtle px-2 py-0.5 fs-11 fw-semibold">
                                                    <i class="feather-check-circle me-1"></i>{{ count($item->serial_numbers) }} Serials Tracked
                                                </span>
                                                <button type="button" class="btn btn-xs btn-outline-secondary py-0.5 px-2 fs-11 copy-serials-btn"
                                                    onclick="copySerialsToClipboard(this, '{{ addslashes(implode(', ', $item->serial_numbers)) }}')"
                                                    title="Copy all serials to clipboard">
                                                    <i class="feather-copy me-1"></i><span>Copy All</span>
                                                </button>
                                            </div>
                                            <div class="tracking-serials-scroll" style="max-height: 105px; overflow-y: auto;">
                                                <div class="d-flex flex-wrap gap-1">
                                                    @foreach($item->serial_numbers as $sn)
                                                        <span class="badge tracking-serial-badge font-monospace fs-11 py-1 px-1.5 border">
                                                            <i class="feather-hash text-muted me-0.5"></i>{{ $sn }}
                                                        </span>
                                                    @endforeach
                                                </div>
                                            </div>
                                            @if(count($item->serial_numbers) > 6)
                                                <div class="mt-1 pt-1 border-top d-flex justify-content-between align-items-center text-muted fs-11">
                                                    <small class="d-flex align-items-center gap-1">
                                                        <i class="feather-info fs-11 text-primary"></i>
                                                        <span>Scroll inside box to view all {{ count($item->serial_numbers) }} numbers</span>
                                                    </small>
                                                </div>
                                            @endif
                                        </div>
                                    @elseif(!$item->batch)
                                        <span class="badge bg-light text-muted border px-2 py-1 fs-11">
                                            <i class="feather-minus me-1"></i>No tracking
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Notes Section -->
            @if($transfer->notes)
                <div class="p-3 bg-light rounded border mt-4">
                    <h6 class="fw-bold text-dark mb-1">
                        <i class="feather-file-text text-primary me-1"></i>{{ __('inventory.notes_remarks') }}
                    </h6>
                    <p class="mb-0 text-muted fs-13">{{ $transfer->notes }}</p>
                </div>
            @endif

        </x-ui.odoo-form-ui>
    </div>

    {{-- Universal confirmation modal for transfer dispatch, receipt, and cancellation --}}
    <x-ui.confirmation-modal />
@endsection

@push('styles')
    <style>
        /* Table row styling */
        #transferredItemsTable td {
            vertical-align: middle !important;
            padding: 12px 10px !important;
        }
        #transferredItemsTable th {
            padding: 10px 12px !important;
            font-size: 11px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .hover-primary:hover {
            color: var(--bs-primary) !important;
        }

        /* Tracking container */
        .tracking-serials-box {
            background-color: #f8fafc;
            border-color: #e2e8f0 !important;
            transition: border-color 0.15s ease;
        }
        .tracking-serials-box:hover {
            border-color: #cbd5e1 !important;
        }

        /* Tracking serial badges */
        .tracking-serial-badge {
            background-color: #ffffff !important;
            color: #1e293b !important;
            border-color: #cbd5e1 !important;
            font-weight: 500;
            line-height: 1.2;
            transition: all 0.15s ease;
        }
        .tracking-serial-badge:hover {
            border-color: #3b82f6 !important;
            color: #1d4ed8 !important;
            background-color: #eff6ff !important;
        }

        /* Sleek scrollbar for serials box */
        .tracking-serials-scroll::-webkit-scrollbar {
            width: 4px;
        }
        .tracking-serials-scroll::-webkit-scrollbar-track {
            background: transparent;
        }
        .tracking-serials-scroll::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }
        .tracking-serials-scroll::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        /* Copy serials button */
        .copy-serials-btn {
            border-color: #cbd5e1;
            color: #475569;
            font-size: 11px;
            padding: 2px 8px;
            border-radius: 4px;
            line-height: 1.4;
            transition: all 0.15s ease;
        }
        .copy-serials-btn:hover {
            background-color: #f1f5f9;
            color: #0f172a;
            border-color: #94a3b8;
        }

        /* ================= DARK MODE STYLES ================= */
        html.app-skin-dark .erp-single-panel {
            background-color: #121a2d !important;
            color: #f1f5f9 !important;
        }
        html.app-skin-dark .erp-single-panel .bg-light,
        html.app-skin-dark .erp-single-panel .bg-light-subtle {
            background-color: #162038 !important;
            border-color: #283c50 !important;
        }
        html.app-skin-dark .erp-single-panel .border {
            border-color: #283c50 !important;
        }
        html.app-skin-dark .erp-single-panel .text-dark {
            color: #f1f5f9 !important;
        }
        html.app-skin-dark #transferredItemsTable thead,
        html.app-skin-dark #transferredItemsTable th {
            background-color: #162038 !important;
            color: #94a3b8 !important;
            border-color: #283c50 !important;
        }
        html.app-skin-dark #transferredItemsTable td {
            border-color: #283c50 !important;
            color: #cbd5e1 !important;
        }
        html.app-skin-dark #transferredItemsTable tbody tr:hover {
            background-color: #1a263d !important;
        }
        html.app-skin-dark .tracking-serials-box {
            background-color: #121a2d !important;
            border-color: #283c50 !important;
        }
        html.app-skin-dark .tracking-serials-box:hover {
            border-color: #3b506b !important;
        }
        html.app-skin-dark .tracking-serial-badge {
            background-color: #162038 !important;
            color: #e2e8f0 !important;
            border-color: #283c50 !important;
        }
        html.app-skin-dark .tracking-serial-badge:hover {
            background-color: #1e293b !important;
            color: #60a5fa !important;
            border-color: #3b82f6 !important;
        }
        html.app-skin-dark .tracking-serials-scroll::-webkit-scrollbar-thumb {
            background: #283c50;
        }
        html.app-skin-dark .tracking-serials-scroll::-webkit-scrollbar-thumb:hover {
            background: #3b506b;
        }
        html.app-skin-dark .copy-serials-btn {
            border-color: #283c50 !important;
            color: #94a3b8 !important;
        }
        html.app-skin-dark .copy-serials-btn:hover {
            background-color: #1e293b !important;
            color: #f1f5f9 !important;
            border-color: #3b82f6 !important;
        }
    </style>
@endpush

@push('scripts')
    <script>
        function handleDispatchTransfer() {
            if (typeof confirmAction === 'function') {
                confirmAction({
                    title: 'Dispatch Stock Transfer',
                    message: 'Are you sure you want to dispatch transfer #{{ $transfer->transfer_number }}? The items will be marked as In-Transit.',
                    confirmButtonText: 'Yes, Dispatch',
                    cancelButtonText: 'Cancel',
                    variant: 'primary',
                    onConfirm: function() {
                        document.getElementById('dispatchTransferForm').submit();
                    }
                });
            } else {
                document.getElementById('dispatchTransferForm').submit();
            }
        }

        function handleReceiveTransfer() {
            if (typeof confirmAction === 'function') {
                confirmAction({
                    title: 'Receive Stock Transfer',
                    message: 'Are you sure you want to receive items for transfer #{{ $transfer->transfer_number }} into {{ $transfer->toWarehouse->name ?? "destination warehouse" }}?',
                    confirmButtonText: 'Yes, Receive Stock',
                    cancelButtonText: 'Cancel',
                    variant: 'success',
                    onConfirm: function() {
                        document.getElementById('receiveTransferForm').submit();
                    }
                });
            } else {
                document.getElementById('receiveTransferForm').submit();
            }
        }

        function handleCancelTransfer() {
            if (typeof confirmAction === 'function') {
                confirmAction({
                    title: 'Cancel Stock Transfer',
                    message: 'Are you sure you want to cancel transfer #{{ $transfer->transfer_number }}? Any reserved inventory will be automatically released.',
                    confirmButtonText: 'Yes, Cancel Transfer',
                    cancelButtonText: 'Keep Transfer',
                    variant: 'warning',
                    onConfirm: function() {
                        document.getElementById('cancelTransferForm').submit();
                    }
                });
            } else {
                document.getElementById('cancelTransferForm').submit();
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