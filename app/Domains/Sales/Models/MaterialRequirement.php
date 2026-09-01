<?php

namespace App\Domains\Sales\Models;

use App\Core\Database\BaseModel;
use App\Models\Concerns\BelongsToBranch;
use App\Models\Concerns\BelongsToCompany;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaterialRequirement extends BaseModel
{
    use BelongsToTenant, BelongsToCompany, BelongsToBranch, HasFactory, SoftDeletes;

    protected $table = 'material_requirements';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
        'sales_order_id',
        'requirement_number',
        'requirement_date',
        'status',
        'carrier',
        'tracking_number',
        'notes',
    ];

    protected $casts = [
        'requirement_date' => 'date',
    ];

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(MaterialRequirementItem::class, 'material_requirement_id');
    }

    public function getTotalOrderedQtyAttribute(): float
    {
        return (float) $this->items->sum(fn($i) => (float)($i->quantity_ordered > 0 ? $i->quantity_ordered : $i->quantity));
    }

    public function getTotalReservedQtyAttribute(): float
    {
        return (float) $this->items->sum('quantity_reserved');
    }

    public function getTotalDispatchedQtyAttribute(): float
    {
        return (float) $this->items->sum('dispatched_qty');
    }

    public function getTotalPendingQtyAttribute(): float
    {
        return max(0.0, $this->total_ordered_qty - $this->total_dispatched_qty - $this->total_reserved_qty);
    }

    public function getFulfillmentRateAttribute(): float
    {
        $ordered = $this->total_ordered_qty;
        if ($ordered <= 0) return 0.0;
        return round((($this->total_dispatched_qty + $this->total_reserved_qty) / $ordered) * 100, 1);
    }
}
