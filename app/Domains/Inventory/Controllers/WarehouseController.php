<?php

namespace App\Domains\Inventory\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Inventory\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WarehouseController extends Controller
{
    public function index(): View
    {
        $warehouses = Warehouse::query()->latest()->get();

        return view('modules.inventory.warehouses.index', compact('warehouses'));
    }

    public function store(Request $request): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id() ?? 1;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('warehouses', 'code')->where(fn($q) => $q->where('tenant_id', $tenantId))
            ],
            'address' => 'nullable|string|max:1000',
            'is_default' => 'nullable|boolean',
        ]);

        $isDefault = !empty($validated['is_default']);

        if ($isDefault) {
            // Unset other default warehouses for this tenant
            Warehouse::query()->where('is_default', true)->update(['is_default' => false]);
        }

        // If this is the only warehouse, make it default automatically
        if (Warehouse::query()->count() === 0) {
            $isDefault = true;
        }

        Warehouse::create([
            'name' => $validated['name'],
            'code' => strtoupper($validated['code']),
            'address' => $validated['address'],
            'is_default' => $isDefault,
            'status' => 'active',
        ]);

        return redirect()->route('inventory.warehouses.index')
            ->with('success', 'Warehouse created successfully.');
    }

    public function update(Request $request, Warehouse $warehouse): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id() ?? 1;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('warehouses', 'code')
                    ->where(fn($q) => $q->where('tenant_id', $tenantId))
                    ->ignore($warehouse->id)
            ],
            'address' => 'nullable|string|max:1000',
            'is_default' => 'nullable|boolean',
            'status' => 'required|in:active,inactive',
        ]);

        $isDefault = !empty($validated['is_default']);

        if ($isDefault) {
            Warehouse::query()->where('is_default', true)->update(['is_default' => false]);
        }

        $warehouse->update([
            'name' => $validated['name'],
            'code' => strtoupper($validated['code']),
            'address' => $validated['address'],
            'is_default' => $isDefault,
            'status' => $validated['status'],
        ]);

        // Ensure at least one default warehouse exists
        if (!Warehouse::query()->where('is_default', true)->exists()) {
            $first = Warehouse::query()->first();
            if ($first) {
                $first->update(['is_default' => true]);
            }
        }

        return redirect()->route('inventory.warehouses.index')
            ->with('success', 'Warehouse updated successfully.');
    }

    public function destroy(Warehouse $warehouse): RedirectResponse
    {
        // Prevent deleting the default warehouse unless another exists
        if ($warehouse->is_default) {
            // Try to assign default to another warehouse
            $other = Warehouse::query()->where('id', '!=', $warehouse->id)->first();
            if ($other) {
                $other->update(['is_default' => true]);
            } else {
                return redirect()->route('inventory.warehouses.index')
                    ->with('error', 'Cannot delete the only warehouse. Add another one first.');
            }
        }

        $warehouse->delete();

        return redirect()->route('inventory.warehouses.index')
            ->with('success', 'Warehouse deleted successfully.');
    }
}
