<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use App\Domains\HRMS\Models\Asset;
use App\Models\Concerns\Loggable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Machine extends BaseModel
{
    use HasFactory, SoftDeletes, Loggable;

    protected $table = 'production_machines';

    // Q3: Defined status constants — no magic strings in application code
    public const STATUS_ACTIVE            = 'active';
    public const STATUS_INACTIVE          = 'inactive';
    public const STATUS_UNDER_MAINTENANCE = 'under_maintenance';
    public const STATUS_DECOMMISSIONED    = 'decommissioned';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_INACTIVE,
        self::STATUS_UNDER_MAINTENANCE,
        self::STATUS_DECOMMISSIONED,
    ];

    public const ENERGY_ELECTRICITY = 'electricity';
    public const ENERGY_PETROL      = 'petrol';
    public const ENERGY_DIESEL      = 'diesel';
    public const ENERGY_OTHERS      = 'others';

    public const ENERGY_SOURCES = [
        self::ENERGY_ELECTRICITY,
        self::ENERGY_PETROL,
        self::ENERGY_DIESEL,
        self::ENERGY_OTHERS,
    ];

    protected $fillable = [
        'tenant_id',
        'section_id',
        'asset_id',
        'name',
        'code',
        'machine_type',
        'manufacturer',
        'model_number',
        'capacity',
        'energy_source',
        'consumption_per_hour',
        'energy_rate_per_unit',
        'operating_cost_per_hour',
        'status',
        'installation_date',
        'maintenance_status', // A7: future maintenance module integration point
        'last_maintenance_date',
        'next_maintenance_due_date',
        'current_state',
        'current_state_reason',
    ];

    protected $casts = [
        'capacity'                  => 'float',
        'consumption_per_hour'      => 'float',
        'energy_rate_per_unit'      => 'float',
        'operating_cost_per_hour'   => 'float',
        'installation_date'         => 'date',
        'last_maintenance_date'     => 'date',
        'next_maintenance_due_date' => 'date',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function section(): BelongsTo
    {
        return $this->belongsTo(WorkCenterSection::class, 'section_id');
    }

    /**
     * Operational Work Center derived through the assigned Section.
     * Returns null when the machine has no Section assignment.
     */
    public function workCenter(): \Illuminate\Database\Eloquent\Relations\HasOneThrough
    {
        return $this->hasOneThrough(
            WorkCenter::class,
            WorkCenterSection::class,
            'id',             // Foreign key on WorkCenterSection table
            'id',             // Foreign key on WorkCenter table
            'section_id',     // Local key on Machine table
            'work_center_id'  // Local key on WorkCenterSection table
        );
    }

    /**
     * The Fixed Asset accounting record this machine was capitalized from
     * (nullable — most machines are commissioned before/without a linked
     * asset, or the link is added retroactively via link-asset).
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }

    public function isLinkedToAsset(): bool
    {
        return $this->asset_id !== null;
    }

    public function operations(): HasMany
    {
        return $this->hasMany(RoutingOperation::class, 'machine_id');
    }

    public function pmSchedules(): HasMany
    {
        return $this->hasMany(ProductionPmSchedule::class, 'machine_id');
    }

    public function maintenanceWorkOrders(): HasMany
    {
        return $this->hasMany(ProductionMaintenanceWorkOrder::class, 'machine_id');
    }

    public function downtimes(): HasMany
    {
        return $this->hasMany(ProductionMachineDowntime::class, 'machine_id');
    }

    // ─── Status Helpers ───────────────────────────────────────────────────────

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isInactive(): bool
    {
        return $this->status === self::STATUS_INACTIVE;
    }

    public function isUnderMaintenance(): bool
    {
        return $this->status === self::STATUS_UNDER_MAINTENANCE;
    }

    public function isDecommissioned(): bool
    {
        return $this->status === self::STATUS_DECOMMISSIONED;
    }

    public function isAvailable(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive(Builder $query): void
    {
        $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeForWorkCenter(Builder $query, int $workCenterId): void
    {
        $query->whereHas('section', fn (Builder $q) => $q->where('work_center_id', $workCenterId));
    }

    public function scopeForSection(Builder $query, int $sectionId): void
    {
        $query->where('section_id', $sectionId);
    }

    public function scopeAvailableForProduction(Builder $query): void
    {
        $query->whereIn('status', [self::STATUS_ACTIVE]);
    }

    // ─── Accessors ────────────────────────────────────────────────────────────

    public function getCapacityPerHourAttribute(): float
    {
        return (float) ($this->capacity ?? 0);
    }

    public function getPowerConsumptionKwAttribute(): float
    {
        return (float) ($this->consumption_per_hour ?? 0);
    }
}
