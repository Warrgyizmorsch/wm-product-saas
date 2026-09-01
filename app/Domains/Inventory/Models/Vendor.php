<?php

namespace App\Domains\Inventory\Models;

use App\Core\Database\BaseModel;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vendor extends BaseModel
{
    use HasFactory, BelongsToCompany, BelongsToBranch;

    protected $table = 'vendors';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
        'name',
        'company_name',
        'code',
        'email',
        'phone',
        'address',
        'billing_address',
        'shipping_address',
        'gstin',
        'pan',
        'bank_name',
        'account_number',
        'ifsc_code',
        'payment_terms',
        'opening_balance',
        'status',
    ];

    public function operations(): HasMany
    {
        return $this->hasMany(\App\Domains\Production\Models\ProductionOrderOperation::class, 'vendor_id');
    }

    public function bills(): HasMany
    {
        return $this->hasMany(\App\Domains\Purchase\Models\VendorBill::class, 'vendor_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(\App\Domains\Purchase\Models\VendorPayment::class, 'vendor_id');
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(\App\Domains\Purchase\Models\PurchaseOrder::class, 'vendor_id');
    }
}
