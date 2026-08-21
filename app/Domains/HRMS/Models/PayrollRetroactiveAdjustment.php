<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;

class PayrollRetroactiveAdjustment extends BaseModel
{
    protected $table = 'payroll_retroactive_adjustments';

    protected $fillable = [
        'tenant_id',
        'employee_id',
        'target_payroll_month',
        'reversal_days',
        'amount_reversal',
        'status',
    ];

    protected $casts = [
        'amount_reversal' => 'decimal:2',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
