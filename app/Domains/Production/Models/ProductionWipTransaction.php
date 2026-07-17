<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionWipTransaction extends BaseModel
{
    protected $table = 'production_wip_transactions';

    protected $fillable = [
        'tenant_id',
        'wip_id',
        'production_order_id',
        'production_batch_id',
        'from_operation_id',
        'to_operation_id',
        'from_work_center_id',
        'to_work_center_id',
        'machine_id',
        'operator_id',
        'transaction_type',
        'quantity',
        'good_quantity',
        'rejected_quantity',
        'scrap_quantity',
        'rework_quantity',
        'cost_before',
        'cost_added',
        'cost_after',
        'remarks',
        'transaction_at',
        'created_by',
    ];

    protected $casts = [
        'quantity' => 'float',
        'good_quantity' => 'float',
        'rejected_quantity' => 'float',
        'scrap_quantity' => 'float',
        'rework_quantity' => 'float',
        'cost_before' => 'float',
        'cost_added' => 'float',
        'cost_after' => 'float',
        'transaction_at' => 'datetime',
    ];

    public function wip(): BelongsTo
    {
        return $this->belongsTo(ProductionWip::class, 'wip_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductionBatch::class, 'production_batch_id');
    }

    public function fromOperation(): BelongsTo
    {
        return $this->belongsTo(RoutingOperation::class, 'from_operation_id');
    }

    public function toOperation(): BelongsTo
    {
        return $this->belongsTo(RoutingOperation::class, 'to_operation_id');
    }

    public function fromWorkCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class, 'from_work_center_id');
    }

    public function toWorkCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class, 'to_work_center_id');
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class, 'machine_id');
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
