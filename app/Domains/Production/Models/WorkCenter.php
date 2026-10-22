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
        if ($this->relationLoaded('sections')) {
            $sections = $this->sections;
            if ($onlyActive) {
                $sections = $sections->filter(fn($sec) => $sec->isActive());
            }

            $shifts = collect();
            foreach ($sections as $section) {
                $secShifts = $section->relationLoaded('shifts')
                    ? $section->shifts
                    : $section->shifts()->get();

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

        $query = ProductionShift::query()
            ->join('production_section_shifts as pss', 'pss.shift_id', '=', 'production_shifts.id')
            ->join('production_work_center_sections as pwcs', 'pwcs.id', '=', 'pss.section_id')
            ->where('pwcs.work_center_id', $this->id)
            ->whereNull('pwcs.deleted_at')
            ->select('production_shifts.*')
            ->distinct();

        if ($onlyActive) {
            $query->where('pwcs.status', WorkCenterSection::STATUS_ACTIVE)
                ->where('pss.active', true)
                ->where('production_shifts.active', true);
        }

        return $query->get();
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
        )->withPivot('id', 'active', 'tenant_id')->withTimestamps();
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
        if ($this->efficiency_percentage === null) {
            return false;
        }

        $sections = $this->relationLoaded('sections')
            ? $this->sections
            : $this->sections()->get();

        if ($sections->isEmpty()) {
            return false;
        }

        $hasRelevantData = $sections->contains(function ($section) {
            $hasMachines = $section->relationLoaded('machines')
                ? $section->machines->isNotEmpty()
                : $section->machines()->exists();

            $hasShifts = $section->relationLoaded('shifts')
                ? $section->shifts->isNotEmpty()
                : $section->shifts()->exists();

            $hasAllocations = $section->relationLoaded('externalWorkerAllocations')
                ? $section->externalWorkerAllocations->isNotEmpty()
                : $section->externalWorkerAllocations()->exists();

            return $hasMachines || $hasShifts || $hasAllocations || (bool) $section->supports_manual_production;
        });

        if (!$hasRelevantData) {
            return false;
        }

        return true;
    }

    /**
     * Effective capacity considering efficiency percentage.
     * Future: used by SchedulingEngine to calculate available slots.
     */
    public function effectiveCapacityPerHour(): float
    {
        if (!$this->hasDeterminedEfficiency() || $this->capacity_per_hour === null) {
            return 0.0;
        }

        return (float) $this->capacity_per_hour * ((float) ($this->efficiency_percentage ?? 0) / 100);
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
