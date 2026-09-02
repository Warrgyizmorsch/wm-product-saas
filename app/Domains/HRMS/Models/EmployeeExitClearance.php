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
}
