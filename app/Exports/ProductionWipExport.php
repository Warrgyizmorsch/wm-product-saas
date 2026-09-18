<?php

namespace App\Exports;

use App\Domains\Production\Models\ProductionWip;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ProductionWipExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(
        private readonly int $tenantId,
        private readonly array $filters = []
    ) {}

    public function collection()
    {
        $query = ProductionWip::where('tenant_id', $this->tenantId)
            ->with([
                'order',
                'product',
                'currentRoutingOperation',
                'currentWorkCenter',
                'currentMachine',
                'batch',
            ]);

        if (!empty($this->filters['search'])) {
            $search = $this->filters['search'];
            $query->where(function ($q) use ($search) {
                $q->whereHas('product', fn($p) => $p->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%"))
                  ->orWhereHas('order', fn($o) => $o->where('order_number', 'like', "%{$search}%"))
                  ->orWhereHas('batch', fn($b) => $b->where('batch_number', 'like', "%{$search}%"));
            });
        }

        if (!empty($this->filters['status'])) {
            $query->where('status', $this->filters['status']);
        }

        if (!empty($this->filters['work_center_id'])) {
            $query->where('current_work_center_id', $this->filters['work_center_id']);
        }

        if (!empty($this->filters['production_order_id'])) {
            $query->where('production_order_id', $this->filters['production_order_id']);
        }

        $sortBy = $this->filters['sort_by'] ?? 'id';
        $sortOrder = strtolower($this->filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['id', 'quantity', 'available_quantity', 'completed_quantity', 'scrap_quantity', 'status', 'started_at', 'created_at'];
        if (in_array($sortBy, $allowedSorts, true)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('id', 'desc');
        }

        return $query->get();
    }

    public static function availableColumns(): array
    {
        return [
            'wip_number' => 'WIP Number',
            'order_number' => 'Production Order',
            'batch_number' => 'Batch Number',
            'product_sku' => 'Product SKU',
            'product_name' => 'Product Name',
            'current_operation' => 'Current Operation',
            'current_work_center' => 'Work Center',
            'current_machine' => 'Machine',
            'available_quantity' => 'Available Qty',
            'completed_quantity' => 'Completed FG Qty',
            'scrap_quantity' => 'Scrap Qty',
            'rework_quantity' => 'Rework Qty',
            'status' => 'Status',
            'total_value' => 'Total WIP Value',
            'started_at' => 'Started At',
            'created_at' => 'Created At',
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
        $active = $this->getActiveColumns();

        return array_map(fn($key) => $all[$key] ?? ucfirst(str_replace('_', ' ', $key)), $active);
    }

    public function map($wip): array
    {
        $active = $this->getActiveColumns();
        $mapped = [];

        foreach ($active as $col) {
            $mapped[] = match ($col) {
                'wip_number' => 'WIP-#' . str_pad((string) $wip->id, 5, '0', STR_PAD_LEFT),
                'order_number' => $wip->order?->order_number ?? ('Order #' . $wip->production_order_id),
                'batch_number' => $wip->batch?->batch_number ?? '',
                'product_sku' => $wip->product?->sku ?? '',
                'product_name' => $wip->product?->name ?? '',
                'current_operation' => $wip->currentRoutingOperation?->name ?? ($wip->status === 'completed' ? 'Finished Goods (Ready)' : ''),
                'current_work_center' => $wip->currentWorkCenter?->name ?? '',
                'current_machine' => $wip->currentMachine?->name ?? '',
                'available_quantity' => (float) $wip->available_quantity,
                'completed_quantity' => (float) $wip->completed_quantity,
                'scrap_quantity' => (float) $wip->scrap_quantity,
                'rework_quantity' => (float) $wip->rework_quantity,
                'status' => ucfirst(str_replace('_', ' ', $wip->status)),
                'total_value' => (float) $wip->total_value,
                'started_at' => $wip->started_at?->format('Y-m-d H:i:s') ?? '',
                'created_at' => $wip->created_at?->format('Y-m-d H:i:s') ?? '',
                default => '',
            };
        }

        return $mapped;
    }
}
