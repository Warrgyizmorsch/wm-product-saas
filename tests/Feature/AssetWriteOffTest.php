<?php

namespace Tests\Feature;

use App\Core\Tenant\TenantContext;
use App\Domains\Accounting\FixedAssets\Models\AssetWriteOff;
use App\Domains\Accounting\FixedAssets\Services\AssetWriteOffService;
use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Services\FiscalPeriodService;
use App\Domains\HRMS\Models\Asset;
use App\Domains\HRMS\Models\AssetCategory;
use App\Domains\HRMS\Models\Company;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetWriteOffTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private AssetCategory $category;
    private Company $company;
    private AssetWriteOffService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Test Tenant', 'slug' => 'test-tenant', 'status' => 'active', 'plan' => 'enterprise',
        ]);

        $this->seed(RbacSeeder::class);
        app(TenantContext::class)->set($this->tenant);

        $this->user = User::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Accountant', 'email' => 'accountant@example.com', 'password' => bcrypt('password'),
        ]);

        $this->company = Company::create(['company_name' => 'Acme Co']);

        app(FiscalPeriodService::class)->createFiscalYearWithMonthlyPeriods([
            'tenant_id' => $this->tenant->id,
            'name' => 'FY ' . now()->year,
            'start_date' => now()->startOfYear()->toDateString(),
            'end_date' => now()->endOfYear()->toDateString(),
        ]);

        $fixedAssetAccount = ChartOfAccount::create([
            'tenant_id' => $this->tenant->id, 'code' => '1500', 'name' => 'Fixed Assets',
            'type' => ChartOfAccount::TYPE_ASSET, 'normal_balance' => ChartOfAccount::BALANCE_DEBIT,
            'is_system' => true, 'is_active' => true,
        ]);
        $accumulatedDepreciationAccount = ChartOfAccount::create([
            'tenant_id' => $this->tenant->id, 'code' => '1510', 'name' => 'Accumulated Depreciation',
            'type' => ChartOfAccount::TYPE_ASSET, 'normal_balance' => ChartOfAccount::BALANCE_CREDIT,
            'is_system' => true, 'is_active' => true,
        ]);
        $lossAccount = ChartOfAccount::create([
            'tenant_id' => $this->tenant->id, 'code' => '5910', 'name' => 'Loss on Asset Disposal',
            'type' => ChartOfAccount::TYPE_EXPENSE, 'normal_balance' => ChartOfAccount::BALANCE_DEBIT,
            'is_system' => true, 'is_active' => true,
        ]);

        $this->category = AssetCategory::create([
            'company_id' => $this->company->id,
            'name' => 'IT Equipment',
            'fixed_asset_account_id' => $fixedAssetAccount->id,
            'accumulated_depreciation_account_id' => $accumulatedDepreciationAccount->id,
            'loss_on_disposal_account_id' => $lossAccount->id,
        ]);

        $this->service = app(AssetWriteOffService::class);
    }

    private function makeActiveAsset(float $capitalizationCost, float $accumulatedDepreciation): Asset
    {
        return Asset::create([
            'company_id' => $this->company->id,
            'asset_category_id' => $this->category->id,
            'asset_code' => 'AST-WO-' . uniqid(),
            'name' => 'Laptop',
            'purchase_date' => now()->subYear()->toDateString(),
            'purchase_cost' => $capitalizationCost,
            'capitalization_cost' => $capitalizationCost,
            'residual_value' => 1000,
            'useful_life_months' => 36,
            'depreciation_method' => Asset::DEPRECIATION_METHOD_STRAIGHT_LINE,
            'depreciation_start_date' => now()->subYear()->toDateString(),
            'capitalization_date' => now()->subYear()->toDateString(),
            'accumulated_depreciation' => $accumulatedDepreciation,
            'book_value' => $capitalizationCost - $accumulatedDepreciation,
            'condition' => 'damaged',
            'status' => Asset::STATUS_ACTIVE,
        ]);
    }

    /** @test */
    public function writing_off_an_asset_posts_a_balanced_journal_and_never_deletes_the_asset(): void
    {
        $asset = $this->makeActiveAsset(30000, 10000); // NBV = 20000

        $writeOff = $this->service->writeOff($asset, [
            'write_off_date' => now()->toDateString(),
            'reason' => 'Damaged beyond repair in a flood.',
        ], $this->user->id);

        $this->assertEquals(20000.0, (float) $writeOff->net_book_value);

        $writeOff = $this->service->approve($writeOff, $this->user->id);
        $writeOff = $this->service->post($writeOff, $this->user->id);

        $this->assertSame(AssetWriteOff::STATUS_POSTED, $writeOff->status);

        $journal = Journal::withoutGlobalScopes()->findOrFail($writeOff->journal_id);
        $this->assertEquals((float) $journal->total_debit, (float) $journal->total_credit);
        $this->assertEquals(30000.0, (float) $journal->total_debit); // 10000 accum. depr. + 20000 loss

        $asset->refresh();
        $this->assertSame(Asset::STATUS_WRITTEN_OFF, $asset->status);
        $this->assertEquals(0.0, (float) $asset->book_value);

        // Never hard-deleted — the row must still exist.
        $this->assertDatabaseHas('assets', ['id' => $asset->id]);
    }

    /** @test */
    public function an_already_written_off_asset_cannot_be_written_off_again(): void
    {
        $asset = $this->makeActiveAsset(30000, 30000);
        $asset->update(['status' => Asset::STATUS_WRITTEN_OFF]);

        $this->expectException(\InvalidArgumentException::class);
        $this->service->writeOff($asset, [
            'write_off_date' => now()->toDateString(),
            'reason' => 'Already gone.',
        ], $this->user->id);
    }
}
