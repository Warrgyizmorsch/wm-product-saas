<?php

namespace App\Domains\HRMS\Models;

use Illuminate\Database\Eloquent\Model;

class SalaryComponent extends Model
{
    protected $fillable = [
        'organization_id',
        'company_id',
        'pay_group_id',
        'name',
        'code',
        'type',
        'calculation_type',
        'default_value',
        'description',
        'status',
        'is_adhoc',
    ];

    protected $casts = [
        'status' => 'boolean',
        'is_adhoc' => 'boolean',
    ];

    public function scopeRecurring($query)
    {
        return $query->where('is_adhoc', false);
    }

    public function scopeAdhoc($query)
    {
        return $query->where('is_adhoc', true);
    }

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

    /**
     * Get the pay group that owns the salary component.
     */
    public function payGroup()
    {
        return $this->belongsTo(PayGroup::class, 'pay_group_id');
    }
}
