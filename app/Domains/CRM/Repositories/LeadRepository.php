<?php

namespace App\Domains\CRM\Repositories;

use App\Domains\CRM\Models\Lead;
use App\Domains\CRM\Models\Customer;
use App\Domains\CRM\Models\Quotation;
use App\Domains\CRM\Models\LeadHistory;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class LeadRepository
{
    /**
     * Get paginated leads with all filters applied.
     */
    public function getPaginatedLeads(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Lead::query()->with('quotations');

        // Search Keywords
        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('company_name', 'like', "%{$search}%")
                  ->orWhere('contact_person', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('source', 'like', "%{$search}%");
            });
        }

        // Duplicates Only Filter (Requires BOTH Email AND Phone match)
        if (!empty($filters['duplicates_only']) && $filters['duplicates_only'] === '1') {
            $query->whereNotNull('email')
                ->whereNotNull('phone')
                ->where('email', '!=', '')
                ->where('phone', '!=', '')
                ->whereIn(DB::raw("CONCAT(LOWER(email), '_', REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', ''))"), function ($sub) {
                    $sub->select(DB::raw("CONCAT(LOWER(email), '_', REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', ''))"))
                        ->from('leads')
                        ->whereNotNull('email')
                        ->whereNotNull('phone')
                        ->where('email', '!=', '')
                        ->where('phone', '!=', '')
                        ->whereNull('deleted_at')
                        ->groupBy(DB::raw("CONCAT(LOWER(email), '_', REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', ''))"))
                        ->havingRaw('COUNT(*) > 1');
                });
            $query->orderByRaw("LOWER(email) ASC, phone ASC, id ASC");
        } else {
            if (!empty($filters['priority'])) {
                $query->where('priority', $filters['priority']);
            }

            if (!empty($filters['segment'])) {
                $query->where('segment', $filters['segment']);
            }

            if (!empty($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            if (!empty($filters['quotation_status'])) {
                if ($filters['quotation_status'] === 'with_quotation') {
                    $query->has('quotations');
                } elseif ($filters['quotation_status'] === 'without_quotation') {
                    $query->doesntHave('quotations');
                }
            }

            $startDate = $filters['start_date'] ?? $filters['date_from'] ?? null;
            if (!empty($startDate)) {
                $query->where(function ($q) use ($startDate) {
                    $q->whereDate('call_date', '>=', $startDate)
                      ->orWhereDate('created_at', '>=', $startDate);
                });
            }

            $endDate = $filters['end_date'] ?? $filters['date_to'] ?? null;
            if (!empty($endDate)) {
                $query->where(function ($q) use ($endDate) {
                    $q->whereDate('call_date', '<=', $endDate)
                      ->orWhereDate('created_at', '<=', $endDate);
                });
            }

            $sortBy = $filters['sort_by'] ?? 'call_date';
            $sortOrder = $filters['sort_order'] ?? 'desc';
            
            if ($sortBy === 'duplicates') {
                $query->orderByRaw("LOWER(email) ASC, phone ASC, id ASC");
            } else {
                $allowedSorts = ['call_date', 'company_name', 'expected_amount', 'priority', 'status'];
                if (in_array($sortBy, $allowedSorts)) {
                    $query->orderBy($sortBy, $sortOrder);
                } else {
                    $query->orderBy('id', 'desc');
                }
            }
        }

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * Get full lead details including relations, linked customer, quotations, prev & next leads.
     */
    public function getLeadDetails(Lead $lead, ?int $activeQuotationId = null): array
    {
        $lead->load(['followups', 'histories.user', 'leadDocuments']);

        $customer = null;
        if ($lead->email) {
            $customer = Customer::where('email', $lead->email)->first();
        }
        if (!$customer && $lead->phone) {
            $customer = Customer::where('phone', $lead->phone)->first();
        }

        $quotations = Quotation::where('lead_id', $lead->id)->latest()->get();
        if ($activeQuotationId) {
            $activeQuotation = Quotation::find($activeQuotationId);
        } else {
            $activeQuotation = $quotations->where('is_current', true)->first() ?: $quotations->first();
        }

        $prevLead = Lead::where('id', '>', $lead->id)->orderBy('id', 'asc')->first();
        $nextLead = Lead::where('id', '<', $lead->id)->orderBy('id', 'desc')->first();

        return compact('customer', 'quotations', 'activeQuotation', 'prevLead', 'nextLead');
    }

    /**
     * Qualify lead and log event.
     */
    public function qualifyLead(Lead $lead): bool
    {
        $oldStatus = $lead->status;
        $updated = $lead->update(['status' => 'Qualified']);

        LeadHistory::logEvent(
            $lead,
            'status_updated',
            $oldStatus,
            'Qualified',
            'Lead verified and marked as Genuine Qualified Lead.'
        );

        return $updated;
    }

    /**
     * Update lead owner and log event.
     */
    public function updateOwner(Lead $lead, ?int $ownerId): bool
    {
        $oldOwnerId = $lead->lead_owner_id;
        $updated = $lead->update(['lead_owner_id' => $ownerId]);

        if ($oldOwnerId != $ownerId) {
            $oldOwnerName = $oldOwnerId ? (User::find($oldOwnerId)?->name ?: 'Unknown') : 'None';
            $newOwnerName = $ownerId ? (User::find($ownerId)?->name ?: 'Unknown') : 'None';
            LeadHistory::logEvent(
                $lead,
                'assigned',
                $oldOwnerName,
                $newOwnerName,
                "Lead owner updated from {$oldOwnerName} to {$newOwnerName}"
            );
        }

        return $updated;
    }
}
