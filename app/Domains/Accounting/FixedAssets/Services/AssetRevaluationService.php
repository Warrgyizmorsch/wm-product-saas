<?php

namespace App\Domains\Accounting\FixedAssets\Services;

use App\Domains\Accounting\FixedAssets\Events\AssetRevalued;
use App\Domains\Accounting\FixedAssets\Models\AssetDepreciationSchedule;
use App\Domains\Accounting\FixedAssets\Models\AssetRevaluation;
use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Repositories\ChartOfAccountRepositoryInterface;
use App\Domains\Accounting\Services\JournalService;
use App\Domains\HRMS\Models\Asset;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Revaluation validate -> approve -> post, per asset-mng.md §21. Never
 * touches Asset.acquisition_cost (the original historical cost) — only
 * capitalization_cost/book_value/useful_life_months/residual_value going
 * forward, and regenerates any not-yet-posted depreciation schedules dated
 * after the revaluation so future depreciation reflects the new basis.
 */
class AssetRevaluationService
{
    private const FALLBACK_REVALUATION_RESERVE_CODE = '3200';
    private const FALLBACK_IMPAIRMENT_LOSS_CODE = '5920';

    public function __construct(
        private readonly JournalService $journals,
        private readonly ChartOfAccountRepositoryInterface $accounts,
        private readonly AssetDepreciationService $depreciation,
    ) {
    }

    /**
     * @param array{revaluation_date: string, revalued_amount: float, revised_useful_life_months?: int, revised_residual_value?: float, reason: string} $data
     */
    public function revalue(Asset $asset, array $data, int $userId): AssetRevaluation
    {
        if ((float) $asset->capitalization_cost <= 0) {
            throw new InvalidArgumentException("Asset #{$asset->id} has not been capitalized; cannot revalue.");
        }

        $previousBookValue = (float) ($asset->book_value ?? $asset->capitalization_cost);
        $revaluedAmount = round((float) $data['revalued_amount'], 2);
        $surplusDeficit = round($revaluedAmount - $previousBookValue, 2);

        return AssetRevaluation::create([
            'company_id' => $asset->company_id,
            'branch_id' => $asset->branch_id,
            'asset_id' => $asset->id,
            'revaluation_date' => $data['revaluation_date'],
            'previous_book_value' => $previousBookValue,
            'revalued_amount' => $revaluedAmount,
            'revaluation_surplus_deficit' => $surplusDeficit,
            'revised_useful_life_months' => $data['revised_useful_life_months'] ?? null,
            'revised_residual_value' => $data['revised_residual_value'] ?? null,
            'reason' => $data['reason'],
            'status' => AssetRevaluation::STATUS_PENDING_APPROVAL,
            'requested_by' => $userId,
        ]);
    }

    public function approve(AssetRevaluation $revaluation, int $userId): AssetRevaluation
    {
        if ($revaluation->status !== AssetRevaluation::STATUS_PENDING_APPROVAL) {
            throw new InvalidArgumentException("Revaluation #{$revaluation->id} must be pending approval; currently {$revaluation->status}.");
        }

        $revaluation->update([
            'status' => AssetRevaluation::STATUS_APPROVED,
            'approved_by' => $userId,
            'approved_at' => now(),
        ]);

        return $revaluation->fresh();
    }

    public function reject(AssetRevaluation $revaluation, int $userId): AssetRevaluation
    {
        if ($revaluation->status !== AssetRevaluation::STATUS_PENDING_APPROVAL) {
            throw new InvalidArgumentException("Revaluation #{$revaluation->id} must be pending approval; currently {$revaluation->status}.");
        }

        $revaluation->update([
            'status' => AssetRevaluation::STATUS_REJECTED,
            'approved_by' => $userId,
            'approved_at' => now(),
        ]);

        return $revaluation->fresh();
    }

    public function post(AssetRevaluation $revaluation, int $userId): AssetRevaluation
    {
        if ($revaluation->status !== AssetRevaluation::STATUS_APPROVED) {
            throw new InvalidArgumentException("Revaluation #{$revaluation->id} must be approved before it can be posted; currently {$revaluation->status}.");
        }

        return DB::transaction(function () use ($revaluation, $userId) {
            $asset = $revaluation->asset()->lockForUpdate()->first();
            $category = $asset->category;
            $tenantId = $asset->tenant_id;

            $fixedAssetAccount = $category?->chartOfAccount ?? $this->accounts->findByCode('1500', $tenantId);
            if ($fixedAssetAccount === null) {
                throw new InvalidArgumentException("Cannot post revaluation for asset #{$asset->id}: no fixed asset account configured.");
            }

            $amount = round(abs((float) $revaluation->revaluation_surplus_deficit), 2);

            if ($amount > 0) {
                if ((float) $revaluation->revaluation_surplus_deficit > 0) {
                    $reserveAccount = $this->accounts->findByCode(self::FALLBACK_REVALUATION_RESERVE_CODE, $tenantId);
                    if ($reserveAccount === null) {
                        throw new InvalidArgumentException("Cannot post revaluation for asset #{$asset->id}: revaluation reserve account (code " . self::FALLBACK_REVALUATION_RESERVE_CODE . ') not found.');
                    }

                    $lines = [
                        ['chart_of_account_id' => $fixedAssetAccount->id, 'debit' => $amount, 'description' => "Revaluation surplus - {$asset->asset_code}"],
                        ['chart_of_account_id' => $reserveAccount->id, 'credit' => $amount, 'description' => "Revaluation surplus - {$asset->asset_code}"],
                    ];
                } else {
                    $impairmentAccount = $this->accounts->findByCode(self::FALLBACK_IMPAIRMENT_LOSS_CODE, $tenantId)
                        ?? $this->accounts->findByCode('5900', $tenantId);
                    if ($impairmentAccount === null) {
                        throw new InvalidArgumentException("Cannot post revaluation for asset #{$asset->id}: impairment loss account not found.");
                    }

                    $lines = [
                        ['chart_of_account_id' => $impairmentAccount->id, 'debit' => $amount, 'description' => "Revaluation deficit / impairment - {$asset->asset_code}"],
                        ['chart_of_account_id' => $fixedAssetAccount->id, 'credit' => $amount, 'description' => "Revaluation deficit / impairment - {$asset->asset_code}"],
                    ];
                }

                $journal = $this->journals->post($lines, [
                    'tenant_id' => $tenantId,
                    'company_id' => $asset->company_id,
                    'branch_id' => $asset->branch_id,
                    'journal_date' => $revaluation->revaluation_date,
                    'source' => Journal::SOURCE_FIXED_ASSETS,
                    'reference_type' => 'asset_revaluation',
                    'reference_id' => $revaluation->id,
                    'memo' => "Revaluation - {$asset->asset_code}",
                    'posted_by' => $userId,
                ]);

                $revaluation->update([
                    'status' => AssetRevaluation::STATUS_POSTED,
                    'journal_id' => $journal->id,
                ]);
            } else {
                // No monetary difference — nothing to post to the GL, but the
                // revaluation (and any useful-life/residual-value revision) is
                // still recorded and marked posted.
                $revaluation->update(['status' => AssetRevaluation::STATUS_POSTED]);
            }

            $updates = [
                'capitalization_cost' => (float) $revaluation->revalued_amount,
                'book_value' => (float) $revaluation->revalued_amount,
            ];

            if ($revaluation->revised_useful_life_months !== null) {
                $updates['useful_life_months'] = $revaluation->revised_useful_life_months;
            }

            if ($revaluation->revised_residual_value !== null) {
                $updates['residual_value'] = (float) $revaluation->revised_residual_value;
            }

            $asset->update($updates);

            // Never touch posted schedules — only unposted (draft) future
            // schedules are stale after a revaluation and must be recomputed
            // against the new basis.
            AssetDepreciationSchedule::where('asset_id', $asset->id)
                ->where('status', AssetDepreciationSchedule::STATUS_DRAFT)
                ->where('period_start_date', '>=', $revaluation->revaluation_date)
                ->delete();

            event(new AssetRevalued($revaluation->fresh()));

            return $revaluation->fresh();
        });
    }
}
