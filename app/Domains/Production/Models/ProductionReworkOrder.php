<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductionReworkOrder extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected $table = 'production_rework_orders';

    protected $fillable = [
        'tenant_id',
        'rework_number',
        'ncr_id',
        'original_production_order_id',
        'status',
        'cost_estimate',
        'actual_cost',
        'labor_hours_actual',
        'machine_hours_actual',
    ];

    protected $casts = [
        'cost_estimate'        => 'float',
        'actual_cost'          => 'float',
        'labor_hours_actual'   => 'float',
        'machine_hours_actual' => 'float',
    ];

    public function ncr(): BelongsTo
    {
        return $this->belongsTo(ProductionNcr::class, 'ncr_id');
    }

    public function originalOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'original_production_order_id');
    }

    public function operations(): HasMany
    {
        return $this->hasMany(ProductionReworkOperation::class, 'rework_order_id');
    }
}
