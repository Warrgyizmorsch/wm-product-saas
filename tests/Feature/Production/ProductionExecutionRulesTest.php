<?php

namespace Tests\Feature\Production;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\ProductWarehouseStock;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionOrderReceipt;
use App\Domains\Production\Models\ProductionOrderReservation;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\RoutingOperationMaterial;
use App\Domains\Production\Policies\ProductionOrderPolicy;
use App\Domains\Production\Services\OperationMaterialService;
use App\Domains\Production\Services\ProductionExecutionService;
use App\Domains\Production\Services\ProductionMaterialService;
use App\Domains\Production\Services\ProductionOrderService;
use App\Domains\Production\Services\ProductionReadinessService;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * PX-01 … PX-21 (docs/production_execution_rules_draft.md) on the 3-level doc example
 * (B1 → B2, B3, B4; B2 → B5; shared M2 / M3), order quantity 10.
 */
class ProductionExecutionRulesTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private Uom $uom;
    private int $branchA;
    /** @var array<string, Product> */
    private array $p = [];
    /** @var array<string, ProductionBom> */
    private array $bom = [];
    /** @var array<string, RoutingOperation> */
    private array $op = [];
    /** @var array<string, RoutingOperationMaterial> */
    private array $alloc = [];
    private Warehouse $wh;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);

        $this->tenant = Tenant::create(['name' => 'PX', 'slug' => 'px', 'status' => 'active', 'plan' => 'enterprise']);
        $this->user = User::create(['tenant_id' => $this->tenant->id, 'name' => 'Planner', 'email' => 'px@example.test', 'password' => bcrypt('x'), 'role' => 'admin']);
        $role = Role::query()->whereNull('tenant_id')->where('slug', 'production_manager')->firstOrFail();
        UserRole::create(['user_id' => $this->user->id, 'role_id' => $role->id, 'tenant_id' => $this->tenant->id]);
        $this->actingAs($this->user);
        app(\App\Core\Tenant\TenantContext::class)->setTenant($this->tenant);

        $row = ['name' => 'BR-A', 'code' => 'BR-A-' . $this->tenant->id, 'created_at' => now(), 'updated_at' => now()];
        if (Schema::hasColumn('branches', 'tenant_id')) {
            $row['tenant_id'] = $this->tenant->id;
        }
        $this->branchA = (int) DB::table('branches')->insertGetId($row);
        $this->uom = Uom::create(['tenant_id' => $this->tenant->id, 'name' => 'Pieces', 'code' => 'PCS']);

        foreach (['B1' => 'finished_good', 'B2' => 'semi_finished', 'B3' => 'semi_finished', 'B4' => 'semi_finished', 'B5' => 'semi_finished'] as $code => $type) {
            $this->p[$code] = $this->product($code, $type, 'manufacture');
        }
        foreach (['M1', 'M2', 'M2ALT', 'M3', 'M4', 'M5', 'M6', 'M7', 'M8', 'MX'] as $code) {
            $this->p[$code] = $this->product($code, 'raw_material', 'trade');
        }

        $this->makeBom('B5', [['OP-10', 10, null, null, [['M5', 2], ['M2', 3], ['M3', 1]]]]);
        $this->makeBom('B2', [
            ['OP-10', 10, null, null, [['B5', 1, 'B5'], ['M2', 2], ['M2ALT', 2, null, 'M2']]],
            ['OP-15', 15, 'OP-10', 1, [['M7', 1]]],
            ['OP-20', 20, null, null, [['M4', 1]]],
        ]);
        $this->makeBom('B3', [['OP-10', 10, null, null, [['M6', 1], ['M7', 2]]]]);
        $this->makeBom('B4', [['OP-10', 10, null, null, [['M8', 1], ['M2', 2]]]]);
        $this->makeBom('B1', [
            ['OP-10', 10, null, null, [['M1', 1], ['B2', 1, 'B2']]],
            ['OP-20', 20, null, null, [['B3', 1, 'B3'], ['M2', 1], ['M2ALT', 1, null, 'M2']]],
            ['OP-25', 25, 'OP-20', 1, [['B3', 1, 'B3'], ['M2', 1.5]]],
            ['OP-30', 30, null, null, [['B4', 1, 'B4'], ['M3', 1]]],
        ]);

        $this->wh = Warehouse::withoutGlobalScopes()->create(['tenant_id' => $this->tenant->id, 'branch_id' => $this->branchA, 'name' => 'WA1', 'code' => 'WA1', 'type' => Warehouse::TYPE_STANDARD, 'status' => 'active']);
        foreach (['B2' => 4, 'B3' => 12, 'B4' => 2, 'B5' => 2, 'M1' => 20, 'M2' => 60, 'M3' => 30, 'M4' => 8, 'M5' => 20, 'M6' => 20, 'M7' => 20, 'M8' => 6] as $code => $qty) {
            ProductWarehouseStock::withoutGlobalScopes()->create(['tenant_id' => $this->tenant->id, 'branch_id' => $this->branchA, 'product_id' => $this->p[$code]->id, 'warehouse_id' => $this->wh->id, 'quantity' => $qty, 'reserved_qty' => 0, 'available_qty' => $qty]);
        }
    }

    // ───────────────────────────── fixtures

    private function product(string $code, string $type, string $method): Product
    {
        return Product::create(['tenant_id' => $this->tenant->id, 'name' => $code, 'sku' => $code, 'type' => $type, 'status' => 'active', 'uom_id' => $this->uom->id, 'unit_cost' => 1, 'supplier_method' => $method]);
    }

    private function makeBom(string $product, array $ops): void
    {
        $routing = Routing::create(['tenant_id' => $this->tenant->id, 'routing_number' => "RT-{$product}", 'name' => "{$product} route", 'version' => '1.0.0', 'status' => Routing::STATUS_ACTIVE, 'effective_from' => now()->toDateString()]);
        $bom = ProductionBom::create(['tenant_id' => $this->tenant->id, 'bom_number' => "BOM-{$product}", 'bom_name' => "{$product} BOM", 'bom_type' => 'manufacturing', 'product_id' => $this->p[$product]->id, 'routing_id' => $routing->id, 'base_quantity' => 1, 'base_uom_id' => $this->uom->id, 'version' => '1.0.0', 'status' => 'approved', 'effective_date' => now()->toDateString()]);
        $this->bom[$product] = $bom;

        foreach ($ops as [$number, $seq, $replaces, $priority, $materials]) {
            $op = RoutingOperation::create([
                'tenant_id' => $this->tenant->id, 'routing_id' => $routing->id, 'sequence' => $seq, 'operation_number' => $number, 'name' => "{$product} {$number}",
                'operation_type' => 'manufacturing', 'is_alternative' => $replaces !== null, 'replaces_operation_id' => $replaces ? $this->op["{$product}.{$replaces}"]->id : null,
                'priority' => $priority ?? 1, 'setup_time_minutes' => 5, 'processing_time_minutes' => 1, 'cost_per_unit' => 1,
            ]);
            $this->op["{$product}.{$number}"] = $op;
            foreach ($materials as $m) {
                [$code, $qty] = $m;
                $childOf = $m[2] ?? null;
                $altOf = $m[3] ?? null;
                $this->alloc["{$product}.{$number}.{$code}"] = RoutingOperationMaterial::create([
                    'tenant_id' => $this->tenant->id, 'bom_id' => $bom->id, 'routing_operation_id' => $op->id, 'material_id' => $this->p[$code]->id,
                    'child_bom_id' => $childOf ? $this->bom[$childOf]->id : null, 'quantity' => $qty, 'uom_id' => $this->uom->id, 'material_scrap_percentage' => 0,
                    'is_alternative' => $altOf !== null, 'priority' => $altOf ? 1 : null,
                    'replaces_routing_material_id' => $altOf ? $this->alloc["{$product}.{$number}.{$altOf}"]->id : null,
                ]);
            }
        }
    }

    private function createOrder(): ProductionOrder
    {
        $ops = ['B1.OP-10', 'B1.OP-20', 'B1.OP-30'];
        $mats = ['B1.OP-10.M1', 'B1.OP-10.B2', 'B1.OP-20.B3', 'B1.OP-20.M2', 'B1.OP-30.B4', 'B1.OP-30.M3'];

        return app(ProductionOrderService::class)->createDirect([
            'product_id' => $this->p['B1']->id, 'quantity_ordered' => 10, 'branch_id' => $this->branchA,
            'start_date' => now()->toDateString(), 'end_date' => now()->addDays(5)->toDateString(),
            'engineering_selection_submitted' => 1,
            'selected_operation_ids' => array_map(fn ($k) => $this->op[$k]->id, $ops),
            'selected_material_ids' => array_map(fn ($k) => $this->alloc[$k]->id, $mats),
        ], $this->tenant->id, $this->user->id);
    }

    private function orderOp(ProductionOrder $order, string $key): ProductionOrderOperation
    {
        return ProductionOrderOperation::where('production_order_id', $order->id)->where('routing_operation_id', $this->op[$key]->id)->firstOrFail();
    }

    private function issue(ProductionOrder $order, string $code, float $qty): void
    {
        $res = ProductionOrderReservation::where('production_order_id', $order->id)->where('product_id', $this->p[$code]->id)->firstOrFail();
        app(ProductionMaterialService::class)->issueMaterial($res->id, $qty, null, $this->user->id);
    }

    private function stock(string $code): float
    {
        return (float) ProductWarehouseStock::withoutGlobalScopes()->where('product_id', $this->p[$code]->id)->sum('quantity');
    }

    // ───────────────────────────── PX-01..04 — one resolver, right quantities

    public function test_operation_materials_come_only_from_the_orders_bom_and_choice(): void
    {
        // The bug of the screenshot: another BOM stores rows on the SAME routing operation.
        $other = ProductionBom::create(['tenant_id' => $this->tenant->id, 'bom_number' => 'BOM-OTHER', 'bom_name' => 'other', 'bom_type' => 'manufacturing', 'product_id' => $this->p['B1']->id, 'routing_id' => $this->bom['B1']->routing_id, 'base_quantity' => 1, 'base_uom_id' => $this->uom->id, 'version' => '2.0.0', 'status' => 'inactive', 'effective_date' => now()->toDateString()]);
        RoutingOperationMaterial::create(['tenant_id' => $this->tenant->id, 'bom_id' => $other->id, 'routing_operation_id' => $this->op['B1.OP-20']->id, 'material_id' => $this->p['MX']->id, 'quantity' => 99, 'uom_id' => $this->uom->id, 'material_scrap_percentage' => 0]);

        $order = $this->createOrder();
        $desc = app(OperationMaterialService::class)->describe($this->orderOp($order, 'B1.OP-20'));

        $names = collect($desc['materials'])->pluck('name')->all();
        $this->assertEqualsCanonicalizing(['B3', 'M2'], $names, 'only this order\'s BOM rows; no alternative (M2ALT), no other BOM (MX)');
        $m2 = collect($desc['materials'])->firstWhere('name', 'M2');
        $this->assertEqualsWithDelta(10.0, $m2['required'], 1e-6, 'op target 10 x 1');
        $this->assertNotEmpty($m2['shared_with'], 'M2 is also used by other operations of the order');
    }

    public function test_child_bom_rows_are_sub_assemblies_with_stock_and_production_split(): void
    {
        $order = $this->createOrder();
        $desc = app(OperationMaterialService::class)->describe($this->orderOp($order, 'B1.OP-10'));

        $this->assertCount(1, $desc['sub_assemblies']);
        $sub = $desc['sub_assemblies'][0];
        $this->assertSame('B2', $sub['name']);
        $this->assertEqualsWithDelta(10.0, $sub['required'], 1e-6);
        $this->assertEqualsWithDelta(6.0, $sub['to_produce'], 1e-6, 'stock 4 of 10');
        $this->assertEqualsWithDelta(4.0, $sub['from_stock'], 1e-6);
        $this->assertNotEmpty($sub['produced_by']);
        // the stock part is still a material to issue, the produced part is not
        $b2 = collect($desc['materials'])->firstWhere('name', 'B2');
        $this->assertEqualsWithDelta(4.0, $b2['required'], 1e-6);
    }

    public function test_order_page_renders_with_the_new_drawer(): void
    {
        $order = $this->createOrder();
        $this->withHeaders(['X-Tenant' => 'px'])->get(route('production.orders.show', $order->id))
            ->assertOk()
            ->assertSee('Sub-assemblies')
            ->assertSee('Order total');
    }

    // ───────────────────────────── PX-05 / 06 / 08 — readiness on issued material, pooled

    /** UR-01: target -> BOM need for 1 unit -> compared with the ISSUED quantity (store stock does not count). */
    public function test_material_column_is_blocked_partially_issued_or_ready_by_the_users_rule(): void
    {
        $order = $this->createOrder();
        $b5 = $this->orderOp($order, 'B5.OP-10');                       // level 3, target 4: M5 8, M2 12, M3 4  (1 unit needs M5 2, M2 3, M3 1)
        $svc = app(ProductionReadinessService::class);

        // nothing issued (plenty of stock in the store) -> cannot produce
        $eval = $svc->evaluateOperationReadiness($b5);
        $this->assertSame(ProductionReadinessService::STATUS_BLOCKED, $eval['material']['status']);
        $this->assertSame('Blocked — minimum quantity not issued', $eval['material']['label']);
        $this->assertSame(ProductionReadinessService::STATUS_BLOCKED, $eval['overall_status']);
        $this->assertEqualsWithDelta(0.0, $eval['ready_qty'], 1e-9);
        $this->assertSame(ProductionReadinessService::REASON_MATERIAL_MINIMUM_NOT_ISSUED, $eval['actionable_blockers'][0]['code']);
        $m2 = collect($eval['material']['materials'])->firstWhere('name', 'M2');
        $this->assertEqualsWithDelta(3.0, $m2['per_unit'], 1e-6, 'BOM need for 1 unit');

        // M5 and M3 fully issued, M2 only 2 although 1 unit needs 3 -> still below the minimum -> blocked
        $this->issue($order, 'M5', 8);
        $this->issue($order, 'M3', 4);
        $this->issue($order, 'M2', 2);
        $eval = $svc->evaluateOperationReadiness($b5->fresh());
        $this->assertSame(ProductionReadinessService::STATUS_BLOCKED, $eval['material']['status']);
        $this->assertEqualsWithDelta(0.0, $eval['ready_qty'], 1e-9, '2 / 3 of a unit cannot be produced');

        // M2 6 issued -> enough for 2 of the 4 units -> partially issued
        $this->issue($order, 'M2', 4);
        $eval = $svc->evaluateOperationReadiness($b5->fresh());
        $this->assertSame(ProductionReadinessService::STATUS_PARTIALLY_READY, $eval['material']['status']);
        $this->assertSame('Partially issued', $eval['material']['label']);
        $this->assertSame(ProductionReadinessService::STATUS_PARTIALLY_READY, $eval['overall_status']);
        $this->assertEqualsWithDelta(2.0, $eval['ready_qty'], 1e-6, '6 M2 / 3 per unit');

        // everything issued -> ready
        $this->issue($order, 'M2', 6);
        $eval = $svc->evaluateOperationReadiness($b5->fresh());
        $this->assertSame(ProductionReadinessService::STATUS_READY, $eval['material']['status']);
        $this->assertSame(ProductionReadinessService::STATUS_READY, $eval['overall_status']);
        $this->assertEqualsWithDelta(4.0, $eval['ready_qty'], 1e-6);
    }

    /**
     * RV-03 (rule A, M-26 / M-27): a sub-assembly made inside the order is ONE line in the Material column, judged like a material —
     * 1 unit needs = its BOM quantity, available = the stock part the store has issued + what its maker operations have produced.
     * (Replaces the UR-01 test "only direct materials are judged".)
     */
    public function test_material_column_judges_a_sub_assembly_as_one_line(): void
    {
        $order = $this->createOrder();
        $b1 = $this->orderOp($order, 'B1.OP-10');                       // M1 (direct) + B2 (child BOM: 10 needed, 4 from stock + 6 to produce)
        $svc = app(ProductionReadinessService::class);

        $lines = collect($svc->evaluateOperationReadiness($b1)['material']['materials'])->keyBy('name');
        $this->assertSame(['M1', 'B2'], $lines->keys()->all(), 'BOM order');
        $b2 = $lines['B2'];
        $this->assertTrue($b2['sub_assembly']);
        $this->assertEqualsWithDelta(10.0, $b2['required'], 1e-6, 'its whole need, not only the stock part');
        $this->assertEqualsWithDelta(1.0, $b2['per_unit'], 1e-6, 'BOM need for 1 unit');
        $this->assertEqualsWithDelta(4.0, $b2['from_stock'], 1e-6);
        $this->assertEqualsWithDelta(6.0, $b2['to_produce'], 1e-6);
        $this->assertEqualsWithDelta(0.0, $b2['made'], 1e-6, 'its maker operations are not completed');
        $this->assertSame('blocked', $b2['state'], 'nothing issued and nothing made: below the minimum of 1 unit');
    }

    /** UR-02: the Operation Flow column says where the operation stands, in words — a wait is never "Blocked". */
    public function test_operation_flow_column_says_where_the_operation_stands(): void
    {
        $order = $this->createOrder();
        $svc = app(ProductionReadinessService::class);

        $independent = $svc->evaluateOperationReadiness($this->orderOp($order, 'B5.OP-10'))['dependency'];
        $this->assertSame('ready', $independent['state']);
        $this->assertSame('Ready to start', $independent['label']);
        $this->assertSame('success', $independent['tone']);

        $waiting = $svc->evaluateOperationReadiness($this->orderOp($order, 'B2.OP-20'))['dependency'];
        $this->assertSame(ProductionReadinessService::STATUS_WAITING_PREDECESSOR, $waiting['status'], 'a wait is WAITING, not BLOCKED');
        $this->assertSame('waiting', $waiting['state']);
        $this->assertSame('Waiting for Op 10', $waiting['label']);
        $this->assertSame('B2 OP-10', $waiting['waiting_for'][0]['name']);

        $parent = $svc->evaluateOperationReadiness($this->orderOp($order, 'B1.OP-10'))['dependency'];
        $this->assertSame('waiting', $parent['state']);
        $this->assertContains('sub_assembly', collect($parent['waiting_for'])->pluck('kind')->all(), 'it waits for the operations that make the child-BOM material');

        $running = $this->orderOp($order, 'B5.OP-10');
        $running->update(['status' => 'running']);
        $this->assertSame('In progress', $svc->evaluateOperationReadiness($running->fresh())['dependency']['label']);
        $running->update(['status' => 'paused']);
        $this->assertSame('Paused', $svc->evaluateOperationReadiness($running->fresh())['dependency']['label']);
        $running->update(['status' => 'completed']);
        $this->assertSame('Completed', $svc->evaluateOperationReadiness($running->fresh())['dependency']['label']);
    }

    /** UR-04: the Subcontract column only says Subcontracted / Not subcontracted — no verdict, no vendor-return block. */
    public function test_subcontract_column_only_says_subcontracted_or_not(): void
    {
        $order = $this->createOrder();
        $op = $this->orderOp($order, 'B5.OP-10');
        $svc = app(ProductionReadinessService::class);

        $eval = $svc->evaluateOperationReadiness($op);
        $this->assertSame('Not subcontracted', $eval['subcontract']['label']);
        $this->assertFalse($eval['subcontract']['is_subcontracted']);

        $op->update(['is_external' => true, 'status' => 'running']);   // the old rule blocked an external operation that had not come back
        $eval = $svc->evaluateOperationReadiness($op->fresh());
        $this->assertSame('Subcontracted', $eval['subcontract']['label']);
        $this->assertTrue($eval['subcontract']['is_subcontracted']);
        $this->assertSame([], $eval['subcontract']['blockers']);
        $this->assertNotContains(ProductionReadinessService::REASON_SUBCONTRACT_WIP_PENDING, collect($eval['blocking_reasons'])->pluck('code')->all());
    }

    /** UR-05: every time the order screen loads, the readiness is checked again and the saved operation status is refreshed. */
    public function test_order_page_load_refreshes_the_saved_operation_status(): void
    {
        $order = $this->createOrder();
        ProductionOrder::query()->where('id', $order->id)->update(['status' => 'released']);
        $this->issue($order, 'M5', 8);
        $this->issue($order, 'M3', 4);
        $this->issue($order, 'M2', 12);                                   // B5.OP-10 is fully issued and has no predecessor -> ready

        $b5 = $this->orderOp($order, 'B5.OP-10');
        $b2 = $this->orderOp($order, 'B2.OP-20');
        $b5->update(['status' => 'waiting']);                             // stale values
        $b2->update(['status' => 'ready']);

        $this->withHeaders(['X-Tenant' => 'px'])->get(route('production.orders.show', $order->id))->assertOk();

        $this->assertSame('ready', $b5->fresh()->status, 'the page load saved the fresh result');
        $this->assertSame('waiting', $b2->fresh()->status, 'it waits for its predecessor');
    }

    public function test_shared_material_is_handed_out_in_execution_order_not_counted_twice(): void
    {
        $order = $this->createOrder();
        $this->issue($order, 'M2', 14);                                   // B5.OP-10 needs 12, B2.OP-10 needs 12
        $mat = app(OperationMaterialService::class);

        $first = collect($mat->describe($this->orderOp($order, 'B5.OP-10'))['materials'])->firstWhere('name', 'M2');
        $second = collect($mat->describe($this->orderOp($order, 'B2.OP-10'))['materials'])->firstWhere('name', 'M2');
        $this->assertEqualsWithDelta(12.0, $first['covered_by_issue'], 1e-6, 'deepest level first');
        $this->assertEqualsWithDelta(2.0, $second['covered_by_issue'], 1e-6, 'only what is left');
    }

    public function test_waiting_for_a_predecessor_is_waiting_not_blocked_and_counted_apart(): void
    {
        $order = $this->createOrder();
        $this->issue($order, 'M4', 6);                                    // B2.OP-20 material is covered; its predecessor B2.OP-10 is not done
        $eval = app(ProductionReadinessService::class)->evaluateOperationReadiness($this->orderOp($order, 'B2.OP-20'));

        $this->assertSame(ProductionReadinessService::STATUS_WAITING_PREDECESSOR, $eval['overall_status']);
        $this->assertEmpty($eval['actionable_blockers']);

        $order->refresh();
        $order->setRelation('operations', $order->operations()->get());
        $orderEval = app(ProductionReadinessService::class)->evaluateOrderReadiness($order);
        $this->assertGreaterThanOrEqual(1, $orderEval['waiting_operations_count']);
        $this->assertArrayHasKey('blocked_operations_count', $orderEval);
    }

    // ───────────────────────────── PX-09 — output capped at what the issued material covers

    public function test_logging_beyond_issued_material_is_rejected_for_operators_and_logged_for_managers(): void
    {
        $order = $this->createOrder();
        $this->issue($order, 'M5', 8);
        $this->issue($order, 'M3', 4);
        $this->issue($order, 'M2', 6);                                    // covers 2 of the 4 units
        $b5 = $this->orderOp($order, 'B5.OP-10');
        $svc = app(ProductionReadinessService::class);

        $operator = User::create(['tenant_id' => $this->tenant->id, 'name' => 'Operator', 'email' => 'op@example.test', 'password' => bcrypt('x'), 'role' => 'operator']);

        $svc->assertWithinReadyQty($b5, 2.0, $operator);                  // within the cover: fine
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Limiting material');
        $svc->assertWithinReadyQty($b5, 3.0, $operator);
    }

    public function test_manager_override_is_allowed_and_written_to_the_order_events(): void
    {
        $order = $this->createOrder();
        $b5 = $this->orderOp($order, 'B5.OP-10');                         // nothing issued -> cover 0
        app(ProductionReadinessService::class)->assertWithinReadyQty($b5, 3.0, $this->user);   // admin holds the override

        $this->assertTrue(DB::table('production_event_timelines')->where('production_order_id', $order->id)->where('event_type', 'Readiness Override')->exists());
    }

    // ───────────────────────────── PX-19 / 18 — FG, close, produced quantity

    public function test_close_needs_finished_goods_received_or_a_reason_and_receiving_works_after_completion(): void
    {
        $order = $this->createOrder();
        $order->forceFill(['status' => 'completed', 'quantity_produced' => 10])->save();
        $svc = app(ProductionOrderService::class);

        try {
            $svc->close($order->id, $this->user->id);
            $this->fail('closing without finished goods must be refused');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('finished goods', $e->getMessage());
        }

        $policy = new ProductionOrderPolicy();
        $this->assertTrue($policy->receiveFg($this->user, $order->fresh()), 'completed order with FG still pending can receive');

        ProductionOrderReceipt::create(['tenant_id' => $this->tenant->id, 'production_order_id' => $order->id, 'product_id' => $order->product_id, 'warehouse_id' => $this->wh->id, 'quantity_received' => 4, 'quality_status' => 'passed', 'received_at' => now()]);
        try {
            $svc->close($order->id, $this->user->id);
            $this->fail('6 units are still not received');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('6', $e->getMessage());
        }

        $svc->close($order->id, $this->user->id, 'Customer cancelled the remaining 6');
        $this->assertSame('closed', $order->fresh()->status);
        $this->assertTrue(DB::table('production_event_timelines')->where('production_order_id', $order->id)->where('event_type', 'Closed Without Finished Goods')->exists());
    }

    // ───────────────────────────── PX-20 — cancel returns issued material

    public function test_cancel_returns_issued_material_closes_slip_and_wip(): void
    {
        $order = $this->createOrder();
        $before = $this->stock('M1');
        $this->issue($order, 'M1', 6);
        $this->assertEqualsWithDelta($before - 6, $this->stock('M1'), 1e-6);

        app(ProductionOrderService::class)->cancel($order->id, $this->user->id);

        $this->assertEqualsWithDelta($before, $this->stock('M1'), 1e-6, 'issued, unconsumed material is back in stock');
        $res = ProductionOrderReservation::where('production_order_id', $order->id)->where('product_id', $this->p['M1']->id)->first();
        $this->assertEqualsWithDelta(0.0, (float) $res->quantity_issued, 1e-9);
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(0, DB::table('production_requisition_slips')->where('production_order_id', $order->id)->where('status', '!=', 'cancelled')->count());
    }

    // ───────────────────────────── PX-10 — opening the page does not write readiness

    public function test_draft_order_gets_no_readiness_status_and_page_view_writes_nothing(): void
    {
        $order = $this->createOrder();
        $stamp = ProductionOrderOperation::where('production_order_id', $order->id)->pluck('updated_at', 'id')->map(fn ($d) => (string) $d)->all();

        $this->travel(5)->minutes();
        app(ProductionOrderService::class)->reconcileOperationReadiness($order);   // draft: no-op
        $this->withHeaders(['X-Tenant' => 'px'])->get(route('production.orders.show', $order->id))->assertOk();

        $after = ProductionOrderOperation::where('production_order_id', $order->id)->pluck('updated_at', 'id')->map(fn ($d) => (string) $d)->all();
        $this->assertSame($stamp, $after, 'no operation row was touched by viewing the draft order');
    }
}
