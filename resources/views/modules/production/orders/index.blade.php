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

    {{-- Toolbar: Title + Sort + Filter --}}
    @php
        $sortBy = request('sort_by', 'id');
        $sortOrder = request('sort_order', 'desc');
    @endphp
    <div class="d-flex align-items-center mb-3">
        <h5 class="fw-bold text-dark mb-0">Production Orders</h5>
        <div class="d-flex gap-2 ms-auto">
            {{-- Sort Dropdown --}}
            <x-ui.sort-dropdown label="Sort">
                <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'id', 'sort_order' => 'desc']) }}"
                   class="dropdown-item {{ $sortBy === 'id' && $sortOrder === 'desc' ? 'active' : '' }}">
                    <span>Newest First</span>
                </a>
                <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'id', 'sort_order' => 'asc']) }}"
                   class="dropdown-item {{ $sortBy === 'id' && $sortOrder === 'asc' ? 'active' : '' }}">
                    <span>Oldest First</span>
                </a>
                <div class="dropdown-divider"></div>
                <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'order_number', 'sort_order' => 'asc']) }}"
                   class="dropdown-item {{ $sortBy === 'order_number' && $sortOrder === 'asc' ? 'active' : '' }}">
                    <span>Order Number (Asc)</span>
                </a>
                <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'order_number', 'sort_order' => 'desc']) }}"
                   class="dropdown-item {{ $sortBy === 'order_number' && $sortOrder === 'desc' ? 'active' : '' }}">
                    <span>Order Number (Desc)</span>
                </a>
                <div class="dropdown-divider"></div>
                <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'end_date', 'sort_order' => 'asc']) }}"
                   class="dropdown-item {{ $sortBy === 'end_date' && $sortOrder === 'asc' ? 'active' : '' }}">
                    <span>Due Date (Earliest)</span>
                </a>
                <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'end_date', 'sort_order' => 'desc']) }}"
                   class="dropdown-item {{ $sortBy === 'end_date' && $sortOrder === 'desc' ? 'active' : '' }}">
                    <span>Due Date (Latest)</span>
                </a>
            </x-ui.sort-dropdown>

            {{-- Filter Overlay --}}
            <form method="GET" action="{{ route('production.orders.index') }}" class="d-inline">
                <x-ui.filter label="Filter" offset="0, 5">
                    <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Filter Options</h6>

                    <div class="mb-3">
                        <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Search Keywords</label>
                        <x-ui.odoo-form-ui type="input" name="search" placeholder="Order number or product SKU..." value="{{ request('search') }}" />
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Status</label>
                        <x-ui.odoo-form-ui type="select" name="status">
                            <option value="">All Statuses</option>
                            <option value="draft" {{ request('status') === 'draft' ? 'selected' : '' }}>Draft</option>
                            <option value="released" {{ request('status') === 'released' ? 'selected' : '' }}>Released</option>
                            <option value="in_progress" {{ request('status') === 'in_progress' ? 'selected' : '' }}>In Progress</option>
                            <option value="completed" {{ request('status') === 'completed' ? 'selected' : '' }}>Completed</option>
                            <option value="closed" {{ request('status') === 'closed' ? 'selected' : '' }}>Closed</option>
                            <option value="cancelled" {{ request('status') === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                        </x-ui.odoo-form-ui>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Date Range</label>
                        <div class="d-flex gap-2">
                            <x-ui.odoo-form-ui type="input" name="start_date" value="{{ request('start_date') }}" />
                            <x-ui.odoo-form-ui type="input" name="end_date" value="{{ request('end_date') }}" />
                        </div>
                    </div>

                    <div class="d-flex gap-2 justify-content-end mt-4">
                        <a href="{{ route('production.orders.index') }}" class="btn btn-sm btn-light border">Reset</a>
                        <button type="submit" class="btn btn-sm btn-primary">Apply Filters</button>
                    </div>
                </x-ui.filter>
            </form>
        </div>
    </div>

    {{-- Orders Table --}}
    <div class="table-responsive">
        <x-ui.odoo-form-ui type="table">
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
                            <div class="fs-12 text-dark">{{ $order->start_date->format('Y-m-d') }} &rarr; {{ $order->end_date->format('Y-m-d') }}</div>
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
                            <x-ui.action-dropdown :viewUrl="route('production.orders.show', $order->id)">
                                @if($order->isDraft())
                                    <li>
                                        <a href="{{ route('production.orders.edit', $order->id) }}" class="dropdown-item">
                                            <i class="feather-edit me-2 text-muted fs-12"></i>Edit Draft
                                        </a>
                                    </li>
                                @endif
                            </x-ui.action-dropdown>
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
        </x-ui.odoo-form-ui>
    </div>

    {{-- Pagination --}}
    @if($orders->hasPages())
        <div class="mt-3">
            {{ $orders->links() }}
        </div>
    @endif
</div>
@endsection
