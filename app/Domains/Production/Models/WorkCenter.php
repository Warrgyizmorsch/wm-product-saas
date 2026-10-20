<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use App\Models\Concerns\Loggable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkCenter extends BaseModel
{
    use HasFactory, SoftDeletes, Loggable;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    protected $table = 'production_work_centers';

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'work_center_type',  // A4: future FK when master table arrives
        'description',
        'location',
        'capacity_per_hour',
        'total_theoretical_capacity',
        'labor_cost_per_hour',
        'total_scheduled_labor_cost',
        'unique_operating_hours',
        'calculation_details',
        'efficiency_percentage',
        'cost_per_hour',
        'overhead_rate',
        'status',
        'production_calendar_id',
    ];

    protected $casts = [
        'capacity_per_hour'          => 'decimal:4',
        'total_theoretical_capacity' => 'decimal:4',
        'labor_cost_per_hour'        => 'decimal:4',
        'total_scheduled_labor_cost' => 'decimal:4',
        'unique_operating_hours'     => 'decimal:4',
        'calculation_details'        => 'array',
        'efficiency_percentage'      => 'float',
        'cost_per_hour'              => 'float',
        'overhead_rate'              => 'float',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function sections(): HasMany
    {
        return $this->hasMany(WorkCenterSection::class, 'work_center_id');
    }

    public function machines(): HasMany
    {
        return $this->hasMany(Machine::class, 'work_center_id');
    }

    public function calendar(): BelongsTo
    {
        return $this->belongsTo(ProductionCalendar::class, 'production_calendar_id');
    }

    public function shifts(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(
            ProductionShift::class,
            'production_work_center_shifts',
            'work_center_id',
            'shift_id'
        )->distinct();
    }

    public function activeMachines(): HasMany
    {
        return $this->hasMany(Machine::class, 'work_center_id')
            ->where('status', 'active');
    }

    public function operations(): HasMany
    {
        return $this->hasMany(RoutingOperation::class, 'work_center_id');
    }

    // ─── Status Helpers ───────────────────────────────────────────────────────

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isInactive(): bool
    {
        return $this->status === 'inactive';
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive(Builder $query): void
    {
        $query->where('status', 'active');
    }

    public function scopeOfType(Builder $query, string $type): void
    {
        $query->where('work_center_type', $type);
    }

    // ─── Computed Helpers ─────────────────────────────────────────────────────

    /**
     * Effective capacity considering efficiency percentage.
     * Future: used by SchedulingEngine to calculate available slots.
     */
    public function effectiveCapacityPerHour(): float
    {
        return ($this->capacity_per_hour ?? 0) * ($this->efficiency_percentage / 100);
    }

    public function getCalculationDetails(): array
    {
        if (is_array($this->calculation_details) && !empty($this->calculation_details)) {
            return $this->calculation_details;
        }

        return app(\App\Domains\Production\Services\WorkCenterCalculationService::class)->calculate($this);
    }

    public function hasOverlappingAssignments(): bool
    {
        $details = $this->getCalculationDetails();
        return (bool) ($details['labor_cost']['has_overlapping_assignments'] ?? false);
    }
}
