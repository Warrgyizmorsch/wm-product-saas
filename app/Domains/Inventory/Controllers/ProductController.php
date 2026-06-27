<?php

namespace App\Domains\Inventory\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Inventory\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    public function quickCreate(Request $request): JsonResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id() ?? 1;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => [
                'required',
                'string',
                'max:255',
                function ($attribute, $value, $fail) use ($tenantId) {
                    if (Product::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('sku', $value)->exists()) {
                        $fail("The SKU '{$value}' has already been taken.");
                    }
                }
            ],
            'type' => 'required|in:finished_good,semi_finished,raw_material,component',
            'unit_cost' => 'nullable|numeric|min:0',
        ]);

        $product = Product::create([
            'tenant_id' => $tenantId,
            'name' => $validated['name'],
            'sku' => $validated['sku'],
            'type' => $validated['type'],
            'unit_cost' => $validated['unit_cost'] ?? 0.0,
            'status' => 'active',
        ]);

        return response()->json([
            'id' => $product->id,
            'name' => $product->name,
            'type' => $product->type,
        ]);
    }
}
