<?php

namespace App\Exports;

use App\Domains\CRM\Models\CrmAccount;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class AccountExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    public function __construct(
        private readonly int $tenantId,
        private readonly array $filters = []
    ) {}

    public function collection()
    {
        $query = CrmAccount::where('tenant_id', $this->tenantId)
            ->with(['owner', 'primaryContact', 'contacts'])
            ->withCount('deals');

        // 1. Status filter
        if (!empty($this->filters['status'])) {
            $query->where('status', $this->filters['status']);
        }

        // 2. Industry Type filter
        if (!empty($this->filters['industry_type'])) {
            $query->where('industry_type', $this->filters['industry_type']);
        }

        // 3. Owner filter
        if (!empty($this->filters['owner_id'])) {
            $query->where('owner_id', $this->filters['owner_id']);
        }

        // 4. Search keyword
        if (!empty($this->filters['search'])) {
            $search = $this->filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('account_number', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('city', 'like', "%{$search}%")
                  ->orWhere('gstin', 'like', "%{$search}%")
                  ->orWhere('industry_type', 'like', "%{$search}%");
            });
        }

        // 5. Sorting
        $sortBy = $this->filters['sort_by'] ?? 'created_at';
        $sortOrder = strtolower($this->filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['id', 'account_number', 'name', 'city', 'state', 'created_at', 'status'];
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
            'account_number'       => 'Account Number',
            'name'                 => 'Account / Company Name',
            'primary_contact_name' => 'Primary Contact Person',
            'email'                => 'Email Address',
            'phone'                => 'Phone Number',
            'website'              => 'Website',
            'industry_type'        => 'Industry Type',
            'gstin'                => 'GSTIN',
            'credit_limit'         => 'Credit Limit (₹)',
            'street'               => 'Street Address',
            'city'                 => 'City',
            'state'                => 'State',
            'country'              => 'Country',
            'zip_code'             => 'Zip / Postal Code',
            'status'               => 'Status',
            'owner_name'           => 'Account Owner',
            'deals_count'          => 'Total Deals',
            'created_at'           => 'Created Date',
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

    public function map($account): array
    {
        $primaryContact = $account->primaryContact ?? $account->contacts->first();

        $values = [
            'account_number'       => $account->account_number,
            'name'                 => $account->name,
            'primary_contact_name' => $primaryContact ? ($primaryContact->name ?: ($primaryContact->first_name . ' ' . $primaryContact->last_name)) : '—',
            'email'                => $account->email ?? '—',
            'phone'                => $account->phone ?? '—',
            'website'              => $account->website ?? '—',
            'industry_type'        => $account->industry_type ?? '—',
            'gstin'                => $account->gstin ?? '—',
            'credit_limit'         => $account->credit_limit ? (float)$account->credit_limit : 0.00,
            'street'               => $account->street ?? '—',
            'city'                 => $account->city ?? '—',
            'state'                => $account->state ?? '—',
            'country'              => $account->country ?? '—',
            'zip_code'             => $account->zip_code ?? '—',
            'status'               => ucfirst((string)$account->status),
            'owner_name'           => $account->owner?->name ?? '—',
            'deals_count'          => (int)$account->deals_count,
            'created_at'           => $account->created_at ? $account->created_at->format('Y-m-d H:i') : '—',
        ];

        $output = [];
        foreach ($this->getActiveColumns() as $col) {
            $output[] = $values[$col] ?? '';
        }

        return $output;
    }
}
