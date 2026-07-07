@extends('layouts.duralux')

@section('title', 'Start Quality Inspection | SaaS ERP')
@section('page-title', 'Select Inspection Quality Plan')
@section('breadcrumb', 'New Checklist')

@section('content')
    <div class="erp-single-panel bg-white p-4 rounded shadow-sm" style="max-width: 600px;">
        <h5 class="fw-bold text-dark mb-4">Start Quality Inspection Checklist</h5>

        <form method="POST" action="{{ route('production.inspections.store') }}">
            @csrf
            
            <div class="mb-3">
                <label class="form-label fw-bold">Quality Plan Template</label>
                <select name="quality_plan_id" class="form-select" required>
                    <option value="">Select Plan</option>
                    @foreach($plans as $plan)
                        <option value="{{ $plan->id }}">{{ $plan->name }} (v{{ $plan->version }})</option>
                    @endforeach
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold">Inspection Stage</label>
                <select name="stage" class="form-select" required>
                    <option value="incoming">Incoming Inspection</option>
                    <option value="in_process">In Process Inspection</option>
                    <option value="final">Final Inspection</option>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold">Production Order ID (Optional)</label>
                <input type="number" name="production_order_id" class="form-control" placeholder="PO ID">
            </div>

            <button type="submit" class="btn btn-primary w-100">Initialize Checklist</button>
        </form>
    </div>
@endsection
