<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * QR-15: the Quality Plans (specific approved versions) a routing operation runs when its QC gate is on.
 */
class RoutingOperationQualityPlan extends BaseModel
{
    protected $table = 'production_routing_operation_quality_plans';

    protected $fillable = [
        'tenant_id',
        'routing_operation_id',
        'quality_plan_id',
        'is_mandatory',
        'sort_order',
    ];

    protected $casts = [
        'is_mandatory' => 'boolean',
        'sort_order'   => 'integer',
    ];

    public function routingOperation(): BelongsTo
    {
        return $this->belongsTo(RoutingOperation::class, 'routing_operation_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ProductionQualityPlan::class, 'quality_plan_id')->withTrashed();
    }
}
