<?php

namespace App\Models\Concerns;

use App\Models\User;
use App\Services\Access\AccessService;
use Illuminate\Database\Eloquent\Builder;

trait HasRbacScope
{
    /**
     * Boot the trait and register the global RBAC scope.
     */
    public static function bootHasRbacScope(): void
    {
        static::addGlobalScope('rbac_scope', function (Builder $builder): void {
            if (! auth()->check()) {
                return;
            }

            /** @var User $user */
            $user = auth()->user();
            $tenantId = tenant_id() ?? $user->tenant_id;

            /** @var \Illuminate\Database\Eloquent\Model&self $model */
            $model = $builder->getModel();

            $permission = $model->rbacPermission();
            if (! $permission) {
                return;
            }

            $access = app(AccessService::class);

            // If the user has tenant-wide access (e.g. SCOPE_TENANT, tenant_owner, company_admin),
            // we do not apply any ownership restriction.
            if ($access->allows($user, $permission, ['tenant_id' => $tenantId])) {
                return;
            }

            // User is scoped (e.g. SCOPE_OWN) — apply model's RBAC scope constraints.
            $model->applyRbacScope($builder, $user);
        });
    }

    /**
     * Get the permission name governing this model's visibility (e.g. 'sales.orders.view').
     */
    public function rbacPermission(): ?string
    {
        return property_exists($this, 'rbacPermission') ? $this->rbacPermission : null;
    }

    /**
     * Apply model-specific ownership / scope constraints to the query builder.
     * Default implementation scopes by $rbacOwnerColumn (defaults to 'created_by').
     */
    public function applyRbacScope(Builder $builder, User $user): void
    {
        $ownerColumn = property_exists($this, 'rbacOwnerColumn') && $this->rbacOwnerColumn
            ? $this->rbacOwnerColumn
            : 'created_by';

        $builder->where($this->getTable().'.'.$ownerColumn, $user->id);
    }

    /**
     * Query scope to bypass the RBAC scope explicitly.
     */
    public function scopeWithoutRbacScope(Builder $query): Builder
    {
        return $query->withoutGlobalScope('rbac_scope');
    }

    /**
     * For Route Model Binding, bypass the RBAC global scope so that
     * authorization Policies can inspect the model and throw a 403 Forbidden
     * instead of failing with a 404 ModelNotFoundException.
     */
    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        return parent::resolveRouteBindingQuery($query, $value, $field)
            ->withoutGlobalScope('rbac_scope');
    }
}
