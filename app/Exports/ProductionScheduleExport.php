<?php

namespace App\Exports;

use App\Domains\Production\Models\ProductionSchedule;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class ProductionScheduleExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(
        private readonly int $tenantId,
        private readonly array $filters = []
    ) {}

    public function collection()
    {
        $query = ProductionSchedule::where('tenant_id', $this->tenantId)
            ->with(['order.product', 'creator', 'operations']);

        if (!empty($this->filters['search'])) {
            $search = $this->filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('schedule_number', 'like', "%{$search}%")
                    ->orWhereHas('order', function ($o) use ($search) {
                        $o->where('order_number', 'like', "%{$search}%")
                            ->orWhereHas('product', fn($p) => $p->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%"));
                    });
            });
        }

        if (!empty($this->filters['status'])) {
            $query->where('status', $this->filters['status']);
        }

        if (!empty($this->filters['scheduling_type'])) {
            $query->where('scheduling_type', $this->filters['scheduling_type']);
        }

        if (!empty($this->filters['production_order_id'])) {
            $query->where('production_order_id', $this->filters['production_order_id']);
        }

        if (!empty($this->filters['start_date'])) {
            $query->whereHas(
                'operations',
                fn($q) => $q->where('planned_start', '>=', $this->filters['start_date'])
            );
        }

        $sortBy = $this->filters['sort_by'] ?? 'id';
        $sortOrder = strtolower($this->filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $allowedSorts = [
            'id',
            'schedule_number',
            'status',
            'scheduling_type',
            'capacity_utilization',
            'scheduled_at',
            'released_at',
            'completed_at',
            'created_at',
        ];

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
            'schedule_number' => 'Schedule Number',
            'order_number' => 'Production Order',
            'product_sku' => 'Product SKU',
            'product_name' => 'Product Name',
            'scheduling_type' => 'Scheduling Type',
            'status' => 'Status',
            'operations_count' => 'Total Operations',
            'planned_start' => 'Planned Start',
            'planned_finish' => 'Planned Finish',
            'capacity_utilization' => 'Capacity Utilization (%)',
            'scheduled_at' => 'Scheduled At',
            'released_at' => 'Released At',
            'completed_at' => 'Completed At',
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

    public function map($schedule): array
    {
        $active = $this->getActiveColumns();
        $mapped = [];

        $sortedOps = $schedule->operations ? $schedule->operations->sortBy('sequence') : collect();
        $firstOp = $sortedOps->first();
        $lastOp = $sortedOps->last();

        foreach ($active as $col) {
            $mapped[] = match ($col) {
                'schedule_number' => $schedule->schedule_number,
                'order_number' => $schedule->order?->order_number ?? ('Order #' . $schedule->production_order_id),
                'product_sku' => $schedule->order?->product?->sku ?? '',
                'product_name' => $schedule->order?->product?->name ?? '',
                'scheduling_type' => ucfirst($schedule->scheduling_type ?? ''),
                'status' => ucfirst(str_replace('_', ' ', $schedule->status ?? '')),
                'operations_count' => (int) ($schedule->operations ? $schedule->operations->count() : 0),
                'planned_start' => $firstOp?->planned_start?->format('Y-m-d H:i') ?? '',
                'planned_finish' => $lastOp?->planned_finish?->format('Y-m-d H:i') ?? '',
                'capacity_utilization' => (float) ($schedule->capacity_utilization ?? 0.0),
                'scheduled_at' => $schedule->scheduled_at?->format('Y-m-d H:i:s') ?? '',
                'released_at' => $schedule->released_at?->format('Y-m-d H:i:s') ?? '',
                'completed_at' => $schedule->completed_at?->format('Y-m-d H:i:s') ?? '',
                'created_at' => $schedule->created_at?->format('Y-m-d H:i:s') ?? '',
                default => '',
            };
        }

        return $mapped;
    }
}
