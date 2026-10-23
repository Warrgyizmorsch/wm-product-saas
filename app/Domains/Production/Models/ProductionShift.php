<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProductionShift extends BaseModel
{
    use HasFactory;

    protected $table = 'production_shifts';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'name',
        'code',
        'start_time',
        'end_time',
        'break_minutes',
        'overtime_allowed',
        'active',
    ];

    protected $casts = [
        'break_minutes'     => 'integer',
        'overtime_allowed'  => 'boolean',
        'active'            => 'boolean',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::saving(function (self $model): void {
            if ($model->exists && $model->isDirty('active') && !$model->active) {
                if ($model->isAssignedToAnySection()) {
                    throw new \InvalidArgumentException("Shift '{$model->name}' cannot be deactivated because it is currently assigned to one or more Sections.");
                }
            }
        });
    }

    public function company(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Domains\HRMS\Models\Company::class, 'company_id');
    }

    /**
     * Get sections assigned this shift via their assigned_shifts JSON.
     * Note: production_section_shifts pivot table was dropped in schema redesign.
     *
     * @return \Illuminate\Support\Collection<int, WorkCenterSection>
     */
    public function getSectionsAttribute(): \Illuminate\Support\Collection
    {
        $allSections = WorkCenterSection::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant_id)
            ->get();

        return $allSections->filter(fn($sec) => $sec->hasShift($this->id))->values();
    }

    /**
     * Check if this Shift is assigned to any Section.
     */
    public function isAssignedToAnySection(): bool
    {
        $sections = WorkCenterSection::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant_id)
            ->get();

        foreach ($sections as $section) {
            if ($section->hasShift($this->id)) {
                return true;
            }
        }

        return false;
    }

    public function externalWorkerAllocations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ExternalWorkerAllocation::class, 'shift_id');
    }

    public function isActive(): bool
    {
        return (bool) $this->active;
    }
}

