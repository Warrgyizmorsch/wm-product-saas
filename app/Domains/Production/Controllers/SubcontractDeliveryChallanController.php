<?php

namespace App\Domains\Production\Controllers;

use App\Domains\Inventory\Models\Vendor;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Production\Models\DeliveryChallan;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionSubcontractReceipt;
use App\Domains\Production\Repositories\DeliveryChallanRepositoryInterface;
use App\Domains\Production\Services\SubcontractDates;
use App\Domains\Production\Services\SubcontractMaterialBalanceService;
use App\Domains\Production\Services\SubcontractReceiptService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Dispatch slips of outsourced operations (docs/subcontracting_module_audit_2026-11-26.md TF-04 / TF-06):
 * created from the operation (lines from the BOM), dispatched without any stock movement, received through the one
 * subcontract receipt (SubcontractReceiptService) — the same one the shop floor uses.
 */
class SubcontractDeliveryChallanController extends Controller
{
    public function __construct(
        private readonly DeliveryChallanRepositoryInterface $repository,
        private readonly SubcontractMaterialBalanceService $materialBalanceService
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', DeliveryChallan::class);
        $tenantId = require_tenant_id();

        $filters = $request->only(['status', 'search']);
        $challans = $this->repository->paginate($tenantId, $filters, 15);
        $statusCounts = $this->repository->getStatusCounts($tenantId);

        return view('modules.production.subcontract.challans.index', compact('challans', 'statusCounts'));
    }

    public function create(Request $request)
    {
        $this->authorize('create', DeliveryChallan::class);
        $tenantId = require_tenant_id();

        $operation = $request->filled('operation_id')
            ? ProductionOrderOperation::where('tenant_id', $tenantId)->with(['vendor', 'order.product'])->find($request->operation_id)
            : null;
        $order = $operation?->order ?? ($request->filled('production_order_id')
            ? ProductionOrder::where('tenant_id', $tenantId)->with('product')->find($request->production_order_id)
            : null);

        if ($operation) {
            $existingDraft = DeliveryChallan::where('tenant_id', $tenantId)
                ->where('production_order_operation_id', $operation->id)
                ->where('status', DeliveryChallan::STATUS_DRAFT)
                ->first();
            if ($existingDraft) {
                return redirect()->route('production.subcontract.delivery-challans.show', $existingDraft->id)
                    ->with('warning', "Draft dispatch slip {$existingDraft->challan_number} already exists for this operation — dispatch or edit it.");
            }
        }

        $unitsNotYetSent = $operation ? $this->materialBalanceService->unitsNotYetSent($operation) : 0.0;
        $defaultUnits = $operation && $operation->isWipJobWork()
            ? min($unitsNotYetSent, $this->materialBalanceService->availableWip($operation)['available_wip'])
            : $unitsNotYetSent;
        $prefilledItems = $operation ? $this->materialBalanceService->prefillLines($operation, $defaultUnits) : [];
        $prevOp = $operation && $operation->isWipJobWork() ? $this->materialBalanceService->availableWip($operation)['prev_op'] : null;
        $expectedReturn = $operation ? app(SubcontractDates::class)->expectedReturnDate($operation, now())->toDateString() : '';

        $vendor = $operation?->vendor;
        $vendors = Vendor::where('tenant_id', $tenantId)
            ->where(fn ($q) => $q->where('status', 'active')->when($vendor, fn ($q2) => $q2->orWhere('id', $vendor->id)))
            ->orderBy('name')->get();

        return view('modules.production.subcontract.challans.create', compact(
            'order', 'operation', 'vendor', 'vendors', 'prefilledItems', 'unitsNotYetSent', 'defaultUnits', 'prevOp', 'expectedReturn'
        ));
    }

    public function checkStock(Request $request)
    {
        $tenantId = require_tenant_id();
        $warehouseId = $request->query('warehouse_id');
        $productId = $request->query('product_id');

        if (!$warehouseId) {
            return response()->json(['success' => false, 'message' => 'Warehouse ID required']);
        }
        $warehouse = Warehouse::where('tenant_id', $tenantId)->find($warehouseId);
        if (!$warehouse) {
            return response()->json(['success' => false, 'message' => 'Warehouse not found']);
        }

        $stocks = \App\Domains\Inventory\Models\ProductWarehouseStock::where('tenant_id', $tenantId)
            ->where('warehouse_id', $warehouseId)
            ->when($productId, fn ($q) => $q->where('product_id', $productId))
            ->get(['product_id', 'quantity', 'available_qty', 'reserved_qty'])
            ->keyBy('product_id');

        return response()->json(['success' => true, 'warehouse' => ['id' => $warehouse->id, 'name' => $warehouse->name], 'stocks' => $stocks]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', DeliveryChallan::class);
        $tenantId = require_tenant_id();
        $tenantRule = fn (string $table) => Rule::exists($table, 'id')->where('tenant_id', $tenantId);

        // C-07: every id is checked against this tenant
        $validated = $request->validate([
            'production_order_id' => ['required', $tenantRule('production_orders')],
            'production_order_operation_id' => ['required', $tenantRule('production_order_operations')],
            'vendor_id' => ['required', $tenantRule('vendors')],
            'challan_date' => 'required|date',
            'expected_return_date' => 'nullable|date|after_or_equal:challan_date',
            'units_sent' => 'required|numeric|gt:0',
            'vehicle_number' => 'nullable|string|max:50',
            'transporter_name' => 'nullable|string|max:100',
            'lr_number' => 'nullable|string|max:50',
            'driver_name' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:2000',
            'status' => 'required|in:draft,dispatched',
            'items' => 'required|array|min:1',
            'items.*.item_type' => 'required|in:material,wip',
            'items.*.product_id' => ['required', $tenantRule('products')],
            'items.*.production_batch_id' => 'nullable|integer',
            'items.*.production_wip_id' => 'nullable|integer',
            'items.*.quantity' => 'required|numeric|min:0',
            'items.*.required_qty' => 'nullable|numeric|min:0',
            'items.*.unit_of_measure' => 'nullable|string|max:20',
        ]);

        $op = ProductionOrderOperation::where('tenant_id', $tenantId)->findOrFail($validated['production_order_operation_id']);
        if ((int) $op->production_order_id !== (int) $validated['production_order_id']) {
            return back()->withInput()->withErrors(['production_order_operation_id' => 'The operation does not belong to this order.']);
        }

        $items = collect($validated['items'])->filter(fn ($i) => (float) $i['quantity'] > 0)->values()->all();
        if (empty($items)) {
            return back()->withInput()->withErrors(['items' => 'Enter a quantity to send on at least one line.']);
        }

        if ($validated['status'] === DeliveryChallan::STATUS_DISPATCHED) {
            $problems = $this->materialBalanceService->dispatchProblems($op, (int) $validated['vendor_id'], $items, (float) $validated['units_sent']);
            if ($problems) {
                return back()->withInput()->withErrors(['items' => implode(' ', $problems)]);
            }
        }

        $challan = $this->repository->create($tenantId, array_merge($validated, ['status' => DeliveryChallan::STATUS_DRAFT]), $items);

        if ($validated['status'] === DeliveryChallan::STATUS_DISPATCHED) {
            $this->materialBalanceService->processDispatchActions($challan->fresh('items'), $tenantId);
            app(\App\Domains\Production\Services\ProductionNotificationService::class)->notifySubcontractChallanDispatched($challan->fresh());
        }

        return redirect()->route('production.subcontract.delivery-challans.show', $challan->id)
            ->with('success', $validated['status'] === DeliveryChallan::STATUS_DISPATCHED
                ? "Dispatch slip {$challan->challan_number} sent to the vendor."
                : "Draft dispatch slip {$challan->challan_number} saved.");
    }

    public function show($id)
    {
        $tenantId = require_tenant_id();
        $challan = $this->repository->findOrFail((int) $id, $tenantId);
        $this->authorize('view', $challan);

        $receipts = ProductionSubcontractReceipt::where('tenant_id', $tenantId)
            ->where('delivery_challan_id', $challan->id)
            ->with(['grn', 'receiver'])
            ->orderBy('id')
            ->get();

        return view('modules.production.subcontract.challans.show', compact('challan', 'receipts'));
    }

    public function print($id)
    {
        $tenantId = require_tenant_id();
        $challan = $this->repository->findOrFail((int) $id, $tenantId);
        $this->authorize('view', $challan);

        return view('modules.production.subcontract.challans.print', compact('challan'));
    }

    public function dispatch($id)
    {
        $tenantId = require_tenant_id();
        $challan = $this->repository->findOrFail((int) $id, $tenantId);

        if ($challan->status !== DeliveryChallan::STATUS_DRAFT) {
            return redirect()->back()->with('warning', "Dispatch slip {$challan->challan_number} is {$challan->status}: only a draft can be dispatched.");
        }
        $this->authorize('dispatch', $challan);

        $op = $challan->operation;
        if (!$op) {
            return redirect()->back()->with('error', 'This slip has no outsourced operation.');
        }

        $items = $challan->items->map(fn ($i) => ['item_type' => $i->item_type, 'product_id' => $i->product_id, 'quantity' => (float) $i->quantity])->all();
        $problems = $this->materialBalanceService->dispatchProblems($op, (int) $challan->vendor_id, $items, (float) $challan->dispatched_wip_qty, $challan->id);
        if ($problems) {
            return redirect()->back()->with('error', implode(' ', $problems));
        }

        $this->materialBalanceService->processDispatchActions($challan, $tenantId);
        app(\App\Domains\Production\Services\ProductionNotificationService::class)->notifySubcontractChallanDispatched($challan->fresh());

        return redirect()->back()->with('success', "Dispatch slip {$challan->challan_number} sent to the vendor.");
    }

    /** SC-02 / TF-06: the Subcontracting-menu receipt — the same receipt as the shop floor */
    public function receive(Request $request, $id)
    {
        $tenantId = require_tenant_id();
        $challan = $this->repository->findOrFail((int) $id, $tenantId);

        if (!$challan->isAtVendor()) {
            return redirect()->back()->with('warning', "Dispatch slip {$challan->challan_number} is {$challan->status}: nothing can be received on it.");
        }
        $this->authorize('receive', $challan);

        $validated = $request->validate([
            'received_qty' => 'required|numeric|gt:0',
            'rejected_qty' => 'nullable|numeric|min:0',
            'scrapped_qty' => 'nullable|numeric|min:0',
            'remarks' => 'nullable|string|max:1000',
            'returns' => 'nullable|array',
            'returns.*' => 'nullable|numeric|min:0',
            'final' => 'nullable|boolean',
        ]);

        $op = $challan->operation;
        if (!$op) {
            return redirect()->back()->with('error', 'This slip has no outsourced operation.');
        }

        try {
            $receipt = app(SubcontractReceiptService::class)->receive($op, $validated, (int) auth()->id(), ProductionSubcontractReceipt::SOURCE_CHALLAN, $challan);
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('production.subcontract.delivery-challans.show', $challan->id)->with('success',
            $receipt->status === ProductionSubcontractReceipt::STATUS_QC_PENDING
                ? "Receipt {$receipt->receipt_number} recorded — the units wait for this operation's QC on the shop floor."
                : "Receipt {$receipt->receipt_number} recorded: good units booked, subcontract PO updated.");
    }
}
