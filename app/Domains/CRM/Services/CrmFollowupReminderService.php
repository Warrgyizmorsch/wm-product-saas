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

        // -------------------------------------------------------------
        // Phase 3: FRS 2.5 (C-01): First Response SLA Breach Scans
        // -------------------------------------------------------------
        try {
            $dispatchedCount += $this->scanOverdueResponseSlas($tenantId);
        } catch (\Throwable $e) {
            if (app()->environment('testing')) {
                throw $e;
            }
            Log::warning("Error scanning CRM response SLAs: " . $e->getMessage());
        }

        // -------------------------------------------------------------
        // Phase 4: FRS 2.5 (C-02): Stale Lead Inactivity Scans
        // -------------------------------------------------------------
        try {
            $dispatchedCount += $this->scanStaleLeads($tenantId);
        } catch (\Throwable $e) {
            if (app()->environment('testing')) {
                throw $e;
            }
            Log::warning("Error scanning CRM stale leads: " . $e->getMessage());
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

    /**
     * FRS 2.5 (Task C-01): Scan open leads exceeding Response SLA threshold.
     */
    public function scanOverdueResponseSlas(?int $tenantId = null): int
    {
        $now = Carbon::now();
        $dispatchedCount = 0;

        $query = Lead::query()
            ->whereNotNull('assigned_at')
            ->whereNull('first_responded_at')
            ->whereNull('sla_escalated_at')
            ->whereNotIn('status', ['Won', 'Lost', 'Converted'])
            // a documented hold / customer pause suspends the response clock (FRS 2.5)
            ->where(function ($q) { $q->whereNull('on_hold_until')->orWhere('on_hold_until', '<', now()->toDateString()); });

        if (!empty($tenantId)) {
            $query->where('tenant_id', $tenantId);
        }

        $leads = $query->with('owner')->orderBy('assigned_at')->take(50)->get();

        foreach ($leads as $lead) {
            $tenant = \App\Models\Tenant::find($lead->tenant_id ?? 1);
            $settings = is_array($tenant?->settings) ? $tenant->settings : [];
            $slaMinutes = (int)($lead->response_sla_minutes ?: ($settings['crm_lead_policy']['response_sla_minutes'] ?? 60));

            $assignedAt = Carbon::parse($lead->assigned_at);
            $policy = $settings['crm_lead_policy'] ?? [];
            $elapsedMinutes = !empty($policy['sla_working_hours_only'])
                ? self::workingMinutesBetween($assignedAt, $now, $policy)
                : $assignedAt->diffInMinutes($now, false);

            if ($elapsedMinutes >= $slaMinutes) {
                $lead->update([
                    'response_sla_breached' => true,
                    'sla_escalated_at' => $now,
                ]);

                $companyName = $lead->company_name ?: ($lead->contact_person ?: ('Lead #' . ($lead->lead_number ?? $lead->id)));
                $ownerName = $lead->owner?->name ?: 'Unassigned';
                $title = "⚠️ SLA Breached: First Response Overdue for {$companyName}";
                $message = "Lead {$companyName} assigned to {$ownerName} has exceeded the first response SLA threshold of {$slaMinutes} minutes without customer touch.";
                $actionUrl = route('crm.leads.show', $lead->id);

                $managerIds = $this->resolveManagerUserIds($lead->tenant_id);
                $targetUserIds = array_unique(array_filter(array_merge([$lead->lead_owner_id], $managerIds)));

                if (!empty($targetUserIds)) {
                    NotificationService::sendToUserIds(
                        userIds: $targetUserIds,
                        title: $title,
                        message: $message,
                        actionUrl: $actionUrl,
                        module: 'crm',
                        type: 'sla_breached',
                        iconClass: 'feather-alert-triangle',
                        extraData: [
                            'lead_id' => $lead->id,
                            'tenant_id' => $lead->tenant_id,
                            'elapsed_minutes' => $elapsedMinutes,
                            'sla_threshold' => $slaMinutes,
                        ]
                    );
                }

                NotificationRuleService::trigger(
                    eventKey: 'crm.lead.sla_breached',
                    data: [
                        'company_name' => $companyName,
                        'lead_owner' => $ownerName,
                        'elapsed_minutes' => (string)$elapsedMinutes,
                        'sla_minutes' => (string)$slaMinutes,
                        'lead_number' => $lead->lead_number ?? '',
                    ],
                    actionUrl: $actionUrl,
                    subjectModel: $lead
                );

                $dispatchedCount++;
            }
        }

        return $dispatchedCount;
    }

    /**
     * FRS 2.5 / C-01: response time counted in working hours only (Mon-Sat 09:00-18:00 by default;
     * crm_lead_policy.work_start / work_end / work_days override it).
     */
    public static function workingMinutesBetween(Carbon $from, Carbon $to, array $policy = []): int
    {
        if ($to->lte($from)) {
            return 0;
        }
        [$sh, $sm] = array_map('intval', explode(':', (string) ($policy['work_start'] ?? '09:00')) + [0, 0]);
        [$eh, $em] = array_map('intval', explode(':', (string) ($policy['work_end'] ?? '18:00')) + [0, 0]);
        $days = array_map('intval', (array) ($policy['work_days'] ?? [1, 2, 3, 4, 5, 6]));

        $minutes = 0;
        $cursor = $from->copy()->startOfDay();
        $guard = 0;
        while ($cursor->lte($to) && $guard++ < 400) {
            if (in_array($cursor->dayOfWeekIso, $days, true)) {
                $dayStart = $cursor->copy()->setTime($sh, $sm);
                $dayEnd = $cursor->copy()->setTime($eh, $em);
                $start = $dayStart->gt($from) ? $dayStart : $from;
                $end = $dayEnd->lt($to) ? $dayEnd : $to;
                if ($end->gt($start)) {
                    $minutes += (int) $start->diffInMinutes($end);
                }
            }
            $cursor->addDay();
        }

        return $minutes;
    }

    /**
     * FRS 2.5 (Task C-02): Scan open leads for inactivity or missing next action.
     */
    public function scanStaleLeads(?int $tenantId = null): int
    {
        $now = Carbon::now();
        $dispatchedCount = 0;

        $leadsQuery = Lead::query()
            ->whereNotIn('status', ['Won', 'Lost', 'Converted'])
            // leads on a valid hold are not stale; the hold review task is their dated next action
            ->where(function ($q) { $q->whereNull('on_hold_until')->orWhere('on_hold_until', '<', now()->toDateString()); });

        if (!empty($tenantId)) {
            $leadsQuery->where('tenant_id', $tenantId);
        }

        $leads = $leadsQuery->with('owner')->orderByRaw('stale_alert_sent_at IS NOT NULL')->orderBy('stale_alert_sent_at')->orderBy('id')->take(50)->get();

        foreach ($leads as $lead) {
            $tenant = \App\Models\Tenant::find($lead->tenant_id ?? 1);
            $settings = is_array($tenant?->settings) ? $tenant->settings : [];
            $staleDays = (int)($settings['crm_lead_policy']['stale_lead_days'] ?? 3);
            $cadenceDays = (int)($settings['crm_lead_policy']['stale_alert_cadence_days'] ?? 2);

            // Stale condition 1: No active next action scheduled
            $hasActiveNextAction = $lead->followups()
                ->where('status', 'Pending')
                ->where('followup_date', '>=', $now->copy()->startOfDay())
                ->exists();

            // Stale condition 2: Overdue or inactivity older than configured days
            $lastActivity = $lead->last_activity_at ?: ($lead->assigned_at ?: $lead->created_at);
            $daysInactive = $lastActivity ? Carbon::parse($lastActivity)->diffInDays($now) : 999;

            $hasOwner = !empty($lead->lead_owner_id);
            $isStale = (!$hasOwner || !$hasActiveNextAction || $daysInactive >= $staleDays);

            if ($isStale) {
                // Check if alert was already sent recently
                if ($lead->stale_alert_sent_at) {
                    $alertSentAgo = Carbon::parse($lead->stale_alert_sent_at)->diffInDays($now);
                    if ($alertSentAgo < $cadenceDays) {
                        continue;
                    }
                }

                $lead->update([
                    'has_active_next_action' => $hasActiveNextAction,
                    'stale_alert_sent_at' => $now,
                ]);

                $companyName = $lead->company_name ?: ($lead->contact_person ?: ('Lead #' . ($lead->lead_number ?? $lead->id)));
                $ownerName = $lead->owner?->name ?: 'Unassigned';
                $reason = !$hasOwner ? 'No accountable owner assigned' : (!$hasActiveNextAction ? 'No active next action scheduled' : "{$daysInactive} days of inactivity");
                $title = "⚠️ Stale Lead Alert: {$companyName}";
                $message = "Lead {$companyName} (Owner: {$ownerName}) requires attention ({$reason}). CRM policy mandates an active next step.";
                $actionUrl = route('crm.leads.show', $lead->id);

                $managerIds = $this->resolveManagerUserIds($lead->tenant_id);
                $targetUserIds = array_unique(array_filter(array_merge([$lead->lead_owner_id], $managerIds)));

                if (!empty($targetUserIds)) {
                    NotificationService::sendToUserIds(
                        userIds: $targetUserIds,
                        title: $title,
                        message: $message,
                        actionUrl: $actionUrl,
                        module: 'crm',
                        type: 'lead_stale_alert',
                        iconClass: 'feather-alert-circle',
                        extraData: [
                            'lead_id' => $lead->id,
                            'tenant_id' => $lead->tenant_id,
                            'reason' => $reason,
                        ]
                    );
                }

                NotificationRuleService::trigger(
                    eventKey: 'crm.lead.stale_inactivity',
                    data: [
                        'company_name' => $companyName,
                        'lead_owner' => $ownerName,
                        'reason' => $reason,
                        'days_inactive' => (string)$daysInactive,
                        'lead_number' => $lead->lead_number ?? '',
                    ],
                    actionUrl: $actionUrl,
                    subjectModel: $lead
                );

                $dispatchedCount++;
            }
        }

        return $dispatchedCount;
    }

    /**
     * Resolve managers and administrator IDs for SLA and Stale escalation.
     */
    protected function resolveManagerUserIds(?int $tenantId): array
    {
        $query = \App\Models\User::query();
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }
        $users = $query->get(['id', 'role', 'role_id']);
        return $users->filter(fn($u) => $u->hasAnyRole('admin', 'sales_manager', 'manager', 'owner', 'super_admin'))
            ->pluck('id')
            ->toArray();
    }
}
