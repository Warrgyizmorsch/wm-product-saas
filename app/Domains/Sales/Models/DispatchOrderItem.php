<?php

namespace App\Domains\Sales\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Concerns\BelongsToTenant;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Warehouse;

class DispatchOrderItem extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'dispatch_order_items';

    protected $fillable = [
        'dispatch_order_id',
        'material_requirement_item_id',
        'product_id',
        'warehouse_id',
        'quantity_ordered',
        'quantity_dispatched',
        'serial_numbers',
        'batch_number',
    ];

    protected $casts = [
        'quantity_ordered'    => 'decimal:4',
        'quantity_dispatched' => 'decimal:4',
    ];

    public function dispatchOrder(): BelongsTo
    {
        return $this->belongsTo(DispatchOrder::class, 'dispatch_order_id');
    }

    public function materialRequirementItem(): BelongsTo
    {
        return $this->belongsTo(MaterialRequirementItem::class, 'material_requirement_item_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    protected static function booted(): void
    {
        static::creating(function (self $item) {
            if (empty($item->tenant_id) && $item->dispatchOrder) {
                $item->tenant_id = $item->dispatchOrder->tenant_id;
            }
        });
    }
}
