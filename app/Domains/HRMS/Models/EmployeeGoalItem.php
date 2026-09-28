<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeGoalItem extends BaseModel
{
    protected $table = 'employee_goal_items';

    protected $fillable = [
        'tenant_id',
        'employee_goal_plan_id',
        'kra_category_id',
        'kpi_master_id',
        'title',
        'description',
        'unit',
        'calculation_type',
        'target',
        'actual',
        'weightage',
        'self_rating',
        'self_score',
        'self_comment',
        'manager_rating',
        'manager_score',
        'manager_comment',
        'final_score',
        'status',
    ];

    protected $casts = [
        'target' => 'float',
        'actual' => 'float',
        'weightage' => 'float',
        'self_rating' => 'float',
        'self_score' => 'float',
        'manager_rating' => 'float',
        'manager_score' => 'float',
        'final_score' => 'float',
    ];

    public function goalPlan(): BelongsTo
    {
        return $this->belongsTo(EmployeeGoalPlan::class, 'employee_goal_plan_id');
    }

    public function kraCategory(): BelongsTo
    {
        return $this->belongsTo(KraCategory::class, 'kra_category_id');
    }

    public function kpiMaster(): BelongsTo
    {
        return $this->belongsTo(KpiMaster::class, 'kpi_master_id');
    }

    public function progressLogs(): HasMany
    {
        return $this->hasMany(GoalProgressLog::class, 'employee_goal_item_id');
    }
}
