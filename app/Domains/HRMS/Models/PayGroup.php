<?php

namespace App\Domains\HRMS\Models;

use Illuminate\Database\Eloquent\Model;

class PayGroup extends Model
{
    protected $fillable = [
        'organization_id',
        'company_id',
        'name',
        'description',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    /**
     * Get the organization that owns the pay group.
     */
    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Get the company that owns the pay group.
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get all components linked to this pay group.
     */
    public function components()
    {
        return $this->hasMany(SalaryComponent::class, 'pay_group_id');
    }

    /**
     * Get all structures linked to this pay group.
     */
    public function structures()
    {
        return $this->hasMany(SalaryStructure::class, 'pay_group_id');
    }
}
