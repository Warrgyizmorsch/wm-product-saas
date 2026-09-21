<?php

namespace App\Models\Concerns;

use App\Core\Branch\BranchContext;
use App\Domains\HRMS\Models\Branch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToBranch
{
    protected static function bootBelongsToBranch(): void
    {
        static::addGlobalScope('branch', function (Builder $builder): void {
            $branchId = branch_id() ?? app(BranchContext::class)->id();

            if ($branchId !== null) {
                $model = $builder->getModel();
                $column = $model->getTable().'.branch_id';

                // Shared masters (no branch) stay visible in every branch.
                if (property_exists($model, 'sharedAcrossCompanies') && $model->sharedAcrossCompanies) {
                    $builder->where(fn (Builder $q) => $q->where($column, $branchId)->orWhereNull($column));
                } else {
                    $builder->where($column, $branchId);
                }
            }
        });

        static::creating(function ($model): void {
            $branchId = branch_id() ?? app(BranchContext::class)->id();

            if ($branchId !== null && empty($model->branch_id)) {
                $model->branch_id = $branchId;
            }
        });
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
