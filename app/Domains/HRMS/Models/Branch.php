<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;

class Branch extends BaseModel
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
        'timezone',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    /**
     * Get the effective timezone: Branch override -> Company default -> App fallback
     */
    public function getTimezoneAttribute(?string $value): string
    {
        if (!empty($value)) {
            return $value;
        }

        if ($this->relationLoaded('company') && !empty($this->company?->timezone)) {
            return $this->company->timezone;
        }

        return $this->company?->timezone ?? config('app.timezone', 'Asia/Kolkata');
    }

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
