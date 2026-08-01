@extends('layouts.duralux')

@section('title', 'Adjustment Details #' . $adjustment->adjustment_number . ' | SaaS ERP')
@section('page-title', 'Stock Adjustment: ' . $adjustment->adjustment_number)
@section('breadcrumb', 'Inventory / Stock Adjustments / Details')

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
                <h4 class="fw-bold mb-1">{{ $adjustment->adjustment_number }}</h4>
                <span class="text-muted">Created on {{ \Carbon\Carbon::parse($adjustment->created_at)->format('d M Y, h:i A') }}</span>
            </div>
            <div class="d-flex gap-2">
                @if($adjustment->status === 'Draft')
                    <form action="{{ route('inventory.adjustments.approve', $adjustment) }}" method="POST" onsubmit="return confirm('Approve adjustment and update stock levels?')">
                        @csrf
                        <button type="submit" class="btn btn-success"><i class="feather-check-circle me-1"></i> Approve Adjustment</button>
                    </form>
                    <form action="{{ route('inventory.adjustments.cancel', $adjustment) }}" method="POST" onsubmit="return confirm('Cancel this adjustment?')">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger">Cancel</button>
                    </form>
                @endif
                <a href="{{ route('inventory.adjustments.index') }}" class="btn btn-light">Back to List</a>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <span class="text-muted d-block fs-12">Warehouse</span>
                <strong class="fs-15">{{ $adjustment->warehouse->name ?? 'N/A' }}</strong>
            </div>
            <div class="col-md-3">
                <span class="text-muted d-block fs-12">Reason</span>
                <span class="badge bg-light text-dark border fs-13">{{ $adjustment->reason }}</span>
            </div>
            <div class="col-md-3">
                <span class="text-muted d-block fs-12">Status</span>
                <span class="badge {{ $adjustment->status === 'Approved' ? 'bg-success' : 'bg-secondary' }} fs-13">{{ $adjustment->status }}</span>
            </div>
            <div class="col-md-3">
                <span class="text-muted d-block fs-12">Adjustment Date</span>
                <strong>{{ \Carbon\Carbon::parse($adjustment->adjustment_date)->format('d M Y') }}</strong>
            </div>
        </div>

        <h5 class="fw-bold mb-3">Adjusted Line Items</h5>
        <div class="table-responsive mb-4">
            <table class="table table-bordered align-middle">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Product Name</th>
                        <th>Type</th>
                        <th>Quantity</th>
                        <th>Unit Cost</th>
                        <th>Total Amount</th>
                        <th>Serial Numbers</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($adjustment->items as $index => $item)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td><strong>{{ $item->product->name ?? 'N/A' }}</strong></td>
                            <td>
                                <span class="badge {{ $item->type === 'Addition' ? 'bg-success' : 'bg-danger' }}">
                                    {{ $item->type }}
                                </span>
                            </td>
                            <td>{{ number_format($item->quantity, 2) }}</td>
                            <td>₹{{ number_format($item->unit_cost, 2) }}</td>
                            <td>₹{{ number_format($item->total_amount, 2) }}</td>
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

        @if($adjustment->notes)
            <div class="bg-light p-3 rounded">
                <h6 class="fw-bold mb-1">Notes:</h6>
                <p class="mb-0 text-muted">{{ $adjustment->notes }}</p>
            </div>
        @endif
    </div>
</div>
@endsection
