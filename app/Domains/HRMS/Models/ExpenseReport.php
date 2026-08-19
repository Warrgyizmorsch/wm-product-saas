<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;

class ExpenseReport extends BaseModel
{
    protected $table = 'expense_reports';

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'travel_request_id',
        'title',
        'total_amount',
        'advance_adjusted',
        'net_reimbursement',
        'status',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'advance_adjusted' => 'decimal:2',
        'net_reimbursement' => 'decimal:2',
    ];

    /**
     * Report belongs to an Employee.
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    /**
     * Report optionally belongs to a Travel Request.
     */
    public function travelRequest()
    {
        return $this->belongsTo(TravelRequest::class, 'travel_request_id');
    }

    /**
     * Report has one associated Cash Advance that is settled with it.
     */
    public function cashAdvance()
    {
        return $this->hasOne(CashAdvance::class, 'expense_report_id');
    }

    /**
     * Report has many claims.
     */
    public function claims()
    {
        return $this->hasMany(ExpenseClaim::class, 'expense_report_id');
    }
}
