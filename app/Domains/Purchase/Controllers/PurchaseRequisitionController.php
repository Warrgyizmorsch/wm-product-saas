<?php

namespace App\Domains\Purchase\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Purchase\Repositories\PurchaseRequisitionRepository;
use App\Domains\Purchase\Services\PurchaseRequisitionService;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionRequisitionSlip;
use App\Domains\Sales\Models\MaterialRequirement;
use App\Domains\Sales\Models\SalesOrder;
use Illuminate\Http\Request;

class PurchaseRequisitionController extends Controller
{
    public function __construct(
        protected PurchaseRequisitionRepository $requisitionRepo,
        protected PurchaseRequisitionService $requisitionService
    ) {}

    public function index(Request $request)
    {
        $requisitions = $this->requisitionRepo->getPaginatedRequisitions($request->all(), 10);
        return view('modules.purchase.requisitions.index', compact('requisitions'));
    }

    public function prApprovals(Request $request)
    {
        $requisitions = $this->requisitionRepo->getPendingApprovals($request->all(), 15);
        return view('modules.purchase.approvals.index', compact('requisitions'));
    }

    public function create()
    {
        $tenantId = require_tenant_id();

        $products = Product::where('tenant_id', $tenantId)->get();
        $warehouses = Warehouse::where('tenant_id', $tenantId)->get();
        $productionOrders = ProductionOrder::where('tenant_id', $tenantId)->get();
        $materialRequests = ProductionRequisitionSlip::where('tenant_id', $tenantId)->get();
        $materialRequirements = MaterialRequirement::where('tenant_id', $tenantId)->get();
        $salesOrders = SalesOrder::where('tenant_id', $tenantId)->get();

        return view('modules.purchase.requisitions.create', compact(
            'products',
            'warehouses',
            'productionOrders',
            'materialRequests',
            'materialRequirements',
            'salesOrders'
        ));
    }

    public function store(Request $request)
    {
        $tenantId = require_tenant_id();

        $validated = $request->validate([
            'requisition_date' => 'required|date',
            'expected_date' => 'nullable|date',
            'source_type' => 'required|string|in:direct,so,mo,material_request,material_requirement,requisition_slip',
            'sales_order_id' => 'nullable|integer|exists:sales_orders,id',
            'production_order_id' => 'nullable|integer|exists:production_orders,id',
            'production_requisition_slip_id' => 'nullable|integer|exists:production_requisition_slips,id',
            'material_requirement_id' => 'nullable|integer|exists:material_requirements,id',
            'requisition_slip_number' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.warehouse_id' => 'nullable|integer|exists:warehouses,id',
            'items.*.quantity' => 'required|numeric|min:0.0001',
            'items.*.estimated_cost' => 'required|numeric|min:0',
        ]);

        $pr = $this->requisitionService->storeRequisition($validated, $tenantId);

        return redirect()->route('purchase.requisitions.show', $pr->id)
            ->with('success', "Purchase Requisition {$pr->requisition_number} created successfully.");
    }

    public function show(int $id)
    {
        $requisition = $this->requisitionRepo->findWithDetails($id);
        return view('modules.purchase.requisitions.show', compact('requisition'));
    }

    public function detailPartial(int $id)
    {
        $requisition = $this->requisitionRepo->findWithDetails($id);
        if ($requisition) {
            $requisition->load('reminders.user');
        }
        return view('modules.purchase.requisitions.detail-partial', compact('requisition'));
    }

    public function edit(int $id)
    {
        $tenantId = require_tenant_id();

        $requisition = $this->requisitionRepo->find($id);
        if (!$requisition) abort(404);

        if ($requisition->status !== 'Draft') {
            return redirect()->route('purchase.requisitions.show', $id)
                ->with('error', 'Only Draft Purchase Requisitions can be edited.');
        }

        $requisition->load('items');
        $products = Product::where('tenant_id', $tenantId)->get();
        $warehouses = Warehouse::where('tenant_id', $tenantId)->get();
        $productionOrders = ProductionOrder::where('tenant_id', $tenantId)->get();
        $materialRequests = ProductionRequisitionSlip::where('tenant_id', $tenantId)->get();
        $materialRequirements = MaterialRequirement::where('tenant_id', $tenantId)->get();
        $salesOrders = SalesOrder::where('tenant_id', $tenantId)->get();

        return view('modules.purchase.requisitions.edit', compact(
            'requisition',
            'products',
            'warehouses',
            'productionOrders',
            'materialRequests',
            'materialRequirements',
            'salesOrders'
        ));
    }

    public function update(Request $request, int $id)
    {
        $requisition = $this->requisitionRepo->find($id);
        if (!$requisition) abort(404);

        if ($requisition->status !== 'Draft') {
            return redirect()->route('purchase.requisitions.show', $id)
                ->with('error', 'Only Draft Purchase Requisitions can be updated.');
        }

        $validated = $request->validate([
            'requisition_date' => 'required|date',
            'expected_date' => 'nullable|date',
            'source_type' => 'required|string|in:direct,so,mo,material_request,material_requirement,requisition_slip',
            'sales_order_id' => 'nullable|integer|exists:sales_orders,id',
            'production_order_id' => 'nullable|integer|exists:production_orders,id',
            'production_requisition_slip_id' => 'nullable|integer|exists:production_requisition_slips,id',
            'material_requirement_id' => 'nullable|integer|exists:material_requirements,id',
            'requisition_slip_number' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.warehouse_id' => 'nullable|integer|exists:warehouses,id',
            'items.*.quantity' => 'required|numeric|min:0.0001',
            'items.*.estimated_cost' => 'required|numeric|min:0',
        ]);

        $this->requisitionService->updateRequisition($requisition, $validated);

        return redirect()->route('purchase.requisitions.show', $id)
            ->with('success', 'Purchase Requisition updated successfully.');
    }

    public function destroy(int $id)
    {
        $requisition = $this->requisitionRepo->find($id);
        if (!$requisition) abort(404);

        if ($requisition->status !== 'Draft') {
            return redirect()->route('purchase.requisitions.show', $id)
                ->with('error', 'Only Draft Purchase Requisitions can be deleted.');
        }

        $this->requisitionRepo->delete($requisition);

        return redirect()->route('purchase.requisitions.index')
            ->with('success', 'Purchase Requisition deleted successfully.');
    }

    public function approve(int $id)
    {
        $requisition = $this->requisitionRepo->find($id);
        if (!$requisition) abort(404);

        if ($requisition->status !== 'Draft') {
            return redirect()->back()->with('error', 'Only Draft Purchase Requisitions can be approved.');
        }

        $this->requisitionRepo->update($requisition, ['status' => 'Approved']);

        return redirect()->back()->with('success', 'Purchase Requisition has been successfully approved.');
    }

    public function reject(Request $request, int $id)
    {
        $requisition = $this->requisitionRepo->find($id);
        if (!$requisition) abort(404);

        if ($requisition->status !== 'Draft') {
            return redirect()->back()->with('error', 'Only Draft Purchase Requisitions can be rejected.');
        }

        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:1000',
        ]);

        $this->requisitionRepo->update($requisition, [
            'status' => 'Cancelled',
            'rejection_reason' => $validated['rejection_reason'],
        ]);

        return redirect()->back()->with('success', 'Purchase Requisition has been rejected.');
    }

    public function getSourceItems(Request $request)
    {
        return response()->json(['success' => true, 'items' => []]);
    }

    public function pendingItems(Request $request)
    {
        $data = $this->requisitionRepo->getPendingItemsData($request->all());
        return view('modules.purchase.requisitions.pending-items', $data);
    }

    public function createPosFromPendingItems(Request $request)
    {
        $tenantId = require_tenant_id();
        $selectedItemIds = $request->input('item_ids', []);
        $actionType = $request->input('bulk_action', 'po');

        if (empty($selectedItemIds)) {
            return redirect()->back()->with('error', 'Please select at least one item.');
        }

        try {
            $res = $this->requisitionService->createPosFromPendingItems($selectedItemIds, $actionType, $tenantId);
            $redirectRoute = $res['type'] === 'po' ? 'purchase.orders.index' : 'purchase.rfqs.index';
            return redirect()->route($redirectRoute)->with('success', $res['message']);
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function remind(Request $request, int $id)
    {
        $requisition = $this->requisitionRepo->find($id);
        if (!$requisition) abort(404);

        if ($requisition->status !== 'Draft') {
            return redirect()->back()->with('error', 'Reminders can only be sent for pending Purchase Requisitions.');
        }

        if ($requisition->last_reminded_at && $requisition->last_reminded_at->diffInSeconds(now()) < 900) {
            $secondsLeft = 900 - $requisition->last_reminded_at->diffInSeconds(now());
            if ($secondsLeft >= 60) {
                $mins = (int)ceil($secondsLeft / 60);
                $timeStr = "{$mins} minute" . ($mins > 1 ? 's' : '');
            } else {
                $secs = max(1, (int)$secondsLeft);
                $timeStr = "{$secs} second" . ($secs > 1 ? 's' : '');
            }
            return redirect()->back()->with('error', "Reminder already sent recently! Please wait {$timeStr} before sending another reminder.");
        }

        $note = $request->input('note');

        \App\Domains\Purchase\Models\ApprovalReminder::create([
            'tenant_id' => require_tenant_id(),
            'remindable_type' => get_class($requisition),
            'remindable_id' => $requisition->id,
            'user_id' => auth()->id(),
            'note' => $note,
        ]);

        $requisition->increment('reminder_count');
        $requisition->update(['last_reminded_at' => now()]);

        return redirect()->back()->with('success', "Quick reminder successfully sent to approvers for PR #{$requisition->requisition_number}!");
    }
}
