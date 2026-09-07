<?php

namespace Tests\Feature\Production;

use App\Core\Tenant\TenantContext;
use App\Domains\HRMS\Models\Asset;
use App\Domains\HRMS\Models\AssetCategory;
use App\Domains\HRMS\Models\Company;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\WorkCenter;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MachineAssetLinkingTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Tenant $otherTenant;
    private User $admin;
    private WorkCenter $workCenter;
    private Company $company;
    private AssetCategory $machineryCategory;
    private AssetCategory $officeCategory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Tenant A',
            'slug' => 'tenant-a',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->otherTenant = Tenant::create([
            'name' => 'Tenant B',
            'slug' => 'tenant-b',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->admin = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Admin A',
            'email' => 'admina@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $this->workCenter = WorkCenter::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'WC Assembly',
            'code' => 'WC-ASSY',
            'status' => 'active',
        ]);

        app(TenantContext::class)->set($this->tenant);

        $this->company = Company::create([
            'tenant_id' => $this->tenant->id,
            'company_name' => 'Acme Co',
        ]);

        $this->machineryCategory = AssetCategory::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'name' => 'Production Machinery',
            'is_production_machinery' => true,
        ]);

        $this->officeCategory = AssetCategory::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'name' => 'IT Laptops',
            'is_production_machinery' => false,
        ]);
    }

    private function makeAsset(AssetCategory $category, string $code): Asset
    {
        return Asset::create([
            'tenant_id' => $this->tenant->id,
            'company_id' => $this->company->id,
            'asset_category_id' => $category->id,
            'asset_code' => $code,
            'name' => "{$code} Unit",
            'purchase_cost' => 250000,
            'condition' => 'new',
            'status' => 'available',
        ]);
    }

    public function test_unregistered_machinery_assets_are_surfaced_and_filtered(): void
    {
        $machineryAsset = $this->makeAsset($this->machineryCategory, 'AST-M-1');
        $this->makeAsset($this->officeCategory, 'AST-O-1'); // non-machinery, must not appear

        $linkedMachineryAsset = $this->makeAsset($this->machineryCategory, 'AST-M-2');
        Machine::create([
            'tenant_id' => $this->tenant->id,
            'work_center_id' => $this->workCenter->id,
            'asset_id' => $linkedMachineryAsset->id,
            'name' => 'Already Registered',
            'code' => 'MCH-REG',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->admin)
            ->withHeader('X-Tenant', 'tenant-a')
            ->get(route('production.machines.index'));

        $response->assertStatus(200);
        $response->assertSee('AST-M-1');
        $response->assertDontSee('AST-O-1');
        $response->assertDontSee('AST-M-2'); // already linked, should be filtered out
    }

    public function test_create_from_asset_prefills_and_still_requires_work_center(): void
    {
        $asset = $this->makeAsset($this->machineryCategory, 'AST-M-3');

        $createPage = $this->actingAs($this->admin)
            ->withHeader('X-Tenant', 'tenant-a')
            ->get(route('production.machines.create', ['asset_id' => $asset->id]));

        $createPage->assertStatus(200);
        $createPage->assertSee($asset->asset_code);

        // Missing work_center_id must still fail validation.
        $missingWorkCenter = $this->actingAs($this->admin)
            ->withHeader('X-Tenant', 'tenant-a')
            ->post(route('production.machines.store'), [
                'asset_id' => $asset->id,
                'name' => $asset->name,
                'code' => 'MCH-NEW-1',
                'status' => 'inactive',
            ]);
        $missingWorkCenter->assertSessionHasErrors(['work_center_id']);

        $response = $this->actingAs($this->admin)
            ->withHeader('X-Tenant', 'tenant-a')
            ->post(route('production.machines.store'), [
                'work_center_id' => $this->workCenter->id,
                'asset_id' => $asset->id,
                'name' => $asset->name,
                'code' => 'MCH-NEW-1',
                'status' => 'inactive',
            ]);

        $response->assertRedirect(route('production.machines.index'));
        $this->assertDatabaseHas('production_machines', [
            'tenant_id' => $this->tenant->id,
            'code' => 'MCH-NEW-1',
            'asset_id' => $asset->id,
        ]);
    }

    public function test_manual_link_rejects_duplicate_and_unlink_clears_without_touching_asset(): void
    {
        $asset = $this->makeAsset($this->machineryCategory, 'AST-M-4');

        $machineOne = Machine::create([
            'tenant_id' => $this->tenant->id,
            'work_center_id' => $this->workCenter->id,
            'name' => 'Machine One',
            'code' => 'MCH-ONE',
            'status' => 'active',
        ]);

        $machineTwo = Machine::create([
            'tenant_id' => $this->tenant->id,
            'work_center_id' => $this->workCenter->id,
            'name' => 'Machine Two',
            'code' => 'MCH-TWO',
            'status' => 'active',
        ]);

        $link = $this->actingAs($this->admin)
            ->withHeader('X-Tenant', 'tenant-a')
            ->post(route('production.machines.link-asset', $machineOne->id), ['asset_id' => $asset->id]);
        $link->assertSessionHas('success');
        $this->assertDatabaseHas('production_machines', ['id' => $machineOne->id, 'asset_id' => $asset->id]);

        $duplicateLink = $this->actingAs($this->admin)
            ->withHeader('X-Tenant', 'tenant-a')
            ->post(route('production.machines.link-asset', $machineTwo->id), ['asset_id' => $asset->id]);
        $duplicateLink->assertSessionHas('error');
        $this->assertDatabaseMissing('production_machines', ['id' => $machineTwo->id, 'asset_id' => $asset->id]);

        $unlink = $this->actingAs($this->admin)
            ->withHeader('X-Tenant', 'tenant-a')
            ->post(route('production.machines.unlink-asset', $machineOne->id));
        $unlink->assertSessionHas('success');
        $this->assertDatabaseHas('production_machines', ['id' => $machineOne->id, 'asset_id' => null]);
        $this->assertDatabaseHas('assets', ['id' => $asset->id, 'status' => 'available']);
    }

    public function test_linking_an_asset_from_another_tenant_is_rejected(): void
    {
        app(TenantContext::class)->set($this->otherTenant);
        $otherCompany = Company::create(['tenant_id' => $this->otherTenant->id, 'company_name' => 'Other Co']);
        $otherCategory = AssetCategory::create([
            'tenant_id' => $this->otherTenant->id,
            'company_id' => $otherCompany->id,
            'name' => 'Other Machinery',
            'is_production_machinery' => true,
        ]);
        $foreignAsset = Asset::create([
            'tenant_id' => $this->otherTenant->id,
            'company_id' => $otherCompany->id,
            'asset_category_id' => $otherCategory->id,
            'asset_code' => 'AST-FOREIGN-1',
            'name' => 'Foreign Unit',
            'purchase_cost' => 100000,
            'condition' => 'new',
            'status' => 'available',
        ]);
        app(TenantContext::class)->set($this->tenant);

        $machine = Machine::create([
            'tenant_id' => $this->tenant->id,
            'work_center_id' => $this->workCenter->id,
            'name' => 'Machine Cross Tenant',
            'code' => 'MCH-XT',
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->admin)
            ->withHeader('X-Tenant', 'tenant-a')
            ->post(route('production.machines.link-asset', $machine->id), ['asset_id' => $foreignAsset->id]);

        $response->assertSessionHas('error');
        $this->assertDatabaseHas('production_machines', ['id' => $machine->id, 'asset_id' => null]);
    }
}
