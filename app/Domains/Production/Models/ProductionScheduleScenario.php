<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionScheduleScenario extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'production_schedule_scenarios';

    public const STATUS_DRAFT      = 'draft';
    public const STATUS_CALCULATED = 'calculated';
    public const STATUS_PROMOTED   = 'promoted';
    public const STATUS_DISCARDED  = 'discarded';
    public const STATUS_EXPIRED    = 'expired';

    public const TYPE_CUSTOM           = 'custom';
    public const TYPE_MACHINE_DOWNTIME = 'machine_downtime';
    public const TYPE_RUSH_ORDER       = 'rush_order';
    public const TYPE_PRIORITY_CHANGE  = 'priority_change';
    public const TYPE_CAPACITY_CHANGE  = 'capacity_change';
    public const TYPE_SCHEDULE_DELAY   = 'schedule_delay';

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'created_by',
        'source_type',
        'source_schedule_id',
        'status',
        'scenario_type',
        'scope_filters',
        'assumptions',
        'summary',
        'promoted_at',
        'promoted_by',
    ];

    protected $casts = [
        'scope_filters' => 'array',
        'assumptions'   => 'array',
        'summary'       => 'array',
        'promoted_at'   => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function promoter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'promoted_by');
    }

    public function sourceSchedule(): BelongsTo
    {
        return $this->belongsTo(ProductionSchedule::class, 'source_schedule_id');
    }

    public function scenarioOperations(): HasMany
    {
        return $this->hasMany(ProductionScheduleScenarioOperation::class, 'scenario_id');
    }

    public function isPromoted(): bool
    {
        return $this->status === self::STATUS_PROMOTED;
    }

    public function isDiscarded(): bool
    {
        return $this->status === self::STATUS_DISCARDED;
    }
}
