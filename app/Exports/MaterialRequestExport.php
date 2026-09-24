<?php

namespace App\Exports;

use App\Domains\Production\Models\ProductionRequisitionSlip;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class MaterialRequestExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    public function __construct(
        private readonly int $tenantId,
        private readonly array $filters = []
    ) {}

    public function collection()
    {
        $query = ProductionRequisitionSlip::where('tenant_id', $this->tenantId)
            ->with(['order.product', 'items.product']);

        // 1. Status Filter
        if (!empty($this->filters['status']) && $this->filters['status'] !== 'all') {
            $query->where('status', $this->filters['status']);
        }

        // 2. Search Keyword
        if (!empty($this->filters['search'])) {
            $search = trim($this->filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('requisition_number', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%")
                  ->orWhereHas('order', function ($oq) use ($search) {
                      $oq->where('order_number', 'like', "%{$search}%")
                        ->orWhereHas('product', function ($pq) use ($search) {
                            $pq->where('name', 'like', "%{$search}%");
                        });
                  });
            });
        }

        // 3. Sorting
        $sortBy = $this->filters['sort_by'] ?? 'requisition_date';
        $sortOrder = strtolower($this->filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['requisition_number', 'requisition_date', 'status', 'created_at'];

        if (in_array($sortBy, $allowedSorts, true)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('requisition_date', 'desc');
        }

        return $query->get();
    }

    public static function availableColumns(): array
    {
        return [
            'requisition_number' => 'Material Slip Number',
            'production_order'   => 'Production Order Number',
            'product_name'       => 'FG Product Name',
            'requisition_date'   => 'Requested Date',
            'status'             => 'Status',
            'items_count'        => 'Items Count',
            'items_summary'      => 'Items Requested Summary',
            'notes'              => 'Notes',
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

    public function map($slip): array
    {
        $itemsSummary = $slip->items
            ->map(fn($item) => ($item->product?->name ?? 'Item') . ' (Req: ' . (float)$item->quantity_requested . ', Issued: ' . (float)$item->quantity_issued . ')')
            ->filter()
            ->implode(', ');

        $values = [
            'requisition_number' => $slip->requisition_number,
            'production_order'   => $slip->order?->order_number ?? '—',
            'product_name'       => $slip->order?->product?->name ?? '—',
            'requisition_date'   => $slip->requisition_date ? date('Y-m-d', strtotime($slip->requisition_date)) : '—',
            'status'             => ucfirst((string)$slip->status),
            'items_count'        => $slip->items?->count() ?? 0,
            'items_summary'      => $itemsSummary ?: '—',
            'notes'              => $slip->notes ?? '—',
            'created_at'         => $slip->created_at ? $slip->created_at->format('Y-m-d H:i') : '—',
        ];

        $output = [];
        foreach ($this->getActiveColumns() as $col) {
            $output[] = $values[$col] ?? '';
        }

        return $output;
    }
}
