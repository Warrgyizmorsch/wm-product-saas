{{--
    Partial: _opening-stock-row.blade.php
    Variables expected:
        $rowIdx            (int)
        $isVariant         (bool)
        $variants          (Collection)
        $selectedVariantId (int|null)
        $warehouses        (Collection)
        $selectedWhId      (int|string)
        $qty               (float)
        $cost              (float)
        $product           (Product)
--}}

@php
    if ($isVariant) {
        $nameBase = ($selectedVariantId && $selectedWhId)
            ? "variant_stocks[{$selectedVariantId}][{$selectedWhId}]"
            : "variant_stocks[_][_]";
    } else {
        $nameBase = $selectedWhId
            ? "warehouse_stocks[{$selectedWhId}]"
            : "warehouse_stocks[_]";
    }
    $rowTotal = ($qty ?? 0) * ($cost ?? 0);
@endphp

<tr class="stock-line-row border-bottom" data-row-idx="{{ $rowIdx }}" style="border-color:#f0f0f0 !important;">

    {{-- Variant selector column (only for variant products) --}}
    @if($isVariant)
    <td class="ps-4 py-2 align-middle">
        <select name="{{ $nameBase }}_variant_meta"
                class="odoo-table-select variant-selector"
                data-row="{{ $rowIdx }}"
                style="min-width:190px;">
            <option value="">— {{ __('inventory.select_variant') }} —</option>
            @foreach($variants as $v)
                <option value="{{ $v->id }}"
                        data-cost="{{ $v->cost_price }}"
                        {{ (string)$v->id === (string)$selectedVariantId ? 'selected' : '' }}>
                    {{ $v->variant_values['label'] ?? $v->name }}
                </option>
            @endforeach
        </select>
    </td>
    @endif

    {{-- Warehouse selector --}}
    <td class="py-2 align-middle">
        <select name="{{ $nameBase }}_wh_meta"
                class="odoo-table-select wh-selector"
                data-row="{{ $rowIdx }}"
                style="min-width:170px;">
            <option value="">— {{ __('inventory.select_warehouse') }} —</option>
            @foreach($warehouses as $wh)
                <option value="{{ $wh->id }}"
                        {{ (string)$wh->id === (string)$selectedWhId ? 'selected' : '' }}>
                    {{ $wh->name }}
                    @if($wh->code)({{ $wh->code }})@endif
                </option>
            @endforeach
        </select>
    </td>

    {{-- Batch Number (if tracked) --}}
    @if($product->track_batch)
    <td class="py-2 align-middle">
        <input type="text"
               name="{{ $nameBase }}[batch_number]"
               class="odoo-table-input batch-input"
               data-row="{{ $rowIdx }}"
               value=""
               placeholder="{{ __('inventory.batch_placeholder') }}"
               style="max-width:140px;">
    </td>
    @endif

    {{-- Serial Numbers (if tracked) --}}
    @if($product->track_serial_number)
    <td class="py-2 align-middle">
        <input type="text"
               name="{{ $nameBase }}[serial_numbers]"
               class="odoo-table-input serials-input"
               data-row="{{ $rowIdx }}"
               value=""
               placeholder="{{ __('inventory.serials_placeholder') }}"
               style="min-width:180px;">
    </td>
    @endif

    {{-- Opening Qty --}}
    <td class="py-2 align-middle">
        <input type="number"
               name="{{ $nameBase }}[quantity]"
               class="odoo-table-input qty-input"
               data-row="{{ $rowIdx }}"
               value="{{ $qty > 0 ? $qty : '' }}"
               placeholder="0"
               min="0" step="any"
               style="max-width:130px;">
    </td>

    {{-- Rate per unit --}}
    <td class="py-2 align-middle">
        <input type="number"
               name="{{ $nameBase }}[unit_cost]"
               class="odoo-table-input cost-input"
               data-row="{{ $rowIdx }}"
               value="{{ $cost ?? $product->cost_price }}"
               placeholder="0.00"
               min="0" step="any"
               style="max-width:130px;">
    </td>

    {{-- Row Total --}}
    <td class="py-2 align-middle fw-semibold fs-13 row-total text-dark" data-row="{{ $rowIdx }}">
        {{ number_format($rowTotal, 2) }}
    </td>

    {{-- Remove row --}}
    <td class="py-2 align-middle text-center">
        <button type="button"
                class="btn btn-sm erp-icon-btn erp-icon-btn--danger remove-row-btn"
                data-row="{{ $rowIdx }}"
                title="{{ __('inventory.delete') }}">
            <i class="feather-trash-2"></i>
        </button>
    </td>
</tr>
