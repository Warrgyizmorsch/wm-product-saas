@extends('layouts.duralux')

@section('title', 'Remnant & Offcut Inventory | Inventory | SaaS ERP')
@section('page-title', 'Reusable Remnants & Offcuts')
@section('breadcrumb', 'Inventory Remnants')

@section('content')
<div class="erp-single-panel bg-white text-dark">
    <!-- Header & Controls Toolbar -->
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4 pb-3 border-bottom">
        <div>
            <h4 class="fw-bold text-dark mb-1">
                <i class="feather-scissors text-primary me-2"></i>Reusable Remnant & Offcut Inventory
            </h4>
            <small class="text-muted fs-12">Track physical dimensions, heat/batch lineage, reservation locks, and reusable offcuts available for production.</small>
        </div>

        <div class="d-flex gap-2 ms-auto align-items-center flex-wrap">
            <!-- Search & Filters Toolbar (Matching BOM Module Layout) -->
            <form method="GET" action="{{ route('inventory.remnants.index') }}" class="d-flex align-items-center gap-2">
                @foreach(request()->except(['search', 'page']) as $k => $v)
                    @if($v) <input type="hidden" name="{{ $k }}" value="{{ $v }}"> @endif
                @endforeach
                <div style="min-width: 240px;">
                    <x-ui.odoo-form-ui 
                        type="input" 
                        name="search" 
                        placeholder="Search code, rack, heat..." 
                        value="{{ request('search') }}" 
                    />
                </div>
                <x-ui.button type="submit" variant="primary" icon="feather-search" size="sm">
                    Search
                </x-ui.button>
            </form>

            <form method="GET" action="{{ route('inventory.remnants.index') }}" class="d-inline">
                <x-ui.filter label="Filter" offset="0, 5">
                    <h6 class="fw-bold text-dark fs-12 mb-3"><i class="feather-sliders me-1 text-primary"></i> Filter Remnants</h6>

                    <div class="mb-3">
                        <x-ui.odoo-form-ui type="select" label="Product" name="product_id" :searchable="false">
                            <option value="">All Products</option>
                            @foreach($products as $p)
                                <option value="{{ $p->id }}" {{ request('product_id') == $p->id ? 'selected' : '' }}>
                                    {{ $p->name }} ({{ $p->sku }})
                                </option>
                            @endforeach
                        </x-ui.odoo-form-ui>
                    </div>

                    <div class="mb-3">
                        <x-ui.odoo-form-ui type="select" label="Measurement Type" name="measurement_type" :searchable="false">
                            <option value="">All Types</option>
                            <option value="linear" {{ request('measurement_type') === 'linear' ? 'selected' : '' }}>Linear (Length)</option>
                            <option value="sheet" {{ request('measurement_type') === 'sheet' ? 'selected' : '' }}>Sheet (Area)</option>
                            <option value="weight" {{ request('measurement_type') === 'weight' ? 'selected' : '' }}>Weight (Kg/g)</option>
                            <option value="count" {{ request('measurement_type') === 'count' ? 'selected' : '' }}>Count (Pieces)</option>
                        </x-ui.odoo-form-ui>
                    </div>

                    <div class="mb-3">
                        <x-ui.odoo-form-ui type="select" label="Warehouse" name="warehouse_id" :searchable="false">
                            <option value="">All Warehouses</option>
                            @foreach($warehouses as $wh)
                                <option value="{{ $wh->id }}" {{ request('warehouse_id') == $wh->id ? 'selected' : '' }}>
                                    {{ $wh->name }}
                                </option>
                            @endforeach
                        </x-ui.odoo-form-ui>
                    </div>

                    <div class="mb-3">
                        <x-ui.odoo-form-ui type="select" label="Status" name="status" :searchable="false">
                            <option value="">All Statuses</option>
                            <option value="available" {{ request('status') === 'available' ? 'selected' : '' }}>Available</option>
                            <option value="partially_reserved" {{ request('status') === 'partially_reserved' ? 'selected' : '' }}>Partially Reserved</option>
                            <option value="fully_reserved" {{ request('status') === 'fully_reserved' ? 'selected' : '' }}>Fully Reserved</option>
                            <option value="consumed" {{ request('status') === 'consumed' ? 'selected' : '' }}>Consumed</option>
                            <option value="scrapped" {{ request('status') === 'scrapped' ? 'selected' : '' }}>Scrapped</option>
                            <option value="pending_confirmation" {{ request('status') === 'pending_confirmation' ? 'selected' : '' }}>Pending Confirmation</option>
                        </x-ui.odoo-form-ui>
                    </div>

                    <div class="d-flex gap-2 justify-content-end mt-4">
                        <x-ui.button href="{{ route('inventory.remnants.index') }}" variant="secondary" size="sm">
                            Reset
                        </x-ui.button>
                        <x-ui.button type="submit" variant="primary" size="sm" icon="feather-filter">
                            Apply Filters
                        </x-ui.button>
                    </div>
                </x-ui.filter>
            </form>
        </div>
    </div>

    <!-- Remnants Data Table (Rendered via Common odoo-form-ui Table Component) -->
    <div class="table-responsive">
        <x-ui.odoo-form-ui type="table">
            <thead>
                <tr>
                    <th style="width: 12%">Remnant Code</th>
                    <th style="width: 18%">Product / Material</th>
                    <th style="width: 8%">Type</th>
                    <th style="width: 16%">Physical Measurements</th>
                    <th style="width: 10%">Available</th>
                    <th style="width: 10%">Location</th>
                    <th style="width: 10%">Lineage</th>
                    <th style="width: 8%">Valuation</th>
                    <th style="width: 8%">Status</th>
                    <th style="width: 5%">Age</th>
                    <th class="text-end" style="width: 7%">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($remnants as $rem)
                    <tr>
                        <td>
                            <a href="{{ route('inventory.remnants.show', $rem->id) }}" class="fw-bold text-primary font-monospace">
                                {{ $rem->remnant_code }}
                            </a>
                        </td>
                        <td>
                            <div class="fw-semibold text-dark">{{ $rem->product->name ?? '—' }}</div>
                            <span class="fs-11 text-muted font-monospace">{{ $rem->product->sku ?? '' }}</span>
                        </td>
                        <td>
                            <span class="badge bg-secondary-subtle text-secondary text-uppercase fs-10">
                                {{ $rem->measurement_type }}
                            </span>
                        </td>
                        <td>
                            @if($rem->measurement_type === 'linear')
                                <span class="fw-bold text-dark">{{ number_format($rem->current_length, 1) }} mm</span>
                                @if($rem->pieces > 1) <span class="text-muted">({{ $rem->pieces }} pcs)</span> @endif
                            @elseif($rem->measurement_type === 'sheet')
                                <span class="fw-bold text-dark">{{ number_format($rem->current_length, 0) }} × {{ number_format($rem->current_width, 0) }} mm</span>
                                @if($rem->thickness) <span class="text-muted">({{ $rem->thickness }}mm)</span> @endif
                            @elseif($rem->measurement_type === 'weight')
                                <span class="fw-bold text-dark">{{ number_format($rem->weight, 3) }} {{ $rem->weight_unit }}</span>
                            @else
                                <span class="fw-bold text-dark">{{ $rem->pieces }} pcs</span>
                            @endif
                            <div class="fs-11 text-muted">{{ number_format($rem->current_quantity, 3) }} canonical</div>
                        </td>
                        <td>
                            @if($rem->measurement_type === 'linear')
                                <span class="fw-bold text-success">{{ number_format($rem->available_length, 1) }} mm</span>
                            @else
                                <span class="fw-bold text-success">{{ number_format($rem->available_quantity, 3) }}</span>
                            @endif
                            @if($rem->reserved_quantity > 0)
                                <div class="fs-10 text-warning">Res: {{ number_format($rem->reserved_quantity, 2) }}</div>
                            @endif
                        </td>
                        <td>
                            <div class="text-dark">{{ $rem->warehouse->name ?? 'Default Store' }}</div>
                            @if($rem->warehouse_location)
                                <span class="badge bg-light text-dark border fs-10"><i class="feather-map-pin me-1"></i>{{ $rem->warehouse_location }}</span>
                            @endif
                        </td>
                        <td>
                            @if($rem->heat_number)
                                <span class="badge bg-info-subtle text-info fs-10">Heat: {{ $rem->heat_number }}</span>
                            @elseif($rem->batch)
                                <span class="badge bg-light text-muted border fs-10">Lot: {{ $rem->batch->batch_number }}</span>
                            @elseif($rem->sourceOrder)
                                <span class="fs-11 text-muted">WO #{{ $rem->sourceOrder->order_number }}</span>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                        <td>
                            <div class="font-monospace fw-semibold text-dark">₹{{ number_format($rem->total_valuation, 2) }}</div>
                            <span class="fs-10 text-muted">@ ₹{{ number_format($rem->unit_cost, 2) }}/u</span>
                        </td>
                        <td>
                            @php
                                $badgeClass = match($rem->status) {
                                    'available' => 'bg-success text-white',
                                    'partially_reserved' => 'bg-warning text-dark',
                                    'fully_reserved' => 'bg-info text-white',
                                    'pending_confirmation' => 'bg-danger text-white',
                                    'consumed' => 'bg-secondary text-white',
                                    'scrapped' => 'bg-dark text-white',
                                    default => 'bg-light text-dark'
                                };
                            @endphp
                            <span class="badge {{ $badgeClass }} fs-10 text-uppercase">
                                {{ str_replace('_', ' ', $rem->status) }}
                            </span>
                        </td>
                        <td>
                            <span class="text-muted fs-11">{{ $rem->age_days }}d</span>
                        </td>
                        <td class="text-end">
                            <x-ui.button href="{{ route('inventory.remnants.show', $rem->id) }}" variant="soft-primary" class="px-2 py-1 fs-11 text-nowrap" icon="feather-eye">
                                View
                            </x-ui.button>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11" class="text-center py-4 text-muted">
                            <i class="feather-inbox fs-3 d-block mb-2 text-muted"></i>
                            No remnants or offcuts matching your criteria.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </x-ui.odoo-form-ui>
    </div>

    <!-- Pagination -->
    <div class="mt-3">
        {{ $remnants->links() }}
    </div>
</div>
@endsection
