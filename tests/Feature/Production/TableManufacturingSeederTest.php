<?php

namespace Tests\Feature\Production;

use App\Domains\Inventory\Models\Product;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\WorkCenter;
use App\Domains\Production\Services\ProductionOrderService;
use App\Domains\Production\Services\SchedulingService;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\TableManufacturingProductSeeder;
use Database\Seeders\TableManufacturingProductionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TableManufacturingSeederTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::create([
            'name' => 'Demo Tenant',
            'slug' => 'warrgyizmorsch',
            'status' => 'active',
            'plan' => 'enterprise',
        ]);

        $this->user = User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Demo Admin',
            'email' => 'admin@example.com',
            'password' => bcrypt('password'),
        ]);

        // Run Table Manufacturing seeders
        $this->seed(TableManufacturingProductSeeder::class);
        $this->seed(TableManufacturingProductionSeeder::class);
    }

    public function test_seeder_creates_expected_master_data(): void
    {
        // Products
        $this->assertDatabaseHas('products', ['tenant_id' => $this->tenant->id, 'sku' => 'FG-TBL-001']);
        $this->assertDatabaseHas('products', ['tenant_id' => $this->tenant->id, 'sku' => 'SFG-TBL-FRAME']);
        $this->assertDatabaseHas('products', ['tenant_id' => $this->tenant->id, 'sku' => 'SFG-TBL-LEG']);
        $this->assertDatabaseHas('products', ['tenant_id' => $this->tenant->id, 'sku' => 'SFG-TBL-SUPPORT']);
        $this->assertDatabaseHas('products', ['tenant_id' => $this->tenant->id, 'sku' => 'SFG-TBL-TOP']);
        $this->assertDatabaseHas('products', ['tenant_id' => $this->tenant->id, 'sku' => 'RM-TBL-PIPE']);
        $this->assertDatabaseHas('products', ['tenant_id' => $this->tenant->id, 'sku' => 'RM-TBL-TOP-BOARD']);
        $this->assertDatabaseHas('products', ['tenant_id' => $this->tenant->id, 'sku' => 'RM-TBL-FASTENER']);

        // Work Centers
        $this->assertEquals(5, WorkCenter::where('tenant_id', $this->tenant->id)->where('code', 'LIKE', 'WC-TBL-%')->count());

        // Machines
        $this->assertEquals(6, Machine::where('tenant_id', $this->tenant->id)->where('code', 'LIKE', 'MAC-TBL-%')->count());

        // Verify Routing count
        $this->assertEquals(5, Routing::where('tenant_id', $this->tenant->id)->where('routing_number', 'LIKE', 'RT-TBL-%')->count());

        // Verify BOM Routing links and Child BOM links
        $bomFg = ProductionBom::where('tenant_id', $this->tenant->id)->where('bom_number', 'BOM-TBL-FG')->firstOrFail();
        $bomFrame = ProductionBom::where('tenant_id', $this->tenant->id)->where('bom_number', 'BOM-TBL-FRAME')->firstOrFail();
        $bomLeg = ProductionBom::where('tenant_id', $this->tenant->id)->where('bom_number', 'BOM-TBL-LEG')->firstOrFail();
        $bomSupport = ProductionBom::where('tenant_id', $this->tenant->id)->where('bom_number', 'BOM-TBL-SUPPORT')->firstOrFail();
        $bomTop = ProductionBom::where('tenant_id', $this->tenant->id)->where('bom_number', 'BOM-TBL-TOP')->firstOrFail();

        $rtFg = Routing::where('tenant_id', $this->tenant->id)->where('routing_number', 'RT-TBL-FG')->firstOrFail();
        $rtFrame = Routing::where('tenant_id', $this->tenant->id)->where('routing_number', 'RT-TBL-FRAME')->firstOrFail();

        $this->assertEquals($rtFg->id, $bomFg->routing_id);
        $this->assertEquals($rtFrame->id, $bomFrame->routing_id);
        $this->assertNotNull($bomLeg->routing_id);
        $this->assertNotNull($bomSupport->routing_id);
        $this->assertNotNull($bomTop->routing_id);

        // Check child_bom_id linkages
        $frameItemInFg = $bomFg->items()->where('material_id', $bomFrame->product_id)->firstOrFail();
        $this->assertEquals($bomFrame->id, $frameItemInFg->child_bom_id);

        $legItemInFrame = $bomFrame->items()->where('material_id', $bomLeg->product_id)->firstOrFail();
        $this->assertEquals($bomLeg->id, $legItemInFrame->child_bom_id);
    }

    public function test_routing_operation_material_consumed_inputs_semantics(): void
    {
        $rmPipe = Product::where('tenant_id', $this->tenant->id)->where('sku', 'RM-TBL-PIPE')->firstOrFail();
        $rmTopBoard = Product::where('tenant_id', $this->tenant->id)->where('sku', 'RM-TBL-TOP-BOARD')->firstOrFail();
        $rmFastener = Product::where('tenant_id', $this->tenant->id)->where('sku', 'RM-TBL-FASTENER')->firstOrFail();
        $sfgLeg = Product::where('tenant_id', $this->tenant->id)->where('sku', 'SFG-TBL-LEG')->firstOrFail();
        $sfgSupport = Product::where('tenant_id', $this->tenant->id)->where('sku', 'SFG-TBL-SUPPORT')->firstOrFail();
        $sfgFrame = Product::where('tenant_id', $this->tenant->id)->where('sku', 'SFG-TBL-FRAME')->firstOrFail();

        // RT-TBL-LEG (OP10 Leg Cutting consumes RM-TBL-PIPE)
        $rtLeg = Routing::where('tenant_id', $this->tenant->id)->where('routing_number', 'RT-TBL-LEG')->firstOrFail();
        $op10 = $rtLeg->operations->first();
        $this->assertDatabaseHas('production_routing_operation_materials', [
            'tenant_id' => $this->tenant->id,
            'routing_operation_id' => $op10->id,
            'material_id' => $rmPipe->id,
            'quantity' => 0.75,
        ]);

        // RT-TBL-SUPPORT (OP20 Support Cutting consumes RM-TBL-PIPE)
        $rtSupport = Routing::where('tenant_id', $this->tenant->id)->where('routing_number', 'RT-TBL-SUPPORT')->firstOrFail();
        $op20 = $rtSupport->operations->first();
        $this->assertDatabaseHas('production_routing_operation_materials', [
            'tenant_id' => $this->tenant->id,
            'routing_operation_id' => $op20->id,
            'material_id' => $rmPipe->id,
            'quantity' => 0.60,
        ]);

        // RT-TBL-FRAME (OP30 Frame Welding consumes SFG-TBL-LEG and SFG-TBL-SUPPORT)
        $rtFrame = Routing::where('tenant_id', $this->tenant->id)->where('routing_number', 'RT-TBL-FRAME')->firstOrFail();
        $op30 = $rtFrame->operations->where('sequence', 30)->first();
        $this->assertDatabaseHas('production_routing_operation_materials', [
            'tenant_id' => $this->tenant->id,
            'routing_operation_id' => $op30->id,
            'material_id' => $sfgLeg->id,
            'quantity' => 4.0,
        ]);
        $this->assertDatabaseHas('production_routing_operation_materials', [
            'tenant_id' => $this->tenant->id,
            'routing_operation_id' => $op30->id,
            'material_id' => $sfgSupport->id,
            'quantity' => 2.0,
        ]);

        // RT-TBL-TOP (OP40 Top Processing consumes RM-TBL-TOP-BOARD)
        $rtTop = Routing::where('tenant_id', $this->tenant->id)->where('routing_number', 'RT-TBL-TOP')->firstOrFail();
        $op40 = $rtTop->operations->first();
        $this->assertDatabaseHas('production_routing_operation_materials', [
            'tenant_id' => $this->tenant->id,
            'routing_operation_id' => $op40->id,
            'material_id' => $rmTopBoard->id,
            'quantity' => 1.0,
        ]);

        // RT-TBL-FG (OP60 Final Assembly consumes Frame, Top, Fasteners)
        $rtFg = Routing::where('tenant_id', $this->tenant->id)->where('routing_number', 'RT-TBL-FG')->firstOrFail();
        $op60 = $rtFg->operations->first();
        $this->assertDatabaseHas('production_routing_operation_materials', [
            'tenant_id' => $this->tenant->id,
            'routing_operation_id' => $op60->id,
            'material_id' => $sfgFrame->id,
            'quantity' => 1.0,
        ]);
        $this->assertDatabaseHas('production_routing_operation_materials', [
            'tenant_id' => $this->tenant->id,
            'routing_operation_id' => $op60->id,
            'material_id' => $rmFastener->id,
            'quantity' => 1.0,
        ]);
    }

    public function test_seeder_idempotency_running_twice_creates_no_duplicates(): void
    {
        $productCount = Product::where('tenant_id', $this->tenant->id)->count();
        $wcCount = WorkCenter::where('tenant_id', $this->tenant->id)->count();
        $mchCount = Machine::where('tenant_id', $this->tenant->id)->count();
        $bomCount = ProductionBom::where('tenant_id', $this->tenant->id)->count();

        // Re-run seeders
        $this->seed(TableManufacturingProductSeeder::class);
        $this->seed(TableManufacturingProductionSeeder::class);

        $this->assertEquals($productCount, Product::where('tenant_id', $this->tenant->id)->count());
        $this->assertEquals($wcCount, WorkCenter::where('tenant_id', $this->tenant->id)->count());
        $this->assertEquals($mchCount, Machine::where('tenant_id', $this->tenant->id)->count());
        $this->assertEquals($bomCount, ProductionBom::where('tenant_id', $this->tenant->id)->count());
    }

    public function test_10_table_production_order_derives_correct_component_targets(): void
    {
        $fgTable = Product::where('tenant_id', $this->tenant->id)->where('sku', 'FG-TBL-001')->firstOrFail();
        $bomFg = ProductionBom::where('tenant_id', $this->tenant->id)->where('bom_number', 'BOM-TBL-FG')->firstOrFail();
        $routing = Routing::where('tenant_id', $this->tenant->id)->where('routing_number', 'RT-TBL-FG')->firstOrFail();

        // Create Production Order for 10 Industrial Dining Tables
        $order = ProductionOrder::create([
            'tenant_id' => $this->tenant->id,
            'order_number' => 'MO-TBL-TEST-10',
            'product_id' => $fgTable->id,
            'bom_id' => $bomFg->id,
            'routing_id' => $routing->id,
            'quantity_ordered' => 10.0,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(7)->toDateString(),
            'status' => ProductionOrder::STATUS_DRAFT,
        ]);

        // Snapshot Routings through real snapshot engine
        app(ProductionOrderService::class)->snapshotMultiLevelRoutings(
            $order,
            $bomFg,
            $routing,
            10.0,
            $this->tenant->id,
            $this->user->id
        );

        $order->refresh();

        $op10 = $order->operations()->where('sequence', 10)->firstOrFail();
        $op20 = $order->operations()->where('sequence', 20)->firstOrFail();
        $op30 = $order->operations()->where('sequence', 30)->firstOrFail();
        $op40 = $order->operations()->where('sequence', 40)->firstOrFail();
        $op50 = $order->operations()->where('sequence', 50)->firstOrFail();
        $op60 = $order->operations()->where('sequence', 60)->firstOrFail();

        // 10 Tables Output Targets:
        // OP10 (Table Leg): 10 FG * 4 Legs = 40 Legs
        $this->assertEquals(40.0, $op10->target_produced_qty);

        // OP20 (Horizontal Support): 10 FG * 2 Supports = 20 Supports
        $this->assertEquals(20.0, $op20->target_produced_qty);

        // OP30 (Table Frame): 10 FG * 1 Frame = 10 Frames
        $this->assertEquals(10.0, $op30->target_produced_qty);

        // OP40 (Table Top): 10 FG * 1 Top = 10 Tops
        $this->assertEquals(10.0, $op40->target_produced_qty);

        // OP50 (Surface Finishing): 10 FG * 1 Frame = 10 Frames
        $this->assertEquals(10.0, $op50->target_produced_qty);

        // OP60 (Final Assembly): 10 FG * 1 Table = 10 Tables
        $this->assertEquals(10.0, $op60->target_produced_qty);

        // Raw Material Inputs Calculation:
        // Steel Pipe for Legs = 40 * 0.75 = 30 Mtr
        // Steel Pipe for Supports = 20 * 0.60 = 12 Mtr
        // Total Steel Pipe = 42 Mtr
        $pipeForLegs = $op10->target_produced_qty * 0.75;
        $pipeForSupports = $op20->target_produced_qty * 0.60;
        $this->assertEquals(42.0, $pipeForLegs + $pipeForSupports);

        // Verify Scheduling Durations without double multiplication:
        // OP10 (40 Legs): 10m setup + (40 * 2m) = 90 min
        $times10 = app(SchedulingService::class)->calculateOperationTimes($op10, $op10->target_produced_qty);
        $this->assertEquals(90.0, $times10['total_minutes']);

        // OP20 (20 Supports): 10m setup + (20 * 2m) = 50 min
        $times20 = app(SchedulingService::class)->calculateOperationTimes($op20, $op20->target_produced_qty);
        $this->assertEquals(50.0, $times20['total_minutes']);

        // OP30 (10 Frames): 15m setup + (10 * 12m) = 135 min
        $times30 = app(SchedulingService::class)->calculateOperationTimes($op30, $op30->target_produced_qty);
        $this->assertEquals(135.0, $times30['total_minutes']);

        // OP40 (10 Tops): 10m setup + (10 * 8m) = 90 min
        $times40 = app(SchedulingService::class)->calculateOperationTimes($op40, $op40->target_produced_qty);
        $this->assertEquals(90.0, $times40['total_minutes']);

        // OP50 (10 Frames): 10m setup + (10 * 6m) = 70 min
        $times50 = app(SchedulingService::class)->calculateOperationTimes($op50, $op50->target_produced_qty);
        $this->assertEquals(70.0, $times50['total_minutes']);

        // OP60 (10 Tables): 10m setup + (10 * 15m) = 160 min
        $times60 = app(SchedulingService::class)->calculateOperationTimes($op60, $op60->target_produced_qty);
        $this->assertEquals(160.0, $times60['total_minutes']);
    }
}
