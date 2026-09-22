<?php

namespace App\Exports;

use App\Domains\Purchase\Models\VendorPayment;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class VendorPaymentExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    public function __construct(
        private readonly int $tenantId,
        private readonly array $filters = []
    ) {}

    public function collection()
    {
        $query = VendorPayment::where('tenant_id', $this->tenantId)
            ->with(['vendor', 'purchaseOrder', 'allocations.bill']);

        // 1. Status Filter
        if (!empty($this->filters['status']) && $this->filters['status'] !== 'all') {
            $query->where('status', $this->filters['status']);
        }

        // 2. Vendor Filter
        if (!empty($this->filters['vendor_id'])) {
            $query->where('vendor_id', $this->filters['vendor_id']);
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

        // 5. Search Keyword
        if (!empty($this->filters['search'])) {
            $search = trim($this->filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('payment_number', 'like', "%{$search}%")
                  ->orWhere('reference_number', 'like', "%{$search}%")
                  ->orWhere('payment_method', 'like', "%{$search}%")
                  ->orWhereHas('vendor', function ($vq) use ($search) {
                      $vq->where('name', 'like', "%{$search}%")
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
            $query->orderBy('payment_date', 'desc');
        }

        return $query->get();
    }

    public static function availableColumns(): array
    {
        return [
            'payment_number'   => 'Payment Voucher Number',
            'vendor_name'      => 'Supplier / Vendor Name',
            'company_name'     => 'Vendor Company Name',
            'vendor_gstin'     => 'Vendor GSTIN',
            'payment_date'     => 'Payment Date',
            'payment_type'     => 'Payment Type',
            'amount'           => 'Amount Paid (₹)',
            'payment_method'   => 'Payment Mode / Method',
            'reference_number' => 'Reference / UTR / Cheque No',
            'status'           => 'Payment Status',
            'allocated_bills'  => 'Allocated Bill Numbers',
            'notes'            => 'Notes',
            'created_at'       => 'Created Date',
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
        $billsList = $payment->allocations
            ->map(fn($a) => ($a->bill?->bill_number ?? 'Bill#' . $a->vendor_bill_id) . ' (₹' . number_format($a->allocated_amount, 2) . ')')
            ->filter()
            ->implode(', ');

        $values = [
            'payment_number'   => $payment->payment_number,
            'vendor_name'      => $payment->vendor?->name ?? '—',
            'company_name'     => $payment->vendor?->company_name ?? '—',
            'vendor_gstin'     => $payment->vendor?->gstin ?? '—',
            'payment_date'     => $payment->payment_date ? date('Y-m-d', strtotime($payment->payment_date)) : '—',
            'payment_type'     => ucfirst((string)($payment->payment_type ?? 'Bill Payment')),
            'amount'           => (float)($payment->amount ?? 0),
            'payment_method'   => strtoupper((string)($payment->payment_method ?? '—')),
            'reference_number' => $payment->reference_number ?? '—',
            'status'           => ucfirst((string)$payment->status),
            'allocated_bills'  => $billsList ?: ($payment->payment_type === 'Advance' ? 'Advance Payment' : 'Unallocated'),
            'notes'            => $payment->notes ?? '—',
            'created_at'       => $payment->created_at ? $payment->created_at->format('Y-m-d H:i') : '—',
        ];

        $output = [];
        foreach ($this->getActiveColumns() as $col) {
            $output[] = $values[$col] ?? '';
        }

        return $output;
    }
}
