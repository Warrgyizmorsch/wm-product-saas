<?php

namespace App\Domains\Purchase\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Purchase\Models\PurchaseRequisition;
use App\Domains\Purchase\Models\PurchaseRequisitionItem;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionRequisitionSlip;
use App\Domains\Sales\Models\MaterialRequirement;
use App\Domains\Sales\Models\SalesOrder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PurchaseRequisitionController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = require_tenant_id();

        $query = PurchaseRequisition::where('tenant_id', $tenantId)
            ->with(['requester', 'sourceable']);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('source_type')) {
            $query->where('source_type', $request->input('source_type'));
        }

        if ($request->filled('search')) {
            $search = '%' . $request->input('search') . '%';
            $query->where('requisition_number', 'like', $search);
        }

        $sortBy = $request->input('sort_by', 'id');
        $sortOrder = $request->input('sort_order', 'desc');
        $allowedSorts = ['id', 'requisition_number', 'requisition_date', 'status'];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('id', 'desc');
        }

        $requisitions = $query->paginate(10)->withQueryString();

        return view('modules.purchase.requisitions.index', compact('requisitions'));
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

        // Resolve polymorphic source_id
        $sourceId = null;
        if ($validated['source_type'] === 'so') {
            $sourceId = $validated['sales_order_id'] ?? null;
        } elseif ($validated['source_type'] === 'mo') {
            $sourceId = $validated['production_order_id'] ?? null;
        } elseif ($validated['source_type'] === 'material_request') {
            $sourceId = $validated['production_requisition_slip_id'] ?? null;
        } elseif ($validated['source_type'] === 'material_requirement') {
            $sourceId = $validated['material_requirement_id'] ?? null;
        }

        return DB::transaction(function () use ($validated, $sourceId, $tenantId) {
            // Generate sequence number YYYY-000001
            $year = now()->format('Y');
            $prefix = "PR-{$year}-";
            $lastPr = PurchaseRequisition::where('tenant_id', $tenantId)
                ->where('requisition_number', 'like', "{$prefix}%")
                ->orderBy('id', 'desc')
                ->first();
            $nextNum = 1;
            if ($lastPr) {
                $lastNumStr = str_replace($prefix, '', $lastPr->requisition_number);
                $nextNum = ((int) $lastNumStr) + 1;
            }
            $requisitionNumber = $prefix . str_pad($nextNum, 6, '0', STR_PAD_LEFT);

            $pr = PurchaseRequisition::create([
                'tenant_id' => $tenantId,
                'requisition_number' => $requisitionNumber,
                'requisition_date' => $validated['requisition_date'],
                'status' => 'Draft',
                'source_type' => $validated['source_type'],
                'source_id' => $sourceId,
                'requisition_slip_number' => $validated['requisition_slip_number'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'requested_by' => auth()->id() ?: 1,
            ]);

            foreach ($validated['items'] as $item) {
                PurchaseRequisitionItem::create([
                    'purchase_requisition_id' => $pr->id,
                    'product_id' => $item['product_id'],
                    'warehouse_id' => $item['warehouse_id'] ?? null,
                    'quantity' => $item['quantity'],
                    'estimated_cost' => $item['estimated_cost'],
                ]);
            }

            return redirect()->route('purchase.requisitions.show', $pr->id)
                ->with('success', "Purchase Requisition {$requisitionNumber} created successfully.");
        });
    }

    public function show(int $id)
    {
        $tenantId = require_tenant_id();

        $requisition = PurchaseRequisition::where('tenant_id', $tenantId)
            ->with([
                'requester',
                'sourceable',
                'items.product',
                'items.warehouse'
            ])
            ->findOrFail($id);

        return view('modules.purchase.requisitions.show', compact('requisition'));
    }

    public function edit(int $id)
    {
        $tenantId = require_tenant_id();

        $requisition = PurchaseRequisition::where('tenant_id', $tenantId)
            ->with('items')
            ->findOrFail($id);

        if ($requisition->status !== 'Draft') {
            return redirect()->route('purchase.requisitions.show', $id)
                ->with('error', 'Only Draft Purchase Requisitions can be edited.');
        }

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
        $tenantId = require_tenant_id();

        $requisition = PurchaseRequisition::where('tenant_id', $tenantId)
            ->findOrFail($id);

        if ($requisition->status !== 'Draft') {
            return redirect()->route('purchase.requisitions.show', $id)
                ->with('error', 'Only Draft Purchase Requisitions can be updated.');
        }

        $validated = $request->validate([
            'requisition_date' => 'required|date',
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

        // Resolve polymorphic source_id
        $sourceId = null;
        if ($validated['source_type'] === 'so') {
            $sourceId = $validated['sales_order_id'] ?? null;
        } elseif ($validated['source_type'] === 'mo') {
            $sourceId = $validated['production_order_id'] ?? null;
        } elseif ($validated['source_type'] === 'material_request') {
            $sourceId = $validated['production_requisition_slip_id'] ?? null;
        } elseif ($validated['source_type'] === 'material_requirement') {
            $sourceId = $validated['material_requirement_id'] ?? null;
        }

        return DB::transaction(function () use ($validated, $sourceId, $requisition) {
            $requisition->update([
                'requisition_date' => $validated['requisition_date'],
                'source_type' => $validated['source_type'],
                'source_id' => $sourceId,
                'requisition_slip_number' => $validated['requisition_slip_number'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);

            // Re-create items to avoid complex diff logic
            $requisition->items()->delete();

            foreach ($validated['items'] as $item) {
                PurchaseRequisitionItem::create([
                    'purchase_requisition_id' => $requisition->id,
                    'product_id' => $item['product_id'],
                    'warehouse_id' => $item['warehouse_id'] ?? null,
                    'quantity' => $item['quantity'],
                    'estimated_cost' => $item['estimated_cost'],
                ]);
            }

            return redirect()->route('purchase.requisitions.show', $requisition->id)
                ->with('success', 'Purchase Requisition updated successfully.');
        });
    }

    public function destroy(int $id)
    {
        $tenantId = require_tenant_id();

        $requisition = PurchaseRequisition::where('tenant_id', $tenantId)
            ->findOrFail($id);

        if ($requisition->status !== 'Draft') {
            return redirect()->route('purchase.requisitions.show', $id)
                ->with('error', 'Only Draft Purchase Requisitions can be deleted.');
        }

        DB::transaction(function () use ($requisition) {
            $requisition->items()->delete();
            $requisition->delete();
        });

        return redirect()->route('purchase.requisitions.index')
            ->with('success', 'Purchase Requisition deleted successfully.');
    }

    public function approve(int $id)
    {
        $tenantId = require_tenant_id();

        $requisition = PurchaseRequisition::where('tenant_id', $tenantId)
            ->findOrFail($id);

        if ($requisition->status !== 'Draft') {
            return redirect()->route('purchase.requisitions.show', $id)
                ->with('error', 'Only Draft Purchase Requisitions can be approved.');
        }

        $requisition->update([
            'status' => 'Approved',
        ]);

        return redirect()->route('purchase.requisitions.show', $id)
            ->with('success', 'Purchase Requisition has been successfully approved.');
    }
}
