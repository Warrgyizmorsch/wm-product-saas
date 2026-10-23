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

    public const SOURCE_TYPE_PRODUCTION_ORDER       = 'production_order';
    public const SOURCE_TYPE_MAINTENANCE_WORK_ORDER = 'maintenance_work_order';
    public const SOURCE_TYPE_REWORK_ORDER           = 'rework_order';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
        'production_order_id',
        'maintenance_work_order_id',
        'rework_order_id',
        'source_type',
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

    public function maintenanceWorkOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionMaintenanceWorkOrder::class, 'maintenance_work_order_id');
    }

    public function reworkOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionReworkOrder::class, 'rework_order_id');
    }

    public function isMaintenance(): bool
    {
        return $this->source_type === self::SOURCE_TYPE_MAINTENANCE_WORK_ORDER
            || $this->maintenance_work_order_id !== null;
    }

    public function isRework(): bool
    {
        return $this->source_type === self::SOURCE_TYPE_REWORK_ORDER
            || $this->rework_order_id !== null;
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
