<?php

namespace App\Domains\Projects\Models;

use App\Core\Database\BaseModel;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProjectReview extends BaseModel
{
    use BelongsToTenant, BelongsToCompany, BelongsToBranch, HasFactory, SoftDeletes;

    protected $table = 'project_reviews';

    public const STATUS_PENDING = 'Pending';
    public const STATUS_APPROVED = 'Approved';
    public const STATUS_REWORK_REQUIRED = 'Rework Required';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_REWORK_REQUIRED,
    ];

    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
        'project_id',
        'reviewer_id',
        'reviewer_name',
        'review_date',
        'status',
        'sign_off_ref',
        'comments',
        'created_by',
    ];

    protected $casts = [
        'review_date' => 'date',
    ];

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isReworkRequired(): bool
    {
        return $this->status === self::STATUS_REWORK_REQUIRED;
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function changeRequests(): HasMany
    {
        return $this->hasMany(ChangeRequest::class, 'project_review_id');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(ProjectDocument::class, 'attachable');
    }
}
