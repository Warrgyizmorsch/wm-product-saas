<?php

namespace App\Exports;

use App\Domains\Purchase\Models\PurchaseRequisition;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class PurchaseRequisitionExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    public function __construct(
        private readonly int $tenantId,
        private readonly array $filters = []
    ) {}

    public function collection()
    {
        $query = PurchaseRequisition::where('tenant_id', $this->tenantId)
            ->with(['requester', 'items.product']);

        // 1. Status Filter
        if (!empty($this->filters['status']) && $this->filters['status'] !== 'all') {
            $query->where('status', $this->filters['status']);
        }

        // 2. Date Range
        if (!empty($this->filters['date_from'])) {
            $query->whereDate('requisition_date', '>=', $this->filters['date_from']);
        }
        if (!empty($this->filters['date_to'])) {
            $query->whereDate('requisition_date', '<=', $this->filters['date_to']);
        }

        // 3. Search Keyword
        if (!empty($this->filters['search'])) {
            $search = trim($this->filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('requisition_number', 'like', "%{$search}%")
                  ->orWhere('requisition_slip_number', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%")
                  ->orWhereHas('requester', function ($uq) use ($search) {
                      $uq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        // 4. Sorting
        $sortBy = $this->filters['sort_by'] ?? 'requisition_date';
        $sortOrder = strtolower($this->filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['requisition_number', 'requisition_date', 'expected_date', 'status', 'created_at'];

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
            'requisition_number' => 'PR Number',
            'slip_number'        => 'Production Slip Reference',
            'requester_name'     => 'Requested By',
            'requisition_date'   => 'Requisition Date',
            'expected_date'      => 'Required By Date',
            'status'             => 'Status',
            'items_count'        => 'Total Items Count',
            'items_summary'      => 'Items Summary',
            'notes'              => 'Notes / Purpose',
            'rejection_reason'   => 'Rejection Reason',
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

    public function map($pr): array
    {
        $itemsSummary = $pr->items
            ->map(fn($item) => ($item->product?->name ?? 'Item') . ' (' . (float)$item->quantity . ')')
            ->filter()
            ->implode(', ');

        $values = [
            'requisition_number' => $pr->requisition_number,
            'slip_number'        => $pr->requisition_slip_number ?? '—',
            'requester_name'     => $pr->requester?->name ?? '—',
            'requisition_date'   => $pr->requisition_date ? date('Y-m-d', strtotime($pr->requisition_date)) : '—',
            'expected_date'      => $pr->expected_date ? date('Y-m-d', strtotime($pr->expected_date)) : '—',
            'status'             => ucfirst((string)$pr->status),
            'items_count'        => $pr->items?->count() ?? 0,
            'items_summary'      => $itemsSummary ?: '—',
            'notes'              => $pr->notes ?? '—',
            'rejection_reason'   => $pr->rejection_reason ?? '—',
            'created_at'         => $pr->created_at ? $pr->created_at->format('Y-m-d H:i') : '—',
        ];

        $output = [];
        foreach ($this->getActiveColumns() as $col) {
            $output[] = $values[$col] ?? '';
        }

        return $output;
    }
}
