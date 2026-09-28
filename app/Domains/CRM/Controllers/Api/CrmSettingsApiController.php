<?php

namespace App\Domains\CRM\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domains\CRM\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class CrmSettingsApiController extends Controller
{
    /**
     * GET /api/crm/settings
     */
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Lead::class);
        $tenant = tenant();
        $settings = is_array($tenant?->settings) ? $tenant->settings : [];

        return response()->json([
            'success' => true,
            'data'    => [
                'quotation_approval_policy' => $settings['quotation_approval_policy'] ?? 'approval_required',
                'invoicing_policy'          => $settings['invoicing_policy'] ?? 'sales_order',
                'raw_settings'              => $settings,
            ],
        ]);
    }

    /**
     * POST /api/crm/settings
     */
    public function update(Request $request): JsonResponse
    {
        $this->authorize('create', Lead::class);

        $validator = Validator::make($request->all(), [
            'quotation_approval_policy' => 'nullable|string|in:approval_required,auto_approve',
            'invoicing_policy'          => 'nullable|string|in:sales_order,dispatch_order,both',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $tenant = tenant();
        if (!$tenant) {
            return response()->json(['success' => false, 'message' => 'Tenant context not found.'], 404);
        }

        $currentSettings = is_array($tenant->settings) ? $tenant->settings : [];
        if ($request->has('quotation_approval_policy')) {
            $currentSettings['quotation_approval_policy'] = $request->input('quotation_approval_policy');
        }
        if ($request->has('invoicing_policy')) {
            $currentSettings['invoicing_policy'] = $request->input('invoicing_policy');
        }

        $tenant->update(['settings' => $currentSettings]);

        return response()->json([
            'success' => true,
            'message' => 'CRM Settings updated successfully.',
            'data'    => [
                'quotation_approval_policy' => $currentSettings['quotation_approval_policy'] ?? 'approval_required',
                'invoicing_policy'          => $currentSettings['invoicing_policy'] ?? 'sales_order',
            ],
        ]);
    }
}
