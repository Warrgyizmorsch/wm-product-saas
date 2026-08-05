<?php

namespace App\Domains\Purchase\Models;

use App\Core\Database\BaseModel;
use App\Domains\Inventory\Models\Vendor;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LandedCostExpense extends BaseModel
{
    use HasFactory;

    protected $table = 'landed_cost_expenses';

    protected $fillable = [
        'tenant_id',
        'landed_cost_voucher_id',
        'cost_head',
        'vendor_id',
        'amount',
        'allocation_basis',
        'description',
    ];

    protected $casts = [
        'amount' => 'float',
    ];

    public function landedCostVoucher(): BelongsTo
    {
        return $this->belongsTo(LandedCostVoucher::class, 'landed_cost_voucher_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }
}
