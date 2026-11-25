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
        'webhook_secret',
        'ip_address',
        'port',
        'status',
        'last_ping_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'webhook_secret',
    ];

    protected $casts = [
        'status' => 'boolean',
        'port' => 'integer',
        'last_ping_at' => 'datetime',
    ];

    public function getWebhookSecretAttribute($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            return \Illuminate\Support\Facades\Crypt::decryptString($value);
        } catch (\Throwable) {
            return $value;
        }
    }

    public function setWebhookSecretAttribute(?string $value): void
    {
        $this->attributes['webhook_secret'] = !empty($value)
            ? \Illuminate\Support\Facades\Crypt::encryptString($value)
            : null;
    }

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
