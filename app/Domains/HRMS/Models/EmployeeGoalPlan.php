<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmployeeGoalPlan extends BaseModel
{
    use SoftDeletes;

    protected $table = 'employee_goal_plans';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'plan_number',
        'employee_id',
        'appraisal_cycle_id',
        'manager_id',
        'kpi_template_id',
        'status',
        'total_weightage',
        'goal_score',
        'competency_score',
        'final_score',
        'final_grade',
        'normalized_score',
        'employee_comments',
        'manager_comments',
        'hr_comments',
        'promotion_recommended',
        'pip_triggered',
        'submitted_at',
        'approved_at',
        'self_reviewed_at',
        'manager_reviewed_at',
        'calibrated_at',
        'signed_off_at',
    ];

    protected $casts = [
        'total_weightage' => 'float',
        'goal_score' => 'float',
        'competency_score' => 'float',
        'final_score' => 'float',
        'normalized_score' => 'float',
        'promotion_recommended' => 'boolean',
        'pip_triggered' => 'boolean',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'self_reviewed_at' => 'datetime',
        'manager_reviewed_at' => 'datetime',
        'calibrated_at' => 'datetime',
        'signed_off_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function appraisalCycle(): BelongsTo
    {
        return $this->belongsTo(AppraisalCycle::class, 'appraisal_cycle_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(KpiTemplate::class, 'kpi_template_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(EmployeeGoalItem::class, 'employee_goal_plan_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(AppraisalReview::class, 'employee_goal_plan_id');
    }

    public function isGoalSettingOverdue(): bool
    {
        if (!in_array($this->status, ['draft', 'submitted'])) {
            return false;
        }
        return $this->appraisalCycle?->goal_setting_deadline 
            && \Carbon\Carbon::today()->gt($this->appraisalCycle->goal_setting_deadline);
    }

    public function isSelfReviewOverdue(): bool
    {
        if (!in_array($this->status, ['approved', 'in_progress'])) {
            return false;
        }
        return $this->appraisalCycle?->self_review_deadline 
            && \Carbon\Carbon::today()->gt($this->appraisalCycle->self_review_deadline);
    }

    public function getOverdueStatus(): ?string
    {
        if ($this->isGoalSettingOverdue()) {
            return 'Goals Overdue';
        }
        if ($this->isSelfReviewOverdue()) {
            return 'Review Overdue';
        }
        return null;
    }
}
