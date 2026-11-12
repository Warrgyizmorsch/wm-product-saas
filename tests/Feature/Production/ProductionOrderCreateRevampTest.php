<?php

namespace Tests\Feature\Production;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\ProductWarehouseStock;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionOrderReservation;
use App\Domains\Production\Models\ProductionRequisitionSlipItem;
use App\Domains\Production\Models\Routing;
use App\Domains\Production\Models\RoutingOperation;
use App\Domains\Production\Models\RoutingOperationMaterial;
use App\Domains\Production\Services\ProductionOrderEngineeringService;
use App\Domains\Production\Services\ProductionOrderService;
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
 * PO-IMPL-07 — Production Order Create revamp (PO-01 .. PO-12), built on the decisions doc
 * "Example 2 — 3-level multi-branch" (B1 → B2, B3, B4; B2 → B5; shared M2 / M3), PO qty 10.
 */
class ProductionOrderCreateRevampTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $user;
    private Uom $uom;
    private int $branchA;
    private int $branchB;
    /** @var array<string, Product> */
    private array $p = [];
    /** @var array<string, ProductionBom> */
    private array $bom = [];
    /** @var array<string, RoutingOperation> */
    private array $op = [];
    /** @var array<string, RoutingOperationMaterial> */
    private array $alloc = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);

        $this->tenant = Tenant::create(['name' => 'PO', 'slug' => 'po', 'status' => 'active', 'plan' => 'enterprise']);
        $this->user = User::create(['tenant_id' => $this->tenant->id, 'name' => 'Planner', 'email' => 'po@example.test', 'password' => bcrypt('x'), 'role' => 'admin']);
        $role = Role::query()->whereNull('tenant_id')->where('slug', 'production_manager')->firstOrFail();
        UserRole::create(['user_id' => $this->user->id, 'role_id' => $role->id, 'tenant_id' => $this->tenant->id]);
        $this->actingAs($this->user);
        app(\App\Core\Tenant\TenantContext::class)->setTenant($this->tenant);

        $this->branchA = $this->branch('BR-A');
        $this->branchB = $this->branch('BR-B');
        $this->uom = Uom::create(['tenant_id' => $this->tenant->id, 'name' => 'Pieces', 'code' => 'PCS']);

        foreach (['B1' => 'finished_good', 'B2' => 'semi_finished', 'B3' => 'semi_finished', 'B4' => 'semi_finished', 'B5' => 'semi_finished'] as $code => $type) {
            $this->p[$code] = $this->product($code, $type, 'manufacture');
        }
        foreach (['M1', 'M2', 'M2ALT', 'M3', 'M4', 'M5', 'M6', 'M7', 'M8'] as $code) {
            $this->p[$code] = $this->product($code, 'raw_material', 'trade');
        }

        // Child BOMs first (B5 → B2; B3; B4), then the parent B1
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

        // Branch A stock (doc Example 2) spread over two standard warehouses, M1 partly reserved
        $whA1 = $this->warehouse('WA1', $this->branchA);
        $whA2 = $this->warehouse('WA2', $this->branchA);
        foreach (['B2' => 4, 'B3' => 12, 'B4' => 2, 'B5' => 2, 'M2' => 20, 'M3' => 11, 'M4' => 8, 'M5' => 5, 'M6' => 20, 'M7' => 20, 'M8' => 6] as $code => $qty) {
            $this->stock($whA1, $code, $qty);
        }
        $this->stock($whA2, 'M2', 10);                 // M2 = 20 + 10 = 30 in branch A
        $this->stock($whA2, 'M1', 20, 5);              // M1 = 20 - 5 reserved = 15
        // Never counted: other branch, subcontractor and inactive warehouses
        $this->stock($this->warehouse('WB1', $this->branchB), 'M2', 500);
        $this->stock($this->warehouse('WSUB', $this->branchA, Warehouse::TYPE_SUBCONTRACTOR), 'M2', 500);
        $this->stock($this->warehouse('WOFF', $this->branchA, Warehouse::TYPE_STANDARD, 'inactive'), 'M2', 500);
    }

    // ───────────────────────────── fixtures

    private function branch(string $code): int
    {
        $row = ['name' => $code, 'code' => $code . '-' . $this->tenant->id, 'created_at' => now(), 'updated_at' => now()];
        if (Schema::hasColumn('branches', 'tenant_id')) {
            $row['tenant_id'] = $this->tenant->id;
        }

        return (int) DB::table('branches')->insertGetId($row);
    }

    private function product(string $code, string $type, string $method): Product
    {
        return Product::create(['tenant_id' => $this->tenant->id, 'name' => $code, 'sku' => $code, 'type' => $type, 'status' => 'active', 'uom_id' => $this->uom->id, 'unit_cost' => 1, 'supplier_method' => $method]);
    }

    private function warehouse(string $code, int $branchId, string $type = Warehouse::TYPE_STANDARD, string $status = 'active'): Warehouse
    {
        return Warehouse::withoutGlobalScopes()->create(['tenant_id' => $this->tenant->id, 'branch_id' => $branchId, 'name' => $code, 'code' => $code, 'type' => $type, 'status' => $status]);
    }

    private function stock(Warehouse $wh, string $code, float $qty, float $reserved = 0): void
    {
        ProductWarehouseStock::withoutGlobalScopes()->create(['tenant_id' => $this->tenant->id, 'branch_id' => $wh->branch_id, 'product_id' => $this->p[$code]->id, 'warehouse_id' => $wh->id, 'quantity' => $qty, 'reserved_qty' => $reserved, 'available_qty' => $qty - $reserved]);
    }

    /**
     * @param array<int, array{0:string,1:int,2:?string,3:?int,4:array}> $ops [opNumber, seq, replacesOp, priority, materials[[code, qty, childBomOf?, altOf?]]]
     */
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

    private function web()
    {
        return $this->withHeaders(['X-Tenant' => 'po']);
    }

    private function api(): array
    {
        return ['Accept' => 'application/json', 'X-Tenant' => 'po', 'X-API-SECRET' => 'wm-production-secret-test-key', 'Authorization' => 'Bearer ' . $this->user->createToken('t')->plainTextToken];
    }

    private function orderPayload(array $over = []): array
    {
        return array_merge([
            'product_id' => $this->p['B1']->id, 'quantity_ordered' => 10, 'branch_id' => $this->branchA,
            'start_date' => now()->toDateString(), 'end_date' => now()->addDays(5)->toDateString(),
        ], $over);
    }

    /** Default UI selection: primary op per stage + primary material per slot of those ops. */
    private function defaults(): array
    {
        $ops = ['B1.OP-10', 'B1.OP-20', 'B1.OP-30'];
        $mats = ['B1.OP-10.M1', 'B1.OP-10.B2', 'B1.OP-20.B3', 'B1.OP-20.M2', 'B1.OP-30.B4', 'B1.OP-30.M3'];

        return [
            'engineering_selection_submitted' => 1,
            'selected_operation_ids' => array_map(fn ($k) => $this->op[$k]->id, $ops),
            'selected_material_ids' => array_map(fn ($k) => $this->alloc[$k]->id, $mats),
        ];
    }

    private function reserved(ProductionOrder $order, string $code): ?float
    {
        $r = ProductionOrderReservation::where('production_order_id', $order->id)->where('product_id', $this->p[$code]->id)->get();

        return $r->isEmpty() ? null : (float) $r->sum('quantity_planned');
    }

    // ───────────────────────────── PO-04 / PO-08: math

    public function test_branch_stock_pools_standard_warehouses_of_the_branch_only(): void
    {
        $svc = app(ProductionOrderEngineeringService::class);
        $map = $svc->getBranchAvailableStockMap($this->tenant->id, $this->branchA, [$this->p['M2']->id, $this->p['M1']->id, $this->p['M9'] ?? $this->p['M8']->id]);

        $this->assertEqualsWithDelta(30.0, $map[$this->p['M2']->id], 1e-9, 'other branch / subcontractor / inactive warehouses excluded');
        $this->assertEqualsWithDelta(15.0, $map[$this->p['M1']->id], 1e-9, 'reserved_qty deducted');
        $this->assertEqualsWithDelta(500.0, $svc->getBranchAvailableStockMap($this->tenant->id, $this->branchB, [$this->p['M2']->id])[$this->p['M2']->id], 1e-9);
    }

    public function test_workspace_payload_defaults_and_doc_example_rollup(): void
    {
        $payload = app(ProductionOrderEngineeringService::class)->buildInteractiveWorkspacePayload($this->p['B1'], $this->tenant->id, $this->branchA, 10);

        $this->assertTrue($payload['validation']['can_create_order'], implode(' | ', $payload['validation']['blocking_reasons']));
        $this->assertSame('BOM-B1', $payload['bom']['bom_number']);
        $this->assertSame('RT-B1', $payload['routing']['routing_number']);
        $stages = $payload['parent_workspace']['stages'];
        $this->assertCount(3, $stages, 'OP-25 is an alternative inside the OP-20 stage');
        $this->assertSame($this->op['B1.OP-20']->id, $stages[1]['default_selected_operation_id']);
        $this->assertTrue($stages[1]['has_alternative_ops']);
        $alt = collect($stages[1]['operations'])->firstWhere('id', $this->op['B1.OP-25']->id);
        $this->assertNotEmpty($alt['default_selected_material_ids'], 'alternative op materials are pre-selected too');
        $this->assertArrayHasKey($this->bom['B5']->id, $payload['child_boms'], 'grandchild BOM preloaded');
        $b2ops = collect($payload['child_boms'][$this->bom['B2']->id]['operations'])->pluck('operation_number')->all();
        $this->assertSame(['OP-10', 'OP-20'], $b2ops, 'child BOM: primary operations only');

        $rows = collect($payload['default_preview']['rows'])->keyBy(fn ($r) => array_search($r['product_id'], array_map(fn ($p) => $p->id, $this->p), true));
        $expect = ['M1' => 10, 'B2' => 10, 'B3' => 10, 'M2' => 50, 'B4' => 10, 'M3' => 14, 'B5' => 6, 'M4' => 6, 'M8' => 8, 'M5' => 8];
        foreach ($expect as $code => $total) {
            $this->assertEqualsWithDelta($total, $rows[$code]['total_req'], 1e-6, "{$code} total");
        }
        $this->assertEqualsWithDelta(20.0, $rows['M2']['shortage'], 1e-6);
        $this->assertEqualsWithDelta(6.0, $rows['B2']['to_produce'], 1e-6);
        $this->assertEqualsWithDelta(0.0, $rows['B3']['to_produce'], 1e-6, 'B3 covered by stock — not exploded');
        $this->assertEqualsWithDelta(4.0, $rows['B5']['to_produce'], 1e-6);
        $this->assertFalse($rows->has('M6'), 'B3 is not exploded, so M6 / M7 stay out');
        $this->assertFalse($rows->has('M7'));
        $this->assertFalse($rows->has('M2ALT'), 'deselected alternatives are hidden');
    }

    // ───────────────────────────── PO-06 / PO-12: zero-edit submit

    public function test_zero_edit_web_submit_snapshots_only_selected_and_sums_shared_materials(): void
    {
        $this->web()->post(route('production.orders.store'), $this->orderPayload($this->defaults()))->assertSessionHasNoErrors();
        $order = ProductionOrder::latest('id')->firstOrFail();

        $this->assertSame($this->bom['B1']->id, (int) $order->bom_id);
        $this->assertSame($this->bom['B1']->routing_id, (int) $order->routing_id);

        $ops = ProductionOrderOperation::where('production_order_id', $order->id)->get();
        $this->assertCount(7, $ops, 'B1 OP-10/20/30 + B2 OP-10/20 + B4 OP-10 + B5 OP-10');
        $this->assertFalse($ops->contains('routing_operation_id', $this->op['B1.OP-25']->id), 'deselected alternative op not snapshotted');
        $this->assertFalse($ops->contains('routing_operation_id', $this->op['B2.OP-15']->id), 'child BOM alternative op not snapshotted');
        $this->assertFalse($ops->contains('source_bom_id', $this->bom['B3']->id), 'B3 covered by stock');
        $b5 = $ops->firstWhere('source_bom_id', $this->bom['B5']->id);
        $this->assertSame(3, (int) $b5->bom_level);
        $this->assertEqualsWithDelta(4.0, (float) $b5->target_produced_qty, 1e-6);
        $this->assertTrue((bool) $b5->is_intermediate);

        $op20 = $ops->firstWhere('routing_operation_id', $this->op['B1.OP-20']->id);
        $this->assertEqualsCanonicalizing([$this->alloc['B1.OP-20.B3']->id, $this->alloc['B1.OP-20.M2']->id], $op20->selected_routing_material_ids);
        $this->assertEqualsCanonicalizing([$this->p['B3']->id, $this->p['M2']->id], $op20->getExpectedComponents()->pluck('material_id')->all(), 'M2-ALT is not expected on the shop floor');

        foreach (['M1' => 10, 'M2' => 50, 'M3' => 14, 'M4' => 6, 'M5' => 8, 'M8' => 8, 'B2' => 4, 'B3' => 10, 'B4' => 2, 'B5' => 2] as $code => $qty) {
            $this->assertEqualsWithDelta($qty, $this->reserved($order, $code), 1e-4, "reservation {$code}");
        }
        $this->assertSame(1, ProductionOrderReservation::where('production_order_id', $order->id)->where('product_id', $this->p['M2']->id)->count(), 'one combined reservation per product');
        $this->assertNull($this->reserved($order, 'M6'));
        $this->assertNull($this->reserved($order, 'M2ALT'));

        $slip = ProductionRequisitionSlipItem::whereHas('slip', fn ($q) => $q->where('production_order_id', $order->id))->get()->keyBy('product_id');
        $this->assertEqualsWithDelta(50.0, (float) $slip[$this->p['M2']->id]->quantity_planned, 1e-4);
        $this->assertEqualsWithDelta(14.0, (float) $slip[$this->p['M3']->id]->quantity_planned, 1e-4);
        $wh = Warehouse::withoutGlobalScopes()->find($slip[$this->p['M2']->id]->warehouse_id);
        $this->assertSame($this->branchA, (int) $wh->branch_id);
        $this->assertSame(Warehouse::TYPE_STANDARD, $wh->type);
    }

    public function test_switching_to_alternative_material_and_operation(): void
    {
        // Alternative material M2-ALT on OP-20
        $sel = $this->defaults();
        $sel['selected_material_ids'] = array_values(array_diff($sel['selected_material_ids'], [$this->alloc['B1.OP-20.M2']->id]));
        $sel['selected_material_ids'][] = $this->alloc['B1.OP-20.M2ALT']->id;
        $this->web()->post(route('production.orders.store'), $this->orderPayload($sel))->assertSessionHasNoErrors();
        $order = ProductionOrder::latest('id')->firstOrFail();
        $this->assertEqualsWithDelta(40.0, $this->reserved($order, 'M2'), 1e-4, 'parent M2 deselected');
        $this->assertEqualsWithDelta(10.0, $this->reserved($order, 'M2ALT'), 1e-4);

        // Alternative operation OP-25 (its own primary materials)
        $sel = $this->defaults();
        $sel['selected_operation_ids'] = [$this->op['B1.OP-10']->id, $this->op['B1.OP-25']->id, $this->op['B1.OP-30']->id];
        $sel['selected_material_ids'] = array_map(fn ($k) => $this->alloc[$k]->id, ['B1.OP-10.M1', 'B1.OP-10.B2', 'B1.OP-25.B3', 'B1.OP-25.M2', 'B1.OP-30.B4', 'B1.OP-30.M3']);
        $this->web()->post(route('production.orders.store'), $this->orderPayload($sel))->assertSessionHasNoErrors();
        $order = ProductionOrder::latest('id')->firstOrFail();
        $ops = ProductionOrderOperation::where('production_order_id', $order->id)->pluck('routing_operation_id');
        $this->assertTrue($ops->contains($this->op['B1.OP-25']->id));
        $this->assertFalse($ops->contains($this->op['B1.OP-20']->id));
        $this->assertEqualsWithDelta(55.0, $this->reserved($order, 'M2'), 1e-4, '15 (1.5 × 10) + 40 from child BOMs');
    }

    // ───────────────────────────── PO-07: completeness

    public function test_incomplete_or_invalid_selection_is_rejected_on_web_and_api(): void
    {
        $base = $this->defaults();
        $cases = [
            'nothing submitted' => ['engineering_selection_submitted' => 1],
            'missing stage' => array_merge($base, ['selected_operation_ids' => array_slice($base['selected_operation_ids'], 0, 2)]),
            'two ops in a stage' => array_merge($base, ['selected_operation_ids' => array_merge($base['selected_operation_ids'], [$this->op['B1.OP-25']->id])]),
            'missing material slot' => array_merge($base, ['selected_material_ids' => array_values(array_diff($base['selected_material_ids'], [$this->alloc['B1.OP-30.M3']->id]))]),
            'two materials in a slot' => array_merge($base, ['selected_material_ids' => array_merge($base['selected_material_ids'], [$this->alloc['B1.OP-20.M2ALT']->id])]),
            'material of unselected op' => array_merge($base, ['selected_material_ids' => array_merge($base['selected_material_ids'], [$this->alloc['B1.OP-25.M2']->id])]),
        ];
        foreach ($cases as $name => $sel) {
            $this->web()->post(route('production.orders.store'), $this->orderPayload($sel))->assertSessionHasErrors();
            $this->assertSame(0, ProductionOrder::count(), $name);
        }

        $this->withHeaders($this->api())->postJson('/api/v1/production/orders', $this->orderPayload(['selected_operation_ids' => []]))
            ->assertStatus(422)->assertJsonValidationErrors(['selected_operation_ids']);
        $this->assertSame(0, ProductionOrder::count());
    }

    public function test_headless_api_without_selection_defaults_to_primary(): void
    {
        $res = $this->withHeaders($this->api())->postJson('/api/v1/production/orders', $this->orderPayload());
        $res->assertStatus(201);
        $order = ProductionOrder::latest('id')->firstOrFail();
        $this->assertSame(7, ProductionOrderOperation::where('production_order_id', $order->id)->count());
        $this->assertEqualsWithDelta(50.0, $this->reserved($order, 'M2'), 1e-4);
        $ops = collect($res->json('data.operations'));
        $this->assertTrue($ops->contains(fn ($o) => $o['bom_level'] === 3 && $o['is_intermediate'] === true), 'API exposes snapshot fields');
        $this->assertNotNull($ops->first()['selected_routing_material_ids']);
    }

    // ───────────────────────────── PO-01 / PO-02

    public function test_create_page_and_engineering_status_endpoints(): void
    {
        $html = $this->web()->get(route('production.orders.create'))->assertOk()->getContent();
        $this->assertStringContainsString('id="bom_display"', $html);
        $this->assertStringContainsString('id="routing_display"', $html);
        $this->assertStringNotContainsString('id="bom_select"', $html);
        $this->assertStringNotContainsString('id="routing_select"', $html);

        $this->web()->getJson(route('production.orders.engineering-status', ['product_id' => $this->p['B1']->id, 'branch_id' => $this->branchA, 'quantity' => 10]))
            ->assertOk()->assertJsonPath('bom.bom_number', 'BOM-B1')->assertJsonPath('validation.can_create_order', true);
        $this->withHeaders($this->api())->getJson('/api/v1/production/orders/engineering-status?product_id=' . $this->p['M1']->id)
            ->assertOk()->assertJsonPath('data.validation.requires_engineering', false);
    }

    public function test_bought_product_needs_no_bom_or_routing(): void
    {
        $this->web()->post(route('production.orders.store'), $this->orderPayload(['product_id' => $this->p['M1']->id]))->assertSessionHasNoErrors();
        $order = ProductionOrder::latest('id')->firstOrFail();
        $this->assertNull($order->bom_id);
        $this->assertNull($order->routing_id);
        $this->assertSame(0, ProductionOrderReservation::where('production_order_id', $order->id)->count());
        $this->assertSame(0, ProductionOrderOperation::where('production_order_id', $order->id)->count());

        $this->withHeaders($this->api())->postJson('/api/v1/production/orders', $this->orderPayload(['product_id' => $this->p['M3']->id]))->assertStatus(201);
    }

    public function test_manufactured_product_is_blocked_when_engineering_is_not_usable(): void
    {
        $post = fn (array $over = []) => $this->web()->post(route('production.orders.store'), $this->orderPayload($over));
        $errorsText = fn () => collect(session('errors')?->all() ?? [])->implode(' ');

        // No BOM at all
        $lonely = $this->product('LONELY', 'finished_good', 'manufacture');
        $post(['product_id' => $lonely->id])->assertSessionHasErrors('bom_id');
        $this->assertStringContainsString('No Manufacturing or Subcontracting BOM', $errorsText());

        // Explicit mismatched routing
        $post(['routing_id' => $this->bom['B2']->routing_id])->assertSessionHasErrors('routing_id');

        // Routing costs not calculated
        $this->op['B1.OP-30']->update(['cost_per_unit' => null]);
        $post()->assertSessionHasErrors('routing_id');
        $this->assertStringContainsString('costs are not calculated', $errorsText());
        $this->op['B1.OP-30']->update(['cost_per_unit' => 1]);

        // Cancelled sub-BOM (BM-08)
        $this->bom['B4']->update(['status' => 'cancelled']);
        $post()->assertSessionHasErrors('bom_id');
        $this->bom['B4']->update(['status' => 'approved']);

        // Child BOM routing inactive
        Routing::whereKey($this->bom['B5']->routing_id)->update(['status' => Routing::STATUS_HISTORICAL]);
        $post()->assertSessionHasErrors('bom_id');
        Routing::whereKey($this->bom['B5']->routing_id)->update(['status' => Routing::STATUS_ACTIVE]);

        // Parent routing inactive
        Routing::whereKey($this->bom['B1']->routing_id)->update(['status' => Routing::STATUS_HISTORICAL]);
        $post()->assertSessionHasErrors('routing_id');
        Routing::whereKey($this->bom['B1']->routing_id)->update(['status' => Routing::STATUS_ACTIVE]);

        // Future-dated BOM
        $this->bom['B1']->update(['effective_date' => now()->addDays(3)->toDateString()]);
        $post()->assertSessionHasErrors('bom_id');
        $this->assertStringContainsString('starts on', $errorsText());

        // Draft BOM
        $this->bom['B1']->update(['effective_date' => now()->toDateString(), 'status' => 'draft']);
        $post()->assertSessionHasErrors('bom_id');
        $this->assertStringContainsString('is draft', $errorsText());

        $this->assertSame(0, ProductionOrder::count());

        // Service guard too (no FormRequest)
        $this->expectException(\InvalidArgumentException::class);
        app(ProductionOrderService::class)->createDirect($this->orderPayload(), $this->tenant->id, $this->user->id);
    }

    public function test_active_scopes_accept_null_start_dates(): void
    {
        Routing::whereKey($this->bom['B1']->routing_id)->update(['effective_from' => null]);

        // production_boms.effective_date is NOT NULL in the schema — only the Routing side can be NULL
        $this->assertTrue(ProductionBom::withoutGlobalScopes()->active()->whereKey($this->bom['B1']->id)->exists());
        $this->assertTrue(Routing::withoutGlobalScopes()->active()->whereKey($this->bom['B1']->routing_id)->exists());
        $this->assertSame($this->bom['B1']->id, app(ProductionOrderEngineeringService::class)->resolveActiveBom($this->p['B1'], $this->tenant->id)?->id);
    }
}
