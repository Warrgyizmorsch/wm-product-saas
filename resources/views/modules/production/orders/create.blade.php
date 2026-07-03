@extends('layouts.duralux')

@section('title', 'Create Production Order | SaaS ERP')
@section('page-title', 'Create Direct Production Order')
@section('breadcrumb', 'Create Order')

@section('page-actions')
    <a href="{{ route('production.orders.index') }}" class="btn btn-secondary">
        <i class="feather-arrow-left me-2"></i>Back to List
    </a>
@endsection

@section('content')
    <div class="row">
        <div class="col-xl-8 col-lg-10 mx-auto">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="card-title mb-0 text-dark">Production Order Details</h5>
                    <p class="text-muted mb-0 fs-12">BOM version and routing operations will be frozen automatically upon order confirmation.</p>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('production.orders.store') }}">
                        @csrf

                        <!-- Product Selector -->
                        <div class="mb-4">
                            <label class="form-label fw-bold text-dark fs-13">Target Product</label>
                            <select name="product_id" id="product_id" class="form-select @error('product_id') is-invalid @enderror" required>
                                <option value="">Select Finished Good or Semi-Finished Product...</option>
                                @foreach($products as $product)
                                    <option value="{{ $product->id }}" {{ old('product_id') == $product->id ? 'selected' : '' }}>
                                        {{ $product->name }} (SKU: {{ $product->sku }})
                                    </option>
                                @endforeach
                            </select>
                            @error('product_id')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Quantity Ordered -->
                        <div class="mb-4">
                            <label class="form-label fw-bold text-dark fs-13">Quantity to Manufacture</label>
                            <div class="input-group">
                                <input type="number" step="0.0001" name="quantity_ordered" class="form-control @error('quantity_ordered') is-invalid @enderror" value="{{ old('quantity_ordered', '1.0000') }}" required>
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
                                <input type="date" name="start_date" class="form-control @error('start_date') is-invalid @enderror" value="{{ old('start_date', date('Y-m-d')) }}" required>
                                @error('start_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-dark fs-13">Scheduled End Date</label>
                                <input type="date" name="end_date" class="form-control @error('end_date') is-invalid @enderror" value="{{ old('end_date', date('Y-m-d', strtotime('+3 days'))) }}" required>
                                @error('end_date')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <!-- Description Notes -->
                        <div class="mb-4">
                            <label class="form-label fw-bold text-dark fs-13">Description & Shop Floor Remarks</label>
                            <textarea name="description" rows="3" class="form-control @error('description') is-invalid @enderror" placeholder="Enter special manufacturing instructions...">{{ old('description') }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="text-end pt-3 border-top">
                            <a href="{{ route('production.orders.index') }}" class="btn btn-secondary px-4 me-2">Cancel</a>
                            <button type="submit" class="btn btn-primary px-4">
                                <i class="feather-check-circle me-2"></i>Create Order
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
