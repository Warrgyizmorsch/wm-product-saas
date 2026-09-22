<?php

namespace App\Exports;

use App\Domains\Inventory\Models\StockTransaction;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class StockTransactionExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    public function __construct(
        private readonly int $tenantId,
        private readonly array $filters = []
    ) {}

    public function collection()
    {
        $query = StockTransaction::where('tenant_id', $this->tenantId)
            ->with(['product', 'warehouse', 'batch', 'incomingSerials', 'outgoingSerials']);

        // 1. Product Filter
        if (!empty($this->filters['product_id'])) {
            $query->where('product_id', $this->filters['product_id']);
        }

        // 2. Warehouse Filter
        if (!empty($this->filters['warehouse_id'])) {
            $query->where('warehouse_id', $this->filters['warehouse_id']);
        }

        // 3. Movement Type Filter (IN / OUT)
        if (!empty($this->filters['type']) && $this->filters['type'] !== 'all') {
            $query->where('type', $this->filters['type']);
        }

        // 4. Reference Type Filter
        if (!empty($this->filters['reference_type']) && $this->filters['reference_type'] !== 'all') {
            $query->where('reference_type', $this->filters['reference_type']);
        }

        // 5. Date Range
        if (!empty($this->filters['date_from'])) {
            $query->whereDate('created_at', '>=', $this->filters['date_from']);
        }
        if (!empty($this->filters['date_to'])) {
            $query->whereDate('created_at', '<=', $this->filters['date_to']);
        }

        // 6. Search Keyword
        if (!empty($this->filters['search'])) {
            $search = trim($this->filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('reference_type', 'like', "%{$search}%")
                  ->orWhere('reference_id', 'like', "%{$search}%")
                  ->orWhereHas('product', function ($pq) use ($search) {
                      $pq->where('name', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%");
                  })
                  ->orWhereHas('warehouse', function ($wq) use ($search) {
                      $wq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        // 7. Sorting
        $sortBy = $this->filters['sort_by'] ?? 'created_at';
        $sortOrder = strtolower($this->filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['created_at', 'quantity', 'unit_cost', 'total_value', 'type'];

        if (in_array($sortBy, $allowedSorts, true)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        return $query->get();
    }

    public static function availableColumns(): array
    {
        return [
            'id'             => 'Transaction ID',
            'product_name'   => 'Product Name',
            'product_sku'    => 'Product SKU Code',
            'warehouse_name' => 'Warehouse Location',
            'batch_number'   => 'Batch / Lot Number',
            'type'           => 'Movement Direction (IN/OUT)',
            'quantity'       => 'Quantity',
            'unit_cost'      => 'Unit Cost (₹)',
            'total_value'    => 'Total Value (₹)',
            'reference_type' => 'Document Type',
            'reference_id'   => 'Reference ID / Doc No',
            'created_at'     => 'Transaction Date & Time',
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

    public function map($tx): array
    {
        $values = [
            'id'             => $tx->id,
            'product_name'   => $tx->product?->name ?? '—',
            'product_sku'    => $tx->product?->sku ?? '—',
            'warehouse_name' => $tx->warehouse?->name ?? '—',
            'batch_number'   => $tx->batch?->batch_number ?? '—',
            'type'           => strtoupper((string)$tx->type),
            'quantity'       => (float)($tx->quantity ?? 0),
            'unit_cost'      => (float)($tx->unit_cost ?? 0),
            'total_value'    => (float)($tx->total_value ?? 0),
            'reference_type' => $tx->reference_type ?? '—',
            'reference_id'   => $tx->reference_id ?? '—',
            'created_at'     => $tx->created_at ? $tx->created_at->format('Y-m-d H:i:s') : '—',
        ];

        $output = [];
        foreach ($this->getActiveColumns() as $col) {
            $output[] = $values[$col] ?? '';
        }

        return $output;
    }
}
