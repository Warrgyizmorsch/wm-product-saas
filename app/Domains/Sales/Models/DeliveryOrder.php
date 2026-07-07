<?php

namespace App\Domains\Sales\Models;

use App\Core\Database\BaseModel;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeliveryOrder extends BaseModel
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $table = 'delivery_orders';

    protected $fillable = [
        'tenant_id',
        'sales_order_id',
        'delivery_number',
        'delivery_date',
        'status',
        'carrier',
        'tracking_number',
        'notes',
    ];

    protected $casts = [
        'delivery_date' => 'date',
    ];

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(DeliveryOrderItem::class);
    }
}
