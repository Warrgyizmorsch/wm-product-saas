<?php

namespace App\Domains\Projects\Models;

use App\Core\Database\BaseModel;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TimeLog extends BaseModel
{
    use BelongsToTenant, BelongsToCompany, BelongsToBranch, HasFactory, SoftDeletes;

    protected $table = 'project_time_logs';

    public const STATUS_PENDING = 'Pending';
    public const STATUS_APPROVED = 'Approved';
    public const STATUS_REJECTED = 'Rejected';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
    ];

    protected $attributes = [
        'approval_status' => self::STATUS_PENDING,
        'is_billable'     => true,
        'is_invoiced'     => false,
    ];

    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
        'project_id',
        'task_id',
        'user_id',
        'log_date',
        'start_time',
        'end_time',
        'hours',
        'is_billable',
        'hourly_rate',
        'description',
        'approval_status',
        'approved_by',
        'approved_at',
        'rejection_remarks',
        'is_invoiced',
        'invoice_id',
    ];

    protected $casts = [
        'log_date'    => 'date',
        'hours'       => 'decimal:2',
        'is_billable' => 'boolean',
        'hourly_rate' => 'decimal:2',
        'approved_at' => 'datetime',
        'is_invoiced' => 'boolean',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isPending(): bool
    {
        return $this->approval_status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->approval_status === self::STATUS_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->approval_status === self::STATUS_REJECTED;
    }

    public function getBillableAmountAttribute(): float
    {
        if (!$this->is_billable || !$this->hourly_rate) {
            return 0.00;
        }

        return round((float) $this->hours * (float) $this->hourly_rate, 2);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('approval_status', self::STATUS_PENDING);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('approval_status', self::STATUS_APPROVED);
    }

    public function scopeRejected(Builder $query): Builder
    {
        return $query->where('approval_status', self::STATUS_REJECTED);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Sales\Models\Invoice::class, 'invoice_id');
    }
}
