<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Routing extends BaseModel
{
    use HasFactory;

    protected $table = 'routings';

    protected $fillable = [
        'tenant_id',
        'name',
        'status',
    ];

    public function boms(): HasMany
    {
        return $this->hasMany(ProductionBom::class, 'routing_id');
    }
}
