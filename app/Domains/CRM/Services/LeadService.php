<?php

namespace App\Domains\CRM\Services;

use App\Domains\CRM\Models\Lead;
use App\Domains\CRM\Models\Customer;
use App\Domains\CRM\Models\LeadDocument;
use App\Domains\CRM\Models\LeadHistory;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class LeadService
{
    /**
     * Process Call Date & Time.
     */
    public function parseCallDateTime(array $validated): Carbon
    {
        if (isset($validated['call_date'])) {
            try {
                return Carbon::parse($validated['call_date']);
            } catch (\Exception $e) {
                return Carbon::now();
            }
        }

        try {
            $hour = intval($validated['call_date_hour'] ?? 0);
            if (($validated['call_date_ampm'] ?? '') === 'PM' && $hour < 12) {
                $hour += 12;
            } elseif (($validated['call_date_ampm'] ?? '') === 'AM' && $hour === 12) {
                $hour = 0;
            }

            $timeString = sprintf('%02d:%02d:00', $hour, intval($validated['call_date_minute'] ?? 0));
            return Carbon::parse(($validated['call_date_date'] ?? Carbon::now()->toDateString()) . ' ' . $timeString);
        } catch (\Exception $e) {
            return Carbon::now();
        }
    }

    /**
     * Process items array into product_items and product_ids.
     */
    public function processItems(array $rawItems, array $rawProductIds = []): array
    {
        $cleanItems = [];
        $cleanProductIds = [];

        foreach ($rawItems as $item) {
            $pId = !empty($item['product_id']) && $item['product_id'] !== '__ADD_NEW__' ? intval($item['product_id']) : null;
            $qty = !empty($item['quantity']) ? floatval($item['quantity']) : 1.0;
            if ($pId) {
                $cleanItems[] = [
                    'product_id' => $pId,
                    'quantity' => $qty > 0 ? $qty : 1.0,
                ];
                if (!in_array($pId, $cleanProductIds)) {
                    $cleanProductIds[] = $pId;
                }
            }
        }

        if (empty($cleanItems)) {
            foreach ($rawProductIds as $pId) {
                $intId = intval($pId);
                if ($intId) {
                    $cleanItems[] = ['product_id' => $intId, 'quantity' => 1.0];
                    $cleanProductIds[] = $intId;
                }
            }
        }

        return [
            'product_items' => $cleanItems,
            'product_ids' => $cleanProductIds,
        ];
    }

    public function cleanAdditionalContacts(?array $rawContacts): array
    {
        if (empty($rawContacts) || !is_array($rawContacts)) {
            return [];
        }

        $clean = [];
        foreach ($rawContacts as $c) {
            $name = trim($c['name'] ?? '');
            $designation = trim($c['designation'] ?? '');
            $email = trim($c['email'] ?? '');
            $phone = trim($c['phone'] ?? '');

            if ($name !== '' || $email !== '' || $phone !== '' || $designation !== '') {
                $clean[] = [
                    'name' => $name,
                    'designation' => $designation,
                    'email' => $email,
                    'phone' => $phone,
                ];
            }
        }
        return $clean;
    }

    /**
     * Store new lead and log events.
     */
    public function storeLead(array $validated, array $rawItems, array $rawProductIds): Lead
    {
        $callDateTime = $this->parseCallDateTime($validated);
        $processed = $this->processItems($rawItems, $rawProductIds);
        $additionalContacts = $this->cleanAdditionalContacts($validated['additional_contacts'] ?? []);
        $firstAddl = $additionalContacts[0] ?? null;

        $explicitOwner = !empty($validated['lead_owner_id']) ? (int)$validated['lead_owner_id'] : null;

        $leadData = [
            'tenant_id' => $validated['tenant_id'] ?? (function_exists('tenant_id') && tenant_id() ? tenant_id() : (auth()->user()?->tenant_id ?? 1)),
            'call_date' => $callDateTime,
            'lead_owner_id' => $explicitOwner,
            'company_name' => !empty($validated['company_name']) ? $validated['company_name'] : (!empty($validated['contact_person']) ? $validated['contact_person'] : 'Individual Customer'),
            'company_email' => $validated['company_email'] ?? null,
            'company_phone' => $validated['company_phone'] ?? null,
            'gstin' => $validated['gstin'] ?? null,
            'lead_type' => $validated['lead_type'] ?? 'b2b',
            'contact_person' => $validated['contact_person'] ?? null,
            'designation' => $validated['designation'] ?? null,
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'additional_contacts' => $additionalContacts,
            'requirement' => $validated['requirement'] ?? null,
            'expected_amount' => !empty($validated['expected_amount']) ? convert_to_base((float) $validated['expected_amount']) : 0.00,
            'expected_sale_date' => !empty($validated['expected_sale_date']) ? Carbon::parse($validated['expected_sale_date']) : null,
            'source' => $validated['source'] ?? 'Select an Option',
            'priority' => $validated['priority'] ?? 'Select an Option',
            'segment' => $validated['segment'] ?? 'Select an Option',
            'industry_type' => $validated['industry_type'] ?? null,
            'provenance' => $validated['provenance'] ?? 'manual',
            'is_inbound_enquiry' => false,
            'project_type' => $validated['project_type'] ?? null,
            'approx_quantity' => isset($validated['approx_quantity']) && $validated['approx_quantity'] !== '' ? (int)$validated['approx_quantity'] : null,
            'decision_timeline' => $validated['decision_timeline'] ?? null,
            'competitor_name' => $validated['competitor_name'] ?? null,
            'decision_maker_identified' => !empty($validated['decision_maker_identified']),
            'country' => $validated['country'] ?? null,
            'state' => $validated['state'] ?? null,
            'city' => $validated['city'] ?? null,
            'address' => $validated['address'] ?? null,
            'product_ids' => $processed['product_ids'],
            'product_items' => $processed['product_items'],
        ];

        $lead = Lead::create($leadData);

        LeadHistory::logEvent(
            $lead,
            'created',
            null,
            $lead->company_name,
            'Lead created with initial stage: ' . ($lead->status ?: 'New')
        );

        if (!$explicitOwner) {
            // Auto-assign based on CRM settings if no explicit owner chosen
            try {
                app(\App\Domains\CRM\Services\LeadAssignmentService::class)->assignLeadOwner($lead, 'manual_create', $validated);
            } catch (\Throwable $e) {
                if (auth()->id()) {
                    $lead->lead_owner_id = auth()->id();
                    $lead->save();
                    self::notifyLeadAssigned($lead, $lead->lead_owner_id, auth()->id());
                }
            }
        } elseif ($lead->lead_owner_id) {
            $ownerName = User::find($lead->lead_owner_id)?->name ?: 'Unknown';
            LeadHistory::logEvent(
                $lead,
                'assigned',
                null,
                $ownerName,
                'Lead assigned to lead owner: ' . $ownerName
            );
            self::notifyLeadAssigned($lead, $lead->lead_owner_id, auth()->id());
        }

        // FRS 2.5: every new open lead gets a dated next action for its owner.
        app(LeadNextActionService::class)->ensureNextAction($lead->fresh());

        // Notify sales management / executive of new lead
        CrmNotificationService::notifyLeadCreated($lead);

        return $lead;
    }

    /**
     * Update existing lead and log events.
     */
    public function updateLead(Lead $lead, array $validated, array $rawItems, array $rawProductIds): bool
    {
        $callDateTime = $this->parseCallDateTime($validated);
        $processed = $this->processItems($rawItems, $rawProductIds);
        $additionalContacts = $this->cleanAdditionalContacts($validated['additional_contacts'] ?? []);
        $firstAddl = $additionalContacts[0] ?? null;

        $leadData = [
            'call_date' => $callDateTime,
            'lead_owner_id' => $validated['lead_owner_id'] ?? null,
            'company_name' => !empty($validated['company_name']) ? $validated['company_name'] : (!empty($validated['contact_person']) ? $validated['contact_person'] : 'Individual Customer'),
            'company_email' => $validated['company_email'] ?? null,
            'company_phone' => $validated['company_phone'] ?? null,
            'gstin' => $validated['gstin'] ?? null,
            'lead_type' => $validated['lead_type'] ?? 'b2b',
            'contact_person' => $validated['contact_person'] ?? null,
            'designation' => $validated['designation'] ?? null,
            'email' => $validated['email'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'additional_contacts' => $additionalContacts,
            'requirement' => $validated['requirement'] ?? null,
            'expected_amount' => !empty($validated['expected_amount']) ? convert_to_base((float) $validated['expected_amount']) : 0.00,
            'expected_sale_date' => !empty($validated['expected_sale_date']) ? Carbon::parse($validated['expected_sale_date']) : null,
            'source' => $validated['source'] ?? 'Select an Option',
            'priority' => $validated['priority'] ?? 'Select an Option',
            'segment' => $validated['segment'] ?? 'Select an Option',
            'industry_type' => $validated['industry_type'] ?? null,
            'country' => $validated['country'] ?? null,
            'state' => $validated['state'] ?? null,
            'city' => $validated['city'] ?? null,
            'address' => $validated['address'] ?? null,
            'product_ids' => $processed['product_ids'],
            'product_items' => $processed['product_items'],
        ];

        // FRS 2.5 / C-03 qualification fields: only overwrite what the form actually sent,
        // so an edit from a screen without these inputs never wipes qualification data.
        foreach (['project_type', 'decision_timeline', 'competitor_name'] as $qualField) {
            if (array_key_exists($qualField, $validated)) {
                $leadData[$qualField] = $validated[$qualField] !== '' ? $validated[$qualField] : null;
            }
        }
        if (array_key_exists('approx_quantity', $validated)) {
            $leadData['approx_quantity'] = $validated['approx_quantity'] !== null && $validated['approx_quantity'] !== '' ? (int) $validated['approx_quantity'] : null;
        }
        if (array_key_exists('communication_opt_out', $validated)) {
            $optOut = !empty($validated['communication_opt_out']);
            $leadData['communication_opt_out'] = $optOut;
            $leadData['communication_opt_out_at'] = $optOut ? ($lead->communication_opt_out_at ?: now()) : null;
        }
        if (array_key_exists('decision_maker_identified', $validated)) {
            $leadData['decision_maker_identified'] = !empty($validated['decision_maker_identified']);
        }

        // FRS 2.5: expected value / expected date / owner changes keep a history trail.
        $oldExpectedAmount = (float) $lead->expected_amount;
        $oldExpectedDate = $lead->expected_sale_date?->toDateString();

        $oldOwnerId = $lead->lead_owner_id;
        $updated = $lead->update($leadData);

        $lead->refresh();
        $newExpectedAmount = (float) $lead->expected_amount;
        $newExpectedDate = $lead->expected_sale_date?->toDateString();
        if (abs($oldExpectedAmount - $newExpectedAmount) > 0.004) {
            LeadHistory::logEvent($lead, 'expected_value_changed', number_format($oldExpectedAmount, 2, '.', ''), number_format($newExpectedAmount, 2, '.', ''), 'Expected value changed');
        }
        if ($oldExpectedDate !== $newExpectedDate) {
            LeadHistory::logEvent($lead, 'expected_date_changed', $oldExpectedDate ?: 'None', $newExpectedDate ?: 'None', 'Expected sale date changed');
        }

        if ($oldOwnerId != $lead->lead_owner_id) {
            if (!empty($lead->lead_owner_id)) {
                $lead->assigned_at = now();
                $lead->first_responded_at = null;
                $lead->response_sla_breached = false;
                $lead->sla_escalated_at = null;
                $lead->save();
            }

            $oldOwnerName = $oldOwnerId ? (User::find($oldOwnerId)?->name ?: 'Unknown') : 'None';
            $newOwnerName = $lead->lead_owner_id ? (User::find($lead->lead_owner_id)?->name ?: 'Unknown') : 'None';
            LeadHistory::logEvent(
                $lead,
                'assigned',
                $oldOwnerName,
                $newOwnerName,
                "Lead owner updated from {$oldOwnerName} to {$newOwnerName}"
            );
            if (!empty($lead->lead_owner_id)) {
                self::notifyLeadAssigned($lead, $lead->lead_owner_id, auth()->id());
            }
        }

        return $updated;
    }

    /**
     * FRS 2.5 / C-04: reopening or reassigning a lead needs an explicit permission.
     * Granted by the dedicated permission (crm.leads.reopen / crm.leads.reassign) or
     * by tenant-wide lead update rights (managers/admins). Record-owner (own-scope)
     * rights are deliberately not enough. Everything goes through AccessService.
     */
    public static function userMayManageLeadOwnership(User $user, Lead $lead, string $action): bool
    {
        $access = app(\App\Services\Access\AccessService::class);
        $context = ['tenant_id' => $lead->tenant_id ?? $user->tenant_id];

        return $access->allows($user, 'crm.leads.' . $action, $context)
            || $access->allows($user, 'crm.leads.update', $context);
    }

    /**
     * Update lead status and handle customer conversion logic.
     */
    public function updateLeadStatus(Lead $lead, string $newStatus, bool $isSystemTriggered = false, array $extraData = []): array
    {
        $oldStatus = $lead->status ?: 'New';

        // Permanent Lock Check: Once a lead is Won, it can NEVER be changed to any other status manually
        if (($oldStatus === 'Won' || $lead->is_customer) && $newStatus !== 'Won' && !$isSystemTriggered) {
            return [
                'success' => false,
                'message' => 'Lead status is Won and permanently locked. Status cannot be changed.'
            ];
        }

        // Lock Check: Once a lead is in Dealing, it cannot be changed to another status manually
        // (FRS 2.5: a deal that is going nowhere can still be closed as Lost with a structured reason.)
        if (strcasecmp($oldStatus, 'Dealing') === 0 && strcasecmp($newStatus, 'Dealing') !== 0 && strcasecmp($newStatus, 'Lost') !== 0 && !$isSystemTriggered) {
            return [
                'success' => false,
                'message' => "Lead status in '{$oldStatus}' stage is locked. Status cannot be changed."
            ];
        }

        if ($oldStatus === $newStatus) {
            return ['success' => true, 'message' => "Lead status is already {$newStatus}."];
        }

        // Direct Won transition guard: manual transition to Won is strictly blocked!
        if (strcasecmp($newStatus, 'Won') === 0 && !$isSystemTriggered) {
            return [
                'success' => false,
                'message' => 'Lead cannot be marked as Won directly. It is automatically marked as Won when Quotation is accepted in Deal.'
            ];
        }

        // Reopen guard (FRS 2.5 / C-04): Reopening a Lost lead requires manager permission
        if (strcasecmp($oldStatus, 'Lost') === 0 && strcasecmp($newStatus, 'Lost') !== 0 && !$isSystemTriggered) {
            $user = auth()->user();
            if (!$user || !self::userMayManageLeadOwnership($user, $lead, 'reopen')) {
                return [
                    'success' => false,
                    'message' => 'Unauthorized: You do not have permission to reopen a Lost lead.'
                ];
            }
        }

        // Stage Gate Validation (FRS 2.5 / C-03 & BASE-05)
        if (!$isSystemTriggered && strcasecmp($newStatus, 'Lost') !== 0) {
            $gateResult = app(LeadStageGateService::class)->validateStageProgression($lead, $newStatus);
            if (!$gateResult['allowed']) {
                return [
                    'success' => false,
                    'message' => $gateResult['message'],
                    'missing_fields' => $gateResult['missing_fields'] ?? [],
                ];
            }
        }

        $updateData = ['status' => $newStatus];
        $message = "Lead status updated to {$newStatus}.";

        if (strcasecmp($newStatus, 'Lost') === 0) {
            if ($lead->is_customer) {
                $updateData['is_customer'] = false;
                $customer = $lead->getCustomer();
                if ($customer) {
                    $customer->update(['status' => 'inactive']);
                }
            }
            $updateData['lost_at'] = now();
            if (!empty($extraData['loss_reason_id'])) {
                $updateData['loss_reason_id'] = (int)$extraData['loss_reason_id'];
            }
            if (!empty($extraData['lost_to_competitor'])) {
                $updateData['lost_to_competitor'] = trim((string)$extraData['lost_to_competitor']);
            }
            if (!empty($extraData['loss_remarks'])) {
                $updateData['loss_remarks'] = trim((string)$extraData['loss_remarks']);
            }
            if (!empty($extraData['nurture_followup_date'])) {
                $updateData['nurture_followup_date'] = $extraData['nurture_followup_date'];
            } else {
                $lossReason = !empty($updateData['loss_reason_id']) ? \App\Domains\CRM\Models\CrmLossReason::find($updateData['loss_reason_id']) : null;
                if ($lossReason && !empty($lossReason->follow_up_days)) {
                    $nurtureDays = (int) $lossReason->follow_up_days;
                } else {
                    $tenant = \App\Models\Tenant::find($lead->tenant_id ?? 1);
                    $settings = is_array($tenant?->settings) ? $tenant->settings : [];
                    $nurtureDays = (int)($settings['crm_lead_policy']['default_nurture_days'] ?? 30);
                }
                $updateData['nurture_followup_date'] = now()->addDays($nurtureDays)->toDateString();
            }
            $message = 'Lead status updated to Lost.';
        } else {
            if (strcasecmp($oldStatus, 'Lost') === 0) {
                $updateData['lost_at'] = null;
            }

            if ($newStatus === 'Dealing') {
                if (!$lead->crm_deal_id) {
                    app(\App\Domains\CRM\Repositories\LeadRepository::class)->qualifyLead($lead);
                }
                $message = 'Lead status updated to Dealing.';
            } elseif (strcasecmp($newStatus, 'Won') === 0) {
                if (!$lead->crm_account_id) {
                    $acc = \App\Domains\CRM\Models\CrmAccount::where('tenant_id', $lead->tenant_id ?? 1)
                        ->where(function($q) use ($lead) {
                            if (!empty($lead->email)) $q->where('email', $lead->email);
                            if (!empty($lead->company_name)) $q->orWhere('name', $lead->company_name);
                        })->first();
                    if (!$acc) {
                        $acc = \App\Domains\CRM\Models\CrmAccount::create([
                            'tenant_id' => $lead->tenant_id ?? 1,
                            'name'      => $lead->company_name ?: ($lead->contact_person ?: 'Customer Account'),
                            'email'     => $lead->email,
                            'phone'     => $lead->phone,
                            'status'    => 'active',
                            'owner_id'  => $lead->lead_owner_id ?: 1,
                        ]);
                    }
                    $updateData['crm_account_id'] = $acc->id;
                    $lead->crm_account_id = $acc->id;
                }
                $updateData['is_customer'] = true;
                $customer = $lead->getCustomer();
                if ($customer) {
                    $customer->update(['status' => 'active']);
                }
                $message = 'Lead marked as Won.';
            }
        }

        $oldStatus = $lead->status ?: 'New';
        Lead::$allowWonTransition = true;
        try {
            $lead->update($updateData);
        } finally {
            Lead::$allowWonTransition = false;
        }

        if ($oldStatus !== $lead->status) {
            LeadHistory::logEvent(
                $lead,
                'status_changed',
                $oldStatus,
                $lead->status,
                "Lead stage status updated from '{$oldStatus}' to '{$lead->status}'"
            );

            // FRS 2.5 / C-04: Auto-schedule future nurture follow-up task upon deal loss
            if (strcasecmp($lead->status, 'Lost') === 0 && !empty($lead->nurture_followup_date)) {
                try {
                    $reasonName = $lead->lossReason?->name ?: ($lead->lost_to_competitor ?: 'Deal Closed Lost');
                    app(\App\Domains\CRM\Repositories\LeadFollowupRepository::class)->create([
                        'tenant_id' => $lead->tenant_id ?? (tenant_id() ?? 1),
                        'company_id' => $lead->company_id,
                        'branch_id' => $lead->branch_id,
                        'lead_id' => $lead->id,
                        'assigned_to_id' => $lead->lead_owner_id ?: (auth()->id() ?: 1),
                        'followup_date' => Carbon::parse($lead->nurture_followup_date)->setTime(10, 0),
                        'type' => 'Call',
                        'title' => 'Nurture Outreach: ' . ($lead->company_name ?: $lead->contact_person),
                        'status' => 'Pending',
                        'notes' => 'Automated nurture reminder after loss. Reason: ' . $reasonName . ($lead->lost_to_competitor ? " | Competitor: {$lead->lost_to_competitor}" : '') . ($lead->loss_remarks ? " | Notes: {$lead->loss_remarks}" : ''),
                    ]);
                    app(\App\Domains\CRM\Repositories\LeadFollowupRepository::class)->syncLeadNextFollowupDate($lead);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('Failed to schedule nurture follow-up: ' . $e->getMessage());
                }
            }

            // FRS 2.5: losing a lead that already became a deal closes that deal with the same reason.
            if (strcasecmp($lead->status, 'Lost') === 0 && $lead->crm_deal_id) {
                try {
                    $deal = \App\Domains\CRM\Models\CrmDeal::find($lead->crm_deal_id);
                    if ($deal && !in_array(strtolower((string) $deal->stage), ['lost', 'closed lost', 'won', 'closed won'], true)) {
                        $deal->update([
                            'stage' => 'Closed Lost',
                            'close_reason' => $lead->lossReason?->name ?: ($lead->loss_remarks ?: 'Lost'),
                        ]);
                    }
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('Failed to close linked deal on lead loss: ' . $e->getMessage());
                }
            }

            CrmNotificationService::notifyLeadStatusUpdated($lead, $oldStatus, $lead->status);
        }

        return ['success' => true, 'message' => $message];
    }

    /**
     * Delete lead and its associated document files.
     */
    public function deleteLead(Lead $lead): bool
    {
        $lead->leadDocuments->each(function ($document) {
            Storage::disk('public')->delete($document->file_path);
            $document->delete();
        });

        return $lead->delete();
    }

    /**
     * Handle multi-file document uploads for a lead.
     */
    public function uploadDocuments(Lead $lead, array $files): void
    {
        $uploadedIds = $lead->documents ?? [];

        foreach ($files as $file) {
            $path = $file->storeTenantFile('lead_documents/' . $lead->id, 'public');

            $document = LeadDocument::create([
                'tenant_id' => $lead->tenant_id,
                'lead_id' => $lead->id,
                'file_name' => $file->getClientOriginalName(),
                'file_type' => $file->getClientOriginalExtension(),
                'file_path' => $path,
                'size' => $file->getSize(),
            ]);

            $uploadedIds[] = $document->id;
        }

        $lead->documents = array_values(array_filter($uploadedIds));
        $lead->save();
    }

    /**
     * Delete a single document.
     */
    public function deleteDocument(LeadDocument $document): void
    {
        $lead = $document->lead;
        $document->delete();
        Storage::disk('public')->delete($document->file_path);

        if ($lead) {
            $lead->documents = array_values(array_filter(array_diff($lead->documents ?? [], [$document->id])));
            $lead->save();
        }
    }

    /**
     * Dispatch in-app bell notification and real-time Pusher alert to assigned lead owner.
     */
    public static function notifyLeadAssigned(Lead $lead, ?int $assignedToUserId, ?int $assignedByUserId = null): void
    {
        if (!$assignedToUserId) {
            return;
        }

        $assignedUser = User::find($assignedToUserId);
        if (!$assignedUser) {
            return;
        }

        $assignedBy = $assignedByUserId ? User::find($assignedByUserId) : (auth()->user() ?: null);
        $assignerName = $assignedBy ? $assignedBy->name : 'System';
        $leadTitle = $lead->company_name ?: ($lead->contact_person ?: $lead->lead_number ?: 'Lead');

        $title = "New Lead Assigned: {$leadTitle}";
        $message = "Lead '{$leadTitle}'" . ($lead->source ? " (Source: {$lead->source})" : '') . " has been assigned to you by {$assignerName}.";
        
        $actionUrl = null;
        try {
            $actionUrl = route('crm.leads.show', $lead->id);
        } catch (\Throwable $e) {
            $actionUrl = "/crm/leads/{$lead->id}";
        }

        // 1. Send In-App Notification (Bell Icon DB record) and Pusher WebSocket broadcast
        try {
            \App\Services\Notification\NotificationService::send(
                user: $assignedUser,
                title: $title,
                message: $message,
                actionUrl: $actionUrl,
                module: 'crm',
                type: 'lead_assigned',
                iconClass: 'feather-user-check',
                extraData: [
                    'lead_id' => $lead->id,
                    'lead_number' => $lead->lead_number,
                    'company_name' => $lead->company_name,
                    'contact_person' => $lead->contact_person,
                    'assigned_by' => $assignerName,
                    'tenant_id' => $lead->tenant_id ?? (function_exists('tenant_id') ? tenant_id() : 1),
                ]
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("LeadService notifyLeadAssigned NotificationService error: " . $e->getMessage());
        }

        // 2. Also trigger configurable NotificationRuleService event if rules are configured in Notification Master
        try {
            \App\Domains\Platform\Services\NotificationRuleService::trigger('crm.lead.assigned', [
                'company_name' => $lead->company_name ?? 'Lead',
                'contact_person' => $lead->contact_person ?? 'N/A',
                'source' => $lead->source ?? 'Direct',
                'expected_amount' => function_exists('format_currency') ? format_currency($lead->expected_amount ?? 0) : (string)($lead->expected_amount ?? 0),
                'lead_number' => $lead->lead_number ?? '',
                'assigned_to' => $assignedUser->name,
                'assigned_user_id' => $assignedUser->id,
                'exclude_user_ids' => [$assignedUser->id],
                'assigned_by' => $assignerName,
                'created_by' => $assignerName,
            ], $actionUrl, $lead);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("NotificationRuleService trigger crm.lead.assigned failed: " . $e->getMessage());
        }
    }
}
