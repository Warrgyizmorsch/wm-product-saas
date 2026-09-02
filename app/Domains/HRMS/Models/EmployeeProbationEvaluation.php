<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeProbationEvaluation extends BaseModel
{
    protected $table = 'employee_probation_evaluations';

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'reviewer_id',
        'evaluation_date',
        'performance_rating',
        'attendance_rating',
        'culture_rating',
        'recommendation',
        'extension_days',
        'new_probation_end_date',
        'remarks',
        'status',
    ];

    protected $casts = [
        'evaluation_date' => 'date',
        'new_probation_end_date' => 'date',
        'performance_rating' => 'integer',
        'attendance_rating' => 'integer',
        'culture_rating' => 'integer',
        'extension_days' => 'integer',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function getAverageRatingAttribute(): float
    {
        $sum = ($this->performance_rating ?? 0) + ($this->attendance_rating ?? 0) + ($this->culture_rating ?? 0);
        return round($sum / 3, 1);
    }
}
