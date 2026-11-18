<?php

namespace Tests\Feature\Production;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\ProductWarehouseStock;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Inventory\Services\StockService;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderIssue;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionOrderProgressLog;
use App\Domains\Production\Models\ProductionOrderReservation;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Services\ProductionExecutionService;
use App\Domains\Production\Services\ProductionMaterialService;
use App\Domains\Production\Services\ProductionOrderService;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\JeyenkayLubricantsDemoSeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/** Temp no-BOM flow: no BOM / material requirements, optional routing resources, materials consumed on shop floor logs. */
class TempFlowTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private Warehouse $warehouse;
    private Product $fg;
    private Product $rm;
    private string $consumptionDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
        $this->consumptionDir = sys_get_temp_dir() . '/temp-production-test-' . uniqid();
        config(['production.temp_flow' => true, 'production.temp_consumption_path' => $this->consumptionDir]);

        $this->tenant = Tenant::create(['name' => 'TF', 'slug' => 'tf', 'status' => 'active', 'plan' => 'enterprise']);
        $this->user = User::create(['tenant_id' => $this->tenant->id, 'name' => 'Planner', 'email' => 'tf@example.test', 'password' => bcrypt('x'), 'role' => 'admin']);
        $role = Role::query()->whereNull('tenant_id')->where('slug', 'production_manager')->firstOrFail();
        UserRole::create(['user_id' => $this->user->id, 'role_id' => $role->id, 'tenant_id' => $this->tenant->id]);
        $this->actingAs($this->user);
        app(\App\Core\Tenant\TenantContext::class)->setTenant($this->tenant);

        $this->warehouse = Warehouse::create(['tenant_id' => $this->tenant->id, 'name' => 'Main', 'code' => 'WH', 'status' => 'active', 'is_default' => true]);
        $this->fg = $this->product('Oil 32', 'OIL-32', 'finished_good', 'manufacture');
        $this->rm = $this->product('Base Oil', 'BASE-OIL', 'raw_material', 'buy');
        StockService::recordInflow($this->tenant->id, $this->rm->id, $this->warehouse->id, 1000, 0.0, 'Opening Stock', $this->rm->id, 'OPEN-1');
    }

    protected function tearDown(): void
    {
        // never touch the real storage/app/temp-production: tests write to their own temp folder
        File::deleteDirectory($this->consumptionDir);
        parent::tearDown();
    }

    private function product(string $name, string $sku, string $type, string $method): Product
    {
        return Product::create([
            'tenant_id' => $this->tenant->id, 'name' => $name, 'sku' => $sku, 'type' => $type, 'item_type' => 'Goods',
            'variation_type' => 'Single', 'status' => 'active', 'supplier_method' => $method,
            'inventory_valuation_method' => 'FIFO', 'track_batch' => true, 'unit_cost' => 0, 'cost_price' => 0, 'selling_price' => 0,
        ]);
    }

    private function stock(Product $p): float
    {
        return (float) ProductWarehouseStock::withoutGlobalScopes()->where('product_id', $p->id)->where('warehouse_id', $this->warehouse->id)->value('quantity');
    }

    private function newOrder(?Routing $routing = null): ProductionOrder
    {
        return app(ProductionOrderService::class)->createDirect([
            'product_id' => $this->fg->id, 'quantity_ordered' => 100, 'start_date' => now()->toDateString(), 'end_date' => now()->addDay()->toDateString(),
            'routing_id' => $routing?->id,
        ], $this->tenant->id, $this->user->id);
    }

    public function test_order_without_bom_or_routing_gets_one_default_operation_and_no_material_requirements(): void
    {
        $order = $this->newOrder();

        $this->assertNull($order->bom_id);
        $this->assertSame(ProductionOrder::STATUS_DRAFT, $order->status);
        $ops = ProductionOrderOperation::where('production_order_id', $order->id)->get();
        $this->assertCount(1, $ops);
        $this->assertNull($ops[0]->work_center_id);
        $this->assertSame(0, ProductionOrderReservation::where('production_order_id', $order->id)->count());
    }

    public function test_routing_operations_with_only_a_name_are_snapshotted_onto_the_order(): void
    {
        $routing = Routing::create(['tenant_id' => $this->tenant->id, 'routing_number' => 'RTG-T1', 'name' => 'Blend route', 'version' => '1.0.0', 'status' => Routing::STATUS_DRAFT, 'effective_from' => now()->toDateString()]);
        RoutingOperation::create(['tenant_id' => $this->tenant->id, 'routing_id' => $routing->id, 'sequence' => 10, 'operation_number' => 'OP-010', 'name' => 'Blend', 'operation_type' => 'manufacturing']);
        RoutingOperation::create(['tenant_id' => $this->tenant->id, 'routing_id' => $routing->id, 'sequence' => 20, 'operation_number' => 'OP-020', 'name' => 'Filter', 'operation_type' => 'manufacturing']);

        $order = $this->newOrder($routing);

        $this->assertSame(['Blend', 'Filter'], ProductionOrderOperation::where('production_order_id', $order->id)->orderBy('sequence')->pluck('name')->all());
        $this->assertSame($routing->id, (int) $order->routing_id);
    }

    public function test_release_then_log_with_materials_consumed_deducts_stock_and_records_issue_and_json(): void
    {
        $order = $this->newOrder();
        app(ProductionOrderService::class)->release($order->id, $this->user->id);
        $op = ProductionOrderOperation::where('production_order_id', $order->id)->firstOrFail();

        $log = app(ProductionExecutionService::class)->logProgress(
            $op->id, 100, 0, 0, 0, 0, 'batch run', null, $this->user->id, true, null, null,
            [['product_id' => $this->rm->id, 'quantity' => 250, 'warehouse_id' => $this->warehouse->id]]
        );

        $this->assertEquals(750.0, $this->stock($this->rm));
        $issue = ProductionOrderIssue::where('production_order_id', $order->id)->firstOrFail();
        $this->assertNull($issue->reservation_id);
        $this->assertSame('consumption', $issue->issue_type);
        $this->assertEquals(250.0, (float) $issue->quantity_issued);

        $byLog = app(ProductionMaterialService::class)->consumptionByLog($this->tenant->id, $order->id);
        $this->assertSame($this->rm->id, $byLog[$log->id]['lines'][0]['product_id']);
        $this->assertSame($issue->id, $byLog[$log->id]['lines'][0]['issue_id']);
    }

    public function test_insufficient_stock_rolls_the_whole_log_back(): void
    {
        $order = $this->newOrder();
        app(ProductionOrderService::class)->release($order->id, $this->user->id);
        $op = ProductionOrderOperation::where('production_order_id', $order->id)->firstOrFail();

        try {
            app(ProductionExecutionService::class)->logProgress(
                $op->id, 100, 0, 0, 0, 0, null, null, $this->user->id, false, null, null,
                [['product_id' => $this->rm->id, 'quantity' => 5000, 'warehouse_id' => $this->warehouse->id]]
            );
            $this->fail('Expected insufficient stock to block the log.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertStringContainsString('Insufficient stock', $e->getMessage());
        }

        $this->assertEquals(1000.0, $this->stock($this->rm));
        $this->assertSame(0, ProductionOrderProgressLog::where('production_order_id', $order->id)->count());
        $this->assertSame(0, ProductionOrderIssue::where('production_order_id', $order->id)->count());
    }

    public function test_routing_and_work_center_forms_save_with_only_names_and_codes(): void
    {
        $this->withHeaders(['X-Tenant' => 'tf'])->post(route('production.routing.store'), [
            'name' => 'Bare route',
            'operations' => [['sequence' => 10, 'name' => 'Blend']],
        ])->assertSessionHasNoErrors();

        $routing = Routing::where('name', 'Bare route')->firstOrFail();
        $op = $routing->operations()->firstOrFail();
        $this->assertSame('Blend', $op->name);
        $this->assertNull($op->work_center_id);
        $this->assertSame('manufacturing', $op->operation_type);

        $this->withHeaders(['X-Tenant' => 'tf'])->post(route('production.work-centers.store'), ['name' => 'Blending', 'code' => 'WC-BL'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('production_work_centers', ['code' => 'WC-BL', 'status' => 'active']);

        // flag off: the same routing post is rejected (work center / section / machine required)
        config(['production.temp_flow' => false]);
        $this->withHeaders(['X-Tenant' => 'tf'])->post(route('production.routing.store'), [
            'name' => 'Strict route',
            'operations' => [['sequence' => 10, 'name' => 'Blend', 'operation_type' => 'manufacturing']],
        ])->assertSessionHasErrors();
    }

    public function test_order_page_log_form_has_the_materials_consumed_block_only_in_temp_flow(): void
    {
        $order = $this->newOrder();
        app(ProductionOrderService::class)->release($order->id, $this->user->id);
        $op = ProductionOrderOperation::where('production_order_id', $order->id)->firstOrFail();

        // submit the form like the browser does
        $this->withHeaders(['X-Tenant' => 'tf'])->post(route('production.orders.log-progress', $order->id), [
            'operation_id' => $op->id, 'quantity_produced' => 100, 'quantity_rejected' => 0, 'quantity_scrapped' => 0,
            'setup_minutes_logged' => 0, 'run_minutes_logged' => 0,
            'consumed_materials' => [['product_id' => $this->rm->id, 'quantity' => 40, 'warehouse_id' => $this->warehouse->id], ['product_id' => '', 'quantity' => '', 'warehouse_id' => $this->warehouse->id]],
        ])->assertSessionHas('success');
        $this->assertEquals(960.0, $this->stock($this->rm));

        $this->withHeaders(['X-Tenant' => 'tf'])->get(route('production.orders.show', $order->id))
            ->assertOk()->assertSee('Materials consumed')->assertSee('consumed_materials', false)->assertSee('Consumed: Base Oil')
            ->assertSee('Materials consumed (shop floor logs)')->assertSee('By operation')->assertSee('By material (whole order)')
            ->assertSee('Every logged line')->assertSee('BASE-OIL');

        $report = app(ProductionMaterialService::class)->consumptionReport($order);
        $this->assertCount(1, $report);
        $this->assertSame($op->id, $report[0]['operation_id']);
        $this->assertEquals(40.0, $report[0]['quantity']);
        $this->assertSame('Warehouse: Main' === '' ? '' : 'Main', $report[0]['warehouse']);
        $this->assertStringContainsString('OPEN-1', $report[0]['inventory_lots']);

        // the WIP detail page carries the same report
        $wip = \App\Domains\Production\Models\ProductionWip::withoutGlobalScopes()->where('production_order_id', $order->id)->firstOrFail();
        $this->withHeaders(['X-Tenant' => 'tf'])->get(route('production.wip.show', $wip->id))
            ->assertOk()->assertSee('Materials consumed (shop floor logs)')->assertSee('BASE-OIL');

        // losing the JSON sidecar must not lose the tracking: it is rebuilt from the issue rows
        File::deleteDirectory($this->consumptionDir);
        $again = app(ProductionMaterialService::class)->consumptionReport($order);
        $this->assertCount(1, $again);
        $this->assertSame($op->id, $again[0]['operation_id']);
        $this->assertEquals(40.0, $again[0]['quantity']);
    }

    public function test_schedule_without_work_center_releases_and_shop_floor_completion_consumes_materials(): void
    {
        $order = $this->newOrder();
        app(ProductionOrderService::class)->release($order->id, $this->user->id);

        $schedule = app(\App\Domains\Production\Services\SchedulingService::class)->generateSchedule($order->fresh(), now());
        $this->assertSame('scheduled', $schedule->status);

        $this->withHeaders(['X-Tenant' => 'tf'])->post(route('production.schedules.release', $schedule->id), ['confirm_warnings' => 1])
            ->assertSessionHas('success');
        $this->assertSame('released', $schedule->fresh()->status);

        $schedOp = \App\Domains\Production\Models\ProductionScheduleOperation::where('production_schedule_id', $schedule->id)->firstOrFail();
        $this->withHeaders(['X-Tenant' => 'tf'])->post(route('production.mes.start', $schedOp->id))->assertSessionHas('success');

        // the shop floor dashboard's "Add production of" dialog carries the materials block
        $this->withHeaders(['X-Tenant' => 'tf'])->get(route('production.mes.dashboard'))
            ->assertOk()->assertSee('completeModal' . $schedOp->id)->assertSee('cmMesDash' . $schedOp->id);

        $this->withHeaders(['X-Tenant' => 'tf'])->post(route('production.mes.complete', $schedOp->id), [
            'quantity_produced' => 100,
            'consumed_materials' => [['product_id' => $this->rm->id, 'quantity' => 300, 'warehouse_id' => $this->warehouse->id]],
        ])->assertSessionHas('success');

        $this->assertEquals(700.0, $this->stock($this->rm));
        $this->assertSame(1, ProductionOrderIssue::where('production_order_id', $order->id)->count());
        $this->assertSame(1, count(app(ProductionMaterialService::class)->consumptionByLog($this->tenant->id, $order->id)));
    }

    public function test_draft_order_release_and_plan_is_not_blocked_by_material_issue(): void
    {
        $order = $this->newOrder();

        $this->withHeaders(['X-Tenant' => 'tf'])->get(route('production.orders.show', $order->id))
            ->assertOk()->assertDontSee('Store must issue raw materials')->assertSee('scheduleModal');

        config(['production.temp_flow' => false]);
        $this->withHeaders(['X-Tenant' => 'tf'])->get(route('production.orders.show', $order->id))
            ->assertSee('Store must issue raw materials');
    }

    public function test_flag_off_keeps_the_bom_requirement_for_manufactured_products(): void
    {
        config(['production.temp_flow' => false]);

        $this->expectException(\InvalidArgumentException::class);
        $this->newOrder();
    }

    public function test_demo_seeder_is_additive_and_idempotent_and_replays_a_voucher_with_its_materials(): void
    {
        $before = Product::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count();

        $seeder = new JeyenkayLubricantsDemoSeeder();
        $seeder->run($this->tenant->id);

        $products = Product::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->get();
        $this->assertSame(11, $products->where('type', 'finished_good')->where('sku', '!=', 'OIL-32')->count());
        $this->assertGreaterThanOrEqual(38, $products->whereIn('type', ['raw_material', 'semi_finished'])->count() - 1); // minus BASE-OIL from setUp
        $this->assertSame('semi_finished', $products->firstWhere('sku', 'EMUL-WHITE')->type);

        $countAfter = Product::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count();
        $seeder->run($this->tenant->id); // second run adds nothing
        $this->assertSame($countAfter, Product::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count());
        $this->assertGreaterThan($before, $countAfter);
        $this->assertEquals(1000.0, $this->stock($this->rm)); // pre-existing data untouched

        // Replay: voucher 1 (has raw materials: ARZOL P 500 + HP MILJUTE 32) and an ASCO M10 voucher (no raw material rows)
        $fg = collect(JeyenkayLubricantsDemoSeeder::readFinishedGoods());
        $rm = JeyenkayLubricantsDemoSeeder::readRawMaterials();
        $vouchers = [$fg->first(), $fg->firstWhere('batch', 'AASCOM10/41')];
        $this->assertSame(2, $seeder->replayVouchers($this->tenant->id, $vouchers, $rm, $this->warehouse->id));
        $this->assertSame(0, $seeder->replayVouchers($this->tenant->id, $vouchers, $rm, $this->warehouse->id)); // idempotent

        $order = ProductionOrder::withoutGlobalScopes()->where('description', 'like', '%' . $vouchers[0]['voucher'] . '%')->firstOrFail();
        $issues = ProductionOrderIssue::where('production_order_id', $order->id)->get();
        $this->assertSame(2, $issues->count());
        $arzol = Product::withoutGlobalScopes()->where('sku', 'ARZOL-P-500')->firstOrFail();
        $this->assertEquals(28286.0, (float) $issues->firstWhere('product_id', $arzol->id)->quantity_issued);
        $opening = ceil(28286 * 1.25);
        $this->assertEquals($opening - 28286.0, (float) ProductWarehouseStock::withoutGlobalScopes()->where('product_id', $arzol->id)->value('quantity'));

        $noRm = ProductionOrder::withoutGlobalScopes()->where('description', 'like', '%' . $vouchers[1]['voucher'] . '%')->firstOrFail();
        $this->assertSame(0, ProductionOrderIssue::where('production_order_id', $noRm->id)->count());
        $this->assertEquals($vouchers[1]['qty'], (float) $noRm->fresh()->operations()->first()->quantity_produced);
    }
}
