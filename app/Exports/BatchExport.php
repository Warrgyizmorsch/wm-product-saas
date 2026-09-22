<?php

namespace App\Exports;

use App\Domains\Inventory\Models\Batch;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class BatchExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    public function __construct(
        private readonly int $tenantId,
        private readonly array $filters = []
    ) {}

    public function collection()
    {
        $query = Batch::where('tenant_id', $this->tenantId)
            ->with(['product', 'warehouse']);

        // 1. Product Filter
        if (!empty($this->filters['product_id'])) {
            $query->where('product_id', $this->filters['product_id']);
        }

        // 2. Warehouse Filter
        if (!empty($this->filters['warehouse_id'])) {
            $query->where('warehouse_id', $this->filters['warehouse_id']);
        }

        // 3. Search Keyword
        if (!empty($this->filters['search'])) {
            $search = trim($this->filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('batch_number', 'like', "%{$search}%")
                  ->orWhereHas('product', function ($pq) use ($search) {
                      $pq->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                  })
                  ->orWhereHas('warehouse', function ($wq) use ($search) {
                      $wq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        // 4. Sorting
        $sortBy = $this->filters['sort_by'] ?? 'expiry_date';
        $sortOrder = strtolower($this->filters['sort_order'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
        $allowedSorts = ['batch_number', 'manufacturing_date', 'expiry_date', 'quantity', 'available_qty', 'created_at'];

        if (in_array($sortBy, $allowedSorts, true)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('expiry_date', 'asc');
        }

        return $query->get();
    }

    public static function availableColumns(): array
    {
        return [
            'batch_number'       => 'Batch / Lot Number',
            'product_name'       => 'Product Name',
            'product_sku'        => 'Product SKU Code',
            'warehouse_name'     => 'Warehouse Location',
            'manufacturing_date' => 'Manufacturing Date',
            'expiry_date'        => 'Expiry Date',
            'quantity'           => 'Initial Quantity',
            'available_qty'      => 'Current Available Qty',
            'created_at'         => 'Created Date',
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

    public function map($batch): array
    {
        $values = [
            'batch_number'       => $batch->batch_number,
            'product_name'       => $batch->product?->name ?? '—',
            'product_sku'        => $batch->product?->sku ?? '—',
            'warehouse_name'     => $batch->warehouse?->name ?? '—',
            'manufacturing_date' => $batch->manufacturing_date ? date('Y-m-d', strtotime($batch->manufacturing_date)) : '—',
            'expiry_date'        => $batch->expiry_date ? date('Y-m-d', strtotime($batch->expiry_date)) : '—',
            'quantity'           => (float)($batch->quantity ?? 0),
            'available_qty'      => (float)($batch->available_qty ?? 0),
            'created_at'         => $batch->created_at ? $batch->created_at->format('Y-m-d H:i') : '—',
        ];

        $output = [];
        foreach ($this->getActiveColumns() as $col) {
            $output[] = $values[$col] ?? '';
        }

        return $output;
    }
}
