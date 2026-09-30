<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoalCheckIn extends BaseModel
{
    protected $table = 'goal_check_ins';

    protected $fillable = [
        'tenant_id',
        'goal_id',
        'goal_key_result_id',
        'user_id',
        'employee_id',
        'previous_value',
        'new_value',
        'previous_progress',
        'new_progress',
        'health_status',
        'comment',
        'blockers',
        'check_in_date',
    ];

    protected $casts = [
        'previous_value'    => 'float',
        'new_value'         => 'float',
        'previous_progress' => 'float',
        'new_progress'      => 'float',
        'check_in_date'     => 'datetime',
    ];

    public function goal(): BelongsTo
    {
        return $this->belongsTo(Goal::class, 'goal_id');
    }

    public function keyResult(): BelongsTo
    {
        return $this->belongsTo(GoalKeyResult::class, 'goal_key_result_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
