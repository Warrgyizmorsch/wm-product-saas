<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;

class PayrollHold extends BaseModel
{
    protected $fillable = [
        'employee_id',
        'payroll_month',
        'status',
        'release_in_month',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
