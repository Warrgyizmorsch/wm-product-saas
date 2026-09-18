<?php

namespace App\Exports;

use App\Domains\Production\Models\Machine;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class MachineExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(
        private readonly int $tenantId,
        private readonly array $filters = []
    ) {}

    public function collection()
    {
        $query = Machine::where('tenant_id', $this->tenantId)->with('workCenter');

        if (!empty($this->filters['work_center_id'])) {
            $query->where('work_center_id', $this->filters['work_center_id']);
        }

        if (!empty($this->filters['status'])) {
            $query->where('status', $this->filters['status']);
        }

        if (!empty($this->filters['search'])) {
            $search = $this->filters['search'];
            $query->where(function($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%")
                  ->orWhere('machine_type', 'like', "%{$search}%");
            });
        }

        $sortBy = $this->filters['sort_by'] ?? 'code';
        $sortOrder = strtolower($this->filters['sort_order'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
        $allowedSorts = ['code', 'name', 'installation_date'];
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
            'work_center_code' => 'Work Center Code',
            'hourly_cost' => 'Hourly Cost',
            'status' => 'Status',
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

    public function map($machine): array
    {
        $values = [
            'code' => $machine->code,
            'name' => $machine->name,
            'work_center_code' => $machine->workCenter?->code ?? '',
            'hourly_cost' => $machine->hourly_cost,
            'status' => $machine->status,
        ];

        $output = [];
        foreach ($this->getActiveColumns() as $col) {
            $output[] = $values[$col] ?? '';
        }

        return $output;
    }
}
