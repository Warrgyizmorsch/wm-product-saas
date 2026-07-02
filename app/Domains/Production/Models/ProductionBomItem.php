<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Uom;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionBomItem extends BaseModel
{
    use HasFactory;

    protected $table = 'production_bom_items';

    protected $fillable = [
        'tenant_id',
        'bom_id',
        'material_id',
        'child_bom_id',
        'quantity',
        'uom_id',
        'material_scrap_percentage',
        'is_alternative',
        'alternative_group',
        'priority',
        'sequence',
        'effective_from',
        'effective_to',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'float',
        'material_scrap_percentage' => 'float',
        'is_alternative' => 'boolean',
        'priority' => 'integer',
        'sequence' => 'integer',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'child_bom_id' => 'integer',
    ];

    public function bom(): BelongsTo
    {
        return $this->belongsTo(ProductionBom::class, 'bom_id');
    }

    public function childBom(): BelongsTo
    {
        return $this->belongsTo(ProductionBom::class, 'child_bom_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'material_id');
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class, 'uom_id');
    }
}
