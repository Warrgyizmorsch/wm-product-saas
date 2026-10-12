<?php

namespace App\Services;

use App\Domains\CRM\Models\Lead;
use App\Domains\CRM\Models\LeadHistory;
use App\Models\MetaConfiguration;
use App\Models\MetaLeadLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaLeadService
{
    /**
     * Verify Meta Webhook Challenge (GET Request).
     */
    public function verifyWebhook(Request $request): ?string
    {
        $mode = $request->input('hub_mode') ?: $request->input('hub.mode');
        $token = $request->input('hub_verify_token') ?: $request->input('hub.verify_token');
        $challenge = $request->input('hub_challenge') ?: $request->input('hub.challenge');

        if ($mode === 'subscribe' && !empty($token)) {
            // Check if token matches any active configuration or default fallback
            $matches = MetaConfiguration::where('is_active', true)
                ->where('verify_token', $token)
                ->exists();

            if ($matches || $token === 'meta_erp_token' || $token === config('services.meta.verify_token')) {
                Log::info('[Meta Webhook] Verification successful for token: ' . $token);
                return (string) $challenge;
            }

            Log::warning('[Meta Webhook] Verification token mismatch: ' . $token);
        }

        return null;
    }

    /**
     * Process Meta Incoming Webhook Payload (POST Request).
     */
    public function processWebhook(Request $request): array
    {
        $payload = $request->all();
        Log::info('[Meta Webhook] Received event payload:', ['payload' => $payload]);

        $results = [];
        $entries = $payload['entry'] ?? [];

        foreach ($entries as $entry) {
            $pageId = (string) ($entry['id'] ?? '');
            $changes = $entry['changes'] ?? [];

            foreach ($changes as $change) {
                if (($change['field'] ?? '') !== 'leadgen') {
                    continue;
                }

                $value = $change['value'] ?? [];
                $leadgenId = (string) ($value['leadgen_id'] ?? '');
                $formId = (string) ($value['form_id'] ?? '');
                $adId = (string) ($value['ad_id'] ?? '');
                $adgroupId = (string) ($value['adgroup_id'] ?? '');
                $campaignId = (string) ($value['campaign_id'] ?? '');
                $createdTimestamp = $value['created_time'] ?? null;
                $leadCreatedTime = $createdTimestamp ? date('Y-m-d H:i:s', $createdTimestamp) : now();

                if (empty($leadgenId)) {
                    continue;
                }

                // Resolve matching Meta Configuration
                $config = MetaConfiguration::where('is_active', true)
                    ->where(function ($q) use ($pageId) {
                        if (!empty($pageId)) {
                            $q->where('page_id', $pageId)->orWhereNull('page_id');
                        }
                    })
                    ->first() ?: MetaConfiguration::where('is_active', true)->first();

                // Idempotency check: Don't ingest same leadgen_id twice
                $existingLog = MetaLeadLog::where('leadgen_id', $leadgenId)->first();
                if ($existingLog && $existingLog->status === 'processed') {
                    Log::info("[Meta Webhook] Leadgen ID {$leadgenId} already processed. Skipping duplicate.");
                    $results[] = [
                        'leadgen_id' => $leadgenId,
                        'status' => 'duplicate',
                        'crm_lead_id' => $existingLog->crm_lead_id,
                    ];
                    continue;
                }

                // Create initial log entry
                $log = $existingLog ?: new MetaLeadLog();
                $log->tenant_id = $config?->tenant_id ?? 1;
                $log->meta_configuration_id = $config?->id;
                $log->leadgen_id = $leadgenId;
                $log->page_id = $pageId;
                $log->form_id = $formId;
                $log->ad_id = $adId;
                $log->adgroup_id = $adgroupId;
                $log->campaign_id = $campaignId;
                $log->raw_payload = $payload;
                $log->lead_created_time = $leadCreatedTime;
                $log->status = 'pending';
                $log->save();

                try {
                    $ingestResult = $this->ingestLeadFromGraphApi($leadgenId, $config, $log);
                    $results[] = $ingestResult;
                } catch (\Throwable $e) {
                    Log::error("[Meta Webhook] Failed to ingest leadgen {$leadgenId}: " . $e->getMessage());
                    $log->update([
                        'status' => 'failed',
                        'error_message' => $e->getMessage(),
                    ]);
                    $results[] = [
                        'leadgen_id' => $leadgenId,
                        'status' => 'failed',
                        'error' => $e->getMessage(),
                    ];
                }
            }
        }

        return $results;
    }

    /**
     * Fetch full lead details from Graph API and insert into CRM Leads table.
     */
    public function ingestLeadFromGraphApi(string $leadgenId, ?MetaConfiguration $config, MetaLeadLog $log): array
    {
        $accessToken = $config?->access_token;

        if (empty($accessToken)) {
            throw new \Exception('No valid Meta Access Token configured in ERP Platform settings.');
        }

        // Call Meta Graph API to fetch lead details
        $response = Http::timeout(15)->get("https://graph.facebook.com/v20.0/{$leadgenId}", [
            'access_token' => $accessToken,
            'fields' => 'id,created_time,ad_id,ad_name,adset_id,adset_name,campaign_id,campaign_name,form_id,form_name,field_data',
        ]);

        if ($response->failed()) {
            $errorMsg = $response->json()['error']['message'] ?? 'Failed to fetch lead data from Meta Graph API.';
            throw new \Exception($errorMsg);
        }

        $leadData = $response->json();
        $fieldData = $leadData['field_data'] ?? [];

        // Parse Standard & Custom Fields
        $extracted = $this->parseFieldData($fieldData);
        $extracted['campaign_name'] = $leadData['campaign_name'] ?? null;
        $extracted['ad_name'] = $leadData['ad_name'] ?? null;
        $extracted['form_name'] = $leadData['form_name'] ?? null;

        $tenantId = $config?->tenant_id ?? 1;
        $companyId = $config?->company_id;
        $branchId = $config?->branch_id;

        // Build Requirement text from Custom Questions
        $requirementParts = [];
        if (!empty($extracted['requirement'])) {
            $requirementParts[] = $extracted['requirement'];
        }
        if (!empty($extracted['form_name'])) {
            $requirementParts[] = "Form: {$extracted['form_name']}";
        }
        if (!empty($extracted['custom_answers'])) {
            foreach ($extracted['custom_answers'] as $q => $ans) {
                $requirementParts[] = "{$q}: {$ans}";
            }
        }
        $finalRequirement = implode(" | ", $requirementParts);

        // Create CRM Lead record
        $metaCompanyName = trim((string)($extracted['company_name'] ?? ''));
        $lead = new Lead();
        $lead->tenant_id = $tenantId;
        $lead->company_id = $companyId;
        $lead->branch_id = $branchId;
        $lead->lead_owner_id = $config?->default_lead_owner_id;
        $lead->contact_person = $extracted['full_name'] ?: ($extracted['first_name'] . ' ' . $extracted['last_name'] ?: 'Meta Lead');
        $lead->phone = $extracted['phone_number'];
        $lead->company_phone = $extracted['phone_number'];
        $lead->email = $extracted['email'];
        $lead->company_email = $extracted['email'];
        $lead->company_name = $metaCompanyName ?: null;
        $lead->lead_type = !empty($metaCompanyName) ? 'b2b' : 'b2c';
        $lead->city = $extracted['city'];
        $lead->state = $extracted['state'];
        $lead->country = $extracted['country'] ?: 'India';
        $lead->address = $extracted['address'];
        $lead->requirement = $finalRequirement ?: 'Inquiry received via Meta (Facebook/Instagram) Lead Ads';
        $lead->source = $config?->default_source ?: 'Meta Ads';
        $lead->priority = $config?->default_priority ?: 'Medium';
        $lead->status = 'New';
        $lead->utm_source = 'meta';
        $lead->utm_medium = 'leadgen_ad';
        $lead->utm_campaign = $extracted['campaign_name'] ?: ($leadData['campaign_name'] ?? $log->campaign_id);
        $lead->utm_term = $leadData['adset_name'] ?? ($log->adgroup_id ?: null);
        $lead->utm_content = $extracted['ad_name'] ?: ($leadData['ad_name'] ?? $log->ad_id);
        $lead->call_date = now();
        $lead->save();

        // Create Lead History
        LeadHistory::create([
            'tenant_id' => $tenantId,
            'company_id' => $companyId,
            'branch_id' => $branchId,
            'lead_id' => $lead->id,
            'user_id' => $config?->default_lead_owner_id,
            'event_type' => 'meta_lead_captured',
            'notes' => "Lead automatically captured via Meta Lead Ads Webhook (Leadgen ID: {$leadgenId}, Form: " . ($extracted['form_name'] ?: $log->form_id) . ")",
        ]);

        // Update Log
        $log->update([
            'full_name' => $lead->contact_person,
            'phone_number' => $lead->phone,
            'email' => $lead->email,
            'extracted_data' => $extracted,
            'crm_lead_id' => $lead->id,
            'status' => 'processed',
            'error_message' => null,
        ]);

        Log::info("[Meta Webhook] Successfully created CRM Lead #{$lead->id} ({$lead->contact_person}) from leadgen {$leadgenId}");

        return [
            'leadgen_id' => $leadgenId,
            'status' => 'processed',
            'crm_lead_id' => $lead->id,
            'lead_number' => $lead->lead_number,
            'contact_person' => $lead->contact_person,
        ];
    }

    /**
     * Parse Meta field_data format into structured key-values.
     */
    protected function parseFieldData(array $fieldData): array
    {
        $result = [
            'full_name' => '',
            'first_name' => '',
            'last_name' => '',
            'phone_number' => '',
            'email' => '',
            'company_name' => '',
            'city' => '',
            'state' => '',
            'country' => '',
            'address' => '',
            'requirement' => '',
            'custom_answers' => [],
        ];

        foreach ($fieldData as $field) {
            $name = strtolower(trim($field['name'] ?? ''));
            $values = $field['values'] ?? [];
            $val = trim($values[0] ?? '');

            if (empty($val)) {
                continue;
            }

            if (in_array($name, ['full_name', 'name', 'your_name', 'contact_person'])) {
                $result['full_name'] = $val;
            } elseif (in_array($name, ['first_name', 'fname'])) {
                $result['first_name'] = $val;
            } elseif (in_array($name, ['last_name', 'lname'])) {
                $result['last_name'] = $val;
            } elseif (str_contains($name, 'phone') || in_array($name, ['phone_number', 'mobile', 'mobile_number', 'contact_number'])) {
                $result['phone_number'] = $val;
            } elseif (str_contains($name, 'email') || in_array($name, ['email_address', 'work_email'])) {
                $result['email'] = $val;
            } elseif (str_contains($name, 'company') || in_array($name, ['company_name', 'organization', 'business_name'])) {
                $result['company_name'] = $val;
            } elseif (in_array($name, ['city', 'town'])) {
                $result['city'] = $val;
            } elseif (in_array($name, ['state', 'province', 'region'])) {
                $result['state'] = $val;
            } elseif (in_array($name, ['country'])) {
                $result['country'] = $val;
            } elseif (in_array($name, ['street_address', 'address', 'address_line_1'])) {
                $result['address'] = $val;
            } elseif (str_contains($name, 'require') || str_contains($name, 'message') || str_contains($name, 'inquiry') || str_contains($name, 'budget')) {
                $result['requirement'] = ($result['requirement'] ? $result['requirement'] . ' | ' : '') . $val;
            } else {
                $result['custom_answers'][$field['name']] = $val;
            }
        }

        if (empty($result['full_name']) && (!empty($result['first_name']) || !empty($result['last_name']))) {
            $result['full_name'] = trim($result['first_name'] . ' ' . $result['last_name']);
        }

        return $result;
    }
}
