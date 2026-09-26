<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppraisalReview extends BaseModel
{
    protected $table = 'appraisal_reviews';

    protected $fillable = [
        'tenant_id',
        'employee_goal_plan_id',
        'reviewer_id',
        'reviewer_role',
        'overall_rating',
        'overall_score',
        'strengths',
        'improvements',
        'promotion_recommendation',
        'increment_recommendation_percent',
        'feedback_comments',
        'status',
    ];

    protected $casts = [
        'overall_rating' => 'float',
        'overall_score' => 'float',
        'promotion_recommendation' => 'boolean',
        'increment_recommendation_percent' => 'float',
    ];

    public function goalPlan(): BelongsTo
    {
        return $this->belongsTo(EmployeeGoalPlan::class, 'employee_goal_plan_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
