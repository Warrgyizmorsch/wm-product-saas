<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeFnfSettlement extends BaseModel
{
    protected $table = 'employee_fnf_settlements';

    protected $fillable = [
        'tenant_id',
        'employee_exit_id',
        'employee_id',
        'calculation_date',
        'lwd',
        'unpaid_salary_days',
        'unpaid_salary_amount',
        'leave_encashment_days',
        'leave_encashment_amount',
        'gratuity_amount',
        'bonus_amount',
        'other_earnings',
        'total_earnings',
        'notice_shortfall_recovery',
        'unsettled_advances_recovery',
        'asset_damage_recovery',
        'other_deductions',
        'total_deductions',
        'net_payable_amount',
        'status',
        'settlement_channel',
        'payment_method',
        'payment_reference',
        'paid_at',
        'notes',
    ];

    protected $casts = [
        'calculation_date' => 'date',
        'lwd' => 'date',
        'paid_at' => 'datetime',
        'unpaid_salary_days' => 'decimal:2',
        'unpaid_salary_amount' => 'decimal:2',
        'leave_encashment_days' => 'decimal:2',
        'leave_encashment_amount' => 'decimal:2',
        'gratuity_amount' => 'decimal:2',
        'bonus_amount' => 'decimal:2',
        'other_earnings' => 'decimal:2',
        'total_earnings' => 'decimal:2',
        'notice_shortfall_recovery' => 'decimal:2',
        'unsettled_advances_recovery' => 'decimal:2',
        'asset_damage_recovery' => 'decimal:2',
        'other_deductions' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'net_payable_amount' => 'decimal:2',
    ];

    public function exit(): BelongsTo
    {
        return $this->belongsTo(EmployeeExit::class, 'employee_exit_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
