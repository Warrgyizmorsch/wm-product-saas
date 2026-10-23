<?php

namespace App\Domains\Accounting\FixedAssets\Services;

use App\Domains\Accounting\FixedAssets\Events\AssetRevalued;
use App\Domains\Accounting\FixedAssets\Models\AssetDepreciationSchedule;
use App\Domains\Accounting\FixedAssets\Models\AssetRevaluation;
use App\Domains\Accounting\FixedAssets\Support\AssetLedgers;
use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Repositories\ChartOfAccountRepositoryInterface;
use App\Domains\Accounting\Services\JournalService;
use App\Domains\Accounting\Services\SystemAccountService;
use App\Domains\Accounting\Support\SystemAccount;
use App\Domains\HRMS\Models\Asset;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Revaluation validate -> approve -> post, per asset-mng.md §21. Never
 * touches Asset.acquisition_cost (the original historical cost) — only
 * capitalization_cost/accumulated_depreciation/book_value/useful_life_months/
 * residual_value going forward, and discards any not-yet-posted depreciation
 * schedules dated after the revaluation so future depreciation reflects the
 * new basis. Posting uses the elimination method (see buildLines()).
 */
class AssetRevaluationService
{
    private const FALLBACK_REVALUATION_RESERVE_KEY = SystemAccount::REVALUATION_RESERVE;
    private const FALLBACK_IMPAIRMENT_LOSS_KEY = SystemAccount::IMPAIRMENT_LOSS;

    public function __construct(
        private readonly JournalService $journals,
        private readonly ChartOfAccountRepositoryInterface $accounts,
        private readonly SystemAccountService $systemAccounts,
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
            $tenantId = $asset->tenant_id;
            $revaluationDate = Carbon::parse($revaluation->revaluation_date)->startOfDay();

            // Depreciation already posted for a period ending after the
            // revaluation date was charged on the old basis; revaluing behind
            // it would leave those months on the wrong carrying amount.
            $laterPosted = AssetDepreciationSchedule::where('asset_id', $asset->id)
                ->where('status', AssetDepreciationSchedule::STATUS_POSTED)
                ->where('period_end_date', '>', $revaluationDate->toDateString())
                ->exists();

            if ($laterPosted) {
                throw new InvalidArgumentException("Cannot post revaluation for asset #{$asset->id}: depreciation has already been posted for a period after {$revaluationDate->toDateString()}.");
            }

            // Figures are taken from the locked asset now, not from the request:
            // depreciation posted between request and posting would otherwise
            // leave the journal out of step with the asset register.
            $cost = round((float) $asset->capitalization_cost, 2);
            $accumulatedDepreciation = round((float) $asset->accumulated_depreciation, 2);
            $netBookValue = round($cost - $accumulatedDepreciation, 2);
            $revaluedAmount = round((float) $revaluation->revalued_amount, 2);
            $surplusDeficit = round($revaluedAmount - $netBookValue, 2);

            $lines = $this->buildLines($asset, $cost, $accumulatedDepreciation, $revaluedAmount, $surplusDeficit);

            $journalId = null;

            if ($lines !== []) {
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
                $journalId = $journal->id;
            }

            $revaluation->update([
                'status' => AssetRevaluation::STATUS_POSTED,
                'journal_id' => $journalId,
                'previous_book_value' => $netBookValue,
                'revaluation_surplus_deficit' => $surplusDeficit,
            ]);

            // Elimination method: the revalued amount becomes the new gross
            // carrying amount and accumulated depreciation restarts at zero, so
            // capitalization_cost - accumulated_depreciation keeps matching the
            // fixed-asset and accumulated-depreciation ledgers. useful_life_months
            // becomes the life remaining from the revaluation date.
            $updates = [
                'capitalization_cost' => $revaluedAmount,
                'accumulated_depreciation' => 0,
                'book_value' => $revaluedAmount,
                'useful_life_months' => $revaluation->revised_useful_life_months
                    ?? $this->remainingLifeMonths($asset, $revaluationDate, $revaluation->id),
            ];

            if ($revaluation->revised_residual_value !== null) {
                $updates['residual_value'] = (float) $revaluation->revised_residual_value;
            }

            $asset->update($updates);

            // Never touch posted schedules. Any unposted schedule (draft, reviewed
            // or approved) for a period ending on/after the revaluation was
            // calculated on the old basis and must be regenerated.
            AssetDepreciationSchedule::where('asset_id', $asset->id)
                ->where('status', '!=', AssetDepreciationSchedule::STATUS_POSTED)
                ->where('period_end_date', '>=', $revaluationDate->toDateString())
                ->delete();

            event(new AssetRevalued($revaluation->fresh()));

            return $revaluation->fresh();
        });
    }

    /**
     * Journal lines for a revaluation using the elimination method:
     *
     *   Dr Accumulated Depreciation      (write back to zero)
     *   Dr/Cr Fixed Asset                (gross cost -> revalued amount)
     *   and the difference against carrying amount:
     *     increase: Cr Impairment Loss up to earlier impairments of this asset
     *               (reversal through P&L), remainder Cr Revaluation Reserve
     *     decrease: Dr Revaluation Reserve up to this asset's reserve balance,
     *               remainder Dr Impairment Loss
     *
     * @return list<array{chart_of_account_id: int, debit?: float, credit?: float, description: string}>
     */
    private function buildLines(Asset $asset, float $cost, float $accumulatedDepreciation, float $revaluedAmount, float $surplusDeficit): array
    {
        $tenantId = $asset->tenant_id;
        $category = $asset->category;
        $code = $asset->asset_code;
        $lines = [];

        // The ledger the asset's cost actually sits in.
        $fixedAssetAccount = app(AssetLedgers::class)->holding($asset);

        if ($accumulatedDepreciation > 0) {
            $accumulatedDepreciationAccount = $category?->accumulatedDepreciationAccount
                ?? $this->systemAccounts->get(SystemAccount::ACCUMULATED_DEPRECIATION, $tenantId);
            if ($accumulatedDepreciationAccount === null) {
                throw new InvalidArgumentException("Cannot post revaluation for asset #{$asset->id}: accumulated depreciation account (code " . SystemAccount::templateCode(SystemAccount::ACCUMULATED_DEPRECIATION) . ') not found.');
            }
            $lines[] = ['chart_of_account_id' => $accumulatedDepreciationAccount->id, 'debit' => $accumulatedDepreciation, 'description' => "Revaluation - accumulated depreciation written back - {$code}"];
        }

        $grossMovement = round($revaluedAmount - $cost, 2);
        if ($grossMovement > 0) {
            $lines[] = ['chart_of_account_id' => $fixedAssetAccount->id, 'debit' => $grossMovement, 'description' => "Revaluation - {$code}"];
        } elseif ($grossMovement < 0) {
            $lines[] = ['chart_of_account_id' => $fixedAssetAccount->id, 'credit' => abs($grossMovement), 'description' => "Revaluation - {$code}"];
        }

        $reserveAccount = $this->systemAccounts->get(self::FALLBACK_REVALUATION_RESERVE_KEY, $tenantId);
        $impairmentAccount = $this->systemAccounts->get(self::FALLBACK_IMPAIRMENT_LOSS_KEY, $tenantId)
            ?? $this->systemAccounts->get(SystemAccount::OTHER_EXPENSE, $tenantId);

        if ($surplusDeficit > 0) {
            $priorImpairment = $impairmentAccount ? max(0.0, $this->assetBalanceOn($asset, $impairmentAccount->id, 'debit')) : 0.0;
            $reversal = round(min($surplusDeficit, $priorImpairment), 2);
            $toReserve = round($surplusDeficit - $reversal, 2);

            if ($reversal > 0) {
                $lines[] = ['chart_of_account_id' => $impairmentAccount->id, 'credit' => $reversal, 'description' => "Reversal of earlier impairment - {$code}"];
            }

            if ($toReserve > 0) {
                if ($reserveAccount === null) {
                    throw new InvalidArgumentException("Cannot post revaluation for asset #{$asset->id}: revaluation reserve account (code " . SystemAccount::templateCode(self::FALLBACK_REVALUATION_RESERVE_KEY) . ') not found.');
                }
                $lines[] = ['chart_of_account_id' => $reserveAccount->id, 'credit' => $toReserve, 'description' => "Revaluation surplus - {$code}"];
            }
        } elseif ($surplusDeficit < 0) {
            $deficit = abs($surplusDeficit);
            $reserveBalance = $reserveAccount ? max(0.0, $this->assetBalanceOn($asset, $reserveAccount->id, 'credit')) : 0.0;
            $fromReserve = round(min($deficit, $reserveBalance), 2);
            $toProfitAndLoss = round($deficit - $fromReserve, 2);

            if ($fromReserve > 0) {
                $lines[] = ['chart_of_account_id' => $reserveAccount->id, 'debit' => $fromReserve, 'description' => "Revaluation deficit against reserve - {$code}"];
            }

            if ($toProfitAndLoss > 0) {
                if ($impairmentAccount === null) {
                    throw new InvalidArgumentException("Cannot post revaluation for asset #{$asset->id}: impairment loss account not found.");
                }
                $lines[] = ['chart_of_account_id' => $impairmentAccount->id, 'debit' => $toProfitAndLoss, 'description' => "Revaluation deficit / impairment - {$code}"];
            }
        }

        return $lines;
    }

    /**
     * Net balance this asset's earlier posted revaluations left on one account,
     * signed so that the account's natural side ($side) is positive.
     */
    private function assetBalanceOn(Asset $asset, int $accountId, string $side): float
    {
        $journalIds = AssetRevaluation::query()
            ->where('asset_id', $asset->id)
            ->where('status', AssetRevaluation::STATUS_POSTED)
            ->whereNotNull('journal_id')
            ->pluck('journal_id');

        if ($journalIds->isEmpty()) {
            return 0.0;
        }

        $totals = JournalEntry::withoutGlobalScopes()
            ->where('tenant_id', $asset->tenant_id)
            ->whereIn('journal_id', $journalIds)
            ->where('chart_of_account_id', $accountId)
            ->selectRaw('COALESCE(SUM(debit), 0) as debits, COALESCE(SUM(credit), 0) as credits')
            ->first();

        $net = (float) $totals->debits - (float) $totals->credits;

        return round($side === 'debit' ? $net : -$net, 2);
    }

    /**
     * Months of useful life left at the revaluation date when the revaluation
     * does not set a revised life. Counted from the previous revaluation (whose
     * useful_life_months was already "remaining from then") or otherwise from
     * the depreciation start date.
     */
    private function remainingLifeMonths(Asset $asset, Carbon $revaluationDate, int $currentRevaluationId): int
    {
        // The revaluation being posted is already marked posted by now; counting
        // from its own date would leave the full original life in place.
        $lastRevaluationDate = AssetRevaluation::query()
            ->where('asset_id', $asset->id)
            ->where('status', AssetRevaluation::STATUS_POSTED)
            ->whereKeyNot($currentRevaluationId)
            ->where('revaluation_date', '<=', $revaluationDate->toDateString())
            ->max('revaluation_date');

        $from = $lastRevaluationDate ?? $asset->depreciation_start_date ?? $asset->capitalization_date;

        if ($from === null) {
            return max(1, (int) $asset->useful_life_months);
        }

        $elapsed = max(0, (int) floor(Carbon::parse($from)->startOfDay()->diffInMonths($revaluationDate, false)));

        return max(1, (int) $asset->useful_life_months - $elapsed);
    }
}
