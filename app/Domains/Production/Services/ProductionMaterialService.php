<?php

namespace App\Domains\Production\Services;

use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Inventory\Services\StockService;
use App\Domains\Production\Models\ProductionOrderIssue;
use App\Domains\Production\Models\ProductionOrderReservation;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ProductionMaterialService
{
    /**
     * Reserve material quantities for a reservation.
     */
    public function reserveMaterial(int $reservationId, float $quantity): ProductionOrderReservation
    {
        $res = ProductionOrderReservation::findOrFail($reservationId);
        $res->quantity_reserved += $quantity;
        $res->save();

        return $res;
    }

    /**
     * Issue material quantities against a reservation snapshot.
     */
    public function issueMaterial(int $reservationId, float $quantity, ?string $remarks = null, ?int $userId = null, ?int $warehouseId = null): ProductionOrderIssue
    {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Quantity to issue must be greater than zero.');
        }

        return DB::transaction(function () use ($reservationId, $quantity, $remarks, $userId, $warehouseId) {
            $res = ProductionOrderReservation::findOrFail($reservationId);

            $warehouseId = $warehouseId ?: $res->warehouse_id;
            if (! $warehouseId) {
                throw new InvalidArgumentException('A warehouse is required before issuing material.');
            }

            if ($quantity > (float) $res->quantity_reserved) {
                throw new InvalidArgumentException("Cannot issue more than reserved quantity ({$res->quantity_reserved}). Refresh MRP or reserve stock first.");
            }

            StockService::releaseStock(
                $res->tenant_id,
                $res->product_id,
                $warehouseId,
                $quantity,
                'Production Order',
                $res->production_order_id,
                $res->id
            );

            StockService::recordOutflow(
                $res->tenant_id,
                $res->product_id,
                $warehouseId,
                $quantity,
                'Production Material Issue',
                $res->production_order_id
            );

            // If total issued exceeds planned, mark as additional issue
            $type = ($res->quantity_issued + $quantity) > $res->quantity_planned ? 'additional' : 'standard';

            $issue = ProductionOrderIssue::create([
                'tenant_id' => $res->tenant_id,
                'production_order_id' => $res->production_order_id,
                'reservation_id' => $res->id,
                'product_id' => $res->product_id,
                'warehouse_id' => $warehouseId,
                'quantity_issued' => $quantity,
                'issue_type' => $type,
                'issued_by' => $userId,
                'issued_at' => now(),
                'remarks' => $remarks,
            ]);

            $res->quantity_issued += $quantity;
            // Deduct from reserved as it's now consumed
            $res->quantity_reserved = max(0.0000, $res->quantity_reserved - $quantity);
            $res->save();

            app(ProductionEventService::class)->writeEvent($res->tenant_id, [
                'production_order_id' => $res->production_order_id,
                'event_type' => 'Material Issued',
                'title' => 'Material Issued',
                'description' => "Issued {$quantity} units of material for production order #{$res->production_order_id}.",
                'severity' => 'info',
                'event_source' => 'ProductionMaterialService',
                'triggered_by' => $userId,
            ]);

            return $issue;
        });
    }

    /**
     * Return unused material back to the inventory stock.
     */
    public function returnMaterial(int $reservationId, float $quantity, ?string $remarks = null, ?int $userId = null, ?int $warehouseId = null): ProductionOrderIssue
    {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Quantity to return must be greater than zero.');
        }

        return DB::transaction(function () use ($reservationId, $quantity, $remarks, $userId, $warehouseId) {
            $res = ProductionOrderReservation::findOrFail($reservationId);

            if ($res->quantity_issued < $quantity) {
                throw new InvalidArgumentException("Cannot return more quantity than has been issued (Issued: {$res->quantity_issued}).");
            }

            $warehouseId = $warehouseId ?: $res->warehouse_id ?: $this->defaultWarehouseId($res->tenant_id);
            if (! $warehouseId) {
                throw new InvalidArgumentException('A warehouse is required before returning material.');
            }

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
                'tenant_id' => $res->tenant_id,
                'production_order_id' => $res->production_order_id,
                'reservation_id' => $res->id,
                'product_id' => $res->product_id,
                'warehouse_id' => $warehouseId,
                'quantity_issued' => -$quantity, // Negative represents a return
                'issue_type' => 'return',
                'issued_by' => $userId,
                'issued_at' => now(),
                'remarks' => $remarks,
            ]);

            $res->quantity_issued -= $quantity;
            $res->save();

            return $issue;
        });
    }

    private function defaultWarehouseId(int $tenantId): ?int
    {
        return Warehouse::query()
            ->where('tenant_id', $tenantId)
            ->where('is_default', true)
            ->value('id')
            ?? Warehouse::query()->where('tenant_id', $tenantId)->value('id');
    }
}
