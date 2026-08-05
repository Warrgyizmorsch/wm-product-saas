<?php

namespace App\Domains\CRM\Repositories;

use App\Domains\CRM\Models\Quotation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class QuotationRepository
{
    public function find(int $id): ?Quotation
    {
        return Quotation::query()->with(['lead', 'salesPerson', 'items.product'])->find($id);
    }

    public function create(array $data): Quotation
    {
        return Quotation::query()->create($data);
    }

    public function update(Quotation $quotation, array $data): bool
    {
        return $quotation->update($data);
    }

    public function delete(Quotation $quotation): ?bool
    {
        return $quotation->delete();
    }

    public function count(): int
    {
        return Quotation::query()->count();
    }

    public function latest(): Collection
    {
        return Quotation::query()
            ->with(['lead', 'salesPerson'])
            ->where('is_current', true)
            ->where('status', '!=', 'Draft')
            ->latest()
            ->get();
    }

    /**
     * Get paginated quotations for main index listing.
     */
    public function getPaginatedQuotations(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $query = Quotation::query()
            ->with(['lead', 'salesPerson'])
            ->where('is_current', true);

        if (empty($filters['status']) && empty($filters['search'])) {
            $query->whereIn('status', ['Accepted', 'Converted', 'Won']);
        }

        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $cleanSearch = str_replace('QT-', '', $search);
            $query->where(function ($q) use ($search, $cleanSearch) {
                $q->where('quotation_number', 'like', "%{$cleanSearch}%")
                  ->orWhere('status', 'like', "%{$search}%")
                  ->orWhereHas('lead', function ($leadQ) use ($search) {
                      $leadQ->where('company_name', 'like', "%{$search}%")
                            ->orWhere('contact_person', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                  });
            });
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $sortBy = $filters['sort_by'] ?? 'quotation_date';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $allowedSorts = ['quotation_number', 'quotation_date', 'expiry_date', 'total_amount', 'status'];

        if ($sortBy === 'customer_name') {
            $query->join('leads', 'quotations.lead_id', '=', 'leads.id')
                  ->select('quotations.*')
                  ->orderBy(DB::raw('COALESCE(leads.company_name, leads.contact_person)'), $sortOrder);
        } elseif (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('quotation_date', 'desc');
        }

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * Get paginated quotations for pending approvals screen.
     */
    public function getPendingApprovals(array $filters = [], int $perPage = 10): LengthAwarePaginator
    {
        $query = Quotation::query()
            ->with(['lead', 'salesPerson'])
            ->where('is_current', true);

        if (empty($filters['status']) && empty($filters['search'])) {
            $query->where('status', 'Pending Approval');
        }

        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $cleanSearch = str_replace('QT-', '', $search);
            $query->where(function ($q) use ($search, $cleanSearch) {
                $q->where('quotation_number', 'like', "%{$cleanSearch}%")
                  ->orWhere('status', 'like', "%{$search}%")
                  ->orWhereHas('lead', function ($leadQ) use ($search) {
                      $leadQ->where('company_name', 'like', "%{$search}%")
                            ->orWhere('contact_person', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%");
                  });
            });
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $sortBy = $filters['sort_by'] ?? 'quotation_date';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $allowedSorts = ['quotation_number', 'quotation_date', 'expiry_date', 'total_amount', 'status'];

        if ($sortBy === 'customer_name') {
            $query->join('leads', 'quotations.lead_id', '=', 'leads.id')
                  ->select('quotations.*')
                  ->orderBy(DB::raw('COALESCE(leads.company_name, leads.contact_person)'), $sortOrder);
        } elseif (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('quotation_date', 'desc');
        }

        return $query->paginate($perPage)->withQueryString();
    }
}
