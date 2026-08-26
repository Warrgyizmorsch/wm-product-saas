<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionOrderOperationDependency extends BaseModel
{
    protected $table = 'production_order_operation_dependencies';

    protected $fillable = [
        'tenant_id',
        'production_order_id',
        'operation_id',
        'predecessor_operation_id',
        'dependency_type',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }

    public function operation(): BelongsTo
    {
        return $this->belongsTo(ProductionOrderOperation::class, 'operation_id');
    }

    public function predecessorOperation(): BelongsTo
    {
        return $this->belongsTo(ProductionOrderOperation::class, 'predecessor_operation_id');
    }
}
