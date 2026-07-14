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

        if (!empty($this->filters['search'])) {
            $search = $this->filters['search'];
            $query->where(function($q) use ($search) {
                $q->where('bom_number', 'like', "%{$search}%")
                  ->orWhere('bom_name', 'like', "%{$search}%");
            });
        }

        if (!empty($this->filters['status'])) {
            $query->where('status', $this->filters['status']);
        }

        $boms = $query->orderBy('bom_number')->get();

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

    public function headings(): array
    {
        return [
            'BOM Number',
            'BOM Name',
            'Product Code',
            'Base Quantity',
            'Base UOM Code',
            'Version',
            'BOM Type',
            'Usage Context',
            'Effective Date',
            'Expiry Date',
            'Component Code',
            'Item Quantity',
            'Item UOM Code',
            'Material Scrap Percentage',
            'Child BOM Number'
        ];
    }

    public function map($row): array
    {
        $bom = $row->bom;
        $item = $row->item;

        // For hierarchical output, clear header fields for subsequent item rows
        if ($row->is_first) {
            return [
                $bom->bom_number,
                $bom->bom_name,
                $bom->product?->sku ?? '',
                $bom->base_quantity,
                $bom->baseUom?->code ?? '',
                $bom->version,
                $bom->bom_type,
                $bom->usage_context,
                $bom->effective_date ? $bom->effective_date->format('Y-m-d') : '',
                $bom->expiry_date ? $bom->expiry_date->format('Y-m-d') : '',
                $item ? ($item->material?->sku ?? '') : '',
                $item ? $item->quantity : '',
                $item ? ($item->uom?->code ?? '') : '',
                $item ? $item->material_scrap_percentage : '',
                $item ? ($item->childBom?->bom_number ?? '') : ''
            ];
        } else {
            return [
                $bom->bom_number,
                '', // empty name
                '', // empty product
                '', // empty base qty
                '', // empty base uom
                '', // empty version
                '', // empty type
                '', // empty usage
                '', // empty effective
                '', // empty expiry
                $item ? ($item->material?->sku ?? '') : '',
                $item ? $item->quantity : '',
                $item ? ($item->uom?->code ?? '') : '',
                $item ? $item->material_scrap_percentage : '',
                $item ? ($item->childBom?->bom_number ?? '') : ''
            ];
        }
    }
}
