<?php

namespace App\Domains\Sales\Models;

use App\Core\Database\BaseModel;
use App\Domains\Platform\Models\Transporter;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DispatchOrder extends BaseModel
{
    use HasFactory, BelongsToCompany, BelongsToBranch;

    protected $table = 'dispatch_orders';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
        'customer_id',
        'transporter_id',
        'material_requirement_id',
        'sales_order_id',
        'dispatch_number',
        'dispatch_date',
        'carrier',
        'tracking_number',
        'eway_bill_number',
        'eway_bill_date',
        'lr_number',
        'lr_date',
        'freight_terms',
        'freight_amount',
        'shipping_address',
        'total_packages',
        'gross_weight',
        'net_weight',
        'volume_cbm',
        'gate_pass_number',
        'pod_attachment_path',
        'delivered_at',
        'vehicle_number',
        'driver_name',
        'driver_phone',
        'status',
        'notes',
    ];

    protected $casts = [
        'dispatch_date' => 'date',
        'eway_bill_date' => 'date',
        'lr_date' => 'date',
        'delivered_at' => 'datetime',
        'freight_amount' => 'decimal:2',
        'gross_weight' => 'decimal:3',
        'net_weight' => 'decimal:3',
        'volume_cbm' => 'decimal:3',
    ];

    public function transporter(): BelongsTo
    {
        return $this->belongsTo(Transporter::class, 'transporter_id');
    }

    public function materialRequirement(): BelongsTo
    {
        return $this->belongsTo(MaterialRequirement::class, 'material_requirement_id');
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\CRM\Models\Customer::class, 'customer_id')->withDefault(function() {
            return $this->salesOrder?->customer;
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(DispatchOrderItem::class, 'dispatch_order_id');
    }
}
