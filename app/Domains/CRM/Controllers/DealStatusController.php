<?php

namespace App\Domains\CRM\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\CRM\Models\DealStatus;
use App\Domains\CRM\Services\DealStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DealStatusController extends Controller
{
    public function __construct(private readonly DealStatusService $service)
    {
    }

    public function index(Request $request): View
    {
        $tenantId = tenant_id() ?? 1;

        if (DealStatus::where('tenant_id', $tenantId)->count() === 0) {
            DealStatus::seedDefaultsForTenant($tenantId);
        }

        $query = DealStatus::query()
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

        return view('modules.crm.masters.deal-statuses.index', compact('statuses'));
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $tenantId = tenant_id() ?? 1;

        $validated = $request->validate([
            'name'        => 'required|string|max:100|unique:deal_statuses,name,NULL,id,tenant_id,' . $tenantId,
            'color'       => 'nullable|string|max:50',
            'sort_order'  => 'nullable|integer|min:0',
            'probability' => 'nullable|integer|min:0|max:100',
        ]);

        // Additional check: prevent creating custom stage with default protected names (Won, Lost)
        if (in_array(trim($validated['name']), DealStatus::PROTECTED_STATUSES, true)) {
            $msg = 'Default stage "' . $validated['name'] . '" already exists as a protected system status.';
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }
            return redirect()->back()->withErrors(['name' => $msg])->withInput();
        }

        $this->service->createStatus([
            'name'        => trim($validated['name']),
            'color'       => $validated['color'] ?? 'bg-primary',
            'sort_order'  => $validated['sort_order'] ?? 0,
            'probability' => isset($validated['probability']) ? (int)$validated['probability'] : 50,
        ]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => 'Deal stage created successfully.']);
        }

        return redirect()->route('crm.masters.deal-statuses.index')->with('success', 'Deal stage created successfully!');
    }

    public function update(Request $request, DealStatus $dealStatus): RedirectResponse|JsonResponse
    {
        $tenantId = tenant_id() ?? 1;

        $validated = $request->validate([
            'name'        => 'required|string|max:100|unique:deal_statuses,name,' . $dealStatus->id . ',id,tenant_id,' . $tenantId,
            'color'       => 'nullable|string|max:50',
            'sort_order'  => 'nullable|integer|min:0',
            'probability' => 'nullable|integer|min:0|max:100',
        ]);

        $res = $this->service->updateStatus($dealStatus, [
            'name'        => trim($validated['name']),
            'color'       => $validated['color'] ?? $dealStatus->color,
            'sort_order'  => isset($validated['sort_order']) ? (int)$validated['sort_order'] : $dealStatus->sort_order,
            'probability' => isset($validated['probability']) ? (int)$validated['probability'] : $dealStatus->probability,
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

        return redirect()->route('crm.masters.deal-statuses.index')->with('success', $res['message']);
    }

    public function destroy(Request $request, DealStatus $dealStatus): RedirectResponse|JsonResponse
    {
        $res = $this->service->deleteStatus($dealStatus);

        if (!$res['success']) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $res['message']], 422);
            }
            return redirect()->back()->with('error', $res['message']);
        }

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => $res['message']]);
        }

        return redirect()->route('crm.masters.deal-statuses.index')->with('success', $res['message']);
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

        return redirect()->route('crm.masters.deal-statuses.index')->with('success', $res['message']);
    }

    public function move(Request $request, DealStatus $dealStatus, string $direction): RedirectResponse
    {
        $tenantId = tenant_id() ?? 1;
        $statuses = $this->service->getAllStatuses($tenantId)->values();

        $currentIndex = $statuses->search(fn($item) => $item->id === $dealStatus->id);

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

                $dealStatus->update(['sort_order' => $targetOrder]);
                $targetStatus->update(['sort_order' => $currentOrder]);
            }
        }

        return redirect()->route('crm.masters.deal-statuses.index')->with('success', 'Deal stage order updated!');
    }
}
