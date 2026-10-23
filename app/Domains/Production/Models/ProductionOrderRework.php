<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToCompany;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionOrderRework extends BaseModel
{
    use BelongsToCompany, BelongsToBranch;

    protected $table = 'production_order_reworks';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
        'production_order_id',
        'production_order_operation_id',
        'production_batch_id',
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

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($rework) {
            if (empty($rework->recorded_at)) {
                $rework->recorded_at = now();
            }
            if ((empty($rework->company_id) || empty($rework->branch_id)) && !empty($rework->production_order_id)) {
                $order = $rework->order ?: ProductionOrder::withoutGlobalScopes()->find($rework->production_order_id);
                if ($order) {
                    if (empty($rework->company_id)) {
                        $rework->company_id = $order->company_id;
                    }
                    if (empty($rework->branch_id)) {
                        $rework->branch_id = $order->branch_id;
                    }
                }
            }
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }

    public function operation(): BelongsTo
    {
        return $this->belongsTo(ProductionOrderOperation::class, 'production_order_operation_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(ProductionBatch::class, 'production_batch_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
