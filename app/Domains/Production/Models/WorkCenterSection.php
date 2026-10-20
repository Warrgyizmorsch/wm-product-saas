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
        'supports_manual_production',
        'manual_capacity_per_hour',
        'capacity_per_hour',
        'labor_cost_per_hour',
        'total_scheduled_labor_cost',
        'unique_operating_hours',
        'calculation_details',
        'description',
        'status',
    ];

    protected $casts = [
        'workers_required'           => 'integer',
        'supports_manual_production' => 'boolean',
        'manual_capacity_per_hour'   => 'decimal:4',
        'capacity_per_hour'          => 'decimal:4',
        'labor_cost_per_hour'        => 'decimal:4',
        'total_scheduled_labor_cost' => 'decimal:4',
        'unique_operating_hours'     => 'decimal:4',
        'calculation_details'        => 'array',
    ];

    public function supportsManualProduction(): bool
    {
        return (bool) $this->supports_manual_production;
    }

    public function getCalculationDetails(): array
    {
        if (is_array($this->calculation_details) && !empty($this->calculation_details)) {
            return $this->calculation_details;
        }

        return app(\App\Domains\Production\Services\SectionCalculationService::class)->calculate($this);
    }

    public function hasOverlappingShifts(): bool
    {
        $details = $this->getCalculationDetails();
        return (bool) ($details['labor_cost']['has_overlapping_shifts'] ?? false);
    }

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
        )->distinct()->withPivot('workers_required', 'tenant_id')->withTimestamps();
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
