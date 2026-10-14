<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Uom;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionBomItem extends BaseModel
{
    use HasFactory;

    protected $table = 'production_bom_items';

    protected $fillable = [
        'tenant_id',
        'bom_id',
        'material_id',
        'child_bom_id',
        'replaces_item_id',
        'quantity',
        'quantity_type', // fixed, formula
        'formula',
        'uom_id',
        'material_scrap_percentage',
        'is_alternative',
        'priority',
        'sequence',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'float',
        'material_scrap_percentage' => 'float',
        'is_alternative' => 'boolean',
        'priority' => 'integer',
        'sequence' => 'integer',
        'child_bom_id' => 'integer',
        'replaces_item_id' => 'integer',
    ];

    public function isFormula(): bool
    {
        return $this->quantity_type === 'formula' && !empty($this->formula);
    }

    public function isFixed(): bool
    {
        return !$this->isFormula();
    }

    public function isPrimary(): bool
    {
        return !$this->is_alternative;
    }

    public function isAlternative(): bool
    {
        return (bool) $this->is_alternative;
    }

    public function scopePrimary($query)
    {
        return $query->where('is_alternative', false);
    }

    public function bom(): BelongsTo
    {
        return $this->belongsTo(ProductionBom::class, 'bom_id');
    }

    public function childBom(): BelongsTo
    {
        return $this->belongsTo(ProductionBom::class, 'child_bom_id');
    }

    public function primaryItem(): BelongsTo
    {
        return $this->belongsTo(ProductionBomItem::class, 'replaces_item_id');
    }

    public function alternatives(): HasMany
    {
        return $this->hasMany(ProductionBomItem::class, 'replaces_item_id')
            ->orderBy('priority', 'asc')
            ->orderBy('sequence', 'asc')
            ->orderBy('id', 'asc');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'material_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'material_id');
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class, 'uom_id');
    }
}
