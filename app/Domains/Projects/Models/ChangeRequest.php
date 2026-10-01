<?php

namespace App\Domains\Projects\Models;

use App\Core\Database\BaseModel;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChangeRequest extends BaseModel
{
    use BelongsToTenant, BelongsToCompany, BelongsToBranch, HasFactory, SoftDeletes;

    protected $table = 'project_change_requests';

    public const STATUS_PENDING = 'Pending';
    public const STATUS_APPROVED = 'Approved';
    public const STATUS_REJECTED = 'Rejected';
    public const STATUS_IMPLEMENTED = 'Implemented';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
        self::STATUS_IMPLEMENTED,
    ];

    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
        'project_id',
        'project_review_id',
        'cr_number',
        'title',
        'description',
        'requested_by',
        'impact_schedule_days',
        'impact_budget_amount',
        'impact_budget_hours',
        'status',
        'approved_by',
        'approved_at',
        'rejection_remarks',
        'created_by',
    ];

    protected $casts = [
        'impact_schedule_days' => 'integer',
        'impact_budget_amount' => 'decimal:2',
        'impact_budget_hours'  => 'decimal:2',
        'approved_at'          => 'datetime',
    ];

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function isImplemented(): bool
    {
        return $this->status === self::STATUS_IMPLEMENTED;
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(ProjectReview::class, 'project_review_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
