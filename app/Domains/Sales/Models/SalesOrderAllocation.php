<?php

namespace App\Domains\Sales\Models;

use App\Core\Database\BaseModel;
use App\Models\Concerns\BelongsToTenant;
use App\Domains\Inventory\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesOrderAllocation extends BaseModel
{
    use BelongsToTenant, HasFactory;

    protected $table = 'sales_order_allocations';

    protected $fillable = [
        'tenant_id',
        'sales_order_id',
        'sales_order_item_id',
        'warehouse_id',
        'reserved_qty',
    ];

    protected $casts = [
        'reserved_qty' => 'decimal:4',
    ];

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function salesOrderItem(): BelongsTo
    {
        return $this->belongsTo(SalesOrderItem::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }
}
