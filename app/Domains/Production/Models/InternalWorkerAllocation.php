<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use App\Models\Concerns\Loggable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InternalWorkerAllocation extends BaseModel
{
    use HasFactory, SoftDeletes, Loggable;

    protected $table = 'production_internal_worker_allocations';

    protected $fillable = [
        'tenant_id',
        'section_shift_id',
        'section_id',
        'shift_id',
        'headcount',
        'rate',
        'active',
    ];

    protected $casts = [
        'headcount' => 'integer',
        'rate'      => 'decimal:4',
        'active'    => 'boolean',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (self $model): void {
            if ($model->headcount !== null && (int) $model->headcount < 1) {
                throw new InvalidArgumentException('Allocated internal worker headcount must be at least 1.');
            }

            if (!$model->tenant_id) {
                throw new InvalidArgumentException('Tenant ID is required for internal worker allocation.');
            }
            if (!$model->section_id) {
                throw new InvalidArgumentException('Section ID is required for internal worker allocation.');
            }
            if (!$model->shift_id) {
                throw new InvalidArgumentException('Shift ID is required for internal worker allocation.');
            }

            // Cross-tenant validation
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

            // Validate Section-Shift relationship
            $sectionShift = DB::table('production_section_shifts')
                ->where('tenant_id', $model->tenant_id)
                ->where('section_id', $model->section_id)
                ->where('shift_id', $model->shift_id)
                ->first();

            if (!$sectionShift) {
                throw new InvalidArgumentException('Shift is not assigned to the specified Section in Section-Shift plan.');
            }

            if ($model->section_shift_id !== null && (int) $model->section_shift_id !== (int) $sectionShift->id) {
                throw new InvalidArgumentException(
                    "Supplied section_shift_id ({$model->section_shift_id}) does not match the actual Section–Shift relationship (#{$sectionShift->id})."
                );
            }

            $model->section_shift_id = $sectionShift->id;

            // Default temporary rate to 100.00
            if ($model->rate === null || (float) $model->rate <= 0) {
                $model->rate = 100.0000;
            }
        });
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(WorkCenterSection::class, 'section_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(ProductionShift::class, 'shift_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('production_internal_worker_allocations.active', true);
    }

    public function isActive(): bool
    {
        return (bool) $this->active;
    }
}
