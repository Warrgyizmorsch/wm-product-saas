<?php

namespace App\Domains\Inventory\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Warehouse extends BaseModel
{
    use HasFactory;

    protected $table = 'warehouses';

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'status',
        'address',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];
}
