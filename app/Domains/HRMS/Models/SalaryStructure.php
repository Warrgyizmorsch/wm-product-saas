<?php

namespace App\Domains\HRMS\Models;

use Illuminate\Database\Eloquent\Model;

class SalaryStructure extends Model
{
    protected $fillable = [
        'company_id',
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
}
