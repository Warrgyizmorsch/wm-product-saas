<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Uom;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoutingOperationMaterial extends BaseModel
{
    use HasFactory;

    protected $table = 'production_routing_operation_materials';

    protected $fillable = [
        'tenant_id',
        'bom_id',
        'bom_item_id',
        'routing_operation_id',
        'material_id',
        'child_bom_id',
        'quantity',
        'quantity_type',
        'formula',
        'uom_id',
        'is_alternative',
        'priority',
        'replaces_routing_material_id',
        'material_scrap_percentage',
        'consumption_type',
        'sequence',
        'notes',
    ];

    protected $casts = [
        'bom_id' => 'integer',
        'bom_item_id' => 'integer',
        'routing_operation_id' => 'integer',
        'material_id' => 'integer',
        'child_bom_id' => 'integer',
        'quantity' => 'float',
        'material_scrap_percentage' => 'float',
        'is_alternative' => 'boolean',
        'priority' => 'integer',
        'replaces_routing_material_id' => 'integer',
        'sequence' => 'integer',
    ];

    public function bom(): BelongsTo
    {
        return $this->belongsTo(ProductionBom::class, 'bom_id');
    }

    public function bomItem(): BelongsTo
    {
        return $this->belongsTo(ProductionBomItem::class, 'bom_item_id');
    }

    public function routingOperation(): BelongsTo
    {
        return $this->belongsTo(RoutingOperation::class, 'routing_operation_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'material_id');
    }

    public function childBom(): BelongsTo
    {
        return $this->belongsTo(ProductionBom::class, 'child_bom_id');
    }

    public function replacesMaterial(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaces_routing_material_id');
    }

    public function replacements(): HasMany
    {
        return $this->hasMany(self::class, 'replaces_routing_material_id')
            ->orderBy('priority', 'asc')
            ->orderBy('sequence', 'asc');
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class, 'uom_id');
    }
}
