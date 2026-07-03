<?php

namespace App\Domains\HRMS\Models;

use Illuminate\Database\Eloquent\Model;

class SalaryStructureItem extends Model
{
    protected $fillable = [
        'salary_structure_id',
        'salary_component_id',
        'calculation_type',
        'value',
        'sort_order',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    public function structure()
    {
        return $this->belongsTo(SalaryStructure::class, 'salary_structure_id');
    }

    public function component()
    {
        return $this->belongsTo(SalaryComponent::class, 'salary_component_id');
    }
}
