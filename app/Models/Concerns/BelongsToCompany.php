<?php

namespace App\Models\Concerns;

use App\Core\Company\CompanyContext;
use App\Domains\HRMS\Models\Company;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToCompany
{
    protected static function bootBelongsToCompany(): void
    {
        static::addGlobalScope('company', function (Builder $builder): void {
            $companyId = company_id() ?? app(CompanyContext::class)->id();

            if ($companyId !== null) {
                $model = $builder->getModel();
                $column = $model->getTable().'.company_id';

                // Shared masters (no company) stay visible in every company.
                if (property_exists($model, 'sharedAcrossCompanies') && $model->sharedAcrossCompanies) {
                    $builder->where(fn (Builder $q) => $q->where($column, $companyId)->orWhereNull($column));
                } else {
                    $builder->where($column, $companyId);
                }
            }
        });

        static::creating(function ($model): void {
            $companyId = company_id() ?? app(CompanyContext::class)->id();

            if ($companyId !== null && empty($model->company_id)) {
                $model->company_id = $companyId;
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
