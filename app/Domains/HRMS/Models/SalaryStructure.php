<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;

class SalaryStructure extends BaseModel
{
    protected $fillable = [
        'company_id',
        'pay_group_id',
        'name',
        'min_ctc',
        'max_ctc',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
        'min_ctc' => 'decimal:2',
        'max_ctc' => 'decimal:2',
    ];

    public function items()
    {
        return $this->hasMany(SalaryStructureItem::class, 'salary_structure_id')->orderBy('sort_order');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the pay group that owns the salary structure.
     */
    public function payGroup()
    {
        return $this->belongsTo(PayGroup::class, 'pay_group_id');
    }
}
