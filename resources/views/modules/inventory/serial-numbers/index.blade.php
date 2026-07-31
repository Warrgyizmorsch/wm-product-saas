@extends('layouts.duralux')

@section('title', 'Tracked Serial Numbers | Inventory | SaaS ERP')
@section('page-title', 'Serial Number Master Index')
@section('breadcrumb', 'Inventory > Serial Numbers')

@section('content')
<div class="erp-single-panel text-dark">
    <x-ui.odoo-form-ui type="sheet" class="p-0">
        <!-- Top Search and Filter Bar -->
        <div class="p-4 border-bottom bg-white">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-3">
                <div>
                    <h4 class="fw-bold text-dark mb-0">
                        <i class="feather-hash text-primary me-2"></i>Tracked Serial Numbers
                    </h4>
                    <span class="fs-12 text-muted">View, search, and track unique serial numbers across warehouses and products.</span>
                </div>
            </div>

            <!-- Filter Form -->
            <form action="{{ route('inventory.serial-numbers.index') }}" method="GET" class="row g-2 align-items-center">
                <div class="col-md-4">
                    <input type="text" name="search" value="{{ request('search') }}" class="form-control form-control-sm" placeholder="Search by Serial Number, Product, SKU...">
                </div>

                <div class="col-md-3">
                    <select name="product_id" class="form-select form-select-sm">
                        <option value="">— All Tracked Products —</option>
                        @foreach($products as $p)
                            <option value="{{ $p->id }}" @selected(request('product_id') == $p->id)>{{ $p->name }} ({{ $p->sku }})</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">— All Statuses —</option>
                        <option value="Available" @selected(request('status') === 'Available')>Available</option>
                        <option value="Sold" @selected(request('status') === 'Sold')>Sold</option>
                        <option value="Reserved" @selected(request('status') === 'Reserved')>Reserved</option>
                        <option value="Returned" @selected(request('status') === 'Returned')>Returned</option>
                        <option value="Damaged" @selected(request('status') === 'Damaged')>Damaged</option>
                    </select>
                </div>

                <div class="col-md-2">
                    <select name="warehouse_id" class="form-select form-select-sm">
                        <option value="">— All Warehouses —</option>
                        @foreach($warehouses as $wh)
                            <option value="{{ $wh->id }}" @selected(request('warehouse_id') == $wh->id)>{{ $wh->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-1">
                    <button type="submit" class="btn btn-sm btn-primary w-100 fw-semibold">
                        <i class="feather-filter me-1"></i>Filter
                    </button>
                </div>
            </form>
        </div>

        <!-- Serial Numbers Table -->
        <div class="p-4 bg-white">
            <div class="table-responsive rounded-3 border">
                <table class="table table-hover align-middle mb-0 text-dark fs-13">
                    <thead class="bg-light text-uppercase fs-11 text-muted fw-semibold">
                        <tr>
                            <th class="ps-3">Serial Number</th>
                            <th>Product Name & SKU</th>
                            <th>Status</th>
                            <th>Current Warehouse</th>
                            <th>Purchase Rate</th>
                            <th>Inward Ref (GRN/Stock)</th>
                            <th>Outward Ref (Dispatch/Invoice)</th>
                            <th class="pe-3">Registered On</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($serials as $sn)
                            <tr>
                                <td class="ps-3 fw-bold text-dark font-monospace fs-14">
                                    <i class="feather-hash text-primary me-1 fs-12"></i>{{ $sn->serial_number }}
                                </td>
                                <td>
                                    @if($sn->product)
                                        <a href="{{ route('inventory.products.show', $sn->product_id) }}" class="fw-bold text-dark hover-underline">
                                            {{ $sn->product->name }}
                                        </a>
                                        <div class="fs-11 text-muted font-monospace">SKU: {{ $sn->product->sku ?: '—' }}</div>
                                    @else
                                        <span class="text-muted">Deleted Product</span>
                                    @endif
                                </td>
                                <td>
                                    @if($sn->status === 'Available')
                                        <span class="badge bg-soft-success text-success px-2.5 py-1 fw-bold fs-11">Available</span>
                                    @elseif($sn->status === 'Sold')
                                        <span class="badge bg-soft-danger text-danger px-2.5 py-1 fw-bold fs-11">Sold</span>
                                    @elseif($sn->status === 'Reserved')
                                        <span class="badge bg-soft-warning text-warning px-2.5 py-1 fw-bold fs-11">Reserved</span>
                                    @else
                                        <span class="badge bg-light text-dark px-2.5 py-1 fw-bold fs-11">{{ $sn->status }}</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="fw-semibold text-dark">{{ $sn->warehouse->name ?? 'Main Warehouse' }}</span>
                                </td>
                                <td class="font-monospace fw-semibold">
                                    ₹{{ number_format($sn->purchase_rate, 2) }}
                                </td>
                                <td>
                                    @if($sn->transactionIn)
                                        @if($sn->transactionIn->reference_type === 'SalesReturn' && $sn->transactionIn->reference_id)
                                            <a href="{{ route('sales.returns.show', $sn->transactionIn->reference_id) }}" class="badge bg-soft-primary text-primary font-monospace">
                                                <i class="feather-rotate-ccw me-1"></i>{{ $sn->transactionIn->document_number }}
                                            </a>
                                        @else
                                            <span class="badge bg-soft-primary text-primary font-monospace">{{ $sn->transactionIn->document_number }}</span>
                                        @endif
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if($sn->transactionOut)
                                        @if(in_array($sn->transactionOut->reference_type, ['DispatchOrder', 'Dispatch']) && $sn->transactionOut->reference_id)
                                            <a href="{{ route('sales.dispatches.show', $sn->transactionOut->reference_id) }}" class="badge bg-soft-danger text-danger font-monospace">
                                                <i class="feather-truck me-1"></i>{{ $sn->transactionOut->document_number }}
                                            </a>
                                        @elseif($sn->transactionOut->reference_type === 'SalesOrder' && $sn->transactionOut->reference_id)
                                            <a href="{{ route('sales.orders.show', $sn->transactionOut->reference_id) }}" class="badge bg-soft-danger text-danger font-monospace">
                                                <i class="feather-file-text me-1"></i>{{ $sn->transactionOut->document_number }}
                                            </a>
                                        @else
                                            <span class="badge bg-soft-danger text-danger font-monospace">{{ $sn->transactionOut->document_number }}</span>
                                        @endif
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="pe-3 text-muted fs-12">
                                    {{ $sn->created_at ? $sn->created_at->format('d-M-Y h:i A') : '—' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <i class="feather-inbox fs-1 d-block mb-2 opacity-50"></i>
                                    No serial numbers found. Serials are registered automatically during Opening Stock or GRN Inward Receipts.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="d-flex justify-content-end mt-3">
                {{ $serials->links() }}
            </div>
        </div>
    </x-ui.odoo-form-ui>
</div>
@endsection
