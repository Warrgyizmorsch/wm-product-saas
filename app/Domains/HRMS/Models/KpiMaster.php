<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KpiMaster extends BaseModel
{
    protected $table = 'kpi_masters';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'kra_category_id',
        'name',
        'code',
        'description',
        'unit',
        'calculation_type',
        'default_target',
        'default_weightage',
        'status',
    ];

    protected $casts = [
        'default_target' => 'float',
        'default_weightage' => 'float',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function kraCategory(): BelongsTo
    {
        return $this->belongsTo(KraCategory::class, 'kra_category_id');
    }

    public function templateItems(): HasMany
    {
        return $this->hasMany(KpiTemplateItem::class, 'kpi_master_id');
    }

    public function goalItems(): HasMany
    {
        return $this->hasMany(EmployeeGoalItem::class, 'kpi_master_id');
    }
}
