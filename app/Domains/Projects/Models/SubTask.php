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

class SubTask extends BaseModel
{
    use BelongsToTenant, BelongsToCompany, BelongsToBranch, HasFactory, SoftDeletes;

    protected $table = 'project_sub_tasks';

    public const STATUS_OPEN = 'Open';
    public const STATUS_IN_PROGRESS = 'In Progress';
    public const STATUS_REVIEW = 'Review';
    public const STATUS_ON_HOLD = 'On Hold';
    public const STATUS_COMPLETED = 'Completed';
    public const STATUS_CANCELLED = 'Cancelled';

    public const STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_IN_PROGRESS,
        self::STATUS_REVIEW,
        self::STATUS_ON_HOLD,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
        'task_id',
        'title',
        'assignee_id',
        'start_date',
        'due_date',
        'estimated_hours',
        'status',
        'is_completed',
        'position',
        'completed_at',
    ];

    protected $casts = [
        'start_date'      => 'date',
        'due_date'        => 'date',
        'estimated_hours' => 'decimal:2',
        'is_completed'    => 'boolean',
        'position'        => 'integer',
        'completed_at'    => 'datetime',
    ];

    public function isFinished(): bool
    {
        return $this->status === self::STATUS_COMPLETED || $this->is_completed;
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }
}
