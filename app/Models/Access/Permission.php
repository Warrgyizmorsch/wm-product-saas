<?php

namespace App\Models\Access;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Permission extends Model
{
    protected $fillable = [
        'name',
        'module',
        'entity',
        'action',
        'is_system',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permissions')
            ->withPivot('scope')
            ->withTimestamps();
    }

    public function rolePermissions(): HasMany
    {
        return $this->hasMany(RolePermission::class);
    }

    public function userOverrides(): HasMany
    {
        return $this->hasMany(UserPermissionOverride::class);
    }

    public function usersWithOverrides(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_permission_overrides')
            ->withPivot(['tenant_id', 'branch_id', 'department_id', 'scope', 'allowed', 'reason'])
            ->withTimestamps();
    }
}
