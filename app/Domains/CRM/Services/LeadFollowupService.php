<?php

namespace App\Domains\CRM\Services;

use App\Domains\CRM\Models\Lead;
use App\Domains\CRM\Models\LeadFollowup;
use App\Domains\CRM\Models\LeadHistory;
use App\Domains\CRM\Repositories\LeadFollowupRepository;
use Illuminate\Support\Carbon;

class LeadFollowupService
{
    public function __construct(
        private readonly LeadFollowupRepository $followupRepo
    ) {}

    /**
     * Store new followup, log history, and sync lead.
     */
    public function storeFollowup(Lead $lead, array $validated): LeadFollowup
    {
        try {
            $followupDateTime = Carbon::parse($validated['followup_date']);
        } catch (\Exception $e) {
            $followupDateTime = Carbon::now();
        }

        $followup = $this->followupRepo->create([
            'lead_id' => $lead->id,
            'followup_date' => $followupDateTime,
            'type' => $validated['type'],
            'status' => $validated['status'],
            'notes' => $validated['notes'] ?? null,
        ]);

        $eventType = $followup->status === 'Pending' ? 'activity_scheduled' : 'activity_completed';
        $description = $followup->status === 'Pending'
            ? "Scheduled a {$followup->type} activity on " . $followup->followup_date->format('d/m/Y h:i A')
            : "Logged a {$followup->type} interaction: " . ($followup->notes ?: 'No details');

        LeadHistory::logEvent(
            $lead,
            $eventType,
            null,
            $followup->type,
            $description
        );

        $this->followupRepo->syncLeadNextFollowupDate($lead);

        return $followup;
    }

    /**
     * Update or Reschedule followup, log history, and sync lead.
     */
    public function updateOrReschedule(LeadFollowup $followup, array $validated, bool $isRescheduleRequested): string
    {
        $isReschedule = !empty($validated['followup_date']) && ($isRescheduleRequested || $followup->status === 'Pending');

        if ($isReschedule && !empty($validated['followup_date'])) {
            try {
                $newFollowupDateTime = Carbon::parse($validated['followup_date']);
                $oldStatus = $followup->status;

                $this->followupRepo->update($followup, ['status' => 'Rescheduled']);

                $this->followupRepo->create([
                    'lead_id' => $followup->lead_id,
                    'type' => $validated['type'] ?? $followup->type,
                    'followup_date' => $newFollowupDateTime,
                    'status' => 'Pending',
                    'notes' => $validated['notes'] ?? $followup->notes,
                    'rescheduled_from_id' => $followup->id,
                    'original_followup_date' => $followup->original_followup_date ?: $followup->followup_date,
                ]);

                LeadHistory::logEvent(
                    $followup->lead,
                    'activity_rescheduled',
                    $oldStatus,
                    'Rescheduled',
                    "Rescheduled {$followup->type} activity from " . $followup->followup_date->format('d/m/Y h:i A') . " to " . $newFollowupDateTime->format('d/m/Y h:i A')
                );

                $this->followupRepo->syncLeadNextFollowupDate($followup->lead);
                return 'Activity successfully rescheduled!';
            } catch (\Exception $e) {
                // Fall through to standard update on parse exception
            }
        }

        $updateData = [];
        if (isset($validated['status'])) $updateData['status'] = $validated['status'];
        if (isset($validated['notes'])) $updateData['notes'] = $validated['notes'];
        if (isset($validated['type'])) $updateData['type'] = $validated['type'];
        if (isset($validated['followup_date'])) {
            try { $updateData['followup_date'] = Carbon::parse($validated['followup_date']); } catch (\Exception $e) {}
        }

        $oldStatus = $followup->status;
        $this->followupRepo->update($followup, $updateData);

        if ($oldStatus !== $followup->status && $followup->status === 'Completed') {
            LeadHistory::logEvent(
                $followup->lead,
                'activity_completed',
                $oldStatus,
                'Completed',
                "Marked scheduled {$followup->type} activity (scheduled for " . $followup->followup_date->format('d/m/Y h:i A') . ") as Completed"
            );
        }

        $this->followupRepo->syncLeadNextFollowupDate($followup->lead);
        return 'Follow-up successfully updated!';
    }

    /**
     * Delete followup, log history, and sync lead.
     */
    public function deleteFollowup(LeadFollowup $followup): void
    {
        $lead = $followup->lead;

        LeadHistory::logEvent(
            $lead,
            'activity_deleted',
            $followup->type,
            null,
            "Deleted {$followup->type} activity (scheduled/logged for " . $followup->followup_date->format('d/m/Y h:i A') . ")"
        );

        $this->followupRepo->delete($followup);
        $this->followupRepo->syncLeadNextFollowupDate($lead);
    }
}
