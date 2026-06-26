<?php

namespace App\Domains\Inventory\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Uom extends BaseModel
{
    use HasFactory;

    protected $table = 'uoms';

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
    ];
}
