@extends('layouts.duralux')

@section('title', 'Edit Production Order | SaaS ERP')
@section('page-title', 'Edit Production Order')
@section('breadcrumb', 'Edit Order')

@section('page-actions')
    <a href="{{ route('production.orders.show', $order->id) }}" class="btn btn-secondary">
        <i class="feather-arrow-left me-2"></i>Back to Order
    </a>
@endsection

@section('content')
    <div class="row">
        <div class="col-xl-8 col-lg-10 mx-auto">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="card-title mb-0 text-dark">Modify Order #{{ $order->order_number }}</h5>
                    <p class="text-muted mb-0 fs-12">Edits are only allowed while the order remains in draft status.</p>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('production.orders.update', $order->id) }}">
                        @csrf
                        @method('PUT')

                        <!-- Product info (locked) -->
                        <div class="mb-4 bg-light p-3 rounded border">
                            <label class="form-label fw-bold text-muted fs-11 text-uppercase mb-1">Product</label>
                            <div class="text-dark fw-bold">{{ $order->product->name }}</div>
                            <div class="text-muted fs-12">SKU: {{ $order->product->sku }}</div>
                        </div>

                        <!-- Quantity Ordered -->
                        <div class="mb-4">
                            <label class="form-label fw-bold text-dark fs-13">Quantity to Manufacture</label>
                            <div class="input-group">
                                <input type="number" step="0.0001" name="quantity_ordered" class="form-control @error('quantity_ordered') is-invalid @enderror" value="{{ old('quantity_ordered', $order->quantity_ordered) }}" required>
                                <span class="input-group-text bg-light text-muted">units</span>
                            </div>
                            @error('quantity_ordered')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Dates Row -->
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-dark fs-13">Scheduled Start Date</label>
                                <input type="date" name="start_date" class="form-control @error('start_date') is-invalid @enderror" value="{{ old('start_date', $order->start_date->format('Y-m-d')) }}" required>
                                @error('start_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-dark fs-13">Scheduled End Date</label>
                                <input type="date" name="end_date" class="form-control @error('end_date') is-invalid @enderror" value="{{ old('end_date', $order->end_date->format('Y-m-d')) }}" required>
                                @error('end_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <!-- Description Notes -->
                        <div class="mb-4">
                            <label class="form-label fw-bold text-dark fs-13">Description & Shop Floor Remarks</label>
                            <textarea name="description" rows="3" class="form-control @error('description') is-invalid @enderror" placeholder="Enter special manufacturing instructions...">{{ old('description', $order->description) }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="text-end pt-3 border-top">
                            <a href="{{ route('production.orders.show', $order->id) }}" class="btn btn-secondary px-4 me-2">Cancel</a>
                            <button type="submit" class="btn btn-primary px-4">
                                <i class="feather-check-circle me-2"></i>Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
