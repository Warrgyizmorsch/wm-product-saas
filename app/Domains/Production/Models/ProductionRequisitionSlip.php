<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionRequisitionSlip extends BaseModel
{
    use BelongsToCompany, BelongsToBranch;

    protected $table = 'production_requisition_slips';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
        'production_order_id',
        'requisition_number',
        'status',
        'requested_by',
        'requisition_date',
        'notes',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProductionRequisitionSlipItem::class, 'production_requisition_slip_id');
    }

    public function purchaseRequisitions(): HasMany
    {
        return $this->hasMany(\App\Domains\Purchase\Models\PurchaseRequisition::class, 'source_id')
            ->where('source_type', 'material_request');
    }
}
