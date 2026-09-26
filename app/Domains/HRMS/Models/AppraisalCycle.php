<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AppraisalCycle extends BaseModel
{
    use SoftDeletes;

    protected $table = 'appraisal_cycles';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'name',
        'code',
        'period_type',
        'start_date',
        'end_date',
        'goal_setting_deadline',
        'self_review_deadline',
        'manager_review_deadline',
        'status',
        'goal_weightage_percent',
        'competency_weightage_percent',
        'description',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'goal_setting_deadline' => 'date',
        'self_review_deadline' => 'date',
        'manager_review_deadline' => 'date',
        'goal_weightage_percent' => 'float',
        'competency_weightage_percent' => 'float',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function goalPlans(): HasMany
    {
        return $this->hasMany(EmployeeGoalPlan::class, 'appraisal_cycle_id');
    }
}
