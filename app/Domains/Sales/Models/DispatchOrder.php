<?php

namespace App\Domains\Sales\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DispatchOrder extends BaseModel
{
    use HasFactory;

    protected $table = 'dispatch_orders';

    protected $fillable = [
        'tenant_id',
        'material_requirement_id',
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

    public function materialRequirement(): BelongsTo
    {
        return $this->belongsTo(MaterialRequirement::class, 'material_requirement_id');
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
