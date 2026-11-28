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
        // RT-IMPL-14: saved figures + source snapshot
        'labor_cost_per_unit',
        'machine_cost_per_unit',
        'setup_cost_per_run',
        'costs_calculated_at',
        'section_calculation_id',
        'section_snapshot_number',
        'work_center_calculation_id',
        'resource_snapshot',
        'setup_time_minutes',          // RT-IMPL-13: setup per operation (once per run)
        'setup_time_per_piece_minutes', // RT-IMPL-13: setup per piece
        'processing_time_minutes',
        'wait_time_minutes',
        'expected_yield_percentage', // A2: production loss tracking
        'labor_cost_rate',
        'machine_cost_rate',
        'instructions',              // A1: inline documentation
        'quality_required',
        'is_external',               // Subcontracting ready
        'vendor_id',                 // Subcontractor Vendor FK
        // SF-1: the outsourced operation's three times, in hours (calendar time)
        'vendor_dispatch_hours',     // travel time from our work floor to the vendor
        'vendor_processing_hours',   // time the vendor takes to do the work
        'vendor_receipt_hours',      // travel time from the vendor back to our work floor
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
        'labor_cost_per_unit'       => 'float',
        'machine_cost_per_unit'     => 'float',
        'setup_cost_per_run'        => 'float',
        'costs_calculated_at'       => 'datetime',
        'section_calculation_id'    => 'integer',
        'section_snapshot_number'   => 'integer',
        'work_center_calculation_id'=> 'integer',
        'resource_snapshot'         => 'array',
        'setup_time_minutes'        => 'float',
        'setup_time_per_piece_minutes' => 'float',
        'processing_time_minutes'   => 'float',
        'wait_time_minutes'         => 'float',
        'expected_yield_percentage' => 'float',
        'labor_cost_rate'           => 'float',
        'machine_cost_rate'         => 'float',
        'vendor_dispatch_hours'     => 'float',
        'vendor_processing_hours'   => 'float',
        'vendor_receipt_hours'      => 'float',
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

    /** QR-15: Quality Plans this operation runs when its QC gate (quality_required) is on. */
    public function qualityPlanLinks(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(RoutingOperationQualityPlan::class, 'routing_operation_id')->orderBy('sort_order')->orderBy('id');
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

    /** SF-1: total vendor time in hours (dispatch + processing + receipt) */
    public function vendorTotalHours(): float
    {
        return (float) ($this->vendor_dispatch_hours ?? 0) + (float) ($this->vendor_processing_hours ?? 0) + (float) ($this->vendor_receipt_hours ?? 0);
    }

    // ─── Computed Helpers ─────────────────────────────────────────────────────

    /**
     * Cycle time per piece in minutes (RT-IMPL-13): setup per piece + run time.
     * Setup per operation (once per run) and wait time are separate.
     */
    public function cycleTimePerPieceMinutes(): float
    {
        return (float) ($this->setup_time_per_piece_minutes ?? 0) + (float) $this->processing_time_minutes;
    }

    /**
     * Setup cost per run (RT-IMPL-13): setup per operation × (labor + machine rate per minute) — no yield.
     */
    public function setupCostPerRun(): float
    {
        if ($this->is_external) {
            return 0.0;
        }
        if ($this->setup_cost_per_run !== null) {
            return round((float) $this->setup_cost_per_run, 4); // RT-IMPL-14: saved value
        }
        return round((float) $this->setup_time_minutes * ((float) $this->labor_cost_rate + (float) $this->machine_cost_rate), 4);
    }

    /**
     * Total operation time in minutes for one piece incl. setup per run and wait (scheduling view).
     */
    public function totalCycleTimeMinutes(): float
    {
        return $this->setup_time_minutes
            + (float) ($this->setup_time_per_piece_minutes ?? 0)
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
     * Operation cost per unit — RT-IMPL-14: the SAVED value (calculated once in the form / API and checked by the
     * backend). No live lookup; an operation saved before costs were stored returns 0 (see costsNotCalculated()).
     */
    public function estimatedCostPerUnit(): float
    {
        return round((float) ($this->cost_per_unit ?? 0), 4);
    }

    /** True for operations saved before figures were stored ("Costs not calculated — open and save the routing"). */
    public function costsNotCalculated(): bool
    {
        return $this->cost_per_unit === null;
    }
}
