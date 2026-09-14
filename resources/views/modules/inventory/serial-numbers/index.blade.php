@extends('layouts.duralux')

@section('title', 'Tracked Serial Numbers | Inventory | SaaS ERP')
@section('page-title', 'Serial Number Master Index')
@section('breadcrumb', 'Inventory > Serial Numbers')

@section('content')
<div class="erp-single-panel text-dark">
    <x-ui.odoo-form-ui type="sheet">
        
        <!-- Header & Toolbar -->
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4 pb-3 border-bottom">
            <div>
                <h4 class="fw-bold text-dark mb-1">
                    <i class="feather-hash text-primary me-2"></i>Tracked Serial Numbers
                </h4>
                <small class="text-muted fs-12">View, search, and track unique serial numbers across warehouses and products.</small>
            </div>

            <!-- Controls: Search, Sort, Filter -->
            <div class="d-flex gap-2 ms-auto align-items-center flex-wrap">
                
                <!-- Quick Search (HRMS Common Component Style) -->
                <form method="GET" action="{{ route('inventory.serial-numbers.index') }}" class="d-flex align-items-center bg-light border rounded px-3 py-1">
                    @if(request('product_id')) <input type="hidden" name="product_id" value="{{ request('product_id') }}"> @endif
                    @if(request('status')) <input type="hidden" name="status" value="{{ request('status') }}"> @endif
                    @if(request('warehouse_id')) <input type="hidden" name="warehouse_id" value="{{ request('warehouse_id') }}"> @endif
                    
                    <i class="feather-search text-muted me-2" style="font-size: 14px;"></i>
                    <input 
                        type="text" 
                        name="search" 
                        class="form-control border-0 bg-transparent p-0 fs-13" 
                        placeholder="Search Serial, Product, SKU..." 
                        value="{{ request('search') }}"
                        style="box-shadow: none; height: 32px; width: 220px;"
                    >
                </form>

                <!-- Custom Sort Dropdown Component -->
                <x-ui.sort-dropdown :label="__('crm.sort')">
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'created_at', 'sort_order' => 'desc']) }}" class="dropdown-item {{ request('sort_by', 'created_at') === 'created_at' && request('sort_order', 'desc') === 'desc' ? 'active' : '' }}">
                        <span>Registered Date (Latest)</span>
                    </a>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'created_at', 'sort_order' => 'asc']) }}" class="dropdown-item {{ request('sort_by') === 'created_at' && request('sort_order') === 'asc' ? 'active' : '' }}">
                        <span>Registered Date (Oldest)</span>
                    </a>
                    <div class="dropdown-divider"></div>
                    <a href="{{ request()->fullUrlWithQuery(['sort_by' => 'serial_number', 'sort_order' => 'asc']) }}" class="dropdown-item {{ request('sort_by') === 'serial_number' ? 'active' : '' }}">
                        <span>Serial Number (A-Z)</span>
                    </a>
                </x-ui.sort-dropdown>

                <!-- Custom Filter Component -->
                <form method="GET" action="{{ route('inventory.serial-numbers.index') }}" class="d-inline">
                    @if(request('search')) <input type="hidden" name="search" value="{{ request('search') }}"> @endif
                    
                    <x-ui.filter :label="__('ui.filter')" offset="0, 5">
                        <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Filter Options</h6>
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Product</label>
                            <x-ui.odoo-form-ui type="select" name="product_id">
                                <option value="">— All Tracked Products —</option>
                                @foreach($products as $p)
                                    <option value="{{ $p->id }}" @selected(request('product_id') == $p->id)>{{ $p->name }} ({{ $p->sku }})</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Status</label>
                            <x-ui.odoo-form-ui type="select" name="status">
                                <option value="">— All Statuses —</option>
                                <option value="Available" @selected(request('status') === 'Available')>Available</option>
                                <option value="Sold" @selected(request('status') === 'Sold')>Sold</option>
                                <option value="Reserved" @selected(request('status') === 'Reserved')>Reserved</option>
                                <option value="Returned" @selected(request('status') === 'Returned')>Returned</option>
                                <option value="Damaged" @selected(request('status') === 'Damaged')>Damaged</option>
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold fs-11 text-uppercase text-muted mb-1">Warehouse</label>
                            <x-ui.odoo-form-ui type="select" name="warehouse_id">
                                <option value="">— All Warehouses —</option>
                                @foreach($warehouses as $wh)
                                    <option value="{{ $wh->id }}" @selected(request('warehouse_id') == $wh->id)>{{ $wh->name }}</option>
                                @endforeach
                            </x-ui.odoo-form-ui>
                        </div>

                        <div class="d-flex gap-2 justify-content-end mt-4">
                            <a href="{{ route('inventory.serial-numbers.index') }}" class="btn btn-sm btn-light border">Reset</a>
                            <button type="submit" class="btn btn-sm btn-primary">Apply Filters</button>
                        </div>
                    </x-ui.filter>
                </form>

            </div>
        </div>

        <!-- Serial Numbers Table -->
        <div class="table-responsive">
            <x-ui.odoo-form-ui type="table" id="serialNumbersTable">
                <thead class="table-light bg-light">
                    <tr>
                        <th class="ps-3" style="width: 18%;">Serial Number</th>
                        <th style="width: 22%;">Product Name & SKU</th>
                        <th style="width: 12%;" class="text-center">Status</th>
                        <th style="width: 15%;">Current Warehouse</th>
                        <th style="width: 12%;">Purchase Rate</th>
                        <th style="width: 12%;">Inward Ref (GRN/Stock)</th>
                        <th style="width: 12%;">Outward Ref (Dispatch/Invoice)</th>
                        <th class="pe-3" style="width: 15%;">Registered On</th>
                    </tr>
                </thead>
                <tbody class="text-dark">
                    @forelse($serials as $sn)
                        <tr>
                            <td class="ps-3">
                                <span class="fw-bold text-primary font-monospace fs-13">
                                    <i class="feather-hash text-primary me-1 fs-12"></i>{{ $sn->serial_number }}
                                </span>
                            </td>
                            <td>
                                @if($sn->product)
                                    <a href="{{ route('inventory.products.show', $sn->product_id) }}" class="fw-bold text-dark text-decoration-none">
                                        {{ $sn->product->name }}
                                    </a>
                                    <div class="fs-11 text-muted font-monospace">SKU: {{ $sn->product->sku ?: '—' }}</div>
                                @else
                                    <span class="text-muted">Deleted Product</span>
                                @endif
                            </td>
                            <td class="text-center">
                                <x-ui.status-badge :status="$sn->status" size="sm" />
                            </td>
                            <td>
                                <span class="fw-semibold text-dark fs-12">
                                    <i class="feather-map-pin text-muted me-1 fs-11"></i>{{ $sn->warehouse->name ?? 'Main Warehouse' }}
                                </span>
                            </td>
                            <td class="font-monospace fw-semibold fs-12">
                                ₹{{ number_format($sn->purchase_rate, 2) }}
                            </td>
                            <td>
                                @if($sn->transactionIn)
                                    @if($sn->transactionIn->reference_type === 'SalesReturn' && $sn->transactionIn->reference_id)
                                        <a href="{{ route('sales.returns.show', $sn->transactionIn->reference_id) }}" class="badge bg-soft-primary text-primary font-monospace text-decoration-none px-2 py-1">
                                            <i class="feather-rotate-ccw me-1"></i>{{ $sn->transactionIn->document_number }}
                                        </a>
                                    @else
                                        <span class="badge bg-soft-primary text-primary font-monospace px-2 py-1">{{ $sn->transactionIn->document_number }}</span>
                                    @endif
                                @else
                                    <span class="text-muted fs-12">—</span>
                                @endif
                            </td>
                            <td>
                                @if($sn->transactionOut)
                                    @if(in_array($sn->transactionOut->reference_type, ['DispatchOrder', 'Dispatch']) && $sn->transactionOut->reference_id)
                                        <a href="{{ route('sales.dispatches.show', $sn->transactionOut->reference_id) }}" class="badge bg-soft-danger text-danger font-monospace text-decoration-none px-2 py-1">
                                            <i class="feather-truck me-1"></i>{{ $sn->transactionOut->document_number }}
                                        </a>
                                    @elseif($sn->transactionOut->reference_type === 'SalesOrder' && $sn->transactionOut->reference_id)
                                        <a href="{{ route('sales.orders.show', $sn->transactionOut->reference_id) }}" class="badge bg-soft-danger text-danger font-monospace text-decoration-none px-2 py-1">
                                            <i class="feather-file-text me-1"></i>{{ $sn->transactionOut->document_number }}
                                        </a>
                                    @else
                                        <span class="badge bg-soft-danger text-danger font-monospace px-2 py-1">{{ $sn->transactionOut->document_number }}</span>
                                    @endif
                                @else
                                    <span class="text-muted fs-12">—</span>
                                @endif
                            </td>
                            <td class="pe-3 text-muted fs-12">
                                <i class="feather-calendar me-1 text-muted fs-11"></i>{{ $sn->created_at ? $sn->created_at->format('d M Y h:i A') : '—' }}
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
            </x-ui.odoo-form-ui>
        </div>

        <!-- Pagination Component -->
        <div class="pt-3">
            <x-ui.pagination 
                :currentPage="$serials->currentPage()" 
                :totalPages="$serials->lastPage()" 
                :totalResults="$serials->total()" 
                :perPage="$serials->perPage()" />
        </div>

    </x-ui.odoo-form-ui>
</div>
@endsection
