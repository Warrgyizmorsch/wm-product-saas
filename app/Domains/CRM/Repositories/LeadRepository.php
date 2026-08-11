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
                      ->orWhereNotNull('phone')->where('phone', '!=', '');
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
        $isB2B = $lead->lead_type === 'b2b' || !empty($lead->company_name) || !empty($lead->gstin) || !empty($lead->company_email) || !empty($lead->company_phone);

        // 1. Find or Create CrmAccount
        $account = null;
        if ($lead->crm_account_id) {
            $account = CrmAccount::find($lead->crm_account_id);
        }

        if ($isB2B) {
            // B2B Account Matching: Match strictly by Company Fields (GSTIN, Company Email, Company Phone, Company Name)
            $companyName = $lead->company_name ?: ($lead->contact_person ?: 'B2B Client Account');
            $compEmail = $lead->company_email ?: null;
            $compPhone = $lead->company_phone ?: null;
            $gstin = $lead->gstin ?: null;

            if (!$account && $gstin) {
                $account = CrmAccount::where('tenant_id', $tenantId)->where('gstin', $gstin)->first();
            }
            if (!$account && $compEmail) {
                $account = CrmAccount::where('tenant_id', $tenantId)->where('email', $compEmail)->first();
            }
            if (!$account && $compPhone) {
                $account = CrmAccount::where('tenant_id', $tenantId)->where('phone', $compPhone)->first();
            }
            if (!$account && $companyName) {
                $account = CrmAccount::where('tenant_id', $tenantId)->where('name', 'like', "%{$companyName}%")->first();
            }

            if (!$account) {
                $account = CrmAccount::create([
                    'tenant_id'     => $tenantId,
                    'name'          => $companyName,
                    'gstin'         => $gstin,
                    'email'         => $compEmail ?: $lead->email,
                    'phone'         => $compPhone ?: $lead->phone,
                    'industry_type' => $lead->industry_type,
                    'city'          => $lead->city,
                    'state'         => $lead->state,
                    'country'       => $lead->country,
                    'street'        => $lead->address,
                    'status'        => 'active',
                    'owner_id'      => $lead->lead_owner_id ?: (auth()->id() ?: 1),
                ]);
            }
        } else {
            // B2C Account Matching: Match by Contact Person details
            $personName = $lead->contact_person ?: ($lead->company_name ?: 'Individual Customer');
            $personEmail = $lead->email ?: null;
            $personPhone = $lead->phone ?: null;

            if (!$account && $personEmail) {
                $account = CrmAccount::where('tenant_id', $tenantId)->where('email', $personEmail)->first();
            }
            if (!$account && $personPhone) {
                $account = CrmAccount::where('tenant_id', $tenantId)->where('phone', $personPhone)->first();
            }
            if (!$account && $personName) {
                $account = CrmAccount::where('tenant_id', $tenantId)->where('name', 'like', "%{$personName}%")->first();
            }

            if (!$account) {
                $account = CrmAccount::create([
                    'tenant_id'     => $tenantId,
                    'name'          => $personName,
                    'email'         => $personEmail,
                    'phone'         => $personPhone,
                    'industry_type' => $lead->industry_type,
                    'city'          => $lead->city,
                    'state'         => $lead->state,
                    'country'       => $lead->country,
                    'street'        => $lead->address,
                    'status'        => 'active',
                    'owner_id'      => $lead->lead_owner_id ?: (auth()->id() ?: 1),
                ]);
            }
        }

        // 2. Find or Create CrmContact
        $contact = null;
        $contactName = $lead->contact_person ?: ($lead->company_name ?: 'Primary Contact');
        $contactEmail = $lead->email;
        $contactPhone = $lead->phone;

        if ($contactName) {
            $contact = CrmContact::where('crm_account_id', $account->id)->where('name', $contactName)->first();
            if (!$contact && $contactEmail) {
                $contact = CrmContact::where('crm_account_id', $account->id)->where('email', $contactEmail)->first();
            }
            if (!$contact) {
                $contact = CrmContact::create([
                    'tenant_id'      => $tenantId,
                    'crm_account_id' => $account->id,
                    'name'           => $contactName,
                    'role'           => 'Primary Contact',
                    'email'          => $contactEmail,
                    'phone'          => $contactPhone,
                    'mobile'         => $contactPhone,
                    'is_primary'     => $account->contacts()->count() === 0,
                    'status'         => 'active',
                ]);
            }
        }

        // 3. Find or Create CrmDeal
        $deal = null;
        if ($lead->crm_deal_id) {
            $deal = CrmDeal::find($lead->crm_deal_id);
        }
        if (!$deal) {
            $companyDisplayName = $isB2B ? ($lead->company_name ?: $account->name) : $account->name;
            $dealTitle = $lead->requirement 
                ? (strlen($lead->requirement) > 40 ? substr($lead->requirement, 0, 40) . '...' : $lead->requirement) 
                : ($companyDisplayName . ' - Opportunity');

            $deal = CrmDeal::create([
                'tenant_id'       => $tenantId,
                'crm_account_id'  => $account->id,
                'crm_contact_id'  => $contact ? $contact->id : null,
                'title'           => $dealTitle,
                'estimated_value' => $lead->expected_amount ?: 0.00,
                'stage'           => 'Qualification',
                'closing_date'    => $lead->expected_sale_date ?: now()->addDays(30),
                'lead_source'     => ($lead->source && !in_array($lead->source, ['Select an Option', 'Select an option', 'Select Option'], true)) ? $lead->source : null,
                'probability'     => 40,
                'owner_id'        => $lead->lead_owner_id ?: (auth()->id() ?: 1),
            ]);
        } else {
            $deal->update([
                'crm_account_id' => $account->id,
                'crm_contact_id' => $contact ? $contact->id : null,
            ]);
        }

        // 4. Update Lead Record with Account, Contact, Deal references & Status
        $updated = $lead->update([
            'status'         => 'Won',
            'is_customer'    => true,
            'crm_account_id' => $account->id,
            'crm_contact_id' => $contact ? $contact->id : null,
            'crm_deal_id'    => $deal->id,
            'converted_at'   => now(),
        ]);

        LeadHistory::logEvent(
            $lead,
            'status_updated',
            $oldStatus,
            'Won',
            "Lead verified & converted into Account #{$account->account_number} and Deal #{$deal->deal_number}"
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
