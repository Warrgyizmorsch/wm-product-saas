<?php

namespace App\Exports;

use App\Domains\Inventory\Models\Product;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ProductExport implements FromCollection, WithHeadings, WithMapping
{
    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return Product::with(['uom', 'parent'])->orderBy('id', 'desc')->get();
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        return [
            'ID',
            'Item Name',
            'SKU',
            'Variation Type',
            'Parent SKU',
            'Variant Attributes',
            'Type',
            'Item Type',
            'Supplier Method',
            'Unit / UOM',
            'Selling Price',
            'Cost Price',
            'Opening Stock',
            'Reorder Point',
            'HSN/SAC',
            'GST Rate (%)',
            'Valuation Method',
            'Length',
            'Width',
            'Height',
            'Dimension Unit',
            'Weight',
            'Weight Unit',
            'Description',
            'Status',
            'Created At'
        ];
    }

    /**
     * @param Product $product
     * @return array
     */
    public function map($product): array
    {
        $parentSku = $product->parent?->sku ?? '';

        $attributesStr = '';
        if ($product->variation_type === 'Variant' && !empty($product->attributes_config)) {
            $parts = [];
            foreach ($product->attributes_config as $cfg) {
                $name = $cfg['name'] ?? '';
                $vals = is_array($cfg['values'] ?? null) ? implode(', ', $cfg['values']) : ($cfg['values'] ?? '');
                if ($name) {
                    $parts[] = "{$name}: {$vals}";
                }
            }
            $attributesStr = implode(' | ', $parts);
        } elseif (!empty($product->variant_values)) {
            $parts = [];
            foreach ($product->variant_values as $k => $v) {
                $parts[] = "{$k}: {$v}";
            }
            $attributesStr = implode(' | ', $parts);
        }

        return [
            $product->id,
            $product->name,
            $product->sku,
            $product->variation_type ?? 'Single',
            $parentSku,
            $attributesStr,
            $product->type,
            $product->item_type,
            $product->supplier_method ?? 'buy',
            $product->uom?->name ?? $product->uom?->code ?? 'PCS',
            $product->selling_price,
            $product->cost_price,
            $product->opening_stock,
            $product->reorder_point,
            $product->hsn_sac,
            $product->gst_rate,
            $product->inventory_valuation_method ?? 'FIFO',
            $product->length,
            $product->width,
            $product->height,
            $product->dimension_unit ?? 'cm',
            $product->weight,
            $product->weight_unit ?? 'kg',
            $product->description,
            $product->status,
            $product->created_at ? $product->created_at->format('Y-m-d H:i') : null,
        ];
    }
}
