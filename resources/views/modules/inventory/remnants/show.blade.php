@extends('layouts.duralux')

@section('title', 'Remnant ' . $remnant->remnant_code . ' | Inventory | SaaS ERP')
@section('page-title', 'Remnant Detail: ' . $remnant->remnant_code)
@section('breadcrumb', 'Remnant Details')

@section('page-actions')
    <x-ui.button href="{{ route('inventory.remnants.index') }}" variant="secondary" icon="feather-arrow-left" class="me-2">
        Back to List
    </x-ui.button>

    @if($remnant->status === 'pending_confirmation')
        <form method="POST" action="{{ route('inventory.remnants.confirm', $remnant->id) }}" class="d-inline me-2">
            @csrf
            <x-ui.button type="submit" variant="success" icon="feather-check-circle">
                Confirm & Release
            </x-ui.button>
        </form>
    @endif

    @if(in_array($remnant->status, ['available', 'partially_reserved']) && $remnant->available_quantity > 0)
        <x-ui.button type="button" variant="primary" icon="feather-scissors" class="me-2" data-bs-toggle="modal" data-bs-target="#splitRemnantModal">
            Split Remnant
        </x-ui.button>
    @endif

    @if($remnant->status !== 'scrapped' && $remnant->status !== 'consumed' && $remnant->reserved_quantity <= 0)
        <x-ui.button type="button" variant="danger" icon="feather-trash-2" data-bs-toggle="modal" data-bs-target="#scrapRemnantModal">
            Mark as Scrap
        </x-ui.button>
    @endif
@endsection

@section('content')
<div class="erp-single-panel bg-white text-dark">
    <!-- Top Details Header Grid (Matching BOM Module Style) -->
    <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom flex-wrap gap-2">
        <div>
            <h4 class="fw-bold text-dark font-monospace mb-1">{{ $remnant->remnant_code }}</h4>
            <div class="text-muted fs-12">
                Product: <strong>{{ $remnant->product->name }}</strong> (SKU: {{ $remnant->product->sku }}) | Age: {{ $remnant->age_days }} days
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            @php
                $badgeClass = match($remnant->status) {
                    'available' => 'bg-success text-white',
                    'partially_reserved' => 'bg-warning text-dark',
                    'fully_reserved' => 'bg-info text-white',
                    'pending_confirmation' => 'bg-danger text-white',
                    'consumed' => 'bg-secondary text-white',
                    'scrapped' => 'bg-dark text-white',
                    default => 'bg-light text-dark'
                };
            @endphp
            <span class="badge {{ $badgeClass }} fs-11 text-uppercase px-3 py-1.5">
                {{ str_replace('_', ' ', $remnant->status) }}
            </span>
            <span class="badge bg-light text-muted border fs-11 text-uppercase px-2.5 py-1.5">
                {{ $remnant->measurement_type }}
            </span>
        </div>
    </div>

    <!-- Physical Metrics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card bg-light border-0 shadow-none p-3 h-100">
                <span class="text-muted fs-11 text-uppercase fw-bold">Current Physical Measurement</span>
                <h4 class="fw-bold text-dark mt-2 mb-1">
                    @if($remnant->measurement_type === 'linear')
                        {{ number_format($remnant->current_length, 1) }} mm
                    @elseif($remnant->measurement_type === 'sheet')
                        {{ number_format($remnant->current_length, 0) }} × {{ number_format($remnant->current_width, 0) }} mm
                    @elseif($remnant->measurement_type === 'weight')
                        {{ number_format($remnant->weight, 3) }} {{ $remnant->weight_unit }}
                    @else
                        {{ $remnant->pieces }} pieces
                    @endif
                </h4>
                <span class="fs-11 text-muted">Initial: {{ number_format($remnant->initial_length ?? $remnant->initial_quantity, 1) }} ({{ $remnant->pieces }} pcs)</span>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card bg-light border-0 shadow-none p-3 h-100">
                <span class="text-muted fs-11 text-uppercase fw-bold">Available for Allocation</span>
                <h4 class="fw-bold text-success mt-2 mb-1">
                    @if($remnant->measurement_type === 'linear')
                        {{ number_format($remnant->available_length, 1) }} mm
                    @else
                        {{ number_format($remnant->available_quantity, 3) }}
                    @endif
                </h4>
                <span class="fs-11 text-muted">Reserved: {{ number_format($remnant->reserved_length ?? $remnant->reserved_quantity, 1) }}</span>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card bg-light border-0 shadow-none p-3 h-100">
                <span class="text-muted fs-11 text-uppercase fw-bold">Storage Location</span>
                <h5 class="fw-bold text-dark mt-2 mb-1">
                    {{ $remnant->warehouse->name ?? 'Default Warehouse' }}
                </h5>
                <span class="fs-11 text-muted">Rack / Bin: <strong>{{ $remnant->warehouse_location ?? 'Unassigned' }}</strong></span>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card bg-light border-0 shadow-none p-3 h-100">
                <span class="text-muted fs-11 text-uppercase fw-bold">Proportional Valuation</span>
                <h4 class="fw-bold text-dark font-monospace mt-2 mb-1">
                    ₹{{ number_format($remnant->total_valuation, 2) }}
                </h4>
                <span class="fs-11 text-muted">Unit Cost: ₹{{ number_format($remnant->unit_cost, 2) }} / canonical unit</span>
            </div>
        </div>
    </div>

    <!-- TAB NAVIGATION (Matching BOM Module) -->
    <x-ui.horizontal-tabs id="remnantDetailsTabs" :tabs="[
        ['id' => 'tab-overview', 'label' => 'Specifications & Lineage', 'active' => true, 'icon' => 'feather-info'],
        ['id' => 'tab-ledger', 'label' => 'Movement & Consumption Ledger', 'icon' => 'feather-clock'],
        ['id' => 'tab-allocations', 'label' => 'Production Allocations', 'icon' => 'feather-lock']
    ]" />

    <!-- TAB CONTENT CONTAINER -->
    <div class="tab-content mt-3">
        <!-- Tab 1: Specifications & Lineage -->
        <div class="tab-pane fade show active" id="tab-overview" role="tabpanel" aria-labelledby="tab-overview-tab">
            <div class="row g-4">
                <div class="col-md-6 border-end">
                    <h6 class="fw-bold text-dark mb-3 border-bottom pb-2">
                        <i class="feather-info me-2 text-primary"></i>Physical & Material Specifications
                    </h6>
                    <div class="row erp-form-row mb-2">
                        <div class="col-md-5"><span class="fw-semibold text-muted fs-13">Measurement Type:</span></div>
                        <div class="col-md-7"><span class="text-dark fw-bold fs-13 text-uppercase">{{ $remnant->measurement_type }}</span></div>
                    </div>
                    @if($remnant->current_length)
                        <div class="row erp-form-row mb-2">
                            <div class="col-md-5"><span class="fw-semibold text-muted fs-13">Length:</span></div>
                            <div class="col-md-7"><span class="text-dark fw-bold fs-13">{{ number_format($remnant->current_length, 2) }} {{ $remnant->dimension_unit }}</span></div>
                        </div>
                    @endif
                    @if($remnant->current_width)
                        <div class="row erp-form-row mb-2">
                            <div class="col-md-5"><span class="fw-semibold text-muted fs-13">Width:</span></div>
                            <div class="col-md-7"><span class="text-dark fw-bold fs-13">{{ number_format($remnant->current_width, 2) }} {{ $remnant->dimension_unit }}</span></div>
                        </div>
                    @endif
                    @if($remnant->thickness)
                        <div class="row erp-form-row mb-2">
                            <div class="col-md-5"><span class="fw-semibold text-muted fs-13">Thickness:</span></div>
                            <div class="col-md-7"><span class="text-dark fw-bold fs-13">{{ number_format($remnant->thickness, 2) }} {{ $remnant->dimension_unit }}</span></div>
                        </div>
                    @endif
                    @if($remnant->weight)
                        <div class="row erp-form-row mb-2">
                            <div class="col-md-5"><span class="fw-semibold text-muted fs-13">Weight:</span></div>
                            <div class="col-md-7"><span class="text-dark fw-bold fs-13">{{ number_format($remnant->weight, 3) }} {{ $remnant->weight_unit }}</span></div>
                        </div>
                    @endif
                    <div class="row erp-form-row mb-2">
                        <div class="col-md-5"><span class="fw-semibold text-muted fs-13">Canonical Quantity:</span></div>
                        <div class="col-md-7"><span class="text-dark fw-bold fs-13 font-monospace">{{ number_format($remnant->current_quantity, 4) }} {{ $remnant->product->uom?->code }}</span></div>
                    </div>
                </div>

                <div class="col-md-6">
                    <h6 class="fw-bold text-dark mb-3 border-bottom pb-2">
                        <i class="feather-git-branch me-2 text-primary"></i>Lineage & Traceability
                    </h6>
                    <div class="row erp-form-row mb-2">
                        <div class="col-md-5"><span class="fw-semibold text-muted fs-13">Parent Remnant:</span></div>
                        <div class="col-md-7">
                            @if($remnant->parentRemnant)
                                <a href="{{ route('inventory.remnants.show', $remnant->parentRemnant->id) }}" class="fw-bold text-primary font-monospace">
                                    {{ $remnant->parentRemnant->remnant_code }}
                                </a>
                            @else
                                <span class="text-muted">None (Original Offcut)</span>
                            @endif
                        </div>
                    </div>
                    <div class="row erp-form-row mb-2">
                        <div class="col-md-5"><span class="fw-semibold text-muted fs-13">Heat / Cast Number:</span></div>
                        <div class="col-md-7"><span class="text-dark fw-bold fs-13 font-monospace">{{ $remnant->heat_number ?? '—' }}</span></div>
                    </div>
                    <div class="row erp-form-row mb-2">
                        <div class="col-md-5"><span class="fw-semibold text-muted fs-13">Parent Inventory Batch:</span></div>
                        <div class="col-md-7"><span class="text-dark fw-bold fs-13">{{ $remnant->batch->batch_number ?? '—' }}</span></div>
                    </div>
                    <div class="row erp-form-row mb-2">
                        <div class="col-md-5"><span class="fw-semibold text-muted fs-13">Source Production Order:</span></div>
                        <div class="col-md-7">
                            @if($remnant->sourceOrder)
                                <span class="fw-bold text-dark">WO #{{ $remnant->sourceOrder->order_number }}</span>
                            @else
                                <span class="text-muted">Direct / Manual Registration</span>
                            @endif
                        </div>
                    </div>
                    <div class="row erp-form-row mb-2">
                        <div class="col-md-5"><span class="fw-semibold text-muted fs-13">Registered At:</span></div>
                        <div class="col-md-7"><span class="text-muted fs-13">{{ $remnant->created_at?->format('d M Y, H:i') }}</span></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab 2: Movement & Consumption Ledger -->
        <div class="tab-pane fade" id="tab-ledger" role="tabpanel" aria-labelledby="tab-ledger-tab">
            <h5 class="fw-bold text-dark mb-3">Immutable Movement & Consumption Ledger</h5>
            <div class="table-responsive">
                <x-ui.odoo-form-ui type="table">
                    <thead>
                        <tr>
                            <th style="width: 14%">Date & Time</th>
                            <th style="width: 12%">Event Type</th>
                            <th style="width: 10%" class="text-end">Qty Affected</th>
                            <th style="width: 10%" class="text-end">Length Affected</th>
                            <th style="width: 10%" class="text-end">Remaining Qty</th>
                            <th style="width: 10%" class="text-end">Remaining Length</th>
                            <th style="width: 12%">Destination / Child</th>
                            <th style="width: 10%">Order</th>
                            <th style="width: 12%">Performed By</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($remnant->consumptions as $entry)
                            <tr>
                                <td class="text-muted">{{ $entry->performed_at?->format('d M Y, H:i') }}</td>
                                <td>
                                    @if($entry->event_type === 'split')
                                        <span class="badge bg-warning-subtle text-warning fw-bold text-uppercase fs-10">Physical Split</span>
                                    @else
                                        <span class="badge bg-primary-subtle text-primary fw-bold text-uppercase fs-10">Production Consumption</span>
                                    @endif
                                </td>
                                <td class="text-end fw-bold font-monospace">{{ number_format($entry->consumed_quantity, 3) }}</td>
                                <td class="text-end text-muted">{{ $entry->consumed_length ? number_format($entry->consumed_length, 1) . ' mm' : '—' }}</td>
                                <td class="text-end fw-semibold font-monospace">{{ number_format($entry->remaining_quantity, 3) }}</td>
                                <td class="text-end text-muted">{{ $entry->remaining_length ? number_format($entry->remaining_length, 1) . ' mm' : '—' }}</td>
                                <td>
                                    @if($entry->splitRemnant)
                                        <a href="{{ route('inventory.remnants.show', $entry->splitRemnant->id) }}" class="fw-bold text-primary font-monospace">
                                            {{ $entry->splitRemnant->remnant_code }}
                                        </a>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if($entry->productionOrder)
                                        <span class="fw-semibold">WO #{{ $entry->productionOrder->order_number }}</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>{{ $entry->performedByUser->name ?? 'System' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted py-3">No movements or consumption events recorded yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-ui.odoo-form-ui>
            </div>
        </div>

        <!-- Tab 3: Production Allocations -->
        <div class="tab-pane fade" id="tab-allocations" role="tabpanel" aria-labelledby="tab-allocations-tab">
            <h5 class="fw-bold text-dark mb-3">Production Order Allocation Ownership</h5>
            <div class="table-responsive">
                <x-ui.odoo-form-ui type="table">
                    <thead>
                        <tr>
                            <th style="width: 10%">Allocation ID</th>
                            <th style="width: 18%">Production Order</th>
                            <th style="width: 12%" class="text-end">Allocated Qty</th>
                            <th style="width: 12%" class="text-end">Allocated Length</th>
                            <th style="width: 12%">Status</th>
                            <th style="width: 12%">Reserved At</th>
                            <th style="width: 12%">Consumed At</th>
                            <th style="width: 12%">Released At</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($remnant->allocations as $alloc)
                            <tr>
                                <td class="font-monospace">#{{ $alloc->id }}</td>
                                <td>
                                    <span class="fw-bold">WO #{{ $alloc->order->order_number ?? $alloc->production_order_id }}</span>
                                </td>
                                <td class="text-end fw-bold font-monospace">{{ number_format($alloc->allocated_quantity, 3) }}</td>
                                <td class="text-end text-muted">{{ $alloc->allocated_length ? number_format($alloc->allocated_length, 1) . ' mm' : '—' }}</td>
                                <td>
                                    @php
                                        $allocBadge = match($alloc->status) {
                                            'reserved' => 'bg-warning text-dark',
                                            'consumed' => 'bg-success text-white',
                                            'released' => 'bg-secondary text-white',
                                            default => 'bg-light text-dark'
                                        };
                                    @endphp
                                    <span class="badge {{ $allocBadge }} fs-10 text-uppercase">{{ $alloc->status }}</span>
                                </td>
                                <td class="text-muted">{{ $alloc->reserved_at?->format('d M Y, H:i') }}</td>
                                <td class="text-muted">{{ $alloc->consumed_at?->format('d M Y, H:i') ?? '—' }}</td>
                                <td class="text-muted">{{ $alloc->released_at?->format('d M Y, H:i') ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-3">No production order allocations recorded for this remnant.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </x-ui.odoo-form-ui>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Split Remnant (Rendered via Common modal and odoo-form-ui Components) -->
<x-ui.modal id="splitRemnantModal" title="<i class='feather-scissors me-2 text-primary'></i>Split Remnant #{{ $remnant->remnant_code }}" size="md" :centered="true" class="text-start">
    <form method="POST" action="{{ route('inventory.remnants.split', $remnant->id) }}" id="splitRemnantForm">
        @csrf
        <div class="alert alert-info py-2 fs-12 mb-3">
            Available: <strong>{{ number_format($remnant->available_length ?? $remnant->available_quantity, 1) }}</strong>
            {{ $remnant->measurement_type === 'linear' ? 'mm' : 'units' }}.
            Splitting divides physical stock into a new remnant asset without production consumption.
        </div>

        @if($remnant->measurement_type === 'linear')
            <div class="mb-3">
                <x-ui.odoo-form-ui type="input" inputType="number" label="Cut Off Length (mm)" name="split_length" step="0.1" max="{{ (float)$remnant->available_length - 1 }}" :required="true" />
                <span class="fs-11 text-muted d-block mt-1">Parent retains remainder; child receives cut length.</span>
            </div>
        @else
            <div class="mb-3">
                <x-ui.odoo-form-ui type="input" inputType="number" label="Quantity to Split Off" name="split_quantity" step="any" max="{{ (float)$remnant->available_quantity - 0.001 }}" :required="true" />
            </div>
        @endif

        <div class="mb-3">
            <x-ui.odoo-form-ui type="input" label="Child Remnant Rack / Location" name="warehouse_location" placeholder="e.g. Rack B-04" />
        </div>

        <div class="mb-3">
            <x-ui.odoo-form-ui type="textarea" label="Notes / Reason" name="notes" rows="2" placeholder="Reason for physical offcut split..." />
        </div>
    </form>
    <x-slot name="footer">
        <x-ui.button type="button" variant="secondary" data-bs-dismiss="modal">Cancel</x-ui.button>
        <x-ui.button type="button" variant="primary" onclick="document.getElementById('splitRemnantForm').submit();">
            Execute Split
        </x-ui.button>
    </x-slot>
</x-ui.modal>

<!-- Modal: Mark as Scrap (Rendered via Common modal and odoo-form-ui Components) -->
<x-ui.modal id="scrapRemnantModal" title="<i class='feather-trash-2 me-2 text-danger'></i>Mark Remnant as Unusable Scrap" size="md" :centered="true" class="text-start">
    <form method="POST" action="{{ route('inventory.remnants.scrap', $remnant->id) }}" id="scrapRemnantForm">
        @csrf
        <p class="fs-12 text-muted mb-3">
            This will permanently mark remnant <strong>{{ $remnant->remnant_code }}</strong> as scrapped and zero out available inventory.
        </p>
        <div class="mb-3">
            <x-ui.odoo-form-ui type="input" label="Scrap Reason" name="reason" placeholder="e.g. Severe corrosion, bent, unusable" :required="true" />
        </div>
    </form>
    <x-slot name="footer">
        <x-ui.button type="button" variant="secondary" data-bs-dismiss="modal">Cancel</x-ui.button>
        <x-ui.button type="button" variant="danger" onclick="document.getElementById('scrapRemnantForm').submit();">
            Confirm Scrap
        </x-ui.button>
    </x-slot>
</x-ui.modal>
@endsection
