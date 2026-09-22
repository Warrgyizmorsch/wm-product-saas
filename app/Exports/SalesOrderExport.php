<?php

namespace App\Exports;

use App\Domains\Sales\Models\SalesOrder;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class SalesOrderExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    public function __construct(
        private readonly int $tenantId,
        private readonly array $filters = []
    ) {}

    public function collection()
    {
        $query = SalesOrder::where('tenant_id', $this->tenantId)
            ->with(['customer', 'salesPerson', 'quotation', 'invoices', 'dispatches']);

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
            $query->whereDate('order_date', '>=', $this->filters['date_from']);
        }
        if (!empty($this->filters['date_to'])) {
            $query->whereDate('order_date', '<=', $this->filters['date_to']);
        }

        // 4. Search keyword
        if (!empty($this->filters['search'])) {
            $search = trim($this->filters['search']);
            $cleanSearch = str_replace('SO-', '', $search);
            $query->where(function ($q) use ($search, $cleanSearch) {
                $q->where('sales_order_number', 'like', "%{$cleanSearch}%")
                  ->orWhere('sales_order_number', 'like', "%{$search}%")
                  ->orWhereHas('customer', function ($cq) use ($search) {
                      $cq->where('name', 'like', "%{$search}%")
                        ->orWhere('company_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                  });
            });
        }

        // 5. Sorting
        $sortBy = $this->filters['sort_by'] ?? 'order_date';
        $sortOrder = strtolower($this->filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['sales_order_number', 'order_date', 'shipment_date', 'total_amount', 'status', 'created_at'];
        if (in_array($sortBy, $allowedSorts, true)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('id', 'desc');
        }

        return $query->get();
    }

    public static function availableColumns(): array
    {
        return [
            'sales_order_number' => 'Sales Order Number',
            'customer_name'      => 'Customer Name',
            'company_name'       => 'Company Name',
            'customer_gstin'     => 'Customer GSTIN',
            'quotation_number'   => 'Linked Quotation',
            'order_date'         => 'Order Date',
            'shipment_date'      => 'Expected Shipment Date',
            'payment_terms'      => 'Payment Terms',
            'sales_person'       => 'Sales Person / Rep',
            'gst_type'           => 'GST Type',
            'subtotal'           => 'Subtotal (₹)',
            'discount'           => 'Discount (₹)',
            'tax'                => 'Tax (₹)',
            'freight_amount'     => 'Freight Charges (₹)',
            'total_amount'       => 'Grand Total (₹)',
            'status'             => 'Order Status',
            'invoiced_count'     => 'Linked Invoices Count',
            'dispatched_count'   => 'Linked Dispatches Count',
            'billing_address'    => 'Billing Address',
            'shipping_address'   => 'Shipping Address',
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

    public function map($order): array
    {
        $values = [
            'sales_order_number' => $order->sales_order_number,
            'customer_name'      => $order->customer?->name ?? '—',
            'company_name'       => $order->customer?->company_name ?? '—',
            'customer_gstin'     => $order->customer?->gstin ?? '—',
            'quotation_number'   => $order->quotation?->quotation_number ?? '—',
            'order_date'         => $order->order_date ? date('Y-m-d', strtotime($order->order_date)) : '—',
            'shipment_date'      => $order->shipment_date ? date('Y-m-d', strtotime($order->shipment_date)) : '—',
            'payment_terms'      => \App\Domains\Platform\Models\PaymentTerm::getLabel($order->payment_terms),
            'sales_person'       => $order->salesPerson?->name ?? '—',
            'gst_type'           => $order->gst_type === 'igst' ? 'IGST (Inter-State)' : ($order->gst_type ? 'CGST + SGST (Intra-State)' : '—'),
            'subtotal'           => (float)($order->subtotal ?? 0),
            'discount'           => (float)($order->discount ?? 0),
            'tax'                => (float)($order->tax ?? 0),
            'freight_amount'     => (float)($order->freight_amount ?? 0),
            'total_amount'       => (float)($order->total_amount ?? 0),
            'status'             => ucfirst((string)$order->status),
            'invoiced_count'     => $order->invoices?->count() ?? 0,
            'dispatched_count'   => $order->dispatches?->count() ?? 0,
            'billing_address'    => $order->billing_address ?? '—',
            'shipping_address'   => $order->shipping_address ?? '—',
            'notes'              => $order->notes ?? '—',
            'created_at'         => $order->created_at ? $order->created_at->format('Y-m-d H:i') : '—',
        ];

        $output = [];
        foreach ($this->getActiveColumns() as $col) {
            $output[] = $values[$col] ?? '';
        }

        return $output;
    }
}
