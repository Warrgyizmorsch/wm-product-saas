<?php

namespace App\Domains\Accounting\Listeners;

use App\Core\Tenant\Events\TenantProvisioning;
use App\Domains\Accounting\Models\CostCenter;
use App\Domains\HRMS\Models\Asset;
use App\Domains\HRMS\Models\AssetCategory;

/**
 * Starter cost centers and fixed-asset categories for a new tenant. Asset
 * categories are left without their own GL accounts on purpose: the asset
 * services fall back to the default Fixed Assets / Accumulated Depreciation
 * accounts seeded by ProvisionChartOfAccounts.
 *
 * Idempotent: keyed on tenant + code (cost centers) or tenant + company +
 * name (asset categories), so `tenant:provision --all` never duplicates rows.
 */
class ProvisionCostCentersAndAssetCategories
{
    /** code => name */
    private const COST_CENTERS = [
        'ADMIN' => 'Administration',
        'SALES' => 'Sales',
        'PRODUCTION' => 'Production',
        'FINANCE' => 'Finance',
    ];

    /** name => [useful life months, method, residual %] */
    private const ASSET_CATEGORIES = [
        'Computer Equipment' => [36, Asset::DEPRECIATION_METHOD_STRAIGHT_LINE, 5],
        'Furniture & Fixtures' => [96, Asset::DEPRECIATION_METHOD_STRAIGHT_LINE, 5],
        'Office Equipment' => [60, Asset::DEPRECIATION_METHOD_STRAIGHT_LINE, 5],
        'Vehicles' => [60, Asset::DEPRECIATION_METHOD_WDV, 10],
        'Plant & Machinery' => [180, Asset::DEPRECIATION_METHOD_STRAIGHT_LINE, 5],
    ];

    public function handle(TenantProvisioning $event): void
    {
        if (! $event->includes('accounting')) {
            return;
        }

        $this->provisionCostCenters($event);
        $this->provisionAssetCategories($event);
    }

    private function provisionCostCenters(TenantProvisioning $event): void
    {
        foreach (self::COST_CENTERS as $code => $name) {
            CostCenter::query()->withoutGlobalScopes()->firstOrCreate(
                ['tenant_id' => $event->tenantId, 'code' => $code],
                [
                    'company_id' => $event->companyId,
                    'branch_id' => $event->branchId,
                    'name' => $name,
                    'is_active' => true,
                ],
            );
        }
    }

    private function provisionAssetCategories(TenantProvisioning $event): void
    {
        foreach (self::ASSET_CATEGORIES as $name => [$lifeMonths, $method, $residualPercent]) {
            AssetCategory::query()->withoutGlobalScopes()->firstOrCreate(
                ['tenant_id' => $event->tenantId, 'company_id' => $event->companyId, 'name' => $name],
                [
                    'status' => AssetCategory::STATUS_ACTIVE,
                    'default_useful_life_months' => $lifeMonths,
                    'default_depreciation_method' => $method,
                    'default_residual_value_percent' => $residualPercent,
                    'capitalization_threshold' => 5000,
                ],
            );
        }
    }
}
