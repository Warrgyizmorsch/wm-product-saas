<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProductionKpiTarget extends BaseModel
{
    use HasFactory;

    protected $table = 'production_kpi_targets';

    protected $fillable = [
        'tenant_id',
        'kpi_name',
        'target_value',
    ];

    protected $casts = [
        'target_value' => 'float',
    ];
}
