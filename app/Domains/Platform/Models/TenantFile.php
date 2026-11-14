<?php

namespace App\Domains\Platform\Models;

use App\Core\Database\BaseModel;

/**
 * A stored upload counted against its tenant's storage allowance
 * (TenantStorage records it; tenant:storage-sync reconciles it with disk).
 */
class TenantFile extends BaseModel
{
    protected $fillable = [
        'tenant_id',
        'disk',
        'path',
        'size',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }
}
