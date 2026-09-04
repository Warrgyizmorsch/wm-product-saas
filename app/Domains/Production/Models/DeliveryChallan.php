<?php

namespace App\Domains\Production\Models;

use App\Domains\Inventory\Models\Vendor;
use App\Domains\Inventory\Models\Warehouse;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeliveryChallan extends Model
{
    use SoftDeletes;

    protected $table = 'delivery_challans';

    protected $fillable = [
        'tenant_id',
        'challan_number',
        'type',
        'production_order_id',
        'production_order_operation_id',
        'vendor_id',
        'warehouse_id',
        'challan_date',
        'expected_return_date',
        'status',
        'dispatched_wip_qty',
        'vehicle_number',
        'transporter_name',
        'lr_number',
        'driver_name',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'challan_date' => 'date',
        'expected_return_date' => 'date',
        'dispatched_wip_qty' => 'float',
    ];

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }

    public function operation(): BelongsTo
    {
        return $this->belongsTo(ProductionOrderOperation::class, 'production_order_operation_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DeliveryChallanItem::class, 'delivery_challan_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
