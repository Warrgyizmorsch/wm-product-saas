<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\DeliveryChallan;
use App\Domains\Production\Models\DeliveryChallanItem;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionOrderReservation;
use App\Domains\Production\Models\ProductionWip;
use App\Domains\Production\Models\ProductionWipTransaction;
use App\Domains\Production\Models\RoutingOperationMaterial;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Dispatch slips (Delivery Challans) and the material / WIP held by subcontract vendors
 * (docs/subcontracting_module_audit_2026-11-26.md, TF-03 / TF-04 / TF-08).
 *
 *  - Company material for an outsourced operation is requested and issued to the order like any other material: the
 *    store issue is the ONLY stock-out. The dispatch slip sends material that was issued to the order and not sent
 *    yet — it never touches stock again (C-03 / N-01).
 *  - WIP job work: the slip sends the previous operation's accepted output (D-3b, my rule: good output − already sent).
 *  - The slip records which material / WIP is at which vendor, for which operation. Value stays inside the order's
 *    WIP (1204) — no separate vendor stock (D-3c, my rule).
 *  - Nothing is backflushed at receipt (TF-08): the receipt records how much of the sent material the vendor used.
 */
class SubcontractMaterialBalanceService
{
    // ─── What an outsourced operation needs and may send ─────────────────────────

    /**
     * Company-supplied materials of the operation (its chosen BOM allocations, vendor-supplied ones left out — TF-02),
     * as quantity per 1 unit of the operation's output.
     *
     * @return Collection<int, array{product_id:int, name:string, sku:?string, uom:string, per_unit:float}>
     */
    public function companyMaterialsPerUnit(ProductionOrderOperation $op): Collection
    {
        $order = $op->order;
        $bomId = $op->source_bom_id ?: $order?->bom_id;
        if (!$bomId || !$op->routing_operation_id) {
            return collect();
        }

        $bom = \App\Domains\Production\Models\ProductionBom::find($bomId);
        $baseQty = ($bom && (float) $bom->base_quantity > 0) ? (float) $bom->base_quantity : 1.0;

        $selected = array_filter((array) ($op->selected_routing_material_ids ?? []));
        $rows = RoutingOperationMaterial::with('material')
            ->where('bom_id', $bomId)
            ->where('routing_operation_id', $op->routing_operation_id)
            ->when($selected, fn ($q) => $q->whereIn('id', $selected), fn ($q) => $q->where('is_alternative', false))
            ->get()
            ->reject(fn ($row) => $this->isVendorSupplied($row, $op));

        return $rows->groupBy('material_id')->map(function ($group, $materialId) use ($baseQty) {
            $first = $group->first();
            $gross = (float) $group->sum(fn ($r) => (float) ($r->gross_quantity ?: $r->quantity));

            return [
                'product_id' => (int) $materialId,
                'name' => $first->material?->name ?? ('Material #' . $materialId),
                'sku' => $first->material?->sku,
                'uom' => $first->material?->unit_of_measure ?? 'PCS',
                'per_unit' => round($gross / $baseQty, 6),
            ];
        })->values();
    }

    /** TF-02: a BOM allocation the vendor brings — marked on the row, or every row of a vendor-supplied operation. */
    public function isVendorSupplied(RoutingOperationMaterial $row, ?ProductionOrderOperation $op = null): bool
    {
        if (($row->supplied_by ?? 'company') === 'vendor') {
            return true;
        }

        return $op && $op->isOutsourced() && $op->material_supply_type === 'vendor_supplied';
    }

    /** Quantity of a material issued to the order so far (store issue or order-screen issue, pooled per product). */
    public function issuedToOrder(int $tenantId, int $orderId, int $productId): float
    {
        return (float) ProductionOrderReservation::where('tenant_id', $tenantId)
            ->where('production_order_id', $orderId)
            ->where('product_id', $productId)
            ->sum('quantity_issued');
    }

    /** Material of the order already sent to vendors (slips that went out), net of what came back unused. */
    public function sentForOrder(int $tenantId, int $orderId, int $productId, ?int $exceptChallanId = null): float
    {
        return (float) DeliveryChallanItem::query()
            ->join('delivery_challans', 'delivery_challans.id', '=', 'delivery_challan_items.delivery_challan_id')
            ->where('delivery_challans.tenant_id', $tenantId)
            ->where('delivery_challans.production_order_id', $orderId)
            ->whereIn('delivery_challans.status', DeliveryChallan::SENT_STATUSES)
            ->whereNull('delivery_challans.deleted_at')
            ->when($exceptChallanId, fn ($q) => $q->where('delivery_challans.id', '!=', $exceptChallanId))
            ->where('delivery_challan_items.item_type', 'material')
            ->where('delivery_challan_items.product_id', $productId)
            ->selectRaw('COALESCE(SUM(delivery_challan_items.quantity - delivery_challan_items.returned_qty), 0) as total')
            ->value('total');
    }

    /** Issued to the order and not yet with a vendor — the most a dispatch slip may send of that material. */
    public function availableToSend(ProductionOrderOperation $op, int $productId, ?int $exceptChallanId = null): float
    {
        $tenantId = (int) $op->tenant_id;
        $orderId = (int) $op->production_order_id;

        return max(0.0, round($this->issuedToOrder($tenantId, $orderId, $productId) - $this->sentForOrder($tenantId, $orderId, $productId, $exceptChallanId), 4));
    }

    /**
     * D-3b (my rule): WIP job work may send the previous operation's good output that was not sent yet. Good output is
     * the operation's quantity_produced (for a QC operation that is the QC-accepted quantity). Drafts never count (H-07).
     *
     * @return array{prev_op: ?ProductionOrderOperation, produced_qty: float, already_dispatched: float, available_wip: float}
     */
    public function availableWip(ProductionOrderOperation $op, ?int $exceptChallanId = null): array
    {
        $prevOp = $op->previousOperation ?: ProductionOrderOperation::where('tenant_id', $op->tenant_id)
            ->where('production_order_id', $op->production_order_id)
            ->where(function ($q) use ($op) {
                $op->source_product_id ? $q->where('source_product_id', $op->source_product_id) : $q->whereNull('source_product_id');
            })
            ->where('sequence', '<', $op->sequence)
            ->orderByDesc('sequence')
            ->first();

        $produced = $prevOp ? (float) $prevOp->quantity_produced : (float) ($op->order?->quantity_ordered ?? 0);

        $sent = (float) DeliveryChallan::where('tenant_id', $op->tenant_id)
            ->where('production_order_operation_id', $op->id)
            ->whereIn('status', DeliveryChallan::SENT_STATUSES)
            ->where('type', '!=', 'vendor_rework')
            ->when($exceptChallanId, fn ($q) => $q->where('id', '!=', $exceptChallanId))
            ->sum('dispatched_wip_qty');

        return [
            'prev_op' => $prevOp,
            'produced_qty' => $produced,
            'already_dispatched' => $sent,
            'available_wip' => max(0.0, round($produced - $sent, 4)),
        ];
    }

    /** Units of the operation not yet sent to a vendor (target − sent on slips that went out). */
    public function unitsNotYetSent(ProductionOrderOperation $op, ?int $exceptChallanId = null): float
    {
        $target = (float) ($op->target_produced_qty ?: ($op->order?->quantity_ordered ?? 0));
        $sent = (float) DeliveryChallan::where('tenant_id', $op->tenant_id)
            ->where('production_order_operation_id', $op->id)
            ->whereIn('status', DeliveryChallan::SENT_STATUSES)
            ->where('type', '!=', 'vendor_rework')
            ->when($exceptChallanId, fn ($q) => $q->where('id', '!=', $exceptChallanId))
            ->sum('dispatched_wip_qty');

        return max(0.0, round($target - $sent, 4));
    }

    /**
     * Pre-filled lines of a new dispatch slip for the operation: the WIP line (job work) and the company materials for
     * $units, each capped at what may be sent.
     */
    public function prefillLines(ProductionOrderOperation $op, float $units): array
    {
        $lines = [];
        $order = $op->order;

        if ($op->isWipJobWork()) {
            $wipData = $this->availableWip($op);
            $prevOp = $wipData['prev_op'];
            $wip = ProductionWip::where('tenant_id', $op->tenant_id)->where('production_order_id', $op->production_order_id)->first();
            $lines[] = [
                'item_type' => 'wip',
                'product_id' => $op->source_product_id ?: $order?->product_id,
                'production_wip_id' => $wip?->id,
                'production_batch_id' => $wip?->production_batch_id,
                'product_name' => ($order?->product?->name ?? 'WIP') . ($prevOp ? " (output of OP-{$prevOp->operation_number})" : ' (in-process WIP)'),
                'sku' => $order?->product?->sku,
                'uom' => $order?->product?->unit_of_measure ?? 'PCS',
                'required_qty' => round($units, 4),
                'available' => $wipData['available_wip'],
                'quantity' => round(min($units, $wipData['available_wip']), 4),
            ];
        }

        foreach ($this->companyMaterialsPerUnit($op) as $material) {
            $required = round($material['per_unit'] * $units, 4);
            $available = $this->availableToSend($op, $material['product_id']);
            $lines[] = [
                'item_type' => 'material',
                'product_id' => $material['product_id'],
                'product_name' => $material['name'],
                'sku' => $material['sku'],
                'uom' => $material['uom'],
                'required_qty' => $required,
                'available' => $available,
                'quantity' => round(min($required, $available), 4),
            ];
        }

        return $lines;
    }

    // ─── Dispatch ────────────────────────────────────────────────────────────────

    /**
     * C-07 / N-20: every rule a slip must pass before it goes out. Returns the problems (empty = fine).
     *
     * @param array<int, array{item_type?:string, product_id:int, quantity:float}> $items
     */
    public function dispatchProblems(ProductionOrderOperation $op, int $vendorId, array $items, float $units, ?int $exceptChallanId = null): array
    {
        $problems = [];
        $order = $op->order;

        if (!$op->isOutsourced()) {
            $problems[] = "Operation #{$op->operation_number} is not an outsourced operation.";
        }
        if ($order && !in_array($order->status, [ProductionOrder::STATUS_RELEASED, ProductionOrder::STATUS_IN_PROGRESS], true)) {
            $problems[] = "Order {$order->order_number} is {$order->status}: material can be sent to a vendor only after the order is released.";
        }
        if ($op->vendor_id && (int) $op->vendor_id !== $vendorId) {
            $problems[] = 'The vendor of the slip is not the vendor of the outsourced operation.';
        }
        if (in_array($op->status, [ProductionOrderOperation::STATUS_COMPLETED, ProductionOrderOperation::STATUS_CANCELLED, ProductionOrderOperation::STATUS_SKIPPED], true)) {
            $problems[] = "Operation #{$op->operation_number} is {$op->status}.";
        }
        if ($units <= 0) {
            $problems[] = 'Enter the units of work sent to the vendor.';
        } elseif ($units > $this->unitsNotYetSent($op, $exceptChallanId) + 0.0001) {
            $problems[] = 'Units sent (' . self::fmt($units) . ') exceed what is still to be sent for this operation (' . self::fmt($this->unitsNotYetSent($op, $exceptChallanId)) . ').';
        }

        $wipQty = 0.0;
        foreach ($items as $item) {
            $qty = (float) ($item['quantity'] ?? 0);
            if (($item['item_type'] ?? 'material') === 'wip') {
                $wipQty += $qty;
                continue;
            }
            $available = $this->availableToSend($op, (int) $item['product_id'], $exceptChallanId);
            if ($qty > $available + 0.0001) {
                $name = \App\Domains\Inventory\Models\Product::find($item['product_id'])?->name ?? ('#' . $item['product_id']);
                $problems[] = "{$name}: " . self::fmt($qty) . ' to send, but only ' . self::fmt($available) . ' is issued to the order and not yet sent. Request and issue it from the store first.';
            }
        }

        if ($op->isWipJobWork()) {
            $wip = $this->availableWip($op, $exceptChallanId);
            if ($wipQty <= 0) {
                $problems[] = 'This is WIP job work: add the WIP line (the previous operation\'s output).';
            } elseif ($wipQty > $wip['available_wip'] + 0.0001) {
                $problems[] = 'WIP to send (' . self::fmt($wipQty) . ') exceeds the previous operation\'s good output not yet sent (' . self::fmt($wip['available_wip']) . ').';
            }
        }

        return $problems;
    }

    /**
     * The slip goes out: no stock movement (TF-04 — the store issue was the stock-out); the operation is at the vendor;
     * WIP lines are logged as WIP sent to the vendor.
     */
    public function processDispatchActions(DeliveryChallan $challan, int $tenantId): void
    {
        DB::transaction(function () use ($challan, $tenantId) {
            $challan->load('items');
            $op = $challan->operation;

            $challan->status = DeliveryChallan::STATUS_DISPATCHED;
            $challan->dispatched_at = now();
            $challan->save();

            foreach ($challan->items->where('item_type', 'wip') as $item) {
                if (!$item->production_wip_id) {
                    continue;
                }
                ProductionWipTransaction::create([
                    'tenant_id' => $tenantId,
                    'wip_id' => $item->production_wip_id,
                    'production_order_id' => $challan->production_order_id,
                    'production_batch_id' => $item->production_batch_id,
                    'from_operation_id' => $op?->previousOperation?->routing_operation_id ?? $op?->routing_operation_id,
                    'to_operation_id' => $op?->routing_operation_id,
                    'from_work_center_id' => $op?->previousOperation?->work_center_id,
                    'to_work_center_id' => null,
                    'transaction_type' => 'wip_vendor_dispatched',
                    'quantity' => (float) $item->quantity,
                    'good_quantity' => (float) $item->quantity,
                    'cost_before' => 0.00,
                    'cost_added' => 0.00,
                    'cost_after' => 0.00,
                    'remarks' => mb_substr("WIP sent to vendor [{$challan->vendor?->name}] on dispatch slip {$challan->challan_number}", 0, 250),
                    'transaction_at' => now(),
                    'created_by' => auth()->id() ?: 1,
                ]);
            }

            if ($op && !in_array($op->status, [ProductionOrderOperation::STATUS_COMPLETED, ProductionOrderOperation::STATUS_SUBCONTRACT_QC_PENDING], true)) {
                $op->status = ProductionOrderOperation::STATUS_VENDOR_DISPATCHED;
                $op->actual_start_time = $op->actual_start_time ?: now();
                $op->save();

                \App\Domains\Production\Models\ProductionScheduleOperation::where('production_order_operation_id', $op->id)
                    ->whereNotIn('status', ['completed', 'skipped', 'cancelled'])
                    ->update(['status' => 'running', 'actual_start' => DB::raw('COALESCE(actual_start, NOW())')]);
            }

            app(ProductionEventService::class)->writeEvent($tenantId, [
                'production_order_id' => $challan->production_order_id,
                'production_order_operation_id' => $op?->id,
                'event_type' => 'Subcontract Dispatched',
                'title' => 'Sent to vendor',
                'description' => mb_substr(self::fmt((float) $challan->dispatched_wip_qty) . " unit(s) of work sent to {$challan->vendor?->name} on dispatch slip {$challan->challan_number}.", 0, 250),
                'severity' => 'info',
                'event_source' => 'SubcontractMaterialBalanceService',
                'triggered_by' => auth()->id(),
            ]);
        });
    }

    // ─── Receipt side: what the vendor used / returned ───────────────────────────

    /**
     * TF-08: no backflush. Material lines of the slip record how much the vendor used for the units it returned
     * (per-unit need × units processed, capped at what is still with the vendor) and what came back unused.
     *
     * @param array<int|string, float> $returns product_id => quantity returned unused (goes back to the floor)
     */
    public function recordMaterialUse(DeliveryChallan $challan, float $unitsProcessed, array $returns = []): void
    {
        $units = max(0.0, (float) $challan->dispatched_wip_qty);
        foreach ($challan->items()->where('item_type', 'material')->get() as $item) {
            $atVendor = max(0.0, (float) $item->quantity - (float) $item->consumed_qty - (float) $item->returned_qty - (float) $item->scrapped_qty);
            $perUnit = $units > 0 ? ((float) ($item->required_qty ?: $item->quantity)) / $units : 0.0;

            $used = min($atVendor, round($perUnit * $unitsProcessed, 4));
            $returned = min(max(0.0, $atVendor - $used), max(0.0, (float) ($returns[$item->product_id] ?? 0)));

            $item->consumed_qty = round((float) $item->consumed_qty + $used, 4);
            $item->returned_qty = round((float) $item->returned_qty + $returned, 4);
            $item->save();
        }
    }

    /**
     * Balance of an outsourced operation: per material sent / used / returned / scrapped / still at the vendor, plus the
     * units of work at the vendor. The flat keys (sent, consumed …) stay for the existing order screen.
     */
    public function getMaterialBalance(int $tenantId, int $orderId, int $operationId): array
    {
        $op = ProductionOrderOperation::where('tenant_id', $tenantId)
            ->where('production_order_id', $orderId)
            ->where(fn ($q) => $q->where('id', $operationId)->orWhere('routing_operation_id', $operationId))
            ->first();

        $empty = ['sent' => 0.0, 'consumed' => 0.0, 'returned' => 0.0, 'scrapped' => 0.0, 'remaining' => 0.0, 'lines' => [], 'units_sent' => 0.0, 'units_received' => 0.0, 'units_at_vendor' => 0.0];
        if (!$op) {
            return $empty;
        }

        $challans = DeliveryChallan::where('tenant_id', $tenantId)
            ->where('production_order_operation_id', $op->id)
            ->whereIn('status', DeliveryChallan::SENT_STATUSES)
            ->with('items.product')
            ->get();

        $lines = $challans->flatMap->items
            ->groupBy(fn ($item) => $item->item_type . ':' . $item->product_id)
            ->map(function ($items) {
                $first = $items->first();
                $sent = (float) $items->sum('quantity');
                $consumed = (float) $items->sum('consumed_qty');
                $returned = (float) $items->sum('returned_qty');
                $scrapped = (float) $items->sum('scrapped_qty');

                return [
                    'item_type' => $first->item_type,
                    'product_id' => $first->product_id,
                    'name' => $first->product?->name ?? ('#' . $first->product_id),
                    'uom' => $first->unit_of_measure,
                    'sent' => $sent,
                    'consumed' => $consumed,
                    'returned' => $returned,
                    'scrapped' => $scrapped,
                    'at_vendor' => $first->item_type === 'wip' ? null : max(0.0, round($sent - $consumed - $returned - $scrapped, 4)),
                ];
            })->values();

        $materials = $lines->where('item_type', 'material');

        return [
            'sent' => (float) $materials->sum('sent'),
            'consumed' => (float) $materials->sum('consumed'),
            'returned' => (float) $materials->sum('returned'),
            'scrapped' => (float) $materials->sum('scrapped'),
            'remaining' => (float) $materials->sum('at_vendor'),
            'lines' => $lines->all(),
            'units_sent' => (float) $challans->sum('dispatched_wip_qty'),
            'units_received' => (float) $challans->sum('received_qty'),
            'units_at_vendor' => (float) $challans->sum(fn ($c) => $c->unitsAtVendor()),
        ];
    }

    // ─── Rework slip ─────────────────────────────────────────────────────────────

    /**
     * Rejected units go back to the vendor for rework (N-19): a WIP slip for the operation. Its receipt goes through
     * SubcontractReceiptService like any other (no PO / GRN — rework is not billed again).
     */
    public function createReworkChallan(int $tenantId, ProductionOrderOperation $op, float $qty, array $data, int $userId): DeliveryChallan
    {
        if (!$op->isOutsourced()) {
            throw new InvalidArgumentException("Operation #{$op->operation_number} is not outsourced.");
        }
        if ($qty <= 0 || $qty > (float) $op->quantity_rejected + 0.0001) {
            throw new InvalidArgumentException('Rework quantity must be more than 0 and at most the rejected quantity (' . self::fmt((float) $op->quantity_rejected) . ').');
        }

        return DB::transaction(function () use ($tenantId, $op, $qty, $data, $userId) {
            $order = $op->order;
            $vendorId = $data['vendor_id'] ?? $op->vendor_id;

            $challan = app(\App\Domains\Production\Repositories\DeliveryChallanRepositoryInterface::class)->create($tenantId, [
                'type' => 'vendor_rework',
                'production_order_id' => $op->production_order_id,
                'production_order_operation_id' => $op->id,
                'vendor_id' => $vendorId,
                'warehouse_id' => $data['warehouse_id'] ?? null,
                'challan_date' => now()->toDateString(),
                'expected_return_date' => app(SubcontractDates::class)->expectedReturnDate($op, now())->toDateString(),
                'status' => DeliveryChallan::STATUS_DRAFT,
                'units_sent' => $qty,
                'notes' => $data['instructions'] ?? "Rework of {$qty} rejected unit(s) on Op #{$op->operation_number}. Reason: " . ($data['reason'] ?? 'Defect rectification'),
                'created_by' => $userId,
            ], [[
                'item_type' => 'wip',
                'product_id' => $op->source_product_id ?: $order?->product_id,
                'quantity' => $qty,
                'required_qty' => $qty,
                'unit_of_measure' => $order?->product?->unit_of_measure ?? 'PCS',
            ]]);

            $this->processDispatchActions($challan->fresh('items'), $tenantId);

            return $challan->fresh();
        });
    }

    private static function fmt(float $value): string
    {
        return rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.') ?: '0';
    }
}
