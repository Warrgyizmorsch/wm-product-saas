<?php

namespace App\Exports;

use App\Domains\Production\Models\WorkCenter;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class WorkCenterExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(
        private readonly int $tenantId,
        private readonly array $filters = []
    ) {}

    public function collection()
    {
        $query = WorkCenter::where('tenant_id', $this->tenantId);

        if (!empty($this->filters['work_center_type'])) {
            $query->where('work_center_type', $this->filters['work_center_type']);
        }

        if (!empty($this->filters['status'])) {
            $query->where('status', $this->filters['status']);
        }

        if (!empty($this->filters['search'])) {
            $search = $this->filters['search'];
            $query->where(function($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%")
                  ->orWhere('department_name', 'like', "%{$search}%");
            });
        }

        $sortBy = $this->filters['sort_by'] ?? 'code';
        $sortOrder = strtolower($this->filters['sort_order'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
        $allowedSorts = ['code', 'name', 'cost_per_hour'];
        if (in_array($sortBy, $allowedSorts, true)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('code', 'asc');
        }

        return $query->get();
    }

    public static function availableColumns(): array
    {
        return [
            'code' => 'Code',
            'name' => 'Name',
            'capacity_hours_per_day' => 'Capacity Hours Per Day',
            'efficiency_percentage' => 'Efficiency Percentage',
            'active' => 'Active',
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

    public function map($wc): array
    {
        $values = [
            'code' => $wc->code,
            'name' => $wc->name,
            'capacity_hours_per_day' => $wc->capacity_hours_per_day,
            'efficiency_percentage' => $wc->efficiency_percentage,
            'active' => $wc->status === 'active' ? 'Yes' : 'No',
        ];

        $output = [];
        foreach ($this->getActiveColumns() as $col) {
            $output[] = $values[$col] ?? '';
        }

        return $output;
    }
}
