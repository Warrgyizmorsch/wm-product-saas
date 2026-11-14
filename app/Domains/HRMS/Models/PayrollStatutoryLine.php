<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One employee's PF and ESI figures for a posted payroll run (ECR / ESIC return source). */
class PayrollStatutoryLine extends BaseModel
{
    protected $table = 'payroll_statutory_lines';

    protected $fillable = [
        'tenant_id', 'company_id', 'payroll_run_id', 'employee_id', 'payroll_month',
        'employee_name', 'uan_number', 'esi_ip_number',
        'gross_wages', 'paid_days', 'ncp_days',
        'epf_wages', 'eps_wages', 'edli_wages', 'epf_employee', 'eps_employer', 'epf_employer_diff',
        'esi_wages', 'esi_employee', 'esi_employer',
    ];

    protected $casts = [
        'gross_wages' => 'float', 'paid_days' => 'float', 'ncp_days' => 'float',
        'epf_wages' => 'float', 'eps_wages' => 'float', 'edli_wages' => 'float',
        'epf_employee' => 'float', 'eps_employer' => 'float', 'epf_employer_diff' => 'float',
        'esi_wages' => 'float', 'esi_employee' => 'float', 'esi_employer' => 'float',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }

    public function hasPf(): bool
    {
        return $this->epf_employee > 0;
    }

    public function hasEsi(): bool
    {
        return $this->esi_employee > 0;
    }
}
