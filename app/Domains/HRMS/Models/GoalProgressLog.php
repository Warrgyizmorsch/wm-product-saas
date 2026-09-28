<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoalProgressLog extends BaseModel
{
    protected $table = 'goal_progress_logs';

    protected $fillable = [
        'tenant_id',
        'employee_goal_item_id',
        'employee_id',
        'logged_by_id',
        'previous_value',
        'current_value',
        'notes',
        'attachment_path',
    ];

    protected $casts = [
        'previous_value' => 'float',
        'current_value' => 'float',
    ];

    public function goalItem(): BelongsTo
    {
        return $this->belongsTo(EmployeeGoalItem::class, 'employee_goal_item_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function loggedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'logged_by_id');
    }
}
