<?php

namespace App\Domains\Production\Services;

use App\Domains\Inventory\Models\Batch as InventoryBatch;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\ProductWarehouseStock;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Inventory\Services\StockService;
use App\Domains\Production\Models\ProductionBatch;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionLotTrace;
use App\Domains\Production\Models\ProductionOrderIssue;
use App\Domains\Production\Models\ProductionOrderIssueBatch;
use App\Domains\Production\Models\ProductionOrderReservation;
use App\Domains\Production\Models\ProductionReworkOrder;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ProductionMaterialService
{
    /**
     * Reserve material quantities for a reservation.
     *
     * Uses the existing Inventory StockReservation via StockService::reserveStock().
     * One production order reservation may map to one or more StockReservation records
     * (e.g. allocated across multiple batches). This method does not add a new
     * batch_id column to production_order_reservations; instead, StockService::reserveStock()
     * handles batch-level allocation internally and returns the StockReservation record.
     */
    public function reserveMaterial(int $reservationId, float $quantity): ProductionOrderReservation
    {
        $res = ProductionOrderReservation::findOrFail($reservationId);

        $warehouseId = $res->warehouse_id ?? $this->defaultWarehouseId($res->tenant_id);
        if (! $warehouseId) {
            throw new InvalidArgumentException('No warehouse available for material reservation.');
        }

        // Validate warehouse belongs to tenant and is active
        $this->assertWarehouseActive($warehouseId, $res->tenant_id);

        $res->quantity_reserved += $quantity;
        $res->save();

        app(ProductionEventService::class)->writeEvent($res->tenant_id, [
            'production_order_id' => $res->production_order_id,
            'event_type'          => 'Material Reserved',
            'title'               => 'Material Reserved',
            'description'         => "Reserved {$quantity} of product #{$res->product_id} for order #{$res->production_order_id}.",
            'severity'            => 'info',
            'event_source'        => 'ProductionMaterialService',
        ]);

        return $res;
    }

    /**
     * Override component-level warehouse for a reservation.
     */
    public function updateReservationWarehouse(int $reservationId, int $warehouseId): ProductionOrderReservation
    {
        $res = ProductionOrderReservation::findOrFail($reservationId);
        $this->assertWarehouseActive($warehouseId, $res->tenant_id);

        $res->warehouse_id = $warehouseId;
        $res->save();

        return $res;
    }

    /**
     * Issue material quantities against a reservation snapshot.
     *
     * Validations enforced:
     *  - Quantity > 0
     *  - Cannot exceed reserved quantity
     *  - Warehouse must belong to tenant and be active (status = 'active')
     *  - If the product uses batch tracking, the consumed inventory batch is identified
     *    and its ID is stored on the issue record for traceability.
     *  - A ProductionLotTrace record is written linking the inventory batch → production order.
     */
    public function issueMaterial(
        int     $reservationId,
        float   $quantity,
        ?string $remarks       = null,
        ?int    $userId        = null,
        ?int    $warehouseId   = null
    ): ProductionOrderIssue {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Quantity to issue must be greater than zero.');
        }

        return DB::transaction(function () use ($reservationId, $quantity, $remarks, $userId, $warehouseId) {
            $res = ProductionOrderReservation::lockForUpdate()->findOrFail($reservationId);

            $warehouseId = $warehouseId ?: $res->warehouse_id;
            if (! $warehouseId) {
                throw new InvalidArgumentException('A warehouse is required before issuing material.');
            }

            // Correction #11: Validate active tenant-owned warehouse
            $this->assertWarehouseActive($warehouseId, $res->tenant_id);

            if ($quantity > (float) $res->quantity_reserved) {
                if ((float) $res->quantity_reserved == 0 && $quantity <= (float) $res->quantity_planned) {
                    $this->reserveMaterial($reservationId, $quantity);
                    $res->refresh();
                } else {
                    throw new InvalidArgumentException(
                        "Cannot issue more than reserved quantity ({$res->quantity_reserved}). Refresh MRP or reserve stock first."
                    );
                }
            }

            // Correction #14: Validate lot/batch eligibility before issuing
            // Check if this product has any batch records (FIFO batch tracking is on if batches exist)
            $hasBatches = InventoryBatch::where('tenant_id', $res->tenant_id)
                ->where('product_id', $res->product_id)
                ->where('warehouse_id', $warehouseId)
                ->exists();

            if ($hasBatches) {
                $this->assertBatchEligibleForIssue($res->tenant_id, $res->product_id, $warehouseId);
            }

            // Release the reservation lock from StockReservation records
            StockService::releaseStock(
                $res->tenant_id,
                $res->product_id,
                $warehouseId,
                $quantity,
                'Production Order',
                $res->production_order_id,
                $res->id
            );

            // Record the inventory outflow — StockService handles FIFO / Weighted Avg batch depletion
            $transaction = StockService::recordOutflow(
                $res->tenant_id,
                $res->product_id,
                $warehouseId,
                $quantity,
                'Production Material Issue',
                $res->production_order_id
            );

            // Get exact allocations from outflow
            $allocations = $transaction->consumed_allocations ?? [];
            $primaryBatchId = !empty($allocations) ? $allocations[0]['batch_id'] : null;

            // Determine issue type
            $type = ($res->quantity_issued + $quantity) > $res->quantity_planned ? 'additional' : 'standard';

            $issue = ProductionOrderIssue::create([
                'tenant_id'           => $res->tenant_id,
                'company_id'          => $res->productionOrder?->company_id ?? company_id(),
                'branch_id'           => $res->productionOrder?->branch_id ?? branch_id(),
                'production_order_id' => $res->production_order_id,
                'reservation_id'      => $res->id,
                'product_id'          => $res->product_id,
                'warehouse_id'        => $warehouseId,
                'inventory_batch_id'  => $primaryBatchId,
                'quantity_issued'     => $quantity,
                'issue_type'          => $type,
                'issued_by'           => $userId,
                'issued_at'           => now(),
                'remarks'             => $remarks,
            ]);

            // Save multi-batch allocations and create ProductionLotTrace for EVERY actual inventory batch consumed
            foreach ($allocations as $allocation) {
                ProductionOrderIssueBatch::create([
                    'tenant_id'                 => $res->tenant_id,
                    'production_order_issue_id' => $issue->id,
                    'inventory_batch_id'        => $allocation['batch_id'],
                    'quantity'                  => $allocation['quantity_consumed'],
                    'stock_transaction_id'      => $transaction->id,
                ]);

                ProductionLotTrace::create([
                    'tenant_id'   => $res->tenant_id,
                    'source_type' => 'lot',              // 'lot' = Inventory::Batch
                    'source_id'   => $allocation['batch_id'],
                    'target_type' => 'order',
                    'target_id'   => $res->production_order_id,
                    'quantity'    => $allocation['quantity_consumed'],
                    'remarks'     => "Material issued from batch #{$allocation['batch_id']} to production order #{$res->production_order_id}.",
                ]);
            }

            $res->quantity_issued   += $quantity;
            $res->quantity_reserved  = max(0.0000, $res->quantity_reserved - $quantity);
            $res->save();

            // Add material cost to Work-in-Progress (WIP)
            $res->loadMissing('product');
            $unitCost = (float) ($res->product?->unit_cost ?? $res->product?->cost_price ?? 0.0);
            app(ProductionWipService::class)->addMaterialCost($res->production_order_id, $quantity * $unitCost);

            app(ProductionEventService::class)->writeEvent($res->tenant_id, [
                'production_order_id' => $res->production_order_id,
                'event_type'          => 'Material Issued',
                'title'               => 'Material Issued',
                'description'         => "Issued {$quantity} units of material for production order #{$res->production_order_id}.",
                'severity'            => 'info',
                'event_source'        => 'ProductionMaterialService',
                'triggered_by'        => $userId,
            ]);

            return $issue;
        });
    }

    /**
     * Issue additional materials directly to a Rework Order via canonical stock outflow.
     *
     * Flow:
     *  - Deducts stock via StockService::recordOutflow() with FIFO/Weighted Avg valuation.
     *  - Creates canonical ProductionOrderIssue with rework_order_id, rework_operation_id, and issue_type='rework'.
     *  - Records batch consumption allocations & ProductionLotTrace.
     *  - Triggers PostProductionConsumptionJournal via StockOutflowRecorded event (Dr WIP 1204, Cr Inventory).
     *  - Adds material cost to parent ProductionOrder WIP sheet.
     *  - Increments ReworkOrder actual_cost.
     */
    public function issueReworkMaterial(
        int     $reworkOrderId,
        int     $productId,
        float   $quantity,
        ?int    $warehouseId       = null,
        ?int    $reworkOperationId = null,
        ?string $remarks           = null,
        ?int    $userId            = null
    ): ProductionOrderIssue {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Quantity to issue must be greater than zero.');
        }

        return DB::transaction(function () use (
            $reworkOrderId,
            $productId,
            $quantity,
            $warehouseId,
            $reworkOperationId,
            $remarks,
            $userId
        ) {
            $rework = ProductionReworkOrder::lockForUpdate()->with('originalOrder')->findOrFail($reworkOrderId);

            if (in_array($rework->status, ['completed', 'failed', 'cancelled'], true)) {
                throw new InvalidArgumentException("Cannot issue material to a {$rework->status} rework order.");
            }

            if ($reworkOperationId !== null) {
                $opExists = \App\Domains\Production\Models\ProductionReworkOperation::where('rework_order_id', $rework->id)
                    ->where('id', $reworkOperationId)
                    ->exists();
                if (! $opExists) {
                    throw new InvalidArgumentException('Specified rework operation does not belong to this rework order.');
                }
            }

            $warehouseId = $warehouseId ?: ($rework->originalOrder?->warehouse_id ?? $this->defaultWarehouseId($rework->tenant_id));
            if (! $warehouseId) {
                throw new InvalidArgumentException('A warehouse is required before issuing rework material.');
            }

            $this->assertWarehouseActive($warehouseId, $rework->tenant_id);

            // Validate product
            $product = Product::withoutGlobalScopes()
                ->where('tenant_id', $rework->tenant_id)
                ->findOrFail($productId);

            // Check lot/batch eligibility if batches exist
            $hasBatches = InventoryBatch::where('tenant_id', $rework->tenant_id)
                ->where('product_id', $productId)
                ->where('warehouse_id', $warehouseId)
                ->exists();

            if ($hasBatches) {
                $this->assertBatchEligibleForIssue($rework->tenant_id, $productId, $warehouseId);
            }

            // Record inventory outflow — StockService computes valuation and fires StockOutflowRecorded
            $transaction = StockService::recordOutflow(
                $rework->tenant_id,
                $productId,
                $warehouseId,
                $quantity,
                'Production Material Issue',
                $rework->original_production_order_id
            );

            $allocations = $transaction->consumed_allocations ?? [];
            $primaryBatchId = !empty($allocations) ? $allocations[0]['batch_id'] : null;

            $issue = ProductionOrderIssue::create([
                'tenant_id'           => $rework->tenant_id,
                'company_id'          => $rework->originalOrder?->company_id ?? company_id(),
                'branch_id'           => $rework->originalOrder?->branch_id ?? branch_id(),
                'production_order_id' => $rework->original_production_order_id,
                'reservation_id'      => null,
                'rework_order_id'     => $rework->id,
                'rework_operation_id' => $reworkOperationId,
                'product_id'          => $productId,
                'warehouse_id'        => $warehouseId,
                'inventory_batch_id'  => $primaryBatchId,
                'quantity_issued'     => $quantity,
                'issue_type'          => 'rework',
                'issued_by'           => $userId ?? auth()->id(),
                'issued_at'           => now(),
                'remarks'             => $remarks,
            ]);

            foreach ($allocations as $allocation) {
                ProductionOrderIssueBatch::create([
                    'tenant_id'                 => $rework->tenant_id,
                    'production_order_issue_id' => $issue->id,
                    'inventory_batch_id'        => $allocation['batch_id'],
                    'quantity'                  => $allocation['quantity_consumed'],
                    'stock_transaction_id'      => $transaction->id,
                ]);

                ProductionLotTrace::create([
                    'tenant_id'   => $rework->tenant_id,
                    'source_type' => 'lot',
                    'source_id'   => $allocation['batch_id'],
                    'target_type' => 'order',
                    'target_id'   => $rework->original_production_order_id,
                    'quantity'    => $allocation['quantity_consumed'],
                    'remarks'     => "Material issued from batch #{$allocation['batch_id']} to rework order #{$rework->rework_number}.",
                ]);
            }

            // Valuation: use actual transaction total_value from FIFO/Weighted-Avg
            $materialCost = (float) $transaction->total_value;
            if ($materialCost <= 0.0) {
                $unitCost = (float) ($product->unit_cost ?? $product->cost_price ?? 0.0);
                $materialCost = $quantity * $unitCost;
            }

            // Capitalize in WIP on parent production order
            if ($rework->original_production_order_id) {
                app(ProductionWipService::class)->addMaterialCost($rework->original_production_order_id, $materialCost);
            }

            // Increment rework actual cost
            $rework->actual_cost += $materialCost;
            $rework->save();

            app(ProductionEventService::class)->writeEvent($rework->tenant_id, [
                'production_order_id' => $rework->original_production_order_id,
                'event_type'          => 'Rework Material Issued',
                'title'               => 'Rework Material Issued',
                'description'         => "Issued {$quantity} units of {$product->name} for rework order #{$rework->rework_number}.",
                'severity'            => 'info',
                'event_source'        => 'ProductionMaterialService',
                'triggered_by'        => $userId ?? auth()->id(),
            ]);

            return $issue;
        });
    }

    /**
     * Return unused material back to the inventory stock.
     */
    public function returnMaterial(
        int     $reservationId,
        float   $quantity,
        ?string $remarks     = null,
        ?int    $userId      = null,
        ?int    $warehouseId = null
    ): ProductionOrderIssue {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Quantity to return must be greater than zero.');
        }

        return DB::transaction(function () use ($reservationId, $quantity, $remarks, $userId, $warehouseId) {
            $res = ProductionOrderReservation::lockForUpdate()->findOrFail($reservationId);

            if ($res->quantity_issued < $quantity) {
                throw new InvalidArgumentException(
                    "Cannot return more quantity than has been issued (Issued: {$res->quantity_issued})."
                );
            }

            $warehouseId = $warehouseId ?: $res->warehouse_id ?: $this->defaultWarehouseId($res->tenant_id);
            if (! $warehouseId) {
                throw new InvalidArgumentException('A warehouse is required before returning material.');
            }

            $this->assertWarehouseActive($warehouseId, $res->tenant_id);

            StockService::recordInflow(
                $res->tenant_id,
                $res->product_id,
                $warehouseId,
                $quantity,
                (float) ($res->product?->unit_cost ?? $res->product?->cost_price ?? 0),
                'Production Order Return',
                $res->production_order_id
            );

            $issue = ProductionOrderIssue::create([
                'tenant_id'           => $res->tenant_id,
                'company_id'          => $res->productionOrder?->company_id ?? company_id(),
                'branch_id'           => $res->productionOrder?->branch_id ?? branch_id(),
                'production_order_id' => $res->production_order_id,
                'reservation_id'      => $res->id,
                'product_id'          => $res->product_id,
                'warehouse_id'        => $warehouseId,
                'quantity_issued'     => -$quantity, // Negative represents a return
                'issue_type'          => 'return',
                'issued_by'           => $userId,
                'issued_at'           => now(),
                'remarks'             => $remarks,
            ]);

            $res->quantity_issued -= $quantity;
            $res->save();

            // Deduct material cost from Work-in-Progress (WIP)
            $res->loadMissing('product');
            $unitCost = (float) ($res->product?->unit_cost ?? $res->product?->cost_price ?? 0.0);
            app(ProductionWipService::class)->deductMaterialCost($res->production_order_id, $quantity * $unitCost);

            return $issue;
        });
    }

    // ─── Private Helpers ─────────────────────────────────────────────────────────

    /**
     * Temp no-BOM flow: raw materials consumed while logging shop floor progress (no reservation involved).
     *
     * Per line: stock outflow with the same reference as a normal material issue (so the existing consumption
     * journal Dr WIP / Cr Inventory is posted), a ProductionOrderIssue (reservation null, issue_type 'consumption'),
     * lot traces and WIP material cost. Insufficient stock throws and rolls back the whole log (call inside the
     * log's DB transaction). The per-log lines are also written to a JSON sidecar for display.
     *
     * @param  array<int, array{product_id:int|string, quantity:float|string, warehouse_id?:int|string|null}> $lines
     * @return array<int, ProductionOrderIssue>
     */
    public function consumeForLog(
        \App\Domains\Production\Models\ProductionOrder $order,
        ?\App\Domains\Production\Models\ProductionOrderOperation $op,
        \App\Domains\Production\Models\ProductionOrderProgressLog $log,
        array $lines,
        ?int $userId = null
    ): array {
        $lines = array_values(array_filter($lines, fn ($l) => !empty($l['product_id']) && (float) ($l['quantity'] ?? 0) > 0));
        if ($lines === []) {
            return [];
        }

        $issues = [];
        $stored = [];

        foreach ($lines as $line) {
            $productId = (int) $line['product_id'];
            $quantity = (float) $line['quantity'];

            $product = Product::withoutGlobalScopes()->where('tenant_id', $order->tenant_id)->find($productId);
            if (! $product) {
                throw new InvalidArgumentException("Consumed material #{$productId} does not exist for this tenant.");
            }

            $warehouseId = !empty($line['warehouse_id'])
                ? (int) $line['warehouse_id']
                : (int) (Warehouse::where('tenant_id', $order->tenant_id)->orderByDesc('is_default')->orderBy('id')->value('id') ?? 0);
            if (! $warehouseId) {
                throw new InvalidArgumentException("A warehouse is required to consume {$product->name}.");
            }
            $this->assertWarehouseActive($warehouseId, $order->tenant_id);

            $transaction = StockService::recordOutflow(
                $order->tenant_id,
                $productId,
                $warehouseId,
                $quantity,
                'Production Material Issue',
                $order->id
            );

            $allocations = $transaction->consumed_allocations ?? [];

            $issue = ProductionOrderIssue::create([
                'tenant_id'           => $order->tenant_id,
                'company_id'          => $order->company_id ?? company_id(),
                'branch_id'           => $order->branch_id ?? branch_id(),
                'production_order_id' => $order->id,
                'reservation_id'      => null,
                'product_id'          => $productId,
                'warehouse_id'        => $warehouseId,
                'inventory_batch_id'  => !empty($allocations) ? $allocations[0]['batch_id'] : null,
                'quantity_issued'     => $quantity,
                'issue_type'          => 'consumption',
                'issued_by'           => $userId,
                'issued_at'           => now(),
                'remarks'             => 'Consumed in shop floor log #' . $log->id . ($op ? " ({$op->operation_number})" : ''),
            ]);

            foreach ($allocations as $allocation) {
                ProductionOrderIssueBatch::create([
                    'tenant_id'                 => $order->tenant_id,
                    'production_order_issue_id' => $issue->id,
                    'inventory_batch_id'        => $allocation['batch_id'],
                    'quantity'                  => $allocation['quantity_consumed'],
                    'stock_transaction_id'      => $transaction->id,
                ]);

                ProductionLotTrace::create([
                    'tenant_id'   => $order->tenant_id,
                    'source_type' => 'lot',
                    'source_id'   => $allocation['batch_id'],
                    'target_type' => 'order',
                    'target_id'   => $order->id,
                    'quantity'    => $allocation['quantity_consumed'],
                    'remarks'     => "Material consumed from batch #{$allocation['batch_id']} by production order #{$order->id}.",
                ]);
            }

            $cost = (float) ($transaction->total_value ?? 0.0);
            if ($cost <= 0.0) {
                $cost = $quantity * (float) ($product->unit_cost ?? $product->cost_price ?? 0.0);
            }
            app(ProductionWipService::class)->addMaterialCost($order->id, $cost);

            $issues[] = $issue;
            $stored[] = [
                'product_id'   => $productId,
                'product_name' => $product->name,
                'warehouse_id' => $warehouseId,
                'quantity'     => $quantity,
                'issue_id'     => $issue->id,
            ];
        }

        app(ProductionEventService::class)->writeEvent($order->tenant_id, [
            'production_order_id' => $order->id,
            'event_type'          => 'Material Consumed',
            'title'               => 'Material Consumed',
            'description'         => count($stored) . " material line(s) consumed in progress log #{$log->id}.",
            'severity'            => 'info',
            'event_source'        => 'ProductionMaterialService',
            'triggered_by'        => $userId,
        ]);

        $this->appendConsumptionJson($order->tenant_id, [
            'log_id'      => $log->id,
            'order_id'    => $order->id,
            'operation_id' => $op?->id,
            'recorded_at' => now()->toDateTimeString(),
            'lines'       => $stored,
        ]);

        return $issues;
    }

    private function consumptionJsonPath(int $tenantId): string
    {
        $base = config('production.temp_consumption_path') ?: storage_path('app/temp-production');

        return rtrim($base, '/\\') . "/{$tenantId}/consumption.json";
    }

    /** JSON sidecar of consumed-material lines per progress log (no table exists for it). */
    private function appendConsumptionJson(int $tenantId, array $entry): void
    {
        $path = $this->consumptionJsonPath($tenantId);
        if (! is_dir(dirname($path))) {
            @mkdir(dirname($path), 0775, true);
        }

        $handle = fopen($path, 'c+');
        if (! $handle) {
            return;
        }
        try {
            flock($handle, LOCK_EX);
            $existing = json_decode((string) stream_get_contents($handle), true);
            $existing = is_array($existing) ? $existing : [];
            $existing[] = $entry;
            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            fflush($handle);
            flock($handle, LOCK_UN);
        } finally {
            fclose($handle);
        }
    }

    /**
     * Consumed-material lines recorded per progress log of an order, keyed by log id.
     * Entries whose log no longer exists (rolled back) are ignored.
     *
     * @return array<int, array>
     */
    public function consumptionByLog(int $tenantId, int $orderId): array
    {
        $logIds = \App\Domains\Production\Models\ProductionOrderProgressLog::withoutGlobalScopes()
            ->where('production_order_id', $orderId)->pluck('id')->all();

        $byLog = [];
        $path = $this->consumptionJsonPath($tenantId);
        $entries = is_file($path) ? json_decode((string) @file_get_contents($path), true) : null;
        foreach (is_array($entries) ? $entries : [] as $entry) {
            if ((int) ($entry['order_id'] ?? 0) === $orderId && in_array($entry['log_id'] ?? null, $logIds, true)) {
                $byLog[(int) $entry['log_id']] = $entry;
            }
        }

        // The JSON file is only a sidecar: any consumption issue it does not know about (file lost / edited) is rebuilt
        // from the DB — the issue's remarks carry "shop floor log #<id>".
        $known = collect($byLog)->pluck('lines')->flatten(1)->pluck('issue_id')->all();
        $orphans = ProductionOrderIssue::withoutGlobalScopes()->with('product')
            ->where('tenant_id', $tenantId)->where('production_order_id', $orderId)->where('issue_type', 'consumption')
            ->whereNotIn('id', $known ?: [0])->get();
        foreach ($orphans as $issue) {
            if (! preg_match('/shop floor log #(\d+)/', (string) $issue->remarks, $m) || ! in_array((int) $m[1], $logIds, true)) {
                continue;
            }
            $logId = (int) $m[1];
            $byLog[$logId] ??= [
                'log_id' => $logId, 'order_id' => $orderId, 'operation_id' => null,
                'recorded_at' => $issue->issued_at?->toDateTimeString(), 'lines' => [],
            ];
            $byLog[$logId]['lines'][] = [
                'product_id' => $issue->product_id, 'product_name' => $issue->product?->name,
                'warehouse_id' => $issue->warehouse_id, 'quantity' => (float) $issue->quantity_issued, 'issue_id' => $issue->id,
            ];
        }

        return $byLog;
    }

    /**
     * Temp no-BOM flow: every material consumed on the order, one row per consumed line, with the operation, progress log,
     * production batch, material, unit, warehouse, inventory lot(s), cost and who logged it.
     *
     * @return array<int, array<string, mixed>> newest first
     */
    public function consumptionReport(\App\Domains\Production\Models\ProductionOrder $order): array
    {
        $byLog = $this->consumptionByLog($order->tenant_id, $order->id);
        if ($byLog === []) {
            return [];
        }

        $logs = \App\Domains\Production\Models\ProductionOrderProgressLog::withoutGlobalScopes()
            ->with(['operation', 'batch', 'user'])
            ->whereIn('id', array_keys($byLog))->get()->keyBy('id');

        $issues = ProductionOrderIssue::withoutGlobalScopes()
            ->with(['product.uom', 'warehouse', 'user', 'batches.inventoryBatch', 'batches.stockTransaction'])
            ->where('production_order_id', $order->id)
            ->whereIn('id', collect($byLog)->pluck('lines')->flatten(1)->pluck('issue_id')->all())
            ->get()->keyBy('id');

        $rows = [];
        foreach ($byLog as $logId => $entry) {
            $log = $logs[$logId] ?? null;
            foreach ($entry['lines'] as $line) {
                $issue = $issues[$line['issue_id']] ?? null;
                $rows[] = [
                    'log_id'           => $logId,
                    'recorded_at'      => $log?->recorded_at ?? $entry['recorded_at'] ?? null,
                    'operation_id'     => $log?->operation_id,
                    'operation_number' => $log?->operation?->operation_number,
                    'operation_name'   => $log?->operation?->name,
                    'production_batch' => $log?->batch?->batch_number,
                    'quantity_produced' => (float) ($log?->quantity_produced ?? 0),
                    'product_id'       => $line['product_id'],
                    'sku'              => $issue?->product?->sku,
                    'product_name'     => $line['product_name'] ?? $issue?->product?->name,
                    'unit'             => $issue?->product?->uom?->code,
                    'quantity'         => (float) $line['quantity'],
                    'warehouse'        => $issue?->warehouse?->name,
                    'inventory_lots'   => $issue ? $issue->batches->map(fn ($b) => ($b->inventoryBatch?->batch_number ?? '#' . $b->inventory_batch_id) . ' (' . rtrim(rtrim(number_format((float) $b->quantity, 4, '.', ''), '0'), '.') . ')')->implode(', ') : '',
                    'cost'             => $issue ? (float) $issue->total_cost : 0.0,
                    'user'             => $log?->user?->name ?? $issue?->user?->name,
                    'issue_id'         => $line['issue_id'],
                ];
            }
        }

        usort($rows, fn ($a, $b) => strcmp((string) $b['recorded_at'], (string) $a['recorded_at']) ?: $b['log_id'] <=> $a['log_id']);

        return $rows;
    }

    /**
     * Assert warehouse is active and belongs to the tenant.
     *
     * @throws InvalidArgumentException
     */
    private function assertWarehouseActive(int $warehouseId, int $tenantId): void
    {
        $warehouse = Warehouse::where('tenant_id', $tenantId)
            ->where('id', $warehouseId)
            ->first();

        if (! $warehouse) {
            throw new InvalidArgumentException(
                "Warehouse #{$warehouseId} does not belong to this tenant or does not exist."
            );
        }

        if (isset($warehouse->status) && $warehouse->status !== 'active') {
            throw new InvalidArgumentException(
                "Warehouse '{$warehouse->name}' is not active (status: {$warehouse->status}). Cannot process material movement."
            );
        }
    }

    /**
     * Validate that no available inventory batch for this product/warehouse is expired or blocked/quarantined.
     * Rejects the issue if the ONLY available batches are expired, blocked, or quarantined.
     *
     * This does not prevent issuing when at least one eligible batch exists —
     * StockService handles FIFO selection.
     *
     * @throws InvalidArgumentException
     */
    private function assertBatchEligibleForIssue(int $tenantId, int $productId, int $warehouseId): void
    {
        $today = Carbon::today()->toDateString();

        // Check if any eligible (non-expired) batch exists with available stock
        $eligibleExists = InventoryBatch::where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->where('available_qty', '>', 0)
            ->where(function ($q) use ($today) {
                $q->whereNull('expiry_date')
                  ->orWhere('expiry_date', '>=', $today);
            })
            ->exists();

        if (! $eligibleExists) {
            throw new InvalidArgumentException(
                "No eligible (non-expired) inventory batch found for product #{$productId} in warehouse #{$warehouseId}. All lots may be expired or fully consumed."
            );
        }
    }

    /**
     * After StockService::recordOutflow() depletes FIFO batches, find the oldest
     * inventory batch by id (FIFO) that still has some quantity.
     * Returns null for non-batch-tracked products.
     */
    private function resolveConsumedInventoryBatchId(int $tenantId, int $productId, int $warehouseId): ?int
    {
        return InventoryBatch::where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->orderBy('manufacturing_date', 'asc')
            ->orderBy('id', 'asc')
            ->value('id');
    }

    private function defaultWarehouseId(int $tenantId): ?int
    {
        return Warehouse::query()
            ->where('tenant_id', $tenantId)
            ->where('is_default', true)
            ->value('id')
            ?? Warehouse::query()->where('tenant_id', $tenantId)->value('id');
    }

    /**
     * Evaluate replacement material demand resulting from scrap.
     *
     * Flow:
     * - Checks physical stock availability in warehouse.
     * - If store stock is available: reserves and issues material directly (type = 'additional').
     * - If store stock is insufficient: issues available portion and records MRP shortage/requisition for net deficit ONLY.
     */
    public function evaluateAndIssueReplacementMaterial(
        int $tenantId,
        int $productionOrderId,
        int $materialProductId,
        float $replacementMaterialQty,
        ?string $reason = null,
        ?int $userId = null
    ): array {
        if ($replacementMaterialQty <= 0) {
            return [
                'status' => 'no_action',
                'issued_qty' => 0.0,
                'requested_qty' => 0.0,
                'message' => 'No replacement quantity required.',
            ];
        }

        return DB::transaction(function () use ($tenantId, $productionOrderId, $materialProductId, $replacementMaterialQty, $reason, $userId) {
            $matProduct = Product::find($materialProductId);
            $matName = $matProduct?->name ?? "Product #{$materialProductId}";
            $defaultWarehouse = $this->defaultWarehouseId($tenantId);

            // Check if store already has available physical stock of this product
            $storeStockQty = (float) ProductWarehouseStock::where('tenant_id', $tenantId)
                ->where('product_id', $materialProductId)
                ->when($defaultWarehouse, fn($q) => $q->where('warehouse_id', $defaultWarehouse))
                ->sum('available_qty');

            $neededQty = $replacementMaterialQty;
            $itemsToRequest = [];

            if ($storeStockQty >= $neededQty) {
                // Store has sufficient physical stock of this SFG/FG/RM product directly
                $itemsToRequest[] = [
                    'product_id' => $materialProductId,
                    'quantity'   => $neededQty,
                    'name'       => $matName,
                    'uom_id'     => $matProduct?->uom_id ?? 1,
                ];
            } else {
                // Insufficient ready stock in store for SFG/FG -> request ready stock portion if any, and explode deficit to Raw Materials via BOM
                if ($storeStockQty > 0) {
                    $itemsToRequest[] = [
                        'product_id' => $materialProductId,
                        'quantity'   => $storeStockQty,
                        'name'       => $matName,
                        'uom_id'     => $matProduct?->uom_id ?? 1,
                    ];
                    $neededQty -= $storeStockQty;
                }

                $explodedItems = $this->resolveMaterialRequisitionItems($tenantId, $materialProductId, $neededQty);
                foreach ($explodedItems as $ei) {
                    $itemsToRequest[] = $ei;
                }
            }

            // 1. Dedicated NEW ProductionRequisitionSlip for Operational Scrap Replacement
            $order = \App\Domains\Production\Models\ProductionOrder::withoutGlobalScopes()->find($productionOrderId);
            $branchId = $order?->branch_id ?? branch_id() ?? app(\App\Core\Branch\BranchContext::class)->id();
            $companyId = $order?->company_id ?? company_id() ?? app(\App\Core\Company\CompanyContext::class)->id();

            $year = now()->format('Y');
            $prefix = "MR-{$year}-";
            $lastSlip = \App\Domains\Production\Models\ProductionRequisitionSlip::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->when($branchId !== null, fn($q) => $q->where('branch_id', $branchId))
                ->where('requisition_number', 'like', "{$prefix}%")
                ->orderBy('id', 'desc')
                ->first();
            $nextNum = 1;
            if ($lastSlip) {
                $lastNumStr = str_replace($prefix, '', $lastSlip->requisition_number);
                $nextNum = ((int) $lastNumStr) + 1;
            }
            $reqNumber = $prefix . str_pad($nextNum, 6, '0', STR_PAD_LEFT);
            while (\App\Domains\Production\Models\ProductionRequisitionSlip::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->when($branchId !== null, fn($q) => $q->where('branch_id', $branchId))
                ->where('requisition_number', $reqNumber)
                ->exists()) {
                $nextNum++;
                $reqNumber = $prefix . str_pad($nextNum, 6, '0', STR_PAD_LEFT);
            }

            $slipNotes = "Operational Scrap Replacement Request ({$matName}: {$replacementMaterialQty} units)";
            if (!empty($reason)) {
                $slipNotes .= " — Reason: {$reason}";
            }

            $slip = \App\Domains\Production\Models\ProductionRequisitionSlip::create([
                'tenant_id' => $tenantId,
                'company_id' => $companyId,
                'branch_id' => $branchId,
                'production_order_id' => $productionOrderId,
                'requisition_number' => $reqNumber,
                'status' => 'pending',
                'requested_by' => $userId ?? auth()->id() ?? 1,
                'requisition_date' => now()->toDateString(),
                'notes' => $slipNotes,
            ]);

            // 2. Process each required component/raw material item
            foreach ($itemsToRequest as $reqItem) {
                $reqPid = $reqItem['product_id'];
                $reqQty = $reqItem['quantity'];
                $reqUom = $reqItem['uom_id'] ?? \App\Domains\Inventory\Models\Product::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('id', $reqPid)->value('uom_id') ?? 1;

                $res = ProductionOrderReservation::where('tenant_id', $tenantId)
                    ->where('production_order_id', $productionOrderId)
                    ->where('product_id', $reqPid)
                    ->first();

                if (!$res) {
                    $res = ProductionOrderReservation::create([
                        'tenant_id' => $tenantId,
                        'production_order_id' => $productionOrderId,
                        'product_id' => $reqPid,
                        'uom_id' => $reqUom,
                        'warehouse_id' => $defaultWarehouse,
                        'quantity_planned' => 0.0,
                        'quantity_additional_requested' => $reqQty,
                        'quantity_reserved' => 0.0,
                        'quantity_issued' => 0.0,
                    ]);
                } else {
                    $res->quantity_additional_requested += $reqQty;
                    $res->save();
                }

                \App\Domains\Production\Models\ProductionRequisitionSlipItem::create([
                    'tenant_id' => $tenantId,
                    'production_requisition_slip_id' => $slip->id,
                    'product_id' => $reqPid,
                    'quantity_planned' => $reqQty,
                    'quantity_reserved' => 0.0,
                    'quantity_issued' => 0.0,
                    'uom_id' => $reqUom,
                    'warehouse_id' => $res->warehouse_id ?? $defaultWarehouse,
                ]);
            }

            app(ProductionEventService::class)->writeEvent($tenantId, [
                'production_order_id' => $productionOrderId,
                'event_type' => 'Store Replacement Requested',
                'title' => 'Replacement Material Requested from Store',
                'description' => "Requested replacement materials on Material Request #{$slip->requisition_number} due to scrap of {$matName} ({$replacementMaterialQty} units): " . ($reason ?? 'Operational loss'),
                'severity' => 'warning',
                'event_source' => 'ProductionMaterialService',
            ]);

            return [
                'status' => 'requested',
                'issued_qty' => 0.0,
                'requested_qty' => $replacementMaterialQty,
                'slip_id' => $slip->id,
                'message' => "Replacement material request for {$replacementMaterialQty} units of {$matName} added to Material Request #{$slip->requisition_number}.",
            ];
        });
    }

    /**
     * Explode product into required raw materials if it is a manufactured SFG/FG with a BOM.
     * Returns an array of items: [['product_id' => int, 'quantity' => float, 'name' => string, 'uom_id' => int]]
     */
    public function resolveMaterialRequisitionItems(int $tenantId, int $productId, float $qty): array
    {
        $product = Product::find($productId);
        if (!$product) {
            return [];
        }

        $isManufactured = in_array($product->type, ['semi_finished', 'semi_finished_goods', 'finished_good', 'finished_goods'])
            || $product->supplier_method === 'manufacture';

        if ($isManufactured) {
            $bom = ProductionBom::where('tenant_id', $tenantId)
                ->where('product_id', $productId)
                ->whereIn('status', ['approved', 'draft'])
                ->with('items.material')
                ->first();

            if ($bom && $bom->items->count() > 0) {
                $items = [];
                foreach ($bom->items as $bomItem) {
                    $childItems = $this->resolveMaterialRequisitionItems($tenantId, $bomItem->material_id, (float) $bomItem->quantity * $qty);
                    if (!empty($childItems)) {
                        foreach ($childItems as $ci) {
                            $items[] = $ci;
                        }
                    } else {
                        $items[] = [
                            'product_id' => $bomItem->material_id,
                            'quantity'   => (float) $bomItem->quantity * $qty,
                            'name'       => $bomItem->material?->name ?? "Product #{$bomItem->material_id}",
                            'uom_id'     => $bomItem->uom_id ?? $bomItem->material?->uom_id ?? 1,
                        ];
                    }
                }
                return $items;
            }
        }

        return [[
            'product_id' => $product->id,
            'quantity'   => $qty,
            'name'       => $product->name,
            'uom_id'     => $product->uom_id ?? 1,
        ]];
    }
}
