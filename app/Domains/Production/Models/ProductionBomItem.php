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
        'quantity',
        'quantity_type', // fixed, formula
        'formula',
        'uom_id',
        'allocation_role', // primary, alternative, mixed
        'material_scrap_percentage',
        'is_alternative',
        'replaces_item_id',
        'priority',
        'sequence',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'float',
        'material_scrap_percentage' => 'float',
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
        return $this->allocation_role === 'primary';
    }

    public function isAlternative(): bool
    {
        return $this->allocation_role === 'alternative';
    }

    public function isMixed(): bool
    {
        return $this->allocation_role === 'mixed';
    }

    public function scopePrimary($query)
    {
        return $query->where('allocation_role', 'primary');
    }

    public function bom(): BelongsTo
    {
        return $this->belongsTo(ProductionBom::class, 'bom_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(RoutingOperationMaterial::class, 'bom_item_id');
    }

    public function getAllocationIdsAttribute(): array
    {
        return $this->allocations->pluck('id')->toArray();
    }

    public function getRoutingOperationIdAttribute()
    {
        if (!empty($this->attributes['routing_operation_id'])) {
            return (int) $this->attributes['routing_operation_id'];
        }
        return $this->allocations()->value('routing_operation_id');
    }

    public function getChildBomIdAttribute()
    {
        if (!empty($this->attributes['child_bom_id'])) {
            return (int) $this->attributes['child_bom_id'];
        }
        return $this->allocations()->whereNotNull('child_bom_id')->value('child_bom_id');
    }

    protected ?int $runtimePriority = null;
    protected ?int $runtimeSequence = null;

    public function setPriorityAttribute($value): void
    {
        $this->runtimePriority = $value !== null ? (int) $value : null;
    }

    public function getPriorityAttribute()
    {
        if ($this->runtimePriority !== null) {
            return $this->runtimePriority;
        }
        return $this->allocations()->value('priority');
    }

    public function setSequenceAttribute($value): void
    {
        $this->runtimeSequence = $value !== null ? (int) $value : null;
    }

    public function getSequenceAttribute()
    {
        if ($this->runtimeSequence !== null) {
            return $this->runtimeSequence;
        }
        return $this->allocations()->value('sequence');
    }

    public function setIsAlternativeAttribute($value): void
    {
        $this->attributes['allocation_role'] = (bool) $value ? 'alternative' : 'primary';
    }

    public function getIsAlternativeAttribute(): bool
    {
        if (!empty($this->attributes['allocation_role'])) {
            return $this->attributes['allocation_role'] === 'alternative';
        }
        return false;
    }

    protected ?int $runtimeReplacesItemId = null;

    public function setReplacesItemIdAttribute($value): void
    {
        $this->runtimeReplacesItemId = $value ? (int) $value : null;
    }

    public function getReplacesItemIdAttribute()
    {
        if ($this->runtimeReplacesItemId !== null) {
            return $this->runtimeReplacesItemId;
        }
        $targetOpMatId = $this->allocations()->whereNotNull('replaces_routing_material_id')->value('replaces_routing_material_id');
        if ($targetOpMatId) {
            return RoutingOperationMaterial::where('id', $targetOpMatId)->value('bom_item_id');
        }
        if ($this->allocation_role === 'alternative') {
            return ProductionBomItem::where('bom_id', $this->bom_id)
                ->where('allocation_role', 'primary')
                ->value('id');
        }
        return null;
    }

    public function getPrimaryItemAttribute(): ?ProductionBomItem
    {
        $replacesItemId = $this->replaces_item_id;
        return $replacesItemId ? ProductionBomItem::find($replacesItemId) : null;
    }

    public function getAlternativesAttribute()
    {
        $opMatIds = $this->allocations()->pluck('id');
        if ($opMatIds->isNotEmpty()) {
            $altBomItemIds = RoutingOperationMaterial::whereIn('replaces_routing_material_id', $opMatIds)
                ->whereNotNull('bom_item_id')
                ->pluck('bom_item_id')
                ->unique();

            if ($altBomItemIds->isNotEmpty()) {
                return ProductionBomItem::whereIn('id', $altBomItemIds)->get();
            }
        }

        return ProductionBomItem::where('bom_id', $this->bom_id)
            ->where('allocation_role', 'alternative')
            ->where('id', '!=', $this->id)
            ->get();
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
