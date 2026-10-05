<?php

namespace App\Domains\Accounting\FixedAssets\Services;

use App\Domains\Accounting\FixedAssets\Events\AssetDisposed;
use App\Domains\Accounting\FixedAssets\Models\AssetDisposal;
use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Repositories\ChartOfAccountRepositoryInterface;
use App\Domains\Accounting\Services\JournalService;
use App\Domains\Accounting\Services\SystemAccountService;
use App\Domains\Accounting\Support\SystemAccount;
use App\Domains\HRMS\Models\Asset;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Disposal validate -> calculate -> approve -> post, per asset-mng.md §19.
 * Posting happens synchronously inside this service's own transaction (not
 * via an event+listener hop) since disposal is itself the primary
 * user-triggered action, matching §32's literal transaction example.
 */
class AssetDisposalService
{
    private const FALLBACK_BANK_KEY = SystemAccount::BANK;
    private const FALLBACK_OUTPUT_TAX_KEY = SystemAccount::OUTPUT_TAX;

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
        private readonly SystemAccountService $systemAccounts,
    ) {
    }

    /**
     * @param array{disposal_type: string, disposal_date: string, sale_proceeds?: float, tax_amount?: float, remarks?: string} $data
     */
    public function dispose(Asset $asset, array $data, int $userId): AssetDisposal
    {
        if (in_array($asset->status, self::TERMINAL_STATUSES, true)) {
            throw new InvalidArgumentException("Asset #{$asset->id} has already been disposed (status: {$asset->status}).");
        }

        $originalCost = (float) $asset->capitalization_cost;
        $accumulatedDepreciation = (float) $asset->accumulated_depreciation;
        $netBookValue = round($originalCost - $accumulatedDepreciation, 2);

        $saleProceeds = round((float) ($data['sale_proceeds'] ?? 0), 2);
        $taxAmount = round((float) ($data['tax_amount'] ?? 0), 2);
        $gainLoss = round($saleProceeds - $taxAmount - $netBookValue, 2);

        return AssetDisposal::create([
            'company_id' => $asset->company_id,
            'branch_id' => $asset->branch_id,
            'asset_id' => $asset->id,
            'disposal_type' => $data['disposal_type'],
            'disposal_date' => $data['disposal_date'],
            'original_cost' => $originalCost,
            'accumulated_depreciation_at_disposal' => $accumulatedDepreciation,
            'net_book_value' => $netBookValue,
            'sale_proceeds' => $saleProceeds,
            'tax_amount' => $taxAmount,
            'gain_loss_amount' => $gainLoss,
            'status' => AssetDisposal::STATUS_PENDING_APPROVAL,
            'requested_by' => $userId,
            'remarks' => $data['remarks'] ?? null,
        ]);
    }

    public function approve(AssetDisposal $disposal, int $userId): AssetDisposal
    {
        if ($disposal->status !== AssetDisposal::STATUS_PENDING_APPROVAL) {
            throw new InvalidArgumentException("Disposal #{$disposal->id} must be pending approval; currently {$disposal->status}.");
        }

        $disposal->update([
            'status' => AssetDisposal::STATUS_APPROVED,
            'approved_by' => $userId,
            'approved_at' => now(),
        ]);

        return $disposal->fresh();
    }

    public function reject(AssetDisposal $disposal, int $userId): AssetDisposal
    {
        if ($disposal->status !== AssetDisposal::STATUS_PENDING_APPROVAL) {
            throw new InvalidArgumentException("Disposal #{$disposal->id} must be pending approval; currently {$disposal->status}.");
        }

        $disposal->update([
            'status' => AssetDisposal::STATUS_REJECTED,
            'approved_by' => $userId,
            'approved_at' => now(),
        ]);

        return $disposal->fresh();
    }

    public function post(AssetDisposal $disposal, int $userId): AssetDisposal
    {
        if ($disposal->status !== AssetDisposal::STATUS_APPROVED) {
            throw new InvalidArgumentException("Disposal #{$disposal->id} must be approved before it can be posted; currently {$disposal->status}.");
        }

        return DB::transaction(function () use ($disposal, $userId) {
            $asset = $disposal->asset()->lockForUpdate()->first();

            if (in_array($asset->status, self::TERMINAL_STATUSES, true)) {
                throw new InvalidArgumentException("Asset #{$asset->id} has already been disposed (status: {$asset->status}).");
            }

            $newStatus = match ($disposal->disposal_type) {
                'sale' => Asset::STATUS_SOLD,
                'lost' => Asset::STATUS_LOST,
                default => Asset::STATUS_SCRAPPED,
            };

            if (!$asset->canTransitionTo($newStatus)) {
                throw new InvalidArgumentException("Asset #{$asset->id} cannot transition from '{$asset->status}' to '{$newStatus}'.");
            }

            $category = $asset->category;
            $tenantId = $asset->tenant_id;

            $fixedAssetAccount = $category?->chartOfAccount
                ?? $this->systemAccounts->get(SystemAccount::FIXED_ASSETS, $tenantId);

            if ($fixedAssetAccount === null) {
                throw new InvalidArgumentException("Cannot post disposal for asset #{$asset->id}: no fixed asset account configured.");
            }

            $lines = [];

            if ($disposal->sale_proceeds > 0) {
                $bankAccount = $this->systemAccounts->get(self::FALLBACK_BANK_KEY, $tenantId);
                if ($bankAccount === null) {
                    throw new InvalidArgumentException("Cannot post disposal for asset #{$asset->id}: bank account (code " . SystemAccount::templateCode(self::FALLBACK_BANK_KEY) . ') not found.');
                }
                $lines[] = [
                    'chart_of_account_id' => $bankAccount->id,
                    'debit' => (float) $disposal->sale_proceeds,
                    'description' => "Sale proceeds - {$asset->asset_code}",
                ];
            }

            if ((float) $disposal->accumulated_depreciation_at_disposal > 0) {
                $accumulatedDepreciationAccount = $category?->accumulatedDepreciationAccount
                    ?? $this->systemAccounts->get(SystemAccount::ACCUMULATED_DEPRECIATION, $tenantId);
                if ($accumulatedDepreciationAccount === null) {
                    throw new InvalidArgumentException("Cannot post disposal for asset #{$asset->id}: accumulated depreciation account not found.");
                }
                $lines[] = [
                    'chart_of_account_id' => $accumulatedDepreciationAccount->id,
                    'debit' => (float) $disposal->accumulated_depreciation_at_disposal,
                    'description' => "Accumulated depreciation write-back - {$asset->asset_code}",
                ];
            }

            $gainLoss = (float) $disposal->gain_loss_amount;

            if ($gainLoss < 0) {
                $lossAccount = $category?->lossOnDisposalAccount
                    ?? $this->systemAccounts->get(SystemAccount::LOSS_ON_ASSET_DISPOSAL, $tenantId)
                    ?? $this->systemAccounts->get(SystemAccount::OTHER_EXPENSE, $tenantId);
                if ($lossAccount === null) {
                    throw new InvalidArgumentException("Cannot post disposal for asset #{$asset->id}: loss-on-disposal account not found.");
                }
                $lines[] = [
                    'chart_of_account_id' => $lossAccount->id,
                    'debit' => round(abs($gainLoss), 2),
                    'description' => "Loss on disposal - {$asset->asset_code}",
                ];
            }

            $lines[] = [
                'chart_of_account_id' => $fixedAssetAccount->id,
                'credit' => (float) $disposal->original_cost,
                'description' => "Disposal - {$asset->asset_code}",
            ];

            if ($gainLoss > 0) {
                // Miscellaneous Income (4900) only for charts that predate 4940.
                $gainAccount = $category?->gainOnDisposalAccount
                    ?? $this->systemAccounts->get(SystemAccount::GAIN_ON_ASSET_DISPOSAL, $tenantId)
                    ?? $this->systemAccounts->get(SystemAccount::MISC_INCOME, $tenantId);
                if ($gainAccount === null) {
                    throw new InvalidArgumentException("Cannot post disposal for asset #{$asset->id}: gain-on-disposal account not found.");
                }
                $lines[] = [
                    'chart_of_account_id' => $gainAccount->id,
                    'credit' => round($gainLoss, 2),
                    'description' => "Gain on disposal - {$asset->asset_code}",
                ];
            }

            if ((float) $disposal->tax_amount > 0) {
                $outputTaxAccount = $this->systemAccounts->get(self::FALLBACK_OUTPUT_TAX_KEY, $tenantId);
                if ($outputTaxAccount === null) {
                    throw new InvalidArgumentException("Cannot post disposal for asset #{$asset->id}: output tax account (code " . SystemAccount::templateCode(self::FALLBACK_OUTPUT_TAX_KEY) . ') not found.');
                }
                $lines[] = [
                    'chart_of_account_id' => $outputTaxAccount->id,
                    'credit' => (float) $disposal->tax_amount,
                    'description' => "Output tax on disposal - {$asset->asset_code}",
                ];
            }

            $journal = $this->journals->post($lines, [
                'tenant_id' => $tenantId,
                'company_id' => $asset->company_id,
                'branch_id' => $asset->branch_id,
                'journal_date' => $disposal->disposal_date,
                'source' => Journal::SOURCE_FIXED_ASSETS,
                'reference_type' => 'asset_disposal',
                'reference_id' => $disposal->id,
                'memo' => "Disposal ({$disposal->disposal_type}) - {$asset->asset_code}",
                'posted_by' => $userId,
            ]);

            $disposal->update([
                'status' => AssetDisposal::STATUS_POSTED,
                'journal_id' => $journal->id,
            ]);

            $asset->update([
                'status' => $newStatus,
                'book_value' => 0,
            ]);

            event(new AssetDisposed($disposal->fresh()));

            return $disposal->fresh();
        });
    }
}
