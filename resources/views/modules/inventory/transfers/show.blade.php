@extends('layouts.duralux')

@section('title', 'Transfer Details #' . $transfer->transfer_number . ' | SaaS ERP')
@section('page-title', 'Stock Transfer Details')
@section('breadcrumb', 'Inventory / Stock Transfers / Details')

@section('content')
<div class="erp-single-panel text-dark">
    <x-ui.odoo-form-ui type="sheet">

        <!-- Top Header & Actions Bar -->
        <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom flex-wrap gap-3">
            <div>
                <span class="fs-11 text-muted text-uppercase fw-bold d-block mb-1 letter-spacing-1">
                    <i class="feather-box text-primary me-1"></i>Stock Transfer Document
                </span>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <h3 class="fw-bold text-dark mb-0">{{ $transfer->transfer_number }}</h3>
                    <x-ui.status-badge :status="$transfer->status" size="md" />
                </div>
                <small class="text-muted fs-12">
                    <i class="feather-clock me-1"></i>Created on {{ \Carbon\Carbon::parse($transfer->created_at)->format('d M Y, h:i A') }}
                </small>
            </div>

            <!-- Action Buttons -->
            <div class="d-flex gap-2 flex-wrap align-items-center">
                @if(in_array($transfer->status, ['Draft', 'Pending']))
                    <form action="{{ route('inventory.transfers.dispatch', $transfer->id) }}" method="POST" onsubmit="return confirm('Dispatch items and mark as In-Transit?')">
                        @csrf
                        <x-ui.button type="submit" variant="primary" icon="feather-truck">
                            Dispatch Transfer
                        </x-ui.button>
                    </form>
                    <form action="{{ route('inventory.transfers.cancel', $transfer->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to cancel this transfer?')">
                        @csrf
                        <x-ui.button type="submit" variant="danger" icon="feather-x-circle">
                            Cancel
                        </x-ui.button>
                    </form>
                @elseif(in_array($transfer->status, ['In Transit', 'In-Transit']))
                    <form action="{{ route('inventory.transfers.receive', $transfer->id) }}" method="POST" onsubmit="return confirm('Receive items at target warehouse?')">
                        @csrf
                        <x-ui.button type="submit" variant="success" icon="feather-check-circle" class="text-white">
                            Receive Stock
                        </x-ui.button>
                    </form>
                    <form action="{{ route('inventory.transfers.cancel', $transfer->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to cancel this transfer?')">
                        @csrf
                        <x-ui.button type="submit" variant="danger" icon="feather-x-circle">
                            Cancel
                        </x-ui.button>
                    </form>
                @endif

                <x-ui.button href="{{ route('inventory.transfers.index') }}" variant="light" icon="feather-arrow-left">
                    Back to List
                </x-ui.button>
            </div>
        </div>

        <!-- Transfer Metadata Summary Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-3 col-6">
                <div class="p-3 bg-light rounded border h-100">
                    <span class="text-muted d-block fs-11 fw-bold text-uppercase mb-1" style="letter-spacing:0.5px;">From Warehouse</span>
                    <span class="fw-bold text-dark fs-14 d-flex align-items-center">
                        <i class="feather-arrow-up-right text-danger me-1 fs-14"></i>{{ $transfer->fromWarehouse->name ?? 'N/A' }}
                    </span>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="p-3 bg-light rounded border h-100">
                    <span class="text-muted d-block fs-11 fw-bold text-uppercase mb-1" style="letter-spacing:0.5px;">To Warehouse</span>
                    <span class="fw-bold text-dark fs-14 d-flex align-items-center">
                        <i class="feather-arrow-down-left text-success me-1 fs-14"></i>{{ $transfer->toWarehouse->name ?? 'N/A' }}
                    </span>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="p-3 bg-light rounded border h-100">
                    <span class="text-muted d-block fs-11 fw-bold text-uppercase mb-1" style="letter-spacing:0.5px;">Transfer Date</span>
                    <span class="fw-bold text-dark fs-14 d-flex align-items-center">
                        <i class="feather-calendar text-muted me-1 fs-13"></i>{{ $transfer->transfer_date ? \Carbon\Carbon::parse($transfer->transfer_date)->format('d M Y') : 'N/A' }}
                    </span>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="p-3 bg-light rounded border h-100">
                    <span class="text-muted d-block fs-11 fw-bold text-uppercase mb-1" style="letter-spacing:0.5px;">Current Status</span>
                    <div class="mt-1">
                        <x-ui.status-badge :status="$transfer->status" size="sm" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Transferred Items Section -->
        <div class="mb-4">
            <h5 class="fw-bold text-dark mb-3">
                <i class="feather-layers text-primary me-2"></i>Transferred Items
            </h5>
            
            <div class="table-responsive">
                <x-ui.odoo-form-ui type="table" id="transferredItemsTable">
                    <thead class="table-light bg-light">
                        <tr>
                            <th style="width: 5%;" class="text-center">#</th>
                            <th style="width: 30%;">Product Details</th>
                            <th style="width: 15%;">SKU</th>
                            <th style="width: 15%;" class="text-end">Transfer Qty</th>
                            <th style="width: 15%;" class="text-end">Received Qty</th>
                            <th style="width: 20%;">Tracked Serial Numbers</th>
                        </tr>
                    </thead>
                    <tbody class="text-dark">
                        @foreach($transfer->items as $index => $item)
                            <tr>
                                <td class="text-center fw-bold text-muted">{{ $index + 1 }}</td>
                                <td>
                                    @if($item->product)
                                        <a href="{{ route('inventory.products.show', $item->product_id) }}" class="fw-bold text-dark text-decoration-none">
                                            {{ $item->product->name }}
                                        </a>
                                    @else
                                        <strong class="text-dark">N/A</strong>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-light text-muted font-monospace border fs-11">
                                        {{ $item->product->sku ?? '—' }}
                                    </span>
                                </td>
                                <td class="text-end font-monospace fw-bold fs-13 text-dark">
                                    {{ number_format($item->quantity, 2) }}
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
                                    @if(!empty($item->serial_numbers))
                                        <div class="d-flex flex-wrap gap-1">
                                            @foreach($item->serial_numbers as $sn)
                                                <span class="badge bg-soft-primary text-primary font-monospace border border-primary-subtle fs-11">
                                                    <i class="feather-hash me-0.5"></i>{{ $sn }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @else
                                        <span class="text-muted fs-12">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.odoo-form-ui>
            </div>
        </div>

        <!-- Notes Section -->
        @if($transfer->notes)
            <div class="p-3 bg-light rounded border">
                <h6 class="fw-bold text-dark mb-1">
                    <i class="feather-file-text text-primary me-1"></i>Notes & Remarks:
                </h6>
                <p class="mb-0 text-muted fs-13">{{ $transfer->notes }}</p>
            </div>
        @endif

    </x-ui.odoo-form-ui>
</div>
@endsection
