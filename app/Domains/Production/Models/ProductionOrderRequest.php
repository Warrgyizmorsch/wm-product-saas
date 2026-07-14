<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use App\Domains\Inventory\Models\Product;
use App\Domains\Sales\Models\DeliveryOrderItem;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionOrderRequest extends BaseModel
{
    use HasFactory;

    protected $table = 'production_order_requests';

    protected $fillable = [
        'tenant_id',
        'delivery_order_item_id',
        'product_id',
        'quantity_requested',
        'status',
        'notes',
        'created_by',
        'production_plan_id',
        'production_order_id',
    ];

    protected $casts = [
        'quantity_requested' => 'float',
    ];

    public function deliveryOrderItem(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrderItem::class, 'delivery_order_item_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }

    public function productionPlan(): BelongsTo
    {
        return $this->belongsTo(ProductionPlan::class, 'production_plan_id');
    }
}
