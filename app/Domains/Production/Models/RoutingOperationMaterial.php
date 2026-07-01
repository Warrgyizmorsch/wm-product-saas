<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Uom;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoutingOperationMaterial extends BaseModel
{
    use HasFactory;

    protected $table = 'production_routing_operation_materials';

    protected $fillable = [
        'tenant_id',
        'routing_operation_id',
        'material_id',
        'quantity',
        'uom_id',
        'consumption_type',
    ];

    protected $casts = [
        'quantity' => 'float',
    ];

    public function routingOperation(): BelongsTo
    {
        return $this->belongsTo(RoutingOperation::class, 'routing_operation_id');
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
