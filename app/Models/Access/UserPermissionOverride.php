<?php

namespace App\Models\Access;

use App\Domains\HRMS\Models\Branch;
use App\Domains\HRMS\Models\Department;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPermissionOverride extends Model
{
    protected $fillable = [
        'user_id',
        'permission_id',
        'tenant_id',
        'branch_id',
        'department_id',
        'scope',
        'allowed',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'allowed' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
