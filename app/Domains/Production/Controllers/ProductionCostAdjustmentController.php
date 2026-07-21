<?php

namespace App\Domains\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Production\Models\ProductionCostAdjustment;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Requests\StoreProductionCostAdjustmentRequest;
use App\Domains\Production\Requests\UpdateProductionCostAdjustmentRequest;
use App\Domains\Production\Services\ProductionCostAdjustmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class ProductionCostAdjustmentController extends Controller
{
    protected ProductionCostAdjustmentService $adjustmentService;

    public function __construct(ProductionCostAdjustmentService $adjustmentService)
    {
        $this->adjustmentService = $adjustmentService;
    }

    public function store(StoreProductionCostAdjustmentRequest $request, int $orderId)
    {
        $order = ProductionOrder::findOrFail($orderId);

        try {
            $adjustment = $this->adjustmentService->createAdjustment(
                $order,
                $request->validated(),
                $request->file('attachment'),
                auth()->id()
            );

            return redirect()->back()
                ->with('success', "Manual cost adjustment of {$adjustment->amount} ({$adjustment->cost_component}) successfully recorded.");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to create cost adjustment: ' . $e->getMessage());
        }
    }

    public function update(UpdateProductionCostAdjustmentRequest $request, int $id)
    {
        $adjustment = ProductionCostAdjustment::findOrFail($id);

        try {
            $updated = $this->adjustmentService->updateAdjustment(
                $adjustment,
                $request->validated(),
                $request->file('attachment'),
                auth()->id()
            );

            return redirect()->back()
                ->with('success', "Cost adjustment #{$updated->id} successfully updated.");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to update cost adjustment: ' . $e->getMessage());
        }
    }

    public function destroy(Request $request, int $id)
    {
        $adjustment = ProductionCostAdjustment::findOrFail($id);
        $order = $adjustment->order;

        $user = auth()->user();
        if (!$user || $user->tenant_id !== $order->tenant_id) {
            abort(403, 'Unauthorized tenant access.');
        }

        $canDelete = $user->role === 'admin'
            || $user->hasProductionPermission('production.cost_adjustment.delete', $order->tenant_id)
            || $user->hasProductionPermission('production.order.update', $order->tenant_id);

        if (!$canDelete) {
            abort(403, 'Unauthorized action.');
        }

        if ($order->isCompleted() || $order->isClosed() || $order->isCancelled()) {
            return redirect()->back()->with('error', 'Cannot delete cost adjustments for a completed, closed, or cancelled order.');
        }

        try {
            $this->adjustmentService->deleteAdjustment($adjustment, auth()->id());
            return redirect()->back()->with('success', 'Cost adjustment successfully soft-deleted.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to delete cost adjustment: ' . $e->getMessage());
        }
    }

    public function downloadAttachment(Request $request, int $id)
    {
        $adjustment = ProductionCostAdjustment::findOrFail($id);
        $order = $adjustment->order;

        $user = auth()->user();
        if (!$user || $user->tenant_id !== $order->tenant_id) {
            abort(404, 'Attachment file not found.');
        }

        $canDownload = $user->role === 'admin'
            || $user->hasProductionPermission('production.cost_adjustment.download', $order->tenant_id)
            || $user->hasProductionPermission('production.cost_adjustment.view', $order->tenant_id)
            || $user->hasProductionPermission('production.order.view', $order->tenant_id);

        if (!$canDownload) {
            abort(403, 'Unauthorized to download attachment.');
        }

        if (!$adjustment->attachment_path || !Storage::disk('local')->exists($adjustment->attachment_path)) {
            abort(404, 'Attachment file not found.');
        }

        return Storage::disk('local')->download($adjustment->attachment_path);
    }
}
