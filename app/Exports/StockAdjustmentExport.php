<?php

namespace App\Exports;

use App\Domains\Inventory\Models\StockAdjustment;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class StockAdjustmentExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    public function __construct(
        private readonly int $tenantId,
        private readonly array $filters = []
    ) {}

    public function collection()
    {
        $query = StockAdjustment::where('tenant_id', $this->tenantId)
            ->with(['warehouse', 'items.product', 'creator']);

        // 1. Status Filter
        if (!empty($this->filters['status']) && $this->filters['status'] !== 'all') {
            $query->where('status', $this->filters['status']);
        }

        // 2. Warehouse Filter
        if (!empty($this->filters['warehouse_id'])) {
            $query->where('warehouse_id', $this->filters['warehouse_id']);
        }

        // 3. Date Range
        if (!empty($this->filters['date_from'])) {
            $query->whereDate('adjustment_date', '>=', $this->filters['date_from']);
        }
        if (!empty($this->filters['date_to'])) {
            $query->whereDate('adjustment_date', '<=', $this->filters['date_to']);
        }

        // 4. Search Keyword
        if (!empty($this->filters['search'])) {
            $search = trim($this->filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('adjustment_number', 'like', "%{$search}%")
                  ->orWhere('reason', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%")
                  ->orWhereHas('warehouse', function ($wq) use ($search) {
                      $wq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        // 5. Sorting
        $sortBy = $this->filters['sort_by'] ?? 'adjustment_date';
        $sortOrder = strtolower($this->filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['adjustment_number', 'adjustment_date', 'status', 'created_at'];

        if (in_array($sortBy, $allowedSorts, true)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('adjustment_date', 'desc');
        }

        return $query->get();
    }

    public static function availableColumns(): array
    {
        return [
            'adjustment_number' => 'Adjustment Number',
            'warehouse_name'    => 'Warehouse Location',
            'adjustment_date'   => 'Adjustment Date',
            'reason'            => 'Adjustment Reason',
            'status'            => 'Status',
            'items_count'       => 'Items Count',
            'items_summary'     => 'Items Adjusted Summary',
            'creator_name'      => 'Created By',
            'notes'             => 'Notes',
            'created_at'        => 'Created Date',
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

    public function map($adj): array
    {
        $itemsSummary = $adj->items
            ->map(fn($item) => ($item->product?->name ?? 'Item') . ' (Qty: ' . (float)$item->quantity . ', Type: ' . $item->type . ')')
            ->filter()
            ->implode(', ');

        $values = [
            'adjustment_number' => $adj->adjustment_number,
            'warehouse_name'    => $adj->warehouse?->name ?? '—',
            'adjustment_date'   => $adj->adjustment_date ? date('Y-m-d', strtotime($adj->adjustment_date)) : '—',
            'reason'            => $adj->reason ?? '—',
            'status'            => ucfirst((string)$adj->status),
            'items_count'       => $adj->items?->count() ?? 0,
            'items_summary'     => $itemsSummary ?: '—',
            'creator_name'      => $adj->creator?->name ?? '—',
            'notes'             => $adj->notes ?? '—',
            'created_at'        => $adj->created_at ? $adj->created_at->format('Y-m-d H:i') : '—',
        ];

        $output = [];
        foreach ($this->getActiveColumns() as $col) {
            $output[] = $values[$col] ?? '';
        }

        return $output;
    }
}
