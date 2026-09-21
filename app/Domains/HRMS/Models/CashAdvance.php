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
        'approval_levels',
        'current_approval_level',
        'l1_approved_by',
        'l1_approved_at',
        'l2_approved_by',
        'l2_approved_at',
    ];

    protected $casts = [
        'amount'                 => 'decimal:2',
        'approved_amount'        => 'decimal:2',
        'approval_levels'        => 'integer',
        'current_approval_level' => 'integer',
        'l1_approved_at'         => 'datetime',
        'l2_approved_at'         => 'datetime',
    ];

    /**
     * Advance belongs to an Employee.
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /**
     * Level 1 Approver user.
     */
    public function l1Approver()
    {
        return $this->belongsTo(\App\Models\User::class, 'l1_approved_by');
    }

    /**
     * Level 2 Approver user.
     */
    public function l2Approver()
    {
        return $this->belongsTo(\App\Models\User::class, 'l2_approved_by');
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
