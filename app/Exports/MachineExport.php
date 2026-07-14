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
            'Work Center Code',
            'Hourly Cost',
            'Status'
        ];
    }

    public function map($machine): array
    {
        return [
            $machine->code,
            $machine->name,
            $machine->workCenter?->code ?? '',
            $machine->hourly_cost,
            $machine->status
        ];
    }
}
