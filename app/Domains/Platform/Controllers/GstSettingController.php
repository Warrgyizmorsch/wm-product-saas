<?php

namespace App\Domains\Platform\Controllers;

use App\Http\Controllers\Controller;
use App\Models\GstConfiguration;
use App\Domains\HRMS\Models\Company;
use App\Domains\HRMS\Models\Branch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\View\View;

class GstSettingController extends Controller
{
    public function index(Request $request): View
    {
        $tenantId = current_tenant_id() ?? 1;
        $companyId = $request->query('company_id') ?: current_company_id();
        $branchId = $request->query('branch_id') ?: current_branch_id();

        $query = GstConfiguration::where('tenant_id', $tenantId);

        if ($companyId) {
            $query->where(function ($q) use ($companyId) {
                $q->where('company_id', $companyId)->orWhereNull('company_id');
            });
        }

        if ($branchId) {
            $query->where(function ($q) use ($branchId) {
                $q->where('branch_id', $branchId)->orWhereNull('branch_id');
            });
        }

        $configurations = $query->orderByDesc('is_default')->orderByDesc('created_at')->get();

        $companies = Company::where('tenant_id', $tenantId)->get();
        $branches = $companyId 
            ? Branch::where('company_id', $companyId)->get() 
            : Branch::where('tenant_id', $tenantId)->get();

        $currentConfig = GstConfiguration::getForCurrentContext($companyId ? (int)$companyId : null, $branchId ? (int)$branchId : null);

        return view('modules.platform.gst_settings.index', compact(
            'configurations',
            'currentConfig',
            'companies',
            'branches',
            'tenantId',
            'companyId',
            'branchId'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'id'                    => 'nullable|exists:gst_configurations,id',
            'provider'              => 'required|string|in:setu,cleartax,masters_india,cygnet,nic_direct,sandbox,custom',
            'environment'           => 'required|string|in:sandbox,production',
            'auth_type'             => 'required|string|in:api_key,bearer_token,gsp_credentials',
            'api_base_url'          => 'nullable|string|max:255',
            'client_id'             => 'nullable|string|max:255',
            'client_secret'         => 'nullable|string',
            'api_token'             => 'nullable|string',
            'gstin_username'        => 'nullable|string|max:100',
            'gstin_password'        => 'nullable|string',
            'seller_gstin'          => 'required|string|size:15',
            'legal_name'            => 'required|string|max:255',
            'trade_name'            => 'nullable|string|max:255',
            'address_line1'         => 'required|string|max:255',
            'address_line2'         => 'nullable|string|max:255',
            'location'              => 'required|string|max:100',
            'pincode'               => 'required|digits:6',
            'state_code'            => 'required|string|size:2',
            'contact_email'         => 'nullable|email|max:191',
            'contact_phone'         => 'nullable|string|max:20',
            'company_id'            => 'nullable|integer',
            'branch_id'             => 'nullable|integer',
            'auto_generate_on_post' => 'nullable|boolean',
            'is_default'            => 'nullable|boolean',
            'is_active'             => 'nullable|boolean',
        ]);

        $tenantId = current_tenant_id() ?? 1;

        // Auto determine base URL based on provider if empty or invalid legacy url
        if (empty($validated['api_base_url']) || str_contains($validated['api_base_url'], 'api.setu.co')) {
            $validated['api_base_url'] = match($validated['provider']) {
                'setu' => 'https://bridge.setu.co',
                'cleartax' => $validated['environment'] === 'production' ? 'https://api.cleartax.in/einvoicing/v2' : 'https://api-sandbox.cleartax.in/einvoicing/v2',
                'masters_india' => $validated['environment'] === 'production' ? 'https://api.mastersindia.co/v1' : 'https://sandbox.mastersindia.co/v1',
                'nic_direct' => $validated['environment'] === 'production' ? 'https://einvoice1.gst.gov.in/api' : 'https://einv-apisandbox.nic.in',
                default => null,
            };
        }

        $validated['tenant_id'] = $tenantId;
        $validated['auto_generate_on_post'] = $request->boolean('auto_generate_on_post');
        $validated['is_active'] = $request->has('is_active') ? $request->boolean('is_active') : true;
        $validated['is_default'] = $request->boolean('is_default');

        if ($validated['is_default']) {
            GstConfiguration::where('tenant_id', $tenantId)
                ->where('company_id', $validated['company_id'] ?? null)
                ->update(['is_default' => false]);
        }

        if (!empty($validated['id'])) {
            $config = GstConfiguration::where('tenant_id', $tenantId)->findOrFail($validated['id']);
            $config->update($validated);
            $msg = 'GST & E-Invoice configuration updated successfully.';
        } else {
            $config = GstConfiguration::create($validated);
            $msg = 'GST & E-Invoice configuration created successfully.';
        }

        return redirect()->route('platform.gstSettings.index', [
            'company_id' => $validated['company_id'] ?? null,
            'branch_id' => $validated['branch_id'] ?? null,
        ])->with('success', $msg);
    }

    public function testConnection(Request $request, int $id): JsonResponse
    {
        $tenantId = current_tenant_id() ?? 1;
        $config = GstConfiguration::where('tenant_id', $tenantId)->findOrFail($id);

        if ($config->provider === 'sandbox') {
            return response()->json([
                'success' => true,
                'status'  => 'online',
                'message' => 'Built-in Sandbox Engine is Online and ready for statutory GST Schema 1.03 generation.',
                'provider' => 'Internal High-Fidelity Sandbox Engine',
            ]);
        }

        if (empty($config->api_token) && empty($config->client_id)) {
            return response()->json([
                'success' => false,
                'status'  => 'error',
                'message' => 'Please configure API Token or Client ID & Secret for this provider.',
            ]);
        }

        try {
            $headers = [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ];

            if (!empty($config->api_token)) {
                $headers['Authorization'] = 'Bearer ' . $config->api_token;
                $headers['x-api-key'] = $config->api_token;
            }

            if (!empty($config->client_id)) {
                $headers['client_id'] = $config->client_id;
                $headers['client_secret'] = $config->client_secret ?? '';
                $headers['x-client-id'] = $config->client_id;
                $headers['x-client-secret'] = $config->client_secret ?? '';
            }

            // Target base url fix
            $baseUrl = $config->api_base_url;
            if (empty($baseUrl) || str_contains($baseUrl, 'api.setu.co')) {
                $baseUrl = ($config->provider === 'setu') ? 'https://bridge.setu.co' : $baseUrl;
                $config->update(['api_base_url' => $baseUrl]);
            }

            $url = rtrim($baseUrl, '/');
            if ($config->provider === 'setu') {
                $url = 'https://bridge.setu.co';
            } elseif (!str_ends_with($url, '/health')) {
                $url .= '/health';
            }

            $response = Http::timeout(6)->withoutVerifying()->withHeaders($headers)->get($url);

            if ($response->successful() || in_array($response->status(), [200, 201, 204, 401, 403, 404])) {
                $isOnline = in_array($response->status(), [200, 201, 204]);
                $statusMsg = $isOnline 
                    ? 'Provider Gateway reachable & connected successfully! (' . $config->provider_name . ')' 
                    : 'Provider Gateway reachable (HTTP ' . $response->status() . '). Gateway is active. Please verify your Client Secret / Token credentials.';

                return response()->json([
                    'success'  => true,
                    'status'   => $isOnline ? 'online' : 'connected_auth_pending',
                    'message'  => $statusMsg,
                    'http_code' => $response->status(),
                ]);
            }

            return response()->json([
                'success' => false,
                'status'  => 'failed',
                'message' => "Gateway responded with HTTP {$response->status()}: " . substr($response->body(), 0, 150),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status'  => 'offline',
                'message' => 'Connection test failed: ' . $e->getMessage(),
            ]);
        }
    }

    public function destroy(int $id): RedirectResponse
    {
        $tenantId = current_tenant_id() ?? 1;
        $config = GstConfiguration::where('tenant_id', $tenantId)->findOrFail($id);
        $config->delete();

        return redirect()->route('platform.gstSettings.index')->with('success', 'GST Configuration removed successfully.');
    }
}
