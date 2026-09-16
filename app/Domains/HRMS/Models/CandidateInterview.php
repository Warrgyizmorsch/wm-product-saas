<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CandidateInterview extends BaseModel
{
    protected $table = 'candidate_interviews';

    protected $fillable = [
        'tenant_id',
        'application_id',
        'round_number',
        'round_name',
        'scheduled_at',
        'interviewer_employee_id',
        'meeting_link',
        'venue_location',
        'status',
        'round_notes',
    ];

    protected $casts = [
        'round_number' => 'integer',
        'scheduled_at' => 'datetime',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(CandidateApplication::class, 'application_id');
    }

    public function interviewer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'interviewer_employee_id');
    }

    public function scorecard(): HasOne
    {
        return $this->hasOne(InterviewScorecard::class, 'interview_id');
    }
}
