<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CandidateApplication extends BaseModel
{
    protected $table = 'candidate_applications';

    protected $fillable = [
        'tenant_id',
        'candidate_id',
        'job_requisition_id',
        'current_stage',
        'stage_updated_at',
        'rejection_reason',
        'notes',
    ];

    protected $casts = [
        'stage_updated_at' => 'datetime',
    ];

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class, 'candidate_id');
    }

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(JobRequisition::class, 'job_requisition_id');
    }

    public function interviews(): HasMany
    {
        return $this->hasMany(CandidateInterview::class, 'application_id')->orderBy('round_number', 'asc');
    }

    public function offer(): HasOne
    {
        return $this->hasOne(JobOffer::class, 'application_id');
    }
}
