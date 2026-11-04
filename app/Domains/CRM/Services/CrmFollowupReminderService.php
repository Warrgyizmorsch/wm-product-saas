<?php

namespace App\Domains\CRM\Services;

use App\Domains\CRM\Models\Lead;
use App\Domains\CRM\Models\CrmDeal;
use App\Domains\CRM\Models\LeadFollowup;
use App\Domains\Platform\Services\NotificationRuleService;
use App\Services\Notification\NotificationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class CrmFollowupReminderService
{
    /**
     * Dispatch due and upcoming reminders for pending Lead and Deal follow-ups and meetings.
     *
     * @param int|null $tenantId
     * @param int|null $userId
     * @return int Number of reminders dispatched
     */
    public function dispatchDueReminders(?int $tenantId = null, ?int $userId = null): int
    {
        $now = Carbon::now();
        $dispatchedCount = 0;

        // -------------------------------------------------------------
        // Phase 1: 30-Minute Advance Reminders (Upcoming Meetings / Calls)
        // -------------------------------------------------------------
        $advanceQuery = LeadFollowup::query()
            ->where('status', 'Pending')
            ->whereNull('advance_reminder_sent_at')
            ->where('followup_date', '<=', $now->copy()->addMinutes(30))
            ->where('followup_date', '>', $now); // strictly upcoming in the future

        $this->applyScopeFilters($advanceQuery, $tenantId, $userId);

        $upcomingFollowups = $advanceQuery->with(['lead', 'deal.account'])->take(50)->get();

        foreach ($upcomingFollowups as $followup) {
            try {
                if ($followup->lead_id && $followup->lead) {
                    $this->dispatchLeadAdvanceReminder($followup, $now);
                    $dispatchedCount++;
                } elseif ($followup->crm_deal_id && $followup->deal) {
                    $this->dispatchDealAdvanceReminder($followup, $now);
                    $dispatchedCount++;
                } else {
                    $followup->update(['advance_reminder_sent_at' => $now]);
                }
            } catch (\Throwable $e) {
                if (app()->environment('testing')) {
                    throw $e;
                }
                Log::warning("Error dispatching CRM advance reminder for followup #{$followup->id}: " . $e->getMessage());
            }
        }

        // -------------------------------------------------------------
        // Phase 2: Exact-Time Due Reminders (Due Now)
        // -------------------------------------------------------------
        $dueQuery = LeadFollowup::query()
            ->where('status', 'Pending')
            ->whereNull('reminder_sent_at')
            ->where('followup_date', '<=', $now)
            ->where('followup_date', '>=', $now->copy()->subDays(2)); // Only alert for recent/active window

        $this->applyScopeFilters($dueQuery, $tenantId, $userId);

        $dueFollowups = $dueQuery->with(['lead', 'deal.account'])->take(50)->get();

        foreach ($dueFollowups as $followup) {
            try {
                if ($followup->lead_id && $followup->lead) {
                    $this->dispatchLeadDueReminder($followup, $now);
                    $dispatchedCount++;
                } elseif ($followup->crm_deal_id && $followup->deal) {
                    $this->dispatchDealDueReminder($followup, $now);
                    $dispatchedCount++;
                } else {
                    $followup->update(['reminder_sent_at' => $now]);
                }
            } catch (\Throwable $e) {
                if (app()->environment('testing')) {
                    throw $e;
                }
                Log::warning("Error dispatching CRM due reminder for followup #{$followup->id}: " . $e->getMessage());
            }
        }

        return $dispatchedCount;
    }

    /**
     * Apply common tenant and user filter scopes to query.
     */
    protected function applyScopeFilters($query, ?int $tenantId, ?int $userId): void
    {
        if (!empty($tenantId)) {
            $query->where('tenant_id', $tenantId);
        }

        if (!empty($userId)) {
            $query->where(function ($q) use ($userId) {
                $q->where('assigned_to_id', $userId)
                ->orWhereHas('lead', function ($lq) use ($userId) {
                    $lq->where('lead_owner_id', $userId);
                })
                ->orWhereHas('deal', function ($dq) use ($userId) {
                    $dq->where('owner_id', $userId);
                })
                ->orWhere('tagged_user_id', $userId)
                ->orWhereJsonContains('tagged_user_ids', $userId);
            });
        }
    }

    /**
     * Phase 1: Advance reminder (30 minutes before) for a Lead.
     */
    protected function dispatchLeadAdvanceReminder(LeadFollowup $followup, Carbon $now): void
    {
        $lead = $followup->lead;
        $targetUserIds = $this->resolveTargetUserIds($lead->lead_owner_id, $followup);

        $minutesLeft = max(1, (int) round($now->diffInMinutes($followup->followup_date, false)));
        $companyName = $lead->company_name ?: ($lead->contact_person ?: ('Lead #' . ($lead->lead_number ?? $lead->id)));
        $activityType = $followup->type ?: 'Follow-up';
        $timeFormatted = $followup->followup_date ? $followup->followup_date->format('d M, h:i A') : 'soon';
        $title = "⏳ Upcoming Follow-up (in {$minutesLeft}m): {$companyName}";
        $message = "{$activityType} scheduled with {$companyName}" . ($lead->contact_person ? " (Contact: {$lead->contact_person})" : "") . " starts in ~{$minutesLeft} mins at {$timeFormatted}.";
        $actionUrl = route('crm.leads.show', $lead->id);

        if (!empty($targetUserIds)) {
            NotificationService::sendToUserIds(
                userIds: $targetUserIds,
                title: $title,
                message: $message,
                actionUrl: $actionUrl,
                module: 'crm',
                type: 'followup_upcoming',
                iconClass: 'feather-clock',
                extraData: [
                    'lead_id' => $lead->id,
                    'followup_id' => $followup->id,
                    'tenant_id' => $followup->tenant_id,
                ]
            );
        }

        NotificationRuleService::trigger(
            eventKey: 'crm.lead.followup_upcoming',
            data: [
                'company_name'   => $companyName,
                'contact_person' => $lead->contact_person ?: '',
                'activity_type'  => $activityType,
                'scheduled_time' => $timeFormatted,
                'minutes_left'   => (string) $minutesLeft,
                'title'          => $followup->title ?: $title,
                'notes'          => $followup->notes ?: '',
                'lead_number'    => $lead->lead_number ?? '',
                'assigned_user_id' => $lead->lead_owner_id,
                'exclude_user_ids' => $targetUserIds,
            ],
            actionUrl: $actionUrl,
            subjectModel: $lead
        );

        $followup->update(['advance_reminder_sent_at' => $now]);
    }

    /**
     * Phase 1: Advance reminder (30 minutes before) for a Deal.
     */
    protected function dispatchDealAdvanceReminder(LeadFollowup $followup, Carbon $now): void
    {
        $deal = $followup->deal;
        $targetUserIds = $this->resolveTargetUserIds($deal->owner_id, $followup);

        $minutesLeft = max(1, (int) round($now->diffInMinutes($followup->followup_date, false)));
        $dealTitle = $deal->title ?: ('Deal #' . ($deal->deal_number ?? $deal->id));
        $accountName = $deal->account?->name ?: '';
        $activityType = $followup->type ?: 'Meeting';
        $timeFormatted = $followup->followup_date ? $followup->followup_date->format('d M, h:i A') : 'soon';
        $title = "⏳ Upcoming Meeting (in {$minutesLeft}m): {$dealTitle}";
        $message = "{$activityType} for Deal {$dealTitle}" . ($accountName ? " (Account: {$accountName})" : "") . " starts in ~{$minutesLeft} mins at {$timeFormatted}.";
        $actionUrl = route('crm.deals.show', $deal->id);

        if (!empty($targetUserIds)) {
            NotificationService::sendToUserIds(
                userIds: $targetUserIds,
                title: $title,
                message: $message,
                actionUrl: $actionUrl,
                module: 'crm',
                type: 'followup_upcoming',
                iconClass: 'feather-clock',
                extraData: [
                    'deal_id' => $deal->id,
                    'followup_id' => $followup->id,
                    'tenant_id' => $followup->tenant_id,
                ]
            );
        }

        NotificationRuleService::trigger(
            eventKey: 'crm.deal.followup_upcoming',
            data: [
                'deal_title'     => $dealTitle,
                'account_name'   => $accountName,
                'activity_type'  => $activityType,
                'scheduled_time' => $timeFormatted,
                'minutes_left'   => (string) $minutesLeft,
                'title'          => $followup->title ?: $title,
                'notes'          => $followup->notes ?: '',
                'deal_number'    => $deal->deal_number ?? '',
                'assigned_user_id' => $deal->owner_id,
                'exclude_user_ids' => $targetUserIds,
            ],
            actionUrl: $actionUrl,
            subjectModel: $deal
        );

        $followup->update(['advance_reminder_sent_at' => $now]);
    }

    /**
     * Phase 2: Exact-time due reminder for a Lead.
     */
    protected function dispatchLeadDueReminder(LeadFollowup $followup, Carbon $now): void
    {
        $lead = $followup->lead;
        $targetUserIds = $this->resolveTargetUserIds($lead->lead_owner_id, $followup);

        $companyName = $lead->company_name ?: ($lead->contact_person ?: ('Lead #' . ($lead->lead_number ?? $lead->id)));
        $activityType = $followup->type ?: 'Follow-up';
        $timeFormatted = $followup->followup_date ? $followup->followup_date->format('d M, h:i A') : 'now';
        $title = "🔔 Follow-up Due Now: {$companyName}";
        $message = "{$activityType} scheduled with {$companyName}" . ($lead->contact_person ? " (Contact: {$lead->contact_person})" : "") . " is due now ({$timeFormatted}).";
        $actionUrl = route('crm.leads.show', $lead->id);

        if (!empty($targetUserIds)) {
            NotificationService::sendToUserIds(
                userIds: $targetUserIds,
                title: $title,
                message: $message,
                actionUrl: $actionUrl,
                module: 'crm',
                type: 'followup_due',
                iconClass: 'feather-calendar',
                extraData: [
                    'lead_id' => $lead->id,
                    'followup_id' => $followup->id,
                    'tenant_id' => $followup->tenant_id,
                ]
            );
        }

        NotificationRuleService::trigger(
            eventKey: 'crm.lead.followup_due',
            data: [
                'company_name'   => $companyName,
                'contact_person' => $lead->contact_person ?: '',
                'activity_type'  => $activityType,
                'scheduled_time' => $timeFormatted,
                'title'          => $followup->title ?: $title,
                'notes'          => $followup->notes ?: '',
                'lead_number'    => $lead->lead_number ?? '',
                'assigned_user_id' => $lead->lead_owner_id,
                'exclude_user_ids' => $targetUserIds,
            ],
            actionUrl: $actionUrl,
            subjectModel: $lead
        );

        $followup->update([
            'reminder_sent_at' => $now,
            'advance_reminder_sent_at' => $followup->advance_reminder_sent_at ?: $now,
        ]);
    }

    /**
     * Phase 2: Exact-time due reminder for a Deal.
     */
    protected function dispatchDealDueReminder(LeadFollowup $followup, Carbon $now): void
    {
        $deal = $followup->deal;
        $targetUserIds = $this->resolveTargetUserIds($deal->owner_id, $followup);

        $dealTitle = $deal->title ?: ('Deal #' . ($deal->deal_number ?? $deal->id));
        $accountName = $deal->account?->name ?: '';
        $activityType = $followup->type ?: 'Meeting';
        $timeFormatted = $followup->followup_date ? $followup->followup_date->format('d M, h:i A') : 'now';
        $title = "🔔 Meeting Due Now: {$dealTitle}";
        $message = "{$activityType} for Deal {$dealTitle}" . ($accountName ? " (Account: {$accountName})" : "") . " is due now ({$timeFormatted}).";
        $actionUrl = route('crm.deals.show', $deal->id);

        if (!empty($targetUserIds)) {
            NotificationService::sendToUserIds(
                userIds: $targetUserIds,
                title: $title,
                message: $message,
                actionUrl: $actionUrl,
                module: 'crm',
                type: 'followup_due',
                iconClass: 'feather-calendar',
                extraData: [
                    'deal_id' => $deal->id,
                    'followup_id' => $followup->id,
                    'tenant_id' => $followup->tenant_id,
                ]
            );
        }

        NotificationRuleService::trigger(
            eventKey: 'crm.deal.followup_due',
            data: [
                'deal_title'     => $dealTitle,
                'account_name'   => $accountName,
                'activity_type'  => $activityType,
                'scheduled_time' => $timeFormatted,
                'title'          => $followup->title ?: $title,
                'notes'          => $followup->notes ?: '',
                'deal_number'    => $deal->deal_number ?? '',
                'assigned_user_id' => $deal->owner_id,
                'exclude_user_ids' => $targetUserIds,
            ],
            actionUrl: $actionUrl,
            subjectModel: $deal
        );

        $followup->update([
            'reminder_sent_at' => $now,
            'advance_reminder_sent_at' => $followup->advance_reminder_sent_at ?: $now,
        ]);
    }

    /**
     * Helper to resolve target user IDs for notifications.
     */
    protected function resolveTargetUserIds(?int $ownerId, LeadFollowup $followup): array
    {
        $targetUserIds = [];
        if (!empty($followup->assigned_to_id)) {
            $targetUserIds[] = (int) $followup->assigned_to_id;
        }
        if (!empty($ownerId)) {
            $targetUserIds[] = (int) $ownerId;
        }
        if (!empty($followup->tagged_user_id)) {
            $targetUserIds[] = (int) $followup->tagged_user_id;
        }
        if (!empty($followup->tagged_user_ids) && is_array($followup->tagged_user_ids)) {
            foreach ($followup->tagged_user_ids as $tid) {
                if (!empty($tid)) {
                    $targetUserIds[] = (int) $tid;
                }
            }
        }
        return array_values(array_unique(array_filter($targetUserIds)));
    }
}
