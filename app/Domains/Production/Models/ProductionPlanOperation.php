<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionPlanOperation extends BaseModel
{
    protected $table = 'production_plan_operations';

    protected $fillable = [
        'tenant_id',
        'production_plan_id',
        'routing_operation_id',
        'sequence',
        'operation_number',
        'name',
        'work_center_id',
        'machine_id',
        'setup_time_minutes',
        'processing_time_minutes',
        'total_time_minutes',
    ];

    protected $casts = [
        'sequence'                => 'integer',
        'setup_time_minutes'      => 'float',
        'processing_time_minutes' => 'float',
        'total_time_minutes'      => 'float',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ProductionPlan::class, 'production_plan_id');
    }

    public function routingOperation(): BelongsTo
    {
        return $this->belongsTo(RoutingOperation::class, 'routing_operation_id');
    }

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class, 'work_center_id');
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class, 'machine_id');
    }
}
