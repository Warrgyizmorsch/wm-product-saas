@extends('layouts.duralux')

@section('title', 'Material Requirements Shortage & Procurement | SaaS ERP')
@section('page-title', 'Material Requirements Shortage & Procurement')
@section('breadcrumb', 'Store / MRP & Shortage Analysis')

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <x-ui.button href="{{ route('inventory.material-requirements.index') }}" variant="light" size="sm" class="border shadow-sm" icon="feather-arrow-left">
            Material Requirements
        </x-ui.button>
        <x-ui.button href="{{ route('purchase.requisitions.index') }}" variant="light" size="sm" class="border shadow-sm" icon="feather-file-text">
            Purchase Requisitions
        </x-ui.button>
    </div>
@endsection

@section('content')
<div class="erp-single-panel">

    <x-ui.odoo-form-ui type="sheet">

        {{-- Header Bar --}}
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 pb-2 mb-3 border-bottom">
            <div>
                <span class="fs-10 text-muted text-uppercase fw-bold d-block letter-spacing-1">Material Requisition Procurement Analysis</span>
                <div class="d-flex align-items-center gap-2">
                    <h5 class="fw-bold text-dark mb-0">Demanded vs Available Stock Shortage Summary</h5>
                    @php
                        $shortageCount = $calculationResult ? count(array_filter($calculationResult['consolidated'], fn($i) => $i['net_shortage_qty'] > 0)) : 0;
                    @endphp
                    @if($shortageCount > 0)
                        <x-ui.badge :soft="true" variant="danger" class="px-2 py-0.5 fs-10 fw-bold">
                            {{ $shortageCount }} Shortage Item(s)
                        </x-ui.badge>
                    @else
                        <x-ui.badge :soft="true" variant="success" class="px-2 py-0.5 fs-10 fw-bold">
                            Sufficient Stock Available
                        </x-ui.badge>
                    @endif
                </div>
            </div>
        </div>

        @if($calculationResult)
            @php
                $summary = $calculationResult['summary'];
                $consolidated = $calculationResult['consolidated'];
            @endphp

            {{-- Compact KPI Summary Row --}}
            <div class="row g-2 mb-3">
                <div class="col-md-3">
                    <div class="py-2 px-3 bg-light rounded border text-center">
                        <span class="text-muted fs-10 text-uppercase fw-bold d-block">Demanded Items</span>
                        <h5 class="fw-bold text-dark mb-0">{{ $summary['total_demanded_items'] }}</h5>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="py-2 px-3 bg-soft-info rounded border border-info-subtle text-center">
                        <span class="text-muted fs-10 text-uppercase fw-bold d-block">Mfg Products</span>
                        <h5 class="fw-bold text-info mb-0">{{ $summary['mfg_products_count'] }}</h5>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="py-2 px-3 bg-soft-danger rounded border border-danger-subtle text-center">
                        <span class="text-muted fs-10 text-uppercase fw-bold d-block">Shortage Items</span>
                        <h5 class="fw-bold text-danger mb-0">{{ $summary['shortage_items_count'] }}</h5>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="py-2 px-3 bg-soft-success rounded border border-success-subtle text-center">
                        <span class="text-muted fs-10 text-uppercase fw-bold d-block">Est. PR Cost</span>
                        <h5 class="fw-bold text-success mb-0">{{ format_currency($summary['estimated_pr_total_cost']) }}</h5>
                    </div>
                </div>
            </div>

            @if(empty($consolidated))
                <div class="card border bg-light shadow-none p-4 rounded text-center my-3">
                    <i class="feather-check-circle fs-28 text-muted mb-2 d-block"></i>
                    <h6 class="fw-bold text-dark mb-1">All Components Available in Stock!</h6>
                    <p class="mb-0 text-muted fs-11">There are no material shortages for the pending MRs across the warehouse.</p>
                </div>
            @else
                <form method="POST" action="{{ route('inventory.mrp-shortage.generate-pr') }}">
                    @csrf
                    <input type="hidden" name="warehouse_id" value="{{ $selectedWarehouseId ?: $defaultWarehouseId }}">

                    {{-- Table Header with Store Filter Dropdown & Short Action Button --}}
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                        <div>
                            <h6 class="fw-bold text-dark mb-0">
                                <i class="feather-layers text-primary me-1 fs-14"></i>Demanded vs Available Material Shortages
                            </h6>
                            <span class="text-muted fs-11">
                                Scope: <strong>ALL Pending MRs ({{ $pendingMrs->count() }})</strong> | Showing <strong>{{ count($consolidated) }}</strong> of <strong>{{ $totalResults }}</strong> item(s) (Page {{ $currentPage }} of {{ $totalPages }})
                            </span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <label class="form-label fs-11 fw-bold text-muted text-uppercase mb-0 text-nowrap">Filter Store:</label>
                            <select class="form-select form-select-sm erp-premium-select bg-white shadow-sm" style="min-width: 220px;" onchange="window.location.href='{{ route('inventory.mrp-shortage.index') }}?warehouse_id=' + this.value">
                                <option value="">All Warehouses (Consolidated Stock)</option>
                                @foreach($warehouses as $wh)
                                    <option value="{{ $wh->id }}" {{ $selectedWarehouseId == $wh->id ? 'selected' : '' }}>
                                        {{ $wh->name }} {{ $wh->is_default ? '(Default Store)' : '' }}
                                    </option>
                                @endforeach
                            </select>
                            <x-ui.button type="submit" variant="success" size="sm" class="fw-bold px-3 py-1.5 shadow-sm text-nowrap" icon="feather-shopping-cart">
                                Generate PR
                            </x-ui.button>
                        </div>
                    </div>

                    {{-- Main Flat Modern Table --}}
                    <div class="table-responsive mb-3" style="max-width: 100%;">
                        <x-ui.odoo-form-ui type="table">
                            <thead class="bg-light text-uppercase fs-10 fw-bold text-muted border-bottom">
                                <tr>
                                    <th class="text-center py-2" style="width: 3%">
                                        <input type="checkbox" id="checkAllPr" class="form-check-input" checked onclick="toggleCheckAll(this)">
                                    </th>
                                    <th class="py-2" style="width: 28%">Component / Material Product</th>
                                    <th class="text-center py-2" style="width: 10%">Demanded</th>
                                    <th class="text-center py-2" style="width: 8%">Reserved</th>
                                    <th class="text-center py-2" style="width: 9%">Available</th>
                                    <th class="text-center text-primary py-2" style="width: 17%">PR Pipeline (Raised / Approved)</th>
                                    <th class="text-center text-danger py-2" style="width: 10%">Net Shortage</th>
                                    <th class="text-center py-2" style="width: 9%">PR Qty to Order</th>
                                    <th class="text-end py-2" style="width: 6%">Est. Cost</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($consolidated as $idx => $row)
                                    @php
                                        $hasShortage = (float)$row['net_shortage_qty'] > 0;
                                    @endphp
                                    <tr class="py-1 border-bottom">
                                        <td class="text-center">
                                            <input type="hidden" name="items[{{ $idx }}][product_id]" value="{{ $row['product_id'] }}">
                                            <input type="hidden" name="items[{{ $idx }}][unit_cost]" value="{{ $row['unit_cost'] }}">
                                            <input type="hidden" name="items[{{ $idx }}][shortage_qty]" value="{{ $row['net_shortage_qty'] }}">
                                            <input type="checkbox" name="items[{{ $idx }}][selected]" value="1" class="form-check-input pr-checkbox" {{ $hasShortage ? 'checked' : '' }}>
                                        </td>
                                        <td>
                                            <div class="fw-bold text-dark fs-12 leading-tight">{{ $row['product_name'] }}</div>
                                            <div class="text-muted fs-10">
                                                SKU: <span class="font-monospace">{{ $row['sku'] }}</span> | 
                                                <span class="text-capitalize">{{ ucfirst(str_replace('_', ' ', $row['type'])) }}</span>
                                            </div>
                                            @if(!empty($row['sources']))
                                                <div class="fs-10 text-primary">Ref: {{ implode(', ', $row['sources']) }}</div>
                                            @endif
                                            @if(!empty($row['warehouse_breakdown']))
                                                <div class="fs-10 text-muted mt-0.5">
                                                    @foreach($row['warehouse_breakdown'] as $whB)
                                                        <span class="text-secondary fs-10 me-2" title="OnHand: {{ number_format($whB['on_hand'], 2) }} | Reserved: {{ number_format($whB['reserved'], 2) }}">
                                                            {{ $whB['warehouse_name'] }}: <strong class="text-dark">{{ number_format($whB['available'], 2) }} {{ $row['uom_code'] }}</strong>
                                                        </span>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </td>
                                        <td class="text-center fw-semibold fs-11">{{ number_format($row['required_qty'], 2) }} {{ $row['uom_code'] }}</td>
                                        <td class="text-center text-muted fs-11">{{ number_format($row['reserved_qty'], 2) }} {{ $row['uom_code'] }}</td>
                                        <td class="text-center fw-semibold fs-11 {{ $row['available_qty'] <= 0 ? 'text-danger' : 'text-dark' }}">
                                            {{ number_format($row['available_qty'], 2) }} {{ $row['uom_code'] }}
                                        </td>
                                        <td class="text-center fs-11">
                                            @if(($row['pr_approved_qty'] ?? 0) > 0)
                                                <div class="mb-1">
                                                    <div class="fw-bold font-monospace text-success fs-11">{{ number_format($row['pr_approved_qty'], 2) }} {{ $row['uom_code'] }}</div>
                                                    <div class="fs-9 text-uppercase fw-semibold text-success" style="letter-spacing: 0.3px;">Approved</div>
                                                </div>
                                            @endif
                                            @if(($row['pr_draft_qty'] ?? 0) > 0)
                                                <div>
                                                    <div class="fw-bold font-monospace text-warning fs-11">{{ number_format($row['pr_draft_qty'], 2) }} {{ $row['uom_code'] }}</div>
                                                    <div class="fs-9 text-uppercase fw-semibold text-warning" style="letter-spacing: 0.3px;">Draft / Pending</div>
                                                </div>
                                            @endif
                                            @if(($row['pr_approved_qty'] ?? 0) <= 0 && ($row['pr_draft_qty'] ?? 0) <= 0)
                                                <span class="text-muted fs-11 font-monospace">0.00</span>
                                            @endif
                                        </td>
                                        <td class="text-center fw-bold">
                                            @if($hasShortage)
                                                <span class="text-danger font-monospace fs-11">
                                                    {{ number_format($row['net_shortage_qty'], 2) }} {{ $row['uom_code'] }}
                                                </span>
                                            @else
                                                <span class="text-success font-monospace fs-11">
                                                    0.00
                                                </span>
                                            @endif
                                        </td>
                                        <td class="text-center align-middle" style="min-width: 110px;">
                                            <div class="mb-0">
                                                <x-ui.input 
                                                    type="number" 
                                                    step="0.0001" 
                                                    min="0" 
                                                    name="items[{{ $idx }}][quantity]" 
                                                    class="mb-0 form-control-sm text-center fw-bold text-primary py-0.5 px-1 mx-auto shadow-none" 
                                                    style="width: 105px; font-size: 12px;"
                                                    placeholder="{{ number_format($row['net_shortage_qty'], 2) }}" 
                                                />
                                            </div>
                                        </td>
                                        <td class="text-end fw-semibold text-dark fs-11">
                                            {{ format_currency($row['total_cost']) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </x-ui.odoo-form-ui>
                    </div>

                    {{-- Common Component Pagination Below Table --}}
                    @if($totalPages > 1)
                        <div class="mt-2 mb-3">
                            <x-ui.pagination 
                                :currentPage="$currentPage" 
                                :totalPages="$totalPages" 
                                :totalResults="$totalResults" 
                                :perPage="10" 
                                pageParam="page" 
                            />
                        </div>
                    @endif
                </form>
            @endif

        @endif

    </x-ui.odoo-form-ui>
</div>

<script>
    function toggleCheckAll(master) {
        const checkboxes = document.querySelectorAll('.pr-checkbox');
        checkboxes.forEach(cb => {
            if (!cb.disabled) {
                cb.checked = master.checked;
            }
        });
    }
</script>
@endsection
