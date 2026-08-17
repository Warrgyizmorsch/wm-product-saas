<?php

namespace App\Domains\CRM\Controllers;

use App\Domains\CRM\Models\Lead;
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
                'type'            => $scheduleType,
                'status'          => 'Pending',
                'followup_date'   => $dueDate,
                'notes'           => $notes,
                'tagged_user_ids' => $validated['tagged_user_ids'] ?? null,
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
                    'type'            => $nextType,
                    'status'          => 'Pending',
                    'followup_date'   => $nextDate,
                    'notes'           => $nextNotes,
                    'tagged_user_ids' => $validated['tagged_user_ids'] ?? null,
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
        $this->authorize('update', $followup->lead);

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
        $this->authorize('update', $followup->lead);

        $this->followupService->deleteFollowup($followup);

        return redirect()->back()->with('success', 'Follow-up successfully deleted!');
    }
}
