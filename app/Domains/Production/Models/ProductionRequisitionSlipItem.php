<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Inventory\Models\Warehouse;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionRequisitionSlipItem extends BaseModel
{
    protected $table = 'production_requisition_slip_items';

    protected $fillable = [
        'tenant_id',
        'production_requisition_slip_id',
        'product_id',
        'warehouse_id',
        'quantity_planned',
        'quantity_reserved',
        'quantity_issued',
        'uom_id',
    ];

    protected $casts = [
        'quantity_planned' => 'float',
        'quantity_reserved' => 'float',
        'quantity_issued' => 'float',
    ];

    public function slip(): BelongsTo
    {
        return $this->belongsTo(ProductionRequisitionSlip::class, 'production_requisition_slip_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class, 'uom_id');
    }
}
