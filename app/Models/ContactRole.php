<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

/**
 * Contact role lookup (FRS 2.3 / C-17).
 *
 * Rows with tenant_id = NULL are system default roles available to every tenant;
 * rows with a tenant_id are custom roles added by that tenant's admin.
 */
class ContactRole extends Model
{
    protected $table = 'contact_roles';

    protected $fillable = [
        'tenant_id',
        'name',
        'slug',
        'is_system',
        'is_active',
    ];

    protected $casts = [
        'tenant_id' => 'integer',
        'is_system' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (ContactRole $role): void {
            if (empty($role->slug) && ! empty($role->name)) {
                $role->slug = Str::slug($role->name, '_');
            }
        });
    }

    public function scopeAvailableForTenant(Builder $query, ?int $tenantId = null): Builder
    {
        $tenantId ??= tenant_id() ?? auth()->user()?->tenant_id;

        return $query->where('is_active', true)
            ->where(function (Builder $q) use ($tenantId) {
                $q->whereNull('tenant_id');
                if ($tenantId !== null) {
                    $q->orWhere('tenant_id', $tenantId);
                }
            })
            ->orderByDesc('is_system')
            ->orderBy('name');
    }

    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(PartyContact::class, 'contact_role_assignments', 'contact_role_id', 'contact_id')
            ->withTimestamps();
    }
}
