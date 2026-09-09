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

        $taggedUserIds = !empty($validated['tagged_user_ids']) ? array_values(array_filter($validated['tagged_user_ids'])) : null;
        $primaryTaggedId = !empty($taggedUserIds) ? $taggedUserIds[0] : ($validated['tagged_user_id'] ?? null);

        $durationMinutes = !empty($validated['duration_minutes']) ? (int)$validated['duration_minutes'] : 30;
        $guestEmailsInput = !empty($validated['guest_emails']) ? trim($validated['guest_emails']) : null;
        $titleInput = !empty($validated['title']) ? trim($validated['title']) : (($validated['type'] ?? 'Scheduled Call') . " with " . ($lead->company_name ?: $lead->contact_person));

        $followup = $this->followupRepo->create([
            'lead_id' => $lead->id,
            'followup_date' => $followupDateTime,
            'type' => $validated['type'],
            'title' => $titleInput,
            'duration_minutes' => $durationMinutes,
            'guest_emails' => $guestEmailsInput,
            'status' => $validated['status'],
            'notes' => $validated['notes'] ?? null,
            'tagged_user_id' => $primaryTaggedId,
            'tagged_user_ids' => $taggedUserIds,
        ]);

        $eventType = $followup->status === 'Pending' ? 'activity_scheduled' : 'activity_completed';
        $description = $followup->status === 'Pending'
            ? "Scheduled a {$followup->type} activity on " . $followup->followup_date->format('d/m/Y h:i A')
            : "Logged a {$followup->type} interaction: " . ($followup->notes ?: 'No details');

        // Auto-Sync with Google Calendar if enabled or if pending activity
        $syncGoogle = isset($validated['sync_google_calendar']) ? (bool)$validated['sync_google_calendar'] : true;
        if ($followup->status === 'Pending' && $syncGoogle) {
            try {
                $createMeet = !empty($validated['create_meet_link']) || in_array(strtolower($validated['type']), ['meeting', 'demo']);
                $attendees = [];
                if ($lead->email) $attendees[] = $lead->email;
                if ($lead->company_email) $attendees[] = $lead->company_email;
                if (auth()->check() && auth()->user()?->email) {
                    $attendees[] = auth()->user()->email;
                }
                if (!empty($taggedUserIds)) {
                    $taggedEmails = \App\Models\User::whereIn('id', $taggedUserIds)->pluck('email')->filter()->toArray();
                    $attendees = array_merge($attendees, $taggedEmails);
                }
                if (!empty($guestEmailsInput)) {
                    $extraEmails = array_map('trim', explode(',', $guestEmailsInput));
                    $attendees = array_merge($attendees, $extraEmails);
                }
                $attendees = array_unique(array_values(array_filter($attendees)));

                $calService = app(GoogleCalendarIntegrationService::class);
                $res = $calService->createEvent([
                    'summary' => $titleInput,
                    'description' => $validated['notes'] ?? '',
                    'start_time' => $followupDateTime->toIso8601String(),
                    'end_time' => (clone $followupDateTime)->addMinutes($durationMinutes)->toIso8601String(),
                    'attendees' => $attendees,
                    'create_meet_link' => $createMeet,
                    'lead_id' => $lead->id,
                ]);

                if (\Schema::hasColumn('lead_followups', 'google_event_id')) {
                    $followup->google_event_id = $res['google_event_id'] ?? null;
                }
                if (\Schema::hasColumn('lead_followups', 'google_meet_link')) {
                    $followup->google_meet_link = $res['meet_link'] ?? null;
                }
                if (\Schema::hasColumn('lead_followups', 'is_google_meet')) {
                    $followup->is_google_meet = $createMeet;
                }
                if (!empty($res['meet_link'])) {
                    $followup->notes = ($followup->notes ? $followup->notes . "\n" : '') . "Google Meet: " . $res['meet_link'];
                }
                $followup->save();
            } catch (\Throwable $ex) {
                \Illuminate\Support\Facades\Log::warning('Google Calendar auto-sync notice: ' . $ex->getMessage());
            }
        }

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

        $taggedUserIds = isset($validated['tagged_user_ids']) ? array_values(array_filter($validated['tagged_user_ids'])) : null;
        $primaryTaggedId = !empty($taggedUserIds) ? $taggedUserIds[0] : (array_key_exists('tagged_user_id', $validated) ? ($validated['tagged_user_id'] ?: null) : $followup->tagged_user_id);

        if ($isReschedule && !empty($validated['followup_date'])) {
            try {
                $newFollowupDateTime = Carbon::parse($validated['followup_date']);
                $oldStatus = $followup->status;

                $this->followupRepo->update($followup, ['status' => 'Rescheduled']);

                $this->followupRepo->create([
                    'lead_id' => $followup->lead_id,
                    'crm_deal_id' => $followup->crm_deal_id,
                    'type' => $validated['type'] ?? $followup->type,
                    'followup_date' => $newFollowupDateTime,
                    'status' => 'Pending',
                    'notes' => $validated['notes'] ?? $followup->notes,
                    'tagged_user_id' => $primaryTaggedId,
                    'tagged_user_ids' => $taggedUserIds ?? $followup->tagged_user_ids,
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
        if (array_key_exists('tagged_user_ids', $validated)) {
            $updateData['tagged_user_ids'] = $taggedUserIds;
            $updateData['tagged_user_id'] = $primaryTaggedId;
        } elseif (array_key_exists('tagged_user_id', $validated)) {
            $updateData['tagged_user_id'] = $validated['tagged_user_id'] ?: null;
        }
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
