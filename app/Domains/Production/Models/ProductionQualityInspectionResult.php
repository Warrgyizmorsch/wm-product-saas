<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionQualityInspectionResult extends BaseModel
{
    use HasFactory;

    protected $table = 'production_quality_inspection_results';

    protected $fillable = [
        'tenant_id',
        'quality_inspection_id',
        'quality_plan_parameter_id',
        'recorded_value_numeric',
        'recorded_value_text',
        'recorded_value_pass',
        'result',
    ];

    protected $casts = [
        'recorded_value_numeric' => 'float',
        'recorded_value_pass'    => 'boolean',
    ];

    public function inspection(): BelongsTo
    {
        return $this->belongsTo(ProductionQualityInspection::class, 'quality_inspection_id');
    }

    public function parameter(): BelongsTo
    {
        return $this->belongsTo(ProductionQualityPlanParameter::class, 'quality_plan_parameter_id');
    }
}
