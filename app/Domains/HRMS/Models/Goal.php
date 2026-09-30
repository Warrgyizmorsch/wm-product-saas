<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Goal extends BaseModel
{
    use SoftDeletes;

    protected $table = 'goals';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'code',
        'title',
        'description',
        'goal_cycle_id',
        'goal_category_id',
        'owner_type',
        'department_id',
        'employee_id',
        'parent_goal_id',
        'visibility',
        'priority',
        'start_date',
        'due_date',
        'weightage',
        'progress_percentage',
        'health_status',
        'status',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'start_date'          => 'date',
        'due_date'            => 'date',
        'weightage'           => 'float',
        'progress_percentage' => 'float',
        'approved_at'         => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(GoalCycle::class, 'goal_cycle_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(GoalCategory::class, 'goal_category_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function employees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'goal_employees', 'goal_id', 'employee_id')
            ->withPivot('tenant_id')
            ->withTimestamps();
    }

    public function parentGoal(): BelongsTo
    {
        return $this->belongsTo(Goal::class, 'parent_goal_id');
    }

    public function childGoals(): HasMany
    {
        return $this->hasMany(Goal::class, 'parent_goal_id');
    }

    public function keyResults(): HasMany
    {
        return $this->hasMany(GoalKeyResult::class, 'goal_id');
    }

    public function checkIns(): HasMany
    {
        return $this->hasMany(GoalCheckIn::class, 'goal_id')->latest('check_in_date');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Recalculate objective overall progress percentage from its key results.
     */
    public function recalculateProgress(): void
    {
        $krs = $this->keyResults;
        if ($krs->isEmpty()) {
            return;
        }

        $totalWeight = $krs->sum('weightage');
        if ($totalWeight <= 0) {
            $avg = $krs->avg('progress_percentage');
            $this->progress_percentage = round((float) $avg, 2);
        } else {
            $weightedSum = 0;
            foreach ($krs as $kr) {
                $weightedSum += ($kr->progress_percentage * $kr->weightage);
            }
            $this->progress_percentage = round((float) ($weightedSum / $totalWeight), 2);
        }

        // Auto health status determination if not manually overridden
        if ($this->progress_percentage >= 100) {
            $this->health_status = 'completed';
        }

        $this->save();
    }
}
