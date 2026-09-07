@extends('layouts.duralux')

@section('title', 'Planning Exceptions & At-Risk Orders | SaaS ERP')
@section('page-title', 'Planning Exceptions & At-Risk Production Orders')
@section('breadcrumb', 'Planning Exceptions')

@section('content')
<div class="erp-single-panel">
    @if (session('success'))
        <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
    @endif
    @if (session('warning'))
        <x-ui.toast :auto="true" type="warning" title="{{ session('warning') }}" />
    @endif

    <!-- Summary KPI Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-2">
            <div class="card border-0 shadow-sm text-center py-3 bg-white">
                <span class="text-uppercase fs-11 fw-bold text-muted">Total Orders</span>
                <h3 class="fw-bold mb-0 text-dark">{{ $summary['total_orders'] }}</h3>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-0 shadow-sm text-center py-3 bg-soft-danger text-danger">
                <span class="text-uppercase fs-11 fw-bold">Critical</span>
                <h3 class="fw-bold mb-0">{{ $summary['critical'] }}</h3>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-0 shadow-sm text-center py-3 bg-soft-warning text-warning">
                <span class="text-uppercase fs-11 fw-bold">High Risk</span>
                <h3 class="fw-bold mb-0">{{ $summary['high'] }}</h3>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-0 shadow-sm text-center py-3 bg-soft-info text-info">
                <span class="text-uppercase fs-11 fw-bold">Medium Risk</span>
                <h3 class="fw-bold mb-0">{{ $summary['medium'] }}</h3>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-0 shadow-sm text-center py-3 bg-soft-secondary text-secondary">
                <span class="text-uppercase fs-11 fw-bold">Low Risk</span>
                <h3 class="fw-bold mb-0">{{ $summary['low'] }}</h3>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card border-0 shadow-sm text-center py-3 bg-soft-success text-success">
                <span class="text-uppercase fs-11 fw-bold">On Track</span>
                <h3 class="fw-bold mb-0">{{ $summary['on_track'] }}</h3>
            </div>
        </div>
    </div>

    <!-- Header & Filter Toolbar -->
    <div class="d-flex align-items-center mb-3">
        <h5 class="fw-bold text-dark mb-0"><i class="feather-alert-triangle text-warning me-2"></i>At-Risk Production Orders</h5>
        <div class="d-flex gap-2 ms-auto">
            <form method="GET" action="{{ route('production.planning-exceptions.index') }}" class="d-inline">
                <x-ui.filter label="Filter" offset="0, 5">
                    <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Filter Risk Exception Orders</h6>

                    <div class="mb-3">
                        <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Severity Level</label>
                        <x-ui.odoo-form-ui type="select" name="severity">
                            <option value="">All Severities</option>
                            <option value="CRITICAL" {{ (request('severity') ?? '') === 'CRITICAL' ? 'selected' : '' }}>CRITICAL</option>
                            <option value="HIGH" {{ (request('severity') ?? '') === 'HIGH' ? 'selected' : '' }}>HIGH</option>
                            <option value="MEDIUM" {{ (request('severity') ?? '') === 'MEDIUM' ? 'selected' : '' }}>MEDIUM</option>
                            <option value="LOW" {{ (request('severity') ?? '') === 'LOW' ? 'selected' : '' }}>LOW</option>
                        </x-ui.odoo-form-ui>
                    </div>

                    <div class="d-flex gap-2 justify-content-end mt-4">
                        <a href="{{ route('production.planning-exceptions.index') }}" class="btn btn-sm btn-light border">Reset</a>
                        <button type="submit" class="btn btn-sm btn-primary">Apply Filters</button>
                    </div>
                </x-ui.filter>
            </form>
        </div>
    </div>

    <!-- At-Risk Orders Table Component -->
    <x-ui.odoo-form-ui type="table">
        <thead>
            <tr>
                <th style="width: 15%">Order Number</th>
                <th style="width: 25%">Product</th>
                <th style="width: 10%" class="text-center">Quantity</th>
                <th style="width: 12%" class="text-center">Overall Risk</th>
                <th style="width: 28%">Primary Exception Vectors</th>
                <th style="width: 10%" class="text-end">Actions</th>
            </tr>
        </thead>
        <tbody>
            @forelse($orders as $row)
                @php
                    $ord = $row['order'] ?? $row;
                    $orderId = is_array($row) ? ($row['order_id'] ?? ($ord->id ?? 0)) : $ord->id;
                    $orderNumber = is_array($row) ? ($row['order_number'] ?? ($ord->order_number ?? '')) : $ord->order_number;
                    $productName = is_array($row) ? ($row['product_name'] ?? ($ord->product?->name ?? 'N/A')) : ($ord->product?->name ?? 'N/A');
                    $dueDate = is_array($row) ? ($row['due_date'] ?? ($ord->end_date ?? 'N/A')) : ($ord->end_date ?? 'N/A');
                    $qtyOrdered = is_array($row) ? ($row['quantity_ordered'] ?? ($ord->quantity_ordered ?? 0)) : $ord->quantity_ordered;
                    $level = strtoupper(is_array($row) ? ($row['overall_risk'] ?? 'ON_TRACK') : 'ON_TRACK');
                    $exceptionsList = is_array($row) ? ($row['exceptions'] ?? []) : [];
                @endphp
                <tr>
                    <td>
                        <a href="{{ route('production.planning-exceptions.show', $orderId) }}" class="fw-bold text-primary text-decoration-none">
                            {{ $orderNumber }}
                        </a>
                    </td>
                    <td>
                        <div class="fw-semibold text-dark">{{ $productName }}</div>
                        <small class="text-muted">Due: {{ $dueDate }}</small>
                    </td>
                    <td class="text-center fw-bold">{{ number_format($qtyOrdered, 2) }}</td>
                    <td class="text-center">
                        @if($level === 'CRITICAL')
                            <span class="badge bg-soft-danger text-danger">CRITICAL</span>
                        @elseif($level === 'HIGH')
                            <span class="badge bg-soft-warning text-warning">HIGH</span>
                        @elseif($level === 'MEDIUM')
                            <span class="badge bg-soft-info text-info">MEDIUM</span>
                        @elseif($level === 'LOW')
                            <span class="badge bg-soft-secondary text-secondary">LOW</span>
                        @else
                            <span class="badge bg-soft-success text-success">ON TRACK</span>
                        @endif
                    </td>
                    <td>
                        <div class="d-flex flex-wrap gap-1">
                            @forelse($exceptionsList as $ex)
                                <span class="badge bg-light text-dark border">
                                    {{ $ex['risk_driver'] ?? ($ex['type'] ?? 'Exception') }}: {{ $ex['severity'] ?? 'LOW' }}
                                </span>
                            @empty
                                <span class="text-muted small">No active exceptions</span>
                            @endforelse
                        </div>
                    </td>
                    <td class="text-end">
                        <x-ui.action-dropdown :viewUrl="route('production.planning-exceptions.show', $orderId)" />
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center py-4 text-muted">
                        <i class="feather-check-circle fs-24 mb-2 text-success d-block"></i>
                        All active production orders are on track with zero open planning exceptions.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </x-ui.odoo-form-ui>
</div>
@endsection
