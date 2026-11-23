<?php

namespace App\Domains\CRM\Services;

use App\Domains\CRM\Models\Lead;
use App\Domains\CRM\Models\LeadHistory;
use App\Domains\Platform\Services\NotificationRuleService;
use App\Models\Tenant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * FRS 2.5 (C-05) / F04 LEAD-10: acknowledgement of a genuine inbound enquiry.
 *
 * Rules enforced here:
 *  - only inbound enquiries are acknowledged; researched / imported / manual prospects never are;
 *  - only when the tenant has switched the acknowledgement on (crm_lead_policy.auto_ack_inbound);
 *  - at most once per lead;
 *  - `acknowledgement_sent_at` is stamped only when a channel actually accepted the message.
 */
class LeadAcknowledgementService
{
    private const BLOCKED_PROVENANCE = ['researched', 'research', 'ai_research', 'api_import', 'import', 'manual'];

    public function shouldAcknowledge(Lead $lead, array $tenantSettings): bool
    {
        if (!$lead->is_inbound_enquiry || !empty($lead->acknowledgement_sent_at)) {
            return false;
        }
        // communication permission: a prospect who opted out / withheld consent is never messaged
        if (!empty($lead->communication_opt_out)) {
            return false;
        }
        if (in_array(strtolower((string) $lead->provenance), self::BLOCKED_PROVENANCE, true)
            || strcasecmp((string) $lead->source, 'research') === 0) {
            return false;
        }
        $policy = $tenantSettings['crm_lead_policy'] ?? [];

        return !empty($policy['auto_ack_inbound']) || !empty($tenantSettings['auto_ack_whatsapp']);
    }

    /**
     * @return array{sent: bool, channels: string[]}
     */
    public function acknowledge(Lead $lead, Tenant $tenant): array
    {
        $settings = is_array($tenant->settings) ? $tenant->settings : [];
        if (!$this->shouldAcknowledge($lead, $settings)) {
            return ['sent' => false, 'channels' => []];
        }

        $ownerName = $lead->fresh()?->owner?->name ?: 'our sales executive';
        $recipient = $lead->contact_person ?: 'Valued Customer';
        $brand = $tenant->name ?: 'our team';
        $policy = $settings['crm_lead_policy'] ?? [];
        $template = trim((string) ($policy['ack_template'] ?? ''));
        if ($template === '') {
            $template = 'Hi {name}, thank you for contacting {brand}! We have received your enquiry. {owner} will connect with you shortly.';
        }
        $text = strtr($template, [
            '{name}' => $recipient,
            '{brand}' => $brand,
            '{owner}' => $ownerName,
            '{company}' => (string) $lead->company_name,
        ]);

        $channels = [];

        if (!empty($lead->phone)) {
            try {
                $res = app(\App\Services\WhatsAppService::class)->sendMessage($lead->phone, $text);
                if (!empty($res['success'])) {
                    $channels[] = 'whatsapp';
                }
            } catch (\Throwable $e) {
                Log::warning('Lead acknowledgement WhatsApp failed: ' . $e->getMessage());
            }
        }

        if (!empty($lead->email) && filter_var($lead->email, FILTER_VALIDATE_EMAIL)) {
            try {
                $subject = trim((string) ($policy['ack_subject'] ?? '')) ?: "We received your enquiry - {$brand}";
                Mail::raw($text, function ($m) use ($lead, $subject) {
                    $m->to($lead->email)->subject($subject);
                });
                $channels[] = 'email';
            } catch (\Throwable $e) {
                Log::warning('Lead acknowledgement email failed: ' . $e->getMessage());
            }
        }

        // Internal staff notice (kept separate from the customer acknowledgement).
        try {
            NotificationRuleService::trigger(
                eventKey: 'crm.lead.inbound_acknowledged',
                data: [
                    'lead_id' => $lead->id,
                    'contact_person' => $recipient,
                    'company_name' => $lead->company_name ?: '',
                    'requirement' => $lead->requirement ?: '',
                    'owner_name' => $ownerName,
                    'channels' => implode(', ', $channels) ?: 'none',
                ],
                actionUrl: route('crm.leads.show', $lead->id),
                subjectModel: $lead
            );
        } catch (\Throwable $e) {
            Log::warning('Lead acknowledgement staff notice failed: ' . $e->getMessage());
        }

        if (!empty($channels)) {
            $lead->acknowledgement_sent_at = now();
            $lead->save();
            LeadHistory::logEvent($lead, 'acknowledged', null, implode(', ', $channels), 'Inbound enquiry acknowledged to the prospect');
        } else {
            LeadHistory::logEvent($lead, 'acknowledgement_failed', null, null, 'Acknowledgement could not be delivered on any channel; owner should respond manually.');
        }

        return ['sent' => !empty($channels), 'channels' => $channels];
    }
}
