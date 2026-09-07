@extends('layouts.duralux')

@section('title', 'Routing Variance & Performance | SaaS ERP')
@section('page-title', 'Routing Variance & Performance')
@section('breadcrumb', 'Performance & Variance')

@section('content')
<div class="erp-single-panel">
    @if (session('success'))
        <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
    @endif
    @if (session('warning'))
        <x-ui.toast :auto="true" type="warning" title="{{ session('warning') }}" />
    @endif

    <!-- Recommendations Section -->
    <div class="d-flex align-items-center mb-3">
        <h5 class="fw-bold text-dark mb-0"><i class="feather-activity text-primary me-2"></i>Evidence-Based Routing Recommendations</h5>
        <span class="badge bg-soft-primary text-primary ms-2">{{ count($recommendations) }} Actionable</span>
    </div>

    <x-ui.odoo-form-ui type="table">
        <thead>
            <tr>
                <th style="width: 25%">Recommendation</th>
                <th style="width: 20%">Product / Operation</th>
                <th style="width: 20%">Evidence</th>
                <th style="width: 12%">Sample Size</th>
                <th style="width: 10%">Avg Variance</th>
                <th style="width: 13%" class="text-end">Action</th>
            </tr>
        </thead>
        <tbody>
            @forelse($recommendations as $rec)
                <tr>
                    <td>
                        <span class="fw-bold d-block text-dark">{{ $rec['title'] }}</span>
                        <span class="text-muted fs-12">{{ $rec['description'] }}</span>
                    </td>
                    <td>
                        <span class="badge bg-soft-secondary text-secondary mb-1">{{ $rec['product_name'] }}</span>
                        <div class="small text-dark font-monospace">{{ $rec['operation_name'] }}</div>
                    </td>
                    <td>
                        <span class="small text-danger font-monospace">{{ $rec['evidence'] }}</span>
                    </td>
                    <td>{{ $rec['sample_size'] }} orders</td>
                    <td>
                        <span class="badge bg-soft-danger text-danger">+{{ $rec['average_variance_percentage'] }}%</span>
                    </td>
                    <td class="text-end">
                        <a href="{{ route('production.ecos.create') }}?product_id={{ $rec['product_id'] ?? '' }}" class="btn btn-sm btn-outline-primary">
                            <i class="feather-file-text me-1"></i> Create Draft ECO
                        </a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center py-4 text-muted">
                        <i class="feather-check-circle fs-24 mb-2 text-success d-block"></i>
                        No recurring routing variances detected across recent production orders.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </x-ui.odoo-form-ui>

    <!-- Recurring Operation Variances -->
    <div class="d-flex align-items-center mt-5 mb-3">
        <h5 class="fw-bold text-dark mb-0"><i class="feather-trending-up text-primary me-2"></i>Recurring Operation Performance Summary</h5>
    </div>

    <x-ui.odoo-form-ui type="table">
        <thead>
            <tr>
                <th>Product</th>
                <th>Operation</th>
                <th>Planned Avg</th>
                <th>Actual Avg</th>
                <th>Variance</th>
                <th>Scrap Count</th>
                <th>Alt Machine Count</th>
            </tr>
        </thead>
        <tbody>
            @forelse($recurringVariances as $var)
                <tr>
                    <td class="fw-bold text-dark">{{ $var['product_name'] }}</td>
                    <td>{{ $var['operation_name'] }}</td>
                    <td>{{ $var['average_planned_time'] }} min</td>
                    <td>{{ $var['average_actual_time'] }} min</td>
                    <td>
                        <span class="fw-bold {{ $var['average_time_variance'] > 0 ? 'text-danger' : 'text-success' }}">
                            {{ $var['average_time_variance'] > 0 ? '+' : '' }}{{ $var['average_time_variance'] }} min ({{ $var['average_variance_percentage'] }}%)
                        </span>
                    </td>
                    <td>{{ $var['scrap_occurrences'] }}</td>
                    <td>{{ $var['alternate_machine_occurrences'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center py-4 text-muted">
                        No historical operation variance data available.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </x-ui.odoo-form-ui>
</div>
@endsection
