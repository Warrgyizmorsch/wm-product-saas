<?php

namespace App\Domains\CRM\Controllers;

use App\Domains\CRM\Models\Lead;
use App\Domains\CRM\Models\LeadFollowup;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class LeadFollowupController extends Controller
{
    /**
     * Store a newly created lead follow-up.
     */
    public function store(Request $request, Lead $lead)
    {
        $this->authorize('update', $lead);

        $validated = $request->validate([
            'followup_date' => 'required|string',
            'type' => 'required|string|in:Call,Email,Meeting,Demo',
            'status' => 'required|string|in:Pending,Completed,Cancelled',
            'notes' => 'nullable|string',
        ]);

        // Parse Followup Date & Time
        try {
            $followupDateTime = Carbon::parse($validated['followup_date']);
        } catch (\Exception $e) {
            $followupDateTime = Carbon::now();
        }

        // Create the followup (BelongsToTenant will auto-inject tenant_id)
        // Create the followup (BelongsToTenant will auto-inject tenant_id)
        $followup = LeadFollowup::create([
            'lead_id' => $lead->id,
            'followup_date' => $followupDateTime,
            'type' => $validated['type'],
            'status' => $validated['status'],
            'notes' => $validated['notes'],
        ]);

        // Log history event
        $eventType = $followup->status === 'Pending' ? 'activity_scheduled' : 'activity_completed';
        $description = $followup->status === 'Pending' 
            ? "Scheduled a {$followup->type} activity on " . $followup->followup_date->format('d/m/Y h:i A')
            : "Logged a {$followup->type} interaction: " . ($followup->notes ?: 'No details');

        \App\Domains\CRM\Models\LeadHistory::logEvent(
            $lead,
            $eventType,
            null,
            $followup->type,
            $description
        );

        // Sync Parent Lead status and next meetup date
        $this->syncLeadStatusAndFollowupDate($lead);

        return redirect()
            ->route('crm.leads.show', $lead->id)
            ->with('success', 'Follow-up successfully scheduled/logged!');
    }

    /**
     * Update the specified lead follow-up.
     */
    public function update(Request $request, LeadFollowup $followup)
    {
        $this->authorize('update', $followup->lead);

        $validated = $request->validate([
            'status' => 'nullable|string|in:Pending,Completed,Cancelled',
            'notes' => 'nullable|string',
            'type' => 'nullable|string|in:Call,Email,Meeting,Demo',
            'followup_date' => 'nullable|string',
        ]);

        $updateData = [];
        if (isset($validated['status'])) {
            $updateData['status'] = $validated['status'];
        }
        if (isset($validated['notes'])) {
            $updateData['notes'] = $validated['notes'];
        }
        if (isset($validated['type'])) {
            $updateData['type'] = $validated['type'];
        }
        if (isset($validated['followup_date'])) {
            try {
                $updateData['followup_date'] = Carbon::parse($validated['followup_date']);
            } catch (\Exception $e) {
                // Ignore invalid date format
            }
        }

        $oldStatus = $followup->status;
        $followup->update($updateData);

        if ($oldStatus !== $followup->status && $followup->status === 'Completed') {
            \App\Domains\CRM\Models\LeadHistory::logEvent(
                $followup->lead,
                'activity_completed',
                $oldStatus,
                'Completed',
                "Marked scheduled {$followup->type} activity (scheduled for " . $followup->followup_date->format('d/m/Y h:i A') . ") as Completed"
            );
        }

        // Sync Parent Lead status and next meetup date
        $lead = $followup->lead;
        $this->syncLeadStatusAndFollowupDate($lead);

        return redirect()
            ->route('crm.leads.show', $lead->id)
            ->with('success', 'Follow-up successfully updated/rescheduled!');
    }

    /**
     * Remove the specified lead follow-up.
     */
    public function destroy(LeadFollowup $followup)
    {
        $this->authorize('update', $followup->lead);

        $lead = $followup->lead;

        \App\Domains\CRM\Models\LeadHistory::logEvent(
            $lead,
            'activity_deleted',
            $followup->type,
            null,
            "Deleted {$followup->type} activity (scheduled/logged for " . $followup->followup_date->format('d/m/Y h:i A') . ")"
        );

        $followup->delete();

        // Sync Parent Lead status and next meetup date
        $this->syncLeadStatusAndFollowupDate($lead);

        return redirect()
            ->route('crm.leads.show', $lead->id)
            ->with('success', 'Follow-up successfully deleted!');
    }

    /**
     * Recalculate and update the parent lead's status and next meetup date.
     */
    protected function syncLeadStatusAndFollowupDate(Lead $lead): void
    {
        // Refresh relation to get latest DB state
        $lead->unsetRelation('followups');

        // Find the next nearest pending followup
        $nextPending = $lead->followups()
            ->where('status', 'Pending')
            ->orderBy('followup_date', 'asc')
            ->first();

        if ($nextPending) {
            $lead->next_followup_date = $nextPending->followup_date;
        } else {
            $lead->next_followup_date = null;
        }
        
        $lead->save();
    }
}
