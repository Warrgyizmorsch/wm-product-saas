<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Feedback360Nomination extends BaseModel
{
    use SoftDeletes;

    protected $table = 'feedback_360_nominations';

    protected $fillable = [
        'tenant_id',
        'cycle_id',
        'participant_id',
        'employee_id',
        'reviewer_id',
        'reviewer_type',
        'status',
        'is_anonymous',
        'nominated_by',
        'approved_by',
        'nomination_reason',
        'rejection_reason',
        'submitted_at',
    ];

    protected $casts = [
        'is_anonymous' => 'boolean',
        'submitted_at' => 'datetime',
    ];

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(Feedback360Cycle::class, 'cycle_id');
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(Feedback360Participant::class, 'participant_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reviewer_id');
    }

    public function nominator(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'nominated_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(Feedback360Response::class, 'nomination_id');
    }
}
