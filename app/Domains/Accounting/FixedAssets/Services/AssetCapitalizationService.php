<?php

namespace App\Domains\Accounting\FixedAssets\Services;

use App\Domains\Accounting\FixedAssets\Events\AssetCapitalized;
use App\Domains\HRMS\Models\Asset;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Explicit "Capitalize as Fixed Asset" action (asset-mng.md §9) — deliberately
 * separate from the automatic GRN->Asset creation in CreateAssetFromGrnLine,
 * which only establishes physical custody at receipt. Capitalization is where
 * an asset gets its financial identity: capitalization cost, useful life,
 * depreciation method/start date, opening book value.
 */
class AssetCapitalizationService
{
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

        return DB::transaction(function () use (
            $asset, $acquisitionCost, $directCost, $recoverableTax, $nonRecoverableTax,
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

            event(new AssetCapitalized($asset));

            return $asset;
        });
    }
}
