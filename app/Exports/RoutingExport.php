<?php

namespace App\Exports;

use App\Domains\Production\Models\Routing;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Routing export — one row per operation (RT-IMPL-01).
 * Routings have no product and no materials any more (materials belong to the BOM — RT-01 / RT-27),
 * so the old product column and per-material rows are gone.
 */
class RoutingExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(
        private readonly int $tenantId,
        private readonly array $filters = []
    ) {}

    public function collection()
    {
        $query = Routing::where('tenant_id', $this->tenantId)
            ->with(['operations.workCenter', 'operations.section', 'operations.machine', 'operations.manualProduction']);

        if (!empty($this->filters['status'])) {
            $query->where('status', $this->filters['status']);
        }

        if (!empty($this->filters['search'])) {
            $search = $this->filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('routing_number', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%");
            });
        }

        $sortBy = $this->filters['sort_by'] ?? 'routing_number';
        $sortOrder = strtolower($this->filters['sort_order'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
        $allowedSorts = ['routing_number', 'name', 'version'];
        $query->orderBy(in_array($sortBy, $allowedSorts, true) ? $sortBy : 'routing_number', $sortOrder);

        $rows = [];
        foreach ($query->get() as $routing) {
            if ($routing->operations->isEmpty()) {
                $rows[] = (object) ['routing' => $routing, 'operation' => null, 'is_routing_first' => true];
                continue;
            }
            $first = true;
            foreach ($routing->operations->sortBy('sequence') as $op) {
                $rows[] = (object) ['routing' => $routing, 'operation' => $op, 'is_routing_first' => $first];
                $first = false;
            }
        }

        return collect($rows);
    }

    public static function availableColumns(): array
    {
        return [
            'routing_code'       => 'Routing Code',
            'routing_name'       => 'Routing Name',
            'version'            => 'Version',
            'status'             => 'Status',
            'operation_sequence' => 'Operation Sequence',
            'operation_name'     => 'Operation Name',
            'operation_code'     => 'Operation Code',
            'operation_type'     => 'Operation Type',
            'work_center_code'   => 'Work Center Code',
            'section_code'       => 'Section Code',
            'machine_code'       => 'Machine Code',
            'manual_batch'       => 'Manual Batch',
            'setup_time'         => 'Setup Per Run Minutes',
            'setup_time_per_piece' => 'Setup Per Piece Minutes',
            'processing_time'    => 'Processing Time Minutes',
            'yield_percentage'   => 'Yield Percentage',
            'is_external'        => 'Is External',
            'cost_per_unit'      => 'Cost Per Unit',
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
        return array_map(fn($key) => $all[$key] ?? $key, $this->getActiveColumns());
    }

    public function map($row): array
    {
        $routing = $row->routing;
        $op = $row->operation;
        $head = $row->is_routing_first;

        $values = [
            'routing_code'       => $head ? ($routing->routing_number ?? '') : '',
            'routing_name'       => $head ? ($routing->name ?? '') : '',
            'version'            => $head ? ($routing->version ?? '') : '',
            'status'             => $head ? ($routing->status ?? '') : '',
            'operation_sequence' => $op?->sequence ?? '',
            'operation_name'     => $op?->name ?? '',
            'operation_code'     => $op?->operation_number ?? '',
            'operation_type'     => $op?->operation_type ?? '',
            'work_center_code'   => $op?->workCenter?->code ?? '',
            'section_code'       => $op?->section?->code ?? '',
            'machine_code'       => $op?->machine?->code ?? '',
            'manual_batch'       => $op?->manualProduction?->batch_name ?? '',
            'setup_time'         => $op?->setup_time_minutes ?? '',
            'setup_time_per_piece' => $op?->setup_time_per_piece_minutes ?? '',
            'processing_time'    => $op?->processing_time_minutes ?? '',
            'yield_percentage'   => $op?->expected_yield_percentage ?? '',
            'is_external'        => $op ? ($op->is_external ? 'Yes' : 'No') : '',
            'cost_per_unit'      => $op?->cost_per_unit ?? '',
        ];

        return array_map(fn($col) => $values[$col] ?? '', $this->getActiveColumns());
    }
}
