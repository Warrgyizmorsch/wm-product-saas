<?php

namespace App\Domains\CRM\Repositories;

use App\Domains\CRM\Models\Lead;
use App\Domains\CRM\Models\Customer;
use App\Domains\CRM\Models\Quotation;
use App\Domains\CRM\Models\LeadHistory;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

use App\Domains\CRM\Models\CrmAccount;
use App\Domains\CRM\Models\CrmContact;
use App\Domains\CRM\Models\CrmDeal;

class LeadRepository
{
    /**
     * Get paginated leads with all filters applied.
     */
    public function getPaginatedLeads(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Lead::query()->with(['quotations', 'owner']);

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

        // Duplicates Only Filter (Tenant-wise & Non-deleted Only)
        if (!empty($filters['duplicates_only']) && $filters['duplicates_only'] === '1') {
            $tenantId = tenant_id() ?? 1;
            $query->where('tenant_id', $tenantId)
                ->whereNull('deleted_at')
                ->where(function ($q) {
                    $q->whereNotNull('email')->where('email', '!=', '')
                      ->orWhereNotNull('company_email')->where('company_email', '!=', '')
                      ->orWhereNotNull('phone')->where('phone', '!=', '')
                      ->orWhereNotNull('company_phone')->where('company_phone', '!=', '');
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

            if (!empty($filters['lead_owner_id'])) {
                $query->where('lead_owner_id', $filters['lead_owner_id']);
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
        $lead->load(['followups.taggedUser', 'histories.user', 'leadDocuments']);

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
     * Qualify lead and automatically convert to CrmAccount, CrmContact, and CrmDeal.
     */
    public function qualifyLead(Lead $lead): bool
    {
        $oldStatus = $lead->status;
        $tenantId = $lead->tenant_id ?? (tenant_id() ?? 1);

        // 1. Find or Create CrmDeal
        $deal = null;
        if ($lead->crm_deal_id) {
            $deal = CrmDeal::find($lead->crm_deal_id);
        }

        if (!$deal) {
            $companyDisplayName = $lead->company_name ?: ($lead->contact_person ?: 'New Lead');
            $dealTitle = $lead->requirement 
                ? (strlen($lead->requirement) > 40 ? substr($lead->requirement, 0, 40) . '...' : $lead->requirement) 
                : ($companyDisplayName . ' - Opportunity');

            $deal = CrmDeal::create([
                'tenant_id'       => $tenantId,
                'company_id'      => $lead->company_id,
                'branch_id'       => $lead->branch_id,
                'crm_account_id'  => $lead->crm_account_id ?: null,
                'lead_id'         => $lead->id,
                'title'           => $dealTitle,
                'estimated_value' => $lead->expected_amount ?: 0.00,
                'stage'           => 'Qualification',
                'closing_date'    => $lead->expected_sale_date ?: now()->addDays(30),
                'lead_source'     => ($lead->source && !in_array($lead->source, ['Select an Option', 'Select an option', 'Select Option'], true)) ? $lead->source : null,
                'probability'     => 40,
                'owner_id'        => $lead->lead_owner_id ?: (auth()->id() ?: 1),
                'product_ids'     => $lead->product_ids,
                'product_items'   => $lead->product_items,
            ]);
        }

        // 2. Update Lead Record with Deal reference & Status to 'Qualified'
        $updated = $lead->update([
            'status'      => 'Qualified',
            'crm_deal_id' => $deal->id,
        ]);

        LeadHistory::logEvent(
            $lead,
            'status_updated',
            $oldStatus,
            'Qualified',
            "Lead qualified & converted into Deal #{$deal->deal_number}"
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
