<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;

class SalaryRevision extends BaseModel
{
    protected $fillable = [
        'employee_id',
        'old_salary_structure_id',
        'new_salary_structure_id',
        'effective_date',
        'old_ctc',
        'new_ctc',
        'arrears_paid',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'old_ctc'        => 'decimal:2',
        'new_ctc'        => 'decimal:2',
        'arrears_paid'   => 'boolean',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function oldStructure()
    {
        return $this->belongsTo(SalaryStructure::class, 'old_salary_structure_id');
    }

    public function newStructure()
    {
        return $this->belongsTo(SalaryStructure::class, 'new_salary_structure_id');
    }
}
