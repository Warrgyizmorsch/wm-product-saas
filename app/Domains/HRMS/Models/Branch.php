<?php

namespace App\Domains\HRMS\Models;

use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    protected $fillable = [
        'company_id',
        'business_unit_id',
        'name',
        'code',
        'manager_employee_id',
        'phone',
        'email',
        'address',
        'city',
        'state',
        'country',
        'postal_code',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    /**
     * Branch belongs to a Company.
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Branch belongs to a Business Unit.
     */
    public function businessUnit()
    {
        return $this->belongsTo(BusinessUnit::class);
    }

    /**
     * Branch Manager.
     */
    public function manager()
    {
        return $this->belongsTo(Employee::class, 'manager_employee_id');
    }

    public function departments()
    {
        return $this->hasMany(Department::class);
    }
}
