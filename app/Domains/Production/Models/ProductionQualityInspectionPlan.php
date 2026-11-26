<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * QR-16: the plans one inspection covered (an operation may run several plans in one QC submission).
 */
class ProductionQualityInspectionPlan extends BaseModel
{
    protected $table = 'production_quality_inspection_plans';

    protected $fillable = [
        'tenant_id',
        'quality_inspection_id',
        'quality_plan_id',
        'is_mandatory',
    ];

    protected $casts = [
        'is_mandatory' => 'boolean',
    ];

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(ProductionQualityInspection::class, 'quality_inspection_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ProductionQualityPlan::class, 'quality_plan_id')->withTrashed();
    }
}
