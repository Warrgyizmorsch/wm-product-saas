@extends('layouts.duralux')

@section('title', 'Opening Stock | ' . $product->name . ' | SaaS ERP')
@section('page-title', 'Update Opening Stock')
@section('breadcrumb', 'Inventory / Items / Opening Stock')

@section('page-actions')
    <div class="d-flex gap-2">
        <x-ui.button variant="light-brand" href="{{ route('inventory.products.show', $product) }}" icon="feather-x">
            Cancel
        </x-ui.button>
    </div>
@endsection

@section('content')

{{-- ── ZOHO-STYLE OPENING STOCK FORM ─────────────────────────────────────── --}}
<form action="{{ route('inventory.products.opening-stock.save', $product) }}"
      method="POST" id="openingStockForm" novalidate>
@csrf

{{-- Top meta bar — Date + Item summary (like Zoho's header row) --}}
<div class="card border-0 shadow-sm mb-0" style="border-radius:8px 8px 0 0;">
    <div class="card-body px-4 py-3">
        <div class="row align-items-center g-3">
            {{-- Item Details --}}
            <div class="col-md-6">
                <div class="d-flex align-items-center gap-3">
                    <div class="avatar-text avatar-md bg-soft-primary text-primary fw-bold fs-5 flex-shrink-0">
                        {{ strtoupper(substr($product->name, 0, 1)) }}
                    </div>
                    <div>
                        <div class="fw-bold text-dark fs-14">{{ $product->name }}</div>
                        <div class="d-flex gap-2 align-items-center mt-1">
                            <span class="text-muted font-monospace fs-12">{{ $product->sku }}</span>
                            @if($product->uom)
                                <span class="text-muted fs-12">·</span>
                                <span class="text-muted fs-12">{{ $product->uom->name }}</span>
                            @endif
                            @if($product->variation_type === 'Variant')
                                <span class="text-muted fs-12">·</span>
                                <span class="text-primary fw-semibold fs-12">
                                    <i class="feather-git-branch me-1"></i>{{ $product->variants->count() }} variants
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            {{-- As Of Date --}}
            <div class="col-md-3 ms-auto">
                <label class="form-label fw-semibold text-muted fs-12 mb-1 text-uppercase">As of Date</label>
                <x-ui.odoo-form-ui
                    type="input"
                    inputType="date"
                    name="as_of_date"
                    :value="date('Y-m-d')"
                />
            </div>
        </div>
    </div>
</div>

{{-- ── LINE ITEM TABLE (Zoho-style) ──────────────────────────────────────── --}}
<div class="card border-0 shadow-sm" style="border-radius:0 0 8px 8px; border-top: 1px solid #e9ecef !important;">
    <div class="card-body p-0">

        <div class="table-responsive">
            <table class="table mb-0" id="stock-lines-table" style="border-collapse:collapse;">
                <thead style="background:#f7f8fa; border-bottom:2px solid #e2e8f0;">
                    <tr>
                        @if($product->variation_type === 'Variant')
                        <th class="ps-4 py-3 text-muted fw-semibold fs-12 text-uppercase" style="min-width:210px;">
                            Variant / Item
                        </th>
                        @endif
                        <th class="py-3 text-muted fw-semibold fs-12 text-uppercase" style="min-width:190px;">
                            Warehouse
                        </th>
                        <th class="py-3 text-muted fw-semibold fs-12 text-uppercase" style="width:160px;">
                            Opening Qty
                            @if($product->uom)
                                <span class="text-muted fw-normal text-lowercase">({{ $product->uom->code }})</span>
                            @endif
                        </th>
                        <th class="py-3 text-muted fw-semibold fs-12 text-uppercase" style="width:160px;">
                            Rate per Unit (₹)
                        </th>
                        <th class="py-3 text-muted fw-semibold fs-12 text-uppercase" style="width:140px;">
                            Amount (₹)
                        </th>
                        <th class="py-3" style="width:48px;"></th>
                    </tr>
                </thead>
                <tbody id="stock-lines-body">

                    @if($product->variation_type === 'Variant')
                        {{-- Pre-populate one row per variant × warehouse that has stock; else one row per variant --}}
                        @php $rowIdx = 0; @endphp
                        @foreach($product->variants as $variant)
                            @php
                                $variantRows = $variantStockMap[$variant->id] ?? [];
                                // If no stock yet, show one empty row per variant
                                if (empty($variantRows)) {
                                    $variantRows = ['' => ['quantity' => 0, 'unit_cost' => $variant->cost_price]];
                                }
                            @endphp
                            @foreach($variantRows as $whId => $ws)
                                @include('modules.inventory.products._opening-stock-row', [
                                    'rowIdx'       => $rowIdx,
                                    'isVariant'    => true,
                                    'variants'     => $product->variants,
                                    'selectedVariantId' => $variant->id,
                                    'warehouses'   => $warehouses,
                                    'selectedWhId' => $whId,
                                    'qty'          => $ws['quantity'] ?? 0,
                                    'cost'         => $ws['unit_cost'] ?? $variant->cost_price,
                                    'product'      => $product,
                                ])
                                @php $rowIdx++; @endphp
                            @endforeach
                        @endforeach

                    @else
                        {{-- Single: one row per warehouse with stock, or all warehouses --}}
                        @php $rowIdx = 0; @endphp
                        @foreach($warehouses as $warehouse)
                            @include('modules.inventory.products._opening-stock-row', [
                                'rowIdx'       => $rowIdx,
                                'isVariant'    => false,
                                'variants'     => collect(),
                                'selectedVariantId' => null,
                                'warehouses'   => $warehouses,
                                'selectedWhId' => $warehouse->id,
                                'qty'          => $stockMap[$warehouse->id]['quantity']  ?? 0,
                                'cost'         => $stockMap[$warehouse->id]['unit_cost'] ?? $product->cost_price,
                                'product'      => $product,
                            ])
                            @php $rowIdx++; @endphp
                        @endforeach
                    @endif

                </tbody>

                {{-- Empty state --}}
                <tbody id="empty-state" class="{{ ($product->variation_type !== 'Variant' && $warehouses->isEmpty()) || ($product->variation_type === 'Variant' && $product->variants->isEmpty()) ? '' : 'd-none' }}">
                    <tr>
                        <td colspan="{{ $product->variation_type === 'Variant' ? 6 : 5 }}" class="text-center py-5 text-muted">
                            <i class="feather-inbox d-block fs-1 mb-2 text-muted" style="opacity:.4;"></i>
                            @if($warehouses->isEmpty())
                                No active warehouses found. Please create warehouses first.
                            @else
                                No variants found. Please add variants to this product first.
                            @endif
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        {{-- Add Line button --}}
        @if(!$warehouses->isEmpty() && !($product->variation_type === 'Variant' && $product->variants->isEmpty()))
        <div class="px-4 py-3 border-top" style="border-color:#e9ecef !important;">
            <button type="button" id="addLineBtn"
                    class="btn btn-link text-primary fw-semibold fs-13 p-0 text-decoration-none">
                <i class="feather-plus-circle me-1"></i>Add Another Line
            </button>
        </div>
        @endif

        {{-- Total Bar --}}
        <div class="px-4 py-3 border-top d-flex justify-content-between align-items-center"
             style="background:#f7f8fa; border-color:#e2e8f0 !important; border-radius:0 0 8px 8px;">
            <div class="text-muted fs-13">
                <i class="feather-info me-1 text-primary"></i>
                Set quantity to <strong>0</strong> or leave blank to remove stock from a warehouse.
            </div>
            <div class="d-flex align-items-center gap-3">
                <span class="text-muted fw-semibold fs-13">Total Inventory Value</span>
                <span id="grand-total-display" class="fw-bold fs-4 text-dark">
                    ₹<span id="grand-total-value">0.00</span>
                </span>
            </div>
        </div>

    </div>{{-- /card-body --}}
</div>{{-- /card --}}

{{-- Action Footer --}}
<div class="d-flex justify-content-end gap-2 mt-4">
    <x-ui.button variant="light-brand" href="{{ route('inventory.products.show', $product) }}" icon="feather-x">
        Cancel
    </x-ui.button>
    <x-ui.button type="submit" variant="primary" icon="feather-save">
        Save Opening Stock
    </x-ui.button>
</div>

</form>

{{-- ── Hidden row template (cloned by JS) ────────────────────────────────── --}}
<template id="row-template">
    <tr class="stock-line-row border-bottom" data-row-idx="__IDX__" style="border-color:#f0f0f0 !important;">
        @if($product->variation_type === 'Variant')
        <td class="ps-4 py-2 align-middle">
            <select name="__variant_name__" class="odoo-table-select variant-selector" data-row="__IDX__" style="min-width:190px;">
                <option value="">— Select Variant —</option>
                @foreach($product->variants as $v)
                    <option value="{{ $v->id }}" data-cost="{{ $v->cost_price }}">
                        {{ $v->variant_values['label'] ?? $v->name }}
                    </option>
                @endforeach
            </select>
        </td>
        @endif
        <td class="py-2 align-middle">
            <select name="__wh_name__" class="odoo-table-select wh-selector" data-row="__IDX__" style="min-width:170px;">
                <option value="">— Select Warehouse —</option>
                @foreach($warehouses as $wh)
                    <option value="{{ $wh->id }}">{{ $wh->name }} ({{ $wh->code }})</option>
                @endforeach
            </select>
        </td>
        <td class="py-2 align-middle">
            <input type="number" name="__qty_name__"
                   class="odoo-table-input qty-input" data-row="__IDX__"
                   value="" placeholder="0" min="0" step="any" style="max-width:130px;">
        </td>
        <td class="py-2 align-middle">
            <input type="number" name="__cost_name__"
                   class="odoo-table-input cost-input" data-row="__IDX__"
                   value="" placeholder="0.00" min="0" step="any" style="max-width:130px;">
        </td>
        <td class="py-2 align-middle fw-semibold fs-13 row-total" data-row="__IDX__">0.00</td>
        <td class="py-2 align-middle text-center">
            <button type="button" class="btn btn-sm erp-icon-btn erp-icon-btn--danger remove-row-btn"
                    data-row="__IDX__" title="Remove row">
                <i class="feather-trash-2"></i>
            </button>
        </td>
    </tr>
</template>

@endsection

@push('scripts')
<script>
(function () {
    'use strict';

    const isVariant = {{ $product->variation_type === 'Variant' ? 'true' : 'false' }};
    const defaultCost = {{ $product->cost_price ?? 0 }};
    let rowCounter = {{ $rowIdx ?? $warehouses->count() }};

    /* ── Formatting ─────────────────────────────────────────────────────── */
    const fmt = (n) => Number(n).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    /* ── Grand Total ─────────────────────────────────────────────────────── */
    function recalcGrand() {
        let grand = 0;
        document.querySelectorAll('#stock-lines-body .stock-line-row').forEach(function (row) {
            const qty  = parseFloat(row.querySelector('.qty-input')?.value)  || 0;
            const cost = parseFloat(row.querySelector('.cost-input')?.value) || 0;
            const rowTotal = qty * cost;
            const totalCell = row.querySelector('.row-total');
            if (totalCell) totalCell.textContent = fmt(rowTotal);
            grand += rowTotal;
        });
        const el = document.getElementById('grand-total-value');
        if (el) el.textContent = fmt(grand);
    }

    /* ── Wire up a single row's inputs ──────────────────────────────────── */
    function wireRow(row) {
        row.querySelector('.qty-input')?.addEventListener('input',  recalcGrand);
        row.querySelector('.cost-input')?.addEventListener('input', recalcGrand);

        // When variant selected, auto-fill cost
        const varSel = row.querySelector('.variant-selector');
        if (varSel) {
            varSel.addEventListener('change', function () {
                const opt  = this.options[this.selectedIndex];
                const cost = parseFloat(opt.dataset.cost) || defaultCost;
                const costInput = row.querySelector('.cost-input');
                if (costInput && (!costInput.value || parseFloat(costInput.value) === 0)) {
                    costInput.value = cost;
                }
                // Update hidden names based on variant + warehouse
                rebuildNames(row);
                recalcGrand();
            });
        }
        const whSel = row.querySelector('.wh-selector');
        if (whSel) {
            whSel.addEventListener('change', function () {
                rebuildNames(row);
                recalcGrand();
            });
        }

        row.querySelector('.remove-row-btn')?.addEventListener('click', function () {
            row.remove();
            recalcGrand();
        });
    }

    /* ── Rebuild input names based on selected variant+warehouse ─────────── */
    function rebuildNames(row) {
        const whSel  = row.querySelector('.wh-selector');
        const whId   = whSel ? whSel.value : '';
        let nameBase = '';

        if (isVariant) {
            const varSel   = row.querySelector('.variant-selector');
            const variantId = varSel ? varSel.value : '';
            nameBase = variantId && whId
                ? `variant_stocks[${variantId}][${whId}]`
                : `variant_stocks[_][_]`;
        } else {
            nameBase = whId ? `warehouse_stocks[${whId}]` : `warehouse_stocks[_]`;
        }

        const qtyInput  = row.querySelector('.qty-input');
        const costInput = row.querySelector('.cost-input');
        if (qtyInput)  qtyInput.name  = nameBase + '[quantity]';
        if (costInput) costInput.name = nameBase + '[unit_cost]';
    }

    /* ── Add New Row ────────────────────────────────────────────────────── */
    function addRow(variantId, whId, qty, cost) {
        const tmpl = document.getElementById('row-template');
        if (!tmpl) return;

        const idx  = rowCounter++;
        let html   = tmpl.innerHTML
            .replace(/__IDX__/g,     idx)
            .replace(/__variant_name__/g, '') // will be rebuilt
            .replace(/__wh_name__/g, '')
            .replace(/__qty_name__/g, '')
            .replace(/__cost_name__/g, '');

        const tbody = document.getElementById('stock-lines-body');
        const tmpEl = document.createElement('tbody');
        tmpEl.innerHTML = html;
        const row = tmpEl.querySelector('tr');

        // Pre-select values
        if (variantId) {
            const vs = row.querySelector('.variant-selector');
            if (vs) vs.value = variantId;
        }
        if (whId) {
            const ws = row.querySelector('.wh-selector');
            if (ws) ws.value = whId;
        }
        if (qty > 0) {
            const qi = row.querySelector('.qty-input');
            if (qi) qi.value = qty;
        }
        if (cost > 0) {
            const ci = row.querySelector('.cost-input');
            if (ci) ci.value = cost;
        }

        tbody.appendChild(row);
        rebuildNames(row);
        wireRow(row);
        recalcGrand();
    }

    /* ── Wire existing rows (server-rendered) ───────────────────────────── */
    document.querySelectorAll('#stock-lines-body .stock-line-row').forEach(wireRow);

    /* ── Add Line button ─────────────────────────────────────────────────── */
    document.getElementById('addLineBtn')?.addEventListener('click', function () {
        addRow('', '', 0, defaultCost);
    });

    /* ── Initial grand total ─────────────────────────────────────────────── */
    recalcGrand();

})();
</script>
@endpush
