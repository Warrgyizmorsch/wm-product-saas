<?php

namespace App\Domains\Inventory\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductWarehouseStock extends BaseModel
{
    protected $table = 'product_warehouse_stocks';

    protected $fillable = [
        'tenant_id',
        'product_id',
        'warehouse_id',
        'quantity',
        'reserved_qty',
        'available_qty',
        'unit_cost',
    ];

    protected $casts = [
        'quantity' => 'float',
        'reserved_qty' => 'float',
        'available_qty' => 'float',
        'unit_cost' => 'float',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
