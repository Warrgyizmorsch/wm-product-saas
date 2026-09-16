<?php

namespace Tests\Feature\Accounting;

use App\Core\Tenant\TenantContext;
use App\Domains\Accounting\FixedAssets\Models\AssetDepreciationSchedule;
use App\Domains\Accounting\FixedAssets\Models\AssetDisposal;
use App\Domains\HRMS\Models\Asset;
use App\Domains\HRMS\Models\AssetCategory;
use App\Domains\HRMS\Models\Company;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Deleting an asset with posted depreciation used to cascade-delete the
 * schedule too, leaving its already-posted journal pointing at nothing —
 * exactly what orphaned schedules 2-5 after HrmsDemoSeeder wiped `assets`.
 */
class AssetDeletionGuardTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $owner;
    private Company $company;
    private AssetCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
        $this->tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme', 'status' => 'active', 'plan' => 'enterprise']);
        app(TenantContext::class)->set($this->tenant);

        $this->owner = User::create(['tenant_id' => $this->tenant->id, 'name' => 'Owner', 'email' => 'owner@acme.test', 'password' => bcrypt('password')]);
        UserRole::create([
            'user_id' => $this->owner->id,
            'role_id' => Role::query()->whereNull('tenant_id')->where('slug', 'tenant_owner')->firstOrFail()->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $this->company = Company::create(['tenant_id' => $this->tenant->id, 'company_name' => 'Acme Co']);
        $this->category = AssetCategory::create(['tenant_id' => $this->tenant->id, 'company_id' => $this->company->id, 'name' => 'Laptops']);
    }

    private function makeAsset(string $code): Asset
    {
        return Asset::create([
            'tenant_id' => $this->tenant->id, 'company_id' => $this->company->id, 'asset_category_id' => $this->category->id,
            'asset_code' => $code, 'name' => 'Laptop', 'purchase_date' => now()->subYear()->toDateString(),
            'purchase_cost' => 36000, 'capitalization_cost' => 36000, 'residual_value' => 0, 'useful_life_months' => 36,
            'depreciation_method' => Asset::DEPRECIATION_METHOD_STRAIGHT_LINE, 'accumulated_depreciation' => 0,
            'book_value' => 36000, 'condition' => 'good', 'status' => Asset::STATUS_ACTIVE,
        ]);
    }

    public function test_an_asset_with_posted_depreciation_cannot_be_deleted(): void
    {
        $asset = $this->makeAsset('AST-1');
        AssetDepreciationSchedule::create([
            'tenant_id' => $this->tenant->id, 'company_id' => $this->company->id, 'asset_id' => $asset->id,
            'period_year' => now()->year, 'period_month' => now()->month, 'period_start_date' => now()->startOfMonth()->toDateString(), 'period_end_date' => now()->endOfMonth()->toDateString(), 'opening_book_value' => 36000,
            'depreciation_amount' => 1000, 'closing_book_value' => 35000, 'method' => Asset::DEPRECIATION_METHOD_STRAIGHT_LINE, 'status' => AssetDepreciationSchedule::STATUS_POSTED,
        ]);

        $response = $this->actingAs($this->owner)->withHeader('X-Tenant', 'acme')
            ->delete(route('hrms.assets.destroy', $asset));

        $response->assertRedirect();
        $this->assertDatabaseHas('assets', ['id' => $asset->id]);
        $this->assertDatabaseHas('asset_depreciation_schedules', ['asset_id' => $asset->id]);
    }

    public function test_an_asset_with_only_a_draft_schedule_can_be_deleted(): void
    {
        $asset = $this->makeAsset('AST-2');
        AssetDepreciationSchedule::create([
            'tenant_id' => $this->tenant->id, 'company_id' => $this->company->id, 'asset_id' => $asset->id,
            'period_year' => now()->year, 'period_month' => now()->month, 'period_start_date' => now()->startOfMonth()->toDateString(), 'period_end_date' => now()->endOfMonth()->toDateString(), 'opening_book_value' => 36000,
            'depreciation_amount' => 1000, 'closing_book_value' => 35000, 'method' => Asset::DEPRECIATION_METHOD_STRAIGHT_LINE, 'status' => AssetDepreciationSchedule::STATUS_DRAFT,
        ]);

        $this->actingAs($this->owner)->withHeader('X-Tenant', 'acme')
            ->delete(route('hrms.assets.destroy', $asset))
            ->assertRedirect();

        $this->assertDatabaseMissing('assets', ['id' => $asset->id]);
    }

    public function test_an_asset_with_a_disposal_record_cannot_be_deleted(): void
    {
        $asset = $this->makeAsset('AST-3');
        AssetDisposal::create([
            'tenant_id' => $this->tenant->id, 'company_id' => $this->company->id, 'asset_id' => $asset->id,
            'disposal_type' => 'sale', 'disposal_date' => now()->toDateString(), 'original_cost' => 36000,
            'accumulated_depreciation_at_disposal' => 0, 'net_book_value' => 36000, 'sale_proceeds' => 36000,
            'gain_loss_amount' => 0, 'status' => AssetDisposal::STATUS_PENDING_APPROVAL, 'requested_by' => $this->owner->id,
        ]);

        $this->actingAs($this->owner)->withHeader('X-Tenant', 'acme')
            ->delete(route('hrms.assets.destroy', $asset))
            ->assertRedirect();

        $this->assertDatabaseHas('assets', ['id' => $asset->id]);
    }

    /**
     * Simulates the orphaned rows the demo seeder left behind before its fix (it
     * truncated `assets` with the real foreign key checks disabled — something
     * this test can't reproduce via Eloquent, since SQLite ignores PRAGMA
     * foreign_keys changes inside RefreshDatabase's wrapping transaction). Instead
     * this renders the view directly against a bare stdClass standing in for a
     * schedule whose asset() relation resolved to null, exactly like Eloquent
     * would give the controller for a dangling asset_id.
     */
    public function test_a_deleted_assets_schedule_shows_as_deleted_asset_instead_of_erroring(): void
    {
        $orphanSchedule = (object) [
            'id' => 1, 'asset_id' => 999999, 'asset' => null,
            'opening_book_value' => 36000, 'depreciation_amount' => 1000, 'closing_book_value' => 35000,
            'status' => AssetDepreciationSchedule::STATUS_POSTED,
        ];
        $schedules = new \Illuminate\Pagination\LengthAwarePaginator([$orphanSchedule], 1, 15);

        // The layout's own header/sidebar partials read the authenticated user,
        // and normally ShareErrorsFromSession middleware supplies $errors.
        $this->actingAs($this->owner);
        view()->share('errors', new \Illuminate\Support\ViewErrorBag());

        $html = view('modules.accounting.fixed-assets.depreciation.index', [
            'schedules' => $schedules, 'year' => now()->year, 'month' => now()->month, 'status' => null,
            'canGenerate' => false, 'canPost' => false,
        ])->render();

        $this->assertStringContainsString('Deleted asset #999999', $html);
    }
}
