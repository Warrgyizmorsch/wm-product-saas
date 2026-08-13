<?php

namespace App\Domains\Production\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class ProductionScheduleOptimizationRun extends Model
{
    use BelongsToTenant;

    public const STATUS_PREVIEW = 'preview';
    public const STATUS_APPLIED = 'applied';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    protected $table = 'production_schedule_optimization_runs';

    protected $fillable = [
        'tenant_id',
        'created_by',
        'scope_filters',
        'summary',
        'proposed_changes',
        'capacity_before',
        'capacity_after',
        'version_snapshot',
        'status',
        'expires_at',
    ];

    protected $casts = [
        'scope_filters'    => 'array',
        'summary'          => 'array',
        'proposed_changes' => 'array',
        'capacity_before'  => 'array',
        'capacity_after'   => 'array',
        'version_snapshot' => 'array',
        'expires_at'       => 'datetime',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PREVIEW);
    }

    public function scopeNonExpired(Builder $query): Builder
    {
        return $query->where('expires_at', '>', Carbon::now());
    }

    public function isExpired(): bool
    {
        return $this->expires_at === null || $this->expires_at->isPast();
    }
}
