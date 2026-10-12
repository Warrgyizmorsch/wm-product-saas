<?php

namespace App\Domains\Platform\Controllers;

use App\Http\Controllers\Controller;
use App\Models\MetaConfiguration;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;

use App\Models\MetaLeadLog;
use App\Services\MetaLeadService;

class MetaSettingController extends Controller
{
    public function __construct(
        protected MetaLeadService $metaLeadService
    ) {}

    public function index(): View
    {
        $tenantId = current_tenant_id() ?? 1;
        $companyId = current_company_id();
        $branchId = current_branch_id();

        $metaConfig = MetaConfiguration::forCurrentContext()->first();
        $recentLogs = MetaLeadLog::where('tenant_id', $tenantId)
            ->with(['crmLead'])
            ->latest()
            ->take(25)
            ->get();

        $users = User::where('tenant_id', $tenantId)->orderBy('name')->get();
        $companies = \App\Domains\HRMS\Models\Company::where('tenant_id', $tenantId)->get();
        $branches = $companyId 
            ? \App\Domains\HRMS\Models\Branch::where('company_id', $companyId)->get()
            : \App\Domains\HRMS\Models\Branch::where('tenant_id', $tenantId)->get();

        return view('modules.platform.meta_settings.index', compact(
            'metaConfig',
            'recentLogs',
            'users',
            'companies',
            'branches',
            'tenantId',
            'companyId',
            'branchId'
        ));
    }

    /**
     * Simulate an instant test lead generation without needing active Facebook ad budget.
     */
    public function simulateTestLead(Request $request): JsonResponse
    {
        $request->validate([
            'contact_person' => 'required|string|max:191',
            'phone'          => 'required|string|max:50',
            'email'          => 'nullable|email|max:191',
            'company_name'   => 'nullable|string|max:191',
            'form_name'      => 'nullable|string|max:191',
            'requirement'    => 'nullable|string|max:500',
        ]);

        $metaConfig = MetaConfiguration::forCurrentContext()->first();
        $tenantId = current_tenant_id() ?? 1;
        $companyId = current_company_id();
        $branchId = current_branch_id();
        $leadgenId = 'test_meta_' . time() . '_' . rand(1000, 9999);

        try {
            $lead = new \App\Domains\CRM\Models\Lead();
            $lead->tenant_id = $tenantId;
            $lead->company_id = $companyId;
            $lead->branch_id = $branchId;
            $lead->lead_owner_id = $metaConfig?->default_lead_owner_id ?: auth()->id();
            $lead->contact_person = $request->input('contact_person');
            $lead->phone = $request->input('phone');
            $lead->company_phone = $request->input('phone');
            $lead->email = $request->input('email');
            $lead->company_email = $request->input('email');
            $lead->company_name = $request->input('company_name') ?: $request->input('contact_person');
            $lead->requirement = ($request->input('form_name') ? "[Form: {$request->input('form_name')}] " : '') . ($request->input('requirement') ?: 'Sample test inquiry submitted via Meta Lead Ads Simulator');
            $lead->source = $metaConfig?->default_source ?: 'Meta Ads';
            $lead->priority = $metaConfig?->default_priority ?: 'High';
            $lead->status = 'New';
            $lead->utm_source = 'meta';
            $lead->utm_medium = 'test_simulation';
            $lead->utm_campaign = $request->input('form_name') ?: 'Meta Sandbox Testing';
            $lead->call_date = now();
            $lead->save();

            // Create Log
            MetaLeadLog::create([
                'tenant_id' => $tenantId,
                'meta_configuration_id' => $metaConfig?->id,
                'leadgen_id' => $leadgenId,
                'page_id' => $metaConfig?->page_id ?: 'sample_page_id',
                'form_id' => 'sample_form_' . date('Ymd'),
                'full_name' => $lead->contact_person,
                'phone_number' => $lead->phone,
                'email' => $lead->email,
                'raw_payload' => [
                    'simulation' => true,
                    'timestamp' => now()->toIso8601String(),
                ],
                'extracted_data' => [
                    'full_name' => $lead->contact_person,
                    'phone_number' => $lead->phone,
                    'email' => $lead->email,
                    'company_name' => $lead->company_name,
                    'form_name' => $request->input('form_name') ?: 'Sample Lead Form',
                ],
                'crm_lead_id' => $lead->id,
                'status' => 'processed',
                'lead_created_time' => now(),
            ]);

            // Create History
            \App\Domains\CRM\Models\LeadHistory::create([
                'tenant_id' => $tenantId,
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'lead_id' => $lead->id,
                'user_id' => auth()->id(),
                'event_type' => 'meta_lead_simulated',
                'notes' => "Test Lead simulated and successfully ingested into CRM (#{$lead->lead_number})",
            ]);

            return response()->json([
                'success' => true,
                'message' => "✓ Test Meta Lead '{$lead->contact_person}' successfully created in CRM as Lead #{$lead->lead_number}!",
                'lead_id' => $lead->id,
                'lead_number' => $lead->lead_number,
                'view_url' => route('crm.leads.show', $lead->id),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to simulate test lead: ' . $e->getMessage(),
            ], 422);
        }
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'id'                     => 'nullable|exists:meta_configurations,id',
            'name'                   => 'required|string|max:191',
            'app_id'                 => 'nullable|string|max:191',
            'app_secret'             => 'nullable|string|max:255',
            'access_token'           => 'nullable|string',
            'ad_account_id'          => 'nullable|string|max:191',
            'page_id'                => 'nullable|string|max:191',
            'pixel_id'               => 'nullable|string|max:191',
            'verify_token'           => 'nullable|string|max:191',
            'default_lead_owner_id'  => 'nullable|exists:users,id',
            'default_source'         => 'required|string|max:100',
            'default_priority'       => 'required|string|max:50',
            'company_id'             => 'nullable|exists:companies,id',
            'branch_id'              => 'nullable|exists:branches,id',
            'is_active'              => 'nullable|boolean',
        ]);

        $validated['tenant_id'] = current_tenant_id() ?? 1;
        $validated['company_id'] = $request->input('company_id') ?: current_company_id();
        $validated['branch_id'] = $request->input('branch_id') ?: current_branch_id();
        $validated['is_active'] = $request->boolean('is_active');

        // Auto-generate verify_token if not supplied
        if (empty($validated['verify_token'])) {
            $validated['verify_token'] = 'meta_erp_' . bin2hex(random_bytes(8));
        }

        // Standardize Ad Account format (ensure act_ prefix if numeric)
        if (!empty($validated['ad_account_id']) && !str_starts_with($validated['ad_account_id'], 'act_')) {
            $validated['ad_account_id'] = 'act_' . ltrim($validated['ad_account_id'], 'act_');
        }

        try {
            if (!empty($validated['id'])) {
                $config = MetaConfiguration::findOrFail($validated['id']);
                $config->update($validated);
                $msg = 'Meta Ads & Marketing Configuration updated successfully!';
            } else {
                MetaConfiguration::create($validated);
                $msg = 'Meta Ads & Marketing Configuration created successfully!';
            }

            return redirect()->route('platform.metaSettings.index')->with('success', $msg);
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('error', 'Failed to save Meta configuration: ' . $e->getMessage());
        }
    }

    public function testToken(Request $request): JsonResponse
    {
        $token = $request->input('access_token');
        $adAccountId = $request->input('ad_account_id');

        if (empty($token)) {
            $config = MetaConfiguration::forCurrentContext()->first();
            $token = $config?->access_token;
            $adAccountId = $adAccountId ?: $config?->ad_account_id;
        }

        if (empty($token)) {
            return response()->json([
                'success' => false,
                'message' => 'No Access Token provided or configured. Please enter your Meta System User / Graph Access Token.',
            ], 422);
        }

        try {
            // Check identity / token info from Meta Graph API
            $response = Http::timeout(10)->get('https://graph.facebook.com/v20.0/me', [
                'access_token' => $token,
                'fields' => 'id,name',
            ]);

            if ($response->failed()) {
                $err = $response->json()['error']['message'] ?? 'Invalid token or insufficient permissions.';
                return response()->json([
                    'success' => false,
                    'message' => 'Meta API Authentication Failed: ' . $err,
                ], 422);
            }

            $userData = $response->json();
            $metaName = $userData['name'] ?? 'Meta App / System User';
            $metaId = $userData['id'] ?? 'N/A';

            $details = [
                'User/App Name' => $metaName,
                'Meta ID' => $metaId,
            ];

            // If Ad Account ID is also supplied, check ad account status
            if (!empty($adAccountId)) {
                $adAccFormatted = str_starts_with($adAccountId, 'act_') ? $adAccountId : 'act_' . $adAccountId;
                $adResponse = Http::timeout(10)->get("https://graph.facebook.com/v20.0/{$adAccFormatted}", [
                    'access_token' => $token,
                    'fields' => 'id,name,account_status,currency,timezone_name',
                ]);

                if ($adResponse->successful()) {
                    $adData = $adResponse->json();
                    $details['Ad Account Name'] = $adData['name'] ?? $adAccFormatted;
                    $details['Currency'] = $adData['currency'] ?? 'INR';
                    $details['Account Status'] = ($adData['account_status'] ?? 1) == 1 ? 'Active (Good Standing)' : 'Status Code: ' . ($adData['account_status'] ?? 'Unknown');
                }
            }

            return response()->json([
                'success' => true,
                'message' => "✓ Meta Graph API Token is Valid and Connected successfully as '{$metaName}'!",
                'details' => $details,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection test failed: ' . $e->getMessage(),
            ], 422);
        }
    }

    public function destroy(int $id): RedirectResponse
    {
        try {
            $config = MetaConfiguration::findOrFail($id);
            $config->delete();

            return redirect()->route('platform.metaSettings.index')->with('success', 'Meta configuration removed successfully.');
        } catch (\Throwable $e) {
            return redirect()->route('platform.metaSettings.index')->with('error', 'Failed to delete configuration: ' . $e->getMessage());
        }
    }
}
