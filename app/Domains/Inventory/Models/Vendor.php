<?php

namespace App\Domains\Inventory\Models;

use App\Core\Database\BaseModel;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Concerns\NormalizesTaxIdentity;

class Vendor extends BaseModel
{
    use HasFactory, BelongsToCompany, BelongsToBranch, NormalizesTaxIdentity;

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
        'opening_balance_type',
        'opening_bills',
        'status',
        // TDS (see App\Domains\Accounting\Services\Tds\TdsCalculator)
        'tds_applicable',
        'tds_section_id',
        'deductee_type',
        'ldc_number',
        'ldc_rate',
        'ldc_valid_from',
        'ldc_valid_to',
        'ldc_limit',
    ];

    protected $casts = [
        'opening_bills' => 'array',
        'tds_applicable' => 'boolean',
        'ldc_rate' => 'float',
        'ldc_limit' => 'float',
        'ldc_valid_from' => 'date',
        'ldc_valid_to' => 'date',
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

    /**
     * Opening balance as this party's statements show it: an amount owed to the vendor (credit) is positive; an advance paid (debit) is negative.
     */
    public function signedOpeningBalance(): float
    {
        $amount = (float) ($this->opening_balance ?? 0);

        return ($this->opening_balance_type ?: 'credit') === 'credit' ? $amount : -$amount;
    }
}
