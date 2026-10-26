<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkCenterSectionCalculation extends BaseModel
{
    use HasFactory;

    protected $table = 'production_work_center_section_calculations';

    protected $fillable = [
        'tenant_id',
        'work_center_id',
        'section_id',
        'work_center_calculation_id',
        'batch_id',
        'trigger_type',
        'capacity_per_hour',
        'daily_capacity',
        'labor_cost_per_hour',
        'total_daily_labor_cost',
        'machine_cost_per_hour',
        'total_daily_machine_cost',
        'total_daily_operating_cost',
        'total_operating_cost_per_hour',
        'unique_operating_hours',
        'calculation_details',
    ];

    protected $casts = [
        'capacity_per_hour'             => 'float',
        'daily_capacity'                => 'float',
        'labor_cost_per_hour'           => 'float',
        'total_daily_labor_cost'        => 'float',
        'machine_cost_per_hour'         => 'float',
        'total_daily_machine_cost'      => 'float',
        'total_daily_operating_cost'    => 'float',
        'total_operating_cost_per_hour' => 'float',
        'unique_operating_hours'        => 'float',
        'calculation_details'           => 'array',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class, 'work_center_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(WorkCenterSection::class, 'section_id');
    }

    public function workCenterCalculation(): BelongsTo
    {
        return $this->belongsTo(WorkCenterCalculation::class, 'work_center_calculation_id');
    }
}
