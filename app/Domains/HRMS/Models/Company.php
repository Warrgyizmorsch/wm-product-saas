<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;

class Company extends BaseModel
{
    protected $fillable = [
        'company_name',
        'legal_name',
        'gst_number',
        'pan_number',
        'cin_number',
        'registration_number',
        'email',
        'phone',
        'website',
        'logo',
        'address',
        'city',
        'state',
        'country',
        'postal_code',
        'currency',
        'timezone',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];


    public function businessUnits()
    {
        return $this->hasMany(BusinessUnit::class);
    }
}
