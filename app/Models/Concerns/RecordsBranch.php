<?php

namespace App\Models\Concerns;

use App\Core\Branch\BranchContext;
use App\Domains\HRMS\Models\Branch;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Like BelongsToBranch, but without the read scope: the branch a row was
 * created in is still recorded, yet picking a branch in the header never hides
 * rows. For records kept per company, not per branch — the accounting books:
 * a P&L or Balance Sheet is the whole company's, whichever branch is selected.
 */
trait RecordsBranch
{
    protected static function bootRecordsBranch(): void
    {
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
