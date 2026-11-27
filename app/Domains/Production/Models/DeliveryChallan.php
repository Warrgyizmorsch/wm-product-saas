<?php

namespace App\Domains\Production\Models;

use App\Domains\Inventory\Models\Vendor;
use App\Domains\Inventory\Models\Warehouse;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToCompany;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeliveryChallan extends Model
{
    use SoftDeletes, BelongsToCompany, BelongsToBranch;

    protected $table = 'delivery_challans';

    // Dispatch slip life cycle (TF-04 / C-07): draft → dispatched → partially_received → completed; cancelled from draft
    public const STATUS_DRAFT              = 'draft';
    public const STATUS_DISPATCHED         = 'dispatched';
    public const STATUS_PARTIALLY_RECEIVED = 'partially_received';
    public const STATUS_COMPLETED          = 'completed';
    public const STATUS_CANCELLED          = 'cancelled';

    /** Material / WIP is still (partly) with the vendor */
    public const AT_VENDOR_STATUSES = [self::STATUS_DISPATCHED, self::STATUS_PARTIALLY_RECEIVED];

    /** Counted as "already sent" when working out what can still be dispatched (a draft never counts, H-07) */
    public const SENT_STATUSES = [self::STATUS_DISPATCHED, self::STATUS_PARTIALLY_RECEIVED, self::STATUS_COMPLETED];

    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
        'challan_number',
        'type',
        'production_order_id',
        'production_order_operation_id',
        'vendor_id',
        'warehouse_id',
        'challan_date',
        'expected_return_date',
        'status',
        'dispatched_at',
        'completed_at',
        'dispatched_wip_qty', // units of work sent (WIP units, or the operation units the sent material is for)
        'received_qty',
        'accepted_qty',
        'rejected_qty',
        'scrapped_qty',
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
        'received_qty' => 'float',
        'accepted_qty' => 'float',
        'rejected_qty' => 'float',
        'scrapped_qty' => 'float',
        'dispatched_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function receipts(): HasMany
    {
        return $this->hasMany(ProductionSubcontractReceipt::class, 'delivery_challan_id');
    }

    /** Units still at the vendor on this slip (sent − received back, whatever the outcome) */
    public function unitsAtVendor(): float
    {
        return max(0.0, round((float) $this->dispatched_wip_qty - (float) $this->received_qty, 4));
    }

    public function isAtVendor(): bool
    {
        return in_array($this->status, self::AT_VENDOR_STATUSES, true);
    }

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
