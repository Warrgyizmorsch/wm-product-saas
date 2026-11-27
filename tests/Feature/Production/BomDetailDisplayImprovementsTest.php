<?php

namespace Tests\Feature\Production;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Production\DTO\ProductionBomDTO;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionShift;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\SectionManualProduction;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Models\WorkCenterSection;
use App\Domains\Production\Services\ProductionBomService;
use App\Domains\Production\Services\ProductionCostService;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BomDetailDisplayImprovementsTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private Uom $uom;
    private WorkCenter $wc;
    private WorkCenterSection $section;
    private SectionManualProduction $batchPrimary;
    private SectionManualProduction $batchAlt;
    private Machine $machine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);

        $this->tenant = Tenant::create([
            'name' => 'BOM Detail UI Tenant',
            'slug' => 'bom-detail-ui',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'BOM Tester',
            'email' => 'bom-ui@example.test',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $role = Role::query()->whereNull('tenant_id')->where('slug', 'production_manager')->firstOrFail();
        UserRole::create([
            'user_id' => $this->user->id,
            'role_id' => $role->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $this->actingAs($this->user);
        app(\App\Core\Tenant\TenantContext::class)->setTenant($this->tenant);

        $this->uom = Uom::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Pieces',
            'code' => 'PCS',
        ]);

        $this->wc = WorkCenter::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Sub-Assembly Line',
            'code' => 'WC-SUB-1',
            'status' => WorkCenter::STATUS_ACTIVE,
        ]);

        $this->section = WorkCenterSection::create([
            'tenant_id' => $this->tenant->id,
            'work_center_id' => $this->wc->id,
            'name' => 'Assembly Bay',
            'code' => 'SEC-ASM',
            'status' => WorkCenterSection::STATUS_ACTIVE,
        ]);

        $shift = ProductionShift::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Morning',
            'code' => 'MORN',
            'start_time' => '06:00:00',
            'end_time' => '14:00:00',
            'break_minutes' => 0,
            'active' => true,
        ]);

        $this->batchPrimary = SectionManualProduction::create([
            'tenant_id' => $this->tenant->id,
            'section_id' => $this->section->id,
            'shift_id' => $shift->id,
            'batch_name' => 'batch-day-shift',
            'capacity_per_hour' => 10,
            'active' => true,
        ]);

        $this->batchAlt = SectionManualProduction::create([
            'tenant_id' => $this->tenant->id,
            'section_id' => $this->section->id,
            'shift_id' => $shift->id,
            'batch_name' => 'batch-morning-crew',
            'capacity_per_hour' => 8,
            'active' => true,
        ]);

        $this->machine = Machine::create([
            'tenant_id' => $this->tenant->id,
            'section_id' => $this->section->id,
            'work_center_id' => $this->wc->id,
            'name' => 'Auto Packager',
            'code' => 'MCH-PACK-1',
            'status' => 'active',
            'operating_cost_per_hour' => 60,
        ]);
    }

    private function makeProduct(string $name, string $sku, string $type, float $unitCost): Product
    {
        return Product::create([
            'tenant_id' => $this->tenant->id,
            'name' => $name,
            'sku' => $sku,
            'type' => $type,
            'supplier_method' => $type === 'raw_material' ? 'purchase' : 'manufacture',
            'status' => 'active',
            'uom_id' => $this->uom->id,
            'unit_cost' => $unitCost,
        ]);
    }

    public function test_bom_show_displays_updated_tabs_and_accurate_primary_vs_rollup_costs(): void
    {
        $fg = $this->makeProduct('Executive Table', 'FG-TBL-1', 'finished_good', 5000);
        $sf = $this->makeProduct('Base Chassis', 'SF-CHAS-1', 'semi_finished', 1400); // catalog 1400 vs sub-BOM
        $rawSteel = $this->makeProduct('Steel Tube', 'RM-TUBE-1', 'raw_material', 100);
        $rawBolt = $this->makeProduct('Bolt Set', 'RM-BOLT-1', 'raw_material', 50);
        $altBolt = $this->makeProduct('Titanium Bolt Set', 'RM-TBOLT-1', 'raw_material', 90);
        $rawBox = $this->makeProduct('Crate Box', 'RM-BOX-1', 'raw_material', 200);

        // Sub-BOM routing & BOM for SF (Base Chassis): 5 * Steel Tube (100) = 500 raw + 25/unit routing
        $sfRouting = Routing::create([
            'tenant_id' => $this->tenant->id,
            'routing_number' => 'RT-SF-1',
            'name' => 'Chassis Routing',
            'version' => '1.0.0',
            'status' => Routing::STATUS_ACTIVE,
            'effective_from' => now()->toDateString(),
        ]);

        $sfOp = RoutingOperation::create([
            'tenant_id' => $this->tenant->id,
            'routing_id' => $sfRouting->id,
            'sequence' => 10,
            'operation_number' => 'OP-10',
            'name' => 'Weld Chassis',
            'operation_type' => 'manufacturing',
            'work_center_id' => $this->wc->id,
            'section_id' => $this->section->id,
            'machine_id' => $this->machine->id,
            'setup_time_minutes' => 10,
            'setup_time_per_piece_minutes' => 1,
            'processing_time_minutes' => 2,
            'expected_yield_percentage' => 100,
            'labor_cost_rate' => 5.0,
            'machine_cost_rate' => 3.3333,
            'labor_cost_per_unit' => 15.0,
            'machine_cost_per_unit' => 10.0,
            'cost_per_unit' => 25.0,
            'setup_cost_per_run' => 83.333,
        ]);

        $bomSvc = app(ProductionBomService::class);
        $subBom = $bomSvc->create(ProductionBomDTO::fromArray([
            'bom_number' => 'BOM-SF-1',
            'bom_name' => 'Welded Base Chassis',
            'bom_type' => 'manufacturing',
            'product_id' => $sf->id,
            'routing_id' => $sfRouting->id,
            'base_quantity' => 1,
            'base_uom_id' => $this->uom->id,
            'version' => '1.0.0',
            'effective_date' => now()->toDateString(),
            'items' => [
                [
                    'row_uid' => 'sf1',
                    'routing_operation_id' => $sfOp->id,
                    'material_id' => $rawSteel->id,
                    'quantity' => 5,
                    'uom_id' => $this->uom->id,
                    'material_scrap_percentage' => 0,
                ],
            ],
        ]), $this->user->id);
        $subBom->update(['status' => ProductionBom::STATUS_APPROVED]);

        // Parent routing with primary OP-010, alternative OP-020, primary OP-030
        $fgRouting = Routing::create([
            'tenant_id' => $this->tenant->id,
            'routing_number' => 'RT-FG-1',
            'name' => 'Table Final Routing',
            'version' => '1.0.0',
            'status' => Routing::STATUS_ACTIVE,
            'effective_from' => now()->toDateString(),
        ]);

        $op10 = RoutingOperation::create([
            'tenant_id' => $this->tenant->id,
            'routing_id' => $fgRouting->id,
            'sequence' => 10,
            'operation_number' => 'OP-010',
            'name' => 'Table assembly',
            'operation_type' => 'manufacturing',
            'work_center_id' => $this->wc->id,
            'section_id' => $this->section->id,
            'section_manual_production_id' => $this->batchPrimary->id,
            'is_alternative' => false,
            'setup_time_minutes' => 10,
            'setup_time_per_piece_minutes' => 1,
            'processing_time_minutes' => 2,
            'expected_yield_percentage' => 100,
            'labor_cost_rate' => 10.0,
            'machine_cost_rate' => 0.0,
            'labor_cost_per_unit' => 30.0,
            'machine_cost_per_unit' => 0.0,
            'cost_per_unit' => 30.0,
            'setup_cost_per_run' => 100.0,
        ]);

        $op20 = RoutingOperation::create([
            'tenant_id' => $this->tenant->id,
            'routing_id' => $fgRouting->id,
            'sequence' => 20,
            'operation_number' => 'OP-020',
            'name' => 'Table assembly — morning crew',
            'operation_type' => 'manufacturing',
            'work_center_id' => $this->wc->id,
            'section_id' => $this->section->id,
            'section_manual_production_id' => $this->batchAlt->id,
            'is_alternative' => true,
            'replaces_operation_id' => $op10->id,
            'priority' => 1,
            'setup_time_minutes' => 10,
            'setup_time_per_piece_minutes' => 1,
            'processing_time_minutes' => 3,
            'expected_yield_percentage' => 100,
            'labor_cost_rate' => 6.0,
            'machine_cost_rate' => 0.0,
            'labor_cost_per_unit' => 24.0,
            'machine_cost_per_unit' => 0.0,
            'cost_per_unit' => 24.0,
            'setup_cost_per_run' => 60.0,
        ]);

        $op30 = RoutingOperation::create([
            'tenant_id' => $this->tenant->id,
            'routing_id' => $fgRouting->id,
            'sequence' => 30,
            'operation_number' => 'OP-030',
            'name' => 'Automated packing',
            'operation_type' => 'manufacturing',
            'work_center_id' => $this->wc->id,
            'section_id' => $this->section->id,
            'machine_id' => $this->machine->id,
            'is_alternative' => false,
            'setup_time_minutes' => 5,
            'setup_time_per_piece_minutes' => 0,
            'processing_time_minutes' => 1,
            'expected_yield_percentage' => 100,
            'labor_cost_rate' => 2.0,
            'machine_cost_rate' => 1.0,
            'labor_cost_per_unit' => 2.0,
            'machine_cost_per_unit' => 1.0,
            'cost_per_unit' => 3.0,
            'setup_cost_per_run' => 15.0,
        ]);

        // Parent BOM (base_quantity = 2 to verify per-unit scaling vs once-per-run setup!)
        $bom = $bomSvc->create(ProductionBomDTO::fromArray([
            'bom_number' => 'BOM-FG-1',
            'bom_name' => 'Executive Table BOM',
            'bom_type' => 'manufacturing',
            'product_id' => $fg->id,
            'routing_id' => $fgRouting->id,
            'base_quantity' => 2,
            'base_uom_id' => $this->uom->id,
            'version' => '1.0.0',
            'effective_date' => now()->toDateString(),
            'items' => [
                // Primary OP-010
                ['row_uid' => 'p1', 'routing_operation_id' => $op10->id, 'material_id' => $sf->id, 'child_bom_id' => $subBom->id, 'quantity' => 2, 'uom_id' => $this->uom->id, 'material_scrap_percentage' => 0],
                ['row_uid' => 'p2', 'routing_operation_id' => $op10->id, 'material_id' => $rawBolt->id, 'quantity' => 4, 'uom_id' => $this->uom->id, 'material_scrap_percentage' => 10], // gross 4.4 -> 4.4*50 = 220 (net 200, scrap 20)
                ['row_uid' => 'a1', 'routing_operation_id' => $op10->id, 'material_id' => $altBolt->id, 'quantity' => 4, 'uom_id' => $this->uom->id, 'material_scrap_percentage' => 0, 'is_alternative' => 1, 'replaces_row_uid' => 'p2', 'priority' => 1],
                // Alternative OP-020 (must NOT double-count!)
                ['row_uid' => 'alt_op_sf', 'routing_operation_id' => $op20->id, 'material_id' => $sf->id, 'child_bom_id' => $subBom->id, 'quantity' => 2, 'uom_id' => $this->uom->id, 'material_scrap_percentage' => 0],
                ['row_uid' => 'alt_op_bolt', 'routing_operation_id' => $op20->id, 'material_id' => $rawBolt->id, 'quantity' => 4, 'uom_id' => $this->uom->id, 'material_scrap_percentage' => 0],
                // Primary OP-030
                ['row_uid' => 'p3', 'routing_operation_id' => $op30->id, 'material_id' => $rawBox->id, 'quantity' => 2, 'uom_id' => $this->uom->id, 'material_scrap_percentage' => 0],
            ],
        ]), $this->user->id);

        // 1. Verify ProductionCostService primary baseline (excludes alt material & alt op OP-020!)
        // Direct materials for base_qty 2: sf (2 * 1400 = 2800) + rawBolt (4.4 * 50 = 220) + rawBox (2 * 200 = 400) = 3420
        // Scrap loss: 0.4 * 50 = 20
        // Labor (per-unit * base_qty): (30 + 2) * 2 = 64
        // Machine (per-unit * base_qty): (0 + 1) * 2 = 2
        // Setup / Run (separate, NOT * base_qty): 100 + 15 = 115
        $costSvc = app(ProductionCostService::class);
        $summary = $costSvc->calculateCost($bom->fresh());

        $this->assertEqualsWithDelta(3420.0, $summary['material_cost'], 0.001);
        $this->assertEqualsWithDelta(3400.0, $summary['net_material_cost'], 0.001);
        $this->assertEqualsWithDelta(20.0, $summary['scrap_adjustment'], 0.001);
        $this->assertEqualsWithDelta(64.0, $summary['labor_cost'], 0.001);
        $this->assertEqualsWithDelta(2.0, $summary['machine_cost'], 0.001);
        $this->assertEqualsWithDelta(66.0, $summary['routing_cost'], 0.001);
        $this->assertEqualsWithDelta(115.0, $summary['setup_cost_per_run'], 0.001);
        $this->assertEqualsWithDelta(3486.0, $summary['total_cost'], 0.001);
        $this->assertEqualsWithDelta(1743.0, $summary['cost_per_unit'], 0.001);

        // 2. Verify Multi-Level Roll-Up Cost
        // 2 * subBom (each 500 raw + 25 routing) = 1000 raw + 50 sub-routing
        // + direct raw leaves: rawBolt (220) + rawBox (400) = 620 -> rolled raw = 1620
        // + own routing (66) + sub routing (50) = 116 -> rolled total = 1736
        $rollup = $costSvc->calculateMultiLevelRollupCost($bom->fresh());
        $this->assertEqualsWithDelta(1620.0, $rollup['rolled_raw_material_cost'], 0.001);
        $this->assertEqualsWithDelta(50.0, $rollup['rolled_sub_routing_cost'], 0.001);
        $this->assertEqualsWithDelta(116.0, $rollup['rolled_routing_cost'], 0.001);
        $this->assertEqualsWithDelta(1736.0, $rollup['rolled_total_excl_setup'], 0.001);
        $this->assertEqualsWithDelta(868.0, $rollup['rolled_cost_per_unit'], 0.001);

        // 3. Verify BOM Show Page Rendering across all 4 updated tabs
        $response = $this->withHeaders(['X-Tenant' => $this->tenant->slug])
            ->get(route('production.boms.show', $bom->id));

        $response->assertOk();
        $html = $response->getContent();

        // Tab 1: Components has master list, does NOT have Allocations by operation
        $response->assertSee(__('production.bom_master_materials'));
        $response->assertDontSee(__('production.bom_allocations_by_operation'));

        // Tab 2: Multi-Level BOM Explosion removes standalone Level column, starts L1/L2 visible & L3 collapsed
        $this->assertStringNotContainsString('>' . __('production.level') . '</th>', $html);
        $this->assertMatchesRegularExpression('/data-level="2"[^>]*>/', $html);
        $this->assertStringContainsString('data-level="3" data-has-children="0" data-expanded="false"', $html);
        $this->assertMatchesRegularExpression('/class="bom-exp-row[^"]*d-none"[^>]*data-node-id="exp-1-0-0"/', $html);
        $response->assertSee('Expand All');
        $response->assertSee('Default (L1–L2 Only)');
        $response->assertSee('Produce Qty:');
        $response->assertSee('Calculate Materials');
        $response->assertSee('bomExplosionTableBody');

        // AJAX explosion endpoint updates only Tab 2 rows without full-page reload
        $ajaxRes = $this->withHeaders(['X-Tenant' => $this->tenant->slug])
            ->getJson(route('production.boms.explosion', $bom->id) . '?calc_qty=10');
        $ajaxRes->assertOk()
            ->assertJsonPath('calc_qty', 10);
        $ajaxHtml = $ajaxRes->json('html');
        $this->assertStringContainsString('bom-exp-row', $ajaxHtml);
        $this->assertStringContainsString('>50<', $ajaxHtml); // 5 Steel Tube * 10 = 50 (base qty 2 -> sf qty 2*10/2=10, 10*5=50)
        $this->assertStringContainsString('>22<', $ajaxHtml); // 4.4 Bolt Set * 10 / 2 = 22

        $this->withHeaders(['X-Tenant' => $this->tenant->slug])
            ->getJson(route('production.boms.explosion', $bom->id) . '?calc_qty=0')
            ->assertStatus(422);

        // Tab 3: Workflow & Routing shows alternative operation badge, manual batches, and full times/yield
        $response->assertSee(__('production.bom_alternative_operation'));
        $response->assertSee(__('production.bom_substitutes') . ' OP-010');
        $response->assertSee('batch-day-shift');
        $response->assertSee('batch-morning-crew');
        $response->assertSee('Manual Batch');
        $response->assertSee(__('production.bom_setup_per_piece'));

        // Tab 4: Cost Summary hides Work Center Overhead, shows Direct vs Rolled-Up, and renders Simulate Cost Modal
        $response->assertDontSee('Work Center Overhead');
        $response->assertSee('Direct Product Cost Total (Catalog Basis)');
        $response->assertSee('Rolled-Up Sub-BOM Cost Total (Multi-Level Basis)');
        $response->assertSee('Simulate Cost');
        $response->assertSee('bomCostSimulationModal');
        $response->assertSee('Excluded &mdash; Alt Op', false);
        $response->assertSee('Excluded &mdash; Alt Material', false);

        // ViewData simulator structure includes both route options and material alternatives
        $simData = $response->viewData('costSimulatorData');
        $this->assertTrue($simData['has_alternatives']);
        $this->assertSame(1, $simData['alt_ops_count']);
        $this->assertSame(1, $simData['alt_mats_count']);
    }
}
