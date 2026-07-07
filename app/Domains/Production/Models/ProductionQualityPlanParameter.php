<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionQualityPlanParameter extends BaseModel
{
    use HasFactory;

    protected $table = 'production_quality_plan_parameters';

    protected $fillable = [
        'tenant_id',
        'quality_plan_id',
        'name',
        'type',
        'min_value',
        'max_value',
        'unit_of_measure',
        'sampling_type',
        'sampling_value',
        'is_mandatory',
    ];

    protected $casts = [
        'min_value'      => 'float',
        'max_value'      => 'float',
        'sampling_value' => 'float',
        'is_mandatory'   => 'boolean',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ProductionQualityPlan::class, 'quality_plan_id');
    }
}
