<?php

namespace Tests\Unit;

use App\Domains\Accounting\FixedAssets\Services\AssetDepreciationService;
use App\Domains\Accounting\Repositories\ChartOfAccountRepositoryInterface;
use App\Domains\Accounting\Services\JournalService;
use App\Domains\HRMS\Models\Asset;
use PHPUnit\Framework\TestCase;

/**
 * Pure calculation tests — no database, no framework boot. Deterministic
 * inputs against known expected outputs, per asset-mng.md §36's requirement
 * to test financial calculations independently of the posting pipeline.
 */
class AssetDepreciationCalculationTest extends TestCase
{
    private AssetDepreciationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new AssetDepreciationService(
            $this->createStub(JournalService::class),
            $this->createStub(ChartOfAccountRepositoryInterface::class),
        );
    }

    private function makeAsset(array $attributes): Asset
    {
        $asset = new Asset();
        $asset->forceFill($attributes);

        return $asset;
    }

    /** @test */
    public function straight_line_depreciation_is_a_flat_monthly_amount(): void
    {
        $asset = $this->makeAsset([
            'capitalization_cost' => 120000,
            'residual_value' => 12000,
            'useful_life_months' => 36,
            'depreciation_method' => Asset::DEPRECIATION_METHOD_STRAIGHT_LINE,
        ]);

        // (120000 - 12000) / 36 = 3000 flat, every period, regardless of opening book value.
        $this->assertSame(3000.0, $this->service->calculateMonthlyDepreciation($asset, 120000));
        $this->assertSame(3000.0, $this->service->calculateMonthlyDepreciation($asset, 60000));
    }

    /** @test */
    public function straight_line_depreciation_never_goes_below_residual_value(): void
    {
        $asset = $this->makeAsset([
            'capitalization_cost' => 120000,
            'residual_value' => 12000,
            'useful_life_months' => 36,
            'depreciation_method' => Asset::DEPRECIATION_METHOD_STRAIGHT_LINE,
        ]);

        // Only 1000 of depreciable value left above residual — must cap there, not the full 3000.
        $this->assertSame(1000.0, $this->service->calculateMonthlyDepreciation($asset, 13000));
        $this->assertSame(0.0, $this->service->calculateMonthlyDepreciation($asset, 12000));
    }

    /** @test */
    public function wdv_depreciation_applies_a_fixed_annual_rate_over_twelve_to_the_declining_book_value(): void
    {
        $asset = $this->makeAsset([
            'capitalization_cost' => 100000,
            'residual_value' => 10000,
            'useful_life_months' => 60, // 5 years
            'depreciation_method' => Asset::DEPRECIATION_METHOD_WDV,
        ]);

        // annual rate = 1 - (10000/100000)^(1/5) = 1 - 0.1^0.2 ≈ 0.36904
        $annualRate = 1 - ((10000 / 100000) ** (1 / 5));
        $monthlyRate = $annualRate / 12;

        $expectedFirstMonth = round(100000 * $monthlyRate, 2);
        $this->assertSame($expectedFirstMonth, $this->service->calculateMonthlyDepreciation($asset, 100000));

        // Declining balance: the second month's amount is smaller because the
        // opening book value it's applied to has already shrunk.
        $openingAfterMonth1 = 100000 - $expectedFirstMonth;
        $expectedSecondMonth = round($openingAfterMonth1 * $monthlyRate, 2);
        $this->assertSame($expectedSecondMonth, $this->service->calculateMonthlyDepreciation($asset, $openingAfterMonth1));
        $this->assertLessThan($expectedFirstMonth, $expectedSecondMonth);
    }

    /** @test */
    public function wdv_depreciation_never_goes_below_residual_value(): void
    {
        $asset = $this->makeAsset([
            'capitalization_cost' => 100000,
            'residual_value' => 10000,
            'useful_life_months' => 60,
            'depreciation_method' => Asset::DEPRECIATION_METHOD_WDV,
        ]);

        // Just above residual: the uncapped WDV amount (rate * opening) would
        // overshoot the tiny remaining depreciable balance, so it must be
        // capped to exactly what's left rather than dipping below residual.
        $this->assertSame(1.0, $this->service->calculateMonthlyDepreciation($asset, 10001));
        $this->assertSame(0.0, $this->service->calculateMonthlyDepreciation($asset, 10000));
    }

    /** @test */
    public function returns_zero_when_the_asset_has_not_been_capitalized(): void
    {
        $asset = $this->makeAsset([
            'capitalization_cost' => null,
            'residual_value' => 0,
            'useful_life_months' => null,
            'depreciation_method' => Asset::DEPRECIATION_METHOD_STRAIGHT_LINE,
        ]);

        $this->assertSame(0.0, $this->service->calculateMonthlyDepreciation($asset, 0));
    }

    /** @test */
    public function returns_zero_when_residual_value_equals_or_exceeds_capitalization_cost(): void
    {
        $asset = $this->makeAsset([
            'capitalization_cost' => 50000,
            'residual_value' => 50000,
            'useful_life_months' => 24,
            'depreciation_method' => Asset::DEPRECIATION_METHOD_STRAIGHT_LINE,
        ]);

        $this->assertSame(0.0, $this->service->calculateMonthlyDepreciation($asset, 50000));
    }
}
