<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PipCheckin extends BaseModel
{
    protected $table = 'pip_checkins';

    protected $fillable = [
        'tenant_id',
        'pip_id',
        'review_date',
        'reviewer_id',
        'rating_status',
        'manager_comments',
        'employee_comments',
        'action_items',
    ];

    protected $casts = [
        'review_date' => 'date',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PerformanceImprovementPlan::class, 'pip_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
