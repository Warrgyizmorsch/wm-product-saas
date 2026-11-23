<?php

namespace App\Domains\CRM\Services;

use App\Domains\CRM\Models\Lead;
use App\Domains\CRM\Models\LeadHistory;
use App\Domains\CRM\Repositories\LeadFollowupRepository;
use App\Models\User;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * FRS 2.5 additional functional controls: an open record may have a documented hold / exception review
 * or a customer-requested pause instead of an outreach activity. The hold carries a reason and a review
 * date (the due date of the "next action"), never forces outreach while it lasts, and is kept in history.
 */
class LeadHoldService
{
    public const TYPES = [
        'customer_pause' => 'Customer-requested pause',
        'exception_review' => 'Documented hold / exception review',
        'internal_clarification' => 'Internal clarification pending',
    ];

    public function placeOnHold(Lead $lead, string $type, string $reason, Carbon|string $reviewDate, ?User $actor = null): Lead
    {
        if (!isset(self::TYPES[$type])) {
            throw new InvalidArgumentException('Unknown hold type.');
        }
        if (!LeadNextActionService::isOpen($lead)) {
            throw new InvalidArgumentException('Only an open lead can be put on hold.');
        }
        if (trim($reason) === '') {
            throw new InvalidArgumentException('A hold reason is required.');
        }
        $review = Carbon::parse($reviewDate)->startOfDay();
        if ($review->lt(now()->startOfDay())) {
            throw new InvalidArgumentException('The hold review date cannot be in the past.');
        }

        $old = $lead->on_hold_until?->toDateString();
        $lead->update([
            'on_hold_until' => $review->toDateString(),
            'hold_type' => $type,
            'hold_reason' => mb_substr(trim($reason), 0, 500),
            'hold_set_at' => now(),
            'hold_set_by' => $actor?->id ?? auth()->id(),
        ]);

        // The review is the dated next action; outreach tasks stay as they are but nothing new is forced.
        app(LeadFollowupRepository::class)->create([
            'tenant_id' => $lead->tenant_id,
            'company_id' => $lead->company_id,
            'branch_id' => $lead->branch_id,
            'lead_id' => $lead->id,
            'assigned_to_id' => $lead->lead_owner_id ?: ($actor?->id ?? auth()->id()),
            'followup_date' => $review->copy()->setTime(10, 0),
            'type' => 'Task',
            'title' => 'Hold review: ' . ($lead->contact_person ?: $lead->company_name),
            'status' => 'Pending',
            'notes' => self::TYPES[$type] . ': ' . trim($reason),
        ]);
        app(LeadFollowupRepository::class)->syncLeadNextFollowupDate($lead->fresh());

        LeadHistory::logEvent(
            $lead,
            'hold_started',
            $old ?: 'None',
            $review->toDateString(),
            self::TYPES[$type] . ': ' . trim($reason)
        );

        return $lead->fresh();
    }

    public function resume(Lead $lead, ?string $note = null): Lead
    {
        if (empty($lead->on_hold_until) && empty($lead->hold_type)) {
            return $lead;
        }

        $previous = $lead->hold_type;
        $lead->update([
            'on_hold_until' => null,
            'hold_type' => null,
            'hold_reason' => null,
            'hold_set_at' => null,
            'hold_set_by' => null,
        ]);

        LeadHistory::logEvent($lead, 'hold_ended', $previous, null, $note ?: 'Hold ended; normal follow-up resumes.');
        app(LeadNextActionService::class)->ensureNextAction($lead->fresh());

        return $lead->fresh();
    }
}
