<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use App\Models\Concerns\Loggable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkCenter extends BaseModel
{
    use HasFactory, SoftDeletes, Loggable;

    public const TYPES = ['department', 'section', 'work_center', 'machine_group'];

    protected $table = 'production_work_centers';

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'work_center_type',  // A4: future FK when master table arrives
        'description',
        'department_name',   // Q2: plain string, no FK
        'location',
        'capacity_per_hour',
        'efficiency_percentage',
        'cost_per_hour',
        'overhead_rate',
        'status',
        'parent_id',
        'type',
        'production_calendar_id',
    ];

    protected $casts = [
        'capacity_per_hour'      => 'float',
        'efficiency_percentage'  => 'float',
        'cost_per_hour'          => 'float',
        'overhead_rate'          => 'float',
        'parent_id'              => 'integer',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function parent(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function machines(): HasMany
    {
        return $this->hasMany(Machine::class, 'work_center_id');
    }

    public function calendar(): BelongsTo
    {
        return $this->belongsTo(ProductionCalendar::class, 'production_calendar_id');
    }

    public function shifts(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(
            ProductionShift::class,
            'production_work_center_shifts',
            'work_center_id',
            'shift_id'
        );
    }

    public function activeMachines(): HasMany
    {
        return $this->hasMany(Machine::class, 'work_center_id')
            ->where('status', 'active');
    }

    public function operations(): HasMany
    {
        return $this->hasMany(RoutingOperation::class, 'work_center_id');
    }

    // ─── Status Helpers ───────────────────────────────────────────────────────

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isInactive(): bool
    {
        return $this->status === 'inactive';
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeActive(Builder $query): void
    {
        $query->where('status', 'active');
    }

    public function scopeOfType(Builder $query, string $type): void
    {
        $query->where('work_center_type', $type);
    }

    // ─── Computed Helpers ─────────────────────────────────────────────────────

    /**
     * Effective capacity considering efficiency percentage.
     * Future: used by SchedulingEngine to calculate available slots.
     */
    public function effectiveCapacityPerHour(): float
    {
        return ($this->capacity_per_hour ?? 0) * ($this->efficiency_percentage / 100);
    }

    /**
     * Get the breadcrumb path of the work center hierarchy.
     * e.g. "Fabrication > Cutting > Laser Cutting"
     */
    public function getHierarchyPath(string $separator = ' > '): string
    {
        $path = [$this->name];
        $current = $this;
        $depth = 0;

        while ($current->parent_id && $current->parent && $depth < 10) {
            $current = $current->parent;
            array_unshift($path, $current->name);
            $depth++;
        }

        return implode($separator, $path);
    }
}
