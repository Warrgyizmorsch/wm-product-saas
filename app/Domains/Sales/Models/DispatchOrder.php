<?php

namespace App\Domains\Sales\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DispatchOrder extends Model
{
    use HasFactory;

    protected $table = 'dispatch_orders';

    protected $fillable = [
        'tenant_id',
        'delivery_order_id',
        'sales_order_id',
        'dispatch_number',
        'dispatch_date',
        'carrier',
        'tracking_number',
        'vehicle_number',
        'driver_name',
        'driver_phone',
        'status',
        'notes',
    ];

    protected $casts = [
        'dispatch_date' => 'date',
    ];

    public function deliveryOrder(): BelongsTo
    {
        return $this->belongsTo(DeliveryOrder::class, 'delivery_order_id');
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DispatchOrderItem::class, 'dispatch_order_id');
    }
}
