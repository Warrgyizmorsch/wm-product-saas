<?php

namespace App\Domains\CRM\Repositories;

use App\Domains\CRM\Models\Lead;
use App\Domains\CRM\Models\LeadFollowup;
use Illuminate\Database\Eloquent\Collection;

class LeadFollowupRepository
{
    public function find(int $id): ?LeadFollowup
    {
        return LeadFollowup::find($id);
    }

    public function create(array $data): LeadFollowup
    {
        return LeadFollowup::create($data);
    }

    public function update(LeadFollowup $followup, array $data): bool
    {
        return $followup->update($data);
    }

    public function delete(LeadFollowup $followup): ?bool
    {
        return $followup->delete();
    }

    /**
     * Recalculate and update the parent lead's next followup date.
     */
    public function syncLeadNextFollowupDate(?Lead $lead = null): void
    {
        if (!$lead) {
            return;
        }

        $lead->unsetRelation('followups');

        $nextPending = $lead->followups()
            ->where('status', 'Pending')
            ->orderBy('followup_date', 'asc')
            ->first();

        $lead->next_followup_date = $nextPending ? $nextPending->followup_date : null;
        $lead->has_active_next_action = ($nextPending !== null);
        if ($nextPending) {
            $lead->stale_alert_sent_at = null;
        }

        // FRS 2.5 / C-01: a real (non-pending, connected) customer interaction stops the response clock,
        // whichever screen completed it (log note, update, meeting status).
        if (empty($lead->first_responded_at)) {
            $done = $lead->followups()
                ->whereIn('status', ['Completed', 'Connected'])
                ->whereIn('type', ['Call', 'Email', 'Meeting', 'Demo', 'Visit'])
                ->orderBy('updated_at', 'asc')
                ->first();
            if ($done) {
                $lead->first_responded_at = $done->updated_at ?: now();
            }
        }
        $lead->save();

        // FRS 2.5: an open, owned lead (not on hold) never ends up without a dated next action.
        if (!$nextPending) {
            app(\App\Domains\CRM\Services\LeadNextActionService::class)->ensureNextAction($lead->fresh());
        }
    }
}
