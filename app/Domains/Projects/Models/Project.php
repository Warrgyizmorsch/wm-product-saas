<?php

namespace App\Domains\Projects\Models;

use App\Core\Database\BaseModel;
use App\Domains\CRM\Models\Customer;
use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends BaseModel
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    public function getRouteKeyName(): string
    {
        return 'project_code';
    }

    public const STATUS_DRAFT = 'Draft';
    public const STATUS_ACTIVE = 'Active';
    public const STATUS_ON_HOLD = 'On Hold';
    public const STATUS_COMPLETED = 'Completed';
    public const STATUS_CLOSED = 'Closed';
    public const STATUS_CANCELLED = 'Cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_ACTIVE,
        self::STATUS_ON_HOLD,
        self::STATUS_COMPLETED,
        self::STATUS_CLOSED,
        self::STATUS_CANCELLED,
    ];

    // A new project may only start as Draft or Active.
    public const CREATABLE_STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_ACTIVE,
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

    public const BUDGET_TYPES = ['Fixed', 'Time & Material'];

    public const BILLING_METHODS = ['Project Based', 'Milestone Based', 'Task Based', 'User Based'];

    protected $fillable = [
        'tenant_id',
        'project_code',
        'name',
        'customer_id',
        'owner_id',
        'manager_id',
        'start_date',
        'end_date',
        'budget_type',
        'budget_amount',
        'budget_hours',
        'billing_method',
        'priority',
        'status',
        'description',
    ];

    protected $casts = [
        'start_date'    => 'date',
        'end_date'      => 'date',
        'budget_amount' => 'decimal:2',
        'budget_hours'  => 'decimal:2',
    ];

    /**
     * Human-readable status label, the single source of truth for how a
     * status renders — used by both the listing UI and the export so the
     * two can never disagree on labels.
     */
    protected function statusLabel(): Attribute
    {
        return Attribute::get(fn () => __('projects.statuses.' . $this->status));
    }

    /**
     * Human-readable priority label, the single source of truth for how a
     * priority renders — used by both the listing UI and the export so the
     * two can never disagree on labels.
     */
    protected function priorityLabel(): Attribute
    {
        return Attribute::get(fn () => __('projects.priorities.' . $this->priority));
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class, 'project_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(ProjectMember::class, 'project_id');
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(Milestone::class, 'project_id');
    }

    public function taskLists(): HasMany
    {
        return $this->hasMany(TaskList::class, 'project_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'project_id');
    }
}
