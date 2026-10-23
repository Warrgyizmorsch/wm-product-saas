<?php

namespace App\Domains\Accounting\FixedAssets\Services;

use App\Domains\Accounting\FixedAssets\Events\DepreciationPosted;
use App\Domains\Accounting\FixedAssets\Models\AssetDepreciationSchedule;
use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Repositories\ChartOfAccountRepositoryInterface;
use App\Domains\Accounting\Services\JournalService;
use App\Domains\Accounting\Services\SystemAccountService;
use App\Domains\Accounting\Support\SystemAccount;
use App\Domains\HRMS\Models\Asset;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class AssetDepreciationService
{
    /**
     * Fallback account codes used when an asset's category doesn't have its
     * own depreciation_expense_account_id / accumulated_depreciation_account_id
     * configured — mirrors the findByCode-with-fallback convention already
     * used by PostPurchaseBillJournal for the fixed-asset account itself (code
     * 1500).
     *
     * The default chart seeds Depreciation Expense as 5400. The fallback used to
     * be 5800, which the default chart never had, so depreciation failed for any
     * category without its own accounts; 5800 is still tried after 5400 for
     * tenants that created it by hand.
     */
    private const FALLBACK_ACCUMULATED_DEPRECIATION_KEY = SystemAccount::ACCUMULATED_DEPRECIATION;
    private const FALLBACK_DEPRECIATION_EXPENSE_KEY = SystemAccount::DEPRECIATION_EXPENSE;
    private const LEGACY_DEPRECIATION_EXPENSE_CODE = '5800';

    public function __construct(
        private readonly JournalService $journals,
        private readonly ChartOfAccountRepositoryInterface $accounts,
        private readonly SystemAccountService $systemAccounts,
    ) {
    }

    /**
     * Pure calculation — no DB reads/writes — so it can be unit-tested with
     * deterministic inputs independent of the generate/post pipeline.
     */
    public function calculateMonthlyDepreciation(Asset $asset, float $openingBookValue): float
    {
        $capitalizationCost = (float) $asset->capitalization_cost;
        $residualValue = (float) $asset->residual_value;
        $usefulLifeMonths = (int) $asset->useful_life_months;

        if ($capitalizationCost <= 0 || $usefulLifeMonths <= 0 || $residualValue < 0 || $residualValue >= $capitalizationCost) {
            return 0.0;
        }

        $remainingDepreciable = round($openingBookValue - $residualValue, 2);

        if ($remainingDepreciable <= 0.0) {
            return 0.0;
        }

        $amount = $asset->depreciation_method === Asset::DEPRECIATION_METHOD_WDV
            ? $this->wdvMonthlyAmount($capitalizationCost, $residualValue, $usefulLifeMonths, $openingBookValue)
            : ($capitalizationCost - $residualValue) / $usefulLifeMonths;

        return round(min(max($amount, 0.0), $remainingDepreciable), 2);
    }

    /**
     * Standard WDV: derive a fixed annual rate once from cost/residual/life,
     * then apply rate/12 to the (declining) opening book value each month —
     * confirmed convention, not compounded monthly.
     */
    private function wdvMonthlyAmount(float $capitalizationCost, float $residualValue, int $usefulLifeMonths, float $openingBookValue): float
    {
        $usefulLifeYears = $usefulLifeMonths / 12;
        $annualRate = 1 - (($residualValue / $capitalizationCost) ** (1 / $usefulLifeYears));
        $monthlyRate = $annualRate / 12;

        return $openingBookValue * $monthlyRate;
    }

    /**
     * Carrying amount the next period depreciates from: gross carrying amount
     * less accumulated depreciation. Both are kept in step with the ledger by
     * capitalization, depreciation posting and revaluation, so this also picks
     * up a revaluation posted after the last depreciation run.
     */
    public function openingBookValueFor(Asset $asset): float
    {
        return round((float) $asset->capitalization_cost - (float) $asset->accumulated_depreciation, 2);
    }

    public function generateSchedule(Asset $asset, int $year, int $month, int $userId): AssetDepreciationSchedule
    {
        if ($asset->status !== Asset::STATUS_ACTIVE) {
            throw new InvalidArgumentException("Asset #{$asset->id} is not active; depreciation can only be generated for active assets.");
        }

        if ((float) $asset->capitalization_cost <= 0 || (int) $asset->useful_life_months <= 0) {
            throw new InvalidArgumentException("Asset #{$asset->id} has not been capitalized (missing capitalization cost or useful life).");
        }

        $existing = AssetDepreciationSchedule::where('asset_id', $asset->id)
            ->where('period_year', $year)
            ->where('period_month', $month)
            ->first();

        if ($existing !== null) {
            throw new InvalidArgumentException("Depreciation for asset #{$asset->id} has already been generated for {$year}-{$month}.");
        }

        $periodStart = \Illuminate\Support\Carbon::create($year, $month, 1)->startOfMonth();
        $periodEnd = $periodStart->copy()->endOfMonth();

        if ($asset->depreciation_start_date && $periodEnd->lt($asset->depreciation_start_date)) {
            throw new InvalidArgumentException("Asset #{$asset->id}'s depreciation has not started as of {$periodEnd->toDateString()}.");
        }

        // Schedules must be posted in order: a later period generated while an
        // earlier one is still unposted would start from a stale book value.
        $earlierUnposted = AssetDepreciationSchedule::where('asset_id', $asset->id)
            ->where('status', '!=', AssetDepreciationSchedule::STATUS_POSTED)
            ->where('period_end_date', '<', $periodStart->toDateString())
            ->exists();

        if ($earlierUnposted) {
            throw new InvalidArgumentException("Asset #{$asset->id} has an earlier depreciation schedule that is not posted yet; post it before generating {$year}-{$month}.");
        }

        $openingBookValue = $this->openingBookValueFor($asset);
        $depreciationAmount = $this->calculateMonthlyDepreciation($asset, $openingBookValue);

        if ($depreciationAmount <= 0) {
            throw new InvalidArgumentException("Asset #{$asset->id} has nothing left to depreciate for {$year}-{$month}.");
        }

        $closingBookValue = round($openingBookValue - $depreciationAmount, 2);

        return AssetDepreciationSchedule::create([
            'company_id' => $asset->company_id,
            'branch_id' => $asset->branch_id,
            'asset_id' => $asset->id,
            'period_year' => $year,
            'period_month' => $month,
            'period_start_date' => $periodStart->toDateString(),
            'period_end_date' => $periodEnd->toDateString(),
            'opening_book_value' => $openingBookValue,
            'depreciation_amount' => $depreciationAmount,
            'closing_book_value' => $closingBookValue,
            'method' => $asset->depreciation_method,
            'status' => AssetDepreciationSchedule::STATUS_DRAFT,
            'generated_by' => $userId,
            'generated_at' => now(),
        ]);
    }

    /**
     * Bulk-generate draft schedules for every eligible active asset in a
     * tenant/period — the entry point used by the artisan command. Assets
     * that already have a schedule for this period, or that fail validation
     * (not capitalized, depreciation not yet started), are skipped rather
     * than aborting the whole batch.
     */
    public function generateForPeriod(int $tenantId, int $year, int $month, ?int $companyId = null, ?int $userId = null): Collection
    {
        $query = Asset::where('tenant_id', $tenantId)->where('status', Asset::STATUS_ACTIVE);

        if ($companyId !== null) {
            $query->where('company_id', $companyId);
        }

        $generated = new Collection();

        $query->each(function (Asset $asset) use ($year, $month, $userId, &$generated) {
            try {
                $generated->push($this->generateSchedule($asset, $year, $month, $userId ?? 0));
            } catch (InvalidArgumentException $e) {
                // Skip ineligible/already-generated assets — logged by the caller (artisan command).
            }
        });

        return $generated;
    }

    public function review(AssetDepreciationSchedule $schedule, int $userId): AssetDepreciationSchedule
    {
        if ($schedule->status !== AssetDepreciationSchedule::STATUS_DRAFT) {
            throw new InvalidArgumentException("Schedule #{$schedule->id} must be in draft status to review; currently {$schedule->status}.");
        }

        $schedule->update([
            'status' => AssetDepreciationSchedule::STATUS_REVIEWED,
            'reviewed_by' => $userId,
            'reviewed_at' => now(),
        ]);

        return $schedule->fresh();
    }

    public function approve(AssetDepreciationSchedule $schedule, int $userId): AssetDepreciationSchedule
    {
        if ($schedule->status !== AssetDepreciationSchedule::STATUS_REVIEWED) {
            throw new InvalidArgumentException("Schedule #{$schedule->id} must be reviewed before it can be approved; currently {$schedule->status}.");
        }

        $schedule->update([
            'status' => AssetDepreciationSchedule::STATUS_APPROVED,
            'approved_by' => $userId,
            'approved_at' => now(),
        ]);

        return $schedule->fresh();
    }

    /**
     * Posts the depreciation journal and updates the asset's running balances.
     * Deliberately does not swallow exceptions — unlike the passive
     * PostPurchaseBillJournal listener, this is a human-triggered "Post"
     * action, so failures (e.g. missing accounts, closed period) must surface
     * directly to the caller. Duplicate posting is prevented by both the
     * status guard here and the DB unique constraint on the schedule table.
     */
    public function post(AssetDepreciationSchedule $schedule, int $userId): AssetDepreciationSchedule
    {
        if ($schedule->status !== AssetDepreciationSchedule::STATUS_APPROVED) {
            throw new InvalidArgumentException("Schedule #{$schedule->id} must be approved before it can be posted; currently {$schedule->status}.");
        }

        return DB::transaction(function () use ($schedule, $userId) {
            $asset = $schedule->asset()->lockForUpdate()->first();
            $category = $asset->category;
            $tenantId = $asset->tenant_id;

            // A sold / scrapped / written-off asset has left the books; posting
            // would recreate accumulated depreciation with no asset behind it.
            if ($asset->isRetired()) {
                throw new InvalidArgumentException("Schedule #{$schedule->id} cannot be posted: asset #{$asset->id} is {$asset->status} and no longer depreciates.");
            }

            // The schedule was calculated against the book value at generation
            // time. If that has moved since (revaluation, another posting), the
            // amount is stale and must be regenerated rather than posted.
            if (abs($this->openingBookValueFor($asset) - (float) $schedule->opening_book_value) >= 0.01) {
                throw new InvalidArgumentException("Schedule #{$schedule->id} was calculated on a book value of {$schedule->opening_book_value}, but asset #{$asset->id} is now carried at {$this->openingBookValueFor($asset)}. Regenerate the schedule before posting.");
            }

            $depreciationExpenseAccount = $category?->depreciationExpenseAccount
                ?? $this->systemAccounts->get(self::FALLBACK_DEPRECIATION_EXPENSE_KEY, $tenantId)
                ?? $this->accounts->findByCode(self::LEGACY_DEPRECIATION_EXPENSE_CODE, $tenantId);
            $accumulatedDepreciationAccount = $category?->accumulatedDepreciationAccount
                ?? $this->systemAccounts->get(self::FALLBACK_ACCUMULATED_DEPRECIATION_KEY, $tenantId);

            if ($depreciationExpenseAccount === null || $accumulatedDepreciationAccount === null) {
                throw new InvalidArgumentException(
                    "Cannot post depreciation for asset #{$asset->id}: depreciation expense / accumulated depreciation accounts are not configured (category or fallback codes " . SystemAccount::templateCode(self::FALLBACK_DEPRECIATION_EXPENSE_KEY) . '/' . SystemAccount::templateCode(self::FALLBACK_ACCUMULATED_DEPRECIATION_KEY) . ' not found).'
                );
            }

            $amount = (float) $schedule->depreciation_amount;

            $journal = $this->journals->post([
                [
                    'chart_of_account_id' => $depreciationExpenseAccount->id,
                    'debit' => $amount,
                    'description' => "Depreciation - {$asset->asset_code} - {$schedule->period_month}/{$schedule->period_year}",
                ],
                [
                    'chart_of_account_id' => $accumulatedDepreciationAccount->id,
                    'credit' => $amount,
                    'description' => "Depreciation - {$asset->asset_code} - {$schedule->period_month}/{$schedule->period_year}",
                ],
            ], [
                'tenant_id' => $tenantId,
                'company_id' => $asset->company_id,
                'branch_id' => $asset->branch_id,
                'journal_date' => $schedule->period_end_date,
                'source' => Journal::SOURCE_FIXED_ASSETS,
                'reference_type' => 'asset_depreciation_schedule',
                'reference_id' => $schedule->id,
                'memo' => "Depreciation for {$asset->asset_code} ({$schedule->period_month}/{$schedule->period_year})",
                'posted_by' => $userId,
            ]);

            $schedule->update([
                'status' => AssetDepreciationSchedule::STATUS_POSTED,
                'journal_id' => $journal->id,
                'posted_by' => $userId,
                'posted_at' => now(),
            ]);

            $newAccumulated = round((float) $asset->accumulated_depreciation + $amount, 2);
            $newBookValue = (float) $schedule->closing_book_value;
            $newStatus = $newBookValue <= (float) $asset->residual_value
                ? Asset::STATUS_FULLY_DEPRECIATED
                : $asset->status;

            $asset->update([
                'accumulated_depreciation' => $newAccumulated,
                'book_value' => $newBookValue,
                'status' => $newStatus,
            ]);

            event(new DepreciationPosted($schedule->fresh()));

            return $schedule->fresh();
        });
    }
}
