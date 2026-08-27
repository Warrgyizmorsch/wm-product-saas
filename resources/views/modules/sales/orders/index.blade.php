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

        {{-- 1. Header: Title & Actions (Matching Leads Index Style) --}}
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <h5 class="fw-bold text-dark mb-0">Sales Orders Listing</h5>
            <div class="d-flex align-items-center flex-wrap gap-2">
                <!-- Outside Search Box (CRM Leads / HRMS Style) -->
                <form method="GET" action="{{ route('sales.orders.index') }}" class="d-flex align-items-center bg-light border rounded px-2.5 py-0.5 me-1" style="height: 34px; min-width: 240px;">
                    @foreach(request()->except(['search', 'page']) as $k => $v)
                        @if(is_scalar($v) && $v !== '')
                            <input type="hidden" name="{{ $k }}" value="{{ $v }}">
                        @endif
                    @endforeach
                    <i class="feather-search text-muted me-2" style="font-size: 13px;"></i>
                    <input type="text" name="search" class="form-control border-0 bg-transparent p-0 fs-12 text-dark" placeholder="Search SO #, Customer..." value="{{ request('search') }}" style="box-shadow: none; outline: none;">
                    @if(request('search'))
                        <a href="{{ route('sales.orders.index', request()->except(['search', 'page'])) }}" class="text-muted text-decoration-none ms-1" title="Clear Search">
                            <i class="feather-x fs-12"></i>
                        </a>
                    @endif
                </form>

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

        <!-- 2. Sales Order List Table (Matching Lead Table Component Architecture) -->
        <div class="table-responsive">
            <x-ui.odoo-form-ui type="table" id="ordersTable">
                <thead>
                    <tr>
                        <th style="width: 3%" class="text-center">
                            <input type="checkbox" class="form-check-input">
                        </th>
                        <th>Reference #</th>
                        <th>Customer Name</th>
                        <th>Status</th>
                        <th class="text-end">Sales order amount</th>
                        <th class="text-end">Invoiced amount</th>
                        <th class="text-end">Balanced</th>
                        <th class="text-center">Send / Action</th>
                        <th class="text-center" style="width: 20%;">Delivery Challan</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($orders as $order)
                        @php
                            $invoicedAmt = $order->invoices ? $order->invoices->where('status', '!=', 'Cancelled')->sum('total_amount') : 0;
                            $balancedAmt = max(0, (float)$order->total_amount - $invoicedAmt);
                            
                            $billingStatusLabel = 'Open';
                            $billingStatusBadge = 'bg-soft-info text-info';
                            if ($invoicedAmt > 0 && $balancedAmt > 0.01) {
                                $billingStatusLabel = 'Partially Invoiced';
                                $billingStatusBadge = 'bg-soft-warning text-warning';
                            } elseif ($balancedAmt <= 0.01 && $invoicedAmt > 0) {
                                $billingStatusLabel = 'Fully Invoiced';
                                $billingStatusBadge = 'bg-soft-success text-success';
                            } elseif ($order->status === 'Cancelled') {
                                $billingStatusLabel = 'Cancelled';
                                $billingStatusBadge = 'bg-soft-danger text-danger';
                            }

                            $doCount = $order->dispatches ? $order->dispatches->count() : 0;
                        @endphp
                        <tr>
                            <td class="text-center">
                                <input type="checkbox" class="form-check-input">
                            </td>
                            <td class="fw-bold font-monospace text-primary">
                                <a href="{{ route('sales.orders.show', $order->id) }}" class="text-decoration-none text-primary">{{ $order->sales_order_number }}</a>
                                <small class="text-muted d-block font-sans-serif fs-11">{{ $order->order_date ? $order->order_date->format('d/m/Y') : '—' }}</small>
                            </td>
                            <td>
                                <span class="fw-bold text-dark">{{ $order->customer?->name ?? '—' }}</span>
                            </td>
                            <td>
                                <span class="badge {{ $billingStatusBadge }} px-2 py-1 fs-11 fw-bold">{{ $billingStatusLabel }}</span>
                                <small class="text-muted d-block fs-10 mt-0.5">SO State: {{ $order->status }}</small>
                            </td>
                            <td class="text-end fw-bold text-dark">₹{{ number_format($order->total_amount, 2) }}</td>
                            <td class="text-end fw-bold text-success">₹{{ number_format($invoicedAmt, 2) }}</td>
                            <td class="text-end fw-bold text-danger">₹{{ number_format($balancedAmt, 2) }}</td>
                            
                            {{-- Send Status & Action Column --}}
                            <td class="text-center py-2">
                                @if(in_array($order->status, ['Confirmed', 'Partially Shipped', 'Shipped']))
                                    <span class="badge bg-soft-secondary text-secondary fs-10 fw-bold px-2 py-0.5 d-inline-block mb-1">Sent</span>
                                    @if($order->invoices && $order->invoices->count() > 0)
                                        <a href="{{ route('sales.orders.show', $order->id) }}#tab-invoices" class="d-block text-primary fs-11 fw-semibold text-decoration-underline">View History of Invoice</a>
                                    @endif
                                @else
                                    <span class="badge bg-soft-warning text-warning fs-10 fw-bold px-2 py-0.5">Pending</span>
                                @endif
                            </td>

                            {{-- Delivery Challan / Invoicing Column (Absolute ERP Style) --}}
                            <td class="text-center py-2">
                                @if($order->status !== 'Cancelled')
                                    @php
                                        $unbilledDo = $order->dispatches ? $order->dispatches->first(function($d) {
                                            return !in_array($d->status, ['Invoiced', 'Fully Invoiced', 'Completed', 'Cancelled']);
                                        }) : null;
                                        $doInvoiceParams = ['sales_order_id' => $order->id, 'mode' => 'dispatch_order'];
                                        if ($unbilledDo) {
                                            $doInvoiceParams['dispatch_order_id'] = $unbilledDo->id;
                                        }
                                    @endphp
                                    <div class="d-flex flex-column gap-1 align-items-center justify-content-center">
                                        @if($unbilledDo)
                                            <x-ui.button href="{!! route('sales.invoices.create', $doInvoiceParams) !!}" variant="soft-primary" size="xs" icon="feather-file-text" class="fw-bold px-2.5 py-1 fs-11 text-nowrap" title="Create Invoice against Dispatches of this order">
                                                Add Invoice From DO
                                            </x-ui.button>
                                        @elseif($doCount > 0)
                                            <span class="badge bg-soft-success text-success fs-11 fw-semibold">All DOs Invoiced</span>
                                        @else
                                            <x-ui.button href="{!! route('sales.invoices.create', $doInvoiceParams) !!}" variant="soft-primary" size="xs" icon="feather-file-text" class="fw-bold px-2.5 py-1 fs-11 text-nowrap" title="Create Invoice against Dispatches of this order">
                                                Add Invoice From DO
                                            </x-ui.button>
                                        @endif
                                        @if($doCount > 0)
                                            <a href="{{ route('sales.orders.show', $order->id) }}#tab-dispatches" class="d-block text-primary fs-11 fw-semibold text-decoration-underline mt-0.5" title="View History of Delivery Challans / Dispatches">
                                                View History of DO
                                            </a>
                                        @else
                                            <span class="text-muted fs-10 opacity-75 mt-0.5">No DO History</span>
                                        @endif
                                    </div>
                                @else
                                    <span class="text-muted fs-11 italic">Cancelled</span>
                                @endif
                            </td>

                            <td class="text-end pe-4">
                                <div class="hstack gap-2 justify-content-end align-items-center">
                                    @if ($order->status === 'Draft')
                                        <form action="{{ route('sales.orders.confirm', $order->id) }}" method="POST" class="d-inline">
                                            @csrf
                                            <x-ui.button type="submit" variant="soft-success" size="sm" icon="feather-check" class="py-1 px-2.5 fs-11 fw-bold text-uppercase border-0">
                                                Confirm
                                            </x-ui.button>
                                        </form>
                                    @endif

                                    <x-ui.action-dropdown :viewUrl="route('sales.orders.show', $order->id)" id="soActions-{{ $order->id }}">
                                        <x-ui.dropdown-item href="{{ route('sales.orders.show', $order->id) }}" icon="feather-eye me-2">
                                            View Details
                                        </x-ui.dropdown-item>
                                        
                                        @if ($order->status !== 'Shipped' && $order->status !== 'Cancelled')
                                            <x-ui.dropdown-item href="{{ route('sales.orders.edit', $order->id) }}" icon="feather-edit-2 me-2">
                                                Edit Sales Order
                                            </x-ui.dropdown-item>
                                        @endif

                                        @if ($order->status === 'Confirmed' || $order->status === 'Partially Shipped')
                                            <x-ui.dropdown-item href="{{ route('sales.dispatches.create', ['sales_order_id' => $order->id]) }}" icon="feather-truck me-2" class="text-primary fw-semibold">
                                                Add Dispatch Order (DO)
                                            </x-ui.dropdown-item>
                                        @endif
                                    </x-ui.action-dropdown>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center py-5 text-muted">
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
