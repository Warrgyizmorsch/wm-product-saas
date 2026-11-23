<?php

namespace App\Domains\CRM\Services;

use App\Domains\CRM\Models\Lead;

class LeadStageGateService
{
    /**
     * Validate whether a lead meets all mandatory qualification criteria (BANT)
     * and activity prerequisites before advancing to the target stage.
     *
     * FRS 2.5 / C-03 & Acceptance Test BASE-05.
     *
     * @param Lead $lead
     * @param string $targetStage
     * @return array ['allowed' => bool, 'missing_fields' => array, 'message' => string]
     */
    public function validateStageProgression(Lead $lead, string $targetStage): array
    {
        $normalizedTarget = strtolower(trim($targetStage));
        $missing = [];

        // Check Tenant Settings: Gating can be soft or strict per tenant configuration
        $tenantId = $lead->tenant_id ?? (tenant_id() ?? 1);
        $tenant = \App\Models\Tenant::find($tenantId);
        $settings = is_array($tenant?->settings) ? $tenant->settings : [];
        $leadPolicy = $settings['crm_lead_policy'] ?? [];
        $enforceGating = $leadPolicy['enforce_stage_gating'] ?? true;

        if (!$enforceGating) {
            return ['allowed' => true, 'missing_fields' => [], 'message' => 'Stage gating bypassed by tenant policy.'];
        }

        // 1. Moving to 'Qualified': Basic Contact & Need Verification
        if (in_array($normalizedTarget, ['qualified', 'dealing', 'won', 'proposal', 'negotiation'])) {
            if (empty(trim((string)$lead->contact_person))) {
                $missing['contact_person'] = 'Contact Person / Name';
            }
            if (empty(trim((string)$lead->phone)) && empty(trim((string)$lead->email))) {
                $missing['contact_route'] = 'Valid Phone or Email';
            }
            if (empty(trim((string)$lead->city)) && empty(trim((string)$lead->state)) && empty(trim((string)$lead->address))) {
                $missing['location'] = 'Location (City, State, or Address)';
            }
        }

        // 2. Moving to 'Dealing' / 'Proposal' / 'Negotiation': Strict BANT & Activity Gate
        if (in_array($normalizedTarget, ['dealing', 'won', 'proposal', 'negotiation'])) {
            // Company and contact role (FRS 2.5 step 4) - business leads only
            if (strtolower((string) $lead->lead_type) !== 'b2c') {
                if (empty(trim((string) $lead->company_name)) || strcasecmp(trim((string) $lead->company_name), 'Individual Customer') === 0) {
                    $missing['company_name'] = 'Company Name';
                }
                if (empty(trim((string) $lead->designation))) {
                    $missing['contact_role'] = 'Contact Role / Designation';
                }
            }

            // Decision process: who decides has been identified
            if (empty($lead->decision_maker_identified)) {
                $missing['decision_process'] = 'Decision maker / decision process identified';
            }

            // Project Type / Need
            if (empty(trim((string)$lead->project_type)) && empty(trim((string)$lead->requirement))) {
                $missing['project_type'] = 'Project Type or Product Requirement';
            }

            // Approximate Quantity
            $hasQty = (!empty($lead->approx_quantity) && $lead->approx_quantity > 0);
            if (!$hasQty && !empty($lead->product_items) && is_array($lead->product_items)) {
                foreach ($lead->product_items as $item) {
                    if (!empty($item['quantity']) && (float)$item['quantity'] > 0) {
                        $hasQty = true;
                        break;
                    }
                }
            }
            if (!$hasQty) {
                $missing['approx_quantity'] = 'Approximate Quantity (units/workstations)';
            }

            // Budget / Expected Amount
            if (empty($lead->expected_amount) || (float)$lead->expected_amount <= 0) {
                $missing['expected_amount'] = 'Budget / Expected Order Amount';
            }

            // Decision Timeline / Expected Date
            if (empty($lead->expected_sale_date) && empty(trim((string)$lead->decision_timeline))) {
                $missing['decision_timeline'] = 'Decision Timeline or Expected Close Date';
            }

            // Activity Gate: Must have at least 1 completed interaction logged (Call/Meeting/Visit)
            $completedInteractionsCount = $lead->followups()
                ->where('status', '!=', 'Pending')
                ->count();

            if ($completedInteractionsCount === 0 && empty($lead->first_responded_at)) {
                $missing['completed_activity'] = 'At least 1 completed sales interaction logged (Call, Visit, or Meeting)';
            }
        }

        if (!empty($missing)) {
            $missingList = implode(', ', array_values($missing));
            return [
                'allowed' => false,
                'missing_fields' => $missing,
                'message' => "Stage Gate Blocked: Cannot advance to '{$targetStage}'. Required qualification missing: {$missingList}.",
            ];
        }

        return [
            'allowed' => true,
            'missing_fields' => [],
            'message' => 'Qualification stage gate satisfied.',
        ];
    }
}
