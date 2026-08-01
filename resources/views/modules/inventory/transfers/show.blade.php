@extends('layouts.duralux')

@section('title', 'Transfer Details #' . $transfer->transfer_number . ' | SaaS ERP')
@section('page-title', 'Stock Transfer: ' . $transfer->transfer_number)
@section('breadcrumb', 'Inventory / Stock Transfers / Details')

@section('content')
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show">
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
            <div>
                <h4 class="fw-bold mb-1">{{ $transfer->transfer_number }}</h4>
                <span class="text-muted">Created on {{ \Carbon\Carbon::parse($transfer->created_at)->format('d M Y, h:i A') }}</span>
            </div>
            <div class="d-flex gap-2">
                @if($transfer->status === 'Draft' || $transfer->status === 'Pending')
                    <form action="{{ route('inventory.transfers.dispatch', $transfer) }}" method="POST" onsubmit="return confirm('Dispatch items and mark as In-Transit?')">
                        @csrf
                        <button type="submit" class="btn btn-warning text-dark"><i class="feather-send me-1"></i> Dispatch Transfer</button>
                    </form>
                    <form action="{{ route('inventory.transfers.cancel', $transfer) }}" method="POST" onsubmit="return confirm('Cancel this transfer?')">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger">Cancel</button>
                    </form>
                @elseif($transfer->status === 'In-Transit')
                    <form action="{{ route('inventory.transfers.receive', $transfer) }}" method="POST" onsubmit="return confirm('Receive items at target warehouse?')">
                        @csrf
                        <button type="submit" class="btn btn-success"><i class="feather-check-circle me-1"></i> Receive Stock</button>
                    </form>
                @endif
                <a href="{{ route('inventory.transfers.index') }}" class="btn btn-light">Back to List</a>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <span class="text-muted d-block fs-12">From Warehouse</span>
                <strong class="fs-15">{{ $transfer->fromWarehouse->name ?? 'N/A' }}</strong>
            </div>
            <div class="col-md-3">
                <span class="text-muted d-block fs-12">To Warehouse</span>
                <strong class="fs-15">{{ $transfer->toWarehouse->name ?? 'N/A' }}</strong>
            </div>
            <div class="col-md-3">
                <span class="text-muted d-block fs-12">Status</span>
                <span class="badge bg-primary fs-13">{{ $transfer->status }}</span>
            </div>
            <div class="col-md-3">
                <span class="text-muted d-block fs-12">Transfer Date</span>
                <strong>{{ $transfer->transfer_date ? \Carbon\Carbon::parse($transfer->transfer_date)->format('d M Y') : 'N/A' }}</strong>
            </div>
        </div>

        <h5 class="fw-bold mb-3">Transferred Items</h5>
        <div class="table-responsive mb-4">
            <table class="table table-bordered align-middle">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Product Name</th>
                        <th>SKU</th>
                        <th>Transfer Qty</th>
                        <th>Received Qty</th>
                        <th>Serial Numbers</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($transfer->items as $index => $item)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td><strong>{{ $item->product->name ?? 'N/A' }}</strong></td>
                            <td>{{ $item->product->sku ?? '-' }}</td>
                            <td>{{ number_format($item->quantity, 2) }}</td>
                            <td>{{ number_format($item->received_quantity, 2) }}</td>
                            <td>
                                @if(!empty($item->serial_numbers))
                                    @foreach($item->serial_numbers as $sn)
                                        <span class="badge bg-light text-dark border">{{ $sn }}</span>
                                    @endforeach
                                @else
                                    <span class="text-muted">-</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($transfer->notes)
            <div class="bg-light p-3 rounded">
                <h6 class="fw-bold mb-1">Notes:</h6>
                <p class="mb-0 text-muted">{{ $transfer->notes }}</p>
            </div>
        @endif
    </div>
</div>
@endsection
