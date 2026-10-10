<?php

namespace App\Domains\Production\Controllers;

use App\Domains\Production\Models\ProductionReworkOrder;
use App\Domains\Production\Requests\CompleteReworkOperationRequest;
use App\Domains\Production\Requests\IssueReworkMaterialRequest;
use App\Domains\Production\Repositories\ProductionQualityRepositoryInterface;
use App\Domains\Production\Services\ProductionMaterialService;
use App\Domains\Production\Services\ReworkService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ReworkController extends Controller
{
    public function __construct(
        private readonly ReworkService $reworkService,
        private readonly ProductionQualityRepositoryInterface $qualityRepository
    ) {
    }

    public function index(Request $request)
    {
        $this->authorize('view', ProductionReworkOrder::class);

        $filters = $request->only(['search', 'status']);
        $reworks = $this->qualityRepository->paginateReworkOrders($filters, 15)->withQueryString();

        return view('modules.production.quality.rework.index', compact('reworks'));
    }

    public function show(int $id)
    {
        $this->authorize('view', ProductionReworkOrder::class);

        $rework = $this->qualityRepository->findReworkOrder($id);
        abort_if(!$rework, 404, 'Rework order not found.');

        $rework->loadMissing([
            'operations.workCenter.machines',
            'operations.machine',
            'requisitionSlips.items.product',
            'requisitionSlips.items.uom',
            'issues.product.uom',
            'issues.warehouse',
            'issues.user',
        ]);

        $tenantId = require_tenant_id();
        $warehouses = \App\Domains\Inventory\Models\Warehouse::where('tenant_id', $tenantId)->where('status', 'active')->get();
        $products = \App\Domains\Inventory\Models\Product::where('tenant_id', $tenantId)->where('status', 'active')->orderBy('name')->get();

        return view('modules.production.quality.rework.show', compact('rework', 'warehouses', 'products'));
    }

    public function startOp(Request $request, int $id)
    {
        $this->authorize('manage', ProductionReworkOrder::class);
        $tenantId = require_tenant_id();
        $machineId = $request->filled('machine_id') ? (int) $request->input('machine_id') : null;
        $this->reworkService->startOperation($id, $tenantId, $machineId);

        return redirect()->back()->with('success', 'Rework operation started.');
    }

    public function requestMaterial(Request $request, int $id)
    {
        $this->authorize('manage', ProductionReworkOrder::class);

        $data = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'quantity' => 'required|numeric|min:0.0001',
            'rework_operation_id' => 'nullable|integer|exists:production_rework_operations,id',
            'remarks' => 'nullable|string|max:500',
        ]);

        $tenantId = require_tenant_id();

        $slip = $this->reworkService->requestMaterial(
            reworkId: $id,
            tenantId: $tenantId,
            productId: (int) $data['product_id'],
            quantity: (float) $data['quantity'],
            reworkOpId: !empty($data['rework_operation_id']) ? (int) $data['rework_operation_id'] : null,
            reason: $data['remarks'] ?? null,
            userId: auth()->id()
        );

        return redirect()->back()->with('success', "Material request submitted to store on Requisition Slip #{$slip->requisition_number}.");
    }

    public function completeOp(CompleteReworkOperationRequest $request, int $id)
    {
        $this->authorize('manage', ProductionReworkOrder::class);
        $tenantId = require_tenant_id();
        $data = $request->validated();

        $this->reworkService->completeOperation($id, $data, $tenantId);

        return redirect()->back()->with('success', 'Rework operation completed.');
    }

    public function fail(Request $request, int $id)
    {
        $this->authorize('manage', ProductionReworkOrder::class);
        $tenantId = require_tenant_id();

        $data = $request->validate([
            'reason' => 'nullable|string|max:500',
            'remarks' => 'nullable|string|max:500',
        ]);

        $this->reworkService->failRework($id, $data, $tenantId);

        return redirect()->back()->with('success', 'Rework order failed and rejected quantity successfully converted to scrap.');
    }

    public function issueMaterial(
        IssueReworkMaterialRequest $request,
        int $id,
        ProductionMaterialService $materialService
    ) {
        $this->authorize('manage', ProductionReworkOrder::class);

        $rework = ProductionReworkOrder::findOrFail($id);
        $data = $request->validated();

        $issue = $materialService->issueReworkMaterial(
            reworkOrderId: $rework->id,
            productId: (int) $data['product_id'],
            quantity: (float) $data['quantity'],
            warehouseId: !empty($data['warehouse_id']) ? (int) $data['warehouse_id'] : null,
            reworkOperationId: !empty($data['rework_operation_id']) ? (int) $data['rework_operation_id'] : null,
            remarks: $data['remarks'] ?? null,
            userId: auth()->id()
        );

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Material successfully issued for rework.',
                'data' => $issue,
            ]);
        }

        return redirect()->back()->with('success', 'Material successfully issued for rework order.');
    }
}
