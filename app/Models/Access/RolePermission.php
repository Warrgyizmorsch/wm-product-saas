<?php

namespace App\Models\Access;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RolePermission extends Model
{
    public const SCOPE_OWN = 'own';
    public const SCOPE_TEAM = 'team';
    public const SCOPE_DEPARTMENT = 'department';
    public const SCOPE_BRANCH = 'branch';
    public const SCOPE_TENANT = 'tenant';
    public const SCOPE_PLATFORM = 'platform';

    public const SCOPES = [
        self::SCOPE_OWN,
        self::SCOPE_TEAM,
        self::SCOPE_DEPARTMENT,
        self::SCOPE_BRANCH,
        self::SCOPE_TENANT,
        self::SCOPE_PLATFORM,
    ];

    protected $fillable = [
        'role_id',
        'permission_id',
        'scope',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class);
    }
}
