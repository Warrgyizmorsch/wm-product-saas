<?php

namespace App\Domains\Accounting\Models;

use App\Core\Database\BaseModel;

class ExchangeRateSyncSetting extends BaseModel
{
    public const STATUS_SUCCESS = 'success';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';

    protected $table = 'exchange_rate_sync_settings';

    protected $fillable = [
        'tenant_id',
        'is_enabled',
        'currencies',
        'last_synced_at',
        'last_status',
        'last_error',
        'unsupported',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'currencies' => 'array',
        'unsupported' => 'array',
        'last_synced_at' => 'datetime',
    ];
}
