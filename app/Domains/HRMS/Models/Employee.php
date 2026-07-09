<?php

namespace App\Domains\HRMS\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    protected $fillable = [

        'company_id',
        'business_unit_id',
        'branch_id',
        'department_id',
        'designation_id',
        'pay_group_id',
        'salary_structure_id',
        'leave_plan_id',
        'attendance_penalty_id',
        'reporting_manager_id',
        'shift_id',

        'employee_id',
        'full_name',
        'nick_name',
        'blood_group',
        'employee_stage',
        'job_title',
        'role',
        'employment_type',
        'date_of_joining',
        'date_of_birth',
        'probation_end_date',
        'confirmation_date',
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
        'office_email',

        'experience',
        'source_of_hire',
        'skill_set',
        'current_salary',
        'qualification',
        'bank_name',
        'account_number',
        'ifsc_code',
        'emergency_contact_name',
        'emergency_contact_number',
        'emergency_contact_relation',

        'status'
    ];

    protected $casts = [
        'date_of_joining' => 'date',
        'date_of_birth' => 'date',
        'probation_end_date' => 'date',
        'confirmation_date' => 'date',
        'experience' => 'decimal:2',
        'current_salary' => 'decimal:2',
        'status' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function businessUnit(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    public function payGroup(): BelongsTo
    {
        return $this->belongsTo(PayGroup::class, 'pay_group_id');
    }

    public function salaryStructure(): BelongsTo
    {
        return $this->belongsTo(SalaryStructure::class, 'salary_structure_id');
    }

    public function leavePlan(): BelongsTo
    {
        return $this->belongsTo(LeavePlan::class, 'leave_plan_id');
    }

    public function attendancePenalty(): BelongsTo
    {
        return $this->belongsTo(AttendancePenalty::class, 'attendance_penalty_id');
    }

    public function reportingManager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reporting_manager_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\Production\Models\ProductionShift::class, 'shift_id');
    }

    public function managedBranches(): HasMany
    {
        return $this->hasMany(Branch::class, 'manager_employee_id');
    }

    public function headedBusinessUnits(): HasMany
    {
        return $this->hasMany(BusinessUnit::class, 'head_employee_id');
    }

    public function headedDepartments(): HasMany
    {
        return $this->hasMany(Department::class, 'head_employee_id');
    }

    public function getFirstNameAttribute(): string
    {
        return (string) str($this->full_name)->before(' ');
    }

    public function getLastNameAttribute(): string
    {
        $parts = preg_split('/\s+/', trim((string) $this->full_name)) ?: [];

        if (count($parts) <= 1) {
            return '';
        }

        array_shift($parts);

        return implode(' ', $parts);
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->full_name ?: trim($this->first_name . ' ' . $this->last_name);
    }
}
