<?php

namespace App\Domains\CRM\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Customer extends BaseModel
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'phone',
        'gstin',
        'status',
    ];

    public function crmAccount()
    {
        return $this->hasOne(CrmAccount::class, 'customer_id');
    }
}
