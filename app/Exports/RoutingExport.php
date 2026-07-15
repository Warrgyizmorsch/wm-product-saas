<?php

namespace App\Exports;

use App\Domains\Production\Models\Routing;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class RoutingExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(
        private readonly int $tenantId,
        private readonly array $filters = []
    ) {}

    public function collection()
    {
        $query = Routing::where('tenant_id', $this->tenantId)
            ->with(['product', 'operations.workCenter', 'operations.machine', 'operations.materials.material', 'operations.materials.uom']);

        if (!empty($this->filters['search'])) {
            $search = $this->filters['search'];
            $query->where(function($q) use ($search) {
                $q->where('routing_number', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%");
            });
        }

        if (!empty($this->filters['status'])) {
            $query->where('status', $this->filters['status']);
        }

        $routings = $query->orderBy('routing_number')->get();

        $rows = [];
        foreach ($routings as $routing) {
            if ($routing->operations->isEmpty()) {
                $rows[] = (object)[
                    'routing' => $routing,
                    'operation' => null,
                    'material' => null,
                    'is_routing_first' => true,
                    'is_operation_first' => true
                ];
            } else {
                $isRoutingFirst = true;
                foreach ($routing->operations as $op) {
                    if ($op->materials->isEmpty()) {
                        $rows[] = (object)[
                            'routing' => $routing,
                            'operation' => $op,
                            'material' => null,
                            'is_routing_first' => $isRoutingFirst,
                            'is_operation_first' => true
                        ];
                        $isRoutingFirst = false;
                    } else {
                        $isOpFirst = true;
                        foreach ($op->materials as $mat) {
                            $rows[] = (object)[
                                'routing' => $routing,
                                'operation' => $op,
                                'material' => $mat,
                                'is_routing_first' => $isRoutingFirst,
                                'is_operation_first' => $isOpFirst
                            ];
                            $isRoutingFirst = false;
                            $isOpFirst = false;
                        }
                    }
                }
            }
        }

        return collect($rows);
    }

    public function headings(): array
    {
        return [
            'Routing Code',
            'Routing Name',
            'Product Code',
            'Version',
            'Operation Sequence',
            'Operation Name',
            'Operation Code',
            'Operation Type',
            'Work Center Code',
            'Machine Code',
            'Setup Time Minutes',
            'Processing Time Minutes',
            'Yield Percentage',
            'Is External',
            'Material Code',
            'Material Quantity'
        ];
    }

    public function map($row): array
    {
        $routing = $row->routing;
        $op = $row->operation;
        $mat = $row->material;

        return [
            $row->is_routing_first ? $routing->routing_number : '',
            $row->is_routing_first ? $routing->name : '',
            $row->is_routing_first ? ($routing->product?->sku ?? '') : '',
            $row->is_routing_first ? $routing->version : '',
            ($op && $row->is_operation_first) ? $op->sequence : '',
            ($op && $row->is_operation_first) ? $op->name : '',
            ($op && $row->is_operation_first) ? $op->operation_number : '',
            ($op && $row->is_operation_first) ? $op->operation_type : '',
            ($op && $row->is_operation_first) ? ($op->workCenter?->code ?? '') : '',
            ($op && $row->is_operation_first) ? ($op->machine?->code ?? '') : '',
            ($op && $row->is_operation_first) ? $op->setup_time_minutes : '',
            ($op && $row->is_operation_first) ? $op->processing_time_minutes : '',
            ($op && $row->is_operation_first) ? $op->expected_yield_percentage : '',
            ($op && $row->is_operation_first) ? ($op->is_external ? 'Yes' : 'No') : '',
            $mat ? ($mat->material?->sku ?? '') : '',
            $mat ? $mat->quantity : ''
        ];
    }
}
