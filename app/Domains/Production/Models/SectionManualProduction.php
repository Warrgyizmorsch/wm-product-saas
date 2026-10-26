<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use App\Models\Concerns\Loggable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SectionManualProduction extends BaseModel
{
    use HasFactory, SoftDeletes, Loggable;

    protected $table = 'production_section_manual_productions';

    protected $fillable = [
        'tenant_id',
        'section_id',
        'shift_id',
        'batch_name',
        'capacity_per_hour',
        'active',
    ];

    protected $casts = [
        'capacity_per_hour' => 'float',
        'active'            => 'boolean',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function section(): BelongsTo
    {
        return $this->belongsTo(WorkCenterSection::class, 'section_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(ProductionShift::class, 'shift_id');
    }

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(
            WorkCenterEmployeeAssignment::class,
            'production_section_manual_production_employees',
            'manual_production_id',
            'work_center_employee_id'
        )->withTimestamps();
    }

    public function externalAllocations(): HasMany
    {
        return $this->hasMany(ExternalWorkerAllocation::class, 'manual_production_id');
    }

    public function externalWorkerAllocations(): HasMany
    {
        return $this->hasMany(ExternalWorkerAllocation::class, 'manual_production_id');
    }

    // ─── Accessors ────────────────────────────────────────────────────────────

    public function getTargetProductionPerHourAttribute(): float
    {
        return (float) ($this->capacity_per_hour ?? 0);
    }

    public function getInternalEmployeesAttribute()
    {
        return $this->employees;
    }

    public function getExternalWorkersAttribute()
    {
        return $this->externalAllocations;
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }
}

