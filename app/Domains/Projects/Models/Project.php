<?php

namespace App\Domains\Projects\Models;

use App\Core\Database\BaseModel;
use App\Domains\CRM\Models\Customer;
use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends BaseModel
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    public const STATUS_DRAFT = 'Draft';
    public const STATUS_ACTIVE = 'Active';
    public const STATUS_ON_HOLD = 'On Hold';
    public const STATUS_COMPLETED = 'Completed';
    public const STATUS_CLOSED = 'Closed';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_ACTIVE,
        self::STATUS_ON_HOLD,
        self::STATUS_COMPLETED,
        self::STATUS_CLOSED,
    ];

    public const PRIORITIES = ['Low', 'Medium', 'High', 'Critical'];

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
}
