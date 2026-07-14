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

        if (!empty($this->filters['search'])) {
            $search = $this->filters['search'];
            $query->where(function($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%");
            });
        }

        if (!empty($this->filters['status'])) {
            $query->where('status', $this->filters['status']);
        }

        return $query->orderBy('code')->get();
    }

    public function headings(): array
    {
        return [
            'Code',
            'Name',
            'Capacity Hours Per Day',
            'Efficiency Percentage',
            'Active'
        ];
    }

    public function map($wc): array
    {
        return [
            $wc->code,
            $wc->name,
            $wc->capacity_hours_per_day,
            $wc->efficiency_percentage,
            $wc->status === 'active' ? 'Yes' : 'No'
        ];
    }
}
