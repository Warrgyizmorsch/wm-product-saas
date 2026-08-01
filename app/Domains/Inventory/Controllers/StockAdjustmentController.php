<?php

namespace App\Domains\Inventory\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Inventory\Models\StockAdjustment;
use App\Domains\Inventory\Models\StockAdjustmentItem;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class StockAdjustmentController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = current_tenant_id() ?? tenant_id() ?? 1;

        $query = StockAdjustment::query()
            ->with(['warehouse', 'creator', 'items.product'])
            ->where('tenant_id', $tenantId);

        if ($request->filled('reason')) {
            $query->where('reason', $request->reason);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->warehouse_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('adjustment_number', 'LIKE', "%{$search}%");
        }

        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');

        if (in_array($sortBy, ['adjustment_number', 'adjustment_date', 'status', 'created_at'])) {
            $query->orderBy($sortBy, $sortOrder === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        $adjustments = $query->paginate(15);
        $warehouses = Warehouse::where('tenant_id', $tenantId)->get();

        return view('modules.inventory.adjustments.index', compact('adjustments', 'warehouses'));
    }

    public function create()
    {
        $tenantId = current_tenant_id() ?? tenant_id() ?? 1;
        $warehouses = Warehouse::where('tenant_id', $tenantId)->get();
        $products = Product::where('tenant_id', $tenantId)->sellable()->get();

        return view('modules.inventory.adjustments.create', compact('warehouses', 'products'));
    }

    public function store(Request $request)
    {
        $tenantId = current_tenant_id() ?? tenant_id() ?? 1;

        $validated = $request->validate([
            'warehouse_id' => 'required|exists:warehouses,id',
            'adjustment_date' => 'required|date',
            'reason' => 'required|in:Damaged,Expired,Stock Count Variance,Theft/Loss,Scrap,Sample,Other',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.type' => 'required|in:Addition,Deduction',
            'items.*.quantity' => 'required|numeric|gt:0',
            'items.*.unit_cost' => 'nullable|numeric|min:0',
        ]);

        DB::transaction(function () use ($validated, $tenantId) {
            $adjNumber = 'ADJ-' . strtoupper(uniqid());

            $adjustment = StockAdjustment::create([
                'tenant_id' => $tenantId,
                'adjustment_number' => $adjNumber,
                'warehouse_id' => $validated['warehouse_id'],
                'adjustment_date' => $validated['adjustment_date'],
                'reason' => $validated['reason'],
                'status' => 'Draft',
                'notes' => $validated['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);

            foreach ($validated['items'] as $item) {
                $product = Product::find($item['product_id']);
                $unitCost = isset($item['unit_cost']) && $item['unit_cost'] > 0 
                    ? (float)$item['unit_cost'] 
                    : (float)($product->cost_price ?? 0);
                $total = (float)$item['quantity'] * $unitCost;

                StockAdjustmentItem::create([
                    'stock_adjustment_id' => $adjustment->id,
                    'product_id' => $item['product_id'],
                    'type' => $item['type'],
                    'quantity' => $item['quantity'],
                    'unit_cost' => $unitCost,
                    'total_amount' => $total,
                    'batch_id' => $item['batch_id'] ?? null,
                    'serial_numbers' => !empty($item['serial_numbers']) ? explode(',', $item['serial_numbers']) : null,
                ]);
            }
        });

        return redirect()->route('inventory.adjustments.index')->with('success', 'Stock Adjustment Created successfully.');
    }

    public function show(StockAdjustment $adjustment)
    {
        $adjustment->load(['warehouse', 'creator', 'approver', 'items.product', 'items.batch']);
        return view('modules.inventory.adjustments.show', compact('adjustment'));
    }

    public function approve(StockAdjustment $adjustment)
    {
        if ($adjustment->status !== 'Draft') {
            return back()->with('error', 'Only Draft adjustments can be approved.');
        }

        $tenantId = current_tenant_id() ?? tenant_id() ?? 1;

        DB::transaction(function () use ($adjustment, $tenantId) {
            foreach ($adjustment->items as $item) {
                if ($item->type === 'Addition') {
                    StockService::recordInflow(
                        tenantId: $tenantId,
                        productId: $item->product_id,
                        warehouseId: $adjustment->warehouse_id,
                        quantity: (float)$item->quantity,
                        unitCost: (float)$item->unit_cost,
                        referenceType: 'StockAdjustment',
                        referenceId: $adjustment->id,
                        serialNumbers: $item->serial_numbers ?? []
                    );
                } else {
                    StockService::recordOutflow(
                        tenantId: $tenantId,
                        productId: $item->product_id,
                        warehouseId: $adjustment->warehouse_id,
                        quantity: (float)$item->quantity,
                        referenceType: 'StockAdjustment',
                        referenceId: $adjustment->id,
                        serialNumbers: $item->serial_numbers ?? []
                    );
                }
            }

            $adjustment->update([
                'status' => 'Approved',
                'approved_by' => Auth::id(),
            ]);
        });

        return back()->with('success', 'Stock Adjustment Approved and inventory updated.');
    }

    public function cancel(StockAdjustment $adjustment)
    {
        if ($adjustment->status === 'Approved') {
            return back()->with('error', 'Cannot cancel an approved adjustment.');
        }

        $adjustment->update(['status' => 'Cancelled']);
        return back()->with('success', 'Stock Adjustment cancelled.');
    }
}
