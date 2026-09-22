<?php

namespace App\Exports;

use App\Domains\Inventory\Models\Vendor;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class VendorExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    public function __construct(
        private readonly int $tenantId,
        private readonly array $filters = []
    ) {}

    public function collection()
    {
        $query = Vendor::where('tenant_id', $this->tenantId);

        // 1. Status Filter
        if (!empty($this->filters['status']) && $this->filters['status'] !== 'all') {
            $query->where('status', $this->filters['status']);
        }

        // 2. Search Keyword
        if (!empty($this->filters['search'])) {
            $search = trim($this->filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('company_name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('gstin', 'like', "%{$search}%");
            });
        }

        // 3. Sorting
        $sortBy = $this->filters['sort_by'] ?? 'created_at';
        $sortOrder = strtolower($this->filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['name', 'company_name', 'code', 'email', 'status', 'created_at'];

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
            'code'           => 'Supplier Code',
            'name'           => 'Display Name',
            'company_name'   => 'Company Name',
            'contact_person' => 'Contact Person',
            'email'          => 'Email Address',
            'phone'          => 'Phone Number',
            'gstin'          => 'GSTIN',
            'pan'            => 'PAN Number',
            'address'        => 'Address',
            'city'           => 'City',
            'state'          => 'State',
            'country'        => 'Country',
            'pincode'        => 'Pincode / Postal Code',
            'payment_terms'  => 'Payment Terms',
            'status'         => 'Status',
            'created_at'     => 'Created Date',
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

    public function map($vendor): array
    {
        $values = [
            'code'           => $vendor->code ?? '—',
            'name'           => $vendor->name ?? '—',
            'company_name'   => $vendor->company_name ?? '—',
            'contact_person' => $vendor->contact_person ?? '—',
            'email'          => $vendor->email ?? '—',
            'phone'          => $vendor->phone ?? '—',
            'gstin'          => $vendor->gstin ?? '—',
            'pan'            => $vendor->pan ?? '—',
            'address'        => $vendor->address ?? '—',
            'city'           => $vendor->city ?? '—',
            'state'          => $vendor->state ?? '—',
            'country'        => $vendor->country ?? '—',
            'pincode'        => $vendor->pincode ?? '—',
            'payment_terms'  => \App\Domains\Platform\Models\PaymentTerm::getLabel($vendor->payment_terms ?? ''),
            'status'         => ucfirst((string)$vendor->status),
            'created_at'     => $vendor->created_at ? $vendor->created_at->format('Y-m-d H:i') : '—',
        ];

        $output = [];
        foreach ($this->getActiveColumns() as $col) {
            $output[] = $values[$col] ?? '';
        }

        return $output;
    }
}
