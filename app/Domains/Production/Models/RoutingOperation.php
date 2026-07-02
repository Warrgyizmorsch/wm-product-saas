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
        'name',
        'description',
        'operation_type',
        'work_center_id',
        'machine_id',
        'setup_time_minutes',
        'processing_time_minutes',
        'wait_time_minutes',
        'expected_yield_percentage', // A2: production loss tracking
        'labor_cost_rate',
        'machine_cost_rate',
        'instructions',              // A1: inline documentation
        'quality_required',
        'is_external',               // Subcontracting ready
        'vendor_id',                 // Future FK → vendors
    ];

    protected $casts = [
        'sequence'                  => 'integer',
        'setup_time_minutes'        => 'float',
        'processing_time_minutes'   => 'float',
        'wait_time_minutes'         => 'float',
        'expected_yield_percentage' => 'float',
        'labor_cost_rate'           => 'float',
        'machine_cost_rate'         => 'float',
        'quality_required'          => 'boolean',
        'is_external'               => 'boolean',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function routing(): BelongsTo
    {
        return $this->belongsTo(Routing::class, 'routing_id');
    }

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class, 'work_center_id');
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class, 'machine_id');
    }

    public function materials(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(RoutingOperationMaterial::class, 'routing_operation_id')->orderBy('sequence');
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
     * Estimated operation cost per unit.
     * Future RoutingCostService will use this for detailed costing.
     */
    public function estimatedCostPerUnit(): float
    {
        $totalMinutes = $this->setup_time_minutes + $this->processing_time_minutes;
        return ($totalMinutes * $this->labor_cost_rate)
             + ($totalMinutes * $this->machine_cost_rate);
    }
}
