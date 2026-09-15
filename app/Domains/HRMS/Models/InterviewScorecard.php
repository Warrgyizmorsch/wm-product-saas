<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InterviewScorecard extends BaseModel
{
    protected $table = 'interview_scorecards';

    protected $fillable = [
        'tenant_id',
        'interview_id',
        'interviewer_user_id',
        'technical_rating',
        'communication_rating',
        'culture_fit_rating',
        'overall_rating',
        'recommendation',
        'feedback_notes',
    ];

    protected $casts = [
        'technical_rating' => 'integer',
        'communication_rating' => 'integer',
        'culture_fit_rating' => 'integer',
        'overall_rating' => 'integer',
    ];

    public function interview(): BelongsTo
    {
        return $this->belongsTo(CandidateInterview::class, 'interview_id');
    }

    public function interviewerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'interviewer_user_id');
    }
}
