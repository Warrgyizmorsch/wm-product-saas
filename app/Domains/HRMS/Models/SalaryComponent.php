<?php

namespace App\Domains\HRMS\Models;

use Illuminate\Database\Eloquent\Model;

class SalaryComponent extends Model
{
    protected $fillable = [
        'organization_id',
        'company_id',
        'name',
        'code',
        'type',
        'calculation_type',
        'default_value',
        'description',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    /**
     * Get the organization that owns the salary component.
     */
    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the company that owns the salary component.
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
