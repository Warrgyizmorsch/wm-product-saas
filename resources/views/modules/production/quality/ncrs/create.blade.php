@extends('layouts.duralux')

@section('title', 'Log Non-Conformance Report | SaaS ERP')
@section('page-title', 'Log Quality Defect (NCR)')
@section('breadcrumb', 'New NCR')

@section('content')
    <div class="erp-single-panel bg-white p-4 rounded shadow-sm" style="max-width: 600px;">
        <h5 class="fw-bold text-dark mb-4">Log Quality Non-Conformance</h5>

        <form method="POST" action="{{ route('production.ncrs.store') }}">
            @csrf
            
            <div class="mb-3">
                <label class="form-label fw-bold">Defect Category</label>
                <select name="category" class="form-select" required>
                    <option value="material">Material Defect</option>
                    <option value="process">Process Defect</option>
                    <option value="machine">Machine Failure</option>
                    <option value="human_error">Operator Mistake</option>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold">Production Order (Optional)</label>
                <select name="production_order_id" class="form-select">
                    <option value="">Select Production Order</option>
                    @foreach($orders as $order)
                        <option value="{{ $order->id }}">Order #{{ $order->id }} (Product: {{ $order->product->name ?? '—' }})</option>
                    @endforeach
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold">Detailed Defect Description</label>
                <textarea name="description" class="form-control" rows="4" placeholder="Describe the non-conformance details..." required></textarea>
            </div>

            <button type="submit" class="btn btn-danger w-100">Record Defect Report (NCR)</button>
        </form>
    </div>
@endsection
