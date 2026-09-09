<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PipCategory extends BaseModel
{
    protected $table = 'pip_categories';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'name',
        'code',
        'description',
        'status',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }

    public function pips(): HasMany
    {
        return $this->hasMany(PerformanceImprovementPlan::class, 'pip_category_id');
    }
}
