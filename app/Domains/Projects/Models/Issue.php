<?php

namespace App\Domains\Projects\Models;

use App\Core\Database\BaseModel;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Issue extends BaseModel
{
    use BelongsToTenant, BelongsToCompany, BelongsToBranch, HasFactory, SoftDeletes;

    protected $table = 'project_issues';

    public const STATUS_OPEN = 'Open';
    public const STATUS_ASSIGNED = 'Assigned';
    public const STATUS_IN_PROGRESS = 'In Progress';
    public const STATUS_RESOLVED = 'Resolved';
    public const STATUS_CLOSED = 'Closed';

    public const STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_ASSIGNED,
        self::STATUS_IN_PROGRESS,
        self::STATUS_RESOLVED,
        self::STATUS_CLOSED,
    ];

    public const PRIORITY_LOW = 'Low';
    public const PRIORITY_MEDIUM = 'Medium';
    public const PRIORITY_HIGH = 'High';
    public const PRIORITY_CRITICAL = 'Critical';

    public const PRIORITIES = [
        self::PRIORITY_LOW,
        self::PRIORITY_MEDIUM,
        self::PRIORITY_HIGH,
        self::PRIORITY_CRITICAL,
    ];

    public const SEVERITY_MINOR = 'Minor';
    public const SEVERITY_MAJOR = 'Major';
    public const SEVERITY_CRITICAL = 'Critical';

    public const SEVERITIES = [
        self::SEVERITY_MINOR,
        self::SEVERITY_MAJOR,
        self::SEVERITY_CRITICAL,
    ];

    protected $attributes = [
        'status'   => self::STATUS_OPEN,
        'priority' => self::PRIORITY_MEDIUM,
        'severity' => self::SEVERITY_MAJOR,
    ];

    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
        'project_id',
        'task_id',
        'issue_number',
        'title',
        'description',
        'reporter_id',
        'assignee_id',
        'priority',
        'severity',
        'status',
        'steps_to_reproduce',
        'resolution_date',
        'resolution_notes',
        'retest_notes',
    ];

    protected $casts = [
        'resolution_date' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(ProjectDocument::class, 'attachable');
    }

    public function getIssueCodeAttribute(): string
    {
        return $this->issue_number;
    }
}
