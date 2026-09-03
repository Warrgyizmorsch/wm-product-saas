<?php

namespace App\Domains\Purchase\Models;

use App\Core\Database\BaseModel;
use App\Domains\Inventory\Models\Vendor;
use App\Domains\Inventory\Models\Warehouse;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToCompany;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class GoodsReceiptNote extends BaseModel
{
    use BelongsToCompany, BelongsToBranch, HasFactory, SoftDeletes;

    protected $table = 'goods_receipt_notes';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
        'grn_number',
        'purchase_order_id',
        'production_order_id',
        'vendor_id',
        'warehouse_id',
        'received_date',
        'challan_number',
        'challan_date',
        'vehicle_number',
        'transporter_id',
        'transporter_name',
        'lr_number',
        'status', // Draft, Approved, Cancelled
        'notes',
        'approved_by',
        'approved_at',
        'created_by',
    ];

    protected $casts = [
        'received_date' => 'date',
        'challan_date' => 'date',
        'approved_at' => 'datetime',
        'production_order_id' => 'integer',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Production\Models\ProductionOrder::class, 'production_order_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function transporter(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Platform\Models\Transporter::class, 'transporter_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(GoodsReceiptNoteItem::class, 'goods_receipt_note_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function vendorBill(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(VendorBill::class, 'goods_receipt_note_id')->where('status', '!=', 'Cancelled')->latestOfMany();
    }

    public function vendorBills(): HasMany
    {
        return $this->hasMany(VendorBill::class, 'goods_receipt_note_id');
    }

    public function landedCostReceipts(): HasMany
    {
        return $this->hasMany(LandedCostReceipt::class, 'goods_receipt_note_id');
    }

    public function getBillingStatusAttribute(): string
    {
        $bill = $this->vendorBill;
        if (!$bill) {
            return 'Pending Bill';
        }

        if ($bill->status === 'Paid') {
            return 'Paid';
        }

        if ($bill->status === 'Partially Paid') {
            return 'Partially Paid';
        }

        return 'Billed';
    }
}
