<?php

namespace App\Exports;

use App\Domains\Production\Models\ProductionOrder;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ProductionOrderExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(
        private readonly int $tenantId,
        private readonly array $filters = []
    ) {}

    public function collection()
    {
        $query = ProductionOrder::where('tenant_id', $this->tenantId)
            ->with(['product', 'bom', 'routing', 'plan']);

        if (!empty($this->filters['search'])) {
            $search = $this->filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhereHas('product', function ($p) use ($search) {
                      $p->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                  });
            });
        }

        if (!empty($this->filters['status'])) {
            $query->where('status', $this->filters['status']);
        }

        if (!empty($this->filters['production_mode'])) {
            $query->where('production_mode', $this->filters['production_mode']);
        }

        if (!empty($this->filters['product_id'])) {
            $query->where('product_id', $this->filters['product_id']);
        }

        if (!empty($this->filters['start_date'])) {
            $query->where('start_date', '>=', $this->filters['start_date']);
        }

        if (!empty($this->filters['end_date'])) {
            $query->where('end_date', '<=', $this->filters['end_date']);
        }

        $sortBy = $this->filters['sort_by'] ?? 'id';
        $sortOrder = strtolower($this->filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['id', 'order_number', 'start_date', 'end_date', 'quantity_ordered', 'status'];
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
            'order_number' => 'Order Number',
            'product_sku' => 'Product SKU',
            'product_name' => 'Product Name',
            'bom_number' => 'BOM Number',
            'routing_number' => 'Routing Number',
            'plan_number' => 'Plan Number',
            'quantity_ordered' => 'Quantity Ordered',
            'quantity_produced' => 'Quantity Produced',
            'quantity_rejected' => 'Quantity Rejected',
            'quantity_scrapped' => 'Quantity Scrapped',
            'status' => 'Status',
            'production_model' => 'Production Model',
            'production_mode' => 'Production Mode',
            'start_date' => 'Planned Start Date',
            'end_date' => 'Planned End Date',
            'actual_start_date' => 'Actual Start Date',
            'actual_end_date' => 'Actual End Date',
            'description' => 'Notes / Description',
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

    public function map($order): array
    {
        $active = $this->getActiveColumns();
        $mapped = [];

        foreach ($active as $col) {
            $mapped[] = match ($col) {
                'order_number' => $order->order_number,
                'product_sku' => $order->product?->sku ?? '',
                'product_name' => $order->product?->name ?? '',
                'bom_number' => $order->bom?->bom_number ?? '',
                'routing_number' => $order->routing?->routing_number ?? '',
                'plan_number' => $order->plan?->plan_number ?? '',
                'quantity_ordered' => (float) $order->quantity_ordered,
                'quantity_produced' => (float) $order->quantity_produced,
                'quantity_rejected' => (float) $order->quantity_rejected,
                'quantity_scrapped' => (float) $order->quantity_scrapped,
                'status' => ucfirst(str_replace('_', ' ', $order->status)),
                'production_model' => ucfirst(str_replace('_', ' ', $order->production_model ?? '')),
                'production_mode' => ucfirst(str_replace('_', ' ', $order->production_mode ?? '')),
                'start_date' => $order->start_date?->format('Y-m-d') ?? '',
                'end_date' => $order->end_date?->format('Y-m-d') ?? '',
                'actual_start_date' => $order->actual_start_date?->format('Y-m-d H:i:s') ?? '',
                'actual_end_date' => $order->actual_end_date?->format('Y-m-d H:i:s') ?? '',
                'description' => $order->description ?? '',
                'created_at' => $order->created_at?->format('Y-m-d H:i:s') ?? '',
                default => '',
            };
        }

        return $mapped;
    }
}
