<?php

namespace App\Exports;

use App\Domains\Inventory\Models\StockTransfer;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class StockTransferExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    public function __construct(
        private readonly int $tenantId,
        private readonly array $filters = []
    ) {}

    public function collection()
    {
        $query = StockTransfer::where('tenant_id', $this->tenantId)
            ->with(['fromWarehouse', 'toWarehouse', 'items.product', 'creator']);

        // 1. Status Filter
        if (!empty($this->filters['status']) && $this->filters['status'] !== 'all') {
            $query->where('status', $this->filters['status']);
        }

        // 2. From Warehouse Filter
        if (!empty($this->filters['from_warehouse_id'])) {
            $query->where('from_warehouse_id', $this->filters['from_warehouse_id']);
        }

        // 3. To Warehouse Filter
        if (!empty($this->filters['to_warehouse_id'])) {
            $query->where('to_warehouse_id', $this->filters['to_warehouse_id']);
        }

        // 4. Date Range
        if (!empty($this->filters['date_from'])) {
            $query->whereDate('transfer_date', '>=', $this->filters['date_from']);
        }
        if (!empty($this->filters['date_to'])) {
            $query->whereDate('transfer_date', '<=', $this->filters['date_to']);
        }

        // 5. Search Keyword
        if (!empty($this->filters['search'])) {
            $search = trim($this->filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('transfer_number', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%")
                  ->orWhereHas('fromWarehouse', function ($wq) use ($search) {
                      $wq->where('name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('toWarehouse', function ($wq) use ($search) {
                      $wq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        // 6. Sorting
        $sortBy = $this->filters['sort_by'] ?? 'transfer_date';
        $sortOrder = strtolower($this->filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['transfer_number', 'transfer_date', 'status', 'created_at'];

        if (in_array($sortBy, $allowedSorts, true)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('transfer_date', 'desc');
        }

        return $query->get();
    }

    public static function availableColumns(): array
    {
        return [
            'transfer_number' => 'Transfer Number',
            'from_warehouse'  => 'Source Warehouse',
            'to_warehouse'    => 'Destination Warehouse',
            'transfer_date'   => 'Transfer Date',
            'status'          => 'Status',
            'items_count'     => 'Items Count',
            'items_summary'   => 'Items Transferred',
            'creator_name'    => 'Created By',
            'notes'           => 'Notes',
            'created_at'      => 'Created Date',
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

    public function map($transfer): array
    {
        $itemsSummary = $transfer->items
            ->map(fn($item) => ($item->product?->name ?? 'Item') . ' (' . (float)$item->quantity . ')')
            ->filter()
            ->implode(', ');

        $values = [
            'transfer_number' => $transfer->transfer_number,
            'from_warehouse'  => $transfer->fromWarehouse?->name ?? '—',
            'to_warehouse'    => $transfer->toWarehouse?->name ?? '—',
            'transfer_date'   => $transfer->transfer_date ? date('Y-m-d', strtotime($transfer->transfer_date)) : '—',
            'status'          => ucfirst((string)$transfer->status),
            'items_count'     => $transfer->items?->count() ?? 0,
            'items_summary'   => $itemsSummary ?: '—',
            'creator_name'    => $transfer->creator?->name ?? '—',
            'notes'           => $transfer->notes ?? '—',
            'created_at'      => $transfer->created_at ? $transfer->created_at->format('Y-m-d H:i') : '—',
        ];

        $output = [];
        foreach ($this->getActiveColumns() as $col) {
            $output[] = $values[$col] ?? '';
        }

        return $output;
    }
}
