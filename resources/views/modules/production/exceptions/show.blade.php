@extends('layouts.duralux')

@section('title', 'Risk Analysis: ' . $order->order_number . ' | SaaS ERP')
@section('page-title', 'Risk Analysis: ' . $order->order_number)
@section('breadcrumb', 'Planning Exceptions')

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <a href="{{ route('production.planning-exceptions.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="feather-arrow-left me-1"></i> Back to Planning Exceptions
        </a>
    </div>
@endsection

@section('content')
<div class="erp-single-panel bg-white">
    <x-ui.odoo-form-ui type="sheet">
        <!-- Header with Close Button -->
        <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
            <div>
                <h4 class="fw-bold text-dark mb-1">Risk Analysis — {{ $order->order_number }}</h4>
                <p class="text-muted fs-13 mb-0">Detailed 9-vector exception analysis and planner recommendations</p>
            </div>
            <a href="{{ route('production.planning-exceptions.index') }}" class="text-muted hover-danger fs-18">
                <i class="feather-x"></i>
            </a>
        </div>

        <!-- Order Overview Header -->
        <div class="row g-4 mb-4 fs-13 text-dark">
            <div class="col-md-3">
                <span class="text-uppercase fs-11 fw-bold text-muted d-block mb-1">Overall Order Risk</span>
                @if($analysis['overall_risk'] === 'CRITICAL')
                    <span class="badge bg-soft-danger text-danger fs-13">CRITICAL</span>
                @elseif($analysis['overall_risk'] === 'HIGH')
                    <span class="badge bg-soft-warning text-warning fs-13">HIGH</span>
                @elseif($analysis['overall_risk'] === 'MEDIUM')
                    <span class="badge bg-soft-info text-info fs-13">MEDIUM</span>
                @elseif($analysis['overall_risk'] === 'LOW')
                    <span class="badge bg-soft-secondary text-secondary fs-13">LOW</span>
                @else
                    <span class="badge bg-soft-success text-success fs-13">ON TRACK</span>
                @endif
            </div>
            <div class="col-md-3">
                <span class="text-uppercase fs-11 fw-bold text-muted d-block mb-1">Product</span>
                <span class="fw-bold text-dark">{{ $analysis['product_name'] }}</span>
            </div>
            <div class="col-md-3">
                <span class="text-uppercase fs-11 fw-bold text-muted d-block mb-1">Ordered / Produced Qty</span>
                <span class="fw-bold text-dark">{{ $analysis['quantity_ordered'] }} / {{ $analysis['quantity_produced'] }}</span>
            </div>
            <div class="col-md-3">
                <span class="text-uppercase fs-11 fw-bold text-muted d-block mb-1">Target Due Date</span>
                <span class="fw-bold text-danger">{{ $analysis['due_date'] ?? 'N/A' }}</span>
            </div>
        </div>

        <!-- Active Exceptions Table -->
        <h5 class="fw-bold text-dark mt-4 mb-3"><i class="feather-alert-triangle text-warning me-2"></i>Active Planning Exceptions ({{ $analysis['exceptions_count'] }})</h5>
        
        @if($analysis['exceptions_count'] === 0)
            <div class="text-center py-4 text-muted">
                <i class="feather-check-circle fs-24 mb-2 text-success d-block"></i>
                <p class="mb-0">Zero exceptions detected for this Production Order across all 9 risk vectors.</p>
            </div>
        @else
            <x-ui.odoo-form-ui type="table">
                <thead>
                    <tr>
                        <th style="width: 20%">Vector / Type</th>
                        <th style="width: 12%">Severity</th>
                        <th style="width: 35%">Reason & Evidence</th>
                        <th style="width: 15%">Operation</th>
                        <th style="width: 18%">Recommended Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($analysis['exceptions'] as $ex)
                        <tr>
                            <td>
                                <span class="fw-bold d-block text-dark">{{ $ex['risk_driver'] }}</span>
                                <span class="badge bg-light text-muted border font-monospace small">{{ $ex['type'] }}</span>
                            </td>
                            <td>
                                @if($ex['severity'] === 'CRITICAL')
                                    <span class="badge bg-soft-danger text-danger">CRITICAL</span>
                                @elseif($ex['severity'] === 'HIGH')
                                    <span class="badge bg-soft-warning text-warning">HIGH</span>
                                @elseif($ex['severity'] === 'MEDIUM')
                                    <span class="badge bg-soft-info text-info">MEDIUM</span>
                                @else
                                    <span class="badge bg-soft-secondary text-secondary">LOW</span>
                                @endif
                            </td>
                            <td>
                                <span class="d-block text-dark fw-semibold mb-1">{{ $ex['reason'] }}</span>
                                <span class="small text-muted font-monospace">{{ $ex['evidence'] }}</span>
                            </td>
                            <td>{{ $ex['operation_name'] ?? 'Order Level' }}</td>
                            <td>
                                <span class="badge bg-soft-primary text-primary">{{ $ex['recommended_action'] }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-ui.odoo-form-ui>
        @endif
    </x-ui.odoo-form-ui>
</div>
@endsection
