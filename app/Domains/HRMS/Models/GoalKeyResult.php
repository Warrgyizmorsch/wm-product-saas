<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class GoalKeyResult extends BaseModel
{
    use SoftDeletes;

    protected $table = 'goal_key_results';

    protected $fillable = [
        'tenant_id',
        'goal_id',
        'title',
        'description',
        'metric_type',
        'unit',
        'start_value',
        'target_value',
        'current_value',
        'weightage',
        'progress_percentage',
        'health_status',
        'owner_id',
        'due_date',
    ];

    protected $casts = [
        'start_value'         => 'float',
        'target_value'        => 'float',
        'current_value'       => 'float',
        'weightage'           => 'float',
        'progress_percentage' => 'float',
        'due_date'            => 'date',
    ];

    public function goal(): BelongsTo
    {
        return $this->belongsTo(Goal::class, 'goal_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'owner_id');
    }

    public function checkIns(): HasMany
    {
        return $this->hasMany(GoalCheckIn::class, 'goal_key_result_id')->latest('check_in_date');
    }

    /**
     * Compute progress percentage based on metric type and current/target values.
     */
    public function calculateProgress(): float
    {
        if ($this->metric_type === 'boolean_milestone') {
            return $this->current_value >= 1 ? 100.0 : 0.0;
        }

        $range = $this->target_value - $this->start_value;
        if ($range <= 0) {
            return $this->current_value >= $this->target_value ? 100.0 : 0.0;
        }

        $achieved = $this->current_value - $this->start_value;
        $pct = ($achieved / $range) * 100;
        return (float) max(0.0, min(100.0, round($pct, 2)));
    }
}
