<?php

namespace App\Exports;

use App\Domains\Inventory\Models\Product;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class ProductExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    public function __construct(
        private readonly ?int $tenantId = null,
        private readonly array $filters = []
    ) {}

    public function collection()
    {
        $query = Product::with(['uom', 'parent']);

        if ($this->tenantId) {
            $query->where('tenant_id', $this->tenantId);
        }

        // 1. Item Type Filter (Goods / Service)
        if (!empty($this->filters['item_type']) && $this->filters['item_type'] !== 'all') {
            $query->where('item_type', $this->filters['item_type']);
        }

        // 2. Status Filter
        if (!empty($this->filters['status']) && $this->filters['status'] !== 'all') {
            $query->where('status', $this->filters['status']);
        }

        // 3. Search Keyword
        if (!empty($this->filters['search'])) {
            $search = trim($this->filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('hsn_sac', 'like', "%{$search}%")
                  ->orWhere('barcode', 'like', "%{$search}%");
            });
        }

        // 4. Sorting
        $sortBy = $this->filters['sort_by'] ?? 'name';
        $sortOrder = strtolower($this->filters['sort_order'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
        $allowedSorts = ['name', 'sku', 'selling_price', 'cost_price', 'created_at'];

        if (in_array($sortBy, $allowedSorts, true)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('name', 'asc');
        }

        return $query->get();
    }

    public static function availableColumns(): array
    {
        return [
            'id'                 => 'Product ID',
            'name'               => 'Item / Product Name',
            'sku'                => 'Item SKU Code',
            'variation_type'     => 'Variation Type',
            'parent_sku'         => 'Parent SKU',
            'variant_attributes' => 'Variant Attributes',
            'type'               => 'Product Type',
            'item_type'          => 'Item Category Type',
            'supplier_method'    => 'Procurement Method',
            'uom'                => 'Unit of Measure (UOM)',
            'selling_price'      => 'Selling Price (₹)',
            'cost_price'         => 'Cost Price (₹)',
            'opening_stock'      => 'Opening Stock Qty',
            'reorder_point'      => 'Reorder Point / Min Level',
            'hsn_sac'            => 'HSN / SAC Code',
            'gst_rate'           => 'GST Rate (%)',
            'valuation_method'   => 'Valuation Method',
            'dimensions'         => 'Dimensions (L x W x H)',
            'weight'             => 'Weight',
            'status'             => 'Status',
            'created_at'         => 'Created Date',
        ];
    }

    public function getActiveColumns(): array
    {
        $all = static::availableColumns();
        if (!empty($this->filters['columns']) && is_array($this->filters['columns'])) {
            $selected = array_values(array_intersect(array_keys($all), $this->filters['columns']));
            if (!empty($selected)) {
                return $selected;
            }
        }
        return array_keys($all);
    }

    public function headings(): array
    {
        $all = static::availableColumns();
        $headers = [];
        foreach ($this->getActiveColumns() as $key) {
            $headers[] = $all[$key] ?? $key;
        }
        return $headers;
    }

    public function map($product): array
    {
        $parentSku = $product->parent?->sku ?? '—';

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

        $dims = '';
        if ($product->length || $product->width || $product->height) {
            $dims = trim("{$product->length} x {$product->width} x {$product->height} " . ($product->dimension_unit ?? 'cm'));
        }

        $weightStr = '';
        if ($product->weight) {
            $weightStr = trim("{$product->weight} " . ($product->weight_unit ?? 'kg'));
        }

        $values = [
            'id'                 => $product->id,
            'name'               => $product->name,
            'sku'                => $product->sku,
            'variation_type'     => $product->variation_type ?? 'Single',
            'parent_sku'         => $parentSku,
            'variant_attributes' => $attributesStr ?: '—',
            'type'               => $product->type ?? '—',
            'item_type'          => $product->item_type ?? 'Goods',
            'supplier_method'    => in_array(strtolower($product->supplier_method ?? ''), ['buy', 'trade'], true) || empty($product->supplier_method) ? 'Trade' : ucfirst($product->supplier_method),
            'uom'                => $product->uom?->name ?? $product->uom?->code ?? 'PCS',
            'selling_price'      => (float)($product->selling_price ?? 0),
            'cost_price'         => (float)($product->cost_price ?? 0),
            'opening_stock'      => (float)($product->opening_stock ?? 0),
            'reorder_point'      => (float)($product->reorder_point ?? 0),
            'hsn_sac'            => $product->hsn_sac ?? '—',
            'gst_rate'           => (float)($product->gst_rate ?? 0),
            'valuation_method'   => $product->inventory_valuation_method ?? 'FIFO',
            'dimensions'         => $dims ?: '—',
            'weight'             => $weightStr ?: '—',
            'status'             => ucfirst((string)$product->status),
            'created_at'         => $product->created_at ? $product->created_at->format('Y-m-d H:i') : '—',
        ];

        $output = [];
        foreach ($this->getActiveColumns() as $col) {
            $output[] = $values[$col] ?? '';
        }

        return $output;
    }
}
