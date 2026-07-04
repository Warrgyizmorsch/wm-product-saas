@extends('layouts.duralux')

@section('title', 'Item Details | SaaS ERP')
@section('page-title', 'Item details')
@section('breadcrumb', 'Inventory / Items / Details')

@section('page-actions')
    <div class="d-flex gap-2">
        <a href="{{ route('inventory.products.index') }}" class="btn btn-light">
            <i class="feather-arrow-left me-2"></i>Back
        </a>
        <a href="{{ route('inventory.products.edit', $product) }}" class="btn btn-primary">
            <i class="feather-edit me-2"></i>Edit Item
        </a>
    </div>
@endsection

@section('content')
    <div class="row">
        <!-- Main details card -->
        <div class="col-12">
            <div class="card border-0 shadow-sm p-4 p-md-5 bg-white mb-4">
                <!-- Item Summary Header -->
                <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 border-bottom pb-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="avatar-text avatar-lg bg-soft-primary text-primary fs-4 fw-bold">
                            {{ strtoupper(substr($product->name, 0, 1)) }}
                        </div>
                        <div>
                            <h3 class="fw-bold text-dark mb-1">{{ $product->name }}</h3>
                            <div class="d-flex gap-2 align-items-center">
                                <span class="text-muted fs-13 font-monospace">{{ $product->sku }}</span>
                                <span class="text-muted">|</span>
                                @if($product->item_type === 'Goods')
                                    <span class="badge bg-soft-info text-info fs-11">Goods</span>
                                @else
                                    <span class="badge bg-soft-warning text-warning fs-11">Service</span>
                                @endif
                                <span class="text-muted">|</span>
                                @if($product->status === 'active')
                                    <span class="badge bg-soft-success text-success fs-11">Active</span>
                                @else
                                    <span class="badge bg-soft-danger text-danger fs-11">Inactive</span>
                                @endif
                            </div>
                        </div>
                    </div>
                    
                    <!-- Stock and Valuation Summary (Only for Goods) -->
                    @if($product->item_type === 'Goods')
                        <div class="d-flex gap-4 text-end mt-3 mt-md-0">
                            <div>
                                <span class="text-muted fs-11 text-uppercase d-block">Stock On Hand</span>
                                <h4 class="fw-bold text-dark mb-0">
                                    {{ number_format($product->total_stock, 0) }}
                                    <span class="fs-12 fw-normal text-muted">{{ $product->uom ? $product->uom->code : 'pcs' }}</span>
                                </h4>
                            </div>
                            <div class="border-start ps-4">
                                <span class="text-muted fs-11 text-uppercase d-block">Asset Value</span>
                                <h4 class="fw-bold text-dark mb-0">
                                    ₹{{ number_format(
                                        $product->variation_type === 'Variant' 
                                        ? $product->variants->sum(fn($v) => $v->warehouseStocks->sum(fn($ws) => $ws->quantity * $ws->unit_cost))
                                        : $product->warehouseStocks->sum(fn($ws) => $ws->quantity * $ws->unit_cost), 
                                        2
                                    ) }}
                                </h4>
                            </div>
                        </div>
                    @endif
                </div>

                <!-- Tabs Navigation -->
                <ul class="nav nav-tabs border-bottom mb-4" id="itemDetailsTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active fw-bold" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview-pane" type="button" role="tab" aria-controls="overview-pane" aria-selected="true">
                            <i class="feather-info me-1"></i>Overview
                        </button>
                    </li>
                    @if($product->item_type === 'Goods')
                        <li class="nav-item" role="presentation">
                            <button class="nav-link fw-bold" id="stock-tab" data-bs-toggle="tab" data-bs-target="#stock-pane" type="button" role="tab" aria-controls="stock-pane" aria-selected="false">
                                <i class="feather-home me-1"></i>Warehouse Stock
                            </button>
                        </li>
                    @endif
                    @if($product->variation_type === 'Variant')
                        <li class="nav-item" role="presentation">
                            <button class="nav-link fw-bold" id="variants-tab" data-bs-toggle="tab" data-bs-target="#variants-pane" type="button" role="tab" aria-controls="variants-pane" aria-selected="false">
                                <i class="feather-git-branch me-1"></i>Child Variants ({{ $product->variants->count() }})
                            </button>
                        </li>
                    @endif
                </ul>

                <!-- Tabs Content -->
                <div class="tab-content" id="itemDetailsTabsContent">
                    <!-- Tab 1: Overview -->
                    <div class="tab-pane fade show active" id="overview-pane" role="tabpanel" aria-labelledby="overview-tab">
                        <div class="row g-4 fs-13 text-dark">
                            <!-- Left Column: Attributes & Details -->
                            <div class="col-lg-6 border-end">
                                <h6 class="fw-bold text-primary mb-3">Item Specifications</h6>
                                
                                <table class="table table-borderless align-middle mb-4">
                                    <tbody>
                                        <tr>
                                            <td class="text-muted" style="width: 140px;">Unit of Measure</td>
                                            <td class="fw-semibold">{{ $product->uom ? $product->uom->name . ' (' . $product->uom->code . ')' : '—' }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Brand</td>
                                            <td class="fw-semibold">{{ $product->brand ?: '—' }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Manufacturer</td>
                                            <td class="fw-semibold">{{ $product->manufacturer ?: '—' }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Part Number (MPN)</td>
                                            <td class="fw-semibold font-monospace">{{ $product->mpn ?: '—' }}</td>
                                        </tr>
                                    </tbody>
                                </table>

                                <h6 class="fw-bold text-primary mb-3">Barcode & Identifiers</h6>
                                <table class="table table-borderless align-middle mb-4">
                                    <tbody>
                                        <tr>
                                            <td class="text-muted" style="width: 140px;">Barcode</td>
                                            <td class="fw-semibold font-monospace">{{ $product->barcode ?: '—' }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">UPC</td>
                                            <td class="fw-semibold font-monospace">{{ $product->upc ?: '—' }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">EAN</td>
                                            <td class="fw-semibold font-monospace">{{ $product->ean ?: '—' }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">ISBN</td>
                                            <td class="fw-semibold font-monospace">{{ $product->isbn ?: '—' }}</td>
                                        </tr>
                                    </tbody>
                                </table>

                                @if($product->description)
                                    <h6 class="fw-bold text-primary mb-2">Description / Notes</h6>
                                    <p class="text-muted bg-light p-3 rounded" style="white-space: pre-wrap;">{{ $product->description }}</p>
                                @endif
                            </div>

                            <!-- Right Column: Financial & Physical details -->
                            <div class="col-lg-6">
                                <h6 class="fw-bold text-primary mb-3">Sales & Purchase Info</h6>
                                <table class="table table-borderless align-middle mb-4">
                                    <tbody>
                                        <tr>
                                            <td class="text-muted" style="width: 140px;">Selling Price</td>
                                            <td class="fw-bold text-success">₹{{ number_format($product->selling_price, 2) }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Sales Account</td>
                                            <td class="fw-semibold">{{ $product->sales_account ?: '—' }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Purchase Cost</td>
                                            <td class="fw-bold text-danger">₹{{ number_format($product->cost_price, 2) }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Purchase Account</td>
                                            <td class="fw-semibold">{{ $product->purchase_account ?: '—' }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">HSN/SAC Code</td>
                                            <td class="fw-semibold font-monospace">{{ $product->hsn_sac ?: '—' }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">GST Rate</td>
                                            <td class="fw-semibold">{{ $product->gst_rate }}%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Preferred Vendor</td>
                                            <td class="fw-semibold">{{ $product->vendor ? $product->vendor->name : '—' }}</td>
                                        </tr>
                                    </tbody>
                                </table>

                                <h6 class="fw-bold text-primary mb-3">Dimensions & Weight</h6>
                                <table class="table table-borderless align-middle mb-4">
                                    <tbody>
                                        <tr>
                                            <td class="text-muted" style="width: 140px;">Dimensions (L x W x H)</td>
                                            <td class="fw-semibold">
                                                @if($product->length || $product->width || $product->height)
                                                    {{ $product->length ?: 0 }} x {{ $product->width ?: 0 }} x {{ $product->height ?: 0 }} {{ $product->dimension_unit }}
                                                @else
                                                    —
                                                @endif
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">Weight</td>
                                            <td class="fw-semibold">
                                                @if($product->weight)
                                                    {{ $product->weight }} {{ $product->weight_unit }}
                                                @else
                                                    —
                                                @endif
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>

                                @if($product->item_type === 'Goods')
                                    <h6 class="fw-bold text-primary mb-3">Tracking & Control</h6>
                                    <table class="table table-borderless align-middle">
                                        <tbody>
                                            <tr>
                                                <td class="text-muted" style="width: 140px;">Track Serials</td>
                                                <td>
                                                    <span class="badge {{ $product->track_serial_number ? 'bg-soft-success text-success' : 'bg-soft-secondary text-secondary' }} px-2 py-0.5">
                                                        {{ $product->track_serial_number ? 'Yes' : 'No' }}
                                                    </span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted">Track Batches</td>
                                                <td>
                                                    <span class="badge {{ $product->track_batch ? 'bg-soft-success text-success' : 'bg-soft-secondary text-secondary' }} px-2 py-0.5">
                                                        {{ $product->track_batch ? 'Yes' : 'No' }}
                                                    </span>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Tab 2: Warehouse Stock -->
                    @if($product->item_type === 'Goods')
                        <div class="tab-pane fade" id="stock-pane" role="tabpanel" aria-labelledby="stock-tab">
                            <div class="table-responsive">
                                <table class="table table-bordered align-middle">
                                    <thead class="table-light text-muted">
                                        <tr>
                                            <th>Warehouse Code</th>
                                            <th>Warehouse Name</th>
                                            <th>Stock On Hand</th>
                                            <th>Valuation Cost (₹)</th>
                                            <th>Total Asset Value (₹)</th>
                                            <th>Alert Reorder Level</th>
                                        </tr>
                                    </thead>
                                    <tbody class="fs-13 text-dark">
                                        @if($product->variation_type === 'Variant')
                                            <!-- Sum warehouse stock for child variants -->
                                            @foreach($warehouses as $wh)
                                                @php
                                                    $qty = $product->variants->sum(fn($v) => $v->warehouseStocks->where('warehouse_id', $wh->id)->sum('quantity'));
                                                    $cost = $product->cost_price; // Average/Default cost price
                                                @endphp
                                                <tr>
                                                    <td class="fw-semibold">{{ $wh->code }}</td>
                                                    <td>{{ $wh->name }}</td>
                                                    <td class="fw-bold">{{ number_format($qty, 0) }}</td>
                                                    <td>₹{{ number_format($cost, 2) }}</td>
                                                    <td class="fw-bold">₹{{ number_format($qty * $cost, 2) }}</td>
                                                    <td>{{ number_format($product->reorder_point, 0) }}</td>
                                                </tr>
                                            @endforeach
                                        @else
                                            @forelse($product->warehouseStocks as $ws)
                                                <tr>
                                                    <td class="fw-semibold">{{ $ws->warehouse->code }}</td>
                                                    <td>{{ $ws->warehouse->name }}</td>
                                                    <td class="fw-bold text-success">{{ number_format($ws->quantity, 0) }}</td>
                                                    <td>₹{{ number_format($ws->unit_cost, 2) }}</td>
                                                    <td class="fw-bold">₹{{ number_format($ws->quantity * $ws->unit_cost, 2) }}</td>
                                                    <td>
                                                        {{ number_format($product->reorder_point, 0) }}
                                                        @if($ws->quantity <= $product->reorder_point)
                                                            <span class="badge bg-soft-danger text-danger ms-2">Reorder Alert</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="6" class="text-center py-4 text-muted">No stock data available in any warehouses. Set opening stocks.</td>
                                                </tr>
                                            @endforelse
                                        @endif
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

                    <!-- Tab 3: Variants -->
                    @if($product->variation_type === 'Variant')
                        <div class="tab-pane fade" id="variants-pane" role="tabpanel" aria-labelledby="variants-tab">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle">
                                    <thead class="table-light fs-11 text-uppercase fw-semibold text-muted">
                                        <tr>
                                            <th>Variant Details</th>
                                            <th>SKU Code</th>
                                            <th>Selling Price (₹)</th>
                                            <th>Cost Price (₹)</th>
                                            <th>Stock On Hand</th>
                                            <th class="text-end pe-4">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="fs-13 text-dark">
                                        @foreach($product->variants as $variant)
                                            <tr>
                                                <td class="fw-semibold">
                                                    {{ $variant->name }}
                                                </td>
                                                <td class="font-monospace">{{ $variant->sku }}</td>
                                                <td>₹{{ number_format($variant->selling_price, 2) }}</td>
                                                <td>₹{{ number_format($variant->cost_price, 2) }}</td>
                                                <td class="fw-bold">
                                                    {{ number_format($variant->total_stock, 0) }}
                                                    <span class="text-muted fs-11">/ {{ $product->uom ? $product->uom->code : 'pcs' }}</span>
                                                </td>
                                                <td class="text-end pe-4">
                                                    <div class="d-inline-flex gap-1 justify-content-end align-items-center">
                                                        <x-ui.icon-btn href="{{ route('inventory.products.show', $variant) }}" variant="soft-primary" icon="feather-eye" title="View Detail" />
                                                        <x-ui.icon-btn href="{{ route('inventory.products.edit', $variant) }}" variant="soft-info" icon="feather-edit" title="Edit" />
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
