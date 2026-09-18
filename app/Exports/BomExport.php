<?php

namespace App\Exports;

use App\Domains\Production\Models\ProductionBom;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class BomExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(
        private readonly int $tenantId,
        private readonly array $filters = []
    ) {}

    public function collection()
    {
        $query = ProductionBom::where('tenant_id', $this->tenantId)
            ->with(['product', 'baseUom', 'items.material', 'items.uom', 'items.childBom']);

        if (!empty($this->filters['product_id'])) {
            $query->where('product_id', $this->filters['product_id']);
        }

        if (!empty($this->filters['status'])) {
            $query->where('status', $this->filters['status']);
        }

        if (!empty($this->filters['search'])) {
            $search = $this->filters['search'];
            $query->where(function($q) use ($search) {
                $q->where('bom_number', 'like', "%{$search}%")
                  ->orWhere('bom_name', 'like', "%{$search}%")
                  ->orWhereHas('product', function ($pq) use ($search) {
                      $pq->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                  });
            });
        }

        $sortBy = $this->filters['sort_by'] ?? 'bom_number';
        $sortOrder = strtolower($this->filters['sort_order'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
        $allowedSorts = ['bom_number', 'bom_name', 'base_quantity'];
        if (in_array($sortBy, $allowedSorts, true)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('bom_number', 'asc');
        }

        $boms = $query->get();

        $rows = [];
        foreach ($boms as $bom) {
            if ($bom->items->isEmpty()) {
                $rows[] = (object)[
                    'bom' => $bom,
                    'item' => null,
                    'is_first' => true
                ];
            } else {
                $isFirst = true;
                foreach ($bom->items as $item) {
                    $rows[] = (object)[
                        'bom' => $bom,
                        'item' => $item,
                        'is_first' => $isFirst
                    ];
                    $isFirst = false;
                }
            }
        }

        return collect($rows);
    }

    public static function availableColumns(): array
    {
        return [
            'bom_number' => 'BOM Number',
            'bom_name' => 'BOM Name',
            'product_code' => 'Product Code',
            'base_quantity' => 'Base Quantity',
            'base_uom' => 'Base UOM Code',
            'version' => 'Version',
            'bom_type' => 'BOM Type',
            'usage_context' => 'Usage Context',
            'effective_date' => 'Effective Date',
            'expiry_date' => 'Expiry Date',
            'component_code' => 'Component Code',
            'item_quantity' => 'Item Quantity',
            'item_uom' => 'Item UOM Code',
            'material_scrap_percentage' => 'Material Scrap Percentage',
            'child_bom_number' => 'Child BOM Number',
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

    public function map($row): array
    {
        $bom = $row->bom;
        $item = $row->item;

        $values = [
            'bom_number' => $bom->bom_number,
            'bom_name' => $row->is_first ? ($bom->bom_name ?? '') : '',
            'product_code' => $row->is_first ? ($bom->product?->sku ?? '') : '',
            'base_quantity' => $row->is_first ? $bom->base_quantity : '',
            'base_uom' => $row->is_first ? ($bom->baseUom?->code ?? '') : '',
            'version' => $row->is_first ? $bom->version : '',
            'bom_type' => $row->is_first ? $bom->bom_type : '',
            'usage_context' => $row->is_first ? $bom->usage_context : '',
            'effective_date' => $row->is_first ? ($bom->effective_date ? $bom->effective_date->format('Y-m-d') : '') : '',
            'expiry_date' => $row->is_first ? ($bom->expiry_date ? $bom->expiry_date->format('Y-m-d') : '') : '',
            'component_code' => $item ? ($item->material?->sku ?? '') : '',
            'item_quantity' => $item ? $item->quantity : '',
            'item_uom' => $item ? ($item->uom?->code ?? '') : '',
            'material_scrap_percentage' => $item ? $item->material_scrap_percentage : '',
            'child_bom_number' => $item ? ($item->childBom?->bom_number ?? '') : '',
        ];

        $output = [];
        foreach ($this->getActiveColumns() as $col) {
            $output[] = $values[$col] ?? '';
        }

        return $output;
    }
}
