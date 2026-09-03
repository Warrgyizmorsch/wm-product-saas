<?php

namespace App\Domains\Purchase\Models;

use App\Core\Database\BaseModel;
use App\Domains\Inventory\Models\Product;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseRfqVendorRate extends BaseModel
{
    use BelongsToCompany, BelongsToBranch;

    protected $table = 'purchase_rfq_vendor_rates';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
        'purchase_rfq_vendor_id',
        'product_id',
        'rate',
        'quantity',
        'delivery_date',
        'validity_date',
    ];

    protected $casts = [
        'purchase_rfq_vendor_id' => 'integer',
        'product_id' => 'integer',
        'rate' => 'decimal:2',
        'quantity' => 'decimal:4',
        'delivery_date' => 'date',
        'validity_date' => 'date',
    ];

    public function rfqVendor(): BelongsTo
    {
        return $this->belongsTo(PurchaseRfqVendor::class, 'purchase_rfq_vendor_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
