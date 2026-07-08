@extends('layouts.duralux')

@section('title', 'Production Orders | SaaS ERP')
@section('page-title', 'Production Orders (Manufacturing Execution)')
@section('breadcrumb', 'Production Orders')

@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendors/css/select2-theme.min.css') }}">
@endpush

@push('scripts')
    <script src="{{ asset('assets/vendors/js/select2.min.js') }}"></script>
    <script src="{{ asset('assets/vendors/js/select2-active.min.js') }}"></script>
@endpush

@section('page-actions')
    <a href="{{ route('production.orders.create') }}" class="btn btn-primary">
        <i class="feather-plus me-2"></i>Create Direct Order
    </a>
@endsection

@section('content')
<div class="erp-single-panel">
    {{-- Alerts --}}
    @if (session('success'))
        <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
    @endif

    @if (session('error'))
        <x-ui.toast :auto="true" type="error" title="{{ session('error') }}" />
    @endif

    {{-- KPI Status Summary --}}
    <div class="row g-3 mb-4">
        <div class="col">
            <div class="bg-light border rounded p-3 text-center">
                <span class="text-muted fs-11 text-uppercase fw-bold">Draft</span>
                <h4 class="text-dark fw-bold mt-1 mb-0">{{ $statusCounts['draft'] ?? 0 }}</h4>
            </div>
        </div>
        <div class="col">
            <div class="bg-soft-primary border rounded p-3 text-center">
                <span class="text-primary fs-11 text-uppercase fw-bold">Released</span>
                <h4 class="text-primary fw-bold mt-1 mb-0">{{ $statusCounts['released'] ?? 0 }}</h4>
            </div>
        </div>
        <div class="col">
            <div class="bg-soft-info border rounded p-3 text-center">
                <span class="text-info fs-11 text-uppercase fw-bold">In Progress</span>
                <h4 class="text-info fw-bold mt-1 mb-0">{{ $statusCounts['in_progress'] ?? 0 }}</h4>
            </div>
        </div>
        <div class="col">
            <div class="bg-soft-success border rounded p-3 text-center">
                <span class="text-success fs-11 text-uppercase fw-bold">Completed</span>
                <h4 class="text-success fw-bold mt-1 mb-0">{{ $statusCounts['completed'] ?? 0 }}</h4>
            </div>
        </div>
        <div class="col">
            <div class="bg-light border rounded p-3 text-center">
                <span class="text-dark fs-11 text-uppercase fw-bold">Closed</span>
                <h4 class="text-dark fw-bold mt-1 mb-0">{{ $statusCounts['closed'] ?? 0 }}</h4>
            </div>
        </div>
    </div>

    {{-- Inline Filters (BOM-style) --}}
    <form method="GET" action="{{ route('production.orders.index') }}" class="mb-4">
        <div class="row g-3">
            <div class="col-md-3 erp-form-input-col">
                <input type="text" name="search" class="form-control"
                       placeholder="Order number or product SKU..." value="{{ request('search') }}">
            </div>
            <div class="col-md-2 erp-form-input-col">
                <select name="status" class="form-select" data-select2-selector="default">
                    <option value="">All Statuses</option>
                    <option value="draft"       {{ request('status') === 'draft'       ? 'selected' : '' }}>Draft</option>
                    <option value="released"    {{ request('status') === 'released'    ? 'selected' : '' }}>Released</option>
                    <option value="in_progress" {{ request('status') === 'in_progress' ? 'selected' : '' }}>In Progress</option>
                    <option value="completed"   {{ request('status') === 'completed'   ? 'selected' : '' }}>Completed</option>
                    <option value="closed"      {{ request('status') === 'closed'      ? 'selected' : '' }}>Closed</option>
                    <option value="cancelled"   {{ request('status') === 'cancelled'   ? 'selected' : '' }}>Cancelled</option>
                </select>
            </div>
            <div class="col-md-2 erp-form-input-col">
                <input type="date" name="start_date" class="form-control" value="{{ request('start_date') }}" placeholder="From Date">
            </div>
            <div class="col-md-2 erp-form-input-col">
                <input type="date" name="end_date" class="form-control" value="{{ request('end_date') }}" placeholder="To Date">
            </div>
            <div class="col-md-3 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-secondary h-40 px-4">
                    <i class="feather-filter me-2"></i>Filter
                </button>
                <a href="{{ route('production.orders.index') }}" class="btn btn-outline-secondary h-40 px-4">Reset</a>
            </div>
        </div>
    </form>

    {{-- Orders Table --}}
    <div class="table-responsive">
        <table class="erp-thin-table">
            <thead>
                <tr>
                    <th style="width:3%" class="text-center">
                        <input type="checkbox" class="form-check-input">
                    </th>
                    <th style="width:14%">Order Number</th>
                    <th style="width:22%">Finished Product</th>
                    <th style="width:12%" class="text-center">Ordered Qty</th>
                    <th style="width:12%" class="text-center">Produced</th>
                    <th style="width:18%">Scheduled Dates</th>
                    <th style="width:10%">Status</th>
                    <th style="width:9%" class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($orders as $order)
                    <tr>
                        <td class="text-center">
                            <input type="checkbox" class="form-check-input">
                        </td>
                        <td>
                            <a href="{{ route('production.orders.show', $order->id) }}" class="fw-bold text-primary hover-primary">
                                {{ $order->order_number }}
                            </a>
                            @if($order->plan)
                                <div class="fs-11 text-muted">Plan: {{ $order->plan->plan_number }}</div>
                            @endif
                        </td>
                        <td>
                            <div class="d-flex flex-column">
                                <span class="fw-semibold text-dark">{{ $order->product->name }}</span>
                                <small class="text-muted font-monospace fs-10">{{ $order->product->sku }}</small>
                            </div>
                        </td>
                        <td class="text-center fw-semibold text-dark">{{ number_format($order->quantity_ordered, 2) }}</td>
                        <td class="text-center fw-bold text-success">{{ number_format($order->quantity_produced, 2) }}</td>
                        <td>
                            <div class="fs-12 text-dark">{{ $order->start_date->format('Y-m-d') }} → {{ $order->end_date->format('Y-m-d') }}</div>
                            @if($order->actual_start_date)
                                <div class="fs-11 text-info">Started: {{ $order->actual_start_date->format('m-d H:i') }}</div>
                            @endif
                        </td>
                        <td>
                            @if($order->isDraft())
                                <span class="erp-badge-draft">Draft</span>
                            @elseif($order->isReleased())
                                <span class="erp-badge-pending">Released</span>
                            @elseif($order->isInProgress())
                                <span class="badge bg-soft-info text-info">In Progress</span>
                            @elseif($order->isCompleted())
                                <span class="erp-badge-active">Completed</span>
                            @elseif($order->isClosed())
                                <span class="badge bg-soft-dark text-dark">Closed</span>
                            @elseif($order->isCancelled())
                                <span class="badge bg-soft-danger text-danger">Cancelled</span>
                            @else
                                <span class="erp-badge-draft text-uppercase">{{ $order->status }}</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <x-ui.icon-btn href="{{ route('production.orders.show', $order->id) }}"
                                           variant="soft-info" title="View Order" icon="feather-eye" />

                            @if($order->isDraft())
                                <x-ui.icon-btn href="{{ route('production.orders.edit', $order->id) }}"
                                               variant="soft-primary" title="Edit Draft" icon="feather-edit" />
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center py-5 text-muted">
                            <i class="feather-package fs-24 d-block mb-2"></i>
                            No Production Orders found. <a href="{{ route('production.orders.create') }}" class="text-primary">Create the first one.</a>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- Pagination --}}
    @if($orders->hasPages())
        <div class="mt-3">
            {{ $orders->links() }}
        </div>
    @endif
</div>
@endsection
