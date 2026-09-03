<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeExitClearance extends BaseModel
{
    protected $table = 'employee_exit_clearances';

    protected $fillable = [
        'tenant_id',
        'employee_exit_id',
        'department',
        'item_name',
        'status',
        'cleared_by',
        'cleared_at',
        'remarks',
        'deduction_amount',
    ];

    protected $casts = [
        'cleared_at' => 'datetime',
        'deduction_amount' => 'decimal:2',
    ];

    public function exit(): BelongsTo
    {
        return $this->belongsTo(EmployeeExit::class, 'employee_exit_id');
    }

    public function clearedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cleared_by');
    }

    public function getDepartmentAttribute(?string $value): string
    {
        return ExitClearanceTemplate::normalizeCategoryKey($value ?? 'other');
    }

    public function setDepartmentAttribute(string $value): void
    {
        $this->attributes['department'] = ExitClearanceTemplate::normalizeCategoryKey($value);
    }

    /**
     * Semantic alias for clearance authority / category.
     */
    public function getClearanceCategoryAttribute(): string
    {
        return $this->department;
    }

    public function setClearanceCategoryAttribute(string $value): void
    {
        $this->department = $value;
    }

    /**
     * Get display metadata for this clearance item's category.
     */
    public function getCategoryMeta(): array
    {
        return ExitClearanceTemplate::getCategoryMetadata($this->department);
    }
}

