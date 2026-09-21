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
        'approval_levels',
        'current_approval_level',
        'l1_approved_by',
        'l1_approved_at',
        'l2_approved_by',
        'l2_approved_at',
    ];

    protected $casts = [
        'start_date'             => 'date',
        'end_date'               => 'date',
        'estimated_budget'       => 'decimal:2',
        'approval_levels'        => 'integer',
        'current_approval_level' => 'integer',
        'l1_approved_at'         => 'datetime',
        'l2_approved_at'         => 'datetime',
    ];

    /**
     * Request belongs to an Employee.
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
