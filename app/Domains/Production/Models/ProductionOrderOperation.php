<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionOrderOperation extends BaseModel
{
    protected $table = 'production_order_operations';

    public const STATUS_WAITING   = 'waiting';
    public const STATUS_READY     = 'ready';
    public const STATUS_RUNNING   = 'running';
    public const STATUS_PAUSED    = 'paused';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_SKIPPED   = 'skipped';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_WAITING,
        self::STATUS_READY,
        self::STATUS_RUNNING,
        self::STATUS_PAUSED,
        self::STATUS_COMPLETED,
        self::STATUS_SKIPPED,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'tenant_id',
        'production_order_id',
        'routing_operation_id',
        'previous_operation_id',
        'source_product_id',
        'source_bom_id',
        'source_routing_id',
        'bom_level',
        'target_produced_qty',
        'is_intermediate',
        'quantity_claimed',
        'quantity_consumed',
        'sequence',
        'operation_number',
        'name',
        'work_center_id',
        'machine_id',
        'status',
        'setup_time_planned',
        'processing_time_planned',
        'total_time_planned',
        'setup_time_actual',
        'processing_time_actual',
        'actual_start_time',
        'actual_end_time',
        'quantity_produced',
        'quantity_rejected',
        'quantity_scrapped',
        'machine_used_id',
        'operator_id',
        'is_external',
        'vendor_id',
        'subcontract_lead_time_days',
        'subcontract_cost_per_unit',
        'subcontract_service_product_id',
        'material_supply_type',
        'subcontract_input_type',
        'dispatch_buffer_days',
        'return_buffer_days',
        'purchase_order_id',
        'purchase_order_item_id',
        'quality_required',
        'parallel_group',
        'is_parallel',
        'parallel_type',
        'queue_threshold_enabled',
        'overlap_enabled',
        'transfer_batch_quantity',
        'transfer_lag_minutes',
        'quantity_transferred_out',
        'quantity_transferred_in',
    ];

    protected $casts = [
        'sequence'                => 'integer',
        'bom_level'               => 'integer',
        'target_produced_qty'     => 'float',
        'is_intermediate'         => 'boolean',
        'quantity_claimed'        => 'float',
        'quantity_consumed'       => 'float',
        'setup_time_planned'      => 'float',
        'processing_time_planned' => 'float',
        'total_time_planned'      => 'float',
        'setup_time_actual'       => 'float',
        'processing_time_actual'  => 'float',
        'is_external'             => 'boolean',
        'quality_required'        => 'boolean',
        'subcontract_lead_time_days'=> 'integer',
        'subcontract_cost_per_unit' => 'float',
        'dispatch_buffer_days'      => 'integer',
        'return_buffer_days'        => 'integer',
        'quantity_produced'       => 'float',
        'quantity_rejected'       => 'float',
        'quantity_scrapped'       => 'float',
        'actual_start_time'       => 'datetime',
        'actual_end_time'         => 'datetime',
        'queue_threshold_enabled' => 'boolean',
        'overlap_enabled'         => 'boolean',
        'transfer_batch_quantity' => 'float',
        'transfer_lag_minutes'    => 'integer',
        'quantity_transferred_out'=> 'float',
        'quantity_transferred_in' => 'float',
    ];

    public function sourceProduct(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Inventory\Models\Product::class, 'source_product_id');
    }

    public function sourceBom(): BelongsTo
    {
        return $this->belongsTo(ProductionBom::class, 'source_bom_id');
    }

    public function sourceRouting(): BelongsTo
    {
        return $this->belongsTo(Routing::class, 'source_routing_id');
    }

    public function predecessorDependencies(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'production_order_operation_dependencies',
            'operation_id',
            'predecessor_operation_id'
        )->withPivot('dependency_type')->withTimestamps();
    }

    public function successorDependencies(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'production_order_operation_dependencies',
            'predecessor_operation_id',
            'operation_id'
        )->withPivot('dependency_type')->withTimestamps();
    }

    public function isEntryOperation(): bool
    {
        if ($this->previous_operation_id) {
            return false;
        }

        if ($this->routingOperation?->previous_operation_id) {
            return false;
        }

        $hasInterPred = \App\Domains\Production\Models\ProductionOrderOperationDependency::where('tenant_id', $this->tenant_id)
            ->where('production_order_id', $this->production_order_id)
            ->where('operation_id', $this->id)
            ->exists();

        if ($hasInterPred) {
            return false;
        }

        // Check if there is an earlier operation in the same product chain
        $hasEarlierOp = static::where('production_order_id', $this->production_order_id)
            ->where('id', '!=', $this->id)
            ->where(function ($q) {
                if ($this->source_product_id) {
                    $q->where('source_product_id', $this->source_product_id);
                } else {
                    $q->whereNull('source_product_id');
                }
            })
            ->where('sequence', '<', $this->sequence)
            ->exists();

        if ($hasEarlierOp) {
            return false;
        }

        return true;
    }

    public function getOverlapEnabledAttribute(): bool
    {
        return (bool) ($this->attributes['queue_threshold_enabled'] ?? $this->attributes['overlap_enabled'] ?? false);
    }

    public function setOverlapEnabledAttribute($value): void
    {
        $bool = filter_var($value, FILTER_VALIDATE_BOOLEAN);
        $this->attributes['queue_threshold_enabled'] = $bool;
        if (array_key_exists('overlap_enabled', $this->attributes)) {
            $this->attributes['overlap_enabled'] = $bool;
        }
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }

    public function productionOrder(): BelongsTo
    {
        return $this->order();
    }

    public function routingOperation(): BelongsTo
    {
        return $this->belongsTo(RoutingOperation::class, 'routing_operation_id');
    }

    public function previousOperation(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_operation_id');
    }

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class, 'work_center_id');
    }

    public function reworks(): HasMany
    {
        return $this->hasMany(ProductionOrderRework::class, 'production_order_operation_id');
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class, 'machine_id');
    }

    public function machineUsed(): BelongsTo
    {
        return $this->belongsTo(Machine::class, 'machine_used_id');
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Inventory\Models\Vendor::class, 'vendor_id');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Purchase\Models\PurchaseOrder::class, 'purchase_order_id');
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Purchase\Models\PurchaseOrderItem::class, 'purchase_order_item_id');
    }

    public function subcontractServiceProduct(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Inventory\Models\Product::class, 'subcontract_service_product_id');
    }

    public function progressLogs(): HasMany
    {
        return $this->hasMany(ProductionOrderProgressLog::class, 'operation_id');
    }

    public function scheduleOperation(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ProductionScheduleOperation::class, 'production_order_operation_id');
    }

    public function operatorAssignments(): HasMany
    {
        return $this->hasMany(ProductionOperatorAssignment::class, 'production_order_operation_id');
    }

    public function deliveryChallans(): HasMany
    {
        return $this->hasMany(DeliveryChallan::class, 'production_order_operation_id');
    }

    public function latestDeliveryChallan(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(DeliveryChallan::class, 'production_order_operation_id')->latestOfMany();
    }

    public function isRunning(): bool
    {
        return $this->status === self::STATUS_RUNNING;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isOutsourced(): bool
    {
        return (bool) ($this->is_external || !empty($this->vendor_id) || $this->routingOperation?->isOutsourced() || $this->purchase_order_id !== null);
    }

    public function isWipJobWork(): bool
    {
        return $this->isOutsourced() && ($this->subcontract_input_type === 'previous_operation_wip' || $this->routingOperation?->subcontract_input_type === 'previous_operation_wip');
    }

    public function getTargetProducedQtyAttribute(): float
    {
        if (array_key_exists('target_produced_qty', $this->attributes) && (float) $this->attributes['target_produced_qty'] > 0) {
            return (float) $this->attributes['target_produced_qty'];
        }

        $order = $this->order;
        if (!$order) {
            return 1.0;
        }

        if (!empty($this->source_product_id) && (int) $this->source_product_id !== (int) $order->product_id) {
            $bomItem = \App\Domains\Production\Models\ProductionBomItem::where('tenant_id', $this->tenant_id)
                ->where('bom_id', $order->bom_id)
                ->where('material_id', $this->source_product_id)
                ->first();
            if ($bomItem && (float) $bomItem->quantity > 0) {
                $baseQty = $order->bom?->base_quantity > 0 ? (float) $order->bom->base_quantity : 1.0;
                return (float) $order->quantity_ordered * ((float) $bomItem->quantity / $baseQty);
            }
        }

        return (float) ($order->quantity_ordered ?? 1.0);
    }

    /**
     * Compute current material balance for an issued raw material or component on this operation/order.
     * Enforces strict material-balance protection:
     *   Remaining = max(0, Issued - Consumed - AlreadyDisposed)
     */
    public function getMaterialBalance(int $productId): array
    {
        $order = $this->order;
        $res = $order ? $order->reservations()->where('product_id', $productId)->first() : null;
        $issuedQty = (float) ($res?->quantity_issued ?? 0.0);
        $plannedQty = (float) ($res?->quantity_planned ?? 0.0);

        // 1. Calculate consumed quantity from BOM and operation progress
        $consumedQty = 0.0;
        if ((float) ($this->quantity_consumed ?? 0) > 0) {
            $consumedQty = (float) $this->quantity_consumed;
        } elseif ($order && $order->bom_id) {
            $bomItem = ProductionBomItem::where('tenant_id', $this->tenant_id)
                ->where('bom_id', $order->bom_id)
                ->where('material_id', $productId)
                ->first();
            if ($bomItem && (float) $bomItem->quantity > 0) {
                $baseQty = max(1.0, (float) ($order->bom?->base_quantity ?? 1.0));
                $ratio = (float) $bomItem->quantity / $baseQty;
                $consumedQty = (float) $this->quantity_produced * $ratio;
            }
        }
        if ($consumedQty == 0 && (float) $this->quantity_produced > 0 && (float) ($order->quantity_ordered ?? 0) > 0 && (int) $this->source_product_id !== $productId && (int) ($order->product_id ?? 0) !== $productId) {
            $consumedRatio = min(1.0, (float) $this->quantity_produced / (float) $order->quantity_ordered);
            $consumedQty = $issuedQty * $consumedRatio;
        }
        $consumedQty = min($issuedQty, max(0.0, $consumedQty));

        // 2. Already disposed Scrap for this product on this production order
        $scrappedQty = (float) ProductionOrderScrap::where('tenant_id', $this->tenant_id)
            ->where('production_order_id', $this->production_order_id)
            ->where('product_id', $productId)
            ->sum('quantity');

        // 3. Already saved Remnants for this product on this production order
        $remnantedQty = (float) \App\Domains\Inventory\Models\InventoryRemnant::where('tenant_id', $this->tenant_id)
            ->where('source_production_order_id', $this->production_order_id)
            ->where('product_id', $productId)
            ->sum('initial_quantity');

        $alreadyDisposedQty = $scrappedQty + $remnantedQty;
        $remainingQty = max(0.0, round($issuedQty - $consumedQty - $alreadyDisposedQty, 4));

        return [
            'has_reservation' => ($res !== null),
            'issued_qty' => $issuedQty,
            'planned_qty' => $plannedQty,
            'consumed_qty' => round($consumedQty, 4),
            'already_disposed_qty' => round($alreadyDisposedQty, 4),
            'scrapped_qty' => round($scrappedQty, 4),
            'remnanted_qty' => round($remnantedQty, 4),
            'remaining_qty' => $remainingQty,
            'reservation' => $res,
        ];
    }

    public function getScrappableMaterialsAttribute(): \Illuminate\Support\Collection
    {
        $materials = collect();
        $measService = app(\App\Domains\Inventory\Services\MaterialMeasurementService::class);
        $order = $this->order;

        $formatMaterial = function ($product, string $typeLabel) use ($measService) {
            if (!$product) {
                return null;
            }
            $product->loadMissing('uom');
            $uomCode = $product->uom?->code ?? $product->uom?->name ?? 'Pcs';
            $inferredType = $measService->inferMeasurementType($product);

            $balance = $this->getMaterialBalance($product->id);
            $issuedQty = $balance['issued_qty'];
            $consumedQty = $balance['consumed_qty'];
            $remainingQty = $balance['remaining_qty'];
            $warehouseId = $balance['reservation']?->warehouse_id;

            $stdLen = (float) ($product->length ?? 0);
            $isLinearMeter = ($inferredType === 'linear' && in_array(strtolower($uomCode), ['mtr', 'm', 'meter', 'meters']));
            $isLinearPiece = ($inferredType === 'linear' && $stdLen > 0);

            if ($issuedQty > 0) {
                if ($isLinearMeter) {
                    $issMm = round($issuedQty * 1000);
                    $conMm = round($consumedQty * 1000);
                    $remMm = round($remainingQty * 1000);
                    if ($consumedQty > 0) {
                        $balanceText = "Issued: {$issMm} mm • Consumed: {$conMm} mm • Remaining: {$remMm} mm";
                    } else {
                        $balanceText = "Issued: {$issMm} mm • Remaining: {$remMm} mm";
                    }
                    $issuedDisplay = "Issued: " . number_format($issuedQty, 2) . " {$uomCode} ({$issMm} mm)";
                } elseif ($isLinearPiece) {
                    $issMm = round($issuedQty * $stdLen);
                    $conMm = round($consumedQty * $stdLen);
                    $remMm = round($remainingQty * $stdLen);
                    if ($consumedQty > 0) {
                        $balanceText = "Issued: {$issMm} mm • Consumed: {$conMm} mm • Remaining: {$remMm} mm";
                    } else {
                        $balanceText = "Issued: {$issMm} mm • Remaining: {$remMm} mm";
                    }
                    $issuedDisplay = "Issued: " . number_format($issuedQty, 2) . " {$uomCode} ({$issMm} mm)";
                } else {
                    if ($consumedQty > 0) {
                        $balanceText = "Issued: " . number_format($issuedQty, 2) . " {$uomCode} • Consumed: " . number_format($consumedQty, 2) . " {$uomCode} • Remaining: " . number_format($remainingQty, 2) . " {$uomCode}";
                    } else {
                        $balanceText = "Issued: " . number_format($issuedQty, 2) . " {$uomCode} • Remaining: " . number_format($remainingQty, 2) . " {$uomCode}";
                    }
                    $issuedDisplay = "Issued: " . number_format($issuedQty, 2) . " {$uomCode}";
                }
            } elseif ($balance['planned_qty'] > 0) {
                $balanceText = "Planned: " . number_format($balance['planned_qty'], 2) . " {$uomCode}";
                $issuedDisplay = $balanceText;
            } else {
                $balanceText = "UOM: {$uomCode}";
                $issuedDisplay = $balanceText;
            }

            return [
                'id' => $product->id,
                'name' => $product->name . ($product->sku ? " ({$product->sku})" : ''),
                'type_label' => $typeLabel,
                'uom_code' => $uomCode,
                'measurement_type' => $inferredType,
                'issued_qty' => $issuedQty,
                'consumed_qty' => $consumedQty,
                'remaining_qty' => $remainingQty,
                'balance_text' => $balanceText,
                'issued_display' => $issuedDisplay,
                'standard_length' => $stdLen,
                'standard_width' => (float) ($product->width ?? 0),
                'standard_thickness' => (float) ($product->thickness ?? $product->height ?? 0),
                'warehouse_id' => $warehouseId,
            ];
        };

        // 1. Explicit RoutingOperationMaterial entries for this operation
        if ($this->routing_operation_id) {
            $opMaterials = RoutingOperationMaterial::where('routing_operation_id', $this->routing_operation_id)
                ->with(['material.uom'])
                ->get();
            foreach ($opMaterials as $opMat) {
                if ($opMat->material) {
                    $formatted = $formatMaterial($opMat->material, 'Operation Input Raw Material');
                    if ($formatted) {
                        $materials->push($formatted);
                    }
                }
            }
        }

        // 2. Items from the source BOM or order BOM
        $bomId = $this->source_bom_id ?: $this->order?->bom_id;
        if ($bomId) {
            $bomItems = ProductionBomItem::where('bom_id', $bomId)->with(['material.uom'])->get();
            foreach ($bomItems as $item) {
                if ($item->material) {
                    $formatted = $formatMaterial($item->material, 'BOM Component / Input');
                    if ($formatted) {
                        $materials->push($formatted);
                    }
                }
            }
        }

        // 3. Operation target product (SFG component)
        if ($this->sourceProduct) {
            $formatted = $formatMaterial($this->sourceProduct, 'Operation Output Component');
            if ($formatted) {
                $materials->push($formatted);
            }
        }

        // 4. Master Production Order Product (FG)
        if ($this->order && $this->order->product) {
            $formatted = $formatMaterial($this->order->product, 'Finished Product Assembly');
            if ($formatted) {
                $materials->push($formatted);
            }
        }

        return $materials->unique('id')->values();
    }
}
