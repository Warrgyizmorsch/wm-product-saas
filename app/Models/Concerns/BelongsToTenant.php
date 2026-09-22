<?php

namespace App\Models\Concerns;

use App\Core\Tenant\TenantContext;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder): void {
            $tenantId = tenant_id() ?? app(TenantContext::class)->id();

            if ($tenantId !== null) {
                $model = $builder->getModel();
                $column = $model->getTable().'.tenant_id';

                // Shared masters (no tenant) stay visible in every tenant.
                if (property_exists($model, 'sharedAcrossTenants') && $model->sharedAcrossTenants) {
                    $builder->where(fn (Builder $q) => $q->where($column, $tenantId)->orWhereNull($column));
                } else {
                    $builder->where($column, $tenantId);
                }
            }
        });

        static::creating(function ($model): void {
            $tenantId = tenant_id() ?? app(TenantContext::class)->id();

            if ($tenantId !== null && empty($model->tenant_id)) {
                $model->tenant_id = $tenantId;
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
