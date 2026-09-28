<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KraCategory extends BaseModel
{
    protected $table = 'kra_categories';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'name',
        'code',
        'color',
        'description',
        'status',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function kpiMasters(): HasMany
    {
        return $this->hasMany(KpiMaster::class, 'kra_category_id');
    }

    public function goalItems(): HasMany
    {
        return $this->hasMany(EmployeeGoalItem::class, 'kra_category_id');
    }
}
