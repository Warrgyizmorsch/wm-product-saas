<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use App\Domains\HRMS\Models\Employee;
use App\Models\Concerns\Loggable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class WorkCenterEmployeeAssignment extends BaseModel
{
    use HasFactory, SoftDeletes, Loggable;

    protected $table = 'production_work_center_employees';

    protected $fillable = [
        'tenant_id',
        'work_center_id',
        'section_id',
        'employee_id',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (self $model): void {
            if (!$model->tenant_id) {
                throw new InvalidArgumentException('Tenant ID is required for employee assignment.');
            }
            if (!$model->work_center_id) {
                throw new InvalidArgumentException('Work Center ID is required for employee assignment.');
            }
            if (!$model->employee_id) {
                throw new InvalidArgumentException('Employee ID is required for employee assignment.');
            }

            // Cross-tenant validation
            $workCenter = DB::table('production_work_centers')
                ->where('id', $model->work_center_id)
                ->first();
            if (!$workCenter || (int) $workCenter->tenant_id !== (int) $model->tenant_id) {
                throw new InvalidArgumentException('Work Center does not exist or does not belong to the assignment tenant.');
            }

            if ($model->section_id) {
                $section = DB::table('production_work_center_sections')
                    ->where('id', $model->section_id)
                    ->first();
                if (!$section || (int) $section->tenant_id !== (int) $model->tenant_id) {
                    throw new InvalidArgumentException('Section does not exist or does not belong to the assignment tenant.');
                }
                if ((int) $section->work_center_id !== (int) $model->work_center_id) {
                    throw new InvalidArgumentException('Section does not belong to the specified Work Center.');
                }
            }

            $employee = DB::table('employees')
                ->where('id', $model->employee_id)
                ->first();
            if (!$employee || (int) $employee->tenant_id !== (int) $model->tenant_id) {
                throw new InvalidArgumentException('Employee does not exist or does not belong to the assignment tenant.');
            }
        });
    }

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class, 'work_center_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(WorkCenterSection::class, 'section_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('production_work_center_employees.active', true);
    }

    public function isActive(): bool
    {
        return (bool) $this->active;
    }
}
