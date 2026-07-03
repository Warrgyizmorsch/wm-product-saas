<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionOrderRework extends BaseModel
{
    protected $table = 'production_order_reworks';

    protected $fillable = [
        'tenant_id',
        'production_order_id',
        'production_order_operation_id',
        'quantity',
        'reason',
        'status',
        'recorded_by',
        'recorded_at',
    ];

    protected $casts = [
        'quantity'    => 'float',
        'recorded_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }

    public function operation(): BelongsTo
    {
        return $this->belongsTo(ProductionOrderOperation::class, 'production_order_operation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
