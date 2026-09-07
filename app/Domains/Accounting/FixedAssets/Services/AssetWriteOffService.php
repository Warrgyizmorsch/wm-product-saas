<?php

namespace App\Domains\Accounting\FixedAssets\Services;

use App\Domains\Accounting\FixedAssets\Events\AssetWrittenOff;
use App\Domains\Accounting\FixedAssets\Models\AssetWriteOff;
use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Repositories\ChartOfAccountRepositoryInterface;
use App\Domains\Accounting\Services\JournalService;
use App\Domains\HRMS\Models\Asset;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Write-off validate -> approve -> post, per asset-mng.md §20. Never deletes
 * the underlying Asset row — only transitions its status.
 */
class AssetWriteOffService
{
    private const TERMINAL_STATUSES = [
        Asset::STATUS_DISPOSED,
        Asset::STATUS_SOLD,
        Asset::STATUS_SCRAPPED,
        Asset::STATUS_WRITTEN_OFF,
        Asset::STATUS_LOST,
    ];

    public function __construct(
        private readonly JournalService $journals,
        private readonly ChartOfAccountRepositoryInterface $accounts,
    ) {
    }

    /**
     * @param array{write_off_date: string, reason: string} $data
     */
    public function writeOff(Asset $asset, array $data, int $userId): AssetWriteOff
    {
        if (in_array($asset->status, self::TERMINAL_STATUSES, true)) {
            throw new InvalidArgumentException("Asset #{$asset->id} has already been disposed/written off (status: {$asset->status}).");
        }

        $netBookValue = round((float) $asset->capitalization_cost - (float) $asset->accumulated_depreciation, 2);

        return AssetWriteOff::create([
            'company_id' => $asset->company_id,
            'branch_id' => $asset->branch_id,
            'asset_id' => $asset->id,
            'write_off_date' => $data['write_off_date'],
            'reason' => $data['reason'],
            'net_book_value' => $netBookValue,
            'status' => AssetWriteOff::STATUS_PENDING_APPROVAL,
            'requested_by' => $userId,
        ]);
    }

    public function approve(AssetWriteOff $writeOff, int $userId): AssetWriteOff
    {
        if ($writeOff->status !== AssetWriteOff::STATUS_PENDING_APPROVAL) {
            throw new InvalidArgumentException("Write-off #{$writeOff->id} must be pending approval; currently {$writeOff->status}.");
        }

        $writeOff->update([
            'status' => AssetWriteOff::STATUS_APPROVED,
            'approved_by' => $userId,
            'approved_at' => now(),
        ]);

        return $writeOff->fresh();
    }

    public function reject(AssetWriteOff $writeOff, int $userId): AssetWriteOff
    {
        if ($writeOff->status !== AssetWriteOff::STATUS_PENDING_APPROVAL) {
            throw new InvalidArgumentException("Write-off #{$writeOff->id} must be pending approval; currently {$writeOff->status}.");
        }

        $writeOff->update([
            'status' => AssetWriteOff::STATUS_REJECTED,
            'approved_by' => $userId,
            'approved_at' => now(),
        ]);

        return $writeOff->fresh();
    }

    public function post(AssetWriteOff $writeOff, int $userId): AssetWriteOff
    {
        if ($writeOff->status !== AssetWriteOff::STATUS_APPROVED) {
            throw new InvalidArgumentException("Write-off #{$writeOff->id} must be approved before it can be posted; currently {$writeOff->status}.");
        }

        return DB::transaction(function () use ($writeOff, $userId) {
            $asset = $writeOff->asset()->lockForUpdate()->first();

            if (in_array($asset->status, self::TERMINAL_STATUSES, true)) {
                throw new InvalidArgumentException("Asset #{$asset->id} has already been disposed/written off (status: {$asset->status}).");
            }

            if (!$asset->canTransitionTo(Asset::STATUS_WRITTEN_OFF)) {
                throw new InvalidArgumentException("Asset #{$asset->id} cannot transition from '{$asset->status}' to 'written_off'.");
            }

            $category = $asset->category;
            $tenantId = $asset->tenant_id;

            $fixedAssetAccount = $category?->chartOfAccount ?? $this->accounts->findByCode('1500', $tenantId);
            $accumulatedDepreciationAccount = $category?->accumulatedDepreciationAccount ?? $this->accounts->findByCode('1510', $tenantId);
            $lossAccount = $category?->lossOnDisposalAccount
                ?? $this->accounts->findByCode('5910', $tenantId)
                ?? $this->accounts->findByCode('5900', $tenantId);

            if ($fixedAssetAccount === null || $accumulatedDepreciationAccount === null || $lossAccount === null) {
                throw new InvalidArgumentException("Cannot post write-off for asset #{$asset->id}: required accounts are not configured.");
            }

            $accumulatedDepreciation = (float) $asset->accumulated_depreciation;
            $netBookValue = (float) $writeOff->net_book_value;

            $lines = [];

            if ($accumulatedDepreciation > 0) {
                $lines[] = [
                    'chart_of_account_id' => $accumulatedDepreciationAccount->id,
                    'debit' => $accumulatedDepreciation,
                    'description' => "Write-off - accumulated depreciation - {$asset->asset_code}",
                ];
            }

            if ($netBookValue > 0) {
                $lines[] = [
                    'chart_of_account_id' => $lossAccount->id,
                    'debit' => $netBookValue,
                    'description' => "Write-off loss - {$asset->asset_code}",
                ];
            }

            $lines[] = [
                'chart_of_account_id' => $fixedAssetAccount->id,
                'credit' => (float) $asset->capitalization_cost,
                'description' => "Write-off - {$asset->asset_code}",
            ];

            $journal = $this->journals->post($lines, [
                'tenant_id' => $tenantId,
                'company_id' => $asset->company_id,
                'branch_id' => $asset->branch_id,
                'journal_date' => $writeOff->write_off_date,
                'source' => Journal::SOURCE_FIXED_ASSETS,
                'reference_type' => 'asset_write_off',
                'reference_id' => $writeOff->id,
                'memo' => "Write-off - {$asset->asset_code}",
                'posted_by' => $userId,
            ]);

            $writeOff->update([
                'status' => AssetWriteOff::STATUS_POSTED,
                'journal_id' => $journal->id,
            ]);

            $asset->update([
                'status' => Asset::STATUS_WRITTEN_OFF,
                'book_value' => 0,
            ]);

            event(new AssetWrittenOff($writeOff->fresh()));

            return $writeOff->fresh();
        });
    }
}
