<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProductionAlertConfiguration extends BaseModel
{
    use HasFactory;

    protected $table = 'production_alert_configurations';

    protected $fillable = [
        'tenant_id',
        'alert_type',
        'threshold',
        'severity',
        'active',
    ];

    protected $casts = [
        'threshold' => 'float',
        'active'    => 'boolean',
    ];
}
