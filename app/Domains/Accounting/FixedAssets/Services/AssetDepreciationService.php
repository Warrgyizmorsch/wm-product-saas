<?php

namespace App\Domains\Accounting\FixedAssets\Services;

use App\Domains\Accounting\FixedAssets\Events\DepreciationPosted;
use App\Domains\Accounting\FixedAssets\Models\AssetDepreciationSchedule;
use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Repositories\ChartOfAccountRepositoryInterface;
use App\Domains\Accounting\Services\JournalService;
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
     */
    private const FALLBACK_ACCUMULATED_DEPRECIATION_CODE = '1510';
    private const FALLBACK_DEPRECIATION_EXPENSE_CODE = '5800';

    public function __construct(
        private readonly JournalService $journals,
        private readonly ChartOfAccountRepositoryInterface $accounts,
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

    public function openingBookValueFor(Asset $asset): float
    {
        $lastPosted = $asset->depreciationSchedules()
            ->where('status', AssetDepreciationSchedule::STATUS_POSTED)
            ->orderByDesc('period_year')
            ->orderByDesc('period_month')
            ->first();

        if ($lastPosted !== null) {
            return (float) $lastPosted->closing_book_value;
        }

        return (float) ($asset->book_value ?? $asset->capitalization_cost ?? 0);
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

        $openingBookValue = $this->openingBookValueFor($asset);
        $depreciationAmount = $this->calculateMonthlyDepreciation($asset, $openingBookValue);
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

            $depreciationExpenseAccount = $category?->depreciationExpenseAccount
                ?? $this->accounts->findByCode(self::FALLBACK_DEPRECIATION_EXPENSE_CODE, $tenantId);
            $accumulatedDepreciationAccount = $category?->accumulatedDepreciationAccount
                ?? $this->accounts->findByCode(self::FALLBACK_ACCUMULATED_DEPRECIATION_CODE, $tenantId);

            if ($depreciationExpenseAccount === null || $accumulatedDepreciationAccount === null) {
                throw new InvalidArgumentException(
                    "Cannot post depreciation for asset #{$asset->id}: depreciation expense / accumulated depreciation accounts are not configured (category or fallback codes " . self::FALLBACK_DEPRECIATION_EXPENSE_CODE . '/' . self::FALLBACK_ACCUMULATED_DEPRECIATION_CODE . ' not found).'
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
