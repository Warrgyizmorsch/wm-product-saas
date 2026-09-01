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
                $builder->where($builder->getModel()->getTable().'.company_id', $companyId);
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
