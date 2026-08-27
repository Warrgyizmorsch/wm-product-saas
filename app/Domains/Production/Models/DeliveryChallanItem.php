<?php

namespace App\Domains\Production\Models;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryChallanItem extends Model
{
    protected $table = 'delivery_challan_items';

    protected $fillable = [
        'tenant_id',
        'delivery_challan_id',
        'product_id',
        'warehouse_id',
        'quantity',
        'unit_of_measure',
        'batch_number',
        'serial_number',
        'notes',
    ];

    public function challan(): BelongsTo
    {
        return $this->belongsTo(DeliveryChallan::class, 'delivery_challan_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }
}
