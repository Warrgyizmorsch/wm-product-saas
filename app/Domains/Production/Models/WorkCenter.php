<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use App\Models\Concerns\Loggable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
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
        'overhead_cost',
        'status',
        'production_calendar_id',
    ];

    protected $casts = [
        'overhead_cost' => 'decimal:4',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function sections(): HasMany
    {
        return $this->hasMany(WorkCenterSection::class, 'work_center_id');
    }

    /**
     * Machines assigned to Sections belonging to this Work Center.
     */
    public function machines(): HasManyThrough
    {
        return $this->hasManyThrough(
            Machine::class,
            WorkCenterSection::class,
            'work_center_id', // Foreign key on WorkCenterSection table
            'section_id',     // Foreign key on Machine table
            'id',             // Local key on WorkCenter table
            'id'              // Local key on WorkCenterSection table
        );
    }

    /**
     * External worker allocations for Sections belonging to this Work Center.
     */
    public function externalWorkerAllocations(): HasManyThrough
    {
        return $this->hasManyThrough(
            ExternalWorkerAllocation::class,
            WorkCenterSection::class,
            'work_center_id', // Foreign key on WorkCenterSection table
            'section_id',     // Foreign key on ExternalWorkerAllocation table
            'id',             // Local key on WorkCenter table
            'id'              // Local key on WorkCenterSection table
        );
    }

    public function calendar(): BelongsTo
    {
        return $this->belongsTo(ProductionCalendar::class, 'production_calendar_id');
    }

    /**
     * Get the effective shifts for this Work Center derived from its Sections.
     *
     * A Work Center's effective shifts are the unique union of shifts assigned to its Sections.
     *
     * @param bool $onlyActive When true, only active sections, active section-shift assignments, and active shifts are returned.
     * @return \Illuminate\Support\Collection<int, ProductionShift>
     */
    public function getEffectiveShifts(bool $onlyActive = true): \Illuminate\Support\Collection
    {
        $sections = $this->relationLoaded('sections')
            ? $this->sections
            : $this->sections()->get();

        if ($onlyActive) {
            $sections = $sections->filter(fn($sec) => $sec->isActive());
        }

        $shifts = collect();
        foreach ($sections as $section) {
            $secShifts = $section->shifts;

            if ($onlyActive) {
                $secShifts = $secShifts->filter(function ($shift) {
                    $shiftActive = (bool) ($shift->active ?? true);
                    $pivotActive = (bool) ($shift->pivot->active ?? true);
                    return $shiftActive && $pivotActive;
                });
            }

            $shifts = $shifts->merge($secShifts);
        }

        return $shifts->unique('id')->values();
    }

    /**
     * Accessor for effective shifts collection.
     */
    public function getEffectiveShiftsAttribute(): \Illuminate\Support\Collection
    {
        return $this->getEffectiveShifts(true);
    }

    public function activeMachines(): HasManyThrough
    {
        return $this->machines()
            ->where('production_machines.status', Machine::STATUS_ACTIVE);
    }

    public function operations(): HasMany
    {
        return $this->hasMany(RoutingOperation::class, 'work_center_id');
    }

    public function employeeAssignments(): HasMany
    {
        return $this->hasMany(WorkCenterEmployeeAssignment::class, 'work_center_id')
            ->whereNull('production_work_center_employees.section_id');
    }

    public function allEmployeeAssignments(): HasMany
    {
        return $this->hasMany(WorkCenterEmployeeAssignment::class, 'work_center_id');
    }

    public function activeEmployeeAssignments(): HasMany
    {
        return $this->employeeAssignments()->where('production_work_center_employees.active', true);
    }

    public function employees(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(
            \App\Domains\HRMS\Models\Employee::class,
            'production_work_center_employees',
            'work_center_id',
            'employee_id'
        )->wherePivotNull('section_id')->withPivot('id', 'active', 'tenant_id')->withTimestamps();
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
     * Determine whether the Work Center has a valid derived efficiency.
     *
     * In the redesigned architecture, efficiency is derived from Sections,
     * employee assignments, and relevant calculation logic rather than entered manually.
     * A newly created Work Center with no relevant Section or assignment data
     * cannot have its efficiency determined.
     */
    public function hasDeterminedEfficiency(): bool
    {
        return false;
    }

    /**
     * Effective capacity considering efficiency percentage.
     * Future: used by SchedulingEngine to calculate available slots.
     */
    public function effectiveCapacityPerHour(): float
    {
        return 0.0;
    }

    public function getCalculationDetails(): array
    {
        return [
            'capacity' => [
                'capacity_per_hour'          => 0.0,
                'total_theoretical_capacity' => 0.0,
                'unique_operating_hours'     => 0.0,
                'capacity_unit'              => 'units/hr',
                'sections'                   => [],
                'windows'                    => [],
                'timeline_windows'           => [],
                'gaps'                       => [],
            ],
            'labor_cost' => [
                'labor_cost_per_hour'         => 0.0,
                'total_scheduled_labor_cost'  => 0.0,
                'unique_operating_hours'      => 0.0,
                'cost_unit'                   => 'currency/hr',
                'has_missing_rates'           => false,
                'has_overlapping_assignments' => false,
                'assignments'                 => [],
                'sections'                    => [],
                'windows'                     => [],
                'gaps'                        => [],
            ],
        ];
    }

    public function hasOverlappingAssignments(): bool
    {
        $details = $this->getCalculationDetails();
        return (bool) ($details['labor_cost']['has_overlapping_assignments'] ?? false);
    }
}
