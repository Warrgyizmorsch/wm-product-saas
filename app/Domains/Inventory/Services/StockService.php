<?php

namespace App\Domains\Inventory\Services;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\ProductWarehouseStock;
use App\Domains\Inventory\Models\StockTransaction;
use App\Domains\Inventory\Models\SerialNumber;
use App\Domains\Inventory\Models\Batch;
use App\Domains\Inventory\Models\StockReservation;
use App\Domains\Inventory\Events\StockOutflowRecorded;
use Illuminate\Support\Facades\DB;

class StockService
{
    /**
     * Get the net available stock for a product in a warehouse (quantity - reserved_qty).
     */
    public static function getAvailableStock(int $productId, int $warehouseId): float
    {
        $stock = ProductWarehouseStock::query()
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->first();

        return $stock ? max(0.0, (float)$stock->available_qty) : 0.0;
    }

    /**
     * Reserve stock for a future movement (e.g. Sales Order, Transfer Out, Manufacturing).
     */
    public static function reserveStock(
        int $tenantId,
        int $productId,
        int $warehouseId,
        float $qty,
        string $referenceType,
        int $referenceId,
        ?int $referenceItemId = null,
        ?string $expiresAt = null
    ): StockReservation {
        return DB::transaction(function () use ($tenantId, $productId, $warehouseId, $qty, $referenceType, $referenceId, $referenceItemId, $expiresAt) {
            // 0. Calculate actually available stock to reserve (only for physical serial/batch constraints)
            $stock = ProductWarehouseStock::query()
                ->where('tenant_id', $tenantId)
                ->where('product_id', $productId)
                ->where('warehouse_id', $warehouseId)
                ->first();

            $availableToReserve = 0.0;
            if ($stock) {
                $availableToReserve = max(0.0, (float)$stock->quantity - (float)$stock->reserved_qty);
            }

            // General reservation allows reserving the full requested quantity
            $qtyToReserve = $qty;

            // 1. Create or update the active reservation record
            $reservation = StockReservation::query()
                ->where('tenant_id', $tenantId)
                ->where('product_id', $productId)
                ->where('warehouse_id', $warehouseId)
                ->where('reference_type', $referenceType)
                ->where('reference_id', $referenceId)
                ->when($referenceItemId, function ($q) use ($referenceItemId) {
                    return $q->where('reference_item_id', $referenceItemId);
                })
                ->where('status', 'Active')
                ->first();

            if ($reservation) {
                $reservation->increment('reserved_qty', $qtyToReserve);
            } else {
                $reservation = StockReservation::create([
                    'tenant_id' => $tenantId,
                    'product_id' => $productId,
                    'warehouse_id' => $warehouseId,
                    'reference_type' => $referenceType,
                    'reference_id' => $referenceId,
                    'reference_item_id' => $referenceItemId,
                    'reserved_qty' => $qtyToReserve,
                    'status' => 'Active',
                    'expires_at' => $expiresAt ? date('Y-m-d H:i:s', strtotime($expiresAt)) : null,
                ]);
            }

            // 2. Adjust warehouse stock reservations
            if ($stock) {
                $stock->increment('reserved_qty', $qtyToReserve);
                $stock->update([
                    'available_qty' => max(0.0, (float)$stock->quantity - (float)$stock->reserved_qty)
                ]);
            } else {
                ProductWarehouseStock::create([
                    'tenant_id' => $tenantId,
                    'product_id' => $productId,
                    'warehouse_id' => $warehouseId,
                    'quantity' => 0.0000,
                    'reserved_qty' => $qtyToReserve,
                    'available_qty' => 0.0000,
                    'unit_cost' => 0.0000,
                ]);
            }

            // 3. Mark matching serial numbers as 'Reserved' if tracked (capped by available physical serials)
            $product = Product::find($productId);
            $qtyToReservePhysical = min($qty, $availableToReserve);
            if ($product && $product->track_serial_number && $qtyToReservePhysical > 0) {
                $serials = SerialNumber::query()
                    ->where('tenant_id', $tenantId)
                    ->where('product_id', $productId)
                    ->where('warehouse_id', $warehouseId)
                    ->where('status', 'Available')
                    ->limit((int)$qtyToReservePhysical)
                    ->get();

                foreach ($serials as $serial) {
                    $serial->update(['status' => 'Reserved']);
                }
            }

            // 4. Adjust batch availability if batch tracked (capped by available physical batch stock)
            if ($product && $product->track_batch && $qtyToReservePhysical > 0) {
                $batches = Batch::query()
                    ->where('tenant_id', $tenantId)
                    ->where('product_id', $productId)
                    ->where('warehouse_id', $warehouseId)
                    ->where('available_qty', '>', 0)
                    ->orderBy('expiry_date', 'asc')
                    ->get();

                $remainingToReserve = $qtyToReservePhysical;
                foreach ($batches as $batch) {
                    if ($remainingToReserve <= 0) break;
                    $avail = (float)$batch->available_qty;
                    if ($avail >= $remainingToReserve) {
                        $batch->decrement('available_qty', $remainingToReserve);
                        $remainingToReserve = 0;
                    } else {
                        $batch->update(['available_qty' => 0]);
                        $remainingToReserve -= $avail;
                    }
                }
            }

            return $reservation;
        });
    }

    /**
     * Release previously reserved stock (e.g. order cancelled or fulfilled).
     */
    public static function releaseStock(
        int $tenantId,
        int $productId,
        int $warehouseId,
        float $qty,
        string $referenceType,
        int $referenceId,
        ?int $referenceItemId = null
    ): void {
        DB::transaction(function () use ($tenantId, $productId, $warehouseId, $qty, $referenceType, $referenceId, $referenceItemId) {
            $reservation = StockReservation::query()
                ->where('tenant_id', $tenantId)
                ->where('product_id', $productId)
                ->where('warehouse_id', $warehouseId)
                ->where('reference_type', $referenceType)
                ->where('reference_id', $referenceId)
                ->when($referenceItemId, function ($q) use ($referenceItemId) {
                    return $q->where('reference_item_id', $referenceItemId);
                })
                ->where('status', 'Active')
                ->first();

            if (!$reservation) return;

            $reservedQty = (float)$reservation->reserved_qty;
            $releaseQty = min($qty, $reservedQty);

            // Revert warehouse stocks
            $stock = ProductWarehouseStock::query()
                ->where('tenant_id', $tenantId)
                ->where('product_id', $productId)
                ->where('warehouse_id', $warehouseId)
                ->first();

            if ($stock) {
                $newReserved = max(0.0, (float)$stock->reserved_qty - $releaseQty);
                $stock->update([
                    'reserved_qty' => $newReserved,
                    'available_qty' => max(0.0, (float)$stock->quantity - $newReserved)
                ]);
            }

            // Revert reservation model status
            if ($reservedQty <= $releaseQty) {
                $reservation->update([
                    'reserved_qty' => 0,
                    'status' => 'Completed'
                ]);
            } else {
                $reservation->decrement('reserved_qty', $releaseQty);
            }

            // Restore serial statuses if tracked
            $product = Product::find($productId);
            if ($product && $product->track_serial_number) {
                $serials = SerialNumber::query()
                    ->where('tenant_id', $tenantId)
                    ->where('product_id', $productId)
                    ->where('warehouse_id', $warehouseId)
                    ->where('status', 'Reserved')
                    ->limit((int)$releaseQty)
                    ->get();

                foreach ($serials as $serial) {
                    $serial->update(['status' => 'Available']);
                }
            }

            // Restore batch availability if tracked
            if ($product && $product->track_batch) {
                // Find batches for the product and warehouse
                $batches = Batch::query()
                    ->where('tenant_id', $tenantId)
                    ->where('product_id', $productId)
                    ->where('warehouse_id', $warehouseId)
                    ->orderBy('expiry_date', 'asc')
                    ->get();

                $remainingToRestore = $releaseQty;
                foreach ($batches as $batch) {
                    if ($remainingToRestore <= 0) break;
                    $batchTotal = (float)$batch->quantity;
                    $batchAvail = (float)$batch->available_qty;
                    $capacity = $batchTotal - $batchAvail;

                    if ($capacity > 0) {
                        $restore = min($remainingToRestore, $capacity);
                        $batch->increment('available_qty', $restore);
                        $remainingToRestore -= $restore;
                    }
                }
            }
        });
    }

    /**
     * Record an incoming stock transaction (Inflow).
     */
    public static function recordInflow(
        int $tenantId,
        int $productId,
        int $warehouseId,
        float $quantity,
        float $unitCost,
        string $referenceType,
        ?int $referenceId = null,
        ?string $batchNumber = null,
        array $serialNumbers = []
    ): StockTransaction {
        return DB::transaction(function () use (
            $tenantId,
            $productId,
            $warehouseId,
            $quantity,
            $unitCost,
            $referenceType,
            $referenceId,
            $batchNumber,
            $serialNumbers
        ) {
            $product = Product::findOrFail($productId);

            // 1. Manage batch details if batch tracking is active
            $batchId = null;
            $batch = null;
            if ($product->track_batch && !empty($batchNumber)) {
                $batch = Batch::query()->where('tenant_id', $tenantId)
                    ->where('product_id', $productId)
                    ->where('warehouse_id', $warehouseId)
                    ->where('batch_number', $batchNumber)
                    ->first();

                if ($batch) {
                    $batch->increment('quantity', $quantity);
                    $batch->increment('available_qty', $quantity);
                } else {
                    $batch = Batch::create([
                        'tenant_id' => $tenantId,
                        'product_id' => $productId,
                        'warehouse_id' => $warehouseId,
                        'batch_number' => $batchNumber,
                        'quantity' => $quantity,
                        'available_qty' => $quantity,
                        'manufacturing_date' => now(),
                        'expiry_date' => now()->addYear(),
                    ]);
                }
                $batchId = $batch->id;
            }

            // 2. Create Inflow Ledger Record
            $transaction = StockTransaction::create([
                'tenant_id' => $tenantId,
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'batch_id' => $batchId,
                'type' => 'IN',
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'total_value' => $quantity * $unitCost,
                'balance_qty' => $quantity,
            ]);

            // 3. Register Serial Numbers
            $createdSerials = [];
            if ($product->track_serial_number && !empty($serialNumbers)) {
                foreach ($serialNumbers as $snString) {
                    if (empty($snString)) continue;
                    $createdSerials[] = SerialNumber::query()->updateOrCreate(
                        [
                            'tenant_id' => $tenantId,
                            'product_id' => $productId,
                            'serial_number' => $snString,
                        ],
                        [
                            'status' => 'Available',
                            'warehouse_id' => $warehouseId,
                            'batch_id' => $batchId,
                            'purchase_rate' => $unitCost,
                            'stock_transaction_id_in' => $transaction->id,
                            'stock_transaction_id_out' => null,
                        ]
                    );
                }
            }

            // 4. Update core ProductWarehouseStock statistics
            $stock = ProductWarehouseStock::query()
                ->where('tenant_id', $tenantId)
                ->where('product_id', $productId)
                ->where('warehouse_id', $warehouseId)
                ->first();

            if ($stock) {
                $currentQty = (float)$stock->quantity;
                $currentCost = (float)$stock->unit_cost;
                $newQty = $currentQty + $quantity;

                // Weighted Average cost recalculation
                $newCost = $newQty > 0 
                    ? (($currentQty * $currentCost) + ($quantity * $unitCost)) / $newQty 
                    : $unitCost;

                $stock->update([
                    'quantity' => $newQty,
                    'available_qty' => max(0.0, $newQty - (float)$stock->reserved_qty),
                    'unit_cost' => $newCost,
                ]);
            } else {
                ProductWarehouseStock::create([
                    'tenant_id' => $tenantId,
                    'product_id' => $productId,
                    'warehouse_id' => $warehouseId,
                    'quantity' => $quantity,
                    'reserved_qty' => 0.0000,
                    'available_qty' => $quantity,
                    'unit_cost' => $unitCost,
                ]);
            }

            // Attach created objects to returned transaction
            $transaction->created_batch = $batch;
            $transaction->created_serials = $createdSerials;

            return $transaction;
        });
    }

    /**
     * Record an outgoing stock transaction (Outflow).
     */
    public static function recordOutflow(
        int $tenantId,
        int $productId,
        int $warehouseId,
        float $quantity,
        string $referenceType,
        ?int $referenceId = null,
        array $serialNumbers = []
    ): StockTransaction {
        return DB::transaction(function () use (
            $tenantId,
            $productId,
            $warehouseId,
            $quantity,
            $referenceType,
            $referenceId,
            $serialNumbers
        ) {
            $product = Product::findOrFail($productId);

            // 1. Consume reservation if one existed for this document
            if ($referenceId) {
                self::releaseStock($tenantId, $productId, $warehouseId, $quantity, $referenceType, $referenceId);
            }

            $stock = ProductWarehouseStock::query()
                ->where('tenant_id', $tenantId)
                ->where('product_id', $productId)
                ->where('warehouse_id', $warehouseId)
                ->first();

            $currentQty = $stock ? (float)$stock->quantity : 0.0;
            $currentCost = $stock ? (float)$stock->unit_cost : (float)$product->cost_price;

            $calculatedUnitCost = $currentCost;
            $totalCostValue = 0.0;
            $remainingToDeduct = $quantity;

            $allocations = [];

            // 2. Valuation calculation (FIFO lot depletion vs Weighted Average cost pricing)
            if ($product->inventory_valuation_method === 'FIFO') {
                $inLots = StockTransaction::query()
                    ->where('tenant_id', $tenantId)
                    ->where('product_id', $productId)
                    ->where('warehouse_id', $warehouseId)
                    ->where('type', 'IN')
                    ->where('balance_qty', '>', 0)
                    ->orderBy('created_at', 'asc')
                    ->get();

                foreach ($inLots as $lot) {
                    if ($remainingToDeduct <= 0) break;

                    $lotBalance = (float)$lot->balance_qty;
                    if ($lotBalance >= $remainingToDeduct) {
                        $qtyConsumed = $remainingToDeduct;
                        $totalCostValue += $remainingToDeduct * (float)$lot->unit_cost;
                        $lot->update(['balance_qty' => $lotBalance - $remainingToDeduct]);

                        // Reduce linked batch counts if tracked
                        if ($lot->batch_id) {
                            $batch = Batch::find($lot->batch_id);
                            if ($batch) {
                                $batch->decrement('quantity', $remainingToDeduct);
                                $batch->update([
                                    'available_qty' => max(0.0, (float)$batch->available_qty - $remainingToDeduct)
                                ]);
                            }
                        }

                        if ($lot->batch_id) {
                            $allocations[] = [
                                'batch_id' => $lot->batch_id,
                                'quantity_consumed' => $qtyConsumed,
                                'stock_transaction_id' => $lot->id,
                            ];
                        }

                        $remainingToDeduct = 0;
                    } else {
                        $qtyConsumed = $lotBalance;
                        $totalCostValue += $lotBalance * (float)$lot->unit_cost;
                        $lot->update(['balance_qty' => 0]);

                        // Reduce linked batch counts if tracked
                        if ($lot->batch_id) {
                            $batch = Batch::find($lot->batch_id);
                            if ($batch) {
                                $batch->decrement('quantity', $lotBalance);
                                $batch->update([
                                    'available_qty' => max(0.0, (float)$batch->available_qty - $lotBalance)
                                ]);
                            }
                        }

                        if ($lot->batch_id) {
                            $allocations[] = [
                                'batch_id' => $lot->batch_id,
                                'quantity_consumed' => $qtyConsumed,
                                'stock_transaction_id' => $lot->id,
                            ];
                        }

                        $remainingToDeduct -= $lotBalance;
                    }
                }

                if ($remainingToDeduct > 0) {
                    $fallbackRate = (float)($product->cost_price ?: 0.0);
                    $totalCostValue += $remainingToDeduct * $fallbackRate;
                }

                $calculatedUnitCost = $quantity > 0 ? $totalCostValue / $quantity : $currentCost;
            } else {
                $calculatedUnitCost = $currentCost;
                $totalCostValue = $quantity * $calculatedUnitCost;

                // Also deplete balance_qty and Batch quantities chronologically for Weighted Average lots
                $inLots = StockTransaction::query()
                    ->where('tenant_id', $tenantId)
                    ->where('product_id', $productId)
                    ->where('warehouse_id', $warehouseId)
                    ->where('type', 'IN')
                    ->where('balance_qty', '>', 0)
                    ->orderBy('created_at', 'asc')
                    ->get();

                foreach ($inLots as $lot) {
                    if ($remainingToDeduct <= 0) break;
                    $lotBalance = (float)$lot->balance_qty;
                    if ($lotBalance >= $remainingToDeduct) {
                        $qtyConsumed = $remainingToDeduct;
                        $lot->update(['balance_qty' => $lotBalance - $remainingToDeduct]);
                        if ($lot->batch_id) {
                            $batch = Batch::find($lot->batch_id);
                            if ($batch) {
                                $batch->decrement('quantity', $remainingToDeduct);
                                $batch->update([
                                    'available_qty' => max(0.0, (float)$batch->available_qty - $remainingToDeduct)
                                ]);
                            }
                        }
                        if ($lot->batch_id) {
                            $allocations[] = [
                                'batch_id' => $lot->batch_id,
                                'quantity_consumed' => $qtyConsumed,
                                'stock_transaction_id' => $lot->id,
                            ];
                        }
                        $remainingToDeduct = 0;
                    } else {
                        $qtyConsumed = $lotBalance;
                        $lot->update(['balance_qty' => 0]);
                        if ($lot->batch_id) {
                            $batch = Batch::find($lot->batch_id);
                            if ($batch) {
                                $batch->decrement('quantity', $lotBalance);
                                $batch->update([
                                    'available_qty' => max(0.0, (float)$batch->available_qty - $lotBalance)
                                ]);
                            }
                        }
                        if ($lot->batch_id) {
                            $allocations[] = [
                                'batch_id' => $lot->batch_id,
                                'quantity_consumed' => $qtyConsumed,
                                'stock_transaction_id' => $lot->id,
                            ];
                        }
                        $remainingToDeduct -= $lotBalance;
                    }
                }
            }

            // 3. Create Outflow Ledger Record
            $transaction = StockTransaction::create([
                'tenant_id' => $tenantId,
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'batch_id' => null,
                'type' => 'OUT',
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'quantity' => $quantity,
                'unit_cost' => $calculatedUnitCost,
                'total_value' => $totalCostValue,
                'balance_qty' => 0,
            ]);

            // 4. Update Serial Number Status to Sold
            $serialIds = [];
            if ($product->track_serial_number && !empty($serialNumbers)) {
                foreach ($serialNumbers as $snString) {
                    if (empty($snString)) continue;
                    $snRecord = SerialNumber::query()
                        ->where('tenant_id', $tenantId)
                        ->where('product_id', $productId)
                        ->where('serial_number', $snString)
                        ->whereIn('status', ['Available', 'Reserved'])
                        ->first();

                    if ($snRecord) {
                        $snRecord->update([
                            'status' => 'Sold',
                            'stock_transaction_id_out' => $transaction->id,
                        ]);
                        $serialIds[] = $snRecord->id;
                    }
                }
            }

            // 5. Update Warehouse Stock totals
            if ($stock) {
                $newQty = max(0.0, $currentQty - $quantity);
                if ($newQty > 0 || (float)$stock->reserved_qty > 0) {
                    $stock->update([
                        'quantity' => $newQty,
                        'available_qty' => max(0.0, $newQty - (float)$stock->reserved_qty)
                    ]);
                } else {
                    $stock->delete();
                }
            }

            // Attach details of allocations and serials consumed to transaction
            $transaction->consumed_allocations = $allocations;
            $transaction->consumed_serial_ids = $serialIds;

            event(new StockOutflowRecorded($transaction));

            return $transaction;
        });
    }
}
