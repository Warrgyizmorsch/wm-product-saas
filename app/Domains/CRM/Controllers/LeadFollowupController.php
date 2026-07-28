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
            'followup_date' => 'required|string',
            'type' => 'required|string|in:Call,Email,Meeting,Demo',
            'status' => 'required|string|in:Pending,Completed,Not Connected,Cancelled,Rescheduled',
            'notes' => 'nullable|string',
        ]);

        $this->followupService->storeFollowup($lead, $validated);

        return redirect()->route('crm.leads.show', $lead->id)->with('success', 'Follow-up successfully scheduled/logged!');
    }

    public function update(Request $request, LeadFollowup $followup)
    {
        $this->authorize('update', $followup->lead);

        $validated = $request->validate([
            'status' => 'nullable|string|in:Pending,Completed,Not Connected,Cancelled,Rescheduled',
            'notes' => 'nullable|string',
            'type' => 'nullable|string|in:Call,Email,Meeting,Demo',
            'followup_date' => 'nullable|string',
            'is_reschedule' => 'nullable|boolean',
        ]);

        $msg = $this->followupService->updateOrReschedule($followup, $validated, $request->boolean('is_reschedule'));

        return redirect()->route('crm.leads.show', $followup->lead_id)->with('success', $msg);
    }

    public function destroy(LeadFollowup $followup)
    {
        $this->authorize('update', $followup->lead);
        $leadId = $followup->lead_id;

        $this->followupService->deleteFollowup($followup);

        return redirect()->route('crm.leads.show', $leadId)->with('success', 'Follow-up successfully deleted!');
    }
}
