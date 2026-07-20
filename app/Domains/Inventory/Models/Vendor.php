<?php

namespace App\Domains\Inventory\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Vendor extends BaseModel
{
    use HasFactory;

    protected $table = 'vendors';

    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'email',
        'phone',
        'address',
        'status',
    ];
}
