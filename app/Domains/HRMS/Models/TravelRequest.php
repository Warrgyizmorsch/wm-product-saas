<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;

class TravelRequest extends BaseModel
{
    protected $table = 'travel_requests';

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'purpose',
        'destination',
        'start_date',
        'end_date',
        'estimated_budget',
        'approved_budget',
        'status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'estimated_budget' => 'decimal:2',
    ];

    /**
     * Request belongs to an Employee.
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /**
     * Request has many Cash Advances.
     */
    public function cashAdvances()
    {
        return $this->hasMany(CashAdvance::class, 'travel_request_id');
    }

    /**
     * Request has many Expense Reports.
     */
    public function expenseReports()
    {
        return $this->hasMany(ExpenseReport::class, 'travel_request_id');
    }
}
