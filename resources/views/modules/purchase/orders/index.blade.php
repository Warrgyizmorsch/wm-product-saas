@extends('layouts.duralux')

@section('title', 'Purchase Orders | SaaS ERP')
@section('page-title', 'Purchase Orders')
@section('breadcrumb', 'Purchase / Orders')

@section('page-actions')
    <x-ui.button href="{{ route('purchase.orders.create') }}" variant="primary" icon="feather-plus" style="background-color: #714B67; border-color: #714B67;">
        Create Purchase Order
    </x-ui.button>
@endsection

@section('content')
    @php
        $sortBy = request('sort_by', 'id');
        $sortOrder = request('sort_order', 'desc');
        $currency = tenant()?->settings['currency'] ?? 'INR';
    @endphp

    <div class="erp-single-panel bg-white p-4 shadow-sm rounded border-0 text-dark">
        <!-- Toast Notifications -->
        @if (session('success'))
            <x-ui.toast :auto="true" type="success" title="{{ session('success') }}" />
        @endif
        @if (session('error'))
            <x-ui.toast :auto="true" type="error" title="{{ session('error') }}" />
        @endif

        <div class="d-flex align-items-center mb-3">
            <h5 class="fw-bold text-dark mb-0">Purchase Orders Listing</h5>
            
            <div class="d-flex gap-2 ms-auto">
                <!-- Custom Sort Dropdown -->
                <x-ui.sort-dropdown :label="__('crm.sort') ?? 'Sort'">
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'date', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'date' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>Date (Latest)</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'date', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'date' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>Date (Oldest)</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'purchase_order_number', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'purchase_order_number' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>PO Number (A-Z)</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'purchase_order_number', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'purchase_order_number' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>PO Number (Z-A)</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'grand_total', 'sort_order' => 'desc']) }}" class="dropdown-item {{ $sortBy === 'grand_total' && $sortOrder === 'desc' ? 'active' : '' }}">
                        <span>Amount (High-Low)</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'grand_total', 'sort_order' => 'asc']) }}" class="dropdown-item {{ $sortBy === 'grand_total' && $sortOrder === 'asc' ? 'active' : '' }}">
                        <span>Amount (Low-High)</span>
                    </a>
                </x-ui.sort-dropdown>

                <!-- Filter Panel -->
                <form method="GET" action="{{ route('purchase.orders.index') }}" class="d-inline">
                    <x-ui.filter :label="__('ui.filter') ?? 'Filters'" offset="0, 5">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Filter Options</h6>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Search Keyword</label>
                            <x-ui.odoo-form-ui type="input" name="search" placeholder="Search PO, Supplier, Ref..." value="{{ request('search') }}" />
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Status</label>
                            <x-ui.odoo-form-ui type="select" name="status">
                                <option value="">All Statuses</option>
                                <option value="Draft" @selected(request('status') === 'Draft')>Draft</option>
                                <option value="Approved" @selected(request('status') === 'Approved')>Approved</option>
                                <option value="Cancelled" @selected(request('status') === 'Cancelled')>Cancelled</option>
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="d-flex gap-2 justify-content-end mt-4">
                            <a href="{{ route('purchase.orders.index') }}" class="btn btn-sm btn-light border">Reset</a>
                            <button type="submit" class="btn btn-sm btn-primary">Apply Filters</button>
                        </div>
                    </x-ui.filter>
                </form>
            </div>
        </div>

        <!-- Listing Table -->
        <div class="table-responsive">
            <x-ui.odoo-form-ui type="table" id="poTable">
                <thead>
                    <tr>
                        <th style="width: 3%" class="text-center">
                            <input type="checkbox" class="form-check-input select-all">
                        </th>
                        <th style="width: 12%">PO Number</th>
                        <th style="width: 18%">Supplier Name</th>
                        <th style="width: 12%">Ref Document</th>
                        <th style="width: 10%">PO Date</th>
                        <th style="width: 10%" class="text-end">Subtotal</th>
                        <th style="width: 9%" class="text-end">Total Tax</th>
                        <th style="width: 12%" class="text-end">Grand Total</th>
                        <th style="width: 12%" class="text-center">Status</th>
                        <th style="width: 12%" class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $order)
                        <tr>
                            <td class="text-center">
                                <input type="checkbox" class="form-check-input row-checkbox" value="{{ $order->id }}">
                            </td>
                            <td class="fw-bold">
                                <a href="{{ route('purchase.orders.show', $order->id) }}" class="text-primary text-decoration-none">
                                    {{ $order->purchase_order_number }}
                                </a>
                            </td>
                            <td>{{ $order->vendor->name ?? '—' }}</td>
                            <td>
                                @if($order->requisition)
                                    <a href="{{ route('purchase.requisitions.show', $order->purchase_requisition_id) }}" class="text-primary fw-medium">
                                        {{ $order->requisition->requisition_number }}
                                    </a>
                                @else
                                    <span class="text-muted small">Direct PO</span>
                                @endif
                            </td>
                            <td>{{ $order->date ? $order->date->format('d-M-Y') : '—' }}</td>
                            <td class="text-end font-monospace">{{ number_format($order->subtotal, 2) }}</td>
                            <td class="text-end font-monospace text-muted">{{ number_format($order->tax_amount, 2) }}</td>
                            <td class="text-end font-monospace fw-bold text-success">{{ number_format($order->grand_total, 2) }}</td>
                            <td class="text-center">
                                @php
                                    $statusClass = 'warning';
                                    if ($order->status === 'Approved') $statusClass = 'success';
                                    elseif ($order->status === 'Cancelled') $statusClass = 'danger';
                                @endphp
                                <x-ui.badge :soft="true" :variant="$statusClass">
                                    {{ $order->status }}
                                </x-ui.badge>
                            </td>
                            <td class="text-end">
                                <x-ui.action-dropdown :viewUrl="route('purchase.orders.show', $order->id)" id="poActions-{{ $order->id }}">
                                    @if($order->status === 'Draft')
                                        <li>
                                            <a class="dropdown-item py-2" href="{{ route('purchase.orders.edit', $order->id) }}">
                                                <i class="feather-edit me-1.5 text-muted"></i> Edit Draft
                                            </a>
                                        </li>
                                        <li>
                                            <form action="{{ route('purchase.orders.approve', $order->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to approve this purchase order?')">
                                                @csrf
                                                <button type="submit" class="dropdown-item py-2 text-success">
                                                    <i class="feather-check-circle me-1.5"></i> Approve
                                                </button>
                                            </form>
                                        </li>
                                        <li>
                                            <form action="{{ route('purchase.orders.reject', $order->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to reject this purchase order?')">
                                                @csrf
                                                <button type="submit" class="dropdown-item py-2 text-danger">
                                                    <i class="feather-x-circle me-1.5"></i> Reject
                                                </button>
                                            </form>
                                        </li>
                                        <li>
                                            <form action="{{ route('purchase.orders.destroy', $order->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this purchase order?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="dropdown-item py-2 text-danger">
                                                    <i class="feather-trash-2 me-1.5"></i> Delete
                                                </button>
                                            </form>
                                        </li>
                                    @endif
                                </x-ui.action-dropdown>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="text-center py-5 text-muted fs-14">
                                <i class="feather-truck fs-24 mb-1.5 d-block opacity-50"></i>
                                No purchase orders found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </x-ui.odoo-form-ui>
        </div>

        <!-- Pagination Links -->
        <div class="pt-3">
            <x-ui.pagination 
                :currentPage="$orders->currentPage()" 
                :totalPages="$orders->lastPage()" 
                :totalResults="$orders->total()" 
                :perPage="$orders->perPage()" />
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        $(document).ready(function() {
            $('.select-all').on('change', function() {
                $('.row-checkbox').prop('checked', $(this).prop('checked'));
            });
        });
    </script>
@endpush
