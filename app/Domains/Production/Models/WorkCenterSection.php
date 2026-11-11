<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use App\Models\Concerns\Loggable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkCenterSection extends BaseModel
{
    use HasFactory, SoftDeletes, Loggable;

    protected $table = 'production_work_center_sections';

    public const STATUS_ACTIVE   = 'active';
    public const STATUS_INACTIVE = 'inactive';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_INACTIVE,
    ];

    protected $fillable = [
        'tenant_id',
        'work_center_id',
        'name',
        'code',
        'assigned_shifts',
        'description',
        'status',
    ];

    protected $casts = [
        'assigned_shifts' => 'array',
    ];

    public function supportsManualProduction(): bool
    {
        return $this->manualProductions()->where('active', true)->exists();
    }

    public function getCalculationDetails(): array
    {
        return [
            'capacity' => [
                'capacity_per_hour' => 0.0,
                'total_capacity'    => 0.0,
                'capacity_unit'     => __('production.units_per_hour'),
                'machine_capacity'  => 0.0,
                'manual_capacity'   => 0.0,
                'shift_capacity'    => 0.0,
                'shifts'            => [],
                'machines'          => [],
            ],
            'labor_cost' => [
                'labor_cost_per_hour'        => 0.0,
                'total_scheduled_labor_cost' => 0.0,
                'unique_operating_hours'     => 0.0,
                'has_missing_rates'          => false,
                'has_overlapping_shifts'     => false,
                'shifts'                     => [],
                'allocations'                => [],
                'windows'                    => [],
                'gaps'                       => [],
            ],
        ];
    }

    public function hasOverlappingShifts(): bool
    {
        $details = $this->getCalculationDetails();
        return (bool) ($details['labor_cost']['has_overlapping_shifts'] ?? false);
    }

    /**
     * Get the array of assigned shift IDs from assigned_shifts JSON.
     *
     * @return array<int>
     */
    public function getAssignedShiftIds(): array
    {
        $assigned = $this->assigned_shifts;
        if (empty($assigned)) {
            return [];
        }

        if (is_string($assigned)) {
            $decoded = json_decode($assigned, true);
            $assigned = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($assigned)) {
            return [];
        }

        $ids = [];
        foreach ($assigned as $item) {
            if (is_numeric($item)) {
                $ids[] = (int) $item;
            } elseif (is_array($item)) {
                if (isset($item['id']) && is_numeric($item['id'])) {
                    $ids[] = (int) $item['id'];
                } elseif (isset($item['shift_id']) && is_numeric($item['shift_id'])) {
                    $ids[] = (int) $item['shift_id'];
                }
            }
        }

        return array_values(array_unique(array_filter($ids)));
    }

    /**
     * Accessor for resolved ProductionShift models assigned to this Section.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, ProductionShift>
     */
    public function getShiftsAttribute(): \Illuminate\Database\Eloquent\Collection
    {
        if ($this->relationLoaded('shifts')) {
            return $this->getRelation('shifts');
        }

        $ids = $this->getAssignedShiftIds();
        $shifts = empty($ids)
            ? new \Illuminate\Database\Eloquent\Collection()
            : ProductionShift::whereIn('id', $ids)->get();

        $this->setRelation('shifts', $shifts);

        return $shifts;
    }

    /**
     * Query builder for shifts assigned to this Section.
     */
    public function shifts(): \Illuminate\Database\Eloquent\Builder
    {
        $ids = $this->getAssignedShiftIds();
        return ProductionShift::query()->whereIn('id', !empty($ids) ? $ids : [0]);
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class, 'work_center_id');
    }

    public function machines(): HasMany
    {
        return $this->hasMany(Machine::class, 'section_id');
    }

    public function manualProductions(): HasMany
    {
        return $this->hasMany(SectionManualProduction::class, 'section_id');
    }

    public function routingOperations(): HasMany
    {
        return $this->hasMany(RoutingOperation::class, 'section_id');
    }

    public function activeManualProductions(): HasMany
    {
        return $this->manualProductions()->where('production_section_manual_productions.active', true);
    }

    public function calculations(): HasMany
    {
        return $this->hasMany(WorkCenterSectionCalculation::class, 'section_id');
    }

    public function latestCalculation(): HasOne
    {
        return $this->hasOne(WorkCenterSectionCalculation::class, 'section_id')->latestOfMany();
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(WorkCenterActivityLog::class, 'section_id');
    }

    public function externalWorkerAllocations(): HasMany
    {
        return $this->hasMany(ExternalWorkerAllocation::class, 'section_id');
    }

    public function activeExternalWorkerAllocations(): HasMany
    {
        return $this->externalWorkerAllocations()->where('production_external_worker_allocations.active', true);
    }

    public function employeeAssignments(): HasMany
    {
        return $this->hasMany(WorkCenterEmployeeAssignment::class, 'section_id');
    }

    public function activeEmployeeAssignments(): HasMany
    {
        return $this->employeeAssignments()->where('production_work_center_employees.active', true);
    }

    /**
     * Determine if this Section has any active internal employee assignments or active external worker allocations.
     */
    public function hasActiveWorkerAssignments(): bool
    {
        $hasInternal = $this->relationLoaded('employeeAssignments')
            ? $this->employeeAssignments->where('active', true)->isNotEmpty()
            : $this->activeEmployeeAssignments()->exists();

        if ($hasInternal) {
            return true;
        }

        return $this->relationLoaded('externalWorkerAllocations')
            ? $this->externalWorkerAllocations->where('active', true)->isNotEmpty()
            : $this->activeExternalWorkerAllocations()->exists();
    }

    /**
     * Get counts of active internal and external workers assigned to this section.
     */
    public function getActiveWorkerCounts(): array
    {
        $internalCount = $this->relationLoaded('employeeAssignments')
            ? $this->employeeAssignments->where('active', true)->count()
            : $this->activeEmployeeAssignments()->count();

        $externalCount = $this->relationLoaded('externalWorkerAllocations')
            ? (int) $this->externalWorkerAllocations->where('active', true)->sum('number_of_external_workers')
            : (int) $this->activeExternalWorkerAllocations()->sum('number_of_external_workers');

        return [
            'internal' => $internalCount,
            'external' => $externalCount,
            'total'    => $internalCount + $externalCount,
        ];
    }


    /**
     * Safe accessor for internal worker allocations (table was dropped).
     *
     * @return \Illuminate\Support\Collection
     */
    public function getInternalWorkerAllocationsAttribute(): \Illuminate\Support\Collection
    {
        return collect();
    }

    /**
     * Safe accessor for active internal worker allocations (table was dropped).
     *
     * @return \Illuminate\Support\Collection
     */
    public function getActiveInternalWorkerAllocationsAttribute(): \Illuminate\Support\Collection
    {
        return collect();
    }

    public function getTotalExternalWorkers(?int $shiftId = null): int
    {
        $query = $this->activeExternalWorkerAllocations();
        if ($shiftId !== null) {
            $query->where('shift_id', $shiftId);
        }
        return (int) $query->sum('number_of_external_workers');
    }

    /**
     * Check if a specific Shift ID is assigned to this Section in assigned_shifts.
     */
    public function hasShift(int $shiftId): bool
    {
        return in_array($shiftId, $this->getAssignedShiftIds(), true);
    }

    /**
     * Check if this section has been linked to manufacturing or routing history.
     */
    public function hasProductionHistory(): bool
    {
        if (\Illuminate\Support\Facades\Schema::hasColumn('production_routing_operations', 'section_id')) {
            if (\Illuminate\Support\Facades\DB::table('production_routing_operations')->where('section_id', $this->id)->exists()) {
                return true;
            }
        }

        if (\Illuminate\Support\Facades\Schema::hasColumn('production_orders', 'section_id')) {
            if (\Illuminate\Support\Facades\DB::table('production_orders')->where('section_id', $this->id)->exists()) {
                return true;
            }
        }

        if (\Illuminate\Support\Facades\Schema::hasColumn('production_schedules', 'section_id')) {
            if (\Illuminate\Support\Facades\DB::table('production_schedules')->where('section_id', $this->id)->exists()) {
                return true;
            }
        }

        return false;
    }

    // ─── Scopes & Helpers ─────────────────────────────────────────────────────

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * WC-IMPL-09 (WC-24): one place for the "labor cost with 0 output" warnings.
     * Triggers on ACTIVE machines only (a section whose only machine is under maintenance produces 0);
     * the reason explains what is missing and names the non-active machines by status.
     *
     * @param float|null $dailyLaborCost labor cost per day in base currency (from the snapshot); null = unknown
     * @return array{zero_output: bool, partial_unutilized: bool, active_machine_count: int, machine_count: int,
     *               inactive_machine_statuses: array<string,int>, active_batch_count: int, total_personnel: int,
     *               active_employee_count: int, active_external_count: int,
     *               unassigned_internal: int, unassigned_external: int, unassigned_total: int, reason: string}
     */
    public function outputLaborStatus(?float $dailyLaborCost = null): array
    {
        $machines = $this->machines ?? collect();
        $activeMachines = $machines->where('status', Machine::STATUS_ACTIVE);
        $inactiveStatuses = $machines->reject(fn($m) => $m->status === Machine::STATUS_ACTIVE)
            ->countBy(fn($m) => (string) ($m->status ?: 'unknown'))
            ->all();

        $activeBatches = ($this->manualProductions ?? collect())->where('active', true);
        $activeBatchIds = $activeBatches->pluck('id');
        $batchEmployeeIds = $activeBatches->flatMap(fn($b) => $b->employees ? $b->employees->pluck('id') : collect())->unique();

        $activeEmployees = ($this->employeeAssignments ?? collect())->where('active', true);
        $activeAllocations = ($this->externalWorkerAllocations ?? collect())->where('active', true);

        $activeEmployeeCount = $activeEmployees->count();
        $activeExternalCount = (int) $activeAllocations->sum('number_of_external_workers');
        $totalPersonnel = $activeEmployeeCount + $activeExternalCount;

        $unassignedInternal = $activeEmployees->reject(fn($a) => $batchEmployeeIds->contains($a->id))->count();
        $unassignedExternal = (int) $activeAllocations
            ->filter(fn($al) => empty($al->manual_production_id) || !$activeBatchIds->contains($al->manual_production_id))
            ->sum('number_of_external_workers');
        $unassignedTotal = $unassignedInternal + $unassignedExternal;

        $noActiveMachine = $activeMachines->isEmpty();
        $zeroOutput = $noActiveMachine && $activeBatches->isEmpty() && $totalPersonnel > 0;
        $partialUnutilized = $noActiveMachine && $activeBatches->isNotEmpty() && $unassignedTotal > 0;

        // Reason (rule 2): what is missing
        if ($machines->isEmpty()) {
            $machineReason = 'No machines assigned';
        } else {
            $parts = [];
            foreach ($inactiveStatuses as $status => $n) {
                $parts[] = $n . ' ' . str_replace('_', ' ', $status);
            }
            $machineReason = $machines->count() . ' ' . \Illuminate\Support\Str::plural('machine', $machines->count())
                . ' assigned but none is active (' . implode(', ', $parts) . ')';
        }

        $reason = '';
        if ($zeroOutput) {
            $costText = $dailyLaborCost !== null ? 'labor cost ' . format_currency($dailyLaborCost) . '/day is' : 'labor cost is';
            $reason = $machineReason . ' and no active manual batches. ' . $totalPersonnel . ' ' . \Illuminate\Support\Str::plural('worker', $totalPersonnel)
                . ' ' . ($totalPersonnel === 1 ? 'is' : 'are') . ' actively assigned — ' . $costText . ' incurred with 0 output.';
        } elseif ($partialUnutilized) {
            $who = [];
            if ($unassignedInternal > 0) $who[] = $unassignedInternal . ' ' . \Illuminate\Support\Str::plural('employee', $unassignedInternal);
            if ($unassignedExternal > 0) $who[] = $unassignedExternal . ' external ' . \Illuminate\Support\Str::plural('worker', $unassignedExternal);
            $reason = $machineReason . '. ' . implode(' and ', $who) . ' ' . ($unassignedTotal === 1 ? 'is' : 'are')
                . ' not assigned to any active manual batch — their labor cost is incurred with 0 output.';
        }

        return [
            'zero_output'               => $zeroOutput,
            'partial_unutilized'        => $partialUnutilized,
            'active_machine_count'      => $activeMachines->count(),
            'machine_count'             => $machines->count(),
            'inactive_machine_statuses' => $inactiveStatuses,
            'active_batch_count'        => $activeBatches->count(),
            'total_personnel'           => $totalPersonnel,
            'active_employee_count'     => $activeEmployeeCount,
            'active_external_count'     => $activeExternalCount,
            'unassigned_internal'       => $unassignedInternal,
            'unassigned_external'       => $unassignedExternal,
            'unassigned_total'          => $unassignedTotal,
            'reason'                    => $reason,
        ];
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeForWorkCenter(Builder $query, int $workCenterId): void
    {
        $query->where('work_center_id', $workCenterId);
    }
}
