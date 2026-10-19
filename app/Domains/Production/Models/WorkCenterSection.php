<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use App\Models\Concerns\Loggable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkCenterSection extends BaseModel
{
    use HasFactory, SoftDeletes, Loggable;

    protected $table = 'production_work_center_sections';

    public const STATUS_ACTIVE   = 'active';
    public const STATUS_INACTIVE = 'inactive';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_INACTIVE,
    ];

    protected $fillable = [
        'tenant_id',
        'work_center_id',
        'name',
        'code',
        'workers_required',
        'description',
        'status',
    ];

    protected $casts = [
        'workers_required' => 'integer',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class, 'work_center_id');
    }

    public function machines(): HasMany
    {
        return $this->hasMany(Machine::class, 'section_id');
    }

    public function shifts(): BelongsToMany
    {
        return $this->belongsToMany(
            ProductionShift::class,
            'production_work_center_section_shifts',
            'section_id',
            'shift_id'
        )->distinct()->withTimestamps();
    }

    // ─── Scopes & Helpers ─────────────────────────────────────────────────────

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeForWorkCenter(Builder $query, int $workCenterId): void
    {
        $query->where('work_center_id', $workCenterId);
    }
}
