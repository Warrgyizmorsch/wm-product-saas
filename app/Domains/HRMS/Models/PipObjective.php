<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PipObjective extends BaseModel
{
    protected $table = 'pip_objectives';

    protected $fillable = [
        'tenant_id',
        'pip_id',
        'title',
        'description',
        'target_criteria',
        'support_provided',
        'weightage',
        'status',
        'manager_remarks',
    ];

    protected $casts = [
        'weightage' => 'float',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(PerformanceImprovementPlan::class, 'pip_id');
    }
}
