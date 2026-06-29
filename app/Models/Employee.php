<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    protected $fillable = [

        'company_id',
        'business_unit_id',
        'branch_id',
        'department_id',
        'designation_id',

        'employee_id',
        'full_name',
        'nick_name',
        'blood_group',
        'employee_stage',
        'job_title',
        'role',
        'employment_type',
        'date_of_joining',
        'office',
        'gender',
        'marital_status',
        'diet_preference',
        'aadhaar_card_number',
        'pan_card_number',
        'photo',

        'present_address',
        'permanent_address',
        'city',
        'postal_code',
        'personal_mobile_number',
        'home_phone',
        'personal_email',

        'experience',
        'source_of_hire',
        'skill_set',
        'current_salary',
        'qualification',

        'status'
    ];

    protected $casts = [
        'date_of_joining' => 'date',
        'experience' => 'decimal:2',
        'current_salary' => 'decimal:2',
        'status' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function businessUnit()
    {
        return $this->belongsTo(BusinessUnit::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function designation()
    {
        return $this->belongsTo(Designation::class);
    }
}
