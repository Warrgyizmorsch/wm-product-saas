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
    public function syncLeadNextFollowupDate(Lead $lead): void
    {
        $lead->unsetRelation('followups');

        $nextPending = $lead->followups()
            ->where('status', 'Pending')
            ->orderBy('followup_date', 'asc')
            ->first();

        $lead->next_followup_date = $nextPending ? $nextPending->followup_date : null;
        $lead->save();
    }
}
