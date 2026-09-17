<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class JobRequisition extends BaseModel
{
    use SoftDeletes;

    protected $table = 'job_requisitions';

    protected $fillable = [
        'tenant_id',
        'requisition_code',
        'job_title',
        'department_id',
        'designation_id',
        'vacancies',
        'min_experience_years',
        'max_experience_years',
        'work_mode',
        'job_location',
        'employment_type',
        'priority',
        'status',
        'requested_by_employee_id',
        'approved_by_user_id',
        'approved_at',
        'skills_required',
        'job_description',
        'target_joining_date',
    ];

    protected $casts = [
        'vacancies' => 'integer',
        'min_experience_years' => 'integer',
        'max_experience_years' => 'integer',
        'approved_at' => 'datetime',
        'target_joining_date' => 'date',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class, 'designation_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'requested_by_employee_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(CandidateApplication::class, 'job_requisition_id');
    }

    public function getActiveApplicantsCountAttribute(): int
    {
        return $this->applications()->where('current_stage', '!=', 'rejected')->count();
    }
}
