<?php

namespace App\Domains\Accounting\FixedAssets\Services;

use App\Domains\Accounting\FixedAssets\Events\AssetCapitalized;
use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Services\JournalService;
use App\Domains\Accounting\Services\SystemAccountService;
use App\Domains\Accounting\Support\SystemAccount;
use App\Domains\HRMS\Models\Asset;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Explicit "Capitalize as Fixed Asset" action (asset-mng.md §9) — deliberately
 * separate from the automatic GRN->Asset creation in CreateAssetFromGrnLine,
 * which only establishes physical custody at receipt. Capitalization is where
 * an asset gets its financial identity: capitalization cost, useful life,
 * depreciation method/start date, opening book value.
 *
 * Ledger: an asset bought through Purchase (PO -> GRN -> Bill) already reached
 * the Fixed Assets account when its bill was posted, so capitalizing it posts
 * nothing. An asset added directly in the register has never been on the
 * ledger; capitalizing it posts Dr Fixed Assets against the funding chosen on
 * the form: an opening balance (equity), a cash/bank account, or a vendor.
 */
class AssetCapitalizationService
{
    public const FUNDING_OPENING_BALANCE = 'opening_balance';
    public const FUNDING_CASH_BANK = 'cash_bank';
    public const FUNDING_VENDOR = 'vendor';

    public const FUNDING_SOURCES = [self::FUNDING_OPENING_BALANCE, self::FUNDING_CASH_BANK, self::FUNDING_VENDOR];

    public const REFERENCE_TYPE = 'asset_capitalization';

    public function __construct(
        private readonly JournalService $journals,
        private readonly SystemAccountService $systemAccounts,
    ) {
    }

    /**
     * @param array{
     *     acquisition_cost?: float,
     *     directly_attributable_cost?: float,
     *     recoverable_tax?: float,
     *     non_recoverable_tax?: float,
     *     residual_value?: float,
     *     useful_life_months?: int,
     *     depreciation_method?: string,
     *     depreciation_start_date?: string,
     *     capitalization_date?: string,
     *     funding_source?: string,
     *     funding_account_id?: int,
     *     vendor_id?: int,
     * } $data
     */
    public function capitalize(Asset $asset, array $data, int $userId): Asset
    {
        if (!in_array($asset->status, [Asset::STATUS_AVAILABLE, Asset::STATUS_DRAFT, Asset::STATUS_PENDING_CAPITALIZATION], true)) {
            throw new InvalidArgumentException("Asset #{$asset->id} is not eligible for capitalization from status '{$asset->status}'.");
        }

        if (!$asset->canTransitionTo(Asset::STATUS_ACTIVE)) {
            throw new InvalidArgumentException("Cannot capitalize an asset in status '{$asset->status}'.");
        }

        $category = $asset->category;

        $acquisitionCost = round((float) ($data['acquisition_cost'] ?? $asset->purchase_cost ?? 0), 2);
        $directCost = round((float) ($data['directly_attributable_cost'] ?? 0), 2);
        $recoverableTax = round((float) ($data['recoverable_tax'] ?? 0), 2);
        $nonRecoverableTax = round((float) ($data['non_recoverable_tax'] ?? 0), 2);
        $capitalizationCost = round($acquisitionCost + $directCost + $nonRecoverableTax, 2);

        if ($capitalizationCost <= 0) {
            throw new InvalidArgumentException('Capitalization cost must be greater than zero.');
        }

        $usefulLifeMonths = (int) ($data['useful_life_months'] ?? $category?->default_useful_life_months ?? 0);

        if ($usefulLifeMonths <= 0) {
            throw new InvalidArgumentException('Useful life (in months) is required to capitalize an asset.');
        }

        $residualValue = isset($data['residual_value'])
            ? round((float) $data['residual_value'], 2)
            : ($category?->default_residual_value_percent !== null
                ? round($capitalizationCost * ((float) $category->default_residual_value_percent / 100), 2)
                : 0.0);

        if ($residualValue >= $capitalizationCost) {
            throw new InvalidArgumentException('Residual value must be less than the capitalization cost.');
        }

        $method = $data['depreciation_method'] ?? $category?->default_depreciation_method ?? Asset::DEPRECIATION_METHOD_STRAIGHT_LINE;
        $capitalizationDate = $data['capitalization_date'] ?? now()->toDateString();
        $depreciationStartDate = $data['depreciation_start_date'] ?? $capitalizationDate;

        // Fail before anything is saved if a manual asset has no valid funding.
        if ($this->needsLedgerEntry($asset)) {
            $this->assertFunding($data);
        }

        return DB::transaction(function () use (
            $asset, $data, $acquisitionCost, $directCost, $recoverableTax, $nonRecoverableTax,
            $capitalizationCost, $residualValue, $usefulLifeMonths, $method,
            $capitalizationDate, $depreciationStartDate, $userId
        ) {
            $asset->update([
                'acquisition_cost' => $acquisitionCost,
                'directly_attributable_cost' => $directCost,
                'recoverable_tax' => $recoverableTax,
                'non_recoverable_tax' => $nonRecoverableTax,
                'capitalization_cost' => $capitalizationCost,
                'residual_value' => $residualValue,
                'useful_life_months' => $usefulLifeMonths,
                'depreciation_method' => $method,
                'depreciation_start_date' => $depreciationStartDate,
                'capitalization_date' => $capitalizationDate,
                'accumulated_depreciation' => 0,
                'book_value' => $capitalizationCost,
                'status' => Asset::STATUS_ACTIVE,
                'updated_by' => $userId,
            ]);

            $asset->refresh();

            if ($this->needsLedgerEntry($asset)) {
                $this->postEntry($asset, $data, $userId);
            }

            event(new AssetCapitalized($asset));

            return $asset;
        });
    }

    /**
     * True for an asset that never came through Purchase: its cost has no
     * ledger entry yet. Purchased assets were debited to Fixed Assets by the bill.
     */
    public function isPurchased(Asset $asset): bool
    {
        return $asset->goods_receipt_note_item_id !== null || $asset->purchase_order_id !== null;
    }

    public function needsLedgerEntry(Asset $asset): bool
    {
        return ! $this->isPurchased($asset);
    }

    /** The journal that put this asset's cost on the ledger, if one was posted here. */
    public function capitalizationJournal(Asset $asset): ?Journal
    {
        return $this->journals->activePosting((int) $asset->tenant_id, self::REFERENCE_TYPE, (int) $asset->id);
    }

    /**
     * Post the missing ledger entry for a manual asset that was capitalized
     * before capitalization posted journals. Safe to call twice: postOnce()
     * returns the existing journal.
     *
     * @param array{funding_source?: string, funding_account_id?: int, vendor_id?: int} $funding
     */
    public function postMissingEntry(Asset $asset, array $funding, int $userId): Journal
    {
        if ($asset->capitalization_date === null || (float) $asset->capitalization_cost <= 0) {
            throw new InvalidArgumentException("Asset #{$asset->id} has not been capitalized yet.");
        }

        if (! $this->needsLedgerEntry($asset)) {
            throw new InvalidArgumentException("Asset #{$asset->id} was bought through Purchase; its cost was posted with the purchase bill.");
        }

        if (in_array($asset->status, [Asset::STATUS_DISPOSED, Asset::STATUS_SOLD, Asset::STATUS_SCRAPPED, Asset::STATUS_WRITTEN_OFF, Asset::STATUS_LOST], true)) {
            throw new InvalidArgumentException("Asset #{$asset->id} is no longer held; post a manual journal if its history needs correcting.");
        }

        $this->assertFunding($funding);

        return DB::transaction(fn () => $this->postEntry($asset, $funding, $userId));
    }

    /**
     * @param array<string, mixed> $data
     */
    private function assertFunding(array $data): void
    {
        $source = $data['funding_source'] ?? null;

        if (! in_array($source, self::FUNDING_SOURCES, true)) {
            throw new InvalidArgumentException('Choose how this asset was funded: opening balance, cash/bank, or vendor.');
        }

        if ($source === self::FUNDING_VENDOR) {
            if (empty($data['vendor_id'])) {
                throw new InvalidArgumentException('Choose the vendor this asset is owed to.');
            }

            return;
        }

        $account = empty($data['funding_account_id']) ? null : ChartOfAccount::find($data['funding_account_id']);

        if ($account === null) {
            throw new InvalidArgumentException($source === self::FUNDING_CASH_BANK
                ? 'Choose the cash or bank account the asset was paid from.'
                : 'Choose the capital / equity account the opening balance goes to.');
        }

        if ($source === self::FUNDING_CASH_BANK && ! $account->is_cash_or_bank) {
            throw new InvalidArgumentException("{$account->code} - {$account->name} is not a cash or bank account.");
        }

        if ($source === self::FUNDING_OPENING_BALANCE && $account->type !== ChartOfAccount::TYPE_EQUITY) {
            throw new InvalidArgumentException("{$account->code} - {$account->name} is not a capital / equity account.");
        }
    }

    /**
     * Dr Fixed Assets            capitalized cost (acquisition + attributable + blocked tax)
     * Dr Input Tax               recoverable GST (not for opening balances)
     *     Cr equity / cash-bank / Accounts Payable (vendor)
     *
     * @param array<string, mixed> $funding
     */
    private function postEntry(Asset $asset, array $funding, int $userId): Journal
    {
        $tenantId = (int) $asset->tenant_id;
        $source = $funding['funding_source'];

        // The cost as capitalized, not as later revalued.
        $cost = round((float) $asset->acquisition_cost + (float) $asset->directly_attributable_cost + (float) $asset->non_recoverable_tax, 2);
        if ($cost <= 0) {
            $cost = round((float) $asset->capitalization_cost, 2);
        }

        $fixedAssetAccount = $asset->category?->chartOfAccount ?? $this->systemAccounts->get(SystemAccount::FIXED_ASSETS, $tenantId);
        if ($fixedAssetAccount === null) {
            throw new InvalidArgumentException("Cannot post capitalization for asset #{$asset->id}: no fixed asset account configured on its category and default account " . SystemAccount::templateCode(SystemAccount::FIXED_ASSETS) . ' not found.');
        }

        $code = $asset->asset_code;
        $lines = [[
            'chart_of_account_id' => $fixedAssetAccount->id,
            'debit' => $cost,
            'description' => "Capitalization - {$code}",
        ]];
        $credit = $cost;

        $recoverableTax = round((float) $asset->recoverable_tax, 2);
        if ($recoverableTax > 0 && $source !== self::FUNDING_OPENING_BALANCE) {
            $inputTax = $this->systemAccounts->require(SystemAccount::INPUT_TAX, $tenantId);
            $lines[] = ['chart_of_account_id' => $inputTax->id, 'debit' => $recoverableTax, 'description' => "Input tax credit - {$code}"];
            $credit = round($credit + $recoverableTax, 2);
        }

        if ($source === self::FUNDING_VENDOR) {
            $payable = $this->systemAccounts->require(SystemAccount::AP, $tenantId);
            $lines[] = [
                'chart_of_account_id' => $payable->id,
                'credit' => $credit,
                'party_type' => JournalEntry::PARTY_VENDOR,
                'party_id' => (int) $funding['vendor_id'],
                'description' => "Capitalization - {$code}",
            ];
        } else {
            $lines[] = [
                'chart_of_account_id' => (int) $funding['funding_account_id'],
                'credit' => $credit,
                'description' => $source === self::FUNDING_OPENING_BALANCE ? "Opening balance - {$code}" : "Capitalization - {$code}",
            ];
        }

        return $this->journals->postOnce($lines, [
            'tenant_id' => $tenantId,
            'company_id' => $asset->company_id,
            'branch_id' => $asset->branch_id,
            'journal_date' => $asset->capitalization_date,
            'source' => Journal::SOURCE_FIXED_ASSETS,
            'reference_type' => self::REFERENCE_TYPE,
            'reference_id' => $asset->id,
            'memo' => "Capitalization of {$code} ({$asset->name})",
            'posted_by' => $userId,
        ]);
    }
}
