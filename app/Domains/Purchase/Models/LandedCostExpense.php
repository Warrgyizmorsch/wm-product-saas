<?php

namespace App\Domains\Purchase\Models;

use App\Core\Database\BaseModel;
use App\Domains\Inventory\Models\Vendor;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LandedCostExpense extends BaseModel
{
    use BelongsToCompany, BelongsToBranch, HasFactory;

    protected $table = 'landed_cost_expenses';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
        'landed_cost_voucher_id',
        'cost_head',
        'vendor_id',
        'amount',
        'tax_rate',
        'gst_type',
        'is_rcm',
        'tax_amount',
        'total_with_tax',
        'vendor_bill_id',
        'allocation_basis',
        'description',
    ];

    protected $casts = [
        'amount'         => 'float',
        'tax_rate'       => 'float',
        'is_rcm'         => 'boolean',
        'tax_amount'     => 'float',
        'total_with_tax' => 'float',
    ];

    public function landedCostVoucher(): BelongsTo
    {
        return $this->belongsTo(LandedCostVoucher::class, 'landed_cost_voucher_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function vendorBill(): BelongsTo
    {
        return $this->belongsTo(VendorBill::class, 'vendor_bill_id');
    }
}
