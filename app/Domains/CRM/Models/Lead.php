<?php

namespace App\Domains\CRM\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

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
        'industry_type',
        'country',
        'state',
        'city',
        'address',
        'product',
    ];

    protected $casts = [
        'call_date' => 'datetime',
        'expected_sale_date' => 'date',
        'expected_amount' => 'decimal:2',
    ];
}
