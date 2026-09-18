<?php

namespace App\Exports;

use App\Domains\Production\Models\ProductionPlan;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ProductionPlanExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(
        private readonly int $tenantId,
        private readonly array $filters = []
    ) {}

    public function collection()
    {
        $query = ProductionPlan::where('tenant_id', $this->tenantId)
            ->with(['product', 'bom', 'routing', 'salesOrder']);

        if (!empty($this->filters['search'])) {
            $search = $this->filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('plan_number', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%")
                  ->orWhereHas('product', function ($p) use ($search) {
                      $p->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                  });
            });
        }

        if (!empty($this->filters['status'])) {
            $query->where('status', $this->filters['status']);
        }

        if (!empty($this->filters['start_date'])) {
            $query->where('start_date', '>=', $this->filters['start_date']);
        }

        if (!empty($this->filters['end_date'])) {
            $query->where('end_date', '<=', $this->filters['end_date']);
        }

        $sortBy = $this->filters['sort_by'] ?? 'id';
        $sortOrder = strtolower($this->filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['id', 'plan_number', 'name', 'quantity', 'start_date', 'end_date', 'status'];
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
            'plan_number' => 'Plan Number',
            'name' => 'Plan Name',
            'product_sku' => 'Product SKU',
            'product_name' => 'Product Name',
            'bom_number' => 'BOM Number',
            'routing_number' => 'Routing Number',
            'sales_order_number' => 'Sales Order Number',
            'quantity' => 'Planned Quantity',
            'status' => 'Status',
            'start_date' => 'Planned Start Date',
            'end_date' => 'Planned End Date',
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

    public function map($plan): array
    {
        $active = $this->getActiveColumns();
        $mapped = [];

        foreach ($active as $col) {
            $mapped[] = match ($col) {
                'plan_number' => $plan->plan_number,
                'name' => $plan->name,
                'product_sku' => $plan->product?->sku ?? '',
                'product_name' => $plan->product?->name ?? '',
                'bom_number' => $plan->bom?->bom_number ?? '',
                'routing_number' => $plan->routing?->routing_number ?? '',
                'sales_order_number' => $plan->salesOrder?->order_number ?? '',
                'quantity' => (float) $plan->quantity,
                'status' => ucfirst(str_replace('_', ' ', $plan->status)),
                'start_date' => $plan->start_date?->format('Y-m-d') ?? '',
                'end_date' => $plan->end_date?->format('Y-m-d') ?? '',
                'description' => $plan->description ?? '',
                'created_at' => $plan->created_at?->format('Y-m-d H:i:s') ?? '',
                default => '',
            };
        }

        return $mapped;
    }
}
