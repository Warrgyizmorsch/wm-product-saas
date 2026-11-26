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
use Illuminate\Validation\Rule;
use App\Exports\StockAdjustmentExport;
use Maatwebsite\Excel\Facades\Excel;

class StockAdjustmentController extends Controller
{
    /**
     * Export Stock Adjustments to Excel with custom columns and active query filters
     */
    public function export(Request $request)
    {
        $this->authorize('viewAny', StockAdjustment::class);
        $tenantId = current_tenant_id() ?? tenant_id() ?? auth()->user()?->tenant_id ?? 1;

        return Excel::download(
            new StockAdjustmentExport($tenantId, $request->all()),
            'stock_adjustments_export_' . date('Y-m-d_His') . '.xlsx'
        );
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', StockAdjustment::class);

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
        $this->authorize('create', StockAdjustment::class);

        $tenantId = current_tenant_id() ?? tenant_id() ?? 1;
        $warehouses = Warehouse::where('tenant_id', $tenantId)->get();
        $products = Product::where('tenant_id', $tenantId)->sellable()->get();

        return view('modules.inventory.adjustments.create', compact('warehouses', 'products'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', StockAdjustment::class);

        $tenantId = current_tenant_id() ?? tenant_id() ?? 1;

        $validated = $request->validate([
            'warehouse_id' => [
                'required',
                Rule::exists('warehouses', 'id')->where('tenant_id', $tenantId),
            ],
            'adjustment_date' => 'required|date',
            'reason' => 'required|in:Damaged,Expired,Stock Count Variance,Theft/Loss,Scrap,Sample,Other',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => [
                'required',
                Rule::exists('products', 'id')->where('tenant_id', $tenantId),
            ],
            'items.*.type' => 'required|in:Addition,Deduction',
            'items.*.quantity' => 'required|numeric|gt:0',
            'items.*.unit_cost' => 'nullable|numeric|min:0',
            'items.*.batch_id' => 'nullable',
            'items.*.batch_number' => 'nullable|string',
            'items.*.expiry_date' => 'nullable|date',
            'items.*.serial_numbers' => 'nullable',
        ]);

        // Validate available stock for Deduction items
        foreach ($validated['items'] as $item) {
            if ($item['type'] === 'Deduction') {
                $stock = \App\Domains\Inventory\Models\ProductWarehouseStock::where('tenant_id', $tenantId)
                    ->where('product_id', $item['product_id'])
                    ->where('warehouse_id', $validated['warehouse_id'])
                    ->first();
                $availQty = $stock ? (float)$stock->available_qty : 0.0;
                if ((float)$item['quantity'] > ($availQty + 0.0001)) {
                    $product = Product::where('tenant_id', $tenantId)->find($item['product_id']);
                    $prodName = $product ? $product->name : 'Product #' . $item['product_id'];
                    return back()->withInput()->with('error', "Cannot deduct {$item['quantity']} units of '{$prodName}'. Only {$availQty} units available in selected warehouse.");
                }
            }
        }

        $adjustment = DB::transaction(function () use ($validated, $tenantId) {
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
                $product = Product::where('tenant_id', $tenantId)->find($item['product_id']);
                $unitCost = isset($item['unit_cost']) && $item['unit_cost'] > 0 
                    ? convert_to_base((float)$item['unit_cost']) 
                    : (float)($product?->cost_price ?? 0);
                $total = (float)$item['quantity'] * $unitCost;

                // Handle batch creation or linking for Addition items
                $batchId = $item['batch_id'] ?? null;
                if (!$batchId && !empty($item['batch_number'])) {
                    $batchNumber = trim($item['batch_number']);
                    $batch = \App\Domains\Inventory\Models\Batch::firstOrCreate(
                        [
                            'tenant_id' => $tenantId,
                            'product_id' => $item['product_id'],
                            'warehouse_id' => $validated['warehouse_id'],
                            'batch_number' => $batchNumber,
                        ],
                        [
                            'quantity' => 0,
                            'available_qty' => 0,
                            'manufacturing_date' => now(),
                            'expiry_date' => !empty($item['expiry_date']) ? \Carbon\Carbon::parse($item['expiry_date']) : now()->addYear(),
                        ]
                    );
                    $batchId = $batch->id;
                }

                // Handle serial numbers parsing
                $serialNumbers = null;
                if (!empty($item['serial_numbers'])) {
                    if (is_array($item['serial_numbers'])) {
                        $serialNumbers = array_values(array_filter(array_map('trim', $item['serial_numbers'])));
                    } else {
                        $serialNumbers = array_values(array_filter(array_map('trim', preg_split('/[\r\n,;]+/', (string)$item['serial_numbers']))));
                    }
                }

                StockAdjustmentItem::create([
                    'stock_adjustment_id' => $adjustment->id,
                    'product_id' => $item['product_id'],
                    'type' => $item['type'],
                    'quantity' => $item['quantity'],
                    'unit_cost' => $unitCost,
                    'total_amount' => $total,
                    'batch_id' => $batchId,
                    'serial_numbers' => $serialNumbers,
                ]);
            }

            return $adjustment;
        });

        \App\Domains\Inventory\Services\InventoryNotificationService::notifyAdjustmentPending($adjustment);

        return redirect()->route('inventory.adjustments.index')->with('success', 'Stock Adjustment Created successfully.');
    }

    public function show(StockAdjustment $adjustment)
    {
        $this->authorize('view', $adjustment);

        $adjustment->load(['warehouse', 'creator', 'approver', 'items.product', 'items.batch']);
        return view('modules.inventory.adjustments.show', compact('adjustment'));
    }

    public function approve(StockAdjustment $adjustment)
    {
        $this->authorize('approve', $adjustment);

        if ($adjustment->status !== 'Draft') {
            return back()->with('error', 'Only Draft adjustments can be approved.');
        }

        $tenantId = current_tenant_id() ?? tenant_id() ?? 1;

        DB::transaction(function () use ($adjustment, $tenantId) {
            foreach ($adjustment->items as $item) {
                if ($item->type === 'Addition') {
                    $batchNumber = $item->batch?->batch_number;
                    $mfgDate = $item->batch?->manufacturing_date ? $item->batch->manufacturing_date->format('Y-m-d') : null;
                    $expiryDate = $item->batch?->expiry_date ? $item->batch->expiry_date->format('Y-m-d') : null;

                    StockService::recordInflow(
                        tenantId: $tenantId,
                        productId: $item->product_id,
                        warehouseId: $adjustment->warehouse_id,
                        quantity: (float)$item->quantity,
                        unitCost: (float)$item->unit_cost,
                        referenceType: 'StockAdjustment',
                        referenceId: $adjustment->id,
                        batchNumber: $batchNumber,
                        serialNumbers: $item->serial_numbers ?? [],
                        mfgDate: $mfgDate,
                        expiryDate: $expiryDate
                    );
                } else {
                    StockService::recordOutflow(
                        tenantId: $tenantId,
                        productId: $item->product_id,
                        warehouseId: $adjustment->warehouse_id,
                        quantity: (float)$item->quantity,
                        referenceType: 'StockAdjustment',
                        referenceId: $adjustment->id,
                        serialNumbers: $item->serial_numbers ?? [],
                        batchId: $item->batch_id
                    );
                }
            }

            $adjustment->update([
                'status' => 'Approved',
                'approved_by' => Auth::id(),
            ]);

            $this->postAdjustmentAccountingJournal($adjustment, $tenantId);
        });

        \App\Domains\Inventory\Services\InventoryNotificationService::notifyAdjustmentApproved($adjustment);

        return back()->with('success', 'Stock Adjustment Approved and inventory updated.');
    }

    protected function postAdjustmentAccountingJournal(StockAdjustment $adjustment, int $tenantId): void
    {
        try {
            if (!class_exists(\App\Domains\Accounting\Services\JournalService::class) || !class_exists(\App\Domains\Accounting\Services\AccountResolverService::class)) {
                return;
            }

            $journalService = app(\App\Domains\Accounting\Services\JournalService::class);
            $accountResolver = app(\App\Domains\Accounting\Services\AccountResolverService::class);

            $lines = [];
            $totalAmount = 0.0;

            foreach ($adjustment->items as $item) {
                $product = $item->product ?? Product::find($item->product_id);
                $itemAmount = (float)($item->total_amount > 0 ? $item->total_amount : ((float)$item->quantity * (float)$item->unit_cost));
                if ($itemAmount <= 0) continue;

                $inventoryAcc = $accountResolver->resolveInventoryAccount($product, $tenantId);
                $expenseAcc = $accountResolver->resolveCogsAccount($product, $tenantId);

                if (!$inventoryAcc || !$expenseAcc) {
                    continue;
                }

                if ($item->type === 'Addition') {
                    // Addition: Debit Inventory Asset, Credit Inventory Adjustment Gain / Cost Expense
                    $lines[] = [
                        'chart_of_account_id' => $inventoryAcc->id,
                        'debit' => $itemAmount,
                        'credit' => 0,
                        'description' => "Stock Adjustment Gain - {$adjustment->adjustment_number} (" . ($product?->name ?? 'Item') . ")",
                    ];
                    $lines[] = [
                        'chart_of_account_id' => $expenseAcc->id,
                        'debit' => 0,
                        'credit' => $itemAmount,
                        'description' => "Stock Adjustment Gain - {$adjustment->adjustment_number} (" . ($product?->name ?? 'Item') . ")",
                    ];
                } else {
                    // Deduction: Debit Shrinkage/Loss Expense, Credit Inventory Asset
                    $lines[] = [
                        'chart_of_account_id' => $expenseAcc->id,
                        'debit' => $itemAmount,
                        'credit' => 0,
                        'description' => "Stock Adjustment Loss/Shrinkage - {$adjustment->adjustment_number} (" . ($product?->name ?? 'Item') . ")",
                    ];
                    $lines[] = [
                        'chart_of_account_id' => $inventoryAcc->id,
                        'debit' => 0,
                        'credit' => $itemAmount,
                        'description' => "Stock Adjustment Loss/Shrinkage - {$adjustment->adjustment_number} (" . ($product?->name ?? 'Item') . ")",
                    ];
                }
                $totalAmount += $itemAmount;
            }

            if (!empty($lines) && $totalAmount > 0) {
                $journalService->postOnce($lines, [
                    'tenant_id' => $tenantId,
                    'journal_date' => $adjustment->adjustment_date ?? now(),
                    'source' => defined('\App\Domains\Accounting\Models\Journal::SOURCE_INVENTORY') ? \App\Domains\Accounting\Models\Journal::SOURCE_INVENTORY : 'inventory',
                    'reference_type' => 'StockAdjustment',
                    'reference_id' => $adjustment->id,
                    'notes' => 'Stock adjustment ' . $adjustment->adjustment_number . ' - ' . ($adjustment->reason ?? 'Manual Adjustment'),
                ]);
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("StockAdjustmentController: Accounting journal posting skipped: " . $e->getMessage());
        }
    }

    public function cancel(StockAdjustment $adjustment)
    {
        $this->authorize('delete', $adjustment);

        if ($adjustment->status === 'Approved') {
            return back()->with('error', 'Cannot cancel an approved adjustment.');
        }

        $adjustment->update(['status' => 'Cancelled']);

        \App\Domains\Inventory\Services\InventoryNotificationService::notifyAdjustmentCancelled($adjustment);

        return back()->with('success', 'Stock Adjustment cancelled.');
    }
}
