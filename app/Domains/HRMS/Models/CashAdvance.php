<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;

class CashAdvance extends BaseModel
{
    protected $table = 'cash_advances';

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'travel_request_id',
        'expense_report_id',
        'amount',
        'approved_amount',
        'purpose',
        'status',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'approved_amount' => 'decimal:2',
    ];

    /**
     * Advance belongs to an Employee.
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /**
     * Advance optionally belongs to a Travel Request.
     */
    public function travelRequest()
    {
        return $this->belongsTo(TravelRequest::class, 'travel_request_id');
    }

    /**
     * Advance optionally belongs to an Expense Report (during settlement adjustment).
     */
    public function expenseReport()
    {
        return $this->belongsTo(ExpenseReport::class, 'expense_report_id');
    }
}
