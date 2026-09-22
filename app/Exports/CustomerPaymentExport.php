<?php

namespace App\Exports;

use App\Domains\Sales\Models\CustomerPayment;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class CustomerPaymentExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    public function __construct(
        private readonly int $tenantId,
        private readonly array $filters = []
    ) {}

    public function collection()
    {
        $query = CustomerPayment::where('tenant_id', $this->tenantId)
            ->with(['customer', 'allocations.invoice']);

        // 1. Status Filter
        if (!empty($this->filters['status']) && $this->filters['status'] !== 'all') {
            $query->where('status', $this->filters['status']);
        }

        // 2. Customer Filter
        if (!empty($this->filters['customer_id'])) {
            $query->where('customer_id', $this->filters['customer_id']);
        }

        // 3. Payment Method Filter
        if (!empty($this->filters['payment_method'])) {
            $query->where('payment_method', $this->filters['payment_method']);
        }

        // 4. Date Range
        if (!empty($this->filters['date_from'])) {
            $query->whereDate('payment_date', '>=', $this->filters['date_from']);
        }
        if (!empty($this->filters['date_to'])) {
            $query->whereDate('payment_date', '<=', $this->filters['date_to']);
        }

        // 5. Search keyword
        if (!empty($this->filters['search'])) {
            $search = trim($this->filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('payment_number', 'like', "%{$search}%")
                  ->orWhere('reference_no', 'like', "%{$search}%")
                  ->orWhere('payment_method', 'like', "%{$search}%")
                  ->orWhereHas('customer', function ($cq) use ($search) {
                      $cq->where('name', 'like', "%{$search}%")
                        ->orWhere('company_name', 'like', "%{$search}%");
                  });
            });
        }

        // 6. Sorting
        $sortBy = $this->filters['sort_by'] ?? 'payment_date';
        $sortOrder = strtolower($this->filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['payment_number', 'payment_date', 'amount', 'status', 'created_at'];

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
            'payment_number'     => 'Receipt / Voucher Number',
            'customer_name'      => 'Customer Name',
            'company_name'       => 'Company Name',
            'customer_gstin'     => 'Customer GSTIN',
            'payment_date'       => 'Payment Date',
            'amount'             => 'Amount Received (₹)',
            'payment_method'     => 'Payment Mode / Method',
            'reference_no'       => 'Reference / Cheque / UTR No',
            'status'             => 'Payment Status',
            'allocated_invoices' => 'Allocated Invoice Numbers',
            'notes'              => 'Notes / Remarks',
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

    public function map($payment): array
    {
        $invoicesList = $payment->allocations
            ->map(fn($a) => ($a->invoice?->invoice_number ?? 'Inv#' . $a->invoice_id) . ' (₹' . number_format($a->allocated_amount, 2) . ')')
            ->filter()
            ->implode(', ');

        $values = [
            'payment_number'     => $payment->payment_number,
            'customer_name'      => $payment->customer?->name ?? '—',
            'company_name'       => $payment->customer?->company_name ?? '—',
            'customer_gstin'     => $payment->customer?->gstin ?? '—',
            'payment_date'       => $payment->payment_date ? date('Y-m-d', strtotime($payment->payment_date)) : '—',
            'amount'             => (float)($payment->amount ?? 0),
            'payment_method'     => strtoupper((string)($payment->payment_method ?? '—')),
            'reference_no'       => $payment->reference_no ?? '—',
            'status'             => ucfirst((string)$payment->status),
            'allocated_invoices' => $invoicesList ?: 'Unallocated',
            'notes'              => $payment->notes ?? '—',
            'created_at'         => $payment->created_at ? $payment->created_at->format('Y-m-d H:i') : '—',
        ];

        $output = [];
        foreach ($this->getActiveColumns() as $col) {
            $output[] = $values[$col] ?? '';
        }

        return $output;
    }
}
