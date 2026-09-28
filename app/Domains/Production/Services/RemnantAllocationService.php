<?php

namespace App\Domains\Production\Services;

use App\Domains\Inventory\Models\InventoryRemnant;
use App\Domains\Inventory\Models\InventoryRemnantConsumption;
use App\Domains\Inventory\Services\RemnantInventoryService;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderRemnantAllocation;
use App\Domains\Production\Models\ProductionOrderReservation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RemnantAllocationService
{
    public function __construct(
        protected RemnantInventoryService $remnantService,
        protected ProductionWipService $wipService
    ) {}

    /**
     * Find deterministic compatible remnants for a given product and dimensional requirement.
     * Linear: same product AND available_length >= required_cut_length.
     * Sheet: same product AND same thickness AND ((L >= req_L && W >= req_W) || (L >= req_W && W >= req_L)).
     */
    public function findCompatibleRemnants(int $tenantId, int $productId, array $requirements = []): Collection
    {
        $query = InventoryRemnant::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('product_id', $productId)
            ->whereIn('status', [InventoryRemnant::STATUS_AVAILABLE, InventoryRemnant::STATUS_PARTIALLY_RESERVED])
            ->whereRaw('(current_quantity - reserved_quantity) > 0.0001');

        if (!empty($requirements['warehouse_id'])) {
            $query->where('warehouse_id', $requirements['warehouse_id']);
        }

        $remnants = $query->get();

        $reqLength = isset($requirements['required_length']) ? (float) $requirements['required_length'] : null;
        $reqWidth = isset($requirements['required_width']) ? (float) $requirements['required_width'] : null;
        $reqThickness = isset($requirements['thickness']) ? (float) $requirements['thickness'] : null;

        return $remnants->filter(function (InventoryRemnant $remnant) use ($reqLength, $reqWidth, $reqThickness) {
            // Must have available quantity
            if ($remnant->available_quantity <= 0.0001) {
                return false;
            }

            if ($remnant->measurement_type === InventoryRemnant::TYPE_LINEAR && $reqLength !== null && $reqLength > 0) {
                return $remnant->available_length >= $reqLength;
            }

            if ($remnant->measurement_type === InventoryRemnant::TYPE_SHEET) {
                if ($reqThickness !== null && $remnant->thickness !== null && abs((float) $remnant->thickness - $reqThickness) > 0.001) {
                    return false;
                }

                if ($reqLength !== null && $reqWidth !== null && $reqLength > 0 && $reqWidth > 0) {
                    $curL = (float) $remnant->available_length;
                    $curW = (float) $remnant->current_width;

                    $standardFit = ($curL >= $reqLength && $curW >= $reqWidth);
                    $rotatedFit = ($curL >= $reqWidth && $curW >= $reqLength);

                    return $standardFit || $rotatedFit;
                }
            }

            return true;
        })->sortBy(function (InventoryRemnant $remnant) {
            // Best fit: smallest sufficient available length / qty first to minimize offcut waste
            return $remnant->available_length > 0 ? $remnant->available_length : $remnant->available_quantity;
        })->values();
    }

    /**
     * Allocate one or more remnants to a production order reservation line.
     * Supports 1-to-many remnant allocation (e.g. 2000 mm fulfilled by 800mm + 700mm + 500mm).
     *
     * @param array $allocations Array of ['remnant_id' => int, 'allocated_length' => ?float, 'allocated_quantity' => ?float]
     */
    public function allocateRemnants(
        int $tenantId,
        int $orderId,
        int $reservationId,
        array $allocations,
        ?int $userId = null
    ): array {
        if (empty($allocations)) {
            throw new InvalidArgumentException("At least one remnant must be provided for allocation.");
        }

        return DB::transaction(function () use ($tenantId, $orderId, $reservationId, $allocations, $userId) {
            $order = ProductionOrder::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->findOrFail($orderId);

            $reservation = ProductionOrderReservation::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('production_order_id', $order->id)
                ->lockForUpdate()
                ->findOrFail($reservationId);

            $createdAllocations = [];
            $totalAllocatedQty = 0.0;

            foreach ($allocations as $allocData) {
                $remnantId = (int) $allocData['remnant_id'];
                $remnant = InventoryRemnant::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->lockForUpdate()
                    ->findOrFail($remnantId);

                if ($remnant->product_id !== $reservation->product_id) {
                    throw new InvalidArgumentException(
                        "Remnant #{$remnant->remnant_code} product does not match reservation requirement product."
                    );
                }

                $allocLength = isset($allocData['allocated_length']) ? (float) $allocData['allocated_length'] : null;
                $allocQty = isset($allocData['allocated_quantity']) ? (float) $allocData['allocated_quantity'] : null;

                // Derive allocated_quantity from length if not explicitly provided
                if ($allocQty === null && $allocLength !== null && $remnant->measurement_type === InventoryRemnant::TYPE_LINEAR) {
                    $ratio = $allocLength / max(0.0001, (float) $remnant->current_length);
                    $allocQty = round($remnant->current_quantity * $ratio, 4);
                }

                if ($allocQty === null || $allocQty <= 0) {
                    throw new InvalidArgumentException("Allocated quantity must be greater than zero.");
                }

                // Reserve the remnant
                $this->remnantService->reserveRemnant($remnant->id, $allocQty, $allocLength);

                // Create ownership allocation record
                $allocation = ProductionOrderRemnantAllocation::create([
                    'tenant_id' => $tenantId,
                    'production_order_id' => $order->id,
                    'production_order_reservation_id' => $reservation->id,
                    'remnant_id' => $remnant->id,
                    'allocated_quantity' => $allocQty,
                    'allocated_length' => $allocLength,
                    'status' => ProductionOrderRemnantAllocation::STATUS_RESERVED,
                    'reserved_at' => now(),
                ]);

                $totalAllocatedQty += $allocQty;
                $createdAllocations[] = $allocation;
            }

            // Update reservation quantity_reserved
            $reservation->quantity_reserved += $totalAllocatedQty;
            $reservation->save();

            return $createdAllocations;
        });
    }

    /**
     * Release a remnant allocation back to available status.
     */
    public function releaseAllocation(int $allocationId, ?int $userId = null): void
    {
        DB::transaction(function () use ($allocationId, $userId) {
            $allocation = ProductionOrderRemnantAllocation::lockForUpdate()->findOrFail($allocationId);

            if ($allocation->status !== ProductionOrderRemnantAllocation::STATUS_RESERVED) {
                throw new InvalidArgumentException("Only active reserved allocations can be released.");
            }

            // Release remnant reservation
            $this->remnantService->releaseReservation(
                $allocation->remnant_id,
                $allocation->allocated_quantity,
                $allocation->allocated_length
            );

            // Decrement reservation quantity_reserved
            if ($allocation->production_order_reservation_id) {
                $res = ProductionOrderReservation::lockForUpdate()->find($allocation->production_order_reservation_id);
                if ($res) {
                    $res->quantity_reserved = max(0.0, (float) $res->quantity_reserved - $allocation->allocated_quantity);
                    $res->save();
                }
            }

            $allocation->status = ProductionOrderRemnantAllocation::STATUS_RELEASED;
            $allocation->released_at = now();
            $allocation->save();
        });
    }

    /**
     * Physically consume an allocated remnant in production.
     * Decrements physical remnant inventory, debits consuming order WIP, and updates reservation.
     */
    public function consumeAllocatedRemnant(
        int $allocationId,
        ?float $actualConsumedLength = null,
        ?float $actualConsumedQty = null,
        ?int $userId = null
    ): InventoryRemnantConsumption {
        return DB::transaction(function () use ($allocationId, $actualConsumedLength, $actualConsumedQty, $userId) {
            $allocation = ProductionOrderRemnantAllocation::lockForUpdate()->findOrFail($allocationId);

            if ($allocation->status !== ProductionOrderRemnantAllocation::STATUS_RESERVED) {
                throw new InvalidArgumentException("Allocation is not in reserved status.");
            }

            $consumedQty = $actualConsumedQty ?? $allocation->allocated_quantity;
            $consumedLength = $actualConsumedLength ?? $allocation->allocated_length;

            $remnant = InventoryRemnant::lockForUpdate()->findOrFail($allocation->remnant_id);

            // Physically consume remnant
            $consumption = $this->remnantService->consumeRemnant(
                $remnant->id,
                $consumedQty,
                $consumedLength,
                $allocation->production_order_id,
                null,
                $userId,
                "Consumed via allocation #{$allocation->id} for Production Order #{$allocation->production_order_id}"
            );

            // Mark allocation as consumed
            $allocation->status = ProductionOrderRemnantAllocation::STATUS_CONSUMED;
            $allocation->consumed_at = now();
            $allocation->save();

            // Update reservation issued and reserved quantities
            if ($allocation->production_order_reservation_id) {
                $res = ProductionOrderReservation::lockForUpdate()->find($allocation->production_order_reservation_id);
                if ($res) {
                    $res->quantity_issued += $consumedQty;
                    $res->quantity_reserved = max(0.0, (float) $res->quantity_reserved - $allocation->allocated_quantity);
                    $res->save();
                }
            }

            // WIP Costing Integration: Debit consuming order WIP balance
            $consumedValue = round($consumedQty * $remnant->unit_cost, 4);
            if ($consumedValue > 0) {
                $this->wipService->addMaterialCost($allocation->production_order_id, $consumedValue);
            }

            return $consumption;
        });
    }
}
