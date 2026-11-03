<?php

namespace App\Domains\CRM\Services;

use App\Domains\CRM\Models\CrmDeal;
use App\Domains\CRM\Models\Lead;
use App\Domains\CRM\Models\Quotation;
use App\Domains\Platform\Services\NotificationRuleService;
use App\Models\User;
use App\Services\Notification\NotificationService;
use Illuminate\Support\Facades\Log;

class CrmNotificationService
{
    /**
     * 1. Trigger when a new Lead is created / captured.
     */
    public static function notifyLeadCreated(Lead $lead): void
    {
        try {
            $companyName = $lead->company_name ?: ($lead->contact_person ?: "Lead #{$lead->lead_number}");
            $actionUrl = route('crm.leads.show', $lead->id);

            NotificationRuleService::trigger(
                eventKey: 'crm.lead.created',
                data: [
                    'company_name'    => $companyName,
                    'contact_person'  => $lead->contact_person ?: '',
                    'source'          => $lead->source ?: 'Direct',
                    'expected_amount' => number_format((float) ($lead->expected_amount ?? 0), 2),
                    'created_by'      => auth()->user()?->name ?: 'System',
                    'lead_number'     => $lead->lead_number ?? '',
                    'assigned_user_id'=> $lead->lead_owner_id,
                ],
                actionUrl: $actionUrl,
                subjectModel: $lead
            );
        } catch (\Throwable $e) {
            Log::warning("CRM Notification failed (crm.lead.created): " . $e->getMessage());
        }
    }

    /**
     * 2. Trigger when a Lead is assigned / reassigned to a Sales Executive.
     */
    public static function notifyLeadAssigned(Lead $lead, ?int $assignedToUserId, ?int $assignedByUserId = null): void
    {
        LeadService::notifyLeadAssigned($lead, $assignedToUserId, $assignedByUserId);
    }

    /**
     * 3. Trigger when a Lead status changes.
     */
    public static function notifyLeadStatusUpdated(Lead $lead, string $oldStatus, string $newStatus): void
    {
        try {
            $companyName = $lead->company_name ?: ($lead->contact_person ?: "Lead #{$lead->lead_number}");
            $actionUrl = route('crm.leads.show', $lead->id);
            $updatedBy = auth()->user()?->name ?: 'System';

            if (strcasecmp($newStatus, 'Lost') === 0) {
                self::notifyLeadLost($lead);
                return;
            }

            // Direct in-app + Pusher alert to lead owner
            if (!empty($lead->lead_owner_id) && $lead->lead_owner_id !== auth()->id()) {
                NotificationService::send(
                    user: $lead->lead_owner_id,
                    title: "Lead Status: {$newStatus} ({$companyName})",
                    message: "Lead {$companyName} stage was updated from {$oldStatus} to {$newStatus} by {$updatedBy}.",
                    actionUrl: $actionUrl,
                    module: 'crm',
                    type: 'lead_status_updated',
                    iconClass: 'feather-refresh-cw'
                );
            }

            NotificationRuleService::trigger(
                eventKey: 'crm.lead.status_updated',
                data: [
                    'company_name'   => $companyName,
                    'contact_person' => $lead->contact_person ?: '',
                    'old_status'     => $oldStatus,
                    'new_status'     => $newStatus,
                    'updated_by'     => $updatedBy,
                    'lead_number'    => $lead->lead_number ?? '',
                    'assigned_user_id' => $lead->lead_owner_id,
                    'exclude_user_ids' => (!empty($lead->lead_owner_id) && $lead->lead_owner_id !== auth()->id()) ? [$lead->lead_owner_id] : [],
                ],
                actionUrl: $actionUrl,
                subjectModel: $lead
            );
        } catch (\Throwable $e) {
            Log::warning("CRM Notification failed (crm.lead.status_updated): " . $e->getMessage());
        }
    }

    /**
     * 4. Trigger when a Lead is marked as Lost.
     */
    public static function notifyLeadLost(Lead $lead, ?string $reason = null): void
    {
        try {
            $companyName = $lead->company_name ?: ($lead->contact_person ?: "Lead #{$lead->lead_number}");
            $actionUrl = route('crm.leads.show', $lead->id);
            $updatedBy = auth()->user()?->name ?: 'System';

            NotificationRuleService::trigger(
                eventKey: 'crm.lead.lost',
                data: [
                    'company_name'   => $companyName,
                    'contact_person' => $lead->contact_person ?: '',
                    'updated_by'     => $updatedBy,
                    'lead_number'    => $lead->lead_number ?? '',
                    'reason'         => $reason ?: 'Not specified',
                    'assigned_user_id' => $lead->lead_owner_id,
                ],
                actionUrl: $actionUrl,
                subjectModel: $lead
            );
        } catch (\Throwable $e) {
            Log::warning("CRM Notification failed (crm.lead.lost): " . $e->getMessage());
        }
    }

    /**
     * 5. Trigger when a Lead is converted to a Deal / Customer.
     */
    public static function notifyLeadConverted(Lead $lead, ?CrmDeal $deal = null): void
    {
        try {
            $companyName = $lead->company_name ?: ($lead->contact_person ?: "Lead #{$lead->lead_number}");
            $actionUrl = $deal ? route('crm.deals.show', $deal->id) : route('crm.leads.show', $lead->id);
            $convertedBy = auth()->user()?->name ?: 'System';

            NotificationRuleService::trigger(
                eventKey: 'crm.lead.converted',
                data: [
                    'company_name'   => $companyName,
                    'contact_person' => $lead->contact_person ?: '',
                    'converted_by'   => $convertedBy,
                    'lead_number'    => $lead->lead_number ?? '',
                    'deal_number'    => $deal?->deal_number ?? '',
                    'assigned_user_id' => $deal?->owner_id ?: $lead->lead_owner_id,
                ],
                actionUrl: $actionUrl,
                subjectModel: $deal ?: $lead
            );
        } catch (\Throwable $e) {
            Log::warning("CRM Notification failed (crm.lead.converted): " . $e->getMessage());
        }
    }

    /**
     * 6. Trigger when a new Deal / Opportunity is created.
     */
    public static function notifyDealCreated(CrmDeal $deal): void
    {
        try {
            $accountName = $deal->account?->name ?: 'General Account';
            $amountFormatted = number_format((float) ($deal->estimated_value ?? 0), 2);
            $createdBy = auth()->user()?->name ?: 'System';
            $actionUrl = route('crm.deals.show', $deal->id);

            // 1. Direct notification to assigned Deal Owner if assigned to someone else
            if (!empty($deal->owner_id) && $deal->owner_id !== auth()->id()) {
                self::notifyDealAssigned($deal, $deal->owner_id, auth()->id());
            }

            // 2. Notification Master trigger for Managers / Executives
            NotificationRuleService::trigger(
                eventKey: 'crm.deal.created',
                data: [
                    'deal_title'   => $deal->title,
                    'account_name' => $accountName,
                    'amount'       => $amountFormatted,
                    'created_by'   => $createdBy,
                    'deal_number'  => $deal->deal_number ?? '',
                    'assigned_user_id' => $deal->owner_id,
                    'exclude_user_ids' => (!empty($deal->owner_id) && $deal->owner_id !== auth()->id()) ? [$deal->owner_id] : [],
                ],
                actionUrl: $actionUrl,
                subjectModel: $deal
            );
        } catch (\Throwable $e) {
            Log::warning("CRM Notification failed (crm.deal.created): " . $e->getMessage());
        }
    }

    /**
     * 7. Trigger when a Deal is assigned / reassigned to an Executive.
     */
    public static function notifyDealAssigned(CrmDeal $deal, ?int $assignedToUserId, ?int $assignedByUserId = null): void
    {
        if (empty($assignedToUserId)) {
            return;
        }

        try {
            $assignedByUser = $assignedByUserId ? User::find($assignedByUserId) : auth()->user();
            $assignedByName = $assignedByUser?->name ?: 'System Admin';
            $accountName = $deal->account?->name ?: 'General Account';
            $dealTitle = $deal->title ?: ('Deal #' . ($deal->deal_number ?? $deal->id));
            $actionUrl = route('crm.deals.show', $deal->id);
            $amountFormatted = number_format((float) ($deal->estimated_value ?? 0), 2);

            // In-app Bell + Real-time Pusher directly to the assigned user
            NotificationService::send(
                user: $assignedToUserId,
                title: "New Deal Assigned: {$dealTitle}",
                message: "Deal '{$dealTitle}' ({$accountName}, ₹{$amountFormatted}) has been assigned to you by {$assignedByName}.",
                actionUrl: $actionUrl,
                module: 'crm',
                type: 'deal_assigned',
                iconClass: 'feather-briefcase',
                extraData: ['deal_id' => $deal->id, 'tenant_id' => $deal->tenant_id]
            );

            NotificationRuleService::trigger(
                eventKey: 'crm.deal.assigned',
                data: [
                    'deal_title'   => $dealTitle,
                    'account_name' => $accountName,
                    'amount'       => $amountFormatted,
                    'assigned_to'  => User::find($assignedToUserId)?->name ?: 'Executive',
                    'assigned_by'  => $assignedByName,
                    'deal_number'  => $deal->deal_number ?? '',
                    'assigned_user_id' => $assignedToUserId,
                    'exclude_user_ids' => [$assignedToUserId],
                ],
                actionUrl: $actionUrl,
                subjectModel: $deal
            );
        } catch (\Throwable $e) {
            Log::warning("CRM Notification failed (crm.deal.assigned): " . $e->getMessage());
        }
    }

    /**
     * 8. Trigger when a Deal stage changes.
     */
    public static function notifyDealStageUpdated(CrmDeal $deal, string $oldStage, string $newStage): void
    {
        try {
            $accountName = $deal->account?->name ?: 'General Account';
            $updatedBy = auth()->user()?->name ?: 'System';
            $actionUrl = route('crm.deals.show', $deal->id);

            if (in_array(strtolower($newStage), ['won', 'closed won'], true)) {
                self::notifyDealWon($deal);
                return;
            }

            if (in_array(strtolower($newStage), ['lost', 'closed lost'], true)) {
                self::notifyDealLost($deal, $deal->close_reason);
                return;
            }

            // Direct in-app + Pusher alert to deal owner
            if (!empty($deal->owner_id) && $deal->owner_id !== auth()->id()) {
                NotificationService::send(
                    user: $deal->owner_id,
                    title: "Deal Stage: {$newStage} ({$deal->title})",
                    message: "Deal '{$deal->title}' moved from {$oldStage} to {$newStage} by {$updatedBy}.",
                    actionUrl: $actionUrl,
                    module: 'crm',
                    type: 'deal_stage_updated',
                    iconClass: 'feather-trending-up'
                );
            }

            NotificationRuleService::trigger(
                eventKey: 'crm.deal.stage_updated',
                data: [
                    'deal_title'   => $deal->title,
                    'account_name' => $accountName,
                    'old_stage'    => $oldStage,
                    'new_stage'    => $newStage,
                    'updated_by'   => $updatedBy,
                    'deal_number'  => $deal->deal_number ?? '',
                    'assigned_user_id' => $deal->owner_id,
                    'exclude_user_ids' => (!empty($deal->owner_id) && $deal->owner_id !== auth()->id()) ? [$deal->owner_id] : [],
                ],
                actionUrl: $actionUrl,
                subjectModel: $deal
            );
        } catch (\Throwable $e) {
            Log::warning("CRM Notification failed (crm.deal.stage_updated): " . $e->getMessage());
        }
    }

    /**
     * 9. Trigger when a Deal is WON!
     */
    public static function notifyDealWon(CrmDeal $deal): void
    {
        try {
            $accountName = $deal->account?->name ?: 'Customer';
            $amountFormatted = number_format((float) ($deal->estimated_value ?? 0), 2);
            $wonBy = auth()->user()?->name ?: 'User';
            $actionUrl = route('crm.deals.show', $deal->id);

            // Direct celebration alert to deal owner
            if (!empty($deal->owner_id)) {
                NotificationService::send(
                    user: $deal->owner_id,
                    title: "🎉 Deal WON: {$deal->title}",
                    message: "Congratulations! Deal '{$deal->title}' for {$accountName} (₹{$amountFormatted}) has been successfully marked as WON!",
                    actionUrl: $actionUrl,
                    module: 'crm',
                    type: 'deal_won',
                    iconClass: 'feather-award'
                );
            }

            NotificationRuleService::trigger(
                eventKey: 'crm.deal.won',
                data: [
                    'deal_title'    => $deal->title,
                    'customer_name' => $accountName,
                    'amount'        => $amountFormatted,
                    'won_by'        => $wonBy,
                    'deal_number'   => $deal->deal_number ?? '',
                    'assigned_user_id' => $deal->owner_id,
                    'exclude_user_ids' => !empty($deal->owner_id) ? [$deal->owner_id] : [],
                ],
                actionUrl: $actionUrl,
                subjectModel: $deal
            );
        } catch (\Throwable $e) {
            Log::warning("CRM Notification failed (crm.deal.won): " . $e->getMessage());
        }
    }

    /**
     * 10. Trigger when a Deal is Closed Lost.
     */
    public static function notifyDealLost(CrmDeal $deal, ?string $reason = null): void
    {
        try {
            $accountName = $deal->account?->name ?: 'Customer';
            $amountFormatted = number_format((float) ($deal->estimated_value ?? 0), 2);
            $closedBy = auth()->user()?->name ?: 'User';
            $actionUrl = route('crm.deals.show', $deal->id);

            NotificationRuleService::trigger(
                eventKey: 'crm.deal.lost',
                data: [
                    'deal_title'    => $deal->title,
                    'customer_name' => $accountName,
                    'amount'        => $amountFormatted,
                    'closed_by'     => $closedBy,
                    'reason'        => $reason ?: ($deal->close_reason ?: 'Not specified'),
                    'deal_number'   => $deal->deal_number ?? '',
                    'assigned_user_id' => $deal->owner_id,
                ],
                actionUrl: $actionUrl,
                subjectModel: $deal
            );
        } catch (\Throwable $e) {
            Log::warning("CRM Notification failed (crm.deal.lost): " . $e->getMessage());
        }
    }

    /**
     * 11. Trigger when a Quotation is created.
     */
    public static function notifyQuotationCreated(Quotation $quotation): void
    {
        try {
            $customerName = $quotation->customer?->name ?: ($quotation->lead?->company_name ?: ($quotation->crmAccount?->name ?: 'Customer'));
            $amountFormatted = number_format((float) ($quotation->total_amount ?? 0), 2);
            $createdBy = auth()->user()?->name ?: 'Sales Team';
            $actionUrl = route('crm.quotations.show', $quotation->id);

            NotificationRuleService::trigger(
                eventKey: 'crm.quotation.created',
                data: [
                    'quotation_no'  => $quotation->quotation_number,
                    'customer_name' => $customerName,
                    'amount'        => $amountFormatted,
                    'created_by'    => $createdBy,
                    'assigned_user_id' => $quotation->sales_person_id,
                    'exclude_user_ids' => !empty($quotation->sales_person_id) ? [$quotation->sales_person_id] : [],
                ],
                actionUrl: $actionUrl,
                subjectModel: $quotation
            );
        } catch (\Throwable $e) {
            Log::warning("CRM Notification failed (crm.quotation.created): " . $e->getMessage());
        }
    }

    /**
     * 12. Trigger when a Quotation is approved.
     */
    public static function notifyQuotationApproved(Quotation $quotation): void
    {
        try {
            $customerName = $quotation->customer?->name ?: ($quotation->lead?->company_name ?: 'Customer');
            $amountFormatted = number_format((float) ($quotation->total_amount ?? 0), 2);
            $approvedBy = auth()->user()?->name ?: 'Manager';
            $actionUrl = route('crm.quotations.show', $quotation->id);

            if (!empty($quotation->sales_person_id)) {
                NotificationService::send(
                    user: $quotation->sales_person_id,
                    title: "Quotation Approved: {$quotation->quotation_number}",
                    message: "Quotation {$quotation->quotation_number} for {$customerName} (₹{$amountFormatted}) was approved by {$approvedBy}.",
                    actionUrl: $actionUrl,
                    module: 'crm',
                    type: 'quotation_approved',
                    iconClass: 'feather-check-circle'
                );
            }

            NotificationRuleService::trigger(
                eventKey: 'crm.quotation.approved',
                data: [
                    'quotation_no'  => $quotation->quotation_number,
                    'customer_name' => $customerName,
                    'amount'        => $amountFormatted,
                    'approved_by'   => $approvedBy,
                    'assigned_user_id' => $quotation->sales_person_id,
                    'exclude_user_ids' => !empty($quotation->sales_person_id) ? [$quotation->sales_person_id] : [],
                ],
                actionUrl: $actionUrl,
                subjectModel: $quotation
            );
        } catch (\Throwable $e) {
            Log::warning("CRM Notification failed (crm.quotation.approved): " . $e->getMessage());
        }
    }

    /**
     * 13. Trigger when a Quotation is rejected.
     */
    public static function notifyQuotationRejected(Quotation $quotation, ?string $reason = null): void
    {
        try {
            $customerName = $quotation->customer?->name ?: ($quotation->lead?->company_name ?: 'Customer');
            $rejectedBy = auth()->user()?->name ?: 'Manager';
            $actionUrl = route('crm.quotations.show', $quotation->id);

            if (!empty($quotation->sales_person_id)) {
                NotificationService::send(
                    user: $quotation->sales_person_id,
                    title: "Quotation Rejected: {$quotation->quotation_number}",
                    message: "Quotation {$quotation->quotation_number} for {$customerName} was rejected by {$rejectedBy}. Reason: " . ($reason ?: 'Needs Revision'),
                    actionUrl: $actionUrl,
                    module: 'crm',
                    type: 'quotation_rejected',
                    iconClass: 'feather-x-circle'
                );
            }

            NotificationRuleService::trigger(
                eventKey: 'crm.quotation.rejected',
                data: [
                    'quotation_no'  => $quotation->quotation_number,
                    'customer_name' => $customerName,
                    'rejected_by'   => $rejectedBy,
                    'reason'        => $reason ?: ($quotation->rejection_reason ?: 'Needs Revision'),
                    'assigned_user_id' => $quotation->sales_person_id,
                    'exclude_user_ids' => !empty($quotation->sales_person_id) ? [$quotation->sales_person_id] : [],
                ],
                actionUrl: $actionUrl,
                subjectModel: $quotation
            );
        } catch (\Throwable $e) {
            Log::warning("CRM Notification failed (crm.quotation.rejected): " . $e->getMessage());
        }
    }

    /**
     * 14. Trigger when a Quotation is accepted by customer.
     */
    public static function notifyQuotationAccepted(Quotation $quotation): void
    {
        try {
            $customerName = $quotation->customer?->name ?: ($quotation->lead?->company_name ?: 'Customer');
            $amountFormatted = number_format((float) ($quotation->total_amount ?? 0), 2);
            $acceptedBy = auth()->user()?->name ?: 'Customer';
            $actionUrl = route('crm.quotations.show', $quotation->id);

            if (!empty($quotation->sales_person_id)) {
                NotificationService::send(
                    user: $quotation->sales_person_id,
                    title: "🎉 Quotation Accepted: {$quotation->quotation_number}",
                    message: "Customer accepted Quotation {$quotation->quotation_number} for {$customerName} (₹{$amountFormatted}). Ready to proceed!",
                    actionUrl: $actionUrl,
                    module: 'crm',
                    type: 'quotation_accepted',
                    iconClass: 'feather-award'
                );
            }

            NotificationRuleService::trigger(
                eventKey: 'crm.quotation.accepted',
                data: [
                    'quotation_no'  => $quotation->quotation_number,
                    'customer_name' => $customerName,
                    'amount'        => $amountFormatted,
                    'accepted_by'   => $acceptedBy,
                    'assigned_user_id' => $quotation->sales_person_id,
                    'exclude_user_ids' => !empty($quotation->sales_person_id) ? [$quotation->sales_person_id] : [],
                ],
                actionUrl: $actionUrl,
                subjectModel: $quotation
            );
        } catch (\Throwable $e) {
            Log::warning("CRM Notification failed (crm.quotation.accepted): " . $e->getMessage());
        }
    }
}
