<?php

namespace App\Domains\Inventory\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Inventory\Models\Uom;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class UomController extends Controller
{
    public function quickCreate(Request $request): JsonResponse
    {
        $this->authorize('create', Uom::class);

        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id() ?? 1;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => [
                'required',
                'string',
                'max:255',
                function ($attribute, $value, $fail) use ($tenantId) {
                    if (Uom::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('code', $value)->exists()) {
                        $fail("The UOM code '{$value}' has already been taken.");
                    }
                }
            ],
        ]);

        $uom = Uom::create([
            'tenant_id' => $tenantId,
            'name' => $validated['name'],
            'code' => $validated['code'],
        ]);

        return response()->json([
            'id' => $uom->id,
            'name' => $uom->name,
        ]);
    }
}
