<?php

namespace App\Domains\CRM\Services;

use App\Domains\CRM\Models\CrmAccount;
use App\Domains\CRM\Models\CrmDeal;
use App\Domains\CRM\Models\Lead;
use App\Domains\CRM\Models\Quotation;
use App\Domains\Party\Contracts\PartyHistoryProvider;
use Illuminate\Support\Collection;

/** Leads, deals and quotations reached through the customer's CRM account(s). */
class CustomerHistoryProvider implements PartyHistoryProvider
{
    public function partyType(): string
    {
        return 'customer';
    }

    public function entries(int $partyId): Collection
    {
        $accountIds = CrmAccount::where('customer_id', $partyId)->pluck('id');
        if ($accountIds->isEmpty()) {
            return collect();
        }

        $rows = collect();

        foreach (Lead::whereIn('crm_account_id', $accountIds)->get() as $l) {
            $rows->push([
                'type' => 'lead', 'type_label' => 'Lead', 'number' => $l->lead_number,
                'title' => $l->requirement ?: $l->company_name, 'amount' => $l->expected_amount,
                'status' => $l->status, 'date' => $l->created_at,
                'url' => route('crm.leads.show', $l->id), 'permission' => 'crm.leads.view',
            ]);
        }

        foreach (CrmDeal::whereIn('crm_account_id', $accountIds)->get() as $d) {
            $rows->push([
                'type' => 'deal', 'type_label' => 'Deal', 'number' => null,
                'title' => $d->title, 'amount' => $d->actual_value ?: $d->estimated_value,
                'status' => $d->stage, 'date' => $d->created_at,
                'url' => route('crm.deals.show', $d->id), 'permission' => 'crm.deals.view',
            ]);
        }

        foreach (Quotation::whereIn('crm_account_id', $accountIds)->get() as $q) {
            $rows->push([
                'type' => 'quotation', 'type_label' => 'Quotation', 'number' => $q->quotation_number,
                'title' => null, 'amount' => $q->total_amount,
                'status' => $q->status, 'date' => $q->quotation_date ?? $q->created_at,
                'url' => route('crm.quotations.show', $q->id), 'permission' => 'crm.quotations.view',
            ]);
        }

        return $rows;
    }
}
