<?php

namespace App\Exports;

use App\Domains\CRM\Models\Quotation;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Illuminate\Support\Facades\DB;

class QuotationExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    public function __construct(
        private readonly int $tenantId,
        private readonly array $filters = []
    ) {}

    public function collection()
    {
        $query = Quotation::where('tenant_id', $this->tenantId)
            ->with(['lead', 'salesPerson', 'crmAccount', 'crmDeal.account', 'crmDeal.contact']);

        // 1. Revision / current filter (default to current if not specified)
        if (!isset($this->filters['all_revisions']) || !$this->filters['all_revisions']) {
            $query->where('is_current', true);
        }

        // 2. Status filter
        if (!empty($this->filters['status']) && $this->filters['status'] !== 'all') {
            $query->where('status', $this->filters['status']);
        }

        // 3. Date Range
        if (!empty($this->filters['date_from'])) {
            $query->whereDate('quotation_date', '>=', $this->filters['date_from']);
        }
        if (!empty($this->filters['date_to'])) {
            $query->whereDate('quotation_date', '<=', $this->filters['date_to']);
        }

        // 4. Search keyword
        if (!empty($this->filters['search'])) {
            $search = trim($this->filters['search']);
            $cleanSearch = str_replace('QT-', '', $search);
            $query->where(function ($q) use ($search, $cleanSearch) {
                $q->where('quotation_number', 'like', "%{$cleanSearch}%")
                  ->orWhere('quotation_number', 'like', "%{$search}%")
                  ->orWhere('status', 'like', "%{$search}%")
                  ->orWhereHas('lead', function ($leadQ) use ($search) {
                      $leadQ->where('company_name', 'like', "%{$search}%")
                            ->orWhere('contact_person', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                  })
                  ->orWhereHas('crmAccount', function ($accQ) use ($search) {
                      $accQ->where('name', 'like', "%{$search}%");
                  });
            });
        }

        // 5. Sorting
        $sortBy = $this->filters['sort_by'] ?? 'quotation_date';
        $sortOrder = strtolower($this->filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['quotation_number', 'quotation_date', 'expiry_date', 'total_amount', 'status', 'created_at'];

        if ($sortBy === 'customer_name') {
            $query->leftJoin('leads', 'quotations.lead_id', '=', 'leads.id')
                  ->select('quotations.*')
                  ->orderBy(DB::raw('COALESCE(leads.company_name, leads.contact_person)'), $sortOrder);
        } elseif (in_array($sortBy, $allowedSorts, true)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('quotation_date', 'desc');
        }

        return $query->get();
    }

    public static function availableColumns(): array
    {
        return [
            'quotation_number' => 'Quotation Number',
            'customer_name'    => 'Customer / Account Name',
            'contact_person'   => 'Contact Person',
            'email'            => 'Email',
            'phone'            => 'Phone Number',
            'quotation_date'   => 'Quotation Date',
            'expiry_date'      => 'Expiry Date',
            'sales_person'     => 'Sales Person / Rep',
            'subtotal'         => 'Subtotal (₹)',
            'discount'         => 'Discount (₹)',
            'tax'              => 'Tax (₹)',
            'total_amount'     => 'Grand Total (₹)',
            'status'           => 'Quotation Status',
            'revision_number'  => 'Revision Number',
            'notes'            => 'Notes',
            'rejection_reason' => 'Rejection Reason',
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

    public function map($quotation): array
    {
        $customer = $quotation->customer;
        $lead = $quotation->lead;
        $acc = $quotation->crmAccount;

        $customerName = $customer?->name ?? $acc?->name ?? $lead?->company_name ?? $lead?->contact_person ?? '—';
        $contactPerson = $customer?->contact_person ?? $lead?->contact_person ?? '—';
        $email = $customer?->email ?? $acc?->email ?? $lead?->email ?? '—';
        $phone = $customer?->phone ?? $acc?->phone ?? $lead?->phone ?? '—';

        $values = [
            'quotation_number' => $quotation->quotation_number,
            'customer_name'    => $customerName,
            'contact_person'   => $contactPerson,
            'email'            => $email,
            'phone'            => $phone,
            'quotation_date'   => $quotation->quotation_date ? date('Y-m-d', strtotime($quotation->quotation_date)) : '—',
            'expiry_date'      => $quotation->expiry_date ? date('Y-m-d', strtotime($quotation->expiry_date)) : '—',
            'sales_person'     => $quotation->salesPerson?->name ?? '—',
            'subtotal'         => (float)($quotation->subtotal ?? 0),
            'discount'         => (float)($quotation->discount ?? 0),
            'tax'              => (float)($quotation->tax ?? 0),
            'total_amount'     => (float)($quotation->total_amount ?? 0),
            'status'           => ucfirst((string)$quotation->status),
            'revision_number'  => $quotation->revision_number ? 'R' . $quotation->revision_number : 'Original',
            'notes'            => $quotation->notes ?? '—',
            'rejection_reason' => $quotation->rejection_reason ?? '—',
            'created_at'       => $quotation->created_at ? $quotation->created_at->format('Y-m-d H:i') : '—',
        ];

        $output = [];
        foreach ($this->getActiveColumns() as $col) {
            $output[] = $values[$col] ?? '';
        }

        return $output;
    }
}
