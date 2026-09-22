<?php

namespace App\Exports;

use App\Domains\Sales\Models\DispatchOrder;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class DispatchOrderExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    public function __construct(
        private readonly int $tenantId,
        private readonly array $filters = []
    ) {}

    public function collection()
    {
        $query = DispatchOrder::where('tenant_id', $this->tenantId)
            ->with(['customer', 'salesOrder.customer', 'transporter', 'materialRequirement']);

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
            $query->whereDate('dispatch_date', '>=', $this->filters['date_from']);
        }
        if (!empty($this->filters['date_to'])) {
            $query->whereDate('dispatch_date', '<=', $this->filters['date_to']);
        }

        // 4. Search keyword
        if (!empty($this->filters['search'])) {
            $search = trim($this->filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('dispatch_number', 'like', "%{$search}%")
                  ->orWhere('carrier', 'like', "%{$search}%")
                  ->orWhere('vehicle_number', 'like', "%{$search}%")
                  ->orWhere('tracking_number', 'like', "%{$search}%")
                  ->orWhere('eway_bill_number', 'like', "%{$search}%")
                  ->orWhere('lr_number', 'like', "%{$search}%")
                  ->orWhereHas('customer', function ($cq) use ($search) {
                      $cq->where('name', 'like', "%{$search}%")
                        ->orWhere('company_name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('salesOrder', function ($sq) use ($search) {
                      $sq->where('sales_order_number', 'like', "%{$search}%");
                  });
            });
        }

        // 5. Sorting
        $sortBy = $this->filters['sort_by'] ?? 'created_at';
        $sortOrder = strtolower($this->filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['dispatch_number', 'dispatch_date', 'status', 'created_at'];

        if (in_array($sortBy, $allowedSorts, true)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('created_at', 'desc');
        }

        return $query->get();
    }

    public static function availableColumns(): array
    {
        return [
            'dispatch_number'   => 'Dispatch / Challan Number',
            'sales_order_number'=> 'Sales Order Number',
            'customer_name'     => 'Customer Name',
            'dispatch_date'     => 'Dispatch Date',
            'status'            => 'Status',
            'transporter_name'  => 'Transporter / Carrier',
            'vehicle_number'    => 'Vehicle Number',
            'driver_name'       => 'Driver Name',
            'driver_phone'      => 'Driver Phone',
            'tracking_number'   => 'Tracking / Docket Number',
            'eway_bill_number'  => 'E-Way Bill Number',
            'lr_number'         => 'LR / GR Number',
            'freight_terms'     => 'Freight Terms',
            'freight_amount'    => 'Freight Amount (₹)',
            'total_packages'    => 'Total Packages',
            'gross_weight'      => 'Gross Weight (Kg)',
            'net_weight'        => 'Net Weight (Kg)',
            'shipping_address'  => 'Shipping Address',
            'delivered_at'      => 'Delivered At',
            'notes'             => 'Notes',
            'created_at'        => 'Created Date',
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

    public function map($dispatch): array
    {
        $customerName = $dispatch->customer?->name ?? $dispatch->salesOrder?->customer?->name ?? '—';
        $transporterName = $dispatch->transporter?->name ?? $dispatch->carrier ?? '—';

        $values = [
            'dispatch_number'   => $dispatch->dispatch_number,
            'sales_order_number'=> $dispatch->salesOrder?->sales_order_number ?? '—',
            'customer_name'     => $customerName,
            'dispatch_date'     => $dispatch->dispatch_date ? date('Y-m-d', strtotime($dispatch->dispatch_date)) : '—',
            'status'            => ucfirst((string)$dispatch->status),
            'transporter_name'  => $transporterName,
            'vehicle_number'    => $dispatch->vehicle_number ?? '—',
            'driver_name'       => $dispatch->driver_name ?? '—',
            'driver_phone'      => $dispatch->driver_phone ?? '—',
            'tracking_number'   => $dispatch->tracking_number ?? '—',
            'eway_bill_number'  => $dispatch->eway_bill_number ?? '—',
            'lr_number'         => $dispatch->lr_number ?? '—',
            'freight_terms'     => ucfirst((string)($dispatch->freight_terms ?? '—')),
            'freight_amount'    => (float)($dispatch->freight_amount ?? 0),
            'total_packages'    => $dispatch->total_packages ?? '—',
            'gross_weight'      => (float)($dispatch->gross_weight ?? 0),
            'net_weight'        => (float)($dispatch->net_weight ?? 0),
            'shipping_address'  => $dispatch->shipping_address ?? '—',
            'delivered_at'      => $dispatch->delivered_at ? date('Y-m-d H:i', strtotime($dispatch->delivered_at)) : '—',
            'notes'             => $dispatch->notes ?? '—',
            'created_at'        => $dispatch->created_at ? $dispatch->created_at->format('Y-m-d H:i') : '—',
        ];

        $output = [];
        foreach ($this->getActiveColumns() as $col) {
            $output[] = $values[$col] ?? '';
        }

        return $output;
    }
}
