<?php

namespace App\Domains\Purchase\Services;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\ProductWarehouseStock;
use App\Domains\Inventory\Models\StockTransaction;
use App\Domains\Purchase\Models\GoodsReceiptNote;
use App\Domains\Purchase\Models\GoodsReceiptNoteItem;
use App\Domains\Purchase\Models\LandedCostVoucher;
use App\Domains\Purchase\Models\LandedCostReceipt;
use App\Domains\Purchase\Models\LandedCostExpense;
use App\Domains\Purchase\Models\LandedCostItem;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class LandedCostService
{
    public function createVoucher(int $tenantId, array $data): LandedCostVoucher
    {
        return DB::transaction(function () use ($tenantId, $data) {
            $grnIds = $data['grn_ids'] ?? [];
            if (empty($grnIds)) {
                throw new InvalidArgumentException('Please select at least one Goods Receipt Note (GRN).');
            }

            $expensesData = $data['expenses'] ?? [];
            if (empty($expensesData)) {
                throw new InvalidArgumentException('Please add at least one expense line.');
            }

            // Generate Voucher Number
            $year = now()->format('Y');
            $prefix = "LCV-{$year}-";
            $lastVoucher = LandedCostVoucher::where('tenant_id', $tenantId)
                ->where('voucher_number', 'like', "{$prefix}%")
                ->orderBy('id', 'desc')
                ->first();

            $nextNum = 1;
            if ($lastVoucher) {
                $lastNumStr = str_replace($prefix, '', $lastVoucher->voucher_number);
                $nextNum = ((int) $lastNumStr) + 1;
            }
            $voucherNumber = $prefix . str_pad($nextNum, 6, '0', STR_PAD_LEFT);

            $voucher = LandedCostVoucher::create([
                'tenant_id'       => $tenantId,
                'voucher_number'  => $voucherNumber,
                'voucher_date'    => $data['voucher_date'] ?? now()->toDateString(),
                'status'          => 'Draft',
                'total_expenses'  => 0.0000,
                'notes'           => $data['notes'] ?? null,
                'created_by'      => auth()->id() ?: 1,
            ]);

            // Save Receipt links
            foreach ($grnIds as $grnId) {
                LandedCostReceipt::create([
                    'tenant_id'              => $tenantId,
                    'landed_cost_voucher_id' => $voucher->id,
                    'goods_receipt_note_id'  => (int) $grnId,
                ]);
            }

            // Save Expense lines & sum total
            $totalExpenses = 0.0;
            foreach ($expensesData as $exp) {
                $amt = (float) ($exp['amount'] ?? 0);
                if ($amt <= 0) continue;

                $totalExpenses += $amt;
                LandedCostExpense::create([
                    'tenant_id'              => $tenantId,
                    'landed_cost_voucher_id' => $voucher->id,
                    'cost_head'              => $exp['cost_head'] ?? 'Freight',
                    'vendor_id'              => !empty($exp['vendor_id']) ? (int) $exp['vendor_id'] : null,
                    'amount'                 => $amt,
                    'allocation_basis'       => $exp['allocation_basis'] ?? 'by_qty',
                    'description'            => $exp['description'] ?? null,
                ]);
            }

            $voucher->total_expenses = $totalExpenses;
            $voucher->save();

            // Calculate and create allocated items
            $this->calculateAndCreateItems($voucher, $tenantId, $grnIds);

            return $voucher;
        });
    }

    public function postVoucher(int $tenantId, int $voucherId): LandedCostVoucher
    {
        return DB::transaction(function () use ($tenantId, $voucherId) {
            $voucher = LandedCostVoucher::where('tenant_id', $tenantId)
                ->with(['items.goodsReceiptNote', 'items.product'])
                ->findOrFail($voucherId);

            if ($voucher->status !== 'Draft') {
                throw new InvalidArgumentException("Voucher is already {$voucher->status}.");
            }

            // Track which product+warehouse pairs need warehouse summary recalculation
            $affectedProductWarehouse = [];

            foreach ($voucher->items as $item) {
                $grn = $item->goodsReceiptNote;
                if (!$grn) continue;

                $warehouseId        = $grn->warehouse_id;
                $productId          = $item->product_id;
                $grnId              = $item->goods_receipt_note_id;
                $allocatedCostTotal = (float) $item->allocated_cost; // total extra cost for this GRN item

                if ($allocatedCostTotal <= 0 || !$warehouseId) continue;

                // ── Step 1: Update ONLY the specific GRN's stock_transaction entry ──
                // This ensures Opening Stock and other GRN batches are completely untouched.
                $stockTxn = StockTransaction::where('tenant_id', $tenantId)
                    ->where('product_id', $productId)
                    ->where('warehouse_id', $warehouseId)
                    ->where('type', 'IN')
                    ->where('reference_type', 'Purchase Receipt')
                    ->where('reference_id', $grnId)
                    ->first();

                if ($stockTxn) {
                    $batchQty      = (float) $stockTxn->quantity;
                    $perUnitExtra  = $batchQty > 0 ? ($allocatedCostTotal / $batchQty) : 0.0;
                    $newUnitCost   = (float) $stockTxn->unit_cost + $perUnitExtra;
                    $newTotalValue = $newUnitCost * $batchQty;

                    $stockTxn->unit_cost   = $newUnitCost;
                    $stockTxn->total_value = $newTotalValue;
                    $stockTxn->save();
                }

                // Track unique product+warehouse combinations for step 2
                $key = "{$productId}_{$warehouseId}";
                $affectedProductWarehouse[$key] = [
                    'product_id'   => $productId,
                    'warehouse_id' => $warehouseId,
                ];
            }

            // ── Step 2: Recalculate warehouse unit_cost as FIFO weighted average ──
            // Formula: total value of all unconsumed IN batches / total balance qty
            // This correctly blends Opening Stock (₹100) with updated GRN batch (₹220).
            foreach ($affectedProductWarehouse as $pair) {
                $productId   = $pair['product_id'];
                $warehouseId = $pair['warehouse_id'];

                $inTxns = StockTransaction::where('tenant_id', $tenantId)
                    ->where('product_id', $productId)
                    ->where('warehouse_id', $warehouseId)
                    ->where('type', 'IN')
                    ->where('balance_qty', '>', 0)
                    ->get();

                $totalValue = $inTxns->sum(fn($t) => (float) $t->unit_cost * (float) $t->balance_qty);
                $totalQty   = $inTxns->sum(fn($t) => (float) $t->balance_qty);
                $newAvgCost = ($totalQty > 0) ? ($totalValue / $totalQty) : 0.0;

                // Update warehouse stock summary unit_cost
                $stock = ProductWarehouseStock::where('tenant_id', $tenantId)
                    ->where('product_id', $productId)
                    ->where('warehouse_id', $warehouseId)
                    ->first();

                if ($stock) {
                    $stock->unit_cost = $newAvgCost;
                    $stock->save();
                }

                // Update product master cost_price with new weighted average
                $product = Product::find($productId);
                if ($product) {
                    $product->unit_cost  = $newAvgCost;
                    $product->cost_price = $newAvgCost;
                    $product->save();
                }
            }

            $voucher->status       = 'Posted';
            $voucher->posting_date = now()->toDateString();
            $voucher->posted_by    = auth()->id() ?: 1;
            $voucher->posted_at    = now();
            $voucher->save();

            // ── Step 3: Post GL Journal Entry for Landed Cost Revaluation ──
            try {
                $inventoryAcc = \App\Domains\Accounting\Models\ChartOfAccount::where('tenant_id', $tenantId)->where('code', '1200')->first();
                $expenseAcc = \App\Domains\Accounting\Models\ChartOfAccount::where('tenant_id', $tenantId)->where('code', '5030')->first()
                    ?: (\App\Domains\Accounting\Models\ChartOfAccount::where('tenant_id', $tenantId)->where('code', '5900')->first());

                if ($inventoryAcc && $expenseAcc && (float)$voucher->total_expenses > 0) {
                    $journalService = app(\App\Domains\Accounting\Services\JournalService::class);
                    $journalService->post([
                        [
                            'chart_of_account_id' => $inventoryAcc->id,
                            'debit'               => round((float)$voucher->total_expenses, 2),
                            'credit'              => 0,
                            'description'         => "Landed Cost Revaluation - Voucher {$voucher->voucher_number}",
                        ],
                        [
                            'chart_of_account_id' => $expenseAcc->id,
                            'debit'               => 0,
                            'credit'              => round((float)$voucher->total_expenses, 2),
                            'description'         => "Freight Expense Allocation - Voucher {$voucher->voucher_number}",
                        ],
                    ], [
                        'tenant_id'      => $tenantId,
                        'journal_date'   => $voucher->voucher_date,
                        'source'         => \App\Domains\Accounting\Models\Journal::SOURCE_PURCHASE,
                        'reference_type' => 'landed_cost_voucher',
                        'reference_id'   => $voucher->id,
                        'memo'           => "Landed Cost Allocation Voucher {$voucher->voucher_number}",
                    ]);
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("LandedCostService: GL posting failed for voucher {$voucher->voucher_number}: " . $e->getMessage());
            }

            return $voucher;
        });
    }

    public function cancelVoucher(int $tenantId, int $voucherId): LandedCostVoucher
    {
        return DB::transaction(function () use ($tenantId, $voucherId) {
            $voucher = LandedCostVoucher::where('tenant_id', $tenantId)
                ->with(['items.goodsReceiptNote', 'items.product'])
                ->findOrFail($voucherId);

            if ($voucher->status === 'Cancelled') {
                throw new InvalidArgumentException("Voucher is already Cancelled.");
            }

            if ($voucher->status === 'Posted') {
                // Track affected pairs
                $affectedProductWarehouse = [];

                foreach ($voucher->items as $item) {
                    $grn = $item->goodsReceiptNote;
                    if (!$grn) continue;

                    $warehouseId        = $grn->warehouse_id;
                    $productId          = $item->product_id;
                    $grnId              = $item->goods_receipt_note_id;
                    $allocatedCostTotal = (float) $item->allocated_cost;

                    if ($allocatedCostTotal <= 0 || !$warehouseId) continue;

                    // ── Revert: Remove landed cost from the specific GRN stock_transaction entry ──
                    $stockTxn = StockTransaction::where('tenant_id', $tenantId)
                        ->where('product_id', $productId)
                        ->where('warehouse_id', $warehouseId)
                        ->where('type', 'IN')
                        ->where('reference_type', 'Purchase Receipt')
                        ->where('reference_id', $grnId)
                        ->first();

                    if ($stockTxn) {
                        $batchQty     = (float) $stockTxn->quantity;
                        $perUnitExtra = $batchQty > 0 ? ($allocatedCostTotal / $batchQty) : 0.0;
                        $revertedCost = max(0.0, (float) $stockTxn->unit_cost - $perUnitExtra);

                        $stockTxn->unit_cost   = $revertedCost;
                        $stockTxn->total_value = $revertedCost * $batchQty;
                        $stockTxn->save();
                    }

                    $key = "{$productId}_{$warehouseId}";
                    $affectedProductWarehouse[$key] = [
                        'product_id'   => $productId,
                        'warehouse_id' => $warehouseId,
                    ];
                }

                // Recalculate warehouse weighted average after revert
                foreach ($affectedProductWarehouse as $pair) {
                    $productId   = $pair['product_id'];
                    $warehouseId = $pair['warehouse_id'];

                    $inTxns = StockTransaction::where('tenant_id', $tenantId)
                        ->where('product_id', $productId)
                        ->where('warehouse_id', $warehouseId)
                        ->where('type', 'IN')
                        ->where('balance_qty', '>', 0)
                        ->get();

                    $totalValue = $inTxns->sum(fn($t) => (float) $t->unit_cost * (float) $t->balance_qty);
                    $totalQty   = $inTxns->sum(fn($t) => (float) $t->balance_qty);
                    $newAvgCost = ($totalQty > 0) ? ($totalValue / $totalQty) : 0.0;

                    $stock = ProductWarehouseStock::where('tenant_id', $tenantId)
                        ->where('product_id', $productId)
                        ->where('warehouse_id', $warehouseId)
                        ->first();

                    if ($stock) {
                        $stock->unit_cost = $newAvgCost;
                        $stock->save();
                    }

                    $product = Product::find($productId);
                    if ($product) {
                        $product->unit_cost  = $newAvgCost;
                        $product->cost_price = $newAvgCost;
                        $product->save();
                    }
                }
            }

            $voucher->status = 'Cancelled';
            $voucher->save();

            return $voucher;
        });
    }

    private function calculateAndCreateItems(LandedCostVoucher $voucher, int $tenantId, array $grnIds): void
    {
        $grnItems = GoodsReceiptNoteItem::whereIn('goods_receipt_note_id', $grnIds)
            ->with(['goodsReceiptNote', 'product'])
            ->get();

        if ($grnItems->isEmpty()) {
            return;
        }

        $totalQty    = (float) $grnItems->sum('received_qty');
        $totalAmount = (float) $grnItems->sum('total_amount');

        $expenses = $voucher->expenses;

        foreach ($grnItems as $item) {
            $qty       = (float) $item->received_qty;
            $unitRate  = (float) $item->unit_rate;
            $baseTotal = (float) $item->total_amount;

            $allocatedCost = 0.0;

            foreach ($expenses as $exp) {
                $expAmount = (float) $exp->amount;
                $basis     = $exp->allocation_basis;

                if ($basis === 'by_amount' && $totalAmount > 0) {
                    $allocatedCost += ($baseTotal / $totalAmount) * $expAmount;
                } elseif ($basis === 'equal' && count($grnItems) > 0) {
                    $allocatedCost += $expAmount / count($grnItems);
                } else {
                    // Default: by_qty
                    if ($totalQty > 0) {
                        $allocatedCost += ($qty / $totalQty) * $expAmount;
                    }
                }
            }

            $newTotalAmount    = $baseTotal + $allocatedCost;
            $newLandedUnitCost = $qty > 0 ? ($newTotalAmount / $qty) : $unitRate;

            LandedCostItem::create([
                'tenant_id'                   => $tenantId,
                'landed_cost_voucher_id'      => $voucher->id,
                'goods_receipt_note_id'       => $item->goods_receipt_note_id,
                'goods_receipt_note_item_id'  => $item->id,
                'product_id'                  => $item->product_id,
                'quantity'                    => $qty,
                'base_unit_rate'              => $unitRate,
                'base_total_amount'           => $baseTotal,
                'allocated_cost'              => $allocatedCost,
                'new_landed_unit_cost'        => $newLandedUnitCost,
                'new_total_amount'            => $newTotalAmount,
            ]);
        }
    }

    public function previewGrnItems(int $tenantId, array $grnIds): array
    {
        $grnItems = GoodsReceiptNoteItem::whereIn('goods_receipt_note_id', $grnIds)
            ->with(['goodsReceiptNote', 'product.uom'])
            ->get();

        $items = [];
        foreach ($grnItems as $item) {
            $items[] = [
                'grn_id'       => $item->goods_receipt_note_id,
                'grn_number'   => $item->goodsReceiptNote->grn_number,
                'product_id'   => $item->product_id,
                'product_name' => $item->product->name,
                'sku'          => $item->product->sku ?: 'No SKU',
                'uom'          => $item->product->uom->code ?? 'PCS',
                'received_qty' => (float) $item->received_qty,
                'unit_rate'    => (float) $item->unit_rate,
                'total_amount' => (float) $item->total_amount,
            ];
        }

        return $items;
    }
}
