<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KpiTemplate extends BaseModel
{
    protected $table = 'kpi_templates';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'department_id',
        'designation_id',
        'name',
        'code',
        'description',
        'status',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class, 'designation_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(KpiTemplateItem::class, 'kpi_template_id');
    }

    public function goalPlans(): HasMany
    {
        return $this->hasMany(EmployeeGoalPlan::class, 'kpi_template_id');
    }
}
