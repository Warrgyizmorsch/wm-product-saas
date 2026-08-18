<?php

namespace App\Domains\HRMS\Models;

use App\Core\Database\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BiometricDevice extends BaseModel
{
    protected $fillable = [
        'tenant_id',
        'company_id',
        'business_unit_id',
        'branch_id',
        'name',
        'device_serial',
        'ip_address',
        'port',
        'status',
        'last_ping_at',
    ];

    protected $casts = [
        'status' => 'boolean',
        'port' => 'integer',
        'last_ping_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function businessUnit(): BelongsTo
    {
        return $this->belongsTo(BusinessUnit::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function punchLogs(): HasMany
    {
        return $this->hasMany(BiometricPunchLog::class);
    }
}
