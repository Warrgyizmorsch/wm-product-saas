<?php

namespace App\Exports;

use App\Domains\Purchase\Models\VendorBill;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class VendorBillExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    public function __construct(
        private readonly int $tenantId,
        private readonly array $filters = []
    ) {}

    public function collection()
    {
        $query = VendorBill::where('tenant_id', $this->tenantId)
            ->with(['vendor', 'purchaseOrder', 'goodsReceiptNote', 'allocations.payment']);

        // 1. Status Filter
        if (!empty($this->filters['status']) && $this->filters['status'] !== 'all') {
            $query->where('status', $this->filters['status']);
        }

        // 2. Vendor Filter
        if (!empty($this->filters['vendor_id'])) {
            $query->where('vendor_id', $this->filters['vendor_id']);
        }

        // 3. Date Range
        if (!empty($this->filters['date_from'])) {
            $query->whereDate('bill_date', '>=', $this->filters['date_from']);
        }
        if (!empty($this->filters['date_to'])) {
            $query->whereDate('bill_date', '<=', $this->filters['date_to']);
        }

        // 4. Search Keyword
        if (!empty($this->filters['search'])) {
            $search = trim($this->filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('bill_number', 'like', "%{$search}%")
                  ->orWhere('vendor_invoice_number', 'like', "%{$search}%")
                  ->orWhereHas('vendor', function ($vq) use ($search) {
                      $vq->where('name', 'like', "%{$search}%")
                        ->orWhere('company_name', 'like', "%{$search}%")
                        ->orWhere('gstin', 'like', "%{$search}%");
                  });
            });
        }

        // 5. Sorting
        $sortBy = $this->filters['sort_by'] ?? 'bill_date';
        $sortOrder = strtolower($this->filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['bill_number', 'bill_date', 'due_date', 'total_amount', 'grand_total', 'status', 'created_at'];

        if (in_array($sortBy, $allowedSorts, true)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('bill_date', 'desc');
        }

        return $query->get();
    }

    public static function availableColumns(): array
    {
        return [
            'bill_number'            => 'System Bill Number',
            'vendor_invoice_number'  => 'Vendor Invoice / Bill No',
            'vendor_name'            => 'Supplier / Vendor Name',
            'company_name'           => 'Vendor Company Name',
            'vendor_gstin'           => 'Vendor GSTIN',
            'po_number'              => 'Linked PO Number',
            'grn_number'             => 'Linked GRN Number',
            'bill_date'              => 'Bill Date',
            'due_date'               => 'Due Date',
            'subtotal'               => 'Subtotal (₹)',
            'tax_amount'             => 'Tax Amount (₹)',
            'freight_amount'         => 'Freight Charges (₹)',
            'total_amount'           => 'Grand Total (₹)',
            'paid_amount'            => 'Amount Paid (₹)',
            'balance_due'            => 'Balance Due (₹)',
            'status'                 => 'Bill Status',
            'notes'                  => 'Notes',
            'created_at'             => 'Created Date',
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

    public function map($bill): array
    {
        $totalAmount = (float)($bill->total_amount ?: $bill->grand_total ?: 0);
        $paidAmount = (float)($bill->paid_amount ?: $bill->allocations->sum('amount'));
        $balanceDue = max(0, $totalAmount - $paidAmount);

        $values = [
            'bill_number'            => $bill->bill_number,
            'vendor_invoice_number'  => $bill->vendor_invoice_number ?: $bill->vendor_bill_number ?: '—',
            'vendor_name'            => $bill->vendor?->name ?? '—',
            'company_name'           => $bill->vendor?->company_name ?? '—',
            'vendor_gstin'           => $bill->vendor?->gstin ?? '—',
            'po_number'              => $bill->purchaseOrder?->purchase_order_number ?? '—',
            'grn_number'             => $bill->goodsReceiptNote?->grn_number ?? '—',
            'bill_date'              => $bill->bill_date ? date('Y-m-d', strtotime($bill->bill_date)) : '—',
            'due_date'               => $bill->due_date ? date('Y-m-d', strtotime($bill->due_date)) : '—',
            'subtotal'               => (float)($bill->subtotal ?? 0),
            'tax_amount'             => (float)($bill->tax_amount ?? 0),
            'freight_amount'         => (float)($bill->freight_amount ?? 0),
            'total_amount'           => $totalAmount,
            'paid_amount'            => $paidAmount,
            'balance_due'            => $balanceDue,
            'status'                 => ucfirst((string)$bill->status),
            'notes'                  => $bill->notes ?? '—',
            'created_at'             => $bill->created_at ? $bill->created_at->format('Y-m-d H:i') : '—',
        ];

        $output = [];
        foreach ($this->getActiveColumns() as $col) {
            $output[] = $values[$col] ?? '';
        }

        return $output;
    }
}
