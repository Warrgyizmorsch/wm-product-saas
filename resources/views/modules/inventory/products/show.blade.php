@extends('layouts.duralux')

@section('title', __('inventory.item_details') . ' | SaaS ERP')
@section('page-title', __('inventory.item_details'))
@section('breadcrumb', __('inventory.inventory_items_details'))

@section('page-actions')
    <div class="d-flex gap-2">
        <a href="{{ route('inventory.products.index') }}" class="btn btn-light">
            <i class="feather-arrow-left me-2"></i>{{ __('inventory.back') }}
        </a>
        @if($product->item_type === 'Goods')
            <a href="{{ route('inventory.products.opening-stock', $product) }}" class="btn btn-soft-warning">
                <i class="feather-package me-2"></i>{{ __('inventory.opening_stock') }}
            </a>
        @endif
        <a href="{{ route('inventory.products.edit', $product) }}" class="btn btn-primary">
            <i class="feather-edit me-2"></i>{{ __('inventory.edit_item') }}
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
                                    <span class="badge bg-soft-info text-info fs-11">{{ __('inventory.goods') }}</span>
                                @else
                                    <span class="badge bg-soft-warning text-warning fs-11">{{ __('inventory.service') }}</span>
                                @endif
                                <span class="text-muted">|</span>
                                @if($product->status === 'active')
                                    <span class="badge bg-soft-success text-success fs-11">{{ __('inventory.active') }}</span>
                                @else
                                    <span class="badge bg-soft-danger text-danger fs-11">{{ __('inventory.inactive') }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                    
                    <!-- Stock and Valuation Summary (Only for Goods) -->
                    @if($product->item_type === 'Goods')
                        <div class="d-flex gap-4 text-end mt-3 mt-md-0">
                            <div>
                                <span class="text-muted fs-11 text-uppercase d-block">{{ __('inventory.stock_on_hand') }}</span>
                                <h4 class="fw-bold text-dark mb-0">
                                    {{ number_format($product->total_stock, 0) }}
                                    <span class="fs-12 fw-normal text-muted">{{ $product->uom ? $product->uom->code : 'pcs' }}</span>
                                </h4>
                            </div>
                            <div class="border-start ps-4">
                                <span class="text-muted fs-11 text-uppercase d-block">{{ __('inventory.asset_value') }}</span>
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
                            <i class="feather-info me-1"></i>{{ __('inventory.overview') }}
                        </button>
                    </li>
                    @if($product->item_type === 'Goods')
                        <li class="nav-item" role="presentation">
                            <button class="nav-link fw-bold" id="stock-tab" data-bs-toggle="tab" data-bs-target="#stock-pane" type="button" role="tab" aria-controls="stock-pane" aria-selected="false">
                                <i class="feather-home me-1"></i>{{ __('inventory.warehouse_stock') }}
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link fw-bold" id="ledger-tab" data-bs-toggle="tab" data-bs-target="#ledger-pane" type="button" role="tab" aria-controls="ledger-pane" aria-selected="false">
                                <i class="feather-list me-1"></i>{{ __('inventory.stock_ledger') }}
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link fw-bold" id="reservations-tab" data-bs-toggle="tab" data-bs-target="#reservations-pane" type="button" role="tab" aria-controls="reservations-pane" aria-selected="false">
                                <i class="feather-bookmark me-1"></i>{{ __('inventory.reservations') }}
                            </button>
                        </li>
                        @if($product->track_serial_number)
                            <li class="nav-item" role="presentation">
                                <button class="nav-link fw-bold" id="serials-tab" data-bs-toggle="tab" data-bs-target="#serials-pane" type="button" role="tab" aria-controls="serials-pane" aria-selected="false">
                                    <i class="feather-hash me-1"></i>{{ __('inventory.tracked_serial_numbers') }}
                                </button>
                            </li>
                        @endif
                        @if($product->track_batch)
                            <li class="nav-item" role="presentation">
                                <button class="nav-link fw-bold" id="batches-tab" data-bs-toggle="tab" data-bs-target="#batches-pane" type="button" role="tab" aria-controls="batches-pane" aria-selected="false">
                                    <i class="feather-layers me-1"></i>{{ __('inventory.batch_log_tracking') }}
                                </button>
                            </li>
                        @endif
                    @endif
                    @if($product->variation_type === 'Variant')
                        <li class="nav-item" role="presentation">
                            <button class="nav-link fw-bold" id="variants-tab" data-bs-toggle="tab" data-bs-target="#variants-pane" type="button" role="tab" aria-controls="variants-pane" aria-selected="false">
                                <i class="feather-git-branch me-1"></i>{{ __('inventory.variants') }} ({{ $product->variants->count() }})
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
                                <h6 class="fw-bold text-primary mb-3">{{ __('inventory.item_specifications') }}</h6>
                                
                                <table class="table table-borderless align-middle mb-4">
                                    <tbody>
                                        <tr>
                                            <td class="text-muted" style="width: 140px;">{{ __('inventory.unit_of_measure') }}</td>
                                            <td class="fw-semibold">{{ $product->uom ? $product->uom->name . ' (' . $product->uom->code . ')' : '—' }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">{{ __('inventory.brand') }}</td>
                                            <td class="fw-semibold">{{ $product->brand ?: '—' }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">{{ __('inventory.manufacturer') }}</td>
                                            <td class="fw-semibold">{{ $product->manufacturer ?: '—' }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">{{ __('inventory.part_number_mpn') }}</td>
                                            <td class="fw-semibold font-monospace">{{ $product->mpn ?: '—' }}</td>
                                        </tr>
                                    </tbody>
                                </table>

                                <h6 class="fw-bold text-primary mb-3">{{ __('inventory.barcode_identifiers') }}</h6>
                                <table class="table table-borderless align-middle mb-4">
                                    <tbody>
                                        <tr>
                                            <td class="text-muted" style="width: 140px;">{{ __('inventory.barcode') }}</td>
                                            <td class="fw-semibold font-monospace">{{ $product->barcode ?: '—' }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">{{ __('inventory.upc') }}</td>
                                            <td class="fw-semibold font-monospace">{{ $product->upc ?: '—' }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">{{ __('inventory.ean') }}</td>
                                            <td class="fw-semibold font-monospace">{{ $product->ean ?: '—' }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">{{ __('inventory.isbn') }}</td>
                                            <td class="fw-semibold font-monospace">{{ $product->isbn ?: '—' }}</td>
                                        </tr>
                                    </tbody>
                                </table>

                                @if($product->description)
                                    <h6 class="fw-bold text-primary mb-2">{{ __('inventory.description_notes') }}</h6>
                                    <p class="text-muted bg-light p-3 rounded" style="white-space: pre-wrap;">{{ $product->description }}</p>
                                @endif
                            </div>

                            <!-- Right Column: Financial & Physical details -->
                            <div class="col-lg-6">
                                <h6 class="fw-bold text-primary mb-3">{{ __('inventory.sales_purchase_info') }}</h6>
                                <table class="table table-borderless align-middle mb-4">
                                    <tbody>
                                        <tr>
                                            <td class="text-muted" style="width: 140px;">{{ __('inventory.selling_price') }}</td>
                                            <td class="fw-bold text-success">₹{{ number_format($product->selling_price, 2) }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">{{ __('inventory.sales_account') }}</td>
                                            <td class="fw-semibold">{{ $product->sales_account ?: '—' }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">{{ __('inventory.purchase_cost') }}</td>
                                            <td class="fw-bold text-danger">₹{{ number_format($product->cost_price, 2) }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">{{ __('inventory.purchase_account') }}</td>
                                            <td class="fw-semibold">{{ $product->purchase_account ?: '—' }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">{{ __('inventory.hsn_sac_code') }}</td>
                                            <td class="fw-semibold font-monospace">{{ $product->hsn_sac ?: '—' }}</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">{{ __('inventory.gst_rate') }}</td>
                                            <td class="fw-semibold">{{ $product->gst_rate }}%</td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">{{ __('inventory.preferred_vendor') }}</td>
                                            <td class="fw-semibold">{{ $product->vendor ? $product->vendor->name : '—' }}</td>
                                        </tr>
                                    </tbody>
                                </table>

                                <h6 class="fw-bold text-primary mb-3">{{ __('inventory.dimensions_weight') }}</h6>
                                <table class="table table-borderless align-middle mb-4">
                                    <tbody>
                                        <tr>
                                            <td class="text-muted" style="width: 140px;">{{ __('inventory.dimensions_lwh') }}</td>
                                            <td class="fw-semibold">
                                                @if($product->length || $product->width || $product->height)
                                                    {{ $product->length ?: 0 }} x {{ $product->width ?: 0 }} x {{ $product->height ?: 0 }} {{ $product->dimension_unit }}
                                                @else
                                                    —
                                                @endif
                                            </td>
                                        </tr>
                                        <tr>
                                            <td class="text-muted">{{ __('inventory.weight') }}</td>
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
                                    <h6 class="fw-bold text-primary mb-3">{{ __('inventory.tracking_control') }}</h6>
                                    <table class="table table-borderless align-middle">
                                        <tbody>
                                            <tr>
                                                <td class="text-muted" style="width: 140px;">{{ __('inventory.valuation_method') }}</td>
                                                <td>
                                                    <span class="badge bg-soft-primary text-primary px-2 py-0.5 text-uppercase">
                                                        {{ $product->inventory_valuation_method ?? 'FIFO' }}
                                                    </span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted">{{ __('inventory.track_serials') }}</td>
                                                <td>
                                                    <span class="badge {{ $product->track_serial_number ? 'bg-soft-success text-success' : 'bg-soft-secondary text-secondary' }} px-2 py-0.5">
                                                        {{ $product->track_serial_number ? __('inventory.yes') : __('inventory.no') }}
                                                    </span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="text-muted">{{ __('inventory.track_batches') }}</td>
                                                <td>
                                                    <span class="badge {{ $product->track_batch ? 'bg-soft-success text-success' : 'bg-soft-secondary text-secondary' }} px-2 py-0.5">
                                                        {{ $product->track_batch ? __('inventory.yes') : __('inventory.no') }}
                                                    </span>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                @endif
                        </div>
                    </div>
                    </div> <!-- Close Tab 1: Overview Pane -->

                    <!-- Tab 2: Warehouse Stock -->
                    @if($product->item_type === 'Goods')
                        <div class="tab-pane fade" id="stock-pane" role="tabpanel" aria-labelledby="stock-tab">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <h6 class="fw-bold mb-0 text-dark">{{ __('inventory.stock_on_hand_by_warehouse') }}</h6>
                                    <p class="text-muted fs-12 mb-0">{{ __('inventory.opening_stock_levels_help') }}</p>
                                </div>
                                <a href="{{ route('inventory.products.opening-stock', $product) }}" class="btn btn-soft-warning btn-sm">
                                    <i class="feather-package me-1"></i>{{ __('inventory.update_opening_stock') }}
                                </a>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-bordered align-middle">
                                    <thead class="table-light text-muted">
                                        <tr>
                                            <th>{{ __('inventory.warehouse_code') }}</th>
                                            <th>{{ __('inventory.warehouse_name') }}</th>
                                            <th class="text-end">{{ __('inventory.stock_on_hand') }}</th>
                                            <th class="text-end">{{ __('inventory.reserved_stock') }}</th>
                                            <th class="text-end">{{ __('inventory.available_stock') }}</th>
                                            <th class="text-end">{{ __('inventory.valuation_cost') }}</th>
                                            <th class="text-end">{{ __('inventory.total_asset_value') }}</th>
                                            <th>{{ __('inventory.alert_reorder_level') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="fs-13 text-dark">
                                        @if($product->variation_type === 'Variant')
                                            <!-- Sum warehouse stock for child variants -->
                                            @foreach($warehouses as $wh)
                                                @php
                                                    $qty = $product->variants->sum(fn($v) => $v->warehouseStocks->where('warehouse_id', $wh->id)->sum('quantity'));
                                                    $reserved = $product->variants->sum(fn($v) => $v->warehouseStocks->where('warehouse_id', $wh->id)->sum('reserved_qty'));
                                                    $available = $product->variants->sum(fn($v) => $v->warehouseStocks->where('warehouse_id', $wh->id)->sum('available_qty'));
                                                    $cost = $product->cost_price; // Average/Default cost price
                                                @endphp
                                                <tr>
                                                    <td class="fw-semibold">{{ $wh->code }}</td>
                                                    <td>{{ $wh->name }}</td>
                                                    <td class="text-end fw-bold text-dark">{{ number_format($qty, 0) }}</td>
                                                    <td class="text-end text-warning fw-semibold">{{ number_format($reserved, 0) }}</td>
                                                    <td class="text-end text-success fw-bold">{{ number_format($available, 0) }}</td>
                                                    <td class="text-end">₹{{ number_format($cost, 2) }}</td>
                                                    <td class="text-end fw-bold">₹{{ number_format($qty * $cost, 2) }}</td>
                                                    <td>{{ number_format($product->reorder_point, 0) }}</td>
                                                </tr>
                                            @endforeach
                                        @else
                                            @forelse($product->warehouseStocks as $ws)
                                                <tr>
                                                    <td class="fw-semibold">{{ $ws->warehouse->code }}</td>
                                                    <td>{{ $ws->warehouse->name }}</td>
                                                    <td class="text-end fw-bold text-dark">{{ number_format($ws->quantity, 0) }}</td>
                                                    <td class="text-end text-warning fw-semibold">{{ number_format($ws->reserved_qty, 0) }}</td>
                                                    <td class="text-end text-success fw-bold">{{ number_format($ws->available_qty, 0) }}</td>
                                                    <td class="text-end">₹{{ number_format($ws->unit_cost, 2) }}</td>
                                                    <td class="text-end fw-bold">₹{{ number_format($ws->quantity * $ws->unit_cost, 2) }}</td>
                                                    <td>
                                                        {{ number_format($product->reorder_point, 0) }}
                                                        @if($ws->quantity <= $product->reorder_point)
                                                            <span class="badge bg-soft-danger text-danger ms-2">{{ __('inventory.reorder_alert') }}</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="8" class="text-center py-4 text-muted">{{ __('inventory.no_stock_data_available') }}</td>
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
                                            <th>{{ __('inventory.variant_details') }}</th>
                                            <th>{{ __('inventory.sku_code') }}</th>
                                            <th>{{ __('inventory.selling_price') }}</th>
                                            <th>{{ __('inventory.cost_price') }}</th>
                                            <th>{{ __('inventory.stock_on_hand') }}</th>
                                            <th class="text-end pe-4">{{ __('inventory.actions') }}</th>
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

                    <!-- Tab 4: Stock Ledger -->
                    @if($product->item_type === 'Goods')
                        <div class="tab-pane fade" id="ledger-pane" role="tabpanel" aria-labelledby="ledger-tab">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <h6 class="fw-bold mb-0 text-dark">{{ __('inventory.stock_ledger_valuation_log') }}</h6>
                                    <p class="text-muted fs-12 mb-0">{{ __('inventory.stock_ledger_help') }}</p>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-bordered align-middle">
                                    <thead class="table-light text-muted">
                                        <tr>
                                            <th>{{ __('inventory.date_time') }}</th>
                                            <th>{{ __('inventory.source_ref') }}</th>
                                            <th>{{ __('inventory.warehouse') }}</th>
                                            <th>{{ __('inventory.type') }}</th>
                                            <th class="text-end">{{ __('inventory.qty') }}</th>
                                            <th class="text-end">{{ __('inventory.rate') }}</th>
                                            <th class="text-end">{{ __('inventory.total') }}</th>
                                            <th class="text-end">{{ __('inventory.unconsumed_qty') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="fs-13 text-dark">
                                        @forelse($product->stockTransactions as $tx)
                                            <tr>
                                                <td>{{ $tx->created_at->format('Y-m-d h:i A') }}</td>
                                                <td>
                                                    <span class="fw-semibold text-primary">{{ $tx->source_type }}</span>
                                                    @if($tx->source_id)
                                                        <span class="text-muted font-monospace">#{{ $tx->source_id }}</span>
                                                    @endif
                                                </td>
                                                <td>{{ $tx->warehouse ? $tx->warehouse->name : 'N/A' }}</td>
                                                <td>
                                                    @if($tx->type === 'IN')
                                                        <span class="badge bg-soft-success text-success px-2 py-0.5">IN</span>
                                                    @else
                                                        <span class="badge bg-soft-danger text-danger px-2 py-0.5">OUT</span>
                                                    @endif
                                                </td>
                                                <td class="text-end fw-bold">{{ number_format($tx->quantity, 0) }}</td>
                                                <td class="text-end">₹{{ number_format($tx->unit_cost, 2) }}</td>
                                                <td class="text-end fw-bold">₹{{ number_format($tx->total_value, 2) }}</td>
                                                <td class="text-end">
                                                    @if($tx->type === 'IN')
                                                        @if($tx->balance_qty > 0)
                                                            <span class="badge bg-soft-primary text-primary">{{ number_format($tx->balance_qty, 0) }} {{ __('inventory.left') }}</span>
                                                        @else
                                                            <span class="badge bg-light text-muted">{{ __('inventory.depleted') }}</span>
                                                        @endif
                                                    @else
                                                        <span class="text-muted">&mdash;</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="8" class="text-center py-4 text-muted">{{ __('inventory.no_transactions_logged') }}</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif

                        <!-- Tab 5: Serial Numbers -->
                        @if($product->track_serial_number)
                            <div class="tab-pane fade" id="serials-pane" role="tabpanel" aria-labelledby="serials-tab">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div>
                                        <h6 class="fw-bold mb-0 text-dark">{{ __('inventory.tracked_serial_numbers') }}</h6>
                                        <p class="text-muted fs-12 mb-0">{{ __('inventory.serials_help') }}</p>
                                    </div>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-bordered align-middle">
                                        <thead class="table-light text-muted">
                                            <tr>
                                                <th>{{ __('inventory.serial_number') }}</th>
                                                <th>{{ __('inventory.status') }}</th>
                                                <th>{{ __('inventory.current_location') }}</th>
                                                <th>{{ __('inventory.batch_associated') }}</th>
                                                <th>{{ __('inventory.inbound_ref') }}</th>
                                                <th>{{ __('inventory.outbound_ref') }}</th>
                                                <th>{{ __('inventory.registered_at') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody class="fs-13 text-dark">
                                            @forelse($product->serialNumbers as $sn)
                                                <tr>
                                                    <td class="fw-bold text-dark font-monospace">{{ $sn->serial_number }}</td>
                                                    <td>
                                                        @if($sn->status === 'Available')
                                                            <span class="badge bg-soft-success text-success px-2 py-0.5">{{ __('inventory.available') }}</span>
                                                        @elseif($sn->status === 'Sold')
                                                            <span class="badge bg-soft-danger text-danger px-2 py-0.5">{{ __('inventory.sold') }}</span>
                                                        @else
                                                            <span class="badge bg-soft-warning text-warning px-2 py-0.5">{{ $sn->status }}</span>
                                                        @endif
                                                    </td>
                                                    <td>{{ $sn->warehouse ? $sn->warehouse->name : 'N/A' }}</td>
                                                    <td>{{ $sn->batch ? $sn->batch->batch_number : '&mdash;' }}</td>
                                                    <td>
                                                        @if($sn->transactionIn)
                                                            <span class="text-primary">{{ $sn->transactionIn->source_type }}</span>
                                                        @else
                                                            <span class="text-muted">&mdash;</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if($sn->transactionOut)
                                                            <span class="text-danger">{{ $sn->transactionOut->source_type }}</span>
                                                        @else
                                                            <span class="text-muted">&mdash;</span>
                                                        @endif
                                                    </td>
                                                    <td>{{ $sn->created_at->format('Y-m-d h:i A') }}</td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="7" class="text-center py-4 text-muted">{{ __('inventory.no_serial_numbers_registered') }}</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif

                        <!-- Tab 6: Batches -->
                        @if($product->track_batch)
                            <div class="tab-pane fade" id="batches-pane" role="tabpanel" aria-labelledby="batches-tab">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <div>
                                        <h6 class="fw-bold mb-0 text-dark">{{ __('inventory.batch_log_tracking') }}</h6>
                                        <p class="text-muted fs-12 mb-0">{{ __('inventory.batch_log_help') }}</p>
                                    </div>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-bordered align-middle">
                                        <thead class="table-light text-muted">
                                            <tr>
                                                <th>{{ __('inventory.batch_number') }}</th>
                                                <th>{{ __('inventory.mfg_date') }}</th>
                                                <th>{{ __('inventory.expiry_date') }}</th>
                                                <th>{{ __('inventory.total_inbound_transactions') }}</th>
                                                <th>{{ __('inventory.expiry_status') }}</th>
                                                <th>{{ __('inventory.created_at') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody class="fs-13 text-dark">
                                            @forelse($product->batches as $batch)
                                                <tr>
                                                    <td class="fw-bold text-dark font-monospace">{{ $batch->batch_number }}</td>
                                                    <td>{{ $batch->manufacturing_date ? $batch->manufacturing_date->format('Y-m-d') : '&mdash;' }}</td>
                                                    <td>{{ $batch->expiry_date ? $batch->expiry_date->format('Y-m-d') : '&mdash;' }}</td>
                                                    <td>{{ $batch->stockTransactions->count() }}</td>
                                                    <td>
                                                        @if($batch->expiry_date)
                                                            @if($batch->expiry_date->isPast())
                                                                 <span class="badge bg-soft-danger text-danger">{{ __('inventory.expired') }}</span>
                                                            @else
                                                                 <span class="badge bg-soft-success text-success">{{ __('inventory.good') }}</span>
                                                            @endif
                                                        @else
                                                            <span class="text-muted">&mdash;</span>
                                                        @endif
                                                    </td>
                                                    <td>{{ $batch->created_at->format('Y-m-d h:i A') }}</td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="6" class="text-center py-4 text-muted">{{ __('inventory.no_batches_tracked') }}</td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif

                        <!-- Tab 7: Reservations -->
                        <div class="tab-pane fade" id="reservations-pane" role="tabpanel" aria-labelledby="reservations-tab">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div>
                                    <h6 class="fw-bold mb-0 text-dark">{{ __('inventory.active_stock_reservations') }}</h6>
                                    <p class="text-muted fs-12 mb-0">{{ __('inventory.reservations_help') }}</p>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-bordered align-middle">
                                    <thead class="table-light text-muted">
                                        <tr>
                                            <th>{{ __('inventory.date') }}</th>
                                            <th>{{ __('inventory.reserved_for') }}</th>
                                            <th>{{ __('inventory.warehouse') }}</th>
                                            <th class="text-end">{{ __('inventory.reserved_qty') }}</th>
                                            <th>{{ __('inventory.status') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="fs-13 text-dark">
                                        @forelse($product->stockReservations as $res)
                                            <tr>
                                                <td>{{ $res->created_at->format('Y-m-d h:i A') }}</td>
                                                <td>
                                                    <span class="fw-semibold text-primary">{{ $res->reference_type }}</span>
                                                    <span class="text-muted font-monospace">#{{ $res->reference_id }}</span>
                                                </td>
                                                <td>{{ $res->warehouse ? $res->warehouse->name : 'N/A' }}</td>
                                                <td class="text-end fw-bold text-warning">{{ number_format($res->reserved_qty, 0) }}</td>
                                                <td>
                                                    @if($res->status === 'Active')
                                                        <span class="badge bg-soft-warning text-warning">{{ __('inventory.active_hold') }}</span>
                                                    @else
                                                        <span class="badge bg-soft-success text-success">{{ $res->status }}</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="text-center py-4 text-muted">{{ __('inventory.no_active_reservations') }}</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
@endsection
