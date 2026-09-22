<?php

namespace App\Exports;

use App\Domains\Purchase\Models\GoodsReceiptNote;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class GoodsReceiptNoteExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    public function __construct(
        private readonly int $tenantId,
        private readonly array $filters = []
    ) {}

    public function collection()
    {
        $query = GoodsReceiptNote::where('tenant_id', $this->tenantId)
            ->with(['purchaseOrder', 'vendor', 'warehouse', 'items.product', 'creator']);

        // 1. Status Filter
        if (!empty($this->filters['status']) && $this->filters['status'] !== 'all') {
            $query->where('status', $this->filters['status']);
        }

        // 2. Vendor Filter
        if (!empty($this->filters['vendor_id'])) {
            $query->where('vendor_id', $this->filters['vendor_id']);
        }

        // 3. Warehouse Filter
        if (!empty($this->filters['warehouse_id'])) {
            $query->where('warehouse_id', $this->filters['warehouse_id']);
        }

        // 4. Date Range
        if (!empty($this->filters['date_from'])) {
            $query->whereDate('received_date', '>=', $this->filters['date_from']);
        }
        if (!empty($this->filters['date_to'])) {
            $query->whereDate('received_date', '<=', $this->filters['date_to']);
        }

        // 5. Search Keyword
        if (!empty($this->filters['search'])) {
            $search = trim($this->filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('grn_number', 'like', "%{$search}%")
                  ->orWhere('challan_number', 'like', "%{$search}%")
                  ->orWhere('lr_number', 'like', "%{$search}%")
                  ->orWhere('vehicle_number', 'like', "%{$search}%")
                  ->orWhereHas('vendor', function ($vq) use ($search) {
                      $vq->where('name', 'like', "%{$search}%")
                        ->orWhere('company_name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('purchaseOrder', function ($poq) use ($search) {
                      $poq->where('purchase_order_number', 'like', "%{$search}%");
                  });
            });
        }

        // 6. Sorting
        $sortBy = $this->filters['sort_by'] ?? 'received_date';
        $sortOrder = strtolower($this->filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['grn_number', 'received_date', 'challan_number', 'status', 'created_at'];

        if (in_array($sortBy, $allowedSorts, true)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('received_date', 'desc');
        }

        return $query->get();
    }

    public static function availableColumns(): array
    {
        return [
            'grn_number'       => 'GRN Number',
            'po_number'        => 'Purchase Order Number',
            'vendor_name'      => 'Supplier / Vendor Name',
            'warehouse_name'   => 'Receiving Warehouse',
            'received_date'    => 'Receipt Date',
            'challan_number'   => 'Delivery Challan No',
            'challan_date'     => 'Challan Date',
            'vehicle_number'   => 'Vehicle Number',
            'transporter_name' => 'Transporter / Carrier',
            'lr_number'        => 'LR / Docket Number',
            'items_count'      => 'Items Count',
            'status'           => 'GRN Status',
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

    public function map($grn): array
    {
        $values = [
            'grn_number'       => $grn->grn_number,
            'po_number'        => $grn->purchaseOrder?->purchase_order_number ?? '—',
            'vendor_name'      => $grn->vendor?->name ?? '—',
            'warehouse_name'   => $grn->warehouse?->name ?? '—',
            'received_date'    => $grn->received_date ? date('Y-m-d', strtotime($grn->received_date)) : '—',
            'challan_number'   => $grn->challan_number ?? '—',
            'challan_date'     => $grn->challan_date ? date('Y-m-d', strtotime($grn->challan_date)) : '—',
            'vehicle_number'   => $grn->vehicle_number ?? '—',
            'transporter_name' => $grn->transporter_name ?? '—',
            'lr_number'        => $grn->lr_number ?? '—',
            'items_count'      => $grn->items?->count() ?? 0,
            'status'           => ucfirst((string)$grn->status),
            'notes'            => $grn->notes ?? '—',
            'created_at'       => $grn->created_at ? $grn->created_at->format('Y-m-d H:i') : '—',
        ];

        $output = [];
        foreach ($this->getActiveColumns() as $col) {
            $output[] = $values[$col] ?? '';
        }

        return $output;
    }
}
