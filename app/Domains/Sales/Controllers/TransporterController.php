<?php

namespace App\Domains\Sales\Controllers;

use App\Domains\Sales\Models\Transporter;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TransporterController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Transporter::class);

        $tenantId = require_tenant_id();

        $transporters = Transporter::where('tenant_id', $tenantId)
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->input('search');
                return $q->where('name', 'like', "%{$search}%")
                    ->orWhere('transporter_id', 'like', "%{$search}%")
                    ->orWhere('gstin', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            })
            ->orderBy('name')
            ->paginate(15);

        return view('modules.sales.transporters.index', compact('transporters'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', Transporter::class);

        $tenantId = require_tenant_id();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'transporter_id' => 'nullable|string|max:50',
            'gstin' => 'nullable|string|max:20',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:150',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'pincode' => 'nullable|string|max:20',
            'status' => 'nullable|string|in:active,inactive',
        ]);

        $validated['tenant_id'] = $tenantId;
        $validated['status'] = $validated['status'] ?? 'active';

        Transporter::create($validated);

        return redirect()->route('sales.transporters.index')
            ->with('success', 'Transporter master created successfully.');
    }

    public function update(Request $request, Transporter $transporter): RedirectResponse
    {
        $this->authorize('update', $transporter);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'transporter_id' => 'nullable|string|max:50',
            'gstin' => 'nullable|string|max:20',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:150',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:100',
            'pincode' => 'nullable|string|max:20',
            'status' => 'required|string|in:active,inactive',
        ]);

        $transporter->update($validated);

        return redirect()->route('sales.transporters.index')
            ->with('success', 'Transporter master updated successfully.');
    }

    public function destroy(Transporter $transporter): RedirectResponse
    {
        $this->authorize('delete', $transporter);

        $transporter->delete();

        return redirect()->route('sales.transporters.index')
            ->with('success', 'Transporter deleted successfully.');
    }

    public function quickCreate(Request $request): JsonResponse
    {
        $this->authorize('create', Transporter::class);

        $tenantId = require_tenant_id();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'transporter_id' => 'nullable|string|max:50',
            'gstin' => 'nullable|string|max:20',
            'phone' => 'nullable|string|max:30',
        ]);

        $validated['tenant_id'] = $tenantId;
        $validated['status'] = 'active';

        $transporter = Transporter::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Transporter added successfully.',
            'transporter' => [
                'id' => $transporter->id,
                'name' => $transporter->name,
                'transporter_id' => $transporter->transporter_id,
                'gstin' => $transporter->gstin,
            ],
        ]);
    }
}
