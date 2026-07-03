<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Uom;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionPlanRequirement extends BaseModel
{
    protected $table = 'production_plan_requirements';

    protected $fillable = [
        'tenant_id',
        'production_plan_id',
        'bom_item_id',
        'product_id',
        'bom_level',
        'required_quantity',
        'available_quantity',
        'reserved_quantity',
        'shortage_quantity',
        'uom_id',
        'source_item_id',
        'status',
    ];

    protected $casts = [
        'bom_level'          => 'integer',
        'required_quantity'  => 'float',
        'available_quantity' => 'float',
        'reserved_quantity'  => 'float',
        'shortage_quantity'  => 'float',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ProductionPlan::class, 'production_plan_id');
    }

    public function bomItem(): BelongsTo
    {
        return $this->belongsTo(ProductionBomItem::class, 'bom_item_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class, 'uom_id');
    }

    public function sourceItem(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'source_item_id');
    }
}
