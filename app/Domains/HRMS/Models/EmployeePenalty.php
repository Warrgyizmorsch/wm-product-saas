<?php

namespace App\Domains\HRMS\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeePenalty extends Model
{
    protected $fillable = [
        'employee_id',
        'date',
        'rule_type',
        'penalty_amount',
        'status',
        'payroll_month',
        'remarks',
    ];

    protected $casts = [
        'date' => 'date',
        'penalty_amount' => 'decimal:2',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
