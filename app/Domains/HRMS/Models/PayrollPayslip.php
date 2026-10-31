<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;

class PayrollPayslip extends BaseModel
{
    protected $fillable = [
        'tenant_id',
        'company_id',
        'payroll_run_id',
        'employee_id',
        'payroll_month',
        'status',
        'gross_earnings',
        'total_deductions',
        'net_payout',
        'lop_days',
        'paid_days',
        'summary_data',
        'items_data',
    ];

    protected $casts = [
        'gross_earnings'   => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'net_payout'       => 'decimal:2',
        'lop_days'         => 'decimal:2',
        'paid_days'        => 'decimal:2',
        'summary_data'     => 'array',
        'items_data'       => 'array',
    ];

    public function payrollRun()
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id')->withTrashed();
    }

    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }
}

