@extends('layouts.duralux')

@section('title', __('crm.mrp_shortage_title') . ' | SaaS ERP')
@section('page-title', __('crm.mrp_shortage_title'))
@section('breadcrumb', __('inventory.store') . ' / ' . __('crm.mrp_shortage_analysis'))

@section('page-actions')
    <div class="d-flex align-items-center gap-2">
        <x-ui.button href="{{ route('inventory.material-requirements.index') }}" variant="light" class="border shadow-sm" icon="feather-arrow-left">
            {{ __('crm.material_requirements') }}
        </x-ui.button>
        <x-ui.button href="{{ route('purchase.requisitions.index') }}" variant="light" class="border shadow-sm" icon="feather-file-text">
            {{ __('crm.purchase_requisitions') }}
        </x-ui.button>
    </div>
@endsection

@section('content')
<div class="erp-single-panel">

    <x-ui.odoo-form-ui type="sheet">

        {{-- Header Bar --}}
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 pb-2 mb-3 border-bottom">
            <div>
                <span class="fs-10 text-muted text-uppercase fw-bold d-block letter-spacing-1">{{ __('crm.mr_procurement_analysis') }}</span>
                <div class="d-flex align-items-center gap-2">
                    <h5 class="fw-bold text-dark mb-0">{{ __('crm.demanded_vs_available_summary') }}</h5>
                    @php
                        $shortageCount = $calculationResult ? count(array_filter($calculationResult['consolidated'], fn($i) => $i['net_shortage_qty'] > 0)) : 0;
                    @endphp
                    @if($shortageCount > 0)
                        <x-ui.badge :soft="true" variant="danger" class="px-2 py-0.5 fs-10 fw-bold">
                            {{ $shortageCount }} {{ __('crm.shortage_items_badge') }}
                        </x-ui.badge>
                    @else
                        <x-ui.badge :soft="true" variant="success" class="px-2 py-0.5 fs-10 fw-bold">
                            {{ __('crm.sufficient_stock_badge') }}
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
                        <span class="text-muted fs-10 text-uppercase fw-bold d-block">{{ __('crm.demanded_items') }}</span>
                        <h5 class="fw-bold text-dark mb-0">{{ $summary['total_demanded_items'] }}</h5>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="py-2 px-3 bg-soft-info rounded border border-info-subtle text-center">
                        <span class="text-muted fs-10 text-uppercase fw-bold d-block">{{ __('crm.mfg_products') }}</span>
                        <h5 class="fw-bold text-info mb-0">{{ $summary['mfg_products_count'] }}</h5>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="py-2 px-3 bg-soft-danger rounded border border-danger-subtle text-center">
                        <span class="text-muted fs-10 text-uppercase fw-bold d-block">{{ __('crm.shortage_items') }}</span>
                        <h5 class="fw-bold text-danger mb-0">{{ $summary['shortage_items_count'] }}</h5>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="py-2 px-3 bg-soft-success rounded border border-success-subtle text-center">
                        <span class="text-muted fs-10 text-uppercase fw-bold d-block">{{ __('crm.est_pr_cost') }}</span>
                        <h5 class="fw-bold text-success mb-0">{{ format_currency($summary['estimated_pr_total_cost']) }}</h5>
                    </div>
                </div>
            </div>

            @if(empty($consolidated))
                <div class="card border bg-light shadow-none p-4 rounded text-center my-3">
                    <i class="feather-check-circle fs-28 text-muted mb-2 d-block"></i>
                    <h6 class="fw-bold text-dark mb-1">{{ __('crm.all_components_available') }}</h6>
                    <p class="mb-0 text-muted fs-11">{{ __('crm.no_material_shortages_desc') }}</p>
                </div>
            @else
                <form method="POST" action="{{ route('inventory.mrp-shortage.generate-pr') }}">
                    @csrf
                    <input type="hidden" name="warehouse_id" value="{{ $selectedWarehouseId ?: $defaultWarehouseId }}">

                    {{-- Table Header with Store Filter Dropdown & Short Action Button --}}
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                        <div>
                            <h6 class="fw-bold text-dark mb-0">
                                <i class="feather-layers text-primary me-1 fs-14"></i>{{ __('crm.demanded_vs_available_shortages') }}
                            </h6>
                            <span class="text-muted fs-11">
                                {{ __('crm.scope') }} <strong>{{ __('crm.all_pending_mrs') }} ({{ $pendingMrs->count() }})</strong> | {{ __('crm.showing') }} <strong>{{ count($consolidated) }}</strong> {{ __('crm.of') }} <strong>{{ $totalResults }}</strong> {{ __('crm.items_count_label') }} ({{ __('crm.page') }} {{ $currentPage }} {{ __('crm.of') }} {{ $totalPages }})
                            </span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <label class="form-label fs-11 fw-bold text-muted text-uppercase mb-0 text-nowrap">{{ __('crm.filter_store') }}</label>
                            <select class="form-select form-select-sm erp-premium-select bg-white shadow-sm" style="min-width: 220px;" onchange="window.location.href='{{ route('inventory.mrp-shortage.index') }}?warehouse_id=' + this.value">
                                <option value="">{{ __('crm.all_warehouses_consolidated') }}</option>
                                @foreach($warehouses as $wh)
                                    <option value="{{ $wh->id }}" {{ $selectedWarehouseId == $wh->id ? 'selected' : '' }}>
                                        {{ $wh->name }} {{ $wh->is_default ? '(' . __('crm.default_store') . ')' : '' }}
                                    </option>
                                @endforeach
                            </select>
                            <x-ui.button type="submit" variant="success" class="fw-bold px-3 py-1.5 shadow-sm text-nowrap" icon="feather-shopping-cart">
                                {{ __('crm.generate_pr') }}
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
                                    <th class="py-2" style="width: 28%">{{ __('crm.component_material_product') }}</th>
                                    <th class="text-center py-2" style="width: 10%">{{ __('crm.demanded') }}</th>
                                    <th class="text-center py-2" style="width: 8%">{{ __('crm.reserved_th') }}</th>
                                    <th class="text-center py-2" style="width: 9%">{{ __('crm.avail_th') }}</th>
                                    <th class="text-center text-primary py-2" style="width: 17%">{{ __('crm.pr_pipeline') }}</th>
                                    <th class="text-center text-danger py-2" style="width: 10%">{{ __('crm.net_shortage') }}</th>
                                    <th class="text-center py-2" style="width: 9%">{{ __('crm.pr_qty_to_order') }}</th>
                                    <th class="text-end py-2" style="width: 6%">{{ __('crm.est_cost') }}</th>
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
                                                    <div class="fs-9 text-uppercase fw-semibold text-success" style="letter-spacing: 0.3px;">{{ __('crm.approved') }}</div>
                                                </div>
                                            @endif
                                            @if(($row['pr_draft_qty'] ?? 0) > 0)
                                                <div>
                                                    <div class="fw-bold font-monospace text-warning fs-11">{{ number_format($row['pr_draft_qty'], 2) }} {{ $row['uom_code'] }}</div>
                                                    <div class="fs-9 text-uppercase fw-semibold text-warning" style="letter-spacing: 0.3px;">{{ __('crm.draft_pending') }}</div>
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
