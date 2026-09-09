<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PipPolicyTemplate extends BaseModel
{
    protected $table = 'pip_policy_templates';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'name',
        'duration_days',
        'checkin_frequency',
        'description',
        'status',
    ];

    protected $casts = [
        'duration_days' => 'integer',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'company_id');
    }
}
