<?php

namespace App\Exports;

use App\Domains\Sales\Models\SalesReturn;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class SalesReturnExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    public function __construct(
        private readonly int $tenantId,
        private readonly array $filters = []
    ) {}

    public function collection()
    {
        $query = SalesReturn::where('tenant_id', $this->tenantId)
            ->with(['customer', 'salesOrder', 'invoice']);

        // 1. Status Filter
        if (!empty($this->filters['status']) && $this->filters['status'] !== 'all') {
            $query->where('status', $this->filters['status']);
        }

        // 2. Customer Filter
        if (!empty($this->filters['customer_id'])) {
            $query->where('customer_id', $this->filters['customer_id']);
        }

        // 3. Date Range
        if (!empty($this->filters['date_from'])) {
            $query->whereDate('return_date', '>=', $this->filters['date_from']);
        }
        if (!empty($this->filters['date_to'])) {
            $query->whereDate('return_date', '<=', $this->filters['date_to']);
        }

        // 4. Search keyword
        if (!empty($this->filters['search'])) {
            $search = trim($this->filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('return_number', 'like', "%{$search}%")
                  ->orWhere('reason', 'like', "%{$search}%")
                  ->orWhereHas('customer', function ($cq) use ($search) {
                      $cq->where('name', 'like', "%{$search}%")
                        ->orWhere('company_name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('salesOrder', function ($sq) use ($search) {
                      $sq->where('sales_order_number', 'like', "%{$search}%");
                  })
                  ->orWhereHas('invoice', function ($iq) use ($search) {
                      $iq->where('invoice_number', 'like', "%{$search}%");
                  });
            });
        }

        // 5. Sorting
        $sortBy = $this->filters['sort_by'] ?? 'return_date';
        $sortOrder = strtolower($this->filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['return_number', 'return_date', 'total_amount', 'status', 'created_at'];

        if (in_array($sortBy, $allowedSorts, true)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->latest();
        }

        return $query->get();
    }

    public static function availableColumns(): array
    {
        return [
            'return_number'       => 'Credit Note / Return No',
            'invoice_number'      => 'Original Invoice Number',
            'sales_order_number'  => 'Sales Order Number',
            'customer_name'       => 'Customer Name',
            'company_name'        => 'Company Name',
            'customer_gstin'      => 'Customer GSTIN',
            'return_date'         => 'Return Date',
            'reason'              => 'Return Reason',
            'status'              => 'Return Status',
            'total_amount'        => 'Total Return Value (₹)',
            'total_refund_amount' => 'Total Refund Amount (₹)',
            'created_at'          => 'Created Date',
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

    public function map($return): array
    {
        $values = [
            'return_number'       => $return->return_number,
            'invoice_number'      => $return->invoice?->invoice_number ?? '—',
            'sales_order_number'  => $return->salesOrder?->sales_order_number ?? '—',
            'customer_name'       => $return->customer?->name ?? '—',
            'company_name'        => $return->customer?->company_name ?? '—',
            'customer_gstin'      => $return->customer?->gstin ?? '—',
            'return_date'         => $return->return_date ? date('Y-m-d', strtotime($return->return_date)) : '—',
            'reason'              => $return->reason ?? '—',
            'status'              => ucfirst((string)$return->status),
            'total_amount'        => (float)($return->total_amount ?? 0),
            'total_refund_amount' => (float)($return->total_refund_amount ?? 0),
            'created_at'          => $return->created_at ? $return->created_at->format('Y-m-d H:i') : '—',
        ];

        $output = [];
        foreach ($this->getActiveColumns() as $col) {
            $output[] = $values[$col] ?? '';
        }

        return $output;
    }
}
