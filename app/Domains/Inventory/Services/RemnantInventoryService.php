<?php

namespace App\Domains\Inventory\Services;

use App\Domains\Inventory\Models\InventoryRemnant;
use App\Domains\Inventory\Models\InventoryRemnantConsumption;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Services\ProductionExecutionService;
use App\Domains\Production\Services\ProductionWipService;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RemnantInventoryService
{
    /**
     * Derive canonical product UOM quantity from measurement type and physical inputs.
     * Enforces strict type-safety and explicit V1 conversion rules.
     */
    public function calculateCanonicalQuantity(Product $product, string $measurementType, array $measurements): float
    {
        return app(MaterialMeasurementService::class)->calculateCanonicalQuantity($product, $measurementType, $measurements);
    }

    /**
     * Register a new reusable remnant from shopfloor or manual inventory entry.
     */
    public function registerRemnant(int $tenantId, array $data, ?int $userId = null): InventoryRemnant
    {
        return DB::transaction(function () use ($tenantId, $data, $userId) {
            $product = Product::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->findOrFail($data['product_id']);

            $warehouseId = $data['warehouse_id'] ?? null;
            if ($warehouseId) {
                $wh = Warehouse::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->find($warehouseId);
                if (!$wh) {
                    throw new InvalidArgumentException("Warehouse does not belong to this tenant or does not exist.");
                }
            }

            $measurementType = $data['measurement_type'] ?? InventoryRemnant::TYPE_LINEAR;

            // Calculate canonical product quantity
            $canonicalQty = $this->calculateCanonicalQuantity($product, $measurementType, $data);

            // Remaining Quantity Protection
            $sourceOpId = $data['source_production_order_operation_id'] ?? null;
            $sourceOrderId = $data['source_production_order_id'] ?? null;
            if ($sourceOpId) {
                $sourceOp = \App\Domains\Production\Models\ProductionOrderOperation::where('tenant_id', $tenantId)->find($sourceOpId);
                if ($sourceOp) {
                    $balance = $sourceOp->getMaterialBalance($product->id);
                    if ($balance['has_reservation'] && $balance['issued_qty'] > 0) {
                        if ($canonicalQty > ($balance['remaining_qty'] + 0.0001)) {
                            throw new InvalidArgumentException(
                                "Cannot save offcut of " . number_format($canonicalQty, 4) . ": Exceeds remaining disposable material quantity of " . number_format($balance['remaining_qty'], 4) . "."
                            );
                        }
                    }
                }
            } elseif ($sourceOrderId) {
                $firstOp = \App\Domains\Production\Models\ProductionOrderOperation::where('tenant_id', $tenantId)
                    ->where('production_order_id', $sourceOrderId)
                    ->first();
                if ($firstOp) {
                    $balance = $firstOp->getMaterialBalance($product->id);
                    if ($balance['has_reservation'] && $balance['issued_qty'] > 0) {
                        if ($canonicalQty > ($balance['remaining_qty'] + 0.0001)) {
                            throw new InvalidArgumentException(
                                "Cannot save offcut of " . number_format($canonicalQty, 4) . ": Exceeds remaining disposable material quantity of " . number_format($balance['remaining_qty'], 4) . "."
                            );
                        }
                    }
                }
            }

            // Determine approval policy from tenant settings
            $tenant = Tenant::find($tenantId);
            $settings = is_array($tenant?->settings) ? $tenant->settings : [];
            $policy = $settings['remnant_release_policy'] ?? 'immediate';

            $status = ($policy === 'require_approval')
                ? InventoryRemnant::STATUS_PENDING_CONFIRMATION
                : InventoryRemnant::STATUS_AVAILABLE;

            // Generate unique remnant code: REM-YYYYMM-XXXXX
            $prefix = 'REM-' . date('Ym') . '-';
            $latest = InventoryRemnant::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('remnant_code', 'LIKE', "{$prefix}%")
                ->orderBy('id', 'desc')
                ->lockForUpdate()
                ->first();

            $nextNum = 1;
            if ($latest && preg_match('/-(\d+)$/', $latest->remnant_code, $matches)) {
                $nextNum = ((int) $matches[1]) + 1;
            }
            $code = $prefix . str_pad((string) $nextNum, 5, '0', STR_PAD_LEFT);

            // Unit cost resolution (proportional valuation)
            $unitCost = (float) ($data['unit_cost'] ?? $product->unit_cost ?? $product->cost_price ?? 0.0);

            $remnant = InventoryRemnant::create([
                'tenant_id' => $tenantId,
                'remnant_code' => $code,
                'product_id' => $product->id,
                'warehouse_id' => $data['warehouse_id'] ?? null,
                'warehouse_location' => $data['warehouse_location'] ?? $data['rack_bin'] ?? null,
                'measurement_type' => $measurementType,
                'status' => $status,
                'parent_batch_id' => $data['parent_batch_id'] ?? null,
                'heat_number' => $data['heat_number'] ?? null,
                'parent_remnant_id' => $data['parent_remnant_id'] ?? null,
                'source_production_order_id' => $data['source_production_order_id'] ?? null,
                'source_production_order_operation_id' => $data['source_production_order_operation_id'] ?? null,
                'dimension_unit' => $data['dimension_unit'] ?? 'mm',
                'initial_length' => $data['length'] ?? null,
                'current_length' => $data['length'] ?? null,
                'reserved_length' => 0.0000,
                'initial_width' => $data['width'] ?? null,
                'current_width' => $data['width'] ?? null,
                'thickness' => $data['thickness'] ?? null,
                'weight' => $data['weight'] ?? null,
                'weight_unit' => $data['weight_unit'] ?? null,
                'pieces' => (int) ($data['pieces'] ?? 1),
                'uom_id' => $product->uom_id,
                'initial_quantity' => $canonicalQty,
                'current_quantity' => $canonicalQty,
                'reserved_quantity' => 0.0000,
                'unit_cost' => $unitCost,
                'confirmed_by' => ($status === InventoryRemnant::STATUS_AVAILABLE) ? $userId : null,
                'confirmed_at' => ($status === InventoryRemnant::STATUS_AVAILABLE) ? now() : null,
                'created_by' => $userId,
                'notes' => $data['notes'] ?? null,
            ]);

            // WIP Costing Integration: Credit source order WIP balance
            if (!empty($remnant->source_production_order_id)) {
                $remnantValue = round($canonicalQty * $unitCost, 4);
                if ($remnantValue > 0) {
                    app(ProductionWipService::class)->deductMaterialCost(
                        $remnant->source_production_order_id,
                        $remnantValue
                    );
                }
            }

            return $remnant;
        });
    }

    /**
     * Confirm a pending remnant when require_approval policy is active.
     */
    public function confirmRemnant(int $remnantId, int $userId): InventoryRemnant
    {
        return DB::transaction(function () use ($remnantId, $userId) {
            $remnant = InventoryRemnant::lockForUpdate()->findOrFail($remnantId);

            if ($remnant->status !== InventoryRemnant::STATUS_PENDING_CONFIRMATION) {
                throw new InvalidArgumentException("Remnant is not pending confirmation.");
            }

            $remnant->status = InventoryRemnant::STATUS_AVAILABLE;
            $remnant->confirmed_by = $userId;
            $remnant->confirmed_at = now();
            $remnant->save();

            return $remnant;
        });
    }

    /**
     * Physically split a remnant into parent and child remnants.
     * Records event_type = 'split' in lineage ledger.
     * Splitting is NOT production consumption.
     */
    public function splitRemnant(int $remnantId, array $splitData, int $userId): array
    {
        return DB::transaction(function () use ($remnantId, $splitData, $userId) {
            $parent = InventoryRemnant::lockForUpdate()->findOrFail($remnantId);

            if (!in_array($parent->status, [InventoryRemnant::STATUS_AVAILABLE, InventoryRemnant::STATUS_PARTIALLY_RESERVED], true)) {
                throw new InvalidArgumentException("Cannot split remnant in status [{$parent->status}].");
            }

            $splitLength = isset($splitData['split_length']) ? (float) $splitData['split_length'] : null;
            $splitPieces = (int) ($splitData['split_pieces'] ?? 1);

            if ($parent->measurement_type === InventoryRemnant::TYPE_LINEAR) {
                if ($splitLength === null || $splitLength <= 0) {
                    throw new InvalidArgumentException("Split length must be specified and greater than zero.");
                }

                $availableLength = $parent->available_length;
                if ($splitLength >= $availableLength) {
                    throw new InvalidArgumentException(
                        "Split length ({$splitLength} mm) cannot exceed or equal available length ({$availableLength} mm)."
                    );
                }

                // Calculate split canonical quantity proportionally
                $splitRatio = $splitLength / max(0.0001, (float) $parent->current_length);
                $splitQty = round($parent->current_quantity * $splitRatio, 4);

                $remainingLength = $parent->current_length - $splitLength;
                $remainingQty = max(0.0, round($parent->current_quantity - $splitQty, 4));

                $childData = [
                    'product_id' => $parent->product_id,
                    'warehouse_id' => $splitData['warehouse_id'] ?? $parent->warehouse_id,
                    'warehouse_location' => $splitData['warehouse_location'] ?? $parent->warehouse_location,
                    'measurement_type' => $parent->measurement_type,
                    'parent_batch_id' => $parent->parent_batch_id,
                    'heat_number' => $parent->heat_number,
                    'parent_remnant_id' => $parent->id,
                    'source_production_order_id' => $parent->source_production_order_id,
                    'dimension_unit' => $parent->dimension_unit,
                    'length' => $splitLength,
                    'pieces' => $splitPieces,
                    'unit_cost' => $parent->unit_cost,
                    'notes' => "Split off from remnant #{$parent->remnant_code}",
                ];
            } elseif ($parent->measurement_type === InventoryRemnant::TYPE_COUNT) {
                if ($splitPieces <= 0 || $splitPieces >= $parent->pieces) {
                    throw new InvalidArgumentException("Split pieces must be greater than zero and less than parent pieces.");
                }

                $splitQty = (float) $splitPieces;
                $splitLength = null;
                $remainingLength = null;
                $remainingQty = max(0.0, $parent->current_quantity - $splitQty);

                $childData = [
                    'product_id' => $parent->product_id,
                    'warehouse_id' => $splitData['warehouse_id'] ?? $parent->warehouse_id,
                    'warehouse_location' => $splitData['warehouse_location'] ?? $parent->warehouse_location,
                    'measurement_type' => $parent->measurement_type,
                    'parent_batch_id' => $parent->parent_batch_id,
                    'heat_number' => $parent->heat_number,
                    'parent_remnant_id' => $parent->id,
                    'source_production_order_id' => $parent->source_production_order_id,
                    'pieces' => $splitPieces,
                    'unit_cost' => $parent->unit_cost,
                    'notes' => "Split off from remnant #{$parent->remnant_code}",
                ];
            } else {
                // Weight or sheet split
                $splitQty = (float) ($splitData['split_quantity'] ?? 0);
                if ($splitQty <= 0 || $splitQty >= $parent->available_quantity) {
                    throw new InvalidArgumentException("Split quantity must be greater than zero and less than available quantity.");
                }

                $splitLength = null;
                $remainingLength = null;
                $remainingQty = max(0.0, $parent->current_quantity - $splitQty);

                $childData = [
                    'product_id' => $parent->product_id,
                    'warehouse_id' => $splitData['warehouse_id'] ?? $parent->warehouse_id,
                    'warehouse_location' => $splitData['warehouse_location'] ?? $parent->warehouse_location,
                    'measurement_type' => $parent->measurement_type,
                    'parent_batch_id' => $parent->parent_batch_id,
                    'heat_number' => $parent->heat_number,
                    'parent_remnant_id' => $parent->id,
                    'source_production_order_id' => $parent->source_production_order_id,
                    'weight' => $splitData['weight'] ?? null,
                    'weight_unit' => $splitData['weight_unit'] ?? $parent->weight_unit,
                    'unit_cost' => $parent->unit_cost,
                    'notes' => "Split off from remnant #{$parent->remnant_code}",
                ];
            }

            // Create child remnant
            $child = $this->registerRemnant($parent->tenant_id, $childData, $userId);

            // Update parent remnant
            if ($parent->measurement_type === InventoryRemnant::TYPE_LINEAR) {
                $parent->current_length = $remainingLength;
            } elseif ($parent->measurement_type === InventoryRemnant::TYPE_COUNT) {
                $parent->pieces -= $splitPieces;
            }
            $parent->current_quantity = $remainingQty;
            $parent->save();

            // Record split event in lineage ledger (NOT a consumption event)
            InventoryRemnantConsumption::create([
                'tenant_id' => $parent->tenant_id,
                'remnant_id' => $parent->id,
                'event_type' => InventoryRemnantConsumption::EVENT_SPLIT,
                'production_order_id' => $splitData['production_order_id'] ?? null,
                'production_order_operation_id' => $splitData['production_order_operation_id'] ?? null,
                'split_remnant_id' => $child->id,
                'consumed_quantity' => $splitQty,
                'consumed_length' => $splitLength,
                'remaining_quantity' => $remainingQty,
                'remaining_length' => $remainingLength,
                'unit_cost' => $parent->unit_cost,
                'total_cost' => round($splitQty * $parent->unit_cost, 4),
                'performed_by' => $userId,
                'performed_at' => now(),
                'notes' => $splitData['notes'] ?? "Physically split into child remnant #{$child->remnant_code}",
            ]);

            return [
                'parent' => $parent->fresh(),
                'child' => $child->fresh(),
            ];
        });
    }

    /**
     * Mark a degraded/damaged remnant as unusable scrap.
     * Delegates genuine scrap recording to ProductionExecutionService if source order is present.
     */
    public function scrapRemnant(int $remnantId, string $reason, int $userId): InventoryRemnant
    {
        return DB::transaction(function () use ($remnantId, $reason, $userId) {
            $remnant = InventoryRemnant::lockForUpdate()->findOrFail($remnantId);

            if ($remnant->status === InventoryRemnant::STATUS_SCRAPPED) {
                throw new InvalidArgumentException("Remnant is already scrapped.");
            }

            if ($remnant->reserved_quantity > 0) {
                throw new InvalidArgumentException("Cannot scrap a remnant with active reservations. Release reservations first.");
            }

            $scrappedQty = $remnant->current_quantity;
            $scrappedLength = $remnant->current_length;

            $remnant->status = InventoryRemnant::STATUS_SCRAPPED;
            $remnant->current_quantity = 0.0000;
            $remnant->current_length = 0.0000;
            $remnant->save();

            // Delegate to production scrap execution if order context exists
            if ($remnant->source_production_order_id) {
                app(ProductionExecutionService::class)->logScrap(
                    $remnant->source_production_order_id,
                    $remnant->source_production_order_operation_id,
                    $remnant->product_id,
                    $scrappedQty,
                    "Remnant #{$remnant->remnant_code} scrapped: {$reason}",
                    $userId,
                    $remnant->warehouse_id
                );
            }

            // Write ledger entry
            InventoryRemnantConsumption::create([
                'tenant_id' => $remnant->tenant_id,
                'remnant_id' => $remnant->id,
                'event_type' => InventoryRemnantConsumption::EVENT_CONSUMPTION,
                'production_order_id' => $remnant->source_production_order_id,
                'production_order_operation_id' => $remnant->source_production_order_operation_id,
                'consumed_quantity' => $scrappedQty,
                'consumed_length' => $scrappedLength,
                'remaining_quantity' => 0.0000,
                'remaining_length' => 0.0000,
                'unit_cost' => $remnant->unit_cost,
                'total_cost' => round($scrappedQty * $remnant->unit_cost, 4),
                'performed_by' => $userId,
                'performed_at' => now(),
                'notes' => "Scrapped remnant: {$reason}",
            ]);

            return $remnant;
        });
    }

    /**
     * Reserve remnant quantity and/or length.
     * Concurrency guarded with lockForUpdate().
     */
    public function reserveRemnant(int $remnantId, float $quantity, ?float $length = null): void
    {
        $remnant = InventoryRemnant::lockForUpdate()->findOrFail($remnantId);

        if (!in_array($remnant->status, [InventoryRemnant::STATUS_AVAILABLE, InventoryRemnant::STATUS_PARTIALLY_RESERVED], true)) {
            throw new InvalidArgumentException("Remnant #{$remnant->remnant_code} is not available for reservation.");
        }

        if ($quantity > $remnant->available_quantity) {
            throw new InvalidArgumentException(
                "Requested reservation qty ({$quantity}) exceeds available qty ({$remnant->available_quantity}) for remnant #{$remnant->remnant_code}."
            );
        }

        if ($length !== null && $length > $remnant->available_length) {
            throw new InvalidArgumentException(
                "Requested reservation length ({$length} mm) exceeds available length ({$remnant->available_length} mm) for remnant #{$remnant->remnant_code}."
            );
        }

        $remnant->reserved_quantity += $quantity;
        if ($length !== null) {
            $remnant->reserved_length = ($remnant->reserved_length ?? 0.0) + $length;
        }

        $remnant->status = ($remnant->available_quantity <= 0.0001)
            ? InventoryRemnant::STATUS_FULLY_RESERVED
            : InventoryRemnant::STATUS_PARTIALLY_RESERVED;

        $remnant->save();
    }

    /**
     * Release remnant reservation without reducing physical quantity.
     */
    public function releaseReservation(int $remnantId, float $quantity, ?float $length = null): void
    {
        $remnant = InventoryRemnant::lockForUpdate()->findOrFail($remnantId);

        $remnant->reserved_quantity = max(0.0, (float) $remnant->reserved_quantity - $quantity);
        if ($length !== null) {
            $remnant->reserved_length = max(0.0, (float) ($remnant->reserved_length ?? 0.0) - $length);
        }

        if ($remnant->current_quantity <= 0.0001) {
            $remnant->status = InventoryRemnant::STATUS_CONSUMED;
        } elseif ($remnant->reserved_quantity > 0) {
            $remnant->status = InventoryRemnant::STATUS_PARTIALLY_RESERVED;
        } else {
            $remnant->status = InventoryRemnant::STATUS_AVAILABLE;
        }

        $remnant->save();
    }

    /**
     * Physically consume a remnant (partial or full).
     * Reduces physical current_quantity and current_length.
     * Records event_type = 'consumption' in lineage ledger.
     */
    public function consumeRemnant(
        int $remnantId,
        float $quantity,
        ?float $length = null,
        ?int $orderId = null,
        ?int $operationId = null,
        ?int $userId = null,
        ?string $notes = null
    ): InventoryRemnantConsumption {
        return DB::transaction(function () use ($remnantId, $quantity, $length, $orderId, $operationId, $userId, $notes) {
            $remnant = InventoryRemnant::lockForUpdate()->findOrFail($remnantId);

            if ($quantity <= 0) {
                throw new InvalidArgumentException("Consumed quantity must be greater than zero.");
            }

            if ($quantity > $remnant->current_quantity) {
                throw new InvalidArgumentException(
                    "Cannot consume ({$quantity}) more than current physical quantity ({$remnant->current_quantity}) for remnant #{$remnant->remnant_code}."
                );
            }

            if ($length !== null && $length > ($remnant->current_length ?? 0.0)) {
                throw new InvalidArgumentException(
                    "Cannot consume ({$length} mm) more than current physical length ({$remnant->current_length} mm) for remnant #{$remnant->remnant_code}."
                );
            }

            // Deduct physical inventory
            $remnant->current_quantity = max(0.0, round($remnant->current_quantity - $quantity, 4));
            $remnant->reserved_quantity = max(0.0, round($remnant->reserved_quantity - $quantity, 4));

            if ($length !== null && $remnant->current_length !== null) {
                $remnant->current_length = max(0.0, round($remnant->current_length - $length, 4));
                $remnant->reserved_length = max(0.0, round(($remnant->reserved_length ?? 0.0) - $length, 4));
            }

            // Update remnant status
            if ($remnant->current_quantity <= 0.0001) {
                $remnant->status = InventoryRemnant::STATUS_CONSUMED;
            } elseif ($remnant->reserved_quantity > 0) {
                $remnant->status = InventoryRemnant::STATUS_PARTIALLY_RESERVED;
            } else {
                $remnant->status = InventoryRemnant::STATUS_AVAILABLE;
            }

            $remnant->save();

            // Record immutable consumption ledger entry
            $consumption = InventoryRemnantConsumption::create([
                'tenant_id' => $remnant->tenant_id,
                'remnant_id' => $remnant->id,
                'event_type' => InventoryRemnantConsumption::EVENT_CONSUMPTION,
                'production_order_id' => $orderId,
                'production_order_operation_id' => $operationId,
                'consumed_quantity' => $quantity,
                'consumed_length' => $length,
                'remaining_quantity' => $remnant->current_quantity,
                'remaining_length' => $remnant->current_length,
                'unit_cost' => $remnant->unit_cost,
                'total_cost' => round($quantity * $remnant->unit_cost, 4),
                'performed_by' => $userId,
                'performed_at' => now(),
                'notes' => $notes ?? "Consumed for Production Order #{$orderId}",
            ]);

            return $consumption;
        });
    }
}
