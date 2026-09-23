<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasHrPermissions;
use App\Models\Concerns\HasProductionPermissions;
use App\Models\Access\Role;
use App\Models\Access\UserPermissionOverride;
use App\Models\Access\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, BelongsToTenant, HasFactory, Notifiable, HasProductionPermissions, HasHrPermissions;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'company_id',
        'branch_id',
        'department_id',
        'role_id',
        'name',
        'email',
        'phone',
        'avatar',
        'password',
        'role',
        'settings',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'settings' => 'array',
        ];
    }

    /**
     * Resolve the avatar URL for display.
     * Uses root-relative URLs so it is independent of base URL / APP_URL on both local and live environments.
     */
    public function getAvatarUrlAttribute(): string
    {
        if (!empty($this->avatar)) {
            if (str_starts_with($this->avatar, 'http://') || str_starts_with($this->avatar, 'https://')) {
                return $this->avatar;
            }
            return '/storage/' . ltrim($this->avatar, '/');
        }

        $employee = $this->relationLoaded('employee') ? $this->employee : $this->employee;
        if ($employee && !empty($employee->photo)) {
            if (str_starts_with($employee->photo, 'http://') || str_starts_with($employee->photo, 'https://')) {
                return $employee->photo;
            }
            return '/storage/' . ltrim($employee->photo, '/');
        }

        return '/assets/images/avatar/default.png';
    }

    /**
     * Resolve effective phone number (user phone preferred, then employee mobile).
     */
    public function getEffectivePhoneAttribute(): ?string
    {
        if (!empty($this->phone)) {
            return $this->phone;
        }

        $employee = $this->relationLoaded('employee') ? $this->employee : $this->employee;
        return $employee?->personal_mobile_number;
    }

    /**
     * Check if the user account is deactivated.
     */
    public function isDeactivated(): bool
    {
        return (bool) ($this->settings['is_deactivated'] ?? false);
    }

    public function primaryRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')
            ->withPivot(['tenant_id', 'branch_id', 'department_id'])
            ->withTimestamps();
    }

    public function userRoles(): HasMany
    {
        return $this->hasMany(UserRole::class);
    }

    public function permissionOverrides(): HasMany
    {
        return $this->hasMany(UserPermissionOverride::class);
    }

    public function employee(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(\App\Domains\HRMS\Models\Employee::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\HRMS\Models\Company::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(\App\Domains\HRMS\Models\Branch::class);
    }

    protected static function booted(): void
    {
        static::saved(function (self $user) {
            if ($user->isDirty('email') && !empty($user->email)) {
                $employee = $user->employee;
                if ($employee && $employee->office_email !== $user->email) {
                    $emailExists = \App\Domains\HRMS\Models\Employee::where('office_email', $user->email)
                        ->where('id', '!=', $employee->id)
                        ->exists();
                    if (!$emailExists) {
                        $employee->updateQuietly(['office_email' => $user->email]);
                    }
                }
            }
        });
    }
}
