<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;

class EmployeeAdhocComponent extends BaseModel
{
    protected $table = 'employee_adhoc_components';

    protected $fillable = [
        'employee_id',
        'salary_component_id',
        'amount',
        'payroll_month',
        'status',
        'remarks'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function component()
    {
        return $this->belongsTo(SalaryComponent::class, 'salary_component_id');
    }
}
