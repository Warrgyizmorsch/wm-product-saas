<?php

namespace App\Domains\Purchase\Models;

use App\Core\Database\BaseModel;
use App\Domains\Inventory\Models\Vendor;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseRfqVendor extends BaseModel
{
    protected $table = 'purchase_rfq_vendors';

    protected $fillable = [
        'tenant_id',
        'purchase_rfq_id',
        'vendor_id',
        'token',
        'delivery_date',
        'validity_date',
        'payment_type',
        'quotation_number',
        'terms_conditions',
        'attachment_path',
        'status', // Sent, Received
    ];

    protected $casts = [
        'purchase_rfq_id' => 'integer',
        'vendor_id' => 'integer',
        'delivery_date' => 'date',
        'validity_date' => 'date',
    ];

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(PurchaseRfq::class, 'purchase_rfq_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function rates(): HasMany
    {
        return $this->hasMany(PurchaseRfqVendorRate::class, 'purchase_rfq_vendor_id')->orderBy('id', 'desc');
    }
}
