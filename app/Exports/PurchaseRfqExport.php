<?php

namespace App\Exports;

use App\Domains\Purchase\Models\PurchaseRfq;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class PurchaseRfqExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    public function __construct(
        private readonly int $tenantId,
        private readonly array $filters = []
    ) {}

    public function collection()
    {
        $query = PurchaseRfq::where('tenant_id', $this->tenantId)
            ->with(['requisition', 'creator', 'items.product', 'rfqVendors.vendor']);

        // 1. Status Filter
        if (!empty($this->filters['status']) && $this->filters['status'] !== 'all') {
            $query->where('status', $this->filters['status']);
        }

        // 2. Date Range
        if (!empty($this->filters['date_from'])) {
            $query->whereDate('rfq_date', '>=', $this->filters['date_from']);
        }
        if (!empty($this->filters['date_to'])) {
            $query->whereDate('rfq_date', '<=', $this->filters['date_to']);
        }

        // 3. Search Keyword
        if (!empty($this->filters['search'])) {
            $search = trim($this->filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('rfq_number', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%")
                  ->orWhereHas('requisition', function ($rq) use ($search) {
                      $rq->where('requisition_number', 'like', "%{$search}%");
                  })
                  ->orWhereHas('rfqVendors.vendor', function ($vq) use ($search) {
                      $vq->where('name', 'like', "%{$search}%")
                        ->orWhere('company_name', 'like', "%{$search}%");
                  });
            });
        }

        // 4. Sorting
        $sortBy = $this->filters['sort_by'] ?? 'rfq_date';
        $sortOrder = strtolower($this->filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['rfq_number', 'rfq_date', 'status', 'created_at'];

        if (in_array($sortBy, $allowedSorts, true)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('rfq_date', 'desc');
        }

        return $query->get();
    }

    public static function availableColumns(): array
    {
        return [
            'rfq_number'         => 'RFQ Number',
            'pr_number'          => 'Linked PR Number',
            'rfq_date'           => 'RFQ Date',
            'status'             => 'Status',
            'vendors_count'      => 'Invited Vendors Count',
            'vendors_list'       => 'Invited Vendors List',
            'items_count'        => 'Items Count',
            'items_summary'      => 'Items Summary',
            'creator_name'       => 'Created By',
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

    public function map($rfq): array
    {
        $vendorsList = $rfq->rfqVendors
            ->map(fn($v) => $v->vendor?->name ?? $v->vendor?->company_name)
            ->filter()
            ->implode(', ');

        $itemsSummary = $rfq->items
            ->map(fn($item) => ($item->product?->name ?? 'Item') . ' (' . (float)$item->quantity . ')')
            ->filter()
            ->implode(', ');

        $values = [
            'rfq_number'         => $rfq->rfq_number,
            'pr_number'          => $rfq->requisition?->requisition_number ?? '—',
            'rfq_date'           => $rfq->rfq_date ? date('Y-m-d', strtotime($rfq->rfq_date)) : '—',
            'status'             => ucfirst((string)$rfq->status),
            'vendors_count'      => $rfq->rfqVendors?->count() ?? 0,
            'vendors_list'       => $vendorsList ?: '—',
            'items_count'        => $rfq->items?->count() ?? 0,
            'items_summary'      => $itemsSummary ?: '—',
            'creator_name'       => $rfq->creator?->name ?? '—',
            'notes'              => $rfq->notes ?? '—',
            'created_at'         => $rfq->created_at ? $rfq->created_at->format('Y-m-d H:i') : '—',
        ];

        $output = [];
        foreach ($this->getActiveColumns() as $col) {
            $output[] = $values[$col] ?? '';
        }

        return $output;
    }
}
