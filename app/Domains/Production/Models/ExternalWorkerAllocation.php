<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use App\Models\Concerns\Loggable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ExternalWorkerAllocation extends BaseModel
{
    use HasFactory, SoftDeletes, Loggable;

    protected $table = 'production_external_worker_allocations';

    protected $fillable = [
        'tenant_id',
        'section_id',
        'shift_id',
        'pay_grade_id',
        'manual_production_id',
        'number_of_external_workers',
        'active',
    ];

    protected $casts = [
        'number_of_external_workers' => 'integer',
        'active'                     => 'boolean',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (self $model): void {
            // Unset obsolete/dropped columns if accidentally present on model attributes
            unset($model->attributes['section_shift_id']);

            // 1. Worker quantity validation: integer >= 1, default 1
            $quantity = (int) ($model->attributes['number_of_external_workers'] ?? $model->number_of_external_workers ?? 1);
            if ($quantity < 1) {
                throw new InvalidArgumentException('Allocated external worker count must be at least 1.');
            }
            $model->attributes['number_of_external_workers'] = $quantity;

            // 2. Validate required foreign keys
            if (!$model->tenant_id) {
                throw new InvalidArgumentException('Tenant ID is required for external worker allocation.');
            }
            if (!$model->section_id) {
                throw new InvalidArgumentException('Section ID is required for external worker allocation.');
            }
            if (!$model->shift_id) {
                throw new InvalidArgumentException('Shift ID is required for external worker allocation.');
            }
            if (!$model->pay_grade_id) {
                throw new InvalidArgumentException('Pay grade ID is required for external worker allocation.');
            }

            // 3. Section existence and tenant validation
            $section = WorkCenterSection::withoutGlobalScopes()->find($model->section_id);
            if (!$section || (int) $section->tenant_id !== (int) $model->tenant_id) {
                throw new InvalidArgumentException('Section does not exist or does not belong to the allocation tenant.');
            }

            // 4. Shift existence and tenant validation
            $shift = ProductionShift::withoutGlobalScopes()->find($model->shift_id);
            if (!$shift || (int) $shift->tenant_id !== (int) $model->tenant_id) {
                throw new InvalidArgumentException('Shift does not exist or does not belong to the allocation tenant.');
            }

            // 5. Pay Grade existence, tenant validation, and soft-delete check
            $payGrade = ExternalWorkerPayGrade::withoutGlobalScopes()->find($model->pay_grade_id);
            if (!$payGrade || (int) $payGrade->tenant_id !== (int) $model->tenant_id) {
                throw new InvalidArgumentException('Pay grade does not exist or does not belong to the allocation tenant.');
            }

            if ((!$model->exists || $model->isDirty('pay_grade_id')) && $payGrade->deleted_at !== null) {
                throw new InvalidArgumentException("Cannot allocate to soft-deleted pay grade '{$payGrade->name}'.");
            }

            // 6. Section–Shift membership: shift must be assigned to section in assigned_shifts
            if (!$section->hasShift((int) $model->shift_id)) {
                throw new InvalidArgumentException("Shift #{$model->shift_id} ('{$shift->name}') is not assigned to Section #{$model->section_id} ('{$section->name}'). Allocation requires a configured Section operating shift.");
            }

            // 7. Active allocations validation across create, update, and activation
            if ((bool) $model->active) {
                if (!$section->isActive()) {
                    throw new InvalidArgumentException("Cannot create or activate allocation because Section '{$section->name}' is inactive.");
                }

                if (!$shift->isActive()) {
                    throw new InvalidArgumentException("Cannot create or activate allocation because Shift '{$shift->name}' is inactive.");
                }

                if ($payGrade->deleted_at !== null) {
                    throw new InvalidArgumentException("Cannot create or activate allocation because Pay Grade '{$payGrade->name}' is soft-deleted.");
                }

                if (!$payGrade->isActive()) {
                    throw new InvalidArgumentException("Cannot create or activate allocation because Pay Grade '{$payGrade->name}' is inactive.");
                }
            }
        });
    }

    // ─── Relationships ────────────────────────────────────────────────────────

    public function section(): BelongsTo
    {
        return $this->belongsTo(WorkCenterSection::class, 'section_id')->withTrashed();
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(ProductionShift::class, 'shift_id');
    }

    public function payGrade(): BelongsTo
    {
        return $this->belongsTo(ExternalWorkerPayGrade::class, 'pay_grade_id')->withTrashed();
    }

    public function manualProduction(): BelongsTo
    {
        return $this->belongsTo(SectionManualProduction::class, 'manual_production_id');
    }

    /**
     * Operational Work Center derived through the Section.
     */
    public function workCenter(): HasOneThrough
    {
        return $this->hasOneThrough(
            WorkCenter::class,
            WorkCenterSection::class,
            'id',             // Local key on WorkCenterSection table
            'id',             // Local key on WorkCenter table
            'section_id',     // Foreign key on ExternalWorkerAllocation table
            'work_center_id'  // Foreign key on WorkCenterSection table
        );
    }

    // ─── Accessors ────────────────────────────────────────────────────────────

    public function getWorkCenterAttribute(): ?WorkCenter
    {
        if ($this->relationLoaded('workCenter')) {
            return $this->getRelation('workCenter');
        }

        if ($this->relationLoaded('section') && $this->section) {
            return $this->section->workCenter;
        }

        return $this->workCenter()->first();
    }

    public function setNumberOfExternalWorkersAttribute($value): void
    {
        $this->attributes['number_of_external_workers'] = (int) $value;
    }

    public function getNumberOfExternalWorkersAttribute(): int
    {
        return (int) ($this->attributes['number_of_external_workers'] ?? 1);
    }

    public function getHourlyRateAttribute(): float
    {
        return (float) ($this->payGrade?->hourly_rate ?? 0);
    }

    // ─── Scopes & Helpers ─────────────────────────────────────────────────────

    public function isActive(): bool
    {
        return (bool) $this->active;
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('active', true);
    }

    public function scopeForSection(Builder $query, int $sectionId): void
    {
        $query->where('section_id', $sectionId);
    }

    public function scopeForShift(Builder $query, int $shiftId): void
    {
        $query->where('shift_id', $shiftId);
    }

    public function scopeForSectionShift(Builder $query, int $sectionId, int $shiftId): void
    {
        $query->where('section_id', $sectionId)->where('shift_id', $shiftId);
    }

    public function scopeForPayGrade(Builder $query, int $payGradeId): void
    {
        $query->where('pay_grade_id', $payGradeId);
    }

    public function scopeForWorkCenter(Builder $query, int $workCenterId): void
    {
        $query->whereHas('section', fn(Builder $q) => $q->where('work_center_id', $workCenterId));
    }
}
