<?php

namespace App\Domains\Accounting\FixedAssets\Support;

use App\Domains\Accounting\FixedAssets\Models\AssetRevaluation;
use App\Domains\Accounting\FixedAssets\Services\AssetCapitalizationService;
use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Services\SystemAccountService;
use App\Domains\Accounting\Support\SystemAccount;
use App\Domains\HRMS\Models\Asset;
use App\Domains\HRMS\Models\AssetCategory;
use InvalidArgumentException;

/**
 * Which ledgers an asset's postings use.
 *
 * - A category's own mapped ledger wins.
 * - Otherwise the default chart's sub-ledger that matches the category name
 *   (Plant & Machinery -> 1502 ...), so an unmapped category no longer lands
 *   on the 1500 group header. 1500 is only the last resort.
 * - Once an asset is on the books, later postings (revaluation, disposal,
 *   write-off) use the ledger it actually sits in, so an asset capitalized to
 *   1500 before this rule is also taken off 1500.
 * - GST is split into CGST + SGST (intra-state) or IGST (inter-state) ledgers,
 *   falling back to the Duties & Taxes header only if those are missing.
 */
class AssetLedgers
{
    public const GST_INTRA = 'cgst_sgst';
    public const GST_INTER = 'igst';
    public const GST_TYPES = [self::GST_INTRA, self::GST_INTER];

    /** Category-name keywords => default chart sub-ledger, first match wins. */
    private const NAME_KEYS = [
        'land' => 'land_and_building',
        'building' => 'land_and_building',
        'plant' => 'plant_and_machinery',
        'machin' => 'plant_and_machinery',
        'computer' => 'computers_and_it_equipment',
        'laptop' => 'computers_and_it_equipment',
        'desktop' => 'computers_and_it_equipment',
        ' it ' => 'computers_and_it_equipment',
        'peripheral' => 'computers_and_it_equipment',
        'server' => 'computers_and_it_equipment',
        'furniture' => 'furniture_and_fixtures',
        'fixture' => 'furniture_and_fixtures',
        'vehicle' => 'vehicles',
        ' car' => 'vehicles',
        'office' => 'office_equipment',
        'equipment' => 'office_equipment',
    ];

    public function __construct(private readonly SystemAccountService $systemAccounts)
    {
    }

    public static function keyForCategoryName(?string $name): ?string
    {
        $haystack = ' ' . strtolower((string) $name) . ' ';

        foreach (self::NAME_KEYS as $needle => $key) {
            if (str_contains($haystack, $needle)) {
                return $key;
            }
        }

        return null;
    }

    /** Ledger a newly capitalized asset of this category is debited to. */
    public function forCategory(?AssetCategory $category, int $tenantId): ChartOfAccount
    {
        $account = $category?->chartOfAccount;

        if ($account === null && ($key = self::keyForCategoryName($category?->name)) !== null) {
            $account = $this->systemAccounts->get($key, $tenantId);
        }

        $account ??= $this->systemAccounts->get(SystemAccount::FIXED_ASSETS, $tenantId);

        if ($account === null) {
            throw new InvalidArgumentException('No fixed asset ledger: map one on the asset category or restore account ' . SystemAccount::templateCode(SystemAccount::FIXED_ASSETS) . '.');
        }

        return $account;
    }

    /**
     * Ledger the asset's cost currently sits in: the one its capitalization
     * journal debited. Assets bought through Purchase were debited by their
     * bill to the category ledger or 1500, so that rule applies to them.
     */
    public function holding(Asset $asset): ChartOfAccount
    {
        $journal = Journal::query()
            ->where('tenant_id', $asset->tenant_id)
            ->where('reference_type', AssetCapitalizationService::REFERENCE_TYPE)
            ->where('reference_id', $asset->id)
            ->where('status', Journal::STATUS_POSTED)
            ->latest('id')
            ->first();

        $line = $journal?->entries()
            ->where('debit', '>', 0)
            ->where('description', 'like', 'Capitalization%')
            ->first();

        if ($line !== null && ($account = ChartOfAccount::query()->find($line->chart_of_account_id)) !== null) {
            return $account;
        }

        $account = $asset->category?->chartOfAccount
            ?? $this->systemAccounts->get(SystemAccount::FIXED_ASSETS, (int) $asset->tenant_id);

        if ($account === null) {
            throw new InvalidArgumentException("No fixed asset ledger found for asset #{$asset->id}.");
        }

        return $account;
    }

    /**
     * When an asset leaves the books, whatever revaluation surplus it still
     * carries is realised: Ind AS 16 ¶41 moves it straight to retained
     * earnings (Reserves & Surplus), not through profit and loss.
     *
     * @return list<array<string, mixed>>
     */
    public function reserveTransferLines(Asset $asset): array
    {
        $tenantId = (int) $asset->tenant_id;
        $reserve = $this->systemAccounts->get(SystemAccount::REVALUATION_RESERVE, $tenantId);
        if ($reserve === null) {
            return [];
        }

        $journalIds = AssetRevaluation::query()
            ->where('asset_id', $asset->id)
            ->where('status', AssetRevaluation::STATUS_POSTED)
            ->whereNotNull('journal_id')
            ->pluck('journal_id');

        if ($journalIds->isEmpty()) {
            return [];
        }

        $totals = JournalEntry::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('journal_id', $journalIds)
            ->where('chart_of_account_id', $reserve->id)
            ->selectRaw('COALESCE(SUM(credit), 0) - COALESCE(SUM(debit), 0) as balance')
            ->value('balance');

        $balance = round((float) $totals, 2);
        if ($balance <= 0) {
            return [];
        }

        // No retained-earnings ledger: leave the reserve rather than block the sale.
        $retained = $this->systemAccounts->get(SystemAccount::RESERVES_AND_SURPLUS, $tenantId);
        if ($retained === null) {
            return [];
        }

        return [
            ['chart_of_account_id' => $reserve->id, 'debit' => $balance, 'description' => "Revaluation reserve realised - {$asset->asset_code}"],
            ['chart_of_account_id' => $retained->id, 'credit' => $balance, 'description' => "Revaluation reserve realised - {$asset->asset_code}"],
        ];
    }

    /**
     * Journal lines for GST: half CGST + half SGST, or all IGST.
     *
     * @return list<array<string, mixed>>
     */
    public function gstLines(float $amount, ?string $gstType, bool $output, int $tenantId, string $description): array
    {
        $amount = round($amount, 2);
        if ($amount <= 0) {
            return [];
        }

        $side = $output ? 'credit' : 'debit';
        $keys = $output
            ? [SystemAccount::OUTPUT_CGST, SystemAccount::OUTPUT_SGST, SystemAccount::OUTPUT_IGST, SystemAccount::OUTPUT_TAX]
            : [SystemAccount::INPUT_CGST, SystemAccount::INPUT_SGST, SystemAccount::INPUT_IGST, SystemAccount::INPUT_TAX];
        [$cgstKey, $sgstKey, $igstKey, $headerKey] = $keys;

        if (($gstType ?? self::GST_INTRA) === self::GST_INTER) {
            if ($igst = $this->systemAccounts->get($igstKey, $tenantId)) {
                return [['chart_of_account_id' => $igst->id, $side => $amount, 'description' => "IGST - {$description}"]];
            }
        } else {
            $cgst = $this->systemAccounts->get($cgstKey, $tenantId);
            $sgst = $this->systemAccounts->get($sgstKey, $tenantId);

            if ($cgst && $sgst) {
                $half = round($amount / 2, 2);

                return [
                    ['chart_of_account_id' => $cgst->id, $side => $half, 'description' => "CGST - {$description}"],
                    ['chart_of_account_id' => $sgst->id, $side => round($amount - $half, 2), 'description' => "SGST - {$description}"],
                ];
            }
        }

        $header = $this->systemAccounts->require($headerKey, $tenantId);

        return [['chart_of_account_id' => $header->id, $side => $amount, 'description' => "GST - {$description}"]];
    }
}
