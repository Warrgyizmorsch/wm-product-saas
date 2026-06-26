<?php

namespace App\Domains\Inventory\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected $table = 'products';

    protected $fillable = [
        'tenant_id',
        'name',
        'sku',
        'type', // finished_good, raw_material, component
        'status',
        'unit_cost',
    ];

    protected $casts = [
        'unit_cost' => 'float',
    ];
}
