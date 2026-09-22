<?php

namespace App\Exports;

use App\Domains\CRM\Models\CrmDeal;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class DealExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize
{
    public function __construct(
        private readonly int $tenantId,
        private readonly array $filters = []
    ) {}

    public function collection()
    {
        $query = CrmDeal::where('tenant_id', $this->tenantId)
            ->with(['account', 'contact', 'owner']);

        // 1. Stage / Status filter
        if (!empty($this->filters['stage'])) {
            $stage = $this->filters['stage'];
            if ($stage === 'won') {
                $query->whereIn('stage', ['Closed Won', 'Won']);
            } elseif ($stage === 'lost') {
                $query->whereIn('stage', ['Closed Lost', 'Lost']);
            } elseif ($stage === 'open') {
                $query->whereNotIn('stage', ['Closed Won', 'Won', 'Closed Lost', 'Lost']);
            } else {
                $query->where('stage', $stage);
            }
        } elseif (!empty($this->filters['status'])) {
            $status = $this->filters['status'];
            if ($status === 'won') {
                $query->whereIn('stage', ['Closed Won', 'Won']);
            } elseif ($status === 'lost') {
                $query->whereIn('stage', ['Closed Lost', 'Lost']);
            } elseif ($status === 'open') {
                $query->whereNotIn('stage', ['Closed Won', 'Won', 'Closed Lost', 'Lost']);
            } else {
                $query->where('stage', $status);
            }
        }

        // 2. Account filter
        if (!empty($this->filters['account_id']) || !empty($this->filters['crm_account_id'])) {
            $accId = $this->filters['account_id'] ?? $this->filters['crm_account_id'];
            $query->where('crm_account_id', $accId);
        }

        // 3. Owner filter
        if (!empty($this->filters['owner_id'])) {
            $query->where('owner_id', $this->filters['owner_id']);
        }

        // 4. Lead Source filter
        if (!empty($this->filters['lead_source'])) {
            $query->where('lead_source', $this->filters['lead_source']);
        }

        // 5. Date Range filters
        if (!empty($this->filters['created_from'])) {
            $query->whereDate('created_at', '>=', $this->filters['created_from']);
        }
        if (!empty($this->filters['created_to'])) {
            $query->whereDate('created_at', '<=', $this->filters['created_to']);
        }
        if (!empty($this->filters['closing_from'])) {
            $query->whereDate('closing_date', '>=', $this->filters['closing_from']);
        }
        if (!empty($this->filters['closing_to'])) {
            $query->whereDate('closing_date', '<=', $this->filters['closing_to']);
        }

        // 6. Search keyword
        if (!empty($this->filters['search'])) {
            $search = $this->filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('deal_number', 'like', "%{$search}%")
                  ->orWhere('title', 'like', "%{$search}%")
                  ->orWhere('stage', 'like', "%{$search}%")
                  ->orWhereHas('account', function ($aq) use ($search) {
                      $aq->where('name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('contact', function ($cq) use ($search) {
                      $cq->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        // 7. Sorting
        $sortBy = $this->filters['sort_by'] ?? 'created_at';
        $sortOrder = strtolower($this->filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['id', 'deal_number', 'title', 'estimated_value', 'stage', 'closing_date', 'created_at', 'health_score'];
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
            'deal_number'      => 'Deal Number',
            'title'            => 'Deal Title',
            'account_name'     => 'Company / Account',
            'contact_name'     => 'Contact Person',
            'contact_email'    => 'Contact Email',
            'contact_phone'    => 'Contact Phone',
            'estimated_value'  => 'Estimated Value (₹)',
            'stage'            => 'Stage / Status',
            'probability'      => 'Probability (%)',
            'closing_date'     => 'Expected Close Date',
            'lead_source'      => 'Lead Source',
            'owner_name'       => 'Deal Owner',
            'health_score'     => 'Health Score',
            'risk_level'       => 'Risk Level',
            'close_reason'     => 'Close Reason',
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

    public function map($deal): array
    {
        $values = [
            'deal_number'      => $deal->deal_number,
            'title'            => $deal->title,
            'account_name'     => $deal->account?->name ?? '—',
            'contact_name'     => $deal->contact?->name ?? '—',
            'contact_email'    => $deal->contact?->email ?? '—',
            'contact_phone'    => $deal->contact?->phone ?? '—',
            'estimated_value'  => (float)$deal->estimated_value,
            'stage'            => $deal->stage,
            'probability'      => $deal->probability !== null ? (int)$deal->probability . '%' : '—',
            'closing_date'     => $deal->closing_date ? $deal->closing_date->format('Y-m-d') : '—',
            'lead_source'      => $deal->lead_source ?? '—',
            'owner_name'       => $deal->owner?->name ?? '—',
            'health_score'     => $deal->health_score !== null ? $deal->health_score : '—',
            'risk_level'       => $deal->risk_level ? ucfirst($deal->risk_level) : '—',
            'close_reason'     => $deal->close_reason ?? '—',
            'notes'            => strip_tags((string)$deal->notes),
            'created_at'       => $deal->created_at ? $deal->created_at->format('Y-m-d H:i') : '—',
        ];

        $output = [];
        foreach ($this->getActiveColumns() as $col) {
            $output[] = $values[$col] ?? '';
        }

        return $output;
    }
}
