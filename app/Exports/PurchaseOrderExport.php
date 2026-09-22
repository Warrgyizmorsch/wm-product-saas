<?php

namespace App\Exports;

use App\Domains\Purchase\Models\PurchaseOrder;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class PurchaseOrderExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    public function __construct(
        private readonly int $tenantId,
        private readonly array $filters = []
    ) {}

    public function collection()
    {
        $query = PurchaseOrder::where('tenant_id', $this->tenantId)
            ->with(['vendor', 'creator', 'items.product', 'requisition', 'grns', 'bills']);

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
            $query->whereDate('date', '>=', $this->filters['date_from']);
        }
        if (!empty($this->filters['date_to'])) {
            $query->whereDate('date', '<=', $this->filters['date_to']);
        }

        // 4. Search Keyword
        if (!empty($this->filters['search'])) {
            $search = trim($this->filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('purchase_order_number', 'like', "%{$search}%")
                  ->orWhere('supplier_quotation_number', 'like', "%{$search}%")
                  ->orWhere('reference', 'like', "%{$search}%")
                  ->orWhereHas('vendor', function ($vq) use ($search) {
                      $vq->where('name', 'like', "%{$search}%")
                        ->orWhere('company_name', 'like', "%{$search}%")
                        ->orWhere('gstin', 'like', "%{$search}%");
                  });
            });
        }

        // 5. Sorting
        $sortBy = $this->filters['sort_by'] ?? 'date';
        $sortOrder = strtolower($this->filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['purchase_order_number', 'date', 'delivery_date', 'grand_total', 'status', 'created_at'];

        if (in_array($sortBy, $allowedSorts, true)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('date', 'desc');
        }

        return $query->get();
    }

    public static function availableColumns(): array
    {
        return [
            'po_number'       => 'PO Number',
            'vendor_name'     => 'Supplier / Vendor Name',
            'company_name'    => 'Vendor Company Name',
            'vendor_gstin'    => 'Vendor GSTIN',
            'pr_number'       => 'Linked PR Number',
            'po_date'         => 'PO Date',
            'delivery_date'   => 'Expected Delivery Date',
            'subtotal'        => 'Subtotal (₹)',
            'discount_amount' => 'Discount (₹)',
            'tax_amount'      => 'GST Amount (₹)',
            'freight_amount'  => 'Freight Charges (₹)',
            'grand_total'     => 'Grand Total (₹)',
            'status'          => 'PO Status',
            'grn_count'       => 'Linked GRN Count',
            'bills_count'     => 'Linked Bills Count',
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

    public function map($po): array
    {
        $values = [
            'po_number'       => $po->purchase_order_number,
            'vendor_name'     => $po->vendor?->name ?? '—',
            'company_name'    => $po->vendor?->company_name ?? '—',
            'vendor_gstin'    => $po->vendor?->gstin ?? '—',
            'pr_number'       => $po->requisition?->requisition_number ?? '—',
            'po_date'         => $po->date ? date('Y-m-d', strtotime($po->date)) : '—',
            'delivery_date'   => $po->delivery_date ? date('Y-m-d', strtotime($po->delivery_date)) : '—',
            'subtotal'        => (float)($po->subtotal ?? 0),
            'discount_amount' => (float)($po->discount_amount ?? 0),
            'tax_amount'      => (float)($po->tax_amount ?? 0),
            'freight_amount'  => (float)($po->freight_amount ?? 0),
            'grand_total'     => (float)($po->grand_total ?? 0),
            'status'          => ucfirst((string)$po->status),
            'grn_count'       => $po->grns?->count() ?? 0,
            'bills_count'     => $po->bills?->count() ?? 0,
            'creator_name'    => $po->creator?->name ?? '—',
            'notes'           => $po->notes ?? '—',
            'created_at'      => $po->created_at ? $po->created_at->format('Y-m-d H:i') : '—',
        ];

        $output = [];
        foreach ($this->getActiveColumns() as $col) {
            $output[] = $values[$col] ?? '';
        }

        return $output;
    }
}
