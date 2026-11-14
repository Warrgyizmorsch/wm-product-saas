<?php

namespace App\Domains\Platform\Models;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What platform staff did in the /admin console. Deliberately NOT tenant-scoped
 * (plain Model): tenant_id here is the tenant the action targeted, and the log
 * must stay readable across all of them. Written by RecordPlatformAudit.
 */
class PlatformAuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'tenant_id', 'action', 'method', 'url',
        'payload', 'status_code', 'ip_address', 'user_agent',
    ];

    protected $casts = [
        'payload' => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withoutGlobalScope('tenant');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
