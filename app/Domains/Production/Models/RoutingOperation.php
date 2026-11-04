<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use App\Models\Concerns\Loggable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class RoutingOperation extends BaseModel
{
    use HasFactory, SoftDeletes, Loggable;

    protected $table = 'production_routing_operations';

    // Operation type constants
    public const TYPE_MANUFACTURING = 'manufacturing';
    public const TYPE_INSPECTION    = 'inspection';
    public const TYPE_OUTSOURCING   = 'outsourcing';
    public const TYPE_TRANSPORT     = 'transport';
    public const TYPE_MAINTENANCE   = 'maintenance';

    public const TYPES = [
        self::TYPE_MANUFACTURING,
        self::TYPE_INSPECTION,
        self::TYPE_OUTSOURCING,
        self::TYPE_TRANSPORT,
        self::TYPE_MAINTENANCE,
    ];

    protected $fillable = [
        'tenant_id',
        'routing_id',
        'sequence',
        'operation_number',
        'previous_operation_id',
        'name',
        'description',
        'operation_type',
        'work_center_id',
        'section_id',
        'machine_id',
        'section_manual_production_id',
        'is_alternative',
        'replaces_operation_id',
        'priority',
        'cost_per_unit',
        'resource_snapshot',
        'setup_time_minutes',
        'processing_time_minutes',
        'wait_time_minutes',
        'expected_yield_percentage', // A2: production loss tracking
        'labor_cost_rate',
        'machine_cost_rate',
        'instructions',              // A1: inline documentation
        'quality_required',
        'is_external',               // Subcontracting ready
        'vendor_id',                 // Subcontractor Vendor FK
        'subcontract_lead_time_days',
        'subcontract_cost_per_unit',
        'subcontract_service_product_id',
        'material_supply_type',
        'subcontract_input_type',
        'dispatch_buffer_days',
        'return_buffer_days',
        'parallel_group',
        'is_parallel',
        'parallel_type',
        'queue_threshold_enabled',
        'overlap_enabled',
        'transfer_batch_quantity',
        'transfer_lag_minutes',
    ];

    protected $casts = [
        'sequence'                  => 'integer',
        'section_id'                => 'integer',
        'section_manual_production_id' => 'integer',
        'is_alternative'            => 'boolean',
        'replaces_operation_id'     => 'integer',
        'priority'                  => 'integer',
        'cost_per_unit'             => 'float',
        'resource_snapshot'         => 'array',
        'setup_time_minutes'        => 'float',
        'processing_time_minutes'   => 'float',
        'wait_time_minutes'         => 'float',
        'expected_yield_percentage' => 'float',
        'labor_cost_rate'           => 'float',
        'machine_cost_rate'         => 'float',
        'subcontract_lead_time_days'=> 'integer',
        'subcontract_cost_per_unit' => 'float',
        'dispatch_buffer_days'      => 'integer',
        'return_buffer_days'        => 'integer',
        'quality_required'          => 'boolean',
        'is_external'               => 'boolean',
        'queue_threshold_enabled'   => 'boolean',
        'overlap_enabled'           => 'boolean',
        'transfer_batch_quantity'   => 'float',
        'transfer_lag_minutes'      => 'integer',
    ];

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

    // ─── Relationships ────────────────────────────────────────────────────────

    public function routing(): BelongsTo
    {
        return $this->belongsTo(Routing::class, 'routing_id');
    }

    public function previousOperation(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_operation_id');
    }

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class, 'work_center_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(WorkCenterSection::class, 'section_id');
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class, 'machine_id');
    }

    public function manualProduction(): BelongsTo
    {
        return $this->belongsTo(SectionManualProduction::class, 'section_manual_production_id');
    }

    public function primaryOperation(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaces_operation_id');
    }

    public function alternativeOperations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(self::class, 'replaces_operation_id')->orderBy('priority');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Inventory\Models\Vendor::class, 'vendor_id');
    }

    public function subcontractServiceProduct(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Inventory\Models\Product::class, 'subcontract_service_product_id');
    }

    public function materials(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(RoutingOperationMaterial::class, 'routing_operation_id')->orderBy('sequence');
    }

    public function bomItems(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ProductionBomItem::class, 'routing_operation_id');
    }

    public function alternateMachines(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(RoutingOperationAlternateMachine::class, 'routing_operation_id')->orderBy('priority');
    }

    // ─── Operation Type Helpers ───────────────────────────────────────────────

    public function isManufacturing(): bool
    {
        return $this->operation_type === self::TYPE_MANUFACTURING;
    }

    public function isInspection(): bool
    {
        return $this->operation_type === self::TYPE_INSPECTION;
    }

    public function isOutsourced(): bool
    {
        return $this->operation_type === self::TYPE_OUTSOURCING || $this->is_external;
    }

    public function isWipJobWork(): bool
    {
        return $this->isOutsourced() && $this->subcontract_input_type === 'previous_operation_wip';
    }

    // ─── Computed Helpers ─────────────────────────────────────────────────────

    /**
     * Total operation cycle time in minutes.
     */
    public function totalCycleTimeMinutes(): float
    {
        return $this->setup_time_minutes
            + $this->processing_time_minutes
            + $this->wait_time_minutes;
    }

    /**
     * A2: Calculate effective input quantity needed to achieve target output.
     * Formula: input_qty = output_qty / (expected_yield_percentage / 100)
     */
    public function effectiveInputQty(float $targetOutputQty): float
    {
        $yield = $this->expected_yield_percentage > 0 ? $this->expected_yield_percentage : 100.0;
        return $targetOutputQty / ($yield / 100);
    }

    /**
     * Estimated operation cost per unit (1-unit baseline).
     * Reconciles setup time, cycle run time, machine power/maintenance, and operator labor.
     */
    public function estimatedCostPerUnit(): float
    {
        if ($this->cost_per_unit !== null && (float) $this->cost_per_unit > 0.0) {
            return (float) $this->cost_per_unit;
        }

        $yieldFactor = $this->expected_yield_percentage > 0 ? (100 / $this->expected_yield_percentage) : 1.0;

        if ($this->is_external) {
            return round(((float) $this->subcontract_cost_per_unit) * $yieldFactor, 4);
        }

        $activeMinutes = (float) $this->setup_time_minutes + (float) $this->processing_time_minutes;
        $laborRate = 0.0;
        $machineRate = 0.0;
        $snap = is_array($this->resource_snapshot) ? $this->resource_snapshot : null;

        if ($this->section_manual_production_id) {
            $machineRate = 0.0;
            if ($snap && isset($snap['labor_cost_per_hour'])) {
                $laborRate = ((float) $snap['labor_cost_per_hour']) / 60.0;
            } elseif ($this->section) {
                $sectionCosts = app(\App\Domains\Production\Services\SectionCalculationService::class)->getSectionResourceCosts($this->section);
                foreach ($sectionCosts['manual_batches'] as $mb) {
                    if ((int) $mb['id'] === (int) $this->section_manual_production_id) {
                        $laborRate = ((float) ($mb['labor_cost_per_hour'] ?? 0.0)) / 60.0;
                        break;
                    }
                }
            }
            if ($laborRate <= 0.0) {
                $laborRate = (float) $this->labor_cost_rate;
            }
        } elseif ($this->machine_id) {
            if ($snap && (isset($snap['operating_cost_per_hour']) || isset($snap['machine_cost_per_hour']))) {
                $machineRate = ((float) ($snap['operating_cost_per_hour'] ?? $snap['machine_cost_per_hour'])) / 60.0;
            } elseif ($this->machine) {
                $machineRate = ((float) $this->machine->operating_cost_per_hour) / 60.0;
            }
            if ($machineRate <= 0.0) {
                $machineRate = (float) $this->machine_cost_rate;
            }

            if ($snap && isset($snap['operator_labor_cost_per_machine_per_hour'])) {
                $laborRate = ((float) $snap['operator_labor_cost_per_machine_per_hour']) / 60.0;
            } elseif ($this->section) {
                $sectionCosts = app(\App\Domains\Production\Services\SectionCalculationService::class)->getSectionResourceCosts($this->section);
                $laborRate = ((float) ($sectionCosts['operator_labor_cost_per_machine_per_hour'] ?? 0.0)) / 60.0;
            }
            if ($laborRate <= 0.0) {
                $laborRate = (float) $this->labor_cost_rate;
            }
        } elseif ($this->work_center_id) {
            $wc = $this->workCenter;
            $wcLaborRate = $wc ? ((float) $wc->cost_per_hour / 60.0) : 0.0;
            $laborRate = $wcLaborRate > 0.0 ? $wcLaborRate : (float) $this->labor_cost_rate;
        }

        return round(($activeMinutes * ($laborRate + $machineRate)) * $yieldFactor, 4);
    }
}
