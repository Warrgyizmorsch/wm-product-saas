<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Uom;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionOrderReservation extends BaseModel
{
    protected $table = 'production_order_reservations';

    protected $fillable = [
        'tenant_id',
        'production_order_id',
        'bom_item_id',
        'product_id',
        'quantity_planned',
        'quantity_reserved',
        'quantity_issued',
        'uom_id',
    ];

    protected $casts = [
        'quantity_planned'  => 'float',
        'quantity_reserved' => 'float',
        'quantity_issued'   => 'float',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }

    public function bomItem(): BelongsTo
    {
        return $this->belongsTo(ProductionBomItem::class, 'bom_item_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class, 'uom_id');
    }

    public function issues(): HasMany
    {
        return $this->hasMany(ProductionOrderIssue::class, 'reservation_id');
    }
}
