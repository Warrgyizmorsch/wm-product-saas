<?php

namespace App\Domains\HRMS\Models;

use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    protected $fillable = [
        'company_id',
        'business_unit_id',
        'branch_id',
        'name',
        'code',
        'head_employee_id',
        'description',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    /**
     * Department belongs to a Company (when branch/bu are skipped).
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Department belongs to a Business Unit (when branch is skipped).
     */
    public function businessUnit()
    {
        return $this->belongsTo(BusinessUnit::class);
    }

    /**
     * Department belongs to a Branch.
     */
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Department Head.
     */
    public function head()
    {
        return $this->belongsTo(Employee::class, 'head_employee_id');
    }

    public function designations()
    {
        return $this->hasMany(Designation::class);
    }
}
