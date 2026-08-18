<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;

class HolidayCalendar extends BaseModel
{
    protected $fillable = [
        'tenant_id',
        'name',
        'holiday_date',
        'description',
        'company_id',
        'business_unit_id',
        'branch_id',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
        'holiday_date' => 'date',
    ];

    /**
     * Get the company that the holiday belongs to.
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the business unit that the holiday belongs to.
     */
    public function businessUnit()
    {
        return $this->belongsTo(BusinessUnit::class);
    }

    /**
     * Get the branch that the holiday belongs to.
     */
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Check if a given date is a holiday for an employee.
     */
    public static function isHolidayForEmployee(Employee $employee, $date): bool
    {
        $formattedDate = \Carbon\Carbon::parse($date)->toDateString();

        return self::query()
            ->where('tenant_id', $employee->tenant_id)
            ->where('holiday_date', $formattedDate)
            ->where('status', true)
            ->where(function ($q) use ($employee) {
                $q->where(function ($sub) {
                    $sub->whereNull('company_id')
                        ->whereNull('business_unit_id')
                        ->whereNull('branch_id');
                })
                ->orWhere(function ($sub) use ($employee) {
                    $sub->where('company_id', $employee->company_id)
                        ->whereNull('business_unit_id')
                        ->whereNull('branch_id');
                })
                ->orWhere(function ($sub) use ($employee) {
                    $sub->where('company_id', $employee->company_id)
                        ->where('business_unit_id', $employee->business_unit_id)
                        ->whereNull('branch_id');
                })
                ->orWhere(function ($sub) use ($employee) {
                    $sub->where('company_id', $employee->company_id)
                        ->where('business_unit_id', $employee->business_unit_id)
                        ->where('branch_id', $employee->branch_id);
                });
            })
            ->exists();
    }
}

