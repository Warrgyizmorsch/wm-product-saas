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
        'unit_cost',
    ];

    protected $casts = [
        'quantity' => 'float',
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
