<?php

namespace App\Domains\Production\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProductionShift extends BaseModel
{
    use HasFactory;

    protected $table = 'production_shifts';

    protected $fillable = [
        'tenant_id',
        'company_id',
        'name',
        'code',
        'start_time',
        'end_time',
        'break_minutes',
        'overtime_allowed',
        'active',
    ];

    protected $casts = [
        'break_minutes'    => 'integer',
        'overtime_allowed' => 'boolean',
        'active'           => 'boolean',
    ];

    public function company(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Domains\HRMS\Models\Company::class, 'company_id');
    }

    public function workCenters(): BelongsToMany
    {
        return $this->belongsToMany(
            WorkCenter::class,
            'production_work_center_shifts',
            'shift_id',
            'work_center_id'
        );
    }
}
