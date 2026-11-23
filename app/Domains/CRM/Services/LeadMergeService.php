<?php

namespace App\Domains\CRM\Services;

use App\Domains\CRM\Models\Lead;
use App\Domains\CRM\Models\LeadFollowup;
use App\Domains\CRM\Models\LeadHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * FRS 2.5 step 2: duplicate leads can be merged and every interaction is kept.
 * The surviving lead receives all follow-ups, history, documents and quotations of the merged-in lead;
 * the merged-in record is retired (soft-deleted, `merged_into_lead_id` set), never hard-deleted.
 */
class LeadMergeService
{
    /** Scalar fields filled on the survivor only when it has no value of its own. */
    private const FILLABLE_FROM_SOURCE = [
        'company_email', 'company_phone', 'gstin', 'designation', 'email', 'phone', 'requirement',
        'industry_type', 'project_type', 'approx_quantity', 'decision_timeline', 'competitor_name',
        'country', 'state', 'city', 'address', 'segment', 'source',
    ];

    public function merge(Lead $survivor, Lead $source, ?User $actor = null): Lead
    {
        if ($survivor->id === $source->id) {
            throw new InvalidArgumentException('A lead cannot be merged into itself.');
        }
        if ((int) $survivor->tenant_id !== (int) $source->tenant_id) {
            throw new InvalidArgumentException('Leads belong to different tenants.');
        }
        if (!empty($source->merged_into_lead_id) || !empty($survivor->merged_into_lead_id)) {
            throw new InvalidArgumentException('One of the leads has already been merged.');
        }
        if (strcasecmp((string) $source->status, 'Won') === 0 || $source->is_customer || !empty($source->crm_deal_id)) {
            throw new InvalidArgumentException('A lead that is Won, converted or linked to a deal cannot be merged away. Merge it as the surviving lead instead.');
        }

        return DB::transaction(function () use ($survivor, $source, $actor) {
            $moved = [
                'followups' => LeadFollowup::where('lead_id', $source->id)->update(['lead_id' => $survivor->id]),
                'history' => LeadHistory::where('lead_id', $source->id)->update(['lead_id' => $survivor->id]),
                'documents' => DB::table('lead_documents')->where('lead_id', $source->id)->update(['lead_id' => $survivor->id]),
                'quotations' => DB::table('quotations')->where('lead_id', $source->id)->update(['lead_id' => $survivor->id]),
            ];

            $fill = [];
            foreach (self::FILLABLE_FROM_SOURCE as $field) {
                if (($survivor->{$field} === null || $survivor->{$field} === '') && !empty($source->{$field})) {
                    $fill[$field] = $source->{$field};
                }
            }
            $contacts = array_values(array_merge((array) $survivor->additional_contacts, (array) $source->additional_contacts));
            if (!empty($contacts)) {
                $fill['additional_contacts'] = $contacts;
            }
            if ((float) $source->expected_amount > (float) $survivor->expected_amount) {
                $fill['expected_amount'] = $source->expected_amount;
            }
            if (!empty($fill)) {
                $survivor->update($fill);
            }

            $survivor->update(['last_activity_at' => now()]);
            $source->update(['merged_into_lead_id' => $survivor->id, 'has_active_next_action' => false]);

            $label = ($source->lead_number ?: ('#' . $source->id)) . ' (' . ($source->company_name ?: $source->contact_person) . ')';
            LeadHistory::logEvent(
                $survivor,
                'merged',
                null,
                $label,
                "Lead {$label} merged into this lead. Moved: {$moved['followups']} follow-up(s), {$moved['history']} history entries, {$moved['documents']} document(s), {$moved['quotations']} quotation(s)."
            );
            $source->delete(); // soft delete: lineage kept through merged_into_lead_id

            app(\App\Domains\CRM\Repositories\LeadFollowupRepository::class)->syncLeadNextFollowupDate($survivor->fresh());

            return $survivor->fresh();
        });
    }
}
