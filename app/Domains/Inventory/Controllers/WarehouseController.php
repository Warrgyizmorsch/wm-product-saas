<?php

namespace App\Domains\Inventory\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Inventory\Repositories\WarehouseRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WarehouseController extends Controller
{
    public function __construct(
        protected WarehouseRepository $warehouseRepo
    ) {}

    public function index(): View
    {
        $this->authorize('viewAny', Warehouse::class);
        $warehouses = $this->warehouseRepo->getAll();
        return view('modules.inventory.warehouses.index', compact('warehouses'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Warehouse::class);
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
            Warehouse::query()->where('is_default', true)->update(['is_default' => false]);
        }
        if (Warehouse::query()->count() === 0) {
            $isDefault = true;
        }

        $this->warehouseRepo->create([
            'name' => $validated['name'],
            'code' => strtoupper($validated['code']),
            'address' => $validated['address'] ?? null,
            'is_default' => $isDefault,
            'status' => 'active',
        ]);

        return redirect()->route('inventory.warehouses.index')->with('success', 'Warehouse created successfully.');
    }

    public function update(Request $request, Warehouse $warehouse): RedirectResponse
    {
        $this->authorize('update', $warehouse);
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id() ?? 1;

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => [
                'required',
                'string',
                'max:50',
                Rule::unique('warehouses', 'code')->where(fn($q) => $q->where('tenant_id', $tenantId))->ignore($warehouse->id)
            ],
            'address' => 'nullable|string|max:1000',
            'is_default' => 'nullable|boolean',
            'status' => 'required|in:active,inactive',
        ]);

        $isDefault = !empty($validated['is_default']);
        if ($isDefault) {
            Warehouse::query()->where('is_default', true)->update(['is_default' => false]);
        }

        $this->warehouseRepo->update($warehouse, [
            'name' => $validated['name'],
            'code' => strtoupper($validated['code']),
            'address' => $validated['address'] ?? null,
            'is_default' => $isDefault,
            'status' => $validated['status'],
        ]);

        if (!Warehouse::query()->where('is_default', true)->exists()) {
            $first = Warehouse::query()->first();
            if ($first) {
                $first->update(['is_default' => true]);
            }
        }

        return redirect()->route('inventory.warehouses.index')->with('success', 'Warehouse updated successfully.');
    }

    public function destroy(Warehouse $warehouse): RedirectResponse
    {
        $this->authorize('delete', $warehouse);

        $success = $this->warehouseRepo->delete($warehouse);

        if ($success === false) {
            return redirect()->route('inventory.warehouses.index')->with('error', 'Cannot delete the only warehouse. Add another one first.');
        }

        return redirect()->route('inventory.warehouses.index')->with('success', 'Warehouse deleted successfully.');
    }

    public function quickCreate(Request $request): JsonResponse
    {
        $this->authorize('create', Warehouse::class);
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
        ]);

        $warehouse = $this->warehouseRepo->create([
            'tenant_id' => $tenantId,
            'name' => $validated['name'],
            'code' => strtoupper($validated['code']),
            'address' => $validated['address'] ?? null,
            'is_default' => Warehouse::query()->count() === 0,
            'status' => 'active',
        ]);

        return response()->json([
            'id' => $warehouse->id,
            'name' => $warehouse->name,
        ]);
    }
}
