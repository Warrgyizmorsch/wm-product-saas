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
        'section_shift_id',
        'section_id',
        'shift_id',
        'pay_grade_id',
        'headcount',
        'active',
    ];

    protected $casts = [
        'headcount' => 'integer',
        'active'    => 'boolean',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (self $model): void {
            // 1. Headcount validation: integer >= 1
            if ($model->headcount !== null && (int) $model->headcount < 1) {
                throw new InvalidArgumentException('Allocated headcount must be at least 1.');
            }

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

            // 3. Cross-tenant validation for Section, Shift, Pay Grade
            $section = DB::table('production_work_center_sections')
                ->where('id', $model->section_id)
                ->first();
            if (!$section || (int) $section->tenant_id !== (int) $model->tenant_id) {
                throw new InvalidArgumentException('Section does not exist or does not belong to the allocation tenant.');
            }

            $shift = DB::table('production_shifts')
                ->where('id', $model->shift_id)
                ->first();
            if (!$shift || (int) $shift->tenant_id !== (int) $model->tenant_id) {
                throw new InvalidArgumentException('Shift does not exist or does not belong to the allocation tenant.');
            }

            $payGrade = DB::table('production_external_worker_pay_grades')
                ->where('id', $model->pay_grade_id)
                ->first();
            if (!$payGrade || (int) $payGrade->tenant_id !== (int) $model->tenant_id) {
                throw new InvalidArgumentException('Pay grade does not exist or does not belong to the allocation tenant.');
            }

            // Soft-deleted pay grade check for new allocations or pay grade changes
            if ((!$model->exists || $model->isDirty('pay_grade_id')) && $payGrade->deleted_at !== null) {
                throw new InvalidArgumentException("Cannot allocate to soft-deleted pay grade '{$payGrade->name}'.");
            }

            // 4. Section–Shift association integrity in production_section_shifts
            $secShift = DB::table('production_section_shifts')
                ->where('section_id', $model->section_id)
                ->where('shift_id', $model->shift_id)
                ->first();

            if (!$secShift) {
                throw new InvalidArgumentException("Shift #{$model->shift_id} is not assigned to Section #{$model->section_id}. Allocation requires a configured Section–Shift assignment.");
            }

            if ((int) $secShift->tenant_id !== (int) $model->tenant_id) {
                throw new InvalidArgumentException('Cross-tenant Section–Shift assignment is prohibited.');
            }

            // If section_shift_id was supplied, verify it matches
            if (!empty($model->section_shift_id)) {
                if ((int) $model->section_shift_id !== (int) $secShift->id) {
                    throw new InvalidArgumentException("Supplied section_shift_id #{$model->section_shift_id} does not match Section #{$model->section_id} and Shift #{$model->shift_id} (expected Section-Shift #{$secShift->id}).");
                }
            } else {
                // Resolve section_shift_id from exact matching pair
                $model->section_shift_id = $secShift->id;
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
