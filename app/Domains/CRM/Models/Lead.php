<?php

namespace App\Domains\CRM\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends BaseModel
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'call_date',
        'company_name',
        'contact_person',
        'email',
        'phone',
        'requirement',
        'expected_amount',
        'expected_sale_date',
        'source',
        'priority',
        'segment',
    ];

    protected $casts = [
        'call_date' => 'datetime',
        'expected_sale_date' => 'date',
        'expected_amount' => 'decimal:2',
    ];
}
