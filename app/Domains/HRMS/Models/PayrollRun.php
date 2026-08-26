<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;
use App\Models\User;

class PayrollRun extends BaseModel
{
    protected $fillable = [
        'tenant_id',
        'company_id',
        'pay_group_id',
        'payroll_month',
        'start_date',
        'end_date',
        'status',
        'processed_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function payGroup()
    {
        return $this->belongsTo(PayGroup::class);
    }

    public function processedBy()
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
