<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KpiTemplateItem extends BaseModel
{
    protected $table = 'kpi_template_items';

    protected $fillable = [
        'tenant_id',
        'kpi_template_id',
        'kra_category_id',
        'kpi_master_id',
        'title',
        'description',
        'unit',
        'calculation_type',
        'target',
        'weightage',
    ];

    protected $casts = [
        'target' => 'float',
        'weightage' => 'float',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(KpiTemplate::class, 'kpi_template_id');
    }

    public function kraCategory(): BelongsTo
    {
        return $this->belongsTo(KraCategory::class, 'kra_category_id');
    }

    public function kpiMaster(): BelongsTo
    {
        return $this->belongsTo(KpiMaster::class, 'kpi_master_id');
    }
}
