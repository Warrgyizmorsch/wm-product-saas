<?php

namespace App\Domains\CRM\Services;

use App\Domains\CRM\Models\Lead;
use App\Domains\CRM\Models\LeadHistory;
use App\Domains\CRM\Repositories\LeadFollowupRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * FRS 2.5 checks: "no open lead without an owner, next action and due date".
 *
 * A lead that is open, owned and NOT on a valid hold always carries a dated next action.
 * When none exists (new lead, last activity completed/cancelled/deleted) a system follow-up is
 * created for the owner so the lead never silently drops out of the pipeline.
 */
class LeadNextActionService
{
    public const CLOSED_STATUSES = ['won', 'lost', 'converted'];

    public static function isOpen(Lead $lead): bool
    {
        return !in_array(strtolower((string) $lead->status), self::CLOSED_STATUSES, true);
    }

    public static function isOnHold(Lead $lead): bool
    {
        return !empty($lead->on_hold_until) && Carbon::parse($lead->on_hold_until)->endOfDay()->isFuture();
    }

    public function hasPendingAction(Lead $lead): bool
    {
        return $lead->followups()->where('status', 'Pending')->exists();
    }

    /**
     * Create a system next action when an open, owned lead has none. Returns true when one was created.
     */
    public function ensureNextAction(Lead $lead, ?string $reason = null): bool
    {
        if (!$lead->exists || !self::isOpen($lead) || empty($lead->lead_owner_id) || self::isOnHold($lead)) {
            return false;
        }
        if ($this->hasPendingAction($lead)) {
            return false;
        }

        try {
            $slaMinutes = (int) ($lead->response_sla_minutes ?: 60);
            $isFirst = empty($lead->first_responded_at);
            $due = $isFirst
                ? Carbon::parse($lead->assigned_at ?: now())->addMinutes($slaMinutes)
                : now()->addDay()->setTime(10, 0);

            app(LeadFollowupRepository::class)->create([
                'tenant_id' => $lead->tenant_id,
                'company_id' => $lead->company_id,
                'branch_id' => $lead->branch_id,
                'lead_id' => $lead->id,
                'assigned_to_id' => $lead->lead_owner_id,
                'followup_date' => $due,
                'type' => $isFirst ? 'Call' : 'Task',
                'title' => $isFirst
                    ? 'First response: ' . ($lead->contact_person ?: $lead->company_name)
                    : 'Next action required: ' . ($lead->contact_person ?: $lead->company_name),
                'status' => 'Pending',
                'notes' => $reason ?: 'System-created next action (FRS 2.5: an open lead always has an owner, a next action and a due date). Edit or reschedule as needed.',
            ]);

            app(LeadFollowupRepository::class)->syncLeadNextFollowupDate($lead);
            LeadHistory::logEvent($lead, 'next_action_created', null, $due->format('Y-m-d H:i'), 'System created the required next action.');

            return true;
        } catch (\Throwable $e) {
            Log::warning('LeadNextActionService failed: ' . $e->getMessage());

            return false;
        }
    }
}
