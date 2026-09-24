<?php

namespace App\Exports;

use App\Domains\CRM\Models\Customer;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class CustomerExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    public function __construct(
        private readonly int $tenantId,
        private readonly array $filters = []
    ) {}

    public function collection()
    {
        $query = Customer::where('tenant_id', $this->tenantId)
            ->withCount(['salesOrders', 'invoices']);

        // 1. Status filter
        if (!empty($this->filters['status'])) {
            $query->where('status', $this->filters['status']);
        }

        // 2. Search keyword
        if (!empty($this->filters['search'])) {
            $search = $this->filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('company_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('gstin', 'like', "%{$search}%");
            });
        }

        // 3. Sorting
        $sortBy = $this->filters['sort_by'] ?? 'created_at';
        $sortOrder = strtolower($this->filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['id', 'name', 'company_name', 'email', 'phone', 'created_at', 'status'];
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
            'name'                => 'Customer Name',
            'company_name'        => 'Company Name',
            'email'               => 'Email Address',
            'phone'               => 'Phone Number',
            'gstin'               => 'GSTIN',
            'status'              => 'Status',
            'billing_address'     => 'Billing Address',
            'shipping_address'    => 'Shipping Address',
            'opening_balance'     => 'Opening Balance (₹)',
            'sales_orders_count'  => 'Total Sales Orders',
            'invoices_count'      => 'Total Invoices',
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

    public function map($customer): array
    {
        $values = [
            'name'               => $customer->name,
            'company_name'       => $customer->company_name ?? '—',
            'email'              => $customer->email ?? '—',
            'phone'              => $customer->phone ?? '—',
            'gstin'              => $customer->gstin ?? '—',
            'status'             => ucfirst((string)$customer->status),
            'billing_address'    => $customer->billing_address ?? '—',
            'shipping_address'   => $customer->shipping_address ?? '—',
            'opening_balance'    => (float)($customer->opening_balance ?? 0),
            'sales_orders_count' => (int)$customer->sales_orders_count,
            'invoices_count'     => (int)$customer->invoices_count,
            'created_at'         => $customer->created_at ? $customer->created_at->format('Y-m-d H:i') : '—',
        ];

        $output = [];
        foreach ($this->getActiveColumns() as $col) {
            $output[] = $values[$col] ?? '';
        }

        return $output;
    }
}
