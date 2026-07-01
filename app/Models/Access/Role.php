<?php

namespace App\Models\Access;

use App\Domains\HRMS\Models\Branch;
use App\Domains\HRMS\Models\Department;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'level',
        'is_system',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'is_system' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions')
            ->withPivot('scope')
            ->withTimestamps();
    }

    public function rolePermissions(): HasMany
    {
        return $this->hasMany(RolePermission::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_roles')
            ->withPivot(['tenant_id', 'branch_id', 'department_id'])
            ->withTimestamps();
    }

    public function userRoles(): HasMany
    {
        return $this->hasMany(UserRole::class);
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'user_roles')
            ->withPivot(['user_id', 'tenant_id', 'department_id'])
            ->withTimestamps();
    }

    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'user_roles')
            ->withPivot(['user_id', 'tenant_id', 'branch_id'])
            ->withTimestamps();
    }
}
