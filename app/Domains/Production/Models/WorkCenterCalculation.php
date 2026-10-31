<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkCenterCalculation extends BaseModel
{
    use HasFactory;

    protected $table = 'production_work_center_calculations';

    protected $fillable = [
        'tenant_id',
        'work_center_id',
        'snapshot_number',
        'batch_id',
        'trigger_type',
        'impacted_section_ids',
        'capacity_per_hour',
        'daily_capacity',
        'labor_cost_per_hour',
        'total_daily_labor_cost',
        'direct_staff_daily_labor_cost',
        'machine_cost_per_hour',
        'total_daily_machine_cost',
        'overhead_cost',
        'total_daily_cost',
        'total_cost_per_hour',
        'cost_per_unit',
        'unique_operating_hours',
        'calculation_details',
    ];

    protected $casts = [
        'impacted_section_ids'          => 'array',
        'capacity_per_hour'             => 'float',
        'daily_capacity'                => 'float',
        'labor_cost_per_hour'           => 'float',
        'total_daily_labor_cost'        => 'float',
        'direct_staff_daily_labor_cost' => 'float',
        'machine_cost_per_hour'         => 'float',
        'total_daily_machine_cost'      => 'float',
        'overhead_cost'                 => 'float',
        'total_daily_cost'              => 'float',
        'total_cost_per_hour'           => 'float',
        'cost_per_unit'                 => 'float',
        'unique_operating_hours'        => 'float',
        'calculation_details'           => 'array',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class, 'work_center_id');
    }

    public function sectionCalculations(): HasMany
    {
        return $this->hasMany(WorkCenterSectionCalculation::class, 'work_center_calculation_id');
    }
}
