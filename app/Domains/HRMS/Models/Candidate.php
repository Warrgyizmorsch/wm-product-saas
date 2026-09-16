<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Candidate extends BaseModel
{
    use SoftDeletes;

    protected $table = 'candidates';

    protected $fillable = [
        'tenant_id',
        'candidate_code',
        'first_name',
        'last_name',
        'email',
        'phone',
        'current_location',
        'current_company',
        'current_designation',
        'total_experience_years',
        'notice_period_days',
        'resume_path',
        'source',
        'source_details',
        'status',
    ];

    protected $casts = [
        'total_experience_years' => 'integer',
        'notice_period_days' => 'integer',
    ];

    public function getFullNameAttribute(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function applications(): HasMany
    {
        return $this->hasMany(CandidateApplication::class, 'candidate_id');
    }
}
