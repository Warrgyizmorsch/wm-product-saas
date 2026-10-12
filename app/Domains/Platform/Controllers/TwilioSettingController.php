<?php

namespace App\Domains\Platform\Controllers;

use App\Http\Controllers\Controller;
use App\Models\TwilioConfiguration;
use App\Models\User;
use App\Domains\HRMS\Models\Company;
use App\Domains\HRMS\Models\Branch;
use App\Domains\CRM\Models\Lead;
use App\Domains\CRM\Models\LeadActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;

class TwilioSettingController extends Controller
{
    public function index(): View
    {
        $tenantId = current_tenant_id() ?? (auth()->user()?->tenant_id ?? 1);
        $companyId = current_company_id();
        $branchId = current_branch_id();

        $twilioConfig = TwilioConfiguration::forCurrentContext()->first();

        $users = User::where('tenant_id', $tenantId)->orderBy('name')->get();
        $companies = Company::where('tenant_id', $tenantId)->get();
        $branches = $companyId 
            ? Branch::where('company_id', $companyId)->get()
            : Branch::where('tenant_id', $tenantId)->get();

        $webhookBase = url('/');
        $voiceWebhookUrl = $webhookBase . '/api/crm/webhooks/twilio/voice';
        $recordingWebhookUrl = $webhookBase . '/api/crm/webhooks/twilio/recording';
        $statusWebhookUrl = $webhookBase . '/api/crm/webhooks/twilio/status';

        return view('modules.platform.twilio_settings.index', compact(
            'twilioConfig',
            'users',
            'companies',
            'branches',
            'tenantId',
            'companyId',
            'branchId',
            'voiceWebhookUrl',
            'recordingWebhookUrl',
            'statusWebhookUrl'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = current_tenant_id() ?? (auth()->user()?->tenant_id ?? 1);
        $companyId = current_company_id();
        $branchId = current_branch_id();

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:100'],
            'account_sid' => ['required', 'string', 'max:100'],
            'auth_token' => ['required', 'string', 'max:255'],
            'phone_number' => ['nullable', 'string', 'max:50'],
            'twiml_app_sid' => ['nullable', 'string', 'max:100'],
            'gemini_api_key' => ['nullable', 'string', 'max:255'],
            'default_lead_owner_id' => ['nullable', 'integer'],
            'default_source' => ['nullable', 'string', 'max:50'],
            'default_priority' => ['nullable', 'string', 'max:50'],
            'record_calls' => ['nullable'],
            'auto_summarize_ai' => ['nullable'],
            'is_active' => ['nullable'],
        ]);

        $config = TwilioConfiguration::forCurrentContext()->first();
        if (!$config) {
            $config = new TwilioConfiguration();
            $config->tenant_id = $tenantId;
            $config->company_id = $companyId;
            $config->branch_id = $branchId;
        }

        $config->name = $validated['name'] ?: 'Primary Twilio Account';
        $config->account_sid = trim($validated['account_sid']);
        $config->auth_token = trim($validated['auth_token']);
        $config->phone_number = !empty($validated['phone_number']) ? trim($validated['phone_number']) : null;
        $config->twiml_app_sid = !empty($validated['twiml_app_sid']) ? trim($validated['twiml_app_sid']) : null;
        $config->gemini_api_key = !empty($validated['gemini_api_key']) ? trim($validated['gemini_api_key']) : null;
        $config->default_lead_owner_id = !empty($validated['default_lead_owner_id']) ? (int)$validated['default_lead_owner_id'] : null;
        $config->default_source = $validated['default_source'] ?: 'Twilio Call';
        $config->default_priority = $validated['default_priority'] ?: 'Medium';
        $config->record_calls = $request->has('record_calls');
        $config->auto_summarize_ai = $request->has('auto_summarize_ai');
        $config->is_active = $request->has('is_active');
        $config->save();

        return redirect()->route('platform.twilioSettings.index')
            ->with('success', 'Twilio Telephony & Call Recording settings saved successfully.');
    }

    /**
     * Test Twilio credentials against Twilio REST API.
     */
    public function testConnection(Request $request): JsonResponse
    {
        $accountSid = trim((string)$request->input('account_sid'));
        $authToken = trim((string)$request->input('auth_token'));

        if (empty($accountSid) || empty($authToken)) {
            $config = TwilioConfiguration::forCurrentContext()->first();
            if ($config) {
                $accountSid = $config->account_sid;
                $authToken = $config->auth_token;
            }
        }

        if (empty($accountSid) || empty($authToken)) {
            return response()->json([
                'success' => false,
                'message' => 'Twilio Account SID and Auth Token are required to test connection.',
            ], 422);
        }

        try {
            $response = Http::withBasicAuth($accountSid, $authToken)
                ->timeout(10)
                ->get("https://api.twilio.com/2010-04-01/Accounts/{$accountSid}.json");

            if ($response->successful()) {
                $data = $response->json();
                return response()->json([
                    'success' => true,
                    'message' => 'Twilio connection successful! Account is active and verified.',
                    'account_name' => $data['friendly_name'] ?? 'Twilio Account',
                    'status' => ucfirst($data['status'] ?? 'Active'),
                    'type' => ucfirst($data['type'] ?? 'Trial / Full'),
                ]);
            }

            $errorData = $response->json();
            $errorMessage = $errorData['message'] ?? 'Failed to authenticate with Twilio. HTTP Status: ' . $response->status();

            return response()->json([
                'success' => false,
                'message' => 'Twilio Authentication Error: ' . $errorMessage,
            ], 400);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Simulate a test call log with audio recording & AI summary to verify pipeline.
     */
    public function simulateCallLog(Request $request): JsonResponse
    {
        $request->validate([
            'caller_name' => 'required|string|max:100',
            'phone' => 'required|string|max:30',
            'call_duration' => 'nullable|integer',
            'sample_notes' => 'nullable|string|max:500',
        ]);

        $tenantId = current_tenant_id() ?? (auth()->user()?->tenant_id ?? 1);
        $companyId = current_company_id();
        $branchId = current_branch_id();
        $twilioConfig = TwilioConfiguration::forCurrentContext()->first();

        try {
            // 1. Find or create lead
            $phone = trim($request->input('phone'));
            $lead = Lead::where('tenant_id', $tenantId)
                ->where(function($q) use ($phone) {
                    $q->where('phone', $phone)->orWhere('company_phone', $phone);
                })
                ->first();

            if (!$lead) {
                $callerCompany = trim((string)$request->input('company_name', ''));
                $callerName = trim((string)$request->input('caller_name', ''));
                $lead = new Lead();
                $lead->tenant_id = $tenantId;
                $lead->company_id = $companyId;
                $lead->branch_id = $branchId;
                $lead->lead_owner_id = $twilioConfig?->default_lead_owner_id ?: auth()->id();
                $lead->contact_person = $callerName ?: 'Twilio Caller';
                $lead->phone = $phone;
                $lead->company_name = !empty($callerCompany) ? $callerCompany : null;
                $lead->lead_type = !empty($callerCompany) ? 'b2b' : 'b2c';
                $lead->source = $twilioConfig?->default_source ?: 'Twilio Call';
                $lead->priority = $twilioConfig?->default_priority ?: 'Medium';
                $lead->status = 'Contacted';
                $lead->call_date = now();
                $lead->requirement = $request->input('sample_notes') ?: 'Inbound call recorded via Twilio Cloud Telephony integration test.';
                $lead->save();
            }

            // 2. Add Lead Followup / Call Log
            $duration = (int)($request->input('call_duration') ?: 145);
            $followup = new LeadFollowup();
            $followup->tenant_id = $tenantId;
            $followup->company_id = $companyId;
            $followup->branch_id = $branchId;
            $followup->lead_id = $lead->id;
            $followup->tagged_user_id = auth()->id() ?: ($lead->lead_owner_id ?: 1);
            $followup->type = 'Call';
            $followup->status = 'completed';
            $followup->title = "Inbound Call ({$duration}s) - Twilio Audio Logged";
            $followup->notes = ($request->input('sample_notes') ?: 'Customer enquired about bulk pricing and delivery timeline.') . "\n[AI Analysis: Positive sentiment, Quotation requested]";
            $followup->followup_date = now();
            $followup->save();

            // 3. Add Lead History Timeline
            LeadHistory::logEvent(
                $lead,
                'call_logged',
                null,
                $lead->status,
                "Call logged via Twilio ({$duration}s). Notes: " . ($request->input('sample_notes') ?: 'Customer enquired about bulk pricing.')
            );

            return response()->json([
                'success' => true,
                'message' => 'Simulated test call logged successfully for lead #' . $lead->lead_number,
                'lead_id' => $lead->id,
                'lead_url' => route('crm.leads.show', $lead->id),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Simulation error: ' . $e->getMessage(),
            ], 500);
        }
    }
}
