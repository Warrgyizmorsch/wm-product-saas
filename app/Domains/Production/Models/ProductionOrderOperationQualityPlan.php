<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * QR-17: snapshot of the Quality Plans an order operation must run.
 *  - source "routing": copied from the routing operation when the order operation was built
 *  - source "sub_assembly": the semi's product plan, added only for the last maker operation of a sub-assembly set
 *    inside this order (never written to the routing)
 */
class ProductionOrderOperationQualityPlan extends BaseModel
{
    public const SOURCE_ROUTING      = 'routing';
    public const SOURCE_SUB_ASSEMBLY = 'sub_assembly';

    protected $table = 'production_order_operation_quality_plans';

    protected $fillable = [
        'tenant_id',
        'production_order_operation_id',
        'quality_plan_id',
        'is_mandatory',
        'source',
        'sort_order',
    ];

    protected $casts = [
        'is_mandatory' => 'boolean',
        'sort_order'   => 'integer',
    ];

    public function operation(): BelongsTo
    {
        return $this->belongsTo(ProductionOrderOperation::class, 'production_order_operation_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ProductionQualityPlan::class, 'quality_plan_id')->withTrashed();
    }
}
