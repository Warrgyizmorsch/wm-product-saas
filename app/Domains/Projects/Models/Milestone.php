<?php

namespace App\Domains\Projects\Models;

use App\Core\Database\BaseModel;
use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Milestone extends BaseModel
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $table = 'project_milestones';

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

    protected $fillable = [
        'tenant_id',
        'project_id',
        'owner_id',
        'name',
        'description',
        'start_date',
        'due_date',
        'status',
        'completion_percentage',
    ];

    protected $casts = [
        'start_date'             => 'date',
        'due_date'                => 'date',
        'completion_percentage'  => 'integer',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
