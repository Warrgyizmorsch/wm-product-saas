@extends('layouts.duralux')

@section('title', 'Sales Orders | SaaS ERP')
@section('page-title', 'Sales Orders')
@section('breadcrumb', 'Sales / Sales Orders')

@section('page-actions')
    <x-ui.button href="{{ route('sales.orders.create') }}" variant="primary" icon="feather-plus">
        Create Sales Order
    </x-ui.button>
@endsection

@section('content')
    @php
        $sortBy = request('sort_by', 'order_date');
        $sortOrder = request('sort_order', 'desc');
    @endphp

    <div class="erp-single-panel">
        @if (session('success'))
            <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
        @endif
        @if ($errors->any())
            <div class="alert alert-danger mb-3 alert-dismissible fade show fs-12 py-2" role="alert">
                <ul class="mb-0 ps-3 text-start">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close" style="padding: 0.75rem 1rem;"></button>
            </div>
        @endif

        <!-- Toolbar: Sort, Filters -->
        <div class="d-flex align-items-center mb-3">
            <h5 class="fw-bold text-dark mb-0">Sales Orders Listing</h5>
            <div class="d-flex gap-2 ms-auto">
                <!-- Custom Sort Component -->
                <x-ui.sort-dropdown label="Sort">
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'order_date', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'order_date' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>Order Date (Latest first)</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'order_date', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'order_date' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>Order Date (Oldest first)</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'sales_order_number', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'sales_order_number' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>Order Number (Ascending)</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'sales_order_number', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'sales_order_number' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>Order Number (Descending)</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'total_amount', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'total_amount' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>Total Amount (High to Low)</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'total_amount', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'total_amount' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>Total Amount (Low to High)</span>
                    </a>
                </x-ui.sort-dropdown>

                <!-- Custom Filter Component -->
                <form method="GET" action="{{ route('sales.orders.index') }}" class="d-inline">
                    <x-ui.filter label="Filter" offset="0, 5">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Filter Options</h6>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Search Keywords</label>
                            <x-ui.odoo-form-ui type="input" name="search" placeholder="Search SO number, customer..." value="{{ request('search') }}" />
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Status</label>
                            <x-ui.odoo-form-ui type="select" name="status">
                                <option value="">All Statuses</option>
                                <option value="Draft" {{ request('status') === 'Draft' ? 'selected' : '' }}>Draft</option>
                                <option value="Confirmed" {{ request('status') === 'Confirmed' ? 'selected' : '' }}>Confirmed</option>
                                <option value="Partially Shipped" {{ request('status') === 'Partially Shipped' ? 'selected' : '' }}>Partially Shipped</option>
                                <option value="Shipped" {{ request('status') === 'Shipped' ? 'selected' : '' }}>Shipped</option>
                                <option value="Cancelled" {{ request('status') === 'Cancelled' ? 'selected' : '' }}>Cancelled</option>
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="d-flex gap-2 justify-content-end mt-4">
                            <a href="{{ route('sales.orders.index') }}" class="btn btn-sm btn-light border">Reset</a>
                            <button type="submit" class="btn btn-sm btn-primary">Apply Filters</button>
                        </div>
                    </x-ui.filter>
                </form>
            </div>
        </div>

        <!-- Sales Order List Table -->
        <div class="table-responsive">
            <x-ui.odoo-form-ui type="table" id="ordersTable">
                <thead>
                    <tr>
                        <th style="width: 3%" class="text-center">
                            <input type="checkbox" class="form-check-input">
                        </th>
                        <th>Sales Order #</th>
                        <th>Customer</th>
                        <th>Quotation Ref</th>
                        <th>Order Date</th>
                        <th>Shipment Date</th>
                        <th>Total Amount</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($orders as $order)
                        <tr>
                            <td class="text-center">
                                <input type="checkbox" class="form-check-input">
                            </td>
                            <td class="fw-bold text-primary">
                                <a href="{{ route('sales.orders.show', $order->id) }}">{{ $order->sales_order_number }}</a>
                            </td>
                            <td>
                                <span class="fw-bold">{{ $order->customer?->name ?? '—' }}</span>
                            </td>
                            <td>
                                @if ($order->quotation)
                                    <a href="{{ route('crm.quotations.show', $order->quotation_id) }}" class="text-muted fw-semibold">
                                        {{ $order->quotation->quotation_number }}
                                    </a>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>{{ $order->order_date ? $order->order_date->format('d/m/Y') : '—' }}</td>
                            <td>{{ $order->shipment_date ? $order->shipment_date->format('d/m/Y') : 'Estimated Shipment Not Scheduled' }}</td>
                            <td class="fw-bold text-dark">₹{{ number_format($order->total_amount, 2) }}</td>
                            <td>
                                @php
                                    $badgeClass = 'bg-soft-secondary text-secondary';
                                    if ($order->status === 'Confirmed') $badgeClass = 'bg-soft-info text-info';
                                    elseif ($order->status === 'Partially Shipped') $badgeClass = 'bg-soft-warning text-warning';
                                    elseif ($order->status === 'Shipped') $badgeClass = 'bg-soft-success text-success';
                                    elseif ($order->status === 'Cancelled') $badgeClass = 'bg-soft-danger text-danger';
                                @endphp
                                <span class="badge {{ $badgeClass }} px-2 py-0.5 fs-11 fw-semibold">{{ $order->status }}</span>
                            </td>
                            <td class="text-end pe-4">
                                <div class="d-flex justify-content-end gap-2 align-items-center">
                                    @if ($order->status === 'Draft')
                                        <form action="{{ route('sales.orders.confirm', $order->id) }}" method="POST" class="d-inline">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-soft-success py-1 px-2 fs-11 fw-bold border-0" data-bs-toggle="tooltip" title="Confirm Order">
                                                <i class="feather-check me-1"></i>Confirm
                                            </button>
                                        </form>
                                    @elseif ($order->status === 'Confirmed' || $order->status === 'Partially Shipped')
                                        <a href="{{ route('sales.deliveries.create', ['sales_order_id' => $order->id]) }}" class="btn btn-sm btn-soft-primary py-1 px-2 fs-11 fw-bold border-0" data-bs-toggle="tooltip" title="Create Delivery Order">
                                            <i class="feather-truck me-1"></i>Ship
                                        </a>
                                    @endif

                                    <a href="{{ route('sales.orders.show', $order->id) }}" class="avatar-text avatar-md bg-soft-primary text-primary" data-bs-toggle="tooltip" title="View Sales Order">
                                        <i class="feather feather-eye"></i>
                                    </a>

                                    @if ($order->status !== 'Shipped' && $order->status !== 'Cancelled')
                                        <a href="{{ route('sales.orders.edit', $order->id) }}" class="avatar-text avatar-md bg-soft-warning text-warning" data-bs-toggle="tooltip" title="Edit Sales Order">
                                            <i class="feather feather-edit-2"></i>
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center py-5 text-muted">
                                <i class="feather-shopping-cart fs-1 mb-2 d-block"></i>
                                No sales orders found in this tenant workspace.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.odoo-form-ui>
        </div>

        <div class="mt-auto pt-3">
            <x-ui.pagination 
                :currentPage="$orders->currentPage()" 
                :totalPages="$orders->lastPage()" 
                :totalResults="$orders->total()" 
                :perPage="$orders->perPage()" />
        </div>
    </div>
@endsection
