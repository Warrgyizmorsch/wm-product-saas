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

        if (!empty($this->filters['product_id'])) {
            $query->where('product_id', $this->filters['product_id']);
        }

        if (!empty($this->filters['status'])) {
            $query->where('status', $this->filters['status']);
        }

        if (!empty($this->filters['search'])) {
            $search = $this->filters['search'];
            $query->where(function($q) use ($search) {
                $q->where('routing_number', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%")
                  ->orWhereHas('product', function ($pq) use ($search) {
                      $pq->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                  });
            });
        }

        $sortBy = $this->filters['sort_by'] ?? 'routing_number';
        $sortOrder = strtolower($this->filters['sort_order'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
        $allowedSorts = ['routing_number', 'name', 'version'];
        if (in_array($sortBy, $allowedSorts, true)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('routing_number', 'asc');
        }

        $routings = $query->get();

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

    public static function availableColumns(): array
    {
        return [
            'routing_code' => 'Routing Code',
            'routing_name' => 'Routing Name',
            'product_code' => 'Product Code',
            'version' => 'Version',
            'operation_sequence' => 'Operation Sequence',
            'operation_name' => 'Operation Name',
            'operation_code' => 'Operation Code',
            'operation_type' => 'Operation Type',
            'work_center_code' => 'Work Center Code',
            'machine_code' => 'Machine Code',
            'setup_time' => 'Setup Time Minutes',
            'processing_time' => 'Processing Time Minutes',
            'yield_percentage' => 'Yield Percentage',
            'is_external' => 'Is External',
            'material_code' => 'Material Code',
            'material_quantity' => 'Material Quantity',
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

    public function map($row): array
    {
        $routing = $row->routing;
        $op = $row->operation;
        $mat = $row->material;

        $values = [
            'routing_code' => $row->is_routing_first ? ($routing->routing_number ?? '') : '',
            'routing_name' => $row->is_routing_first ? ($routing->name ?? '') : '',
            'product_code' => $row->is_routing_first ? ($routing->product?->sku ?? '') : '',
            'version' => $row->is_routing_first ? ($routing->version ?? '') : '',
            'operation_sequence' => ($op && $row->is_operation_first) ? $op->sequence : '',
            'operation_name' => ($op && $row->is_operation_first) ? $op->name : '',
            'operation_code' => ($op && $row->is_operation_first) ? $op->operation_number : '',
            'operation_type' => ($op && $row->is_operation_first) ? $op->operation_type : '',
            'work_center_code' => ($op && $row->is_operation_first) ? ($op->workCenter?->code ?? '') : '',
            'machine_code' => ($op && $row->is_operation_first) ? ($op->machine?->code ?? '') : '',
            'setup_time' => ($op && $row->is_operation_first) ? $op->setup_time_minutes : '',
            'processing_time' => ($op && $row->is_operation_first) ? $op->processing_time_minutes : '',
            'yield_percentage' => ($op && $row->is_operation_first) ? $op->expected_yield_percentage : '',
            'is_external' => ($op && $row->is_operation_first) ? ($op->is_external ? 'Yes' : 'No') : '',
            'material_code' => $mat ? ($mat->material?->sku ?? '') : '',
            'material_quantity' => $mat ? $mat->quantity : '',
        ];

        $output = [];
        foreach ($this->getActiveColumns() as $col) {
            $output[] = $values[$col] ?? '';
        }

        return $output;
    }
}
