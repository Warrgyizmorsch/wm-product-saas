<?php

namespace App\Exports;

use App\Domains\Purchase\Models\PurchaseReturn;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class PurchaseReturnExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    public function __construct(
        private readonly int $tenantId,
        private readonly array $filters = []
    ) {}

    public function collection()
    {
        $query = PurchaseReturn::where('tenant_id', $this->tenantId)
            ->with(['vendor', 'purchaseOrder', 'goodsReceiptNote', 'vendorBill']);

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
            $query->whereDate('return_date', '>=', $this->filters['date_from']);
        }
        if (!empty($this->filters['date_to'])) {
            $query->whereDate('return_date', '<=', $this->filters['date_to']);
        }

        // 4. Search Keyword
        if (!empty($this->filters['search'])) {
            $search = trim($this->filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('return_number', 'like', "%{$search}%")
                  ->orWhere('reason', 'like', "%{$search}%")
                  ->orWhereHas('vendor', function ($vq) use ($search) {
                      $vq->where('name', 'like', "%{$search}%")
                        ->orWhere('company_name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('purchaseOrder', function ($poq) use ($search) {
                      $poq->where('purchase_order_number', 'like', "%{$search}%");
                  })
                  ->orWhereHas('vendorBill', function ($bq) use ($search) {
                      $bq->where('bill_number', 'like', "%{$search}%");
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
            $query->orderBy('return_date', 'desc');
        }

        return $query->get();
    }

    public static function availableColumns(): array
    {
        return [
            'return_number'       => 'Debit Note / Return Number',
            'po_number'           => 'Purchase Order Number',
            'grn_number'          => 'Linked GRN Number',
            'bill_number'         => 'Vendor Bill Number',
            'vendor_name'         => 'Supplier / Vendor Name',
            'company_name'        => 'Vendor Company Name',
            'vendor_gstin'        => 'Vendor GSTIN',
            'return_date'         => 'Return Date',
            'reason'              => 'Return Reason',
            'total_amount'        => 'Total Return Value (₹)',
            'total_refund_amount' => 'Total Refund Amount (₹)',
            'status'              => 'Return Status',
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
            'po_number'           => $return->purchaseOrder?->purchase_order_number ?? '—',
            'grn_number'          => $return->goodsReceiptNote?->grn_number ?? '—',
            'bill_number'         => $return->vendorBill?->bill_number ?? '—',
            'vendor_name'         => $return->vendor?->name ?? '—',
            'company_name'        => $return->vendor?->company_name ?? '—',
            'vendor_gstin'        => $return->vendor?->gstin ?? '—',
            'return_date'         => $return->return_date ? date('Y-m-d', strtotime($return->return_date)) : '—',
            'reason'              => $return->reason ?? '—',
            'total_amount'        => (float)($return->total_amount ?? 0),
            'total_refund_amount' => (float)($return->total_refund_amount ?? 0),
            'status'              => ucfirst((string)$return->status),
            'created_at'          => $return->created_at ? $return->created_at->format('Y-m-d H:i') : '—',
        ];

        $output = [];
        foreach ($this->getActiveColumns() as $col) {
            $output[] = $values[$col] ?? '';
        }

        return $output;
    }
}
