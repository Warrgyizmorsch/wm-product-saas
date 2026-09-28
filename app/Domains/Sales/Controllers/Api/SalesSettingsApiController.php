<?php

namespace App\Domains\Sales\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domains\Sales\Models\SalesOrder;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class SalesSettingsApiController extends Controller
{
    /**
     * GET /api/sales/settings
     * Get tenant Sales & Invoicing policy settings.
     */
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', SalesOrder::class);
        $tenant = tenant();
        $settings = is_array($tenant?->settings) ? $tenant->settings : [];

        return response()->json([
            'success' => true,
            'data'    => [
                'invoicing_policy' => $settings['invoicing_policy'] ?? 'both',
                'raw_settings'     => $settings,
            ],
        ]);
    }

    /**
     * POST /api/sales/settings
     * Update tenant Sales invoicing policy.
     */
    public function update(Request $request): JsonResponse
    {
        $this->authorize('create', SalesOrder::class);

        $validator = Validator::make($request->all(), [
            'invoicing_policy' => ['required', 'string', 'in:sales_order,dispatch_order,both'],
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
        $currentSettings['invoicing_policy'] = $request->input('invoicing_policy');

        $tenant->update(['settings' => $currentSettings]);

        return response()->json([
            'success' => true,
            'message' => 'Sales Invoicing Policy updated successfully.',
            'data'    => [
                'invoicing_policy' => $currentSettings['invoicing_policy'],
            ],
        ]);
    }
}
