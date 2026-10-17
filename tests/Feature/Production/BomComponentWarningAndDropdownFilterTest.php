<?php

namespace Tests\Feature\Production;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionBomItem;
use App\Domains\Production\Services\ProductionBomService;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BomComponentWarningAndDropdownFilterTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private Uom $uomPcs;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);

        $this->tenant = Tenant::create([
            'name' => 'BOM Test Tenant',
            'slug' => 'bom-tenant-' . uniqid(),
            'domain' => 'bom-tenant-' . uniqid() . '.localhost',
            'is_active' => true,
        ]);

        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'BOM Manager',
            'email' => 'bom-mgr-' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'role' => 'production_manager',
        ]);

        $this->uomPcs = Uom::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Pieces',
            'code' => 'PCS',
            'unit_type' => 'unit',
            'ratio' => 1.0,
            'is_active' => true,
        ]);
    }

    public function test_bom_create_product_dropdown_only_lists_manufactured_products(): void
    {
        // 1. Manufactured products (should appear)
        $mfgFG = Product::create([
            'tenant_id' => $this->tenant->id,
            'sku' => 'FG-MFG-001',
            'name' => 'Manufactured Dining Table',
            'type' => 'finished_good',
            'supplier_method' => 'manufacture',
            'uom_id' => $this->uomPcs->id,
        ]);

        $mfgSFG = Product::create([
            'tenant_id' => $this->tenant->id,
            'sku' => 'SFG-MFG-001',
            'name' => 'Manufactured Table Leg',
            'type' => 'semi_finished',
            'supplier_method' => 'manufacture',
            'uom_id' => $this->uomPcs->id,
        ]);

        // 2. Both (manufacture and trade) products (should also appear)
        $bothFG = Product::create([
            'tenant_id' => $this->tenant->id,
            'sku' => 'FG-BOTH-001',
            'name' => 'Hybrid Manufactured and Traded Product',
            'type' => 'finished_good',
            'supplier_method' => 'both',
            'uom_id' => $this->uomPcs->id,
        ]);

        // 3. Traded products (must NOT appear in Item to Produce dropdown)
        $tradedFG = Product::create([
            'tenant_id' => $this->tenant->id,
            'sku' => 'FG-TRADE-001',
            'name' => 'Imported Traded Chair',
            'type' => 'finished_good',
            'supplier_method' => 'trade',
            'uom_id' => $this->uomPcs->id,
        ]);

        $tradedRaw = Product::create([
            'tenant_id' => $this->tenant->id,
            'sku' => 'RM-TRADE-001',
            'name' => 'Traded Steel Pipe',
            'type' => 'raw_material',
            'supplier_method' => 'trade',
            'uom_id' => $this->uomPcs->id,
        ]);

        session(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->get(route('production.boms.create'));

        $response->assertOk();
        $response->assertViewHas('products', function ($products) use ($mfgFG, $mfgSFG, $bothFG, $tradedFG, $tradedRaw) {
            $ids = $products->pluck('id')->toArray();
            return in_array($mfgFG->id, $ids, true)
                && in_array($mfgSFG->id, $ids, true)
                && in_array($bothFG->id, $ids, true)
                && !in_array($tradedFG->id, $ids, true)
                && !in_array($tradedRaw->id, $ids, true);
        });
    }

    public function test_component_sub_bom_dropdown_only_lists_approved_active_or_scheduled_boms(): void
    {
        $compProduct = Product::create([
            'tenant_id' => $this->tenant->id,
            'sku' => 'SFG-LEG-SUB',
            'name' => 'Steel Leg Sub-Assembly',
            'type' => 'semi_finished',
            'supplier_method' => 'manufacture',
            'uom_id' => $this->uomPcs->id,
        ]);

        $today = Carbon::today();

        // 1. Active approved BOM (should appear)
        $activeBom = ProductionBom::create([
            'tenant_id' => $this->tenant->id,
            'bom_number' => 'BOM-SUB-ACTIVE',
            'bom_name' => 'Active Sub-BOM',
            'bom_type' => 'manufacturing',
            'product_id' => $compProduct->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $this->uomPcs->id,
            'version' => '1.0.0',
            'status' => 'approved',
            'effective_date' => $today->copy()->subDays(5)->toDateString(),
            'expiry_date' => null,
            'created_by' => $this->user->id,
        ]);

        // 2. Scheduled approved BOM (should appear)
        $scheduledBom = ProductionBom::create([
            'tenant_id' => $this->tenant->id,
            'bom_number' => 'BOM-SUB-SCHED',
            'bom_name' => 'Scheduled Sub-BOM',
            'bom_type' => 'manufacturing',
            'product_id' => $compProduct->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $this->uomPcs->id,
            'version' => '2.0.0',
            'status' => 'scheduled',
            'effective_date' => $today->copy()->addDays(10)->toDateString(),
            'expiry_date' => null,
            'created_by' => $this->user->id,
        ]);

        // 3. Cancelled BOM (must NOT appear)
        $cancelledBom = ProductionBom::create([
            'tenant_id' => $this->tenant->id,
            'bom_number' => 'BOM-SUB-CANCELLED',
            'bom_name' => 'Cancelled Sub-BOM',
            'bom_type' => 'manufacturing',
            'product_id' => $compProduct->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $this->uomPcs->id,
            'version' => '0.9.0',
            'status' => 'cancelled',
            'effective_date' => $today->copy()->subDays(20)->toDateString(),
            'created_by' => $this->user->id,
        ]);

        // 4. Inactive BOM (must NOT appear)
        $inactiveBom = ProductionBom::create([
            'tenant_id' => $this->tenant->id,
            'bom_number' => 'BOM-SUB-INACTIVE',
            'bom_name' => 'Inactive Sub-BOM',
            'bom_type' => 'manufacturing',
            'product_id' => $compProduct->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $this->uomPcs->id,
            'version' => '0.8.0',
            'status' => 'inactive',
            'effective_date' => $today->copy()->subDays(30)->toDateString(),
            'created_by' => $this->user->id,
        ]);

        // 5. Expired BOM (must NOT appear)
        $expiredBom = ProductionBom::create([
            'tenant_id' => $this->tenant->id,
            'bom_number' => 'BOM-SUB-EXPIRED',
            'bom_name' => 'Expired Sub-BOM',
            'bom_type' => 'manufacturing',
            'product_id' => $compProduct->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $this->uomPcs->id,
            'version' => '0.7.0',
            'status' => 'approved',
            'effective_date' => $today->copy()->subDays(30)->toDateString(),
            'expiry_date' => $today->copy()->subDays(2)->toDateString(),
            'created_by' => $this->user->id,
        ]);

        // 6. Draft BOM (must NOT appear)
        $draftBom = ProductionBom::create([
            'tenant_id' => $this->tenant->id,
            'bom_number' => 'BOM-SUB-DRAFT',
            'bom_name' => 'Draft Sub-BOM',
            'bom_type' => 'manufacturing',
            'product_id' => $compProduct->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $this->uomPcs->id,
            'version' => '3.0.0',
            'status' => 'draft',
            'effective_date' => $today->toDateString(),
            'created_by' => $this->user->id,
        ]);

        session(['tenant_id' => $this->tenant->id]);

        $response = $this->actingAs($this->user)
            ->withHeader('X-Tenant', $this->tenant->slug)
            ->get(route('production.boms.check-child', $compProduct->id));

        $response->assertOk();
        $data = $response->json();

        $versionIds = collect($data['versions'])->pluck('id')->toArray();

        $this->assertContains($activeBom->id, $versionIds, 'Active approved BOM should be listed');
        $this->assertContains($scheduledBom->id, $versionIds, 'Scheduled approved BOM should be listed');
        $this->assertNotContains($cancelledBom->id, $versionIds, 'Cancelled BOM must NOT be listed');
        $this->assertNotContains($inactiveBom->id, $versionIds, 'Inactive BOM must NOT be listed');
        $this->assertNotContains($expiredBom->id, $versionIds, 'Expired BOM must NOT be listed');
        $this->assertNotContains($draftBom->id, $versionIds, 'Draft BOM must NOT be listed');
    }

    public function test_parent_bom_gets_warning_status_when_component_bom_is_cancelled(): void
    {
        $compProduct = Product::create([
            'tenant_id' => $this->tenant->id,
            'sku' => 'SFG-SUB-FRAME',
            'name' => 'Sub Frame',
            'type' => 'semi_finished',
            'supplier_method' => 'manufacture',
            'uom_id' => $this->uomPcs->id,
        ]);

        $parentProduct = Product::create([
            'tenant_id' => $this->tenant->id,
            'sku' => 'FG-BIG-TABLE',
            'name' => 'Complete Big Table',
            'type' => 'finished_good',
            'supplier_method' => 'manufacture',
            'uom_id' => $this->uomPcs->id,
        ]);

        // Create child BOM
        $childBom = ProductionBom::create([
            'tenant_id' => $this->tenant->id,
            'bom_number' => 'BOM-CHILD-001',
            'bom_name' => 'Child BOM v1',
            'bom_type' => 'manufacturing',
            'product_id' => $compProduct->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $this->uomPcs->id,
            'version' => '1.0.0',
            'status' => 'approved',
            'effective_date' => Carbon::today()->subDays(5)->toDateString(),
            'created_by' => $this->user->id,
        ]);

        // Create parent BOM referencing child BOM
        $parentBom = ProductionBom::create([
            'tenant_id' => $this->tenant->id,
            'bom_number' => 'BOM-PARENT-001',
            'bom_name' => 'Parent Table BOM',
            'bom_type' => 'manufacturing',
            'product_id' => $parentProduct->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $this->uomPcs->id,
            'version' => '1.0.0',
            'status' => 'approved',
            'effective_date' => Carbon::today()->toDateString(),
            'has_warning' => false,
            'created_by' => $this->user->id,
        ]);

        ProductionBomItem::create([
            'tenant_id' => $this->tenant->id,
            'bom_id' => $parentBom->id,
            'material_id' => $compProduct->id,
            'child_bom_id' => $childBom->id,
            'quantity' => 1.0,
            'uom_id' => $this->uomPcs->id,
            'sequence' => 1,
        ]);

        $parentBom->refreshWarningStatus();
        $this->assertFalse((bool) $parentBom->fresh()->has_warning, 'Initially parent BOM has no warnings');

        // Cancel child BOM using service
        $bomService = app(ProductionBomService::class);
        $bomService->cancel($childBom->id, $this->user->id, 'Obsolescence');

        $parentBom->refresh();
        $this->assertTrue((bool) $parentBom->has_warning, 'Parent BOM gets warning status when component BOM is cancelled');
        $this->assertStringContainsString('cancelled', strtolower($parentBom->warning_reason));
    }

    public function test_parent_bom_gets_warning_status_when_component_bom_version_is_updated(): void
    {
        $compProduct = Product::create([
            'tenant_id' => $this->tenant->id,
            'sku' => 'SFG-SUB-PANEL',
            'name' => 'Sub Panel',
            'type' => 'semi_finished',
            'supplier_method' => 'manufacture',
            'uom_id' => $this->uomPcs->id,
        ]);

        $parentProduct = Product::create([
            'tenant_id' => $this->tenant->id,
            'sku' => 'FG-DESK-001',
            'name' => 'Office Desk',
            'type' => 'finished_good',
            'supplier_method' => 'manufacture',
            'uom_id' => $this->uomPcs->id,
        ]);

        // Create child BOM v1.0.0
        $childBomV1 = ProductionBom::create([
            'tenant_id' => $this->tenant->id,
            'bom_number' => 'BOM-PANEL-001',
            'bom_name' => 'Panel BOM v1',
            'bom_type' => 'manufacturing',
            'product_id' => $compProduct->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $this->uomPcs->id,
            'version' => '1.0.0',
            'status' => 'approved',
            'effective_date' => Carbon::today()->subDays(10)->toDateString(),
            'created_by' => $this->user->id,
        ]);

        // Parent BOM references child BOM v1.0.0
        $parentBom = ProductionBom::create([
            'tenant_id' => $this->tenant->id,
            'bom_number' => 'BOM-DESK-001',
            'bom_name' => 'Desk BOM',
            'bom_type' => 'manufacturing',
            'product_id' => $parentProduct->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $this->uomPcs->id,
            'version' => '1.0.0',
            'status' => 'approved',
            'effective_date' => Carbon::today()->subDays(5)->toDateString(),
            'has_warning' => false,
            'created_by' => $this->user->id,
        ]);

        ProductionBomItem::create([
            'tenant_id' => $this->tenant->id,
            'bom_id' => $parentBom->id,
            'material_id' => $compProduct->id,
            'child_bom_id' => $childBomV1->id,
            'quantity' => 2.0,
            'uom_id' => $this->uomPcs->id,
            'sequence' => 1,
        ]);

        $parentBom->refreshWarningStatus();
        $this->assertFalse((bool) $parentBom->fresh()->has_warning, 'Parent BOM starts without warnings');

        // Now create and approve child BOM v2.0.0
        $childBomV2 = ProductionBom::create([
            'tenant_id' => $this->tenant->id,
            'bom_number' => 'BOM-PANEL-002',
            'bom_name' => 'Panel BOM v2',
            'bom_type' => 'manufacturing',
            'product_id' => $compProduct->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $this->uomPcs->id,
            'version' => '2.0.0',
            'status' => 'pending_approval',
            'effective_date' => Carbon::today()->toDateString(),
            'created_by' => $this->user->id,
        ]);

        $bomService = app(ProductionBomService::class);
        $bomService->approve($childBomV2->id, $this->user->id);

        $parentBom->refresh();
        $this->assertTrue((bool) $parentBom->has_warning, 'Parent BOM gets warning when child BOM version is updated');
        $this->assertStringContainsString('v1.0.0 [Status: Inactive]', $parentBom->warning_reason);
        $this->assertStringContainsString('v2.0.0 [Status: Active]', $parentBom->warning_reason);
        $this->assertEquals('1.0.0', $parentBom->version, 'Parent BOM version is NOT automatically altered');
    }

    public function test_cancelled_or_inactive_parent_bom_does_not_show_warning(): void
    {
        $compProduct = Product::create([
            'tenant_id' => $this->tenant->id,
            'sku' => 'SFG-SUB-BRACKET',
            'name' => 'Mounting Bracket',
            'type' => 'semi_finished',
            'supplier_method' => 'manufacture',
            'uom_id' => $this->uomPcs->id,
        ]);

        $parentProduct = Product::create([
            'tenant_id' => $this->tenant->id,
            'sku' => 'FG-WALL-UNIT',
            'name' => 'Wall Unit',
            'type' => 'finished_good',
            'supplier_method' => 'manufacture',
            'uom_id' => $this->uomPcs->id,
        ]);

        $childBom = ProductionBom::create([
            'tenant_id' => $this->tenant->id,
            'bom_number' => 'BOM-BRACKET-001',
            'bom_name' => 'Bracket BOM v1',
            'bom_type' => 'manufacturing',
            'product_id' => $compProduct->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $this->uomPcs->id,
            'version' => '1.0.0',
            'status' => 'approved',
            'effective_date' => Carbon::today()->subDays(5)->toDateString(),
            'created_by' => $this->user->id,
        ]);

        // 1. Create an active parent BOM
        $parentBom = ProductionBom::create([
            'tenant_id' => $this->tenant->id,
            'bom_number' => 'BOM-WALL-001',
            'bom_name' => 'Wall Unit BOM',
            'bom_type' => 'manufacturing',
            'product_id' => $parentProduct->id,
            'base_quantity' => 1.0,
            'base_uom_id' => $this->uomPcs->id,
            'version' => '1.0.0',
            'status' => 'approved',
            'effective_date' => Carbon::today()->toDateString(),
            'has_warning' => false,
            'created_by' => $this->user->id,
        ]);

        ProductionBomItem::create([
            'tenant_id' => $this->tenant->id,
            'bom_id' => $parentBom->id,
            'material_id' => $compProduct->id,
            'child_bom_id' => $childBom->id,
            'quantity' => 4.0,
            'uom_id' => $this->uomPcs->id,
            'sequence' => 1,
        ]);

        // Cancel child BOM -> active parent BOM gets warning
        $bomService = app(ProductionBomService::class);
        $bomService->cancel($childBom->id, $this->user->id, 'Bracket obsolete');

        $parentBom->refresh();
        $this->assertTrue((bool) $parentBom->has_warning, 'Active parent BOM gets warning');

        // Now cancel the parent BOM -> warning must be cleared
        $bomService->cancel($parentBom->id, $this->user->id, 'Parent discontinued');

        $parentBom->refresh();
        $this->assertEquals('cancelled', $parentBom->status);
        $this->assertFalse((bool) $parentBom->has_warning, 'Cancelled parent BOM has warning flag cleared');
        $this->assertNull($parentBom->warning_reason, 'Cancelled parent BOM warning reason is null');
        $this->assertFalse($parentBom->hasComponentWarning(), 'hasComponentWarning() returns false for cancelled BOM');
        $this->assertEmpty($parentBom->getComponentWarnings(), 'getComponentWarnings() returns empty for cancelled BOM');

        // Test with inactive parent BOM
        $parentBom->update(['status' => 'inactive']);
        $parentBom->refreshWarningStatus();
        $this->assertFalse((bool) $parentBom->fresh()->has_warning, 'Inactive parent BOM does not show warning');
        $this->assertFalse($parentBom->hasComponentWarning());
    }
}

