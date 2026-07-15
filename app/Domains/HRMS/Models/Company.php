<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;

class Company extends BaseModel
{
    protected $fillable = [
        'organization_id',
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

    /**
     * Company belongs to an Organization.
     */
    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function businessUnits()
    {
        return $this->hasMany(BusinessUnit::class);
    }
}
