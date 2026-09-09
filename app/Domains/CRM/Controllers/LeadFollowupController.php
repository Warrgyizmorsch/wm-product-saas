<?php

namespace App\Domains\CRM\Controllers;

use App\Domains\CRM\Models\Lead;
use App\Domains\CRM\Models\CrmDeal;
use App\Domains\CRM\Models\LeadFollowup;
use App\Domains\CRM\Services\LeadFollowupService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class LeadFollowupController extends Controller
{
    public function __construct(
        private readonly LeadFollowupService $followupService
    ) {}

    public function store(Request $request, Lead $lead)
    {
        $this->authorize('update', $lead);

        $validated = $request->validate([
            'followup_date'      => 'nullable|string',
            'type'               => 'nullable|string',
            'status'             => 'nullable|string',
            'notes'              => 'nullable|string',
            'lead_status'        => 'nullable|string',
            'priority'           => 'nullable|string',
            'segment'            => 'nullable|string',
            'sub_status'         => 'nullable|string',
            'tagged_user_id'     => 'nullable|exists:users,id',
            'tagged_user_ids'    => 'nullable|array',
            'tagged_user_ids.*'  => 'nullable|exists:users,id',
            'recording'          => 'nullable|file|max:20480',
            'next_activity_type' => 'nullable|string',
            'next_followup_date' => 'nullable|string',
        ]);

        $actionMode = $request->input('action_mode', 'log_note');

        if (!empty($validated['lead_status'])) {
            $lead->status = $validated['lead_status'];
        }
        if (!empty($validated['priority'])) {
            $lead->priority = $validated['priority'];
        }
        if (!empty($validated['segment'])) {
            $lead->segment = $validated['segment'];
        }
        if ($lead->isDirty()) {
            $lead->save();
        }

        if ($request->hasFile('recording')) {
            app(\App\Domains\CRM\Services\LeadService::class)->uploadDocuments($lead, [$request->file('recording')]);
        }

        if ($actionMode === 'schedule') {
            $scheduleType = $request->input('schedule_type') ?: ($validated['type'] ?? 'Call');
            $dueDate = $request->input('followup_date') ?: date('Y-m-d H:i');
            $notes = $request->input('schedule_notes') ?: $validated['notes'];

            $this->followupService->storeFollowup($lead, [
                'type'                 => $scheduleType,
                'title'                => $request->input('title'),
                'duration_minutes'     => $request->input('duration_minutes'),
                'guest_emails'         => $request->input('guest_emails'),
                'status'               => 'Pending',
                'followup_date'        => $dueDate,
                'notes'                => $notes,
                'tagged_user_ids'      => $validated['tagged_user_ids'] ?? null,
                'sync_google_calendar' => $request->boolean('sync_google_calendar'),
                'create_meet_link'     => $request->boolean('create_meet_link'),
            ]);

            try {
                $lead->next_followup_date = \Illuminate\Support\Carbon::parse($dueDate);
            } catch (\Exception $e) {}
        } else {
            $pastType = $validated['type'] ?? 'Call';
            $pastStatus = $validated['status'] ?? 'Connected';
            $pastNotes = trim($validated['notes'] ?? '');

            // 1. Create Past Interaction Log (Status: Connected / Not Connected / Completed) - No tagged users on past log
            $this->followupService->storeFollowup($lead, [
                'type'            => $pastType,
                'status'          => $pastStatus,
                'followup_date'   => date('Y-m-d H:i'),
                'notes'           => $pastNotes,
                'tagged_user_ids' => null,
            ]);

            // 2. Create Next Scheduled Activity Log (Status: Pending) - Includes past notes + pipe separator + context suffix
            $nextDate = $request->input('next_followup_date');
            if (!empty($nextDate)) {
                $nextType = $request->input('next_activity_type') ?: 'Call';
                $contextSuffix = "Scheduled " . $nextType . " after " . strtolower($pastType) . " interaction";
                $nextNotes = !empty($pastNotes) ? ($pastNotes . " | " . $contextSuffix) : $contextSuffix;

                $this->followupService->storeFollowup($lead, [
                    'type'                 => $nextType,
                    'title'                => $request->input('next_title') ?: ($request->input('title') ?: ("Next " . $nextType . " with " . ($lead->company_name ?: $lead->contact_person))),
                    'duration_minutes'     => $request->input('next_duration_minutes') ?: ($request->input('duration_minutes') ?: 30),
                    'guest_emails'         => $request->input('next_guest_emails') ?: $request->input('guest_emails'),
                    'status'               => 'Pending',
                    'followup_date'        => $nextDate,
                    'notes'                => $nextNotes,
                    'tagged_user_ids'      => $validated['tagged_user_ids'] ?? null,
                    'sync_google_calendar' => $request->has('next_sync_google_calendar') ? $request->boolean('next_sync_google_calendar') : ($request->has('sync_google_calendar') ? $request->boolean('sync_google_calendar') : false),
                    'create_meet_link'     => $request->has('next_create_meet_link') ? $request->boolean('next_create_meet_link') : $request->boolean('create_meet_link'),
                ]);

                try {
                    $lead->next_followup_date = \Illuminate\Support\Carbon::parse($nextDate);
                } catch (\Exception $e) {}
            }
        }

        if ($lead->isDirty()) {
            $lead->save();
        }

        return redirect()->back()->with('success', 'Follow-up / Activity updated successfully!');
    }

    public function update(Request $request, LeadFollowup $followup)
    {
        if ($followup->lead) {
            $this->authorize('update', $followup->lead);
        }

        $validated = $request->validate([
            'status' => 'nullable|string|in:Pending,Completed,Not Connected,Cancelled,Rescheduled',
            'notes' => 'nullable|string',
            'type' => 'nullable|string|in:Call,Email,Meeting,Demo,Task',
            'followup_date' => 'nullable|string',
            'tagged_user_id' => 'nullable|exists:users,id',
            'tagged_user_ids' => 'nullable|array',
            'tagged_user_ids.*' => 'nullable|exists:users,id',
            'is_reschedule' => 'nullable|boolean',
        ]);

        $msg = $this->followupService->updateOrReschedule($followup, $validated, $request->boolean('is_reschedule'));

        return redirect()->back()->with('success', $msg);
    }

    public function destroy(LeadFollowup $followup)
    {
        if ($followup->lead) {
            $this->authorize('update', $followup->lead);
        }

        $this->followupService->deleteFollowup($followup);

        return redirect()->back()->with('success', 'Follow-up successfully deleted!');
    }

    public function storeDealFollowup(Request $request, CrmDeal $deal)
    {
        $validated = $request->validate([
            'followup_date'      => 'nullable|string',
            'type'               => 'nullable|string',
            'status'             => 'nullable|string',
            'notes'              => 'nullable|string',
            'stage'              => 'nullable|string',
            'title'              => 'nullable|string',
            'duration_minutes'   => 'nullable|integer',
            'guest_emails'       => 'nullable|string',
            'tagged_user_id'     => 'nullable|exists:users,id',
            'tagged_user_ids'    => 'nullable|array',
            'tagged_user_ids.*'  => 'nullable|exists:users,id',
            'next_activity_type' => 'nullable|string',
            'next_followup_date' => 'nullable|string',
        ]);

        $actionMode = $request->input('action_mode', 'log_note');

        if (!empty($validated['stage'])) {
            $deal->stage = $validated['stage'];
            $deal->save();
        }

        $tenantId = tenant_id() ?? $deal->tenant_id ?? 1;

        if ($actionMode === 'schedule') {
            $scheduleType = $request->input('schedule_type') ?: ($validated['type'] ?? 'Call');
            $dueDate = $request->input('followup_date') ?: date('Y-m-d H:i');
            $notes = $request->input('schedule_notes') ?: $validated['notes'];

            try {
                $followupDateTime = \Illuminate\Support\Carbon::parse($dueDate);
            } catch (\Exception $e) {
                $followupDateTime = \Illuminate\Support\Carbon::now();
            }

            $taggedUserIds = !empty($validated['tagged_user_ids']) ? array_values(array_filter($validated['tagged_user_ids'])) : null;
            $primaryTaggedId = !empty($taggedUserIds) ? $taggedUserIds[0] : ($validated['tagged_user_id'] ?? null);

            $durationMinutes = !empty($request->input('duration_minutes')) ? (int)$request->input('duration_minutes') : 30;
            $guestEmailsInput = !empty($request->input('guest_emails')) ? trim($request->input('guest_emails')) : null;
            $titleInput = !empty($request->input('title')) ? trim($request->input('title')) : ($scheduleType . " with " . ($deal->title ?: 'Deal #' . $deal->id));

            $followup = LeadFollowup::create([
                'tenant_id'        => $tenantId,
                'crm_deal_id'      => $deal->id,
                'lead_id'          => null,
                'followup_date'    => $followupDateTime,
                'type'             => $scheduleType,
                'title'            => $titleInput,
                'duration_minutes' => $durationMinutes,
                'guest_emails'     => $guestEmailsInput,
                'status'           => 'Pending',
                'notes'            => $notes,
                'tagged_user_id'   => $primaryTaggedId,
                'tagged_user_ids'  => $taggedUserIds,
            ]);

            $syncGoogle = $request->boolean('sync_google_calendar') || $request->boolean('create_meet_link');
            if ($syncGoogle) {
                try {
                    $createMeet = $request->boolean('create_meet_link');
                    $attendees = [];
                    if ($deal->contact?->email) $attendees[] = $deal->contact->email;
                    if ($deal->account?->email) $attendees[] = $deal->account->email;
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

                    $calService = app(\App\Domains\CRM\Services\GoogleCalendarIntegrationService::class);
                    $res = $calService->createEvent([
                        'summary'          => $titleInput,
                        'description'      => $notes ?? '',
                        'start_time'       => $followupDateTime->toIso8601String(),
                        'end_time'         => (clone $followupDateTime)->addMinutes($durationMinutes)->toIso8601String(),
                        'attendees'        => $attendees,
                        'create_meet_link' => $createMeet,
                        'deal_id'          => $deal->id,
                    ]);

                    if (\Schema::hasColumn('lead_followups', 'google_event_id')) {
                        $followup->google_event_id = $res['google_event_id'] ?? null;
                    }
                    if (\Schema::hasColumn('lead_followups', 'google_meet_link')) {
                        // Sirf Meet select kiya ho to meet link store karo
                        $followup->google_meet_link = $createMeet ? ($res['meet_link'] ?? null) : null;
                    }
                    if (\Schema::hasColumn('lead_followups', 'is_google_meet')) {
                        $followup->is_google_meet = $createMeet;
                    }
                    // Notes mein Meet URL sirf tab append karo jab Meet select tha
                    if ($createMeet && !empty($res['meet_link'])) {
                        $followup->notes = ($followup->notes ? $followup->notes . "\n" : '') . "Google Meet: " . $res['meet_link'];
                    }
                    $followup->save();
                } catch (\Throwable $ex) {
                    \Illuminate\Support\Facades\Log::warning('Google Calendar auto-sync notice for deal: ' . $ex->getMessage());
                }
            }
        } else {
            $pastType = $validated['type'] ?? 'Call';
            $pastStatus = $validated['status'] ?? 'Connected';
            $pastNotes = trim($validated['notes'] ?? '');

            LeadFollowup::create([
                'tenant_id'       => $tenantId,
                'crm_deal_id'     => $deal->id,
                'lead_id'         => null,
                'followup_date'   => \Illuminate\Support\Carbon::now(),
                'type'            => $pastType,
                'status'          => $pastStatus,
                'notes'           => $pastNotes,
                'tagged_user_id'  => null,
                'tagged_user_ids' => null,
            ]);

            $nextDate = $request->input('next_followup_date');
            if (!empty($nextDate)) {
                $nextType = $request->input('next_activity_type') ?: 'Call';
                $contextSuffix = "Scheduled " . $nextType . " after " . strtolower($pastType) . " interaction";
                $nextNotes = !empty($pastNotes) ? ($pastNotes . " | " . $contextSuffix) : $contextSuffix;

                $taggedUserIds = !empty($validated['tagged_user_ids']) ? array_values(array_filter($validated['tagged_user_ids'])) : null;
                $primaryTaggedId = !empty($taggedUserIds) ? $taggedUserIds[0] : ($validated['tagged_user_id'] ?? null);

                try {
                    $nextDateTime = \Illuminate\Support\Carbon::parse($nextDate);
                } catch (\Exception $e) {
                    $nextDateTime = \Illuminate\Support\Carbon::now();
                }

                LeadFollowup::create([
                    'tenant_id'       => $tenantId,
                    'crm_deal_id'     => $deal->id,
                    'lead_id'         => null,
                    'followup_date'   => $nextDateTime,
                    'type'            => $nextType,
                    'status'          => 'Pending',
                    'notes'           => $nextNotes,
                    'tagged_user_id'  => $primaryTaggedId,
                    'tagged_user_ids' => $taggedUserIds,
                ]);
            }
        }

        return redirect()->back()->with('success', 'Deal activity logged/scheduled successfully!');
    }
}
