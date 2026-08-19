<?php

namespace App\Domains\CRM\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\CRM\Models\LeadStatus;
use App\Domains\CRM\Services\LeadStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeadStatusController extends Controller
{
    public function __construct(private readonly LeadStatusService $service)
    {
    }

    public function index(Request $request): View
    {
        $tenantId = tenant_id() ?? 1;

        if (LeadStatus::where('tenant_id', $tenantId)->count() === 0) {
            LeadStatus::seedDefaultsForTenant($tenantId);
        }

        $query = LeadStatus::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%");
        }

        if ($request->filled('type') && $request->type !== 'all') {
            if ($request->type === 'protected') {
                $query->where('is_protected', true);
            } elseif ($request->type === 'custom') {
                $query->where('is_protected', false);
            }
        }

        $sortBy = $request->input('sort_by', 'sort_order');
        $sortOrder = $request->input('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder)->orderBy('id', 'asc');

        $statuses = $query->paginate(15)->withQueryString();

        return view('modules.crm.masters.lead-statuses.index', compact('statuses'));
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $tenantId = tenant_id() ?? 1;

        $validated = $request->validate([
            'name'       => 'required|string|max:100|unique:lead_statuses,name,NULL,id,tenant_id,' . $tenantId,
            'color'      => 'nullable|string|max:50',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        // Additional check: prevent creating custom status with default protected names
        if (in_array(trim($validated['name']), LeadStatus::PROTECTED_STATUSES, true)) {
            $msg = 'Default status "' . $validated['name'] . '" already exists as a protected system status.';
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }
            return redirect()->back()->withErrors(['name' => $msg])->withInput();
        }

        $this->service->createStatus([
            'name'       => trim($validated['name']),
            'color'      => $validated['color'] ?? 'bg-primary',
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Lead status created successfully.']);
        }

        return redirect()->route('crm.masters.lead-statuses.index')->with('success', 'Lead status created successfully!');
    }

    public function update(Request $request, LeadStatus $leadStatus): RedirectResponse|JsonResponse
    {
        $tenantId = tenant_id() ?? 1;

        $validated = $request->validate([
            'name'       => 'required|string|max:100|unique:lead_statuses,name,' . $leadStatus->id . ',id,tenant_id,' . $tenantId,
            'color'      => 'nullable|string|max:50',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $res = $this->service->updateStatus($leadStatus, [
            'name'       => trim($validated['name']),
            'color'      => $validated['color'] ?? $leadStatus->color,
            'sort_order' => isset($validated['sort_order']) ? (int)$validated['sort_order'] : $leadStatus->sort_order,
        ]);

        if (!$res['success']) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $res['message']], 422);
            }
            return redirect()->back()->withErrors(['name' => $res['message']])->withInput();
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => $res['message']]);
        }

        return redirect()->route('crm.masters.lead-statuses.index')->with('success', $res['message']);
    }

    public function destroy(Request $request, LeadStatus $leadStatus): RedirectResponse|JsonResponse
    {
        $res = $this->service->deleteStatus($leadStatus);

        if (!$res['success']) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $res['message']], 422);
            }
            return redirect()->back()->with('error', $res['message']);
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => $res['message']]);
        }

        return redirect()->route('crm.masters.lead-statuses.index')->with('success', $res['message']);
    }

    public function reorder(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'order' => 'required|array',
            'order.*' => 'integer',
        ]);

        $res = $this->service->reorderStatuses($validated['order']);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => $res['message']]);
        }

        return redirect()->route('crm.masters.lead-statuses.index')->with('success', $res['message']);
    }

    public function move(Request $request, LeadStatus $leadStatus, string $direction): RedirectResponse
    {
        $tenantId = tenant_id() ?? 1;
        $statuses = $this->service->getAllStatuses($tenantId)->values();

        $currentIndex = $statuses->search(fn($item) => $item->id === $leadStatus->id);

        if ($currentIndex !== false) {
            $targetIndex = $direction === 'up' ? $currentIndex - 1 : $currentIndex + 1;

            if ($targetIndex >= 0 && $targetIndex < $statuses->count()) {
                $targetStatus = $statuses[$targetIndex];

                // Swap sort orders
                $currentOrder = $leadStatus->sort_order ?: ($currentIndex + 1);
                $targetOrder  = $targetStatus->sort_order ?: ($targetIndex + 1);

                if ($currentOrder === $targetOrder) {
                    $currentOrder = $currentIndex + 1;
                    $targetOrder  = $targetIndex + 1;
                }

                $leadStatus->update(['sort_order' => $targetOrder]);
                $targetStatus->update(['sort_order' => $currentOrder]);
            }
        }

        return redirect()->route('crm.masters.lead-statuses.index')->with('success', 'Lead status order updated!');
    }
}
