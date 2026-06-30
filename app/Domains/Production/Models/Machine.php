<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use App\Models\Concerns\Loggable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Machine extends BaseModel
{
    use HasFactory, SoftDeletes, Loggable;

    protected $table = 'production_machines';

    // Q3: Defined status constants — no magic strings in application code
    public const STATUS_ACTIVE            = 'active';
    public const STATUS_INACTIVE          = 'inactive';
    public const STATUS_UNDER_MAINTENANCE = 'under_maintenance';
    public const STATUS_DECOMMISSIONED    = 'decommissioned';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_INACTIVE,
        self::STATUS_UNDER_MAINTENANCE,
        self::STATUS_DECOMMISSIONED,
    ];

    protected $fillable = [
        'tenant_id',
        'work_center_id',
        'name',
        'code',
        'machine_type',
        'manufacturer',
        'model_number',
        'capacity',
        'status',
        'installation_date',
        'maintenance_status', // A7: future maintenance module integration point
    ];

    protected $casts = [
        'capacity'          => 'float',
        'installation_date' => 'date',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class, 'work_center_id');
    }

    public function operations(): HasMany
    {
        return $this->hasMany(RoutingOperation::class, 'machine_id');
    }

    // ─── Status Helpers ───────────────────────────────────────────────────────

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isInactive(): bool
    {
        return $this->status === self::STATUS_INACTIVE;
    }

    public function isUnderMaintenance(): bool
    {
        return $this->status === self::STATUS_UNDER_MAINTENANCE;
    }

    public function isDecommissioned(): bool
    {
        return $this->status === self::STATUS_DECOMMISSIONED;
    }

    public function isAvailable(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive(Builder $query): void
    {
        $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeForWorkCenter(Builder $query, int $workCenterId): void
    {
        $query->where('work_center_id', $workCenterId);
    }

    public function scopeAvailableForProduction(Builder $query): void
    {
        $query->whereIn('status', [self::STATUS_ACTIVE]);
    }
}
